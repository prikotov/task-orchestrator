---
type: feat
created: 2026-05-02
value: V3
complexity: C2
priority: P0
depends_on:
epic: EPIC-sprint-9-resilience-observability
author: system_analyst_sherlock (Шерлок)
assignee:
branch:
pr:
status: todo
---

# TASK-feat-model-failover: Model failover: CB open → trigger fallback

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда [`CircuitBreakerAgentRunner`](../../src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunner.php) переходит в open-состояние и блокирует вызов, цепочка падает — хотя fallback runner может быть уже сконфигурирован через [`FallbackConfigVo`](../../src/Module/Orchestrator/Domain/ValueObject/FallbackConfigVo.php). Я хочу, чтобы CB open автоматически триггерил fallback runner, чтобы цепочка продолжала работу при недоступности основного runner'а.

### Goal (Цель по SMART)
Связать [`CircuitBreakerAgentRunner`](../../src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunner.php) с [`FallbackConfigVo`](../../src/Module/Orchestrator/Domain/ValueObject/FallbackConfigVo.php) через [`RoleConfigVo::$fallback`](../../src/Module/Orchestrator/Domain/ValueObject/RoleConfigVo.php): при CB open → автоматически триггерить fallback runner (если сконфигурирован). Wiring существующего кода, не новая архитектура. Срок: 1 день.

## 2. Context and Scope (Контекст и Границы)
### Где делаем
- [`src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunner.php`](../../src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunner.php) — основной файл изменений
- [`src/Module/Orchestrator/Domain/ValueObject/RoleConfigVo.php`](../../src/Module/Orchestrator/Domain/ValueObject/RoleConfigVo.php) — источник fallback-конфигурации
- [`src/Module/Orchestrator/Domain/ValueObject/FallbackConfigVo.php`](../../src/Module/Orchestrator/Domain/ValueObject/FallbackConfigVo.php) — конфигурация fallback-команды
- DI-конфигурация: wiring CB → fallback runner

### Текущее поведение
- [`CircuitBreakerAgentRunner`](../../src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunner.php) при open-состоянии (строка ~60): возвращает `AgentResultVo::createFromError("Circuit breaker is open")` — цепочка падает
- [`FallbackConfigVo`](../../src/Module/Orchestrator/Domain/ValueObject/FallbackConfigVo.php) существует и содержит fallback-команду, но не используется при CB open
- [`RoleConfigVo::$fallback`](../../src/Module/Orchestrator/Domain/ValueObject/RoleConfigVo.php) возвращает `?FallbackConfigVo` — уже есть в конфигурации

### Границы (Out of Scope)
- Не создаём отдельный `FailoverAgentRunner` decorator (Вариант B из отчёта Локи — overengineering)
- Не добавляем cooldown-механизм: CB уже имеет `resetTimeoutSeconds` в [`CircuitBreakerStateVo`](../../src/Module/AgentRunner/Domain/ValueObject/CircuitBreakerStateVo.php)
- Не меняем YAML DSL: fallback уже конфигурируется в `roles:` section
- Не меняем `AgentRunnerInterface` — fallback wiring через конструктор CB

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] [`CircuitBreakerAgentRunner`](../../src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunner.php) при CB open проверяет наличие fallback runner
- [ ] CB open + fallback сконфигурирован → fallback runner запускается, результат возвращается
- [ ] CB open + fallback НЕ сконфигурирован → текущее поведение (error result)
- [ ] Fallback runner использует `FallbackConfigVo::$command` для построения [`AgentRunRequestVo`](../../src/Module/AgentRunner/Domain/ValueObject/AgentRunRequestVo.php)
- [ ] Unit-тесты: CB open → fallback triggered, CB open → no fallback → error, CB closed → inner runner
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные

### 🟡 Should Have (Желательно)
- [ ] Логирование: при fallback trigger — warning в log с указанием runner name + fallback runner name
- [ ] Audit: fallback activation зафиксирован в [`AuditLoggerInterface`](../../src/Module/Orchestrator/Domain/Service/Chain/Audit/AuditLoggerInterface.php)

