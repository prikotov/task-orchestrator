<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\AgentRunner\Infrastructure\Metrics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Metrics\InMemoryMetricsCollector;

#[CoversClass(InMemoryMetricsCollector::class)]
final class InMemoryMetricsCollectorTest extends TestCase
{
    private InMemoryMetricsCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new InMemoryMetricsCollector();
    }

    // ─── Counter ───────────────────────────────────────────────────────────

    #[Test]
    public function recordCounterIncrementsValue(): void
    {
        $this->collector->recordCounter('runner.attempt');
        $this->collector->recordCounter('runner.attempt');

        $counters = $this->collector->getCounters();

        self::assertArrayHasKey('runner.attempt', $counters);
        self::assertSame(2, $counters['runner.attempt']['']);
    }

    #[Test]
    public function recordCounterWithCustomValue(): void
    {
        $this->collector->recordCounter('tokens.total', 100);

        $counters = $this->collector->getCounters();

        self::assertSame(100, $counters['tokens.total']['']);
    }

    #[Test]
    public function recordCounterWithTagsGroupsByTags(): void
    {
        $this->collector->recordCounter('runner.attempt', 1, ['runner' => 'pi']);
        $this->collector->recordCounter('runner.attempt', 1, ['runner' => 'pi']);
        $this->collector->recordCounter('runner.attempt', 1, ['runner' => 'codex']);

        $counters = $this->collector->getCounters();

        self::assertSame(2, $counters['runner.attempt']['runner=pi']);
        self::assertSame(1, $counters['runner.attempt']['runner=codex']);
    }

    #[Test]
    public function recordCounterTagsOrderDoesNotMatter(): void
    {
        $this->collector->recordCounter('test.metric', 1, ['a' => '1', 'b' => '2']);
        $this->collector->recordCounter('test.metric', 1, ['b' => '2', 'a' => '1']);

        $counters = $this->collector->getCounters();

        // Оба вызова попали в один и тот же bucket
        self::assertSame(2, $counters['test.metric']['a=1,b=2']);
    }

    #[Test]
    public function getCounterTotalSumsAcrossAllTags(): void
    {
        $this->collector->recordCounter('runner.attempt', 3, ['runner' => 'pi']);
        $this->collector->recordCounter('runner.attempt', 2, ['runner' => 'codex']);

        self::assertSame(5, $this->collector->getCounterTotal('runner.attempt'));
    }

    #[Test]
    public function getCounterTotalReturnsZeroForUnknownMetric(): void
    {
        self::assertSame(0, $this->collector->getCounterTotal('unknown'));
    }

    #[Test]
    public function getCountersReturnsEmptyArrayWhenNoMetricsRecorded(): void
    {
        self::assertSame([], $this->collector->getCounters());
    }

    // ─── Gauge ─────────────────────────────────────────────────────────────

    #[Test]
    public function recordGaugeSetsValue(): void
    {
        $this->collector->recordGauge('cb.failure_count', 3.0, ['runner' => 'pi']);

        $gauges = $this->collector->getGauges();

        self::assertSame(3.0, $gauges['cb.failure_count']['runner=pi']);
    }

    #[Test]
    public function recordGaugeOverwritesPreviousValue(): void
    {
        $this->collector->recordGauge('memory.usage', 100.0);
        $this->collector->recordGauge('memory.usage', 200.0);

        $gauges = $this->collector->getGauges();

        self::assertSame(200.0, $gauges['memory.usage']['']);
    }

    #[Test]
    public function recordGaugeWithDifferentTagsAreIndependent(): void
    {
        $this->collector->recordGauge('cb.failure_count', 3.0, ['runner' => 'pi']);
        $this->collector->recordGauge('cb.failure_count', 1.0, ['runner' => 'codex']);

        $gauges = $this->collector->getGauges();

        self::assertSame(3.0, $gauges['cb.failure_count']['runner=pi']);
        self::assertSame(1.0, $gauges['cb.failure_count']['runner=codex']);
    }

    #[Test]
    public function getGaugesReturnsEmptyArrayWhenNoMetricsRecorded(): void
    {
        self::assertSame([], $this->collector->getGauges());
    }

    // ─── Timing ────────────────────────────────────────────────────────────

    #[Test]
    public function recordTimingStoresValue(): void
    {
        $this->collector->recordTiming('runner.duration', 1.5);

        $timings = $this->collector->getTimings();

        self::assertArrayHasKey('runner.duration', $timings);
        self::assertSame([1.5], $timings['runner.duration']['']);
    }

    #[Test]
    public function recordTimingAccumulatesMultipleValues(): void
    {
        $this->collector->recordTiming('runner.duration', 1.0);
        $this->collector->recordTiming('runner.duration', 2.0);
        $this->collector->recordTiming('runner.duration', 3.0);

        $timings = $this->collector->getTimings();

        self::assertSame([1.0, 2.0, 3.0], $timings['runner.duration']['']);
    }

    #[Test]
    public function recordTimingWithTagsGroupsByTags(): void
    {
        $this->collector->recordTiming('runner.duration', 1.0, ['runner' => 'pi']);
        $this->collector->recordTiming('runner.duration', 2.0, ['runner' => 'codex']);

        $timings = $this->collector->getTimings();

        self::assertSame([1.0], $timings['runner.duration']['runner=pi']);
        self::assertSame([2.0], $timings['runner.duration']['runner=codex']);
    }

    #[Test]
    public function getTimingsReturnsEmptyArrayWhenNoMetricsRecorded(): void
    {
        self::assertSame([], $this->collector->getTimings());
    }

    #[Test]
    public function getAverageTimingReturnsCorrectAverage(): void
    {
        $this->collector->recordTiming('runner.duration', 1.0);
        $this->collector->recordTiming('runner.duration', 2.0);
        $this->collector->recordTiming('runner.duration', 3.0);

        self::assertSame(2.0, $this->collector->getAverageTiming('runner.duration'));
    }

    #[Test]
    public function getAverageTimingReturnsNullWhenNoTimings(): void
    {
        self::assertNull($this->collector->getAverageTiming('unknown'));
    }

    #[Test]
    public function getAverageTimingAveragesAcrossAllTags(): void
    {
        $this->collector->recordTiming('runner.duration', 2.0, ['runner' => 'pi']);
        $this->collector->recordTiming('runner.duration', 4.0, ['runner' => 'codex']);

        // (2 + 4) / 2 = 3.0
        self::assertSame(3.0, $this->collector->getAverageTiming('runner.duration'));
    }

    // ─── Изоляция типов ────────────────────────────────────────────────────

    #[Test]
    public function countersAndTimingsAreIndependent(): void
    {
        $this->collector->recordCounter('test', 1);
        $this->collector->recordTiming('test', 1.0);

        $counters = $this->collector->getCounters();
        $timings = $this->collector->getTimings();

        self::assertArrayHasKey('test', $counters);
        self::assertArrayHasKey('test', $timings);
        self::assertSame(1, $counters['test']['']);
        self::assertSame([1.0], $timings['test']['']);
    }

    #[Test]
    public function sameMetricNameWithDifferentTypesDoNotConflict(): void
    {
        $this->collector->recordCounter('metric', 5);
        $this->collector->recordGauge('metric', 3.14);
        $this->collector->recordTiming('metric', 2.5);

        self::assertSame(5, $this->collector->getCounters()['metric']['']);
        self::assertSame(3.14, $this->collector->getGauges()['metric']['']);
        self::assertSame([2.5], $this->collector->getTimings()['metric']['']);
    }
}
