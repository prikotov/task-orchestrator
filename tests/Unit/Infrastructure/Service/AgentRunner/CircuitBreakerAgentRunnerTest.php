<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\AgentRunner;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\Enum\CircuitStateEnum;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\CircuitBreakerStateVo;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Metrics\InMemoryMetricsCollector;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\CircuitBreakerAgentRunner;
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
        $successResult = AgentResultVo::createSuccess(outputText: 'OK');

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
        $successResult = AgentResultVo::createSuccess(outputText: 'OK');

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
        $errorResult = AgentResultVo::createError(errorMessage: 'API error');

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
        $errorResult = AgentResultVo::createError(errorMessage: 'Fail');

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

    // ─── Open state: вызовы блокируются (без fallback) ─────────────────────

    #[Test]
    public function openStateBlocksCallsAndReturnsErrorWithoutFallback(): void
    {
        $errorResult = AgentResultVo::createError(errorMessage: 'Fail');

        $this->innerRunner->method('getName')->willReturn('pi');
        // 3 вызова для перехода в Open, затем inner runner НЕ вызывается
        $this->innerRunner->expects(self::exactly(3))->method('run')->willReturn($errorResult);

        $this->logger->method('warning');

        $runner = new CircuitBreakerAgentRunner($this->innerRunner, $this->defaultState, $this->logger);

        // Доводим до Open
        $runner->run($this->request);
        $runner->run($this->request);
        $runner->run($this->request); // → Open

        // Теперь вызов блокируется (нет fallback)
        $blockedResult = $runner->run($this->request);

        self::assertTrue($blockedResult->isError());
        self::assertStringContainsString('Circuit breaker is open', $blockedResult->getErrorMessage());
        self::assertStringContainsString('pi', $blockedResult->getErrorMessage());
    }

    // ─── Open state + fallback runner ──────────────────────────────────────

    #[Test]
    public function openStateDelegatesToFallbackRunner(): void
    {
        $fallbackRunner = $this->createMock(AgentRunnerInterface::class);
        $fallbackRunner->method('getName')->willReturn('codex');

        $fallbackSuccessResult = AgentResultVo::createSuccess(outputText: 'Fallback OK');
        $fallbackRunner->expects(self::once())->method('run')->willReturn($fallbackSuccessResult);

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn(AgentResultVo::createError(errorMessage: 'Fail'));

        $this->logger->method('warning');
        $this->logger->method('info');

        $runner = new CircuitBreakerAgentRunner(
            $this->innerRunner,
            $this->defaultState,
            $this->logger,
            $fallbackRunner,
        );

        // Доводим до Open (3 failures)
        $runner->run($this->request);
        $runner->run($this->request);
        $runner->run($this->request);

        // CB open → fallback runner
        $result = $runner->run($this->request);

        self::assertFalse($result->isError());
        self::assertSame('Fallback OK', $result->getOutputText());
    }

    #[Test]
    public function openStateDelegatesToFallbackWithCustomCommand(): void
    {
        $fallbackRunner = $this->createMock(AgentRunnerInterface::class);
        $fallbackRunner->method('getName')->willReturn('codex');

        $fallbackSuccessResult = AgentResultVo::createSuccess(outputText: 'Codex OK');
        $fallbackCommand = ['codex', '--model', 'gpt-4o', '--full-auto'];

        // Проверяем, что fallback runner получает request с fallback command
        $fallbackRunner->expects(self::once())->method('run')
            ->willReturnCallback(function (AgentRunRequestVo $request) use ($fallbackSuccessResult) {
                // Fallback runner получает fallback command, а не оригинальную
                self::assertSame(['codex', '--model', 'gpt-4o', '--full-auto'], $request->getCommand());

                return $fallbackSuccessResult;
            });

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn(AgentResultVo::createError(errorMessage: 'Fail'));

        $this->logger->method('warning');
        $this->logger->method('info');

        $runner = new CircuitBreakerAgentRunner(
            $this->innerRunner,
            $this->defaultState,
            $this->logger,
            $fallbackRunner,
            $fallbackCommand,
        );

        // Доводим до Open
        $runner->run($this->request);
        $runner->run($this->request);
        $runner->run($this->request);

        $result = $runner->run($this->request);

        self::assertFalse($result->isError());
        self::assertSame('Codex OK', $result->getOutputText());
    }

    #[Test]
    public function openStateFallbackRunnerFailsGracefully(): void
    {
        $fallbackRunner = $this->createMock(AgentRunnerInterface::class);
        $fallbackRunner->method('getName')->willReturn('codex');

        $fallbackErrorResult = AgentResultVo::createError(errorMessage: 'Codex also failed');
        $fallbackRunner->method('run')->willReturn($fallbackErrorResult);

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn(AgentResultVo::createError(errorMessage: 'Fail'));

        $this->logger->method('warning');
        $this->logger->method('error');

        $runner = new CircuitBreakerAgentRunner(
            $this->innerRunner,
            $this->defaultState,
            $this->logger,
            $fallbackRunner,
        );

        // Доводим до Open
        $runner->run($this->request);
        $runner->run($this->request);
        $runner->run($this->request);

        $result = $runner->run($this->request);

        self::assertTrue($result->isError());
        self::assertSame('Codex also failed', $result->getErrorMessage());
    }

    #[Test]
    public function openStateFallbackRunnerExceptionReturnsError(): void
    {
        $fallbackRunner = $this->createMock(AgentRunnerInterface::class);
        $fallbackRunner->method('getName')->willReturn('codex');
        $fallbackRunner->method('run')->willThrowException(new RuntimeException('Connection refused'));

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn(AgentResultVo::createError(errorMessage: 'Fail'));

        $this->logger->method('warning');
        $this->logger->method('error');

        $runner = new CircuitBreakerAgentRunner(
            $this->innerRunner,
            $this->defaultState,
            $this->logger,
            $fallbackRunner,
        );

        // Доводим до Open
        $runner->run($this->request);
        $runner->run($this->request);
        $runner->run($this->request);

        $result = $runner->run($this->request);

        self::assertTrue($result->isError());
        self::assertStringContainsString('Circuit breaker is open', $result->getErrorMessage());
        self::assertStringContainsString('codex', $result->getErrorMessage());
        self::assertStringContainsString('Connection refused', $result->getErrorMessage());
    }

    #[Test]
    public function openStateFallbackPreservesOtherRequestFields(): void
    {
        $fallbackRunner = $this->createMock(AgentRunnerInterface::class);
        $fallbackRunner->method('getName')->willReturn('codex');

        $fallbackSuccessResult = AgentResultVo::createSuccess(outputText: 'OK');
        $fallbackCommand = ['codex', '--model', 'gpt-4o'];

        $customRequest = new AgentRunRequestVo(
            role: 'analyst',
            task: 'review code',
            systemPrompt: 'You are a code reviewer',
            previousContext: 'previous data',
            model: 'claude-3',
            tools: 'tool1,tool2',
            workingDir: '/tmp/work',
            timeout: 120,
            maxContextLength: 30000,
            command: ['pi', '--mode', 'json'],
            runnerArgs: ['--verbose'],
            noContextFiles: true,
        );

        $fallbackRunner->expects(self::once())->method('run')
            ->willReturnCallback(function (AgentRunRequestVo $request) use ($fallbackSuccessResult) {
                // command заменена на fallback command
                self::assertSame(['codex', '--model', 'gpt-4o'], $request->getCommand());
                // Остальные поля сохранены
                self::assertSame('analyst', $request->getRole());
                self::assertSame('review code', $request->getTask());
                self::assertSame('You are a code reviewer', $request->getSystemPrompt());
                self::assertSame('previous data', $request->getPreviousContext());
                self::assertSame('claude-3', $request->getModel());
                self::assertSame('tool1,tool2', $request->getTools());
                self::assertSame('/tmp/work', $request->getWorkingDir());
                self::assertSame(120, $request->getTimeout());
                self::assertSame(30000, $request->getMaxContextLength());
                self::assertSame(['--verbose'], $request->getRunnerArgs());
                self::assertTrue($request->hasNoContextFiles());

                return $fallbackSuccessResult;
            });

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn(AgentResultVo::createError(errorMessage: 'Fail'));

        $this->logger->method('warning');
        $this->logger->method('info');

        $runner = new CircuitBreakerAgentRunner(
            $this->innerRunner,
            $this->defaultState,
            $this->logger,
            $fallbackRunner,
            $fallbackCommand,
        );

        // Доводим до Open
        $runner->run($this->request);
        $runner->run($this->request);
        $runner->run($this->request);

        $result = $runner->run($customRequest);

        self::assertFalse($result->isError());
    }

    #[Test]
    public function openStateFallbackWithoutCustomCommandUsesOriginalRequest(): void
    {
        $fallbackRunner = $this->createMock(AgentRunnerInterface::class);
        $fallbackRunner->method('getName')->willReturn('codex');

        $fallbackSuccessResult = AgentResultVo::createSuccess(outputText: 'OK');

        $originalRequest = new AgentRunRequestVo(
            role: 'test',
            task: 'do work',
            command: ['pi', '--mode', 'json'],
        );

        // fallbackCommand = [] → request передаётся без изменений
        $fallbackRunner->expects(self::once())->method('run')
            ->willReturnCallback(function (AgentRunRequestVo $request) use ($fallbackSuccessResult) {
                // command НЕ заменена (fallbackCommand пустая)
                self::assertSame(['pi', '--mode', 'json'], $request->getCommand());

                return $fallbackSuccessResult;
            });

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn(AgentResultVo::createError(errorMessage: 'Fail'));

        $this->logger->method('warning');
        $this->logger->method('info');

        // fallbackCommand не передана (default = [])
        $runner = new CircuitBreakerAgentRunner(
            $this->innerRunner,
            $this->defaultState,
            $this->logger,
            $fallbackRunner,
        );

        // Доводим до Open
        $runner->run($this->request);
        $runner->run($this->request);
        $runner->run($this->request);

        $runner->run($originalRequest);
    }

    #[Test]
    public function openStateFallbackLogsWarningAndSuccess(): void
    {
        $fallbackRunner = $this->createMock(AgentRunnerInterface::class);
        $fallbackRunner->method('getName')->willReturn('codex');
        $fallbackRunner->method('run')->willReturn(
            AgentResultVo::createSuccess(outputText: 'OK'),
        );

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn(AgentResultVo::createError(errorMessage: 'Fail'));

        // Ожидаем: 1 warning (Closed → Open) + 1 warning (delegating to fallback) + 1 info (fallback succeeded)
        $warningCalls = [];
        $infoCalls = [];

        $this->logger->method('warning')
            ->willReturnCallback(function (string $message) use (&$warningCalls) {
                $warningCalls[] = $message;
            });
        $this->logger->method('info')
            ->willReturnCallback(function (string $message) use (&$infoCalls) {
                $infoCalls[] = $message;
            });

        $runner = new CircuitBreakerAgentRunner(
            $this->innerRunner,
            $this->defaultState,
            $this->logger,
            $fallbackRunner,
        );

        // Доводим до Open
        $runner->run($this->request);
        $runner->run($this->request);
        $runner->run($this->request);

        $runner->run($this->request);

        // Проверяем лог-сообщения
        self::assertCount(2, $warningCalls);
        self::assertStringContainsString('Closed → Open', $warningCalls[0]);
        self::assertStringContainsString('delegating to fallback', $warningCalls[1]);
        self::assertStringContainsString('codex', $warningCalls[1]);

        self::assertCount(1, $infoCalls);
        self::assertStringContainsString('succeeded', $infoCalls[0]);
        self::assertStringContainsString('codex', $infoCalls[0]);
    }

    #[Test]
    public function openStateFallbackLogsErrorWhenFallbackFails(): void
    {
        $fallbackRunner = $this->createMock(AgentRunnerInterface::class);
        $fallbackRunner->method('getName')->willReturn('codex');
        $fallbackRunner->method('run')->willReturn(
            AgentResultVo::createError(errorMessage: 'Codex error'),
        );

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn(AgentResultVo::createError(errorMessage: 'Fail'));

        $errorCalls = [];
        $this->logger->method('warning');
        $this->logger->method('error')
            ->willReturnCallback(function (string $message) use (&$errorCalls) {
                $errorCalls[] = $message;
            });

        $runner = new CircuitBreakerAgentRunner(
            $this->innerRunner,
            $this->defaultState,
            $this->logger,
            $fallbackRunner,
        );

        // Доводим до Open
        $runner->run($this->request);
        $runner->run($this->request);
        $runner->run($this->request);

        $runner->run($this->request);

        self::assertCount(1, $errorCalls);
        self::assertStringContainsString('also failed', $errorCalls[0]);
        self::assertStringContainsString('codex', $errorCalls[0]);
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

        $successResult = AgentResultVo::createSuccess(outputText: 'Recovered');

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

        $errorResult = AgentResultVo::createError(errorMessage: 'Still broken');

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
        $successResult = AgentResultVo::createSuccess(outputText: 'OK');

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
        $errorResult = AgentResultVo::createError(errorMessage: 'Error');

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

        // 4: заблокирован (Open, нет fallback)
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

        $successResult = AgentResultVo::createSuccess(outputText: 'Recovered');
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

    // ─── Обратная совместимость: конструктор без fallback ──────────────────

    #[Test]
    public function constructorWithoutFallbackMaintainsBackwardCompatibility(): void
    {
        $errorResult = AgentResultVo::createError(errorMessage: 'Fail');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn($errorResult);

        $this->logger->method('warning');

        // Конструктор только с 3 обязательными параметрами (как раньше)
        $runner = new CircuitBreakerAgentRunner(
            $this->innerRunner,
            $this->defaultState,
            $this->logger,
        );

        // Доводим до Open
        $runner->run($this->request);
        $runner->run($this->request);
        $runner->run($this->request);

        // Open → ошибка как раньше (нет fallback)
        $result = $runner->run($this->request);

        self::assertTrue($result->isError());
        self::assertStringContainsString('Circuit breaker is open', $result->getErrorMessage());
    }

    // ─── Metrics integration ─────────────────────────────────────────────

    #[Test]
    public function recordsCbStateChangeWhenClosedToOpen(): void
    {
        $errorResult = AgentResultVo::createError(errorMessage: 'Fail');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn($errorResult);

        $this->logger->method('warning');

        $metrics = new InMemoryMetricsCollector();

        $runner = new CircuitBreakerAgentRunner(
            $this->innerRunner,
            $this->defaultState,
            $this->logger,
            metrics: $metrics,
        );

        // failureThreshold = 3
        $runner->run($this->request); // failure 1
        $runner->run($this->request); // failure 2
        $runner->run($this->request); // failure 3 → Open

        // state_change counter: Closed → Open recorded once
        $counters = $metrics->getCounters();
        self::assertArrayHasKey('cb.state_change', $counters);
        $stateChangeKey = 'from=closed,runner=pi,to=open';
        self::assertArrayHasKey($stateChangeKey, $counters['cb.state_change']);
        self::assertSame(1, $counters['cb.state_change'][$stateChangeKey]);
    }

    #[Test]
    public function recordsCbRejectionWhenCallBlocked(): void
    {
        $errorResult = AgentResultVo::createError(errorMessage: 'Fail');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn($errorResult);

        $this->logger->method('warning');

        $metrics = new InMemoryMetricsCollector();

        $runner = new CircuitBreakerAgentRunner(
            $this->innerRunner,
            $this->defaultState,
            $this->logger,
            metrics: $metrics,
        );

        // Доводим до Open
        $runner->run($this->request);
        $runner->run($this->request);
        $runner->run($this->request);

        // CB open — вызов блокируется
        $runner->run($this->request);

        // rejection counter recorded
        $counters = $metrics->getCounters();
        self::assertArrayHasKey('cb.rejection', $counters);
        self::assertSame(1, $counters['cb.rejection']['runner=pi']);
    }

    #[Test]
    public function recordsCbStateChangeWhenHalfOpenToOpen(): void
    {
        $pastTime = time() - 120;

        $openState = new CircuitBreakerStateVo(
            state: CircuitStateEnum::open,
            failureCount: 3,
            failureThreshold: 3,
            resetTimeoutSeconds: 60,
            lastFailureAt: $pastTime,
        );

        $errorResult = AgentResultVo::createError(errorMessage: 'Still broken');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn($errorResult);

        $this->logger->method('warning');

        $metrics = new InMemoryMetricsCollector();

        $runner = new CircuitBreakerAgentRunner(
            $this->innerRunner,
            $openState,
            $this->logger,
            metrics: $metrics,
        );

        $runner->run($this->request);

        // HalfOpen → Open state change recorded
        $counters = $metrics->getCounters();
        self::assertArrayHasKey('cb.state_change', $counters);
        $stateChangeKey = 'from=half_open,runner=pi,to=open';
        self::assertArrayHasKey($stateChangeKey, $counters['cb.state_change']);
        self::assertSame(1, $counters['cb.state_change'][$stateChangeKey]);
    }

    #[Test]
    public function recordsCbStateChangeWhenHalfOpenToClosed(): void
    {
        $pastTime = time() - 120;

        $openState = new CircuitBreakerStateVo(
            state: CircuitStateEnum::open,
            failureCount: 3,
            failureThreshold: 3,
            resetTimeoutSeconds: 60,
            lastFailureAt: $pastTime,
        );

        $successResult = AgentResultVo::createSuccess(outputText: 'OK');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn($successResult);

        $this->logger->method('info');

        $metrics = new InMemoryMetricsCollector();

        $runner = new CircuitBreakerAgentRunner(
            $this->innerRunner,
            $openState,
            $this->logger,
            metrics: $metrics,
        );

        $runner->run($this->request);

        // HalfOpen → Closed state change recorded
        $counters = $metrics->getCounters();
        self::assertArrayHasKey('cb.state_change', $counters);
        $stateChangeKey = 'from=half_open,runner=pi,to=closed';
        self::assertArrayHasKey($stateChangeKey, $counters['cb.state_change']);
        self::assertSame(1, $counters['cb.state_change'][$stateChangeKey]);
    }

    #[Test]
    public function recordsMultipleRejectionsWhenCbIsOpen(): void
    {
        $errorResult = AgentResultVo::createError(errorMessage: 'Fail');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn($errorResult);

        $this->logger->method('warning');

        $metrics = new InMemoryMetricsCollector();

        $runner = new CircuitBreakerAgentRunner(
            $this->innerRunner,
            $this->defaultState,
            $this->logger,
            metrics: $metrics,
        );

        // Доводим до Open
        $runner->run($this->request);
        $runner->run($this->request);
        $runner->run($this->request);

        // 3 rejection'а подряд
        $runner->run($this->request);
        $runner->run($this->request);
        $runner->run($this->request);

        $counters = $metrics->getCounters();
        self::assertSame(3, $counters['cb.rejection']['runner=pi']);
    }

    #[Test]
    public function worksssWithoutMetricsCollector(): void
    {
        $successResult = AgentResultVo::createSuccess(outputText: 'OK');

        $this->innerRunner->method('getName')->willReturn('pi');
        $this->innerRunner->method('run')->willReturn($successResult);

        // Metrics = null (default)
        $runner = new CircuitBreakerAgentRunner(
            $this->innerRunner,
            $this->defaultState,
            $this->logger,
        );

        $result = $runner->run($this->request);

        self::assertFalse($result->isError());
    }
}
