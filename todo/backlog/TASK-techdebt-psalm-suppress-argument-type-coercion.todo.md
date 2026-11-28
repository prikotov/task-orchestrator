---
type: refactor
created: 2026-11-28 03:28:53 (1795836533)
due: 
started: 
completed: 
cancelled: 
value: V1
complexity: C1
priority: P3
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Бэкендер Левша (pi)
assignee: 
consultants: []
reviewer: 
approver: 
informed: []
branch: 
pr: 
status: backlog
---

# TASK-techdebt-psalm-suppress-argument-type-coercion: Удалить избыточное @psalm-suppress ArgumentTypeCoercion в RunDynamicLoopService::initExecution()

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Над методом `RunDynamicLoopService::initExecution()` осталось подавление проверки типов `@psalm-suppress ArgumentTypeCoercion`. Оно было нужно, пока контракт чтения записей раундов (`getRoundFiles()`) описывался неточно; после сужения контракта до упорядоченного списка (список с полным описанием формы записи) подавление стало лишним: диагностика подтвердила, что без него анализатор ошибок не находит. Лишнее подавление скрывает потенциальные несоответствия типов без причины и снижает доверие к автоматическим проверкам.

### Варианты или путь решения (Solution Sketch)
- Удалить строку `@psalm-suppress ArgumentTypeCoercion` (и ставший пустым блок PHPDoc-комментария, если в нём не останется содержательного текста) в `RunDynamicLoopService::initExecution()`. Правка касается только комментария к коду: поведение не меняется.
- Прогнать статический анализ и профильные тесты, убедиться, что без подавления ошибок нет.

### Ожидаемый результат (Expected Result)
Подавления в `RunDynamicLoopService` больше нет, статический анализ (Psalm, PHPStan) и тесты проходят без него. Автоматические проверки типов снова охватывают вызов восстановления цикла, а неподтверждённых локальных исключений в коде стало на одно меньше.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как сопровождающий проекта, я хочу, чтобы в `RunDynamicLoopService` не было подавлений без причины, чтобы анализатор типов проверял восстановление dynamic-цикла наравне с остальным кодом.

### Цель по SMART (Goal)
- **S — конкретно:** удалить ставшее избыточным подавление `@psalm-suppress ArgumentTypeCoercion` над `RunDynamicLoopService::initExecution()`; других правок кода и поведения не делать.
- **M — измеримо:** `vendor/bin/psalm` и `vendor/bin/phpstan analyse --no-progress` зелёные без подавления; целевые Unit-тесты `tests/Unit/Domain/Service/Chain/Dynamic/` проходят.
- **A — достижимо:** правка ограничена одним блоком комментария к коду; избыточность подтверждена диагностикой Т6 задачи-источника.
- **R — релевантно:** закрывает конкретный техдолг, зафиксированный при сужении контракта `getRoundFiles()`, и приводит код в соответствие с чек-листом подавлений («подавления имеют причину и техдолг-задачу»).
- **T — ограничено во времени:** дедлайн не назначен (`P3`, `backlog`); выполняется одной итерацией и одним отдельным PR после слияния задачи-источника.

## 2. Контекст и Границы (Context and Scope)

* **Где делаем:** `src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopService.php` — блок PHPDoc с `@psalm-suppress ArgumentTypeCoercion` над `initExecution()`; маркер `@techdebt` с ссылкой на эту задачу удаляется вместе с подавлением.
* **Текущее поведение:** подавление унаследовано от прежнего, более слабого контракта `getRoundFiles()`; после сужения контракта до `list<array{...}>` (задача-источник) анализатору подавление не требуется — диагностика Т6 показала только отсутствие ошибки `ArgumentTypeCoercion` без тега.
* **Прецеденты для переиспользования:** аудит подавлений (задача-источник аудита) — порядок диагностики «временно снять тег → прогнать Psalm → вернуть/удалить по результату»; контекст задачи-источника, разделы Т6 и Verification.
* **Критичные конвенции:** [Domain](../../docs/conventions/layers/domain.md) — без подавлений при наличии точного контракта; правила оформления подавлений и техдолга из AGENTS.md (маркер `@techdebt` + отдельная задача техдолга). Поведение восстановления, контракты Writer/Reader и тесты не меняются.
* **Границы (Out of Scope):** остальные подавления Psalm/PHPStan в проекте (в том числе `ArgumentTypeCoercion` в `ChainExecution`), контракт `getRoundFiles()` и `restoreFromRoundFiles()`, формат `session.json`, тесты, настройка `findUnusedPsalmSuppress`.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] Из `RunDynamicLoopService::initExecution()` удалено подавление `@psalm-suppress ArgumentTypeCoercion` вместе с маркером `@techdebt`, ссылающимся на эту задачу; содержательный текст комментария сохраняется, если не относится к подавлению.
- [ ] Поведение метода не изменилось: правка ограничена комментарием к коду; diff не содержит других изменений.
- [ ] `vendor/bin/psalm` и `vendor/bin/phpstan analyse --no-progress` зелёные без подавления (включая отсутствие несматченных подавлений для PHPStan).
- [ ] Целевые Unit-тесты `tests/Unit/Domain/Service/Chain/Dynamic/` проходят.

