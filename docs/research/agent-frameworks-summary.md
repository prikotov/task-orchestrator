# Сводная таблица: AI-agent фреймворки и оркестраторы

> **Цель:** Сравнить исследованные AI-agent фреймворки с task-orchestrator, определить паттерны для заимствования.
> **Эпик:** [EPIC-research-agent-frameworks-comparison](framework-comparisons/../todo/EPIC-research-agent-frameworks-comparison.md)

---

## Сравнительная таблица

> **Статус заполнения:** 25 / 25 исследований

| # | Фреймворк | Язык | Категория | Модель оркестрации | State mgmt | Error handling | Extensibility | Вердикт | Отчёт |
|:---:|---|---|---|---|---|---|---|---|---|
| 1 | Charmbracelet Crush | Go | `CLI-agent` | `agent-loop` (LLM → tool call → LLM → ...) | `persistent` (SQLite) | `manual` (только retry при 401) | `MCP + SKILL.md + config + sub-agents (Coder → Task)` | 🟡 заимствовать отдельные паттерны | [crush-comparison.md](framework-comparisons/crush-comparison.md) ✅ |
| 2 | pi_agent_rust | Rust | `CLI-agent` | `agent-loop` (LLM → tool call → LLM → ...) | `persistent` (JSONL tree + SQLite index) | `basic retry` (exponential backoff, global config) | `Extensions (QuickJS/WASM) + Skills (SKILL.md) + Packages` | 🟡 заимствовать отдельные паттерны | [pi-agent-rust-comparison.md](framework-comparisons/pi-agent-rust-comparison.md) ✅ |
| 3 | CrewAI | Python | `multi-agent` | `sequential / hierarchical (Crews) + event-driven (Flows)` | `in-memory + checkpoint (SQLite)` | `basic retry (LLM level)` | `custom tools + Skills (SKILL.md) + MCP + RAG + Flows` | 🟡 заимствовать отдельные паттерны | [crewai-langgraph-autogen-comparison.md](framework-comparisons/crewai-langgraph-autogen-comparison.md) ✅ |
| 4 | LangGraph | Python | `multi-agent` | `graph/DAG (StateGraph) + superstep execution` | `TypedDict + reducers + checkpoint (memory/SQLite/PostgreSQL)` | `RetryPolicy per node, durable execution` | `subgraphs + conditional edges + Send (map-reduce) + interrupts` | 🟡 заимствовать отдельные паттерны | *(в отчёте №3)* ✅ |
| 5 | AutoGen (Microsoft) | Python + .NET | `multi-agent` | `event-driven (Core) / group chat (AgentChat) / graph` | `message thread + model context` | `CancellationToken, exception propagation` | `custom agents + tools + group chat managers + subscriptions` | 🟡 заимствовать отдельные паттерны | *(в отчёте №3)* ✅ |
| 6 | OpenHands SDK | Python | `SDK` | `agent-loop` (LLM → Action → Tool → Observation → LLM → ...) | `event-stream` (file-backed) | `retry+backoff (tenacity) + fallback LLM profiles` | `custom tools + MCP + Skills (SKILL.md) + Plugins + Hooks + sub-agents` | 🟡 заимствовать отдельные паттерны | [openhands-sdk-comparison.md](framework-comparisons/openhands-sdk-comparison.md) ✅ |
| 7 | Archon | TypeScript (Bun) | `workflow-engine` | `DAG (YAML) + topological layers + parallel execution` | `persistent (SQLite/PostgreSQL, 8 таблиц)` | `node-level retry (default 2, max 5) + error classification (FATAL/TRANSIENT/UNKNOWN) + fallbackModel` | `IPlatformAdapter + IAgentProvider + IIsolationProvider + MCP + Hooks (21 SDK event) + Skills + Commands` | 🟡 заимствовать отдельные паттерны | [archon-comparison.md](framework-comparisons/archon-comparison.md) ✅ |
| 8 | MetaGPT | Python | `multi-agent` | `SOP (BY_ORDER) / react-loop / plan-and-act` | `in-memory (Memory + index by cause_by)` | `budget guard (NoMoneyException)` | `custom roles + actions + tools + skills + RAG` | 🟡 заимствовать отдельные паттерны | [metagpt-openclaw-comparison.md](framework-comparisons/metagpt-openclaw-comparison.md) ✅ |
| 9 | OpenClaw | TypeScript (Node.js) | `CLI-agent` | `agent-loop` (LLM → tool call → observation → LLM → ...) | `file-backed transcripts + pluggable context engine` | `model failover с cooldown + error classification` | `plugin SDK + 40+ extensions + Skills + MCP + sandbox` | 🟡 заимствовать отдельные паттерны | *(в отчёте №8)* ✅ |
| 10 | Mastra AI | TypeScript (Node.js) | `SDK` | `step-based workflow (chaining API: .then/.branch/.parallel/.dowhile/.dountil/.foreach) + agent-loop` | `pluggable (22+ адаптеров: LibSQL, PostgreSQL, MongoDB, Redis, ClickHouse, D1, Upstash и др.)` | `basic retry (attempts+delay, no exponential backoff) + TripWire (abort with retry hint)` | `Processors pipeline + MCP + Tools (Zod schemas) + custom storage + Agent network (delegation)` | 🟡 заимствовать отдельные паттерны | [mastra-ai-comparison.md](framework-comparisons/mastra-ai-comparison.md) ✅ |
| 11 | Claude Code | — (проприетарный) | `CLI-agent` | `agent-loop` (LLM → tool call → observation → LLM → ...) | `in-memory + auto-compact` | `basic API retry` (429/500, без backoff) | `MCP + 30+ tools + hooks (20+ events, 4 handler types) + CLAUDE.md + slash commands + sub-agents + Agent SDK + agent teams` | 🟡 заимствовать отдельные паттерны | [claude-code-comparison.md](framework-comparisons/claude-code-comparison.md) ✅ |
| 12 | GitHub Copilot Cloud Agent | — (проприетарный) | `cloud/SaaS` | `agent-loop` (cloud sandbox, Issue→Plan→Execute→PR) | `cloud-managed` (session-based, GitHub infrastructure) | `transparent` (built-in API retry, org-level rate limits) | `MCP + custom instructions + GitHub Actions + GitHub Models marketplace + Copilot CLI/SDK/Spark` | 🟡 заимствовать отдельные паттерны | [copilot-agent-hq-comparison.md](framework-comparisons/copilot-agent-hq-comparison.md) ✅ |
| 13 | Docker Agent + OpenAI Codex | Rust (codex-rs) + TypeScript | `CLI-agent + cloud/SaaS` | `agent-loop` (LLM → tool call → observation → LLM → ...) + hierarchical multi-agent | `persistent` (SQLite + rollout JSONL files) + `auto-compact` | `basic API retry` + `Guardian (LLM safety reviewer)` + `Starlark exec policy (rules)` | `MCP client/server` + `SKILL.md` + `AGENTS.md` + `hooks` + `memories` + `plugins` + `Starlark exec policy` + `external-sandbox` + `custom agent roles` + `Docker sandbox` | 🟡 заимствовать отдельные паттерны | [docker-agent-codex-comparison.md](framework-comparisons/docker-agent-codex-comparison.md) ✅ |
| 14 | Agno (бывший Phi) | Python | `SDK` | `step-based workflow (Step/Steps/Loop/Parallel/Router/Condition) + agent-loop + 4 team modes (coordinate/route/broadcast/tasks)` | `pluggable (12+ адаптеров: PostgreSQL, SQLite, MySQL, Redis, MongoDB, DynamoDB, Firestore, ...)` | `FallbackConfig (error-specific: on_error/on_rate_limit/on_context_overflow) + max_retries per step` | `Tools + MCP + Skills + Guardrails (PII, prompt injection) + Evals + Hooks + custom DB + HITL (3 режима) + Compression` | 🟡 заимствовать отдельные паттерны | [agno-comparison.md](framework-comparisons/agno-comparison.md) ✅ |
| 15 | Paperclip AI | TypeScript (Node.js) | `meta-orchestration` | `heartbeat-based (scheduled/event-driven wakeup → adapter invocation → result)` | `persistent` (PostgreSQL / embedded PGlite, ~70 таблиц) | `transient failure retry с bounded backoff (2m→10m→30m→2h) + error classification (transient_upstream) + escalation strategy` | `Plugin SDK (events/jobs/data/tools/state/UI) + 7+ agent adapters (Claude/Codex/Cursor/Gemini/OpenClaw/pi/HTTP) + MCP + Skills + Company Skills + Adapter interface + Execution policies + Environments` | 🟡 заимствовать отдельные паттерны | [paperclip-ai-comparison.md](framework-comparisons/paperclip-ai-comparison.md) ✅ |
| 16 | AgentCraft | — (проприетарный) | `GUI-orchestrator` | `GUI wrapper (RTS-интерфейс поверх внешних агентов: Claude Code, OpenCode, Cursor, OpenClaw)` | `local (git worktrees, mission history)` | `нет (делегируется агентам)` | `4 agent integrations + Skill Scrolls + Agent Teams + Docker/Apple Containers + Git Worktrees + Scheduled Tasks + Remote Access (tunnels + PWA) + Voice Input + Channels (upcoming)` | 🟡 заимствовать отдельные паттерны | [agentcraft-comparison.md](framework-comparisons/agentcraft-comparison.md) ✅ |
| 17 | Factory Missions | — (проприетарный) | `multi-agent SaaS` | `orchestrator-worker (LLM orchestrator → serial worker sessions → auto-injected validators)` | `file-based (mission.md, features.json, validation-state.json, AGENTS.md, .factory/)` | `failed feature → orchestrator handles (no retry; creates fix features)` | `SKILL.md per worker type + .factory/library/ + services.yaml + 5 communication patterns (delegation/creator-verifier/broadcast/negotiation/direct)` | 🟡 заимствовать отдельные паттерны | [missions-framework-comparison.md](framework-comparisons/missions-framework-comparison.md) ✅ |
| 18 | Hermes Agent (Nous Research) | Python | `CLI-agent` | `agent-loop` (LLM → tool call → result → LLM → ...) + subagent delegation | `persistent` (SQLite + FTS5 search) + `file-based memory` (MEMORY.md, USER.md) + Honcho dialectic modeling | `error classification (20+ failover reasons) + credential pool rotation (4 strategies) + fallback model` | `40+ tools + MCP + SKILL.md (agentskills.io) + Plugin system (memory/context_engine/model-providers/kanban) + 7 terminal backends + 15+ messaging platforms + Kanban multi-agent` | 🟡 заимствовать отдельные паттерны | [hermes-agent-comparison.md](framework-comparisons/hermes-agent-comparison.md) ✅ |
| 19 | Oz (Warp) | — (проприетарный SaaS; SDK: Python, TypeScript) | `cloud/SaaS` | `cloud-managed agent runs` (QUEUED → INPROGRESS → SUCCEEDED/FAILED) + triggers (cron/webhook/API) | `cloud-managed` (run_id + state transitions + session links) | `SDK built-in retries` (HTTP-level) | `REST API + SDK (Python/TS) + Skills (SKILL.md) + MCP + Rules + Agent Profiles & Permissions + Integrations (Slack/Linear/GitHub Actions) + Cron Schedules` | 🟡 заимствовать отдельные паттерны | [oz-cloud-agents-comparison.md](framework-comparisons/oz-cloud-agents-comparison.md) ✅ |
| 20 | Sandcastle (Matt Pocock) | TypeScript (Node.js) | `sandbox-orchestration` | `agent invocation loop` (run agent in sandbox → collect commits → iterate) | `git worktrees + commit collection` (без persistent DB) | `typed error hierarchy (20+ error types) + idle timeout + AbortSignal + worktree preservation on failure` | `AgentProvider (4 built-in: Claude Code/Codex/Pi/OpenCode) + SandboxProvider (Docker/Podman/Vercel/Daytona/custom) + Hooks + Templates (5) + Structured Output (Zod) + Completion Signal` | 🟡 заимствовать отдельные паттерны | [sandcastle-comparison.md](framework-comparisons/sandcastle-comparison.md) ✅ |
| 21 | Kilo Code | TypeScript (Bun, Effect-TS) | `CLI-agent + SDK` | `agent-loop` (LLM → tool call → observation → LLM → ...) + subagent delegation (task tool: wave-based parallel execution) | `in-memory + context compaction (LLM summarization) + session persistence (SQLite)` | `error classification (ContextOverflow/API 5xx/rate limit/Kilo errors) + exponential backoff + Retry-After header parsing` | `Custom agents (JSON/Markdown/CLI) + MCP + Skills (SKILL.md) + Plugins + Workflows (slash commands) + Permission system (glob patterns) + 7 built-in agents` | 🟡 заимствовать отдельные паттерны | [opencode-orchestrator-comparison.md](framework-comparisons/opencode-orchestrator-comparison.md) ✅ |
| 22 | OpenCode (anomalyco) | TypeScript (Bun, Effect-TS) | `CLI-agent + Desktop + SDK` | `agent-loop` (LLM → tool call → observation → LLM → ...) + subagent delegation (task tool: isolated session + resume) | `persistent` (SQLite через Drizzle ORM + event-sourced sync) | `error classification (ContextOverflow/API 5xx/rate limit/FreeUsageLimitError/GoUsageLimitError) + exponential backoff + Retry-After parsing + doom loop detection` | `7 built-in agents + custom agents (MD) + AI-generated agents + Skills (SKILL.md) + MCP + Plugins + Permission system (glob patterns) + Git Worktrees + ACP + LSP + 23+ LLM providers` | 🟡 заимствовать отдельные паттерны | [opencode-comparison.md](framework-comparisons/opencode-comparison.md) ✅ |
| 23 | Oh My OpenAgent (OmO) | TypeScript (Bun, plugin для OpenCode) | `CLI-agent + multi-agent` | `agent-loop` (LLM → tool call → LLM → ...) + Discipline Agents (11) + Category System (8) + IntentGate (pre-routing) + Team Mode (lead + 8 members, shared mailbox + task list) | `persistent` (SQLite через OpenCode) + shared mailbox + shared task list + proactive context management (monitor + compaction + pruning) | `runtime fallback (per-agent provider chains, 429/503/529, cooldown) + doom loop detection (3 identical → ask) + error classification (унаследовано от OpenCode)` | `50+ hooks + Skill-Embedded MCPs + 11 Discipline Agents + 8 categories + custom agents + plugins + Agent-Model matching (per-agent fallback chains) + 75+ LLM providers` | 🟡 заимствовать отдельные паттерны | [oh-my-openagent-comparison.md](framework-comparisons/oh-my-openagent-comparison.md) ✅ |
| 24 | Duet (Aomni) | TypeScript (Bun, проприетарный) | `business-agent SaaS` | `skill-driven` (intent → skill selection → multi-phase autonomous execution) | `cloud-managed` (workspace + channels + file artifacts + Convex tables) | `prompt-driven` (gotchas в SKILL.md, нет retry/CB/fallback) | `SKILL.md skills (19 default + 12 industry) + UseCase surface placements + Composio integrations (19+) + Chat SDK adapters (8 platforms) + Cron tool + Build-apps tool` | 🟡 заимствовать отдельные паттерны | [duet-comparison.md](framework-comparisons/duet-comparison.md) ✅ |
| 25 | Multica | TypeScript + Go | `project-management platform` | `daemon-based task queue (poll + WS wakeup) + autopilot (cron/webhook) + session resumption` | `persistent` (PostgreSQL 17, 28 таблиц, ACID) + event-sourced (activity_log + WS broadcast) | `task-level failure classification (4 категории) + poisoned session detection + runtime health (heartbeat + sweeper) + admission check + orphan recovery` | `Skills (SKILL.md, import) + 11 agent providers + autopilot + comprehensive CLI + MCP per agent + autopilot triggers (cron/webhook/API)` | 🟡 заимствовать отдельные паттерны | [multica-comparison.md](framework-comparisons/multica-comparison.md) ✅ |

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

