<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\Service\Chain;

use LogicException;
use Override;
use Psr\Log\LoggerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Contract\Chain\ExecutionStrategyInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\StepResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainExecutionTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Chain\Hook\HookExecutorInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Condition\EvaluateConditionServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\ExecuteConditionalStepServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Integration\ChainDefinitionProviderInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ConditionalStepResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionChainInfoVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionConditionalChainConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStepVo;

/**
 * Стратегия выполнения conditional-цепочки.
 *
 * Линейное выполнение шагов с условным ветвлением (when-expressions).
 * Конфигурация загружается через ChainDefinitionProviderInterface по имени цепочки.
 * Resume не поддерживается — LogicException.
 */
final readonly class ConditionalExecutionStrategy implements ExecutionStrategyInterface
{
    private const int DEFAULT_CONDITIONAL_TIMEOUT = 300;

    public function __construct(
        private EvaluateConditionServiceInterface $conditionEvaluator,
        private ExecuteConditionalStepServiceInterface $stepExecutor,
        private HookExecutorInterface $hookExecutor,
        private ChainDefinitionProviderInterface $chainProvider,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function execute(ExecutionChainInfoVo $chainInfo, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        $config = $this->chainProvider->loadConditionalChainConfig($chainInfo->name);
        $steps = $config->steps;
        $timeout = $command->timeout ?? $config->timeout ?? self::DEFAULT_CONDITIONAL_TIMEOUT;

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
                $config,
                $command,
                $context,
                $timeout,
                $stepResults,
            );

            $stepResults[] = $stepResult;

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
    public function resume(ExecutionChainInfoVo $chainInfo, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        throw new LogicException('Conditional chain does not support resume.');
    }

    #[Override]
    public function supports(ExecutionChainInfoVo $chainInfo): bool
    {
        return $chainInfo->type === ChainExecutionTypeEnum::conditionalType;
    }

    // ─── Private helpers ──────────────────────────────────────────────────

    /**
     * @param array<string, array{passed: bool, exitCode: int, status: string}> $context
     * @param list<StepResultDto> $previousResults
     */
    private function processStep(
        ExecutionStepVo $step,
        int $stepIndex,
        ExecutionConditionalChainConfigVo $config,
        OrchestrateChainCommand $command,
        array $context,
        int $timeout,
        array $previousResults,
    ): StepResultDto {
        $when = $step->getWhen();
        if ($when !== null) {
            $shouldExecute = $this->conditionEvaluator->evaluate($when, $context);

            if (!$shouldExecute) {
                $this->logSkippedStep($step, $stepIndex, $when->getRawExpression());

                return $this->createSkippedResult($step);
            }
        }

        $previousContext = $this->buildPreviousContext($previousResults);
        $role = $step->isAgent() ? ($step->getRole() ?? '') : 'quality_gate';
        $roleConfig = $config->getRoleConfig($role);

        $result = $this->stepExecutor->executeStep(
            $step,
            $command->task,
            $command->workingDir,
            $timeout,
            $previousContext,
            $roleConfig,
            $command->noContextFiles,
        );

        $stepResultDto = $this->toStepResultDto($result);

        $this->executePostStepHook($step, $config->name, $stepResultDto);

        return $stepResultDto;
    }

    private function executePostStepHook(ExecutionStepVo $step, string $chainName, StepResultDto $stepResult): void
    {
        $postStep = $step->getPostStep();
        if ($postStep === null) {
            return;
        }

        $hookContext = [
            'chain_name' => $chainName,
            'step_name' => $step->getName(),
            'runner' => $step->isAgent() ? $step->getRunner() : 'shell',
            'role' => $step->isAgent() ? ($step->getRole() ?? '') : 'quality_gate',
            'exit_code' => $stepResult->exitCode,
            'duration' => $stepResult->duration,
        ];

        $hookResult = $this->hookExecutor->execute($postStep, $hookContext);

        if ($hookResult->isWarning()) {
            $this->logger?->warning('Post-step hook failed (chain continues)', [
                'chain' => $chainName,
                'step' => $step->getName(),
                'hook' => $postStep,
                'reason' => $hookResult->getWarningReason(),
                'exitCode' => $hookResult->getExitCode(),
            ]);
        }
    }

    private function createSkippedResult(ExecutionStepVo $step): StepResultDto
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

    private function logSkippedStep(ExecutionStepVo $step, int $stepIndex, string $rawExpression): void
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
