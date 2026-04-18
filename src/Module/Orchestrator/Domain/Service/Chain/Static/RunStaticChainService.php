<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Static;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Dto\ChainResultAuditDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Dto\StepAuditStatusDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Entity\StaticChainExecution;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Integration\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FixIterationGroupVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\StaticChainResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\StaticProcessResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\StaticStepResultVo;
use Psr\Log\LoggerInterface;

use function count;
use function sprintf;

/**
 * Доменная логика выполнения static-цепочки: линейное выполнение шагов с итерациями и budget.
 *
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @todo PHPMD bug: multi-file analysis counts 82 LOC for processStep(), single-file = 74. Recheck after PHPMD upgrade.
 */
final readonly class RunStaticChainService
{
    public function __construct(
        private RunAgentServiceInterface $agentRunner,
        private ExecuteStaticStepService $stepExecution,
        private CheckStaticBudgetServiceInterface $budgetService,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return StaticChainResultVo
     */
    public function execute(
        ChainDefinitionVo $chain,
        string $runnerName,
        string $task,
        ?string $model = null,
        ?string $workingDir = null,
        int $timeout = 300,
        ?AuditLoggerInterface $auditLogger = null,
    ): StaticChainResultVo {
        $steps = $chain->getSteps();
        $fixIterations = $chain->getFixIterations();
        $nameToIndexMap = $this->buildNameToIndexMap($steps);
        $groupForStep = $this->buildGroupForStepMap(
            $steps,
            $fixIterations,
        );
        $execution = new StaticChainExecution();
        /** @var list<StaticStepResultVo> $results */
        $results = [];
        $startTime = microtime(true);
        $auditLogger?->logChainStart($chain->getName(), $task);

        while (!$execution->isComplete(count($steps))) {
            $stepResult = $this->processStep(
                $chain,
                $runnerName,
                $task,
                $model,
                $workingDir,
                $timeout,
                $execution,
                $steps,
                $fixIterations,
                $groupForStep,
                $nameToIndexMap,
                $results, // @psalm-suppress ArgumentTypeCoercion loop reassignment widens type
                $auditLogger,
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
            $chain->getName(),
            $startTime,
            $results, // @psalm-suppress ArgumentTypeCoercion loop reassignment widens type
            $execution,
            $auditLogger,
        );

        return $result;
    }

    /**
     * Обрабатывает один шаг в цикле: budget check → execute → record → iteration.
     *
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     * @param array<int, FixIterationGroupVo> $groupForStep
     * @param array<string, int> $nameToIndexMap
     * @param list<StaticStepResultVo> $results
     */
    private function processStep(
        ChainDefinitionVo $chain,
        string $runnerName,
        string $task,
        ?string $model,
        ?string $workingDir,
        int $timeout,
        StaticChainExecution $execution,
        array $steps,
        array $fixIterations,
        array $groupForStep,
        array $nameToIndexMap,
        array $results,
        ?AuditLoggerInterface $auditLogger,
    ): ?StaticProcessResultVo {
        $step = $steps[$execution->getStepIndex()];
        $budgetRole = ($step->isAgent() ? $step->getRole() : null) ?? 'quality_gate';

        if ($this->budgetService->shouldBreakBeforeStep($execution, $chain->getBudget(), $budgetRole)) {
            return null;
        }

        $stepIndex1 = $execution->getStepIndex() + 1;
        $role = $step->isAgent() ? ($step->getRole() ?? '') : 'quality_gate';
        $stepResult = $this->executeStep(
            $step,
            $chain,
            $runnerName,
            $task,
            $model,
            $workingDir,
            $timeout,
            $execution,
            $groupForStep,
            $auditLogger,
            $stepIndex1,
            $role,
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

        return $this->handlePostStep(
            $execution,
            $chain->getBudget(),
            $budgetRole,
            $stepResult,
            $step,
            $fixIterations,
            $nameToIndexMap,
            $results,
        );
    }

    /**
     * Проверяет бюджет, ошибки и retry-группы после выполнения шага.
     *
     * @param list<FixIterationGroupVo> $fixIterations
     * @param array<string, int> $nameToIndexMap
     * @param list<StaticStepResultVo> $results
     */
    private function handlePostStep(
        StaticChainExecution $execution,
        ?\TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\BudgetVo $budget,
        string $budgetRole,
        StaticStepResultVo $stepResult,
        ChainStepVo $step,
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
     * @param array<int, FixIterationGroupVo> $groupForStep
     */
    private function executeStep(
        ChainStepVo $step,
        ChainDefinitionVo $chain,
        string $runnerName,
        string $task,
        ?string $model,
        ?string $workingDir,
        int $timeout,
        StaticChainExecution $execution,
        array $groupForStep,
        ?AuditLoggerInterface $auditLogger,
        int $stepIndex1,
        string $role,
    ): StaticStepResultVo {
        if ($step->isQualityGate()) {
            $auditLogger?->logStepStart(
                $chain->getName(),
                $stepIndex1,
                $role,
                'shell',
            );
            $stepResult = $this->stepExecution->runQualityGate($step);
            $auditLogger?->logStepResult(
                $chain->getName(),
                $stepIndex1,
                $role,
                'shell',
                $this->stepExecution->createAgentResultFromStep(
                    $stepResult,
                ),
                $stepResult->duration * 1000.0,
            );

            return $stepResult;
        }

        $iterationGroup = $groupForStep[$execution->getStepIndex()] ?? null;
        $iterationNumber = $iterationGroup !== null
            ? $execution->getIterationNumber($iterationGroup->getGroup()) : null;
        $roleConfig = $chain->getRoleConfig($role);
        $auditLogger?->logStepStart(
            $chain->getName(),
            $stepIndex1,
            $role,
            $runnerName,
        );

        $stepResult = $this->stepExecution->runAgentStep(
            $step,
            $runnerName,
            $task,
            $model,
            $workingDir,
            $timeout,
            $execution->getPreviousContext(),
            $iterationNumber,
            $roleConfig,
        );
        $auditLogger?->logStepResult(
            $chain->getName(),
            $stepIndex1,
            $role,
            $runnerName,
            $this->stepExecution->createAgentResultFromStep(
                $stepResult,
            ),
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
        ?AuditLoggerInterface $auditLogger,
    ): StaticChainResultVo {
        $auditLogger?->logChainResult(new ChainResultAuditDto(
            chainName: $chainName,
            totalDurationMs: (microtime(true) - $startTime) * 1000.0,
            totalInputTokens: $execution->getTotalInputTokens(),
            totalOutputTokens: $execution->getTotalOutputTokens(),
            totalCost: $execution->getTotalCost(),
            budgetExceeded: $execution->isBudgetExceeded(),
            stepsCount: count($results),
            stepStatuses: array_map(
                static fn(StaticStepResultVo $step): StepAuditStatusDto => new StepAuditStatusDto($step->isError),
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

    /** @param list<ChainStepVo> $steps @return array<string, int> */
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
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     * @return array<int, FixIterationGroupVo>
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
        FixIterationGroupVo $retryGroup,
    ): void {
        $this->logger?->info(sprintf(
            '[StaticChainExecutor] Iteration %d/%d for group "%s".',
            $execution->getIterationNumber($retryGroup->getGroup()) ?? 0,
            $retryGroup->getMaxIterations(),
            $retryGroup->getGroup(),
        ));
    }

    private function logMaxIterationsReached(
        FixIterationGroupVo $retryGroup,
    ): void {
        $this->logger?->warning(sprintf(
            '[StaticChainExecutor] Max iterations (%d) reached for group "%s". Continuing chain.',
            $retryGroup->getMaxIterations(),
            $retryGroup->getGroup(),
        ));
    }
}
