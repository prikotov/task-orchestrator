---
type: refactor
created: 2026-04-29
value: V3
complexity: C2
priority: P2
depends_on:
epic: EPIC-refactor-orchestrator-decomposition
author: Тимлид (Алекс)
assignee: Бэкендер (Левша)
branch: task/execution-strategy-implementation
pr: https://github.com/prikotov/task-orchestrator/pull/104
status: done
---

# TASK-refactor-execution-strategy: ExecutionStrategyInterface + Static/Dynamic + CommandHandler rewrite

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда OrchestrateChainCommandHandler (328 строк) содержит два поведенческих пути (static + dynamic) и 5 условных проверок `isDynamic()`, я хочу выделить ExecutionStrategyInterface и переписать CommandHandler как чистый диспетчер (~30 строк), чтобы при добавлении conditional branching не раздувать handler.

### Goal (Цель по SMART)
Создать `ExecutionStrategyInterface` в Application-слое с методами `execute()`, `resume()`, `supports()`. Реализовать `StaticExecutionStrategy` и `DynamicExecutionStrategy`. Переписать CommandHandler как диспетчер. Blast radius: 3 новых файла, 1 переписанный, DI config обновлён.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/Orchestrator/Application/`
*   **Текущее поведение:** CommandHandler — God-object: загружает цепочку, строит контекст, запускает dynamic/static loop, финализирует сессию, маппит DTO, диспетчит события.
*   **ADR:** [ADR-006](../../docs/adr/006-execution-strategy-composition.md) — контракт зафиксирован
*   **Границы (Out of Scope):**
    *   Не меняем Domain-слой (RunDynamicLoopService, RunStaticChainService)
    *   Не добавляем conditional branching (отдельная roadmap-фича)
    *   Не трогаем CLI-команду

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `ExecutionStrategyInterface` создан в Application-слое
- [ ] `StaticExecutionStrategy` реализован (делегирует `ExecuteStaticChainServiceInterface`)
- [ ] `DynamicExecutionStrategy` реализован (execute + resume + finalizeSession + toResultDto)
- [ ] CommandHandler переписан как диспетчер (~30 строк)
- [ ] DI-конфигурация обновлена (tagged strategies, auto-discovery)
- [ ] Все существующие тесты проходят
- [ ] `vendor/bin/phpunit` + `vendor/bin/psalm` — без ошибок

### 🟡 Should Have (Желательно)
- [ ] Unit-тест на `StaticExecutionStrategy`
- [ ] Unit-тест на `DynamicExecutionStrategy`

### ⚫ Won't Have (Не будем делать)
- [ ] Conditional branching strategy
- [ ] Изменение Domain-сервисов

## 4. Implementation Plan (План реализации)
1. [ ] Создать `ExecutionStrategyInterface` в `Application/Service/Chain/`
2. [ ] Создать `StaticExecutionStrategy` — обёртка над `ExecuteStaticChainServiceInterface`
3. [ ] Создать `DynamicExecutionStrategy` — перенести dynamic-path из CommandHandler
4. [ ] Переписать `OrchestrateChainCommandHandler` как диспетчер
5. [ ] Обновить `config/services.yaml` — зарегистрировать стратегии
6. [ ] Адаптировать существующие тесты CommandHandler
7. [ ] Добавить unit-тесты на стратегии
8. [ ] Запустить проверки: phpunit, psalm, phpcs

## 5. Definition of Done (Критерии приёмки)
- [ ] `ExecutionStrategyInterface` в Application с `execute()`, `resume()`, `supports()`
- [ ] `StaticExecutionStrategy` + `DynamicExecutionStrategy` реализованы
- [ ] CommandHandler ≤ 50 строк (чистый диспетчер)
- [ ] Все тесты проходят
- [ ] 0 switch-точек `isDynamic()` в CommandHandler

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
php vendor/bin/phpcs --standard=phpcs.xml.dist src/
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск 1:** Интеграционный тест CommandHandler (1095 строк) потребует адаптации — стратегии тестируются изолированно
- **Риск 2:** DynamicExecutionStrategy содержит много логики (session management, finalize, DTO mapping) — может быть ~200 строк
- **Зависимость:** ADR-006 (контракт зафиксирован)

## 8. Sources (Источники)
- [ ] [ADR-006: ExecutionStrategy](../../docs/adr/006-execution-strategy-composition.md)
- [ ] [Протокол brainstorm (решение 2)](../var/sessions/brainstorm/2026-04-29_08-06-49/result.md)

## 9. Comments (Комментарии)
Brainstorm-раунды 4-7: Гэндальф предложил Strategy, Локи — tagged union (отвергнуто). Консенсус: composition через интерфейс в Application-слое.

Action items #4-#6 из brainstorm-протокола (объединены в одну задачу — сильная связность).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-29 | Тимлид (Алекс) | Создание задачи |
