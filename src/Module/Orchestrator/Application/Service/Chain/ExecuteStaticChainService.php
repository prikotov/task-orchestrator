<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\StepResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Static\RunStaticChainService;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\StaticChainResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\StaticStepResultVo;

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
        string $task,
        ?string $workingDir = null,
        int $timeout = 300,
        ?AuditLoggerInterface $auditLogger = null,
        bool $noContextFiles = false,
    ): OrchestrateChainResultDto {
        $result = $this->staticChainRunner->execute(
            $chain,
            $task,
            $workingDir,
            $timeout,
            $auditLogger,
            $noContextFiles,
        );

        return $this->toResultDto($result);
    }

    /**
     * @psalm-suppress ArgumentTypeCoercion array_map on StaticChainResultVo::$stepResults
     */
    private function toResultDto(StaticChainResultVo $result): OrchestrateChainResultDto
    {
        $stepDtos = array_map(
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
                timedOut: $step->timedOut,
            ),
            $result->stepResults,
        );

        // Цепочка timedOut, если хотя бы один шаг завершился по таймауту
        $chainTimedOut = array_any(
            $result->stepResults,
            static fn(StaticStepResultVo $step): bool => $step->timedOut,
        );

        return new OrchestrateChainResultDto(
            stepResults: $stepDtos,
            totalTime: $result->totalTime,
            totalInputTokens: $result->totalInputTokens,
            totalOutputTokens: $result->totalOutputTokens,
            totalCost: $result->totalCost,
            budgetExceeded: $result->budgetExceeded,
            budgetLimit: $result->budgetLimit,
            budgetExceededRole: $result->budgetExceededRole,
            totalIterations: $result->totalIterations,
            timedOut: $chainTimedOut,
        );
    }
}
