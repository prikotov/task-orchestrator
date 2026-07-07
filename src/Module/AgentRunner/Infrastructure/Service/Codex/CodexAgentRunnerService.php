<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex;

use InvalidArgumentException;
use Override;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\CodexAgentRunnerServiceInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;

/**
 * Реализация AgentRunnerInterface для Codex CLI (OpenAI).
 *
 * Запускает `codex exec --full-auto --json --sandbox danger-full-access`
 * через Symfony Process.
 *
 * Передача промптов (в отличие от pi):
 *
 * | Назначение       | pi                            | Codex                                      |
 * |------------------|-------------------------------|--------------------------------------------|
 * | System prompt    | --system-prompt <path>        | -c model_instructions_file="<path>"        |
 * | Append prompt    | --append-system-prompt <path> | -c developer_instructions="<содержимое>"   |
 *
 * `model_instructions_file` принимает путь — codex сам читает файл.
 * `developer_instructions` принимает текст — runner читает файл и подставляет.
 *
 * Маркеры в command (chains.yaml):
 * - `@system-prompt` → путь к файлу (из systemPrompt или runnerArgs)
 * - `@append-system-prompt` → содержимое файла (с TOML-экранированием)
 *
 * Поддержка HTTPS-прокси через HttpsProxyBridge:
 * Если CODEX_HTTP_PROXY содержит https:// схему, автоматически запускается
 * локальный HTTP-прокси-мост, пересылающий CONNECT-запросы через TLS.
 */
