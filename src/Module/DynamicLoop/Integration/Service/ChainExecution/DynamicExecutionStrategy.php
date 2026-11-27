<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Integration\Service\ChainExecution;

use LogicException;
use Override;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Contract\Chain\ExecutionStrategyInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\DynamicRoundResultDto as ChainDynamicRoundResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainExecutionTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionChainInfoVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerFactoryInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\ChainDefinition\ChainDefinitionProviderInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\BuildDynamicContextServiceInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\RunDynamicLoopServiceInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\SessionCompletedNotifierInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session\DynamicLoopSessionLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopSessionStateVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicRoundResultVo;

/**
 * Стратегия выполнения dynamic-цепочки.
 *
 * Инкапсулирует фасилитаторный цикл: session start/resume,
 * context build, loop run, finalize, DTO mapping, event dispatch.
 *
 * Расположен в Integration-слое, т.к. реализует контракт ExecutionStrategyInterface
 * из ChainExecution.Application.Contract и работает с DTO чужого модуля
 * (разрешено: Integration → foreign Application).
 */
final readonly class DynamicExecutionStrategy implements ExecutionStrategyInterface
{
    /** @var int Дефолтный таймаут (секунды) для dynamic-цепочки */
    private const int DEFAULT_DYNAMIC_TIMEOUT = 600;

    /** @var int Дефолтный max_time (секунды) для dynamic-цепочки */
    private const int DEFAULT_DYNAMIC_MAX_TIME = 3600;

    public function __construct(
        private BuildDynamicContextServiceInterface $contextBuilder,
        private RunDynamicLoopServiceInterface $dynamicLoopRunner,
        private DynamicLoopSessionLoggerInterface $sessionLogger,
        private ChainDefinitionProviderInterface $chainProvider,
        private DynamicLoopAuditLoggerFactoryInterface $auditLoggerFactory,
        private SessionCompletedNotifierInterface $sessionNotifier,
    ) {
    }

    #[Override]
    public function execute(ExecutionChainInfoVo $chainInfo, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        $complexity = $command->complexity ?? DynamicLoopSessionStateVo::DEFAULT_COMPLEXITY;
        $config = $this->chainProvider->loadDynamicChainConfig($chainInfo->getName(), $complexity);

        $facilitatorRole = $command->facilitator ?? $config->getFacilitator();
        $participants = $command->participants ?? $config->getParticipants();
        $maxRounds = $command->maxRounds ?? $config->getMaxRounds();
        $topic = $command->topic ?? $command->task;
        $timeout = $command->timeout ?? $config->getTimeout() ?? self::DEFAULT_DYNAMIC_TIMEOUT;
        $maxTime = $command->maxTime ?? $config->getMaxTime() ?? self::DEFAULT_DYNAMIC_MAX_TIME;

        $sessionDir = $this->sessionLogger->startSession(
            $config->getName(),
            $topic,
            $facilitatorRole,
            $participants,
            $maxRounds,
            $complexity,
        );
        $auditLogger = $this->resolveAuditLogger($sessionDir, $command->noAuditLog);
        $this->sessionLogger->setBudget($config->getBudget());
        $this->sessionLogger->logInvocation(
            $this->contextBuilder->buildInvocation(
                $config,
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
            $config,
            $facilitatorRole,
            $participants,
            $maxRounds,
            $topic,
            $command->workingDir,
            $timeout,
            $maxTime,
        );

        $loopResult = $this->runDynamicLoop($config, $context, auditLogger: $auditLogger);
        $this->finalizeSession($loopResult, $sessionDir);

        return $this->toResultDto($loopResult, $sessionDir);
    }

    #[Override]
    public function resume(ExecutionChainInfoVo $chainInfo, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        $resumeDir = $command->resumeDir;
        assert($resumeDir !== null);

        $auditLogger = $this->resolveAuditLogger($resumeDir, $command->noAuditLog);
        $this->sessionLogger->resumeSession($resumeDir);
        $state = $this->sessionLogger->getResumedState();

        if ($state === null) {
            throw new LogicException("Failed to resume session from: {$resumeDir}");
        }

        // Явная --complexity приоритетнее сохранённой в session.json.
        $complexity = $command->complexity ?? $state->getComplexity();
        $config = $this->chainProvider->loadDynamicChainConfig($chainInfo->getName(), $complexity);

        $this->sessionLogger->setBudget($config->getBudget());
        $resumeTimeout = $command->timeout ?? $config->getTimeout() ?? self::DEFAULT_DYNAMIC_TIMEOUT;

        $invocation = $this->contextBuilder->buildInvocation(
            $config,
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
            $config,
            $state->getFacilitator(),
            $state->getParticipants(),
            $state->getMaxRounds(),
            $state->getTopic(),
            $command->workingDir,
            $resumeTimeout,
            $command->maxTime ?? $config->getMaxTime() ?? self::DEFAULT_DYNAMIC_MAX_TIME,
            $command->task,
        );

        $loopResult = $this->runDynamicLoop(
            $config,
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
    public function supports(ExecutionChainInfoVo $chainInfo): bool
    {
        return $chainInfo->getType() === ChainExecutionTypeEnum::dynamicType;
    }

    // ─── Private helpers ──────────────────────────────────────────────────

    private function runDynamicLoop(
        DynamicLoopConfigVo $chain,
        DynamicLoopContextVo $context,
        int $startRound = 0,
        string $initialDiscussionHistory = '',
        string $initialFacilitatorJournal = '',
        ?DynamicLoopAuditLoggerInterface $auditLogger = null,
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

        if ($loopResult->getSynthesis() !== null) {
            $this->sessionLogger->completeSession(
                $loopResult->getSynthesis(),
                $loopResult->getTotalTime(),
                $loopResult->getTotalInputTokens(),
                $loopResult->getTotalOutputTokens(),
                $loopResult->getTotalCost(),
                count($loopResult->getRoundResults()),
                $reason,
            );
            $this->dispatchCompletedEvent($loopResult, $sessionDir, $reason);

            return;
        }

        $this->sessionLogger->interruptSession($reason);
        $this->dispatchCompletedEvent($loopResult, $sessionDir, $reason);
    }

    private function resolveAuditLogger(?string $sessionDir, bool $noAuditLog = false): ?DynamicLoopAuditLoggerInterface
    {
        if ($noAuditLog || $sessionDir === null) {
            return null;
        }

        return $this->auditLoggerFactory->create($sessionDir . '/audit.jsonl');
    }

    private function dispatchCompletedEvent(DynamicLoopResultVo $loopResult, ?string $sessionDir, string $reason): void
    {
        $status = $loopResult->getSynthesis() !== null ? 'completed' : 'interrupted';

        $this->sessionNotifier->notifySessionCompleted(
            status: $status,
            completionReason: $reason,
            totalRounds: count($loopResult->getRoundResults()),
            totalTime: $loopResult->getTotalTime(),
            totalInputTokens: $loopResult->getTotalInputTokens(),
            totalOutputTokens: $loopResult->getTotalOutputTokens(),
            totalCost: $loopResult->getTotalCost(),
            synthesis: $loopResult->getSynthesis(),
            sessionDir: $sessionDir,
            budgetExceeded: $loopResult->isBudgetExceeded(),
            budgetLimit: $loopResult->getBudgetLimit(),
            budgetExceededRole: $loopResult->getBudgetExceededRole(),
        );
    }

    private function toResultDto(DynamicLoopResultVo $loopResult, ?string $sessionDir): OrchestrateChainResultDto
    {
        $roundDtos = $this->toRoundResultDtos($loopResult->getRoundResults());

        $chainTimedOut = array_any(
            $roundDtos,
            static fn(ChainDynamicRoundResultDto $round): bool => $round->timedOut,
        );

        return new OrchestrateChainResultDto(
            roundResults: $roundDtos,
            totalTime: $loopResult->getTotalTime(),
            totalInputTokens: $loopResult->getTotalInputTokens(),
            totalOutputTokens: $loopResult->getTotalOutputTokens(),
            totalCost: $loopResult->getTotalCost(),
            synthesis: $loopResult->getSynthesis(),
            maxRoundsReached: $loopResult->isMaxRoundsReached(),
            sessionDir: $sessionDir,
            budgetExceeded: $loopResult->isBudgetExceeded(),
            budgetLimit: $loopResult->getBudgetLimit(),
            budgetExceededRole: $loopResult->getBudgetExceededRole(),
            timedOut: $chainTimedOut,
        );
    }

    /**
     * @param list<DynamicRoundResultVo> $roundVos
     *
     * @return list<ChainDynamicRoundResultDto>
     */
    private function toRoundResultDtos(array $roundVos): array
    {
        return array_map(
            static fn(DynamicRoundResultVo $roundVo): ChainDynamicRoundResultDto => new ChainDynamicRoundResultDto(
                round: $roundVo->getRound(),
                role: $roundVo->getRole(),
                isFacilitator: $roundVo->isFacilitator(),
                outputText: $roundVo->getOutputText(),
                inputTokens: $roundVo->getInputTokens(),
                outputTokens: $roundVo->getOutputTokens(),
                cost: $roundVo->getCost(),
                duration: $roundVo->getDuration(),
                isError: $roundVo->isError(),
                errorMessage: $roundVo->getErrorMessage(),
                invocation: $roundVo->getInvocation(),
                systemPrompt: $roundVo->getSystemPrompt(),
                userPrompt: $roundVo->getUserPrompt(),
                timedOut: $roundVo->isTimedOut(),
                model: $roundVo->getModel(),
                provider: $roundVo->getProvider(),
                thinking: $roundVo->getThinking(),
                cacheReadTokens: $roundVo->getCacheReadTokens(),
                cacheWriteTokens: $roundVo->getCacheWriteTokens(),
            ),
            $roundVos,
        );
    }
}
