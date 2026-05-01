<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain;

use LogicException;
use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\StepResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\StaticExecution\Application\Service\ExecuteStaticChainServiceInterface;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\ValueObject\StaticChainResultVo;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\ValueObject\StaticStepResultVo;

/**
 * Стратегия выполнения static-цепочки.
 *
 * Делегирует линейное выполнение шагов в ExecuteStaticChainServiceInterface (StaticExecution module).
 * Выполняет маппинг StaticChainResultVo → OrchestrateChainResultDto на границе модулей.
 * Resume не поддерживается — брошено LogicException.
 */
final readonly class StaticExecutionStrategy implements ExecutionStrategyInterface
{
    /** @var int Дефолтный таймаут (секунды) для static-цепочки при отсутствии CLI timeout */
    private const int DEFAULT_STATIC_TIMEOUT = 300;

    public function __construct(
        private ExecuteStaticChainServiceInterface $staticChainExecutor,
    ) {
    }

    #[Override]
    public function execute(ChainDefinitionVo $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        $result = $this->staticChainExecutor->execute(
            $chain,
            $command->task,
            $command->workingDir,
            $command->timeout ?? self::DEFAULT_STATIC_TIMEOUT,
            null, // static chains have no session-scoped audit log
            $command->noContextFiles,
        );

        return $this->toResultDto($result);
    }

    #[Override]
    public function resume(ChainDefinitionVo $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        throw new LogicException('Static chain does not support resume.');
    }

    #[Override]
    public function supports(ChainDefinitionVo $chain): bool
    {
        return $chain->getSharedDefinition()->getType() === ChainTypeEnum::staticType;
    }

    /**
     * Маппит StaticChainResultVo (StaticExecution Domain) → OrchestrateChainResultDto (Orchestrator Application).
     *
     * Integration-маппинг на границе модулей: StaticExecution VO → Orchestrator DTO.
     *
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
