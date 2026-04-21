# Сводная таблица: AI-agent фреймворки и оркестраторы

> **Цель:** Сравнить исследованные AI-agent фреймворки с task-orchestrator, определить паттерны для заимствования.
> **Эпик:** [EPIC-research-agent-frameworks-comparison](../todo/EPIC-research-agent-frameworks-comparison.md)

---

## Сравнительная таблица

> **Статус заполнения:** 13 / 13 исследований

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
| 11 | Claude Code | — (проприетарный) | `CLI-agent` | `agent-loop` (LLM → tool call → observation → LLM → ...) | `in-memory + auto-compact` | `basic API retry` (429/500, без backoff) | `MCP + hooks (shell) + CLAUDE.md + slash commands + sub-agents` | 🟡 заимствовать отдельные паттерны | [claude-code-comparison.md](claude-code-comparison.md) ✅ |
| 12 | GitHub Copilot Agent HQ | — (проприетарный) | `cloud/SaaS` | `agent-loop` (cloud sandbox, Issue→Plan→Execute→PR) | `cloud-managed` (session-based, GitHub infrastructure) | `transparent` (built-in API retry, org-level rate limits) | `MCP + custom instructions + GitHub Actions + GitHub Models marketplace` | 🟡 заимствовать отдельные паттерны | [copilot-agent-hq-comparison.md](copilot-agent-hq-comparison.md) ✅ |
| 13 | Docker Agent + OpenAI Codex | Rust (codex-rs) + TypeScript | `CLI-agent + cloud/SaaS` | `agent-loop` (LLM → tool call → observation → LLM → ...) + hierarchical multi-agent | `persistent` (SQLite + rollout JSONL files) + `auto-compact` | `basic API retry` + `Guardian (LLM safety reviewer)` + `exec policy (rules)` | `MCP client/server` + `SKILL.md` + `AGENTS.md` + `apps (connectors)` + `custom agent roles` + `Docker sandbox` | 🟡 заимствовать отдельные паттерны | [docker-agent-codex-comparison.md](docker-agent-codex-comparison.md) ✅ |

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

## Резюме для принятия решений (Executive Summary)

По результатам исследования 13 AI-agent фреймворков и инструментов можно сделать **три главных вывода**:

1. **task-orchestrator обладает уникальной комбинацией возможностей**, которой нет ни у одного из исследованных проектов: YAML-цепочки + retry с backoff + circuit breaker + quality gates (shell) + бюджетный контроль + fix_iterations + fallback routing + JSONL audit trail. Ни один фреймворк — ни open-source, ни проприетарный — не предлагает все эти механизмы вместе.

2. **Наибольший потенциал для заимствования** — в трёх кластерах: (а) интеллектуальная обработка ошибок (error classification, stuck detection, model failover), (б) безопасность автономного выполнения (sandboxing, exec policy, permission system), (в) расширенные модели оркестрации (conditional branching, parallel execution, sub-agents).

3. **Ближайший аналог** по уровню абстракции — Archon (TypeScript/Bun), который тоже оркестирует внешние AI-ассистенты (Claude Code, Codex CLI) через subprocess SDK. Однако Archon не имеет circuit breaker, quality gates или бюджетного контроля — наши ключевые отличия сохраняются.

---

## Рекомендации по заимствованию

> Рекомендации сгруппированы по тематическим кластерам и приоритизированы: **Quick wins** (реализуемо за 1–2 задачи), **Среднесрочные** (требуют архитектурных решений), **R&D** (исследование перед реализацией).

### Кластер 1: Интеллектуальная обработка ошибок и восстановление

> **Проблема:** Сейчас `RetryingAgentRunner` делает retry на любую ошибку без разбора. Нет защиты от зацикливания в fix_iterations. Нет fallback на уровне модели.

#### 🟢 Quick wins (P2)

