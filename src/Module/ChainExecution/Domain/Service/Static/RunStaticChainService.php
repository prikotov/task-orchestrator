<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static;

use Psr\Log\LoggerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Entity\StaticChainExecution;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Chain\Hook\HookExecutorInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionFixIterationGroupVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStaticChainConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticChainAuditVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticChainResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticProcessResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticStepAuditVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticStepResultVo;

/**
 * Доменная логика выполнения static-цепочки: линейное выполнение шагов с итерациями и budget.
 *
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @todo PHPMD bug: multi-file analysis counts 82 LOC for processStep(), single-file = 74. Recheck after PHPMD upgrade.
 */
final readonly class RunStaticChainService
{
    public function __construct(
        private ExecuteStaticStepService $stepExecution,
        private CheckStaticBudgetServiceInterface $budgetService,
        private HookExecutorInterface $hookExecutor,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return StaticChainResultVo
     */
    public function execute(
        ExecutionStaticChainConfigVo $chain,
        string $task,
        ?string $workingDir = null,
        int $timeout = 300,
        ?StaticAuditServiceInterface $auditService = null,
        bool $noContextFiles = false,
    ): StaticChainResultVo {
        $steps = $chain->steps;
        $fixIterations = $chain->fixIterations;
        $nameToIndexMap = $this->buildNameToIndexMap($steps);
        $groupForStep = $this->buildGroupForStepMap(
            $steps,
            $fixIterations,
        );
        $execution = new StaticChainExecution();
        /** @var list<StaticStepResultVo> $results */
        $results = [];
        $startTime = microtime(true);
        $auditService?->logChainStart($chain->name, $task);

        $stepCount = count($steps);
        while (!$execution->isComplete($stepCount)) {
            $stepResult = $this->processStep(
                $chain,
                $task,
                $workingDir,
                $timeout,
                $execution,
                $steps,
                $fixIterations,
                $groupForStep,
                $nameToIndexMap,
                $results, // @psalm-suppress ArgumentTypeCoercion loop reassignment widens type
                $auditService,
                $noContextFiles,
            );
            if ($stepResult === null) {
                break;
            }
            $results = $stepResult->results;
            if ($stepResult->shouldRetry) {
                continue;
            }
            if ($stepResult->shouldBreak) {
                break;
            }
        }

        $result = $this->buildResult(
            $chain->name,
            $startTime,
            $results, // @psalm-suppress ArgumentTypeCoercion loop reassignment widens type
            $execution,
            $auditService,
        );

        return $result;
    }

    /**
     * @param list<ExecutionStepVo> $steps
     * @param list<ExecutionFixIterationGroupVo> $fixIterations
     * @param array<int, ExecutionFixIterationGroupVo> $groupForStep
     * @param array<string, int> $nameToIndexMap
     * @param list<StaticStepResultVo> $results
     */
    private function processStep(
        ExecutionStaticChainConfigVo $chain,
        string $task,
        ?string $workingDir,
        int $timeout,
        StaticChainExecution $execution,
        array $steps,
        array $fixIterations,
        array $groupForStep,
        array $nameToIndexMap,
        array $results,
        ?StaticAuditServiceInterface $auditService,
        bool $noContextFiles = false,
    ): ?StaticProcessResultVo {
        $step = $steps[$execution->getStepIndex()];
        $budgetRole = ($step->isAgent() ? $step->getRole() : null) ?? 'quality_gate';

        if ($this->budgetService->shouldBreakBeforeStep($execution, $chain->budget, $budgetRole)) {
            return null;
        }

        $stepIndex1 = $execution->getStepIndex() + 1;
        $role = $step->isAgent() ? ($step->getRole() ?? '') : 'quality_gate';
        $stepResult = $this->executeStep(
            $step,
            $chain,
            $task,
            $workingDir,
            $timeout,
            $execution,
            $groupForStep,
            $auditService,
            $stepIndex1,
            $role,
            $noContextFiles,
        );

        $results[] = $stepResult;
        $execution->recordStep(
            $stepResult->outputText,
            $stepResult->inputTokens,
            $stepResult->outputTokens,
            $stepResult->cost,
            $stepResult->duration,
            $role,
        );

        $this->executePostStepHook($step, $chain->name, $stepResult);

        return $this->handlePostStep(
            $execution,
            $chain->budget,
            $budgetRole,
            $stepResult,
            $step,
            $fixIterations,
            $nameToIndexMap,
            $results,
        );
    }

    /**
     * @param list<ExecutionFixIterationGroupVo> $fixIterations
     * @param array<string, int> $nameToIndexMap
     * @param list<StaticStepResultVo> $results
     */
    private function handlePostStep(
        StaticChainExecution $execution,
        ?\TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionBudgetVo $budget,
        string $budgetRole,
        StaticStepResultVo $stepResult,
        ExecutionStepVo $step,
        array $fixIterations,
        array $nameToIndexMap,
        array $results,
    ): ?StaticProcessResultVo {
        if (
            $this->budgetService->shouldBreakAfterStep(
                $execution,
                $budget,
                $budgetRole,
                $stepResult->cost,
            )
        ) {
            return new StaticProcessResultVo($results, false, true);
        }
        if ($stepResult->isError) {
            return new StaticProcessResultVo($results, false, true);
        }

        $retryGroup = $execution->findRetryGroup($step, $fixIterations);
        if ($retryGroup !== null && $execution->shouldRetryGroup($retryGroup)) {
            $execution->executeGroupRetry($retryGroup, $nameToIndexMap);
            $this->logRetryGroupIteration($execution, $retryGroup);

            return new StaticProcessResultVo($results, true);
        }
        if ($retryGroup !== null) {
            $results = $this->markIterationWarning($results);
            $this->logMaxIterationsReached($retryGroup);
        }
        $execution->advance();

        return new StaticProcessResultVo($results, false);
    }

    /**
     * @param array<int, ExecutionFixIterationGroupVo> $groupForStep
     */
    private function executeStep(
        ExecutionStepVo $step,
        ExecutionStaticChainConfigVo $chain,
        string $task,
        ?string $workingDir,
        int $timeout,
        StaticChainExecution $execution,
        array $groupForStep,
        ?StaticAuditServiceInterface $auditService,
        int $stepIndex1,
        string $role,
        bool $noContextFiles = false,
    ): StaticStepResultVo {
        if ($step->isQualityGate()) {
            $auditService?->logStepStart(
                $chain->name,
                $stepIndex1,
                $role,
                'shell',
            );
            $stepResult = $this->stepExecution->runQualityGate($step);
            $auditService?->logStepResult(
                $chain->name,
                $stepIndex1,
                $role,
                'shell',
                $stepResult,
                $stepResult->duration * 1000.0,
            );

            return $stepResult;
        }

        $iterationGroup = $groupForStep[$execution->getStepIndex()] ?? null;
        $iterationNumber = $iterationGroup !== null
            ? $execution->getIterationNumber($iterationGroup->getGroup()) : null;
        $roleConfig = $chain->getRoleConfig($role);
        $runnerName = $step->getRunner();
        $auditService?->logStepStart(
            $chain->name,
            $stepIndex1,
            $role,
            $runnerName,
        );

        $stepResult = $this->stepExecution->runAgentStep(
            $step,
            $task,
            $workingDir,
            $timeout,
            $execution->getPreviousContext(),
            $iterationNumber,
            $roleConfig,
            $noContextFiles,
        );
        $auditService?->logStepResult(
            $chain->name,
            $stepIndex1,
            $role,
            $runnerName,
            $stepResult,
            $stepResult->duration * 1000.0,
        );

        return $stepResult;
    }

    /**
     * @param list<StaticStepResultVo> $results
     */
    private function buildResult(
        string $chainName,
        float $startTime,
        array $results,
        StaticChainExecution $execution,
        ?StaticAuditServiceInterface $auditService,
    ): StaticChainResultVo {
        $auditService?->logChainResult(new StaticChainAuditVo(
            chainName: $chainName,
            totalDurationMs: (microtime(true) - $startTime) * 1000.0,
            totalInputTokens: $execution->getTotalInputTokens(),
            totalOutputTokens: $execution->getTotalOutputTokens(),
            totalCost: $execution->getTotalCost(),
            budgetExceeded: $execution->isBudgetExceeded(),
            stepsCount: count($results),
            stepStatuses: array_map(
                static fn(StaticStepResultVo $step): StaticStepAuditVo => new StaticStepAuditVo($step->isError),
                $results,
            ),
        ));

        return new StaticChainResultVo(
            stepResults: $results,
            totalTime: $execution->getTotalTime(),
            totalInputTokens: $execution->getTotalInputTokens(),
            totalOutputTokens: $execution->getTotalOutputTokens(),
            totalCost: $execution->getTotalCost(),
            budgetExceeded: $execution->isBudgetExceeded(),
            budgetLimit: $execution->getBudgetLimit(),
            budgetExceededRole: $execution->getBudgetExceededRole(),
            totalIterations: $execution->getTotalIterations(),
        );
    }

    /**
     * @param list<StaticStepResultVo> $results
     * @return list<StaticStepResultVo>
     */
    private function markIterationWarning(array $results): array
    {
        if ($results === []) {
            return $results;
        }
        $lastIndex = count($results) - 1;
        $last = $results[$lastIndex];
        if (!$last->iterationWarning) {
            $results[$lastIndex] = $last->withIterationWarning();
        }

        return $results;
    }

    /** @param list<ExecutionStepVo> $steps @return array<string, int> */
    private function buildNameToIndexMap(array $steps): array
    {
        $map = [];
        foreach ($steps as $index => $step) {
            $name = $step->getName();
            if ($name !== null) {
                $map[$name] = $index;
            }
        }

        return $map;
    }

    /**
     * @param list<ExecutionStepVo> $steps
     * @param list<ExecutionFixIterationGroupVo> $fixIterations
     * @return array<int, ExecutionFixIterationGroupVo>
     */
    private function buildGroupForStepMap(
        array $steps,
        array $fixIterations,
    ): array {
        $nameToIndex = $this->buildNameToIndexMap($steps);
        $map = [];
        foreach ($fixIterations as $group) {
            foreach ($group->getStepNames() as $stepName) {
                $index = $nameToIndex[$stepName] ?? null;
                if ($index !== null) {
                    $map[$index] = $group;
                }
            }
        }

        return $map;
    }

    private function logRetryGroupIteration(
        StaticChainExecution $execution,
        ExecutionFixIterationGroupVo $retryGroup,
    ): void {
        $this->logger?->info(sprintf(
            '[StaticChainExecutor] Iteration %d/%d for group "%s".',
            $execution->getIterationNumber($retryGroup->getGroup()) ?? 0,
            $retryGroup->getMaxIterations(),
            $retryGroup->getGroup(),
        ));
    }

    private function logMaxIterationsReached(
        ExecutionFixIterationGroupVo $retryGroup,
    ): void {
        $this->logger?->warning(sprintf(
            '[StaticChainExecutor] Max iterations (%d) reached for group "%s". Continuing chain.',
            $retryGroup->getMaxIterations(),
            $retryGroup->getGroup(),
        ));
    }

    /**
     * Выполняет post_step hook, если шаг его имеет.
     */
    private function executePostStepHook(ExecutionStepVo $step, string $chainName, StaticStepResultVo $stepResult): void
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
}
