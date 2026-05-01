<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Application\UseCase\Command\OrchestrateChain;

use LogicException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain\DynamicExecutionStrategy;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommandHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerFactoryInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Audit\AuditLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\BuildDynamicContextService;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\RunDynamicLoopServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Session\ChainSessionLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\SessionCompletedNotifierInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainContextVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicLoopResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicRoundResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain\YamlChainLoader;

/**
 * Integration-тест: dynamic chain end-to-end.
 *
 * Проверяет полный цикл: YAML-конфигурация → YamlChainLoader → OrchestrateChainCommandHandler
 * → DynamicExecutionStrategy → контекст build → loop run → session finalize → DTO mapping.
 *
 * RunDynamicLoopService подменяется стабом — проверяем корректность сборки и маршрутизации.
 */
#[Group('integration')]
#[CoversClass(OrchestrateChainCommandHandler::class)]
#[CoversClass(DynamicExecutionStrategy::class)]
#[CoversClass(BuildDynamicContextService::class)]
#[CoversClass(YamlChainLoader::class)]
final class DynamicChainIntegrationTest extends TestCase
{
    private const string FIXTURES_DIR = __DIR__ . '/../../../../_fixtures';

    private OrchestrateChainCommandHandler $handler;

    private StubDynamicLoopService $stubLoopRunner;

    private StubSessionLogger $stubSessionLogger;

    protected function setUp(): void
    {
        $chainLoader = new YamlChainLoader(self::FIXTURES_DIR . '/test_chains.yaml');
        $this->stubLoopRunner = new StubDynamicLoopService();
        $this->stubSessionLogger = new StubSessionLogger();

        $contextBuilder = new BuildDynamicContextService();

        $auditFactory = $this->createMock(AuditLoggerFactoryInterface::class);
        $sessionNotifier = $this->createMock(SessionCompletedNotifierInterface::class);
        $sessionNotifier->method('notifySessionCompleted');

        $dynamicStrategy = new DynamicExecutionStrategy(
            contextBuilder: $contextBuilder,
            dynamicLoopRunner: $this->stubLoopRunner,
            sessionLogger: $this->stubSessionLogger,
            auditLoggerFactory: $auditFactory,
            sessionNotifier: $sessionNotifier,
        );

        $this->handler = new OrchestrateChainCommandHandler(
            $chainLoader,
            new \ArrayIterator([$dynamicStrategy]),
        );
    }

    // --- Dynamic chain: single participant, synthesis produced ---

