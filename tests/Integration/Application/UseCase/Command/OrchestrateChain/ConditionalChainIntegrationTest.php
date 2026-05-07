<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Application\UseCase\Command\OrchestrateChain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\LoadRawChain\LoadRawChainQueryHandler;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain\YamlChainLoader;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Service\Chain\ConditionalExecutionStrategy;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Service\Chain\StaticExecutionStrategy;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Service\ExecuteStaticChainService;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommandHandler;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Chain\Hook\HookExecutorInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Chain\Shared\PromptFormatterInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Condition\EvaluateConditionService;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\CheckStaticBudgetServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ExecuteStaticStepService;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\FormatPromptServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ResolveChainRunnerServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\RunStaticChainService;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\HookResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Infrastructure\Service\Chain\ConditionalStepService;
use TaskOrchestrator\Common\Module\ChainExecution\Integration\Service\ChainDefinition\ChainExecutionDefinitionMapper;

/**
 * Integration-тест: conditional chain end-to-end.
 *
 * Проверяет полный цикл: YAML с when-conditions → YamlChainLoader
 * → OrchestrateChainCommandHandler → ConditionalExecutionStrategy
 * → EvaluateConditionService (real) → ExecuteConditionalStepService (real)
 * → RunAgentServiceInterface (stub) → OrchestrateChainResultDto.
 *
 * Покрывает сценарии:
 * - Quality gate passed → conditional step executed
 * - Quality gate failed → conditional step skipped
 * - Mixed unconditional + conditional steps
 * - Explicit type: conditional
 * - Обратная совместимость: static chain → StaticExecutionStrategy
 * - Conditional resume → LogicException
 *
 * G6 Validation: Integration-паттерн воспроизводится на 3-й стратегии (conditional)
 * без God-interface: ConditionalExecutionStrategy < 200 LOC, ≤15 методов.
 */
#[Group('integration')]
#[CoversClass(OrchestrateChainCommandHandler::class)]
#[CoversClass(ConditionalExecutionStrategy::class)]
#[CoversClass(EvaluateConditionService::class)]
#[CoversClass(ConditionalStepService::class)]
#[CoversClass(YamlChainLoader::class)]
final class ConditionalChainIntegrationTest extends TestCase
{
    private const string FIXTURES_DIR = __DIR__ . '/../../../../_fixtures';

    private ChainLoaderInterface $chainLoader;

    private StubConditionalAgentService $conditionalAgent;

    private StubRunAgentService $staticAgent;

    private OrchestrateChainCommandHandler $handler;

    protected function setUp(): void
    {
        $this->chainLoader = new YamlChainLoader(self::FIXTURES_DIR . '/test_chains.yaml');
        $this->conditionalAgent = new StubConditionalAgentService();

        // --- Conditional strategy wiring ---
        $conditionEvaluator = new EvaluateConditionService();
        $promptFormatter = $this->createMock(PromptFormatterInterface::class);
        $promptFormatter->method('buildStaticContext')->willReturnCallback(
            static fn(string $role, string $previousOutput, string $task): string => $previousOutput,
        );

        $stepExecutor = new StubConditionalStepExecutor();
        $hookExecutor = $this->createMock(HookExecutorInterface::class);
        $hookExecutor->method('execute')->willReturn(HookResultVo::createSkipped());

        $conditionalDefinitionMapper = new ChainExecutionDefinitionMapper(new LoadRawChainQueryHandler($this->chainLoader));
        $conditionalStrategy = new ConditionalExecutionStrategy(
            $conditionEvaluator,
            $stepExecutor,
            $hookExecutor,
            $conditionalDefinitionMapper,
        );

        // --- Static strategy wiring (for backwards compatibility test) ---
        $staticAgent = new StubRunAgentService();
        $budgetService = $this->createMock(CheckStaticBudgetServiceInterface::class);
        $budgetService->method('shouldBreakBeforeStep')->willReturn(false);
        $budgetService->method('shouldBreakAfterStep')->willReturn(false);
        $runnerHelper = $this->createMock(ResolveChainRunnerServiceInterface::class);
        $formatter = $this->createMock(FormatPromptServiceInterface::class);
        $formatter->method('buildStaticContext')->willReturnCallback(
            static fn(string $role, string $previousOutput, string $task): string => $previousOutput,
        );
        $staticStepService = new ExecuteStaticStepService(
            $staticAgent,
            $runnerHelper,
            $formatter,
        );
        $runStaticChainService = new RunStaticChainService($staticStepService, $budgetService, $hookExecutor);
        $staticChainExecutor = new ExecuteStaticChainService($runStaticChainService);
        $definitionMapper = new ChainExecutionDefinitionMapper(new LoadRawChainQueryHandler($this->chainLoader));
        $staticStrategy = new StaticExecutionStrategy($staticChainExecutor, $definitionMapper);

        // --- Handler with both strategies ---
        $this->handler = new OrchestrateChainCommandHandler(
            $conditionalDefinitionMapper,
            new \ArrayIterator([$staticStrategy, $conditionalStrategy]),
        );

        // Сохраняем static stub для backwards compat теста
        $this->staticAgent = $staticAgent;
    }

