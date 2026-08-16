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
pr: "261"
status: done
---

# EPIC-refactor-phpmd-baseline-elimination: Устранить все PHPMD baseline suppression

## 0. Простое описание (Human Brief)
Устранить весь PHPMD baseline, чтобы код соответствовал порогам проекта.

### Проблема простыми словами (Problem)
8 записей в `phpmd.baseline.xml` маскируют реальные нарушения порогов PHPMD. 2 `@todo` о PHPMD bug не проверены.

### Варианты или путь решения (Solution Sketch)
Декомпозиция длинных методов, сокращение классов, устранение unused параметров.

### Ожидаемый результат (Expected Result)
`phpmd.baseline.xml` пуст, `make phpmd-full` = 0 violations, все `@todo` обработаны.

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
- [x] Устранить **ВСЕ** записи из `phpmd.baseline.xml` (12/12): 9 — рефакторингом методов/классов, 1 (bridge.php) — exclude-pattern, 2 (DynamicLoopExecution) — архитектурным редизайном агрегата
- [x] Убрать `@todo` о PHPMD bug из `ExecuteAgentStepService.php` и `RunStaticChainService.php`
- [x] `make phpmd` (с пустым baseline) — 0 violations, 3× подряд зелёный
- [x] `make check` — зелёный

### 🟡 Should Have
- [x] `phpmd.baseline.xml` пустой (все suppression устранены)

### ⚫ Won't Have
- Изменение порогов в `phpmd.xml`
- Рефакторинг, не связанный с PHPMD violation

