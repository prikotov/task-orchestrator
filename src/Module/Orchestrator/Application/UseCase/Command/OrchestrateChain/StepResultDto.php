<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain;

/**
 * DTO результата одного шага оркестрации.
 *
 * Содержит метрику шага: какой шаг выполнялся, результат, длительность.
 * Используется как для agent-шагов, так и для quality_gate-шагов.
 */
final readonly class StepResultDto
{
    public function __construct(
        public string $role,
        public string $runner,
        public string $outputText,
        public int $inputTokens,
        public int $outputTokens,
        public float $cost,
        public float $duration,
        public bool $isError,
        public ?string $errorMessage = null,
        public ?string $fallbackRunnerUsed = null,
        public ?int $iterationNumber = null,
        public bool $iterationWarning = false,
        public bool $passed = true,
        public int $exitCode = 0,
        public string $label = '',
        public bool $timedOut = false,
    ) {
    }
}
