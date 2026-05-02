<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Metrics;

use Override;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\MetricsCollectorInterface;

/**
 * In-memory реализация MetricsCollectorInterface.
 *
 * Хранит все метрики в массивах. Подходит для MVP и тестирования.
 * Не предназначена для production с persistent storage —
 * при перезапуске процесса данные теряются.
 */
final class InMemoryMetricsCollector implements MetricsCollectorInterface
{
    /** @var array<string, array<string, int>> metric → [tagsKey → count] */
    private array $counters = [];

    /** @var array<string, array<string, float>> metric → [tagsKey → value] */
    private array $gauges = [];

    /** @var array<string, array<string, list<float>>> metric → [tagsKey → values[]] */
    private array $timings = [];

    #[Override]
    public function recordCounter(string $metric, int $value = 1, array $tags = []): void
    {
        $tagsKey = $this->tagsKey($tags);
        $current = $this->counters[$metric][$tagsKey] ?? 0;
        $this->counters[$metric][$tagsKey] = $current + $value;
    }

    #[Override]
    public function recordGauge(string $metric, float $value, array $tags = []): void
    {
        $tagsKey = $this->tagsKey($tags);
        $this->gauges[$metric][$tagsKey] = $value;
    }

    #[Override]
    public function recordTiming(string $metric, float $seconds, array $tags = []): void
    {
        $tagsKey = $this->tagsKey($tags);
        $this->timings[$metric][$tagsKey][] = $seconds;
    }

    #[Override]
    public function getCounters(): array
    {
        return $this->counters;
    }

    #[Override]
    public function getTimings(): array
    {
        return $this->timings;
    }

    #[Override]
    public function getGauges(): array
    {
        return $this->gauges;
    }

    /**
     * Возвращает общую сумму счётчика по всем тегам.
     */
    public function getCounterTotal(string $metric): int
    {
        $entries = $this->counters[$metric] ?? [];

        return array_sum($entries);
    }

    /**
     * Возвращает среднее значение timing по всем тегам.
     *
     * @return float|null null если timing'ов нет
     */
    public function getAverageTiming(string $metric): ?float
    {
        $entries = $this->timings[$metric] ?? [];

        if ($entries === []) {
            return null;
        }

        $allValues = array_merge(...array_values($entries));

        if ($allValues === []) {
            return null;
        }

        $sum = array_sum($allValues);
        $count = (float) count($allValues);

        return $sum / $count;
    }

    /**
     * Формирует ключ тегов для группировки.
     *
     * @param array<string, string> $tags
     */
    private function tagsKey(array $tags): string
    {
        if ($tags === []) {
            return '';
        }

        ksort($tags);

        $parts = [];
        foreach ($tags as $k => $v) {
            $parts[] = $k . '=' . $v;
        }

        return implode(',', $parts);
    }
}