| Паттерн | Источники | Суть | Обоснование |
|---|---|---|---|
| **Error classification** | Archon (FATAL/TRANSIENT/UNKNOWN), OpenClaw (6 категорий), Codex (Guardian) | Классификация ошибок перед retry: FATAL → не retry, TRANSIENT → retry с backoff, UNKNOWN →保守ный подход | Не тратить попытки retry на заведомо неисправимые ошибки (401, 403). Подтверждён 3+ проектами |
| **Stuck / Loop detection** | Crush (window-based), OpenHands SDK (5 паттернов) | Обнаружение зацикливания: повторяющиеся действия, повторяющиеся ошибки, чередование, context overflow | Актуально для fix_iterations — если агент повторяет одни и те же действия, лучше остановить раньше. Подтверждено 2+ проектами |
| **Model failover с cooldown** | OpenClaw (per-profile), Archon (fallbackModel), OpenHands SDK (FallbackStrategy) | При недоступности модели → переключение на fallback с cooldown, чтобы не «долбить» упавший endpoint | Дополнение к нашему circuit breaker: CB защищает от cascade failures, failover — переключает на альтернативу |

#### 🟡 Среднесрочные (P2–P3)

| Паттерн | Источники | Суть | Обоснование |
|---|---|---|---|
| **Декларативные termination conditions** | AutoGen (max_turns, timeout, token_usage, text_mention, комбинирование через \| и &) | Условия остановки как декларативные правила, комбинируемые через AND/OR | У нас только max_iterations + budget. Timeout, token limit, keyword-based — полезные дополнения |
| **DAG Resume on Failure** | Archon | При повторном запуске после сбоя — пропуск завершённых шагов, выполнение только failed | Для длинных цепочек: не повторять шаги 1–3, если сбой произошёл на шаге 4 |
| **`$nodeId.output` substitution** | Archon | Явная передача структурированных данных между шагами | У нас контекст через общий payload. Явная подстановка делает цепочки более предсказуемыми |

### Кластер 2: Безопасность и контроль автономного выполнения

> **Проблема:** task-orchestrator выполняет shell-команды без ограничений. Для автономного выполнения в CI/CD нужна sandboxing и policy enforcement.

#### 🟢 Quick wins (P2)

| Паттерн | Источники | Суть | Обоснование |
|---|---|---|---|
| **Exec policy (rules)** | Codex (.rules файлы), Claude Code (allow/deny lists) | Декларативные правила: banned prefixes (`bash -c`), safe command detection, per-path restrictions | Простой и надёжный механизм. Не требует Docker — работает на уровне chain executor |
| **Permission system** | Claude Code (auto-accept/ask/deny), Crush (allow-list), Codex (split FS permissions) | Ограничение доступных runner'ов и команд для цепочки: allow/deny per step | Для CI/CD: `--allowedTools`, `--max-turns` — аналогичные ограничения для chain execution |

#### 🟡 Среднесрочные (P2–P3)

| Паттерн | Источники | Суть | Обоснование |
|---|---|---|---|
| **Docker-based sandboxing** | Codex (iptables + Docker), Copilot Agent HQ (container isolation) | Shell-команды в Docker-контейнере с network whitelist | Для production CI/CD — критически важно. Codex — наиболее полная реализация: iptables + ipset + auto-cleanup |
| **Guardian (LLM safety reviewer)** | Codex | Pre-execution LLM-based risk assessment: data exfiltration, credential probing, destructive actions | Дополняет наши post-execution quality gates. Guardian оценивает risk ДО выполнения, gates — ПОСЛЕ |
| **Network isolation** | Codex (iptables/ipset) | Default DROP, whitelist доменов (API endpoints, git servers) | Блокировка data exfiltration через network-level firewall |
| **Policy engine** | Copilot Agent HQ (org-level policies) | Организационные политики: scope, permissions, audit | Для enterprise-использования: ограничение chain execution по env, repo, team |

### Кластер 3: Расширенные модели оркестрации

