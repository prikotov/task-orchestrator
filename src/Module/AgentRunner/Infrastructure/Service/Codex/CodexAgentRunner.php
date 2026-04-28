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
 * Промпт роли склеивается с user prompt и передаётся как единый аргумент.
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
     * Последовательно добавляет: базовую команду (из config или default),
     * runner args, model, и склеенный prompt (system + user).
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

            // Model — обязательна для Codex
            if ($request->getModel() !== null) {
                $command[] = '-m';
                $command[] = $request->getModel();
            }
        } elseif ($command[0] !== 'codex' && !str_contains($command[0] ?? '', 'codex')) {
            throw new \InvalidArgumentException(sprintf(
                'AgentRunRequestVo::$command must be either empty (runner default) or a full CLI command starting with "codex". '
                . 'Got: %s',
                implode(' ', $command),
            ));
        }

        // Разрешение @file → содержимое файла
        $command = $this->resolveCommandFiles($command, $request->getWorkingDir());

        // Доп. аргументы runner'а (prompt-файлы от dynamic loop)
        foreach ($request->getRunnerArgs() as $arg) {
            if (str_starts_with($arg, '@')) {
                $resolved = $this->resolveFileArg($arg, $request->getWorkingDir());
                $command[] = $resolved;
            } else {
                $command[] = $arg;
            }
        }

        // Склеенный промпт: system-prompt + previous context + task
        $prompt = $this->buildPrompt($request);
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
     * Формирует единый промпт для Codex.
     *
     * Codex не имеет --system-prompt, поэтому склеиваем:
     * system-prompt + previous context + task в один аргумент.
     */
    private function buildPrompt(AgentRunRequestVo $request): string
    {
        $parts = [];

        if ($request->getSystemPrompt() !== null) {
            $parts[] = $request->getSystemPrompt();
        }

        if ($request->getPreviousContext() !== null) {
            $parts[] = $request->getPreviousContext();
        }

        $parts[] = sprintf('[Задача]: %s', $request->getTask());

        return implode("\n\n", $parts);
    }
}
