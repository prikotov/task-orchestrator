<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject;

/**
 * Результат dynamic-цикла: агрегированные метрики, synthesis, флаги.
 *
 * Domain-аналог Application DTO DynamicLoopResultDto.
 * Содержит только primitives и Domain VOs — без Application зависимостей.
 */
final readonly class DynamicLoopResultVo
{
    /**
     * @param list<DynamicRoundResultVo> $roundResults
     */
    public function __construct(
        private array $roundResults,
        private float $totalTime,
        private int $totalInputTokens,
        private int $totalOutputTokens,
        private float $totalCost,
        private ?string $synthesis,
        private bool $maxRoundsReached,
        private ?string $interruptionReason = null,
        private bool $budgetExceeded = false,
        private float $budgetLimit = 0.0,
        private ?string $budgetExceededRole = null,
        private bool $maxTimeExceeded = false,
    ) {
    }

    /**
     * @return list<DynamicRoundResultVo>
     */
    public function getRoundResults(): array
    {
        return $this->roundResults;
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

    public function getSynthesis(): ?string
    {
        return $this->synthesis;
    }

    public function isMaxRoundsReached(): bool
    {
        return $this->maxRoundsReached;
    }

    public function getInterruptionReason(): ?string
    {
        return $this->interruptionReason;
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

    public function isMaxTimeExceeded(): bool
    {
        return $this->maxTimeExceeded;
    }

    /**
     * Определяет причину завершения dynamic-цикла.
     *
     * Бизнес-правило классификации: budget > maxTime > synthesis presence > interruption.
     */
    public function getCompletionReason(): string
    {
        if ($this->budgetExceeded) {
            return 'budget_exceeded';
        }

        if ($this->maxTimeExceeded) {
            return 'max_time_exceeded';
        }

        if ($this->synthesis !== null) {
            return $this->maxRoundsReached ? 'max_rounds_reached' : 'facilitator_done';
        }

        return $this->interruptionReason ?? 'no_synthesis';
    }
}
