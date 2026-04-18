<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicRoundResultVo;
use Override;

/**
 * Записывает раунд dynamic-цикла: накопление метрик в entity, аудит, уведомление о завершении.
 */
final readonly class RecordDynamicRoundService implements RecordDynamicRoundServiceInterface
{
    public function __construct(
        private ChainSessionLoggerInterface $sessionLogger,
        private RoundCompletedNotifierInterface $roundNotifier,
    ) {
    }

    #[Override]
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
    ): void {
        $execution->recordRound($roundResult);

        $this->roundNotifier->notifyRoundCompleted(
            step: $step,
            round: $round,
            role: $role,
            isFacilitator: $isFacilitator,
            isError: $roundResult->isError,
            errorMessage: $roundResult->errorMessage,
            duration: $roundResult->duration,
            inputTokens: $roundResult->inputTokens,
            outputTokens: $roundResult->outputTokens,
            cost: $roundResult->cost,
            nextRole: $nextRole,
            done: $done,
            synthesis: $synthesis,
        );

        $auditLogger?->logStepResult(
            $chainName,
            $step,
            $role,
            $runnerName,
            $this->createDynamicAgentResult($roundResult),
            $roundResult->duration * 1000.0,
        );

        $this->sessionLogger->logRound(
            $step,
            $round,
            $role,
            $isFacilitator,
            $roundResult->systemPrompt,
            $roundResult->userPrompt,
            $roundResult->outputText,
            $roundResult->duration,
            $roundResult->inputTokens,
            $roundResult->outputTokens,
            $roundResult->cost,
            $roundResult->invocation,
        );
        $this->sessionLogger->updateSessionState($step);
    }

    private function createDynamicAgentResult(DynamicRoundResultVo $roundResult): AgentResultVo
    {
        if ($roundResult->isError) {
            return AgentResultVo::createFromError($roundResult->errorMessage ?? 'unknown');
        }

        return AgentResultVo::createFromSuccess(
            $roundResult->outputText,
            $roundResult->inputTokens,
            $roundResult->outputTokens,
            cost: $roundResult->cost,
        );
    }
}
