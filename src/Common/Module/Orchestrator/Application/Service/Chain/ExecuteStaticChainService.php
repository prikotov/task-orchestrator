<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\StepResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Port\AgentRunnerPortInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Static\RunStaticChainService;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\StaticChainResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\StaticStepResultVo;
use Override;

/**
 * Application-обёртка: делегирует static-chain выполнение в Domain-сервис,
 * конвертирует Domain VO → Application DTO.
 */
final readonly class ExecuteStaticChainService implements ExecuteStaticChainServiceInterface
{
    public function __construct(
        private RunStaticChainService $staticChainRunner,
    ) {
    }

    #[Override]
    public function execute(
        ChainDefinitionVo $chain,
        AgentRunnerPortInterface $runner,
        string $runnerName,
        string $task,
        ?string $model = null,
        ?string $workingDir = null,
        int $timeout = 300,
        ?AuditLoggerInterface $auditLogger = null,
    ): OrchestrateChainResultDto {
        $result = $this->staticChainRunner->execute(
            $chain,
            $runner,
            $runnerName,
            $task,
            $model,
            $workingDir,
            $timeout,
            $auditLogger,
        );

        return $this->toResultDto($result);
    }

    /**
     * @psalm-suppress ArgumentTypeCoercion array_map on StaticChainResultVo::$stepResults
     */
    private function toResultDto(StaticChainResultVo $result): OrchestrateChainResultDto
    {
        return new OrchestrateChainResultDto(
            stepResults: array_map(
                static fn(StaticStepResultVo $step): StepResultDto => new StepResultDto(
                    role: $step->role,
                    runner: $step->runner,
                    outputText: $step->outputText,
                    inputTokens: $step->inputTokens,
                    outputTokens: $step->outputTokens,
                    cost: $step->cost,
                    duration: $step->duration,
                    isError: $step->isError,
                    errorMessage: $step->errorMessage,
                    fallbackRunnerUsed: $step->fallbackRunnerUsed,
                    iterationNumber: $step->iterationNumber,
                    iterationWarning: $step->iterationWarning,
                    passed: $step->passed,
                    exitCode: $step->exitCode,
                    label: $step->label,
                ),
                $result->stepResults,
            ),
            totalTime: $result->totalTime,
            totalInputTokens: $result->totalInputTokens,
            totalOutputTokens: $result->totalOutputTokens,
            totalCost: $result->totalCost,
            budgetExceeded: $result->budgetExceeded,
            budgetLimit: $result->budgetLimit,
            budgetExceededRole: $result->budgetExceededRole,
            totalIterations: $result->totalIterations,
        );
    }
}
