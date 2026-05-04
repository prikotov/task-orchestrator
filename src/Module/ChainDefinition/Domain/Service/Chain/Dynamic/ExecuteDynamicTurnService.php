<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\Dynamic;

use LogicException;
use Override;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\Session\ChainSessionLoggerInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainTurnResultVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainContextVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicRoundResultVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FacilitatorResponseVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FacilitatorTurnResultVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\RoleConfigVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\TurnBreakVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\TurnContinueVo;

/**
 * Исполнитель шагов dynamic-цикла: полный turn (agent call + journal + budget + error).
 *
 * Инкапсулирует логику выполнения facilitator/participant turn'ов:
 * - запуск agentRunner через подметоды
 * - запись в журналы и discussion history
 * - проверка бюджета через CheckDynamicLoopBudgetService
 * - обработка ошибок агента
 * - возврат TurnContinueVo|TurnBreakVo решения
 */
final readonly class ExecuteDynamicTurnService implements ExecuteDynamicTurnServiceInterface
{
    /** @var string Причина прерывания цикла по таймауту */
    private const string INTERRUPTION_REASON_TIMEOUT = 'timeout';

    public function __construct(
        private RunDynamicLoopAgentServiceInterface $agentRunner,
        private RecordDynamicRoundServiceInterface $roundRecorder,
        private FormatDynamicJournalServiceInterface $journal,
        private ChainSessionLoggerInterface $sessionLogger,
        private CheckDynamicLoopBudgetServiceInterface $budgetChecker,
    ) {
    }

    // ─── Turn orchestration (public) ──────────────────────────────────

    /**
     * Выполняет полный facilitator turn: agent call, journal, budget, error handling.
     */
    #[Override]
    public function runFacilitatorTurn(
        DynamicChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?BudgetVo $budget,
        ?AuditLoggerInterface $auditLogger,
    ): TurnContinueVo|TurnBreakVo {
        [$turnResult, $facResponse] = $this->runFacilitatorStep(
            $chain,
            $context,
            $execution,
            $auditLogger,
        );

        $execution->appendDiscussionHistory(
            $this->journal->formatFacilitatorDiscussionEntry(
                $context->facilitatorRole,
                $facResponse->isDone(),
                $facResponse->getNextRole(),
                $facResponse->getChallenge(),
                $facResponse->getSynthesis(),
            ),
        );

        $stepCost = $turnResult->agentResult->getCost();
        $execution->addRoleCost($context->facilitatorRole, $stepCost);

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
            $execution->appendFacilitatorSummary(sprintf(
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

    /**
     * Выполняет полный participant turn: agent call, journal, budget, error handling.
     */
    #[Override]
    public function runParticipantTurn(
        DynamicChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?BudgetVo $budget,
        ?AuditLoggerInterface $auditLogger,
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
        if ($turnResult->agentResult->isError()) {
            $reason = $turnResult->agentResult->isTimedOut()
                ? self::INTERRUPTION_REASON_TIMEOUT
                : 'agent_error';
            $this->sessionLogger->interruptSession($reason);

            return new TurnBreakVo(interruptionReason: $reason);
        }

        return new TurnContinueVo();
    }

    // ─── Agent step runners (called internally) ───────────────────────

    /**
     * Запускает facilitator agent step: подготовка аргументов, вызов agentRunner, запись раунда.
     *
     * @return array{ChainTurnResultVo, FacilitatorResponseVo}
     */
    #[Override]
    public function runFacilitatorStep(
        DynamicChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?AuditLoggerInterface $auditLogger,
    ): array {
        $facResponsePaths = $this->sessionLogger->getResponseFilePaths(
            $execution->getStep() - 1,
        );
        $facResponseFilesList = $this->buildResponseFilesList($facResponsePaths);
        $facRoleConfig = $chain->getSharedDefinition()->getRoleConfig($context->facilitatorRole);
        $facRunner = self::resolveRunner($facRoleConfig);
        $auditLogger?->logStepStart(
            $chain->getSharedDefinition()->getName(),
            $execution->getStep(),
            $context->facilitatorRole,
            $facRunner,
        );

        /** @var array{ChainTurnResultVo, FacilitatorResponseVo} $facRun */
        $facRun = $this->agentRunner->runFacilitator(
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
        [$turnResult, $facResponse] = $facRun;

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
            $chain->getSharedDefinition()->getName(),
            $facRunner,
            $context->facilitatorRole,
            true,
            $roundVo,
            $facResponse->getNextRole(),
            $facResponse->isDone(),
            $facResponse->getSynthesis(),
            $auditLogger,
        );

        $execution->appendFacilitatorJournal(
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

    /**
     * Запускает participant agent step: подготовка аргументов, вызов agentRunner, запись раунда.
     */
    #[Override]
    public function runParticipantStep(
        DynamicChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?AuditLoggerInterface $auditLogger,
        string $nextRole,
        ?string $challenge,
    ): ChainTurnResultVo {
        $prevResponsePaths = $this->sessionLogger->getResponseFilePaths(
            $execution->getStep() - 1,
        );
        $responseFilesList = $this->buildResponseFilesList($prevResponsePaths);
        $partRoleConfig = $chain->getSharedDefinition()->getRoleConfig($nextRole);
        $partRunner = self::resolveRunner($partRoleConfig);
        $auditLogger?->logStepStart(
            $chain->getSharedDefinition()->getName(),
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
            $chain->getSharedDefinition()->getName(),
            $partRunner,
            $nextRole,
            false,
            $roundVo,
            auditLogger: $auditLogger,
        );

        return $turnResult;
    }

    /**
     * Запускает finalize agent step: подготовка аргументов, вызов agentRunner, запись раунда.
     */
    #[Override]
    public function runFinalizeStep(
        DynamicChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?AuditLoggerInterface $auditLogger,
    ): ChainTurnResultVo {
        $facResponsePaths = $this->sessionLogger->getResponseFilePaths(
            $execution->getStep() - 1,
        );
        $facResponseFilesList = $this->buildResponseFilesList($facResponsePaths);
        $finRoleConfig = $chain->getSharedDefinition()->getRoleConfig($context->facilitatorRole);
        $finRunner = self::resolveRunner($finRoleConfig);
        $auditLogger?->logStepStart(
            $chain->getSharedDefinition()->getName(),
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
            $chain->getSharedDefinition()->getName(),
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

    // ─── Helpers ───────────────────────────────────────────────────────

    /**
     * Извлекает имя runner'а из конфигурации роли.
     *
     * @throws LogicException если конфигурация роли отсутствует или command пуста
     */
    public static function resolveRunner(?RoleConfigVo $roleConfig): string
    {
        $command = $roleConfig?->getCommand() ?? [];
        if ($command === [] || $command[0] === '') {
            throw new LogicException(
                'Role configuration must define a non-empty command with runner name as the first element.',
            );
        }

        return $command[0];
    }

    /**
     * Конвертирует ChainTurnResultVo в DynamicRoundResultVo для записи раунда.
     */
    public static function toRoundResultVo(
        ChainTurnResultVo $turn,
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

    /**
     * Форматирует пути файлов ответов в текстовый список.
     *
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