### 🟢 Could Have (Опционально)
- [ ] Метрика: counter `fallback_triggered` в MetricsCollector (если TASK-feat-metrics-collector уже завершена)

### ⚫ Won't Have (Не будем делать)
- [ ] Model-level failover (Claude → GPT через тот же runner) — это отдельная задача
- [ ] Приоритизированный список fallback-раннеров
- [ ] Fallback chaining (fallback от fallback'а)

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем (Левша) перед стартом.*

Предлагаемый подход (Вариант A из отчёта Локи):
1. [ ] Добавить `AgentRunnerInterface $fallbackRunner` как optional зависимость в [`CircuitBreakerAgentRunner`](../../src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunner.php) (nullable, по умолчанию null)
2. [ ] В методе `run()`: при `CircuitStateEnum::open` и `$fallbackRunner !== null` → делегировать вызов fallback runner
3. [ ] Обновить DI-конфигурацию: wire fallback runner из `FallbackConfigVo` в `CircuitBreakerAgentRunner`
4. [ ] Unit-тесты: 3 сценария (open + fallback, open + no fallback, closed)
5. [ ] Обновить PHPDoc

### Структура файлов
```
src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunner.php  — изменить
config/services.yaml                                                         — обновить DI wiring
tests/Unit/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunnerTest.php  — обновить/создать
```

## 5. Definition of Done (Критерии приёмки)
- [ ] CB open → fallback runner запускается (если сконфигурирован через [`FallbackConfigVo`](../../src/Module/Orchestrator/Domain/ValueObject/FallbackConfigVo.php))
- [ ] CB open → error (если fallback не сконфигурирован) — обратная совместимость
- [ ] CB closed/half-open → inner runner — поведение не изменилось
- [ ] Unit-тесты покрывают все 3 сценария
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные
- [ ] Deptrac green

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Module/AgentRunner/
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Cross-module dependency:** `CircuitBreakerAgentRunner` (AgentRunner) читает `FallbackConfigVo` (Orchestrator). Это нарушение направления зависимости. Митигация: передавать fallback runner через DI как `AgentRunnerInterface`, не читать Orchestrator VO напрямую в AgentRunner.
- **DI complexity:** wiring fallback runner через config может потребовать named services или factory. Проверить существующий DI wiring pattern.
- **Зависимость от конвенций:** [`Value Object`](../../docs/conventions/core_patterns/value-object.md), [`Decorator pattern`](../../docs/conventions/core_patterns/wrapper.md)

## 8. Sources (Источники)
- [ ] [Roadmap: Sprint 9](../../docs/releases/ROADMAP-2026-Q2-Q3.md)
- [ ] [Анализ Локи: Model Failover](../../docs/research/loki-roadmap-review-2026-05.md) — Вариант A (через RoleConfigVo fallback)
- [ ] [CircuitBreakerAgentRunner](../../src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunner.php)
- [ ] [FallbackConfigVo](../../src/Module/Orchestrator/Domain/ValueObject/FallbackConfigVo.php)
- [ ] [RoleConfigVo](../../src/Module/Orchestrator/Domain/ValueObject/RoleConfigVo.php)

## 9. Comments (Комментарии)
- Pain level: 7/10 — реальная production боль. LLM API rate limits, 529 overloaded, 503 service unavailable — обычное дело. Сейчас вся цепочка падает при недоступности модели.
- Вариант A (через `RoleConfigVo::$fallback`) выбран как более простой: wiring существующих CB + FallbackConfigVo, без новой архитектуры.
- Важно не нарушить направление зависимостей: AgentRunner не должен зависеть от Orchestrator VO. Fallback runner передаётся через DI как `AgentRunnerInterface`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-02 | system_analyst_sherlock (Шерлок) | Создание задачи |
