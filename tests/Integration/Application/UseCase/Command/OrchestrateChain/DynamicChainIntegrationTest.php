<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Application\UseCase\Command\OrchestrateChain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Chain\YamlChainLoader;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\LoadRawChain\LoadRawChainQueryHandler;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommandHandler;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Integration\ChainDefinition\ChainExecutionDefinitionMapper;
use TaskOrchestrator\Common\Module\DynamicLoop\Integration\Service\ChainExecution\DynamicExecutionStrategy;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerFactoryInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\BuildDynamicContextService;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\SessionCompletedNotifierInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicRoundResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Integration\ChainDefinition\DynamicLoopDefinitionMapper;

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
        $configMapper = new DynamicLoopDefinitionMapper(new \TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\LoadRawChain\LoadRawChainQueryHandler($chainLoader));

        $auditFactory = $this->createMock(DynamicLoopAuditLoggerFactoryInterface::class);
        $sessionNotifier = $this->createMock(SessionCompletedNotifierInterface::class);
        $sessionNotifier->method('notifySessionCompleted');

        $dynamicStrategy = new DynamicExecutionStrategy(
            contextBuilder: $contextBuilder,
            dynamicLoopRunner: $this->stubLoopRunner,
            sessionLogger: $this->stubSessionLogger,
            chainProvider: $configMapper,
            auditLoggerFactory: $auditFactory,
            sessionNotifier: $sessionNotifier,
        );

        $chainDefinitionProvider = new ChainExecutionDefinitionMapper(new \TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\LoadRawChain\LoadRawChainQueryHandler($chainLoader));
        $this->handler = new OrchestrateChainCommandHandler(
            $chainDefinitionProvider,
            new \ArrayIterator([$dynamicStrategy]),
        );
    }

    protected function tearDown(): void
    {
        $this->stubSessionLogger->cleanup();
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
        $this->stubLoopRunner->onExecute(static function (DynamicLoopConfigVo $chain, DynamicLoopContextVo $context) use (&$capturedContext): void {
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
