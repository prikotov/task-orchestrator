<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\Service\Chain;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\DynamicLoop\Application\Service\DynamicExecutionStrategy;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerFactoryInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\BuildDynamicContextService;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\BuildDynamicContextServiceInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\RunDynamicLoopServiceInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session\DynamicLoopSessionLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\SessionCompletedNotifierInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ChainDefinitionInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopSessionStateVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicRoundResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopBudgetVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopPromptConfigVo;

#[CoversClass(DynamicExecutionStrategy::class)]
final class DynamicExecutionStrategyTest extends TestCase
{
    private RunDynamicLoopServiceInterface $dynamicLoopRunner;
    private BuildDynamicContextServiceInterface $contextBuilder;
    private DynamicLoopSessionLoggerInterface $sessionLogger;
    private DynamicLoopAuditLoggerFactoryInterface $auditLoggerFactory;
    private SessionCompletedNotifierInterface $sessionNotifier;
    private DynamicExecutionStrategy $strategy;

    protected function setUp(): void
    {
        $this->dynamicLoopRunner = $this->createMock(RunDynamicLoopServiceInterface::class);
        $this->contextBuilder = new BuildDynamicContextService();
        $this->sessionLogger = $this->createMock(DynamicLoopSessionLoggerInterface::class);
        $this->auditLoggerFactory = $this->createMock(DynamicLoopAuditLoggerFactoryInterface::class);
        $this->sessionNotifier = $this->createMock(SessionCompletedNotifierInterface::class);

        $this->sessionLogger->method('startSession')->willReturn('/tmp/test-session');
        $this->sessionLogger->method('logInvocation');
        $this->sessionLogger->method('completeSession');
        $this->sessionLogger->method('interruptSession');

        $this->strategy = $this->createStrategy();
    }

    private function createStrategy(): DynamicExecutionStrategy
    {
        return new DynamicExecutionStrategy(
            $this->contextBuilder,
            $this->dynamicLoopRunner,
            $this->sessionLogger,
            $this->auditLoggerFactory,
            $this->sessionNotifier,
        );
    }

    // --- supports() ---

    #[Test]
    public function supportsReturnsTrueForDynamicChain(): void
    {
        $chain = $this->createDynamicChain('test', 'facilitator', ['participant']);

        self::assertTrue($this->strategy->supports($chain));
    }

    #[Test]
    public function supportsReturnsFalseForStaticChain(): void
    {
        $chain = StaticChainDefinitionVo::create(
            name: 'static',
            description: '',
            steps: [\TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo::agent(role: 'role', runner: 'pi')],
        );

        self::assertFalse($this->strategy->supports($chain));
    }

    // --- execute() ---

