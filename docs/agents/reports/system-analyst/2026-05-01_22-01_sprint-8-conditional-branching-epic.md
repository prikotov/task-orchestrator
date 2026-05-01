# Sprint 8: Conditional Branching — Эпик и задачи

**Роль:** system_analyst_sherlock (Аналитик Шерлок)
**Дата:** 2026-05-01
**Объект:** Roadmap Sprint 8 — Conditional Branching: создание эпика и 4 задач
**Задача:** Запрос пользователя на создание EPIC-sprint-8-conditional-branching и задач

---

## Резюме

Создан эпик **EPIC-sprint-8-conditional-branching** и 4 связанные задачи для Sprint 8 (11 августа — 24 августа).

## Созданные файлы

### Эпик
- `todo/EPIC-sprint-8-conditional-branching.md` — 12.3 KB

### Задачи (порядок по зависимостям)

| # | Файл | Тип | Сложность | Зависимости |
|---|---|---|---|---|
| 1 | `todo/TASK-refactor-static-audit-isolation.todo.md` | refactor | C2 | — |
| 2 | `todo/TASK-feat-conditional-yaml-dsl.todo.md` | feat | C3 | Task 1 |
| 3 | `todo/TASK-feat-conditional-execution-strategy.todo.md` | feat | C3 | Task 1, Task 2 |
| 4 | `todo/TASK-feat-conditional-integration-layer.todo.md` | feat | C2 | Task 3 |

## Ключевые решения

1. **Tech debt включён в эпик** — Audit isolation (Task 1) выполняется первой, т.к. `RunStaticChainService` напрямую конструирует `ChainResultAuditDto` из Orchestrator Domain (нарушение границ модулей).

2. **`when:` DSL syntax** — основан на исследовании 4+ фреймворков (Archon, Mastra AI, LangGraph, Agno). Предложен простой синтаксис: `when: 'steps.tests.passed == true'`. Рекомендовано зафиксировать в ADR-009.

3. **`ChainTypeEnum::conditionalType`** — рекомендовано добавить отдельный тип (не subtype static), чтобы `supports()` в ConditionalExecutionStrategy мог однозначно определить поддержку.

4. **G6 Validation** — Task 4 (Integration layer) является точкой валидации G6: Integration-паттерн должен масштабироваться на 3-ю стратегию без God-interface.

5. **Architecture decision: Conditional в Orchestrator** — для MVP ConditionalExecutionStrategy живёт в `Orchestrator\Application\Service\Chain\`. Выделение в отдельный модуль — после G6 validation.

## Анализ кодовой базы

Исследованы ключевые файлы:
- `ExecutionStrategyInterface` — 3 метода (`execute`, `resume`, `supports`), стабильный контракт
- `OrchestrateChainCommandHandler` — 58 LOC, диспетчер через tagged iterator, не требует изменений
- `ChainStepVo` — 11 полей, нужно добавить `?ConditionExpressionVo $when`
- `ChainTypeEnum` — 2 значения (`static`, `dynamic`), нужно добавить `conditional`
- `YamlChainLoader` — парсинг YAML, нужно расширить для `when:` expressions
- `RunStaticChainService` — 12 обращений к `$auditLogger?->`, зависит от Orchestrator Domain DTO
- `StaticExecutionStrategy` — референс для реализации третьей стратегии

## Риски

- **R-6 (унаследованный):** Integration-паттерн может не масштабироваться на 3-ю стратегию — Sprint 8 = точка валидации
- **R-2:** Выбор `when:` DSL syntax может вызвать обсуждения — митигация через ADR-009
