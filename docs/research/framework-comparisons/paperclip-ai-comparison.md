# Исследование: Paperclip AI — Control Plane для AI-агентов (TypeScript/Node.js)

> **Проект:** [github.com/paperclipai/paperclip](https://github.com/paperclipai/paperclip)
> **Дата анализа:** 2026-04-28
> **Язык:** TypeScript (Node.js)
> **Лицензия:** MIT
> **Звёзды:** 60 000+
> **Форки:** 10 000+
> **Аналитик:** Технический писатель (Гермиона)

---

## 1. Обзор проекта

Paperclip AI — это open-source «control plane» (панель управления) для оркестрации команд AI-агентов, моделирующий целую компанию. Ключевая идея: **не фреймворк для построения агентов, а платформа для управления агентами как сотрудниками компании**. Paperclip предоставляет org chart (органограмму), бюджеты, governance (управление), goal alignment (выравнивание целей), heartbeat-выполнение и ticket-систему — всё для координации множества внешних AI-агентов (Claude Code, Codex, Cursor, Gemini, OpenClaw, pi, HTTP-боты).

Paperclip **не является** фреймворком оркестрации цепочек шагов (chains) или agent loop runtime. Это **мета-оркестратор уровня выше**: он управляет тем, *какие* задачи назначаются агентам, *когда* они их выполняют, сколько это *стоит* и кто *утверждает* результаты. В отличие от task-orchestrator, Paperclip не определяет последовательность шагов внутри одной задачи — он управляет потоком задач между агентами на уровне компании.

### Архитектура

```
paperclipai/paperclip/
├── server/ # Express REST API + сервисы оркестрации
│ ├── src/
│ │ ├── adapters/ # Адаптеры: process (CLI), HTTP
│ │ │ ├── process/execute.ts # Process adapter — subprocess execution
│ │ │ └── http/execute.ts # HTTP adapter — webhook/API invocation
│ │ ├── auth/ # Аутентификация: better-auth, JWT
│ │ ├── middleware/ # Auth, error handler, logging, validation
│ │ ├── routes/ # REST API маршруты (~30 модулей)
│ │ ├── secrets/ # Encrypted storage, provider registry
│ │ ├── services/ # Бизнес-логика (~70 сервисов)
│ │ │ ├── heartbeat.ts # ★ Core execution engine: heartbeat runs
│ │ │ ├── agents.ts # Agent CRUD, config revisions, API keys
│ │ │ ├── issues.ts # Task/issue management, status workflow
│ │ │ ├── budgets.ts # Budget policies, enforcement, incidents
│ │ │ ├── costs.ts # Cost tracking, monthly spend, billing
│ │ │ ├── approvals.ts # Governance: approval workflows
│ │ │ ├── routines.ts # Scheduled tasks (cron, webhook, API)
│ │ │ ├── recovery/ # ★ Run recovery, liveness, auto-restart
│ │ │ ├── company-skills.ts # Skill management, import/export
│ │ │ ├── agent-instructions.ts # AGENTS.md prompt bundle management
│ │ │ ├── environments.ts # Execution environments (local, remote)
│ │ │ ├── execution-workspaces/ # Git worktree isolation
│ │ │ ├── run-liveness.ts # ★ Stuck run detection (LLM output analysis)
│ │ │ ├── run-continuations.ts # Run continuation after transient failure
│ │ │ ├── plugin-*.ts # Plugin lifecycle, registry, workers
│ │ │ └── ... # +40 других сервисов
│ │ └── storage/ # Local disk, S3 providers
│ └── scripts/
├── packages/
│ ├── db/ # Drizzle ORM schema (~70 таблиц), migrations
│ ├── shared/ # Types, validators, constants, telemetry
│ ├── adapter-utils/ # Shared adapter utilities, session compaction
│ ├── adapters/ # Agent adapter implementations
│ │ ├── claude-local/ # Claude Code adapter (subprocess, skill sync)
│ │ ├── codex-local/ # OpenAI Codex adapter
│ │ ├── cursor-local/ # Cursor adapter
│ │ ├── gemini-local/ # Gemini adapter
│ │ ├── openclaw-gateway/ # OpenClaw gateway adapter
│ │ ├── opencode-local/ # OpenCode adapter
│ │ └── pi-local/ # Pi adapter
│ ├── plugins/ # Plugin system
│ │ ├── sdk/ # Plugin SDK (definePlugin, context API)
│ │ ├── examples/ # Example plugins (kitchen-sink, orchestration)
│ │ └── sandbox-providers/ # E2B sandbox provider
│ └── mcp-server/ # MCP server integration
├── ui/ # React + Vite dashboard UI
├── cli/ # CLI tools (onboard, configure)
├── skills/ # Built-in agent skills
└── tests/ # Integration tests (Playwright)
```

### Ключевые характеристики

| Характеристика | Значение |
| --- | --- |
| **Тип** | Мета-оркестратор (control plane) для AI-агентных компаний |
| **Модель выполнения** | Heartbeat-based: scheduled/event-driven wakeup → adapter invocation → result |
| **State management** | Persistent (PostgreSQL / embedded PGlite), ~70 таблиц |
| **Провайдеры агентов** | Claude Code, Codex, Cursor, Gemini, OpenClaw, pi, HTTP, process |
| **Расширяемость** | Plugin SDK, MCP server, adapter interface, skill system |
| **Интерфейс** | React UI dashboard + REST API + CLI |
| **Платформы** | Node.js 20+, кроссплатформенный |

### Основные компоненты

| Компонент | Назначение |
| --- | --- |
| [`server/src/services/heartbeat.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/heartbeat.ts) | ★ Core engine: wakeup queue, budget enforcement, adapter invocation, run lifecycle, session compaction |
| [`server/src/services/agents.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/agents.ts) | Agent CRUD, config revisions (с rollback), API keys, runtime state |
| [`server/src/services/issues.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/issues.ts) | Task/issue management: status workflow, atomic checkout, blocker dependencies |
| [`server/src/services/budgets.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/budgets.ts) | Budget policies: scoped (company/agent/project), warning thresholds, hard stops, auto-pause |
| [`server/src/services/costs.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/costs.ts) | Cost tracking: per-agent, per-company, per-provider billing types, monthly spend |
| [`server/src/services/approvals.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/approvals.ts) | Governance: approval workflows, decision tracking, hire approvals |
| [`server/src/services/routines.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/routines.ts) | Scheduled routines: cron, webhook triggers, catch-up policies, concurrency |
| [`server/src/services/recovery/`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/recovery/) | ★ Run recovery: liveness classification, stranded issue recovery, stale run evaluation |
| [`server/src/services/run-liveness.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/run-liveness.ts) | ★ Stuck run detection: regex-based output analysis, evidence-based liveness classification |
| [`server/src/services/issue-execution-policy.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/issue-execution-policy.ts) | Execution policies: multi-stage review/approval per issue |
| [`packages/adapter-utils/src/session-compaction.ts`](https://github.com/paperclipai/paperclip/blob/master/packages/adapter-utils/src/session-compaction.ts) | Session compaction: policy per adapter, max runs/tokens/age thresholds |
| [`packages/plugins/sdk/`](https://github.com/paperclipai/paperclip/blob/master/packages/plugins/sdk/) | Plugin SDK: definePlugin, events, jobs, data, tools, state, UI contributions |
| [`packages/adapters/`](https://github.com/paperclipai/paperclip/blob/master/packages/adapters/) | Agent adapters: Claude, Codex, Cursor, Gemini, OpenClaw, pi, OpenCode |

---

## 2. Возможности оркестрации — обзор

| Функция | Paperclip AI |
| --- | --- |
| **Retry с backoff** | ✅ Transient failure retry с bounded delays (2m → 10m → 30m → 2h) |
| **Бюджетный контроль** | ✅ Scoped budget policies (company/agent/project), warning + hard stop, auto-pause |
| **Audit Trail (JSONL)** | ✅ Activity log (DB), run events, workspace operation logs |
| **Ролевые промпты** | ✅ AGENTS.md bundles (managed/external), skill injection per adapter |
| **Multiple runners** | ✅ 7+ adapters: Claude, Codex, Cursor, Gemini, OpenClaw, pi, HTTP, process |
| **DDD-архитектура** | ❌ Плоская структура: routes → services → db schema |
| **Decorator pattern** | ❌ Прямой вызов через adapter.execute() |
| **YAML-конфигурация** | ✅ DB-stored config, API-driven |
| **Org chart / иерархия агентов** | ✅ Роли, title, reportsTo, иерархия управления |
| **Goal alignment** | ✅ Company → Project → Goal → Issue, goal ancestry в контексте агента |
| **Ticket system (issues)** | ✅ Full issue lifecycle: backlog → todo → in_progress → in_review → done |
| **Heartbeat / scheduling** | ✅ DB-backed wakeup queue, cron/webhook/API triggers, coalescing |
| **Governance / approvals** | ✅ Multi-stage approval workflows, decision tracking, hire approvals |
| **Budget enforcement** | ✅ Scoped policies, warning thresholds, hard stops, auto-pause агентов |
| **Run liveness / stuck detection** | ✅ Regex-based output analysis + evidence-based liveness classification |
| **Run recovery** | ✅ Stranded issue recovery, stale run evaluation, auto-wakeup |
| **Session compaction** | ✅ Per-adapter policy: max runs/tokens/age, adapter-managed vs. threshold |
| **Config revisions / rollback** | ✅ Agent config revisions с SHA snapshot + rollback |
| **Plugin system** | ✅ Full SDK: events, jobs, data, tools, state, UI contributions, DB |
| **Multi-company isolation** | ✅ Every entity is company-scoped, complete data isolation |
| **React UI dashboard** | ✅ Full React + Vite dashboard: org chart, costs, issues, agents |
| **Company portability** | ✅ Export/import entire orgs, secret scrubbing, collision handling |
| **Execution workspaces** | ✅ Git worktree isolation, runtime services (dev servers, preview URLs) |
| **MCP server** | ✅ MCP server package (packages/mcp-server) |

---

## 3. Оркестрационные возможности

### 3.1 🟡 Run Liveness / Stuck Detection (`server/src/services/run-liveness.ts`)

**Что у них:** Paperclip классифицирует каждый завершённый run по «liveness» — живой ли он или застрял. Классификация основана на:

1. **Regex-анализ вывода агента**: паттерны blocker (`can't proceed`, `waiting on`), manager review (`security review`, `deploy to production`), approval required (`pending approval`), runnable (`run tests`, `implement`)
2. **Evidence-based подход**: подсчёт комментариев, ревизий документов, work products, workspace operations, tool/action events — есть ли прогресс
3. **Контекстная классификация**: issue title/description анализируются (планирование vs. реализация)
4. **Actionability assessment**: `runnable` / `manager_review` / `blocked_external` / `approval_required` / `unknown`

```typescript
// Пример regex паттернов из run-liveness.ts
const BLOCKER_RE = /\b(?:blocked|can't proceed|cannot proceed|waiting on|need(?:s|ed)? .{0,80}\b(?:approval|access|credential|api key|token))\b/i;
const MANAGER_REVIEW_RE = /\b(?:manager review|human review|manual review|security review|escalate)\b/i;
const APPROVAL_REQUIRED_RE = /\b(?:approval required|requires? .{0,80}\bapproval|pending approval)\b/i;
```

**Оркестрационная значимость:** Для итерационных циклов (fix_iterations) — если агент застрял (повторяет одни и те же действия, или не может продвинуться), нужно это обнаружить и остановить. Подход Paperclip через regex + evidence — более продвинутый, чем простой loop detection через SHA-256 сигнатуры (как у Crush), и не требует LLM-вызова.

---

### 3.2 🟡 Transient Failure Retry с Bounded Backoff (`server/src/services/heartbeat.ts`)

**Что у них:** Paperclip реализует retry при transient-ошибках (transient upstream) с bounded exponential backoff:

```typescript
const BOUNDED_TRANSIENT_HEARTBEAT_RETRY_DELAYS_MS = [
 2 * 60 * 1000, // 2 минуты
 10 * 60 * 1000, // 10 минут
 30 * 60 * 1000, // 30 минут
 2 * 60 * 60 * 1000, // 2 часа
];
```

И escalation strategy для Codex-адаптера:
- Attempt 1: same_session (retry в той же сессии)
- Attempt 2: safer_invocation (более безопасные параметры)
- Attempt 3: fresh_session (новая сессия)
- Attempt 4+: fresh_session_safer_invocation

**Оркестрационная значимость:** Наш RetryingAgentRunner делает retry с backoff, но без классификации ошибок и без escalation strategy. Подход Paperclip — retry *с изменением стратегии* при каждой попытке — это более продвинутый паттерн, чем простой retry.

---

### 3.3 🟡 Budget Enforcement — Scoped Policies (`server/src/services/budgets.ts`)

**Что у них:** Paperclip реализует многоуровневую систему бюджетов:

- **Scope types:** company / agent / project — бюджет можно задать на любом уровне
- **Window types:** monthly / lifetime — период учёта
- **Metrics:** billed_cents (стоимость в центах)
- **Thresholds:** warning (предупреждение) + hard stop (автоматическая остановка)
- **Auto-pause:** при hard stop — агент автоматически ставится на паузу, queued work отменяется
- **Budget incidents:** создание инцидента при превышении порога, с approval для восстановления

```typescript
function budgetStatusFromObserved(observedAmount, amount, warnPercent) {
 if (amount <= 0) return "ok";
 if (observedAmount >= amount) return "hard_stop";
 if (observedAmount >= Math.ceil((amount * warnPercent) / 100)) return "warning";
 return "ok";
}
```

**Оркестрационная значимость:** Наш BudgetVo контролирует бюджет на уровне шага в цепочке. У Paperclip — на уровне компании/агента/проекта с monthly/lifetime окном. Это разные уровни: мы контролируем *одну цепочку*, Paperclip — *всего агента*. Но подход с scoped policies + warning thresholds + auto-pause — более гибкий.

---

### 3.4 🟡 Config Revisions / Rollback (`server/src/services/agents.ts`)

**Что у них:** Paperclip сохраняет snapshot конфигурации агента при каждом изменении. Механизм основан на отдельной таблице `agent_config_revisions` (Drizzle ORM schema):

```typescript
const CONFIG_REVISION_FIELDS = [
 "name", "role", "title", "reportsTo", "capabilities",
 "adapterType", "adapterConfig", "runtimeConfig",
 "defaultEnvironmentId", "budgetMonthlyCents", "metadata",
];
```

Каждый revision содержит:
- **JSON snapshot** — полный дамп всех полей из `CONFIG_REVISION_FIELDS` на момент изменения
- **SHA snapshot** — хеш для быстрого сравнения
- **Метаданные** — `createdBy` (кто изменил), `source` (откуда: API, UI, CLI, rollback), `rollbackSourceRevisionId` (если это rollback — ссылка на исходный revision)

**Механика rollback:** Rollback не перезаписывает историю — он создаёт *новый* revision, чей JSON snapshot копируется из целевого предыдущего revision. Это append-only модель: история изменений никогда не удаляется, rollback фиксируется как отдельное событие с `source: "rollback"`.

**Trade-offs подхода:**
- **Append-only vs. diff-based:** Paperclip хранит полный JSON snapshot на каждое изменение, а не diff. Это упрощает чтение (не нужно применять цепочку diffs), но при частых изменениях (например, автоматический rotation API keys) storage растёт линейно.
- **Нет semantic diff:** два revision'а сравниваются побайтово (SHA), но нет инструмента для отображения *смысловых* различий (какие именно параметры изменились и как).
- **Нет GC policy:** в документации и коде не обнаружен механизм очистки старых revisions. При длительной эксплуатации количество revisions будет расти неограниченно.
- **Нет branching/merging:** конфигурация — линейная история. Невозможно вести параллельные конфигурации (например, staging vs. production) и мержить их.

---

### 3.5 🟡 Session Compaction / Context Management (`packages/adapter-utils/src/session-compaction.ts`)

**Что у них:** Paperclip реализует политику сжатия сессий с per-adapter конфигурацией:

```typescript
const DEFAULT_SESSION_COMPACTION_POLICY: SessionCompactionPolicy = {
 enabled: true,
 maxSessionRuns: 200, // максимум runs в сессии
 maxRawInputTokens: 2_000_000, // максимум токенов
 maxSessionAgeHours: 72, // максимум возраст сессии
};
```

Адаптеры с native context management (Claude Code, Codex) получают `ADAPTER_MANAGED_SESSION_POLICY` — Paperclip не вмешивается в их context management. Остальные адаптеры получают default policy.

**Оркестрационная значимость:** Для длинных цепочек с многократными вызовами runner'ов — контекст может расти. Per-runner policy compression — актуально для session-based runner'ов (Claude Code, Codex).

---

### 3.6 🟡 Goal Alignment — Goal Ancestry в контексте агента (`server/src/services/goals.ts`, heartbeat)

**Что у них:** Paperclip создаёт иерархию целей: Company → Project → Goal → Issue. Каждый issue содержит full goal ancestry — агент видит не только текущую задачу, но и полный путь до корневой цели:

```
Company: "Acme Corp" (mission: "Build reliable payment infrastructure")
  └─ Project: "Billing v2" (description: "Redesign billing system")
      └─ Goal: "Migrate to new pricing model" (description: "...")
          └─ Issue #42: "Implement proration logic" (description: "...")
```

Goal ancestry передаётся агенту двумя механизмами:
1. **Environment variables:** `PAPERCLIP_TASK_ID` (issue ID), `PAPERCLIP_WAKE_REASON` (причина пробуждения: cron, webhook, manual, catch-up)
2. **Prompt injection:** heartbeat-сервис формирует текстовый блок с ancestry и вставляет его в инструкции для adapter execution. Адаптеры (например, Claude Code) получают это как часть AGENTS.md bundle.

**Ограничения подхода:**
- **Token overhead:** full ancestry для глубоких иерархий (company → 3-4 уровня projects/goals → issue) может занимать сотни токенов в промпте. При множестве параллельных issue'ов это суммируется.
- **Stale context:** если goal или project изменились между началом и концом выполнения issue, агент может работать с устаревшим контекстом. Механизма refresh goal ancestry во время выполнения не обнаружено.
- **Нет формальной оценки эффективности:** утверждение «goal alignment улучшает качество решений» — концептуальное. Paperclip не предоставляет метрик (A/B тесты, сравнение с/без ancestry) для подтверждения.
- **Flat structure:** внутри одного Goal все Issues равнозначны — нет приоритизации по вкладу в цель (weight, impact score).



---

### 3.7 🟡 Issue Execution Policy — Multi-Stage Approval (`server/src/services/issue-execution-policy.ts`)

**Что у них:** Paperclip позволяет задать execution policy для issue — multi-stage процесс с approval gates:

```typescript
interface IssueExecutionPolicy {
 mode: "normal";
 commentRequired: boolean;
 stages: Array<{
 type: string;
 approvalsNeeded: 1;
 participants: Array<{
 type: "agent" | "user";
 agentId?: string;
 userId?: string;
 }>;
 }>;
}
```

Каждый stage требует approval от указанных участников. Execution state отслеживает текущую стадию и решения.

**Оркестрационная значимость:** Это аналог наших quality gates, но на уровне governance (кто утверждает), а не технических проверок (прошли ли тесты). Комбинация: quality gates (автоматическая проверка) + execution policy (human approval) — полная модель.

---

### 3.8 🟡 Plugin System (`packages/plugins/sdk/`)

**Что у них:** Paperclip реализует полнофункциональную plugin system:

- **definePlugin()** — factory для определения plugin
- **PluginContext API:**
 - `ctx.events.on("issue.created", ...)` — подписка на domain events
 - `ctx.jobs.register("full-sync", ...)` — регистрация job handlers
 - `ctx.data.register("sync-health", ...)` — data providers для UI
 - `ctx.tools.register(...)` — предоставляет tools для агентов
 - `ctx.state.get/set(...)` — persistent state
 - `ctx.config.get(...)` — plugin config
 - `ctx.secrets.resolve(...)` — секреты
 - `ctx.http.fetch(...)` — HTTP client
 - `ctx.logger.info(...)` — logging
- **Capability-gated:** плагины объявляют необходимые capabilities, host валидирует
- **Out-of-process workers:** плагины запускаются как отдельные процессы (JSON-RPC)
- **UI contributions:** плагины могут добавлять UI-компоненты в dashboard
- **Plugin DB:** каждый плагин может иметь собственные таблицы в DB

**Оркестрационная значимость:** Plugin system — mechanism для расширения оркестратора без изменения core. Пользователи могут добавлять custom runners, quality gates, event handlers через plugin SDK.

**⚠️ Преждевременная рекомендация для CLI:** Plugin system оправдан только при появлении внешних пользователей, которым нужна кастомизация без форка. Для текущего stage проекта (CLI-утилита с внутренним использованием) — накладные расходы на plugin SDK (out-of-process workers, DB, UI contributions) не оправданы. Расширение через реализацию `AgentRunnerInterface` покрывает текущие потребности.

---

### 3.9 🟡 Agent Adapter Interface (`packages/adapter-utils/src/types.ts`)

**Что у них:** Paperclip определяет adapter interface для подключения внешних AI-агентов:

```typescript
interface AdapterExecutionContext {
 runId: string;
 agent: AdapterAgent;
 runtime: AdapterRuntime;
 config: Record<string, unknown>;
 context: Record<string, unknown>;
 executionTarget?: AdapterExecutionTarget;
 onLog: (stream, chunk) => Promise<void>;
 onMeta?: (meta) => Promise<void>;
 onSpawn?: (meta) => Promise<void>;
 authToken?: string;
}

interface AdapterExecutionResult {
 exitCode: number | null;
 timedOut: boolean;
 errorMessage?: string;
 errorCode?: string;
 errorFamily?: AdapterExecutionErrorFamily; // "transient_upstream"
 usage?: UsageSummary;
 costUsd?: number;
 billingType?: AdapterBillingType;
 summary?: string;
 runtimeServices?: AdapterRuntimeServiceReport[];
 question?: { prompt, choices }; // HITL
}
```

Ключевые особенности:
- **Error classification:** `errorCode` + `errorFamily` для transient failure detection
- **Cost tracking:** `costUsd` + `billingType` (api/subscription/metered/credits/fixed)
- **HITL:** `question` — адаптер может задать вопрос пользователю
- **Runtime services:** отчёт о запущенных сервисах (dev servers, preview URLs)
- **Session management:** `sessionId` + `sessionParams` + `sessionDisplayId`

**Оркестрационная значимость:** AdapterExecutionContext / AdapterExecutionResult — развитый контракт для runner interface. Ключевые элементы: error classification (errorFamily), cost tracking в result, HITL (question), runtime services.

---

### 3.10 🟡 Security: Secrets Management, Execution Environments, Agent Permissions

Paperclip AI реализует три уровня безопасности:

**Secrets Management** (`server/src/secrets/`):
- Encrypted storage секретов (API keys, tokens) с provider registry — каждый секрет шифруется перед сохранением в БД и расшифровывается на уровне adapter execution
- Секреты резолвятся по имени через provider registry — агент получает только секреты своего adapter config
- Plugin SDK имеет доступ к секретам через `ctx.secrets.resolve(...)` — плагины не могут перечислить чужие секреты, но могут обратиться к своим по имени
- Export/import организаций включает scrubbing секретов (замена на placeholders)

**Ограничения:**
- Нет интеграции с внешними secret managers (Vault, AWS Secrets Manager) — секреты хранятся только в БД Paperclip
- Нет automatic rotation: обновление API key требует ручного edit через API/UI
- Нет audit log доступа к секретам (кто и когда резолвил конкретный секрет)

**Execution Environments** (`server/src/services/environments.ts`):
- Конфигурируемые окружения: local, remote (SSH), Docker (через E2B sandbox plugin)
- Каждый agent привязан к default environment — adapter execution направляется в указанное окружение
- Execution workspaces (`server/src/services/execution-workspaces/`) — изоляция через git worktree: каждый run получает отдельный worktree, предотвращая конфликты между параллельными агентами
- Runtime services: dev servers, preview URLs с auto-cleanup при завершении run

**Ограничения:**
- Git worktree обеспечивает изоляцию filesystem, но не process/network — агент может влиять на процессы другого агента в том же хосте
- Docker-изоляция доступна через E2B plugin, но не встроена в core — требует отдельной настройки и внешнего сервиса
- Нет resource limits (CPU, memory) для local-окружений — агент может исчерпать ресурсы хоста

**Agent Permissions**:
- Pause/resume/terminate агентов — coarse-grained административный контроль (binary: agent active/paused/terminated)
- Budget hard stops + auto-pause — автоматическое ограничение при превышении бюджета (глава 3.3)
- Execution policy (multi-stage approval) — governance-level контроль: кто должен утвердить выполнение (глава 3.7)
- Activity audit — логирование действий через DB (activity log + run events)

**Ограничения:**
- Нет fine-grained RBAC — нет ролей с различными уровнями доступа (readonly, operator, admin)
- Нет access control list для ресурсов — любой agent может читать любой issue в своей компании
- Нет ограничений на выполняемые команды — адаптер (CLI-agent) имеет полный доступ к shell; нет whitelist/blacklist команд
- Audit log хранится в БД без tamper protection — нет cryptographic chaining или external log shipping

**Модель угроз (покрытие):**
| Угроза | Покрытие |
|--------|----------|
| Компрометация API key в storage | ✅ Encrypted at rest |
| Компрометация API key в transit | ⚠️ Зависит от HTTPS/TLS настройки хоста |
| Агент выходит из-под контроля (бесконечный loop) | ✅ Budget hard stop + run liveness detection |
| Агент получает доступ к чужим секретам | ⚠️ Provider registry, но нет ACL |
| Агент выполняет destructive команды (rm -rf) | ❌ Нет command filtering |
| Параллельные агенты конфликтуют в filesystem | ✅ Git worktree isolation |
| Параллельные агенты конфликтуют в process namespace | ❌ Нет process isolation |
| Insider threat (администратор компании) | ⚠️ Audit log, но нет tamper protection |

---

## 4. Прочие возможности (вне оркестрации)

### 4.1 🟢 Org Chart / Иерархия агентов

Paperclip моделирует компанию с CEO, CTO, инженерами, дизайнерами. Это valuable для управления «zero-human companies», но task-orchestrator — CLI-утилита для chain-оркестрации, а не платформа управления компанией.

### 4.2 🟢 React UI Dashboard

Paperclip — full-stack приложение с React dashboard. Task-orchestrator — CLI-утилита. Разные парадигмы.

### 4.3 🟢 Multi-Company Isolation

Paperclip поддерживает множество компаний на одной инстанции с complete data isolation. Для open-source CLI-утилиты это избыточно.

### 4.4 🟢 Company Portability (Export/Import)

Экспорт/импорт организаций — valuable для Paperclip как платформы, но не нужен для CLI chain-оркестратора.

### 4.5 🟢 Ticket System (Issues)

Paperclip реализует полноценную ticket system (backlog → todo → in_progress → in_review → done). Task-orchestrator не нуждается в системе управления задачами — задачи задаются через YAML chains.

### 4.6 🟢 Heartbeat / Scheduling (Cron, Webhook)

Paperclip wakes агентов по расписанию (heartbeat). Task-orchestrator запускается по команде. Для CLI-утилиты cron scheduling — не приоритет.

### 4.7 🟢 Embedded PostgreSQL

Paperclip использует embedded PGlite для dev и полноценный PostgreSQL для production.

---

## 5. Сводка по оркестрации

| Возможность | Статус в продукте | Описание |
| --- | --- | --- |
| Run liveness / stuck detection | 🟡 P2 | Evidence-based + regex — защита от зацикливания в fix_iterations |
| Error classification + escalation strategy | 🟡 P2 | Transient failure retry с bounded backoff + strategy escalation |
| Adapter execution context (rich result) | 🟡 P2 | Error family, cost, HITL, runtime services в контракте runner'а |
| Goal alignment в промптах | 🟡 P3 | Улучшение качества решений через контекст цели |
| Session compaction policy | 🟡 P3 | Для session-based runner'ов (Claude Code, Codex) |
| Config revisions / rollback | 🟡 P3 | Механизм отката chain YAML при ошибках |
| Multi-stage execution policy | 🟡 P3 | Human approval gates — дополнение к автоматическим quality gates |
| Scoped budget policies | 🟡 P3 | Budget на уровне agent/project, а не только chain |
| Plugin system | 🟡 P3 | Расширение без изменения core (custom runners, gates, events) |
| Org chart / UI dashboard | 🟢 — | Разная парадигма |
| Ticket system | 🟢 — | YAML chains заменяют |
| Multi-company isolation | 🟢 — | Избыточно для CLI |
| Heartbeat scheduling | 🟢 — | CLI запускается по команде |
| Company portability | 🟢 — | Не актуально |

---

## 6. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории Paperclip AI:

- [`server/src/services/heartbeat.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/heartbeat.ts) — Core execution engine: wakeup queue, budget enforcement, adapter invocation, run lifecycle, transient retry, session compaction
- [`server/src/services/run-liveness.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/run-liveness.ts) — Run liveness classification: regex patterns, evidence-based detection, actionability assessment
- [`server/src/services/recovery/service.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/recovery/service.ts) — Recovery: stranded issue recovery, stale active run evaluation, auto-wakeup
- [`server/src/services/budgets.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/budgets.ts) — Budget policies: scoped enforcement (company/agent/project), warning + hard stop, auto-pause, incidents
- [`server/src/services/costs.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/costs.ts) — Cost tracking: per-agent/company, billing types, monthly spend
- [`server/src/services/agents.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/agents.ts) — Agent CRUD, config revisions, rollback
- [`server/src/services/issues.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/issues.ts) — Issue/task lifecycle, atomic checkout, blocker dependencies
- [`server/src/services/approvals.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/approvals.ts) — Governance: approval workflows, decision tracking
- [`server/src/services/issue-execution-policy.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/issue-execution-policy.ts) — Multi-stage execution policy с approval gates
- [`server/src/services/routines.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/routines.ts) — Scheduled routines: cron, webhook, catch-up policies
- [`server/src/services/agent-instructions.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/services/agent-instructions.ts) — AGENTS.md prompt bundle management
- [`server/src/adapters/process/execute.ts`](https://github.com/paperclipai/paperclip/blob/master/server/src/adapters/process/execute.ts) — Process adapter: subprocess execution
- [`packages/adapter-utils/src/types.ts`](https://github.com/paperclipai/paperclip/blob/master/packages/adapter-utils/src/types.ts) — Adapter interface contracts
- [`packages/adapter-utils/src/session-compaction.ts`](https://github.com/paperclipai/paperclip/blob/master/packages/adapter-utils/src/session-compaction.ts) — Session compaction policy
- [`packages/plugins/sdk/src/define-plugin.ts`](https://github.com/paperclipai/paperclip/blob/master/packages/plugins/sdk/src/define-plugin.ts) — Plugin SDK: definePlugin, context API
- [`packages/adapters/claude-local/src/server/execute.ts`](https://github.com/paperclipai/paperclip/blob/master/packages/adapters/claude-local/src/server/execute.ts) — Claude Code adapter: subprocess invocation, skill sync, session management
- [`README.md`](https://github.com/paperclipai/paperclip/blob/master/README.md) — Documentation: features, architecture, quickstart
- [`AGENTS.md`](https://github.com/paperclipai/paperclip/blob/master/AGENTS.md) — Developer guide: architecture, engineering rules, DB workflow

---

📚 **Источники:**
1. [github.com/paperclipai/paperclip](https://github.com/paperclipai/paperclip) — репозиторий проекта
2. [paperclip.ing/docs](https://paperclip.ing/docs) — документация Paperclip
3. [github.com/paperclipai/paperclip/blob/master/AGENTS.md](https://github.com/paperclipai/paperclip/blob/master/AGENTS.md) — AI-agent guide для контрибьюторов
4. [github.com/paperclipai/paperclip/blob/master/ROADMAP.md](https://github.com/paperclipai/paperclip/blob/master/ROADMAP.md) — Roadmap проекта
5. [awesome-paperclip](https://github.com/gsxdsm/awesome-paperclip) — Community plugins и resources
