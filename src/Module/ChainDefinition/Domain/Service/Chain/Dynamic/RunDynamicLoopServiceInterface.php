<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\Dynamic;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainContextVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicLoopResultVo;

/**
 * Исполнитель dynamic-цикла: фасилитатор + routing участников.
 */
interface RunDynamicLoopServiceInterface
{
    /**
     * Выполняет dynamic-цикл: фасилитатор решает в рантайме, кому дать слово.
     */
    public function execute(
        DynamicChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        int $startRound = 0,
        string $initialDiscussionHistory = '',
        string $initialFacilitatorJournal = '',
        ?AuditLoggerInterface $auditLogger = null,
    ): DynamicLoopResultVo;
}