    #[Test]
    public function dynamicChainExecutesLoopAndReturnsSynthesis(): void
    {
        // Arrange
        $this->stubLoopRunner->setResult(new DynamicLoopResultVo(
            roundResults: [
                new DynamicRoundResultVo(
                    round: 1,
                    role: 'facilitator',
                    isFacilitator: true,
                    outputText: 'Start discussion about feature X',
                    inputTokens: 200,
                    outputTokens: 100,
                    cost: 0.01,
                    duration: 2.5,
                ),
                new DynamicRoundResultVo(
                    round: 2,
                    role: 'participant',
                    isFacilitator: false,
                    outputText: 'Participant suggests approach A',
                    inputTokens: 300,
                    outputTokens: 150,
                    cost: 0.02,
                    duration: 3.0,
                ),
            ],
            totalTime: 5.5,
            totalInputTokens: 500,
            totalOutputTokens: 250,
            totalCost: 0.03,
            synthesis: 'Synthesis: combine approach A with B',
            maxRoundsReached: false,
        ));

        // Act
        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'dynamic_simple',
            task: 'Discuss feature X',
            topic: 'Feature X architecture',
        ));

        // Assert
        self::assertInstanceOf(OrchestrateChainResultDto::class, $result);
        self::assertSame('Synthesis: combine approach A with B', $result->synthesis);
        self::assertFalse($result->maxRoundsReached);
        self::assertCount(2, $result->roundResults);

        // Round 1: facilitator
        self::assertSame(1, $result->roundResults[0]->round);
        self::assertSame('facilitator', $result->roundResults[0]->role);
        self::assertTrue($result->roundResults[0]->isFacilitator);
        self::assertSame('Start discussion about feature X', $result->roundResults[0]->outputText);

        // Round 2: participant
        self::assertSame(2, $result->roundResults[1]->round);
        self::assertSame('participant', $result->roundResults[1]->role);
        self::assertFalse($result->roundResults[1]->isFacilitator);
        self::assertSame('Participant suggests approach A', $result->roundResults[1]->outputText);

        // Aggregated metrics
        self::assertSame(5.5, $result->totalTime);
        self::assertSame(500, $result->totalInputTokens);
        self::assertSame(250, $result->totalOutputTokens);
        self::assertSame(0.03, $result->totalCost);
        self::assertFalse($result->budgetExceeded);
    }

    // --- Dynamic chain: multiple participants ---

    #[Test]
    public function dynamicChainWithMultipleParticipantsBuildsCorrectContext(): void
    {
        // Arrange
        $capturedContext = null;
        $this->stubLoopRunner->setResult(new DynamicLoopResultVo(
            roundResults: [],
            totalTime: 1.0,
            totalInputTokens: 0,
            totalOutputTokens: 0,
            totalCost: 0.0,
            synthesis: 'Done',
            maxRoundsReached: false,
        ));
        $this->stubLoopRunner->onExecute(static function (ChainDefinitionVo $chain, DynamicChainContextVo $context) use (&$capturedContext): void {
            $capturedContext = $context;
        });

        // Act
        ($this->handler)(new OrchestrateChainCommand(
            chainName: 'dynamic_multi_participant',
            task: 'Multi-participant brainstorm',
        ));

        // Assert
        self::assertNotNull($capturedContext);
        self::assertSame('facilitator', $capturedContext->facilitatorRole);
        self::assertSame(['participant', 'analyst'], $capturedContext->participants);
        self::assertSame(10, $capturedContext->maxRounds);
        self::assertNotNull($capturedContext->promptConfiguration);
        self::assertSame('Multi-participant brainstorm system.', $capturedContext->promptConfiguration->getBrainstormSystemPrompt());
    }

    // --- Dynamic chain: max rounds reached ---

    #[Test]
    public function dynamicChainReportsMaxRoundsReached(): void
    {
        // Arrange
        $this->stubLoopRunner->setResult(new DynamicLoopResultVo(
            roundResults: [
                new DynamicRoundResultVo(1, 'facilitator', true, 'Round 1', 100, 50, 0.01, 1.0),
                new DynamicRoundResultVo(2, 'participant', false, 'Round 2', 100, 50, 0.01, 1.0),
                new DynamicRoundResultVo(3, 'facilitator', true, 'Round 3', 100, 50, 0.01, 1.0),
                new DynamicRoundResultVo(4, 'participant', false, 'Round 4', 100, 50, 0.01, 1.0),
                new DynamicRoundResultVo(5, 'facilitator', true, 'Round 5', 100, 50, 0.01, 1.0),
            ],
            totalTime: 5.0,
            totalInputTokens: 500,
            totalOutputTokens: 250,
            totalCost: 0.05,
            synthesis: 'Final synthesis',
            maxRoundsReached: true,
        ));

        // Act
        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'dynamic_simple',
            task: 'Hit max rounds',
        ));

        // Assert
        self::assertTrue($result->maxRoundsReached);
        self::assertSame('Final synthesis', $result->synthesis);
        self::assertCount(5, $result->roundResults);
    }

    // --- Dynamic chain: without synthesis (interrupted) ---

    #[Test]
    public function dynamicChainWithoutSynthesisReturnsEmptySynthesis(): void
    {
        // Arrange
        $this->stubLoopRunner->setResult(new DynamicLoopResultVo(
            roundResults: [
                new DynamicRoundResultVo(1, 'facilitator', true, 'Started', 100, 50, 0.01, 1.0),
            ],
            totalTime: 1.0,
            totalInputTokens: 100,
            totalOutputTokens: 50,
            totalCost: 0.01,
            synthesis: null,
            maxRoundsReached: false,
            interruptionReason: 'agent_error',
        ));

        // Act
        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'dynamic_simple',
            task: 'Will be interrupted',
        ));

        // Assert
        self::assertNull($result->synthesis);
        self::assertCount(1, $result->roundResults);
    }
}

