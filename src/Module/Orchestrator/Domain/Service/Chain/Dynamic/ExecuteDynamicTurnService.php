<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic;

use LogicException;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Session\ChainSessionLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainTurnResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainContextVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicRoundResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FacilitatorResponseVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FacilitatorTurnResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\RoleConfigVo;

use function array_map;
use function implode;

/**
 * Подготовка аргументов и вызов agentRunner для dynamic-цикла.
 *
 * Содержит методы-адаптеры, которые собирают аргументы из execution/context
 * и делегируют вызов RunDynamicLoopAgentServiceInterface.
 *
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @todo PHPMD bug: multi-file analysis inflates LOC counts. Recheck after PHPMD upgrade.
 */
final readonly class ExecuteDynamicTurnService
{
    public function __construct(
        private RunDynamicLoopAgentServiceInterface $agentRunner,
        private RecordDynamicRoundServiceInterface $roundRecorder,
        private FormatDynamicJournalServiceInterface $journal,
        private ChainSessionLoggerInterface $sessionLogger,
    ) {
    }

    /**
     * @return array{ChainTurnResultVo, FacilitatorResponseVo}
     */
    public function runFacilitatorStep(
        ChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?AuditLoggerInterface $auditLogger,
    ): array {
        $facResponsePaths = $this->sessionLogger->getResponseFilePaths(
            $execution->getStep() - 1,
        );
        $facResponseFilesList = $facResponsePaths !== []
            ? implode(
                "\n",
                array_map(
                    static fn(string $path): string => "- {$path}",
                    $facResponsePaths,
                ),
            )
            : '';
        $facRoleConfig = $chain->getRoleConfig($context->facilitatorRole);
        $facRunner = $this->resolveRunner($facRoleConfig);
        $auditLogger?->logStepStart(
            $chain->getName(),
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
            $context->brainstormSystemPrompt,
            $context->facilitatorAppendPrompt,
            $context->facilitatorStartPrompt,
            $context->facilitatorContinuePrompt,
            $context->workingDir,
            $execution->getFacilitatorSummary(),
            $facResponseFilesList,
            $facRoleConfig?->getTimeout() ?? $context->timeout,
            $facRoleConfig?->getCommand() ?? [],
        );
        [$turnResult, $facResponse] = $facRun;

        $roundVo = $this->toRoundResultVo(
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

    public function runParticipantStep(
        ChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?AuditLoggerInterface $auditLogger,
        string $nextRole,
        ?string $challenge,
    ): ChainTurnResultVo {
        $prevResponsePaths = $this->sessionLogger->getResponseFilePaths(
            $execution->getStep() - 1,
        );
        $responseFilesList = $prevResponsePaths !== []
            ? implode(
                "\n",
                array_map(
                    static fn(string $path): string => "- {$path}",
                    $prevResponsePaths,
                ),
            )
            : '';
        $partRoleConfig = $chain->getRoleConfig($nextRole);
        $partRunner = $this->resolveRunner($partRoleConfig);
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
            $context->brainstormSystemPrompt,
            $context->participantAppendPrompt,
            $context->participantUserPrompt,
            $context->workingDir,
            $responseFilesList,
            $partRoleConfig?->getTimeout() ?? $context->timeout,
            $partRoleConfig?->getCommand() ?? [],
            $prevResponsePaths !== [],
            $challenge,
            $partRoleConfig?->getPromptFile(),
        );
        $roundVo = $this->toRoundResultVo(
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

    public function runFinalizeStep(
        ChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        DynamicLoopExecution $execution,
        ?AuditLoggerInterface $auditLogger,
    ): ChainTurnResultVo {
        $facResponsePaths = $this->sessionLogger->getResponseFilePaths(
            $execution->getStep() - 1,
        );
        $facResponseFilesList = $facResponsePaths !== []
            ? implode(
                "\n",
                array_map(
                    static fn(string $path): string => "- {$path}",
                    $facResponsePaths,
                ),
            )
            : '';
        $finRoleConfig = $chain->getRoleConfig($context->facilitatorRole);
        $finRunner = $this->resolveRunner($finRoleConfig);
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
            brainstormSystemPrompt: $context->brainstormSystemPrompt,
            facilitatorAppendPrompt: $context->facilitatorAppendPrompt,
            facilitatorFinalizePrompt: $context->facilitatorFinalizePrompt,
            workingDir: $context->workingDir,
            responseFilesList: $facResponseFilesList,
            timeout: $finRoleConfig?->getTimeout() ?? $context->timeout,
            command: $finRoleConfig?->getCommand() ?? [],
        );

        $roundVo = $this->toRoundResultVo(
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

    /**
     * Извлекает имя runner'а из конфигурации роли.
     *
     * Runner — первый элемент массива command в RoleConfigVo.
     *
     * @throws LogicException если конфигурация роли отсутствует или command пуста
     */
    private function resolveRunner(?RoleConfigVo $roleConfig): string
    {
        $command = $roleConfig?->getCommand() ?? [];
        if ($command === [] || $command[0] === '') {
            throw new LogicException(
                'Role configuration must define a non-empty command with runner name as the first element.',
            );
        }

        return $command[0];
    }

    private function toRoundResultVo(
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
}
