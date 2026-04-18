<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

/**
 * Value Object запроса на запуск AI-агента через Port.
 *
 * Orchestrator Domain VO — дубликат AgentRunner\AgentRunRequestVo.
 * Маппинг в AgentRunRequestVo выполняется в Infrastructure Adapter.
 *
 * Immutable, передаётся через границы слоёв как строго типизированный объект.
 */
final readonly class ChainRunRequestVo
{
    /**
     * @param list<string> $command полная CLI-команда из role config (пусто = runner default)
     * @param list<string> $runnerArgs доп. аргументы runner'а (напр. --append-system-prompt <path>)
     * @param bool $noContextFiles отключить автоматическую загрузку контекстных файлов проекта
     */
    public function __construct(
        private string $role,
        private string $task,
        private ?string $systemPrompt = null,
        private ?string $previousContext = null,
        private ?string $model = null,
        private ?string $tools = null,
        private ?string $workingDir = null,
        private int $timeout = 300,
        private int $maxContextLength = 50000,
        /** @var list<string> */
        private array $command = [],
        /** @var list<string> */
        private array $runnerArgs = [],
        private ?string $runnerName = null,
        private bool $noContextFiles = false,
    ) {
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getTask(): string
    {
        return $this->task;
    }

    public function getSystemPrompt(): ?string
    {
        return $this->systemPrompt;
    }

    public function getPreviousContext(): ?string
    {
        return $this->previousContext;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getTools(): ?string
    {
        return $this->tools;
    }

    public function getWorkingDir(): ?string
    {
        return $this->workingDir;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getMaxContextLength(): int
    {
        return $this->maxContextLength;
    }

    /**
     * @return list<string> полная CLI-команда из role config (пусто = runner default).
     */
    public function getCommand(): array
    {
        return $this->command;
    }

    /**
     * @return list<string> доп. аргументы runner'а (напр. --append-system-prompt <path>).
     */
    public function getRunnerArgs(): array
    {
        return $this->runnerArgs;
    }

    /**
     * Возвращает имя runner'а (напр. 'pi', 'codex').
     * null означает «runner по умолчанию».
     */
    public function getRunnerName(): ?string
    {
        return $this->runnerName;
    }

    /**
     * Отключена ли автоматическая загрузка контекстных файлов проекта.
     */
    public function getNoContextFiles(): bool
    {
        return $this->noContextFiles;
    }

    /**
     * Возвращает новый VO с обрезанным previousContext по maxContextLength.
     * Обрезка происходит с конца (оставляются самые свежие данные).
     */
    public function withTruncatedContext(): self
    {
        if ($this->previousContext === null || strlen($this->previousContext) <= $this->maxContextLength) {
            return $this;
        }

        return new self(
            role: $this->role,
            task: $this->task,
            systemPrompt: $this->systemPrompt,
            previousContext: substr($this->previousContext, -$this->maxContextLength),
            model: $this->model,
            tools: $this->tools,
            workingDir: $this->workingDir,
            timeout: $this->timeout,
            maxContextLength: $this->maxContextLength,
            command: $this->command,
            runnerArgs: $this->runnerArgs,
            runnerName: $this->runnerName,
            noContextFiles: $this->noContextFiles,
        );
    }
}
