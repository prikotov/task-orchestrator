<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Pi;

use Override;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;

/**
 * Реализация AgentRunnerInterface для pi CLI.
 *
 * Запускает pi через Symfony Process.
 * Если в AgentRunRequestVo задан command — используется он как базовая команда.
 * Иначе — стандартная: `pi --mode json -p --no-session`.
 * Пути к файлам промптов (--system-prompt, --append-system-prompt) передаются
 * как абсолютные пути — Pi читает файлы самостоятельно через existsSync-эвристику.
 * Значения с префиксом @ разрешаются как пути к файлам (содержимое подставляется inline).
 */
final readonly class PiAgentRunner implements AgentRunnerInterface
{
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
    public function buildCommand(AgentRunRequestVo $request): array
    {
        $command = $request->getCommand();

        if ($command === []) {
            $command = ['pi', '--mode', 'json', '-p', '--no-session'];
        } elseif ($command[0] !== 'pi' && !str_contains($command[0] ?? '', 'pi')) {
            throw new \InvalidArgumentException(sprintf(
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
        if ($request->getNoContextFiles() && !in_array('-nc', $command, true) && !in_array('-no-context-files', $command, true)) {
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

        try {
            $process->run();
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException) {
            return AgentResultVo::createFromError(
                sprintf('Agent timed out after %d seconds.', $request->getTimeout()),
            );
        }

        if (!$process->isSuccessful()) {
            return AgentResultVo::createFromError(
                $process->getErrorOutput() ?: sprintf('pi exited with code %d.', $process->getExitCode() ?? 1),
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
}