final readonly class CodexAgentRunnerService implements CodexAgentRunnerServiceInterface
{
    /** @var int Максимальный stderr-tail для AgentResultVo ошибки процесса */
    private const int ERROR_OUTPUT_TAIL_BYTES = 65536;

    public function __construct(
        private CodexJsonlParser $parser,
    ) {
    }

    #[Override]
    public function getName(): string
    {
        return 'codex';
    }

    #[Override]
    public function isAvailable(): bool
    {
        $process = new Process(['which', 'codex']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Строит массив CLI-команды для запуска Codex по AgentRunRequestVo.
     *
     * Обработка маркеров в command:
     * - `@system-prompt` → подставляется путь к файлу (codex читает сам через model_instructions_file)
     * - `@append-system-prompt` → подставляется содержимое файла (developer_instructions принимает текст)
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
            $command = ['codex', 'exec', '--full-auto', '--json', '--sandbox', 'danger-full-access'];

            if ($request->getModel() !== null) {
                $command[] = '--model';
                $command[] = $request->getModel();
            }
        } elseif ($command[0] !== 'codex' && !str_contains($command[0], 'codex')) {
            throw new InvalidArgumentException(sprintf(
                'AgentRunRequestVo::$command must be either empty (runner default) or a full CLI command starting with "codex". '
                . 'Got: %s',
                implode(' ', $command),
            ));
        }

        // Резолвить @system-prompt и @append-system-prompt маркеры в command
        $command = $this->resolvePromptSlots($command, $request);

        // Добавить runnerArgs без --system-prompt / --append-system-prompt
        foreach ($this->getFilteredRunnerArgs($request->getRunnerArgs()) as $arg) {
            $command[] = $arg;
        }

        // Пользовательский промпт: previous context + task (последний позиционный аргумент)
        $userPrompt = $this->buildUserPrompt($request);
        $command[] = $userPrompt;

        return $command;
    }

    #[Override]
    public function run(AgentRunRequestVo $request): AgentResultVo
    {
        $command = $this->buildCommand($request);

        $process = new Process($command);
        // Liveness-adaptive timeout: Symfony setTimeout = абсолютный hard cap (fallback),
        // poll-loop ниже убивает раньше при простое (idle). Активный процесс (модель
        // рассуждает/стримит) дорабатывает до cap, не обрезаясь по жёсткому таймеру.
        // Симметрично PiAgentRunnerService (#297).
        $hardCap = max($request->getTimeout(), $this->envInt('AGENT_RUNNER_HARD_TIMEOUT_SEC', 1800));
        $process->setTimeout($hardCap);

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
        // CODEX_HTTP_PROXY (приоритет) подменяет HTTPS_PROXY для codex-процесса.
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
            $completed = $this->waitForProcessWithLiveness($process);
            $this->flushStdoutBuffer($stdoutBuffer);
            if (!$completed) {
                // Процесс простаивал (idle) — нет прогресса CPU/IO дольше порога.
                // Возвращаем transient-error (timedOut) → retry подхватит.
                return AgentResultVo::createError(
                    errorMessage: sprintf(
                        'Agent idle: no CPU/IO progress for %d seconds.',
                        $this->envInt('AGENT_RUNNER_IDLE_TIMEOUT_SEC', 60),
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
                errorMessage: sprintf('codex process terminated by signal %d.', $e->getSignal()),
                exitCode: 128 + $e->getSignal(),
            );
        } finally {
            // Гарантированная остановка orphan-процесса моста при любом исходе
            // (включая таймаут/сигнал/нормальное завершение).
            $bridge?->stop();
        }

        if (!$process->isSuccessful()) {
            return AgentResultVo::createError(
                $errorOutput !== ''
                    ? $errorOutput
                    : sprintf('codex exited with code %d.', $process->getExitCode() ?? 1),
                $process->getExitCode() ?? 1,
            );
        }

        $parsed = $this->parser->result();

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
     * Приоритет HTTPS_PROXY для codex-процесса:
     *   CODEX_HTTP_PROXY (если задан) → подменяет HTTPS_PROXY
     *   HTTPS_PROXY из окружения      → унаследуется автоматически (если setEnv не вызван)
     *   HTTP_PROXY из окружения       → унаследуется автоматически
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
     * Poll-loop с liveness-проверкой вместо блокирующего wait(): пока процесс
     * активен (грузит CPU/IO — модель рассуждает/стримит), даём ему доработать
     * до абсолютного hard cap (Symfony setTimeout). При простое (idle) дольше
     * AGENT_RUNNER_IDLE_TIMEOUT_SEC — останавливаем: это зависание, быстрее
     * вернуть transient-error и дать retry, чем ждать hard cap.
     *
     * Симметрично PiAgentRunnerService::waitForProcessWithLiveness (#297).
     *
     * @return bool true — процесс завершился сам; false — остановлен по idle.
     */
    private function waitForProcessWithLiveness(Process $process): bool
    {
        $idleThreshold = (float) $this->envInt('AGENT_RUNNER_IDLE_TIMEOUT_SEC', 60);
        $pollIntervalUs = 500_000; // 0.5с

        $lastActivity = microtime(true);
        $prevCpu = null;
        $prevIo = null;

        while ($process->isRunning()) {
            // Symfony fallback: бросит ProcessTimedOutException при превышении hard cap.
            $process->checkTimeout();

            $pid = $process->getPid();
            if ($pid !== null) {
                $cpu = $this->readProcessCpuTime($pid);
                $io = $this->readProcessIo($pid);
                if ($prevCpu !== null && ($cpu > $prevCpu || $io > $prevIo)) {
                    $lastActivity = microtime(true); // прогресс есть — работает
                }
                $prevCpu = $cpu;
                $prevIo = $io;
            }

            if (microtime(true) - $lastActivity > $idleThreshold) {
                // Зависание: grace SIGTERM, затем SIGKILL.
                $process->stop(2);

                return false;
            }

            usleep($pollIntervalUs);
        }

        return true;
    }

    /**
     * CPU-time процесса в секундах (ps times суммирует потоки, важно для
     * многопоточного node/bun).
     */
    private function readProcessCpuTime(int $pid): int
    {
        /** @psalm-suppress ForbiddenCode */
        $out = @shell_exec(sprintf('ps -o times= -p %d 2>/dev/null', $pid));
        if (!is_string($out) || $out === '') {
            return 0;
        }

        $t = trim($out);

        return is_numeric($t) ? (int) $t : 0;
    }

    /**
     * Сумма rchar+wchar из /proc/<pid>/io (включает сетевой IO через libc,
     * не только диск). Linux-only; отсутствие /proc — 0 (liveness по CPU).
     */
    private function readProcessIo(int $pid): int
    {
        $path = sprintf('/proc/%d/io', $pid);
        if (!@is_readable($path)) {
            return 0;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return 0;
        }

        $sum = 0;
        foreach (['rchar', 'wchar'] as $key) {
            if (preg_match('/^' . $key . ':\s*(\d+)/m', $content, $m) === 1) {
                $sum += (int) $m[1];
            }
        }

        return $sum;
    }

    /**
     * Читает int из env с дефолтом; не-числовые/пустые значения → default.
     */
    private function envInt(string $name, int $default): int
    {
        $v = getenv($name);
        if ($v === false || $v === '' || !is_numeric($v)) {
            return $default;
        }

        return (int) $v;
    }

    /**
     * Резолвит слоты @system-prompt и @append-system-prompt в элементах command.
     *
     * @system-prompt → путь к файлу (model_instructions_file читает файл сам).
     * @append-system-prompt → содержимое файла с TOML-экранированием
     *   (developer_instructions принимает текст).
     *
     * Источники путей:
     * - systemPrompt VO-поле (файл роли от RunDynamicLoopAgentService)
     * - runnerArgs --append-system-prompt <path>
     *
     * @param list<string> $command
     * @param AgentRunRequestVo $request
     *
     * @return list<string>
     */
    private function resolvePromptSlots(array $command, AgentRunRequestVo $request): array
    {
        // @system-prompt: путь к файлу (codex читает сам)
        $systemPath = $this->resolveSystemPromptPath($request);

        // @append-system-prompt: содержимое файла (TOML-экранированное)
        $appendContent = $this->extractAppendFromRunnerArgs($request->getRunnerArgs());
        $escapedAppend = $appendContent !== '' ? $this->escapeTomlString($appendContent) : null;

        $resolved = [];
        foreach ($command as $value) {
            $replaced = $value;

            if ($systemPath !== null && str_contains($value, '@system-prompt')) {
                $replaced = str_replace('@system-prompt', $systemPath, $replaced);
            }

            if ($escapedAppend !== null && str_contains($replaced, '@append-system-prompt')) {
                $replaced = str_replace('@append-system-prompt', $escapedAppend, $replaced);
            }

            $resolved[] = $replaced;
        }

        return $resolved;
    }

    /**
     * Определяет путь к файлу system-prompt.
     *
     * Из VO-поля systemPrompt: если это путь к существующему файлу —
     * используется как есть (codex прочитает сам через model_instructions_file).
     * Иначе — null (маркер не резолвится).
     */
    private function resolveSystemPromptPath(AgentRunRequestVo $request): ?string
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
     * Формирует пользовательский промпт (user message).
     *
     * Содержит previous context + task — передаётся как
     * последний позиционный аргумент команды Codex.
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
     * Экранирует строку для TOML basic string (double-quoted).
     *
     * TOML basic string: `"..."`
     * Спецсимволы: `\` → `\\`, `"` → `\"`, перенос строки → `\n`,
     * табуляция → `\t`, возврат каретки → `\r`.
     */
    private function escapeTomlString(string $value): string
    {
        $escaped = str_replace('\\', '\\\\', $value);
        $escaped = str_replace('"', '\\"', $escaped);
        $escaped = str_replace("\n", '\\n', $escaped);
        $escaped = str_replace("\t", '\\t', $escaped);
        $escaped = str_replace("\r", '\\r', $escaped);

        return $escaped;
    }

    /**
     * Извлекает содержимое файла --append-system-prompt <path> из runnerArgs.
     *
     * developer_instructions принимает текст, поэтому файл читается здесь.
     *
     * @param list<string> $runnerArgs
     *
     * @return string содержимое файла или пустая строка
     */
    private function extractAppendFromRunnerArgs(array $runnerArgs): string
    {
        for ($i = 0, $count = count($runnerArgs); $i < $count - 1; $i++) {
            if ($runnerArgs[$i] === '--append-system-prompt') {
                return $this->readFileOrValue($runnerArgs[$i + 1]);
            }
        }

        return '';
    }

    /**
     * Возвращает runnerArgs без --system-prompt / --append-system-prompt и их значений.
     *
     * @param list<string> $runnerArgs
     *
     * @return list<string>
     */
    private function getFilteredRunnerArgs(array $runnerArgs): array
    {
        $filtered = [];
        $skip = false;

        foreach ($runnerArgs as $arg) {
            if ($arg === '--append-system-prompt') {
                $skip = true;
                continue;
            }
            if ($skip) {
                $skip = false;
                continue;
            }
            $filtered[] = $arg;
        }

        return $filtered;
    }

    /**
     * Читает содержимое файла по пути или возвращает значение как есть.
     *
     * Если путь указывает на существующий файл — читает его.
     * Иначе — возвращает исходное значение (текст).
     */
    private function readFileOrValue(string $value): string
    {
        if (file_exists($value) && is_file($value)) {
            $content = file_get_contents($value);

            return $content !== false ? trim($content) : $value;
        }

        return $value;
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
