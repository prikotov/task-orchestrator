<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Service\Chain\Dynamic;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Budget\CheckDynamicBudgetServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\CheckDynamicLoopBudgetService;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\CheckDynamicLoopBudgetServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\ExecuteDynamicTurnService;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\FormatDynamicJournalServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\RecordDynamicRoundServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\RunDynamicLoopAgentServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Session\ChainSessionLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainTurnResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicBudgetCheckVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainContextVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FacilitatorResponseVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\RoleConfigVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\TurnBreakVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\TurnContinueVo;

#[CoversClass(ExecuteDynamicTurnService::class)]
final class ExecuteDynamicTurnServiceTest extends TestCase
{
    private RunDynamicLoopAgentServiceInterface $agentRunner;
    private RecordDynamicRoundServiceInterface $roundRecorder;
    private FormatDynamicJournalServiceInterface $journal;
    private ChainSessionLoggerInterface $sessionLogger;
    private CheckDynamicLoopBudgetServiceInterface $budgetChecker;
    private ExecuteDynamicTurnService $service;

    protected function setUp(): void
    {
        $this->agentRunner = $this->createMock(RunDynamicLoopAgentServiceInterface::class);
        $this->roundRecorder = $this->createMock(RecordDynamicRoundServiceInterface::class);
        $this->journal = $this->createMock(FormatDynamicJournalServiceInterface::class);
        $this->sessionLogger = $this->createMock(ChainSessionLoggerInterface::class);
        $this->budgetChecker = $this->createMock(CheckDynamicLoopBudgetServiceInterface::class);

        $this->service = new ExecuteDynamicTurnService(
            $this->agentRunner,
            $this->roundRecorder,
            $this->journal,
            $this->sessionLogger,
            $this->budgetChecker,
        );
    }

    // ─── resolveRunner ────────────────────────────────────────────────

    #[Test]
    public function resolveRunnerReturnsFirstCommandElement(): void
    {
        $config = new RoleConfigVo(command: ['pi', '--model', 'gpt-4'], timeout: 60);

        self::assertSame('pi', ExecuteDynamicTurnService::resolveRunner($config));
    }

    #[Test]
    public function resolveRunnerThrowsOnNullConfig(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Role configuration must define a non-empty command');

        ExecuteDynamicTurnService::resolveRunner(null);
    }

    #[Test]
    public function resolveRunnerThrowsOnEmptyCommand(): void
    {
        $config = new RoleConfigVo(command: [], timeout: 60);

        $this->expectException(LogicException::class);

        ExecuteDynamicTurnService::resolveRunner($config);
    }

    #[Test]
    public function resolveRunnerThrowsOnEmptyFirstElement(): void
    {
        $config = new RoleConfigVo(command: ['', '--model'], timeout: 60);

        $this->expectException(LogicException::class);

        ExecuteDynamicTurnService::resolveRunner($config);
    }

    // ─── runFacilitatorTurn ───────────────────────────────────────────

    #[Test]
    public function runFacilitatorTurnReturnsContinueWhenNextRole(): void
    {
        $chain = $this->createDynamicChain('test', 'facilitator', ['architect']);
        $context = $this->createContext('facilitator', ['architect']);
        $execution = new DynamicLoopExecution();
        $execution->advanceStep();
        $execution->advanceRound();

        $turnResult = $this->createSuccessTurnResult('response');
        $facResponse = FacilitatorResponseVo::createFromNextRole('architect', 'challenge text');

        $this->agentRunner->method('runFacilitator')->willReturn([$turnResult, $facResponse]);
        $this->roundRecorder->method('record');
        $this->journal->method('formatFacilitatorEntry')->willReturn('');
        $this->journal->method('formatFacilitatorDiscussionEntry')->willReturn('');
        $this->sessionLogger->method('getResponseFilePaths')->willReturn([]);
        $this->sessionLogger->method('writeContextFile');
        $this->budgetChecker->method('checkAndApply')->willReturn(null);

        $result = $this->service->runFacilitatorTurn(
            $chain, $context, $execution, null, null,
        );

        self::assertInstanceOf(TurnContinueVo::class, $result);
        self::assertSame('architect', $result->nextRole);
        self::assertSame('challenge text', $result->challenge);
    }

