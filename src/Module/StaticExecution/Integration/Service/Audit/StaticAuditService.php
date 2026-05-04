<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\StaticExecution\Integration\Service\Audit;

use Override;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Dto\ChainResultAuditDto;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Dto\StepAuditStatusDto;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\StaticAuditServiceInterface;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\ValueObject\StaticChainAuditVo;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\ValueObject\StaticStepResultVo;

/**
 * Integration-сервис: маппит StaticExecution VO → Orchestrator DTO
 * и делегирует в Orchestrator AuditLoggerInterface.
 *
 * Изолирует StaticExecution Domain от Orchestrator Domain DTO.
 */
final readonly class StaticAuditService implements StaticAuditServiceInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
    ) {
    }

    #[Override]
    public function logChainStart(string $chainName, string $task): void
    {
        $this->auditLogger->logChainStart($chainName, $task);
    }

    #[Override]
    public function logStepStart(string $chainName, int $stepNumber, string $role, string $runner): void
    {
        $this->auditLogger->logStepStart($chainName, $stepNumber, $role, $runner);
    }

    #[Override]
    public function logStepResult(
        string $chainName,
        int $stepNumber,
        string $role,
        string $runner,
        StaticStepResultVo $stepResult,
        float $durationMs,
    ): void {
        $this->auditLogger->logStepResult(
            $chainName,
            $stepNumber,
            $role,
            $runner,
            $this->mapToChainRunResult($stepResult),
            $durationMs,
        );
    }

    #[Override]
    public function logChainResult(StaticChainAuditVo $audit): void
    {
        $this->auditLogger->logChainResult(new ChainResultAuditDto(
            chainName: $audit->chainName,
            totalDurationMs: $audit->totalDurationMs,
            totalInputTokens: $audit->totalInputTokens,
            totalOutputTokens: $audit->totalOutputTokens,
            totalCost: $audit->totalCost,
            budgetExceeded: $audit->budgetExceeded,
            stepsCount: $audit->stepsCount,
            stepStatuses: array_map(
                static fn(\TaskOrchestrator\Common\Module\StaticExecution\Domain\ValueObject\StaticStepAuditVo $step): StepAuditStatusDto => new StepAuditStatusDto($step->isError),
                $audit->stepStatuses,
            ),
        ));
    }

    private function mapToChainRunResult(StaticStepResultVo $stepResult): ChainRunResultVo
    {
        if ($stepResult->isError) {
            return ChainRunResultVo::createFromError(
                $stepResult->errorMessage ?? 'unknown',
                timedOut: $stepResult->timedOut,
            );
        }

        return ChainRunResultVo::createFromSuccess(
            $stepResult->outputText,
            $stepResult->inputTokens,
            $stepResult->outputTokens,
            cost: $stepResult->cost,
        );
    }
}
