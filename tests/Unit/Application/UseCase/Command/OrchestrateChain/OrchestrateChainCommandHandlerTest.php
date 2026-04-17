<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\UseCase\Command\OrchestrateChain;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain\ExecuteStaticChainServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommandHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\StepResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Port\AgentRunnerPortInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Port\AgentRunnerRegistryPortInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerFactoryInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\BuildDynamicContextService;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Session\ChainSessionLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\RunDynamicLoopServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainSessionStateVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainContextVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicLoopResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicRoundResultVo;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrchestrateChainCommandHandler::class)]
#[CoversClass(OrchestrateChainCommand::class)]
final class OrchestrateChainCommandHandlerTest extends TestCase
{
    private ChainLoaderInterface $chainLoader;
    private AgentRunnerRegistryPortInterface $runnerRegistry;
    private ExecuteStaticChainServiceInterface $staticChainExecutor;
    private RunDynamicLoopServiceInterface $dynamicLoopRunner;
    private ChainSessionLoggerInterface $sessionLogger;
    private AuditLoggerFactoryInterface $auditLoggerFactory;
    private ?AuditLoggerInterface $defaultAuditLogger;
    private OrchestrateChainCommandHandler $handler;

    protected function setUp(): void
    {
        $this->chainLoader = $this->createMock(ChainLoaderInterface::class);
        $this->runnerRegistry = $this->createMock(AgentRunnerRegistryPortInterface::class);
        $this->staticChainExecutor = $this->createMock(ExecuteStaticChainServiceInterface::class);
        $this->dynamicLoopRunner = $this->createMock(RunDynamicLoopServiceInterface::class);
        $this->sessionLogger = $this->createMock(ChainSessionLoggerInterface::class);
        $this->auditLoggerFactory = $this->createMock(AuditLoggerFactoryInterface::class);
        $this->defaultAuditLogger = null;

        $this->sessionLogger->method('startSession')->willReturn('/tmp/test-session');
        $this->sessionLogger->method('logInvocation');
        $this->sessionLogger->method('completeSession');
        $this->sessionLogger->method('interruptSession');

        $mockRunner = $this->createMock(AgentRunnerPortInterface::class);
        $this->runnerRegistry->method('get')->willReturn($mockRunner);

        $this->handler = $this->createHandler();
    }

    private function createHandler(): OrchestrateChainCommandHandler
    {
        return new OrchestrateChainCommandHandler(
            $this->chainLoader,
            $this->runnerRegistry,
            $this->staticChainExecutor,
            $this->dynamicLoopRunner,
            new BuildDynamicContextService(),
            $this->sessionLogger,
            $this->auditLoggerFactory,
            $this->defaultAuditLogger,
        );
    }

    // --- Static chain tests ---

