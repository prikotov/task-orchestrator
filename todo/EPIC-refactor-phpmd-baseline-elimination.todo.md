---
type: epic
created: 2026-05-21
value: V2
complexity: C3
priority: P2
depends_on:
epic:
author: Тимлид (Алекс)
assignee: Тимлид (Алекс)
branch: refactor/phpmd-baseline-elimination
pr:
status: in_progress
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
- [x] Устранить все **рефакторируемые** записи из `phpmd.baseline.xml` (9 из 12; 3 остаются как задокументированные исключения — см. «Открытые пункты»)
- [x] Убрать `@todo` о PHPMD bug из `ExecuteAgentStepService.php` и `RunStaticChainService.php`
- [x] `make phpmd` (с оставшимся baseline) — 0 violations
- [x] `make check` — зелёный

### 🟡 Should Have
- [ ] Удалить `phpmd.baseline.xml` если suppression больше не нужны

### ⚫ Won't Have
- Изменение порогов в `phpmd.xml`
- Рефакторинг, не связанный с PHPMD violation

## 4. Implementation Plan
1. [x] [TASK-refactor-phpmd-retrying-runner-run](done/TASK-refactor-phpmd-retrying-runner-run.todo.md) — RetryingAgentRunnerService::run() 112 строк → ≤79 *(залита напрямую в main как PR #259 до ввода эпик-ветки)*
2. [x] [TASK-refactor-phpmd-chaindefinition-classes](done/TASK-refactor-phpmd-chaindefinition-classes.todo.md) — ChainDefinitionVo 545→493, YamlChainLoaderService 563→449, parseSteps() вынесен в ChainStepParserHelper
3. [x] [TASK-refactor-phpmd-chainexecution-methods](done/TASK-refactor-phpmd-chainexecution-methods.todo.md) — ShellHookExecutorService::execute() 107→52, `@todo` PHPMD bug убраны (корень = кэш PDepend)
4. [x] [TASK-refactor-phpmd-dynamicloop-methods](done/TASK-refactor-phpmd-dynamicloop-methods.todo.md) — ExecuteDynamicTurnService::runParticipantTurn() 82→61, runFacilitatorStep() 81→70
5. [x] [TASK-fix-phpmd-errorclassificationvo](done/TASK-fix-phpmd-errorclassificationvo.todo.md) — ErrorClassificationVo::createFromClassException() unused parameter $throwable
6. [x] [TASK-fix-phpmd-auditloggers](done/TASK-fix-phpmd-auditloggers.todo.md) — `@mkdir` → guard без `@` (fail-fast) в JsonlAuditLoggerService и JsonlAuditLogger
7. [ ] [TASK-refactor-phpmd-dynamicloop-aggregate](TASK-refactor-phpmd-dynamicloop-aggregate.todo.md) — DynamicLoopExecution TooManyPublicMethods (14/10) + TooManyFields (18/12): архитектурный редизайн агрегата (follow-up, вне этого эпика)

### Открытые пункты — РЕШЕНО Тимлидом (2026-06-14)
- **bridge.php** (`ErrorControlOperator`, vendored Codex-скрипт, suppression намеренное) — **РЕШЕНО: ОСТАВИТЬ** suppression. Запись в baseline остаётся как документированное исключение.
- **DynamicLoopExecution** (`TooManyPublicMethods` **14**/10 + `TooManyFields` **18**/12 — реальные замеры, не 35/53) — **РЕШЕНО: ВЫНЕСТИ ЗА SCOPE**. Требует архитектурного редизайна агрегата; создана follow-up задача `TASK-refactor-phpmd-dynamicloop-aggregate` (см. план, п. 7). 2 записи в baseline остаются как документированное исключение до её выполнения.

### ⚠️ Технический нюанс: флакучесть PHPMD
Единичный прогон `make phpmd` на этом репозитории **недосчитывает** нарушения (воспроизводимо: единичные прогоны показывают 0–3 вместо реальных 6). Очистка кэша PDepend НЕ помогает; флакучесть не от кэша. Поэтому все решения по baseline принимались по **стабильному набору** (3/3 идентичных прогона). Это предсуществующий риск CI (отдельная задача), не блокирующий эпик.

## 5. Definition of Done
- [x] `phpmd.baseline.xml` сокращён с 12 до **3 задокументированных исключений** (bridge.php — intentional vendored; DynamicLoopExecution ×2 — вне scope, follow-up)
- [x] `make phpmd` (с baseline) = 0 violations; `make phpmd` 3× подряд зелёный после удаления записей
- [x] `make check` зелёный (963 теста, Psalm/PHPStan/Deptrac/PHPCS OK)
- [x] Все `@todo 2026-05-21: PHPMD bug` убраны

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

### Устранённые baseline записи (9 из 12)

| # | Правило | Файл | Метод | Было | Стало | Задача |
|---|---------|------|-------|------|-------|--------|
| 1 | LongMethod | RetryingAgentRunnerService.php | run() | 112 | 45 | #259 (main) |
| 2 | LongClass | ChainDefinitionVo.php | — | 545 | 493 | задача 2 |
| 3 | LongClass | YamlChainLoaderService.php | — | 563 | 449 | задача 2 |
| 4 | LongMethod | YamlChainLoaderService.php | parseSteps() | 93 | → ChainStepParserHelper | задача 2 |
| 5 | LongMethod | ShellHookExecutorService.php | execute() | 107 | 52 | задача 3 |
| 6 | LongMethod | ExecuteDynamicTurnService.php | runParticipantTurn() | 82 | 61 | задача 4 |
| 7 | LongMethod | ExecuteDynamicTurnService.php | runFacilitatorStep() | 81 | 70 | задача 4 |
| 8 | ErrorControlOperator | JsonlAuditLoggerService.php | append | @mkdir | guard без @ | задача 6 |
| 9 | ErrorControlOperator | JsonlAuditLogger.php | append | @mkdir | guard без @ | задача 6 |
| — | LongMethod | ExecuteAgentStepService.php | run() | (stale) | — | задача 3 (ложная запись удалена) |

### Оставшиеся baseline записи (3 — задокументированные исключения)

| # | Правило | Файл | Решение |
|---|---------|------|--------|
| 1 | ErrorControlOperator | bridge.php | KEEP — vendored Codex-скрипт, suppression намеренное |
| 2 | TooManyPublicMethods | DynamicLoopExecution.php | OUT OF SCOPE — follow-up TASK-refactor-phpmd-dynamicloop-aggregate |
| 3 | TooManyFields | DynamicLoopExecution.php | OUT OF SCOPE — follow-up TASK-refactor-phpmd-dynamicloop-aggregate |

### Дополнительный техдолг (решён)

| Файл | Решение |
|------|--------|
| ExecuteAgentStepService.php | `@todo` убран — нарушения нет (61 LOC), baseline-запись была ложной |
| RunStaticChainService.php | `@todo` убран — нарушения нет |

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-21 | Тимлид (Алекс) | Создание эпика |
| 2026-06-06 | Бэкендер (Левша) | TASK-fix-phpmd-errorclassificationvo выполнена, baseline обновлён |
| 2026-06-13 | Тимлид (Алекс) | PR #259 (задача 1) влит в main; создана эпик-ветка `refactor/phpmd-baseline-elimination`, статус → in_progress; разведка выявила 5 непокрытых baseline-записей — добавлена задача 6 (audit-логгеры), по bridge.php и DynamicLoopExecution запрошено решение пользователя |
| 2026-06-14 | Бэкендер (Левша) | TASK-refactor-phpmd-chaindefinition-classes выполнена (ChainDefinitionVo 545→493, YamlChainLoaderService 563→449, parseSteps→ChainStepParserHelper), 3 записи убраны из baseline, FQCN ChainFixIterationsValidatorHelper добавлен в StaticAccess exceptions |
| 2026-06-14 | Бэкендер (Левша) | TASK-fix-phpmd-auditloggers + TASK-refactor-phpmd-chainexecution-methods выполнены (@mkdir→guard без @, ShellHookExecutor 107→52, @todo убраны), 4 записи убраны из baseline |
| 2026-06-14 | Ревьювер (Пуаро) | Финальное ревью эпика: APPROVE — эквивалентность всех 6 частей подтверждена построчно |
| 2026-06-14 | Тимлид (Алекс) | РЕШЕНО: Q-A bridge.php — KEEP; Q-B DynamicLoopExecution — OUT OF SCOPE (follow-up TASK-refactor-phpmd-dynamicloop-aggregate). Baseline 12→3. Эпик готов к финальному PR |
