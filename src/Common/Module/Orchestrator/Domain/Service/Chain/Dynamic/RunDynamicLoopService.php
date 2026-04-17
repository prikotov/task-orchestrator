<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Dto\ChainResultAuditDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Dto\StepAuditStatusDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Port\AgentRunnerPortInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Budget\CheckDynamicBudgetServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Session\ChainSessionLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicBudgetCheckVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainContextVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicLoopResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicRoundResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicTurnResultVo;
use Override;
use Psr\Log\LoggerInterface;

use function array_map;
use function count;
use function date;
use function in_array;
use function sprintf;
use function str_contains;

/**
 * Исполнитель dynamic-цикла: координирует facilitator/participant turns, бюджет.
 *
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @todo PHPMD bug: multi-file analysis inflates LOC counts. Recheck after PHPMD upgrade.
 */
final readonly class RunDynamicLoopService implements RunDynamicLoopServiceInterface
{
    public function __construct(
        private ExecuteDynamicTurnService $turnExecution,
        private CheckDynamicBudgetServiceInterface $budgetChecker,
        private FormatDynamicJournalServiceInterface $journal,
        private ChainSessionLoggerInterface $sessionLogger,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function execute(
        ChainDefinitionVo $chain,
        AgentRunnerPortInterface $runner,
        DynamicChainContextVo $context,
        int $startRound = 0,
        string $initialDiscussionHistory = '',
        string $initialFacilitatorJournal = '',
        ?AuditLoggerInterface $auditLogger = null,
    ): DynamicLoopResultVo {
        $execution = $this->initExecution(
            $startRound,
            $initialDiscussionHistory,
            $initialFacilitatorJournal,
        );
        $budget = $chain->getBudget();
        $startTime = microtime(true);

        $auditLogger?->logChainStart($chain->getName(), $context->topic);

        while ($execution->getParticipantRounds() < $context->maxRounds) {
            $execution->advanceStep();
            $execution->advanceRound();

            $facResult = $this->executeFacilitatorTurn(
                $chain,
                $runner,
                $context,
                $execution,
                $budget,
                $auditLogger,
            );
            if ($facResult->shouldBreak) {
                $this->applyBreakResult($execution, $facResult);
                break;
            }
            if ($facResult->synthesis !== null) {
                $execution->setSynthesis($facResult->synthesis);
                break;
            }

            $partResult = $this->executeParticipantTurn(
                $chain,
                $runner,
                $context,
                $execution,
                $budget,
                $auditLogger,
                $facResult->nextRole,
                $facResult->challenge,
            );
            if ($partResult->shouldBreak) {
                $this->applyBreakResult($execution, $partResult);
                break;
            }
        }

        $execution->markMaxRoundsReached(
            $execution->getSynthesis() === null
            && $execution->getParticipantRounds() >= $context->maxRounds,
        );

        if ($execution->isMaxRoundsReached() && $execution->getSynthesis() === null) {
            $this->executeFinalizeTurn(
                $chain,
                $runner,
                $context,
                $execution,
                $auditLogger,
            );
        }

        $finalJournal = $this->journal->formatFinalEntry(
            $execution->getFacilitatorJournal(),
            $execution->getTotals(),
            count($execution->getRoundResults()),
            $execution->getSynthesis(),
            $execution->isMaxRoundsReached(),
        );
        $execution->setFacilitatorJournal($finalJournal);
        $this->sessionLogger->writeContextFile(
            'facilitator_journal.md',
            $execution->getFacilitatorJournal(),
        );

        $auditLogger?->logChainResult(
            $this->buildChainAuditDto(
                $chain->getName(),
                $startTime,
                $execution,
            ),
        );

        return $execution->toLoopResultVo();
    }

    // ─── Turn executors ────────────────────────────────────────────────

    private function executeFacilitatorTurn(
        ChainDefinitionVo $chain,
        AgentRunnerPortInterface $runner,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?BudgetVo $budget,
        ?AuditLoggerInterface $auditLogger,
    ): DynamicTurnResultVo {
        [$turnResult, $facResponse] = $this->turnExecution->runFacilitatorStep(
            $chain,
            $runner,
            $context,
            $execution,
            $auditLogger,
        );

        $stepCost = $turnResult->agentResult->getCost();
        $execution->addRoleCost($context->facilitatorRole, $stepCost);

        $budgetCheck = $this->checkBudget(
            $execution,
            $budget,
            $context->facilitatorRole,
            $stepCost,
        );
        if ($budgetCheck !== null) {
            $this->applyBudgetCheck($execution, $budgetCheck);
            if ($budgetCheck->shouldBreak) {
                return new DynamicTurnResultVo(
                    shouldBreak: true,
                    interruptionReason: 'budget_exceeded',
                    budgetResult: $budgetCheck,
                );
            }
        }

        if ($turnResult->agentResult->isError()) {
            $reason = str_contains(
                $turnResult->agentResult->getErrorMessage() ?? '',
                'imed out',
            ) ? 'timeout' : 'agent_error';
            $this->sessionLogger->interruptSession($reason);

            return new DynamicTurnResultVo(
                shouldBreak: true,
                interruptionReason: $reason,
            );
        }

        if ($facResponse->isDone()) {
            return new DynamicTurnResultVo(
                synthesis: $facResponse->getSynthesis(),
            );
        }

        $nextRole = $facResponse->getNextRole();
        if (
            $nextRole !== null && in_array(
                $nextRole,
                $context->participants,
                true,
            )
        ) {
            $execution->appendFacilitatorSummary(sprintf(
                "Round %d: %s\n",
                $execution->getRound(),
                $nextRole,
            ));
        }

        return new DynamicTurnResultVo(
            nextRole: $nextRole,
            challenge: $facResponse->getChallenge(),
        );
    }

    private function executeParticipantTurn(
        ChainDefinitionVo $chain,
        AgentRunnerPortInterface $runner,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?BudgetVo $budget,
        ?AuditLoggerInterface $auditLogger,
        ?string $nextRole,
        ?string $challenge,
    ): DynamicTurnResultVo {
        if (
            $nextRole === null || !in_array(
                $nextRole,
                $context->participants,
                true,
            )
        ) {
            return new DynamicTurnResultVo();
        }
        if ($execution->getParticipantRounds() >= $context->maxRounds) {
            return new DynamicTurnResultVo();
        }
        $execution->advanceStep();
        $execution->advanceParticipantRounds();

        $turnResult = $this->turnExecution->runParticipantStep(
            $chain,
            $runner,
            $context,
            $execution,
            $auditLogger,
            $nextRole,
            $challenge,
        );

        $execution->appendDiscussionHistory(
            $this->journal->formatDiscussionEntry(
                $nextRole,
                $turnResult->agentResult->getOutputText(),
            ),
        );
        $execution->appendFacilitatorJournal(
            $this->journal->formatParticipantEntry(
                $nextRole,
                $turnResult->agentResult->getOutputText(),
                $execution->getStep(),
                $execution->getRound(),
            ),
        );
        $this->sessionLogger->writeContextFile(
            'discussion_history.md',
            $execution->getDiscussionHistory(),
        );
        $this->sessionLogger->writeContextFile(
            'facilitator_journal.md',
            $execution->getFacilitatorJournal(),
        );

        $stepCost = $turnResult->agentResult->getCost();
        $execution->addRoleCost($nextRole, $stepCost);

        $budgetCheck = $this->checkBudget(
            $execution,
            $budget,
            $nextRole,
            $stepCost,
        );
        if ($budgetCheck !== null) {
            $this->applyBudgetCheck($execution, $budgetCheck);
            if ($budgetCheck->shouldBreak) {
                return new DynamicTurnResultVo(
                    shouldBreak: true,
                    interruptionReason: 'budget_exceeded',
                    budgetResult: $budgetCheck,
                );
            }
        }
        if ($turnResult->agentResult->isError()) {
            $reason = str_contains(
                $turnResult->agentResult->getErrorMessage() ?? '',
                'imed out',
            ) ? 'timeout' : 'agent_error';
            $this->sessionLogger->interruptSession($reason);

            return new DynamicTurnResultVo(
                shouldBreak: true,
                interruptionReason: $reason,
            );
        }

        return new DynamicTurnResultVo();
    }

    private function executeFinalizeTurn(
        ChainDefinitionVo $chain,
        AgentRunnerPortInterface $runner,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?AuditLoggerInterface $auditLogger,
    ): void {
        $execution->advanceStep();
        $execution->advanceRound();

        $turnResult = $this->turnExecution->runFinalizeStep(
            $chain,
            $runner,
            $context,
            $execution,
            $auditLogger,
        );

        $dur = round($turnResult->duration, 1);
        $execution->appendFacilitatorJournal(sprintf(
            "[%s %s] Step %d | Round %d | %s → synthesis (финализация)\n",
            date('Y-m-d'),
            date('H:i'),
            $execution->getStep(),
            $execution->getRound(),
            $turnResult->duration !== 0.0 ? "{$dur}s" : '',
        ));
        $this->sessionLogger->writeContextFile(
            'facilitator_journal.md',
            $execution->getFacilitatorJournal(),
        );

        $execution->setSynthesis($turnResult->agentResult->getOutputText());
    }

    // ─── State management ──────────────────────────────────────────────

    /**
     * @psalm-suppress ArgumentTypeCoercion getRoundFiles() returns array<int, array{...}> not list<array{...}>
     */
    private function initExecution(
        int $startRound,
        string $initialDiscussionHistory,
        string $initialFacilitatorJournal,
    ): DynamicLoopExecution {
        $execution = new DynamicLoopExecution(
            startRound: $startRound,
            initialDiscussionHistory: $initialDiscussionHistory,
            initialFacilitatorJournal: $initialFacilitatorJournal,
        );

        if ($startRound > 0) {
            $roundFiles = $this->sessionLogger->getRoundFiles();
            $execution->restoreFromRoundFiles($roundFiles);
        }

        return $execution;
    }

    private function checkBudget(
        DynamicLoopExecution $execution,
        ?BudgetVo $budget,
        string $role,
        float $stepCost,
    ): ?DynamicBudgetCheckVo {
        return $this->budgetChecker->checkAfterTurn(
            $budget,
            $execution->getTotalCost(),
            $execution->getRoleCosts(),
            $role,
            $stepCost,
            $execution->isBudgetWarning80Logged(),
        );
    }

    private function applyBudgetCheck(
        DynamicLoopExecution $execution,
        DynamicBudgetCheckVo $budgetCheck,
    ): void {
        if ($budgetCheck->warning80Triggered) {
            $execution->markBudgetWarning80Logged();
        }
        if ($budgetCheck->warningMessage !== '') {
            $execution->appendFacilitatorJournal($budgetCheck->warningMessage);
            $this->sessionLogger->writeContextFile(
                'facilitator_journal.md',
                $execution->getFacilitatorJournal(),
            );
        }
    }

    private function applyBreakResult(
        DynamicLoopExecution $execution,
        DynamicTurnResultVo $result,
    ): void {
        $execution->setBudgetBreak($result->budgetResult);
        $execution->setInterruptionReason($result->interruptionReason);
        $execution->setSynthesis($result->synthesis);
    }

    // ─── Result builders ───────────────────────────────────────────────

    private function buildChainAuditDto(
        string $chainName,
        float $startTime,
        DynamicLoopExecution $execution,
    ): ChainResultAuditDto {
        return new ChainResultAuditDto(
            chainName: $chainName,
            totalDurationMs: (microtime(true) - $startTime) * 1000.0,
            totalInputTokens: $execution->getTotals()['in'],
            totalOutputTokens: $execution->getTotals()['out'],
            totalCost: $execution->getTotals()['cost'],
            budgetExceeded: $execution->getBudgetBreak()?->budgetExceeded ?? false,
            stepsCount: count($execution->getRoundResults()),
            stepStatuses: array_map(
                static fn(DynamicRoundResultVo $round): StepAuditStatusDto => new StepAuditStatusDto(
                    $round->isError,
                ),
                $execution->getRoundResults(),
            ),
        );
    }
}