/**
 * Стаб RunDynamicLoopServiceInterface.
 */
final class StubDynamicLoopService implements RunDynamicLoopServiceInterface
{
    private ?DynamicLoopResultVo $result = null;

    /** @var \Closure|null */
    private $onExecuteCallback = null;

    #[Override]
    public function execute(
        ChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        int $startRound = 0,
        string $initialDiscussionHistory = '',
        string $initialFacilitatorJournal = '',
        ?AuditLoggerInterface $auditLogger = null,
    ): DynamicLoopResultVo {
        if ($this->onExecuteCallback !== null) {
            ($this->onExecuteCallback)($chain, $context, $startRound, $initialDiscussionHistory, $initialFacilitatorJournal);
        }

        if ($this->result === null) {
            throw new LogicException('StubDynamicLoopService: no result set. Call setResult() first.');
        }

        return $this->result;
    }

    public function setResult(DynamicLoopResultVo $result): self
    {
        $this->result = $result;

        return $this;
    }

    /**
     * @param \Closure(ChainDefinitionVo, DynamicChainContextVo, int, string, string): void $callback
     */
    public function onExecute(\Closure $callback): self
    {
        $this->onExecuteCallback = $callback;

        return $this;
    }
}

/**
 * Стаб ChainSessionLoggerInterface.
 */
final class StubSessionLogger implements ChainSessionLoggerInterface
{
    private ?string $sessionDir = null;

    #[Override]
    public function startSession(string $chainName, string $topic, string $facilitator, array $participants, int $maxRounds): string
    {
        $this->sessionDir = sys_get_temp_dir() . '/test_session_' . uniqid();
        mkdir($this->sessionDir, 0777, true);

        return $this->sessionDir;
    }

    #[Override]
    public function logRound(int $step, int $round, string $role, bool $isFacilitator, string $systemPrompt, string $userPrompt, string $response, float $duration, int $inputTokens, int $outputTokens, float $cost, ?string $invocation = null): void
    {
        // no-op
    }

    #[Override]
    public function completeSession(?string $synthesis, float $totalTime, int $totalInputTokens, int $totalOutputTokens, float $totalCost, int $totalSteps, string $reason = 'facilitator_done'): void
    {
        // no-op
    }

    #[Override]
    public function setBudget(?BudgetVo $budget): void
    {
        // no-op
    }

    #[Override]
    public function interruptSession(string $reason = ''): void
    {
        // no-op
    }

    #[Override]
    public function updateSessionState(int $completedRounds): void
    {
        // no-op
    }

    #[Override]
    public function logInvocation(array $invocation): void
    {
        // no-op
    }

    #[Override]
    public function writeContextFile(string $name, string $content): string
    {
        $path = ($this->sessionDir ?? sys_get_temp_dir()) . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }

    #[Override]
    public function writePromptFile(int $step, int $round, string $role, string $content, string $suffix): string
    {
        $filename = sprintf('step_%03d_round_%03d_%s%s', $step, $round, $role, $suffix);
        $path = ($this->sessionDir ?? sys_get_temp_dir()) . '/' . $filename;
        file_put_contents($path, $content);

        return $path;
    }

    #[Override]
    public function resumeSession(string $sessionDir): void
    {
        $this->sessionDir = $sessionDir;
    }

    #[Override]
    public function getResumedState(): ?\TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainSessionStateVo
    {
        return null;
    }

    #[Override]
    public function getResponseFilePaths(int $upToStep): array
    {
        return [];
    }

    #[Override]
    public function getRoundFiles(): array
    {
        return [];
    }
}
