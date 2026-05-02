<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Service\Chain\Dynamic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Dto\ChainResultAuditDto;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\ExecuteDynamicTurnServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\FinalizeDynamicLoopService;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\FormatDynamicJournalServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Session\ChainSessionLoggerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\FacilitatorResponseParserInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainTurnResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainContextVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FacilitatorResponseVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\PromptConfigurationVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\RoleConfigVo;

#[CoversClass(FinalizeDynamicLoopService::class)]
final class FinalizeDynamicLoopServiceTest extends TestCase
{
    private ExecuteDynamicTurnServiceInterface $turnExecutor;
    private FormatDynamicJournalServiceInterface $journal;
    private ChainSessionLoggerInterface $sessionLogger;
    private FacilitatorResponseParserInterface $facParser;
    private FinalizeDynamicLoopService $service;

    protected function setUp(): void
    {
        $this->turnExecutor = $this->createMock(ExecuteDynamicTurnServiceInterface::class);
        $this->journal = $this->createMock(FormatDynamicJournalServiceInterface::class);
        $this->sessionLogger = $this->createMock(ChainSessionLoggerInterface::class);
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
        $chain = $this->createChain();
        $context = $this->createContext();
        $execution = new DynamicLoopExecution();

        $turnResult = $this->createSuccessTurnResult('Final synthesis');
        $facResponse = FacilitatorResponseVo::createFromDone('Final synthesis');

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
        $chain = $this->createChain();
        $context = $this->createContext();
        $execution = new DynamicLoopExecution();

        $turnResult = $this->createSuccessTurnResult('{"synthesis":"Parsed result"}');
        $facResponse = FacilitatorResponseVo::createFromDone('Parsed result');

        $this->turnExecutor->method('runFinalizeStep')->willReturn($turnResult);
        $this->sessionLogger->method('writeContextFile');
        $this->facParser->method('parse')->willReturn($facResponse);

        $this->service->executeFinalizeTurn($chain, $context, $execution, null);

        self::assertSame('Parsed result', $execution->getSynthesis());
    }

    #[Test]
    public function executeFinalizeTurnFallsBackToRawTextWhenNoSynthesis(): void
    {
        $chain = $this->createChain();
        $context = $this->createContext();
        $execution = new DynamicLoopExecution();

        $turnResult = $this->createSuccessTurnResult('Raw synthesis text');
        // createFromNextRole returns FacilitatorResponseVo with getSynthesis() = null
        $facResponse = FacilitatorResponseVo::createFromNextRole('architect');

        $this->turnExecutor->method('runFinalizeStep')->willReturn($turnResult);
        $this->sessionLogger->method('writeContextFile');
        $this->facParser->method('parse')->willReturn($facResponse);

        $this->service->executeFinalizeTurn($chain, $context, $execution, null);

        self::assertSame('Raw synthesis text', $execution->getSynthesis());
    }

    #[Test]
    public function executeFinalizeTurnWritesJournalEntry(): void
    {
        $chain = $this->createChain();
        $context = $this->createContext();
        $execution = new DynamicLoopExecution();

        $turnResult = $this->createSuccessTurnResult('Result');
        $facResponse = FacilitatorResponseVo::createFromDone('Result');

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

        self::assertInstanceOf(ChainResultAuditDto::class, $dto);
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

    private function createSuccessTurnResult(string $output): ChainTurnResultVo
    {
        return new ChainTurnResultVo(
            agentResult: ChainRunResultVo::createFromSuccess(
                outputText: $output,
                inputTokens: 200,
                outputTokens: 100,
                cost: 0.05,
            ),
            duration: 3.0,
        );
    }

    private function createChain(): DynamicChainDefinitionVo
    {
        return DynamicChainDefinitionVo::create(
            name: 'test',
            description: '',
            facilitator: 'facilitator',
            participants: ['architect'],
            maxRounds: 10,
            brainstormSystemPrompt: 'sys',
            facilitatorAppendPrompt: 'fac_append %s',
            facilitatorStartPrompt: 'start %s',
            facilitatorContinuePrompt: 'cont %s %s %s',
            facilitatorFinalizePrompt: 'final %s %s',
            participantAppendPrompt: 'part_append %s',
            participantUserPrompt: 'ctx %s %s',
            roles: [
                'facilitator' => new RoleConfigVo(command: ['pi', '--model', 'gpt-4'], timeout: 60),
            ],
        );
    }

    private function createContext(): DynamicChainContextVo
    {
        return new DynamicChainContextVo(
            topic: 'Test topic',
            facilitatorRole: 'facilitator',
            participants: ['architect'],
            maxRounds: 10,
            maxTime: null,
            timeout: 600,
            workingDir: '/tmp/test',
            promptConfiguration: new PromptConfigurationVo(
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
