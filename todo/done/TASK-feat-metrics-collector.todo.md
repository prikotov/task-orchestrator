---
type: feat
created: 2026-05-02
value: V2
complexity: C2
priority: P1
depends_on:
epic: EPIC-sprint-9-resilience-observability
author: system_analyst_sherlock (Шерлок)
assignee:
branch:
pr:
status: todo
---

# TASK-feat-metrics-collector: MetricsCollectorInterface + in-memory реализация

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда [`AuditLoggerInterface`](../../src/Module/Orchestrator/Domain/Service/Chain/Audit/AuditLoggerInterface.php) пишет в JSONL-файл, но нет способа агрегировать метрики — какая цепочка дольше, какая роль дороже, какой runner чаще падает — я хочу добавить `MetricsCollectorInterface` в Domain с in-memory реализацией, чтобы заложить observability-фундамент и дать команде данные для приоритизации оптимизаций.

### Goal (Цель по SMART)
Создать [`Interface`](../../docs/conventions/core_patterns/external-service.md) `MetricsCollectorInterface` в Domain (AgentRunner или Orchestrator) с методами для записи и чтения метрик. In-memory реализация в Infrastructure. Интеграция в decorator'ы ([`RetryingAgentRunner`](../../src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunner.php), [`CircuitBreakerAgentRunner`](../../src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunner.php)). Foundation для hooks system в Sprint 10. Срок: 1 день.

## 2. Context and Scope (Контекст и Границы)
### Где делаем
- `src/Module/AgentRunner/Domain/Service/MetricsCollectorInterface.php` — новый [`Interface`](../../docs/conventions/core_patterns/external-service.md) (агностичный к Orchestrator)
- `src/Module/AgentRunner/Domain/ValueObject/MetricVo.php` — новый [`Value Object`](../../docs/conventions/core_patterns/value-object.md) для единичной метрики
- `src/Module/AgentRunner/Infrastructure/Metrics/InMemoryMetricsCollector.php` — новый [`Service`](../../docs/conventions/core_patterns/service.md)
- [`src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunner.php`](../../src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunner.php) — интеграция (record attempt, latency, result)
- [`src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunner.php`](../../src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunner.php) — интеграция (record CB state transitions)
- DI-конфигурация

### Текущее поведение
- [`AuditLoggerInterface`](../../src/Module/Orchestrator/Domain/Service/Chain/Audit/AuditLoggerInterface.php) пишет в JSONL — есть granular log, нет агрегации
- Нет способа ответить на вопросы: какая цепочка дольше всего? какой runner чаще падает? какая роль тратит больше токенов?
- Observability gap — мы не знаем, какая боль самая острая, потому что не измеряем

