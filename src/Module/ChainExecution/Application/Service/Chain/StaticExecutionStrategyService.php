<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\Service\Chain;

use LogicException;
use Override;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Service\Chain\StaticExecutionStrategyServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Service\ExecuteStaticChainServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\StepResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainExecutionTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\ChainDefinition\ChainDefinitionProviderInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionChainInfoVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticChainResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticStepResultVo;

/**
 * Стратегия выполнения static-цепочки.
 *
 * Делегирует линейное выполнение шагов в ExecuteStaticChainServiceInterface.
 * Конфигурация загружается через ChainDefinitionProviderInterface по имени цепочки.
 * Resume не поддерживается — брошено LogicException.
 */
final readonly class StaticExecutionStrategyService implements StaticExecutionStrategyServiceInterface
{
    /**
     * Жёсткий потолок по умолчанию для static-цепочек, сек.
     */
    private const int DEFAULT_STATIC_TIMEOUT = 600;

    public function __construct(
        private ExecuteStaticChainServiceInterface $staticChainExecutor,
        private ChainDefinitionProviderInterface $chainProvider,
    ) {
    }

    #[Override]
    public function execute(ExecutionChainInfoVo $chainInfo, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        $config = $this->chainProvider->loadStaticChainConfig(
            $chainInfo->getName(),
            // C3 — дефолтная сложность (единый контракт с навыком run-subagent);
            // совпадает с int $complexity = 3 в ChainDefinitionProviderInterface.
            $command->complexity ?? 3,
        );

        $result = $this->staticChainExecutor->execute(
            $config,
            $command->task,
            $command->workingDir,
            $command->timeout ?? $config->getTimeout() ?? self::DEFAULT_STATIC_TIMEOUT,
            null,
            $command->noContextFiles,
        );

        return $this->toResultDto($result);
    }

    #[Override]
    public function resume(ExecutionChainInfoVo $chainInfo, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        throw new LogicException('Static chain does not support resume.');
    }

    #[Override]
    public function supports(ExecutionChainInfoVo $chainInfo): bool
    {
        return $chainInfo->getType() === ChainExecutionTypeEnum::staticType;
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
                role: $step->getRole(),
                runner: $step->getRunner(),
                outputText: $step->getOutputText(),
                inputTokens: $step->getInputTokens(),
                outputTokens: $step->getOutputTokens(),
                cost: $step->getCost(),
                duration: $step->getDuration(),
                isError: $step->isError(),
                errorMessage: $step->getErrorMessage(),
                fallbackRunnerUsed: $step->getFallbackRunnerUsed(),
                iterationNumber: $step->getIterationNumber(),
                iterationWarning: $step->isIterationWarning(),
                passed: $step->isPassed(),
                exitCode: $step->getExitCode(),
                label: $step->getLabel(),
                timedOut: $step->isTimedOut(),
            ),
            $result->getStepResults(),
        );

        $chainTimedOut = array_any(
            $result->getStepResults(),
            static fn(StaticStepResultVo $step): bool => $step->isTimedOut(),
        );

        return new OrchestrateChainResultDto(
            stepResults: $stepDtos,
            totalTime: $result->getTotalTime(),
            totalInputTokens: $result->getTotalInputTokens(),
            totalOutputTokens: $result->getTotalOutputTokens(),
            totalCost: $result->getTotalCost(),
            budgetExceeded: $result->isBudgetExceeded(),
            budgetLimit: $result->getBudgetLimit(),
            budgetExceededRole: $result->getBudgetExceededRole(),
            totalIterations: $result->getTotalIterations(),
            timedOut: $chainTimedOut,
        );
    }
}
