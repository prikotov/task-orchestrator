<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Domain\Service\Chain;

use TasK\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface;
use TasK\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TasK\Orchestrator\Domain\ValueObject\DynamicChainContextVo;
use TasK\Orchestrator\Domain\ValueObject\DynamicLoopResultVo;

/**
 * Исполнитель dynamic-цикла: фасилитатор + routing участников.
 */
interface RunDynamicLoopServiceInterface
{
    /**
     * Выполняет dynamic-цикл: фасилитатор решает в рантайме, кому дать слово.
     */
    public function execute(
        ChainDefinitionVo $chain,
        AgentRunnerInterface $runner,
        DynamicChainContextVo $context,
        int $startRound = 0,
        string $initialDiscussionHistory = '',
        string $initialFacilitatorJournal = '',
        ?AuditLoggerInterface $auditLogger = null,
    ): DynamicLoopResultVo;
}
