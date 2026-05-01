<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Dto\ChainResultAuditDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainContextVo;

/**
 * Финализация dynamic-цикла: finalize turn, форматирование журнала, аудит.
 */
interface FinalizeDynamicLoopServiceInterface
{
    /**
     * Запускает finalize turn: вызывает агента для получения synthesis.
     */
    public function executeFinalizeTurn(
        ChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?AuditLoggerInterface $auditLogger,
    ): void;

    /**
     * Форматирует финальную запись facilitator journal и записывает в файл.
     */
    public function formatFinalJournal(DynamicLoopExecution $execution): void;

    /**
     * Строит audit DTO для логирования результата цепочки.
     */
    public function buildChainAuditDto(
        string $chainName,
        float $startTime,
        DynamicLoopExecution $execution,
    ): ChainResultAuditDto;
}
