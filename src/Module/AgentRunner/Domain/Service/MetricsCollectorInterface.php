<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Domain\Service;

/**
 * Интерфейс сбора метрик выполнения AI-агентов.
 *
 * Агностичный к конкретной реализации (in-memory, Prometheus, DataDog).
 * Используется в decorator'ах AgentRunner для записи observability-данных:
 * счётчики попыток, длительности, переходы состояний circuit breaker.
 *
 * Методы записи (record*) не выбрасывают исключений — metrics не должны
 * влиять на основной поток выполнения.
 */
interface MetricsCollectorInterface
{
    /**
     * Записывает счётчик (counter) — монотонно возрастающая величина.
     *
     * @param string $metric название метрики (например, 'runner.attempt')
     * @param int $value приращение (default = 1)
     * @param array<string, string> $tags теги для фильтрации (например, ['runner' => 'pi', 'result' => 'error'])
     */
    public function recordCounter(string $metric, int $value = 1, array $tags = []): void;

    /**
     * Записывает gauge — текущее значение, которое может расти и падать.
     *
     * @param string $metric название метрики (например, 'cb.failure_count')
     * @param float $value текущее значение
     * @param array<string, string> $tags теги для фильтрации
     */
    public function recordGauge(string $metric, float $value, array $tags = []): void;

    /**
     * Записывает timing — длительность операции в секундах.
     *
     * @param string $metric название метрики (например, 'runner.duration')
     * @param float $seconds длительность в секундах
     * @param array<string, string> $tags теги для фильтрации
     */
    public function recordTiming(string $metric, float $seconds, array $tags = []): void;

    /**
     * Возвращает все записанные счётчики.
     *
     * Структура: metric → [tags_hash => count]
     * Для тестов и отладки.
     *
     * @return array<string, array<string, int>>
     */
    public function getCounters(): array;

    /**
     * Возвращает все записанные timing'и.
     *
     * Структура: metric → [tags_hash => [values]]
     * Для тестов и отладки.
     *
     * @return array<string, array<string, list<float>>>
     */
    public function getTimings(): array;

    /**
     * Возвращает все записанные gauge'и.
     *
     * Структура: metric → [tags_hash => value]
     * Для тестов и отладки.
     *
     * @return array<string, array<string, float>>
     */
    public function getGauges(): array;
}
