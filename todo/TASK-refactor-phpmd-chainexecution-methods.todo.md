---
type: refactor
created: 2026-05-21
value: V2
complexity: C2
priority: P2
depends_on:
epic: EPIC-refactor-phpmd-baseline-elimination
author: Тимлид (Алекс)
assignee: Бэкендер (Левша)
branch: refactor/phpmd-baseline-elimination
pr: (единый эпик-PR в конце)
status: in_progress
---

# TASK-refactor-phpmd-chainexecution-methods: Устранить LongMethod в ChainExecution + убрать @todo PHPMD bug

## 0. Простое описание (Human Brief)
Устранить PHPMD violation (technical debt), чтобы код соответствовал порогам проекта.

### Проблема простыми словами (Problem)
Метод или класс превышает порог PHPMD, suppression в baseline маскирует проблему.

### Варианты или путь решения (Solution Sketch)
Экстракция приватных методов или рефакторинг для уменьшения LOC.

### Ожидаемый результат (Expected Result)
PHPMD baseline пуст, `make phpmd-full` = 0 violations.

## 0. Простое описание (Human Brief)
Устранить PHPMD violation (technical debt) и убрать @todo о PHPMD bug.

### Проблема простыми словами (Problem)
ShellHookExecutorService::execute() = 107 LOC превышает порог 80; @todo о PHPMD bug требует проверки.

### Варианты или путь решения (Solution Sketch)
Экстракция приватных методов в ShellHookExecutorService; проверка PHPMD bug.

### Ожидаемый результат (Expected Result)
PHPMD baseline пуст, `@todo` убраны, `make phpmd-full` = 0 violations.

## 1. Concept and Goal (Концепция и Цель)
### Story
Как разработчик, я хочу чтобы `ShellHookExecutorService::execute()` (107 строк) и `ExecuteAgentStepService::run()` соответствовали порогам PHPMD, а `@todo` о PHPMD bug был убран.

### Goal
- `ShellHookExecutorService::execute()` ≤79 LOC
- Проверить и убрать `@todo 2026-05-21: PHPMD bug` из `ExecuteAgentStepService.php` и `RunStaticChainService.php`

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/ChainExecution/Infrastructure/Service/Chain/Hook/ShellHookExecutorService.php`, `src/Module/ChainExecution/Domain/Service/Static/ExecuteAgentStepService.php`, `src/Module/ChainExecution/Domain/Service/Static/RunStaticChainService.php`
*   **Текущее поведение:** ShellHookExecutorService::execute() = 107 LOC; @todo о PHPMD multi-file analysis bug
*   **Границы (Out of Scope):** не меняем hook execution semantics, не трогаем ExecuteAgentStepService::run() если он уже ≤80 LOC

## 3. Requirements (MoSCoW)
### 🔴 Must Have
- [ ] `ShellHookExecutorService::execute()` ≤79 LOC
- [ ] Проверить `ExecuteAgentStepService::run()` — если multi-file PHPMD bug устранён, убрать `@todo`
- [ ] Проверить `RunStaticChainService::processStep()` — аналогично
- [ ] Все тесты проходят
- [ ] Удалить 1 запись из `phpmd.baseline.xml` (ShellHookExecutor)

### 🟡 Should Have
- [ ] Если `@todo` подтвердился (PHPMD bug воспроизводится) — уточнить статус и оставить

### ⚫ Won't Have (Не будем делать)
- Изменение hook execution контракта
- Изменение порогов в phpmd.xml

## 4. Implementation Plan
*Заполняется исполнителем.*

## 5. Definition of Done
- [ ] `phpmd` не ругается на ShellHookExecutorService
- [ ] `@todo` в ExecuteAgentStepService и RunStaticChainService обработан (убран или обновлён)
- [ ] `make check` зелёный

## 6. Verification
```bash
make phpmd
make check
```

## 7. Risks and Dependencies
- `ShellHookExecutorService::execute()` работает с `proc_open` — сложный flow с subprocess management

## 8. Sources
- `src/Module/ChainExecution/Infrastructure/Service/Chain/Hook/ShellHookExecutorService.php`
- `src/Module/ChainExecution/Domain/Service/Static/ExecuteAgentStepService.php`
- `src/Module/ChainExecution/Domain/Service/Static/RunStaticChainService.php`

## Инструкции для сабагента

**Режим работы:** эпик-ветка `refactor/phpmd-baseline-elimination` напрямую (без подветки/PR) — единый эпик-PR в конце.

**Контекст от Тимлида (resolved):** «PHPMD bug» из @todo — это НЕ баг PHPMD. Корень — застарелый кэш PDepend в `~/.cache/pdepend`. После очистки кэша:
- `ExecuteAgentStepService::run()` — реальный размер **61 LOC**, нарушения НЕТ → запись в baseline **ложная**. Удалить baseline-запись + убрать `@todo`.
- `RunStaticChainService::processStep()` — нарушения нет, в baseline НЕТ → просто убрать `@todo`.
- Перед `make check` очисти кэш: `rm -rf ~/.cache/pdepend`.

**Порядок:**
1. Активна ветка `refactor/phpmd-baseline-elimination`.
2. Рефакторинг `ShellHookExecutorService::execute()` (107→≤79 LOC) экстракцией приватных методов, БЕЗ изменения hook execution семантики (proc_open flow: открытие процесса, чтение stdout/stderr/pipes, закрытие, обработка exit-code).
3. Убрать `@todo 2026-05-21` из `ExecuteAgentStepService.php` и `RunStaticChainService.php`.
4. Удалить из `phpmd.baseline.xml`: `ExecuteAgentStepService::run` (ложная) и `ShellHookExecutorService::execute` (после рефакторинга).
5. `rm -rf ~/.cache/pdepend && make check` зелёный. Коммить (Conventional Commits, scope `Hook`/`ChainExecution`).
6. `git push`.

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-21 | Тимлид (Алекс) | Создание задачи |
| 2026-06-14 | Тимлид (Алекс) | Reverse Briefing: статус → in_progress, исполнитель Левша, работа в эпик-ветке; @todo-вопрос разрешён (корень = кэш PDepend) |
