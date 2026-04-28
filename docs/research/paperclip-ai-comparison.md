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
├── server/                           # Express REST API + сервисы оркестрации
│   ├── src/
│   │   ├── adapters/                 # Адаптеры: process (CLI), HTTP
│   │   │   ├── process/execute.ts    # Process adapter — subprocess execution
│   │   │   └── http/execute.ts       # HTTP adapter — webhook/API invocation
│   │   ├── auth/                     # Аутентификация: better-auth, JWT
│   │   ├── middleware/               # Auth, error handler, logging, validation
│   │   ├── routes/                   # REST API маршруты (~30 модулей)
│   │   ├── secrets/                  # Encrypted storage, provider registry
│   │   ├── services/                 # Бизнес-логика (~70 сервисов)
│   │   │   ├── heartbeat.ts          # ★ Core execution engine: heartbeat runs
│   │   │   ├── agents.ts             # Agent CRUD, config revisions, API keys
│   │   │   ├── issues.ts             # Task/issue management, status workflow
│   │   │   ├── budgets.ts            # Budget policies, enforcement, incidents
│   │   │   ├── costs.ts              # Cost tracking, monthly spend, billing
│   │   │   ├── approvals.ts          # Governance: approval workflows
│   │   │   ├── routines.ts           # Scheduled tasks (cron, webhook, API)
│   │   │   ├── recovery/             # ★ Run recovery, liveness, auto-restart
│   │   │   ├── company-skills.ts     # Skill management, import/export
│   │   │   ├── agent-instructions.ts # AGENTS.md prompt bundle management
│   │   │   ├── environments.ts       # Execution environments (local, remote)
│   │   │   ├── execution-workspaces/ # Git worktree isolation
│   │   │   ├── run-liveness.ts       # ★ Stuck run detection (LLM output analysis)
│   │   │   ├── run-continuations.ts  # Run continuation after transient failure
│   │   │   ├── plugin-*.ts           # Plugin lifecycle, registry, workers
│   │   │   └── ...                   # +40 других сервисов
│   │   └── storage/                  # Local disk, S3 providers
│   └── scripts/
├── packages/
│   ├── db/                           # Drizzle ORM schema (~70 таблиц), migrations
│   ├── shared/                       # Types, validators, constants, telemetry
│   ├── adapter-utils/                # Shared adapter utilities, session compaction
│   ├── adapters/                     # Agent adapter implementations
│   │   ├── claude-local/             # Claude Code adapter (subprocess, skill sync)
│   │   ├── codex-local/              # OpenAI Codex adapter
│   │   ├── cursor-local/             # Cursor adapter
│   │   ├── gemini-local/             # Gemini adapter
│   │   ├── openclaw-gateway/         # OpenClaw gateway adapter
│   │   ├── opencode-local/           # OpenCode adapter
│   │   └── pi-local/                 # Pi adapter
│   ├── plugins/                      # Plugin system
│   │   ├── sdk/                      # Plugin SDK (definePlugin, context API)
│   │   ├── examples/                 # Example plugins (kitchen-sink, orchestration)
│   │   └── sandbox-providers/        # E2B sandbox provider
│   └── mcp-server/                   # MCP server integration
├── ui/                               # React + Vite dashboard UI
├── cli/                              # CLI tools (onboard, configure)
├── skills/                           # Built-in agent skills
└── tests/                            # Integration tests (Playwright)
```

### Ключевые характеристики

| Характеристика | Значение |
|---|---|
| **Тип** | Мета-оркестратор (control plane) для AI-агентных компаний |
| **Модель выполнения** | Heartbeat-based: scheduled/event-driven wakeup → adapter invocation → result |
| **State management** | Persistent (PostgreSQL / embedded PGlite), ~70 таблиц |
| **Провайдеры агентов** | Claude Code, Codex, Cursor, Gemini, OpenClaw, pi, HTTP, process |
| **Расширяемость** | Plugin SDK, MCP server, adapter interface, skill system |
| **Интерфейс** | React UI dashboard + REST API + CLI |
| **Платформы** | Node.js 20+, кроссплатформенный |

### Основные компоненты

| Компонент | Назначение |
|---|---|
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

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | TasK Orchestrator | Paperclip AI | Статус |
|---|---|---|---|
| **Цепочки шагов (chains)** | ✅ YAML chains, статические и динамические | ❌ Нет. Heartbeat-based task execution (одна задача = один run) | ✅ У нас есть |
| **Retry с backoff** | ✅ RetryingAgentRunner | ✅ Transient failure retry с bounded delays (2m → 10m → 30m → 2h) | ✅ Паритет |
| **Circuit Breaker** | ✅ CircuitBreakerAgentRunner | ❌ Нет (но есть quota windows + budget hard stops) | ✅ У нас есть |
| **Quality Gates** | ✅ Shell-команды как проверки | ❌ Нет (но есть execution policy с approval stages) | ✅ У нас есть |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based) | ✅ Scoped budget policies (company/agent/project), warning + hard stop, auto-pause | 🟡 У них шире |
| **Итерационные циклы (fix_iterations)** | ✅ Группа шагов с max_iterations | ❌ Нет (одноразовый run per heartbeat) | ✅ У нас есть |
| **Fallback routing** | ✅ Per-step fallback runner | ❌ Нет (но есть transient failure retry с escalation strategy) | ✅ У нас есть |
| **Audit Trail (JSONL)** | ✅ JsonlAuditLogger | ✅ Activity log (DB), run events, workspace operation logs | ✅ Паритет |
| **Ролевые промпты** | ✅ .md файлы (18+ ролей) | ✅ AGENTS.md bundles (managed/external), skill injection per adapter | ✅ Паритет |
| **Multiple runners** | ✅ Pi + Codex (через interface) | ✅ 7+ adapters: Claude, Codex, Cursor, Gemini, OpenClaw, pi, HTTP, process | ✅ Паритет |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❌ Плоская структура: routes → services → db schema | ✅ У нас лучше |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Прямой вызов через adapter.execute() | ✅ У нас лучше |
| **YAML-конфигурация** | ✅ Chains + roles в YAML | ✅ DB-stored config, API-driven | ✅ Разные подходы |
| **Org chart / иерархия агентов** | ❌ Нет | ✅ Роли, title, reportsTo, иерархия управления | 🟡 Интересно |
| **Goal alignment** | ❌ Нет | ✅ Company → Project → Goal → Issue, goal ancestry в контексте агента | 🟡 Интересно |
| **Ticket system (issues)** | ❌ Нет | ✅ Full issue lifecycle: backlog → todo → in_progress → in_review → done | 🟡 Позже |
| **Heartbeat / scheduling** | ❌ Нет (только CLI запуск) | ✅ DB-backed wakeup queue, cron/webhook/API triggers, coalescing | 🟡 Интересно |
| **Governance / approvals** | ❌ Нет | ✅ Multi-stage approval workflows, decision tracking, hire approvals | 🟡 Позже |
| **Budget enforcement** | ⚠️ BudgetVo (проверка перед шагом) | ✅ Scoped policies, warning thresholds, hard stops, auto-pause агентов | 🟡 У них шире |
| **Run liveness / stuck detection** | ❌ Нет | ✅ Regex-based output analysis + evidence-based liveness classification | 🟡 Интересно |
| **Run recovery** | ❌ Нет | ✅ Stranded issue recovery, stale run evaluation, auto-wakeup | 🟡 Интересно |
| **Session compaction** | ❌ Нет | ✅ Per-adapter policy: max runs/tokens/age, adapter-managed vs. threshold | 🟡 Позже |
| **Config revisions / rollback** | ❌ Нет | ✅ Agent config revisions с SHA snapshot + rollback | 🟡 Интересно |
| **Plugin system** | ❌ Нет | ✅ Full SDK: events, jobs, data, tools, state, UI contributions, DB | 🟡 Позже |
| **Multi-company isolation** | ❌ Нет | ✅ Every entity is company-scoped, complete data isolation | 🟢 Не берём |
| **React UI dashboard** | ❌ Нет (CLI only) | ✅ Full React + Vite dashboard: org chart, costs, issues, agents | 🟢 Не берём |
| **Company portability** | ❌ Нет | ✅ Export/import entire orgs, secret scrubbing, collision handling | 🟢 Не берём |
| **Execution workspaces** | ❌ Нет | ✅ Git worktree isolation, runtime services (dev servers, preview URLs) | 🟡 Позже |
| **MCP server** | ❌ Нет | ✅ MCP server package (packages/mcp-server) | 🟡 Позже |

---

## 3. Что полезно взять и почему

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

**Почему нам интересно:** Для итерационных циклов (fix_iterations) — если агент застрял (повторяет одни и те же действия, или не может продвинуться), нужно это обнаружить и остановить. Подход Paperclip через regex + evidence — более продвинутый, чем простой loop detection через SHA-256 сигнатуры (как у Crush), и не требует LLM-вызова.

**Отличие от нашей реализации:**
- У нас: нет защиты от зацикливания в fix_iterations
- У них: evidence-based liveness classification с actionable рекомендациями

---

### 3.2 🟡 Transient Failure Retry с Bounded Backoff (`server/src/services/heartbeat.ts`)

**Что у них:** Paperclip реализует retry при transient-ошибках (transient upstream) с bounded exponential backoff:

```typescript
const BOUNDED_TRANSIENT_HEARTBEAT_RETRY_DELAYS_MS = [
  2 * 60 * 1000,   // 2 минуты
  10 * 60 * 1000,  // 10 минут
  30 * 60 * 1000,  // 30 минут
  2 * 60 * 60 * 1000, // 2 часа
];
```

И escalation strategy для Codex-адаптера:
- Attempt 1: same_session (retry в той же сессии)
- Attempt 2: safer_invocation (более безопасные параметры)
- Attempt 3: fresh_session (новая сессия)
- Attempt 4+: fresh_session_safer_invocation

**Почему нам интересно:** Наш RetryingAgentRunner делает retry с backoff, но без классификации ошибок и без escalation strategy. Подход Paperclip — retry *с изменением стратегии* при каждой попытке — это более продвинутый паттерн, чем простой retry.

**Отличие от нашей реализации:**
- У нас: retry с uniform backoff на любую ошибку
- У них: error classification (transient_upstream) + bounded delays + escalation strategy

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

**Почему нам интересно:** Наш BudgetVo контролирует бюджет на уровне шага в цепочке. У Paperclip — на уровне компании/агента/проекта с monthly/lifetime окном. Это разные уровни: мы контролируем *одну цепочку*, Paperclip — *всего агента*. Но подход с scoped policies + warning thresholds + auto-pause — более гибкий.

**Отличие от нашей реализации:**
- У нас: BudgetVo на уровне шага, cost-based
- У них: BudgetPolicy на уровне company/agent/project, с warning + hard stop + auto-pause + incidents

---

### 3.4 🟡 Config Revisions / Rollback (`server/src/services/agents.ts`)

**Что у них:** Paperclip сохраняет snapshot конфигурации агента при каждом изменении:

```typescript
const CONFIG_REVISION_FIELDS = [
  "name", "role", "title", "reportsTo", "capabilities",
  "adapterType", "adapterConfig", "runtimeConfig",
  "defaultEnvironmentId", "budgetMonthlyCents", "metadata",
];
```

Каждый revision хранит JSON snapshot + метаданные (кто изменил, откуда, rollback source). Rollback — это создание нового revision из предыдущего snapshot.

**Почему нам интересно:** Для task-orchestrator: если chain YAML был изменён и цепочка начала падать — нужен механизм отката. Сейчас мы полагаемся на git history, но формализованные config revisions с API-доступом — более удобны.

**Отличие от нашей реализации:**
- У нас: git history для YAML chains
- У них: DB-stored config revisions с API-driven rollback

---

### 3.5 🟡 Session Compaction / Context Management (`packages/adapter-utils/src/session-compaction.ts`)

**Что у них:** Paperclip реализует политику сжатия сессий с per-adapter конфигурацией:

```typescript
const DEFAULT_SESSION_COMPACTION_POLICY: SessionCompactionPolicy = {
  enabled: true,
  maxSessionRuns: 200,       // максимум runs в сессии
  maxRawInputTokens: 2_000_000, // максимум токенов
  maxSessionAgeHours: 72,    // максимум возраст сессии
};
```

Адаптеры с native context management (Claude Code, Codex) получают `ADAPTER_MANAGED_SESSION_POLICY` — Paperclip не вмешивается в их context management. Остальные адаптеры получают default policy.

**Почему нам интересно:** Для длинных цепочек с многократными вызовами runner'ов — контекст может расти. Per-runner policy сompression — актуально, если task-orchestrator будет работать с session-based runner'ами (Claude Code, Codex).

**Отличие от нашей реализации:**
- У нас: нет context management (каждый вызов runner'а — новый контекст)
- У них: per-adapter session compaction policy с thresholds

---

### 3.6 🟡 Goal Alignment — Goal Ancestry в контексте агента (`server/src/services/goals.ts`, heartbeat)

**Что у них:** Paperclip создаёт иерархию целей: Company → Project → Goal → Issue. Каждый issue carry full goal ancestry — агент видит не только текущую задачу, но и:

- К какой цели относится задача
- В каком проекте
- Какова mission компании

Это передаётся в контекст heartbeat run через `PAPERCLIP_TASK_ID`, `PAPERCLIP_WAKE_REASON` и prompt injection.

**Почему нам интересно:** Для сложных цепочек (implement → review → fix → deploy) — каждый шаг должен понимать *зачем* он выполняется, а не только *что*. Goal alignment в промпте улучшает качество решений агента.

**⚠️ Ограниченная применимость для task-orchestrator:** Эффект ограничен: в task-orchestrator chain выполняется в контексте одной задачи, goal ancestry даст меньше пользы, чем в мета-оркестраторе с множеством параллельных агентов. Каждый шаг chain'а и так знает свою задачу из YAML-конфигурации. Полезность возрастёт только при переходе к multi-chain / multi-agent сценариям.

**Отличие от нашей реализации:**
- У нас: role .md промпт + payload (task description)
- У них: goal ancestry + company mission + project context в каждом run

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

**Почему нам интересно:** Это аналог наших quality gates, но на уровне governance (кто утверждает), а не технических проверок (прошли ли тесты). Комбинация: quality gates (автоматическая проверка) + execution policy (human approval) — полная модель.

**Отличие от нашей реализации:**
- У нас: shell-команды как quality gates (автоматические)
- У них: multi-stage approval с participants (human governance)

---

### 3.8 🟡 Plugin System (`packages/plugins/sdk/`)

**Что у них:** Paperclip реализует полнофункциональную plugin system:

- **definePlugin()** — factory для определения plugin
- **PluginContext API:**
  - `ctx.events.on("issue.created", ...)` — подписка на domain events
  - `ctx.jobs.register("full-sync", ...)` — регистрация job handlers
  - `ctx.data.register("sync-health", ...)` — data providers для UI
  - `ctx.tools.register(...)` —暴露 tools для агентов
  - `ctx.state.get/set(...)` — persistent state
  - `ctx.config.get(...)` — plugin config
  - `ctx.secrets.resolve(...)` — секреты
  - `ctx.http.fetch(...)` — HTTP client
  - `ctx.logger.info(...)` — logging
- **Capability-gated:** плагины объявляют необходимые capabilities, host валидирует
- **Out-of-process workers:** плагины запускаются как отдельные процессы (JSON-RPC)
- **UI contributions:** плагины могут добавлять UI-компоненты в dashboard
- **Plugin DB:** каждый плагин может иметь собственные таблицы в DB

**Почему нам интересно:** Plugin system — это mechanism для расширения task-orchestrator без изменения core. Если мы хотим позволить пользователям добавлять custom runners, quality gates, event handlers — plugin SDK — готовый паттерн.

**⚠️ Преждевременная рекомендация для CLI:** Plugin system оправдан только при появлении внешних пользователей, которым нужна кастомизация без форка. Для текущего stage проекта (CLI-утилита с внутренним использованием) — накладные расходы на plugin SDK (out-of-process workers, DB, UI contributions) не оправданы. Расширение через реализацию `AgentRunnerInterface` покрывает текущие потребности.

**Отличие от нашей реализации:**
- У нас: расширение через реализацию AgentRunnerInterface
- У них: полноценный plugin SDK с events, jobs, data, tools, state, UI

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

**Почему нам интересно:** AdapterExecutionContext / AdapterExecutionResult — более развитый контракт, чем наш AgentRunnerInterface. Особенно: error classification (errorFamily), cost tracking в result, HITL (question), runtime services.

**Отличие от нашей реализации:**
- У нас: AgentRunnerInterface с простым run() → AgentResult
- У них: AdapterExecutionContext с rich result (error classification, cost, HITL, runtime services)

---

### 3.10 🟡 Security: Secrets Management, Execution Environments, Agent Permissions

Paperclip AI реализует три уровня безопасности, которые не были разобраны в предыдущих секциях:

**Secrets Management** (`server/src/secrets/`):
- Encrypted storage секретов (API keys, tokens) с provider registry
- Секреты резолвятся на уровне adapter execution — агент получает только нужные
- Plugin SDK имеет доступ к секретам через `ctx.secrets.resolve(...)`
- Export/import организаций включает scrubbing секретов

**Execution Environments** (`server/src/services/environments.ts`):
- Конфигурируемые окружения: local, remote, Docker
- Каждый agent привязан к default environment
- Execution workspaces (`server/src/services/execution-workspaces/`) — изоляция через git worktree
- Runtime services: dev servers, preview URLs с auto-cleanup

**Agent Permissions**:
- Pause/resume/terminate агентов — административный контроль
- Budget hard stops + auto-pause — автоматическое ограничение при превышении
- Execution policy (multi-stage approval) — governance-level контроль
- Activity audit — полный лог действий для compliance

**Почему нам интересно:** Для автономного выполнения в CI/CD (наша roadmap) минимальный набор: secrets management (шифрование API keys runner'ов) + exec policy (ограничение shell-команд) + basic audit. Полноценные execution environments (Docker, git worktree) — долгосрочная перспектива.

**Отличие от нашей реализации:**
- У нас: нет secrets management (ключи в env vars), нет exec policy, нет sandboxing
- У них: encrypted secrets + execution environments + governance permissions + audit

---

## 4. Что НЕ берём и почему

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

Paperclip использует embedded PGlite для dev и полноценный PostgreSQL для production. Для task-orchestrator (in-memory + JSONL) — overhead.

---

## 5. Сводка рекомендаций

| Фича | Приоритет | Обоснование |
|---|---|---|
| Chain orchestration | ✅ Уже есть | Core-функциональность task-orchestrator |
| Retry + Circuit Breaker | ✅ Уже есть | Устойчивость при сбоях |
| Quality Gates | ✅ Уже есть | Автоматическая проверка кода |
| Budget control | ✅ Уже есть | Предотвращение runaway spending |
| Fix iterations | ✅ Уже есть | Closed-loop цикл разработки |
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
