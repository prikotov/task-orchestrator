<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\Service\Chain;

use LogicException;
use Override;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Contract\Chain\ExecutionStrategyInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Service\ExecuteStaticChainServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\StepResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Chain\ChainConfigMapperInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticChainResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticStepResultVo;

/**
 * Стратегия выполнения static-цепочки.
 *
 * Делегирует линейное выполнение шагов в ExecuteStaticChainServiceInterface.
 * Маппит StaticChainDefinitionVo → ExecutionStaticChainConfigVo на границе Application/Domain.
 * Resume не поддерживается — брошено LogicException.
 */
final readonly class StaticExecutionStrategy implements ExecutionStrategyInterface
{
    private const int DEFAULT_STATIC_TIMEOUT = 300;

    public function __construct(
        private ExecuteStaticChainServiceInterface $staticChainExecutor,
        private ChainConfigMapperInterface $definitionMapper,
    ) {
    }

    #[Override]
    public function execute(ChainDefinitionInterface $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        assert($chain instanceof StaticChainDefinitionVo);

        $config = $this->definitionMapper->mapStaticChain($chain);

        $result = $this->staticChainExecutor->execute(
            $config,
            $command->task,
            $command->workingDir,
            $command->timeout ?? self::DEFAULT_STATIC_TIMEOUT,
            null,
            $command->noContextFiles,
        );

        return $this->toResultDto($result);
    }

    #[Override]
    public function resume(ChainDefinitionInterface $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        throw new LogicException('Static chain does not support resume.');
    }

    #[Override]
    public function supports(ChainDefinitionInterface $chain): bool
    {
        return $chain->getType() === ChainTypeEnum::staticType;
    }

    /**
     * Маппит StaticChainResultVo → OrchestrateChainResultDto.
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