По результатам исследования 25 AI-agent фреймворков и инструментов можно сделать **три главных вывода**:
По результатам исследования 25 AI-agent фреймворков и инструментов можно сделать **три главных вывода**:

1. **task-orchestrator обладает уникальной комбинацией возможностей**, которой нет ни у одного из исследованных проектов: YAML-цепочки + retry с backoff + circuit breaker + quality gates (shell) + бюджетный контроль + fix_iterations + fallback routing + JSONL audit trail. Ни один фреймворк — ни open-source, ни проприетарный — не предлагает все эти механизмы вместе. **Paperclip AI** — ближайший аналог по уровню (мета-оркестратор), но работает на уровне компании/агентов, а не chain steps. **Duet** (Aomni, $4.4M seed) подтверждает тренд: даже well-funded SaaS-платформы для автономных AI-агентов не имеют retry с backoff, circuit breaker, quality gates или бюджетного контроля.

2. **Наибольший потенциал для заимствования** — в трёх кластерах: (а) интеллектуальная обработка ошибок (error classification, stuck detection, model failover), (б) безопасность автономного выполнения (sandboxing, exec policy, permission system), (в) расширенные модели оркестрации (conditional branching, parallel execution, sub-agents). **Duet** (#24) добавляет четвёртый кластер: (г) cron-triggered recurring execution и prompt engineering паттерны (gotchas, idempotency guidance, multi-phase workflows).
2. **Наибольший потенциал для заимствования** — в трёх кластерах: (а) интеллектуальная обработка ошибок (error classification, stuck detection, model failover), (б) безопасность автономного выполнения (sandboxing, exec policy, permission system), (в) расширенные модели оркестрации (conditional branching, parallel execution, sub-agents). **Duet** добавляет кластер: (г) skill-based специализация и use-case curation (SKILL.md формат, persona-based routing, prompt-level guardrails).

3. **Ближайшие аналоги** по уровню абстракции — Archon (TypeScript/Bun, chain-level оркестрация через subprocess SDK) и Paperclip AI (TypeScript/Node.js, company-level мета-оркестратор). Archon не имеет circuit breaker, quality gates или бюджетного контроля. Paperclip AI имеет развитый budget enforcement, run recovery и plugin system, но не поддерживает chains, circuit breaker или quality gates. Наши ключевые отличия сохраняются. **Duet** — не аналог, а complement: skill-based SaaS-агент для continuous business automation, не chain orchestrator.

---

## Рекомендации по заимствованию

> Рекомендации сгруппированы по тематическим кластерам и приоритизированы: **Quick wins** (реализуемо за 1–2 задачи), **Среднесрочные** (требуют архитектурных решений), **R&D** (исследование перед реализацией).

### Кластер 1: Интеллектуальная обработка ошибок и восстановление

> **Проблема:** Сейчас `RetryingAgentRunner` делает retry на любую ошибку без разбора. Нет защиты от зацикливания в fix_iterations. Нет fallback на уровне модели.

#### 🟢 Quick wins (P2)

| Паттерн | Источники | Суть | Обоснование |
|---|---|---|---|
| **Error classification** | Archon (FATAL/TRANSIENT/UNKNOWN), OpenClaw (6 категорий), Codex (Guardian), Paperclip AI (transient_upstream errorFamily), Sandcastle (20+ typed errors: AgentError, AgentIdleTimeoutError, ExecError, SyncError, WorktreeError и др.), **Kilo Code** (ContextOverflow/API 5xx/rate limit/FreeUsageLimitError/Kilo errors) | Классификация ошибок перед retry: FATAL → не retry, TRANSIENT → retry с backoff, UNKNOWN → консервативный подход | Не тратить попытки retry на заведомо неисправимые ошибки (401, 403). Подтверждён 5+ проектами. Kilo Code добавляет Retry-After header parsing и context overflow detection |
| **Error-specific fallback routing** | Agno (FallbackConfig: on_error/on_rate_limit/on_context_overflow) | При ошибке конкретного типа → переключение на альтернативный runner, а не retry того же | Дополнение к circuit breaker: CB защищает от cascade, error-specific routing переключает на альтернативу по типу ошибки |
| **Stuck / Loop detection** | Crush (window-based), OpenHands SDK (4+1: 4 активных + 1 TODO), Paperclip AI (evidence-based liveness + regex output analysis), **OpenCode** (doom loop: 3 идентичных tool call → permission ask) | Обнаружение зацикливания: повторяющиеся действия, повторяющиеся ошибки, чередование (context overflow — TODO) | Актуально для fix_iterations — если агент повторяет одни и те же действия, лучше остановить раньше. Paperclip AI добавляет evidence-based подход (подсчёт комментариев, документов, work products). OpenCode предлагает простейшую реализацию: 3 идентичных вызова = doom loop. Подтверждено 4 проектами |
| **Model failover с cooldown** | OpenClaw (per-profile), Archon (fallbackModel), OpenHands SDK (FallbackStrategy), Paperclip AI (Codex escalation: same_session → safer_invocation → fresh_session) | При недоступности модели → переключение на fallback с cooldown, чтобы не «долбить» упавший endpoint | Дополнение к нашему circuit breaker: CB защищает от cascade failures, failover — переключает на альтернативу. Paperclip AI добавляет escalation strategy — не просто retry, а с изменением параметров |

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
| **Exec policy (rules)** | Codex (.rules файлы), Claude Code (allow/deny lists), **Kilo Code** (bash allowlist 40+ команд + glob patterns + last-matching-wins) | Декларативные правила: banned prefixes (`bash -c`), safe command detection, per-path restrictions | Простой и надёжный механизм. Не требует Docker — работает на уровне chain executor. Kilo Code подтверждает подход: read-only bash отдельный набор (без git stash, пайпов, редиректов) |
| **Permission system** | Claude Code (auto-accept/ask/deny), Crush (allow-list), Codex (split FS permissions), **Kilo Code** (allow/ask/deny per tool + glob patterns + inheritance) | Ограничение доступных runner'ов и команд для цепочки: allow/deny per step | Для CI/CD: `--allowedTools`, `--max-turns` — аналогичные ограничения для chain execution. Kilo Code добавляет permission inheritance для subagents |

#### 🟡 Среднесрочные (P2–P3)

| Паттерн | Источники | Суть | Обоснование |
|---|---|---|---|
| **Docker-based sandboxing** | Codex (iptables + Docker), Copilot Cloud Agent (container isolation), Sandcastle (SandboxProvider: Docker/Podman/Vercel + SELinux + UID/GID alignment + network isolation) | Shell-команды в Docker-контейнере с network whitelist | Для production CI/CD — критически важно. Sandcastle — наиболее зрелая plug-and-play реализация: не Docker-специфичная, поддерживает Podman, Vercel VM, Daytona, и custom |
| **Guardian (LLM safety reviewer)** | Codex | Pre-execution LLM-based risk assessment: data exfiltration, credential probing, destructive actions | Дополняет наши post-execution quality gates. Guardian оценивает risk ДО выполнения, gates — ПОСЛЕ |
| **Network isolation** | Codex (iptables/ipset) | Default DROP, whitelist доменов (API endpoints, git servers) | Блокировка data exfiltration через network-level firewall |
| **Policy engine** | Copilot Cloud Agent (org-level policies) | Организационные политики: scope, permissions, audit | Для enterprise-использования: ограничение chain execution по env, repo, team |

### Кластер 3: Расширенные модели оркестрации

> **Проблема:** YAML-цепочки — линейные. Нет conditional branching, parallel execution, sub-agents. DAG-миграция — слишком масштабная для текущего этапа.

#### 🟢 Quick wins (P2)

| Паттерн | Источники | Суть | Обоснование |
|---|---|---|---|
| **Loop с `until_bash`** | Archon | Detector завершения цикла через shell-команду (тесты прошли → стоп) | Усиление fix_iterations: сейчас только max_iterations, а с until_bash — детерминированная проверка |
| **Completion signal** | Sandcastle (`<promise>COMPLETE</promise>`) | Agent эмитит маркер в stdout — оркестратор останавливает loop раньше max_iterations | Усиление fix_iterations: probabilistic early termination (агент может забыть эмитить). Дополнение к until_bash (deterministic) |
| **Loop с `end_condition` (callable)** | Agno (Loop: callable/CEL end_condition + forward_iteration_output) | Detector завершения цикла через произвольное условие или CEL-выражение | Обобщение until_bash: произвольная проверка вместо только shell. Для YAML нужен DSL или shell-команда |
| **Loop с `fresh_context`** | Archon | Каждый iteration с чистым контекстом (agent читает state с диска) | Альтернатива накоплению контекста: agent не «перегружается» историей предыдущих итераций |
| **Conditional branching** | Mastra AI (.branch()), LangGraph (conditional edges), Archon (when: expressions), Agno (Condition + Router) | Условное ветвление внутри цепочки | Подтверждено 4+ проектами. Реализуемо без полной DAG-миграции через расширение YAML-chain DSL |

#### 🟡 Среднесрочные (P3)

| Паттерн | Источники | Суть | Обоснование |
|---|---|---|---|
| **Typed I/O per step** | Mastra AI (Zod), LangGraph (TypedDict), Archon (JSON Schema), Sandcastle (Output.object/string с Zod-валидацией) | Схемы валидации входных/выходных данных каждого шага | Повышает надёжность цепочек: невалидный input → fail-fast. Подтверждено 4+ проектами |
| **Sub-agent pattern** | Claude Code (Task tool), Codex (spawn/wait/close_agent), OpenHands SDK (DelegateTool), Kilo Code (task tool: isolated session, permission inheritance, cost propagation, resume, parallel invocation), **OpenCode** (task tool: isolated session + permission inheritance + resume по task_id + no recursive delegation) | Изолированный контекст подзадачи, потенциально параллельно | «Chain внутри chain» с собственным бюджетом и контекстом. Для dynamic chains. OpenCode добавляет resume по task_id — уникальная возможность |
| **Parallel execution** | Archon (DAG layers), pi_agent_rust (read-only tools), Mastra AI (.parallel()), **Kilo Code** (wave-based: parallel tool calls в одном LLM-сообщении, dependency classification) | Параллельное выполнение независимых шагов | Оптимизация: lint + type-check + tests одновременно. Kilo Code добавляет wave-based подход — практичная альтернатива DAG |
| **Per-step model override** | Archon (per-node provider/model), Mastra AI, Codex (custom agent roles) | Дешёвая модель для простых шагов, дорогая для сложных | Оптимизация стоимости: классификация → Haiku, кодогенерация → Sonnet |
| **Processor pipeline** | Mastra AI (6 фаз), OpenHands SDK (condenser pipeline) | Middleware-паттерн: pre/post обработка на уровне шага | Расширение decorator pattern: более granular контроль (input → output) |

### Кластер 4: Контекст и memory

> **Проблема:** Контекст между шагами — общий payload без сжатия. Нет ограничения на размер контекста. Нет памяти между запусками.

#### 🟡 Среднесрочные (P3)

| Паттерн | Источники | Суть | Обоснование |
|---|---|---|---|
| **Auto-compaction / summarization** | Crush, pi_agent_rust, OpenHands SDK, Mastra AI, Claude Code, Codex, Kilo Code, **OpenCode** (7-секционный structured summary template: Goal/Constraints/Progress/Key Decisions/Next Steps/Critical Context/Relevant Files + pruning + preserve recent turns + replay) | LLM-суммаризация при context overflow | Широко распространённый паттерн (11/23). Для длинных цепочек и dynamic loops. OpenCode добавляет pruning, preserve recent и replay — три дополнительных механизма. OmO добавляет proactive compaction |
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
| **Human-in-the-loop** | Archon (approval nodes), Mastra AI (suspend/resume), Copilot Workspace (plan review), Agno (3 режима: confirmation/user_input/output_review) | Пауза для подтверждения человеком в критических точках | Для production: LLM генерирует chain → человек подтверждает → оркестратор выполняет. ⚠️ В CLI ограниченно: только интерактивный режим |
| **Workflow nesting** | Mastra AI (chain как шаг другой chain), Agno (Workflow как Step, до 10 уровней) | Вложенные цепочки: реализация → (review + fix) → deploy | Композиция chain из переиспользуемых подцепочек |
| **Git worktree isolation** | Archon (IIsolationProvider) | Каждый run в своём git worktree — параллельные runs не конфликтуют | Для параллельного выполнения цепочек в одном репозитории |

### Полный перечень индивидуальных рекомендаций

<details>
<summary>📋 Развернуть полный список (по каждому фреймворку)</summary>

#### Quick wins (P2)

* Crush: loop detection (защита от зацикливания в fix_iterations) — 🟡 P2
* Paperclip AI: run liveness / stuck detection (evidence-based + regex output analysis — защита от зацикливания в fix_iterations) — 🟡 P2
* Paperclip AI: error classification (transient_upstream errorFamily — классификация для умного retry) — 🟡 P2
* Paperclip AI: Codex transient failure escalation strategy (same_session → safer_invocation → fresh_session — retry с изменением стратегии) — 🟡 P2
* Hermes Agent: error classification (20+ FailoverReason с recovery hints — наиболее развитая система из исследованных) — 🟡 P2
* Hermes Agent: context file injection scanning (11 threat patterns + invisible unicode — защита от prompt injection в .md файлах) — 🟡 P2

#### Среднесрочные (P2)

* Crush: формализация Agent Skills (SKILL.md standard, discovery, validation) — 🟡 P2
* Paperclip AI: adapter execution context (rich result: error family, cost, HITL question, runtime services) — 🟡 P2
* pi_agent_rust: tool parallelism (параллельное выполнение read-only шагов в chain) — 🟡 P2
* AutoGen: декларативные termination conditions (timeout, token limit, keyword) — 🟡 P2
* OpenHands SDK: stuck detection (4 активных + 1 TODO — repeating action-obs, action-error, monologue, alternating; context-window loop не реализован) — 🟡 P2
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
* GitHub Copilot Cloud Agent: Issue → Agent → PR workflow pattern (webhook-triggered chains, PR review chains) — 🟡 P2
* GitHub Copilot Cloud Agent: sandboxed execution (Docker-container изоляция для shell-команд в CI/CD) — 🟡 P2
* GitHub Copilot Cloud Agent: policy engine (permissions, scopes, org-level ограничения) — 🟡 P2
* Docker Agent + Codex: Docker-based sandboxing (container isolation + iptables firewall + domain whitelist) — 🟡 P2
* Docker Agent + Codex: network isolation (iptables/ipset — DROP default, whitelist доменов) — 🟡 P2
* Docker Agent + Codex: Guardian (LLM-based safety reviewer — data exfiltration, credential probing, destructive actions) — 🟡 P2
* Docker Agent + Codex: exec policy (rules-based command filtering — banned prefixes, safe command detection) — 🟡 P2
* Docker Agent + Codex: split filesystem permissions (per-path read/write/none) — 🟡 P2
* Docker Agent + Codex: hierarchical multi-agent (spawn/send_message/wait/close_agent + depth limit + mailbox) — 🟡 P2
* Agno: error-specific fallback routing (FallbackConfig: on_error/on_rate_limit/on_context_overflow) — 🟡 P2
* Hermes Agent: credential pool / API key rotation (4 стратегии: fill_first, round_robin, random, least_used — дополнение к circuit breaker) — 🟡 P2
* Agno: Loop с end_condition (callable/CEL) — усиление fix_iterations — 🟡 P2
* Agno: conditional branching (Condition + Router) — 🟡 P2

#### Долгосрочные / R&D (P3)

* Crush: auto-summarization при переполнении контекста — 🟡 P3
* Paperclip AI: scoped budget policies (company/agent/project с warning thresholds + hard stop + auto-pause) — 🟡 P3
* Paperclip AI: goal alignment в промптах (goal ancestry: company → project → goal → issue) — 🟡 P3
* Paperclip AI: session compaction policy (per-adapter: max runs/tokens/age thresholds, adapter-managed vs. threshold-based) — 🟡 P3
* Paperclip AI: config revisions / rollback (agent config snapshots с API-driven rollback) — 🟡 P3
* Paperclip AI: multi-stage execution policy (approval gates с participants — дополнение к quality gates) — 🟡 P3
* Paperclip AI: plugin system (definePlugin SDK: events/jobs/data/tools/state/UI — расширение без изменения core) — 🟡 P3
* Paperclip AI: run recovery (stranded issue recovery, stale active run evaluation, auto-wakeup) — 🟡 P3
* Crush: permission system для автономного выполнения — 🟡 P3
* Crush: множественный context file discovery (CRUSH.md, CLAUDE.md и т.д.) — 🟡 P3
* Crush: sub-agent pattern (Coder → Task: иерархия «основный агент → субагент поиска») — 🟡 P3
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
* OpenHands SDK: hooks system (6 lifecycle events, pre/post tool use shell-скрипты) — 🟡 P3
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
* GitHub Copilot Cloud Agent: Plan → Review → Execute (LLM-generated dynamic chains с human-in-the-loop) — 🟡 P3
* GitHub Copilot Cloud Agent: knowledge base integration (обогащение контекста документацией) — 🟡 P3
* Docker Agent + Codex: auto-compaction (LLM summarization при context overflow, inline + remote) — 🟡 P3
* Docker Agent + Codex: plan mode (read-only exploration перед execution) — 🟡 P3
* Docker Agent + Codex: session persistence (SQLite + rollout JSONL — resumable sessions) — 🟡 P3
* Agno: HITL — 3 режима (confirmation/user_input/output_review) — ⚠️ только интерактивный CLI — 🟡 P3
* Agno: compression (LLM-based сжатие tool results при overflow) — 🟡 P3
* Agno: guardrails (PII detection, prompt injection, moderation) — 🟡 P3
* Agno: evaluation (pre/post checks) — 🟡 P3
* Agno: multi-agent Teams (4 режима) — 🟡 P3
* Agno: parallel execution (Parallel) — 🟡 P3
* Agno: nested workflows (Workflow как Step, до 10 уровней) — 🟡 P3
* AgentCraft: git worktrees для параллельного выполнения цепочек — 🟡 P3
* AgentCraft: multi-agent teams — эволюция dynamic chains (координация нескольких агентов) — 🟡 P3
* AgentCraft: isolated containers (Docker/Apple Containers) для CI/CD sandboxing — 🟡 P3
* AgentCraft: scheduled tasks (cron-like запуск chain по расписанию) — 🟡 P3
* AgentCraft: channels — Human-in-the-loop через мессенджеры (Telegram/Discord) — 🟡 P3
* Factory Missions: structured handoffs (JSON-схема runner output: success/partial/failure + issues) — 🟡 P2
* Factory Missions: validation contract (mission-level TDD — behavioral assertions пишутся ДО кода) — 🟡 P2
* Factory Missions: mission boundaries (pre-execution ограничения: port ranges, off-limits resources) — 🟡 P2
* Factory Missions: fresh context per iteration (каждый worker с чистым контекстом, state с диска) — 🟡 P2
* Factory Missions: services manifest (.factory/services.yaml — единый файл команд и сервисов) — 🟡 P3
* Factory Missions: milestone sealing (завершённые этапы замораживаются, never add after validation) — 🟡 P3
* Factory Missions: structured feature description (preconditions + expectedBehavior + verificationSteps per step) — 🟡 P3
* Factory Missions: knowledge library (.factory/library/ — персистентная база знаний) — 🟡 P3
* Hermes Agent: structured context compression (14-секционный summary template для передачи контекста в fix_iterations) — 🟡 P3
* Hermes Agent: subagent delegation с DELEGATE_BLOCKED_TOOLS (no recursive delegation, no user interaction, no shared memory writes) — 🟡 P3
* Hermes Agent: filesystem checkpoints (shadow git store — rollback при ошибке в fix_iterations) — 🟡 P3
* Hermes Agent: rate limit header tracking (превентивное переключение runner до 429) — 🟡 P3
* Hermes Agent: kanban multi-agent coordination (shared board — модель для будущих multi-agent scenarios) — 🟡 P3
* Hermes Agent: tool result deduplication (для fix_iterations: не повторять одинаковые tool calls) — 🟡 P3
* Oz (Warp): cron-расписания (schedule management: create/pause/resume/delete для автоматического запуска цепочек) — 🟡 P3
* Oz (Warp): webhook-триггеры (Slack/Linear/GitHub Actions → agent run для CI/CD интеграции) — 🟡 P3
* Oz (Warp): Cloud Environments (Docker-окружения с репозиториями и секретами для изоляции) — 🟡 P3
* Oz (Warp): REST API / SDK (программное управление запуском и мониторингом цепочек) — 🟡 P3
* Oz (Warp): Planning (LLM-генерация пошаговых планов для dynamic chains) — 🟡 P3
* Oz (Warp): Codebase Context (semantic indexing для обогащения контекста агента) — 🟡 P3
* Sandcastle: Sandbox Provider Interface (plug-and-play Docker/Podman/Vercel/Daytona/custom — для CI/CD sandboxing runner'ов) — 🟡 P2
* Sandcastle: structured output (Output.object/string с Zod-валидацией — дополнение к quality gates) — 🟡 P2
* Sandcastle: completion signal (early termination через маркер в agent stdout — усиление fix_iterations) — 🟡 P2
* Sandcastle: prompt template engine ({{KEY}} + !`command` expansion — richer prompt templating) — 🟡 P2
* Sandcastle: typed error hierarchy (20+ error classes с timeout, sandbox, git, agent categories — error classification) — 🟡 P2
* Sandcastle: idle timeout (configurable timeout с periodic warnings — защита от зависших агентов) — 🟡 P2
* Sandcastle: branch strategy / git worktrees (3 стратегии + stale pruning + dirty preservation — для параллельных chain runs) — 🟡 P3
* Sandcastle: multi-agent templates (plan → execute N parallel → merge — модель для dynamic chains) — 🟡 P3
* Sandcastle: lifecycle hooks (host.onWorktreeReady/onSandboxReady, sandbox.onSandboxReady — pre/post execution) — 🟡 P3
* Sandcastle: AbortSignal / cancellation (cooperative cancellation через AbortSignal) — 🟡 P3
* Sandcastle: worktree preservation (dirty state preservation при failure — для отладки failed chains) — 🟡 P3
* Kilo Code: subagent isolation + permission inheritance (task tool: isolated session, edit/bash/MCP restrictions propagation, no recursive delegation — модель для вложенных chain runs) — 🟡 P2
* Kilo Code: permission system с glob patterns (allow/ask/deny per tool, bash allowlist из 40+ команд, last-matching-wins — для CI/CD sandboxing без Docker) — 🟡 P2
* Kilo Code: error classification для retry (ContextOverflow → не retry, API 5xx → retry, rate limit → retry с backoff + Retry-After — модель для RetryingAgentRunner) — 🟡 P2
* Kilo Code: steps limit per agent (max agentic iterations → forced text-only response — аналог max_iterations per chain step) — 🟡 P2
* Kilo Code: cost propagation child→parent (acquireUseRelease pattern + concurrent lock от race conditions — для вложенных chain runs) — 🟡 P3
* Kilo Code: wave-based parallel execution (parallel subagent calls в одном LLM-сообщении, dependency classification — модель для будущих dynamic chains) — 🟡 P3
* Kilo Code: context compaction с structured template (7-секционный summary: Goal/Constraints/Progress/Decisions/Next Steps/Critical Context/Files — для длинных цепочек и fix_iterations) — 🟡 P3
* Kilo Code: custom agent config (JSON/markdown/CLI create + AI-генерация — модель для переиспользуемых chain templates) — 🟡 P3
* Kilo Code: session resume (task_id для продолжения subagent session — для возобновления прерванных chain runs) — 🟡 P3
* OpenCode: doom loop detection (3 идентичных tool call → permission ask — защита от зацикливания в fix_iterations) — P2
* **OmO: IntentGate — pre-orchestration routing** (классификация намерения: research/implementation/fix/investigation → выбор цепочки — Application-сервис, без Domain-изменений, quick win) — P2
* **OmO: Skill-Embedded Requires** (скиллы объявляют зависимости: инструменты, env-переменные — валидация при загрузке цепочки, защита от «скилл без инструмента») — P2
* OpenCode: error classification для retry (ContextOverflow → compact, 5xx → retry, rate limit → retry с Retry-After — модель для RetryingAgentRunner) — P2
* OpenCode: permission system с glob patterns (allow/ask/deny per tool + session-level overrides — для CI/CD sandboxing без Docker) — P2
* OpenCode: cost tracking (per-step tokens + cache + reasoning + provider-specific pricing — детализация для BudgetVo) — P2
* OpenCode: context compaction (7-секционный structured template + pruning + preserve recent turns + replay — для длинных цепочек и dynamic loops) — P3
* **OmO: Category-based Runner Resolution** (категория задачи deep/quick/default → routing к оптимальному runner'у — требует ADR и расширения ChainStepVo) — P2
* **OmO: Per-role Permissions** (RolePermissionsVo — ограничение операций для роли: read-only/file-write/shell-exec, вдохновлено Discipline Agent Oracle) — P2
* **OmO: Proactive Context Management** (мониторинг context window + проактивная компакция до ошибки + dynamic pruning dedup/supersede/purge — ценный для длинных цепочек и fix_iterations) — P3
* OpenCode: subagent task tool (isolated session + permission inheritance + resume по task_id + no recursive delegation — модель для future dynamic chains) — P3
* OpenCode: custom agents через Markdown (.opencode/agent/*.md + AI-генерация — модель для переиспользуемых chain templates) — P3
* OpenCode: git worktree management (create/remove/reset + submodule update — для параллельных chain runs) — P3
* OpenCode: snapshot tracking + revert (file diff per step + откат failed chains — audit trail на уровне файловых изменений) — P3
* **OmO: Team Mode Architecture для DynamicLoop** (Lead + параллельные members, shared mailbox + task list — Blackboard Architecture, research-задача для пост-MVP) — P3
* Duet: cron-trigger для chains (cron expression → автоматический запуск цепочки — расширение применимости за пределы ручного запуска) — P2
* Duet: idempotency guidance в prompts (обогатить промпт runner'а инструкцией по idempotency для fix_iterations) — P2
* Duet: baseline → diff pattern (первый run = baseline snapshot, последующие = diff against previous — для recurring chains с state comparison) — P2
* Duet: SKILL.md-формат для chain templates (frontmatter: runner, tools; body: prompt enrichment — де-факто стандарт, 17/25 проектов) — P3
* Duet: UseCase/Skill separation (строгое разделение capability и presentation, TypeScript-enforced — архитектурный урок для Domain/Presentation layers) — P3
* Duet: gotchas в system prompt (антипаттерны и edge cases в body SKILL.md — enrich runner prompts) — P3
* Duet: multi-phase workflow в prompts (явное описание фаз выполнения в system prompt — для fix_iterations: diagnose → fix → verify) — P3
* Duet: pipeline state persistence (сохранение предыдущего output для comparison — для recurring chains) — P3
* **Multica: Poisoned session detection** (классификация agent output/error: iteration_limit / agent_fallback_message / api_invalid_request → fresh start вместо resume — для fix_iterations) — P2
* **Multica: Autopilot cron/webhook триггеры** (scheduled recurring chain execution — для CI/CD: ночной regression test, ежедневный code review) — P2
* **Multica: Runtime health + admission check** (heartbeat + sweeper + offline skip — дополнение к circuit breaker на уровне runner availability) — P2
* **Multica: Session resumption** (reuse session_id + work_dir для (agent, issue) → context continuity — для fix_iterations, требует session concept) — P3
* **Multica: GC loop для workdir cleanup** (periodic scan: done/cancelled + TTL → remove, orphan → remove — cleanup артефактов chain execution) — P3

</details>

---

## Общие тренды

> Анализ выполнен на основе всех 25 исследований. Тренды сгруппированы по значимости для архитектуры task-orchestrator.

### 1. Уникальная позиция task-orchestrator

**Ни один из исследованных проектов — ни open-source, ни коммерческий — не имеет полного набора:** chains + retry с backoff + circuit breaker + quality gates + бюджетный контроль + fix_iterations + fallback routing. Это подлинная (genuine) комбинация, отличающая task-orchestrator от всех 25 фреймворков.

**Ни один проприетарный продукт** (Claude Code, GitHub Copilot Cloud Agent, OpenAI Codex, Duet) не имеет retry с backoff, circuit breaker, quality gates, budget limits или декларативных chains — все наши ключевые отличия актуальны даже против крупнейших коммерческих AI-agent продуктов (включая Factory Missions — SaaS-продукт для multi-day autonomous software engineering, оценённый в $1.5B, и Duet — always-on business agent SaaS, $4.4M funding).

**Oh My OpenAgent (OmO, #23)** — plugin для OpenCode, добавляющий мультиагентную координацию (Team Mode), pre-orchestration routing (IntentGate) и proactive context management. OmO работает на уровне agent loop, не цепочек — его вклад в исследование — уникальные паттерны маршрутизации и координации.

**Ближайший аналог** по уровню абстракции — Archon (TypeScript/Bun), который тоже оркестирует внешние AI-ассистенты через subprocess SDK. Однако Archon не имеет circuit breaker, quality gates или бюджетного контроля. **Sandcastle** (TypeScript/Node.js) — ближайший аналог по sandbox management (Docker/Podman/Vercel), но работает на уровне sandbox lifecycle, не chain orchestration: нет retry, circuit breaker, quality gates, budget. Agno (Python SDK) предлагает наиболее развитый workflow engine из исследованных (6 строительных блоков + вложенные workflows), но работает на уровне прямых LLM API, а не оркестрации внешних runner'ов.

**Duet** (#24, business-agent SaaS) занимает уникальную нишу — уровень целых бизнес-процессов (campaign, pipeline, dashboard). Duet не конкурирует с task-orchestrator напрямую: разные уровни абстракции. Duet = product, task-orchestrator = framework/engine.

**Oz (Warp)** — облачная платформа оркестрации (SaaS), занимающая уникальную нишу: не SDK, не workflow engine, не мета-оркестратор, а **Cloud Agent Platform** — управляемая инфраструктура для запуска, координации и мониторинга автономных AI-агентов в Docker-окружениях через API/SDK, CLI, cron и webhook. Oz не имеет цепочек шагов (chains), retry с backoff, circuit breaker, quality gates или бюджетного контроля — все эти механизмы отсутствуют. Ключевое отличие Oz от task-orchestrator: Oz — это инфраструктура для запуска агентных *задач* (один prompt = один run), а task-orchestrator — оркестратор *процессов* (многошаговые цепочки с обработкой ошибок).

### 2. Agent Loop — доминирующая модель выполнения

**17 из 25 фреймворков** (исключая AgentCraft, Oz, Sandcastle, **Duet** и **Multica**, см. ниже) используют базовую модель `LLM → tool call → observation → LLM → ...` (Crush, pi_agent_rust, CrewAI, AutoGen, OpenHands SDK, MetaGPT, OpenClaw, Mastra AI, Claude Code, Copilot Cloud Agent, Codex, Agno, Hermes Agent, Kilo Code, **OpenCode**, **OmO** и др.). LangGraph (graph/DAG с superstep execution), Archon (DAG + subprocess SDK), Paperclip AI (heartbeat-based мета-оркестрация), Factory Missions (orchestrator-worker delegation с auto-injected validators) и Sandcastle (agent invocation loop в песочнице) используют принципиально другие модели. **Duet** (#24) использует skill-driven orchestration: intent → skill selection → multi-phase autonomous execution. **Multica** (#25) использует daemon-based task dispatch (не agent loop, а task queue).

**AgentCraft не учитывается в этом подсчёте:** он не имеет собственной модели выполнения, а выступает как GUI wrapper, делегируя выполнение подключённым внешним агентам (Claude Code, OpenCode, Cursor, OpenClaw). Эти агенты сами используют agent loop — AgentCraft лишь управляет их запуском и визуализирует прогресс. Таким образом, AgentCraft не является ни «agent loop», ни «другой моделью выполнения» — это управляющий слой поверх существующих сред.

**Sandcastle не учитывается в этом подсчёте:** он не работает с LLM API напрямую. Sandcastle запускает внешние AI-агенты (Claude Code, Codex, Pi, OpenCode) как subprocess в песочнице — agent invocation loop, не классический `LLM → tool call → LLM`. Это ближе к Archon (subprocess SDK), но с фокусом на sandbox management (Docker/Podman/Vercel) вместо DAG workflow.

Agno также поддерживает **step-based workflow** (Step/Steps/Loop/Parallel/Router/Condition) и **4 team modes** (coordinate/route/broadcast/tasks) поверх agent loop — наиболее развитый workflow engine из исследованных.

**Multica не учитывается в этом подсчёте:** Multica — это не SDK и не agent runtime. Это project management platform, которая оркестирует внешние agent CLI через daemon-based task queue. Multica не вызывает LLM API напрямую — она делегирует выполнение подключённым agent CLI (Claude Code, Codex и т.д.) через subprocess. Это принципиально другой уровень: platform layer, а не execution layer.

**Вывод:** Наша модель (YAML chain → runner call → payload) — это оркестрация поверх agent loop. Это правильный уровень: мы не дублируем LLM interaction, а управляем им. Oz (Warp) подтверждает тренд: облачные платформы (SaaS) управляют *запуском* агентов (когда, где, с каким окружением), а task-orchestrator управляет *процессом* (шаги, retry, quality gates).

### 3. Разделение на семь уровней абстракции

**Все 25 проектов** чётко делятся на семь уровней:

| Уровень | Проекты | Что делают | Аналог в task-orchestrator |
|---|---|---|---|
| **SDK / Agent runtime** | Crush, pi_agent_rust, OpenHands SDK, Mastra AI, Claude Code, Codex, OpenClaw, Agno, Hermes Agent, Kilo Code, **OpenCode**, **OmO** | Работают на уровне прямых LLM API | Runner'ы (pi, codex) |
| **Оркестратор / Workflow engine** | CrewAI, LangGraph, AutoGen, Archon, MetaGPT, Copilot Workspace | Управляют потоком выполнения между агентами/шагами | Chain executor |
| **Sandbox orchestration** | Sandcastle | Управляет жизненным циклом песочниц (Docker/Podman/Vercel), git worktrees, branch strategies для внешних AI-агентов | — (нет аналога) |
| **GUI Manager / Launcher** | AgentCraft | Визуальный интерфейс для запуска и мониторинга внешних агентов, без собственной логики выполнения | — (нет аналога) |
| **Multi-agent SaaS / Product** | Factory Missions | Автономная multi-day software development: orchestrator → workers → validators, file-based shared state | — (нет аналога, closest — chain executor + dynamic loops) |
| **Business Agent SaaS** | Duet (Aomni) | Always-on автономный бизнес-агент: skill-driven execution, shared workspace, scheduled pipelines, multi-channel delivery | — (нет аналога) |
| **Cloud Agent Platform** | Oz (Warp) | Облачная платформа оркестрации: Docker-окружения, cron/webhook/API триггеры, REST API/SDK, observability | — (нет аналога) |
| **Project Management Platform** | Multica | Управляет задачами (issues) для команд людей + AI-агентов: board, chat, autopilot, skills, runtimes | — (нет аналога) |
| **Мета-оркестратор / Control plane** | Paperclip AI | Управляет компаниями из агентов: org charts, budgets, governance, goals | — (нет аналога) |

**Multica** подтверждает тренд: platform layer между оркестратором и мета-оркестратором — управление задачами, людьми и агентами в едином workspace.

**Paperclip AI** подтверждает тренд на многоуровневую абстракцию: SDK/runtime → оркестратор → GUI manager → мета-оркестратор. Paperclip — наиболее продвинутый мета-оркестратор из исследованных: org charts, budgets, governance, goal alignment, company portability.

### 4. SKILL.md / AGENTS.md — де-факто стандарт

**16 из 25 проектов** используют SKILL.md или аналогичный формат для формализации agent capabilities:
- Crush, pi_agent_rust, CrewAI, OpenHands SDK, Archon, OpenClaw, Mastra AI, Codex, Agno, Factory Missions (.factory/skills/), Hermes Agent, Oz (Warp) (oz-skills), Kilo Code, **OpenCode** (.opencode/skills/ + remote URLs + .claude/skills/ + .agents/skills/), **OmO** (Skill-Embedded MCPs: SKILL.md + собственные MCP-серверы), **Duet** (SKILL.md в duet-skills реестр: frontmatter id/model/tools + markdown body = system prompt), **Multica** (workspace-level skills, SKILL.md + files, import from URL/runtime/ClawHub/Skills.sh/GitHub)
- Формат: YAML frontmatter + markdown body, discovery из нескольких мест, валидация
- Стандарт [agentskills.io](https://agentskills.io) получает широкое распространение
- Paperclip AI добавляет Company Skills: managed skill registry с import/export, trust levels, compatibility checks
- **Multica** реализует workspace-level Skills (SKILL.md) с import из ClawHub/skills.sh/GitHub и per-agent assignment с provider-native injection (`.claude/skills/`, `CODEX_HOME/skills/`, `.opencode/skills/` и т.д.)

**AGENTS.md** используется как минимум в 8 проектах (Crush, pi_agent_rust, OpenHands SDK, Codex, Paperclip AI, Factory Missions (per-mission AGENTS.md с boundaries + guidance), Hermes Agent (`.hermes.md` / `HERMES.md`), task-orchestrator) — де-факто стандарт для AI-agent контекста.

### 5. MCP (Model Context Protocol) — повсеместный протокол расширения

**16 из 25 проектов** поддерживают MCP:
- Crush, CrewAI, OpenHands SDK, Archon, OpenClaw, Mastra AI, Claude Code, Copilot Cloud Agent, Codex, Agno, Paperclip AI, Hermes Agent, Oz (Warp), Kilo Code, **OpenCode** (full MCP client: tools + resources + OAuth), **OmO** (Skill-Embedded MCPs: MCP-серверы внутри скиллов, per-session изоляция)
- **Multica** поддерживает MCP через per-agent config (JSONB в `agent` таблице), но не на уровне platform — делегирует agent CLI
- MCP — стандарт де-факто для расширения возможностей AI-агентов через внешние tool-серверы
- **Duet** (#24) использует **Composio** вместо MCP — проприетарный интеграционный слой (19+ connectors). Это не стандарт MCP, но решает ту же задачу: доступ к внешним API через стандартизированный interface

**Вывод:** MCP-поддержка в task-orchestrator — вопрос времени. Но реализовывать нужно на уровне runner'ов, не оркестратора.

### 6. Контекст-менеджмент — повсеместная проблема

**11 из 25 проектов** реализуют auto-compaction / auto-summarization при context overflow:
- Crush, pi_agent_rust, OpenHands SDK, Mastra AI, Claude Code, Codex, Agno, Hermes Agent, Kilo Code, **OpenCode** (7-секционный structured template + pruning + preserve recent turns + replay), **OmO** (proactive compaction: monitor + compact **до** ошибки + dynamic context pruning: dedup/supersede/purge)
- Все используют LLM-суммаризацию для сжатия истории
- Hermes Agent — наиболее продвинутый подход: 14-секционный structured summary template, tool result pruning + deduplication, anti-thrashing protection, iterative summary updates, tool_call/result pair integrity, last-user-message anchoring (~1500 LOC)
- OpenClaw: формализованный `ContextEngine` interface (ingest → assemble → compact → maintain) с tokenBudget
- Mastra AI: Observer + Reflector agents с async buffering
- Paperclip AI: session compaction policy (per-adapter thresholds: max runs/tokens/age)

**Вывод:** Для длинных цепочек и dynamic loops контекст-менеджмент станет необходим. Но для текущих конечных цепочек (max_iterations) это P3. Factory Missions решает проблему иначе: **fresh context per worker session** — каждый worker стартует с чистым контекстом, читая state с диска.

### 7. Безопасность автономного выполнения — зреющий тренд

**6 проектов** имеют продвинутые модели безопасности:

| Проект | Подход | Уровень зрелости |
|---|---|---|
| **Codex** | Guardian (LLM safety) + exec policy (rules) + Docker sandbox (iptables) + split FS permissions | Production-ready, defence in depth |
| **Copilot Cloud Agent** | Docker-container sandbox + org-level policy engine + audit | Production-grade, enterprise |
| **OpenHands SDK** | Security risk assessment (LLM + heuristics) + confirmation policies + defense-in-depth rails | SDK-level, composable |
| **OpenCode** | Permission system (allow/ask/deny per tool + glob patterns + inherited для subagents + session-level overrides) + doom loop detection | Agent-level, composable, no Docker needed |
| **Claude Code** | Permission system (allow/deny) + tiered prompts | Basic but effective |
| **Paperclip AI** | Execution policies (multi-stage approval) + budget hard stops + agent pause/resume/terminate + activity audit | Governance-level, company-wide |
| **Oz (Warp)** | Agent Profiles & Permissions (autonomy levels) + command denylist + Docker isolation (Cloud Environments) + secrets management + run audit trail | Platform-level, SaaS-managed |

**Sandcastle** — уникальная позиция: sandbox isolation как core product (не security feature). Встроенные SandboxProvider (Docker/Podman/Vercel) предоставляют container-level изоляцию по умолчанию. SELinux labels, UID/GID alignment, network isolation — из коробки.

**Codex — наиболее полная реализация:** трёхуровневая модель (rules filter → LLM safety review → container isolation). Для CI/CD — наиболее готовый к production подход.

**Вывод:** Безопасность станет критичной при переходе к автономному выполнению в CI/CD. Рекомендуется начать с exec policy (rules) — это quick win.

### 8. Sub-agents / Multi-agent — тренд к иерархической декомпозиции

**15 из 25 проектов** поддерживают sub-agents или multi-agent:
- Crush (Coder → Task), Claude Code (Task tool), Codex (spawn/send_message/wait/close_agent с depth limit), OpenHands SDK (DelegateTool), OpenClaw (ACP spawn с limits), Mastra AI (agent network), Archon (inline sub-agents), CrewAI (Crew), AutoGen (group chat), Agno (Team с 4 режимами), Factory Missions (Task tool для subagents: investigation, review, research), Hermes Agent (delegate_task: parallel spawning, orchestrator/leaf roles, depth control до 3), Kilo Code (task tool: isolated session, permission inheritance, cost propagation, resume, parallel invocation), **OpenCode** (task tool: isolated session + permission inheritance + resume по task_id + no recursive delegation), **OmO** (Team Mode: Lead + до 8 параллельных members, shared mailbox + shared task list, 12 team_* инструментов)

Oz (Warp) не имеет sub-agents в традиционном понимании, но поддерживает **unlimited parallel cloud agents** — одновременный запуск множества независимых agent runs через API. Это горизонтальное масштабирование, не иерархическая декомпозиция.

**Duet** (#24) не поддерживает sub-agents — один агент per thread. Каждый skill описывает multi-phase workflow, выполняемый одним агентом (не несколькими). Это сознательное ограничение: простота и предсказуемость ценой параллелизма.

Sandcastle поддерживает multi-agent orchestration через **templates**: parallel-planner запускает N агентов параллельно (Promise.allSettled), каждый на своей ветке; sequential-reviewer — implement + review в shared sandbox. Это parallel `run()` calls, не классический sub-agent (spawn/wait/close), но паттерн working — one failing agent doesn't cancel others.
- Codex — наиболее продвинутая sub-agent система: mailbox pattern, fork modes, role system
- Hermes Agent — наиболее продвинутая security model для sub-agents: DELEGATE_BLOCKED_TOOLS (no recursive delegation, no user interaction, no shared memory writes), auto-deny/approve для dangerous commands, child timeout
- Paperclip AI не имеет sub-agents, но моделирует иерархию через org chart (агенты как «сотрудники» с reportsTo)
- AgentCraft реализует Agent Teams — мультиагентные командные workflows через GUI wrapper (детали закрыты)

**Вывод:** Sub-agent pattern — готовый механизм для dynamic chains. Рекомендуется как P2: «chain внутри chain» с изолированным контекстом.

### 9. Conditional branching — востребованная возможность

**5 проектов** реализуют conditional branching:
- LangGraph (conditional edges), Archon (`when:` expressions), Mastra AI (`.branch()`), Copilot Workspace (plan branching), Agno (Condition + Router)

**Вывод:** Conditional branching — самый запрашиваемый паттерн для расширения YAML chains. Реализуемо без полной DAG-миграции.

### 10. Typed I/O — повышение надёжности цепочек

**3 проекта** используют типизированные схемы для I/O шагов:
- Mastra AI (Zod-схемы), LangGraph (TypedDict + reducers), Archon (JSON Schema)

**Вывод:** Валидация входных/выходных данных каждого шага через JSON Schema — повышение надёжности. Реализуемо в PHP через Symfony Validator или JSON Schema.

### 11. Error classification — повторяющийся паттерн

**6 проектов** классифицируют ошибки для умного retry:
- Archon (FATAL/TRANSIENT/UNKNOWN), OpenClaw (6 категорий по HTTP status), Codex (Guardian risk taxonomy), Hermes Agent (20+ FailoverReason: auth, billing, rate_limit, overloaded, server_error, timeout, context_overflow, model_not_found, provider_policy_blocked и др.), **Kilo Code** (ContextOverflow, API 5xx, rate limit, FreeUsageLimitError, Kilo errors — retry/no-retry classification), **OpenCode** (ContextOverflow/API 5xx/rate limit/FreeUsageLimitError/GoUsageLimitError — retry/no-retry + Retry-After header parsing + doom loop detection)

**Multica** добавляет уникальный подход: **poisoned session detection** — классификация не для retry, а для определения, можно ли возобновить session. 4 категории: `iteration_limit`, `agent_fallback_message`, `api_invalid_request`, `agent_error`. Если session «отравлена» (output или error содержит признаки poisoning) → следующий run стартует fresh, а не resume.

Hermes Agent предлагает наиболее развитую систему: **20+ failover reasons с recovery hints** (retryable, should_compress, should_rotate_credential, should_fallback) + **credential pool rotation** (4 стратегии: fill_first, round_robin, random, least_used). Error classification + credential rotation — дополняют circuit breaker: CB защищает от cascade, rotation — от rate limiting.

Agno предлагает **error-specific fallback routing** (on_error/on_rate_limit/on_context_overflow) — уникальная модель, не классификация для retry, а routing на другой провайдер по типу ошибки. Дополняет, а не заменяет error classification.

**Вывод:** Классификация ошибок — актуальное улучшение для RetryingAgentRunner. Hermes Agent подтверждает тренд и предлагает наиболее полную реализацию.

### 12. Архитектурная зрелость проекта

**Слоистая DDD-архитектура** (Domain/Application/Infrastructure) — редкость среди исследованных проектов. Большинство используют плоскую структуру (`internal/`, `src/`, `lib/`). Только AutoGen имеет слоистую архитектуру (core/agentchat/ext), но без DDD. Paperclip AI использует монолитную структуру `server/src/` с модульными сервисами (~70 файлов в services/), что обеспечивает good separation of concerns при отсутствии формальных DDD-слоёв. AgentCraft — проприетарный, внутренняя архитектура неизвестна.

**Decorator pattern** через интерфейс (AgentRunnerInterface) — уникальный для task-orchestrator подход. Ни один из исследованных проектов не использует decoration для добавления cross-cutting concerns (retry, circuit breaker, budget). Типичные подходы: direct call, composition, middleware pipeline.

**Вывод:** Наша архитектура — сильная сторона. Не нужно менять ради «как у всех».

### 13. Языковой и экосистемный ландшафт

| Язык | Проекты | Примечание |
|---|---|---|
| **Python** | CrewAI, LangGraph, AutoGen, OpenHands SDK, MetaGPT, Agno, Hermes Agent | Доминирующий язык для AI-agent фреймворков |
| **TypeScript** | Archon, OpenClaw, Mastra AI, Paperclip AI, Sandcastle, Kilo Code, **OpenCode**, **Multica** (frontend) | Растущая экосистема, особенно для workflow engines, мета-оркестраторов и sandbox orchestration |
| **Rust** | pi_agent_rust, Codex (codex-rs) | High-performance CLI-агенты |
| **Go** | Crush | TUI-ориентированный агент |
| **Проприетарный** | Claude Code, Copilot Cloud Agent, AgentCraft, Factory Missions, Oz (Warp), **Duet** | Закрытый код, анализ по документации и reverse engineering |
| **Go** | Crush, **Multica** (backend + daemon) | Server-side platforms и CLI-агенты |
| **Проприетарный** | Claude Code, Copilot Cloud Agent, AgentCraft, Factory Missions, Oz (Warp) | Закрытый код, анализ по документации и reverse engineering |

**Task-orchestrator (PHP/Symfony)** — единственный в своей нише: Symfony Bundle для chain-оркестрации AI-агентов. Это не недостаток — это уникальная позиция в PHP-экосистеме. Hermes Agent (Python, 136K+ звёзд) — наиболее популярный из исследованных, но работает на другом уровне абстракции (interactive AI assistant, не chain steps). Paperclip AI (TypeScript/Node.js) — ближайший по масштабу (60K+ звёзд), но тоже на другом уровне (company management). Duet (TypeScript/Bun, $4.4M funding) — closest по языку/экосистеме, но принципиально другой продукт (business-agent SaaS, не framework).

### 14. Отдельные наблюдения

* **Paperclip AI — самый масштабный из исследованных проектов:** 60K+ звёзд, 10K+ форков, ~70 таблиц в DB, 7+ agent adapters, полноценный plugin SDK. При этом не фреймворк для построения агентов, а мета-оркестратор для управления «компаниями» из агентов. Подтверждает тренд separation of concerns: agent runtime → chain orchestrator → meta-orchestrator (control plane).
* **Oz (Warp) — облачная платформа оркестрации с уникальной позицией:** 56K+ звёзд (Warp terminal), 500K+ пользователей, SDK (Python + TypeScript), REST API, cron/webhook/API триггеры, 10+ Docker-окружений, интеграции (Slack, Linear, GitHub Actions). Oz — не фреймворк для построения агентов, не workflow engine и не мета-оркестратор — это **Cloud Agent Platform**: управляемая инфраструктура для запуска автономных AI-агентов в облаке. Oz не имеет цепочек шагов, retry с backoff, circuit breaker, quality gates, бюджетного контроля или fix_iterations — все наши ключевые отличия актуальны. Подтверждает тренд separation of concerns: agent runtime → chain orchestrator (task-orchestrator) → cloud agent platform (Oz) → meta-orchestrator (Paperclip AI).
* **LangGraph — единственный** с durable execution и checkpoint persistence. Ключевое преимущество для длинных workflows. Требует `langchain-core` как обязательную зависимость — не standalone.
* **AutoGen в maintenance mode**, Microsoft рекомендует Microsoft Agent Framework (MAF). Заимствование паттернов безопасно, но dependency невозможна.
* **CrewAI — самый «productized»:** Enterprise (Crew Control Plane), сертификация 100k+ разработчиков, monetization через cloud.
* **Archon v2 переписан с Python на TypeScript/Bun** — показательный пример смены стека для production-ready проекта.
* **OpenHands SDK — наиболее зрелая Action/Observation-модель:** типизированные Action/Observation (Pydantic), security risk assessment, stuck detection, context condensation, sub-agent delegation — при этом SDK работает на уровне single agent loop, не оркестрации.
* **Mastra AI и Archon — два TypeScript-проекта с workflow engine**, но на разных уровнях: Mastra = SDK (LLM API), Archon = orchestrator (subprocess SDK). Task-orchestrator ближе к Archon.
* **OpenClaw — production-ready multi-channel personal assistant** (20+ мессенджеров, desktop/mobile apps, voice wake). Не фреймворк оркестрации, а законченный продукт.
* **Copilot Cloud Agent подтверждает тренд multi-model marketplace:** единый API поверх GPT-4, Claude, Gemini, Llama. Индустриальный аналог нашего AgentRunnerInterface.
* **Agno — наиболее развитый workflow engine** из исследованных: 6 строительных блоков (Step, Steps, Loop, Parallel, Router, Condition) + nested workflows (до 10 уровней). При этом Agno — in-process SDK, не оркестратор внешних runner'ов. Error-specific fallback routing (on_error/on_rate_limit/on_context_overflow) — уникальная модель, дополняющая error classification. HITL (3 режима) требует runtime (FastAPI) — в CLI ограниченно применимо.
* **Factory Missions — наиболее продвинутая multi-agent система для software engineering:** orchestrator-worker-validator архитектура, 51KB mission prompt, 5 communication patterns (delegation/creator-verifier/broadcast/negotiation/direct), validation contracts (mission-level TDD), sealed milestones, production runs до 16 дней. Проприетарный SaaS (Factory AI, Series C $150M, оценка $1.5B, Khosla Ventures). Prompt-driven архитектура (не hard-coded — улучшается с моделями). Каждый worker = fresh context, state на диске. 50% финального кода = тесты, 90% test coverage. Подтверждает тренд: autonomous software development — реальная production-возможность.
* **AgentCraft — единственный GUI-оркестратор** в исследовании: RTS-геймификация (fog of war, achievements, race skins) поверх 4 внешних AI-агентов (Claude Code, OpenCode, Cursor, OpenClaw). Не фреймворк и не SDK — визуальный интерфейс для управления существующими агентами. Подтверждает тренд: оркестрация AI-агентов — отдельная продуктовая ниша, не только техническая. Git worktrees, Docker/Apple Containers, scheduled tasks — функциональные фичи, перекликающиеся с Archon и Codex.
* **Sandcastle — наиболее продвинутый sandbox orchestration layer** из исследованных: plug-and-play SandboxProvider (Docker/Podman/Vercel/Daytona/custom), 3 branch strategies (head/merge-to-head/branch), git worktree management со stale pruning и dirty preservation, AgentProvider interface (4 built-in: Claude Code/Codex/Pi/OpenCode), structured output (Zod), completion signal, prompt template engine ({{KEY}} + !`command`), 5 multi-agent templates. Построен на Effect-TS — функциональной effect system. Не workflow engine и не chain orchestrator — инфраструктурный слой для запуска агентов в песочницах. Наиболее зрелая реализация sandbox management из исследованных: SELinux labels, UID/GID alignment, Windows path compatibility, worktree locking. Подтверждает тренд separation of concerns: sandbox orchestration (Sandcastle) → chain orchestrator (task-orchestrator) → cloud agent platform (Oz).
* **Kilo Code — наиболее развитая AI-агентная платформа для разработки** из исследованных: VS Code extension + CLI + JetBrains plugin, 19K звёзд, MIT лицензия, 7 built-in агентов (code/plan/debug/ask/orchestrator/general/explore), custom agents через JSON/markdown/CLI, permission system с glob patterns, context compaction с structured template, error classification для retry policy, subagent delegation через task tool (isolated session, permission inheritance, cost propagation, resume, parallel invocation), MCP, Skills (SKILL.md), Plugins, Workflows (slash commands), autonomous CI/CD mode (`kilo run --auto`). Orchestrator Mode объявлен **deprecated** — subagents встроены в каждый primary agent. Wave-based execution pattern (parallel waves, dependency classification) — модель для будущих dynamic chains. Построен на Effect-TS + Bun + Vercel AI SDK. Подтверждает тренд: оркестрация — не отдельный режим, а capability каждого агента.
* **OpenCode — наиболее популярный open source AI-coding agent** из исследованных: 156K+ звёзд, 18K+ форков, TypeScript/Bun, MIT лицензия. TUI + Desktop App + VS Code/JetBrains/Zed extensions + клиент-серверная архитектура (HTTP API + WebSocket + mDNS). 23+ LLM провайдеров через Vercel AI SDK (provider-agnostic). 7 built-in агентов (build/plan/general/explore/compaction/title/summary) + custom agents через Markdown (.opencode/agent/*.md) + AI-генерация. Doom loop detection (3 идентичных tool call → ask). Permission system с glob patterns (allow/ask/deny per tool, session-level overrides, inherited для subagents). Context compaction с 7-секционным structured template + pruning + preserve recent + replay. Subagent delegation через task tool (isolated session + resume по task_id). Error classification с Retry-After header parsing. Git worktree management (create/remove/reset + submodule update). Snapshot tracking + revert (file diff per step). Skills (SKILL.md), MCP, Plugins, ACP (Agent Client Protocol). Cost tracking с provider-specific pricing (cache tokens, reasoning tokens). Подтверждает тренды: provider-agnostic, клиент-серверная архитектура, structured compaction, doom loop detection.
* **Oh My OpenAgent (OmO) — plugin-оркестратор поверх OpenCode**, добавляющий три уникальных паттерна, не встреченных в других 22 проектах: (1) IntentGate — pre-orchestration routing через классификацию намерения **до** выполнения; (2) Skill-Embedded MCPs — скиллы, несущие собственные MCP-серверы с per-session изоляцией; (3) proactive context management — мониторинг + компакция + pruning **до** ошибки, а не реактивно при переполнении. OmO = plugin, не standalone продукт. Лицензия SUL-1.0 ограничивает коммерческое распространение, но разрешает внутреннее бизнес-использование. Архитектурное ревью (Гэндальф): 2 паттерна — заимствовать (IntentGate, Skill-Embedded Tools), 3 — наблюдать (Discipline Agents, Team Mode, Category System), 1 — антипаттерн (Dual-Prompt: нарушение слоистой архитектуры).
* **Duet (Aomni) — единственный business-agent SaaS** в исследовании: always-on AI-агент для командной работы, автоматизирующий GTM, product и ops workflows. Позиционирование: «Build an autonomous business in 45 minutes». Уникальная модель оркестрации — skill-driven: intent → skill selection → multi-phase autonomous execution. Не DAG, не agent loop, не graph — декларативные SKILL.md описывают multi-phase workflows (2–7 фаз), которые LLM автономно выполняет. Error handling — полностью prompt-driven (gotchas в SKILL.md), без retry/CB/fallback. Расширяемость через SKILL.md (19 default + 12 industry skills), UseCase surface placements (strict TypeScript-enforced separation capability/presentation), Composio integrations (19+), chat SDK adapters (8 platforms). Cron tool для scheduled pipelines. Build-apps tool для генерации мини-приложений. Подтверждает тренд: SaaS-продукты на уровне целых бизнес-процессов — отдельная ниша, не пересекающаяся с chain orchestration.

* **Multica — первый project management platform для human+agent teams** в исследовании: Linear/Jira-like board, где AI-агенты — first-class teammates с профилями, assignee picker и activity timeline. Уникальная комбинация: daemon-based task queue (poll + WS wakeup) + session resumption + poisoned session detection + autopilot (cron/webhook) + workspace-level skills с provider-native injection. Multica **не вызывает LLM API напрямую** — все AI-взаимодействия делегируются внешним agent CLI (11 providers). Это platform layer, а не execution engine — принципиально другой уровень, чем task-orchestrator. Подтверждает тренд separation of concerns: chain orchestrator (task-orchestrator) → project management platform (Multica) → meta-orchestrator (Paperclip AI).

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
| 2026-04-28 | Тимлид (Алекс) | Эпик reopen: добавлены задачи на ресерч Paperclip AI и AgentCraft |
| 2026-04-22 | Архитектор (Локи) | Ревью консистентности: исправлены данные по результатам ревью индивидуальных отчётов — Archon (8 таблиц, node-level retry, 21 hook), Mastra AI (22+ адаптеров), Claude Code (30+ tools, 20+ hook events, Agent SDK, agent teams), Codex (hooks, memories, plugins, Starlark exec policy, external-sandbox), Crush (sub-agents Coder → Task), OpenHands SDK (4+1 stuck detector, 6 hook events), Copilot (Cloud Agent, CLI/SDK/Spark), LangGraph (langchain-core dependency). Обновлён тренд sub-agents (9/13 вместо 8/13). |
| 2026-04-22 | Тимлид (Алекс) | Добавлена строка Agno (#14). Пересчитаны тренды (13→14): agent loop 12/14, SKILL.md 9/14, MCP 10/14, sub-agents 10/14, conditional branching 5 проектов, compression 7/14. Добавлен error-specific fallback routing (Agno) в Кластер 1. Добавлены Loop end_condition и Agno conditional branching в Кластер 3. Добавлены индивидуальные рекомендации Agno (P2: error-specific fallback, Loop end_condition, conditional branching; P3: HITL, compression, guardrails, evals, Teams, parallel, nested workflows). |
| 2026-04-28 | Технический писатель (Гермиона) | Создан отчёт paperclip-ai-comparison.md, заполнена строка Paperclip AI (#15). Пересчитаны тренды (14→15): agent loop 12/15, SKILL.md 9/15 (+Company Skills), MCP 11/15, sub-agents 10/15, compression 7/15 (+session compaction policy). Добавлен третий уровень абстракции (meta-orchestrator). Добавлены рекомендации Paperclip AI (P2: run liveness, error classification, escalation strategy, adapter context; P3: session compaction, config revisions, execution policy, plugin system, run recovery, scoped budgets, goal alignment). |
| 2026-04-28 | Технический писатель (Гермиона) | Доработка по замечаниям ревьювера (Архитектор Локи, PR #95): goal alignment — добавлена оговорка об ограниченной применимости для chain-оркестратора (секция 3.6); plugin system — добавлена оговорка о преждевременности для CLI (секция 3.8); добавлен подраздел Security: secrets management, execution environments, agent permissions (секция 3.10); scoped budget policies — унифицирован приоритет P3 в отчёте и сводной таблице. |
| 2026-04-29 | Технический писатель (Гермиона) | Доработка по результатам саморевью PR #96: исправлены счётчики трендов (15→16) в трендах 2–6, AgentCraft добавлен в список исключений тренда 2 (agent loop), добавлен в таблицу тренда 3 (уровни абстракции), добавлены индивидуальные рекомендации AgentCraft в секцию details. |
| 2026-04-28 | Технический писатель (Гермиона) | Создан отчёт agentcraft-comparison.md, заполнена строка AgentCraft (#16). Пересчитаны тренды (15→16): sub-agents 10/16 (+AgentCraft Agent Teams), проприетарные продукты 3/16. Добавлено наблюдение: GUI-оркестратор как отдельная продуктовая ниша. Все 16 исследований завершены. |
| 2026-04-29 | Технический писатель (Гермиона) | Доработка по замечаниям ревьювера (Архитектор Локи, post-factum ревью PR #96): (1) исправлен stale-текст «13 исследований» → «16 исследований» в преамбуле трендов; (2) AgentCraft убран из перечисления «принципиально других моделей» в тренде 2 — добавлено пояснение, что AgentCraft не имеет собственной модели выполнения (GUI wrapper), счётчик 12/15; (3) AgentCraft выделен в отдельную строку «GUI Manager / Launcher» в таблице тренда 3. |
| 2026-05-07 | Аналитик (Шерлок) | Создан отчёт missions-framework-comparison.md, заполнена строка Factory Missions (#17). Пересчитаны тренды (16→17): agent loop 12/17, SKILL.md 10/17, MCP 11/17, sub-agents 11/17, compression 7/17. Добавлен пятый уровень абстракции (Multi-agent SaaS / Product). Добавлены рекомендации Factory Missions (P2: structured handoffs, validation contract, mission boundaries, fresh context; P3: services manifest, milestone sealing, structured feature description, knowledge library). Добавлено наблюдение: Factory Missions — наиболее продвинутая multi-agent система для software engineering. Все 17 исследований завершены. |
| 2026-05-07 | Аналитик (Шерлок) | Создан отчёт hermes-agent-comparison.md, заполнена строка Hermes Agent (#18). Пересчитаны тренды (17→18): agent loop 13/18, SKILL.md 11/18, MCP 12/18, sub-agents 12/18, compression 8/18. Добавлены рекомендации Hermes Agent (P2: error classification, context file injection scanning, credential pool rotation; P3: structured context compression, subagent delegation, filesystem checkpoints, rate limit header tracking, kanban multi-agent, tool result deduplication). Все 18 исследований завершены. |
| 2026-05-07 | Аналитик (Шерлок) | Создан отчёт oz-cloud-agents-comparison.md, заполнена строка Oz (Warp) (#19). Пересчитаны тренды (18→19): agent loop 13/19, SKILL.md 12/19, MCP 13/19, sub-agents 12/19, compression 8/19. Добавлен шестой уровень абстракции (Cloud Agent Platform). Добавлены рекомендации Oz (P3: cron-расписания, webhook-триггеры, Cloud Environments, REST API/SDK, Planning, Codebase Context). Добавлено наблюдение: Oz — облачная платформа оркестрации с уникальной позицией (SaaS, не фреймворк). Все 19 исследований завершены. |
| 2026-05-07 | Аналитик (Шерлок) | Создан отчёт sandcastle-comparison.md, заполнена строка Sandcastle (#20). Пересчитаны тренды (19→20): agent loop 13/20, SKILL.md 12/20, MCP 13/20, sub-agents 12/20, compression 8/20. Добавлен седьмой уровень абстракции (Sandbox orchestration). Добавлены рекомендации Sandcastle (P2: Sandbox Provider Interface, structured output, completion signal, prompt template engine, typed error hierarchy, idle timeout; P3: branch strategy/git worktrees, multi-agent templates, lifecycle hooks, AbortSignal, worktree preservation). Добавлено наблюдение: Sandcastle — наиболее продвинутый sandbox orchestration layer из исследованных. Все 20 исследований завершены. |
| 2026-05-08 | Аналитик (Шерлок) | Создан отчёт opencode-orchestrator-comparison.md, заполнена строка Kilo Code (#21). Пересчитаны тренды (20→21): agent loop 14/21, SKILL.md 13/21, MCP 14/21, sub-agents 13/21, compression 9/21, error classification 5/21. Добавлены рекомендации Kilo Code (P2: subagent isolation, permission system, error classification, steps limit; P3: cost propagation, wave-based parallel execution, context compaction, custom agent config, session resume). Добавлено наблюдение: Kilo Code — наиболее развитая AI-агентная платформа для разработки (19K звёзд, MIT, deprecated Orchestrator Mode). Все 21 исследование завершены. |
| 2026-05-08 | Аналитик (Шерлок) | Создан отчёт opencode-comparison.md, заполнена строка OpenCode (#22). Пересчитаны тренды (21→22): agent loop 15/22, SKILL.md 14/22, MCP 15/22, sub-agents 14/22, compression 10/22, error classification 6/22. Добавлены рекомендации OpenCode (P2: doom loop detection, error classification, permission system, cost tracking; P3: context compaction, subagent task tool, custom agents, git worktrees, snapshot tracking). Добавлено наблюдение: OpenCode — наиболее популярный open source AI-coding agent (156K+ звёзд, 18K+ форков, 23+ LLM провайдеров, 7 built-in агентов, клиент-серверная архитектура). Все 22 исследования завершены. |
| 2026-05-13 | Аналитик (Шерлок) | Создан отчёт oh-my-openagent-comparison.md, заполнена строка OmO (#23). Пересчитаны тренды (22→23): agent loop 16/23, SKILL.md 15/23, MCP 16/23, sub-agents 15/23, compression 11/23. Добавлены рекомендации OmO (P2: IntentGate, Skill-Embedded Requires, Category-based Runner Resolution, Per-role Permissions; P3: Proactive Context Management, Team Mode для DynamicLoop). Добавлено наблюдение: OmO — plugin-оркестратор с уникальными паттернами (IntentGate, Skill-Embedded MCPs, proactive context management). Все 23 исследования завершены. |
| 2026-05-13 | Аналитик (Шерлок) | Создан отчёт duet-comparison.md, заполнена строка Duet (#24). Добавлен восьмой уровень абстракции (Business Agent SaaS). Добавлены рекомендации Duet (P2: cron-trigger, idempotency guidance, baseline-diff pattern; P3: SKILL.md-format, UseCase/Skill separation, gotchas, multi-phase workflows, pipeline state persistence). Добавлено наблюдение: Duet — единственный business-agent SaaS. |
| 2026-05-13 | Аналитик (Шерлок) | Создан отчёт multica-comparison.md, заполнена строка Multica (#25). Пересчитаны тренды (23→25). Добавлен девятый уровень абстракции (Project Management Platform). Добавлены рекомендации Multica (P2: poisoned session detection, autopilot cron/webhook, runtime health + admission check; P3: session resumption, GC loop). Добавлено наблюдение: Multica — первый project management platform для human+agent teams в исследовании. Все 25 исследований завершены. |
