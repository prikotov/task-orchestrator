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
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainSessionStateVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainContextVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicLoopResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicRoundResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain\YamlChainLoader;

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

    // --- Resume: restores state and continues from last round ---

    #[Test]
    public function resumeDynamicChainContinuesFromLastRound(): void
    {
        // Arrange: resumed state has 3 completed rounds
        $this->stubSessionLogger->setResumedState(new ChainSessionStateVo(
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
            @rmdir($resumeDir);
        }
    }

    // --- Resume: context built from resumed state parameters ---

    #[Test]
    public function resumeDynamicChainUsesResumedStateParameters(): void
    {
        // Arrange
        $this->stubSessionLogger->setResumedState(new ChainSessionStateVo(
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

        try {
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
        } finally {
            @rmdir($resumeDir);
        }
    }
}

/**
 * Стаб RunDynamicLoopServiceInterface для resume-тестов.
 * Захватывает параметры вызова для проверок.
 */
final class ResumeStubDynamicLoopService implements RunDynamicLoopServiceInterface
{
    private ?DynamicLoopResultVo $result = null;

    private ?int $capturedStartRound = null;

    private ?string $capturedHistory = null;

    private ?string $capturedJournal = null;

    private ?DynamicChainContextVo $capturedContext = null;

    #[Override]
    public function execute(
        ChainDefinitionVo $chain,
        DynamicChainContextVo $context,
        int $startRound = 0,
        string $initialDiscussionHistory = '',
        string $initialFacilitatorJournal = '',
        ?AuditLoggerInterface $auditLogger = null,
    ): DynamicLoopResultVo {
        $this->capturedContext = $context;
        $this->capturedStartRound = $startRound;
        $this->capturedHistory = $initialDiscussionHistory;
        $this->capturedJournal = $initialFacilitatorJournal;

        if ($this->result === null) {
            throw new LogicException('ResumeStubDynamicLoopService: no result set.');
        }

        return $this->result;
    }

    public function setResult(DynamicLoopResultVo $result): self
    {
        $this->result = $result;

        return $this;
    }

    public function getCapturedStartRound(): ?int
    {
        return $this->capturedStartRound;
    }

    public function getCapturedHistory(): ?string
    {
        return $this->capturedHistory;
    }

    public function getCapturedJournal(): ?string
    {
        return $this->capturedJournal;
    }

    public function getCapturedContext(): ?DynamicChainContextVo
    {
        return $this->capturedContext;
    }
}

/**
 * Стаб ChainSessionLoggerInterface для resume-тестов.
 * Поддерживает задание resumedState.
 */
final class ResumeStubSessionLogger implements ChainSessionLoggerInterface
{
    private ?ChainSessionStateVo $resumedState = null;

    private ?string $sessionDir = null;

    public function setResumedState(ChainSessionStateVo $state): self
    {
        $this->resumedState = $state;

        return $this;
    }

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
    public function getResumedState(): ?ChainSessionStateVo
    {
        return $this->resumedState;
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
