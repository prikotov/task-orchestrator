<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

/**
 * Value Object: контекст выполнения одного шага цепочки.
 *
 * Инкапсулирует параметры, необходимые StepRunnerInterface::run()
 * для выполнения agent-шага, quality-gate или tool-шага.
 */
final readonly class StepContextVo
{
    public function __construct(
        public string $task,
        public ?string $workingDir = null,
        public int $timeout = 300,
        public ?string $previousContext = null,
        public ?int $iterationNumber = null,
        public ?ExecutionRoleConfigVo $roleConfig = null,
        public bool $noContextFiles = false,
    ) {
    }
}
