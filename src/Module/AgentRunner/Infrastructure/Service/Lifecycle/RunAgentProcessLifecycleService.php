<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Lifecycle;

use Override;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\HttpsProxyBridge;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\ProcessLivenessWatcher;

/**
 * Общий жизненный цикл процесса AI-агента для раннеров Codex/Pi.
 *
 * Единственная точка изменения runtime-поведения, общего для обоих раннеров
 * (замечание №1 ревью PR #356): таймауты, прокси-мост, буферизация stdout,
 * stderr-tail, остановка процесса и моста. Раннеры делегируют сюда свой run()
 * через callbacks {@see RunAgentProcessLifecycleServiceInterface::run()},
 * сохраняя runner-specific `buildCommand()` и `buildResult()` hook.
 *
 * Design-решение (DS Архитектора, TASK-techdebt-agent-runner-lifecycle-helper):
 * Infrastructure-сервис с callback-контрактом к JSONL-парсерам — без общего
 * parser-interface и без Helper/trait (здесь есть I/O: Symfony Process,
 * getenv, старт/стоп моста).
 */
final readonly class RunAgentProcessLifecycleService implements RunAgentProcessLifecycleServiceInterface
{
    /** @var int Максимальный stderr-tail для AgentResultVo ошибки процесса */
    private const int ERROR_OUTPUT_TAIL_BYTES = 65536;

    public function __construct(
        private ProcessLivenessWatcher $livenessWatcher,
    ) {
    }

    #[Override]
    public function run(
        AgentRunRequestVo $request,
        string $runnerName,
        callable $buildCommand,
        callable $resetParser,
        callable $feedParserLine,
        callable $buildResult,
    ): AgentResultVo {
        $hardCap = $this->livenessWatcher->resolveHardCap($request->getTimeout());
        $process = $this->createConfiguredProcess($request, $hardCap, $buildCommand);

        // Ошибки подготовки прокси-окружения (запуск моста, env-настройка) не должны
        // бросать исключение из run() — возвращаем AgentResultVo::createError()
        // по контракту AgentRunnerInterface.
        try {
            $bridge = $this->attachProxyEnvironment($process);
        } catch (\Throwable $e) {
            return AgentResultVo::createError(
                errorMessage: sprintf('Failed to prepare proxy environment: %s', $e->getMessage()),
            );
        }

        $resetParser();
        $stdoutBuffer = '';
        $errorOutput = '';

        $outputHandler = function (string $type, string $chunk) use ($process, &$stdoutBuffer, &$errorOutput, $feedParserLine): void {
            if ($type === Process::OUT) {
                $this->bufferStdoutChunk($chunk, $stdoutBuffer, $feedParserLine);
                $process->clearOutput();

                return;
            }

            $errorOutput = $this->appendErrorOutputTail($errorOutput, $chunk);
            $process->clearErrorOutput();
        };

        try {
            $process->start($outputHandler);
            $completed = $this->livenessWatcher->waitFor($process);
            $this->flushStdoutBuffer($stdoutBuffer, $feedParserLine);
            if (!$completed) {
                // Процесс простаивал (idle) — нет прогресса CPU/IO дольше порога.
                // Возвращаем transient-error (timedOut) → retry подхватит.
                return AgentResultVo::createError(
                    errorMessage: sprintf(
                        'Agent idle: no CPU/IO progress for %d seconds.',
                        $this->livenessWatcher->getIdleThreshold(),
                    ),
                    timedOut: true,
                );
            }
        } catch (ProcessTimedOutException) {
            return AgentResultVo::createError(
                errorMessage: sprintf('Agent timed out after %d seconds (hard cap).', $hardCap),
                timedOut: true,
            );
        } catch (ProcessSignaledException $e) {
            return AgentResultVo::createError(
                errorMessage: sprintf('%s process terminated by signal %d.', $runnerName, $e->getSignal()),
                exitCode: 128 + $e->getSignal(),
            );
        } finally {
            $this->stopProcessAndBridge($process, $bridge);
        }

        return $buildResult($process, $errorOutput);
    }

    #[Override]
    public function buildProcessEnv(array $currentEnv): array
    {
        $codexProxy = $currentEnv['CODEX_HTTP_PROXY'] ?? null;

        if ($codexProxy !== null && $codexProxy !== '') {
            // Для HTTPS-прокси НЕ подменяем HTTPS_PROXY здесь — мост это сделает в run()
            if (!str_starts_with($codexProxy, 'https://')) {
                $currentEnv['HTTPS_PROXY'] = $codexProxy;
            }
        }

        return $currentEnv;
    }

    #[Override]
    public function createBridgeIfNeeded(): ?HttpsProxyBridge
    {
        $codexProxy = getenv('CODEX_HTTP_PROXY');
        if ($codexProxy === false || $codexProxy === '') {
            return null;
        }

        if (!str_starts_with($codexProxy, 'https://')) {
            return null;
        }

        $bridge = new HttpsProxyBridge($codexProxy);
        $bridge->start();

        return $bridge;
    }

    #[Override]
    public function buildUserPrompt(AgentRunRequestVo $request): string
    {
        $parts = [];

        if ($request->getPreviousContext() !== null) {
            $parts[] = $request->getPreviousContext();
        }

        $parts[] = sprintf('[Задача]: %s', $request->getTask());

        return implode("\n\n", $parts);
    }

    #[Override]
    public function resolveSystemPromptPath(AgentRunRequestVo $request): ?string
    {
        $systemPrompt = $request->getSystemPrompt();
        if ($systemPrompt === null) {
            return null;
        }

        if (file_exists($systemPrompt) && is_file($systemPrompt)) {
            return $systemPrompt;
        }

        return null;
    }

    /**
     * Создаёт Symfony Process с командой, hard-cap таймаутом и рабочей директорией.
     *
     * Liveness-adaptive timeout: Symfony setTimeout = абсолютный hard cap (fallback),
     * poll-loop waitFor() убивает раньше при простое (idle). Активный процесс (модель
     * рассуждает/стримит) дорабатывает до cap, не обрезаясь по жёсткому таймеру.
     *
     * @param AgentRunRequestVo $request запрос на запуск агента
     * @param int $hardCap абсолютный hard cap в секундах (уже резолвнут через resolveHardCap)
     * @param callable(AgentRunRequestVo): list<string> $buildCommand
     */
    private function createConfiguredProcess(AgentRunRequestVo $request, int $hardCap, callable $buildCommand): Process
    {
        $process = new Process($buildCommand($request));
        $process->setTimeout($hardCap);

        if ($request->getWorkingDir() !== null) {
            $process->setWorkingDirectory($request->getWorkingDir());
        }

        return $process;
    }

    /**
     * Создаёт HTTPS-прокси-мост (если нужен) и настраивает env Symfony Process.
     *
     * HTTPS-прокси мост: если CODEX_HTTP_PROXY содержит https:// схему,
     * запускается локальный HTTP-прокси-мост для пересылки через TLS.
     *
     * Передача HTTP-прокси через env-переменные:
     * CODEX_HTTP_PROXY (приоритет) подменяет HTTPS_PROXY для процесса раннера.
     * Если CODEX_HTTP_PROXY не задан — Process наследует env родителя (HTTPS_PROXY, HTTP_PROXY).
     *
     * @return HttpsProxyBridge|null мост или null, если прокси не требуется
     *
     * @throws RuntimeException при ошибке запуска моста; любые сбои подготовки
     *                         прокси-окружения перехватываются в run()
     */
    private function attachProxyEnvironment(Process $process): ?HttpsProxyBridge
    {
        $bridge = $this->createBridgeIfNeeded();

        $codexProxy = getenv('CODEX_HTTP_PROXY');
        if ($codexProxy !== false && $codexProxy !== '') {
            $processEnv = $this->buildProcessEnv(getenv());

            // Если мост запущен — подменяем HTTPS_PROXY на локальный URL моста
            if ($bridge !== null) {
                $processEnv['HTTPS_PROXY'] = $bridge->getLocalProxyUrl();
                $processEnv['HTTP_PROXY'] = $bridge->getLocalProxyUrl();
            }

            $process->setEnv($processEnv);
        }

        return $bridge;
    }

    /**
     * Гарантированно останавливает agent-процесс и orphan-процесс моста.
     *
     * При fail-fast ошибке liveness-пробы не оставляем agent process
     * жить до недетерминированного вызова Symfony destructor/GC.
     */
    private function stopProcessAndBridge(Process $process, ?HttpsProxyBridge $bridge): void
    {
        try {
            if ($process->isRunning()) {
                $process->stop(0);
            }
        } finally {
            $bridge?->stop();
        }
    }

    /**
     * Буферизует stdout-чанк до переводов строк и отдаёт полные JSONL-строки в parser.
     *
     * @param callable(string): void $feedParserLine
     */
    private function bufferStdoutChunk(string $chunk, string &$stdoutBuffer, callable $feedParserLine): void
    {
        $stdoutBuffer .= $chunk;
        $newlinePosition = strpos($stdoutBuffer, "\n");

        while ($newlinePosition !== false) {
            $line = substr($stdoutBuffer, 0, $newlinePosition);
            $stdoutBuffer = substr($stdoutBuffer, $newlinePosition + 1);
            $feedParserLine($line);
            $newlinePosition = strpos($stdoutBuffer, "\n");
        }
    }

    /**
     * Отдаёт parser последнюю строку без завершающего перевода строки.
     *
     * @param callable(string): void $feedParserLine
     */
    private function flushStdoutBuffer(string &$stdoutBuffer, callable $feedParserLine): void
    {
        if ($stdoutBuffer === '') {
            return;
        }

        $feedParserLine($stdoutBuffer);
        $stdoutBuffer = '';
    }

    private function appendErrorOutputTail(string $currentOutput, string $chunk): string
    {
        $currentOutput .= $chunk;
        if (strlen($currentOutput) <= self::ERROR_OUTPUT_TAIL_BYTES) {
            return $currentOutput;
        }

        return substr($currentOutput, -self::ERROR_OUTPUT_TAIL_BYTES);
    }
}
