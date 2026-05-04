<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Application\Service;

use LogicException;
use Override;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\Service\Chain\ExecutionStrategyInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\OrchestrateChain\DynamicRoundResultDto as ChainDynamicRoundResultDto;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\DynamicLoop\Application\UseCase\Command\OrchestrateChain\DynamicRoundResultDto;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerFactoryInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\BuildDynamicContextServiceInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\RunDynamicLoopServiceInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\SessionCompletedNotifierInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session\DynamicLoopSessionLoggerInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicRoundResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Integration\Service\ChainDefinition\DynamicLoopDefinitionMapper;

/**
 * Стратегия выполнения dynamic-цепочки.
 *
 * Инкапсулирует фасилитаторный цикл: session start/resume,
 * context build, loop run, finalize, DTO mapping, event dispatch.
 *
 * DynamicLoopConfigVo получается через DynamicLoopDefinitionMapper (Integration).
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
        private DynamicLoopAuditLoggerFactoryInterface $auditLoggerFactory,
        private SessionCompletedNotifierInterface $sessionNotifier,
    ) {
    }

    #[Override]
    public function execute(ChainDefinitionInterface $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        $config = $this->extractConfig($chain);

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
    public function resume(ChainDefinitionInterface $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        $config = $this->extractConfig($chain);

        $resumeDir = $command->resumeDir;
        assert($resumeDir !== null);

        $auditLogger = $this->resolveAuditLogger($resumeDir, $command->noAuditLog);
        $this->sessionLogger->resumeSession($resumeDir);
        $state = $this->sessionLogger->getResumedState();

        if ($state === null) {
            throw new LogicException("Failed to resume session from: {$resumeDir}");
        }

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
    public function supports(ChainDefinitionInterface $chain): bool
    {
        return $chain->getType() === ChainTypeEnum::dynamicType;
    }

    // ─── Private helpers ──────────────────────────────────────────────────

    private function extractConfig(ChainDefinitionInterface $chain): DynamicLoopConfigVo
    {
        if ($chain instanceof DynamicLoopConfigVo) {
            return $chain;
        }

        if ($chain instanceof DynamicChainDefinitionVo) {
            return (new DynamicLoopDefinitionMapper())->map($chain);
        }

        throw new LogicException(
            sprintf('Expected DynamicLoopConfigVo or DynamicChainDefinitionVo, got %s', $chain::class),
        );
    }

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

    private function resolveAuditLogger(?string $sessionDir, bool $noAuditLog = false): ?DynamicLoopAuditLoggerInterface
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

        $chainTimedOut = array_any(
            $roundDtos,
            static fn(ChainDynamicRoundResultDto $round): bool => $round->timedOut,
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
     * @return list<ChainDynamicRoundResultDto>
     */
    private function toRoundResultDtos(array $roundVos): array
    {
        return array_map(
            static fn(DynamicRoundResultVo $roundVo): ChainDynamicRoundResultDto => new ChainDynamicRoundResultDto(
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
