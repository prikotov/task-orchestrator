# Сводная таблица: AI-agent фреймворки и оркестраторы

> **Цель:** Сравнить исследованные AI-agent фреймворки с task-orchestrator, определить паттерны для заимствования.
> **Эпик:** [EPIC-research-agent-frameworks-comparison](../todo/EPIC-research-agent-frameworks-comparison.md)

---

## Сравнительная таблица

> **Статус заполнения:** 0 / 11 исследований

| # | Фреймворк | Язык | Категория | Модель оркестрации | State mgmt | Error handling | Extensibility | Вердикт | Отчёт |
|:---:|---|---|---|---|---|---|---|---|---|
| 1 | Charmbracelet Crush | Go | | | | | | | [crush-comparison.md](crush-comparison.md) ⏳ |
| 2 | pi_agent_rust | Rust | | | | | | | [pi-agent-rust-comparison.md](pi-agent-rust-comparison.md) ⏳ |
| 3 | CrewAI | Python | | | | | | | [crewai-langgraph-autogen-comparison.md](crewai-langgraph-autogen-comparison.md) ⏳ |
| 4 | LangGraph | Python | | | | | | | *(в отчёте №3)* ⏳ |
| 5 | AutoGen | Python | | | | | | | *(в отчёте №3)* ⏳ |
| 6 | OpenHands SDK | Python | | | | | | | [openhands-sdk-comparison.md](openhands-sdk-comparison.md) ⏳ |
| 7 | Archon | Python | | | | | | | [archon-comparison.md](archon-comparison.md) ⏳ |
| 8 | MetaGPT | Python | | | | | | | [metagpt-openclaw-comparison.md](metagpt-openclaw-comparison.md) ⏳ |
| 9 | OpenClaw | Python | | | | | | | *(в отчёте №8)* ⏳ |
| 10 | Mastra AI | TypeScript | | | | | | | [mastra-ai-comparison.md](mastra-ai-comparison.md) ⏳ |
| 11 | Claude Code | — (проприетарный) | | | | | | | [claude-code-comparison.md](claude-code-comparison.md) ⏳ |
| 12 | GitHub Copilot Agent HQ | — (проприетарный) | | | | | | | [copilot-agent-hq-comparison.md](copilot-agent-hq-comparison.md) ⏳ |
| 13 | Docker Agent + OpenAI Codex | — (проприетарный) | | | | | | | [docker-agent-codex-comparison.md](docker-agent-codex-comparison.md) ⏳ |

### Легенда колонок

| Колонка | Описание | Значения |
|---|---|---|
| **Категория** | Тип фреймворка | `single-agent`, `multi-agent`, `meta-orchestration`, `cloud/SaaS`, `CLI-agent` |
| **Модель оркестрации** | Как оркестируются шаги/агенты | `sequential`, `graph/DAG`, `event-driven`, `SOP`, `agent-loop` и т.д. |
| **State mgmt** | Как хранится и передаётся состояние | `in-memory`, `shared-context`, `message-passing`, `persistent` и т.д. |
| **Error handling** | Retry, circuit breaker, fallback | `retry+backoff`, `circuit-breaker`, `fallback-model`, `manual` и т.д. |
| **Extensibility** | Как расширяется | `plugins`, `SDK/interface`, `config-only`, `inheritance` и т.д. |
| **Вердикт** | Рекомендация для task-orchestrator | `🟢 заимствовать паттерны`, `🟡 dependency`, `🔴 не подходит`, `✅ уже есть` |

---

## Рекомендации по заимствованию

> Заполняется по мере завершения исследований.

### Приоритет 1 (Quick wins)

*

### Приоритет 2 (Среднесрочные)

*

### Приоритет 3 (Долгосрочные / R&D)

*

---

## Общие тренды

> Заполняется после завершения всех исследований.

*

---

## Изменения

| Дата | Автор | Изменение |
|:---|:---|:---|
| 2026-04-21 | Тимлид (Алекс) | Создание шаблона сводной таблицы |
