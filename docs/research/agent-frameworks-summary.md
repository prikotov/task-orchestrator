# Сводная таблица: AI-agent фреймворки и оркестраторы

> **Цель:** Сравнить исследованные AI-agent фреймворки с task-orchestrator, определить паттерны для заимствования.
> **Эпик:** [EPIC-research-agent-frameworks-comparison](../../todo/done/EPIC-research-agent-frameworks-comparison.md)

---


## Терминологическая пометка

В сводке англоязычные термины используются в следующих значениях: **fan-out** — веерная раздача задачи нескольким агентам; **BYO (bring your own)** — «принеси свой» агент/подписку; **control surface** — управляющая поверхность; **terminal agent runtime** — постоянная терминальная среда выполнения агентов; **agent multiplexer** — терминальный мультиплексор, распознающий агентов; **human-in-the-loop** — человек в контуре принятия решения; **workflow** — рабочий процесс; **workbench** — рабочее место/панель управления; **live monitoring** — наблюдение за живым запуском; **runner-/chain-level retry/backoff/CB** — повтор, задержка и circuit breaker (предохранитель отказов) на уровне запуска агента или цепочки; **dispatch-level retry/circuit-break** — повтор и размыкание на уровне dispatch-задачи Orca с порогом 3 failures.

---

## Сравнительная таблица

> **Статус заполнения:** 32 завершённых / 34 запланированных исследования. Номера #32 (`qm`) и #33 (`omnigent`) зарезервированы активными задачами; Herdr #34 завершён раньше них.

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
| 26 | Zeroclaw | Rust (edition 2024) | `CLI-agent + agent-runtime` | `agent-loop (LLM → tool call → obs → LLM) + SOP engine (triggered procedures: MQTT/webhook/cron/peripheral) + loop detection (3 patterns)` | `pluggable Memory trait (SQLite/PostgreSQL/Qdrant) + session persistence + namespaced isolation + history pruning + context compression` | `ReliableProvider (fallback chain + retry 2x exponential) + error classification (retryable/non-retryable/context window) + loop detection (Warning/Block/Break)` | `Trait-driven (Provider/Channel/Tool/Memory/Observer/RuntimeAdapter/Sandbox/Peripheral) + WASM plugins + MCP client + SkillForge + 30+ channels + hardware` | 🟡 заимствовать отдельные паттерны | [zeroclaw-comparison.md](framework-comparisons/zeroclaw-comparison.md) ✅ |
| 27 | Odysseus (PewDiePie archdaemon) | Python (FastAPI) | `self-hosted AI workspace` | `agent-loop (ReAct) + Deep Research (multi-step runs) + Skills (SKILL.md) + Memory (ChromaDB)` | `session persistence (SQLite) + ChromaDB (memory/skills) + vector + keyword retrieval` | `retry max 3 + host health (cooldown 20s after 2 consecutive failures) + tool timeout (60s)` | `Tools (bash/python/web_search/etc.) + MCP servers + Skills (SKILL.md) + custom endpoints + Deep Research workflow` | 🔴 не dependency, 🟡 feature candidates for independent implementation | [odysseus-comparison.md](framework-comparisons/odysseus-comparison.md) ✅ |
| 28 | Agent Skills (Addy Osmani) | Markdown + Bash + JavaScript | `skill-pack` | `host-driven skill activation + lifecycle commands (/spec→/plan→/build→/test→/review→/ship) + /ship fan-out review` | `N/A / host-dependent` (delegated to host agent/IDE) | `N/A / delegated to host agent` (prompt-level verification, no retry/CB/fallback) | `24 SKILL.md + 8 commands + 4 personas + hooks + plugin manifests + skill validator` | 🔴 не dependency, 🟡 заимствовать authoring/governance patterns | [agent-skills-comparison.md](framework-comparisons/agent-skills-comparison.md) ✅ |
| 29 | SwarmForge (Uncle Bob) | Babashka/Clojure + zsh | `swarm-orchestration` | `peer-to-peer handoff pipeline` (tmux sessions + daemon-delivered inbox/outbox files + git worktrees per role) | `file-based` (`.swarmforge/` handoffs + `roles.tsv` + git worktrees) | `validation/fail-fast` (strict handoff schema, failed queue, ambiguous-state errors; no retry/CB/gates/budget) | `swarmforge.conf topology + roles/*.prompt + layered constitution + backends codex/claude/copilot/grok + pack presets` | 🔴 не dependency, 🟡 заимствовать swarm-governance patterns | [swarm-forge-comparison.md](framework-comparisons/swarm-forge-comparison.md) ✅ |
| 30 | Orca ADE | TypeScript + Electron + React Native | `ADE / manual-orchestrator` | `parallel worktree fan-out` (1 prompt → N CLI agents in isolated git worktrees → compare → merge winner) + experimental messages/tasks/dispatches/gates | `git worktrees + app/runtime state` (branches/files/terminals/tasks/dispatches; mobile pairing) | `manual recovery/rerun + validation/precheck` (нет universal runner-/chain-level retry/backoff/CB/gates/budget на уровне agent steps; есть experimental dispatch-level retry/circuit-break после 3 failures) | `any CLI agent + BYO subscription + Orca Skills (SKILL.md) + MCP endpoints + CLI + SSH/mobile/emulator/computer-use` | 🔴 не dependency, 🟡 заимствовать fan-out/worktree-monitoring patterns | [orca-ade-comparison.md](framework-comparisons/orca-ade-comparison.md) ✅ |
| 31 | bx-dev | Markdown + Python | `Codex-skill / workflow-harness` | `Lead-orchestrated single-shot Codex subagents` (Dev → reviewers → conventional commit → optional post-commit QA → Merger) on session branch | `file-based` (`.bx-dev/<session-id>/state.json` + `brief.md`/`context.md`) + git branch/commits/PR | `fail-fast + bounded review rounds + post-commit QA amend rounds + merge rollback protocol` (нет universal retry/backoff/CB/budget/fix_iterations; failures preserve session and ask user) | `Codex subagents + strict flags + 105 bundled support skills (SKILL.md) / 9 categories + Merger template + BYO Codex runtime` | 🔴 не dependency, 🟡 заимствовать session/flags/merge-governance patterns | [bx-dev-skill-comparison.md](framework-comparisons/bx-dev-skill-comparison.md) ✅ |
| 34 | Herdr | Rust 2021 | `terminal agent runtime / control surface` | `background server + real PTY panes + imperative agent start/prompt/wait/read` над 19+ внешними агентами | `live PTY + session snapshot + optional screen history + native agent resume + experimental live handoff` | `startup/wait timeouts + agent_prompt_stalled + structured CLI errors` (нет universal retry/backoff/CB/gates/budget; screen state эвристический) | `90-method CLI/socket API + events + Git worktrees + executable plugins + release-matched SKILL.md + SSH remote attach` | 🔴 не dependency, 🟡 заимствовать lifecycle/wait/ownership/worktree patterns, 🟢 optional manual runtime | [herdr-comparison.md](framework-comparisons/herdr-comparison.md) ✅ |

### Легенда колонок

