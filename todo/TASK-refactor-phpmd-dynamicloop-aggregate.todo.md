---
type: refactor
created: 2026-06-14
value: V2
complexity: C3
priority: P3
depends_on:
epic:
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: todo
---

# TASK-refactor-phpmd-dynamicloop-aggregate: Редизайн DynamicLoopExecution под пороги PHPMD

> Follow-up эпика `EPIC-refactor-phpmd-baseline-elimination`. Вынесен за scope эпика, т.к. требует архитектурного редизайна domain-агрегата, а не механической экстракции.

## 0. Простое описание (Human Brief)
Устранить 2 PHPMD violation на domain-агрегате `DynamicLoopExecution`.

### Проблема простыми словами (Problem)
Агрегат `DynamicLoopExecution` содержит слишком много публичных методов (14 при пороге 10) и полей (18 при пороге 12). Suppression в baseline маскирует проблему.

### Варианты или путь решения (Solution Sketch)
Архитектурный редизайн: выделение подчинённых VO (состояние выполнения, прогресс, журналы), перенос поведения в domain-сервисы. Требует отдельного проектирования Архитектором.

### Ожидаемый результат (Expected Result)
`DynamicLoopExecution` ≤10 public methods и ≤12 fields; 2 записи убраны из `phpmd.baseline.xml`.

## 1. Concept and Goal (Концепция и Цель)
### Story
Как разработчик, я хочу, чтобы агрегат `DynamicLoopExecution` соответствовал порогам PHPMD (TooManyPublicMethods, TooManyFields), чтобы он оставался поддерживаемым и не превращался в «god object».

### Goal
- `DynamicLoopExecution` ≤10 public methods (сейчас 14) и ≤12 fields (сейчас 18).
- Удалить 2 записи из `phpmd.baseline.xml`.
- Поведение динамического цикла сохранено (тесты зелёные).

## 2. Context and Scope (Контекст и Границы)
* **Где делаем:** `src/Module/DynamicLoop/Domain/Entity/DynamicLoopExecution.php`
* **Текущее поведение:** 14 public methods, 18 fields.
* **Реальные замеры (Тимлид, стабильный набор phpmd 3/3):** TooManyPublicMethods 14/10, TooManyFields 18/12.
* **Границы (Out of Scope):** не меняем public-контракт агрегата без явного решения; не меняем пороги phpmd.xml.

## 3. Requirements (MoSCoW)
### 🔴 Must Have
- [ ] Архитектурный дизайн редизайна (Архитектор): какие VO/сервисы выделить.
- [ ] `DynamicLoopExecution` ≤10 public methods и ≤12 fields.
- [ ] Все тесты проходят.
- [ ] Удалить 2 записи из `phpmd.baseline.xml`.

### ⚫ Won't Have (Не будем делать)
- Изменение бизнес-поведения динамического цикла.
- Изменение порогов в phpmd.xml.

## 4. Implementation Plan
*Заполняется после архитектурного дизайна.*

## 5. Definition of Done
- [ ] `phpmd` не ругается на `DynamicLoopExecution`
- [ ] `make check` зелёный
- [ ] 2 записи убраны из baseline

## 6. Verification
```bash
make phpmd   # 3 прогона (флакучесть — см. эпик)
make check
```

## 7. Risks and Dependencies
- `DynamicLoopExecution` — центральный агрегат домена DynamicLoop; редизайн затронет много потребителей.
- Требует согласования с Архитектором (Гэндальф/Локи) до реализации.

## 8. Sources
- `src/Module/DynamicLoop/Domain/Entity/DynamicLoopExecution.php`
- `phpmd.baseline.xml` (2 записи для этого файла)
- `todo/done/EPIC-refactor-phpmd-baseline-elimination.todo.md` (контекст эпика)

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-14 | Тимлид (Алекс) | Создание follow-up задачи (Q-B эпика решён: вынос за scope) |
