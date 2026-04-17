<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Application\Mapper;

use TaskOrchestrator\Common\Module\Orchestrator\Application\Mapper\ReportJsonMapper;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\DynamicRoundResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\StepResultDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit-тесты ReportJsonMapper.
 */
#[CoversClass(ReportJsonMapper::class)]
final class ReportJsonMapperTest extends TestCase
{
    private ReportJsonMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ReportJsonMapper();
    }

    #[Test]
    public function mapStaticChainReportIsParsableJson(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [
                new StepResultDto(
                    role: 'analyst',
                    runner: 'pi',
                    outputText: 'Analysis',
                    inputTokens: 3000,
                    outputTokens: 800,
                    cost: 0.04,
                    duration: 5.2,
                    isError: false,
                ),
            ],
            totalTime: 5.2,
            totalInputTokens: 3000,
            totalOutputTokens: 800,
            totalCost: 0.04,
        );

        $json = $this->mapper->map($result, 'implement', 'Add endpoint');

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($data);
        self::assertSame('implement', $data['chain']);
        self::assertSame('Add endpoint', $data['task']);
        self::assertSame('success', $data['status']);
    }

    #[Test]
    public function mapStaticChainReportContainsSteps(): void
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

        $json = $this->mapper->map($result, 'implement', 'task');
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertCount(2, $data['steps']);
        self::assertSame('analyst', $data['steps'][0]['role']);
        self::assertSame('pi', $data['steps'][0]['runner']);
        self::assertSame('success', $data['steps'][0]['status']);
        self::assertSame(3000, $data['steps'][0]['input_tokens']);
        self::assertSame(800, $data['steps'][0]['output_tokens']);
        self::assertSame(5000, $data['steps'][0]['duration_ms']);
        self::assertSame('developer', $data['steps'][1]['role']);
    }

    #[Test]
    public function mapReportWithQualityGateStep(): void
    {
        $result = new OrchestrateChainResultDto(
            stepResults: [
                new StepResultDto(
                    role: 'quality_gate',
                    runner: '',
                    outputText: 'Passed',
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

        $json = $this->mapper->map($result, 'implement', 'task');
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('passed', $data['steps'][0]['status']);
        self::assertSame('Unit Tests', $data['steps'][0]['label']);
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

        $json = $this->mapper->map($result, 'implement', 'task');
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('partial', $data['status']);
        self::assertSame('error', $data['steps'][0]['status']);
        self::assertSame('Timeout', $data['steps'][0]['error_message']);
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

        $json = $this->mapper->map($result, 'implement', 'task');
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('budget_exceeded', $data['status']);
        self::assertTrue($data['budget_exceeded']);
        self::assertEquals(1.0, $data['budget_limit']);
        self::assertSame('developer', $data['budget_exceeded_role']);
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
                    outputText: 'Discussion',
                    inputTokens: 1000,
                    outputTokens: 500,
                    cost: 0.03,
                    duration: 8.0,
                ),
                new DynamicRoundResultDto(
                    round: 2,
                    role: 'backend_developer',
                    isFacilitator: false,
                    outputText: 'Suggestion',
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
            synthesis: 'We agreed',
            maxRoundsReached: false,
        );

        $json = $this->mapper->map($result, 'brainstorm', 'Discuss API');
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertCount(2, $data['rounds']);
        self::assertSame('team_lead', $data['rounds'][0]['role']);
        self::assertTrue($data['rounds'][0]['is_facilitator']);
        self::assertSame('backend_developer', $data['rounds'][1]['role']);
        self::assertFalse($data['rounds'][1]['is_facilitator']);
        self::assertSame('We agreed', $data['synthesis']);
        self::assertFalse($data['max_rounds_reached']);
    }

    #[Test]
    public function mapReportWithFallbackRunner(): void
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
                    fallbackRunnerUsed: 'codex',
                ),
            ],
            totalTime: 5.0,
            totalInputTokens: 100,
            totalOutputTokens: 100,
            totalCost: 0.01,
        );

        $json = $this->mapper->map($result, 'implement', 'task');
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('codex', $data['steps'][0]['fallback_runner']);
    }

    #[Test]
    public function mapReportWithIterationInfo(): void
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
                    iterationNumber: 3,
                    iterationWarning: true,
                ),
            ],
            totalTime: 5.0,
            totalInputTokens: 100,
            totalOutputTokens: 100,
            totalCost: 0.01,
            totalIterations: 3,
        );

        $json = $this->mapper->map($result, 'implement', 'task');
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(3, $data['steps'][0]['iteration']);
        self::assertTrue($data['steps'][0]['iteration_warning']);
        self::assertSame(3, $data['total_iterations']);
    }

    #[Test]
    public function mapEmptyResultReportIsParsableJson(): void
    {
        $result = new OrchestrateChainResultDto();

        $json = $this->mapper->map($result, 'implement', 'Empty');
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('success', $data['status']);
        self::assertArrayNotHasKey('steps', $data);
        self::assertArrayNotHasKey('rounds', $data);
    }

    #[Test]
    public function mapReportWithSessionDir(): void
    {
        $result = new OrchestrateChainResultDto(
            roundResults: [],
            totalTime: 0.0,
            totalInputTokens: 0,
            totalOutputTokens: 0,
            totalCost: 0.0,
            synthesis: null,
            maxRoundsReached: false,
            sessionDir: '/var/sessions/abc123',
        );

        $json = $this->mapper->map($result, 'brainstorm', 'task');
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('/var/sessions/abc123', $data['session_dir']);
    }

    #[Test]
    public function mapReportContainsTotalMetrics(): void
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
                    duration: 5.2,
                    isError: false,
                ),
            ],
            totalTime: 5.2,
            totalInputTokens: 3000,
            totalOutputTokens: 800,
            totalCost: 0.04,
        );

        $json = $this->mapper->map($result, 'implement', 'task');
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(5200, $data['total_duration_ms']);
        self::assertSame(3000, $data['total_input_tokens']);
        self::assertSame(800, $data['total_output_tokens']);
        self::assertSame(0.04, $data['total_cost']);
    }
}