> **Проблема:** YAML-цепочки — линейные. Нет conditional branching, parallel execution, sub-agents. DAG-миграция — слишком масштабная для текущего этапа.

#### 🟢 Quick wins (P2)

| Паттерн | Источники | Суть | Обоснование |
|---|---|---|---|
| **Loop с `until_bash`** | Archon | Detector завершения цикла через shell-команду (тесты прошли → стоп) | Усиление fix_iterations: сейчас только max_iterations, а с until_bash — детерминированная проверка |
| **Loop с `fresh_context`** | Archon | Каждый iteration с чистым контекстом (agent читает state с диска) | Альтернатива накоплению контекста: agent не «перегружается» историей предыдущих итераций |
| **Conditional branching** | Mastra AI (.branch()), LangGraph (conditional edges), Archon (when: expressions) | Условное ветвление внутри цепочки | Подтверждено 3+ проектами. Реализуемо без полной DAG-миграции через расширение YAML-chain DSL |

#### 🟡 Среднесрочные (P3)

| Паттерн | Источники | Суть | Обоснование |
|---|---|---|---|
| **Typed I/O per step** | Mastra AI (Zod), LangGraph (TypedDict), Archon (JSON Schema) | Схемы валидации входных/выходных данных каждого шага | Повышает надёжность цепочек: невалидный input → fail-fast. Подтверждено 3+ проектами |
| **Sub-agent pattern** | Claude Code (Task tool), Codex (spawn/wait/close_agent), OpenHands SDK (DelegateTool) | Изолированный контекст подзадачи, потенциально параллельно | «Chain внутри chain» с собственным бюджетом и контекстом. Для dynamic chains |
| **Parallel execution** | Archon (DAG layers), pi_agent_rust (read-only tools), Mastra AI (.parallel()) | Параллельное выполнение независимых шагов | Оптимизация: lint + type-check + tests одновременно |
| **Per-step model override** | Archon (per-node provider/model), Mastra AI, Codex (custom agent roles) | Дешёвая модель для простых шагов, дорогая для сложных | Оптимизация стоимости: классификация → Haiku, кодогенерация → Sonnet |
| **Processor pipeline** | Mastra AI (6 фаз), OpenHands SDK (condenser pipeline) | Middleware-паттерн: pre/post обработка на уровне шага | Расширение decorator pattern: более granular контроль (input → output) |

### Кластер 4: Контекст и memory

> **Проблема:** Контекст между шагами — общий payload без сжатия. Нет ограничения на размер контекста. Нет памяти между запусками.

#### 🟡 Среднесрочные (P3)

| Паттерн | Источники | Суть | Обоснование |
|---|---|---|---|
| **Auto-compaction / summarization** | Crush, pi_agent_rust, OpenHands SDK, Mastra AI, Claude Code, Codex (6/13 проектов) | LLM-суммаризация при context overflow | Широко распространённый паттерн (6/13). Для длинных цепочек и dynamic loops |
| **Bootstrap budget** | OpenClaw | Ограничение на размер context injection (AGENTS.md, skills, system prompts) | Защита от oversized промптов — context injection не превышает N% context window |
| **Pluggable context engine** | OpenClaw (ingest/assemble/compact/maintain) | Формализованный интерфейс для context management lifecycle | Самый продвинутый подход к context management из исследованных |

#### 🔵 R&D (долгосрочные)

| Паттерн | Источники | Суть | Обоснование |
|---|---|---|---|
| **Observational memory** | Mastra AI (Observer + Reflector agents) | Автоматическая компрессия истории через выделение observations | Для dynamic loops с memory: агент «учится» на предыдущих запусках |
| **Memory between runs** | CrewAI (short+long term), LangGraph (store) | Персистентная память между запусками цепочек | Кэширование результатов типовых задач, обучение на опыте |
| **Hierarchical context discovery** | Claude Code (CLAUDE.md per directory), Codex (AGENTS.md hierarchical) | Динамическая загрузка контекста по мере необходимости | Экономия tokens: загружать только релевантный контекст |

