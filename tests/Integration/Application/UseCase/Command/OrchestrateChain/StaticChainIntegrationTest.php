<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Application\UseCase\Command\OrchestrateChain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\StaticExecution\Application\Service\ExecuteStaticChainServiceInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain\StaticExecutionStrategy;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommandHandler;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\Orchestrator\Application\Service\Chain\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Shared\PromptFormatterInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain\YamlChainLoader;
use TaskOrchestrator\Common\Module\StaticExecution\Application\Service\ExecuteStaticChainService;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\CheckStaticBudgetServiceInterface;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\ExecuteStaticStepService;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\FormatPromptServiceInterface;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\ResolveChainRunnerServiceInterface;
use TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\RunStaticChainService;

/**
 * Integration-тест: static chain end-to-end.
 *
 * Проверяет полный цикл: YAML-конфигурация → YamlChainLoader → OrchestrateChainCommandHandler
 * → StaticExecutionStrategy → RunStaticChainService → ExecuteStaticStepService → RunAgentServiceInterface (stub)
 * → OrchestrateChainResultDto.
 *
 * Внешние зависимости (AI-агент) подменяются стабом. Все внутренние слои — реальные объекты.
 */
#[Group('integration')]
#[CoversClass(OrchestrateChainCommandHandler::class)]
#[CoversClass(StaticExecutionStrategy::class)]
#[CoversClass(ExecuteStaticChainService::class)]
#[CoversClass(RunStaticChainService::class)]
#[CoversClass(ExecuteStaticStepService::class)]
#[CoversClass(YamlChainLoader::class)]
final class StaticChainIntegrationTest extends TestCase
{
    private const string FIXTURES_DIR = __DIR__ . '/../../../../_fixtures';

    private ChainLoaderInterface $chainLoader;

    private StubRunAgentService $stubAgent;

    private OrchestrateChainCommandHandler $handler;

    protected function setUp(): void
    {
        $this->chainLoader = new YamlChainLoader(self::FIXTURES_DIR . '/test_chains.yaml');
        $this->stubAgent = new StubRunAgentService();

        $budgetService = $this->createMock(CheckStaticBudgetServiceInterface::class);
        $budgetService->method('shouldBreakBeforeStep')->willReturn(false);
        $budgetService->method('shouldBreakAfterStep')->willReturn(false);

        $runnerHelper = $this->createMock(ResolveChainRunnerServiceInterface::class);
        $formatter = $this->createMock(FormatPromptServiceInterface::class);
        $formatter->method('buildStaticContext')->willReturnCallback(
            static fn(string $role, string $previousOutput, string $task): string => $previousOutput,
        );

        $stepService = new ExecuteStaticStepService(
            $this->stubAgent,
            $runnerHelper,
            $formatter,
        );
        $runStaticChainService = new RunStaticChainService(
            $stepService,
            $budgetService,
        );
        $staticChainExecutor = new ExecuteStaticChainService($runStaticChainService);
        $staticStrategy = new StaticExecutionStrategy($staticChainExecutor);

        $this->handler = new OrchestrateChainCommandHandler(
            $this->chainLoader,
            new \ArrayIterator([$staticStrategy]),
        );
    }

    // --- Static chain: simple two-step ---

