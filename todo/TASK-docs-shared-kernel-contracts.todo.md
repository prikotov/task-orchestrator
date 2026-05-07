---
type: docs
created: 2026-05-06
value: V1
complexity: C1
priority: P2
depends_on: TASK-refactor-cross-module-dependencies
epic: EPIC-refactor-responsibility-decomposition
author: Тимлид Алекс
assignee:
branch: task/docs-shared-kernel-contracts
pr:
status: in_progress
---

# TASK-docs-shared-kernel-contracts: ADR на межмодульное взаимодействие через Application

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда модули взаимодействуют через foreign Application (QueryHandler/CommandHandler), а Domain каждого модуля изолирован, я хочу задокументировать эту архитектурную модель, чтобы команда принимала осознанные решения при добавлении новых модулей.

### Goal (Цель по SMART)
Создать ADR, фиксирующий модель межмодульного взаимодействия: Integration → foreign Application. Обновить `docs/guide/architecture.md`.

## 2. Context and Scope (Контекст и Границы)

### Где делаем
**Создаётся:** `docs/adr/` — новый ADR
**Обновляется:** `docs/guide/architecture.md` — матрица зависимостей

### Текущее поведение
После TASK-refactor-cross-module-dependencies:
- Каталог `Domain\Contract\` упразднён
- Integration-сервисы обращаются к foreign Application, не к foreign Domain
- Infrastructure реализует только Port'ы своего модуля
- Deptrac: 0 violations

### Границы (Out of Scope)
- ❌ Не меняем код (только документация)

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] Создан ADR: модель межмодульного взаимодействия
- [ ] ADR содержит: контекст, решение, примеры Integration→foreign Application, критерии пересмотра
- [ ] `docs/guide/architecture.md` обновлён

### ⚫ Won't Have (Не будем делать)
- Не проводим рефакторинг
- Не создаём отдельный модуль

## 4. Implementation Plan (План реализации)

1. [ ] Создать ветку `task/docs-shared-kernel-contracts` от `main`
2. [ ] Создать ADR: «Межмодульное взаимодействие через Application»
3. [ ] Обновить `docs/guide/architecture.md`

## 5. Definition of Done (Критерии приёмки)
- [ ] ADR создан, проходит review
- [ ] `docs/guide/architecture.md` обновлён

## 6. Verification (Самопроверка)
```bash
# Docs-only — проверки пропущены
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Зависимость:** TASK-refactor-cross-module-dependencies — желательно завершить до документирования

## 8. Sources (Источники)
- [Решение Гэндальфа](../docs/agents/reports/system-architect/2026-05-06_16-00_cross-module-dependencies-solution.md)
- [Критический разбор Локи](../docs/agents/reports/system-architect/2026-05-06_17-00_critical-review-cross-module-solution.md)

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-06 | Тимлид Алекс | Создание задачи |
| 2026-05-06 | Тимлид Алекс | Переформулировка: Domain\Contract\ упраздняется, фокус на ADR по межмодульному взаимодействию |
