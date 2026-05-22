---
type: fix
created: 2026-05-21
value: V2
complexity: C1
priority: P2
depends_on:
epic: EPIC-refactor-phpmd-baseline-elimination
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: todo
---

# TASK-fix-phpmd-errorclassificationvo: Устранить UnusedFormalParameter в ErrorClassificationVo

## 0. Простое описание (Human Brief)
Устранить PHPMD violation (technical debt), чтобы код соответствовал порогам проекта.

### Проблема простыми словами (Problem)
Метод или класс превышает порог PHPMD, suppression в baseline маскирует проблему.

### Варианты или путь решения (Solution Sketch)
Экстракция приватных методов или рефакторинг для уменьшения LOC.

### Ожидаемый результат (Expected Result)
PHPMD baseline пуст, `make phpmd-full` = 0 violations.

## 0. Простое описание (Human Brief)
Устранить PHPMD violation (technical debt), чтобы код соответствовал порогам проекта.

### Проблема простыми словами (Problem)
Параметр `$throwable` в `createFromClassException()` не используется.

### Варианты или путь решения (Solution Sketch)
Использовать `$throwable` для классификации или убрать параметр.

### Ожидаемый результат (Expected Result)
PHPMD baseline пуст, `make phpmd-full` = 0 violations.

## 1. Concept and Goal (Концепция и Цель)
### Story
Как разработчик, я хочу чтобы `ErrorClassificationVo::createFromClassException()` не имел неиспользуемого параметра `$throwable`.

### Goal
Использовать параметр `$throwable` в методе `createFromClassException()` или убрать его из сигнатуры (с обновлением всех вызовов).

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/AgentRunner/Domain/ValueObject/ErrorClassificationVo.php:62`
*   **Текущее поведение:** параметр `$throwable` типа `Throwable` передан, но не используется в теле метода
*   **Границы (Out of Scope):** не меняем классификацию ошибок

## 3. Requirements (MoSCoW)
### 🔴 Must Have
- [ ] PHPMD не ругается на UnusedFormalParameter
- [ ] Все тесты проходят
- [ ] Удалить запись из `phpmd.baseline.xml`

### ⚫ Won't Have (Не будем делать)
- Изменение классификации ошибок
- Изменение порогов в phpmd.xml

## 4. Implementation Plan
*Заполняется исполнителем. Варианты:*
1. Использовать `$throwable` для классификации (например, получить FQCN исключения)
2. Убрать параметр, если он не нужен по смыслу

## 5. Definition of Done
- [ ] `phpmd` не ругается на ErrorClassificationVo
- [ ] `make check` зелёный

## 6. Verification
```bash
make phpmd
make check
```

## 7. Risks and Dependencies
- Нужно проверить все вызовы `createFromClassException` — если где-то передаётся `$throwable`, сигнатура должна быть совместимой

## 8. Sources
- `src/Module/AgentRunner/Domain/ValueObject/ErrorClassificationVo.php`

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-21 | Тимлид (Алекс) | Создание задачи |