### Кластер 5: Архитектурные паттерны (R&D)

> **Проблема:** Линейные YAML-цепочки могут стать ограничением для сложных сценариев. Нужен путь эволюции.

#### 🔵 R&D

| Паттерн | Источники | Суть | Обоснование |
|---|---|---|---|
| **Graph/DAG orchestration** | LangGraph (StateGraph), Archon (DAG + topological layers) | Произвольный направленный граф вместо линейной цепочки | Самый гибкий подход, но высокий порог входа. Подтверждён 2 проектами. Отдельный PR с обоснованием |
| **Checkpoint / Durable execution** | LangGraph (checkpoint + replay) | Сохранение состояния после каждого шага, resume после сбоя | LangGraph — единственный с полноценным durable execution |
| **SOP / Message-passing** | MetaGPT (watch/cause_by routing), CrewAI (event-driven Flows) | Event-driven активация шагов по типу результата, а не по позиции | Для dynamic chains: шаг активируется когда готов его input, а не по порядку |
| **Human-in-the-loop** | Archon (approval nodes), Mastra AI (suspend/resume), Copilot Workspace (plan review) | Пауза для подтверждения человеком в критических точках | Для production: LLM генерирует chain → человек подтверждает → оркестратор выполняет |
| **Workflow nesting** | Mastra AI (chain как шаг другой chain) | Вложенные цепочки: реализация → (review + fix) → deploy | Композиция chain из переиспользуемых подцепочек |
| **Git worktree isolation** | Archon (IIsolationProvider) | Каждый run в своём git worktree — параллельные runs не конфликтуют | Для параллельного выполнения цепочек в одном репозитории |

### Полный перечень индивидуальных рекомендаций

<details>
<summary>📋 Развернуть полный список (по каждому фреймворку)</summary>

#### Quick wins (P2)

* Crush: loop detection (защита от зацикливания в fix_iterations) — 🟡 P2

