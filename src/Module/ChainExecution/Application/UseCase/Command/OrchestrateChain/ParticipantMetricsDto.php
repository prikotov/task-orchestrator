<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain;

/**
 * Агрегированные метрики одного участника (по роли) dynamic-цепочки.
 *
 * Строится ParticipantsMetricsMapper из round-результатов сессии.
 * Поля model/provider/thinking равны null, если раннер не сообщил модель
 * (например codex/ollama или раунды старой сессии до появления этих полей).
 */
final readonly class ParticipantMetricsDto
{
    public function __construct(
        public string $role,
        public ?string $model,
        public ?string $provider,
        public ?string $thinking,
        /** Число выступлений (шаги с is_facilitator = false). */
        public int $speeches,
        /** Число шагов фасилитатора (шаги с is_facilitator = true). */
        public int $facilitatorSteps,
        /** Суммарное время шагов, секунды. */
        public float $totalDuration,
        public int $inputTokens,
        public int $outputTokens,
        public int $cacheReadTokens,
        /** Доля кэша: cache_read / (cache_read + input); null — знаменатель равен нулю. */
        public ?float $cacheHitRate,
        public float $cost,
    ) {
    }
}
