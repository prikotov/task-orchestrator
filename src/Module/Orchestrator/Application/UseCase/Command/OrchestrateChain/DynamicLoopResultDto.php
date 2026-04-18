<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain;

/**
 * DTO результата цикла dynamic-цепочки.
 *
 * Содержит все раунды, агрегированные метрики, synthesis и флаг достижения лимита.
 */
final readonly class DynamicLoopResultDto
{
    /**
     * @param list<DynamicRoundResultDto> $roundResults
     */
    public function __construct(
        public array $roundResults,
        public float $totalTime,
        public int $totalInputTokens,
        public int $totalOutputTokens,
        public float $totalCost,
        public ?string $synthesis,
        public bool $maxRoundsReached,
        public ?string $interruptionReason = null,
        public bool $budgetExceeded = false,
        public float $budgetLimit = 0.0,
        public ?string $budgetExceededRole = null,
    ) {
    }
}
