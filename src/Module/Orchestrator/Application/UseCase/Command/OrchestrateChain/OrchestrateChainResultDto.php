<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain;

/**
 * DTO результата оркестрации цепочки.
 *
 * Содержит результаты всех шагов/раундов и агрегированные метрики.
 * Поддерживает как static, так и dynamic цепочки.
 */
final readonly class OrchestrateChainResultDto
{
    /**
     * @param list<StepResultDto> $stepResults результаты шагов (static цепочки)
     * @param list<DynamicRoundResultDto> $roundResults результаты раундов (dynamic цепочки)
     * @param string|null $synthesis итоговый synthesis от фасилитатора (dynamic)
     * @param bool $maxRoundsReached достигнут ли лимит раундов (dynamic)
     */
    public function __construct(
        public array $stepResults = [],
        public array $roundResults = [],
        public float $totalTime = 0.0,
        public int $totalInputTokens = 0,
        public int $totalOutputTokens = 0,
        public float $totalCost = 0.0,
        public ?string $synthesis = null,
        public bool $maxRoundsReached = false,
        public ?string $sessionDir = null,
        public bool $budgetExceeded = false,
        public float $budgetLimit = 0.0,
        public ?string $budgetExceededRole = null,
        public int $totalIterations = 0,
        public bool $timedOut = false,
    ) {
    }
}
