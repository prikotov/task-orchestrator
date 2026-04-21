# Сводная таблица: AI-agent фреймворки и оркестраторы

> **Цель:** Сравнить исследованные AI-agent фреймворки с task-orchestrator, определить паттерны для заимствования.
> **Эпик:** [EPIC-research-agent-frameworks-comparison](../todo/EPIC-research-agent-frameworks-comparison.md)

---

## Сравнительная таблица

> **Статус заполнения:** 10 / 13 исследований

| # | Фреймворк | Язык | Категория | Модель оркестрации | State mgmt | Error handling | Extensibility | Вердикт | Отчёт |
|:---:|---|---|---|---|---|---|---|---|---|
| 1 | Charmbracelet Crush | Go | `CLI-agent` | `agent-loop` (LLM → tool call → LLM → ...) | `persistent` (SQLite) | `manual` (только retry при 401) | `MCP + SKILL.md + config` | 🟡 заимствовать отдельные паттерны | [crush-comparison.md](crush-comparison.md) ✅ |
| 2 | pi_agent_rust | Rust | `CLI-agent` | `agent-loop` (LLM → tool call → LLM → ...) | `persistent` (JSONL tree + SQLite index) | `basic retry` (exponential backoff, global config) | `Extensions (QuickJS/WASM) + Skills (SKILL.md) + Packages` | 🟡 заимствовать отдельные паттерны | [pi-agent-rust-comparison.md](pi-agent-rust-comparison.md) ✅ |
| 3 | CrewAI | Python | `multi-agent` | `sequential / hierarchical (Crews) + event-driven (Flows)` | `in-memory + checkpoint (SQLite)` | `basic retry (LLM level)` | `custom tools + Skills (SKILL.md) + MCP + RAG + Flows` | 🟡 заимствовать отдельные паттерны | [crewai-langgraph-autogen-comparison.md](crewai-langgraph-autogen-comparison.md) ✅ |
| 4 | LangGraph | Python | `multi-agent` | `graph/DAG (StateGraph) + superstep execution` | `TypedDict + reducers + checkpoint (memory/SQLite/PostgreSQL)` | `RetryPolicy per node, durable execution` | `subgraphs + conditional edges + Send (map-reduce) + interrupts` | 🟡 заимствовать отдельные паттерны | *(в отчёте №3)* ✅ |
| 5 | AutoGen (Microsoft) | Python + .NET | `multi-agent` | `event-driven (Core) / group chat (AgentChat) / graph` | `message thread + model context` | `CancellationToken, exception propagation` | `custom agents + tools + group chat managers + subscriptions` | 🟡 заимствовать отдельные паттерны | *(в отчёте №3)* ✅ |
| 6 | OpenHands SDK | Python | `SDK` | `agent-loop` (LLM → Action → Tool → Observation → LLM → ...) | `event-stream` (file-backed) | `retry+backoff (tenacity) + fallback LLM profiles` | `custom tools + MCP + Skills (SKILL.md) + Plugins + Hooks + sub-agents` | 🟡 заимствовать отдельные паттерны | [openhands-sdk-comparison.md](openhands-sdk-comparison.md) ✅ |
| 7 | Archon | TypeScript (Bun) | `workflow-engine` | `DAG (YAML) + topological layers + parallel execution` | `persistent (SQLite/PostgreSQL, 7 таблиц)` | `2-layer retry (SDK + node) + error classification (FATAL/TRANSIENT/UNKNOWN) + fallbackModel` | `IPlatformAdapter + IAgentProvider + IIsolationProvider + MCP + Hooks + Skills + Commands` | 🟡 заимствовать отдельные паттерны | [archon-comparison.md](archon-comparison.md) ✅ |
| 8 | MetaGPT | Python | `multi-agent` | `SOP (BY_ORDER) / react-loop / plan-and-act` | `in-memory (Memory + index by cause_by)` | `budget guard (NoMoneyException)` | `custom roles + actions + tools + skills + RAG` | 🟡 заимствовать отдельные паттерны | [metagpt-openclaw-comparison.md](metagpt-openclaw-comparison.md) ✅ |
| 9 | OpenClaw | TypeScript (Node.js) | `CLI-agent` | `agent-loop` (LLM → tool call → observation → LLM → ...) | `file-backed transcripts + pluggable context engine` | `model failover с cooldown + error classification` | `plugin SDK + 40+ extensions + Skills + MCP + sandbox` | 🟡 заимствовать отдельные паттерны | *(в отчёте №8)* ✅ |
| 10 | Mastra AI | TypeScript (Node.js) | `SDK` | `step-based workflow (chaining API: .then/.branch/.parallel/.dowhile/.dountil/.foreach) + agent-loop` | `pluggable (LibSQL/PostgreSQL/D1/Upstash)` | `basic retry (attempts+delay, no exponential backoff) + TripWire (abort with retry hint)` | `Processors pipeline + MCP + Tools (Zod schemas) + custom storage + Agent network (delegation)` | 🟡 заимствовать отдельные паттерны | [mastra-ai-comparison.md](mastra-ai-comparison.md) ✅ |
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
* OpenHands SDK: stuck detection (5 паттернов зацикливания — repeating action-obs, action-error, monologue, alternating, context overflow) — 🟡 P2
* OpenHands SDK: security risk assessment + confirmation policies (для автономного выполнения) — 🟡 P2
* Archon: error classification (FATAL/TRANSIENT/UNKNOWN) — умный retry, не тратить попытки на неисправимые ошибки — 🟡 P2
* Archon: loop nodes с `until_bash` — детерминированная проверка завершения (тесты прошли → стоп) — 🟡 P2
* Archon: loop nodes с `fresh_context` — каждый iteration с чистым контекстом — 🟡 P2
* Archon: `$nodeId.output` substitution — явная передача данных между шагами — 🟡 P2
* OpenClaw: model failover с cooldown и per-profile error classification — 🟡 P2
* OpenClaw: error classification (rate_limit, overloaded, auth, billing, timeout, model_not_found) — 🟡 P2
* Mastra AI: TripWire (LLM-based quality abort с retry hint) — дополнение к shell-based quality gates — 🟡 P2
* Mastra AI: Processor pipeline (6-фазная input/output middleware) — альтернатива decorator pattern — 🟡 P2
* Mastra AI: typed I/O per step (Zod-схемы для валидации входа/выхода) — 🟡 P2
* Mastra AI: conditional branching в chains (концепция из .branch()) — 🟡 P2
* Mastra AI: LLM-based evaluation (scorers, LLM-as-judge) — 🟡 P2

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
* OpenHands SDK: context condensation (LLM-суммаризация при переполнении context window) — 🟡 P3
* OpenHands SDK: tool annotations (readOnly / destructive / idempotent / openWorld hints) — 🟡 P3
* OpenHands SDK: critic (LLM-based quality scoring) + iterative refinement — 🟡 P3
* OpenHands SDK: LLM Profile Store (файловые JSON-профили с параметрами LLM) — 🟡 P3
* OpenHands SDK: hooks system (pre/post tool use shell-скрипты) — 🟡 P3
* OpenHands SDK: parallel tool execution с resource-level locking — 🟡 P3
* OpenHands SDK: sub-agent delegation (файловые YAML-определения агентов) — 🟡 P3
* Archon: DAG-based workflow (parallel execution независимых шагов) — 🟡 P3
* Archon: human-in-the-loop (approval gates + interactive loops) — 🟡 P3
* Archon: DAG Resume on Failure (автоматический resume с пропуском завершённых узлов) — 🟡 P3
* Archon: `output_format` (structured JSON output для quality gates) — 🟡 P3
* Archon: per-node provider/model override (дешёвая модель для простых шагов) — 🟡 P3
* Archon: isolation через git worktrees (для параллельного выполнения цепочек) — 🟡 P3
* MetaGPT: SOP event-driven step activation (watch/cause_by routing для dynamic chains) — 🟡 P3
* MetaGPT: message-based coordination (Environment + Message + cause_by) — 🟡 P3
* OpenClaw: pluggable context engine (ingest/assemble/compact/maintain с tokenBudget) — 🟡 P3
* OpenClaw: sub-agent spawning с limits (maxDepth, maxChildren, maxConcurrent) — 🟡 P3
* OpenClaw: bootstrap budget для context injection (защита от oversized промптов) — 🟡 P3
* Mastra AI: observational memory (Observer + Reflector agents для auto-compression) — для длинных dynamic loops — 🟡 P3
* Mastra AI: suspend/resume (human-in-the-loop с persistence) — 🟡 P3
* Mastra AI: parallel execution в chains — 🟡 P3
* Mastra AI: agent delegation (multi-agent orchestration с hooks) — 🟡 P3
* Mastra AI: foreach (map/reduce) в chains — 🟡 P3
* Mastra AI: workflow nesting (chain как шаг другой chain) — 🟡 P3

