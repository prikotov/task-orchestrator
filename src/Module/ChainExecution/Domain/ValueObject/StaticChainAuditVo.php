<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject;

/**
 * VO агрегированного результата static-цепочки для audit-лога.
 *
 * StaticExecution Domain-тип: изолирует модуль от Orchestrator DTO.
 * Маппинг в Orchestrator\ChainResultAuditDto выполняется в Integration-слое.
 *
 * @param list<StaticStepAuditVo> $stepStatuses
 */
final readonly class StaticChainAuditVo
{
    /**
     * @param list<StaticStepAuditVo> $stepStatuses
     */
    public function __construct(
        private string $chainName,
        private float $totalDurationMs,
        private int $totalInputTokens,
        private int $totalOutputTokens,
        private float $totalCost,
        private bool $budgetExceeded,
        private int $stepsCount,
        private array $stepStatuses,
    ) {
    }

    public function getChainName(): string
    {
        return $this->chainName;
    }

    public function getTotalDurationMs(): float
    {
        return $this->totalDurationMs;
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

    public function getStepsCount(): int
    {
        return $this->stepsCount;
    }

    /**
     * @return list<StaticStepAuditVo>
     */
    public function getStepStatuses(): array
    {
        return $this->stepStatuses;
    }
}
