<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject;

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
        public bool $maxTimeExceeded = false,
    ) {
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
