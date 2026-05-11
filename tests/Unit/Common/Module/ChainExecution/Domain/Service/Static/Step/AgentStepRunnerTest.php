<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\ChainExecution\Domain\Service\Static\Step;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Agent\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\FormatPromptServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ResolveChainRunnerServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\Step\AgentStepRunner;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRoleConfigVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StepContextVo;

#[CoversClass(AgentStepRunner::class)]
final class AgentStepRunnerTest extends TestCase
{
    #[Test]
    public function supportsReturnsTrueForAgentType(): void
    {
        $runner = new AgentStepRunner(
            $this->createMock(RunAgentServiceInterface::class),
            $this->createMock(ResolveChainRunnerServiceInterface::class),
            $this->createMock(FormatPromptServiceInterface::class),
        );
        self::assertTrue($runner->supports(ChainStepTypeEnum::agent));
    }

    #[Test]
    public function supportsReturnsFalseForOtherTypes(): void
    {
        $runner = new AgentStepRunner(
            $this->createMock(RunAgentServiceInterface::class),
            $this->createMock(ResolveChainRunnerServiceInterface::class),
            $this->createMock(FormatPromptServiceInterface::class),
        );
        self::assertFalse($runner->supports(ChainStepTypeEnum::qualityGate));
        self::assertFalse($runner->supports(ChainStepTypeEnum::tool));
    }

    #[Test]
    public function runReturnsSuccessfulResult(): void
    {
        $agentRunner = $this->createMock(RunAgentServiceInterface::class);
        $agentRunner->method('run')->willReturn(
            ChainRunResultVo::createSuccess('Analysis result', 100, 200, cost: 0.01),
        );

        $formatter = $this->createMock(FormatPromptServiceInterface::class);
        $formatter->method('buildStaticContext')->willReturn('formatted context');

        $runner = new AgentStepRunner(
            $agentRunner,
            $this->createMock(ResolveChainRunnerServiceInterface::class),
            $formatter,
        );

        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::agent,
            role: 'analyst',
            runner: 'pi',
        );

        $context = new StepContextVo(
            task: 'Implement feature X',
            workingDir: '/tmp/project',
            timeout: 300,
            previousContext: 'Previous output',
            iterationNumber: 1,
        );

        $result = $runner->run($step, $context);

        self::assertSame('analyst', $result->role);
        self::assertSame('pi', $result->runner);
        self::assertSame('Analysis result', $result->outputText);
        self::assertSame(100, $result->inputTokens);
        self::assertSame(200, $result->outputTokens);
        self::assertSame(0.01, $result->cost);
        self::assertFalse($result->isError);
        self::assertSame(1, $result->iterationNumber);
        self::assertGreaterThan(0.0, $result->duration);
    }

    #[Test]
    public function runReturnsErrorResult(): void
    {
        $agentRunner = $this->createMock(RunAgentServiceInterface::class);
        $agentRunner->method('run')->willReturn(
            ChainRunResultVo::createError('Agent failed: timeout exceeded', timedOut: true),
        );

        $runner = new AgentStepRunner(
            $agentRunner,
            $this->createMock(ResolveChainRunnerServiceInterface::class),
            $this->createMock(FormatPromptServiceInterface::class),
        );

        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::agent,
            role: 'developer',
            runner: 'pi',
        );

        $context = new StepContextVo(task: 'Test task');
        $result = $runner->run($step, $context);

        self::assertTrue($result->isError);
        self::assertSame('Agent failed: timeout exceeded', $result->errorMessage);
        self::assertTrue($result->timedOut);
    }

    #[Test]
    public function runWithoutPreviousContextDoesNotFormat(): void
    {
        $agentRunner = $this->createMock(RunAgentServiceInterface::class);
        $agentRunner->expects($this->once())->method('run')->with(
            $this->callback(function (ChainRunRequestVo $request): bool {
                return $request->getPreviousContext() === null;
            }),
        )->willReturn(
            ChainRunResultVo::createSuccess('Result', 50, 100, cost: 0.005),
        );

        $runner = new AgentStepRunner(
            $agentRunner,
            $this->createMock(ResolveChainRunnerServiceInterface::class),
            $this->createMock(FormatPromptServiceInterface::class),
        );

        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::agent,
            role: 'analyst',
            runner: 'pi',
        );

        $context = new StepContextVo(
            task: 'Test task',
            previousContext: null,
        );

        $runner->run($step, $context);
    }

    #[Test]
    public function runWithPreviousContextFormatsContext(): void
    {
        $agentRunner = $this->createMock(RunAgentServiceInterface::class);
        $agentRunner->expects($this->once())->method('run')->with(
            $this->callback(function (ChainRunRequestVo $request): bool {
                return $request->getPreviousContext() === 'formatted: previous output';
            }),
        )->willReturn(
            ChainRunResultVo::createSuccess('Result', 50, 100, cost: 0.005),
        );

        $formatter = $this->createMock(FormatPromptServiceInterface::class);
        $formatter->expects($this->once())->method('buildStaticContext')->with(
            'analyst',
            'previous output',
            'Test task',
        )->willReturn('formatted: previous output');

        $runner = new AgentStepRunner(
            $agentRunner,
            $this->createMock(ResolveChainRunnerServiceInterface::class),
            $formatter,
        );

        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::agent,
            role: 'analyst',
            runner: 'pi',
        );

        $context = new StepContextVo(
            task: 'Test task',
            previousContext: 'previous output',
        );

        $runner->run($step, $context);
    }

    #[Test]
    public function runPassesRoleConfigTimeout(): void
    {
        $agentRunner = $this->createMock(RunAgentServiceInterface::class);
        $agentRunner->expects($this->once())->method('run')->with(
            $this->callback(function (ChainRunRequestVo $request): bool {
                return $request->getTimeout() === 600;
            }),
        )->willReturn(
            ChainRunResultVo::createSuccess('Result', 50, 100, cost: 0.005),
        );

        $runner = new AgentStepRunner(
            $agentRunner,
            $this->createMock(ResolveChainRunnerServiceInterface::class),
            $this->createMock(FormatPromptServiceInterface::class),
        );

        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::agent,
            role: 'analyst',
            runner: 'pi',
        );

        $context = new StepContextVo(
            task: 'Test task',
            timeout: 300,
            roleConfig: new ExecutionRoleConfigVo(timeout: 600),
        );

        $runner->run($step, $context);
    }
}
