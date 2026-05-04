<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Dto\DynamicLoopAuditDto;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;

/**
 * Финализация dynamic-цикла: finalize turn, форматирование журнала, аудит.
 */
interface FinalizeDynamicLoopServiceInterface
{
    public function executeFinalizeTurn(
        DynamicLoopConfigVo $chain,
        DynamicLoopContextVo $context,
        DynamicLoopExecution $execution,
        ?DynamicLoopAuditLoggerInterface $auditLogger,
    ): void;

    public function formatFinalJournal(DynamicLoopExecution $execution): void;

    public function buildChainAuditDto(
        string $chainName,
        float $startTime,
        DynamicLoopExecution $execution,
    ): DynamicLoopAuditDto;
}
