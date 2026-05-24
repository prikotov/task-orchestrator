<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Service\Chain\Dynamic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Audit\DynamicLoopAuditDto;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\ExecuteDynamicTurnServiceInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\FinalizeDynamicLoopService;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\FormatDynamicJournalServiceInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session\DynamicLoopSessionLoggerInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic\FacilitatorResponseParserInterface;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRunResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopTurnResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRoleConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopPromptConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\FacilitatorResponseVo;

#[CoversClass(FinalizeDynamicLoopService::class)]
final class FinalizeDynamicLoopServiceTest extends TestCase
{
    private ExecuteDynamicTurnServiceInterface $turnExecutor;
    private FormatDynamicJournalServiceInterface $journal;
    private DynamicLoopSessionLoggerInterface $sessionLogger;
    private FacilitatorResponseParserInterface $facParser;
    private FinalizeDynamicLoopService $service;

    protected function setUp(): void
    {
        $this->turnExecutor = $this->createMock(ExecuteDynamicTurnServiceInterface::class);
        $this->journal = $this->createMock(FormatDynamicJournalServiceInterface::class);
        $this->sessionLogger = $this->createMock(DynamicLoopSessionLoggerInterface::class);
        $this->facParser = $this->createMock(FacilitatorResponseParserInterface::class);
        $this->service = new FinalizeDynamicLoopService(
            $this->turnExecutor,
            $this->journal,
            $this->sessionLogger,
            $this->facParser,
        );
    }

    // ─── executeFinalizeTurn ──────────────────────────────────────────

    #[Test]
    public function executeFinalizeTurnAdvancesStepAndRound(): void
    {
        $chain = $this->createConfig();
        $context = $this->createContext();
        $execution = new DynamicLoopExecution();

        $turnResult = $this->createSuccessTurnResult('Final synthesis');
        $facResponse = FacilitatorResponseVo::createDone('Final synthesis');

        $this->turnExecutor->method('runFinalizeStep')->willReturn($turnResult);
        $this->sessionLogger->method('writeContextFile');
        $this->facParser->method('parse')->willReturn($facResponse);

        $initialStep = $execution->getStep();
        $initialRound = $execution->getRound();

        $this->service->executeFinalizeTurn($chain, $context, $execution, null);

        self::assertSame($initialStep + 1, $execution->getStep());
        self::assertSame($initialRound + 1, $execution->getRound());
    }

    #[Test]
    public function executeFinalizeTurnSetsSynthesisFromParsedResponse(): void
    {
        $chain = $this->createConfig();
        $context = $this->createContext();
        $execution = new DynamicLoopExecution();

        $turnResult = $this->createSuccessTurnResult('{"synthesis":"Parsed result"}');
        $facResponse = FacilitatorResponseVo::createDone('Parsed result');

        $this->turnExecutor->method('runFinalizeStep')->willReturn($turnResult);
        $this->sessionLogger->method('writeContextFile');
        $this->facParser->method('parse')->willReturn($facResponse);

        $this->service->executeFinalizeTurn($chain, $context, $execution, null);

        self::assertSame('Parsed result', $execution->getSynthesis());
    }

    #[Test]
    public function executeFinalizeTurnFallsBackToRawTextWhenNoSynthesis(): void
    {
        $chain = $this->createConfig();
        $context = $this->createContext();
        $execution = new DynamicLoopExecution();

        $turnResult = $this->createSuccessTurnResult('Raw synthesis text');
        // createNextRole returns FacilitatorResponseVo with getSynthesis() = null
        $facResponse = FacilitatorResponseVo::createNextRole('architect');

        $this->turnExecutor->method('runFinalizeStep')->willReturn($turnResult);
        $this->sessionLogger->method('writeContextFile');
        $this->facParser->method('parse')->willReturn($facResponse);

        $this->service->executeFinalizeTurn($chain, $context, $execution, null);

        self::assertSame('Raw synthesis text', $execution->getSynthesis());
    }

    #[Test]
    public function executeFinalizeTurnWritesJournalEntry(): void
    {
        $chain = $this->createConfig();
        $context = $this->createContext();
        $execution = new DynamicLoopExecution();

        $turnResult = $this->createSuccessTurnResult('Result');
        $facResponse = FacilitatorResponseVo::createDone('Result');

        $this->turnExecutor->method('runFinalizeStep')->willReturn($turnResult);
        $this->facParser->method('parse')->willReturn($facResponse);
        $this->sessionLogger->expects(self::once())
            ->method('writeContextFile')
            ->with('facilitator_journal.md', self::stringContains('synthesis'));

        $this->service->executeFinalizeTurn($chain, $context, $execution, null);
    }

