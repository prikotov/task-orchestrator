<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\AgentRunner;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\MetricsCollectorInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\RetryPolicyVo;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Metrics\InMemoryMetricsCollector;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\RetryingAgentRunner;
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
        $successResult = AgentResultVo::createSuccess(outputText: 'OK');

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
        $successResult = AgentResultVo::createSuccess(outputText: 'Recovered');

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
        $errorResult = AgentResultVo::createError(errorMessage: 'API rate limit');
        $successResult = AgentResultVo::createSuccess(outputText: 'Done');

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
        $errorResult = AgentResultVo::createError(errorMessage: 'Model overloaded');

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
        $successResult = AgentResultVo::createSuccess(outputText: 'OK');

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

    // ─── timeout propagation ────────────────────────────────────────────

    #[Test]
    public function propagatesTimedOutFromInnerRunnerErrorResult(): void
    {
        $timedOutResult = AgentResultVo::createError(
            errorMessage: 'Agent timed out after 30 seconds.',
            timedOut: true,
        );

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::exactly(2))
            ->method('run')
            ->willReturnOnConsecutiveCalls($timedOutResult, $timedOutResult);

        $policy = new RetryPolicyVo(maxRetries: 1, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $result = $runner->run($this->request);

        self::assertTrue($result->isError());
        self::assertTrue($result->isTimedOut());
    }

    #[Test]
    public function doesNotSetTimedOutWhenInnerErrorIsNotTimeout(): void
    {
        $errorResult = AgentResultVo::createError(errorMessage: 'API rate limit');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::exactly(2))
            ->method('run')
            ->willReturnOnConsecutiveCalls($errorResult, $errorResult);

        $policy = new RetryPolicyVo(maxRetries: 1, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $result = $runner->run($this->request);

        self::assertTrue($result->isError());
        self::assertFalse($result->isTimedOut());
    }

    #[Test]
    public function retrySucceedsAfterTimeoutClearsTimedOut(): void
    {
        $timedOutResult = AgentResultVo::createError(
            errorMessage: 'Agent timed out',
            timedOut: true,
        );
        $successResult = AgentResultVo::createSuccess(outputText: 'Recovered');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::exactly(2))
            ->method('run')
            ->willReturnOnConsecutiveCalls($timedOutResult, $successResult);

        $policy = new RetryPolicyVo(maxRetries: 1, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $result = $runner->run($this->request);

        self::assertFalse($result->isError());
        self::assertFalse($result->isTimedOut());
    }

    // ─── error classification: FATAL ────────────────────────────────────

    #[Test]
    public function doesNotRetryOnFatalExitCode(): void
    {
        $fatalResult = AgentResultVo::createError(
            errorMessage: 'Process crash',
            exitCode: 137,
        );

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::once())
            ->method('run')
            ->willReturn($fatalResult);

        $policy = new RetryPolicyVo(maxRetries: 3, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $result = $runner->run($this->request);

        self::assertTrue($result->isError());
        self::assertSame(137, $result->getExitCode());
        self::assertSame('Process crash', $result->getErrorMessage());
    }

    #[Test]
    public function doesNotRetryOnFatalExitCode100(): void
    {
        $fatalResult = AgentResultVo::createError(
            errorMessage: 'Segmentation fault',
            exitCode: 100,
        );

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::once())
            ->method('run')
            ->willReturn($fatalResult);

        $policy = new RetryPolicyVo(maxRetries: 3, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $result = $runner->run($this->request);

        self::assertTrue($result->isError());
        self::assertSame(100, $result->getExitCode());
    }

    #[Test]
    public function doesNotRetryOnFatalExitCode255(): void
    {
        $fatalResult = AgentResultVo::createError(
            errorMessage: 'Invalid API key',
            exitCode: 255,
        );

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::once())
            ->method('run')
            ->willReturn($fatalResult);

        $policy = new RetryPolicyVo(maxRetries: 5, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $result = $runner->run($this->request);

        self::assertTrue($result->isError());
        self::assertSame(255, $result->getExitCode());
    }

    // ─── error classification: TRANSIENT (backward compatibility) ────────

    #[Test]
    public function retriesOnTransientExitCode1(): void
    {
        $transientResult = AgentResultVo::createError(
            errorMessage: 'Rate limit',
            exitCode: 1,
        );
        $successResult = AgentResultVo::createSuccess(outputText: 'OK');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::exactly(2))
            ->method('run')
            ->willReturnOnConsecutiveCalls($transientResult, $successResult);

        $policy = new RetryPolicyVo(maxRetries: 3, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $result = $runner->run($this->request);

        self::assertFalse($result->isError());
        self::assertSame('OK', $result->getOutputText());
    }

    #[Test]
    public function retriesOnTimedOutResult(): void
    {
        $timedOutResult = AgentResultVo::createError(
            errorMessage: 'Agent timed out',
            timedOut: true,
        );
        $successResult = AgentResultVo::createSuccess(outputText: 'OK');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::exactly(2))
            ->method('run')
            ->willReturnOnConsecutiveCalls($timedOutResult, $successResult);

        $policy = new RetryPolicyVo(maxRetries: 3, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $result = $runner->run($this->request);

        self::assertFalse($result->isError());
    }

    // ─── error classification: UNKNOWN (retry conservatively) ────────────

    #[Test]
    public function retriesOnUnknownExitCode0WithError(): void
    {
        $unknownResult = AgentResultVo::createError(
            errorMessage: 'Anomaly',
            exitCode: 0,
        );
        $successResult = AgentResultVo::createSuccess(outputText: 'OK');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::exactly(2))
            ->method('run')
            ->willReturnOnConsecutiveCalls($unknownResult, $successResult);

        $policy = new RetryPolicyVo(maxRetries: 3, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $result = $runner->run($this->request);

        self::assertFalse($result->isError());
    }

    #[Test]
    public function logsWarningOnFatalClassification(): void
    {
        $fatalResult = AgentResultVo::createError(
            errorMessage: 'Process crash',
            exitCode: 137,
        );

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::once())
            ->method('run')
            ->willReturn($fatalResult);

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('fatal error'));

        $policy = new RetryPolicyVo(maxRetries: 3, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $runner->run($this->request);
    }

    // ─── Metrics integration ────────────────────────────────────────────

    #[Test]
    public function recordsAttemptCounterOnEachAttempt(): void
    {
        $successResult = AgentResultVo::createSuccess(outputText: 'OK');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn($successResult);

        $metrics = new InMemoryMetricsCollector();

        $policy = new RetryPolicyVo(maxRetries: 3, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger, $metrics);

        $runner->run($this->request);

        // Одна попытка, один attempt counter
        self::assertSame(1, $metrics->getCounterTotal('runner.attempt'));
        $counters = $metrics->getCounters();
        self::assertArrayHasKey('attempt=1,runner=pi', $counters['runner.attempt']);
    }

    #[Test]
    public function recordsAttemptCounterForMultipleAttempts(): void
    {
        $successResult = AgentResultVo::createSuccess(outputText: 'OK');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::exactly(2))
            ->method('run')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new RuntimeException('Fail')),
                $successResult,
            );

        $metrics = new InMemoryMetricsCollector();

        $policy = new RetryPolicyVo(maxRetries: 3, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger, $metrics);

        $runner->run($this->request);

        // Две попытки
        self::assertSame(2, $metrics->getCounterTotal('runner.attempt'));
        $counters = $metrics->getCounters();
        self::assertSame(1, $counters['runner.attempt']['attempt=1,runner=pi']);
        self::assertSame(1, $counters['runner.attempt']['attempt=2,runner=pi']);
    }

    #[Test]
    public function recordsErrorCounterOnException(): void
    {
        $successResult = AgentResultVo::createSuccess(outputText: 'OK');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::exactly(2))
            ->method('run')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new RuntimeException('Fail')),
                $successResult,
            );

        $metrics = new InMemoryMetricsCollector();

        $policy = new RetryPolicyVo(maxRetries: 3, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger, $metrics);

        $runner->run($this->request);

        // 1 error counter (первая попытка)
        self::assertSame(1, $metrics->getCounterTotal('runner.error'));
    }

    #[Test]
    public function recordsErrorCounterOnErrorResult(): void
    {
        $errorResult = AgentResultVo::createError(errorMessage: 'API error');
        $successResult = AgentResultVo::createSuccess(outputText: 'OK');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::exactly(2))
            ->method('run')
            ->willReturnOnConsecutiveCalls($errorResult, $successResult);

        $metrics = new InMemoryMetricsCollector();

        $policy = new RetryPolicyVo(maxRetries: 3, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger, $metrics);

        $runner->run($this->request);

        // 1 error counter
        self::assertSame(1, $metrics->getCounterTotal('runner.error'));
    }

    #[Test]
    public function recordsDurationOnSuccess(): void
    {
        $successResult = AgentResultVo::createSuccess(outputText: 'OK');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn($successResult);

        $metrics = new InMemoryMetricsCollector();

        $policy = new RetryPolicyVo(maxRetries: 3, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger, $metrics);

        $runner->run($this->request);

        // Длительность записана
        $timings = $metrics->getTimings();
        self::assertArrayHasKey('runner.duration', $timings);
        $durationValues = $timings['runner.duration'];
        self::assertCount(1, $durationValues);
        // Проверяем что result=success
        self::assertArrayHasKey('result=success,runner=pi', $durationValues);
        self::assertGreaterThan(0.0, $durationValues['result=success,runner=pi'][0]);
    }

    #[Test]
    public function recordsDurationOnExhausted(): void
    {
        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner
            ->expects(self::exactly(2))
            ->method('run')
            ->willThrowException(new RuntimeException('Fail'));

        $metrics = new InMemoryMetricsCollector();

        $policy = new RetryPolicyVo(maxRetries: 1, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger, $metrics);

        $runner->run($this->request);

        // Длительность записана с result=exhausted
        $timings = $metrics->getTimings();
        self::assertArrayHasKey('runner.duration', $timings);
        self::assertArrayHasKey('result=exhausted,runner=pi', $timings['runner.duration']);
    }

    #[Test]
    public function worksWithoutMetricsCollector(): void
    {
        $successResult = AgentResultVo::createSuccess(outputText: 'OK');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn($successResult);

        // Metrics = null (default)
        $policy = new RetryPolicyVo(maxRetries: 3, initialDelayMs: 0);
        $runner = new RetryingAgentRunner($this->innerRunner, $policy, $this->logger);

        $result = $runner->run($this->request);

        self::assertFalse($result->isError());
    }
}