---

## Общие тренды

> Заполняется по мере завершения исследований.

* Все три Python multi-agent фреймворка (CrewAI, LangGraph, AutoGen) работают на уровне прямых LLM API, тогда как task-orchestrator работает на уровне runner'ов (pi, codex). Разный уровень абстракции.
* LangGraph — единственный из тройки с durable execution и checkpoint persistence. Это ключевое преимущество для длинных workflows.
* AutoGen в maintenance mode, Microsoft рекомендует Microsoft Agent Framework (MAF). Заимствование паттернов безопасно, но dependency невозможна.
* CrewAI — самый «productized» из тройки: Enterprise (Crew Control Plane), сертификация 100k+ разработчиков, monetization через cloud.
* Ни один из трёх фреймворков не имеет встроенных quality gates, budget control или circuit breaker — наши ключевые отличия.
* Graph-based модель (LangGraph) — самый гибкий подход к оркестрации, но с более высоким порогом входа по сравнению с YAML chains.
* OpenHands SDK — наиболее зрелая Action/Observation-модель из исследованных: типизированные Action/Observation (Pydantic), security risk assessment, confirmation policies, stuck detection, parallel tool execution с resource locking, context condensation, sub-agent delegation. При этом SDK не является chain-оркестратором — он работает на уровне single agent loop.
* OpenHands SDK — единственный проект с полноценной security-моделью (risk assessment + confirmation policies + defense-in-depth rails). Для autonomous execution — критически важная возможность.
* Stuck detection — повторяющийся паттерн в нескольких проектах (Crush: loop detection, OpenHands SDK: 5-паттерн stuck detector). Это подтверждает актуальность P2-задачи.
* Archon — единственный из исследованных проектов, работающий на уровне оркестрации внешних AI-ассистентов (subprocess SDK), а не на уровне прямых LLM API. Это ближайший к task-orchestrator по уровню абстракции.
* Archon v2 полностью переписан с Python на TypeScript/Bun — показательный пример смены стека для production-ready проекта.
* DAG-модель оркестрации (Archon, LangGraph) — повторяющийся паттерн в двух проектах. Это альтернатива нашим YAML chains, но перенос в PHP требует значительных усилий.
* Loop nodes с детерминированной проверкой завершения (`until_bash`) — паттерн, который усиливает наш `fix_iterations` без полной DAG-миграции.
* MetaGPT подтверждает наш подход к оркестрации: Team (chain) + investment (budget) + n_round (max_iterations) + idle detection — концептуально идентично нашим YAML chains.
* Model failover с error classification — повторяющийся паттерн: Archon (FATAL/TRANSIENT/UNKNOWN), OpenClaw (rate_limit/overloaded/auth/billing/timeout/model_not_found). OpenClaw наиболее гранулярная реализация.
* Pluggable context engine (OpenClaw) — единственный проект с формализованным интерфейсом для context management lifecycle (ingest → assemble → compact → maintain). Это уровень абстракции выше, чем auto-summarization в Crush и auto-compaction в pi_agent_rust.
* OpenClaw — единственный проект с production-ready multi-channel personal assistant, включая 20+ мессенджеров, desktop/mobile apps, voice wake, live canvas. Это не фреймворк оркестрации, а законченный продукт.
* MetaGPT SOP-подход ("Code = SOP(Team)") — концептуально близок нашим YAML chains, но реализован через message-passing (watch/cause_by) вместо позиционного порядка. Для dynamic chains message-passing может быть гибче.
* Mastra AI — наиболее полный TypeScript-фреймворк из исследованных: step-based workflows с chaining API, 4-уровневая memory, eval framework, processor pipeline, agent network. При этом работает на уровне прямых LLM API, а не внешних runner'ов.
* Mastra AI и Archon — два TypeScript-проекта с workflow engine, но на разных уровнях: Mastra = SDK (LLM API), Archon = orchestrator (subprocess SDK). Task-orchestrator ближе к Archon по уровню абстракции.
* Processor pipeline (Mastra) — расширение middleware-паттерна: 6 фаз перехвата (input, inputStep, outputStream, outputResult, outputStep) vs. наш decorator pattern (retry, circuit breaker, budget). Pipeline даёт более granular контроль.
* Typed I/O per step — повторяющийся паттерн: Mastra (Zod-схемы), LangGraph (TypedDict + reducers), Archon (JSON Schema). Валидация входных/выходных данных каждого шага повышает надёжность цепочек.
* Observational memory (Mastra) — самый продвинутый подход к context management из исследованных: Observer + Reflector agents с async buffering, token budgets, и activation thresholds. Это развитие идей auto-summarization (Crush) и auto-compaction (pi_agent_rust).
* Conditional branching — третий проект с branching (после LangGraph и Archon), что подтверждает востребованность этого паттерна для workflows.

