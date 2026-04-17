<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\Mapper;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Mapper\ReportTextMapper;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\DynamicRoundResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\StepResultDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit-тесты ReportTextMapper.
 */
#[CoversClass(ReportTextMapper::class)]
final class ReportTextMapperTest extends TestCase
{
    private ReportTextMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ReportTextMapper();
    }

    #[Test]
    public function mapStaticChainReportContainsHeaderAndSummary(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [
                new StepResultDto(
                    role: 'analyst',
                    runner: 'pi',
                    outputText: 'Analysis done',
                    inputTokens: 3000,
                    outputTokens: 800,
                    cost: 0.04,
                    duration: 5.2,
                    isError: false,
                ),
                new StepResultDto(
                    role: 'developer',
                    runner: 'pi',
                    outputText: 'Code written',
                    inputTokens: 4500,
                    outputTokens: 4500,
                    cost: 0.18,
                    duration: 22.3,
                    isError: false,
                ),
            ],
            totalTime: 27.5,
            totalInputTokens: 7500,
            totalOutputTokens: 5300,
            totalCost: 0.22,
        );

        $report = $this->mapper->map($result, 'implement', 'Add GET /api/products');

        self::assertStringContainsString('Agent Chain Report: implement', $report);
        self::assertStringContainsString('Task: Add GET /api/products', $report);
        self::assertStringContainsString('27.5s', $report);
        self::assertStringContainsString('↑7.5k ↓5.3k', $report);
        self::assertStringContainsString('$0.2200', $report);
    }

    #[Test]
    public function mapStaticChainReportContainsStepBreakdown(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [
                new StepResultDto(
                    role: 'analyst',
                    runner: 'pi',
                    outputText: '',
                    inputTokens: 3000,
                    outputTokens: 800,
                    cost: 0.04,
                    duration: 5.0,
                    isError: false,
                ),
                new StepResultDto(
                    role: 'developer',
                    runner: 'pi',
                    outputText: '',
                    inputTokens: 4500,
                    outputTokens: 4500,
                    cost: 0.18,
                    duration: 22.0,
                    isError: false,
                ),
            ],
            totalTime: 27.0,
            totalInputTokens: 7500,
            totalOutputTokens: 5300,
            totalCost: 0.22,
        );

        $report = $this->mapper->map($result, 'implement', 'task');

        self::assertStringContainsString('[1/2] analyst @ pi', $report);
        self::assertStringContainsString('[2/2] developer @ pi', $report);
        self::assertStringContainsString('✓', $report);
    }

    #[Test]
    public function mapReportWithQualityGateStep(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [
                new StepResultDto(
                    role: 'quality_gate',
                    runner: '',
                    outputText: 'All tests passed',
                    inputTokens: 0,
                    outputTokens: 0,
                    cost: 0.0,
                    duration: 3.0,
                    isError: false,
                    passed: true,
                    exitCode: 0,
                    label: 'Unit Tests',
                ),
            ],
            totalTime: 3.0,
        );

        $report = $this->mapper->map($result, 'implement', 'task');

        self::assertStringContainsString('🔍 Unit Tests: ✓', $report);
    }

    #[Test]
    public function mapReportWithFailedStep(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [
                new StepResultDto(
                    role: 'developer',
                    runner: 'pi',
                    outputText: '',
                    inputTokens: 100,
                    outputTokens: 50,
                    cost: 0.01,
                    duration: 2.0,
                    isError: true,
                    errorMessage: 'Timeout',
                ),
            ],
            totalTime: 2.0,
            totalInputTokens: 100,
            totalOutputTokens: 50,
            totalCost: 0.01,
        );

        $report = $this->mapper->map($result, 'implement', 'task');

        self::assertStringContainsString('✗', $report);
        self::assertStringContainsString('Result: PARTIAL', $report);
    }

    #[Test]
    public function mapReportWithFallbackRunner(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [
                new StepResultDto(
                    role: 'developer',
                    runner: 'pi',
                    outputText: 'Code',
                    inputTokens: 100,
                    outputTokens: 100,
                    cost: 0.01,
                    duration: 5.0,
                    isError: false,
                    fallbackRunnerUsed: 'codex',
                ),
            ],
            totalTime: 5.0,
            totalInputTokens: 100,
            totalOutputTokens: 100,
            totalCost: 0.01,
        );

        $report = $this->mapper->map($result, 'implement', 'task');

        self::assertStringContainsString('→ codex', $report);
    }

    #[Test]
    public function mapReportWithIterationNumber(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [
                new StepResultDto(
                    role: 'developer',
                    runner: 'pi',
                    outputText: '',
                    inputTokens: 100,
                    outputTokens: 100,
                    cost: 0.01,
                    duration: 5.0,
                    isError: false,
                    iterationNumber: 2,
                ),
            ],
            totalTime: 5.0,
            totalInputTokens: 100,
            totalOutputTokens: 100,
            totalCost: 0.01,
            totalIterations: 2,
        );

        $report = $this->mapper->map($result, 'implement', 'task');

        self::assertStringContainsString('(iter 2)', $report);
        self::assertStringContainsString('Iterations: 2', $report);
    }

    #[Test]
    public function mapReportWithBudgetExceeded(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [],
            totalTime: 10.0,
            totalCost: 1.5,
            budgetExceeded: true,
            budgetLimit: 1.0,
            budgetExceededRole: 'developer',
        );

        $report = $this->mapper->map($result, 'implement', 'task');

        self::assertStringContainsString('⚠ Budget exceeded', $report);
        self::assertStringContainsString('developer', $report);
        self::assertStringContainsString('Result: BUDGET_EXCEEDED', $report);
    }

    #[Test]
    public function mapDynamicChainReportContainsRounds(): void
    {
        $result = new OrchestrateChainResultDto(
            roundResults: [
                new DynamicRoundResultDto(
                    round: 1,
                    role: 'team_lead',
                    isFacilitator: true,
                    outputText: 'Let\'s discuss',
                    inputTokens: 1000,
                    outputTokens: 500,
                    cost: 0.03,
                    duration: 8.0,
                ),
                new DynamicRoundResultDto(
                    round: 2,
                    role: 'backend_developer',
                    isFacilitator: false,
                    outputText: 'I suggest...',
                    inputTokens: 2000,
                    outputTokens: 1500,
                    cost: 0.05,
                    duration: 12.0,
                ),
            ],
            totalTime: 20.0,
            totalInputTokens: 3000,
            totalOutputTokens: 2000,
            totalCost: 0.08,
            synthesis: 'We agreed on approach X',
        );

        $report = $this->mapper->map($result, 'brainstorm', 'Discuss API design');

        self::assertStringContainsString('[Round 1/2] 🎤 team_lead', $report);
        self::assertStringContainsString('[Round 2/2] backend_developer', $report);
        self::assertStringContainsString('Result: SUCCESS | Synthesis available', $report);
    }

    #[Test]
    public function mapEmptyResultReport(): void
    {
        $result = new OrchestrateChainResultDto(
            totalTime: 0.0,
            totalInputTokens: 0,
            totalOutputTokens: 0,
            totalCost: 0.0,
        );

        $report = $this->mapper->map($result, 'implement', 'Empty task');

        self::assertStringContainsString('Agent Chain Report: implement', $report);
        self::assertStringContainsString('Result: SUCCESS | All steps completed', $report);
    }

    #[Test]
    public function formatTimeFormatsMinutesAndSeconds(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [
                new StepResultDto(
                    role: 'analyst',
                    runner: 'pi',
                    outputText: '',
                    inputTokens: 100,
                    outputTokens: 50,
                    cost: 0.01,
                    duration: 75.0,
                    isError: false,
                ),
            ],
            totalTime: 75.0,
            totalInputTokens: 100,
            totalOutputTokens: 50,
            totalCost: 0.01,
        );

        $report = $this->mapper->map($result, 'implement', 'task');

        self::assertStringContainsString('1m 15.0s', $report);
    }
}
