<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\Mapper;

use TaskOrchestrator\Common\Module\ChainExecution\Application\Mapper\ParticipantsMetricsMapper;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Mapper\ReportTextMapper;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\DynamicRoundResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\StepResultDto;
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
        $this->mapper = new ReportTextMapper(new ParticipantsMetricsMapper());
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
    public function mapDynamicChainReportContainsParticipantsMetricsTable(): void
    {
        // Golden-тест секции «Participants metrics»: facilitator + participant
        // с моделью, thinking, кэш-токенами и расчётом cache %.
        $result = new OrchestrateChainResultDto(
            roundResults: [
                self::dynamicRound(
                    role: 'team_lead_alex',
                    isFacilitator: true,
                    inputTokens: 1000,
                    outputTokens: 500,
                    cost: 0.02,
                    duration: 8.0,
                    model: 'glm-5.3-flash',
                    provider: 'zai',
                    thinking: 'high',
                    cacheReadTokens: 8000,
                ),
                self::dynamicRound(
                    role: 'backend_developer_levsha',
                    isFacilitator: false,
                    inputTokens: 2000,
                    outputTokens: 1500,
                    cost: 0.05,
                    duration: 12.0,
                    model: 'mimo-v2.6-pro',
                    provider: 'xiaomi-token-plan-sgp',
                    thinking: 'off',
                    cacheReadTokens: 6000,
                ),
                self::dynamicRound(
                    role: 'backend_developer_levsha',
                    isFacilitator: false,
                    inputTokens: 2000,
                    outputTokens: 1500,
                    cost: 0.05,
                    duration: 10.0,
                    model: 'mimo-v2.6-pro',
                    provider: 'xiaomi-token-plan-sgp',
                    thinking: 'off',
                    cacheReadTokens: 4000,
                ),
            ],
            totalTime: 30.0,
            totalInputTokens: 5000,
            totalOutputTokens: 3500,
            totalCost: 0.12,
            synthesis: 'We agreed on approach X',
        );

        $report = $this->mapper->map($result, 'brainstorm', 'Discuss API design');

        self::assertStringContainsString('Participants metrics', $report);
        self::assertStringContainsString('Participant', $report);
        self::assertStringContainsString('Cache %', $report);
        // facilitator: 1 шаг фасилитатора, 0 выступлений
        self::assertStringContainsString('team_lead_alex', $report);
        self::assertStringContainsString('zai/glm-5.3-flash (high)', $report);
        // participant: 2 выступления, суммарно 22.0s, cache 10k/14k ≈ 71.4%
        self::assertStringContainsString('xiaomi-token-plan-sgp/mimo-v2.6-pro (off)', $report);
        self::assertStringContainsString('71.4%', $report);
        self::assertStringContainsString('22.0s', $report);
        self::assertStringContainsString('$0.1000', $report);
    }

    #[Test]
    public function mapDynamicChainReportShowsUnknownModelAsNotAvailable(): void
    {
        // Раунды без модельных полей (старая сессия / раннер без model) — «н/д».
        $result = new OrchestrateChainResultDto(
            roundResults: [
                new DynamicRoundResultDto(
                    round: 1,
                    role: 'team_lead',
                    isFacilitator: true,
                    outputText: 'Discuss',
                    inputTokens: 0,
                    outputTokens: 0,
                    cost: 0.0,
                    duration: 3.0,
                ),
            ],
            totalTime: 3.0,
        );

        $report = $this->mapper->map($result, 'brainstorm', 'Old session resume');

        self::assertStringContainsString('Participants metrics', $report);
        self::assertStringContainsString('н/д', $report);

        // Мини-ассерт выравнивания: «н/д» — 3 символа / 5 байт UTF-8, и байтовое
        // выравнивание сдвинуло бы колонку «Cost» строки данных влево на 2
        // позиции относительно шапки.
        $headerLine = null;
        $dataLine = null;
        foreach (explode("\n", $report) as $line) {
            if (str_starts_with($line, 'Participant ')) {
                $headerLine = $line;
            }
            if (str_starts_with($line, 'team_lead ')) {
                $dataLine = $line;
            }
        }
        self::assertNotNull($headerLine);
        self::assertNotNull($dataLine);
        self::assertSame(
            mb_strpos($headerLine, 'Cost'),
            mb_strpos($dataLine, '$0.0000'),
            'Колонка Cost строки данных должна совпадать по позиции с шапкой',
        );
    }

    #[Test]
    public function mapStaticChainReportDoesNotContainParticipantsSection(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [
                new StepResultDto(
                    role: 'analyst',
                    runner: 'pi',
                    outputText: 'Analysis done',
                    inputTokens: 100,
                    outputTokens: 50,
                    cost: 0.01,
                    duration: 5.0,
                    isError: false,
                ),
            ],
            totalTime: 5.0,
            totalInputTokens: 100,
            totalOutputTokens: 50,
            totalCost: 0.01,
        );

        $report = $this->mapper->map($result, 'implement', 'task');

        self::assertStringNotContainsString('Participants metrics', $report);
    }

    private static function dynamicRound(
        string $role,
        bool $isFacilitator,
        int $inputTokens,
        int $outputTokens,
        float $cost,
        float $duration,
        string $model,
        string $provider,
        string $thinking,
        int $cacheReadTokens,
    ): DynamicRoundResultDto {
        return new DynamicRoundResultDto(
            round: 1,
            role: $role,
            isFacilitator: $isFacilitator,
            outputText: 'output',
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $cost,
            duration: $duration,
            model: $model,
            provider: $provider,
            thinking: $thinking,
            cacheReadTokens: $cacheReadTokens,
        );
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
    public function mapAgentStepContainsOutputText(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [
                new StepResultDto(
                    role: 'technical_writer',
                    runner: 'pi',
                    outputText: '# Hello World\n\nThis is the output.',
                    inputTokens: 1000,
                    outputTokens: 500,
                    cost: 0.02,
                    duration: 10.0,
                    isError: false,
                ),
            ],
            totalTime: 10.0,
            totalInputTokens: 1000,
            totalOutputTokens: 500,
            totalCost: 0.02,
        );

        $report = $this->mapper->map($result, 'write-readme', 'Write README');

        self::assertStringContainsString('# Hello World', $report);
        self::assertStringContainsString('This is the output.', $report);
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
