<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopBudgetVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopTurnResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorResponseVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\TurnBreakVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\TurnContinueVo;

/**
 * Исполнитель шагов dynamic-цикла: полный turn + agent step runners.
 */
interface ExecuteDynamicTurnServiceInterface
{
    public function runFacilitatorTurn(
        DynamicLoopConfigVo $chain,
        DynamicLoopContextVo $context,
        DynamicLoopExecution $execution,
        ?DynamicLoopBudgetVo $budget,
        ?DynamicLoopAuditLoggerInterface $auditLogger,
    ): TurnContinueVo|TurnBreakVo;

    public function runParticipantTurn(
        DynamicLoopConfigVo $chain,
        DynamicLoopContextVo $context,
        DynamicLoopExecution $execution,
        ?DynamicLoopBudgetVo $budget,
        ?DynamicLoopAuditLoggerInterface $auditLogger,
        ?string $nextRole,
        ?string $challenge,
    ): TurnContinueVo|TurnBreakVo;

    /**
     * @return array{DynamicLoopTurnResultVo, FacilitatorResponseVo}
     */
    public function runFacilitatorStep(
        DynamicLoopConfigVo $chain,
        DynamicLoopContextVo $context,
        DynamicLoopExecution $execution,
        ?DynamicLoopAuditLoggerInterface $auditLogger,
    ): array;

    public function runParticipantStep(
        DynamicLoopConfigVo $chain,
        DynamicLoopContextVo $context,
        DynamicLoopExecution $execution,
        ?DynamicLoopAuditLoggerInterface $auditLogger,
        string $nextRole,
        ?string $challenge,
    ): DynamicLoopTurnResultVo;

    public function runFinalizeStep(
        DynamicLoopConfigVo $chain,
        DynamicLoopContextVo $context,
        DynamicLoopExecution $execution,
        ?DynamicLoopAuditLoggerInterface $auditLogger,
    ): DynamicLoopTurnResultVo;
}
