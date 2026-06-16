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
     * @techdebt 2026-06-16 (CR-1, code review Пуаро): 300 — intended default для static,
     *   вынесен в TASK-fix-static-timeout-default-300 как осознанный behavior change.
     *   Здесь оставлено 600 ради back-compat-нейтральности: из-за давнего CLI-бага
     *   (default --timeout=600) static-цепочки фактически всегда получали 600, поэтому
     *   возврат к 300 в этом fix-PR был бы тихим regression. Меняется на 300 отдельно.
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
        $config = $this->chainProvider->loadStaticChainConfig($chainInfo->name);

        $result = $this->staticChainExecutor->execute(
            $config,
            $command->task,
            $command->workingDir,
            $command->timeout ?? $config->timeout ?? self::DEFAULT_STATIC_TIMEOUT,
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
        return $chainInfo->type === ChainExecutionTypeEnum::staticType;
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
