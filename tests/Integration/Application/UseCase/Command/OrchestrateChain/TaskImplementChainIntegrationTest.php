<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Application\UseCase\Command\OrchestrateChain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Application\UseCase\Query\Chain\LoadRawChain\LoadRawChainQueryHandler;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\ChainLoaderInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\FixIterationGroupVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo;
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
use TaskOrchestrator\Tests\Double\Component\BusTestFactory;

/**
 * Integration-тест: цепочка task-implement.
 *
 * Проверяет:
 * 1. Загрузку цепочки task-implement из YAML-фикстур (YamlChainLoaderService)
 * 2. Валидацию структуры: 4 шага (agent × 3 + quality_gate), fix_iterations
 * 3. End-to-end выполнение со стабом RunAgentServiceInterface
 * 4. Корректность цикла fix_iterations (implement ↔ review, max 3)
 */
#[Group('integration')]
#[CoversClass(YamlChainLoaderService::class)]
#[CoversClass(StaticChainDefinitionVo::class)]
#[CoversClass(OrchestrateChainCommandHandler::class)]
#[CoversClass(StaticExecutionStrategyService::class)]
#[CoversClass(ExecuteStaticChainService::class)]
#[CoversClass(RunStaticChainService::class)]
#[CoversClass(ResolveStepRunnerService::class)]
#[CoversClass(ExecuteAgentStepService::class)]
final class TaskImplementChainIntegrationTest extends TestCase
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
        $definitionMapper = new ChainExecutionDefinitionMapperService(BusTestFactory::queryBus(new LoadRawChainQueryHandler($this->chainLoader)));
        $staticStrategy = new StaticExecutionStrategyService($staticChainExecutor, $definitionMapper);

        $this->handler = new OrchestrateChainCommandHandler(
            $definitionMapper,
            new \ArrayIterator([$staticStrategy]),
        );
    }

    // ─── Chain Loading & Structure Validation ──────────────────────────────────

    #[Test]
    public function taskImplementChainLoadsFromYaml(): void
    {
        // Act
        $chain = $this->chainLoader->load('task-implement');

        // Assert: type is static
        self::assertInstanceOf(StaticChainDefinitionVo::class, $chain);
        self::assertSame('task-implement', $chain->getName());
        self::assertSame(ChainTypeEnum::staticType, $chain->getType());
        self::assertSame(
            'Шаблонный workflow реализации задачи: implement → self-review → review → quality gate',
            $chain->getDescription(),
        );
    }

    #[Test]
    public function taskImplementChainHasFourSteps(): void
    {
        $chain = $this->chainLoader->load('task-implement');
        $steps = $chain->getSteps();

        self::assertCount(4, $steps);
    }

    #[Test]
    public function step1IsImplementAgentStep(): void
    {
        $chain = $this->chainLoader->load('task-implement');
        $step = $chain->getSteps()[0];

        self::assertSame(ChainStepTypeEnum::agent, $step->getType());
        self::assertSame('backend_developer_levsha', $step->getRole());
        self::assertSame('implement', $step->getName());
        self::assertTrue($step->isAgent());
    }

    #[Test]
    public function step2IsSelfReviewAgentStep(): void
    {
        $chain = $this->chainLoader->load('task-implement');
        $step = $chain->getSteps()[1];

        self::assertSame(ChainStepTypeEnum::agent, $step->getType());
        self::assertSame('backend_developer_levsha', $step->getRole());
        self::assertSame('self-review', $step->getName());
        self::assertTrue($step->isAgent());
    }

    #[Test]
    public function step3IsReviewAgentStep(): void
    {
        $chain = $this->chainLoader->load('task-implement');
        $step = $chain->getSteps()[2];

        self::assertSame(ChainStepTypeEnum::agent, $step->getType());
        self::assertSame('code_reviewer_backend_puaro', $step->getRole());
        self::assertSame('review', $step->getName());
        self::assertTrue($step->isAgent());
    }

    #[Test]
    public function step4IsQualityGateStep(): void
    {
        $chain = $this->chainLoader->load('task-implement');
        $step = $chain->getSteps()[3];

        self::assertSame(ChainStepTypeEnum::qualityGate, $step->getType());
        self::assertSame('make check', $step->getCommand());
        self::assertSame('Quality Gate (phpunit + psalm + deptrac)', $step->getLabel());
        self::assertSame(300, $step->getTimeoutSeconds());
        self::assertTrue($step->isQualityGate());
    }

    #[Test]
    public function taskImplementChainHasFixIterationsGroup(): void
    {
        $chain = $this->chainLoader->load('task-implement');
        $fixIterations = $chain->getFixIterations();

        self::assertCount(1, $fixIterations);

        $group = $fixIterations[0];
        self::assertInstanceOf(FixIterationGroupVo::class, $group);
        self::assertSame('implement-review', $group->getGroup());
        self::assertSame(['implement', 'review'], $group->getStepNames());
        self::assertSame(3, $group->getMaxIterations());
    }

    #[Test]
    public function fixIterationsGroupReferencesCorrectSteps(): void
    {
        $chain = $this->chainLoader->load('task-implement');
        $group = $chain->getFixIterations()[0];

        // Group includes implement (step 0) and review (step 2)
        self::assertTrue($group->containsStep('implement'));
        self::assertTrue($group->containsStep('review'));
        self::assertFalse($group->containsStep('self-review'));

        self::assertTrue($group->isFirstStep('implement'));
        self::assertTrue($group->isLastStep('review'));
    }

    #[Test]
    public function taskImplementChainRolesAreConfigured(): void
    {
        $chain = $this->chainLoader->load('task-implement');
        $roles = $chain->getRoles();

        self::assertArrayHasKey('backend_developer_levsha', $roles);
        self::assertArrayHasKey('code_reviewer_backend_puaro', $roles);

        $devRole = $roles['backend_developer_levsha'];
        self::assertSame('docs/agents/roles/team/backend_developer_levsha.ru.md', $devRole->getPromptFile());

        $reviewerRole = $roles['code_reviewer_backend_puaro'];
        self::assertSame('docs/agents/roles/team/code_reviewer_backend_puaro.ru.md', $reviewerRole->getPromptFile());
    }

    // ─── End-to-End Execution ─────────────────────────────────────────────────

    /**
     * Линейное выполнение цепочки task-implement.
     *
     * fix_iterations (implement ↔ review, max 3) всегда делает 3 итерации,
     * т.к. retryPolicy всегда возвращает true (без анализа вывода reviewer).
     * Итого: 3 итерации × (implement + self-review + review) + quality_gate = 10 результатов.
     */
    #[Test]
    public function taskImplementExecutesAllThreeIterations(): void
    {
        // Arrange: stub all agent steps with success results for 3 iterations
        // Iteration 1: implement + self-review + review
        $this->stubAgent->pushSuccess('Implementation v1', inputTokens: 200, outputTokens: 400, cost: 0.02);
        $this->stubAgent->pushSuccess('Self-review v1: ok', inputTokens: 100, outputTokens: 150, cost: 0.01);
        $this->stubAgent->pushSuccess('Review v1: minor issues', inputTokens: 120, outputTokens: 80, cost: 0.008);
        // Iteration 2: implement + self-review + review
        $this->stubAgent->pushSuccess('Implementation v2 (improved)', inputTokens: 250, outputTokens: 450, cost: 0.025);
        $this->stubAgent->pushSuccess('Self-review v2: looks good', inputTokens: 100, outputTokens: 150, cost: 0.01);
        $this->stubAgent->pushSuccess('Review v2: still needs work', inputTokens: 120, outputTokens: 80, cost: 0.008);
        // Iteration 3: implement + self-review + review (max reached)
        $this->stubAgent->pushSuccess('Implementation v3 (final)', inputTokens: 300, outputTokens: 500, cost: 0.03);
        $this->stubAgent->pushSuccess('Self-review v3: approved', inputTokens: 100, outputTokens: 150, cost: 0.01);
        $this->stubAgent->pushSuccess('Review v3: LGTM', inputTokens: 120, outputTokens: 80, cost: 0.008);

        // Act
        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'task-implement',
            task: 'Implement feature X',
        ));

        // Assert: 9 agent steps (3 iterations × 3) + 1 quality_gate = 10 results
        self::assertInstanceOf(OrchestrateChainResultDto::class, $result);
        self::assertCount(10, $result->stepResults);

        // Iteration 1
        self::assertSame('backend_developer_levsha', $result->stepResults[0]->role);
        self::assertSame('Implementation v1', $result->stepResults[0]->outputText);
        self::assertFalse($result->stepResults[0]->isError);

        self::assertSame('backend_developer_levsha', $result->stepResults[1]->role);
        self::assertSame('Self-review v1: ok', $result->stepResults[1]->outputText);

        self::assertSame('code_reviewer_backend_puaro', $result->stepResults[2]->role);
        self::assertSame('Review v1: minor issues', $result->stepResults[2]->outputText);
        self::assertFalse($result->stepResults[2]->iterationWarning); // retry triggered

        // Iteration 2
        self::assertSame('Implementation v2 (improved)', $result->stepResults[3]->outputText);
        self::assertSame('Self-review v2: looks good', $result->stepResults[4]->outputText);
        self::assertSame('Review v2: still needs work', $result->stepResults[5]->outputText);
        self::assertFalse($result->stepResults[5]->iterationWarning); // retry triggered

        // Iteration 3 (max reached → iteration warning on last group step)
        self::assertSame('Implementation v3 (final)', $result->stepResults[6]->outputText);
        self::assertSame('Self-review v3: approved', $result->stepResults[7]->outputText);
        self::assertSame('Review v3: LGTM', $result->stepResults[8]->outputText);
        self::assertTrue($result->stepResults[8]->iterationWarning); // max reached

        // Quality gate
        self::assertSame('quality_gate', $result->stepResults[9]->role);
        self::assertTrue($result->stepResults[9]->passed);

        // Aggregated metrics
        self::assertSame(1410, $result->totalInputTokens);
        self::assertSame(2040, $result->totalOutputTokens);
        self::assertEqualsWithDelta(0.129, $result->totalCost, 0.0001);
        self::assertSame(2, $result->totalIterations);
    }

    /**
     * Выполнение цепочки с ошибкой на одном из agent-шагов.
     *
     * При ошибке на implement (шаг в fix_iterations group) цепочка останавливается.
     */
    #[Test]
    public function taskImplementStopsOnError(): void
    {
        // Arrange: implement succeeds, self-review fails
        $this->stubAgent->pushSuccess('Implementation done', inputTokens: 200, outputTokens: 400, cost: 0.02);
        $this->stubAgent->pushError('Self-review failed: timeout exceeded');

        // Act
        $result = ($this->handler)(new OrchestrateChainCommand(
            chainName: 'task-implement',
            task: 'Implement feature X',
        ));

        // Assert: chain stops after error on self-review
        self::assertCount(2, $result->stepResults);
        self::assertFalse($result->stepResults[0]->isError);
        self::assertTrue($result->stepResults[1]->isError);
        self::assertSame('Self-review failed: timeout exceeded', $result->stepResults[1]->errorMessage);
    }
}