    #[Test]
    public function runFacilitatorTurnReturnsBreakOnBudgetExceeded(): void
    {
        $chain = $this->createDynamicChain('test', 'facilitator', ['architect']);
        $context = $this->createContext('facilitator', ['architect']);
        $execution = new DynamicLoopExecution();
        $execution->advanceStep();
        $execution->advanceRound();

        $turnResult = $this->createSuccessTurnResult('response');
        $facResponse = FacilitatorResponseVo::createFromNextRole('architect');

        $budgetCheck = new DynamicBudgetCheckVo(
            shouldBreak: true,
            budgetExceeded: true,
            budgetLimit: 5.0,
        );

        $this->agentRunner->method('runFacilitator')->willReturn([$turnResult, $facResponse]);
        $this->roundRecorder->method('record');
        $this->journal->method('formatFacilitatorEntry')->willReturn('');
        $this->journal->method('formatFacilitatorDiscussionEntry')->willReturn('');
        $this->sessionLogger->method('getResponseFilePaths')->willReturn([]);
        $this->sessionLogger->method('writeContextFile');
        $this->budgetChecker->method('checkAndApply')->willReturn($budgetCheck);

        $result = $this->service->runFacilitatorTurn(
            $chain, $context, $execution, new BudgetVo(maxCostTotal: 5.0), null,
        );

        self::assertInstanceOf(TurnBreakVo::class, $result);
        self::assertSame('budget_exceeded', $result->interruptionReason);
        self::assertSame($budgetCheck, $result->budgetResult);
    }

    #[Test]
    public function runFacilitatorTurnReturnsBreakOnAgentError(): void
    {
        $chain = $this->createDynamicChain('test', 'facilitator', ['architect']);
        $context = $this->createContext('facilitator', ['architect']);
        $execution = new DynamicLoopExecution();
        $execution->advanceStep();
        $execution->advanceRound();

        $turnResult = $this->createErrorTurnResult('Agent crashed');
        $facResponse = FacilitatorResponseVo::createFromNextRole('architect');

        $this->agentRunner->method('runFacilitator')->willReturn([$turnResult, $facResponse]);
        $this->roundRecorder->method('record');
        $this->journal->method('formatFacilitatorEntry')->willReturn('');
        $this->journal->method('formatFacilitatorDiscussionEntry')->willReturn('');
        $this->sessionLogger->method('getResponseFilePaths')->willReturn([]);
        $this->sessionLogger->method('writeContextFile');
        $this->budgetChecker->method('checkAndApply')->willReturn(null);
        $this->sessionLogger->expects(self::once())->method('interruptSession')->with('agent_error');

        $result = $this->service->runFacilitatorTurn(
            $chain, $context, $execution, null, null,
        );

        self::assertInstanceOf(TurnBreakVo::class, $result);
        self::assertSame('agent_error', $result->interruptionReason);
    }

    #[Test]
    public function runFacilitatorTurnReturnsBreakWithSynthesisWhenDone(): void
    {
        $chain = $this->createDynamicChain('test', 'facilitator', ['architect']);
        $context = $this->createContext('facilitator', ['architect']);
        $execution = new DynamicLoopExecution();
        $execution->advanceStep();
        $execution->advanceRound();

        $turnResult = $this->createSuccessTurnResult('{"done":true,"synthesis":"Final answer"}');
        $facResponse = FacilitatorResponseVo::createFromDone('Final answer');

        $this->agentRunner->method('runFacilitator')->willReturn([$turnResult, $facResponse]);
        $this->roundRecorder->method('record');
        $this->journal->method('formatFacilitatorEntry')->willReturn('');
        $this->journal->method('formatFacilitatorDiscussionEntry')->willReturn('');
        $this->sessionLogger->method('getResponseFilePaths')->willReturn([]);
        $this->sessionLogger->method('writeContextFile');
        $this->budgetChecker->method('checkAndApply')->willReturn(null);

        $result = $this->service->runFacilitatorTurn(
            $chain, $context, $execution, null, null,
        );

        self::assertInstanceOf(TurnBreakVo::class, $result);
        self::assertSame('Final answer', $result->synthesis);
    }

    #[Test]
    public function runFacilitatorTurnReturnsTimeoutBreak(): void
    {
        $chain = $this->createDynamicChain('test', 'facilitator', ['architect']);
        $context = $this->createContext('facilitator', ['architect']);
        $execution = new DynamicLoopExecution();
        $execution->advanceStep();
        $execution->advanceRound();

        $turnResult = $this->createTimedOutTurnResult();
        $facResponse = FacilitatorResponseVo::createFromNextRole('architect');

        $this->agentRunner->method('runFacilitator')->willReturn([$turnResult, $facResponse]);
        $this->roundRecorder->method('record');
        $this->journal->method('formatFacilitatorEntry')->willReturn('');
        $this->journal->method('formatFacilitatorDiscussionEntry')->willReturn('');
        $this->sessionLogger->method('getResponseFilePaths')->willReturn([]);
        $this->sessionLogger->method('writeContextFile');
        $this->budgetChecker->method('checkAndApply')->willReturn(null);
        $this->sessionLogger->expects(self::once())->method('interruptSession')->with('timeout');

        $result = $this->service->runFacilitatorTurn(
            $chain, $context, $execution, null, null,
        );

        self::assertInstanceOf(TurnBreakVo::class, $result);
        self::assertSame('timeout', $result->interruptionReason);
    }