    // ─── formatFinalJournal ───────────────────────────────────────────

    #[Test]
    public function formatFinalJournalSetsFormattedJournal(): void
    {
        $execution = new DynamicLoopExecution();

        $this->journal->method('formatFinalEntry')->willReturn('=== Final Journal ===');
        $this->sessionLogger->method('writeContextFile');

        $this->service->formatFinalJournal($execution);

        self::assertSame('=== Final Journal ===', $execution->getFacilitatorJournal());
    }

    #[Test]
    public function formatFinalJournalWritesContextFile(): void
    {
        $execution = new DynamicLoopExecution();

        $this->journal->method('formatFinalEntry')->willReturn('Journal');
        $this->sessionLogger->expects(self::once())
            ->method('writeContextFile')
            ->with('facilitator_journal.md', 'Journal');

        $this->service->formatFinalJournal($execution);
    }

    // ─── buildChainAuditDto ───────────────────────────────────────────

    #[Test]
    public function buildChainAuditDtoContainsChainMetrics(): void
    {
        $execution = new DynamicLoopExecution();

        $startTime = microtime(true) - 5.0;

        $dto = $this->service->buildChainAuditDto('test_chain', $startTime, $execution);

        self::assertInstanceOf(DynamicLoopAuditDto::class, $dto);
        self::assertSame('test_chain', $dto->chainName);
        self::assertGreaterThanOrEqual(5000.0, $dto->totalDurationMs);
        self::assertSame(0, $dto->stepsCount);
        self::assertFalse($dto->budgetExceeded);
    }

    #[Test]
    public function buildChainAuditDtoReflectsBudgetBreak(): void
    {
        $execution = new DynamicLoopExecution();
        $execution->markMaxRoundsReached(true);

        $startTime = microtime(true);

        $dto = $this->service->buildChainAuditDto('chain', $startTime, $execution);

        self::assertFalse($dto->budgetExceeded);
        self::assertSame(0, $dto->stepsCount);
    }

    // ─── Helpers ───────────────────────────────────────────────────────

    private function createSuccessTurnResult(string $output): DynamicLoopTurnResultVo
    {
        return new DynamicLoopTurnResultVo(
            agentResult: DynamicLoopRunResultVo::createSuccess(
                outputText: $output,
                inputTokens: 200,
                outputTokens: 100,
                cost: 0.05,
            ),
            duration: 3.0,
        );
    }

    private function createConfig(): DynamicLoopConfigVo
    {
        return DynamicLoopConfigVo::createFromDynamic(
            name: 'test',
            description: '',
            facilitator: 'facilitator',
            participants: ['architect'],
            maxRounds: 10,
            promptConfiguration: new DynamicLoopPromptConfigVo(
                brainstormSystemPrompt: 'sys',
                facilitatorAppendPrompt: 'fac_append %s',
                facilitatorStartPrompt: 'start %s',
                facilitatorContinuePrompt: 'cont %s %s %s',
                facilitatorFinalizePrompt: 'final %s %s',
                participantAppendPrompt: 'part_append %s',
                participantUserPrompt: 'ctx %s %s',
            ),
            roleConfigs: [
                'facilitator' => new DynamicLoopRoleConfigVo(command: ['pi', '--model', 'gpt-4'], timeout: 60),
            ],
        );
    }

    private function createContext(): DynamicLoopContextVo
    {
        return new DynamicLoopContextVo(
            facilitatorRole: 'facilitator',
            participants: ['architect'],
            maxRounds: 10,
            topic: 'Test topic',
            promptConfiguration: new DynamicLoopPromptConfigVo(
                brainstormSystemPrompt: 'sys',
                facilitatorAppendPrompt: 'fac_append %s',
                facilitatorStartPrompt: 'start %s',
                facilitatorContinuePrompt: 'cont %s %s %s',
                facilitatorFinalizePrompt: 'final %s %s',
                participantAppendPrompt: 'part_append %s',
                participantUserPrompt: 'ctx %s %s',
            ),
            workingDir: '/tmp/test',
            timeout: 600,
        );
    }
}
