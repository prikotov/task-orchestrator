# Сводная таблица: AI-agent фреймворки и оркестраторы

> **Цель:** Сравнить исследованные AI-agent фреймворки с task-orchestrator, определить паттерны для заимствования.
> **Эпик:** [EPIC-research-agent-frameworks-comparison](../todo/EPIC-research-agent-frameworks-comparison.md)

---

## Сравнительная таблица

> **Статус заполнения:** 5 / 13 исследований

| # | Фреймворк | Язык | Категория | Модель оркестрации | State mgmt | Error handling | Extensibility | Вердикт | Отчёт |
|:---:|---|---|---|---|---|---|---|---|---|
| 1 | Charmbracelet Crush | Go | `CLI-agent` | `agent-loop` (LLM → tool call → LLM → ...) | `persistent` (SQLite) | `manual` (только retry при 401) | `MCP + SKILL.md + config` | 🟡 заимствовать отдельные паттерны | [crush-comparison.md](crush-comparison.md) ✅ |
| 2 | pi_agent_rust | Rust | `CLI-agent` | `agent-loop` (LLM → tool call → LLM → ...) | `persistent` (JSONL tree + SQLite index) | `basic retry` (exponential backoff, global config) | `Extensions (QuickJS/WASM) + Skills (SKILL.md) + Packages` | 🟡 заимствовать отдельные паттерны | [pi-agent-rust-comparison.md](pi-agent-rust-comparison.md) ✅ |
| 3 | CrewAI | Python | `multi-agent` | `sequential / hierarchical (Crews) + event-driven (Flows)` | `in-memory + checkpoint (SQLite)` | `basic retry (LLM level)` | `custom tools + Skills (SKILL.md) + MCP + RAG + Flows` | 🟡 заимствовать отдельные паттерны | [crewai-langgraph-autogen-comparison.md](crewai-langgraph-autogen-comparison.md) ✅ |
| 4 | LangGraph | Python | `multi-agent` | `graph/DAG (StateGraph) + superstep execution` | `TypedDict + reducers + checkpoint (memory/SQLite/PostgreSQL)` | `RetryPolicy per node, durable execution` | `subgraphs + conditional edges + Send (map-reduce) + interrupts` | 🟡 заимствовать отдельные паттерны | *(в отчёте №3)* ✅ |
| 5 | AutoGen (Microsoft) | Python + .NET | `multi-agent` | `event-driven (Core) / group chat (AgentChat) / graph` | `message thread + model context` | `CancellationToken, exception propagation` | `custom agents + tools + group chat managers + subscriptions` | 🟡 заимствовать отдельные паттерны | *(в отчёте №3)* ✅ |
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
* AutoGen: декларативные termination conditions (timeout, token limit, keyword) — 🟡 P2

### Приоритет 3 (Долгосрочные / R&D)

* Crush: auto-summarization при переполнении контекста — 🟡 P3
* Crush: permission system для автономного выполнения — 🟡 P3
* Crush: множественный context file discovery (CRUSH.md, CLAUDE.md и т.д.) — 🟡 P3
* pi_agent_rust: auto-compaction при переполнении контекста — 🟡 P3
* pi_agent_rust: session persistence с tree branching — 🟡 P3
* pi_agent_rust: extension/permission system для custom runners — 🟡 P3
* pi_agent_rust: формализация execution invariants для chain executor — 🟡 P3
* LangGraph: graph-based conditional routing для сложных dynamic chains — 🟡 P3
* LangGraph: checkpoint / durable execution (resume после сбоя) — 🟡 P3
* CrewAI: hierarchical orchestration с manager (dynamic delegation) — 🟡 P3
* CrewAI: event-driven architecture (events на уровне chain executor) — 🟡 P3
* AutoGen: multi-agent patterns (swarm, handoff) для будущих dynamic chains — 🟡 P3
* CrewAI / LangGraph: memory system (кэширование, обучение на предыдущих запусках) — 🟡 P3

---

## Общие тренды

> Заполняется по мере завершения исследований.

* Все три Python multi-agent фреймворка (CrewAI, LangGraph, AutoGen) работают на уровне прямых LLM API, тогда как task-orchestrator работает на уровне runner'ов (pi, codex). Разный уровень абстракции.
* LangGraph — единственный из тройки с durable execution и checkpoint persistence. Это ключевое преимущество для длинных workflows.
* AutoGen в maintenance mode, Microsoft рекомендует Microsoft Agent Framework (MAF). Заимствование паттернов безопасно, но dependency невозможна.
* CrewAI — самый «productized» из тройки: Enterprise (Crew Control Plane), сертификация 100k+ разработчиков, monetization через cloud.
* Ни один из трёх фреймворков не имеет встроенных quality gates, budget control или circuit breaker — наши ключевые отличия.
* Graph-based модель (LangGraph) — самый гибкий подход к оркестрации, но с более высоким порогом входа по сравнению с YAML chains.

---

## Изменения

| Дата | Автор | Изменение |
|:---|:---|:---|
| 2026-04-21 | Тимлид (Алекс) | Создание шаблона сводной таблицы |
| 2026-04-21 | Технический писатель (Гермиона) | Заполнена строка pi_agent_rust (#2), добавлены рекомендации |
| 2026-04-21 | Технический писатель (Гермиона) | Создан отчёт crewai-langgraph-autogen-comparison.md, заполнены строки CrewAI (#3), LangGraph (#4), AutoGen (#5) |
