<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

/**
 * Value Object запроса на запуск AI-агента для dynamic-цикла.
 *
 * Копия ChainDefinition\ChainRunRequestVo, без зависимости от ChainDefinition.Domain.
 */
final readonly class DynamicLoopRunRequestVo
{
    /**
     * @param list<string> $command полная CLI-команда из role config
     * @param list<string> $runnerArgs доп. аргументы runner'а
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
        private array $command = [],
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
     * @return list<string>
     */
    public function getCommand(): array
    {
        return $this->command;
    }

    /**
     * @return list<string>
     */
    public function getRunnerArgs(): array
    {
        return $this->runnerArgs;
    }

    public function getRunnerName(): ?string
    {
        return $this->runnerName;
    }

    public function hasNoContextFiles(): bool
    {
        return $this->noContextFiles;
    }


}
