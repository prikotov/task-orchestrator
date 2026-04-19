<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Event\OrchestrateChain\OrchestrateSessionCompletedEvent;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain\ExecuteStaticChainServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Integration\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerFactoryInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\BuildDynamicContextServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Session\ChainSessionLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\RunDynamicLoopServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicLoopResultVo;
use LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;

use function count;

/**
 * UseCase оркестрации цепочки AI-агентов.
 *
 * Тонкий координатор: загружает цепочку, создаёт контекст,
 * делегирует выполнение сервисам оркестрации,
 * финализирует сессию.
 */
final readonly class OrchestrateChainCommandHandler
{
    public function __construct(
        private ChainLoaderInterface $chainLoader,
        private RunAgentServiceInterface $agentRunner,
        private ExecuteStaticChainServiceInterface $staticChainExecutor,
        private RunDynamicLoopServiceInterface $dynamicLoopRunner,
        private BuildDynamicContextServiceInterface $contextBuilder,
        private ChainSessionLoggerInterface $sessionLogger,
        private AuditLoggerFactoryInterface $auditLoggerFactory,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    /**
     * Выполняет оркестрацию цепочки.
     */
    public function __invoke(OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        if ($command->resumeDir !== null) {
            return $this->resumeDynamic($command);
        }

        $chain = $this->chainLoader->load($command->chainName);

        return $chain->isDynamic()
            ? $this->executeDynamic($chain, $command)
            : $this->executeStatic($chain, $command);
    }

    // ─── Static chain ────────────────────────────────────────────────────

    private function executeStatic(
        ChainDefinitionVo $chain,
        OrchestrateChainCommand $command,
    ): OrchestrateChainResultDto {
        $runnerName = $command->runner ?? 'pi';

        return $this->staticChainExecutor->execute(
            $chain,
            $runnerName,
            $command->task,
            $command->model,
            $command->workingDir,
            $command->timeout,
            null, // static chains have no session-scoped audit log
            $command->noContextFiles,
        );
    }

    // ─── Dynamic chain ────────────────────────────────────────────────────

    private function executeDynamic(
        ChainDefinitionVo $chain,
        OrchestrateChainCommand $command,
    ): OrchestrateChainResultDto {
        $facilitatorRole = $command->facilitator ?? $chain->getFacilitator() ?? 'team_lead';
        $participants = $command->participants ?? $chain->getParticipants();
        $maxRounds = $command->maxRounds ?? $chain->getMaxRounds();
        $topic = $command->topic ?? $command->task;
        $runnerName = $command->runner ?? 'pi';

        $sessionDir = $this->sessionLogger->startSession(
            $chain->getName(),
            $topic,
            $facilitatorRole,
            $participants,
            $maxRounds,
        );
        $auditLogger = $this->resolveAuditLogger($sessionDir, $command->noAuditLog);
        $this->sessionLogger->setBudget($chain->getBudget());
        $this->sessionLogger->logInvocation(
            $this->contextBuilder->buildInvocation(
                $chain,
                $command->task,
                $command->model,
                $command->timeout,
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
            $runnerName,
            $command->model,
            $command->workingDir,
            $command->timeout,
        );

        $loopResult = $this->runDynamicLoop($chain, $context, $runnerName, auditLogger: $auditLogger);
        $this->finalizeSession($loopResult, $sessionDir);

        return $this->toResultDto($loopResult, $sessionDir);
    }

    private function resumeDynamic(OrchestrateChainCommand $command): OrchestrateChainResultDto
    {
        $resumeDir = $command->resumeDir;
        assert($resumeDir !== null);
        $auditLogger = $this->resolveAuditLogger($resumeDir, $command->noAuditLog);
        $this->sessionLogger->resumeSession($resumeDir);
        $state = $this->sessionLogger->getResumedState();

        if ($state === null) {
            throw new LogicException("Failed to resume session from: {$resumeDir}");
        }

        $chain = $this->chainLoader->load($command->chainName);
        $this->sessionLogger->setBudget($chain->getBudget());

        $invocation = $this->contextBuilder->buildInvocation(
            $chain,
            $command->task,
            $command->model,
            $command->timeout,
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
            $command->runner ?? 'pi',
            $command->model,
            $command->workingDir,
            $command->timeout,
        );

        $loopResult = $this->runDynamicLoop(
            $chain,
            $context,
            $command->runner ?? 'pi',
            $state->getCompletedRounds(),
            $state->getDiscussionHistory(),
            $state->getFacilitatorJournal(),
            $auditLogger,
        );
        $this->finalizeSession($loopResult, $resumeDir);

        return $this->toResultDto($loopResult, $resumeDir);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function runDynamicLoop(
        ChainDefinitionVo $chain,
        \TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainContextVo $context,
        string $runnerName,
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
        $synthesis = $loopResult->synthesis;
        $reason = $loopResult->budgetExceeded
            ? 'budget_exceeded'
            : ($synthesis !== null
                ? ($loopResult->maxRoundsReached ? 'max_rounds_reached' : 'facilitator_done')
                : ($loopResult->interruptionReason ?? 'no_synthesis'));

        if ($synthesis !== null) {
            $this->sessionLogger->completeSession(
                $synthesis,
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
        $synthesis = $loopResult->synthesis;
        $this->eventDispatcher?->dispatch(new OrchestrateSessionCompletedEvent(
            status: $synthesis !== null ? 'completed' : 'interrupted',
            completionReason: $reason,
            totalRounds: count($loopResult->roundResults),
            totalTime: $loopResult->totalTime,
            totalInputTokens: $loopResult->totalInputTokens,
            totalOutputTokens: $loopResult->totalOutputTokens,
            totalCost: $loopResult->totalCost,
            synthesis: $synthesis,
            sessionDir: $sessionDir,
            budgetExceeded: $loopResult->budgetExceeded,
            budgetLimit: $loopResult->budgetLimit,
            budgetExceededRole: $loopResult->budgetExceededRole,
        ));
    }

    private function toResultDto(DynamicLoopResultVo $loopResult, ?string $sessionDir): OrchestrateChainResultDto
    {
        return new OrchestrateChainResultDto(
            roundResults: $this->toRoundResultDtos($loopResult->roundResults),
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
        );
    }

    /**
     * @param list<\TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicRoundResultVo> $roundVos
     *
     * @return list<DynamicRoundResultDto>
     */
    private function toRoundResultDtos(array $roundVos): array
    {
        return array_map(
            static fn(\TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicRoundResultVo $roundVo): DynamicRoundResultDto => new DynamicRoundResultDto(
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
            ),
            $roundVos,
        );
    }
}
