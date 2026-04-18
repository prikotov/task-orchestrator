<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\AgentRunner;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\RetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\AgentRunner\RetryingAgentRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(RetryingAgentRunner::class)]
final class RetryingAgentRunnerTest extends TestCase
{
    private AgentRunnerInterface&MockObject $innerRunner;
    private LoggerInterface&MockObject $logger;
    private AgentRunRequestVo $request;

    protected function setUp(): void
    {
        $this->innerRunner = $this->createMock(AgentRunnerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->request = new AgentRunRequestVo(
            role: 'test_role',
            task: 'test task',
        );
    }

    #[Test]
    public function returnsSuccessOnFirstAttempt(): void
    {
        $successResult = AgentResultVo::createFromSuccess(outputText: 'OK');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->expects(self::once())->method('run')->willReturn($successResult);

        $policy = new RetryPolicyVo(maxRetries: 3, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $result = $runner->run($this->request);

        self::assertFalse($result->isError());
        self::assertSame('OK', $result->getOutputText());
    }

    #[Test]
    public function retriesOnExceptionAndSucceedsOnSecondAttempt(): void
    {
        $successResult = AgentResultVo::createFromSuccess(outputText: 'Recovered');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::exactly(2))
            ->method('run')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new RuntimeException('Connection timeout')),
                $successResult,
            );

        $policy = new RetryPolicyVo(maxRetries: 2, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $result = $runner->run($this->request);

        self::assertFalse($result->isError());
        self::assertSame('Recovered', $result->getOutputText());
    }

    #[Test]
    public function retriesOnErrorResultAndSucceedsOnNextAttempt(): void
    {
        $errorResult = AgentResultVo::createFromError(errorMessage: 'API rate limit');
        $successResult = AgentResultVo::createFromSuccess(outputText: 'Done');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::exactly(2))
            ->method('run')
            ->willReturnOnConsecutiveCalls($errorResult, $successResult);

        $policy = new RetryPolicyVo(maxRetries: 3, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $result = $runner->run($this->request);

        self::assertFalse($result->isError());
        self::assertSame('Done', $result->getOutputText());
    }

    #[Test]
    public function returnsErrorAfterAllAttemptsExhausted(): void
    {
        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::exactly(4))
            ->method('run')
            ->willThrowException(new RuntimeException('Persistent failure'));

        $policy = new RetryPolicyVo(maxRetries: 3, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $result = $runner->run($this->request);

        self::assertTrue($result->isError());
        self::assertStringContainsString('All 4 attempts exhausted', $result->getErrorMessage());
        self::assertStringContainsString('Persistent failure', $result->getErrorMessage());
    }

    #[Test]
    public function returnsErrorAfterAllAttemptsExhaustedFromErrorResult(): void
    {
        $errorResult = AgentResultVo::createFromError(errorMessage: 'Model overloaded');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::exactly(3))
            ->method('run')
            ->willReturn($errorResult);

        $policy = new RetryPolicyVo(maxRetries: 2, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $result = $runner->run($this->request);

        self::assertTrue($result->isError());
        self::assertStringContainsString('All 3 attempts exhausted', $result->getErrorMessage());
        self::assertStringContainsString('Model overloaded', $result->getErrorMessage());
    }

    #[Test]
    public function delegatesGetName(): void
    {
        $this->innerRunner->method('getName')->willReturn('codex');

        $policy = new RetryPolicyVo(maxRetries: 1, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        self::assertSame('codex', $runner->getName());
    }

    #[Test]
    public function delegatesIsAvailable(): void
    {
        $this->innerRunner->method('isAvailable')->willReturn(true);

        $policy = new RetryPolicyVo(maxRetries: 1, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        self::assertTrue($runner->isAvailable());
    }

    #[Test]
    public function noRetryWhenMaxRetriesIsZero(): void
    {
        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::once())
            ->method('run')
            ->willThrowException(new RuntimeException('Fail'));

        $policy = new RetryPolicyVo(maxRetries: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $result = $runner->run($this->request);

        self::assertTrue($result->isError());
        self::assertStringContainsString('All 1 attempts exhausted', $result->getErrorMessage());
    }

    #[Test]
    public function logsWarningOnRetryAttempt(): void
    {
        $successResult = AgentResultVo::createFromSuccess(outputText: 'OK');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::exactly(2))
            ->method('run')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new RuntimeException('Timeout')),
                $successResult,
            );

        // 1 warning: попытка не удалась
        $this->logger->expects(self::once())->method('warning');
        // 1 info: succeeded on retry
        $this->logger->expects(self::once())->method('info');

        $policy = new RetryPolicyVo(maxRetries: 1, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $runner->run($this->request);
    }
}
