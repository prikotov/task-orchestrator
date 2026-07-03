<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Pi;

use InvalidArgumentException;
use Override;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\PiAgentRunnerServiceInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\HttpsProxyBridge;

/**
 * Реализация AgentRunnerInterface для pi CLI.
 *
 * Запускает pi через Symfony Process.
 * Если в AgentRunRequestVo задан command — используется он как базовая команда.
 * Иначе — стандартная: `pi --mode json -p --no-session`.
 * Пути к файлам промптов (--system-prompt, --append-system-prompt) передаются
 * как абсолютные пути — Pi читает файлы самостоятельно через existsSync-эвристику.
 * Значения с префиксом @ разрешаются как пути к файлам (содержимое подставляется inline).
 *
 * Поддержка HTTPS-прокси через HttpsProxyBridge (переиспользуется из Codex-раннера):
 * если CODEX_HTTP_PROXY содержит https:// схему, автоматически запускается
 * локальный HTTP-прокси-мост, пересылающий CONNECT-запросы через TLS.
 * Симметрично CodexAgentRunnerService, чтобы любой pi-профиль, ходящий к OpenAI,
 * работал через прокси без ручных действий.
 */
final readonly class PiAgentRunnerService implements PiAgentRunnerServiceInterface
{
    /** @var int Максимальный stderr-tail для AgentResultVo ошибки процесса */
    private const int ERROR_OUTPUT_TAIL_BYTES = 65536;

    public function __construct(
        private PiJsonlParser $parser,
    ) {
    }

    #[Override]
    public function getName(): string
    {
        return 'pi';
    }

    #[Override]
    public function isAvailable(): bool
    {
        $process = new Process(['which', 'pi']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Строит массив CLI-команды для запуска pi по AgentRunRequestVo.
     *
     * Последовательно добавляет: базовую команду, runner args,
     * model, tools, system prompt, флаг -nc и user prompt.
     *
     * @param AgentRunRequestVo $request запрос на запуск агента
     *
     * @return list<string> готовый массив аргументов для Symfony Process
     */
    #[Override]
    public function buildCommand(AgentRunRequestVo $request): array
    {
        $command = $request->getCommand();

        if ($command === []) {
            $command = ['pi', '--mode', 'json', '-p', '--no-session'];
        } elseif ($command[0] !== 'pi' && !str_contains($command[0], 'pi')) {
            throw new InvalidArgumentException(sprintf(
                'AgentRunRequestVo::$command must be either empty (runner default) or a full CLI command starting with an executable. '
                . 'Got: %s',
                implode(' ', $command),
            ));
        }

        // Доп. аргументы runner'а (prompt-файлы от dynamic loop)
        foreach ($request->getRunnerArgs() as $arg) {
            $command[] = $arg;
        }

        // Разрешение @file → содержимое файла
        $command = $this->resolveCommandFiles($command, $request->getWorkingDir());

        // Model — только если не задан в command
        if ($request->getModel() !== null && !in_array('--model', $command, true)) {
            $command[] = '--model';
            $command[] = $request->getModel();
        }

        // Tools — только если не задан в command
        if ($request->getTools() === '' && !in_array('--no-tools', $command, true)) {
            $command[] = '--no-tools';
        } elseif ($request->getTools() !== null && !in_array('--tools', $command, true)) {
            $command[] = '--tools';
            $command[] = $request->getTools();
        }

        // System prompt — только если не задан в command
        if ($request->getSystemPrompt() !== null && !in_array('--system-prompt', $command, true)) {
            $command[] = '--system-prompt';
            $command[] = $request->getSystemPrompt();
        }

        // No context files — отключить загрузку AGENTS.md / CLAUDE.md
        if ($request->hasNoContextFiles() && !in_array('-nc', $command, true) && !in_array('-no-context-files', $command, true)) {
            $command[] = '-nc';
        }

        // User prompt: previous context + task
        $prompt = $this->buildUserPrompt($request);
        $command[] = $prompt;

        return $command;
    }

    #[Override]
    public function run(AgentRunRequestVo $request): AgentResultVo
    {
        $command = $this->buildCommand($request);

        $process = new Process($command);
        $process->setTimeout($request->getTimeout());

        if ($request->getWorkingDir() !== null) {
            $process->setWorkingDirectory($request->getWorkingDir());
        }

        // HTTPS-прокси мост: если CODEX_HTTP_PROXY содержит https:// схему,
        // запускаем локальный HTTP-прокси-мост для пересылки через TLS.
        // Ошибки запуска моста не должны бросать исключение из run() —
        // возвращаем AgentResultVo::createError() по контракту AgentRunnerInterface.
        $bridge = null;
        try {
            $bridge = $this->createBridgeIfNeeded();
        } catch (\Throwable $e) {
            return AgentResultVo::createError(
                errorMessage: sprintf('Failed to start HTTPS proxy bridge: %s', $e->getMessage()),
            );
        }

        // Передача HTTP-прокси через env-переменные:
        // CODEX_HTTP_PROXY (приоритет) подменяет HTTPS_PROXY для pi-процесса.
        // Если CODEX_HTTP_PROXY не задан — Process наследует env родителя (HTTPS_PROXY, HTTP_PROXY).
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

        $this->parser->reset();
        $stdoutBuffer = '';
        $errorOutput = '';

        $outputHandler = function (string $type, string $chunk) use ($process, &$stdoutBuffer, &$errorOutput): void {
            if ($type === Process::OUT) {
                $this->bufferStdoutChunk($chunk, $stdoutBuffer);
                $process->clearOutput();

                return;
            }

            $errorOutput = self::appendErrorOutputTail($errorOutput, $chunk);
            $process->clearErrorOutput();
        };

        try {
            $process->start($outputHandler);
            $process->wait();
            $this->flushStdoutBuffer($stdoutBuffer);
        } catch (ProcessTimedOutException) {
            return AgentResultVo::createError(
                errorMessage: sprintf('Agent timed out after %d seconds.', $request->getTimeout()),
                timedOut: true,
            );
        } catch (ProcessSignaledException $e) {
            return AgentResultVo::createError(
                errorMessage: sprintf('pi process terminated by signal %d.', $e->getSignal()),
                exitCode: 128 + $e->getSignal(),
            );
        } finally {
            // Гарантированная остановка orphan-процесса моста при любом исходе
            // (включая таймаут/сигнал/нормальное завершение).
            $bridge?->stop();
        }

        if (!$process->isSuccessful()) {
            return AgentResultVo::createError(
                $errorOutput !== '' ? $errorOutput : sprintf('pi exited with code %d.', $process->getExitCode() ?? 1),
                $process->getExitCode() ?? 1,
            );
        }

        $parsed = $this->parser->result();

        if ($parsed['isError']) {
            return AgentResultVo::createError(
                errorMessage: $parsed['errorMessage'],
            );
        }

        return AgentResultVo::createSuccess(
            outputText: $parsed['outputText'],
            inputTokens: $parsed['inputTokens'],
            outputTokens: $parsed['outputTokens'],
            cacheReadTokens: $parsed['cacheReadTokens'],
            cacheWriteTokens: $parsed['cacheWriteTokens'],
            cost: $parsed['cost'],
            model: $parsed['model'],
            turns: $parsed['turns'],
        );
    }

    /**
     * Формирует env-переменные для Symfony Process с учётом HTTP-прокси.
     *
     * Приоритет HTTPS_PROXY для pi-процесса:
     *   CODEX_HTTP_PROXY (если задан) → подменяет HTTPS_PROXY
     *   HTTPS_PROXY из окружения      → унаследуется автоматически (если setEnv не вызван)
     *   HTTP_PROXY из окружения       → унаследуется автоматически
     *
     * Для https://-схемы HTTPS_PROXY НЕ подменяется здесь — это сделает мост в run()
     * (подставит свой локальный http:// URL).
     *
     * Метод принимает текущее окружение как параметр для тестируемости.
     *
     * @param array<string, string> $currentEnv текущее окружение (например, из getenv())
     *
     * @return array<string, string> окружение с подменённым HTTPS_PROXY
     */
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

    /**
     * Создаёт HttpsProxyBridge если CODEX_HTTP_PROXY содержит https:// схему.
     *
     * Мост переиспользуется из Codex-раннера (общий механизм обхода Cloudflare
     * IP-block для endpoint'ов OpenAI).
     *
     * @return HttpsProxyBridge|null мост или null если HTTPS-прокси не нужен
     */
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

    /**
     * Разрешает @file в элементах command.
     *
     * Формат: `@path/to/file.txt` → содержимое файла.
     * Если файл не найден — значение остаётся как есть.
     *
     * @param list<string> $command
     * @param string|null $workingDir базовая директория для относительных путей
     *
     * @return list<string>
     */
    private function resolveCommandFiles(array $command, ?string $workingDir): array
    {
        $resolved = [];

        foreach ($command as $value) {
            if (str_starts_with($value, '@')) {
                $path = substr($value, 1);

                if ($workingDir !== null && !str_starts_with($path, '/')) {
                    $path = $workingDir . '/' . $path;
                }

                if (file_exists($path)) {
                    $content = file_get_contents($path);
                    if ($content !== false) {
                        $resolved[] = trim($content);
                        continue;
                    }
                }
            }

            $resolved[] = $value;
        }

        return $resolved;
    }

    /**
     * Формирует user-промпт из контекста и задачи.
     */
    private function buildUserPrompt(AgentRunRequestVo $request): string
    {
        $parts = [];

        if ($request->getPreviousContext() !== null) {
            $parts[] = $request->getPreviousContext();
        }

        $parts[] = sprintf('[Задача]: %s', $request->getTask());

        return implode("\n\n", $parts);
    }

    /**
     * Буферизует stdout-чанк до переводов строк и отдаёт полные JSONL-строки в parser.
     */
    private function bufferStdoutChunk(string $chunk, string &$stdoutBuffer): void
    {
        $stdoutBuffer .= $chunk;
        $newlinePosition = strpos($stdoutBuffer, "\n");

        while ($newlinePosition !== false) {
            $line = substr($stdoutBuffer, 0, $newlinePosition);
            $stdoutBuffer = substr($stdoutBuffer, $newlinePosition + 1);
            $this->parser->feed($line);
            $newlinePosition = strpos($stdoutBuffer, "\n");
        }
    }

    /**
     * Отдаёт parser последнюю строку без завершающего перевода строки.
     */
    private function flushStdoutBuffer(string &$stdoutBuffer): void
    {
        if ($stdoutBuffer === '') {
            return;
        }

        $this->parser->feed($stdoutBuffer);
        $stdoutBuffer = '';
    }

    private static function appendErrorOutputTail(string $currentOutput, string $chunk): string
    {
        $currentOutput .= $chunk;
        if (strlen($currentOutput) <= self::ERROR_OUTPUT_TAIL_BYTES) {
            return $currentOutput;
        }

        return substr($currentOutput, -self::ERROR_OUTPUT_TAIL_BYTES);
    }
}
