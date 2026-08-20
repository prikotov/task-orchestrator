<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex;

use InvalidArgumentException;
use Override;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\CodexAgentRunnerServiceInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Lifecycle\RunAgentProcessLifecycleServiceInterface;

/**
 * Реализация AgentRunnerInterface для Codex CLI (OpenAI).
 *
 * Запускает `codex exec --full-auto --json --sandbox danger-full-access`
 * через Symfony Process.
 *
 * Runtime-жизненный цикл (создание Process, прокси-окружение, буферизация
 * stdout, liveness-ожидание, остановка) делегирован общему
 * {@see RunAgentProcessLifecycleServiceInterface}; здесь остаётся только
 * Codex-специфика: buildCommand и buildResult.
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
    public function __construct(
        private CodexJsonlParser $parser,
        private RunAgentProcessLifecycleServiceInterface $processLifecycle,
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
        $userPrompt = $this->processLifecycle->buildUserPrompt($request);
        $command[] = $userPrompt;

        return $command;
    }

    #[Override]
    public function run(AgentRunRequestVo $request): AgentResultVo
    {
        return $this->processLifecycle->run(
            request: $request,
            runnerName: 'codex',
            buildCommand: fn (AgentRunRequestVo $request): array => $this->buildCommand($request),
            resetParser: fn () => $this->parser->reset(),
            feedParserLine: fn (string $line) => $this->parser->feed($line),
            buildResult: fn (Process $process, string $errorOutput): AgentResultVo => $this->buildResult($process, $errorOutput),
        );
    }

    /**
     * Формирует итоговый AgentResultVo по завершившемуся процессу.
     *
     * Неуспешный exit — ошибка с stderr-tail (или кодом выхода);
     * успешный — метрики из накопленного JSONL-парсера.
     */
    private function buildResult(Process $process, string $errorOutput): AgentResultVo
    {
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
        $systemPath = $this->processLifecycle->resolveSystemPromptPath($request);

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
}
