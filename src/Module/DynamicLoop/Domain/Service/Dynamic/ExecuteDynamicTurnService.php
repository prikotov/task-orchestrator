<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic;

use Override;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Helper\DynamicRoleCommandHelper;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Helper\DynamicRoundResultHelper;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session\DynamicLoopSessionLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopBudgetVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRetryPolicyVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRoleConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopTurnResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorFinalizeRunContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorResponseVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorRunContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorTurnResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\ParticipantRunContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\TurnBreakVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\TurnContinueVo;

/**
 * Исполнитель шагов dynamic-цикла: полный turn (agent call + journal + budget + error).
 */
final readonly class ExecuteDynamicTurnService implements ExecuteDynamicTurnServiceInterface
{
    /** @var string Причина прерывания цикла по таймауту */
    private const string INTERRUPTION_REASON_TIMEOUT = 'timeout';

    public function __construct(
        private RunDynamicLoopAgentServiceInterface $agentRunner,
        private RecordDynamicRoundServiceInterface $roundRecorder,
        private FormatDynamicJournalServiceInterface $journal,
        private DynamicLoopSessionLoggerInterface $sessionLogger,
        private CheckDynamicLoopBudgetServiceInterface $budgetChecker,
    ) {
    }

    #[Override]
    public function runFacilitatorTurn(
        DynamicLoopConfigVo $chain,
        DynamicLoopContextVo $context,
        DynamicLoopExecution $execution,
        ?DynamicLoopBudgetVo $budget,
        ?DynamicLoopAuditLoggerInterface $auditLogger,
    ): TurnContinueVo|TurnBreakVo {
        [$turnResult, $facResponse] = $this->runFacilitatorStep(
            $chain,
            $context,
            $execution,
            $auditLogger,
        );

        $execution->getJournal()->appendDiscussionHistory(
            $this->journal->formatFacilitatorDiscussionEntry(
                $context->getFacilitatorRole(),
                $facResponse->isDone(),
                $facResponse->getNextRole(),
                $facResponse->getChallenge(),
                $facResponse->getSynthesis(),
            ),
        );

        $stepCost = $turnResult->getAgentResult()->getCost();
        $execution->getMetrics()->addRoleCost($context->getFacilitatorRole(), $stepCost);

        $budgetCheck = $this->budgetChecker->checkAndApply(
            $execution,
            $budget,
            $context->getFacilitatorRole(),
            $stepCost,
        );
        if ($budgetCheck !== null && $budgetCheck->isBreakNeeded()) {
            return new TurnBreakVo(
                interruptionReason: 'budget_exceeded',
                budgetResult: $budgetCheck,
            );
        }

        if ($turnResult->getAgentResult()->isError()) {
            $reason = $turnResult->getAgentResult()->isTimedOut()
                ? self::INTERRUPTION_REASON_TIMEOUT
                : 'agent_error';
            $this->sessionLogger->interruptSession($reason);

            return new TurnBreakVo(interruptionReason: $reason);
        }

        if ($facResponse->isDone()) {
            return new TurnBreakVo(synthesis: $facResponse->getSynthesis());
        }

        $nextRole = $facResponse->getNextRole();
        if (
            $nextRole !== null && in_array(
                $nextRole,
                $context->getParticipants(),
                true,
            )
        ) {
            $execution->getJournal()->appendFacilitatorSummary(sprintf(
                "Round %d: %s\n",
                $execution->getRound(),
                $nextRole,
            ));
        }

        return new TurnContinueVo(
            nextRole: $nextRole,
            challenge: $facResponse->getChallenge(),
        );
    }

    #[Override]
    public function runParticipantTurn(
        DynamicLoopConfigVo $chain,
        DynamicLoopContextVo $context,
        DynamicLoopExecution $execution,
        ?DynamicLoopBudgetVo $budget,
        ?DynamicLoopAuditLoggerInterface $auditLogger,
        ?string $nextRole,
        ?string $challenge,
    ): TurnContinueVo|TurnBreakVo {
        if (
            $nextRole === null || !in_array(
                $nextRole,
                $context->getParticipants(),
                true,
            )
        ) {
            return new TurnContinueVo();
        }
        if ($execution->getParticipantRounds() >= $context->getMaxRounds()) {
            return new TurnContinueVo();
        }
        $execution->advanceStep();
        $execution->advanceParticipantRounds();

        $turnResult = $this->runParticipantStep(
            $chain,
            $context,
            $execution,
            $auditLogger,
            $nextRole,
            $challenge,
        );

        $stepCost = $turnResult->getAgentResult()->getCost();
        $execution->getMetrics()->addRoleCost($nextRole, $stepCost);

        if ($turnResult->getAgentResult()->isError()) {
            $reason = $turnResult->getAgentResult()->isTimedOut()
                ? self::INTERRUPTION_REASON_TIMEOUT
                : 'agent_error';
            $this->sessionLogger->interruptSession($reason);

            return new TurnBreakVo(interruptionReason: $reason);
        }

        $this->recordParticipantTurnJournals($execution, $nextRole, $turnResult);

        $budgetCheck = $this->budgetChecker->checkAndApply(
            $execution,
            $budget,
            $nextRole,
            $stepCost,
        );
        if ($budgetCheck !== null && $budgetCheck->isBreakNeeded()) {
            return new TurnBreakVo(
                interruptionReason: 'budget_exceeded',
                budgetResult: $budgetCheck,
            );
        }

        return new TurnContinueVo();
    }

    #[Override]
    public function runFacilitatorStep(
        DynamicLoopConfigVo $chain,
        DynamicLoopContextVo $context,
        DynamicLoopExecution $execution,
        ?DynamicLoopAuditLoggerInterface $auditLogger,
    ): array {
        $facResponsePaths = $this->sessionLogger->getResponseFilePaths(
            $execution->getStep() - 1,
        );
        $facResponseFilesList = $this->buildResponseFilesList($facResponsePaths);
        $facRoleConfig = $chain->getRoleConfig($context->getFacilitatorRole());
        $facRunner = DynamicRoleCommandHelper::parseRunner($facRoleConfig);
        $auditLogger?->logStepStart(
            $chain->getName(),
            $execution->getStep(),
            $context->getFacilitatorRole(),
            $facRunner,
        );

        [$turnResult, $facResponse] = $this->callFacilitatorAgent(
            $context,
            $execution,
            $facResponseFilesList,
            $facRoleConfig,
            $chain->getDefaultRetryPolicy(),
        );

        $roundVo = DynamicRoundResultHelper::fromTurnResult(
            $turnResult,
            $execution->getStep(),
            $context->getFacilitatorRole(),
            true,
            DynamicRoleCommandHelper::parseThinking($facRoleConfig),
        );
        $facVo = new FacilitatorTurnResultVo(
            roundResult: $roundVo,
            done: $facResponse->isDone(),
            nextRole: $facResponse->getNextRole(),
            synthesis: $facResponse->getSynthesis(),
            challenge: $facResponse->getChallenge(),
            userPrompt: $turnResult->getUserPrompt(),
        );

        $this->roundRecorder->record(
            $execution,
            $execution->getStep(),
            $execution->getRound(),
            $chain->getName(),
            $facRunner,
            $context->getFacilitatorRole(),
            true,
            $roundVo,
            $facResponse->getNextRole(),
            $facResponse->isDone(),
            $facResponse->getSynthesis(),
            $auditLogger,
        );

        $execution->getJournal()->appendFacilitatorJournal(
            $this->journal->formatFacilitatorEntry(
                $execution->getStep(),
                $execution->getRound(),
                $facVo,
            ),
        );
        $this->sessionLogger->writeContextFile(
            'facilitator_journal.md',
            $execution->getFacilitatorJournal(),
        );

        return [$turnResult, $facResponse];
    }

    #[Override]
    public function runParticipantStep(
        DynamicLoopConfigVo $chain,
        DynamicLoopContextVo $context,
        DynamicLoopExecution $execution,
        ?DynamicLoopAuditLoggerInterface $auditLogger,
        string $nextRole,
        ?string $challenge,
    ): DynamicLoopTurnResultVo {
        $prevResponsePaths = $this->sessionLogger->getResponseFilePaths(
            $execution->getStep() - 1,
        );
        $responseFilesList = $this->buildResponseFilesList($prevResponsePaths);
        $partRoleConfig = $chain->getRoleConfig($nextRole);
        $partRunner = DynamicRoleCommandHelper::parseRunner($partRoleConfig);
        $auditLogger?->logStepStart(
            $chain->getName(),
            $execution->getStep(),
            $nextRole,
            $partRunner,
        );

        $turnResult = $this->agentRunner->runParticipant(
            step: $execution->getStep(),
            round: $execution->getRound(),
            role: $nextRole,
            topic: $context->getTopic(),
            runContext: new ParticipantRunContextVo(
                brainstormSystemPrompt: $context->getPromptConfiguration()->getBrainstormSystemPrompt(),
                participantAppendPrompt: $context->getPromptConfiguration()->getParticipantAppendPrompt(),
                participantUserPrompt: $context->getPromptConfiguration()->getParticipantUserPrompt(),
                workingDir: $context->getWorkingDir(),
                responseFilesList: $responseFilesList,
                timeout: $partRoleConfig?->getTimeout() ?? $context->getTimeout(),
                command: $partRoleConfig?->getCommand() ?? [],
                hasPreviousResponses: $prevResponsePaths !== [],
                challenge: $challenge,
                promptFile: $partRoleConfig?->getPromptFile(),
                retryPolicy: $chain->getDefaultRetryPolicy(),
            ),
        );
        $turnResult = DynamicRoundResultHelper::normalizeEmptyOutput($turnResult);

        $roundVo = DynamicRoundResultHelper::fromTurnResult(
            $turnResult,
            $execution->getStep(),
            $nextRole,
            false,
            DynamicRoleCommandHelper::parseThinking($partRoleConfig),
        );
        $this->roundRecorder->record(
            $execution,
            $execution->getStep(),
            $execution->getRound(),
            $chain->getName(),
            $partRunner,
            $nextRole,
            false,
            $roundVo,
            auditLogger: $auditLogger,
        );

        return $turnResult;
    }

    #[Override]
    public function runFinalizeStep(
        DynamicLoopConfigVo $chain,
        DynamicLoopContextVo $context,
        DynamicLoopExecution $execution,
        ?DynamicLoopAuditLoggerInterface $auditLogger,
    ): DynamicLoopTurnResultVo {
        $facResponsePaths = $this->sessionLogger->getResponseFilePaths(
            $execution->getStep() - 1,
        );
        $facResponseFilesList = $this->buildResponseFilesList($facResponsePaths);
        $finRoleConfig = $chain->getRoleConfig($context->getFacilitatorRole());
        $finRunner = DynamicRoleCommandHelper::parseRunner($finRoleConfig);
        $auditLogger?->logStepStart(
            $chain->getName(),
            $execution->getStep(),
            $context->getFacilitatorRole(),
            $finRunner,
        );

        $turnResult = $this->agentRunner->runFacilitatorFinalize(
            step: $execution->getStep(),
            round: $execution->getRound(),
            facilitatorRole: $context->getFacilitatorRole(),
            topic: $context->getTopic(),
            runContext: new FacilitatorFinalizeRunContextVo(
                brainstormSystemPrompt: $context->getPromptConfiguration()->getBrainstormSystemPrompt(),
                facilitatorAppendPrompt: $context->getPromptConfiguration()->getFacilitatorAppendPrompt(),
                facilitatorFinalizePrompt: $context->getPromptConfiguration()->getFacilitatorFinalizePrompt(),
                workingDir: $context->getWorkingDir(),
                responseFilesList: $facResponseFilesList,
                timeout: $finRoleConfig?->getTimeout() ?? $context->getTimeout(),
                command: $finRoleConfig?->getCommand() ?? [],
                retryPolicy: $chain->getDefaultRetryPolicy(),
            ),
        );

        $roundVo = DynamicRoundResultHelper::fromTurnResult(
            $turnResult,
            $execution->getStep(),
            $context->getFacilitatorRole(),
            true,
            DynamicRoleCommandHelper::parseThinking($finRoleConfig),
        );
        $this->roundRecorder->record(
            $execution,
            $execution->getStep(),
            $execution->getRound(),
            $chain->getName(),
            $finRunner,
            $context->getFacilitatorRole(),
            true,
            $roundVo,
            done: true,
            synthesis: $turnResult->getAgentResult()->getOutputText(),
            auditLogger: $auditLogger,
        );

        return $turnResult;
    }

    /**
     * Записывает вывод участника в дискуссионную историю, журнал фасилитатора и сессионные файлы.
     */
    private function recordParticipantTurnJournals(
        DynamicLoopExecution $execution,
        string $nextRole,
        DynamicLoopTurnResultVo $turnResult,
    ): void {
        $execution->getJournal()->appendDiscussionHistory(
            $this->journal->formatDiscussionEntry(
                $nextRole,
                $turnResult->getAgentResult()->getOutputText(),
            ),
        );
        $execution->getJournal()->appendFacilitatorJournal(
            $this->journal->formatParticipantEntry(
                $nextRole,
                $turnResult->getAgentResult()->getOutputText(),
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
    }

    /**
     * Вызывает агент-фасилитатор и возвращает результат turn + разобранный ответ.
     *
     * @return array{DynamicLoopTurnResultVo, FacilitatorResponseVo}
     */
    private function callFacilitatorAgent(
        DynamicLoopContextVo $context,
        DynamicLoopExecution $execution,
        string $facResponseFilesList,
        ?DynamicLoopRoleConfigVo $facRoleConfig,
        ?DynamicLoopRetryPolicyVo $retryPolicy = null,
    ): array {
        return $this->agentRunner->runFacilitator(
            step: $execution->getStep(),
            round: $execution->getRound(),
            facilitatorRole: $context->getFacilitatorRole(),
            topic: $context->getTopic(),
            runContext: new FacilitatorRunContextVo(
                brainstormSystemPrompt: $context->getPromptConfiguration()->getBrainstormSystemPrompt(),
                facilitatorAppendPrompt: $context->getPromptConfiguration()->getFacilitatorAppendPrompt(),
                facilitatorStartPrompt: $context->getPromptConfiguration()->getFacilitatorStartPrompt(),
                facilitatorContinuePrompt: $context->getPromptConfiguration()->getFacilitatorContinuePrompt(),
                workingDir: $context->getWorkingDir(),
                facilitatorSummary: $execution->getFacilitatorSummary(),
                responseFilesList: $facResponseFilesList,
                timeout: $facRoleConfig?->getTimeout() ?? $context->getTimeout(),
                command: $facRoleConfig?->getCommand() ?? [],
                retryPolicy: $retryPolicy,
                taskAddendum: $context->getTaskAddendum(),
            ),
        );
    }

    /**
     * @param list<array{role: string, path: string}> $responsePaths
     */
    private function buildResponseFilesList(array $responsePaths): string
    {
        if ($responsePaths === []) {
            return '';
        }

        return implode(
            "\n",
            array_map(
                static fn(array $item): string => "- {$item['role']}: {$item['path']}",
                $responsePaths,
            ),
        );
    }
}
