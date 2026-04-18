<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Event\OrchestrateChain;

/**
 * Событие: сессия оркестрации завершена.
 *
 * Диспатчится один раз после завершения всей цепочки.
 */
final readonly class OrchestrateSessionCompletedEvent
{
    public function __construct(
        public string $status,
        public ?string $completionReason,
        public int $totalRounds,
        public float $totalTime,
        public int $totalInputTokens,
        public int $totalOutputTokens,
        public float $totalCost,
        public ?string $synthesis,
        public ?string $sessionDir,
        public bool $budgetExceeded = false,
        public float $budgetLimit = 0.0,
        public ?string $budgetExceededRole = null,
    ) {
    }
}
