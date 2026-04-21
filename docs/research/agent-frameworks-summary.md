# Сводная таблица: AI-agent фреймворки и оркестраторы

> **Цель:** Сравнить исследованные AI-agent фреймворки с task-orchestrator, определить паттерны для заимствования.
> **Эпик:** [EPIC-research-agent-frameworks-comparison](../todo/EPIC-research-agent-frameworks-comparison.md)

---

## Сравнительная таблица

> **Статус заполнения:** 2 / 13 исследований

| # | Фреймворк | Язык | Категория | Модель оркестрации | State mgmt | Error handling | Extensibility | Вердикт | Отчёт |
|:---:|---|---|---|---|---|---|---|---|---|
| 1 | Charmbracelet Crush | Go | `CLI-agent` | `agent-loop` (LLM → tool call → LLM → ...) | `persistent` (SQLite) | `manual` (только retry при 401) | `MCP + SKILL.md + config` | 🟡 заимствовать отдельные паттерны | [crush-comparison.md](crush-comparison.md) ✅ |
| 2 | pi_agent_rust | Rust | `CLI-agent` | `agent-loop` (LLM → tool call → LLM → ...) | `persistent` (JSONL tree + SQLite index) | `basic retry` (exponential backoff, global config) | `Extensions (QuickJS/WASM) + Skills (SKILL.md) + Packages` | 🟡 заимствовать отдельные паттерны | [pi-agent-rust-comparison.md](pi-agent-rust-comparison.md) ✅ |
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

* Crush: loop detection (защита от зацикливания в fix_iterations) — 🟡 P2

### Приоритет 2 (Среднесрочные)

* Crush: формализация Agent Skills (SKILL.md standard, discovery, validation) — 🟡 P2
* pi_agent_rust: tool parallelism (параллельное выполнение read-only шагов в chain) — 🟡 P2

### Приоритет 3 (Долгосрочные / R&D)

* Crush: auto-summarization при переполнении контекста — 🟡 P3
* Crush: permission system для автономного выполнения — 🟡 P3
* Crush: множественный context file discovery (CRUSH.md, CLAUDE.md и т.д.) — 🟡 P3
* pi_agent_rust: auto-compaction при переполнении контекста — 🟡 P3
* pi_agent_rust: session persistence с tree branching — 🟡 P3
* pi_agent_rust: extension/permission system для custom runners — 🟡 P3
* pi_agent_rust: формализация execution invariants для chain executor — 🟡 P3

---

## Общие тренды

> Заполняется после завершения всех исследований.

*

---

## Изменения

| Дата | Автор | Изменение |
|:---|:---|:---|
| 2026-04-21 | Тимлид (Алекс) | Создание шаблона сводной таблицы |
| 2026-04-21 | Технический писатель (Гермиона) | Заполнена строка pi_agent_rust (#2), добавлены рекомендации |
