<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

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
    /**
     * @param list<StaticStepResultVo> $stepResults
     */
    public function __construct(
        private array $stepResults,
        private float $totalTime,
        private int $totalInputTokens,
        private int $totalOutputTokens,
        private float $totalCost,
        private bool $budgetExceeded,
        private float $budgetLimit,
        private ?string $budgetExceededRole,
        private int $totalIterations,
    ) {
    }

    /**
     * @return list<StaticStepResultVo>
     */
    public function getStepResults(): array
    {
        return $this->stepResults;
    }

    public function getTotalTime(): float
    {
        return $this->totalTime;
    }

    public function getTotalInputTokens(): int
    {
        return $this->totalInputTokens;
    }

    public function getTotalOutputTokens(): int
    {
        return $this->totalOutputTokens;
    }

    public function getTotalCost(): float
    {
        return $this->totalCost;
    }

    public function isBudgetExceeded(): bool
    {
        return $this->budgetExceeded;
    }

    public function getBudgetLimit(): float
    {
        return $this->budgetLimit;
    }

    public function getBudgetExceededRole(): ?string
    {
        return $this->budgetExceededRole;
    }

    public function getTotalIterations(): int
    {
        return $this->totalIterations;
    }
}
