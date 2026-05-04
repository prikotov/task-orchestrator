<?php

declare(strict_types=1);

namespace Tests\Unit\Module\ChainDefinition\Application\Service\Chain;

use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Service\Chain\ConditionalExecutionStrategy;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainExecutionTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Chain\Hook\HookExecutorInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Condition\EvaluateConditionServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\ExecuteConditionalStepServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Integration\ChainDefinitionProviderInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ConditionalStepResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ConditionExpressionVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionChainInfoVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionConditionalChainConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\HookResultVo;

final class ConditionalExecutionStrategyTest extends TestCase
{
    private EvaluateConditionServiceInterface&MockObject $conditionEvaluator;
    private ExecuteConditionalStepServiceInterface&MockObject $stepExecutor;
    private HookExecutorInterface&MockObject $hookExecutor;
    private ChainDefinitionProviderInterface&MockObject $chainProvider;
    private LoggerInterface&MockObject $logger;
    private ConditionalExecutionStrategy $strategy;

    protected function setUp(): void
    {
        $this->conditionEvaluator = $this->createMock(EvaluateConditionServiceInterface::class);
        $this->stepExecutor = $this->createMock(ExecuteConditionalStepServiceInterface::class);
        $this->hookExecutor = $this->createMock(HookExecutorInterface::class);
        $this->chainProvider = $this->createMock(ChainDefinitionProviderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // По умолчанию hook executor возвращает skipped (hook не сконфигурирован)
        $this->hookExecutor->method('execute')->willReturn(HookResultVo::skipped());

        $this->strategy = new ConditionalExecutionStrategy(
            $this->conditionEvaluator,
            $this->stepExecutor,
            $this->hookExecutor,
            $this->chainProvider,
            $this->logger,
        );
    }

    // ─── supports() ───────────────────────────────────────────────────

    public function testSupportsConditionalChain(): void
    {
        $chainInfo = new ExecutionChainInfoVo('test', ChainExecutionTypeEnum::conditionalType);
        $this->assertTrue($this->strategy->supports($chainInfo));
    }

    public function testDoesNotSupportStaticChain(): void
    {
        $chainInfo = new ExecutionChainInfoVo('test', ChainExecutionTypeEnum::staticType);
        $this->assertFalse($this->strategy->supports($chainInfo));
    }

    public function testDoesNotSupportDynamicChain(): void
    {
        $chainInfo = new ExecutionChainInfoVo('test', ChainExecutionTypeEnum::dynamicType);
        $this->assertFalse($this->strategy->supports($chainInfo));
    }

    // ─── resume() ─────────────────────────────────────────────────────

    public function testResumeThrowsLogicException(): void
    {
        $chainInfo = new ExecutionChainInfoVo('test', ChainExecutionTypeEnum::conditionalType);
        $command = $this->createCommand();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Conditional chain does not support resume.');

        $this->strategy->resume($chainInfo, $command);
    }

    // ─── execute() — no conditions ────────────────────────────────────

    public function testExecuteStepsWithoutConditions(): void
    {
        $chainInfo = new ExecutionChainInfoVo('test-chain', ChainExecutionTypeEnum::conditionalType);
        $command = $this->createCommand();

        $config = $this->createConfigFromSteps([
            $this->createExecutionStep(role: 'developer', name: 'dev'),
            $this->createExecutionStep(role: 'reviewer', name: 'rev'),
        ]);
        $this->chainProvider->method('loadConditionalChainConfig')
            ->with('test-chain')
            ->willReturn($config);

        $this->conditionEvaluator->expects($this->never())->method('evaluate');

        $callCount = 0;
        $this->stepExecutor->expects($this->exactly(2))
            ->method('executeStep')
            ->willReturnCallback(function (ExecutionStepVo $step) use (&$callCount): ConditionalStepResultVo {
                $callCount++;

                return new ConditionalStepResultVo(
                    role: $callCount === 1 ? 'developer' : 'reviewer',
                    runner: 'pi',
                    outputText: "output-{$callCount}",
                    inputTokens: 100 * $callCount,
                    outputTokens: 200 * $callCount,
                    cost: 0.01 * $callCount,
                    duration: 1.0 * $callCount,
                    isError: false,
                );
            });

        $result = $this->strategy->execute($chainInfo, $command);

        $this->assertCount(2, $result->stepResults);
        $this->assertFalse($result->stepResults[0]->skipped);
        $this->assertFalse($result->stepResults[1]->skipped);
        $this->assertSame('developer', $result->stepResults[0]->role);
        $this->assertSame('reviewer', $result->stepResults[1]->role);
        $this->assertEquals(300, $result->totalInputTokens);
        $this->assertEquals(600, $result->totalOutputTokens);
        $this->assertEquals(0.03, $result->totalCost);
        $this->assertFalse($result->timedOut);
    }

    // ─── execute() — with conditions (all true) ───────────────────────

    public function testExecuteStepsWithConditionsAllTrue(): void
    {
        $chainInfo = new ExecutionChainInfoVo('test-chain', ChainExecutionTypeEnum::conditionalType);
        $command = $this->createCommand();

        $when = ConditionExpressionVo::createFromExpression('steps.lint.passed == true');
        $config = $this->createConfigFromSteps([
            $this->createExecutionStep(role: 'linter', name: 'lint'),
            $this->createExecutionStep(role: 'developer', name: 'dev', when: $when),
        ]);
        $this->chainProvider->method('loadConditionalChainConfig')
            ->with('test-chain')
            ->willReturn($config);

        $this->conditionEvaluator->expects($this->once())
            ->method('evaluate')
            ->willReturnCallback(function (\TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ConditionExpressionVo $expr, array $ctx): bool {
                return true;
            });

        $this->stepExecutor->expects($this->exactly(2))
            ->method('executeStep')
            ->willReturnCallback(function (ExecutionStepVo $step): ConditionalStepResultVo {
                return new ConditionalStepResultVo(
                    role: $step->getRole() ?? '',
                    runner: 'pi',
                    outputText: 'output',
                    inputTokens: 100,
                    outputTokens: 200,
                    cost: 0.01,
                    duration: 1.0,
                    isError: false,
                    passed: true,
                );
            });

        $result = $this->strategy->execute($chainInfo, $command);

        $this->assertCount(2, $result->stepResults);
        $this->assertFalse($result->stepResults[0]->skipped);
        $this->assertFalse($result->stepResults[1]->skipped);
    }

    // ─── execute() — with condition false → skipped ───────────────────

    public function testExecuteStepSkippedWhenConditionFalse(): void
    {
        $chainInfo = new ExecutionChainInfoVo('test-chain', ChainExecutionTypeEnum::conditionalType);
        $command = $this->createCommand();

        $when = ConditionExpressionVo::createFromExpression('steps.lint.passed == true');
        $config = $this->createConfigFromSteps([
            $this->createExecutionStep(role: 'linter', name: 'lint'),
            $this->createExecutionStep(role: 'developer', name: 'dev', when: $when),
        ]);
        $this->chainProvider->method('loadConditionalChainConfig')
            ->with('test-chain')
            ->willReturn($config);

        $this->conditionEvaluator->expects($this->once())
            ->method('evaluate')
            ->willReturn(false);

        // Только первый шаг выполняется, второй — skipped
        $this->stepExecutor->expects($this->once())
            ->method('executeStep')
            ->willReturn(new ConditionalStepResultVo(
                role: 'linter',
                runner: 'pi',
                outputText: 'lint output',
                inputTokens: 50,
                outputTokens: 100,
                cost: 0.005,
                duration: 0.5,
                isError: false,
                passed: true,
            ));

        // Логируем skipped
        $this->logger->expects($this->once())->method('info');

        $result = $this->strategy->execute($chainInfo, $command);

        $this->assertCount(2, $result->stepResults);
        $this->assertFalse($result->stepResults[0]->skipped);
        $this->assertTrue($result->stepResults[1]->skipped);
        $this->assertSame('developer', $result->stepResults[1]->role);
        $this->assertSame('', $result->stepResults[1]->outputText);
        // Skipped шаги не учитываются в метриках
        $this->assertEquals(50, $result->totalInputTokens);
        $this->assertEquals(0.005, $result->totalCost);
    }

    // ─── execute() — mixed conditions ─────────────────────────────────

    public function testExecuteMixedConditions(): void
    {
        $chainInfo = new ExecutionChainInfoVo('test-chain', ChainExecutionTypeEnum::conditionalType);
        $command = $this->createCommand();

        $whenDev = ConditionExpressionVo::createFromExpression('steps.lint.passed == true');
        $whenDeploy = ConditionExpressionVo::createFromExpression('steps.tests.passed == true');
        $config = $this->createConfigFromSteps([
            $this->createExecutionStep(role: 'linter', name: 'lint'),
            $this->createExecutionStep(role: 'developer', name: 'dev', when: $whenDev),
            $this->createExecutionStep(role: null, name: 'tests', isAgent: false),
            $this->createExecutionStep(role: 'deployer', name: 'deploy', when: $whenDeploy),
        ]);
        $this->chainProvider->method('loadConditionalChainConfig')
            ->with('test-chain')
            ->willReturn($config);

        $evalCount = 0;
        $this->conditionEvaluator->expects($this->exactly(2))
            ->method('evaluate')
            ->willReturnCallback(function () use (&$evalCount): bool {
                $evalCount++;

                return $evalCount === 1; // первый condition true, второй false
            });

        $execCount = 0;
        $this->stepExecutor->expects($this->exactly(3))
            ->method('executeStep')
            ->willReturnCallback(function (ExecutionStepVo $step) use (&$execCount): ConditionalStepResultVo {
                $execCount++;

                return new ConditionalStepResultVo(
                    role: $step->isAgent() ? ($step->getRole() ?? '') : 'quality_gate',
                    runner: $step->isAgent() ? $step->getRunner() : 'shell',
                    outputText: "output-{$execCount}",
                    inputTokens: $step->isAgent() ? 100 : 0,
                    outputTokens: $step->isAgent() ? 200 : 0,
                    cost: $step->isAgent() ? 0.01 : 0.0,
                    duration: 1.0,
                    isError: false,
                    passed: true,
                    exitCode: 0,
                    label: $step->getLabel(),
                );
            });

        $result = $this->strategy->execute($chainInfo, $command);

        $this->assertCount(4, $result->stepResults);
        $this->assertFalse($result->stepResults[0]->skipped);  // lint — unconditional
        $this->assertFalse($result->stepResults[1]->skipped);  // dev — condition true
        $this->assertFalse($result->stepResults[2]->skipped);  // tests — unconditional
        $this->assertTrue($result->stepResults[3]->skipped);   // deploy — condition false
    }

    // ─── execute() — context propagation ──────────────────────────────

    public function testContextPropagatedToConditionEvaluator(): void
    {
        $chainInfo = new ExecutionChainInfoVo('test-chain', ChainExecutionTypeEnum::conditionalType);
        $command = $this->createCommand();

        $when = ConditionExpressionVo::createFromExpression('steps.lint.passed == true');
        $config = $this->createConfigFromSteps([
            $this->createExecutionStep(role: 'linter', name: 'lint'),
            $this->createExecutionStep(role: 'developer', name: 'dev', when: $when),
        ]);
        $this->chainProvider->method('loadConditionalChainConfig')
            ->with('test-chain')
            ->willReturn($config);

        $capturedContext = null;
        $this->conditionEvaluator->expects($this->once())
            ->method('evaluate')
            ->willReturnCallback(function (\TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ConditionExpressionVo $expr, array $context) use (&$capturedContext): bool {
                $capturedContext = $context;

                return true;
            });

        $this->stepExecutor->expects($this->exactly(2))
            ->method('executeStep')
            ->willReturn(new ConditionalStepResultVo(
                role: 'test',
                runner: 'pi',
                outputText: 'output',
                inputTokens: 100,
                outputTokens: 200,
                cost: 0.01,
                duration: 1.0,
                isError: false,
                passed: true,
                exitCode: 0,
            ));

        $this->strategy->execute($chainInfo, $command);

        $this->assertNotNull($capturedContext);
        $this->assertArrayHasKey('lint', $capturedContext);
        $this->assertTrue($capturedContext['lint']['passed']);
        $this->assertSame(0, $capturedContext['lint']['exitCode']);
        $this->assertSame('success', $capturedContext['lint']['status']);
    }

    // ─── execute() — quality gate skipped correctly ───────────────────

    public function testQualityGateSkippedWhenConditionFalse(): void
    {
        $chainInfo = new ExecutionChainInfoVo('test-chain', ChainExecutionTypeEnum::conditionalType);
        $command = $this->createCommand();

        $when = ConditionExpressionVo::createFromExpression('steps.lint.passed == true');
        $config = $this->createConfigFromSteps([
            $this->createExecutionStep(role: 'linter', name: 'lint'),
            $this->createExecutionStep(role: null, name: 'deploy', isAgent: false, when: $when),
        ]);
        $this->chainProvider->method('loadConditionalChainConfig')
            ->with('test-chain')
            ->willReturn($config);

        $this->conditionEvaluator->expects($this->once())
            ->method('evaluate')
            ->willReturn(false);

        $this->stepExecutor->expects($this->once())
            ->method('executeStep')
            ->willReturn(new ConditionalStepResultVo(
                role: 'linter',
                runner: 'pi',
                outputText: 'lint output',
                inputTokens: 50,
                outputTokens: 100,
                cost: 0.005,
                duration: 0.5,
                isError: false,
                passed: false,
                exitCode: 1,
            ));

        $result = $this->strategy->execute($chainInfo, $command);

        $this->assertCount(2, $result->stepResults);
        $this->assertFalse($result->stepResults[0]->skipped);
        $this->assertTrue($result->stepResults[1]->skipped);
        $this->assertSame('quality_gate', $result->stepResults[1]->role);
        $this->assertSame('shell', $result->stepResults[1]->runner);
    }

    // ─── execute() — single step, no condition ───────────────────────

    public function testExecuteSingleStepNoCondition(): void
    {
        $chainInfo = new ExecutionChainInfoVo('test-chain', ChainExecutionTypeEnum::conditionalType);
        $command = $this->createCommand();

        $config = $this->createConfigFromSteps([
            $this->createExecutionStep(role: 'developer', name: 'dev'),
        ]);
        $this->chainProvider->method('loadConditionalChainConfig')
            ->with('test-chain')
            ->willReturn($config);

        $this->conditionEvaluator->expects($this->never())->method('evaluate');

        $this->stepExecutor->expects($this->once())
            ->method('executeStep')
            ->willReturn(new ConditionalStepResultVo(
                role: 'developer',
                runner: 'pi',
                outputText: 'output',
                inputTokens: 100,
                outputTokens: 200,
                cost: 0.01,
                duration: 1.0,
                isError: false,
            ));

        $result = $this->strategy->execute($chainInfo, $command);

        $this->assertCount(1, $result->stepResults);
        $this->assertFalse($result->stepResults[0]->skipped);
        $this->assertEquals(100, $result->totalInputTokens);
    }

    // ─── execute() — timeout propagation ──────────────────────────────

    public function testExecuteWithTimedOutStep(): void
    {
        $chainInfo = new ExecutionChainInfoVo('test-chain', ChainExecutionTypeEnum::conditionalType);
        $command = $this->createCommand();

        $config = $this->createConfigFromSteps([
            $this->createExecutionStep(role: 'developer', name: 'dev'),
        ]);
        $this->chainProvider->method('loadConditionalChainConfig')
            ->with('test-chain')
            ->willReturn($config);

        $this->stepExecutor->expects($this->once())
            ->method('executeStep')
            ->willReturn(new ConditionalStepResultVo(
                role: 'developer',
                runner: 'pi',
                outputText: '',
                inputTokens: 100,
                outputTokens: 0,
                cost: 0.01,
                duration: 300.0,
                isError: true,
                errorMessage: 'Timeout exceeded',
                timedOut: true,
            ));

        $result = $this->strategy->execute($chainInfo, $command);

        $this->assertTrue($result->timedOut);
        $this->assertTrue($result->stepResults[0]->timedOut);
    }

    // ─── execute() — custom timeout from command ──────────────────────

    public function testCustomTimeoutFromCommand(): void
    {
        $chainInfo = new ExecutionChainInfoVo('test-chain', ChainExecutionTypeEnum::conditionalType);
        $command = new OrchestrateChainCommand(
            chainName: 'test-chain',
            task: 'test task',
            timeout: 600,
        );

        $config = $this->createConfigFromSteps([
            $this->createExecutionStep(role: 'developer', name: 'dev'),
        ]);
        $this->chainProvider->method('loadConditionalChainConfig')
            ->with('test-chain')
            ->willReturn($config);

        $capturedTimeout = null;
        $this->stepExecutor->expects($this->once())
            ->method('executeStep')
            ->willReturnCallback(function (ExecutionStepVo $step, string $task, ?string $workingDir, int $timeout) use (&$capturedTimeout): ConditionalStepResultVo {
                $capturedTimeout = $timeout;

                return new ConditionalStepResultVo(
                    role: 'developer',
                    runner: 'pi',
                    outputText: 'ok',
                    inputTokens: 0,
                    outputTokens: 0,
                    cost: 0.0,
                    duration: 1.0,
                    isError: false,
                );
            });

        $this->strategy->execute($chainInfo, $command);

        $this->assertSame(600, $capturedTimeout);
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    private function createExecutionStep(
        ?string $role = null,
        ?string $name = null,
        bool $isAgent = true,
        ?ConditionExpressionVo $when = null,
    ): ExecutionStepVo {
        return new ExecutionStepVo(
            type: $isAgent
                ? \TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum::agent
                : \TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum::qualityGate,
            role: $role,
            runner: $isAgent ? 'pi' : 'shell',
            tools: null,
            model: null,
            retryPolicy: null,
            name: $name,
            command: $isAgent ? '' : 'echo test',
            label: '',
            timeoutSeconds: 120,
            noContextFiles: false,
            when: $when,
            postStep: null,
        );
    }

    /**
     * @param list<ExecutionStepVo> $steps
     */
    private function createConfigFromSteps(array $steps): ExecutionConditionalChainConfigVo
    {
        return new ExecutionConditionalChainConfigVo(
            name: 'test-chain',
            steps: $steps,
            budget: null,
            timeout: null,
            roles: [],
        );
    }

    private function createCommand(): OrchestrateChainCommand
    {
        return new OrchestrateChainCommand(
            chainName: 'test-chain',
            task: 'test task',
        );
    }
}