#### Среднесрочные (P2)

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
* Claude Code: hooks system (pre/post step execution через shell-скрипты) — декларативная альтернатива decorator pattern — 🟡 P2
* Claude Code: permission system (allow/deny для runner'ов и команд, CI/CD) — 🟡 P2
* Claude Code: sub-agent pattern (Task tool: изолированный контекст, потенциально параллельно) — 🟡 P2
* GitHub Copilot Agent HQ: Issue → Agent → PR workflow pattern (webhook-triggered chains, PR review chains) — 🟡 P2
* GitHub Copilot Agent HQ: sandboxed execution (Docker-container изоляция для shell-команд в CI/CD) — 🟡 P2
* GitHub Copilot Agent HQ: policy engine (permissions, scopes, org-level ограничения) — 🟡 P2
* Docker Agent + Codex: Docker-based sandboxing (container isolation + iptables firewall + domain whitelist) — 🟡 P2
* Docker Agent + Codex: network isolation (iptables/ipset — DROP default, whitelist доменов) — 🟡 P2
* Docker Agent + Codex: Guardian (LLM-based safety reviewer — data exfiltration, credential probing, destructive actions) — 🟡 P2
* Docker Agent + Codex: exec policy (rules-based command filtering — banned prefixes, safe command detection) — 🟡 P2
* Docker Agent + Codex: split filesystem permissions (per-path read/write/none) — 🟡 P2
* Docker Agent + Codex: hierarchical multi-agent (spawn/send_message/wait/close_agent + depth limit + mailbox) — 🟡 P2

#### Долгосрочные / R&D (P3)

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
* Claude Code: hierarchical context discovery (CLAUDE.md: dynamic loading по директории) — 🟡 P3
* Claude Code: slash commands как макросы (файл = команда, $ARGUMENTS placeholder) — 🟡 P3
* Claude Code: headless CI/CD mode (--max-turns, --allowedTools, JSON output) — 🟡 P3
* GitHub Copilot Agent HQ: Plan → Review → Execute (LLM-generated dynamic chains с human-in-the-loop) — 🟡 P3
* GitHub Copilot Agent HQ: knowledge base integration (обогащение контекста документацией) — 🟡 P3
* Docker Agent + Codex: auto-compaction (LLM summarization при context overflow, inline + remote) — 🟡 P3
* Docker Agent + Codex: plan mode (read-only exploration перед execution) — 🟡 P3
* Docker Agent + Codex: session persistence (SQLite + rollout JSONL — resumable sessions) — 🟡 P3

</details>

---

## Общие тренды

> Анализ выполнен на основе всех 13 исследований. Тренды сгруппированы по значимости для архитектуры task-orchestrator.

### 1. Уникальная позиция task-orchestrator

**Ни один из исследованных проектов — ни open-source, ни коммерческий — не имеет полного набора:** chains + retry с backoff + circuit breaker + quality gates + бюджетный контроль + fix_iterations + fallback routing. Это нашственная (genuine) комбинация, отличающая task-orchestrator от всех 13 фреймворков.

**Ни один проприетарный продукт** (Claude Code, GitHub Copilot Agent HQ, OpenAI Codex) не имеет retry с backoff, circuit breaker, quality gates, budget limits или декларативных chains — все наши ключевые отличия актуальны даже против крупнейших коммерческих AI-agent продуктов.

**Ближайший аналог** по уровню абстракции — Archon (TypeScript/Bun), который тоже оркестирует внешние AI-ассистенты через subprocess SDK. Однако Archon не имеет circuit breaker, quality gates или бюджетного контроля.

### 2. Agent Loop — доминирующая модель выполнения

**11 из 13 фреймворков** используют базовую модель `LLM → tool call → observation → LLM → ...` (Crush, pi_agent_rust, CrewAI, OpenHands SDK, MetaGPT, OpenClaw, Claude Code, Copilot Agent HQ, Codex и др.). Только LangGraph (graph/DAG с superstep execution) и Archon (DAG + subprocess SDK) используют принципиально другие модели.

**Вывод для task-orchestrator:** Наша модель (YAML chain → runner call → payload) — это оркестрация поверх agent loop. Это правильный уровень: мы не дублируем LLM interaction, а управляем им.

### 3. Разделение на два уровня абстракции

Все 13 проектов чётко делятся на два уровня:

| Уровень | Проекты | Что делают | Аналог в task-orchestrator |
|---|---|---|---|
| **SDK / Agent runtime** | Crush, pi_agent_rust, OpenHands SDK, Mastra AI, Claude Code, Codex, OpenClaw | Работают на уровне прямых LLM API | Runner'ы (pi, codex) |
| **Оркестратор / Workflow engine** | CrewAI, LangGraph, AutoGen, Archon, MetaGPT, Copilot Workspace | Управляют потоком выполнения между агентами/шагами | Chain executor |

**MetaGPT** подтверждает наш подход концептуально: `Team` (chain) + `investment` (budget) + `n_round` (max_iterations) + `idle detection` — практически идентично нашим YAML chains.

### 4. SKILL.md / AGENTS.md — де-факто стандарт

**8 из 13 проектов** используют SKILL.md или аналогичный формат для формализации agent capabilities:
- Crush, pi_agent_rust, CrewAI, OpenHands SDK, Archon, OpenClaw, Mastra AI, Codex
- Формат: YAML frontmatter + markdown body, discovery из нескольких мест, валидация
- Стандарт [agentskills.io](https://agentskills.io) получает широкое распространение

**AGENTS.md** используется как минимум в 5 проектах (Crush, pi_agent_rust, OpenHands SDK, Codex, task-orchestrator) — де-факто стандарт для AI-agent контекста.

### 5. MCP (Model Context Protocol) — повсеместный протокол расширения

**9 из 13 проектов** поддерживают MCP:
- Crush, CrewAI, OpenHands SDK, Archon, OpenClaw, Mastra AI, Claude Code, Copilot Agent HQ, Codex
- MCP — стандарт де-факто для расширения возможностей AI-агентов через внешние tool-серверы

**Вывод:** MCP-поддержка в task-orchestrator — вопрос времени. Но реализовывать нужно на уровне runner'ов, не оркестратора.

### 6. Контекст-менеджмент — повсеместная проблема

**6 из 13 проектов** реализуют auto-compaction / auto-summarization при context overflow:
- Crush, pi_agent_rust, OpenHands SDK, Mastra AI, Claude Code, Codex
- Все используют LLM-суммаризацию для сжатия истории
- OpenClaw пошёл дальше: формализованный `ContextEngine` interface (ingest → assemble → compact → maintain) с tokenBudget
- Mastra AI — самый продвинутый подход: Observer + Reflector agents с async buffering

**Вывод:** Для длинных цепочек и dynamic loops контекст-менеджмент станет необходим. Но для текущих конечных цепочек (max_iterations) это P3.

### 7. Безопасность автономного выполнения — зреющий тренд

**4 проекта** имеют продвинутые модели безопасности:

| Проект | Подход | Уровень зрелости |
|---|---|---|
| **Codex** | Guardian (LLM safety) + exec policy (rules) + Docker sandbox (iptables) + split FS permissions | Production-ready, defence in depth |
| **Copilot Agent HQ** | Docker-container sandbox + org-level policy engine + audit | Production-grade, enterprise |
| **OpenHands SDK** | Security risk assessment (LLM + heuristics) + confirmation policies + defense-in-depth rails | SDK-level, composable |
| **Claude Code** | Permission system (allow/deny) + tiered prompts | Basic but effective |

**Codex — наиболее полная реализация:** трёхуровневая модель (rules filter → LLM safety review → container isolation). Для CI/CD — наиболее готовый к production подход.

**Вывод:** Безопасность станет критичной при переходе к автономному выполнению в CI/CD. Рекомендуется начать с exec policy (rules) — это quick win.

### 8. Sub-agents / Multi-agent — тренд к иерархической декомпозиции

**8 из 13 проектов** поддерживают sub-agents или multi-agent:
- Claude Code (Task tool), Codex (spawn/send_message/wait/close_agent с depth limit), OpenHands SDK (DelegateTool), OpenClaw (ACP spawn с limits), Mastra AI (agent network), Archon (inline sub-agents), CrewAI (Crew), AutoGen (group chat)
- Codex — наиболее продвинутая sub-agent система: mailbox pattern, fork modes, role system

**Вывод:** Sub-agent pattern — готовый механизм для dynamic chains. Рекомендуется как P2: «chain внутри chain» с изолированным контекстом.

### 9. Conditional branching — востребованная возможность

**4 проекта** реализуют conditional branching:
- LangGraph (conditional edges), Archon (`when:` expressions), Mastra AI (`.branch()`), Copilot Workspace (plan branching)

**Вывод:** Conditional branching — самый запрашиваемый паттерн для расширения YAML chains. Реализуемо без полной DAG-миграции.

### 10. Typed I/O — повышение надёжности цепочек

**3 проекта** используют типизированные схемы для I/O шагов:
- Mastra AI (Zod-схемы), LangGraph (TypedDict + reducers), Archon (JSON Schema)

**Вывод:** Валидация входных/выходных данных каждого шага через JSON Schema — повышение надёжности. Реализуемо в PHP через Symfony Validator или JSON Schema.

### 11. Error classification — повторяющийся паттерн

**3 проекта** классифицируют ошибки для умного retry:
- Archon (FATAL/TRANSIENT/UNKNOWN), OpenClaw (6 категорий по HTTP status), Codex (Guardian risk taxonomy)

**Вывод:** Классификация ошибок — актуальное улучшение для RetryingAgentRunner. Не тратить попытки retry на неисправимые ошибки (401, 403).

### 12. Архитектурная зрелость проекта

**Слоистая DDD-архитектура** (Domain/Application/Infrastructure) — редкость среди исследованных проектов. Большинство используют плоскую структуру (`internal/`, `src/`, `lib/`). Только AutoGen имеет слоистую архитектуру (core/agentchat/ext), но без DDD.

**Decorator pattern** через интерфейс (AgentRunnerInterface) — уникальный для task-orchestrator подход. Ни один из исследованных проектов не использует decoration для добавления cross-cutting concerns (retry, circuit breaker, budget). Типичные подходы: direct call, composition, middleware pipeline.

**Вывод:** Наша архитектура — сильная сторона. Не нужно менять ради «как у всех».

### 13. Языковой и экосистемный ландшафт

| Язык | Проекты | Примечание |
|---|---|---|
| **Python** | CrewAI, LangGraph, AutoGen, OpenHands SDK, MetaGPT | Доминирующий язык для AI-agent фреймворков |
| **TypeScript** | Archon, OpenClaw, Mastra AI | Растущая экосистема, особенно для workflow engines |
| **Rust** | pi_agent_rust, Codex (codex-rs) | High-performance CLI-агенты |
| **Go** | Crush | TUI-ориентированный агент |
| **Проприетарный** | Claude Code, Copilot Agent HQ | Закрытый код, анализ по документации |

**Task-orchestrator (PHP/Symfony)** — единственный в своей нише: Symfony Bundle для chain-оркестрации AI-агентов. Это не недостаток — это уникальная позиция в PHP-экосистеме.

### 14. Отдельные наблюдения

* **LangGraph — единственный** с durable execution и checkpoint persistence. Ключевое преимущество для длинных workflows.
* **AutoGen в maintenance mode**, Microsoft рекомендует Microsoft Agent Framework (MAF). Заимствование паттернов безопасно, но dependency невозможна.
* **CrewAI — самый «productized»:** Enterprise (Crew Control Plane), сертификация 100k+ разработчиков, monetization через cloud.
* **Archon v2 переписан с Python на TypeScript/Bun** — показательный пример смены стека для production-ready проекта.
* **OpenHands SDK — наиболее зрелая Action/Observation-модель:** типизированные Action/Observation (Pydantic), security risk assessment, stuck detection, context condensation, sub-agent delegation — при этом SDK работает на уровне single agent loop, не оркестрации.
* **Mastra AI и Archon — два TypeScript-проекта с workflow engine**, но на разных уровнях: Mastra = SDK (LLM API), Archon = orchestrator (subprocess SDK). Task-orchestrator ближе к Archon.
* **OpenClaw — production-ready multi-channel personal assistant** (20+ мессенджеров, desktop/mobile apps, voice wake). Не фреймворк оркестрации, а законченный продукт.
* **Copilot Agent HQ подтверждает тренд multi-model marketplace:** единый API поверх GPT-4, Claude, Gemini, Llama. Индустриальный аналог нашего AgentRunnerInterface.

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
| 2026-04-22 | Технический писатель (Гермиона) | Создан отчёт claude-code-comparison.md, заполнена строка Claude Code (#11), добавлены рекомендации и тренды |
| 2026-04-22 | Технический писатель (Гермиона) | Создан отчёт copilot-agent-hq-comparison.md, заполнена строка GitHub Copilot Agent HQ (#12), добавлены рекомендации и тренды |
| 2026-04-22 | Технический писатель (Гермиона) | Создан отчёт docker-agent-codex-comparison.md, заполнена строка Docker Agent + OpenAI Codex (#13), добавлены рекомендации и тренды. Все 13 исследований завершены. |
| 2026-04-22 | Технический писатель (Гермиона) | Финализация сводной таблицы: добавлен Executive Summary, реорганизованы рекомендации по 5 тематическим кластерам (Quick wins / Среднесрочные / R&D), консолидированы 14 общих трендов с кросс-анализом всех 13 исследований. |
