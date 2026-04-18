<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicRoundResultVo;

/**
 * Записывает раунд dynamic-цикла: накопление метрик в entity, аудит, уведомление о завершении.
 */
interface RecordDynamicRoundServiceInterface
{
    public function record(
        DynamicLoopExecution $execution,
        int $step,
        int $round,
        string $chainName,
        string $runnerName,
        string $role,
        bool $isFacilitator,
        DynamicRoundResultVo $roundResult,
        ?string $nextRole = null,
        bool $done = false,
        ?string $synthesis = null,
        ?AuditLoggerInterface $auditLogger = null,
    ): void;
}
