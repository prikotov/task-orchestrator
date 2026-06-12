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
status: done
reopened: 2026-04-28
pr: "#51 (исследование), #52 (ревью и исправления), #97 (Paperclip AI + AgentCraft, финализация)"
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
- [x] Каждый фреймворк исследован по единой методологии (модель оркестрации, state, error handling, extensibility)
- [x] По каждому фреймворку создан отчёт в `docs/research/` по формату существующих comparison-документов
- [x] Сводная таблица в `docs/research/agent-frameworks-summary.md` со всеми фреймворками
- [x] Чёткий вердикт по каждому: заимствовать / dependency / не подходит

### 🟡 Should Have (Важные требования)
- [x] Сравнительная таблица с группировкой по категориям (multi-agent, single-agent, cloud, meta-orchestration)
- [x] Рекомендации по приоритетам заимствования паттернов

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

- [x] [TASK-research-charmbracelet-crush](TASK-research-charmbracelet-crush.todo.md) — Charmbracelet Crush (Go, CLI-agent)
- [x] [TASK-research-pi-agent-rust](TASK-research-pi-agent-rust.todo.md) — pi_agent_rust (Rust)
- [x] [TASK-research-crewai-langgraph-autogen](TASK-research-crewai-langgraph-autogen.todo.md) — CrewAI, LangGraph, AutoGen (Python multi-agent)
- [x] [TASK-research-openhands-sdk](TASK-research-openhands-sdk.todo.md) — OpenHands SDK (Python, SDK-подход)
- [x] [TASK-research-archon-ai-planner](TASK-research-archon-ai-planner.todo.md) — Archon (Python, мета-оркестрация)
- [x] [TASK-research-metagpt-openclaw](TASK-research-metagpt-openclaw.todo.md) — MetaGPT, OpenClaw (Python, SOP/роли)
- [x] [TASK-research-mastra-ai](TASK-research-mastra-ai.todo.md) — Mastra AI (TypeScript, workflows)
- [x] [TASK-research-claude-code](TASK-research-claude-code.todo.md) — Claude Code (проприетарный, agent loop)
- [x] [TASK-research-copilot-agent-hq](TASK-research-copilot-agent-hq.todo.md) — GitHub Copilot Agent HQ (проприетарный, cloud)
- [x] [TASK-research-docker-agent-codex](TASK-research-docker-agent-codex.todo.md) — Docker Agent, OpenAI Codex (проприетарный, sandboxing)
- [x] [TASK-research-agno](TASK-research-agno.todo.md) — Agno / бывший Phi (Python, multi-agent teams)

### Этап 1b: Дополнительные исследования (2026-04-28)

- [x] [TASK-research-paperclip-ai](TASK-research-paperclip-ai.todo.md) — Paperclip AI
- [x] [TASK-research-agentcraft](TASK-research-agentcraft.todo.md) — AgentCraft

### Этап 1c: Дополнительные исследования (2026-05-04)

- [ ] [TASK-research-sandcastle](TASK-research-sandcastle.todo.md) — Sandcastle (Matt Pocock)
- [ ] [TASK-research-hermes-agent](TASK-research-hermes-agent.todo.md) — Hermes Agent (Nous Research)
- [x] [TASK-research-oh-my-openagent](../done/TASK-research-oh-my-openagent.todo.md) — Oh My OpenAgent (форк OpenCode, TypeScript + паттерны оркестрации)

### Этап 1d: Дополнительные исследования (2026-05-13)

- [ ] [TASK-research-duet](TASK-research-duet.todo.md) — Duet (Aomni, cloud/SaaS, team AI-агент)
- [ ] [TASK-research-multica](TASK-research-multica.todo.md) — Multica (open-source, project management для human + agent teams)

### Этап 1e: Дополнительные исследования (2026-05-20)

- [x] [TASK-research-zeroclaw](done/TASK-research-zeroclaw.todo.md) — Zeroclaw (zeroclaw-labs, AI-agent orchestration)

### Этап 1f: Дополнительные исследования (2026-06-12)

- [ ] [TASK-research-odysseus-ai-workspace](../TASK-research-odysseus-ai-workspace.todo.md) — Odysseus (PewDiePie archdaemon, self-hosted AI workspace)

### Этап 2: Сводный анализ (после завершения Этапа 1)

- [x] [TASK-research-agent-frameworks-summary](TASK-research-agent-frameworks-summary.todo.md) — Сводная таблица и итоговые рекомендации

## 6. Definition of Done (Критерии приёмки эпика)
- [x] Все индивидуальные research-задачи выполнены
- [x] Каждый comparison-документ создан в `docs/research/`
- [x] Сводная таблица в `docs/research/agent-frameworks-summary.md` создана
- [x] По каждому фреймворку есть вердикт: заимствовать / dependency / не подходит
- [x] Выделены конкретные паттерны для заимствования с приоритетами

## 7. Release Notes and Deployment (Инструкция по релизу)
Не требуется — эпик содержит только исследовательские задачи (docs).

## 8. Risks and Dependencies (Риски и зависимости)
- 10+ фреймворков — значительный объём исследования
- Многие продукты активно развиваются — информация может устареть
- Проприетарные продукты (Claude Code, Copilot, Codex) — анализ только по документации
- Разные языки/экосистемы (Python, TypeScript, Rust, Go) — нужна аккуратность при переносе паттернов в PHP

## 9. Sources (Источники)
- Существующие comparison-документы: `docs/research/framework-comparisons/agent-bernstein-comparison.md`, `docs/research/framework-comparisons/agent-orchestrator-comparison.md`, `docs/research/framework-comparisons/superpowers-brainstorming-comparison.md`
- Ссылки на репозитории и документацию — в индивидуальных задачах

## 10. Comments (Комментарии)
Эпик объединяет все накопившиеся research-задачи в единый трек с чётким финальным артефактом — сводной таблицей. Задачи Этапа 1 можно выполнять в любом порядке и параллельно. Задача Этапа 2 запускается только после завершения всех исследований.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-21 | Тимлид (Алекс) | Создание эпика |
| 2026-04-22 | Технический писатель (Гермиона) | Все 11 задач выполнены. Эпик завершён. |
| 2026-04-22 | Тимлид (Алекс) + Пуаро + Локи | Постфактум ревью всех 11 отчётов через сабагентов. 5 критических и 15+ значимых исправлений. PR #52. |
