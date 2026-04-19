---
type: docs
created: 2026-04-19
value: V4
complexity: C2
priority: P2
author: Тимлид (Алекс)
assignee: Тимлид (Алекс)
branch:
pr:
status: in_progress
assignee: Тимлид (Алекс)
branch: task/docs-architecture-sync-and-delegation
---

# TASK-docs-architecture-integration-sync: Актуализация архитектурной документации — Port/Adapter → Integration

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда разработчик читает архитектурную документацию, он видит описанные классы Port/Adapter (AgentRunnerPortInterface, AgentRunnerAdapter, AgentVoMapper), которых не существует в коде. Фактическая связь модулей — через Integration-слой (RunAgentService, AgentDtoMapper). Документация должна отражать реальную архитектуру (Clean Architecture / луковичная).

### Goal (Цель по SMART)
Убрать все упоминания несуществующих Port/Adapter-классов из документации и заменить на актуальное описание Integration-слоя. Обновить диаграммы, ADR и reliability-гайд.

## 2. Context and Scope (Контекст и Границы)
* **Где делаем:**
  * `docs/guide/architecture.md`
  * `docs/guide/diagrams.md`
  * `docs/guide/reliability.md`
  * `docs/adr/001-module-decomposition.md`
  * `docs/agents/team-reflections/team_lead/2026-04-17_12-00-00-orchestrator-decomposition.md`
* **Границы (Out of Scope):**
  * Конвенции (`docs/conventions/`) — там нет упоминаний Port/Adapter
  * AGENTS.md — не трогаем
  * Код — не трогаем

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Убрать упоминания AgentRunnerPortInterface, AgentRunnerRegistryPortInterface, AgentRunnerAdapter, AgentRunnerRegistryAdapter, AgentVoMapper из всех .md
- [ ] Заменить на актуальные: RunAgentServiceInterface, RunAgentService, AgentDtoMapper
- [ ] Обновить деревья каталогов (убрать Adapter/, добавить Integration/, Application/ у AgentRunner)
- [ ] Обновить таблицу зависимостей слоёв
- [ ] Обновить Mermaid-диаграммы: Component, Class, Sequence

### 🟡 Should Have (Желательно)
—
### 🟢 Could Have (Опционально)
—
### ⚫ Won't Have (Не будем делать)
—
## 4. Implementation Plan (План реализации)
1. [x] Актуализировать `docs/adr/001-module-decomposition.md`
2. [x] Актуализировать `docs/guide/architecture.md`
3. [x] Актуализировать `docs/guide/diagrams.md`
4. [x] Актуализировать `docs/guide/reliability.md`
5. [x] Обновить историческую запись в `docs/agents/team-reflections/`

## 5. Definition of Done (Критерии приёмки)
- [ ] grep по docs/ не находит AgentRunnerPortInterface, AgentRunnerAdapter, AgentVoMapper
- [ ] Диаграммы отображают Integration-слой
- [ ] Таблицы зависимостей корректны

## 6. Verification (Самопроверка)
```bash
grep -rn "AgentRunnerPortInterface\|AgentRunnerAdapter\|AgentVoMapper\|Hexagonal" docs/ --include="*.md"
# Ожидается: 0 совпадений (кроме исторических рефлексий)
```

## 7. Risks and Dependencies (Риски и зависимости)
—
