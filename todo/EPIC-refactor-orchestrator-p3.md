---
type: epic
created: 2026-04-30
value: V3
complexity: C3
priority: P2
author: pi
assignee:
branch: task/epic-refactor-orchestrator-p3
pr:
status: in_progress
---

# EPIC-refactor-orchestrator-p3: P3 Декомпозиция + Аналитика + Static Split

## 1. Concept and Goal (Концепция и цель)
### Story (Job Story)
> Когда P1 и P2 завершены (ExecutionStrategy, CommandHandler rewrite, ChainDefinitionVo split), а brainstorm #2 подтвердил необходимость физического split Static в Sprint 7, я хочу завершить декомпозицию Orchestrator и вынести StaticExecution в отдельный модуль, чтобы подготовить архитектуру к Conditional Branching (Sprint 8).

### Goal (Цель по SMART)
Завершить P3 декомпозицию God-объектов (RunDynamicLoopService 786 LOC, ChainSessionLogger 536 LOC), провести аналитику (инвентаризация, security-анализ) и физически вынести StaticExecution в отдельный модуль. Критерий успеха: Integration-паттерн задокументирован для G6-валидации в Sprint 8.

## 2. Context and Scope (Контекст и границы)
*   **In Scope:**
    *   Инвентаризация Domain-слоя Orchestrator (AI#13)
    *   Security Policy анализ (AI#14)
    *   Декомпозиция RunDynamicLoopService (AI#11)
    *   Расщепление ChainSessionLogger (AI#15)
    *   Переразложение Shared/ каталога (AI#16)
    *   Физический split StaticExecution (AI#17)
    *   Интеграционное тестирование P2 (Sprint 5)
*   **Out of Scope:**
    *   Conditional Branching (Sprint 8)
    *   Security Policy implementation (Sprint 9)
    *   Error handling, hooks, sub-agents

### Предпосылки
- P1 + P2 завершены (EPIC-refactor-orchestrator-decomposition, PR #98–105)
- Brainstorm #2 (2026-04-30): Static/ и Dynamic/ — 0 cross-imports, компромисс Sprint 7
- Roadmap: Sprint 5–7

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] RunDynamicLoopService ≤ 200 LOC (координатор)
- [ ] ChainSessionLogger расщеплён на 4 класса
- [ ] Shared/ содержит только общие интерфейсы
- [ ] StaticExecution — отдельный модуль, Deptrac green на 3 модулях
- [ ] Integration-слой Orchestrator ↔ StaticExecution < 200 LOC
- [ ] Domain-инвентаризация и Security-анализ завершены

### 🟡 Should Have (Желательно)
- [ ] Deptrac rules для Integration-слоя
- [ ] Integration-тесты P2 Strategy pattern

### ⚫ Won't Have (Не будем делать)
- [ ] Conditional Branching
- [ ] DynamicExecution split
- [ ] Security Policy module

## 4. Implementation Plan (План реализации)

### Sprint 5: Валидация P2
- [x] [TASK-chore-p2-integration-testing](TASK-chore-p2-integration-testing.todo.md) — Интеграционное тестирование Strategy pattern

### Sprint 2 (дополнительно): Аналитика
- [x] [TASK-docs-domain-inventory](TASK-docs-domain-inventory.todo.md) — Инвентаризация Domain-слоя Orchestrator (AI#13)
- [x] [TASK-docs-security-policy-analysis](TASK-docs-security-policy-analysis.todo.md) — Security Policy анализ (AI#14)

### Sprint 6: P3 Decomposition
- [x] [TASK-refactor-dynamic-loop-decomposition](TASK-refactor-dynamic-loop-decomposition.todo.md) — Декомпозиция RunDynamicLoopService (AI#11)

### Sprint 7: P3 Infrastructure + Static Split
- [x] [TASK-refactor-session-logger-split](TASK-refactor-session-logger-split.todo.md) — Расщепление ChainSessionLogger (AI#15)
- [x] [TASK-refactor-shared-reorg](TASK-refactor-shared-reorg.todo.md) — Переразложение Shared/ каталога (AI#16)
- [x] [TASK-refactor-static-execution-split](TASK-refactor-static-execution-split.todo.md) — Физический split StaticExecution (AI#17)

## 5. Definition of Done (Критерии приёмки эпика)
- [ ] Все 7 задач выполнены
- [ ] 3 модуля: AgentRunner, Orchestrator, StaticExecution — Deptrac green
- [ ] Integration-паттерн задокументирован для G6-валидации
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные
- [ ] Roadmap: все AI# обновлены (`✅ Done` + ссылки на задачи/PR), чекбоксы Sprint 5–7 отмечены `[x]`

## 6. Risks and Dependencies (Риски и зависимости)
- **R-6:** Integration-паттерн может не масштабироваться на Conditional Branching — Sprint 8 = точка валидации
- **Зависимость:** P1 + P2 завершены ✅

## 7. Sources (Источники)
- [ ] [Roadmap 2026 Q2–Q3](../docs/releases/ROADMAP-2026-Q2-Q3.md)
- [ ] [Протокол brainstorm #2](../var/sessions/brainstorm/2026-04-30_16-02-26/result.md)
- [ ] [EPIC-refactor-orchestrator-decomposition (P1+P2)](done/EPIC-refactor-orchestrator-decomposition.md)

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-30 | pi | Создание эпика |
