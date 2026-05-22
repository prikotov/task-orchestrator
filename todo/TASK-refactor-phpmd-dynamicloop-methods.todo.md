---
type: refactor
created: 2026-05-21
value: V2
complexity: C2
priority: P2
depends_on:
epic: EPIC-refactor-phpmd-baseline-elimination
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: todo
---

# TASK-refactor-phpmd-dynamicloop-methods: Устранить LongMethod в ExecuteDynamicTurnService

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
Два метода превышают порог на 2-3 строки (82 и 81 LOC при пороге 80).

### Варианты или путь решения (Solution Sketch)
Экстракция приватных методов.

### Ожидаемый результат (Expected Result)
PHPMD baseline пуст, `make phpmd-full` = 0 violations.

## 1. Concept and Goal (Концепция и Цель)
### Story
Как разработчик, я хочу чтобы `runParticipantTurn()` (82 LOC) и `runFacilitatorStep()` (81 LOC) в `ExecuteDynamicTurnService` были ≤79 LOC.

### Goal
Уменьшить оба метода до ≤79 LOC через экстракцию приватных методов.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/DynamicLoop/Domain/Service/Dynamic/ExecuteDynamicTurnService.php`
*   **Текущее поведение:** 2 метода превышают порог на 2-3 строки
*   **Границы (Out of Scope):** не меняем dynamic loop семантику

## 3. Requirements (MoSCoW)
### 🔴 Must Have
- [ ] `runParticipantTurn()` ≤79 LOC
- [ ] `runFacilitatorStep()` ≤79 LOC
- [ ] Все тесты проходят
- [ ] Удалить 2 записи из `phpmd.baseline.xml`

### ⚫ Won't Have (Не будем делать)
- Изменение dynamic loop контракта
- Изменение порогов в phpmd.xml

## 4. Implementation Plan
*Заполняется исполнителем.*

## 5. Definition of Done
- [ ] `phpmd` не ругается на ExecuteDynamicTurnService
- [ ] `make check` зелёный

## 6. Verification
```bash
make phpmd
make check
```

## 7. Risks and Dependencies
- Нарушения минимальные (82 и 81) — экстракция должна быть простой

## 8. Sources
- `src/Module/DynamicLoop/Domain/Service/Dynamic/ExecuteDynamicTurnService.php`

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-21 | Тимлид (Алекс) | Создание задачи |
