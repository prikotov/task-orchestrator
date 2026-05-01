<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain;

use LogicException;
use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\DynamicRoundResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerFactoryInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\BuildDynamicContextServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\RunDynamicLoopServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Session\ChainSessionLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\SessionCompletedNotifierInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainContextVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicLoopResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicRoundResultVo;

/**
 * Стратегия выполнения dynamic-цепочки.
 *
 * Инкапсулирует фасилитаторный цикл: session start/resume,
 * context build, loop run, finalize, DTO mapping, event dispatch.
 */
final readonly class DynamicExecutionStrategy implements ExecutionStrategyInterface
{
    /** @var int Дефолтный таймаут (секунды) для dynamic-цепочки при отсутствии CLI и chain timeout */
    private const int DEFAULT_DYNAMIC_TIMEOUT = 600;

    /** @var int Дефолтный max_time (секунды) для dynamic-цепочки при отсутствии CLI и chain max_time */
    private const int DEFAULT_DYNAMIC_MAX_TIME = 3600;

    public function __construct(
        private BuildDynamicContextServiceInterface $contextBuilder,
        private RunDynamicLoopServiceInterface $dynamicLoopRunner,
        private ChainSessionLoggerInterface $sessionLogger,
        private AuditLoggerFactoryInterface $auditLoggerFactory,
        private SessionCompletedNotifierInterface $sessionNotifier,
    ) {
    }

    #[Override]
    public function execute(ChainDefinitionVo $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        $facilitatorRole = $command->facilitator ?? $chain->getFacilitator() ?? 'team_lead';
        $participants = $command->participants ?? $chain->getParticipants();
        $maxRounds = $command->maxRounds ?? $chain->getMaxRounds();
        $topic = $command->topic ?? $command->task;
        $shared = $chain->getSharedDefinition();
        $timeout = $command->timeout ?? $shared->getTimeout() ?? self::DEFAULT_DYNAMIC_TIMEOUT;
        $maxTime = $command->maxTime ?? $shared->getMaxTime() ?? self::DEFAULT_DYNAMIC_MAX_TIME;

        $sessionDir = $this->sessionLogger->startSession(
            $shared->getName(),
            $topic,
            $facilitatorRole,
            $participants,
            $maxRounds,
        );
        $auditLogger = $this->resolveAuditLogger($sessionDir, $command->noAuditLog);
        $this->sessionLogger->setBudget($shared->getBudget());
        $this->sessionLogger->logInvocation(
            $this->contextBuilder->buildInvocation(
                $chain,
                $command->task,
                $timeout,
                $command->workingDir,
                $command->resumeDir,
                $facilitatorRole,
                $participants,
                $maxRounds,
                $topic,
            ),
        );

        $context = $this->contextBuilder->buildContext(
            $chain,
            $facilitatorRole,
            $participants,
            $maxRounds,
            $topic,
            $command->workingDir,
            $timeout,
            $maxTime,
        );

        $loopResult = $this->runDynamicLoop($chain, $context, auditLogger: $auditLogger);
        $this->finalizeSession($loopResult, $sessionDir);

        return $this->toResultDto($loopResult, $sessionDir);
    }

    #[Override]
    public function resume(ChainDefinitionVo $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        $resumeDir = $command->resumeDir;
        assert($resumeDir !== null);

        $auditLogger = $this->resolveAuditLogger($resumeDir, $command->noAuditLog);
        $this->sessionLogger->resumeSession($resumeDir);
        $state = $this->sessionLogger->getResumedState();

        if ($state === null) {
            throw new LogicException("Failed to resume session from: {$resumeDir}");
        }

        $shared = $chain->getSharedDefinition();
        $this->sessionLogger->setBudget($shared->getBudget());
        $resumeTimeout = $command->timeout ?? $shared->getTimeout() ?? self::DEFAULT_DYNAMIC_TIMEOUT;

        $invocation = $this->contextBuilder->buildInvocation(
            $chain,
            $command->task,
            $resumeTimeout,
            $command->workingDir,
            $command->resumeDir,
            $state->getFacilitator(),
            $state->getParticipants(),
            $state->getMaxRounds(),
            $state->getTopic(),
        );
        $invocation['resumed_from'] = $resumeDir;
        $this->sessionLogger->logInvocation($invocation);

        $context = $this->contextBuilder->buildContext(
            $chain,
            $state->getFacilitator(),
            $state->getParticipants(),
            $state->getMaxRounds(),
            $state->getTopic(),
            $command->workingDir,
            $resumeTimeout,
            $command->maxTime ?? $shared->getMaxTime() ?? self::DEFAULT_DYNAMIC_MAX_TIME,
        );

        $loopResult = $this->runDynamicLoop(
            $chain,
            $context,
            $state->getCompletedRounds(),
            $state->getDiscussionHistory(),
            $state->getFacilitatorJournal(),
            $auditLogger,
        );
        $this->finalizeSession($loopResult, $resumeDir);

        return $this->toResultDto($loopResult, $resumeDir);
    }

    #[Override]
    public function supports(ChainDefinitionVo $chain): bool
    {
        return $chain->getSharedDefinition()->getType() === ChainTypeEnum::dynamicType;
    }

    // ─── Private helpers ──────────────────────────────────────────────────

    private function runDynamicLoop(
        ChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        int $startRound = 0,
        string $initialDiscussionHistory = '',
        string $initialFacilitatorJournal = '',
        ?AuditLoggerInterface $auditLogger = null,
    ): DynamicLoopResultVo {
        return $this->dynamicLoopRunner->execute(
            $chain,
            $context,
            $startRound,
            $initialDiscussionHistory,
            $initialFacilitatorJournal,
            $auditLogger,
        );
    }

    private function finalizeSession(DynamicLoopResultVo $loopResult, ?string $sessionDir = null): void
    {
        $reason = $loopResult->getCompletionReason();

        if ($loopResult->synthesis !== null) {
            $this->sessionLogger->completeSession(
                $loopResult->synthesis,
                $loopResult->totalTime,
                $loopResult->totalInputTokens,
                $loopResult->totalOutputTokens,
                $loopResult->totalCost,
                count($loopResult->roundResults),
                $reason,
            );
            $this->dispatchCompletedEvent($loopResult, $sessionDir, $reason);

            return;
        }

        $this->sessionLogger->interruptSession($reason);
        $this->dispatchCompletedEvent($loopResult, $sessionDir, $reason);
    }

    /**
     * @param string|null $sessionDir Директория сессии для audit.jsonl
     */
    private function resolveAuditLogger(?string $sessionDir, bool $noAuditLog = false): ?AuditLoggerInterface
    {
        if ($noAuditLog || $sessionDir === null) {
            return null;
        }

        return $this->auditLoggerFactory->create($sessionDir . '/audit.jsonl');
    }

    private function dispatchCompletedEvent(DynamicLoopResultVo $loopResult, ?string $sessionDir, string $reason): void
    {
        $status = $loopResult->synthesis !== null ? 'completed' : 'interrupted';

        $this->sessionNotifier->notifySessionCompleted(
            status: $status,
            completionReason: $reason,
            totalRounds: count($loopResult->roundResults),
            totalTime: $loopResult->totalTime,
            totalInputTokens: $loopResult->totalInputTokens,
            totalOutputTokens: $loopResult->totalOutputTokens,
            totalCost: $loopResult->totalCost,
            synthesis: $loopResult->synthesis,
            sessionDir: $sessionDir,
            budgetExceeded: $loopResult->budgetExceeded,
            budgetLimit: $loopResult->budgetLimit,
            budgetExceededRole: $loopResult->budgetExceededRole,
        );
    }

    private function toResultDto(DynamicLoopResultVo $loopResult, ?string $sessionDir): OrchestrateChainResultDto
    {
        $roundDtos = $this->toRoundResultDtos($loopResult->roundResults);

        // timedOut вычисляем из round-level флагов — хотя бы один раунд таймаут
        $chainTimedOut = array_any(
            $roundDtos,
            static fn(DynamicRoundResultDto $round): bool => $round->timedOut,
        );

        return new OrchestrateChainResultDto(
            roundResults: $roundDtos,
            totalTime: $loopResult->totalTime,
            totalInputTokens: $loopResult->totalInputTokens,
            totalOutputTokens: $loopResult->totalOutputTokens,
            totalCost: $loopResult->totalCost,
            synthesis: $loopResult->synthesis,
            maxRoundsReached: $loopResult->maxRoundsReached,
            sessionDir: $sessionDir,
            budgetExceeded: $loopResult->budgetExceeded,
            budgetLimit: $loopResult->budgetLimit,
            budgetExceededRole: $loopResult->budgetExceededRole,
            timedOut: $chainTimedOut,
        );
    }

    /**
     * @param list<DynamicRoundResultVo> $roundVos
     *
     * @return list<DynamicRoundResultDto>
     */
    private function toRoundResultDtos(array $roundVos): array
    {
        return array_map(
            static fn(DynamicRoundResultVo $roundVo): DynamicRoundResultDto => new DynamicRoundResultDto(
                round: $roundVo->round,
                role: $roundVo->role,
                isFacilitator: $roundVo->isFacilitator,
                outputText: $roundVo->outputText,
                inputTokens: $roundVo->inputTokens,
                outputTokens: $roundVo->outputTokens,
                cost: $roundVo->cost,
                duration: $roundVo->duration,
                isError: $roundVo->isError,
                errorMessage: $roundVo->errorMessage,
                invocation: $roundVo->invocation,
                systemPrompt: $roundVo->systemPrompt,
                userPrompt: $roundVo->userPrompt,
                timedOut: $roundVo->timedOut,
            ),
            $roundVos,
        );
    }
}
