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
     * model, и склеенный prompt (system + append + user).
     *
     * Маркеры @system-prompt / @append-system-prompt в command резолвятся
     * в содержимое файлов и выносятся из command — codex не имеет
     * флагов --system-prompt / --append-system-prompt, поэтому промпты
     * склеиваются в единый аргумент в конце команды.
     *
     * Аналогично, runnerArgs вида ['--append-system-prompt', '/path']
     * обрабатываются: путь читается, содержимое добавляется в промпт.
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

        // Извлечь @system-prompt / @append-system-prompt из command,
        // резолвить в содержимое файлов и убрать из command
        $promptParts = $this->extractPromptMarkers($command, $request->getWorkingDir());

        // Разрешение @file → содержимое файла (для остальных @-маркеров)
        $command = $this->resolveCommandFiles($command, $request->getWorkingDir());

        // Извлечь --append-system-prompt <path> из runnerArgs
        $appendFromArgs = $this->extractAppendFromRunnerArgs($request->getRunnerArgs());
        if ($appendFromArgs !== '') {
            $promptParts[] = $appendFromArgs;
        }

        // Добавить runnerArgs без --append-system-prompt
        foreach ($this->getFilteredRunnerArgs($request->getRunnerArgs()) as $arg) {
            $command[] = $arg;
        }

        // Склеенный промпт: prompt parts + systemPrompt + previous context + task
        $prompt = $this->buildPrompt($request, $promptParts);
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
     * Извлекает маркеры @system-prompt / @append-system-prompt из command,
     * резолвит в содержимое файлов и убирает их из command.
     *
     * @param list<string> $command (modified by reference)
     * @param string|null $workingDir
     * @return list<string> содержимое резолвленных файлов
     */
    private function extractPromptMarkers(array &$command, ?string $workingDir): array
    {
        $promptParts = [];
        $filtered = [];

        foreach ($command as $value) {
            if ($value === '@system-prompt' || $value === '@append-system-prompt') {
                // Это маркеры-заполнители: содержимое будет передано через
                // systemPrompt / runnerArgs, а не через command.
                // Убираем из command — codex не знает этих флагов.
                continue;
            }

            if (str_starts_with($value, '@')) {
                $resolved = $this->resolveFileArg($value, $workingDir);
                if ($resolved !== $value) {
                    // Файл найден — содержимое в промпт, не в command
                    $promptParts[] = $resolved;
                    continue;
                }
            }

            $filtered[] = $value;
        }

        $command = $filtered;

        return $promptParts;
    }

    /**
     * Извлекает содержимое --append-system-prompt <path> из runnerArgs.
     *
     * Codex не поддерживает этот флаг — содержимое файла
     * добавляется в склеенный промпт.
     *
     * @param list<string> $runnerArgs
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
     * Иначе — возвращает исходное значение.
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
     * Формирует единый промпт для Codex.
     *
     * Codex не имеет --system-prompt, поэтому склеиваем:
     * prompt markers (из command) + systemPrompt + previous context + task
     * в один аргумент.
     *
     * Если systemPrompt — путь к существующему файлу, читает его содержимое.
     *
     * @param list<string> $promptParts содержимое @system-prompt / @append-system-prompt маркеров
     */
    private function buildPrompt(AgentRunRequestVo $request, array $promptParts = []): string
    {
        $parts = $promptParts;

        if ($request->getSystemPrompt() !== null) {
            $systemContent = $this->readFileOrValue($request->getSystemPrompt());
            if ($systemContent !== '') {
                $parts[] = $systemContent;
            }
        }

        if ($request->getPreviousContext() !== null) {
            $parts[] = $request->getPreviousContext();
        }

        $parts[] = sprintf('[Задача]: %s', $request->getTask());

        return implode("\n\n", $parts);
    }
}