### Границы (Out of Scope)
- Persistent storage (in-memory достаточно для MVP; persistent — Q4 при необходимости)
- Dashboard/UI для метрик
- Histogram/percentile-агрегации (только counters и gauges)
- Токен-метрики (token counting пока не реализован в AgentResultVo)
- Интеграция в ExecutionStrategy (только decorator'ы AgentRunner)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `MetricsCollectorInterface` в `src/Module/AgentRunner/Domain/Service/` с методами:
  - `recordCounter(string $metric, int $value = 1, array $tags = []): void` — счётчик
  - `recordGauge(string $metric, float $value, array $tags = []): void` — gauge
  - `recordTiming(string $metric, float $seconds, array $tags = []): void` — timing (duration)
  - `getCounters(): array` — для тестов и чтения
  - `getTimings(): array` — для тестов и чтения
- [ ] `InMemoryMetricsCollector` в `src/Module/AgentRunner/Infrastructure/Metrics/`
- [ ] `MetricsCollectorInterface` внедряется как optional зависимость (nullable) в decorator'ы AgentRunner
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные

### 🟡 Should Have (Желательно)
- [ ] Интеграция в [`RetryingAgentRunner`](../../src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunner.php): record `runner.attempt` (counter), `runner.duration` (timing)
- [ ] Интеграция в [`CircuitBreakerAgentRunner`](../../src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunner.php): record `cb.state_change` (counter), `cb.rejection` (counter)
- [ ] Unit-тесты ≥80% покрытия нового кода

### 🟢 Could Have (Опционально)
- [ ] Простые агрегации: `getAverageTiming(string $metric): ?float`, `getCounterTotal(string $metric): int`
- [ ] Tags filtration: `getCounters(string $metric, array $tags): array`

### ⚫ Won't Have (Не будем делать)
- [ ] Persistent storage (Redis, DB)
- [ ] Export в Prometheus/DataDog
- [ ] Histogram/percentile
- [ ] Token-метрики
- [ ] Integration в Orchestrator ExecutionStrategy (только AgentRunner decorator'ы)

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем (Левша) перед стартом.*

1. [ ] Создать `MetricsCollectorInterface` в `src/Module/AgentRunner/Domain/Service/`
2. [ ] Создать `InMemoryMetricsCollector` в `src/Module/AgentRunner/Infrastructure/Metrics/`
3. [ ] Добавить `?MetricsCollectorInterface $metrics` (nullable) в [`RetryingAgentRunner`](../../src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunner.php) — record attempt counter + duration timing
4. [ ] Добавить `?MetricsCollectorInterface $metrics` (nullable) в [`CircuitBreakerAgentRunner`](../../src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunner.php) — record CB state change counter
5. [ ] Обновить DI-конфигурацию: wire `InMemoryMetricsCollector`
6. [ ] Unit-тесты: `InMemoryMetricsCollectorTest`, обновить `RetryingAgentRunnerTest`, `CircuitBreakerAgentRunnerTest`
7. [ ] Проверить Psalm и Deptrac

### Структура файлов
```
src/Module/AgentRunner/Domain/Service/MetricsCollectorInterface.php                  — новый
src/Module/AgentRunner/Infrastructure/Metrics/InMemoryMetricsCollector.php            — новый
src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunner.php                 — изменить
src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunner.php           — изменить
config/services.yaml                                                                  — обновить
tests/Unit/Module/AgentRunner/Infrastructure/Metrics/InMemoryMetricsCollectorTest.php — новый
tests/Unit/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunnerTest.php      — обновить
tests/Unit/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunnerTest.php — обновить
```

## 5. Definition of Done (Критерии приёмки)
- [ ] `MetricsCollectorInterface` в Domain AgentRunner с методами: recordCounter, recordGauge, recordTiming, getCounters, getTimings
- [ ] `InMemoryMetricsCollector` реализует интерфейс, хранит данные in-memory
- [ ] Decorator'ы AgentRunner record метрики (если MetricsCollector внедрён)
- [ ] Nullable внедрение — обратная совместимость: без MetricsCollector decorator'ы работают как раньше
- [ ] Unit-тесты покрывают InMemoryMetricsCollector + интеграцию в decorator'ы
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные
- [ ] Deptrac green

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Module/AgentRunner/
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Domain placement:** `MetricsCollectorInterface` в AgentRunner Domain — агностичен к Orchestrator. Альтернатива: Orchestrator Domain — но тогда AgentRunner зависит от Orchestrator (нарушение). AgentRunner — правильный слой: metrics о runner'ах, не о цепочках.
- **Optional dependency:** nullable внедрение через конструктор. Если MetricsCollector = null → metrics не recordятся. Это минимизирует риск регрессии.
- **Нет зависимости от других задач Sprint 9** — можно выполнять параллельно (но логически после TASK-feat-model-failover и TASK-feat-error-classification, т.к. decorator'ы обновляются).
- **Hooks integration (Sprint 10):** `MetricsCollectorInterface` будет использоваться hooks system для записи post_step метрик. Интерфейс должен быть достаточно гибким для этого.

## 8. Sources (Источники)
- [ ] [Анализ Локи: Observability gap](../../docs/research/analytical/loki-roadmap-review-2026-05.md) — MetricsCollector как упущенная потребность
- [ ] [AuditLoggerInterface](../../src/Module/Orchestrator/Domain/Service/Chain/Audit/AuditLoggerInterface.php) — существующий observability механизм
- [ ] [RetryingAgentRunner](../../src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunner.php)
- [ ] [CircuitBreakerAgentRunner](../../src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunner.php)
- [ ] [Конвенция: External Service (Interface)](../../docs/conventions/core_patterns/external-service.md)

## 9. Comments (Комментарии)
- Pain level: 5/10 — observability gap. Мы не знаем, какая боль самая острая, потому что не измеряем.
- Это foundation для Sprint 10 hooks: hook pipeline будет использовать MetricsCollector для записи post_step метрик.
- In-memory реализация достаточна для MVP. Persistent storage — если появится реальный кейс (dashboard, alerting).
- Interface в AgentRunner Domain (не Orchestrator) — runner metrics не зависят от chain orchestration.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-02 | system_analyst_sherlock (Шерлок) | Создание задачи |
