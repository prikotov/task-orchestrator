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
 * Ключевое отличие от pi: Codex не имеет `--system-prompt`.
 * Системные инструкции (роль + append) передаются через
 * `-c developer_instructions="..."` (TOML config override).
 * Пользовательский промпт (контекст + задача) — последний позиционный аргумент.
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
     * Формирует команду в два уровня:
     * 1. CLI-флаги: базовая команда из config (или default) + model
     * 2. developer_instructions: системные инструкции через `-c` (роль + append)
     * 3. Пользовательский промпт: последний позиционный аргумент (контекст + задача)
     *
     * Маркеры @system-prompt / @append-system-prompt в command удаляются —
     * codex не поддерживает эти флаги, содержимое передаётся через developer_instructions.
     *
     * Аналогично, runnerArgs вида ['--append-system-prompt', '/path']
     * обрабатываются: путь читается, содержимое добавляется в developer_instructions.
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

        // Удалить маркеры @system-prompt / @append-system-prompt из command
        $command = $this->removePromptMarkers($command);

        // Разрешение @file → содержимое файла (для остальных @-маркеров)
        $command = $this->resolveCommandFiles($command, $request->getWorkingDir());

        // Собрать developer_instructions из systemPrompt + append из runnerArgs
        $devInstructions = $this->buildDeveloperInstructions($request);
        if ($devInstructions !== '') {
            $command[] = '-c';
            $command[] = sprintf('developer_instructions="%s"', $this->escapeTomlString($devInstructions));
        }

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
     * Удаляет маркеры @system-prompt / @append-system-prompt из command.
     *
     * Codex не поддерживает эти флаги — содержимое передаётся через developer_instructions.
     *
     * @param list<string> $command
     *
     * @return list<string>
     */
    private function removePromptMarkers(array $command): array
    {
        return array_values(array_filter(
            $command,
            static fn(string $value): bool => $value !== '@system-prompt' && $value !== '@append-system-prompt',
        ));
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
                $resolved[] = $this->resolveFileArg($value, $workingDir);
            } else {
                $resolved[] = $value;
            }
        }

        return $resolved;
    }

    /**
     * Разрешает единичный @file-аргумент в содержимое файла.
     *
     * Если файл не найден — возвращает исходное значение.
     */
    private function resolveFileArg(string $arg, ?string $workingDir): string
    {
        $path = substr($arg, 1);

        if ($workingDir !== null && !str_starts_with($path, '/')) {
            $path = $workingDir . '/' . $path;
        }

        if (file_exists($path)) {
            $content = file_get_contents($path);
            if ($content !== false) {
                return trim($content);
            }
        }

        return $arg;
    }

    /**
     * Собирает developer_instructions из systemPrompt и --append-system-prompt из runnerArgs.
     *
     * Это аналоги --system-prompt и --append-system-prompt из pi,
     * передаваемые через `-c developer_instructions="..."` в Codex.
     */
    private function buildDeveloperInstructions(AgentRunRequestVo $request): string
    {
        $parts = [];

        // systemPrompt — путь к файлу или текст (от RunDynamicLoopAgentService)
        if ($request->getSystemPrompt() !== null) {
            $systemContent = $this->readFileOrValue($request->getSystemPrompt());
            if ($systemContent !== '') {
                $parts[] = $systemContent;
            }
        }

        // --append-system-prompt <path> из runnerArgs
        $appendContent = $this->extractAppendFromRunnerArgs($request->getRunnerArgs());
        if ($appendContent !== '') {
            $parts[] = $appendContent;
        }

        return implode("\n\n", $parts);
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
     * Codex не поддерживает этот флаг — содержимое файла
     * добавляется в developer_instructions.
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