| Колонка | Описание | Значения |
|---|---|---|
| **Категория** | Тип фреймворка | `single-agent`, `multi-agent`, `meta-orchestration`, `cloud/SaaS`, `CLI-agent`, `ADE / manual-orchestrator`, `terminal agent runtime / control surface` |
| **Модель оркестрации** | Как оркестируются шаги/агенты | `sequential`, `graph/DAG`, `event-driven`, `SOP`, `agent-loop` и т.д. |
| **State mgmt** | Как хранится и передаётся состояние | `in-memory`, `shared-context`, `message-passing`, `persistent` и т.д. |
| **Error handling** | Retry, circuit breaker, fallback | `retry+backoff`, `circuit-breaker`, `fallback-model`, `manual` и т.д. |
| **Extensibility** | Как расширяется | `plugins`, `SDK/interface`, `config-only`, `inheritance` и т.д. |
| **Вердикт** | Рекомендация для task-orchestrator | `🟢 заимствовать паттерны`, `🟡 dependency`, `🔴 не подходит`, `✅ уже есть` |

---

## Резюме для принятия решений (Executive Summary)

По результатам 32 завершённых исследований AI-agent фреймворков, инструментов, оркестраторов, ADE, skill packs и терминальных сред можно сделать **три главных вывода**. Всего запланировано 34 исследования; `qm` #32 и `omnigent` #33 ещё не завершены и не входят в расчёты трендов.

