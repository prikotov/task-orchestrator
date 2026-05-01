<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\StaticExecution\Domain\ValueObject;

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
        public string $chainName,
        public float $totalDurationMs,
        public int $totalInputTokens,
        public int $totalOutputTokens,
        public float $totalCost,
        public bool $budgetExceeded,
        public int $stepsCount,
        public array $stepStatuses,
    ) {
    }
}
