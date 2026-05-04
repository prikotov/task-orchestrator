<?php

declare(strict_types=1);

namespace Tests\Unit\Module\ChainDefinition\Application\Service\Chain;

use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Application\Service\Chain\ConditionalExecutionStrategy;
use TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Condition\EvaluateConditionServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\ExecuteConditionalStepServiceInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionalChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ConditionExpressionVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ConditionalStepResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Chain\Hook\HookExecutorInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\HookResultVo;

final class ConditionalExecutionStrategyTest extends TestCase
{
    private EvaluateConditionServiceInterface&MockObject $conditionEvaluator;
    private ExecuteConditionalStepServiceInterface&MockObject $stepExecutor;
    private HookExecutorInterface&MockObject $hookExecutor;
    private LoggerInterface&MockObject $logger;
    private ConditionalExecutionStrategy $strategy;

    protected function setUp(): void
    {
        $this->conditionEvaluator = $this->createMock(EvaluateConditionServiceInterface::class);
        $this->stepExecutor = $this->createMock(ExecuteConditionalStepServiceInterface::class);
        $this->hookExecutor = $this->createMock(HookExecutorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // По умолчанию hook executor возвращает skipped (hook не сконфигурирован)
        $this->hookExecutor->method('execute')->willReturn(HookResultVo::skipped());

        $this->strategy = new ConditionalExecutionStrategy(
            $this->conditionEvaluator,
            $this->stepExecutor,
            $this->hookExecutor,
            $this->logger,
        );
    }

    // ─── supports() ───────────────────────────────────────────────────

    public function testSupportsConditionalChain(): void
    {
        $chain = $this->createConditionalChain([ChainStepVo::agent(role: 'developer')]);
        $this->assertTrue($this->strategy->supports($chain));
    }

    public function testDoesNotSupportStaticChain(): void
    {
        $chain = StaticChainDefinitionVo::create(
            name: 'static-chain',
            description: 'test',
            steps: [ChainStepVo::agent(role: 'developer')],
        );
        $this->assertFalse($this->strategy->supports($chain));
    }

    public function testDoesNotSupportDynamicChain(): void
    {
        $chain = $this->createDynamicChain();
        $this->assertFalse($this->strategy->supports($chain));
    }

    // ─── resume() ─────────────────────────────────────────────────────

    public function testResumeThrowsLogicException(): void
    {
        $chain = $this->createConditionalChain([ChainStepVo::agent(role: 'developer')]);
        $command = $this->createCommand();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Conditional chain does not support resume.');

        $this->strategy->resume($chain, $command);
    }

    // ─── execute() — no conditions ────────────────────────────────────

    public function testExecuteStepsWithoutConditions(): void
    {
        $steps = [
            ChainStepVo::agent(role: 'developer', name: 'dev'),
            ChainStepVo::agent(role: 'reviewer', name: 'rev'),
        ];
        $chain = $this->createConditionalChain($steps);
        $command = $this->createCommand();

        $this->conditionEvaluator->expects($this->never())->method('evaluate');

        $callCount = 0;
        $this->stepExecutor->expects($this->exactly(2))
            ->method('executeStep')
            ->willReturnCallback(function () use (&$callCount): ConditionalStepResultVo {
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

        $result = $this->strategy->execute($chain, $command);

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
        $when = ConditionExpressionVo::createFromExpression('steps.lint.passed == true');
        $steps = [
            ChainStepVo::agent(role: 'linter', name: 'lint'),
            ChainStepVo::agent(role: 'developer', name: 'dev', when: $when),
        ];
        $chain = $this->createConditionalChain($steps);
        $command = $this->createCommand();

        $this->conditionEvaluator->expects($this->once())
            ->method('evaluate')
            ->with($when, $this->callback(fn (array $ctx) => isset($ctx['lint'])))
            ->willReturn(true);

        $this->stepExecutor->expects($this->exactly(2))
            ->method('executeStep')
            ->willReturnCallback(function (ChainStepVo $step): ConditionalStepResultVo {
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

        $result = $this->strategy->execute($chain, $command);

        $this->assertCount(2, $result->stepResults);
        $this->assertFalse($result->stepResults[0]->skipped);
        $this->assertFalse($result->stepResults[1]->skipped);
    }

    // ─── execute() — with condition false → skipped ───────────────────

    public function testExecuteStepSkippedWhenConditionFalse(): void
    {
        $when = ConditionExpressionVo::createFromExpression('steps.lint.passed == true');
        $steps = [
            ChainStepVo::agent(role: 'linter', name: 'lint'),
            ChainStepVo::agent(role: 'developer', name: 'dev', when: $when),
        ];
        $chain = $this->createConditionalChain($steps);
        $command = $this->createCommand();

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

        $result = $this->strategy->execute($chain, $command);

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
        $whenDev = ConditionExpressionVo::createFromExpression('steps.lint.passed == true');
        $whenDeploy = ConditionExpressionVo::createFromExpression('steps.tests.passed == true');
        $steps = [
            ChainStepVo::agent(role: 'linter', name: 'lint'),
            ChainStepVo::agent(role: 'developer', name: 'dev', when: $whenDev),
            ChainStepVo::qualityGate(command: 'run-tests.sh', label: 'tests', name: 'tests'),
            ChainStepVo::agent(role: 'deployer', name: 'deploy', when: $whenDeploy),
        ];
        $chain = $this->createConditionalChain($steps);
        $command = $this->createCommand();

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
            ->willReturnCallback(function (ChainStepVo $step) use (&$execCount): ConditionalStepResultVo {
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

        $result = $this->strategy->execute($chain, $command);

        $this->assertCount(4, $result->stepResults);
        $this->assertFalse($result->stepResults[0]->skipped);  // lint — unconditional
        $this->assertFalse($result->stepResults[1]->skipped);  // dev — condition true
        $this->assertFalse($result->stepResults[2]->skipped);  // tests — unconditional
        $this->assertTrue($result->stepResults[3]->skipped);   // deploy — condition false
    }

    // ─── execute() — context propagation ──────────────────────────────

    public function testContextPropagatedToConditionEvaluator(): void
    {
        $when = ConditionExpressionVo::createFromExpression('steps.lint.passed == true');
        $steps = [
            ChainStepVo::agent(role: 'linter', name: 'lint'),
            ChainStepVo::agent(role: 'developer', name: 'dev', when: $when),
        ];
        $chain = $this->createConditionalChain($steps);
        $command = $this->createCommand();

        $capturedContext = null;
        $this->conditionEvaluator->expects($this->once())
            ->method('evaluate')
            ->willReturnCallback(function (ConditionExpressionVo $expr, array $context) use (&$capturedContext): bool {
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

        $this->strategy->execute($chain, $command);

        $this->assertNotNull($capturedContext);
        $this->assertArrayHasKey('lint', $capturedContext);
        $this->assertTrue($capturedContext['lint']['passed']);
        $this->assertSame(0, $capturedContext['lint']['exitCode']);
        $this->assertSame('success', $capturedContext['lint']['status']);
    }

    // ─── execute() — quality gate skipped correctly ───────────────────

    public function testQualityGateSkippedWhenConditionFalse(): void
    {
        $when = ConditionExpressionVo::createFromExpression('steps.lint.passed == true');
        $steps = [
            ChainStepVo::agent(role: 'linter', name: 'lint'),
            ChainStepVo::qualityGate(command: 'run-deploy-check.sh', label: 'deploy-check', name: 'deploy', when: $when),
        ];
        $chain = $this->createConditionalChain($steps);
        $command = $this->createCommand();

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

        $result = $this->strategy->execute($chain, $command);

        $this->assertCount(2, $result->stepResults);
        $this->assertFalse($result->stepResults[0]->skipped);
        $this->assertTrue($result->stepResults[1]->skipped);
        $this->assertSame('quality_gate', $result->stepResults[1]->role);
        $this->assertSame('shell', $result->stepResults[1]->runner);
    }

    // ─── execute() — single step, no condition ───────────────────────

    public function testExecuteSingleStepNoCondition(): void
    {
        $steps = [ChainStepVo::agent(role: 'developer', name: 'dev')];
        $chain = $this->createConditionalChain($steps);
        $command = $this->createCommand();

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

        $result = $this->strategy->execute($chain, $command);

        $this->assertCount(1, $result->stepResults);
        $this->assertFalse($result->stepResults[0]->skipped);
        $this->assertEquals(100, $result->totalInputTokens);
    }

    // ─── execute() — timeout propagation ──────────────────────────────

    public function testExecuteWithTimedOutStep(): void
    {
        $steps = [
            ChainStepVo::agent(role: 'developer', name: 'dev'),
        ];
        $chain = $this->createConditionalChain($steps);
        $command = $this->createCommand();

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

        $result = $this->strategy->execute($chain, $command);

        $this->assertTrue($result->timedOut);
        $this->assertTrue($result->stepResults[0]->timedOut);
    }

    // ─── execute() — custom timeout from command ──────────────────────

    public function testCustomTimeoutFromCommand(): void
    {
        $steps = [ChainStepVo::agent(role: 'developer', name: 'dev')];
        $chain = $this->createConditionalChain($steps);
        $command = new OrchestrateChainCommand(
            chainName: 'test-chain',
            task: 'test task',
            timeout: 600,
        );

        $capturedTimeout = null;
        $this->stepExecutor->expects($this->once())
            ->method('executeStep')
            ->willReturnCallback(function (ChainStepVo $step, string $task, ?string $workingDir, int $timeout) use (&$capturedTimeout): ConditionalStepResultVo {
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

        $this->strategy->execute($chain, $command);

        $this->assertSame(600, $capturedTimeout);
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    private function createConditionalChain(array $steps): ConditionalChainDefinitionVo
    {
        return ConditionalChainDefinitionVo::create(
            name: 'test-conditional-chain',
            description: 'test conditional chain',
            steps: $steps,
        );
    }

    private function createDynamicChain(): DynamicChainDefinitionVo
    {
        return DynamicChainDefinitionVo::create(
            name: 'dynamic-chain',
            description: 'test',
            facilitator: 'team_lead',
            participants: ['developer'],
            maxRounds: 3,
            brainstormSystemPrompt: 'system',
            facilitatorAppendPrompt: 'fac-append',
            facilitatorStartPrompt: 'fac-start',
            facilitatorContinuePrompt: 'fac-continue',
            facilitatorFinalizePrompt: 'fac-finalize',
            participantAppendPrompt: 'part-append',
            participantUserPrompt: 'part-user',
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