### 🟡 Желательно (Should Have)
- [ ] Изменения оформлены отдельным PR от актуального `main` с коммитом по Conventional Commits (например, `refactor(dynamic-loop): drop redundant psalm suppression ...`).

### 🟢 Опционально (Could Have)
- [ ] Нет.

### ⚫ Не будем делать (Won't Have)
- [ ] Не трогать другие подавления Psalm/PHPStan и аудит suppressing-тегов в остальных модулях.
- [ ] Не менять контракты `getRoundFiles()` / `restoreFromRoundFiles()`, поведение восстановления, метрик и формат сессии.
- [ ] Не включать изменение в PR задачи-источника: задача выполняется после его слияния.

## 4. План реализации (Implementation Plan)
1. [ ] Убедиться, что задача-источник слита в `main` и ветка создана от актуального `main`. **Проверка:** `git status` чист, база ветки — `main`.
2. [ ] Удалить строку `@psalm-suppress ArgumentTypeCoercion` и маркер `@techdebt` в блоке PHPDoc над `RunDynamicLoopService::initExecution()`; при пустом блоке удалить блок целиком. **Проверка:** diff содержит только эту правку.
3. [ ] Прогнать `vendor/bin/psalm` и `vendor/bin/phpstan analyse --no-progress`. **Проверка:** ошибок нет; ошибки `ArgumentTypeCoercion` не возникло (подтверждено диагностикой Т6).
4. [ ] Прогнать `vendor/bin/phpunit tests/Unit/Domain/Service/Chain/Dynamic/` и полный `vendor/bin/phpunit`. **Проверка:** тесты зелёные, регрессий нет.
5. [ ] Обновить задачу (критерии, Verification, история), оформить отдельный PR и пройти ревью по RACI для `refactor`.

## 5. Критерии приёмки (Definition of Done)
- [ ] Подавление `@psalm-suppress ArgumentTypeCoercion` и связанный маркер `@techdebt` отсутствуют в `RunDynamicLoopService`.
- [ ] `vendor/bin/psalm` — 0 ошибок; `vendor/bin/phpstan analyse --no-progress` — `No errors`.
- [ ] `vendor/bin/phpunit` успешен; регрессий в модуле `DynamicLoop` нет.
- [ ] Diff ограничен удалением подавления; поведение не изменилось.
- [ ] Изменения оформлены отдельным PR, задача переведена в `done` по регламенту.

## 6. Самопроверка (Verification)

Состояние при постановке: код не изменялся, задача предназначена для `backlog`; команды ниже — обязательные для исполнителя при реализации.

```bash
php vendor/bin/todo-md validate TASK-techdebt-psalm-suppress-argument-type-coercion
vendor/bin/psalm
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit tests/Unit/Domain/Service/Chain/Dynamic/
vendor/bin/phpunit
```

## 7. Риски и зависимости (Risks and Dependencies)
- Зависимость: правка имеет смысл только после слияния задачи-источника (сужение контракта `getRoundFiles()`), иначе подавление ещё необходимо.
- Риск низкий: при реализации без подавления возникнет ошибка анализа (что противоречит диагностике Т6) — тогда остановиться, вернуть тег и переоткрыть постановку на уточнение причин.

## 8. Источники (Sources)
- [Задача-источник TASK-techdebt-getroundfiles-contract](../TASK-techdebt-getroundfiles-contract.todo.md) — диагностика Т6 (временное снятие тега: `ArgumentTypeCoercion` не возникает; подавление избыточно после сужения контракта).
- [Отчёт ревью getroundfiles-contract (CR-1)](../../docs/agents/reports/code-reviewer-backend/2026-11-28_10-19_getroundfiles-contract-review.md) — требование отдельной техдолг-задачи и маркировки подавления.
- [Решение DS по чек-поинту формы](../../docs/agents/reports/system-architect/2026-11-28_09-51_getroundfiles-shape-checkpoint.md) — контекст принятия открытой принимающей shape.
- [Ревью аудита подавлений (источник аудита)](../../docs/agents/reports/code-reviewer-backend/2026-11-27_23-31_phpstan-suppressions-audit-review.md) — порядок работы с подавлениями.

## 9. Комментарии (Comments)
- Задача создана в ходе доработки задачи-источника по замечанию CR-1 ревью от 2026-11-28 (DoD Т6: «при обнаружении лишнего подавления создана отдельная техдолг-задача»). Автор постановки — исполнитель задачи-источника.
- В PR задачи-источника само подавление не трогается и остаётся с маркером `@techdebt`, ссылающимся на эту задачу; удаление выполняется только здесь.

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-28 03:28:53 (1795836533) | Бэкендер Левша (pi) | Создание задачи |
