---
type: docs
created: 2026-05-02
value: V3
complexity: C1
priority: P0
depends_on:
epic: EPIC-sprint-9-security-policy
author: system_analyst (Шерлок)
assignee:
branch:
pr:
status: todo
---

# TASK-docs-security-policy-adr: ADR-010 — Security Policy Architecture

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как архитектор, я хочу зафиксировать в ADR архитектурное решение по Security Policy (модель rules → permissions → execution, модульная структура, точки интеграции), чтобы команда имела единый источник правды при реализации Sprint 9.

### Goal (Цель по SMART)
Создать ADR-010 в `docs/adr/010-security-policy-architecture.md`, фиксирующий: (1) SecurityPolicy как отдельный модуль, (2) Dependency Inversion через ports в Orchestrator Domain, (3) Decorator pattern для интеграции, (4) exec policy model (declarative rules), (5) permission system (allow/deny per resource), (6) точки расширения (LLM Guardian, Docker sandbox).

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/adr/010-security-policy-architecture.md` (новый файл)
*   **Текущее поведение:** Анализ Локи (`docs/releases/security-policy-cross-cutting-analysis.md`) содержит эскиз архитектуры, но не зафиксирован как ADR
*   **Границы (Out of Scope):**
    *   Не менять существующие ADR (ADR-006, ADR-007, ADR-008, ADR-009)
    *   Не создавать код — только документация
    *   Не описывать YAML DSL (это в отдельной задаче)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] ADR-010 создан по формату проекта (Context, Decision, Consequences, Alternatives)
- [ ] Зафиксировано: SecurityPolicy — отдельный модуль (`src/Module/SecurityPolicy/`)
- [ ] Зафиксирована модель: Exec Rules → Permission Check → Execution
- [ ] Зафиксирован Dependency Inversion: ports в `Orchestrator/Domain/Service/Security/`, реализация в `SecurityPolicy/Infrastructure/Orchestrator/`
- [ ] Зафиксирован Decorator pattern: `SecurityPolicyRunAgentDecorator`, `SecurityPolicyExecutionStrategyDecorator`
- [ ] Описаны 6 точек вмешательства Security Policy (из анализа Локи, секция 2)
- [ ] Описаны альтернативы: ACL vs Decorator, Shared Kernel vs Domain Ports
- [ ] Описаны последствия: Deptrac конфигурация для 3+ модулей, DI wiring

### 🟡 Should Have (Желательно)
- [ ] Эскиз YAML DSL `permissions:` block (для связи с Task 5)
- [ ] Эскиз Exec policy file формата

### 🟢 Could Have (Опционально)
- [ ] Mermaid-диаграмма потока данных с decorators

### ⚫ Won't Have (Не будем делать)
- [ ] Код реализации
- [ ] Тесты
- [ ] YAML DSL спецификация (детальная — в Task 5)

## 4. Implementation Plan (План реализации)
1. [ ] Создать `docs/adr/010-security-policy-architecture.md`
2. [ ] Заполнить ADR по формату: Context → Decision → Consequences → Alternatives
3. [ ] Включить выводы из анализа Локи: G4 НЕ срабатывает, permission model единая
4. [ ] Описать interfaces: `ChainSecurityPolicyInterface`, `ExecPolicyInterface`
5. [ ] Описать структуру модуля SecurityPolicy (Domain/Application/Infrastructure)

## 5. Definition of Done (Критерии приёмки)
- [ ] ADR-010 создан в `docs/adr/`
- [ ] ADR содержит все Must Have пункты
- [ ] ADR согласовывает с анализом Локи и brainstorm #2 решениями
- [ ] Ссылка на ADR-010 добавлена в Roadmap

## 6. Verification (Самопроверка)
```bash
# Проверка — docs-only, автотесты не нужны
cat docs/adr/010-security-policy-architecture.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск:** ADR может потребовать итераций при реализации Domain (Task 2). Митигация: ADR фиксирует direction, детали уточняются в code.
- **Зависимости:** нет — это первая задача в цепочке.

## 8. Sources (Источники)
- [ ] [Security Policy Cross-Cutting Analysis](../../docs/releases/security-policy-cross-cutting-analysis.md)
- [ ] [ADR-006: ExecutionStrategy Composition](../../docs/adr/006-execution-strategy-composition.md)
- [ ] [ADR-008: Shared Kernel Contract](../../docs/adr/008-shared-kernel-contract.md)
- [ ] [Протокол brainstorm #2](../../var/sessions/brainstorm/2026-04-30_16-02-26/result.md)
- [ ] [Roadmap 2026 Q2–Q3: Sprint 9](../../docs/releases/ROADMAP-2026-Q2-Q3.md)

## 9. Comments (Комментарии)
ADR — foundation task. Все последующие задачи ссылаются на него. Рекомендуется быстрый review архитектором (Гэндальф).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-02 | system_analyst (Шерлок) | Создание задачи |
