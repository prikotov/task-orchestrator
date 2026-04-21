# Исследование: MetaGPT и OpenClaw — SOP/ролевая модель и multi-channel AI-ассистент

> **Проекты:**
> - [github.com/FoundationAgents/MetaGPT](https://github.com/FoundationAgents/MetaGPT) (★ 52k, MIT)
> - [github.com/openclaw/openclaw](https://github.com/openclaw/openclaw) (MIT)
>
> **Дата анализа:** 2026-04-21
> **Язык:** Python (MetaGPT), TypeScript / Node.js (OpenClaw)
> **Аналитик:** Технический писатель (Гермиона)

---

## 1. Обзор проектов

### 1.1 MetaGPT

MetaGPT — multi-agent фреймворк, моделирующий работу софтверной компании. Ключевая идея: **`Code = SOP(Team)`** — стандартные операционные процедуры (SOP), применённые к команде LLM-агентов с фиксированными ролями (ProductManager, Architect, Engineer, QaEngineer, ProjectManager). На вход — одно предложение-требование, на выходе — PRD, system design, код, тесты.

MetaGPT работает на уровне прямых LLM API. Каждый агент — это Python-класс с ролями, actions, memory и message-passing через общий Environment. Поддерживает два режима: **Fixed SOP** (жёсткий порядок действий) и **RoleZero** (динамический react-loop с tool calling).

**Архитектура:**

```
metagpt/
  roles/                         Роли-агенты
    role.py                      Role: базовый класс (think → act, watch, react_mode)
    product_manager.py           ProductManager: PrepareDocuments → WritePRD
    architect.py                 Architect: WriteDesign
    engineer.py                  Engineer: WriteCode → WriteCodeReview
    qa_engineer.py               QaEngineer: WriteTest
    project_manager.py           ProjectManager: WriteTasks
    di/
      role_zero.py               RoleZero: универсальный react-loop агент
      team_leader.py             TeamLeader: планирование и координация
      data_analyst.py            DataAnalyst: анализ данных
      engineer2.py               Engineer2: кодинг через RoleZero

  actions/                       Actions — атомарные единицы работы
    action.py                    Action: base class (_aask → LLM call)
    write_prd.py                 WritePRD
    design_api.py                WriteDesign
    write_code.py                WriteCode
    write_code_review.py         WriteCodeReview
    write_test.py                WriteTest
    action_node.py               ActionNode: structured output через Pydantic

  environment/                   Environment — шина сообщений между ролями
    base_env.py                  Environment: publish_message, add_roles, run
    software/                    SoftwareEnv: SOP для software development

  schema.py                      Message, Task, Plan, TaskResult
  team.py                        Team: hire + invest + run (n_round)
  memory/                        Memory: storage + index по cause_by
  strategy/                      Planner: plan-based execution (Plan → Task)
  provider/                      LLM provider abstraction
  skills/                        Pre-built skills (Writer, Summarize)
  tools/                         Tool registry + built-in tools
  rag/                           RAG: retrievers, rankers, parsers
  utils/
    cost_manager.py              CostManager: token cost tracking + max_budget
```

**Ключевые характеристики:**

| Характеристика | Значение |
|---|---|
| **Тип** | Multi-agent SOP-фреймворк (role-based) |
| **Модель выполнения** | Fixed SOP (BY_ORDER) / Dynamic react-loop (REACT / PLAN_AND_ACT) |
| **State management** | In-memory Memory (message storage + index) |
| **Провайдеры** | OpenAI, Anthropic, Azure, Ollama, ZhipuAI и др. |
| **Расширяемость** | Custom roles, actions, tools, skills, RAG |
| **Ролевая модель** | ProductManager, Architect, Engineer, QaEngineer, ProjectManager, TeamLeader |
| **Координация** | Environment (message bus) + watch/cause_by routing |
| **Бюджетный контроль** | CostManager: max_budget → NoMoneyException |
| **Лицензия** | MIT |

---

### 1.2 OpenClaw

OpenClaw — personal AI assistant с multi-channel gateway, написанный на TypeScript (Node.js). Работает как локальный демон (gateway), подключается к 20+ мессенджерам (WhatsApp, Telegram, Slack, Discord, iMessage, Signal и др.). Не является multi-agent фреймворком оркестрации — это **single-agent multi-channel** платформа с развитой инфраструктурой: model failover, context engine, sandboxing, plugin system, skills.

OpenClaw использует встроенный Pi-агент (встроенный coding agent) для выполнения tool calls. Агент работает через react-loop (LLM → tool call → observation → LLM → ...).

**Архитектура:**

```
src/
  agents/                        Агентский runtime (~800 файлов)
    agent-scope.ts               Multi-agent routing: agentId → config → model → sandbox
    agent-command.ts             CLI agent command
    acp-spawn.ts                 ACP sub-agent spawning
    model-fallback.ts            Model failover: primary → fallbacks с cooldown
    failover-error.ts            Structured error: FailoverError (reason, provider, model, status)
    failover-policy.ts           Failover policy: rate_limit, overloaded, billing, timeout, auth
    bootstrap-*.ts               Bootstrap: injection budget, hooks, files, prompt
    context.ts                   Context window: resolve, cache, guard
    context-window-guard.ts      Context window guard: warn/block below threshold
    bash-tools.ts                Bash tool: exec, process, approval
    skills/                      Skills: discovery, filter, frontmatter, bundled, plugin
    auth-profiles/               Auth profiles: rotation, cooldown, store
    sandbox/                     Sandbox: Docker, SSH, OpenShell backends
    subagent-*.ts                Sub-agent: registry, depth, capabilities

  context-engine/                Pluggable context management
    types.ts                     ContextEngine interface: ingest, assemble, compact, maintain
    legacy.ts                    LegacyContextEngine: wraps existing compaction
    delegate.ts                  Delegates compaction to Pi runtime
    registry.ts                  Context engine factory registry

  gateway/                       Gateway: HTTP/WS server, auth, protocol
  channels/                      Channel integrations (20+ messengers)
  routing/                       Session routing: sessionKey → agentId → binding
  sessions/                      Session management: lifecycle, transcript, model overrides
  security/                      Security audit: DM policy, tool policy, sandbox, deep probes
  plugins/                       Plugin loader/registry
  flows/                         Setup flows: channel setup, provider flow, search setup
  skills/                        (root) Bundled skills (53 skills)
  mcp/                           MCP integration (mcporter)
  hooks/                         Internal hooks (pre/post bootstrap, etc.)
  tasks/                         Detached task runtime
  cron/                          Cron jobs
  memory-host-sdk/               Memory plugin host SDK

extensions/                      Bundled provider plugins (40+)
  anthropic/, openai/, codex/, amazon-bedrock/, ...
```

**Ключевые характеристики:**

| Характеристика | Значение |
|---|---|
| **Тип** | Personal AI assistant (single-agent, multi-channel) |
| **Модель выполнения** | Agent loop (LLM → tool call → observation → LLM → ...) |
| **State management** | File-backed session transcripts + context engine |
| **Провайдеры** | 40+ провайдеров через plugin extensions |
| **Расширяемость** | Plugin SDK, Skills (SKILL.md), MCP, Context Engine |
| **Каналы** | 20+ мессенджеров (WhatsApp, Telegram, Slack, Discord, iMessage и т.д.) |
| **Model failover** | Primary + fallbacks с per-profile cooldown |
| **Context engine** | Pluggable: ingest → assemble → compact → maintain |
| **Sandbox** | Docker, SSH, OpenShell backends |
| **Лицензия** | MIT |

---

## 2. Сравнительная таблица: MetaGPT, OpenClaw vs. task-orchestrator

| Функция | Task Orchestrator | MetaGPT | OpenClaw |
|---|---|---|---|
| **Язык** | PHP 8.4 | Python | TypeScript (Node.js) |
| **Тип** | Chain-based orchestrator | Multi-agent SOP-фреймворк | Single-agent multi-channel assistant |
| **Модель оркестрации** | YAML chains (sequential/dynamic) | SOP (BY_ORDER) / React-loop / Plan-and-Act | Agent loop + tool calls |
| **State management** | In-memory + JSONL audit | In-memory Memory (index by cause_by) | File-backed transcripts + context engine |
| **Error handling** | Retry + Circuit Breaker + Fallback | ❌ Нет встроенного retry | ✅ Model failover с cooldown + per-profile rotation |
| **Quality Gates** | ✅ Shell-команды | ❌ Нет | ❌ Нет |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based) | ⚠️ CostManager: tracking + max_budget → NoMoneyException | ⚠️ Bootstrap budget (context window chars), token tracking |
| **Итерационные циклы** | ✅ fix_iterations (max_iterations) | ⚠️ n_round loop в Team.run(), max_react_loop в RoleZero | ❌ Нет явных iteration loops |
| **Fallback routing** | ✅ Per-step fallback runner | ❌ Нет | ✅ Model failover: primary → fallback chain |
| **Circuit Breaker** | ✅ 3-state (closed/open/half-open) | ❌ Нет | ⚠️ Cooldown per auth profile (similar concept) |
| **Audit Trail** | ✅ JSONL | ⚠️ Memory storage (in-memory) | ✅ Session transcripts (file-backed) |
| **Ролевые промпты** | ✅ .md файлы (18+ ролей) | ✅ Роли: ProductManager, Architect, Engineer и т.д. | ⚠️ Multi-agent routing (agentId → config), без SOP |
| **Multiple runners** | ✅ Pi + Codex (через interface) | ✅ Multi-provider LLM abstraction | ✅ 40+ провайдеров через plugin extensions |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❌ Плоская структура (roles/actions/environment) | ❌ Плоская структура (src/agents/, src/gateway/...) |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Прямой вызов | ❌ Прямой вызов |
| **Message passing** | ❌ Нет (chain = linear steps) | ✅ Environment + Message + cause_by/watch | ❌ Нет (single agent) |
| **SOP (Standard Operating Procedures)** | ❌ Нет (YAML chains — похожая идея) | ✅ Core concept: BY_ORDER + watch/cause_by | ❌ Нет |
| **Sub-agent spawning** | ❌ Нет | ✅ Hierarchical: TeamLeader → Task delegation | ✅ ACP spawn + subagent registry (max depth/children) |
| **Context engine** | ❌ Нет | ⚠️ Memory (in-memory storage + index) | ✅ Pluggable ContextEngine (ingest/assemble/compact/maintain) |
| **Sandbox** | ❌ Нет | ❌ Нет | ✅ Docker, SSH, OpenShell backends |
| **Plugin system** | ❌ Нет | ⚠️ Tool registry + Skills | ✅ Plugin SDK + extensions (40+ providers) |
| **MCP support** | ❌ Нет | ❌ Нет | ✅ mcporter integration |
| **Multi-channel** | ❌ Нет (CLI) | ❌ Нет (CLI) | ✅ 20+ messengers |
| **Memory / RAG** | ❌ Нет | ✅ Memory + RAG (retrievers, rankers) | ✅ Memory plugin slot + context engine |
| **Security** | ❌ Нет | ❌ Нет | ✅ DM policy, tool policy, sandbox, deep probes, audit |
| **Статус проекта** | Активный | Активный, commercial (MGX) | Активный, спонсируемый (OpenAI, GitHub, NVIDIA) |

---

## 3. Что полезно взять и почему

### 3.1 🟡 SOP / Fixed Action Sequence (MetaGPT)

**Что у них:** MetaGPT реализует **Standard Operating Procedures** через `RoleReactMode.BY_ORDER`: роль имеет список actions (`set_actions([PrepareDocuments, WritePRD])`) и выполняет их последовательно. Роль «наблюдает» (`_watch`) за определёнными типами сообщений и активируется при их получении.

```python
class ProductManager(RoleZero):
    def __init__(self, **kwargs):
        super().__init__(**kwargs)
        if self.use_fixed_sop:
            self.set_actions([PrepareDocuments, WritePRD])
            self._watch([UserRequirement, PrepareDocuments])
            self.rc.react_mode = RoleReactMode.BY_ORDER
```

**Почему нам интересно:** Наш подход YAML chains — это уже форма SOP (фиксированная последовательность шагов). Однако MetaGPT формализует связь «watch → act» через типы сообщений (`cause_by`), а не через позицию в цепочке. Это позволяет строить **event-driven chains**, где шаг активируется не по порядку, а по типу полученного результата.

**Отличие от нашей реализации:**
- У нас: шаг в chain активируется по порядку (step 1 → step 2 → ...)
- У них: роль активируется по типу сообщения (`cause_by=WritePRD` → Architect запускается)

---

### 3.2 🟡 Message-based Coordination (MetaGPT)

**Что у них:** Роли обмениваются через общий `Environment`:

```python
class Environment(ExtEnv):
    roles: dict[str, BaseRole]
    history: Memory

    def publish_message(self, message: Message): ...
    def add_roles(self, roles: list[BaseRole]): ...
    async def run(self): ...
```

Сообщение содержит `cause_by` (какое action создало) и `send_to` (кому адресовано). Роли подписываются на `cause_by` через `_watch`. Это автоматическая маршрутизация: Architect «смотрит» за `WritePRD` и автоматически получает PRD для обработки.

**Почему нам интересно:** Для будущих dynamic chains — когда цепочка может ветвиться или когда несколько шагов могут выполняться параллельно — message-passing модель удобнее линейных YAML chains. Сейчас это R&D, но паттерн值得изучения.

---

### 3.3 🟡 Model Failover с Cooldown (OpenClaw)

**Что у них:** OpenClaw реализует структурированный model failover:

```typescript
// Основной цикл: primary model → fallback chain
// При ошибке — классификация: rate_limit / overloaded / billing / auth / timeout
// Per-profile cooldown с auto-expiry
// FallbackSummaryError с деталями по каждой попытке
```

`FailoverError` содержит: `reason` (rate_limit, overloaded, billing, auth, timeout, ...), `provider`, `model`, `profileId`, `status`. Политика cooldown: rate_limit и overloaded → transient cooldown probe; auth_permanent → preserve slot.

**Почему нам интересно:** Наш `CircuitBreakerAgentRunner` — это per-step circuit breaker. OpenClaw показывает более гранулярную модель: **per-profile** cooldown с классификацией ошибок. Это похоже на наш retry + circuit breaker, но с явной categorization error reasons и separate cooldown per auth profile.

**Отличие от нашей реализации:**
- У нас: circuit breaker на уровне runner (все запросы через один breaker)
- У них: failover на уровне model profile (primary → fallback1 → fallback2) с per-profile cooldown

---

### 3.4 🟡 Pluggable Context Engine (OpenClaw)

**Что у них:** OpenClaw определяет интерфейс `ContextEngine` с полным lifecycle:

```typescript
interface ContextEngine {
  readonly info: ContextEngineInfo;
  bootstrap?(params): Promise<BootstrapResult>;       // Init engine for session
  ingest(params): Promise<IngestResult>;               // Add message to engine
  assemble(params): Promise<AssembleResult>;           // Build context for LLM call
  compact(params): Promise<CompactResult>;             // Compress context
  afterTurn?(params): Promise<void>;                    // Post-turn lifecycle
  maintain?(params): Promise<ContextEngineMaintenanceResult>; // Transcript maintenance
  prepareSubagentSpawn?(params): Promise<...>;         // Sub-agent context prep
  onSubagentEnded?(params): Promise<void>;             // Sub-agent cleanup
  dispose?(): Promise<void>;                           // Cleanup resources
}
```

**Почему нам интересно:** Для длинных цепочек (implement → review → fix → review → ...) context management критичен. `assemble()` с `tokenBudget` — это именно то, чего нам не хватает: собрать контекст так, чтобы уложиться в бюджет. `compact()` — auto-summarization при переполнении.

---

### 3.5 🟡 Sub-agent Spawning с Limits (OpenClaw)

**Что у них:** OpenClaw реализует sub-agent spawning с жёсткими лимитами:

```typescript
DEFAULT_AGENT_MAX_CONCURRENT = 4;
DEFAULT_SUBAGENT_MAX_CONCURRENT = 8;
DEFAULT_SUBAGENT_MAX_CHILDREN_PER_AGENT = 5;
DEFAULT_SUBAGENT_MAX_SPAWN_DEPTH = 1;  // depth-1 = leaves by default
```

Sub-agent может работать в sandbox (Docker), с собственным контекстом и session key. Регистрация sub-agent через `subagent-registry.ts`.

**Почему нам интересно:** Если task-orchestrator будет поддерживать параллельное выполнение шагов (например, review и test одновременно) — нужна модель sub-agent с лимитами. OpenClaw показывает простую но эффективную модель: max depth + max children + max concurrent.

---

### 3.6 🟡 Error Classification для Failover (OpenClaw)

**Что у них:** OpenClaw классифицирует ошибки по `FailoverReason`:

| Reason | HTTP Status | Поведение |
|---|---|---|
| `rate_limit` | 429 | Transient cooldown probe |
| `overloaded` | 503 | Transient cooldown probe |
| `billing` | 402 | Cooldown probe |
| `auth` | 401 | Preserve cooldown slot |
| `auth_permanent` | 403 | No retry, preserve slot |
| `timeout` | 408 | Transient cooldown probe |
| `model_not_found` | 404 | Preserve slot |
| `format` | 400 | Preserve slot |

**Почему нам интересно:** Это перекликается с Archon error classification (FATAL/TRANSIENT/UNKNOWN). Классификация ошибок позволяет умный retry: не тратить попытки на неисправимые ошибки (403, 404) и делать cooldown на временные (429, 503).

---

### 3.7 🟡 Team Composition + Budget (MetaGPT)

**Что у них:** MetaGPT `Team` собирает роли и задаёт бюджет:

```python
class Team:
    env: Environment
    investment: float = 10.0

    def hire(self, roles: list[Role]):
        self.env.add_roles(roles)

    def invest(self, investment: float):
        self.investment = investment
        self.cost_manager.max_budget = investment

    async def run(self, n_round=3, idea="", ...):
        while n_round > 0:
            if self.env.is_idle: break
            n_round -= 1
            self._check_balance()  # → NoMoneyException if over budget
            await self.env.run()
```

**Почему нам интересно:** Это почти буквально наша модель: chain (Team) + budget (investment) + max_iterations (n_round) + idle detection. Подтверждает, что наш подход к оркестрации — правильный. Отличие: у MetaGPT бюджет на уровне команды (все роли делят один бюджет), у нас — на уровне chain (все шаги делят один budget).

---

### 3.8 🟡 Bootstrap Budget для Context Injection (OpenClaw)

**Что у них:** OpenClaw управляет «bootstrap budget» — сколько символов/токенов можно потратить на injection контекстных файлов (AGENTS.md, skills, system prompts) в начало сессии:

```typescript
DEFAULT_BOOTSTRAP_NEAR_LIMIT_RATIO = 0.85;  // Предупреждение при 85% бюджета
// Per-file limit + total limit
// Truncation report: truncatedFiles, nearLimitFiles, totalNearLimit
```

**Почему нам интересно:** У нас нет ограничения на размер role .md файлов и context injection. Если роль содержит огромный промпт — он весь уйдёт в context window, оставляя меньше места для полезной работы. Bootstrap budget — это превентивная защита.

---

## 4. Что НЕ берём и почему

### 4.1 🟢 Python как язык (MetaGPT)

MetaGPT — Python-фреймворк. Task-orchestrator — PHP/Symfony. Нельзя использовать как dependency. Только заимствование паттернов.

### 4.2 🟢 TypeScript / Node.js как язык (OpenClaw)

OpenClaw — TypeScript-проект. Та же проблема: другой стек. Только паттерны.

### 4.3 🟢 Multi-channel Messaging (OpenClaw)

OpenClaw — это multi-channel AI assistant с 20+ мессенджерами. Task-orchestrator — CLI-утилита для chain-оркестрации. Абсолютно разные целевые сценарии.

### 4.4 🟢 SOP-ролевая модель как единственный способ координации (MetaGPT)

MetaGPT жёстко привязывает роли к конкретным actions (ProductManager → WritePRD, Architect → WriteDesign). Это ограничивает гибкость: мы хотим, чтобы любая роль могла выполнять любой шаг. Наша модель (role .md + chain YAML) гибче.

### 4.5 🟢 LLM-level Integration (оба проекта)

MetaGPT и OpenClaw работают на уровне прямых LLM API (provider abstraction). Наш оркестратор работает на уровне runner'ов (pi, codex), которые сами управляют LLM-взаимодействием. Разный уровень абстракции.

### 4.6 🟢 Desktop / Mobile Companion Apps (OpenClaw)

OpenClaw имеет macOS menu bar app, iOS/Android nodes, Canvas. Не актуально для CLI-first Symfony bundle.

### 4.7 🟢 Gateway Daemon (OpenClaw)

OpenClaw запускается как persistent daemon (launchd/systemd). Task-orchestrator запускается, выполняет цепочку, завершается. Разные модели работы.

### 4.8 🟢 MGX / Commercial Platform (MetaGPT)

MetaGPT имеет коммерческую платформу [mgx.dev](https://mgx.dev) — SaaS для AI agent development. Не относится к нашему open-source bundle.

---

## 5. Сводка рекомендаций

| Фича | Источник | Приоритет | Обоснование |
|---|---|---|---|
| Chain orchestration (static + dynamic) | — | ✅ Уже есть | Core-функциональность task-orchestrator |
| Retry + Circuit Breaker | — | ✅ Уже есть | Устойчивость при сбоях |
| Quality Gates | — | ✅ Уже есть | Автоматическая проверка кода |
| Budget control | — | ✅ Уже есть | Предотвращение runaway spending |
| Fix iterations | — | ✅ Уже есть | Closed-loop цикл разработки |
| JSONL Audit Trail | — | ✅ Уже есть | Воспроизводимость и отладка |
| Team/chain budget model | MetaGPT | ✅ Паритет | Подтверждает наш подход (invest + max_budget + n_round) |
| SOP: event-driven step activation | MetaGPT | 🟡 P3 | watch/cause_by routing для dynamic chains |
| Model failover с cooldown | OpenClaw | 🟡 P2 | Per-profile fallback с error classification |
| Error classification | OpenClaw | 🟡 P2 | Умный retry: не тратить попытки на неисправимые ошибки |
| Pluggable context engine | OpenClaw | 🟡 P3 | assemble() с tokenBudget, compact() при overflow |
| Sub-agent spawning с limits | OpenClaw | 🟡 P3 | Для параллельного выполнения шагов |
| Bootstrap budget для context injection | OpenClaw | 🟡 P3 | Защита от oversized промптов |
| Message-based coordination | MetaGPT | 🟡 P3 | Для будущих multi-agent dynamic chains |
| Python/TypeScript dependency | Оба | 🟢 — | Разный стек |
| Multi-channel messaging | OpenClaw | 🟢 — | Разная парадигма |
| SOP-ролевая модель (жёсткая) | MetaGPT | 🟢 — | Ограничивает гибкость |
| LLM-level integration | Оба | 🟢 — | Разный уровень абстракции |
| Gateway daemon | OpenClaw | 🟢 — | Разная модель работы |
| Desktop/Mobile apps | OpenClaw | 🟢 — | Не актуально для CLI bundle |
| MGX commercial platform | MetaGPT | 🟢 — | Не относится к open-source |

---

## 6. Указатель источников для деталей

### MetaGPT

- [`metagpt/roles/role.py`](https://github.com/FoundationAgents/MetaGPT/blob/main/metagpt/roles/role.py) — Role: базовый класс агента (think → act, watch, react_mode, RoleContext)
- [`metagpt/roles/product_manager.py`](https://github.com/FoundationAgents/MetaGPT/blob/main/metagpt/roles/product_manager.py) — ProductManager: Fixed SOP (PrepareDocuments → WritePRD)
- [`metagpt/roles/architect.py`](https://github.com/FoundationAgents/MetaGPT/blob/main/metagpt/roles/architect.py) — Architect: WriteDesign, _watch(WritePRD)
- [`metagpt/roles/di/role_zero.py`](https://github.com/FoundationAgents/MetaGPT/blob/main/metagpt/roles/di/role_zero.py) — RoleZero: универсальный react-loop агент (tools, editor, browser)
- [`metagpt/actions/action.py`](https://github.com/FoundationAgents/MetaGPT/blob/main/metagpt/actions/action.py) — Action: base class (_aask → LLM call, ActionNode)
- [`metagpt/environment/base_env.py`](https://github.com/FoundationAgents/MetaGPT/blob/main/metagpt/environment/base_env.py) — Environment: publish_message, add_roles, run
- [`metagpt/team.py`](https://github.com/FoundationAgents/MetaGPT/blob/main/metagpt/team.py) — Team: hire + invest + run (n_round, budget check)
- [`metagpt/schema.py`](https://github.com/FoundationAgents/MetaGPT/blob/main/metagpt/schema.py) — Message, Task, Plan, TaskResult
- [`metagpt/memory/memory.py`](https://github.com/FoundationAgents/MetaGPT/blob/main/metagpt/memory/memory.py) — Memory: storage + index (get_by_actions, get_by_role)
- [`metagpt/strategy/planner.py`](https://github.com/FoundationAgents/MetaGPT/blob/main/metagpt/strategy/planner.py) — Planner: plan-based execution (goal → tasks → current_task)
- [`metagpt/utils/cost_manager.py`](https://github.com/FoundationAgents/MetaGPT/blob/main/metagpt/utils/cost_manager.py) — CostManager: token cost tracking + max_budget
- [`metagpt/software_company.py`](https://github.com/FoundationAgents/MetaGPT/blob/main/metagpt/software_company.py) — CLI entry: Team.hire([TeamLeader, ProductManager, Architect, Engineer2, DataAnalyst])
- [README.md](https://github.com/FoundationAgents/MetaGPT/blob/main/README.md) — Overview, philosophy `Code = SOP(Team)`, installation

### OpenClaw

- [`src/agents/agent-scope.ts`](https://github.com/openclaw/openclaw/blob/main/src/agents/agent-scope.ts) — Multi-agent routing: resolveSessionAgentId, agent config, model resolution
- [`src/agents/model-fallback.ts`](https://github.com/openclaw/openclaw/blob/main/src/agents/model-fallback.ts) — Model failover: primary → fallback chain, FallbackSummaryError, per-profile cooldown
- [`src/agents/failover-error.ts`](https://github.com/openclaw/openclaw/blob/main/src/agents/failover-error.ts) — FailoverError: structured error classification (rate_limit, auth, billing, timeout...)
- [`src/agents/failover-policy.ts`](https://github.com/openclaw/openclaw/blob/main/src/agents/failover-policy.ts) — Failover policy: transient vs preserve cooldown slots
- [`src/agents/context-window-guard.ts`](https://github.com/openclaw/openclaw/blob/main/src/agents/context-window-guard.ts) — Context window resolution: model → config → default, guard warn/block
- [`src/agents/bootstrap-budget.ts`](https://github.com/openclaw/openclaw/blob/main/src/agents/bootstrap-budget.ts) — Bootstrap budget: per-file limit, total limit, truncation reporting
- [`src/agents/acp-spawn.ts`](https://github.com/openclaw/openclaw/blob/main/src/agents/acp-spawn.ts) — ACP sub-agent spawning with sandbox policies
- [`src/config/agent-limits.ts`](https://github.com/openclaw/openclaw/blob/main/src/config/agent-limits.ts) — Agent limits: maxConcurrent, maxChildrenPerAgent, maxSpawnDepth
- [`src/context-engine/types.ts`](https://github.com/openclaw/openclaw/blob/main/src/context-engine/types.ts) — ContextEngine interface: ingest, assemble, compact, maintain, afterTurn
- [`src/context-engine/legacy.ts`](https://github.com/openclaw/openclaw/blob/main/src/context-engine/legacy.ts) — LegacyContextEngine: backward-compatible wrapper
- [`src/agents/skills/config.ts`](https://github.com/openclaw/openclaw/blob/main/src/agents/skills/config.ts) — Skills: resolveSkillConfig, eligibility, bundled allowlist
- [`src/security/`](https://github.com/openclaw/openclaw/blob/main/src/security/) — Security: audit, DM policy, tool policy, sandbox, deep probes
- [`extensions/`](https://github.com/openclaw/openclaw/blob/main/extensions/) — 40+ bundled provider plugins
- [`VISION.md`](https://github.com/openclaw/openclaw/blob/main/VISION.md) — Project vision and direction
- [`README.md`](https://github.com/openclaw/openclaw/blob/main/README.md) — Overview, channels, security model, architecture

---

📚 **Источники:**
1. [github.com/FoundationAgents/MetaGPT](https://github.com/FoundationAgents/MetaGPT) — репозиторий MetaGPT
2. [github.com/openclaw/openclaw](https://github.com/openclaw/openclaw) — репозиторий OpenClaw
3. [docs.deepwisdom.ai](https://docs.deepwisdom.ai/main/en/) — документация MetaGPT
4. [docs.openclaw.ai](https://docs.openclaw.ai) — документация OpenClaw
5. [mgx.dev](https://mgx.dev/) — коммерческая платформа MetaGPT X
