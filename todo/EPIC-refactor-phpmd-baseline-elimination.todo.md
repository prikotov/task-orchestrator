---
type: epic
created: 2026-05-21
value: V2
complexity: C3
priority: P2
depends_on:
epic:
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: todo
---

# EPIC-refactor-phpmd-baseline-elimination: Устранить все PHPMD baseline suppression

## 0. Простое описание (Human Brief)
Устранить весь PHPMD baseline, чтобы код соответствовал порогам проекта.

### Проблема простыми словами (Problem)
8 записей в `phpmd.baseline.xml` маскируют реальные нарушения порогов PHPMD. 2 `@todo` о PHPMD bug не проверены.

### Варианты или путь решения (Solution Sketch)
Декомпозиция длинных методов, сокращение классов, устранение unused параметров.

### Ожидаемый результат (Expected Result)
`phpmd.baseline.xml` пуст или удалён, `make phpmd-full` = 0 violations, все `@todo` обработаны.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда в проекте накапливается техдолг в виде PHPMD baseline suppression, я хочу устранить его, чтобы `make phpmd-full` проходил без baseline и код соответствовал порогам PHPMD.

### Goal (SMART)
Устранить все 8 записей в `phpmd.baseline.xml` + 2 записи `@todo` о PHPMD bug в `ExecuteAgentStepService` и `RunStaticChainService`, чтобы `phpmd.baseline.xml` был пустым или удалён, а `make phpmd` и `make phpmd-full` давали одинаковый результат (0 violations).

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `phpmd.baseline.xml`, затронутые модули (AgentRunner, ChainDefinition, ChainExecution, DynamicLoop)
*   **Текущее поведение:** `phpmd.baseline.xml` содержит 8 suppression для реальных нарушений порогов PHPMD
*   **Границы (Out of Scope):** не меняем пороги в `phpmd.xml`, не трогаем другие инструменты (PHPCS, Psalm, Deptrac)

## 3. Requirements (MoSCoW)
### 🔴 Must Have
- [ ] Устранить все записи из `phpmd.baseline.xml`
- [ ] Убрать `@todo` о PHPMD bug из `ExecuteAgentStepService.php` и `RunStaticChainService.php`
- [ ] `make phpmd-full` — 0 violations
- [ ] `make check` — зелёный

### 🟡 Should Have
- [ ] Удалить `phpmd.baseline.xml` если suppression больше не нужны

### ⚫ Won't Have
- Изменение порогов в `phpmd.xml`
- Рефакторинг, не связанный с PHPMD violation

## 4. Implementation Plan
1. [x] [TASK-refactor-phpmd-retrying-runner-run](done/TASK-refactor-phpmd-retrying-runner-run.todo.md) — RetryingAgentRunnerService::run() 112 строк → ≤79
2. [ ] [TASK-refactor-phpmd-chaindefinition-classes](TASK-refactor-phpmd-chaindefinition-classes.todo.md) — ChainDefinitionVo 528 строк, YamlChainLoaderService 553 строк + parseSteps() 92 строки
3. [ ] [TASK-refactor-phpmd-chainexecution-methods](TASK-refactor-phpmd-chainexecution-methods.todo.md) — ShellHookExecutorService::execute() 107 строк, ExecuteAgentStepService::run() PHPMD bug @todo
4. [ ] [TASK-refactor-phpmd-dynamicloop-methods](TASK-refactor-phpmd-dynamicloop-methods.todo.md) — ExecuteDynamicTurnService::runParticipantTurn() 82 строки, runFacilitatorStep() 81 строка
5. [x] [TASK-fix-phpmd-errorclassificationvo](done/TASK-fix-phpmd-errorclassificationvo.todo.md) — ErrorClassificationVo::createFromClassException() unused parameter $throwable

## 5. Definition of Done
- [ ] `phpmd.baseline.xml` пуст или удалён
- [ ] `make phpmd-full` = 0 violations
- [ ] `make check` зелёный
- [ ] Все `@todo 2026-05-21: PHPMD bug` убраны

## 6. Verification
```bash
make phpmd-full
make check
```

## 7. Risks and Dependencies
- Декомпозиция длинных методов может вскрыть скрытую сложность
- Рефакторинг YamlChainLoader (553 строки) может потребовать изменения DSL-парсинга
- execute() в ShellHookExecutor работает с proc_open — требуется аккуратное разбиение

## 8. Sources
- [phpmd.xml](../phpmd.xml) — текущие пороги
- [phpmd.baseline.xml](../phpmd.baseline.xml) — текущие suppression
- [docs/conventions/core_patterns/service.md](../docs/conventions/core_patterns/service.md)

## 9. Comments

### Оставшиеся baseline записи (7 штук)

| # | Правило | Файл | Метод | Значение | Порог |
|---|---------|------|-------|----------|-------|
| 1 | LongMethod | RetryingAgentRunnerService.php | run() | 112 LOC | 80 |
| 2 | LongClass | ChainDefinitionVo.php | — | 528 LOC | 500 |
| 3 | LongClass | YamlChainLoaderService.php | — | 553 LOC | 500 |
| 4 | LongMethod | YamlChainLoaderService.php | parseSteps() | 92 LOC | 80 |
| 5 | LongMethod | ShellHookExecutorService.php | execute() | 107 LOC | 80 |
| 6 | LongMethod | ExecuteDynamicTurnService.php | runParticipantTurn() | 82 LOC | 80 |
| 7 | LongMethod | ExecuteDynamicTurnService.php | runFacilitatorStep() | 81 LOC | 80 |

### Дополнительный техдолг (не в baseline)

| # | Файл | Описание |
|---|------|----------|
| 9 | ExecuteAgentStepService.php:27 | `@todo 2026-05-21: PHPMD bug` — проверить, устранён ли баг в PHPMD |
| 10 | RunStaticChainService.php:26 | `@todo 2026-05-21: PHPMD bug` — аналогично |

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-21 | Тимлид (Алекс) | Создание эпика |
| 2026-06-06 | Бэкендер (Левша) | TASK-fix-phpmd-errorclassificationvo выполнена, baseline обновлён |
