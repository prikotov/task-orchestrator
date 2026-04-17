<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

/**
 * Агрегированный результат выполнения static-цепочки (Domain VO).
 *
 * Заменяет OrchestrateChainResultDto для static path.
 * Содержит все доменные метрики цепочки.
 *
 * @param list<StaticStepResultVo> $stepResults
 */
final readonly class StaticChainResultVo
{
    public function __construct(
        public array $stepResults,
        public float $totalTime,
        public int $totalInputTokens,
        public int $totalOutputTokens,
        public float $totalCost,
        public bool $budgetExceeded,
        public float $budgetLimit,
        public ?string $budgetExceededRole,
        public int $totalIterations,
    ) {
    }
}
