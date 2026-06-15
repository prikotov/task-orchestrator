<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Application\UseCase\Command\OrchestrateChain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\LoadRawChain\LoadRawChainQueryHandler;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainDefinitionFactory;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainStepFactory;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Mapper\Chain\YamlChainStepMapper;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Mapper\Chain\YamlRetryPolicyMapper;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Specification\Chain\FixIterationsReferenceIntegritySpecification;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain\YamlChainLoaderService;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Service\Chain\StaticExecutionStrategyService;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Service\ExecuteStaticChainService;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommandHandler;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Chain\Hook\HookExecutorInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\CheckStaticBudgetServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\FormatPromptServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ResolveChainRunnerServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\RunStaticChainService;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ExecuteAgentStepService;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ExecuteQualityGateStepService;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ResolveStepRunnerService;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ExecuteToolStepService;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\HookResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Integration\Service\ChainDefinition\ChainExecutionDefinitionMapperService;

/**
 * Integration-тест: static chain end-to-end.
 *
 * Проверяет полный цикл: YAML-конфигурация → YamlChainLoaderService → OrchestrateChainCommandHandler
 * → StaticExecutionStrategyService → RunStaticChainService → ResolveStepRunnerService → ExecuteAgentStepService → RunAgentServiceInterface (stub)
 * → OrchestrateChainResultDto.
 *
 * Внешние зависимости (AI-агент) подменяются стабом. Все внутренние слои — реальные объекты.
 */
#[Group('integration')]
#[CoversClass(OrchestrateChainCommandHandler::class)]
#[CoversClass(StaticExecutionStrategyService::class)]
#[CoversClass(ExecuteStaticChainService::class)]
#[CoversClass(RunStaticChainService::class)]
#[CoversClass(ResolveStepRunnerService::class)]
#[CoversClass(ExecuteAgentStepService::class)]
#[CoversClass(YamlChainLoaderService::class)]
final class StaticChainIntegrationTest extends TestCase
{
    private const string FIXTURES_DIR = __DIR__ . '/../../../../_fixtures';

    private ChainLoaderInterface $chainLoader;

    private StubRunAgentService $stubAgent;

    private OrchestrateChainCommandHandler $handler;

    protected function setUp(): void
    {
        $this->chainLoader = new YamlChainLoaderService(self::FIXTURES_DIR . '/test_chains.yaml', new ChainDefinitionFactory(new FixIterationsReferenceIntegritySpecification()), new YamlChainStepMapper(new ChainStepFactory(), new YamlRetryPolicyMapper()), new YamlRetryPolicyMapper());
        $this->stubAgent = new StubRunAgentService();

        $budgetService = $this->createMock(CheckStaticBudgetServiceInterface::class);
        $budgetService->method('shouldBreakBeforeStep')->willReturn(false);
        $budgetService->method('shouldBreakAfterStep')->willReturn(false);

        $runnerHelper = $this->createMock(ResolveChainRunnerServiceInterface::class);
        $formatter = $this->createMock(FormatPromptServiceInterface::class);
        $formatter->method('buildStaticContext')->willReturnCallback(
            static fn(string $role, string $previousOutput, string $task): string => $previousOutput,
        );

        $agentStepRunner = new ExecuteAgentStepService(
            $this->stubAgent,
            $runnerHelper,
            $formatter,
        );
        $gateStepRunner = new ExecuteQualityGateStepService();
        $toolStepRunner = new ExecuteToolStepService();
        $stepRunnerResolver = new ResolveStepRunnerService([$agentStepRunner, $gateStepRunner, $toolStepRunner]);

        $hookExecutor = $this->createMock(HookExecutorInterface::class);
        $hookExecutor->method('execute')->willReturn(
            HookResultVo::createSkipped(),
        );

        $runStaticChainService = new RunStaticChainService(
            $stepRunnerResolver,
            $budgetService,
            $hookExecutor,
        );
        $staticChainExecutor = new ExecuteStaticChainService($runStaticChainService);
        $definitionMapper = new ChainExecutionDefinitionMapperService(new LoadRawChainQueryHandler($this->chainLoader));
        $staticStrategy = new StaticExecutionStrategyService($staticChainExecutor, $definitionMapper);

        $this->handler = new OrchestrateChainCommandHandler(
            $definitionMapper,
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
        self::assertTotalTimeAggregatedFromSteps($result);
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
        self::assertTotalTimeAggregatedFromSteps($result);
        self::assertFalse($result->budgetExceeded);
        self::assertSame(0, $result->totalIterations);
    }

    private static function assertTotalTimeAggregatedFromSteps(OrchestrateChainResultDto $result): void
    {
        $totalDuration = 0.0;

        foreach ($result->stepResults as $stepResult) {
            self::assertGreaterThanOrEqual(0.0, $stepResult->duration);
            $totalDuration += $stepResult->duration;
        }

        self::assertGreaterThanOrEqual(0.0, $result->totalTime);
        self::assertEqualsWithDelta($totalDuration, $result->totalTime, 0.000_001);
    }
}