    #[Test]
    public function invokeDelegatesStaticChainToOrchestrator(): void
    {
        $chain = ChainDefinitionVo::createFromSteps(
            name: 'test',
            description: 'Test chain',
            steps: [
                ChainStepVo::agent(role: 'system_analyst', runner: 'pi'),
                ChainStepVo::agent(role: 'backend_developer', runner: 'pi'),
            ],
        );

        $this->chainLoader->method('load')->with('test')->willReturn($chain);

        $staticResult = new OrchestrateChainResultDto(
            stepResults: [
                new StepResultDto(
                    role: 'system_analyst',
                    runner: 'pi',
                    outputText: 'Step 1',
                    inputTokens: 100,
                    outputTokens: 50,
                    cost: 0.01,
                    duration: 1.0,
                    isError: false,
                    errorMessage: null,
                ),
            ],
            totalTime: 1.0,
            totalInputTokens: 100,
            totalOutputTokens: 50,
            totalCost: 0.01,
        );

        $this->staticChainExecutor->method('execute')->willReturn($staticResult);

        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'test',
            task: 'Implement feature',
        ));

        self::assertSame($staticResult, $result);
        self::assertCount(1, $result->stepResults);
        self::assertSame('system_analyst', $result->stepResults[0]->role);
    }

    #[Test]
    public function invokeStaticPassesRunnerOverride(): void
    {
        $chain = ChainDefinitionVo::createFromSteps(
            name: 'runner-test',
            description: '',
            steps: [ChainStepVo::agent(role: 'step1', runner: 'pi')],
        );

        $this->chainLoader->method('load')->willReturn($chain);

        $staticResult = new OrchestrateChainResultDto();
        $capturedRunner = null;
        $this->staticChainExecutor->method('execute')
            ->willReturnCallback(function (ChainDefinitionVo $c, AgentRunnerPortInterface $r, string $runnerName) use (&$capturedRunner, $staticResult): OrchestrateChainResultDto {
                $capturedRunner = $runnerName;

                return $staticResult;
            });

        ($this->handler)(new OrchestrateChainCommand(
            chainName: 'runner-test',
            task: 'Test',
            runner: 'codex',
        ));

        self::assertSame('codex', $capturedRunner);
    }

    #[Test]
    public function invokeStaticUsesDefaultRunnerPiWhenNotProvided(): void
    {
        $chain = ChainDefinitionVo::createFromSteps(
            name: 'default-runner',
            description: '',
            steps: [ChainStepVo::agent(role: 'step1', runner: 'pi')],
        );

        $this->chainLoader->method('load')->willReturn($chain);

        $capturedRunner = null;
        $this->staticChainExecutor->method('execute')
            ->willReturnCallback(function (ChainDefinitionVo $c, AgentRunnerPortInterface $r, string $runnerName) use (&$capturedRunner): OrchestrateChainResultDto {
                $capturedRunner = $runnerName;

                return new OrchestrateChainResultDto();
            });

        ($this->handler)(new OrchestrateChainCommand(
            chainName: 'default-runner',
            task: 'Test',
        ));

        self::assertSame('pi', $capturedRunner);
    }

    // --- Dynamic chain tests ---

    #[Test]
    public function invokeExecutesDynamicChainWithFacilitatorDone(): void
    {
        $chain = $this->createDynamicChain(
            name: 'brainstorm',
            facilitator: 'system_analyst',
            participants: ['architect', 'marketer'],
            maxRounds: 10,
        );

        $this->chainLoader->method('load')->with('brainstorm')->willReturn($chain);

        $loopResult = new DynamicLoopResultVo(
            roundResults: [
                new DynamicRoundResultVo(round: 1, role: 'system_analyst', isFacilitator: true, outputText: '{"next_role":"architect"}', inputTokens: 200, outputTokens: 20, cost: 0.02, duration: 1.0, isError: false, errorMessage: null, invocation: '', systemPrompt: '', userPrompt: ''),
                new DynamicRoundResultVo(round: 2, role: 'architect', isFacilitator: false, outputText: 'Architect suggests microservices.', inputTokens: 300, outputTokens: 100, cost: 0.05, duration: 2.0, isError: false, errorMessage: null, invocation: '', systemPrompt: '', userPrompt: ''),
                new DynamicRoundResultVo(round: 3, role: 'system_analyst', isFacilitator: true, outputText: '{"done":true}', inputTokens: 400, outputTokens: 50, cost: 0.04, duration: 1.5, isError: false, errorMessage: null, invocation: '', systemPrompt: '', userPrompt: ''),
            ],
            totalTime: 4.5,
            totalInputTokens: 900,
            totalOutputTokens: 170,
            totalCost: 0.11,
            synthesis: 'Use microservices approach',
            maxRoundsReached: false,
        );

        $this->dynamicLoopRunner->method('execute')->willReturn($loopResult);

        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'brainstorm',
            task: 'Design a new system',
        ));

        self::assertCount(3, $result->roundResults);
        self::assertEmpty($result->stepResults);
        self::assertSame('Use microservices approach', $result->synthesis);
        self::assertFalse($result->maxRoundsReached);
        self::assertSame('/tmp/test-session', $result->sessionDir);
    }

    #[Test]
    public function invokeDynamicUsesQueryOverrides(): void
    {
        $chain = $this->createDynamicChain(
            name: 'dyn-override',
            facilitator: 'default_facilitator',
            participants: ['default_participant'],
            maxRounds: 20,
        );

        $this->chainLoader->method('load')->willReturn($chain);

        $loopResult = new DynamicLoopResultVo(
            roundResults: [],
            totalTime: 0.0,
            totalInputTokens: 0,
            totalOutputTokens: 0,
            totalCost: 0.0,
            synthesis: 'Quick summary',
            maxRoundsReached: false,
        );

        $capturedContext = null;
        $this->dynamicLoopRunner->method('execute')->willReturnCallback(
            function (
                ChainDefinitionVo $chain,
                AgentRunnerPortInterface $runner,
                DynamicChainContextVo $context,
            ) use (
                &$capturedContext,
                $loopResult
            ): DynamicLoopResultVo {
                $capturedContext = $context;

                return $loopResult;
            },
        );

        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'dyn-override',
            task: 'Test',
            facilitator: 'custom_facilitator',
            maxRounds: 2,
        ));

        self::assertSame('Quick summary', $result->synthesis);
        self::assertSame('custom_facilitator', $capturedContext->facilitatorRole);
        self::assertSame(2, $capturedContext->maxRounds);
    }

    #[Test]
    public function invokeDynamicWithTopicOverride(): void
    {
        $chain = $this->createDynamicChain(
            name: 'dyn-topic',
            facilitator: 'facilitator',
            participants: ['participant'],
        );

        $this->chainLoader->method('load')->willReturn($chain);

        $loopResult = new DynamicLoopResultVo(
            roundResults: [],
            totalTime: 0.0,
            totalInputTokens: 0,
            totalOutputTokens: 0,
            totalCost: 0.0,
            synthesis: 'Done',
            maxRoundsReached: false,
        );

        $capturedTopic = null;
        $this->dynamicLoopRunner->method('execute')->willReturnCallback(
            function (
                ChainDefinitionVo $chain,
                AgentRunnerPortInterface $runner,
                DynamicChainContextVo $context,
            ) use (
                &$capturedTopic,
                $loopResult
            ): DynamicLoopResultVo {
                $capturedTopic = $context->topic;

                return $loopResult;
            },
        );

        ($this->handler)(new OrchestrateChainCommand(
            chainName: 'dyn-topic',
            task: 'task text',
            topic: 'Custom topic',
        ));

        self::assertSame('Custom topic', $capturedTopic);
    }

    #[Test]
    public function invokeDynamicStartsAndFinalizesSession(): void
    {
        $chain = $this->createDynamicChain(
            name: 'brainstorm',
            facilitator: 'system_analyst',
            participants: ['architect'],
            maxRounds: 5,
        );

        $this->chainLoader->method('load')->willReturn($chain);

        $loopResult = new DynamicLoopResultVo(
            roundResults: [],
            totalTime: 1.0,
            totalInputTokens: 100,
            totalOutputTokens: 50,
            totalCost: 0.01,
            synthesis: 'Result',
            maxRoundsReached: false,
        );

        $this->dynamicLoopRunner->method('execute')->willReturn($loopResult);

        $startSessionCalled = false;
        $completeSessionCalled = false;
        $this->sessionLogger->method('startSession')
            ->willReturnCallback(function () use (&$startSessionCalled): string {
                $startSessionCalled = true;

                return '/tmp/session';
            });
        $this->sessionLogger->method('completeSession')
            ->willReturnCallback(function () use (&$completeSessionCalled): void {
                $completeSessionCalled = true;
            });

        ($this->handler)(new OrchestrateChainCommand(
            chainName: 'brainstorm',
            task: 'Test',
        ));

        self::assertTrue($startSessionCalled);
        self::assertTrue($completeSessionCalled);
    }

    #[Test]
    public function invokeDynamicInterruptsSessionWhenNoSynthesis(): void
    {
        $chain = $this->createDynamicChain(
            name: 'interrupt',
            facilitator: 'facilitator',
            participants: ['participant'],
        );

        $this->chainLoader->method('load')->willReturn($chain);

        $loopResult = new DynamicLoopResultVo(
            roundResults: [
                new DynamicRoundResultVo(round: 1, role: 'facilitator', isFacilitator: true, outputText: 'error', inputTokens: 0, outputTokens: 0, cost: 0.0, duration: 0.0, isError: true, errorMessage: 'Agent error', invocation: '', systemPrompt: '', userPrompt: ''),
            ],
            totalTime: 0.0,
            totalInputTokens: 0,
            totalOutputTokens: 0,
            totalCost: 0.0,
            synthesis: null,
            maxRoundsReached: false,
            interruptionReason: 'agent_error',
        );

        $this->dynamicLoopRunner->method('execute')->willReturn($loopResult);

        $interruptCalled = false;
        $this->sessionLogger->method('interruptSession')
            ->willReturnCallback(function () use (&$interruptCalled): void {
                $interruptCalled = true;
            });

        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'interrupt',
            task: 'Test',
        ));

        self::assertTrue($interruptCalled);
        self::assertNull($result->synthesis);
    }

    #[Test]
    public function invokeDynamicLogsInvocationViaPromptBuilder(): void
    {
        $chain = $this->createDynamicChain('brainstorm', 'system_analyst', ['architect'], 1);
        $this->chainLoader->method('load')->willReturn($chain);

        $loopResult = new DynamicLoopResultVo(
            roundResults: [],
            totalTime: 0.0,
            totalInputTokens: 0,
            totalOutputTokens: 0,
            totalCost: 0.0,
            synthesis: 'Done',
            maxRoundsReached: false,
        );

        $this->dynamicLoopRunner->method('execute')->willReturn($loopResult);

        $logCapture = null;
        $this->sessionLogger->method('logInvocation')
            ->willReturnCallback(function (array $inv) use (&$logCapture): void {
                $logCapture = $inv;
            });

        ($this->handler)(new OrchestrateChainCommand(
            chainName: 'brainstorm',
            task: 'Design a system',
            model: 'gpt-4o-mini',
            timeout: 60,
        ));

        self::assertNotNull($logCapture);
        self::assertSame('Design a system', $logCapture['task']);
        self::assertSame('gpt-4o-mini', $logCapture['model']);
        self::assertSame(60, $logCapture['timeout']);
        self::assertSame('system_analyst', $logCapture['facilitator']);
        self::assertSame(1, $logCapture['max_rounds']);
    }

    // --- Resume tests ---

    #[Test]
    public function resumeDynamicThrowsWhenStateIsNull(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Failed to resume session');

        $this->sessionLogger->method('resumeSession');
        $this->sessionLogger->method('getResumedState')->willReturn(null);

        ($this->handler)(new OrchestrateChainCommand(
            chainName: 'test',
            task: 'Test',
            resumeDir: '/tmp/resume-dir',
        ));
    }

    #[Test]
    public function resumeDynamicResumesWithState(): void
    {
        $chain = $this->createDynamicChain('brainstorm', 'facilitator', ['participant'], 5);
        $this->chainLoader->method('load')->willReturn($chain);

        $state = new ChainSessionStateVo(
            topic: 'Resumed topic',
            facilitator: 'facilitator',
            participants: ['participant'],
            maxRounds: 5,
            completedRounds: 3,
            discussionHistory: 'History',
            facilitatorJournal: 'Journal',
        );

        $this->sessionLogger->method('resumeSession');
        $this->sessionLogger->method('getResumedState')->willReturn($state);

        $loopResult = new DynamicLoopResultVo(
            roundResults: [],
            totalTime: 1.0,
            totalInputTokens: 100,
            totalOutputTokens: 50,
            totalCost: 0.01,
            synthesis: 'Resumed result',
            maxRoundsReached: false,
        );

        $capturedStartRound = 0;
        $capturedHistory = '';
        $capturedJournal = '';
        $this->dynamicLoopRunner->method('execute')
            ->willReturnCallback(function (
                ChainDefinitionVo $c,
                AgentRunnerPortInterface $runner,
                DynamicChainContextVo $ctx,
                int $startRound = 0,
                string $history = '',
                string $journal = '',
                ?AuditLoggerInterface $auditLogger = null,
            ) use (
                &$capturedStartRound,
                &$capturedHistory,
                &$capturedJournal,
                $loopResult
): DynamicLoopResultVo {
                $capturedStartRound = $startRound;
                $capturedHistory = $history;
                $capturedJournal = $journal;

                return $loopResult;
            });

        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'brainstorm',
            task: 'Test',
            resumeDir: '/tmp/resume-dir',
        ));

        self::assertSame('Resumed result', $result->synthesis);
        self::assertSame('/tmp/resume-dir', $result->sessionDir);
        self::assertSame(3, $capturedStartRound);
        self::assertSame('History', $capturedHistory);
        self::assertSame('Journal', $capturedJournal);
    }

    // --- Audit logger DI tests ---

    #[Test]
    public function auditUsesDefaultLoggerWhenNoPathProvided(): void
    {
        $defaultLogger = $this->createMock(AuditLoggerInterface::class);
        $this->defaultAuditLogger = $defaultLogger;
        $this->handler = $this->createHandler();

        $chain = ChainDefinitionVo::createFromSteps(
            name: 'audit-default',
            description: '',
            steps: [ChainStepVo::agent(role: 'step1', runner: 'pi')],
        );

        $this->chainLoader->method('load')->willReturn($chain);

        $capturedLogger = null;
        $this->staticChainExecutor->method('execute')
            ->willReturnCallback(function (ChainDefinitionVo $c, AgentRunnerPortInterface $runner, string $runnerName, string $t, ?string $m, ?string $w, int $to, ?AuditLoggerInterface $logger) use (&$capturedLogger): OrchestrateChainResultDto {
                $capturedLogger = $logger;

                return new OrchestrateChainResultDto();
            });

        ($this->handler)(new OrchestrateChainCommand(
            chainName: 'audit-default',
            task: 'Test',
        ));

        self::assertSame($defaultLogger, $capturedLogger);
    }

    #[Test]
    public function auditOverrideUsesFactoryWhenPathProvided(): void
    {
        $defaultLogger = $this->createMock(AuditLoggerInterface::class);
        $customLogger = $this->createMock(AuditLoggerInterface::class);
        $this->defaultAuditLogger = $defaultLogger;
        $this->auditLoggerFactory->method('create')
            ->with('/custom/audit.jsonl')
            ->willReturn($customLogger);
        $this->handler = $this->createHandler();

        $chain = ChainDefinitionVo::createFromSteps(
            name: 'audit-override',
            description: '',
            steps: [ChainStepVo::agent(role: 'step1', runner: 'pi')],
        );

        $this->chainLoader->method('load')->willReturn($chain);

        $capturedLogger = null;
        $this->staticChainExecutor->method('execute')
            ->willReturnCallback(function (ChainDefinitionVo $c, AgentRunnerPortInterface $runner, string $runnerName, string $t, ?string $m, ?string $w, int $to, ?AuditLoggerInterface $logger) use (&$capturedLogger): OrchestrateChainResultDto {
                $capturedLogger = $logger;

                return new OrchestrateChainResultDto();
            });

        ($this->handler)(new OrchestrateChainCommand(
            chainName: 'audit-override',
            task: 'Test',
            auditLogPath: '/custom/audit.jsonl',
        ));

        self::assertSame($customLogger, $capturedLogger);
    }

    #[Test]
    public function auditDisabledWithNoAuditLogFlag(): void
    {
        $defaultLogger = $this->createMock(AuditLoggerInterface::class);
        $this->defaultAuditLogger = $defaultLogger;
        $this->handler = $this->createHandler();

        $chain = ChainDefinitionVo::createFromSteps(
            name: 'audit-disabled',
            description: '',
            steps: [ChainStepVo::agent(role: 'step1', runner: 'pi')],
        );

        $this->chainLoader->method('load')->willReturn($chain);

        $capturedLogger = null;
        $this->staticChainExecutor->method('execute')
            ->willReturnCallback(function (ChainDefinitionVo $c, AgentRunnerPortInterface $runner, string $runnerName, string $t, ?string $m, ?string $w, int $to, ?AuditLoggerInterface $logger) use (&$capturedLogger): OrchestrateChainResultDto {
                $capturedLogger = $logger;

                return new OrchestrateChainResultDto();
            });

        ($this->handler)(new OrchestrateChainCommand(
            chainName: 'audit-disabled',
            task: 'Test',
            noAuditLog: true,
        ));

        self::assertNull($capturedLogger);
    }

    #[Test]
    public function auditNoDefaultLoggerReturnsNullWhenNotProvided(): void
    {
        $this->defaultAuditLogger = null;
        $this->handler = $this->createHandler();

        $chain = ChainDefinitionVo::createFromSteps(
            name: 'audit-no-default',
            description: '',
            steps: [ChainStepVo::agent(role: 'step1', runner: 'pi')],
        );

        $this->chainLoader->method('load')->willReturn($chain);

        $capturedLogger = null;
        $this->staticChainExecutor->method('execute')
            ->willReturnCallback(function (ChainDefinitionVo $c, AgentRunnerPortInterface $runner, string $runnerName, string $t, ?string $m, ?string $w, int $to, ?AuditLoggerInterface $logger) use (&$capturedLogger): OrchestrateChainResultDto {
                $capturedLogger = $logger;

                return new OrchestrateChainResultDto();
            });

        ($this->handler)(new OrchestrateChainCommand(
            chainName: 'audit-no-default',
            task: 'Test',
        ));

        self::assertNull($capturedLogger);
    }

    // --- Helpers ---

    /**
     * @param list<string> $participants
     */
    private function createDynamicChain(
        string $name,
        string $facilitator,
        array $participants,
        int $maxRounds = 10,
    ): ChainDefinitionVo {
        return ChainDefinitionVo::createFromDynamic(
            name: $name,
            description: '',
            facilitator: $facilitator,
            participants: $participants,
            maxRounds: $maxRounds,
            brainstormSystemPrompt: 'Base system prompt',
            facilitatorAppendPrompt: 'Fac append %s',
            facilitatorStartPrompt: 'Start %s',
            facilitatorContinuePrompt: 'Cont %s %s %s',
            facilitatorFinalizePrompt: 'Final %s %s',
            participantAppendPrompt: 'Part append %s',
            participantUserPrompt: 'Ctx %s %s',
        );
    }

    private function createDynamicContextDto(
        string $facilitatorRole = 'facilitator',
        array $participants = ['participant'],
        int $maxRounds = 10,
        string $topic = 'test topic',
        string $runnerName = 'pi',
    ): DynamicChainContextVo {
        return new DynamicChainContextVo(
            facilitatorRole: $facilitatorRole,
            participants: $participants,
            maxRounds: $maxRounds,
            topic: $topic,
            runnerName: $runnerName,
            brainstormSystemPrompt: 'Base system prompt',
            facilitatorAppendPrompt: 'Fac append participant',
            facilitatorStartPrompt: 'Start %s',
            facilitatorContinuePrompt: 'Cont %s %s %s',
            facilitatorFinalizePrompt: 'Final %s %s',
            participantAppendPrompt: 'Part append %s',
            participantUserPrompt: 'Ctx %s %s',
        );
    }
}
