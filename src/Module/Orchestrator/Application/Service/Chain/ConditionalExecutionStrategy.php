<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain;

use LogicException;
use Override;
use Psr\Log\LoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\StepResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Condition\EvaluateConditionServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\ExecuteConditionalStepServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ConditionalStepResultVo;

/**
 * Стратегия выполнения conditional-цепочки.
 *
 * Линейное выполнение шагов с условным ветвлением (when-expressions):
 * iterate steps → evaluate condition → execute/skip → collect results.
 * Resume не поддерживается — LogicException.
 */
final readonly class ConditionalExecutionStrategy implements ExecutionStrategyInterface
{
    /** @var int Дефолтный таймаут (секунды) для conditional-цепочки */
    private const int DEFAULT_CONDITIONAL_TIMEOUT = 300;

    public function __construct(
        private EvaluateConditionServiceInterface $conditionEvaluator,
        private ExecuteConditionalStepServiceInterface $stepExecutor,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function execute(ChainDefinitionVo $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        $steps = $chain->getSteps();
        $shared = $chain->getSharedDefinition();
        $timeout = $command->timeout ?? $shared->getTimeout() ?? self::DEFAULT_CONDITIONAL_TIMEOUT;

        /** @var list<StepResultDto> $stepResults */
        $stepResults = [];
        /** @var array<string, array{passed: bool, exitCode: int, status: string}> $context */
        $context = [];
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $totalCost = 0.0;
        $startTime = microtime(true);
        $chainTimedOut = false;

        foreach ($steps as $stepIndex => $step) {
            $stepResult = $this->processStep(
                $step,
                $stepIndex,
                $chain,
                $command,
                $context,
                $timeout,
                $stepResults,
            );

            $stepResults[] = $stepResult;

            // Обновляем контекст для следующих шагов
            if ($step->getName() !== null) {
                $context[$step->getName()] = [
                    'passed' => $stepResult->passed,
                    'exitCode' => $stepResult->exitCode,
                    'status' => $this->resolveStepStatus($stepResult),
                ];
            }

            if (!$stepResult->skipped) {
                $totalInputTokens += $stepResult->inputTokens;
                $totalOutputTokens += $stepResult->outputTokens;
                $totalCost += $stepResult->cost;
                if ($stepResult->timedOut) {
                    $chainTimedOut = true;
                }
            }
        }

        return new OrchestrateChainResultDto(
            stepResults: $stepResults,
            totalTime: microtime(true) - $startTime,
            totalInputTokens: $totalInputTokens,
            totalOutputTokens: $totalOutputTokens,
            totalCost: $totalCost,
            timedOut: $chainTimedOut,
        );
    }

    #[Override]
    public function resume(ChainDefinitionVo $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        throw new LogicException('Conditional chain does not support resume.');
    }

    #[Override]
    public function supports(ChainDefinitionVo $chain): bool
    {
        return $chain->getSharedDefinition()->getType() === ChainTypeEnum::conditionalType;
    }

    /**
     * Обрабатывает один шаг: evaluate condition → execute or skip.
     *
     * @param array<string, array{passed: bool, exitCode: int, status: string}> $context
     * @param list<StepResultDto> $previousResults
     */
    private function processStep(
        ChainStepVo $step,
        int $stepIndex,
        ChainDefinitionVo $chain,
        OrchestrateChainCommand $command,
        array $context,
        int $timeout,
        array $previousResults,
    ): StepResultDto {
        // Evaluate condition (when-expression)
        $when = $step->getWhen();
        if ($when !== null) {
            $shouldExecute = $this->conditionEvaluator->evaluate($when, $context);

            if (!$shouldExecute) {
                $this->logSkippedStep($step, $stepIndex, $when->getRawExpression());

                return $this->createSkippedResult($step);
            }
        }

        // Execute step
        $previousContext = $this->buildPreviousContext($previousResults);
        $role = $step->isAgent() ? ($step->getRole() ?? '') : 'quality_gate';
        $roleConfig = $chain->getSharedDefinition()->getRoleConfig($role);

        $result = $this->stepExecutor->executeStep(
            $step,
            $command->task,
            $command->workingDir,
            $timeout,
            $previousContext,
            $roleConfig,
            $command->noContextFiles,
        );

        return $this->toStepResultDto($result);
    }

    /**
     * Создаёт StepResultDto с маркером skipped.
     */
    private function createSkippedResult(ChainStepVo $step): StepResultDto
    {
        $role = $step->isAgent() ? ($step->getRole() ?? '') : 'quality_gate';
        $runner = $step->isQualityGate() ? 'shell' : $step->getRunner();

        return new StepResultDto(
            role: $role,
            runner: $runner,
            outputText: '',
            inputTokens: 0,
            outputTokens: 0,
            cost: 0.0,
            duration: 0.0,
            isError: false,
            label: $step->getLabel(),
            skipped: true,
        );
    }

    /**
     * Маппит ConditionalStepResultVo → StepResultDto.
     */
    private function toStepResultDto(ConditionalStepResultVo $result): StepResultDto
    {
        return new StepResultDto(
            role: $result->role,
            runner: $result->runner,
            outputText: $result->outputText,
            inputTokens: $result->inputTokens,
            outputTokens: $result->outputTokens,
            cost: $result->cost,
            duration: $result->duration,
            isError: $result->isError,
            errorMessage: $result->errorMessage,
            passed: $result->passed,
            exitCode: $result->exitCode,
            label: $result->label,
            timedOut: $result->timedOut,
        );
    }

    /**
     * Собирает текстовый контекст из результатов предыдущих шагов.
     *
     * @param list<StepResultDto> $results
     */
    private function buildPreviousContext(array $results): ?string
    {
        if ($results === []) {
            return null;
        }

        $parts = [];
        foreach ($results as $result) {
            if ($result->skipped || $result->outputText === '') {
                continue;
            }
            $parts[] = $result->outputText;
        }

        return $parts === [] ? null : implode("\n\n", $parts);
    }

    /**
     * Определяет строковый статус шага для контекста.
     */
    private function resolveStepStatus(StepResultDto $step): string
    {
        if ($step->skipped) {
            return 'skipped';
        }

        if ($step->isError) {
            return 'error';
        }

        if ($step->role === 'quality_gate') {
            return $step->passed ? 'passed' : 'failed';
        }

        return 'success';
    }

    private function logSkippedStep(ChainStepVo $step, int $stepIndex, string $rawExpression): void
    {
        $stepName = $step->getName() ?? "#{$stepIndex}";
        $role = $step->isAgent() ? ($step->getRole() ?? '') : 'quality_gate';

        $this->logger?->info(sprintf(
            '[ConditionalChain] Step "%s" (role: %s) skipped — condition not met: "%s".',
            $stepName,
            $role,
            $rawExpression,
        ));
    }
}