## 4. Implementation Plan
1. [x] [TASK-refactor-phpmd-retrying-runner-run](TASK-refactor-phpmd-retrying-runner-run.todo.md) — RetryingAgentRunnerService::run() 112 строк → ≤79 *(залита напрямую в main как PR #259 до ввода эпик-ветки)*
2. [x] [TASK-refactor-phpmd-chaindefinition-classes](TASK-refactor-phpmd-chaindefinition-classes.todo.md) — ChainDefinitionVo 545→493, YamlChainLoaderService 563→449, parseSteps() вынесен из лоадера (далее редизайнен в Mapper+Factory, см. правки 2026-06-15)
3. [x] [TASK-refactor-phpmd-chainexecution-methods](TASK-refactor-phpmd-chainexecution-methods.todo.md) — ShellHookExecutorService::execute() 107→52, `@todo` PHPMD bug убраны (корень = кэш PDepend)
4. [x] [TASK-refactor-phpmd-dynamicloop-methods](TASK-refactor-phpmd-dynamicloop-methods.todo.md) — ExecuteDynamicTurnService::runParticipantTurn() 82→61, runFacilitatorStep() 81→70
5. [x] [TASK-fix-phpmd-errorclassificationvo](TASK-fix-phpmd-errorclassificationvo.todo.md) — ErrorClassificationVo::createFromClassException() unused parameter $throwable
6. [x] [TASK-fix-phpmd-auditloggers](TASK-fix-phpmd-auditloggers.todo.md) — `@mkdir` → guard без `@` (fail-fast) в JsonlAuditLoggerService и JsonlAuditLogger
7. [x] [TASK-refactor-phpmd-dynamicloop-aggregate](TASK-refactor-phpmd-dynamicloop-aggregate.todo.md) — DynamicLoopExecution TooManyPublicMethods (14/10) + TooManyFields (18/12): архитектурный редизайн агрегата — выделение владеемых компонентов DynamicLoopMetrics + DynamicLoopJournal (возвращено в scope по решению пользователя 2026-06-15)
8. [x] bridge.php — exclude-pattern в `phpmd.xml` (standalone proc_open-процесс, ≡ bin/-скриптам)

### Открытые пункты — РЕШЕНО (финал)
- **bridge.php** (`ErrorControlOperator`, standalone Codex-процесс) — **ИТОГ: exclude-pattern в `phpmd.xml`** (ранее планировалось KEEP в baseline; пересмотрено по решению пользователя 2026-06-15 — устранить все записи). Запись убрана из baseline.
- **DynamicLoopExecution** (`TooManyPublicMethods` 14/10 + `TooManyFields` 18/12) — **ИТОГ: архитектурный редизайн выполнен в этом же эпике** (ранее планировалось OUT OF SCOPE как follow-up; пересмотрено по решению пользователя 2026-06-15). 2 записи убраны из baseline.

### ⚠️ Технический нюанс: флакучесть PHPMD
Единичный прогон `make phpmd` на этом репозитории **недосчитывает** нарушения (воспроизводимо: единичные прогоны показывают 0–3 вместо реальных 6). Очистка кэша PDepend НЕ помогает; флакучесть не от кэша. Поэтому все решения по baseline принимались по **стабильному набору** (3/3 идентичных прогона). Это предсуществующий риск CI (отдельная задача), не блокирующий эпик.

## 5. Definition of Done
- [x] `phpmd.baseline.xml` **полностью пустой** (12→0): bridge.php → exclude-pattern; DynamicLoopExecution ×2 → архитектурный редизайн агрегата
- [x] `make phpmd` (с пустым baseline) = 0 violations; 3× подряд зелёный (283 файла)
- [x] `make check` зелёный (1032 теста, Psalm 0 errors, Deptrac 0 violations)
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
- [docs/conventions/core-patterns/service.md](../../docs/conventions/core-patterns/service.md)

## 9. Comments

### Устранённые baseline записи (9 из 12)

| # | Правило | Файл | Метод | Было | Стало | Задача |
|---|---------|------|-------|------|-------|--------|
| 1 | LongMethod | RetryingAgentRunnerService.php | run() | 112 | 45 | #259 (main) |
| 2 | LongClass | ChainDefinitionVo.php | — | 545 | 493 | задача 2 |
| 3 | LongClass | YamlChainLoaderService.php | — | 563 | 449 | задача 2 |
| 4 | LongMethod | YamlChainLoaderService.php | parseSteps() | 93 | → Mapper+Factory (ChainStepParserHelper удалён) | задача 2 |
| 5 | LongMethod | ShellHookExecutorService.php | execute() | 107 | 52 | задача 3 |
| 6 | LongMethod | ExecuteDynamicTurnService.php | runParticipantTurn() | 82 | 61 | задача 4 |
| 7 | LongMethod | ExecuteDynamicTurnService.php | runFacilitatorStep() | 81 | 70 | задача 4 |
| 8 | ErrorControlOperator | JsonlAuditLoggerService.php | append | @mkdir | guard без @ | задача 6 |
| 9 | ErrorControlOperator | JsonlAuditLogger.php | append | @mkdir | guard без @ | задача 6 |
| — | LongMethod | ExecuteAgentStepService.php | run() | (stale) | — | задача 3 (ложная запись удалена) |

### Оставшиеся baseline записи

**Нет.** Baseline полностью пустой (12→0). bridge.php — exclude-pattern в `phpmd.xml`; DynamicLoopExecution ×2 — устранены архитектурным редизайном (выделение `DynamicLoopMetrics` + `DynamicLoopJournal`).

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
| 2026-06-15 | Архитектор (Локи) → Бэкендер (Левша) → Тимлид (Алекс) | **Редизайн двух helper'ов с бизнес-логикой из коммита c8f2789** по конвенциям (после аудита Пуаро): `ChainFixIterationsValidatorHelper` → `FixIterationsReferenceIntegritySpecification` (Domain) + `ChainDefinitionFactory` (Domain); `ChainStepParserHelper` → `ChainStepFactory` (Domain) + `YamlChainStepMapper` + `YamlRetryPolicyMapper` (Infrastructure). Поведение сохранено byte-to-byte, прямые unit-тесты добавлены (PHPUnit 981→1017). PR #261 доведён до конвенционной чистоты |
| 2026-06-14 | Тимлид (Алекс) | РЕШЕНО (предварительно): Q-A bridge.php — KEEP; Q-B DynamicLoopExecution — OUT OF SCOPE. Baseline 12→3 |
| 2026-06-15 | Пользователь | Решение: «осталось часть ошибок в phpmd.baseline.xml» — устранить ВСЕ оставшиеся записи. bridge.php → exclude-pattern; DynamicLoopExecution → редизайн в этом же эпике |
| 2026-06-15 | Архитектор (Локи) → Бэкендер (Левша) → Ревьювер (Пуаро) | Полный конвейер устранения оставшихся 3 записей: bridge.php exclude + DynamicLoopExecution редизайн (DynamicLoopMetrics + DynamicLoopJournal). **Baseline 12→0**. PHPUnit 1032/2888, Psalm 0, Deptrac 0, PHPMD 0 (baseline пустой). Эпик → done, PR #261 |
