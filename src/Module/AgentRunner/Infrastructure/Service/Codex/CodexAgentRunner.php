<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex;

use Override;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;

/**
 * Реализация AgentRunnerInterface для Codex CLI (OpenAI).
 *
 * Запускает `codex exec --full-auto --json --sandbox danger-full-access`
 * через Symfony Process.
 *
 * Ключевое отличие от pi: Codex не имеет `--system-prompt` / `--append-system-prompt`.
 * Системные инструкции передаются через `-c developer_instructions="..."` (TOML config override).
 *
 * Маркер `@append-system-prompt` в command резолвится в содержимое файла,
 * путь к которому передаётся через runnerArgs `--append-system-prompt <path>`.
 *
 * Если в AgentRunRequestVo задан command — используется он как базовая команда.
 * Иначе — стандартная: `codex exec --full-auto --json --sandbox danger-full-access -m <model>`.
 */
final readonly class CodexAgentRunner implements AgentRunnerInterface
{
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
     * - `@append-system-prompt` — резолвится из runnerArgs `--append-system-prompt <path>`
     *   в содержимое файла и подставляется в строку command.
     *
     * @param AgentRunRequestVo $request запрос на запуск агента
     *
     * @return list<string> готовый массив аргументов для Symfony Process
     */
    public function buildCommand(AgentRunRequestVo $request): array
    {
        $command = $request->getCommand();

        if ($command === []) {
            $command = ['codex', 'exec', '--full-auto', '--json', '--sandbox', 'danger-full-access'];

            if ($request->getModel() !== null) {
                $command[] = '--model';
                $command[] = $request->getModel();
            }
        } elseif ($command[0] !== 'codex' && !str_contains($command[0] ?? '', 'codex')) {
            throw new \InvalidArgumentException(sprintf(
                'AgentRunRequestVo::$command must be either empty (runner default) or a full CLI command starting with "codex". '
                . 'Got: %s',
                implode(' ', $command),
            ));
        }

        // Резолвить @append-system-prompt маркер в command → содержимое файла из runnerArgs
        $command = $this->resolvePromptSlots($command, $request->getRunnerArgs());

        // Добавить runnerArgs без --append-system-prompt
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
        $process->setTimeout($request->getTimeout());

        if ($request->getWorkingDir() !== null) {
            $process->setWorkingDirectory($request->getWorkingDir());
        }

        try {
            $process->run();
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException) {
            return AgentResultVo::createFromError(
                errorMessage: sprintf('Agent timed out after %d seconds.', $request->getTimeout()),
                timedOut: true,
            );
        }

        if (!$process->isSuccessful()) {
            return AgentResultVo::createFromError(
                $process->getErrorOutput() ?: sprintf('codex exited with code %d.', $process->getExitCode() ?? 1),
                $process->getExitCode() ?? 1,
            );
        }

        $parsed = $this->parser->parse($process->getOutput());

        return AgentResultVo::createFromSuccess(
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
     * Резолвит слоты @append-system-prompt в элементах command.
     *
     * Ищет `@append-system-prompt` в строках command, берёт путь из runnerArgs
     * `--append-system-prompt <path>`, читает файл и подставляет содержимое
     * вместо маркера (с TOML-экранированием).
     *
     * @param list<string> $command
     * @param list<string> $runnerArgs
     *
     * @return list<string>
     */
    private function resolvePromptSlots(array $command, array $runnerArgs): array
    {
        // Извлечь содержимое файла из runnerArgs
        $appendContent = $this->extractAppendFromRunnerArgs($runnerArgs);
        $escapedContent = $appendContent !== '' ? $this->escapeTomlString($appendContent) : null;

        $resolved = [];
        foreach ($command as $value) {
            if ($escapedContent !== null && str_contains($value, '@append-system-prompt')) {
                $resolved[] = str_replace('@append-system-prompt', $escapedContent, $value);
            } else {
                $resolved[] = $value;
            }
        }

        return $resolved;
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
     * Извлекает содержимое --append-system-prompt <path> из runnerArgs.
     *
     * @param list<string> $runnerArgs
     *
     * @return string содержимое файла или пустая строка
     */
    private function extractAppendFromRunnerArgs(array $runnerArgs): string
    {
        for ($i = 0; $i < count($runnerArgs) - 1; $i++) {
            if ($runnerArgs[$i] === '--append-system-prompt') {
                return $this->readFileOrValue($runnerArgs[$i + 1]);
            }
        }

        return '';
    }

    /**
     * Возвращает runnerArgs без --append-system-prompt <path>.
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