---

## Изменения

| Дата | Автор | Изменение |
|:---|:---|:---|
| 2026-04-21 | Тимлид (Алекс) | Создание шаблона сводной таблицы |
| 2026-04-21 | Технический писатель (Гермиона) | Заполнена строка pi_agent_rust (#2), добавлены рекомендации |
| 2026-04-21 | Технический писатель (Гермиона) | Создан отчёт crewai-langgraph-autogen-comparison.md, заполнены строки CrewAI (#3), LangGraph (#4), AutoGen (#5) |
| 2026-04-21 | Технический писатель (Гермиона) | Создан отчёт openhands-sdk-comparison.md, заполнена строка OpenHands SDK (#6), добавлены рекомендации |
| 2026-04-21 | Технический писатель (Гермиона) | Создан отчёт archon-comparison.md, заполнена строка Archon (#7), добавлены рекомендации |
| 2026-04-21 | Технический писатель (Гермиона) | Создан отчёт metagpt-openclaw-comparison.md, заполнены строки MetaGPT (#8) и OpenClaw (#9), добавлены рекомендации и тренды |
| 2026-04-21 | Технический писатель (Гермиона) | Создан отчёт mastra-ai-comparison.md, заполнена строка Mastra AI (#10), добавлены рекомендации и тренды |
