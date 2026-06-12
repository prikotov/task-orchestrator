---
type: research
created: 2026-06-12
value: V3
complexity: C2
priority: P2
depends_on: []
epic:
author: Аналитик (Шерлок)
assignee:
branch:
pr:
status: backlog
---

# TASK-arch-provider-health-failover: Provider health/failover поверх существующих retry/circuit breaker

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда LLM provider недоступен, я хочу автоматическое переключение на fallback provider с health monitoring (cooldown после consecutive failures), чтобы повысить надёжность выполнения.

### Goal (Цель по SMART)
Спроектировать и реализовать Provider Health / Failover: host health monitoring (cooldown после consecutive failures), fallback routing на альтернативный provider, интеграция с существующими retry/circuit breaker. Определить entity/VO, health monitor, и интегрировать с нашей DDD/Clean Architecture.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/AgentRunner/` (Application + Domain layers). Возможные новые сущности: `ProviderHealth`, `HealthStatus`, `ProviderHealthMonitor`, `FallbackStrategy`.
*   **Текущее поведение:** task-orchestrator имеет circuit breaker и retry с backoff, но нет provider-level health monitoring и fallback routing.
*   **Границы (Out of Scope):** Не менять существующий circuit breaker implementation — только добавить provider health layer поверх него. Не реализовывать cost-based routing (дорогие/дешёвые models).

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Определить entity/VO для Provider Health: `ProviderHealthId`, `ProviderName`, `HealthStatus` (healthy/degraded/cooldown/dead), `ConsecutiveFailures`, `CooldownExpiry`, `LastSuccessAt`, `LastFailureAt`
- [ ] Определить health monitor interface: `ProviderHealthMonitorInterface` (Domain layer) с методами `markSuccess(ProviderName)`, `markFailure(ProviderName)`, `getHealth(ProviderName): HealthStatus`, `isAvailable(ProviderName): bool`
- [ ] Реализовать health monitor (Infrastructure layer): in-memory storage, cooldown calculation, consecutive failure threshold
- [ ] Определить fallback strategy interface: `FallbackStrategyInterface` (Domain layer) с методом `selectProvider(AvailableProviders[], CurrentProvider): ProviderName`
- [ ] Реализовать fallback strategy (Infrastructure layer): round-robin, weighted, or priority-based selection
- [ ] Интегрировать provider health monitoring в agent runner execution flow (Application layer)
- [ ] Указать ссылки на конвенции: [`Entity`](../../docs/conventions/layers/domain/entity.md), [`VO`](../../docs/conventions/core_patterns/value-object.md), [`Service`](../../docs/conventions/core_patterns/service.md)

### 🟡 Should Have (Желательно)
- [ ] Реализовать health persistence (Infrastructure layer): file-based storage for health state across restarts
- [ ] Реализовать health metrics: uptime, failure rate, average response time
- [ ] Реализовать health status change events: for monitoring/alerting (optional)
- [ ] Интегрировать с existing circuit breaker: health monitor triggers circuit breaker state change

### 🟢 Could Have (Опционально)
- [ ] Рассмотреть cost-based routing: select cheapest available provider with required capabilities
- [ ] Рассмотреть capability-based routing: select provider with vision, tools, etc.
- [ ] Рассмотреть geo-based routing: select provider closest to user (if multi-region)

### ⚫ Won't Have (Не будем делать)
- [ ] Не менять существующий circuit breaker implementation — только интеграция
- [ ] Не реализовывать cost-based routing (дорогие/дешёвые models) — отдельная задача/эпик
- [ ] Не реализовавать geo-based routing — отдельная задача/эпик

## 4. Implementation Plan (План реализации)
*План заполняется исполнителем перед стартом.*
1. [ ] Определить entity/VO для Provider Health
2. [ ] Определить health monitor interface и implementation (in-memory, cooldown calculation)
3. [ ] Определить fallback strategy interface и implementation (round-robin, priority-based)
4. [ ] Интегрировать provider health monitoring в agent runner execution flow
5. [ ] Интегрировать с existing circuit breaker (если возможно)
6. [ ] Создать unit-тесты для health monitor и fallback strategy
7. [ ] Обновить документацию (архитектура, health/failover examples)

## 5. Definition of Done (Критерии приёмки)
- [ ] Определены entity/VO для Provider Health (Domain layer)
- [ ] Определен health monitor interface (Domain layer) и implementation (Infrastructure layer)
- [ ] Определен fallback strategy interface (Domain layer) и implementation (Infrastructure layer)
- [ ] Интегрировано provider health monitoring в agent runner execution flow
- [ ] Созданы unit-тесты для health monitor и fallback strategy
- [ ] Обновлена документация: архитектура provider health/failover, examples

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Module/AgentRunner/Infrastructure/Health/ProviderHealthMonitorTest.php
vendor/bin/phpunit tests/Unit/Module/AgentRunner/Infrastructure/Fallback/ProviderFallbackStrategyTest.php
ls data/health/  # Проверка storage directory (если есть)
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск:** Health monitoring overhead may affect performance — need to measure and optimize.
- **Риск:** Fallback routing may cause inconsistent results (different providers for same request) — need to document trade-offs.
- **Зависимость:** Задача должна быть выполнена после понимания existing circuit breaker implementation.

## 8. Sources (Источники)
- [odysseus-comparison.md](../../docs/research/framework-comparisons/odysseus-comparison.md) — секция "Implementation Candidates for task-orchestrator"
- [agent-frameworks-summary.md](../../docs/research/agent-frameworks-summary.md) — кластер "Интеллектуальная обработка ошибок и восстановление"
- [OpenCode comparison](../../docs/research/framework-comparisons/opencode-comparison.md) — error classification + host health
- [Zeroclaw comparison](../../docs/research/framework-comparisons/zeroclaw-comparison.md) — ReliableProvider (fallback chain + retry)

## 9. Comments (Комментарии)
Цель этой задачи — add provider health/failover layer поверх existing retry/circuit breaker, not replace them.

**AGPL disclaimer:** Концепция host health (cooldown после consecutive failures) взята из Odysseus, но мы не копируем код. Implementation будет с нуля в нашей DDD/Clean Architecture.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-12 | Аналитик (Шерлок) | Создание задачи |