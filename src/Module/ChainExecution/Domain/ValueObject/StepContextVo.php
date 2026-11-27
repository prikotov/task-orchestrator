<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

/**
 * Value Object: контекст выполнения одного шага цепочки.
 *
 * Инкапсулирует параметры, необходимые ExecuteStepServiceInterface::run()
 * для выполнения agent-шага, quality-gate или tool-шага.
 */
final readonly class StepContextVo
{
    public function __construct(
        private string $task,
        private ?string $workingDir = null,
        private int $timeout = 300,
        private ?string $previousContext = null,
        private ?int $iterationNumber = null,
        private ?ExecutionRoleConfigVo $roleConfig = null,
        private bool $noContextFiles = false,
    ) {
    }

    public function getTask(): string
    {
        return $this->task;
    }

    public function getWorkingDir(): ?string
    {
        return $this->workingDir;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getPreviousContext(): ?string
    {
        return $this->previousContext;
    }

    public function getIterationNumber(): ?int
    {
        return $this->iterationNumber;
    }

    public function getRoleConfig(): ?ExecutionRoleConfigVo
    {
        return $this->roleConfig;
    }

    /**
     * Отключена ли автоматическая загрузка контекстных файлов проекта.
     */
    public function hasNoContextFiles(): bool
    {
        return $this->noContextFiles;
    }
}
