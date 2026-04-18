<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject;

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
    ) {
    }
}
