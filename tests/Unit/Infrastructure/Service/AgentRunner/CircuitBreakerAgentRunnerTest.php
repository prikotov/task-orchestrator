<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\AgentRunner;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\CircuitStateEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\CircuitBreakerStateVo;
use TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\AgentRunner\CircuitBreakerAgentRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(CircuitBreakerAgentRunner::class)]
final class CircuitBreakerAgentRunnerTest extends TestCase
{
    private AgentRunnerInterface&MockObject $innerRunner;
    private LoggerInterface&MockObject $logger;
    private AgentRunRequestVo $request;
    private CircuitBreakerStateVo $defaultState;

    protected function setUp(): void
    {
        $this->innerRunner = $this->createMock(AgentRunnerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->request = new AgentRunRequestVo(
            role: 'test_role',
            task: 'test task',
        );
        $this->defaultState = new CircuitBreakerStateVo(
            failureThreshold: 3,
            resetTimeoutSeconds: 60,
        );
    }

    // ─── Closed state: нормальная работа ───────────────────────────────────

    #[Test]
    public function closedStatePassesCallToInnerRunner(): void
    {
        $successResult = AgentResultVo::createFromSuccess(outputText: 'OK');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->expects(self::once())->method('run')->willReturn($successResult);

        $runner = new CircuitBreakerAgentRunner($this->innerRunner, $this->defaultState, $this->logger);

        $result = $runner->run($this->request);

        self::assertFalse($result->isError());
        self::assertSame('OK', $result->getOutputText());
    }

    #[Test]
    public function closedStateStaysClosedOnSuccess(): void
    {
        $successResult = AgentResultVo::createFromSuccess(outputText: 'OK');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn($successResult);

        $runner = new CircuitBreakerAgentRunner($this->innerRunner, $this->defaultState, $this->logger);
        $runner->run($this->request);

        $state = $runner->getCircuitState('pi');
        self::assertSame(CircuitStateEnum::closed, $state->getState());
        self::assertSame(0, $state->getFailureCount());
    }

    #[Test]
    public function closedStateRecordsFailureOnErrorResult(): void
    {
        $errorResult = AgentResultVo::createFromError(errorMessage: 'API error');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn($errorResult);

        $runner = new CircuitBreakerAgentRunner($this->innerRunner, $this->defaultState, $this->logger);
        $result = $runner->run($this->request);

        // Результат возвращается как есть, но состояние изменяется
        self::assertTrue($result->isError());
        self::assertStringContainsString('API error', $result->getErrorMessage());

        $state = $runner->getCircuitState('pi');
        self::assertSame(CircuitStateEnum::closed, $state->getState());
        self::assertSame(1, $state->getFailureCount());
    }

    #[Test]
    public function closedStateRecordsFailureOnException(): void
    {
        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willThrowException(new RuntimeException('Connection timeout'));

        $runner = new CircuitBreakerAgentRunner($this->innerRunner, $this->defaultState, $this->logger);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection timeout');
        $runner->run($this->request);
    }

    // ─── Closed → Open при достижении порога ───────────────────────────────

    #[Test]
    public function transitionsToOpenAfterThresholdFailures(): void
    {
        $errorResult = AgentResultVo::createFromError(errorMessage: 'Fail');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn($errorResult);

        // Логируется только переход Closed → Open (1 warning)
        $this->logger->expects(self::once())->method('warning');

        $runner = new CircuitBreakerAgentRunner($this->innerRunner, $this->defaultState, $this->logger);

        // failureThreshold = 3
        $runner->run($this->request); // failure 1
        $runner->run($this->request); // failure 2
        $runner->run($this->request); // failure 3 → Open

        $state = $runner->getCircuitState('pi');
        self::assertSame(CircuitStateEnum::open, $state->getState());
        self::assertSame(3, $state->getFailureCount());
    }

    // ─── Open state: вызовы блокируются ────────────────────────────────────

    #[Test]
    public function openStateBlocksCallsAndReturnsError(): void
    {
        $errorResult = AgentResultVo::createFromError(errorMessage: 'Fail');

        $this->innerRunner->method('getName')->willReturn('pi');
        // 3 вызова для перехода в Open, затем inner runner НЕ вызывается
        $this->innerRunner->expects(self::exactly(3))->method('run')->willReturn($errorResult);

        $this->logger->method('warning');

        $runner = new CircuitBreakerAgentRunner($this->innerRunner, $this->defaultState, $this->logger);

        // Доводим до Open
        $runner->run($this->request);
        $runner->run($this->request);
        $runner->run($this->request); // → Open

        // Теперь вызов блокируется
        $blockedResult = $runner->run($this->request);

        self::assertTrue($blockedResult->isError());
        self::assertStringContainsString('Circuit breaker is open', $blockedResult->getErrorMessage());
        self::assertStringContainsString('pi', $blockedResult->getErrorMessage());
    }

    // ─── HalfOpen → Closed при успехе ──────────────────────────────────────

    #[Test]
    public function halfOpenTransitionsToClosedOnSuccess(): void
    {
        $pastTime = time() - 120; // resetTimeout=60 прошёл

        $openState = new CircuitBreakerStateVo(
            state: CircuitStateEnum::open,
            failureCount: 3,
            failureThreshold: 3,
            resetTimeoutSeconds: 60,
            lastFailureAt: $pastTime,
        );

        $successResult = AgentResultVo::createFromSuccess(outputText: 'Recovered');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->expects(self::once())->method('run')->willReturn($successResult);

        // Логируем переход HalfOpen → Closed
        $this->logger->expects(self::once())->method('info');

        $runner = new CircuitBreakerAgentRunner($this->innerRunner, $openState, $this->logger);

        $result = $runner->run($this->request);

        self::assertFalse($result->isError());
        self::assertSame('Recovered', $result->getOutputText());

        $state = $runner->getCircuitState('pi');
        self::assertSame(CircuitStateEnum::closed, $state->getState());
        self::assertSame(0, $state->getFailureCount());
    }

    // ─── HalfOpen → Open при ошибке ────────────────────────────────────────

    #[Test]
    public function halfOpenTransitionsToOpenOnFailure(): void
    {
        $pastTime = time() - 120;

        $openState = new CircuitBreakerStateVo(
            state: CircuitStateEnum::open,
            failureCount: 3,
            failureThreshold: 3,
            resetTimeoutSeconds: 60,
            lastFailureAt: $pastTime,
        );

        $errorResult = AgentResultVo::createFromError(errorMessage: 'Still broken');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->expects(self::once())->method('run')->willReturn($errorResult);

        $this->logger->method('warning');

        $runner = new CircuitBreakerAgentRunner($this->innerRunner, $openState, $this->logger);

        $result = $runner->run($this->request);

        self::assertTrue($result->isError());
        // Это ошибка runner'а, не блокировка CB

        $state = $runner->getCircuitState('pi');
        self::assertSame(CircuitStateEnum::open, $state->getState());
        self::assertSame(4, $state->getFailureCount());
        // После ошибки lastFailureAt обновился → снова Open (не HalfOpen)
        self::assertTrue($state->isOpen());
        self::assertFalse($state->isHalfOpen());
    }

    #[Test]
    public function halfOpenTransitionsToOpenOnException(): void
    {
        $pastTime = time() - 120;

        $openState = new CircuitBreakerStateVo(
            state: CircuitStateEnum::open,
            failureCount: 3,
            failureThreshold: 3,
            resetTimeoutSeconds: 60,
            lastFailureAt: $pastTime,
        );

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->expects(self::once())->method('run')->willThrowException(
            new RuntimeException('Connection refused'),
        );

        $this->logger->method('warning');

        $runner = new CircuitBreakerAgentRunner($this->innerRunner, $openState, $this->logger);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection refused');

        try {
            $runner->run($this->request);
        } catch (RuntimeException $e) {
            // Проверяем, что CB перешёл в Open
            $state = $runner->getCircuitState('pi');
            self::assertSame(CircuitStateEnum::open, $state->getState());
            self::assertSame(4, $state->getFailureCount());

            throw $e;
        }
    }

    // ─── Delegation ────────────────────────────────────────────────────────

    #[Test]
    public function delegatesGetName(): void
    {
        $this->innerRunner->method('getName')->willReturn('codex');

        $runner = new CircuitBreakerAgentRunner($this->innerRunner, $this->defaultState, $this->logger);

        self::assertSame('codex', $runner->getName());
    }

    #[Test]
    public function delegatesIsAvailable(): void
    {
        $this->innerRunner->method('isAvailable')->willReturn(true);

        $runner = new CircuitBreakerAgentRunner($this->innerRunner, $this->defaultState, $this->logger);

        self::assertTrue($runner->isAvailable());
    }

    // ─── In-memory state изоляция по runner name ───────────────────────────

    #[Test]
    public function circuitStateIsPerRunner(): void
    {
        $successResult = AgentResultVo::createFromSuccess(outputText: 'OK');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn($successResult);

        $runner = new CircuitBreakerAgentRunner($this->innerRunner, $this->defaultState, $this->logger);
        $runner->run($this->request);

        // Проверяем состояние для 'pi'
        $piState = $runner->getCircuitState('pi');
        self::assertSame(CircuitStateEnum::closed, $piState->getState());

        // Состояние для 'codex' — дефолтное (не затронуто)
        $codexState = $runner->getCircuitState('codex');
        self::assertSame(CircuitStateEnum::closed, $codexState->getState());
        self::assertSame(0, $codexState->getFailureCount());
    }

    // ─── Полный цикл через декоратор ───────────────────────────────────────

    #[Test]
    public function fullCycleClosedOpenHalfOpenClosedThroughRunner(): void
    {
        $errorResult = AgentResultVo::createFromError(errorMessage: 'Error');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn($errorResult);

        $this->logger->method('warning');
        $this->logger->method('info');

        $runner = new CircuitBreakerAgentRunner($this->innerRunner, $this->defaultState, $this->logger);

        // 1-3: failures → Open (failureThreshold=3)
        $runner->run($this->request);
        $runner->run($this->request);
        $runner->run($this->request);

        $state = $runner->getCircuitState('pi');
        self::assertSame(CircuitStateEnum::open, $state->getState());

        // 4: заблокирован (Open)
        $blocked = $runner->run($this->request);
        self::assertTrue($blocked->isError());
        self::assertStringContainsString('Circuit breaker is open', $blocked->getErrorMessage());

        // Для эмуляции HalfOpen создаём новый runner с прошедшим временем
        $pastTime = time() - 120;
        $halfOpenState = new CircuitBreakerStateVo(
            state: CircuitStateEnum::open,
            failureCount: 3,
            failureThreshold: 3,
            resetTimeoutSeconds: 60,
            lastFailureAt: $pastTime,
        );

        $successResult = AgentResultVo::createFromSuccess(outputText: 'Recovered');
        $halfOpenRunner = $this->createMock(AgentRunnerInterface::class);
        $halfOpenRunner->method('getName')->willReturn('pi');
        $halfOpenRunner->method('run')->willReturn($successResult);

        $runner2 = new CircuitBreakerAgentRunner($halfOpenRunner, $halfOpenState, $this->logger);

        $result = $runner2->run($this->request);
        self::assertFalse($result->isError());
        self::assertSame('Recovered', $result->getOutputText());

        $finalState = $runner2->getCircuitState('pi');
        self::assertSame(CircuitStateEnum::closed, $finalState->getState());
        self::assertSame(0, $finalState->getFailureCount());
    }
}
