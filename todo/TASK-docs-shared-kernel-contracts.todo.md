---
type: docs
created: 2026-05-06
value: V1
complexity: C1
priority: P2
depends_on: TASK-refactor-integration-layer-violations
epic: EPIC-refactor-responsibility-decomposition
author: Тимлид Алекс
assignee:
branch:
pr:
status: todo
---

# TASK-docs-shared-kernel-contracts: Документирование статуса общих контрактов ChainExecution.Domain.Contract

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда контракты `ChainExecution\Domain\Contract\` (`AuditLoggerInterface`, `RunAgentServiceInterface`, `PromptProviderInterface`) реализуются в нескольких модулях (ChainExecution.Infrastructure + DynamicLoop.Infrastructure), я хочу задокументировать их архитектурный статус, чтобы команда понимала, является ли это Shared Kernel, и принимала осознанные решения при добавлении новых модулей.

### Goal (Цель по SMART)
Создать ADR, фиксирующий статус `ChainExecution.Domain.Contract` — является ли он Shared Kernel, какие интерфейсы общие, критерии пересмотра. Обновить `docs/guide/architecture.md`.

## 2. Context and Scope (Контекст и Границы)

### Где делаем
**Создаётся:** `docs/adr/` — новый ADR на Shared Kernel / общие контракты
**Обновляется:** `docs/guide/architecture.md` — матрица зависимостей

### Текущее поведение
`ChainExecution\Domain\Contract\` содержит интерфейсы, которые реализуются в двух модулях:
- `AuditLoggerInterface` → ChainExecution.Infrastructure + DynamicLoop.Infrastructure
- `RunAgentServiceInterface` → ChainExecution.Infrastructure + DynamicLoop.Infrastructure
- `PromptProviderInterface` → ChainExecution.Infrastructure + DynamicLoop.Infrastructure

Это de facto Shared Kernel, но не документировано. Каждый новый модуль, реализующий эти контракты, будет порождать новые Deptrac-violations и ad-hoc исключения.

### Границы (Out of Scope)
- ❌ Не меняем код (только документация)
- ❌ Не создаём отдельный Shared Kernel-модуль
- ❌ Не выделяем контракты в отдельный namespace (может быть following task)

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] Создан ADR: статус `ChainExecution.Domain.Contract` (Shared Kernel или нет)
- [ ] ADR содержит: контекст, решение, список общих интерфейсов, критерии пересмотра
- [ ] `docs/guide/architecture.md` обновлён: отмечены общие контракты

### 🟡 Should Have (Желательно)
- [ ] ADR содержит диаграмму: какие модули реализуют какие контракты

### ⚫ Won't Have (Не будем делать)
- Не проводим рефакторинг контрактов
- Не создаём отдельный модуль

## 4. Implementation Plan (План реализации)

1. [ ] Создать ветку `task/docs-shared-kernel-contracts` от `main`
2. [ ] Создать ADR: «Общие контракты ChainExecution.Domain.Contract»
   - Контекст: контракты реализуются в 2+ модулях
   - Варианты: (A) признать Shared Kernel, (B) перенести контракты в потребляющие модули, (C) выделить отдельный модуль
   - Решение: документировать текущее состояние + критерии пересмотра
   - Список общих интерфейсов с указанием модулей-реализаторов
3. [ ] Обновить `docs/guide/architecture.md`: отметить Shared Kernel-контракты в матрице зависимостей

## 5. Definition of Done (Критерии приёмки)
- [ ] ADR создан, проходит review
- [ ] `docs/guide/architecture.md` обновлён

## 6. Verification (Самопроверка)
```bash
# Docs-only — проверки пропущены
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Зависимость:** TASK-refactor-integration-layer-violations — желательно завершить до документирования, чтобы зафиксировать финальное состояние

## 8. Sources (Источники)
- [Критический анализ Архитектора Локи](../docs/agents/reports/system-architect/2026-05-06_12-00_critical-review-deptrac-violations.md) — слепая зона #3: «Общие порты — скрытый Shared Kernel»
- [`docs/guide/architecture.md`](../docs/guide/architecture.md)

## 9. Comments (Комментарии)
Архитектор Локи выявил, что `ChainExecution.Domain.Contract` является de facto Shared Kernel — его контракты обслуживают несколько Bounded Context'ов. Гэндальф упомянул это, но отмахнулся. Локи обоснованно настаивает на документировании этого архитектурного решения.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-06 | Тимлид Алекс | Создание задачи |
