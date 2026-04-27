<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Service\Chain\Dynamic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic\RunDynamicLoopService;

#[CoversClass(RunDynamicLoopService::class)]
final class RunDynamicLoopServiceFinalizeReserveTest extends TestCase
{
    // ─── calculateFinalizeReserve ─────────────────────────────────────

    #[Test]
    public function calculateFinalizeReserveReturnsMin60ForShortMaxTime(): void
    {
        // maxTime=100 → 10% = 10, но min = 60
        $reserve = RunDynamicLoopService::calculateFinalizeReserve(100);

        self::assertSame(60, $reserve);
    }

    #[Test]
    public function calculateFinalizeReserveReturns10PercentForLongMaxTime(): void
    {
        // maxTime=3600 → 10% = 360 > 60
        $reserve = RunDynamicLoopService::calculateFinalizeReserve(3600);

        self::assertSame(360, $reserve);
    }

    #[Test]
    public function calculateFinalizeReserveBoundaryAt600(): void
    {
        // maxTime=600 → 10% = 60, равно min
        $reserve = RunDynamicLoopService::calculateFinalizeReserve(600);

        self::assertSame(60, $reserve);
    }

    #[Test]
    public function calculateFinalizeReserveBoundaryAt601(): void
    {
        // maxTime=601 → 10% = 60.1 → round = 60, min = 60
        $reserve = RunDynamicLoopService::calculateFinalizeReserve(601);

        self::assertSame(60, $reserve);
    }

    #[Test]
    public function calculateFinalizeReserveReturns10PercentForVeryLongMaxTime(): void
    {
        // maxTime=7200 → 10% = 720
        $reserve = RunDynamicLoopService::calculateFinalizeReserve(7200);

        self::assertSame(720, $reserve);
    }

    #[Test]
    public function calculateFinalizeReserveReturnsMin60ForOneSecond(): void
    {
        // Крайний случай: maxTime=1
        $reserve = RunDynamicLoopService::calculateFinalizeReserve(1);

        self::assertSame(60, $reserve);
    }

    /**
     * @param positive-int $maxTime
     */
    #[Test]
    #[DataProvider('maxTimeProvider')]
    public function calculateFinalizeReserveAlwaysAtLeast60(int $maxTime): void
    {
        $reserve = RunDynamicLoopService::calculateFinalizeReserve($maxTime);

        self::assertGreaterThanOrEqual(60, $reserve);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function maxTimeProvider(): iterable
    {
        yield '1 second' => [1];
        yield '10 seconds' => [10];
        yield '59 seconds' => [59];
        yield '60 seconds' => [60];
        yield '100 seconds' => [100];
        yield '600 seconds' => [600];
        yield '3600 seconds' => [3600];
        yield '7200 seconds' => [7200];
    }

    /**
     * Для maxTime >= 60 резерв не превышает maxTime.
     * При maxTime < 60 резерв (60) будет больше maxTime — это ожидаемо,
     * т.к. задача гарантирует минимум 60 секунд на finalize.
     *
     * @param positive-int $maxTime
     */
    #[Test]
    #[DataProvider('realisticMaxTimeProvider')]
    public function calculateFinalizeReserveNeverExceedsMaxTime(int $maxTime): void
    {
        $reserve = RunDynamicLoopService::calculateFinalizeReserve($maxTime);

        self::assertLessThanOrEqual($maxTime, $reserve);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function realisticMaxTimeProvider(): iterable
    {
        yield '60 seconds' => [60];
        yield '100 seconds' => [100];
        yield '300 seconds' => [300];
        yield '600 seconds' => [600];
        yield '1800 seconds' => [1800];
        yield '3600 seconds' => [3600];
        yield '7200 seconds' => [7200];
    }

    #[Test]
    public function calculateFinalizeReserveIsExactly10PercentForLargeValues(): void
    {
        // maxTime=3600 → 360
        self::assertSame(360, RunDynamicLoopService::calculateFinalizeReserve(3600));
        // maxTime=1800 → 180
        self::assertSame(180, RunDynamicLoopService::calculateFinalizeReserve(1800));
        // maxTime=5400 → 540
        self::assertSame(540, RunDynamicLoopService::calculateFinalizeReserve(5400));
    }

    // ─── shouldReserveForFinalize backward compatibility ────────────

    /**
     * При maxTime=null shouldReserveForFinalize должен возвращать false,
     * чтобы цикл продолжался без резервирования — backward compatible.
     */
    #[Test]
    public function shouldReserveForFinalizeReturnsFalseWhenMaxTimeIsNull(): void
    {
        $method = new ReflectionMethod(RunDynamicLoopService::class, 'shouldReserveForFinalize');
        $method->setAccessible(true);

        $service = (new \ReflectionClass(RunDynamicLoopService::class))
            ->newInstanceWithoutConstructor();

        $startTime = microtime(true) - 1000.0; // много времени прошло

        $result = $method->invoke($service, null, $startTime);

        self::assertFalse($result, 'shouldReserveForFinalize must return false when maxTime is null (backward compatible)');
    }
}
