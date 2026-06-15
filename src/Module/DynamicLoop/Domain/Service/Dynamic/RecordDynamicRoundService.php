<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic;

use Override;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session\DynamicLoopSessionWriterInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicRoundResultVo;

/**
 * Записывает раунд dynamic-цикла: накопление метрик в entity, аудит, уведомление о завершении.
 */
final readonly class RecordDynamicRoundService implements RecordDynamicRoundServiceInterface
{
    public function __construct(
        private DynamicLoopSessionWriterInterface $sessionWriter,
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
        ?DynamicLoopAuditLoggerInterface $auditLogger = null,
    ): void {
        $execution->getMetrics()->recordRound($roundResult);

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

        $auditLogger?->logDynamicStepResult(
            $chainName,
            $step,
            $role,
            $runnerName,
            $roundResult,
            $roundResult->duration * 1000.0,
        );

        $this->sessionWriter->logRound(
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
        $this->sessionWriter->updateSessionState($step);
    }
}
