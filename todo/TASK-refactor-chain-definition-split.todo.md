---
type: refactor
created: 2026-04-29
value: V3
complexity: C2
priority: P2
depends_on:
epic: EPIC-refactor-orchestrator-decomposition
author: Тимлид (Алекс)
assignee: Бэкендер (Левша)
branch: task/chain-definition-split
pr:
status: review
---

# TASK-refactor-chain-definition-split: Расщепление ChainDefinitionVo (ADR-008)

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда ChainDefinitionVo содержит 20+ параметров (static + dynamic + общие), я хочу расщепить его на SharedChainDefinitionVo + стратегийно-специфичные части, чтобы каждый consumer получал только нужные ему данные.

### Goal (Цель по SMART)
Создать `SharedChainDefinitionVo` (chain identity: name, type, budget, roles, timeout). Static- и Dynamic-специфичные поля остаются в `ChainDefinitionVo` с `getSharedDefinition()`. Back-compat change, deprecated геттеры.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/Orchestrator/Domain/ValueObject/`
*   **ADR:** [ADR-008](../../docs/adr/008-shared-kernel-contract.md) — Shared Kernel = chain identity only
*   **Границы (Out of Scope):**
    *   Не создаём отдельные StaticChainDefinitionVo / DynamicChainDefinitionVo (YAGNI до conditional branching)
    *   Не меняем ExecutionStrategy (отдельная задача)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] `SharedChainDefinitionVo` создан (name, type, budget, roles, timeout)
- [x] `ChainDefinitionVo::getSharedDefinition()` добавлен
- [x] Старые геттеры shared-полей помечены `@deprecated`
- [x] Все тесты проходят

### ⚫ Won't Have (Не будем делать)
- [ ] Физическое расщепление на подклассы

## 4. Implementation Plan (План реализации)
1. [x] Создать `SharedChainDefinitionVo` (immutable, readonly)
2. [x] Добавить `getSharedDefinition()` в `ChainDefinitionVo`
3. [x] Пометить shared-геттеры `@deprecated`
4. [x] Адаптировать тесты
5. [x] Запустить проверки

## 5. Definition of Done (Критерии приёмки)
- [x] `SharedChainDefinitionVo` создан
- [x] `ChainDefinitionVo::getSharedDefinition()` работает
- [x] Все тесты проходят

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
php vendor/bin/phpcs --standard=phpcs.xml.dist src/
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Зависимость:** Может потребовать адаптации после ExecutionStrategy (TASK-refactor-execution-strategy)

## 8. Sources (Источники)
- [ ] [ADR-008: Shared Kernel Contract](../../docs/adr/008-shared-kernel-contract.md)

## 9. Comments (Комментарии)
ADR-008 определил Shared Kernel как chain identity (name, budget, roles). OCP через sub-интерфейсы.

Action item из brainstorm-протокола. P2 — зависит от ExecutionStrategy.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-29 | Тимлид (Алекс) | Создание задачи |
