<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Application\Event\OrchestrateChain;

/**
 * Событие: завершён раунд оркестрации.
 *
 * Диспатчится после каждого шага (фасилитатор или участник).
 */
final readonly class OrchestrateRoundCompletedEvent
{
    public function __construct(
        public int $step,
        public int $round,
        public string $role,
        public bool $isFacilitator,
        public bool $isError,
        public ?string $errorMessage,
        public float $duration,
        public int $inputTokens,
        public int $outputTokens,
        public float $cost,
        public ?string $nextRole = null,
        public bool $done = false,
        public ?string $synthesis = null,
    ) {
    }
}
