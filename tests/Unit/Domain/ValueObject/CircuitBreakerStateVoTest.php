<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\Enum\CircuitStateEnum;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\CircuitBreakerStateVo;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CircuitBreakerStateVo::class)]
final class CircuitBreakerStateVoTest extends TestCase
{
    // ─── Конструктор и defaults ────────────────────────────────────────────

    #[Test]
    public function defaultStateIsClosed(): void
    {
        $vo = new CircuitBreakerStateVo();

        self::assertSame(CircuitStateEnum::closed, $vo->getState());
        self::assertSame(0, $vo->getFailureCount());
        self::assertSame(5, $vo->getFailureThreshold());
        self::assertSame(60, $vo->getResetTimeoutSeconds());
        self::assertNull($vo->getLastFailureAt());
    }

    #[Test]
    public function throwsOnInvalidFailureThreshold(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('failureThreshold must be >= 1');

        new CircuitBreakerStateVo(failureThreshold: 0);
    }

    #[Test]
    public function throwsOnInvalidResetTimeout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('resetTimeoutSeconds must be >= 1');

        new CircuitBreakerStateVo(resetTimeoutSeconds: 0);
    }

    #[Test]
    public function throwsOnNegativeFailureCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('failureCount must be >= 0');

        new CircuitBreakerStateVo(failureCount: -1);
    }

    // ─── recordFailure: Closed → Open ─────────────────────────────────────

    #[Test]
    public function recordFailureIncrementsCountInClosedState(): void
    {
        $vo = new CircuitBreakerStateVo(failureThreshold: 5);

        $newVo = $vo->recordFailure();

        self::assertSame(CircuitStateEnum::closed, $newVo->getState());
        self::assertSame(1, $newVo->getFailureCount());
        self::assertNotNull($newVo->getLastFailureAt());
    }

    #[Test]
    public function recordFailureTransitionsToOpenWhenThresholdReached(): void
    {
        $vo = new CircuitBreakerStateVo(
            state: CircuitStateEnum::closed,
            failureCount: 4,
            failureThreshold: 5,
            resetTimeoutSeconds: 60,
        );

        $newVo = $vo->recordFailure();

        self::assertSame(CircuitStateEnum::open, $newVo->getState());
        self::assertSame(5, $newVo->getFailureCount());
        self::assertNotNull($newVo->getLastFailureAt());
    }

    #[Test]
    public function recordFailureStaysClosedBelowThreshold(): void
    {
        $vo = new CircuitBreakerStateVo(
            state: CircuitStateEnum::closed,
            failureCount: 3,
            failureThreshold: 5,
            resetTimeoutSeconds: 60,
        );

        $newVo = $vo->recordFailure();

        self::assertSame(CircuitStateEnum::closed, $newVo->getState());
        self::assertSame(4, $newVo->getFailureCount());
    }

    // ─── recordFailure: Open state ─────────────────────────────────────────

    #[Test]
    public function recordFailureInOpenStateDoesNotChangeState(): void
    {
        $vo = new CircuitBreakerStateVo(
            state: CircuitStateEnum::open,
            failureCount: 5,
            failureThreshold: 5,
            resetTimeoutSeconds: 60,
            lastFailureAt: time(),
        );

        $newVo = $vo->recordFailure();

        // В Open состоянии recordFailure не меняет состояние
        self::assertSame(CircuitStateEnum::open, $newVo->getState());
        self::assertSame(5, $newVo->getFailureCount());
    }

    // ─── recordFailure: HalfOpen → Open ────────────────────────────────────

    #[Test]
    public function recordFailureInHalfOpenTransitionsToOpen(): void
    {
        // HalfOpen — это формально Open с прошедшим resetTimeout,
        // но recordFailure должен корректно обрабатывать это
        $pastTime = time() - 120; // resetTimeout=60, значит прошло 120 секунд → HalfOpen
        $vo = new CircuitBreakerStateVo(
            state: CircuitStateEnum::open,
            failureCount: 5,
            failureThreshold: 5,
            resetTimeoutSeconds: 60,
            lastFailureAt: $pastTime,
        );

        self::assertTrue($vo->isHalfOpen());

        $newVo = $vo->recordFailure();

        self::assertSame(CircuitStateEnum::open, $newVo->getState());
        self::assertSame(6, $newVo->getFailureCount());
        // lastFailureAt обновился
        self::assertNotNull($newVo->getLastFailureAt());
    }

    // ─── recordSuccess ─────────────────────────────────────────────────────

    #[Test]
    public function recordSuccessInClosedResetsFailures(): void
    {
        $vo = new CircuitBreakerStateVo(
            state: CircuitStateEnum::closed,
            failureCount: 3,
            failureThreshold: 5,
            resetTimeoutSeconds: 60,
            lastFailureAt: time(),
        );

        $newVo = $vo->recordSuccess();

        self::assertSame(CircuitStateEnum::closed, $newVo->getState());
        self::assertSame(0, $newVo->getFailureCount());
        self::assertNull($newVo->getLastFailureAt());
    }

    #[Test]
    public function recordSuccessInHalfOpenTransitionsToClosed(): void
    {
        $pastTime = time() - 120;
        $vo = new CircuitBreakerStateVo(
            state: CircuitStateEnum::open,
            failureCount: 5,
            failureThreshold: 5,
            resetTimeoutSeconds: 60,
            lastFailureAt: $pastTime,
        );

        self::assertTrue($vo->isHalfOpen());

        $newVo = $vo->recordSuccess();

        self::assertSame(CircuitStateEnum::closed, $newVo->getState());
        self::assertSame(0, $newVo->getFailureCount());
        self::assertNull($newVo->getLastFailureAt());
    }

    #[Test]
    public function recordSuccessInOpenStateDoesNotChangeState(): void
    {
        $vo = new CircuitBreakerStateVo(
            state: CircuitStateEnum::open,
            failureCount: 5,
            failureThreshold: 5,
            resetTimeoutSeconds: 60,
            lastFailureAt: time(),
        );

        $newVo = $vo->recordSuccess();

        self::assertSame(CircuitStateEnum::open, $newVo->getState());
        self::assertSame(5, $newVo->getFailureCount());
    }

    // ─── isOpen / isHalfOpen ───────────────────────────────────────────────

    #[Test]
    public function isOpenReturnsFalseInClosedState(): void
    {
        $vo = new CircuitBreakerStateVo(state: CircuitStateEnum::closed);

        self::assertFalse($vo->isOpen());
    }

    #[Test]
    public function isOpenReturnsTrueWhenOpenAndTimeoutNotElapsed(): void
    {
        $vo = new CircuitBreakerStateVo(
            state: CircuitStateEnum::open,
            failureCount: 5,
            failureThreshold: 5,
            resetTimeoutSeconds: 60,
            lastFailureAt: time(),
        );

        self::assertTrue($vo->isOpen());
        self::assertFalse($vo->isHalfOpen());
    }

    #[Test]
    public function isOpenReturnsFalseWhenTimeoutElapsed(): void
    {
        $pastTime = time() - 120;
        $vo = new CircuitBreakerStateVo(
            state: CircuitStateEnum::open,
            failureCount: 5,
            failureThreshold: 5,
            resetTimeoutSeconds: 60,
            lastFailureAt: $pastTime,
        );

        self::assertFalse($vo->isOpen());
        self::assertTrue($vo->isHalfOpen());
    }

    #[Test]
    public function isHalfOpenReturnsFalseInClosedState(): void
    {
        $vo = new CircuitBreakerStateVo(state: CircuitStateEnum::closed);

        self::assertFalse($vo->isHalfOpen());
    }

    #[Test]
    public function isHalfOpenReturnsFalseInOpenWithoutTimeoutElapsed(): void
    {
        $vo = new CircuitBreakerStateVo(
            state: CircuitStateEnum::open,
            failureCount: 5,
            failureThreshold: 5,
            resetTimeoutSeconds: 60,
            lastFailureAt: time(),
        );

        self::assertFalse($vo->isHalfOpen());
    }

    // ─── getEffectiveState ─────────────────────────────────────────────────

    #[Test]
    public function getEffectiveStateReturnsClosed(): void
    {
        $vo = new CircuitBreakerStateVo(state: CircuitStateEnum::closed);

        self::assertSame(CircuitStateEnum::closed, $vo->getEffectiveState());
    }

    #[Test]
    public function getEffectiveStateReturnsOpenWhenTimeoutNotElapsed(): void
    {
        $vo = new CircuitBreakerStateVo(
            state: CircuitStateEnum::open,
            failureCount: 5,
            failureThreshold: 5,
            resetTimeoutSeconds: 60,
            lastFailureAt: time(),
        );

        self::assertSame(CircuitStateEnum::open, $vo->getEffectiveState());
    }

    #[Test]
    public function getEffectiveStateReturnsHalfOpenWhenTimeoutElapsed(): void
    {
        $pastTime = time() - 120;
        $vo = new CircuitBreakerStateVo(
            state: CircuitStateEnum::open,
            failureCount: 5,
            failureThreshold: 5,
            resetTimeoutSeconds: 60,
            lastFailureAt: $pastTime,
        );

        self::assertSame(CircuitStateEnum::halfOpen, $vo->getEffectiveState());
    }

    // ─── Полный цикл: Closed → Open → HalfOpen → Closed ───────────────────

    #[Test]
    public function fullCycleClosedOpenHalfOpenClosed(): void
    {
        $pastTime = time() - 120;

        // Step 1: Closed
        $vo = new CircuitBreakerStateVo(failureThreshold: 3, resetTimeoutSeconds: 60);
        self::assertSame(CircuitStateEnum::closed, $vo->getEffectiveState());

        // Step 2: Closed → после N failures → Open
        $vo = $vo->recordFailure(); // 1
        self::assertSame(CircuitStateEnum::closed, $vo->getState());
        $vo = $vo->recordFailure(); // 2
        self::assertSame(CircuitStateEnum::closed, $vo->getState());
        $vo = $vo->recordFailure(); // 3 → Open
        self::assertSame(CircuitStateEnum::open, $vo->getState());
        self::assertTrue($vo->isOpen());

        // Step 3: Симулируем прошествие resetTimeout → HalfOpen
        // Создаём VO вручную с прошлым lastFailureAt (имитация прошествия времени)
        $vo = new CircuitBreakerStateVo(
            state: CircuitStateEnum::open,
            failureCount: 3,
            failureThreshold: 3,
            resetTimeoutSeconds: 60,
            lastFailureAt: $pastTime,
        );
        self::assertTrue($vo->isHalfOpen());
        self::assertSame(CircuitStateEnum::halfOpen, $vo->getEffectiveState());

        // Step 4: HalfOpen → success → Closed
        $vo = $vo->recordSuccess();
        self::assertSame(CircuitStateEnum::closed, $vo->getState());
        self::assertSame(0, $vo->getFailureCount());
    }

    // ─── Полный цикл: Closed → Open → HalfOpen → Open ─────────────────────

    #[Test]
    public function halfOpenToOpenOnFailure(): void
    {
        $pastTime = time() - 120;

        $vo = new CircuitBreakerStateVo(
            state: CircuitStateEnum::open,
            failureCount: 5,
            failureThreshold: 5,
            resetTimeoutSeconds: 60,
            lastFailureAt: $pastTime,
        );

        self::assertTrue($vo->isHalfOpen());

        $vo = $vo->recordFailure();

        self::assertSame(CircuitStateEnum::open, $vo->getState());
        self::assertSame(6, $vo->getFailureCount());
        // Новый lastFailureAt — timeout снова не прошел
        self::assertFalse($vo->isHalfOpen());
        self::assertTrue($vo->isOpen());
    }

    // ─── fromArray ─────────────────────────────────────────────────────────

    #[Test]
    public function fromArrayWithDefaults(): void
    {
        $vo = CircuitBreakerStateVo::fromArray([]);

        self::assertSame(5, $vo->getFailureThreshold());
        self::assertSame(60, $vo->getResetTimeoutSeconds());
        self::assertSame(CircuitStateEnum::closed, $vo->getState());
    }

    #[Test]
    public function fromArrayWithCustomValues(): void
    {
        $vo = CircuitBreakerStateVo::fromArray([
            'failure_threshold' => 10,
            'reset_timeout_seconds' => 120,
        ]);

        self::assertSame(10, $vo->getFailureThreshold());
        self::assertSame(120, $vo->getResetTimeoutSeconds());
    }

    // ─── toLogString ───────────────────────────────────────────────────────

    #[Test]
    public function toLogStringContainsStateInfo(): void
    {
        $vo = new CircuitBreakerStateVo(
            state: CircuitStateEnum::closed,
            failureCount: 2,
            failureThreshold: 5,
            resetTimeoutSeconds: 60,
        );

        $logString = $vo->toLogString();

        self::assertStringContainsString('closed', $logString);
        self::assertStringContainsString('2/5', $logString);
        self::assertStringContainsString('60s', $logString);
    }

    // ─── Immutability ─────────────────────────────────────────────────────

    #[Test]
    public function recordFailureReturnsNewInstance(): void
    {
        $original = new CircuitBreakerStateVo(failureThreshold: 5);

        $modified = $original->recordFailure();

        self::assertNotSame($original, $modified);
        self::assertSame(0, $original->getFailureCount());
        self::assertSame(1, $modified->getFailureCount());
    }

    #[Test]
    public function recordSuccessReturnsNewInstance(): void
    {
        $original = new CircuitBreakerStateVo(
            failureCount: 3,
            failureThreshold: 5,
        );

        $modified = $original->recordSuccess();

        self::assertNotSame($original, $modified);
        self::assertSame(3, $original->getFailureCount());
        self::assertSame(0, $modified->getFailureCount());
    }
}
