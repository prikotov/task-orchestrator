<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Application\UseCase\Command\OrchestrateChain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain\YamlChainLoader;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommandHandler;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Integration\Service\ChainDefinition\ChainExecutionDefinitionMapper;
use TaskOrchestrator\Common\Module\DynamicLoop\Application\Service\DynamicExecutionStrategy;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerFactoryInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\BuildDynamicContextService;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\SessionCompletedNotifierInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopSessionStateVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicRoundResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Integration\Service\ChainDefinition\DynamicLoopDefinitionMapper;

/**
 * Integration-тест: resume dynamic chain end-to-end.
 *
 * Проверяет полный цикл возобновления: YAML → YamlChainLoader → OrchestrateChainCommandHandler
 * → DynamicExecutionStrategy::resume() → session restore → context build → loop run → finalize.
 *
 * Session logger и dynamic loop runner подменяются стабами.
 */
#[Group('integration')]
#[CoversClass(OrchestrateChainCommandHandler::class)]
#[CoversClass(DynamicExecutionStrategy::class)]
#[CoversClass(BuildDynamicContextService::class)]
#[CoversClass(YamlChainLoader::class)]
final class ResumeDynamicChainIntegrationTest extends TestCase
{
    private const string FIXTURES_DIR = __DIR__ . '/../../../../_fixtures';

    private OrchestrateChainCommandHandler $handler;

    private ResumeStubDynamicLoopService $stubLoopRunner;

    private ResumeStubSessionLogger $stubSessionLogger;

    protected function setUp(): void
    {
        $chainLoader = new YamlChainLoader(self::FIXTURES_DIR . '/test_chains.yaml');
        $this->stubLoopRunner = new ResumeStubDynamicLoopService();
        $this->stubSessionLogger = new ResumeStubSessionLogger();

        $contextBuilder = new BuildDynamicContextService();
        $configMapper = new DynamicLoopDefinitionMapper($chainLoader);
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

        $chainDefinitionProvider = new ChainExecutionDefinitionMapper($chainLoader);
        $this->handler = new OrchestrateChainCommandHandler(
            $chainDefinitionProvider,
            new \ArrayIterator([$dynamicStrategy]),
        );
    }

    protected function tearDown(): void
    {
        $this->stubSessionLogger->cleanup();
    }

    // --- Resume: restores state and continues from last round ---

    #[Test]
    public function resumeDynamicChainContinuesFromLastRound(): void
    {
        // Arrange: resumed state has 3 completed rounds
        $this->stubSessionLogger->setResumedState(new DynamicLoopSessionStateVo(
            topic: 'Feature Y design',
            facilitator: 'facilitator',
            participants: ['participant'],
            maxRounds: 10,
            completedRounds: 3,
            discussionHistory: 'History of rounds 1-3...',
            facilitatorJournal: 'Journal of rounds 1-3...',
        ));

        // Stub loop runner returns results from round 4 onward
        $this->stubLoopRunner->setResult(new DynamicLoopResultVo(
            roundResults: [
                new DynamicRoundResultVo(
                    round: 4,
                    role: 'facilitator',
                    isFacilitator: true,
                    outputText: 'Resumed: facilitating round 4',
                    inputTokens: 200,
                    outputTokens: 100,
                    cost: 0.01,
                    duration: 2.0,
                ),
                new DynamicRoundResultVo(
                    round: 5,
                    role: 'participant',
                    isFacilitator: false,
                    outputText: 'Resumed: participant response',
                    inputTokens: 150,
                    outputTokens: 80,
                    cost: 0.008,
                    duration: 1.5,
                ),
            ],
            totalTime: 3.5,
            totalInputTokens: 350,
            totalOutputTokens: 180,
            totalCost: 0.018,
            synthesis: 'Resume synthesis: continue with approach Z',
            maxRoundsReached: false,
        ));

        $resumeDir = sys_get_temp_dir() . '/test_resume_' . uniqid();
        mkdir($resumeDir, 0777, true);

        try {
            // Act
            $result = ($this->handler)(new OrchestrateChainCommand(
                chainName: 'dynamic_simple',
                task: 'Continue discussion',
                resumeDir: $resumeDir,
            ));

            // Assert
            self::assertInstanceOf(OrchestrateChainResultDto::class, $result);
            self::assertSame('Resume synthesis: continue with approach Z', $result->synthesis);
            self::assertCount(2, $result->roundResults);

            // Round 4 (resumed)
            self::assertSame(4, $result->roundResults[0]->round);
            self::assertSame('facilitator', $result->roundResults[0]->role);
            self::assertTrue($result->roundResults[0]->isFacilitator);
            self::assertSame('Resumed: facilitating round 4', $result->roundResults[0]->outputText);

            // Round 5 (resumed)
            self::assertSame(5, $result->roundResults[1]->round);
            self::assertSame('participant', $result->roundResults[1]->role);

            // Verify startRound=3 was passed to loop runner
            self::assertSame(3, $this->stubLoopRunner->getCapturedStartRound());

            // Verify history was passed
            self::assertSame('History of rounds 1-3...', $this->stubLoopRunner->getCapturedHistory());
            self::assertSame('Journal of rounds 1-3...', $this->stubLoopRunner->getCapturedJournal());
        } finally {
            // tearDown() via cleanup() handles recursive deletion
        }
    }

    // --- Resume: context built from resumed state parameters ---

    #[Test]
    public function resumeDynamicChainUsesResumedStateParameters(): void
    {
        // Arrange
        $this->stubSessionLogger->setResumedState(new DynamicLoopSessionStateVo(
            topic: 'Original topic',
            facilitator: 'facilitator',
            participants: ['participant', 'analyst'],
            maxRounds: 15,
            completedRounds: 5,
            discussionHistory: 'Discussion so far...',
            facilitatorJournal: 'Journal so far...',
        ));

        $this->stubLoopRunner->setResult(new DynamicLoopResultVo(
            roundResults: [],
            totalTime: 0.0,
            totalInputTokens: 0,
            totalOutputTokens: 0,
            totalCost: 0.0,
            synthesis: 'Done',
            maxRoundsReached: false,
        ));

        $resumeDir = sys_get_temp_dir() . '/test_resume_params_' . uniqid();
        mkdir($resumeDir, 0777, true);

        // Act
        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'dynamic_simple',
            task: 'Continue with state params',
            resumeDir: $resumeDir,
        ));

        // Assert: context built from resumed state
        $capturedContext = $this->stubLoopRunner->getCapturedContext();
        self::assertNotNull($capturedContext);
        self::assertSame('facilitator', $capturedContext->facilitatorRole);
        self::assertSame(['participant', 'analyst'], $capturedContext->participants);
        self::assertSame(15, $capturedContext->maxRounds);
        self::assertSame('Original topic', $capturedContext->topic);
    }
}
