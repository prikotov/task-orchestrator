<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainTurnResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainContextVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FacilitatorResponseVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\TurnBreakVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\TurnContinueVo;

/**
 * Исполнитель шагов dynamic-цикла: полный turn + agent step runners.
 */
interface ExecuteDynamicTurnServiceInterface
{
    /**
     * Выполняет полный facilitator turn (agent call + journal + budget + error handling).
     */
    public function runFacilitatorTurn(
        ChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?BudgetVo $budget,
        ?AuditLoggerInterface $auditLogger,
    ): TurnContinueVo|TurnBreakVo;

    /**
     * Выполняет полный participant turn (agent call + journal + budget + error handling).
     */
    public function runParticipantTurn(
        ChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?BudgetVo $budget,
        ?AuditLoggerInterface $auditLogger,
        ?string $nextRole,
        ?string $challenge,
    ): TurnContinueVo|TurnBreakVo;

    /**
     * Запускает facilitator agent step (низкоуровневый вызов).
     *
     * @return array{ChainTurnResultVo, FacilitatorResponseVo}
     */
    public function runFacilitatorStep(
        ChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?AuditLoggerInterface $auditLogger,
    ): array;

    /**
     * Запускает participant agent step (низкоуровневый вызов).
     */
    public function runParticipantStep(
        ChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?AuditLoggerInterface $auditLogger,
        string $nextRole,
        ?string $challenge,
    ): ChainTurnResultVo;

    /**
     * Запускает finalize agent step (низкоуровневый вызов).
     */
    public function runFinalizeStep(
        ChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?AuditLoggerInterface $auditLogger,
    ): ChainTurnResultVo;
}
