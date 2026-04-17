<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentCommand;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Command\RunAgent\RunAgentCommandHandler;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\RetryableRunnerFactoryInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\RetryPolicyVo;

#[CoversClass(RunAgentCommandHandler::class)]
final class RunAgentCommandHandlerTest extends TestCase
{
    private AgentRunnerInterface&MockObject $runner;
    private RetryableRunnerFactoryInterface&MockObject $retryFactory;
    private RunAgentCommandHandler $handler;

    protected function setUp(): void
    {
        $this->runner = $this->createMock(AgentRunnerInterface::class);
        $this->retryFactory = $this->createMock(RetryableRunnerFactoryInterface::class);
        $this->handler = new RunAgentCommandHandler($this->runner, $this->retryFactory);
    }

    #[Test]
    public function handleRunsWithoutRetry(): void
    {
        $command = new RunAgentCommand(
            role: 'dev',
            task: 'Write code',
            systemPrompt: 'Be helpful',
            model: 'gpt-4',
            timeout: 600,
        );

        $this->retryFactory->expects(self::never())->method('createRetryableRunner');
        $this->runner->expects(self::once())->method('run')
            ->willReturnCallback(function (AgentRunRequestVo $req): AgentResultVo {
                self::assertSame('dev', $req->getRole());
                self::assertSame('Write code', $req->getTask());
                self::assertSame('Be helpful', $req->getSystemPrompt());
                self::assertSame('gpt-4', $req->getModel());
                self::assertSame(600, $req->getTimeout());

                return AgentResultVo::createFromSuccess(
                    outputText: 'Code written',
                    inputTokens: 100,
                    outputTokens: 50,
                    cost: 0.025,
                    model: 'gpt-4',
                    turns: 2,
                );
            });

        $result = $this->handler->handle($command);

        self::assertFalse($result->isError);
        self::assertSame('Code written', $result->outputText);
        self::assertSame(100, $result->inputTokens);
        self::assertSame(50, $result->outputTokens);
        self::assertSame(0.025, $result->cost);
        self::assertSame('gpt-4', $result->model);
        self::assertSame(2, $result->turns);
        self::assertSame(0, $result->exitCode);
        self::assertNull($result->errorMessage);
    }

    #[Test]
    public function handleWrapsRunnerWithRetryWhenRetryEnabled(): void
    {
        $command = new RunAgentCommand(
            role: 'dev',
            task: 'Write code',
            retryMaxRetries: 3,
            retryInitialDelayMs: 500,
            retryMaxDelayMs: 10000,
            retryMultiplier: 2.0,
        );

        $retryRunner = $this->createMock(AgentRunnerInterface::class);

        $this->retryFactory->expects(self::once())->method('createRetryableRunner')
            ->willReturnCallback(function (
                AgentRunnerInterface $r,
                RetryPolicyVo $policy,
            ) use ($retryRunner): AgentRunnerInterface {
                self::assertSame($this->runner, $r);
                self::assertSame(3, $policy->getMaxRetries());
                self::assertSame(500, $policy->getInitialDelayMs());
                self::assertSame(10000, $policy->getMaxDelayMs());
                self::assertSame(2.0, $policy->getMultiplier());

                return $retryRunner;
            });

        $retryRunner->expects(self::once())->method('run')
            ->willReturn(AgentResultVo::createFromSuccess(outputText: 'Retried OK'));

        $result = $this->handler->handle($command);

        self::assertFalse($result->isError);
        self::assertSame('Retried OK', $result->outputText);
    }

    #[Test]
    public function handleDoesNotWrapWhenRetryMaxRetriesIsNull(): void
    {
        $command = new RunAgentCommand(
            role: 'dev',
            task: 'Write code',
            retryMaxRetries: null,
        );

        $this->retryFactory->expects(self::never())->method('createRetryableRunner');
        $this->runner->method('run')->willReturn(AgentResultVo::createFromSuccess(outputText: 'OK'));

        $result = $this->handler->handle($command);

        self::assertFalse($result->isError);
    }

    #[Test]
    public function handleDoesNotWrapWhenRetryMaxRetriesIsZero(): void
    {
        $command = new RunAgentCommand(
            role: 'dev',
            task: 'Write code',
            retryMaxRetries: 0,
        );

        $this->retryFactory->expects(self::never())->method('createRetryableRunner');
        $this->runner->method('run')->willReturn(AgentResultVo::createFromSuccess(outputText: 'OK'));

        $result = $this->handler->handle($command);

        self::assertFalse($result->isError);
    }

    #[Test]
    public function handleMapsErrorResult(): void
    {
        $command = new RunAgentCommand(role: 'dev', task: 'Write code');

        $this->runner->method('run')
            ->willReturn(AgentResultVo::createFromError(errorMessage: 'Timeout', exitCode: 124));

        $result = $this->handler->handle($command);

        self::assertTrue($result->isError);
        self::assertSame('Timeout', $result->errorMessage);
        self::assertSame(124, $result->exitCode);
        self::assertSame('', $result->outputText);
    }

    #[Test]
    public function handleMapsAllRequestFields(): void
    {
        $command = new RunAgentCommand(
            role: 'analyst',
            task: 'Analyze data',
            systemPrompt: 'Be precise',
            previousContext: 'ctx-123',
            model: 'gpt-4',
            tools: 'toolset-a',
            workingDir: '/tmp/work',
            timeout: 600,
            maxContextLength: 80000,
            command: ['run', '--verbose'],
            runnerArgs: ['--append-system-prompt', '/path/to/prompt.md'],
        );

        $this->runner->expects(self::once())->method('run')
            ->willReturnCallback(function (AgentRunRequestVo $req): AgentResultVo {
                self::assertSame('analyst', $req->getRole());
                self::assertSame('Analyze data', $req->getTask());
                self::assertSame('Be precise', $req->getSystemPrompt());
                self::assertSame('ctx-123', $req->getPreviousContext());
                self::assertSame('gpt-4', $req->getModel());
                self::assertSame('toolset-a', $req->getTools());
                self::assertSame('/tmp/work', $req->getWorkingDir());
                self::assertSame(600, $req->getTimeout());
                self::assertSame(80000, $req->getMaxContextLength());
                self::assertSame(['run', '--verbose'], $req->getCommand());
                self::assertSame(['--append-system-prompt', '/path/to/prompt.md'], $req->getRunnerArgs());

                return AgentResultVo::createFromSuccess(outputText: 'Analysis done');
            });

        $result = $this->handler->handle($command);

        self::assertFalse($result->isError);
        self::assertSame('Analysis done', $result->outputText);
    }
}
