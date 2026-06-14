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
branch: task/refactor-phpmd-dynamicloop-methods
pr:
status: in_progress
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
- [x] `runParticipantTurn()` ≤79 LOC
- [x] `runFacilitatorStep()` ≤79 LOC
- [x] Все тесты проходят
- [x] Удалить 2 записи из `phpmd.baseline.xml`

### ⚫ Won't Have (Не будем делать)
- Изменение dynamic loop контракта
- Изменение порогов в phpmd.xml

## 4. Implementation Plan

Выполнено (Бэкендер Левша):

1. **`runParticipantTurn`** (82 → 61 LOC): экстракция блока записи журналов/сессионных файлов в приватный метод `recordParticipantTurnJournals()`.
2. **`runFacilitatorStep`** (81 → 70 LOC): экстракция вызова агент-фасилитатора в приватный метод `callFacilitatorAgent()`.
3. Удалены 2 записи из `phpmd.baseline.xml`.
4. Исправлена битая ссылка на Конвенции (была `../../`, стала `../`).
5. Поведение не изменено — чистая экстракция без семантических изменений.

## 5. Definition of Done
- [x] `phpmd` не ругается на ExecuteDynamicTurnService
- [x] `make check` зелёный

## 6. Verification
```bash
make phpmd
make check
```

## 7. Risks and Dependencies
- Нарушения минимальные (82 и 81) — экстракция должна быть простой

## 8. Sources
- `src/Module/DynamicLoop/Domain/Service/Dynamic/ExecuteDynamicTurnService.php`

## Инструкции для сабагента

**Ветка:** `task/refactor-phpmd-dynamicloop-methods` (уже создана от `refactor/phpmd-baseline-elimination` и активна)
**PR:** уже создан (draft) из `task/refactor-phpmd-dynamicloop-methods` в `refactor/phpmd-baseline-elimination` — [PR #260](https://github.com/prikotov/task-orchestrator/pull/260)

### Порядок действий
1. Переключись в ветку `task/refactor-phpmd-dynamicloop-methods`: `git checkout task/refactor-phpmd-dynamicloop-methods`.
2. Реализуй задачу согласно описанию выше: уменьшить `runParticipantTurn()` и `runFacilitatorStep()` до ≤79 LOC экстракцией приватных методов, **не меняя поведение**.
3. Следуй [Конвенциям](../docs/conventions/index.md) проекта и AGENTS.md.
4. Делай промежуточные коммиты после каждого логического этапа (Conventional Commits, scope `DynamicLoop`).
5. После реализации запусти проверки: `make check`. Должен быть зелёным.
6. Сделай `git push`.
7. Переведи PR из draft в ready: `gh pr ready 260`.

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-21 | Тимлид (Алекс) | Создание задачи |
| 2026-06-13 | Тимлид (Алекс) | Reverse Briefing: статус → in_progress, назначен исполнитель (Бэкендер Левша), создана подветка от эпик-ветки |
| 2026-06-14 | Бэкендер (Левша) | Реализация: экстракция `recordParticipantTurnJournals` и `callFacilitatorAgent`, удаление 2 записей из phpmd.baseline.xml, `make check` зелёный |