    // ─── runParticipantTurn ───────────────────────────────────────────

    #[Test]
    public function runParticipantTurnReturnsContinueWhenNoNextRole(): void
    {
        $chain = $this->createDynamicChain('test', 'facilitator', ['architect']);
        $context = $this->createContext('facilitator', ['architect']);
        $execution = new DynamicLoopExecution();

        $result = $this->service->runParticipantTurn(
            $chain, $context, $execution, null, null, null, null,
        );

        self::assertInstanceOf(TurnContinueVo::class, $result);
        self::assertNull($result->nextRole);
    }

    #[Test]
    public function runParticipantTurnReturnsContinueWhenRoleNotInParticipants(): void
    {
        $chain = $this->createDynamicChain('test', 'facilitator', ['architect']);
        $context = $this->createContext('facilitator', ['architect']);
        $execution = new DynamicLoopExecution();

        $result = $this->service->runParticipantTurn(
            $chain, $context, $execution, null, null, 'unknown_role', null,
        );

        self::assertInstanceOf(TurnContinueVo::class, $result);
    }

    #[Test]
    public function runParticipantTurnReturnsContinueWhenMaxRoundsExceeded(): void
    {
        $chain = $this->createDynamicChain('test', 'facilitator', ['architect']);
        $context = $this->createContext('facilitator', ['architect'], maxRounds: 0);
        $execution = new DynamicLoopExecution();

        $result = $this->service->runParticipantTurn(
            $chain, $context, $execution, null, null, 'architect', null,
        );

        self::assertInstanceOf(TurnContinueVo::class, $result);
    }

    #[Test]
    public function runParticipantTurnReturnsBreakOnBudgetExceeded(): void
    {
        $chain = $this->createDynamicChain('test', 'facilitator', ['architect']);
        $context = $this->createContext('facilitator', ['architect']);
        $execution = new DynamicLoopExecution();

        $turnResult = $this->createSuccessTurnResult('My response');
        $budgetCheck = new DynamicBudgetCheckVo(
            shouldBreak: true,
            budgetExceeded: true,
        );

        $this->agentRunner->method('runParticipant')->willReturn($turnResult);
        $this->roundRecorder->method('record');
        $this->journal->method('formatDiscussionEntry')->willReturn('');
        $this->journal->method('formatParticipantEntry')->willReturn('');
        $this->sessionLogger->method('getResponseFilePaths')->willReturn([]);
        $this->sessionLogger->method('writeContextFile');
        $this->budgetChecker->method('checkAndApply')->willReturn($budgetCheck);

        $result = $this->service->runParticipantTurn(
            $chain, $context, $execution, new BudgetVo(maxCostTotal: 1.0), null, 'architect', null,
        );

        self::assertInstanceOf(TurnBreakVo::class, $result);
        self::assertSame('budget_exceeded', $result->interruptionReason);
    }

    #[Test]
    public function runParticipantTurnReturnsBreakOnAgentError(): void
    {
        $chain = $this->createDynamicChain('test', 'facilitator', ['architect']);
        $context = $this->createContext('facilitator', ['architect']);
        $execution = new DynamicLoopExecution();

        $turnResult = $this->createErrorTurnResult('Agent failed');

        $this->agentRunner->method('runParticipant')->willReturn($turnResult);
        $this->roundRecorder->method('record');
        $this->journal->method('formatDiscussionEntry')->willReturn('');
        $this->journal->method('formatParticipantEntry')->willReturn('');
        $this->sessionLogger->method('getResponseFilePaths')->willReturn([]);
        $this->sessionLogger->method('writeContextFile');
        $this->budgetChecker->method('checkAndApply')->willReturn(null);
        $this->sessionLogger->expects(self::once())->method('interruptSession');

        $result = $this->service->runParticipantTurn(
            $chain, $context, $execution, null, null, 'architect', 'challenge',
        );

        self::assertInstanceOf(TurnBreakVo::class, $result);
        self::assertSame('agent_error', $result->interruptionReason);
    }

