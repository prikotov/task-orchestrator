<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic;

use LogicException;
use Override;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session\DynamicLoopSessionLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopBudgetVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRoleConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRunResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopTurnResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicRoundResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorResponseVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorTurnResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\TurnBreakVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\TurnContinueVo;

/**
 * Исполнитель шагов dynamic-цикла: полный turn (agent call + journal + budget + error).
 */
final readonly class ExecuteDynamicTurnService implements ExecuteDynamicTurnServiceInterface
{
    /** @var string Причина прерывания цикла по таймауту */
    private const string INTERRUPTION_REASON_TIMEOUT = 'timeout';

    /** @var string Ошибка пустого ответа агента */
    private const string EMPTY_OUTPUT_ERROR_MESSAGE = 'Agent returned empty output.';

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
                $context->facilitatorRole,
                $facResponse->isDone(),
                $facResponse->getNextRole(),
                $facResponse->getChallenge(),
                $facResponse->getSynthesis(),
            ),
        );

        $stepCost = $turnResult->agentResult->getCost();
        $execution->getMetrics()->addRoleCost($context->facilitatorRole, $stepCost);

        $budgetCheck = $this->budgetChecker->checkAndApply(
            $execution,
            $budget,
            $context->facilitatorRole,
            $stepCost,
        );
        if ($budgetCheck !== null && $budgetCheck->shouldBreak) {
            return new TurnBreakVo(
                interruptionReason: 'budget_exceeded',
                budgetResult: $budgetCheck,
            );
        }

        if ($turnResult->agentResult->isError()) {
            $reason = $turnResult->agentResult->isTimedOut()
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
                $context->participants,
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
                $context->participants,
                true,
            )
        ) {
            return new TurnContinueVo();
        }
        if ($execution->getParticipantRounds() >= $context->maxRounds) {
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

        $stepCost = $turnResult->agentResult->getCost();
        $execution->getMetrics()->addRoleCost($nextRole, $stepCost);

        if ($turnResult->agentResult->isError()) {
            $reason = $turnResult->agentResult->isTimedOut()
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
        if ($budgetCheck !== null && $budgetCheck->shouldBreak) {
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
        $facRoleConfig = $chain->getRoleConfig($context->facilitatorRole);
        $facRunner = self::resolveRunner($facRoleConfig);
        $auditLogger?->logStepStart(
            $chain->getName(),
            $execution->getStep(),
            $context->facilitatorRole,
            $facRunner,
        );

        [$turnResult, $facResponse] = $this->callFacilitatorAgent(
            $context,
            $execution,
            $facResponseFilesList,
            $facRoleConfig,
        );

        $roundVo = self::toRoundResultVo(
            $turnResult,
            $execution->getStep(),
            $context->facilitatorRole,
            true,
        );
        $facVo = new FacilitatorTurnResultVo(
            roundResult: $roundVo,
            done: $facResponse->isDone(),
            nextRole: $facResponse->getNextRole(),
            synthesis: $facResponse->getSynthesis(),
            challenge: $facResponse->getChallenge(),
            userPrompt: $turnResult->userPrompt,
        );

        $this->roundRecorder->record(
            $execution,
            $execution->getStep(),
            $execution->getRound(),
            $chain->getName(),
            $facRunner,
            $context->facilitatorRole,
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
        $partRunner = self::resolveRunner($partRoleConfig);
        $auditLogger?->logStepStart(
            $chain->getName(),
            $execution->getStep(),
            $nextRole,
            $partRunner,
        );

        $turnResult = $this->agentRunner->runParticipant(
            $execution->getStep(),
            $execution->getRound(),
            $nextRole,
            $context->topic,
            $context->promptConfiguration->getBrainstormSystemPrompt(),
            $context->promptConfiguration->getParticipantAppendPrompt(),
            $context->promptConfiguration->getParticipantUserPrompt(),
            $context->workingDir,
            $responseFilesList,
            $partRoleConfig?->getTimeout() ?? $context->timeout,
            $partRoleConfig?->getCommand() ?? [],
            $prevResponsePaths !== [],
            $challenge,
            $partRoleConfig?->getPromptFile(),
        );
        $turnResult = self::normalizeEmptySuccessfulOutput($turnResult);

        $roundVo = self::toRoundResultVo(
            $turnResult,
            $execution->getStep(),
            $nextRole,
            false,
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
        $finRoleConfig = $chain->getRoleConfig($context->facilitatorRole);
        $finRunner = self::resolveRunner($finRoleConfig);
        $auditLogger?->logStepStart(
            $chain->getName(),
            $execution->getStep(),
            $context->facilitatorRole,
            $finRunner,
        );

        $turnResult = $this->agentRunner->runFacilitatorFinalize(
            step: $execution->getStep(),
            round: $execution->getRound(),
            facilitatorRole: $context->facilitatorRole,
            topic: $context->topic,
            brainstormSystemPrompt: $context->promptConfiguration->getBrainstormSystemPrompt(),
            facilitatorAppendPrompt: $context->promptConfiguration->getFacilitatorAppendPrompt(),
            facilitatorFinalizePrompt: $context->promptConfiguration->getFacilitatorFinalizePrompt(),
            workingDir: $context->workingDir,
            responseFilesList: $facResponseFilesList,
            timeout: $finRoleConfig?->getTimeout() ?? $context->timeout,
            command: $finRoleConfig?->getCommand() ?? [],
        );

        $roundVo = self::toRoundResultVo(
            $turnResult,
            $execution->getStep(),
            $context->facilitatorRole,
            true,
        );
        $this->roundRecorder->record(
            $execution,
            $execution->getStep(),
            $execution->getRound(),
            $chain->getName(),
            $finRunner,
            $context->facilitatorRole,
            true,
            $roundVo,
            done: true,
            synthesis: $turnResult->agentResult->getOutputText(),
            auditLogger: $auditLogger,
        );

        return $turnResult;
    }

    public static function resolveRunner(?DynamicLoopRoleConfigVo $roleConfig): string
    {
        $command = $roleConfig?->getCommand() ?? [];
        if ($command === [] || $command[0] === '') {
            throw new LogicException(
                'Role configuration must define a non-empty command with runner name as the first element.',
            );
        }

        return $command[0];
    }

    public static function toRoundResultVo(
        DynamicLoopTurnResultVo $turn,
        int $step,
        string $role,
        bool $isFacilitator,
    ): DynamicRoundResultVo {
        $agent = $turn->agentResult;

        return new DynamicRoundResultVo(
            round: $step,
            role: $role,
            isFacilitator: $isFacilitator,
            outputText: $agent->getOutputText(),
            inputTokens: $agent->getInputTokens(),
            outputTokens: $agent->getOutputTokens(),
            cost: $agent->getCost(),
            duration: $turn->duration,
            isError: $agent->isError(),
            errorMessage: $agent->getErrorMessage(),
            invocation: $turn->invocation,
            systemPrompt: $turn->systemPrompt,
            userPrompt: $turn->userPrompt,
            timedOut: $agent->isTimedOut(),
        );
    }

    private static function normalizeEmptySuccessfulOutput(DynamicLoopTurnResultVo $turnResult): DynamicLoopTurnResultVo
    {
        if ($turnResult->agentResult->isError() || trim($turnResult->agentResult->getOutputText()) !== '') {
            return $turnResult;
        }

        return new DynamicLoopTurnResultVo(
            agentResult: DynamicLoopRunResultVo::createError(
                errorMessage: self::EMPTY_OUTPUT_ERROR_MESSAGE,
                exitCode: 0,
            ),
            duration: $turnResult->duration,
            userPrompt: $turnResult->userPrompt,
            systemPrompt: $turnResult->systemPrompt,
            invocation: $turnResult->invocation,
        );
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
                $turnResult->agentResult->getOutputText(),
            ),
        );
        $execution->getJournal()->appendFacilitatorJournal(
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
    ): array {
        return $this->agentRunner->runFacilitator(
            $execution->getStep(),
            $execution->getRound(),
            $context->facilitatorRole,
            $context->topic,
            $context->promptConfiguration->getBrainstormSystemPrompt(),
            $context->promptConfiguration->getFacilitatorAppendPrompt(),
            $context->promptConfiguration->getFacilitatorStartPrompt(),
            $context->promptConfiguration->getFacilitatorContinuePrompt(),
            $context->workingDir,
            $execution->getFacilitatorSummary(),
            $facResponseFilesList,
            $facRoleConfig?->getTimeout() ?? $context->timeout,
            $facRoleConfig?->getCommand() ?? [],
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