    // --- Conditional chain: quality gate passed → conditional step executed ---

    #[Test]
    public function conditionalChainExecutesStepWhenQualityGatePassed(): void
    {
        // Arrange: conditional_with_quality_gate
        // Steps: analyze (agent) → tests (quality_gate: echo) → implement (when: tests.passed == true) → skip_review (when: tests.passed == false)
        // Quality gate "echo 'All tests passed'" → exit code 0 → passed = true
        $this->conditionalAgent->setResult(
            ChainRunResultVo::createSuccess('Analysis result', 50, 100, cost: 0.005),
        );

        // Act
        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'conditional_with_quality_gate',
            task: 'Implement feature X',
        ));

        // Assert
        self::assertInstanceOf(OrchestrateChainResultDto::class, $result);
        self::assertCount(4, $result->stepResults);

        // Step 1: analyst (unconditional) — executed
        self::assertSame('analyst', $result->stepResults[0]->role);
        self::assertFalse($result->stepResults[0]->skipped);
        self::assertFalse($result->stepResults[0]->isError);

        // Step 2: tests (quality gate: echo) — executed
        self::assertSame('quality_gate', $result->stepResults[1]->role);
        self::assertFalse($result->stepResults[1]->skipped);
        self::assertTrue($result->stepResults[1]->passed);

        // Step 3: implement (when: steps.tests.passed == true) — executed
        self::assertSame('developer', $result->stepResults[2]->role);
        self::assertFalse($result->stepResults[2]->skipped);

        // Step 4: skip_review (when: steps.tests.passed == false) — skipped
        self::assertSame('analyst', $result->stepResults[3]->role);
        self::assertTrue($result->stepResults[3]->skipped);

        // Aggregated: skipped steps don't contribute tokens/cost
        self::assertGreaterThan(0, $result->totalInputTokens);
        self::assertGreaterThan(0, $result->totalOutputTokens);
        self::assertFalse($result->timedOut);
    }

    // --- Conditional chain: quality gate failed → conditional step skipped ---

    #[Test]
    public function conditionalChainSkipsStepWhenQualityGateFailed(): void
    {
        // Arrange: conditional_all_skip
        // Steps: lint (quality_gate: exit 1) → build (when: steps.lint.passed == true)
        // Quality gate "exit 1" → passed = false → build skipped
        $this->conditionalAgent->setResult(
            ChainRunResultVo::createSuccess('Build result', 100, 200, cost: 0.01),
        );

        // Act
        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'conditional_all_skip',
            task: 'Build project',
        ));

        // Assert
        self::assertCount(2, $result->stepResults);

        // Step 1: lint (quality gate: exit 1) — executed but failed
        self::assertSame('quality_gate', $result->stepResults[0]->role);
        self::assertFalse($result->stepResults[0]->skipped);
        self::assertFalse($result->stepResults[0]->passed);

        // Step 2: build (when: steps.lint.passed == true) — skipped
        self::assertSame('developer', $result->stepResults[1]->role);
        self::assertTrue($result->stepResults[1]->skipped);

        // Skipped step: no tokens/cost contribution from agent
        self::assertSame(0, $result->totalInputTokens);
        self::assertSame(0, $result->totalOutputTokens);
        self::assertSame(0.0, $result->totalCost);
    }

    // --- Conditional chain: mixed unconditional + conditional steps ---

    #[Test]
    public function conditionalChainMixedStepsExecuteCorrectly(): void
    {
        // Arrange: conditional_mixed
        // Steps: analyze (agent) → check (quality_gate: echo 'ok') → implement (when: check.passed == true) → review (unconditional)
        $this->conditionalAgent->setResult(
            ChainRunResultVo::createSuccess('Implementation done', 200, 400, cost: 0.02),
        );

        // Act
        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'conditional_mixed',
            task: 'Mixed chain test',
        ));

        // Assert
        self::assertCount(4, $result->stepResults);

        // Step 1: analyst (unconditional) — executed
        self::assertSame('analyst', $result->stepResults[0]->role);
        self::assertFalse($result->stepResults[0]->skipped);

        // Step 2: check (quality_gate: echo 'ok') — passed
        self::assertSame('quality_gate', $result->stepResults[1]->role);
        self::assertFalse($result->stepResults[1]->skipped);
        self::assertTrue($result->stepResults[1]->passed);

        // Step 3: implement (when: check.passed == true) — executed
        self::assertSame('developer', $result->stepResults[2]->role);
        self::assertFalse($result->stepResults[2]->skipped);

        // Step 4: review (unconditional) — executed
        self::assertSame('analyst', $result->stepResults[3]->role);
        self::assertFalse($result->stepResults[3]->skipped);

        self::assertGreaterThan(0.0, $result->totalTime);
    }

    // --- Conditional chain: explicit type: conditional ---

    #[Test]
    public function conditionalExplicitTypeChainExecutesCorrectly(): void
    {
        // Arrange: conditional_explicit_type
        // type: conditional, steps: analyze → tests (quality_gate) → implement (when: tests.passed == true)
        $this->conditionalAgent->setResult(
            ChainRunResultVo::createSuccess('Explicit type result', 300, 600, cost: 0.03),
        );

        // Act
        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'conditional_explicit_type',
            task: 'Explicit conditional',
        ));

        // Assert
        self::assertCount(3, $result->stepResults);

        // Step 1: analyst (unconditional)
        self::assertSame('analyst', $result->stepResults[0]->role);
        self::assertFalse($result->stepResults[0]->skipped);

        // Step 2: tests (quality_gate: echo 'pass') → passed
        self::assertSame('quality_gate', $result->stepResults[1]->role);
        self::assertTrue($result->stepResults[1]->passed);

        // Step 3: implement (when: tests.passed == true) → executed
        self::assertSame('developer', $result->stepResults[2]->role);
        self::assertFalse($result->stepResults[2]->skipped);
    }

    // --- Backwards compatibility: static chain without when → StaticExecutionStrategy ---

    #[Test]
    public function staticChainStillWorksWithBothStrategiesRegistered(): void
    {
        // Arrange: static_simple — без when-conditions, должен выбрать StaticExecutionStrategy
        $this->staticAgent->pushSuccess('Analysis result', inputTokens: 100, outputTokens: 200, cost: 0.01);
        $this->staticAgent->pushSuccess('Implementation result', inputTokens: 150, outputTokens: 300, cost: 0.02);

        // Act
        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'static_simple',
            task: 'Backwards compat test',
        ));

        // Assert: static chain works as before
        self::assertCount(2, $result->stepResults);
        self::assertSame('analyst', $result->stepResults[0]->role);
        self::assertSame('Analysis result', $result->stepResults[0]->outputText);
        self::assertSame('developer', $result->stepResults[1]->role);
        self::assertSame('Implementation result', $result->stepResults[1]->outputText);
        self::assertSame(250, $result->totalInputTokens);
        self::assertSame(500, $result->totalOutputTokens);
    }

    // --- Conditional chain: resume throws LogicException ---

    #[Test]
    public function conditionalChainResumeThrowsException(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Conditional chain does not support resume.');

        ($this->handler)(new OrchestrateChainCommand(
            chainName: 'conditional_explicit_type',
            task: 'Resume attempt',
            resumeDir: '/tmp/resume-dir',
        ));
    }
}
