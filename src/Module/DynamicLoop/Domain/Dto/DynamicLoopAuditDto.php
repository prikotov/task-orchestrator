<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Dto;

/**
 * DTO финального результата dynamic-цикла для audit-лога.
 *
 * Копия ChainDefinition\ChainResultAuditDto + StepAuditStatusDto,
 * без зависимости от ChainDefinition.Domain.
 */
final readonly class DynamicLoopAuditDto
{
    /**
     * @param list<DynLoopStepAuditDto> $stepStatuses
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
