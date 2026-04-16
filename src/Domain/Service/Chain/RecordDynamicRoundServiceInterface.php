<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Service\Chain;

use TasK\Orchestrator\Domain\Entity\DynamicLoopExecution;
use TasK\Orchestrator\Domain\ValueObject\DynamicRoundResultVo;

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