1. **task-orchestrator обладает уникальной комбинацией возможностей**, которой нет ни у одного из исследованных проектов: YAML-цепочки + retry с backoff + circuit breaker + quality gates (shell-проверки качества) + бюджетный контроль + fix_iterations + fallback routing + JSONL audit trail. Ни один фреймворк — ни open-source, ни проприетарный — не предлагает все эти механизмы вместе. **Paperclip AI** — ближайший аналог по уровню (мета-оркестратор), но работает на уровне компании/агентов, а не chain steps. **Zeroclaw** (#26, Rust, 31.5k ★) — наиболее развитый single-agent runtime из исследованных, но не имеет circuit breaker, quality gates, бюджетного контроля или chain-level retry. **Duet** (Aomni, $4.4M seed) подтверждает тренд: даже well-funded SaaS-платформы, локальные swarm-оркестраторы и Codex-skill harnesses для автономных AI-агентов не имеют полного набора этих механизмов.

2. **Наибольший потенциал для заимствования** — в четырёх кластерах: (а) интеллектуальная обработка ошибок (error classification, stuck detection, model failover), (б) безопасность автономного выполнения (sandboxing, exec policy, permission system), (в) расширенные модели оркестрации (conditional branching, parallel execution, sub-agents), (г) skill-based специализация и recurring execution (cron-triggered recurring execution, gotchas, idempotency guidance, multi-phase workflows). **Duet** (#24) усиливает кластер (г) через SKILL.md-формат, use-case curation и prompt-level guardrails.

3. **Ближайшие аналоги** по уровню абстракции — Archon (TypeScript/Bun, chain-level оркестрация через subprocess SDK), Paperclip AI (TypeScript/Node.js, company-level мета-оркестратор), SwarmForge (#29), Orca ADE (#30), bx-dev (#31) и Herdr (#34). Archon не имеет circuit breaker, quality gates или бюджетного контроля. Paperclip AI имеет развитый budget enforcement, run recovery и plugin system, но не поддерживает chains, circuit breaker или quality gates. **SwarmForge** близок по ролевой координации. **Orca ADE** близок по worktree-first ручной оркестрации. **bx-dev** близок к нашему `task-via-subagents`. **Herdr** находится уровнем ниже: это постоянный сервер реальных терминалов с агентными состояниями, событиями, рабочими деревьями и единым CLI/socket API, но без предписанного рабочего процесса. Все четыре не подходят как core dependency (основная зависимость): SwarmForge ценен для governance/handoff/team topology, Orca — для fan-out comparison и live worktree monitoring, bx-dev — для session UX и merge-governance, Herdr — для lifecycle authority, atomic prompt-and-wait, ownership и worktree provenance. У Herdr нет universal runner-/chain-level retry/backoff/CB, quality gates, budget или машинного результата шага, сопоставимого с task-orchestrator.

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
| **Prompt transition stall check** | **Herdr** (`agent_prompt_stalled`) | После отправки промпта из нерабочего состояния потребовать наблюдаемый переход жизненного цикла за короткий срок | Ранний fail-fast (быстрый отказ) до общего stall/hard timeout; не заменяет correlation id (идентификатор корреляции) конкретного задания |
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
| **Sub-agent pattern** | Claude Code (Task tool), Codex (spawn/wait/close_agent), OpenHands SDK (DelegateTool), Kilo Code (task tool: isolated session, permission inheritance, cost propagation, resume, parallel invocation), **OpenCode** (task tool: isolated session + permission inheritance + resume по task_id + no recursive delegation), **Herdr** (agent start/prompt/wait с фиксацией владельца панели) | Изолированный контекст подзадачи, потенциально параллельно | «Chain внутри chain» с собственным бюджетом и контекстом. Для dynamic chains. OpenCode добавляет resume по task_id; Herdr показывает атомарный prompt+wait и защиту от подмены агента, но не даёт машинный результат хода |
| **Structured handoff schema** | Factory Missions (worker handoff), **SwarmForge** (`awake`/`git_handoff`/`note` через daemon) | Стандартизировать результат сабагента: status, task, commit/files, issues, blockers, next action | Позволяет оркестратору принимать решения детерминированно вместо разбора свободного текста |
| **Team topology presets** | **SwarmForge** (`two-pack`/`four-pack`/`six-pack`), Factory Missions (worker types) | Наборы ролей под сложность задачи: quick/full/research/epic | У нас roles и skills уже есть; не хватает декларативных presets поверх них |
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
| **Git worktree isolation** | Archon (IIsolationProvider), Orca ADE, SwarmForge, **Herdr** (`worktree.create/open/remove` + provenance + events) | Каждый run в своём git worktree — параллельные runs не конфликтуют | Для параллельного выполнения цепочек в одном репозитории. Herdr добавляет единый машинный lifecycle (жизненный цикл) рабочих деревьев, но не определяет роли и merge policy (политику слияния) |

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
* Duet: SKILL.md-формат для chain templates (frontmatter: runner, tools; body: prompt enrichment — де-факто стандарт, 20/28 проектов) — P3
* Duet: UseCase/Skill separation (строгое разделение capability и presentation, TypeScript-enforced — архитектурный урок для Domain/Presentation layers) — P3
* Duet: gotchas в system prompt (антипаттерны и edge cases в body SKILL.md — enrich runner prompts) — P3
* Duet: multi-phase workflow в prompts (явное описание фаз выполнения в system prompt — для fix_iterations: diagnose → fix → verify) — P3
* Duet: pipeline state persistence (сохранение предыдущего output для comparison — для recurring chains) — P3
* **Multica: Poisoned session detection** (классификация agent output/error: iteration_limit / agent_fallback_message / api_invalid_request → fresh start вместо resume — для fix_iterations) — P2
* **Multica: Autopilot cron/webhook триггеры** (scheduled recurring chain execution — для CI/CD: ночной regression test, ежедневный code review) — P2
* **Multica: Runtime health + admission check** (heartbeat + sweeper + offline skip — дополнение к circuit breaker на уровне runner availability) — P2
* **Multica: Session resumption** (reuse session_id + work_dir для (agent, issue) → context continuity — для fix_iterations, требует session concept) — P3
* **Multica: GC loop для workdir cleanup** (periodic scan: done/cancelled + TTL → remove, orphan → remove — cleanup артефактов chain execution) — P3

#### Zeroclaw

* **Zeroclaw: loop detection — 3 patterns** (exact repeat: 3+ consecutive identical tool+args → Warning→Block→Break; ping-pong: A↔B 4+ cycles → Warning→Block; no-progress: same tool 5+ with identical result_hash → Warning→Block — для fix_iterations) — P2 Quick win
* **Zeroclaw: error classification** (retryable: context window errors, 429, 408; non-retryable: 4xx except 429/408, auth failures, model not found — дополнение к RetryingAgentRunner) — P2 Quick win
* **Zeroclaw: context limit auto-detection** (parse_context_limit_from_error(): regex-парсинг error messages для auto-detect context window limit — для retry logic) — P2 Quick win
* **Zeroclaw: hint-based model routing** (RouterProvider: hint:reasoning → Sonnet, hint:cheap → Haiku; composable с ReliableProvider fallback — per-step model override в chains) — P3
* **Zeroclaw: history pruning** (orphaned tool messages + collapsed pairs + protected indices + 1.2x token safety margin — для длинных chains и fix_iterations) — P3
* **Zeroclaw: SOP deterministic mode** (steps pipe outputs as inputs без LLM round-trips + checkpoint steps pause для human approval — модель для future "fast chains") — P3
* **Zeroclaw: cost-optimized routing** (resolve_cost_optimized(): cheapest qualifying provider с capability filtering: vision, tools — для multi-provider scenarios) — P3
* **Zeroclaw: cryptographic tool receipts** (signed audit chain: каждый receipt содержит hash предыдущего — tamper-evident audit trail, для enterprise-grade) — R&D
* **Zeroclaw: 6-layer security model** (channel pairing → autonomy → workspace boundary → command policy → OS sandbox → tool receipts — для CI/CD autonomous execution) — R&D

#### Agent Skills

* **Agent Skills: Skill anatomy validator** (frontmatter name/description, required sections, cross-skill references — модель для `docs/agents/skills/*`) — P2
* **Agent Skills: anti-rationalization tables** (типовые отговорки агента + factual rebuttal — снизить риск пропуска review/checks/DoD) — P2
* **Agent Skills: red flags** (наблюдаемые признаки нарушения workflow — использовать в self-review и review skills) — P2
* **Agent Skills: lifecycle mapping** (`/spec → /plan → /build → /test → /review → /ship` — карта наших ролей/скиллов по стадиям delivery) — P3
* **Agent Skills: persona composition guardrail** (персоны не вызывают персоны; fan-out только с независимыми отчётами и merge step) — P2
* **Agent Skills: progressive disclosure references** (длинные checklists вынести из core SKILL.md в reference docs) — P3

#### SwarmForge

* **SwarmForge: layered constitution override semantics** (`local-*.prompt` дополняет shared article, same-name article замещает shared article — формализовать precedence для `AGENTS.md`/roles/skills) — P2
* **SwarmForge: strict handoff draft schema** (`awake`/`git_handoff`/`note`, reserved headers, canonical payload, audit timestamps — модель для структурированных отчётов сабагентов) — P2
* **SwarmForge: role ownership blocks** (`Owns` / `Does Not Own` / `Handoff` — усилить role files и SKILL.md) — P2
* **SwarmForge: pack presets by task complexity** (`two-pack`/`four-pack`/`six-pack` — presets для quick/full/research/epic workflows) — P3
* **SwarmForge: batch receive mode** (группировать equal-priority handoffs перед review/merge decision) — P3
* **SwarmForge: git worktree per role** (изоляция параллельных сабагентов в epic-level fan-out) — P3

#### Orca ADE

* **Orca ADE: parallel fan-out candidate comparison** (1 prompt → N agents/worktrees → compare → merge winner — модель для будущих `task-via-subagents` fan-out workflows) — P2
* **Orca ADE: structured `worker_done` + dispatch-id completion authority** (taskId/dispatchId/files/report-path — защита от stale completion и модель для отчётов сабагентов) — P2
* **Orca ADE: BYO runner/subscription ergonomics** (явно документировать runner как внешний CLI + аккаунт пользователя, usage/rate-limit assumptions) — P2
* **Orca ADE: worktree isolation for parallel subagents** (отдельные worktrees для independent research/review/candidate implementation) — P3
* **Orca ADE: workspace setup/preflight recipe** (`orca.yaml` setup/environmentRecipes как аналог project-owned preflight metadata рядом с chains) — P3
* **Orca ADE: live run monitoring / notifications** (идея dashboard/уведомлений для long-running chains; не переносить mobile native UI) — P3

#### bx-dev

* **bx-dev: strict workflow flags** (`--solo`/`--careful`/`--no-review`/`--plan-approve`/`--no-sop` + fail-fast unknown flags — явный UX режимов для subagent workflows) — P2
* **bx-dev: scout-plan approval gate** (`--plan-approve`: scout-only план → HITL approval → fresh implementation subagent — модель для рискованных задач) — P2
* **bx-dev: session-state resume metadata** (`.bx-dev/<session-id>/state.json`: waiting_for, codex_agents, push_count, completed_tasks — дополнение к JSONL audit для live workflow recovery) — P2
* **bx-dev: structured single-shot subagent report/close contract** (Status/Summary/Files/Verification/Findings/Blockers + explicit close lifecycle — усилить `run-subagent`/`task-via-subagents`) — P2
* **bx-dev: optional post-commit QA as flag** (`--careful` добавляет QA phase после task commit; QA-fixes amend'ят этот commit, сохраняя one commit per task) — P2
* **bx-dev: MERGE-PROTOCOL as standalone artifact** (merge taxonomy + rollback/status contract; адаптировать без auto-merge, с нашим требованием явного user confirmation) — P3
* **bx-dev: skill-library router + manifest** (105 support skills / 9 categories, category INDEX + MANIFEST + exclusions — governance для publishable skills) — P3

#### Herdr

* **Herdr: lifecycle state with authority and evidence** (`idle`/`working`/`blocked`/`done`/`unknown` + integration/screen authority + `agent explain` — не считать неопределённость успехом) — P2
* **Herdr: atomic prompt-and-wait with occupant pinning** (отправить промпт и зарегистрировать ожидание одним запросом; новый агент в той же панели не завершает старое ожидание) — P2
* **Herdr: prompt transition stall check** (`agent_prompt_stalled`, если после отправки нет наблюдаемого перехода — ранний сигнал до общего таймаута) — P2
* **Herdr: opaque IDs, caller context and ownership rules** (`--current`, явные ID, `HERDR_*_ID`, не закрывать чужие ресурсы — контракт для параллельной координации) — P2
* **Herdr: worktree lifecycle with provenance and events** (`create/open/remove`, branch сохраняется, внешние клиенты получают события) — P3
* **Herdr: release-matched skill** (`herdr --skill` печатает навык своего выпуска — защита от рассинхронизации CLI и инструкций агента) — P3
* **Herdr: layered resume semantics** (отдельно process continuity/layout recovery/transcript replay/native conversation resume/live handoff) — R&D

</details>

---

## Общие тренды

> Анализ выполнен на основе 32 завершённых исследований. `qm` #32 и `omnigent` #33 запланированы, но до их завершения не входят в расчёты. Тренды сгруппированы по значимости для архитектуры task-orchestrator.

### 1. Уникальная позиция task-orchestrator

**Ни один из исследованных проектов — ни open-source, ни коммерческий — не имеет полного набора:** chains + retry с backoff + circuit breaker + quality gates + бюджетный контроль + fix_iterations + fallback routing. Это подлинная (genuine) комбинация, отличающая task-orchestrator от всех 32 завершённых исследований.

**Ни один проприетарный продукт** (Claude Code, GitHub Copilot Cloud Agent, OpenAI Codex, Duet) не имеет retry с backoff, circuit breaker, quality gates, budget limits или декларативных chains — все наши ключевые отличия актуальны даже против крупнейших коммерческих AI-agent продуктов (включая Factory Missions — SaaS-продукт для multi-day autonomous software engineering, оценённый в $1.5B, и Duet — always-on business agent SaaS, $4.4M funding).

**Oh My OpenAgent (OmO, #23)** — plugin для OpenCode, добавляющий мультиагентную координацию (Team Mode), pre-orchestration routing (IntentGate) и proactive context management. OmO работает на уровне agent loop, не цепочек — его вклад в исследование — уникальные паттерны маршрутизации и координации.

**Ближайший аналог** по уровню абстракции — Archon (TypeScript/Bun), который тоже оркестирует внешние AI-ассистенты через subprocess SDK. Однако Archon не имеет circuit breaker, quality gates или бюджетного контроля. **Sandcastle** (TypeScript/Node.js) — ближайший аналог по sandbox management (Docker/Podman/Vercel), но работает на уровне sandbox lifecycle, не chain orchestration: нет retry, circuit breaker, quality gates, budget. Agno (Python SDK) предлагает наиболее развитый workflow engine из исследованных (6 строительных блоков + вложенные workflows), но работает на уровне прямых LLM API, а не оркестрации внешних runner'ов.

**Duet** (#24, business-agent SaaS) занимает уникальную нишу — уровень целых бизнес-процессов (campaign, pipeline, dashboard). Duet не конкурирует с task-orchestrator напрямую: разные уровни абстракции. Duet = product, task-orchestrator = framework/engine.

**Oz (Warp)** — облачная платформа оркестрации (SaaS), занимающая уникальную нишу: не SDK, не workflow engine, не мета-оркестратор, а **Cloud Agent Platform** — управляемая инфраструктура для запуска, координации и мониторинга автономных AI-агентов в Docker-окружениях через API/SDK, CLI, cron и webhook. Oz не имеет цепочек шагов (chains), retry с backoff, circuit breaker, quality gates или бюджетного контроля — все эти механизмы отсутствуют. Ключевое отличие Oz от task-orchestrator: Oz — это инфраструктура для запуска агентных *задач* (один prompt = один run), а task-orchestrator — оркестратор *процессов* (многошаговые цепочки с обработкой ошибок).

### 2. Agent Loop — доминирующая модель выполнения

**19 из 32 завершённых исследований** используют базовую или first-class модель `LLM → tool call → observation → LLM → ...`. В numerator (числитель) входят: Crush (#1), pi_agent_rust (#2), CrewAI (#3), LangGraph (#4; agent loop может быть выражен как graph cycle/superstep), AutoGen (#5), OpenHands SDK (#6), MetaGPT (#8), OpenClaw (#9), Mastra AI (#10), Claude Code (#11), Copilot Cloud Agent (#12), Docker Agent + Codex (#13), Agno (#14), Hermes Agent (#18), Kilo Code (#21), OpenCode (#22), OmO (#23), Zeroclaw (#26), **Odysseus (#27)**. Herdr (#34) увеличивает denominator (знаменатель) до `32`, но не numerator: он запускает внешние agent loops (циклы агентов) в реальных терминалах и не реализует собственный. Ранее добавленные **bx-dev (#31)**, **Orca ADE (#30)** и **SwarmForge (#29)** также не увеличивали numerator: это соответственно manual workflow harness, ADE/manual orchestration layer и swarm-orchestration layer.

Не входят в agent-loop numerator: Archon (#7; DAG + subprocess SDK), Paperclip AI (#15; heartbeat-based мета-оркестрация), AgentCraft (#16; GUI wrapper), Factory Missions (#17; orchestrator-worker delegation), Oz (#19; cloud-managed runs), Sandcastle (#20; agent invocation loop в песочнице), Duet (#24; skill-driven orchestration), Multica (#25; daemon-based task dispatch), Agent Skills (#28; skill pack / prompt workflow library), SwarmForge (#29; tmux-based swarm orchestration platform), Orca ADE (#30; desktop/mobile manual parallel-agent harness), bx-dev (#31; Codex-skill/manual workflow harness), Herdr (#34; terminal agent runtime / control surface). **Duet** (#24) использует skill-driven orchestration: intent → skill selection → multi-phase autonomous execution. **Multica** (#25) использует daemon-based task dispatch (не agent loop, а task queue).

**AgentCraft не учитывается в этом подсчёте:** он не имеет собственной модели выполнения, а выступает как GUI wrapper, делегируя выполнение подключённым внешним агентам (Claude Code, OpenCode, Cursor, OpenClaw). Эти агенты сами используют agent loop — AgentCraft лишь управляет их запуском и визуализирует прогресс. Таким образом, AgentCraft не является ни «agent loop», ни «другой моделью выполнения» — это управляющий слой поверх существующих сред.

**Sandcastle не учитывается в этом подсчёте:** он не работает с LLM API напрямую. Sandcastle запускает внешние AI-агенты (Claude Code, Codex, Pi, OpenCode) как subprocess в песочнице — agent invocation loop, не классический `LLM → tool call → LLM`. Это ближе к Archon (subprocess SDK), но с фокусом на sandbox management (Docker/Podman/Vercel) вместо DAG workflow.

Agno также поддерживает **step-based workflow** (Step/Steps/Loop/Parallel/Router/Condition) и **4 team modes** (coordinate/route/broadcast/tasks) поверх agent loop — наиболее развитый workflow engine из исследованных.

**Multica не учитывается в этом подсчёте:** Multica — это не SDK и не agent runtime. Это project management platform, которая оркестирует внешние agent CLI через daemon-based task queue. Multica не вызывает LLM API напрямую — она делегирует выполнение подключённым agent CLI (Claude Code, Codex и т.д.) через subprocess. Это принципиально другой уровень: platform layer, а не execution layer.

**Agent Skills не учитывается в этом подсчёте:** это skill pack, а не execution layer. Он формализует workflows, но не выполняет LLM/tool loop самостоятельно.

**SwarmForge не учитывается в этом подсчёте:** он запускает внешние CLI-агенты (`codex`, `claude`, `copilot`, `grok`) в `tmux` sessions и координирует их handoff-файлами. Собственного LLM/tool loop у SwarmForge нет.

**bx-dev не учитывается в этом подсчёте:** это Codex skill, который координирует single-shot Codex subagents, но не вызывает LLM API и не реализует собственный `LLM → tool call → observation` loop.

**Herdr не учитывается в этом подсчёте:** это терминальная среда выполнения над внешними агентами. Она запускает, наблюдает и возобновляет их процессы, но не реализует собственный LLM/tool loop.

**Вывод:** Наша модель (YAML chain → runner call → payload) — это оркестрация поверх agent loop. Это правильный уровень: мы не дублируем LLM interaction, а управляем им. Oz (Warp) подтверждает тренд: облачные платформы (SaaS) управляют *запуском* агентов (когда, где, с каким окружением), а task-orchestrator управляет *процессом* (шаги, retry, quality gates).

### 3. Разделение на тринадцать уровней абстракции

**Все 32 завершённых исследования** чётко делятся на тринадцать уровней:

| Уровень | Проекты | Что делают | Аналог в task-orchestrator |
|---|---|---|---|
| **SDK / Agent runtime** | Crush, pi_agent_rust, OpenHands SDK, Mastra AI, Claude Code, Codex, OpenClaw, Agno, Hermes Agent, Kilo Code, **OpenCode**, **OmO**, **Zeroclaw** | Работают на уровне прямых LLM API | Runner'ы (pi, codex) |
| **Оркестратор / Workflow engine** | CrewAI, LangGraph, AutoGen, Archon, MetaGPT, Copilot Workspace | Управляют потоком выполнения между агентами/шагами | Chain executor |
| **Sandbox orchestration** | Sandcastle | Управляет жизненным циклом песочниц (Docker/Podman/Vercel), git worktrees, branch strategies для внешних AI-агентов | — (нет аналога) |
| **Swarm orchestration** | SwarmForge | Запускает локальный рой внешних AI-агентов в `tmux`, распределяет роли по `git worktree`, доставляет peer-to-peer handoffs через daemon | `docs/agents/roles/team/*` + `task-via-subagents` (частичный аналог, но централизованный) |
| **Terminal agent runtime / Control surface** | Herdr | Держит реальные PTY и внешние агенты в фоновом сервере, определяет их состояние, предоставляет CLI/socket API, события, восстановление и рабочие деревья Git | Потенциальная вспомогательная среда вокруг `run-subagent`; прямого аналога нет |
| **ADE / Manual agent harness** | Orca ADE, bx-dev | Orca: desktop/mobile workbench для параллельного запуска внешних CLI-агентов в `git worktree`; bx-dev: Codex-skill dev-session harness с single-shot subagents, `.bx-dev/` state, review → commit → post-commit QA/Merger lifecycle | `task-via-subagents` / `epic-via-subagents` + потенциальный fan-out/session workflow |
| **GUI Manager / Launcher** | AgentCraft | Визуальный интерфейс для запуска и мониторинга внешних агентов, без собственной логики выполнения | — (нет аналога) |
| **Multi-agent SaaS / Product** | Factory Missions | Автономная multi-day software development: orchestrator → workers → validators, file-based shared state | — (нет аналога, closest — chain executor + dynamic loops) |
| **Business Agent SaaS** | Duet (Aomni) | Always-on автономный бизнес-агент: skill-driven execution, shared workspace, scheduled pipelines, multi-channel delivery | — (нет аналога) |
| **Cloud Agent Platform** | Oz (Warp) | Облачная платформа оркестрации: Docker-окружения, cron/webhook/API триггеры, REST API/SDK, observability | — (нет аналога) |
| **Project Management Platform** | Multica | Управляет задачами (issues) для команд людей + AI-агентов: board, chat, autopilot, skills, runtimes | — (нет аналога) |
| **Skill Pack / Prompt Workflow Library** | Agent Skills | Поставляет переносимые SKILL.md, personas, commands, hooks и validator для host agents | `docs/agents/skills/*` + `docs/agents/roles/team/*` |
| **Мета-оркестратор / Control plane** | Paperclip AI | Управляет компаниями из агентов: org charts, budgets, governance, goals | — (нет аналога) |

**Multica** подтверждает тренд: platform layer между оркестратором и мета-оркестратором — управление задачами, людьми и агентами в едином workspace.

**Agent Skills** добавляет отдельный уровень **Skill Pack / Prompt Workflow Library**: это не runtime и не orchestrator, а переносимый слой инженерных workflows, personas, commands и validation вокруг host agents.

**SwarmForge** добавляет уровень **Swarm orchestration**: это не LLM runtime и не coding agent, а локальный control layer (контур управления) для нескольких внешних CLI-агентов с peer-to-peer handoff protocol. **Orca ADE** добавляет уровень **ADE / Manual parallel-agent harness**: desktop/mobile control surface для параллельных external CLI agents в git worktrees, с human-in-the-loop comparison and merge. **bx-dev** расширяет этот уровень до **manual agent harness** без GUI: Codex-skill переносит dev workflow `impl → review → commit → post-commit QA (amend on failure) → PR/merge → cleanup` в текстовый skill с session-state `.bx-dev/`.

**Herdr** добавляет отдельный уровень **Terminal agent runtime / Control surface**: это agent-aware (понимающий агентов) терминальный substrate между обычным мультиплексором и специализированным оркестратором. В отличие от SwarmForge, Orca и bx-dev, Herdr не предписывает роли, fan-out, handoff или review lifecycle; он предоставляет общие примитивы `start/prompt/wait/read`, постоянные PTY, состояния, события и worktree API.

**Paperclip AI** подтверждает тренд на многоуровневую абстракцию: SDK/runtime → оркестратор → GUI manager → мета-оркестратор. Paperclip — наиболее продвинутый мета-оркестратор из исследованных: org charts, budgets, governance, goal alignment, company portability.

### 4. SKILL.md / AGENTS.md — де-факто стандарт

**23 из 32 завершённых исследований** используют SKILL.md или аналогичный формат для формализации agent capabilities:
- Crush, pi_agent_rust, CrewAI, OpenHands SDK, Archon, OpenClaw, Mastra AI, Codex, Agno, Factory Missions (.factory/skills/), Hermes Agent, Oz (Warp) (oz-skills), Kilo Code, **OpenCode** (.opencode/skills/ + remote URLs + .claude/skills/ + .agents/skills/), **OmO** (Skill-Embedded MCPs: SKILL.md + собственные MCP-серверы), **Duet** (SKILL.md в duet-skills реестр: frontmatter id/model/tools + markdown body = system prompt), **Multica** (workspace-level skills, SKILL.md + files, import from URL/runtime/ClawHub/Skills.sh/GitHub), **Zeroclaw** (SkillForge: SKILL.md discovery + validation + WASM skill packaging), **Odysseus** (SKILL.md в memory/skills), **Agent Skills** (24 переносимых SKILL.md + validator + plugin manifests), **Orca ADE** (installable Orca Skills: `orca-cli`, `orchestration`, `computer-use`, `orca-linear`, emulator/per-workspace env skills), **bx-dev** (`skills/bx-dev/SKILL.md` + 105 bundled support skills / 9 categories), **Herdr** (`skills/herdr/SKILL.md` + `herdr --skill` для согласованной с выпуском копии)
- Формат: YAML frontmatter + markdown body, discovery из нескольких мест, валидация
- Стандарт [agentskills.io](https://agentskills.io) получает широкое распространение
- Paperclip AI добавляет Company Skills: managed skill registry с import/export, trust levels, compatibility checks
- **Multica** реализует workspace-level Skills (SKILL.md) с import из ClawHub/skills.sh/GitHub и per-agent assignment с provider-native injection (`.claude/skills/`, `CODEX_HOME/skills/`, `.opencode/skills/` и т.д.)

**AGENTS.md** используется как минимум в 10 проектах (Crush, pi_agent_rust, OpenHands SDK, Codex, Paperclip AI, Factory Missions (per-mission AGENTS.md с boundaries + guidance), Hermes Agent (`.hermes.md` / `HERMES.md`), Agent Skills, Orca ADE, task-orchestrator) — де-факто стандарт для AI-agent контекста.

### 5. MCP (Model Context Protocol) — повсеместный протокол расширения

**18 из 32 завершённых исследований** поддерживают MCP:
- Crush, CrewAI, OpenHands SDK, Archon, OpenClaw, Mastra AI, Claude Code, Copilot Cloud Agent, Codex, Agno, Paperclip AI, Hermes Agent, Oz (Warp), Kilo Code, **OpenCode** (full MCP client: tools + resources + OAuth), **OmO** (Skill-Embedded MCPs: MCP-серверы внутри скиллов, per-session изоляция), **Zeroclaw** (MCP client: подключение внешних MCP-серверов как инструментов), **Orca ADE** (MCP endpoints under Settings → Integrations → MCP; tools appear inside compatible agent CLIs)
- **Multica** поддерживает MCP через per-agent config (JSONB в `agent` таблице), но не на уровне platform — делегирует agent CLI
- MCP — стандарт де-факто для расширения возможностей AI-агентов через внешние tool-серверы
- **Duet** (#24) использует **Composio** вместо MCP — проприетарный интеграционный слой (19+ connectors). Это не стандарт MCP, но решает ту же задачу: доступ к внешним API через стандартизированный interface

**Вывод:** MCP-поддержка в task-orchestrator — вопрос времени. Но реализовывать нужно на уровне runner'ов, не оркестратора.

**Herdr не увеличивает числитель MCP:** его расширения используют CLI/socket API и исполняемые плагины. Это независимая управляющая поверхность, а не MCP client/server (клиент/сервер MCP).

### 6. Контекст-менеджмент — повсеместная проблема

**12 из 32 завершённых исследований** реализуют auto-compaction / auto-summarization при context overflow:
- Crush, pi_agent_rust, OpenHands SDK, Mastra AI, Claude Code, Codex, Agno, Hermes Agent, Kilo Code, **OpenCode** (7-секционный structured template + pruning + preserve recent turns + replay), **OmO** (proactive compaction: monitor + compact **до** ошибки + dynamic context pruning: dedup/supersede/purge), **Zeroclaw** (ContextCompressor: LLM-суммаризация + HistoryPruner: orphaned tool messages + collapsed pairs + protected indices + 1.2x token safety margin)
- Все используют LLM-суммаризацию для сжатия истории
- Hermes Agent — наиболее продвинутый подход: 14-секционный structured summary template, tool result pruning + deduplication, anti-thrashing protection, iterative summary updates, tool_call/result pair integrity, last-user-message anchoring (~1500 LOC)
- OpenClaw: формализованный `ContextEngine` interface (ingest → assemble → compact → maintain) с tokenBudget
- Mastra AI: Observer + Reflector agents с async buffering
- Paperclip AI: session compaction policy (per-adapter thresholds: max runs/tokens/age)

**Вывод:** Для длинных цепочек и dynamic loops контекст-менеджмент станет необходим. Но для текущих конечных цепочек (max_iterations) это P3. Factory Missions решает проблему иначе: **fresh context per worker session** — каждый worker стартует с чистым контекстом, читая state с диска.

### 7. Безопасность автономного выполнения — зреющий тренд

**8 проектов** имеют продвинутые модели безопасности:

| Проект | Подход | Уровень зрелости |
|---|---|---|
| **Codex** | Guardian (LLM safety) + exec policy (rules) + Docker sandbox (iptables) + split FS permissions | Production-ready, defence in depth |
| **Copilot Cloud Agent** | Docker-container sandbox + org-level policy engine + audit | Production-grade, enterprise |
| **OpenHands SDK** | Security risk assessment (LLM + heuristics) + confirmation policies + defense-in-depth rails | SDK-level, composable |
| **OpenCode** | Permission system (allow/ask/deny per tool + glob patterns + inherited для subagents + session-level overrides) + doom loop detection | Agent-level, composable, no Docker needed |
| **Claude Code** | Permission system (allow/deny) + tiered prompts | Basic but effective |
| **Paperclip AI** | Execution policies (multi-stage approval) + budget hard stops + agent pause/resume/terminate + activity audit | Governance-level, company-wide |
| **Oz (Warp)** | Agent Profiles & Permissions (autonomy levels) + command denylist + Docker isolation (Cloud Environments) + secrets management + run audit trail | Platform-level, SaaS-managed |
| **Zeroclaw** | 6-layer security model: channel pairing → autonomy levels (supervised/assisted/autonomous) → workspace boundary → command policy → OS sandbox → cryptographic tool receipts | Runtime-level, defence in depth, Rust |

**Sandcastle** — уникальная позиция: sandbox isolation как core product (не security feature). Встроенные SandboxProvider (Docker/Podman/Vercel) предоставляют container-level изоляцию по умолчанию. SELinux labels, UID/GID alignment, network isolation — из коробки.

**Herdr не входит в восемь продвинутых моделей безопасности:** локальные Unix-сокеты ограничены владельцем (`0600`), SSH использует OpenSSH, а история панелей с потенциальными секретами выключена по умолчанию. Но процессы работают с правами пользователя, плагины не изолируются, marketplace (каталог) не проходит проверку, собственных command policy (политики команд) и sandbox (песочницы) нет.

**Codex — наиболее полная реализация:** трёхуровневая модель (rules filter → LLM safety review → container isolation). Для CI/CD — наиболее готовый к production подход.

**Вывод:** Безопасность станет критичной при переходе к автономному выполнению в CI/CD. Рекомендуется начать с exec policy (rules) — это quick win.

### 8. Sub-agents / Multi-agent — тренд к иерархической декомпозиции

**20 из 32 завершённых runtime/platform-level исследований** поддерживают sub-agents или multi-agent на уровне runtime (среды выполнения) или platform-level (уровне платформы). Orca ADE входит в числитель как manual parallel-agent orchestration (ручная параллельная оркестрация агентов), bx-dev — как Codex-skill orchestration of single-shot subagents, Herdr — как агентная управляющая поверхность, где агент может создать панель, запустить именованного внешнего агента, отправить ему промпт и ждать состояния.

Воспроизводимый список 20 учтённых проектов:

1. Crush — Coder → Task.
2. Claude Code — Task tool.
3. Codex — spawn/send_message/wait/close_agent с depth limit.
4. OpenHands SDK — DelegateTool.
5. OpenClaw — ACP spawn с limits.
6. Mastra AI — agent network.
7. Archon — inline sub-agents.
8. CrewAI — Crew.
9. AutoGen — group chat.
10. Agno — Team с 4 режимами.
11. Factory Missions — Task tool для subagents: investigation, review, research.
12. Hermes Agent — delegate_task: parallel spawning, orchestrator/leaf roles, depth control до 3.
13. Kilo Code — task tool: isolated session, permission inheritance, cost propagation, resume, parallel invocation.
14. OpenCode — task tool: isolated session + permission inheritance + resume по task_id + no recursive delegation.
15. OmO — Team Mode: Lead + до 8 параллельных members, shared mailbox + shared task list, 12 team_* инструментов.
16. Zeroclaw — DelegateTool: sync/background/parallel delegation + SwarmTool: pipeline/parallel/router multi-agent patterns.
17. SwarmForge — peer-to-peer swarm coordination через `handoffd.bb`, durable inbox/outbox files и `git worktree` per role.
18. Orca ADE — manual parallel-agent orchestration через worktree fan-out и experimental messages/tasks/dispatches/worker_done/decision gates.
19. bx-dev — Codex-skill orchestration of single-shot subagents: Dev, reviewers, post-commit optional QA and Merger with explicit report/close lifecycle.
20. Herdr — agent-aware terminal runtime: `agent start/prompt/wait/read`, несколько независимых PTY, фиксация текущего владельца панели и worktree lifecycle.

Oz (Warp) не имеет sub-agents в традиционном понимании, но поддерживает **unlimited parallel cloud agents** — одновременный запуск множества независимых agent runs через API. Это горизонтальное масштабирование, не иерархическая декомпозиция.

**Agent Skills** вынесен за пределы этого счётчика: это host-dependent prompt-level fan-out pattern (паттерн веерной проверки на уровне промптов, зависящий от агента-хоста), а не runtime support (поддержка на уровне исполнения) sub-agents. Проект поставляет personas и canonical fan-out pattern (`/ship`: code-reviewer + security-auditor + test-engineer → merge), делегируя запуск host agent (например, Claude Code subagents).

**Duet** (#24) не поддерживает sub-agents — один агент per thread. Каждый skill описывает multi-phase workflow, выполняемый одним агентом (не несколькими). Это сознательное ограничение: простота и предсказуемость ценой параллелизма.

Sandcastle поддерживает multi-agent orchestration через **templates**: parallel-planner запускает N агентов параллельно (Promise.allSettled), каждый на своей ветке; sequential-reviewer — implement + review в shared sandbox. Это parallel `run()` calls, а не sub-agent primitive с собственной координацией, поэтому Sandcastle описан как adjacent pattern (смежный паттерн), но не включён в числитель 19.

AgentCraft реализует Agent Teams — мультиагентные командные workflows через GUI wrapper, но детали закрыты и не позволяют воспроизводимо подтвердить runtime/platform-level механизм; поэтому AgentCraft также вынесен из числителя.

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

**7 проектов** классифицируют ошибки для умного retry:
- Archon (FATAL/TRANSIENT/UNKNOWN), OpenClaw (6 категорий по HTTP status), Codex (Guardian risk taxonomy), Hermes Agent (20+ FailoverReason: auth, billing, rate_limit, overloaded, server_error, timeout, context_overflow, model_not_found, provider_policy_blocked и др.), **Kilo Code** (ContextOverflow, API 5xx, rate limit, FreeUsageLimitError, Kilo errors — retry/no-retry classification), **OpenCode** (ContextOverflow/API 5xx/rate limit/FreeUsageLimitError/GoUsageLimitError — retry/no-retry + Retry-After header parsing + doom loop detection), **Zeroclaw** (retryable: context window errors, 429, 408; non-retryable: 4xx except 429/408, auth failures, model not found + context limit auto-detection из error messages)

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
| **Python** | CrewAI, LangGraph, AutoGen, OpenHands SDK, MetaGPT, Agno, Hermes Agent, **bx-dev** (support scripts; primary artifact is Markdown skill) | Доминирующий язык для AI-agent фреймворков и support tooling |
| **TypeScript** | Archon, OpenClaw, Mastra AI, Paperclip AI, Sandcastle, Kilo Code, **OpenCode**, **Multica** (frontend), Orca ADE | Растущая экосистема, особенно для workflow engines, мета-оркестраторов, ADE и sandbox orchestration |
| **Rust** | pi_agent_rust, Codex (codex-rs), **Zeroclaw**, **Herdr** | High-performance CLI-агенты и терминальные среды выполнения |
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
* **SwarmForge — самый близкий open-source аналог нашей ролевой governance-модели:** `swarmforge.conf` задаёт team topology, `roles/*.prompt` задают role ownership, layered constitution задаёт shared/project/local rules, а `handoffd.bb` доставляет structured handoffs (`awake`/`git_handoff`/`note`) между worktree-изолированными ролями. При этом SwarmForge desktop-first (`tmux`, Terminal.app/iTerm2/Ghostty/Windows Terminal), не CI/server-first и не имеет retry, circuit breaker, quality gates или budget control. Вердикт: не dependency, но сильный источник patterns для layered instructions, structured handoff и pack presets.
* **Sandcastle — наиболее продвинутый sandbox orchestration layer** из исследованных: plug-and-play SandboxProvider (Docker/Podman/Vercel/Daytona/custom), 3 branch strategies (head/merge-to-head/branch), git worktree management со stale pruning и dirty preservation, AgentProvider interface (4 built-in: Claude Code/Codex/Pi/OpenCode), structured output (Zod), completion signal, prompt template engine ({{KEY}} + !`command`), 5 multi-agent templates. Построен на Effect-TS — функциональной effect system. Не workflow engine и не chain orchestrator — инфраструктурный слой для запуска агентов в песочницах. Наиболее зрелая реализация sandbox management из исследованных: SELinux labels, UID/GID alignment, Windows path compatibility, worktree locking. Подтверждает тренд separation of concerns: sandbox orchestration (Sandcastle) → chain orchestrator (task-orchestrator) → cloud agent platform (Oz).
* **Kilo Code — наиболее развитая AI-агентная платформа для разработки** из исследованных: VS Code extension + CLI + JetBrains plugin, 19K звёзд, MIT лицензия, 7 built-in агентов (code/plan/debug/ask/orchestrator/general/explore), custom agents через JSON/markdown/CLI, permission system с glob patterns, context compaction с structured template, error classification для retry policy, subagent delegation через task tool (isolated session, permission inheritance, cost propagation, resume, parallel invocation), MCP, Skills (SKILL.md), Plugins, Workflows (slash commands), autonomous CI/CD mode (`kilo run --auto`). Orchestrator Mode объявлен **deprecated** — subagents встроены в каждый primary agent. Wave-based execution pattern (parallel waves, dependency classification) — модель для будущих dynamic chains. Построен на Effect-TS + Bun + Vercel AI SDK. Подтверждает тренд: оркестрация — не отдельный режим, а capability каждого агента.
* **OpenCode — наиболее популярный open source AI-coding agent** из исследованных: 156K+ звёзд, 18K+ форков, TypeScript/Bun, MIT лицензия. TUI + Desktop App + VS Code/JetBrains/Zed extensions + клиент-серверная архитектура (HTTP API + WebSocket + mDNS). 23+ LLM провайдеров через Vercel AI SDK (provider-agnostic). 7 built-in агентов (build/plan/general/explore/compaction/title/summary) + custom agents через Markdown (.opencode/agent/*.md) + AI-генерация. Doom loop detection (3 идентичных tool call → ask). Permission system с glob patterns (allow/ask/deny per tool, session-level overrides, inherited для subagents). Context compaction с 7-секционным structured template + pruning + preserve recent + replay. Subagent delegation через task tool (isolated session + resume по task_id). Error classification с Retry-After header parsing. Git worktree management (create/remove/reset + submodule update). Snapshot tracking + revert (file diff per step). Skills (SKILL.md), MCP, Plugins, ACP (Agent Client Protocol). Cost tracking с provider-specific pricing (cache tokens, reasoning tokens). Подтверждает тренды: provider-agnostic, клиент-серверная архитектура, structured compaction, doom loop detection.
* **Oh My OpenAgent (OmO) — plugin-оркестратор поверх OpenCode**, добавляющий три уникальных паттерна, не встреченных в других 22 проектах: (1) IntentGate — pre-orchestration routing через классификацию намерения **до** выполнения; (2) Skill-Embedded MCPs — скиллы, несущие собственные MCP-серверы с per-session изоляцией; (3) proactive context management — мониторинг + компакция + pruning **до** ошибки, а не реактивно при переполнении. OmO = plugin, не standalone продукт. Лицензия SUL-1.0 ограничивает коммерческое распространение, но разрешает внутреннее бизнес-использование. Архитектурное ревью (Гэндальф): 2 паттерна — заимствовать (IntentGate, Skill-Embedded Tools), 3 — наблюдать (Discipline Agents, Team Mode, Category System), 1 — антипаттерн (Dual-Prompt: нарушение слоистой архитектуры).
* **Duet (Aomni) — единственный business-agent SaaS** в исследовании: always-on AI-агент для командной работы, автоматизирующий GTM, product и ops workflows. Позиционирование: «Build an autonomous business in 45 minutes». Уникальная модель оркестрации — skill-driven: intent → skill selection → multi-phase autonomous execution. Не DAG, не agent loop, не graph — декларативные SKILL.md описывают multi-phase workflows (2–7 фаз), которые LLM автономно выполняет. Error handling — полностью prompt-driven (gotchas в SKILL.md), без retry/CB/fallback. Расширяемость через SKILL.md (19 default + 12 industry skills), UseCase surface placements (strict TypeScript-enforced separation capability/presentation), Composio integrations (19+), chat SDK adapters (8 platforms). Cron tool для scheduled pipelines. Build-apps tool для генерации мини-приложений. Подтверждает тренд: SaaS-продукты на уровне целых бизнес-процессов — отдельная ниша, не пересекающаяся с chain orchestration.

* **Multica — первый project management platform для human+agent teams** в исследовании: Linear/Jira-like board, где AI-агенты — first-class teammates с профилями, assignee picker и activity timeline. Уникальная комбинация: daemon-based task queue (poll + WS wakeup) + session resumption + poisoned session detection + autopilot (cron/webhook) + workspace-level skills с provider-native injection. Multica **не вызывает LLM API напрямую** — все AI-взаимодействия делегируются внешним agent CLI (11 providers). Это platform layer, а не execution engine — принципиально другой уровень, чем task-orchestrator. Подтверждает тренд separation of concerns: chain orchestrator (task-orchestrator) → project management platform (Multica) → meta-orchestrator (Paperclip AI).
* **Orca ADE — первый полноценный open-source desktop/mobile ADE для параллельной ручной оркестрации coding-агентов** в исследовании: Electron/TypeScript desktop, React Native mobile companion, BYO subscriptions, any CLI agent, real `git worktree` per task/agent, fan-out prompt → compare → merge winner, Tasks/Automations, installable Orca Skills и MCP endpoints. Orca **не вызывает LLM API напрямую** и не заменяет coding agents; это control surface поверх Codex/Claude Code/OpenCode/Pi/etc. Вердикт: не dependency, но ценный источник patterns для fan-out comparison, worktree isolation, dispatch-id completion authority и live monitoring.
* **bx-dev — Codex-native зеркало нашего `task-via-subagents` workflow**: самодостаточный `$bx-dev` skill создаёт session branch от `origin/dev`, хранит state в `.bx-dev/<session-id>/`, orchestrates single-shot Codex subagents (Dev → review → commit → post-commit optional QA → Merger), проводит PR/merge/cleanup and packages 105 support skills / 9 categories. Это не coding agent и не LLM runtime: BYO Codex runtime, no agent-loop, no chain-level retry/CB/budget/fix_iterations. Вердикт: не dependency, но ценный источник patterns для strict mode flags, scout-plan gate, session-state recovery, structured subagent reports and standalone MERGE-PROTOCOL.
* **Herdr — первый agent-aware terminal runtime в исследовании:** один Rust binary держит реальные PTY в фоновом сервере, распознаёт состояния внешних агентов, предоставляет 90-method CLI/socket API, события, Git worktrees, SSH remote attach, native resume и release-matched `SKILL.md`. Это не chain engine и не LLM runtime: нет universal retry/CB/gates/budget или структурированного результата хода. Вердикт: не core dependency, но ценный источник patterns для lifecycle authority/evidence, atomic prompt-and-wait, occupant pinning, ownership и layered resume semantics; вручную может быть вспомогательной средой рядом с проектом.

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
| 2026-05-13 | Аналитик (Шерлок) | Создан отчёт multica-comparison.md, заполнена строка Multica (#25). Пересчитаны тренды (23→25). Добавлен девятый уровень абстракции (Project Management Platform). Добавлены рекомендации Multica (P2: poisoned session detection, autopilot cron/webhook, runtime health + admission check; P3: session resumption, GC loop). Добавлено наблюдение: Multica — первый project management platform для human+agent teams в исследовании. |
| 2026-05-20 | Аналитик (Шерлок) | Создан отчёт zeroclaw-comparison.md, заполнена строка Zeroclaw (#26). Пересчитаны тренды (25→26). Добавлены рекомендации Zeroclaw (P2 Quick win: loop detection 3 patterns, error classification, context limit auto-detection; P3: hint-based model routing, history pruning, SOP deterministic mode, cost-optimized routing; R&D: cryptographic tool receipts, 6-layer security). Добавлено наблюдение: Zeroclaw — тематически связан с OpenClaw (независимый проект, не fork), наиболее развитый single-agent runtime (SOP engine, loop detection, 6-layer security, WASM plugins, hardware). |
| 2026-06-13 | Аналитик (Шерлок) | Создан отчёт agent-skills-comparison.md, заполнена строка Agent Skills (#28). Статус заполнения обновлён до 28/28. Добавлены рекомендации: skill anatomy validator, anti-rationalization tables, red flags, lifecycle mapping, persona composition guardrail. |
| 2026-06-18 | Аналитик (Шерлок) | Создан отчёт swarm-forge-comparison.md, заполнена строка SwarmForge (#29). Статус заполнения обновлён до 29/29. Добавлены рекомендации: layered constitution override semantics, strict handoff draft schema, role ownership blocks, pack presets, batch receive mode, git worktree per role. |
| 2026-07-10 | Аналитик (Шерлок) | Создан отчёт orca-ade-comparison.md, заполнена строка Orca ADE (#30). Статус заполнения обновлён до 30/30. Пересчитаны тренды (29→30): agent loop 19/30 (Orca не execution layer), SKILL.md 21/30, MCP 18/30, sub-agents/multi-agent 18/30 с воспроизводимым списком учтённых проектов, compression 12/30. Уточнено: у Orca нет universal runner-/chain-level retry/backoff/CB, но есть experimental dispatch-level retry/circuit-break после 3 failures. Добавлен двенадцатый уровень абстракции: ADE / manual parallel-agent harness. Добавлены рекомендации: parallel fan-out comparison, structured worker_done/dispatch-id reports, BYO runner ergonomics, worktree isolation, workspace setup/preflight recipe, live monitoring. |
| 2026-07-26 | Аналитик (Шерлок) | Создан отчёт bx-dev-skill-comparison.md, заполнена строка bx-dev (#31). Статус заполнения обновлён до 31/31. Пересчитаны тренды: agent loop 19/31 (bx-dev не execution layer), SKILL.md 22/31, MCP 18/31, sub-agents/multi-agent 19/31, compression 12/31. Добавлены рекомендации: strict workflow flags, scout-plan approval gate, session-state resume metadata, structured subagent report/close contract, optional post-commit QA flag, MERGE-PROTOCOL artifact, skill-library router/manifest. |
| 2026-08-11 | Аналитик (Шерлок) | Создан отчёт herdr-comparison.md, заполнена строка Herdr (#34). Статус обновлён до 32 завершённых / 34 запланированных: #32 qm и #33 omnigent ожидают исследования. Пересчитаны тренды на 32 завершённых результата: agent loop 19/32 (Herdr не execution layer), SKILL.md 23/32, MCP 18/32, sub-agents/multi-agent 20/32, compression 12/32. Добавлен тринадцатый уровень `Terminal agent runtime / Control surface` и рекомендации: lifecycle authority/evidence, atomic prompt-and-wait, prompt transition stall check, opaque IDs/ownership, worktree lifecycle, release-matched skill, layered resume semantics. |
