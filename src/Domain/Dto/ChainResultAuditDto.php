<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Dto;

/**
 * DTO финального результата цепочки для audit-лога.
 *
 * Инкапсулирует агрегированные метрики выполнения цепочки
 * для передачи в AuditLoggerInterface::logChainResult().
 *
 * @param list<StepAuditStatusDto> $stepStatuses
 */
final readonly class ChainResultAuditDto
{
    /**
     * @param list<StepAuditStatusDto> $stepStatuses
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