    #[Test]
    public function staticSimpleChainExecutesAllStepsEndToEnd(): void
    {
        // Arrange
        $this->stubAgent->pushSuccess('Analysis result', inputTokens: 100, outputTokens: 200, cost: 0.01);
        $this->stubAgent->pushSuccess('Implementation result', inputTokens: 150, outputTokens: 300, cost: 0.02);

        // Act
        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'static_simple',
            task: 'Implement feature X',
        ));

        // Assert
        self::assertInstanceOf(OrchestrateChainResultDto::class, $result);
        self::assertCount(2, $result->stepResults);

        // Step 1: analyst
        self::assertSame('analyst', $result->stepResults[0]->role);
        self::assertSame('Analysis result', $result->stepResults[0]->outputText);
        self::assertFalse($result->stepResults[0]->isError);
        self::assertSame(100, $result->stepResults[0]->inputTokens);
        self::assertSame(200, $result->stepResults[0]->outputTokens);
        self::assertSame(0.01, $result->stepResults[0]->cost);

        // Step 2: developer
        self::assertSame('developer', $result->stepResults[1]->role);
        self::assertSame('Implementation result', $result->stepResults[1]->outputText);
        self::assertFalse($result->stepResults[1]->isError);
        self::assertSame(150, $result->stepResults[1]->inputTokens);
        self::assertSame(300, $result->stepResults[1]->outputTokens);
        self::assertSame(0.02, $result->stepResults[1]->cost);

        // Aggregated metrics
        self::assertSame(250, $result->totalInputTokens);
        self::assertSame(500, $result->totalOutputTokens);
        self::assertSame(0.03, $result->totalCost);
        self::assertGreaterThan(0.0, $result->totalTime);
        self::assertFalse($result->timedOut);
    }

    // --- Static chain: with fix iterations ---

    #[Test]
    public function staticChainWithFixIterationsExecutesIterationLoop(): void
    {
        // Arrange: 3 steps: analyst, implement, review
        // fix_iterations: implement ↔ review, max 2 iterations
        $this->stubAgent->pushSuccess('Analysis done', inputTokens: 50, outputTokens: 100, cost: 0.005);
        // Iteration 1: implement + review
        $this->stubAgent->pushSuccess('Implementation v1', inputTokens: 100, outputTokens: 200, cost: 0.01);
        $this->stubAgent->pushSuccess('Review v1: needs improvement', inputTokens: 80, outputTokens: 50, cost: 0.005);
        // Iteration 2: implement + review
        $this->stubAgent->pushSuccess('Implementation v2 (improved)', inputTokens: 120, outputTokens: 250, cost: 0.015);
        $this->stubAgent->pushSuccess('Review v2: approved', inputTokens: 80, outputTokens: 50, cost: 0.005);

        // Act
        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'static_with_fix_iterations',
            task: 'Implement and review',
        ));

        // Assert: 5 step results (1 analyst + 2 iterations × (implement + review))
        self::assertCount(5, $result->stepResults);

        // Step 1: analyst
        self::assertSame('analyst', $result->stepResults[0]->role);
        self::assertSame('Analysis done', $result->stepResults[0]->outputText);
        self::assertFalse($result->stepResults[0]->isError);

        // Step 2: implement (iteration 1)
        self::assertSame('developer', $result->stepResults[1]->role);
        self::assertSame('Implementation v1', $result->stepResults[1]->outputText);

        // Step 3: review (iteration 1, triggers retry)
        self::assertSame('analyst', $result->stepResults[2]->role);
        self::assertSame('Review v1: needs improvement', $result->stepResults[2]->outputText);
        self::assertFalse($result->stepResults[2]->isError);

        // Step 4: implement (iteration 2)
        self::assertSame('developer', $result->stepResults[3]->role);
        self::assertSame('Implementation v2 (improved)', $result->stepResults[3]->outputText);

        // Step 5: review (iteration 2, max reached → iteration warning)
        self::assertSame('analyst', $result->stepResults[4]->role);
        self::assertSame('Review v2: approved', $result->stepResults[4]->outputText);
        self::assertTrue($result->stepResults[4]->iterationWarning);

        self::assertSame(1, $result->totalIterations);
    }

    // --- Static chain: error scenario stops execution ---

    #[Test]
    public function staticChainStopsOnError(): void
    {
        // Arrange
        $this->stubAgent->pushSuccess('Analysis completed', inputTokens: 50, outputTokens: 100, cost: 0.005);
        $this->stubAgent->pushError('Agent failed: timeout exceeded');

        // Act
        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'static_error_scenario',
            task: 'This will fail',
        ));

        // Assert
        self::assertCount(2, $result->stepResults);

        // Step 1 succeeds
        self::assertSame('analyst', $result->stepResults[0]->role);
        self::assertFalse($result->stepResults[0]->isError);
        self::assertSame('Analysis completed', $result->stepResults[0]->outputText);

        // Step 2 fails — chain stops
        self::assertSame('failing_agent', $result->stepResults[1]->role);
        self::assertTrue($result->stepResults[1]->isError);
        self::assertSame('Agent failed: timeout exceeded', $result->stepResults[1]->errorMessage);
    }

    // --- Static chain: resume throws LogicException ---

    #[Test]
    public function staticChainResumeThrowsException(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Static chain does not support resume.');

        ($this->handler)(new OrchestrateChainCommand(
            chainName: 'static_simple',
            task: 'Resume attempt',
            resumeDir: '/tmp/resume-dir',
        ));
    }

    // --- Static chain: aggregated metrics are correct ---

    #[Test]
    public function staticChainAggregatedMetricsAreAccumulated(): void
    {
        // Arrange
        $this->stubAgent->pushSuccess('First step', inputTokens: 500, outputTokens: 1000, cost: 0.05);
        $this->stubAgent->pushSuccess('Second step', inputTokens: 300, outputTokens: 600, cost: 0.03);

        // Act
        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'static_simple',
            task: 'Check metrics',
        ));

        // Assert
        self::assertSame(800, $result->totalInputTokens);
        self::assertSame(1600, $result->totalOutputTokens);
        self::assertSame(0.08, $result->totalCost);
        self::assertGreaterThan(0.0, $result->totalTime);
        self::assertFalse($result->budgetExceeded);
        self::assertSame(0, $result->totalIterations);
    }
}
