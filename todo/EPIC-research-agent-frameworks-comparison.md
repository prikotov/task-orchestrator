---
# Metadata (Метаданные)
type: epic
created: 2026-04-21
value: V3
complexity: C4
priority: P2
author: Тимлид (Алекс)
assignee:
branch: task/research-agent-frameworks-comparison
status: todo
pr:
---

# EPIC-research-agent-frameworks-comparison: Исследование AI-agent фреймворков и оркестраторов

## 1. Concept and Goal (Концепция и цель)
### Story (Job Story)
Когда мы развиваем архитектуру task-orchestrator, я хочу провести систематическое исследование AI-agent фреймворков и оркестраторов, чтобы понять лучшие паттерны оркестрации, обработки ошибок, state management — и определить, что стоит заимствовать, а от чего отказаться.

### Goal (Цель по SMART)
Исследовать 10+ AI-agent фреймворков и инструментов, составить единый сравнительный отчёт со сводной таблицей (модель оркестрации, state management, error handling, extensibility, применимость). По каждому — вердикт: заимствовать паттерны / использовать как dependency / не подходит. Отчёт в `docs/research/` до конца Q2 2026.

## 2. Context and Scope (Контекст и границы)
*   **In Scope (Что делаем):**
    *   Исследование каждого фреймворка/инструмента по единой методологии
    *   Индивидуальные comparison-отчёты в `docs/research/`
    *   Сводная таблица с классификацией и рекомендациями
*   **Out of Scope (Чего НЕ делаем):**
    *   Написание кода интеграции — только исследование
    *   Глубокий code review исходников — анализ на уровне архитектуры и паттернов

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Блокирующие требования)
- [ ] Каждый фреймворк исследован по единой методологии (модель оркестрации, state, error handling, extensibility)
- [ ] По каждому фреймворку создан отчёт в `docs/research/` по формату существующих comparison-документов
- [ ] Сводная таблица в `docs/research/agent-frameworks-summary.md` со всеми фреймворками
- [ ] Чёткий вердикт по каждому: заимствовать / dependency / не подходит

### 🟡 Should Have (Важные требования)
- [ ] Сравнительная таблица с группировкой по категориям (multi-agent, single-agent, cloud, meta-orchestration)
- [ ] Рекомендации по приоритетам заимствования паттернов

### 🟢 Could Have (Желательно)
- [ ] Визуализация (Mermaid-диаграммы) ключевых архитектурных различий

### ⚫ Won't Have (Не в этот раз)
- [ ] Код интеграции любого из фреймворков
- [ ] Performance-бенчмарки

## 4. Solution Design (Техническое решение)

Исследование проводится в два этапа:

**Этап 1 — Индивидуальные research-задачи:** каждая задача изучает один фреймворк (или группу), пишет отдельный comparison-документ **и заполняет свою строку** в сводной таблице `docs/research/agent-frameworks-summary.md`. Задачи независимы, могут выполняться параллельно.

**Этап 2 — Финализация:** после завершения всех индивидуальных исследований финальная задача проверяет полноту таблицы, выявляет тренды и составляет итоговые рекомендации.

Все отчёты размещаются в `docs/research/` рядом с уже существующими:
- `agent-bernstein-comparison.md`
- `agent-orchestrator-comparison.md`
- `superpowers-brainstorming-comparison.md`

**Сводная таблица** `docs/research/agent-frameworks-summary.md` создаётся заранее (пустой шаблон) и заполняется инкрементально — каждая задача Этапа 1 добавляет свою строку при выполнении.

## 5. Implementation Plan (План реализации)

### Этап 1: Индивидуальные исследования (параллельные)

- [x] [TASK-research-charmbracelet-crush](done/TASK-research-charmbracelet-crush.todo.md) — Charmbracelet Crush (Go, CLI-agent)
- [x] [TASK-research-pi-agent-rust](done/TASK-research-pi-agent-rust.todo.md) — pi_agent_rust (Rust)
- [x] [TASK-research-crewai-langgraph-autogen](done/TASK-research-crewai-langgraph-autogen.todo.md) — CrewAI, LangGraph, AutoGen (Python multi-agent)
- [x] [TASK-research-openhands-sdk](done/TASK-research-openhands-sdk.todo.md) — OpenHands SDK (Python, SDK-подход)
- [x] [TASK-research-archon-ai-planner](done/TASK-research-archon-ai-planner.todo.md) — Archon (Python, мета-оркестрация)
- [x] [TASK-research-metagpt-openclaw](done/TASK-research-metagpt-openclaw.todo.md) — MetaGPT, OpenClaw (Python, SOP/роли)
- [x] [TASK-research-mastra-ai](done/TASK-research-mastra-ai.todo.md) — Mastra AI (TypeScript, workflows)
- [x] [TASK-research-claude-code](done/TASK-research-claude-code.todo.md) — Claude Code (проприетарный, agent loop)
- [x] [TASK-research-copilot-agent-hq](done/TASK-research-copilot-agent-hq.todo.md) — GitHub Copilot Agent HQ (проприетарный, cloud)
- [ ] [TASK-research-docker-agent-codex](TASK-research-docker-agent-codex.todo.md) — Docker Agent, OpenAI Codex (проприетарный, sandboxing)

### Этап 2: Сводный анализ (после завершения Этапа 1)

- [ ] [TASK-research-agent-frameworks-summary](TASK-research-agent-frameworks-summary.todo.md) — Сводная таблица и итоговые рекомендации

## 6. Definition of Done (Критерии приёмки эпика)
- [ ] Все индивидуальные research-задачи выполнены
- [ ] Каждый comparison-документ создан в `docs/research/`
- [ ] Сводная таблица в `docs/research/agent-frameworks-summary.md` создана
- [ ] По каждому фреймворку есть вердикт: заимствовать / dependency / не подходит
- [ ] Выделены конкретные паттерны для заимствования с приоритетами

## 7. Release Notes and Deployment (Инструкция по релизу)
Не требуется — эпик содержит только исследовательские задачи (docs).

## 8. Risks and Dependencies (Риски и зависимости)
- 10+ фреймворков — значительный объём исследования
- Многие продукты активно развиваются — информация может устареть
- Проприетарные продукты (Claude Code, Copilot, Codex) — анализ только по документации
- Разные языки/экосистемы (Python, TypeScript, Rust, Go) — нужна аккуратность при переносе паттернов в PHP

## 9. Sources (Источники)
- Существующие comparison-документы: `docs/research/agent-bernstein-comparison.md`, `docs/research/agent-orchestrator-comparison.md`, `docs/research/superpowers-brainstorming-comparison.md`
- Ссылки на репозитории и документацию — в индивидуальных задачах

## 10. Comments (Комментарии)
Эпик объединяет все накопившиеся research-задачи в единый трек с чётким финальным артефактом — сводной таблицей. Задачи Этапа 1 можно выполнять в любом порядке и параллельно. Задача Этапа 2 запускается только после завершения всех исследований.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-21 | Тимлид (Алекс) | Создание эпика |
