---
type: refactor
created: 2026-06-14
value: V2
complexity: C3
priority: P3
depends_on:
epic: EPIC-refactor-phpmd-baseline-elimination
author: Тимлид (Алекс)
assignee: Бэкендер (Левша)
branch: refactor/phpmd-baseline-elimination
pr: "261"
status: done
---

# TASK-refactor-phpmd-dynamicloop-aggregate: Редизайн DynamicLoopExecution под пороги PHPMD

> Изначально — follow-up эпика `EPIC-refactor-phpmd-baseline-elimination` (вынесен за scope). **Возвращён в scope эпика и выполнен в PR #261** по решению пользователя (2026-06-15): «осталось часть ошибок в phpmd.baseline.xml» — устранить все оставшиеся записи.

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
- [x] Архитектурный дизайн редизайна (Архитектор Локи): мини-ADR с 6 развилками — выделить 2 владеемых мутабельных компонента `DynamicLoopMetrics` + `DynamicLoopJournal` (Domain\Entity, NOT ValueObject).
- [x] `DynamicLoopExecution` 11 fields (≤12 ✓) и 9 counted public methods (≤10 ✓).
- [x] Все тесты проходят (PHPUnit 1032/2888).
- [x] Удалить 2 записи из `phpmd.baseline.xml` (baseline полностью пустой).

### ⚫ Won't Have (Не будем делать)
- Изменение бизнес-поведения динамического цикла.
- Изменение порогов в phpmd.xml.

## 4. Implementation Plan
1. [x] Архитектор (Локи): мини-ADR `docs/agents/reports/system-architect/2026-06-15_18-00_dynamicloop-aggregate-redesign.md` — 6 развилок (owned mutable components в Domain\Entity, NOT Vo; группировка полей; контракт callers).
2. [x] Бэкендер (Левша): реализация — `DynamicLoopMetrics` (6 fields, 3 counted) + `DynamicLoopJournal` (3 fields, 4 counted); `DynamicLoopExecution` rewrite (9 fields удалено→2 компонента, 5 write-методов удалено, read/set delegates сохранены); 5 callers переведены на `getMetrics()`/`getJournal()`; 2 unit-теста + обновлён `DynamicLoopExecutionMaxTimeTest`. Отчёт `docs/agents/reports/backend-developer/2026-06-15_18-14_dynamicloop-aggregate-implementation.md`.
3. [x] Тимлид (Алекс): sanity-check (toLoopResultVo byte-to-byte, полей 11/≤12) + закрытие REMARK E.3 (контрактный unit-тест `DynamicLoopExecutionResultMappingTest` на cross-component поток metrics → toLoopResultVo).
4. [x] Ревьювер (Пуаро): APPROVE, все секции A-F PASS, byte-to-byte сверка с оригиналом HEAD. Отчёт `docs/agents/reports/code-reviewer-backend/2026-06-15_18-25_dynamicloop-aggregate-review.md`.

## 5. Definition of Done
- [x] `phpmd` не ругается на `DynamicLoopExecution` (и на `DynamicLoopMetrics`/`DynamicLoopJournal`)
- [x] `make check` зелёный (PHPUnit 1032/2888, Psalm 0 errors, Deptrac 0 violations, PHPMD 0 violations)
- [x] 2 записи убраны из baseline (baseline полностью пустой)

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
- `src/Module/DynamicLoop/Domain/Entity/DynamicLoopMetrics.php` (новый)
- `src/Module/DynamicLoop/Domain/Entity/DynamicLoopJournal.php` (новый)
- `phpmd.baseline.xml` (теперь пустой)
- `EPIC-refactor-phpmd-baseline-elimination.todo.md` (контекст эпика, та же папка `todo/done/`)

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-14 | Тимлид (Алекс) | Создание follow-up задачи (Q-B эпика решён: вынос за scope) |
| 2026-06-15 | Тимлид (Алекс) | Возвращена в scope эпика (решение пользователя «устранить все оставшиеся записи»). Status → done после полного конвейера Локи→Левша→Пуаро. PR #261. |