    #[Test]
    public function runParticipantTurnReturnsContinueOnSuccess(): void
    {
        $chain = $this->createDynamicChain('test', 'facilitator', ['architect']);
        $context = $this->createContext('facilitator', ['architect']);
        $execution = new DynamicLoopExecution();

        $turnResult = $this->createSuccessTurnResult('Good response');

        $this->agentRunner->method('runParticipant')->willReturn($turnResult);
        $this->roundRecorder->method('record');
        $this->journal->method('formatDiscussionEntry')->willReturn('');
        $this->journal->method('formatParticipantEntry')->willReturn('');
        $this->sessionLogger->method('getResponseFilePaths')->willReturn([]);
        $this->sessionLogger->method('writeContextFile');
        $this->budgetChecker->method('checkAndApply')->willReturn(null);

        $result = $this->service->runParticipantTurn(
            $chain, $context, $execution, null, null, 'architect', 'challenge',
        );

        self::assertInstanceOf(TurnContinueVo::class, $result);
        self::assertNull($result->nextRole);
    }

    // ─── toRoundResultVo ──────────────────────────────────────────────

    #[Test]
    public function toRoundResultVoMapsAllFields(): void
    {
        $agentResult = ChainRunResultVo::createFromSuccess(
            outputText: 'Hello',
            inputTokens: 100,
            outputTokens: 50,
            cost: 0.05,
        );
        $turnResult = new ChainTurnResultVo(
            agentResult: $agentResult,
            duration: 2.5,
            userPrompt: 'prompt',
            systemPrompt: 'system',
            invocation: 'inv',
        );

        $roundVo = ExecuteDynamicTurnService::toRoundResultVo($turnResult, 1, 'role', true);

        self::assertSame(1, $roundVo->round);
        self::assertSame('role', $roundVo->role);
        self::assertTrue($roundVo->isFacilitator);
        self::assertSame('Hello', $roundVo->outputText);
        self::assertSame(100, $roundVo->inputTokens);
        self::assertSame(50, $roundVo->outputTokens);
        self::assertSame(0.05, $roundVo->cost);
        self::assertSame(2.5, $roundVo->duration);
        self::assertFalse($roundVo->isError);
        self::assertSame('inv', $roundVo->invocation);
        self::assertSame('system', $roundVo->systemPrompt);
        self::assertSame('prompt', $roundVo->userPrompt);
    }

    // ─── Helpers ───────────────────────────────────────────────────────

    private function createSuccessTurnResult(string $output): ChainTurnResultVo
    {
        return new ChainTurnResultVo(
            agentResult: ChainRunResultVo::createFromSuccess(
                outputText: $output,
                inputTokens: 100,
                outputTokens: 50,
                cost: 0.01,
            ),
            duration: 1.5,
        );
    }

    private function createErrorTurnResult(string $errorMessage): ChainTurnResultVo
    {
        return new ChainTurnResultVo(
            agentResult: ChainRunResultVo::createFromError($errorMessage),
            duration: 1.0,
        );
    }

    private function createTimedOutTurnResult(): ChainTurnResultVo
    {
        return new ChainTurnResultVo(
            agentResult: ChainRunResultVo::createFromError('Timed out', timedOut: true),
            duration: 60.0,
        );
    }

    /**
     * @param list<string> $participants
     */
    private function createDynamicChain(
        string $name,
        string $facilitator,
        array $participants,
    ): DynamicChainDefinitionVo {
        $roles = [];
        foreach (array_merge([$facilitator], $participants) as $role) {
            $roles[$role] = new RoleConfigVo(command: ['pi', '--model', 'gpt-4'], timeout: 60);
        }

        return DynamicChainDefinitionVo::create(
            name: $name,
            description: '',
            facilitator: $facilitator,
            participants: $participants,
            maxRounds: 10,
            brainstormSystemPrompt: 'sys',
            facilitatorAppendPrompt: 'fac_append %s',
            facilitatorStartPrompt: 'start %s',
            facilitatorContinuePrompt: 'cont %s %s %s',
            facilitatorFinalizePrompt: 'final %s %s',
            participantAppendPrompt: 'part_append %s',
            participantUserPrompt: 'ctx %s %s',
            roles: $roles,
        );
    }

    /**
     * @param list<string> $participants
     */
    private function createContext(
        string $facilitator,
        array $participants,
        int $maxRounds = 10,
    ): DynamicChainContextVo {
        return new DynamicChainContextVo(
            topic: 'Test topic',
            facilitatorRole: $facilitator,
            participants: $participants,
            maxRounds: $maxRounds,
            maxTime: null,
            timeout: 600,
            workingDir: '/tmp/test',
            promptConfiguration: new \TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\PromptConfigurationVo(
                brainstormSystemPrompt: 'sys',
                facilitatorAppendPrompt: 'fac_append %s',
                facilitatorStartPrompt: 'start %s',
                facilitatorContinuePrompt: 'cont %s %s %s',
                facilitatorFinalizePrompt: 'final %s %s',
                participantAppendPrompt: 'part_append %s',
                participantUserPrompt: 'ctx %s %s',
            ),
        );
    }
}