    #[Test]
    public function executeRunsDynamicLoopWithFacilitatorDone(): void
    {
        $chain = $this->createDynamicChain(
            name: 'brainstorm',
            facilitator: 'system_analyst',
            participants: ['architect', 'marketer'],
            maxRounds: 10,
        );

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

        $result = $this->strategy->execute($chain, new OrchestrateChainCommand(
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
    public function executeUsesQueryOverrides(): void
    {
        $chain = $this->createDynamicChain(
            name: 'dyn-override',
            facilitator: 'default_facilitator',
            participants: ['default_participant'],
            maxRounds: 20,
        );

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
                DynamicLoopConfigVo $config,
                DynamicLoopContextVo $context,
            ) use (
                &$capturedContext,
                $loopResult,
            ): DynamicLoopResultVo {
                $capturedContext = $context;

                return $loopResult;
            },
        );

        $result = $this->strategy->execute($chain, new OrchestrateChainCommand(
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
    public function executeWithTopicOverride(): void
    {
        $chain = $this->createDynamicChain(
            name: 'dyn-topic',
            facilitator: 'facilitator',
            participants: ['participant'],
        );

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
                DynamicLoopConfigVo $config,
                DynamicLoopContextVo $context,
            ) use (
                &$capturedTopic,
                $loopResult,
            ): DynamicLoopResultVo {
                $capturedTopic = $context->topic;

                return $loopResult;
            },
        );

        $this->strategy->execute($chain, new OrchestrateChainCommand(
            chainName: 'dyn-topic',
            task: 'task text',
            topic: 'Custom topic',
        ));

        self::assertSame('Custom topic', $capturedTopic);
    }

    #[Test]
    public function executeStartsAndFinalizesSession(): void
    {
        $chain = $this->createDynamicChain(
            name: 'brainstorm',
            facilitator: 'system_analyst',
            participants: ['architect'],
            maxRounds: 5,
        );

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

        $this->strategy->execute($chain, new OrchestrateChainCommand(
            chainName: 'brainstorm',
            task: 'Test',
        ));

        self::assertTrue($startSessionCalled);
        self::assertTrue($completeSessionCalled);
    }

    #[Test]
    public function executeInterruptsSessionWhenNoSynthesis(): void
    {
        $chain = $this->createDynamicChain(
            name: 'interrupt',
            facilitator: 'facilitator',
            participants: ['participant'],
        );

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

        $result = $this->strategy->execute($chain, new OrchestrateChainCommand(
            chainName: 'interrupt',
            task: 'Test',
        ));

        self::assertTrue($interruptCalled);
        self::assertNull($result->synthesis);
    }

    #[Test]
    public function executeLogsInvocation(): void
    {
        $chain = $this->createDynamicChain('brainstorm', 'system_analyst', ['architect'], 1);

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

        $this->strategy->execute($chain, new OrchestrateChainCommand(
            chainName: 'brainstorm',
            task: 'Design a system',
            timeout: 60,
        ));

        self::assertNotNull($logCapture);
        self::assertSame('Design a system', $logCapture['task']);
        self::assertSame(60, $logCapture['timeout']);
        self::assertSame('system_analyst', $logCapture['facilitator']);
        self::assertSame(1, $logCapture['max_rounds']);
    }

    #[Test]
    public function executeUsesChainTimeoutWhenNoCliOverride(): void
    {
        $chain = $this->createDynamicChainWithTimeout(
            name: 'timed_chain',
            facilitator: 'facilitator',
            participants: ['participant'],
            timeout: 600,
        );

        $loopResult = new DynamicLoopResultVo(
            roundResults: [],
            totalTime: 0.0,
            totalInputTokens: 0,
            totalOutputTokens: 0,
            totalCost: 0.0,
            synthesis: 'Done',
            maxRoundsReached: false,
        );

        $capturedTimeout = null;
        $this->dynamicLoopRunner->method('execute')->willReturnCallback(
            function (
                DynamicLoopConfigVo $c,
                DynamicLoopContextVo $context,
            ) use (
                &$capturedTimeout,
                $loopResult,
            ): DynamicLoopResultVo {
                $capturedTimeout = $context->timeout;

                return $loopResult;
            },
        );

        $this->strategy->execute($chain, new OrchestrateChainCommand(
            chainName: 'timed_chain',
            task: 'Test',
        ));

        self::assertSame(600, $capturedTimeout);
    }

    #[Test]
    public function executeCliTimeoutOverridesChain(): void
    {
        $chain = $this->createDynamicChainWithTimeout(
            name: 'timed_chain',
            facilitator: 'facilitator',
            participants: ['participant'],
            timeout: 600,
        );

        $loopResult = new DynamicLoopResultVo(
            roundResults: [],
            totalTime: 0.0,
            totalInputTokens: 0,
            totalOutputTokens: 0,
            totalCost: 0.0,
            synthesis: 'Done',
            maxRoundsReached: false,
        );

        $capturedTimeout = null;
        $this->dynamicLoopRunner->method('execute')->willReturnCallback(
            function (
                DynamicLoopConfigVo $c,
                DynamicLoopContextVo $context,
            ) use (
                &$capturedTimeout,
                $loopResult,
            ): DynamicLoopResultVo {
                $capturedTimeout = $context->timeout;

                return $loopResult;
            },
        );

        $this->strategy->execute($chain, new OrchestrateChainCommand(
            chainName: 'timed_chain',
            task: 'Test',
            timeout: 300,
        ));

        self::assertSame(300, $capturedTimeout);
    }

    #[Test]
    public function executeDefaultsTo600WhenNoTimeoutAnyWhere(): void
    {
        $chain = $this->createDynamicChain(
            name: 'no_timeout',
            facilitator: 'facilitator',
            participants: ['participant'],
        );

        $loopResult = new DynamicLoopResultVo(
            roundResults: [],
            totalTime: 0.0,
            totalInputTokens: 0,
            totalOutputTokens: 0,
            totalCost: 0.0,
            synthesis: 'Done',
            maxRoundsReached: false,
        );

        $capturedTimeout = null;
        $this->dynamicLoopRunner->method('execute')->willReturnCallback(
            function (
                DynamicLoopConfigVo $c,
                DynamicLoopContextVo $context,
            ) use (
                &$capturedTimeout,
                $loopResult,
            ): DynamicLoopResultVo {
                $capturedTimeout = $context->timeout;

                return $loopResult;
            },
        );

        $this->strategy->execute($chain, new OrchestrateChainCommand(
            chainName: 'no_timeout',
            task: 'Test',
        ));

        self::assertSame(600, $capturedTimeout);
    }

    // --- Audit logger tests ---

    #[Test]
    public function executeCreatesAuditLoggerFromSessionDir(): void
    {
        $sessionDir = '/tmp/test-session';
        $auditLogger = $this->createMock(DynamicLoopAuditLoggerInterface::class);

        $chain = $this->createDynamicChain(
            name: 'audit-dynamic',
            facilitator: 'facilitator',
            participants: ['participant'],
        );

        $this->auditLoggerFactory->method('create')
            ->with($sessionDir . '/audit.jsonl')
            ->willReturn($auditLogger);

        $capturedLogger = null;
        $this->dynamicLoopRunner->method('execute')
            ->willReturnCallback(function (
                DynamicLoopConfigVo $c,
                DynamicLoopContextVo $ctx,
                int $startRound = 0,
                string $history = '',
                string $journal = '',
                ?DynamicLoopAuditLoggerInterface $logger = null,
            ) use (&$capturedLogger): DynamicLoopResultVo {
                $capturedLogger = $logger;

                return new DynamicLoopResultVo(
                    roundResults: [],
                    totalTime: 0.0,
                    totalInputTokens: 0,
                    totalOutputTokens: 0,
                    totalCost: 0.0,
                    synthesis: 'Done',
                    maxRoundsReached: false,
                );
            });

        $this->strategy->execute($chain, new OrchestrateChainCommand(
            chainName: 'audit-dynamic',
            task: 'Test',
        ));

        self::assertSame($auditLogger, $capturedLogger);
    }

    #[Test]
    public function executeWithNoAuditLogPassesNull(): void
    {
        $chain = $this->createDynamicChain(
            name: 'audit-disabled',
            facilitator: 'facilitator',
            participants: ['participant'],
        );

        $capturedLogger = 'not-null';
        $this->dynamicLoopRunner->method('execute')
            ->willReturnCallback(function (
                DynamicLoopConfigVo $c,
                DynamicLoopContextVo $ctx,
                int $startRound = 0,
                string $history = '',
                string $journal = '',
                ?DynamicLoopAuditLoggerInterface $logger = null,
            ) use (&$capturedLogger): DynamicLoopResultVo {
                $capturedLogger = $logger;

                return new DynamicLoopResultVo(
                    roundResults: [],
                    totalTime: 0.0,
                    totalInputTokens: 0,
                    totalOutputTokens: 0,
                    totalCost: 0.0,
                    synthesis: 'Done',
                    maxRoundsReached: false,
                );
            });

        $this->strategy->execute($chain, new OrchestrateChainCommand(
            chainName: 'audit-disabled',
            task: 'Test',
            noAuditLog: true,
        ));

        self::assertNull($capturedLogger);
    }

    // --- resume() ---

    #[Test]
    public function resumeThrowsWhenStateIsNull(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Failed to resume session');

        $chain = $this->createDynamicChain('test', 'facilitator', ['participant']);

        $this->sessionLogger->method('resumeSession');
        $this->sessionLogger->method('getResumedState')->willReturn(null);

        $this->strategy->resume($chain, new OrchestrateChainCommand(
            chainName: 'test',
            task: 'Test',
            resumeDir: '/tmp/resume-dir',
        ));
    }

    #[Test]
    public function resumeResumesWithState(): void
    {
        $chain = $this->createDynamicChain('brainstorm', 'facilitator', ['participant'], 5);

        $state = new DynamicLoopSessionStateVo(
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
                DynamicLoopConfigVo $c,
                DynamicLoopContextVo $ctx,
                int $startRound = 0,
                string $history = '',
                string $journal = '',
                ?DynamicLoopAuditLoggerInterface $auditLogger = null,
            ) use (
                &$capturedStartRound,
                &$capturedHistory,
                &$capturedJournal,
                $loopResult,
            ): DynamicLoopResultVo {
                $capturedStartRound = $startRound;
                $capturedHistory = $history;
                $capturedJournal = $journal;

                return $loopResult;
            });

        $result = $this->strategy->resume($chain, new OrchestrateChainCommand(
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

    #[Test]
    public function resumeUsesChainTimeoutWhenNoCliOverride(): void
    {
        $chain = $this->createDynamicChainWithTimeout(
            name: 'timed_chain',
            facilitator: 'facilitator',
            participants: ['participant'],
            timeout: 600,
        );

        $state = new DynamicLoopSessionStateVo(
            topic: 'Resumed topic',
            facilitator: 'facilitator',
            participants: ['participant'],
            maxRounds: 5,
            completedRounds: 2,
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
            synthesis: 'Resumed with chain timeout',
            maxRoundsReached: false,
        );

        $capturedTimeout = null;
        $this->dynamicLoopRunner->method('execute')
            ->willReturnCallback(function (
                DynamicLoopConfigVo $c,
                DynamicLoopContextVo $ctx,
                int $startRound = 0,
                string $history = '',
                string $journal = '',
                ?DynamicLoopAuditLoggerInterface $auditLogger = null,
            ) use (
                &$capturedTimeout,
                $loopResult,
            ): DynamicLoopResultVo {
                $capturedTimeout = $ctx->timeout;

                return $loopResult;
            });

        $this->strategy->resume($chain, new OrchestrateChainCommand(
            chainName: 'timed_chain',
            task: 'Test',
            resumeDir: '/tmp/resume-dir',
        ));

        self::assertSame(600, $capturedTimeout);
    }

    #[Test]
    public function resumeCliTimeoutOverridesChainTimeout(): void
    {
        $chain = $this->createDynamicChainWithTimeout(
            name: 'timed_chain',
            facilitator: 'facilitator',
            participants: ['participant'],
            timeout: 600,
        );

        $state = new DynamicLoopSessionStateVo(
            topic: 'Resumed topic',
            facilitator: 'facilitator',
            participants: ['participant'],
            maxRounds: 5,
            completedRounds: 2,
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
            synthesis: 'Resumed with CLI timeout',
            maxRoundsReached: false,
        );

        $capturedTimeout = null;
        $this->dynamicLoopRunner->method('execute')
            ->willReturnCallback(function (
                DynamicLoopConfigVo $c,
                DynamicLoopContextVo $ctx,
                int $startRound = 0,
                string $history = '',
                string $journal = '',
                ?DynamicLoopAuditLoggerInterface $auditLogger = null,
            ) use (
                &$capturedTimeout,
                $loopResult,
            ): DynamicLoopResultVo {
                $capturedTimeout = $ctx->timeout;

                return $loopResult;
            });

        $this->strategy->resume($chain, new OrchestrateChainCommand(
            chainName: 'timed_chain',
            task: 'Test',
            timeout: 300,
            resumeDir: '/tmp/resume-dir',
        ));

        self::assertSame(300, $capturedTimeout);
    }

    #[Test]
    public function resumeFallsBackTo600WhenNoTimeoutAnywhere(): void
    {
        $chain = $this->createDynamicChain(
            name: 'no_timeout',
            facilitator: 'facilitator',
            participants: ['participant'],
        );

        $state = new DynamicLoopSessionStateVo(
            topic: 'Resumed topic',
            facilitator: 'facilitator',
            participants: ['participant'],
            maxRounds: 5,
            completedRounds: 2,
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
            synthesis: 'Resumed with default timeout',
            maxRoundsReached: false,
        );

        $capturedTimeout = null;
        $this->dynamicLoopRunner->method('execute')
            ->willReturnCallback(function (
                DynamicLoopConfigVo $c,
                DynamicLoopContextVo $ctx,
                int $startRound = 0,
                string $history = '',
                string $journal = '',
                ?DynamicLoopAuditLoggerInterface $auditLogger = null,
            ) use (
                &$capturedTimeout,
                $loopResult,
            ): DynamicLoopResultVo {
                $capturedTimeout = $ctx->timeout;

                return $loopResult;
            });

        $this->strategy->resume($chain, new OrchestrateChainCommand(
            chainName: 'no_timeout',
            task: 'Test',
            resumeDir: '/tmp/resume-dir',
        ));

        self::assertSame(600, $capturedTimeout);
    }

    #[Test]
    public function resumeCreatesAuditLoggerFromResumeDir(): void
    {
        $chain = $this->createDynamicChain('brainstorm', 'facilitator', ['participant'], 5);

        $state = new DynamicLoopSessionStateVo(
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

        $auditLogger = $this->createMock(DynamicLoopAuditLoggerInterface::class);
        $this->auditLoggerFactory->method('create')
            ->with('/tmp/resume-dir/audit.jsonl')
            ->willReturn($auditLogger);

        $capturedLogger = null;
        $loopResult = new DynamicLoopResultVo(
            roundResults: [],
            totalTime: 1.0,
            totalInputTokens: 100,
            totalOutputTokens: 50,
            totalCost: 0.01,
            synthesis: 'Resumed result',
            maxRoundsReached: false,
        );
        $this->dynamicLoopRunner->method('execute')
            ->willReturnCallback(function (
                DynamicLoopConfigVo $c,
                DynamicLoopContextVo $ctx,
                int $startRound = 0,
                string $history = '',
                string $journal = '',
                ?DynamicLoopAuditLoggerInterface $logger = null,
            ) use (
                &$capturedLogger,
                $loopResult,
            ): DynamicLoopResultVo {
                $capturedLogger = $logger;

                return $loopResult;
            });

        $this->strategy->resume($chain, new OrchestrateChainCommand(
            chainName: 'brainstorm',
            task: 'Test',
            resumeDir: '/tmp/resume-dir',
        ));

        self::assertSame($auditLogger, $capturedLogger);
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
    ): DynamicChainDefinitionVo {
        return DynamicChainDefinitionVo::create(
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

    /**
     * @param list<string> $participants
     */
    private function createDynamicChainWithTimeout(
        string $name,
        string $facilitator,
        array $participants,
        int $timeout,
        int $maxRounds = 10,
    ): DynamicChainDefinitionVo {
        return DynamicChainDefinitionVo::create(
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
            timeout: $timeout,
        );
    }
}
