# Multica: Project Management для Human + Agent Teams

**Дата:** 2026-05-13
**Аналитик:** Шерлок (System Analyst)
**Задача:** [TASK-research-multica](../../../todo/TASK-research-multica.todo.md)
**Объект:** [github.com/multica-ai/multica](https://github.com/multica-ai/multica) (~28k stars, TypeScript/Go), [multica.ai](https://multica.ai/)

---

## 1. Обзор проекта

### Что такое Multica

**Multica** — open-source платформа проектного управления (project management), в которой AI-агенты являются «равноправными участниками команды» (first-class teammates). Агенты появляются в assignee picker, создают issues, пишут комментарии, обновляют статусы — наравне с людьми.

**Позиционирование:** «Your next 10 hires won't be human» — превращает coding agents в реальных коллег с профилями, задачами и отслеживанием прогресса.

**Стек:**
- Frontend: Next.js 16 (App Router) + Electron desktop
- Backend: Go (Chi router, sqlc, gorilla/websocket)
- Database: PostgreSQL 17 + pgvector
- Agent Runtime: Daemon (Go), запускающий внешние CLI: Claude Code, Codex, Copilot CLI, OpenClaw, OpenCode, Hermes, Gemini, Pi, Cursor Agent, Kimi, Kiro CLI

**Лицензия:** NOASSERTION (проверить перед любым использованием)

**Ключевые метрики:** ~28k stars, 3.3k forks, 513 open issues, 106 миграций БД (зрелая schema).

### Архитектура

```
┌──────────────┐     ┌──────────────┐     ┌──────────────────┐
│   Next.js    │────>│  Go Backend  │────>│   PostgreSQL 17  │
│   Frontend   │<────│  (Chi + WS)  │<────│   (pgvector)     │
└──────────────┘     └──────┬───────┘     └──────────────────┘
                            │
                     ┌──────┴───────┐
                     │ Agent Daemon │  runs on user machine
                     └──────────────┘  (Claude Code, Codex, etc.)
```

**Ключевой принцип:** Multica **не вызывает LLM API напрямую**. Все LLM-взаимодействия делегируются внешним agent CLI, которые daemon запускает как subprocess. Server — только task scheduler + state manager + event broadcaster.

### Модель данных

28 таблиц, 10 доменов: workspace, issue, project, agent, runtime, skill, autopilot, chat, inbox, activity_log.

**Ключевой паттерн:** Polymorphic Actor — `actor_type` (`member`/`agent`) + `actor_id` пронизывает все таблицы. Агенты и люди — равноправные участники во всех операциях.

---

## 2. Анализ по 4 осям

### Ось 1: Модель оркестрации

**Тип:** Daemon-based task queue с WebSocket wakeup + polling fallback.

#### Как работает

1. **Task lifecycle:** `queued → dispatched → running → completed/failed/cancelled`
2. **Server** помещает задачу в `agent_task_queue` при назначении issue на agent
3. **Daemon** на машине пользователя:
   - Каждые 3 секунды **poll** server: `GET /api/daemon/claim`
   - WebSocket wakeup: server отправляет `daemon:task_available` → daemon мгновенно poll'ит
   - Каждый claim — атомарная транзакция: только один daemon забирает задачу
4. **Daemon** запускает agent CLI как subprocess в изолированном workdir
5. **CLI stdout** стримится обратно на server через daemon, классифицируется как task_message
6. При завершении daemon отправляет результат (status, comment, usage, session_id)

#### Триггеры оркестрации

| Триггер | Механизм |
|---|---|
| Issue assignment | `agent_task_queue` + daemon claim |
| @agent в комментарии | Comment listener → auto-enqueue |
| Autopilot (cron/webhook) | Scheduler goroutine (каждые 30s) → `DispatchAutopilot` |
| Chat message | Chat → task (session reuse) |
| Quick-create modal | Agent формирует issue из natural language |

#### Autopilot

- **Режимы:** `create_issue` (autopilot создаёт issue и назначает agent) или `run_only` (прямое выполнение без issue)
- **Триггеры:** cron (schedule), webhook (внешний HTTP), API (ручной вызов)
- **Admission check:** проверка runtime online **перед** dispatch — если daemon offline, run → `skipped`
- **Concurrency policy:** управляет параллельными runs

#### Session Resumption

- Для `issue` задач: daemon автоматически возобновляет предыдущую Claude Code session (`session_id` + `work_dir`), если для (agent, issue) уже был run
- «Poisoned session» detection: если предыдущий run завершился с `iteration_limit`, `agent_fallback_message` или `api_invalid_request` — session НЕ возобновляется, стартует fresh

#### Сравнение с task-orchestrator

| Аспект | Multica | task-orchestrator |
|---|---|---|
| Оркестрация | Task queue: 1 issue = 1 task = 1 agent run | Chain: N шагов = N runner calls |
| Granularity | Issue-level (грубая) | Step-level (тонкая) |
| Retry | На уровне task (claim rerun) | На уровне chain step (retry с backoff) |
| Ветвление | Нет | Нет (линейные chains) |
| Параллельность | Нет (serial task queue per agent) | Нет (линейные chains) |
| Session reuse | Да (Claude session_id + workdir) | Нет |

**Вердикт по оси:** Модель оркестрации принципиально другая. Multica — это **task management layer** поверх внешних agents, а не chain executor. Каждая задача — атомарная, delegate-и-забудь. Наша модель (multi-step chains с retry, circuit breaker, quality gates) — глубже по уровню контроля, но не покрывает issue lifecycle management.

---

### Ось 2: State Management

**Тип:** Persistent (PostgreSQL 17), event-sourced (activity_log + WebSocket broadcast), 28 таблиц.

#### Ключевые сущности

| Сущность | Таблица | Роль |
|---|---|---|
| Workspace | `workspace` | Контейнер всего (multi-tenant boundary) |
| Issue | `issue` | Core work unit (Linear/Jira-like) |
| Agent | `agent` | AI worker с профилем, config, skills |
| Runtime | `agent_runtime` | Машина, на которой agent выполняется |
| Task | `agent_task_queue` | Один run агента (queued → completed/failed) |
| Skill | `skill`, `skill_file` | Переиспользуемые инструкции для агентов |
| Autopilot | `autopilot`, `autopilot_trigger`, `autopilot_run` | Scheduled/recurring автоматизации |
| Chat | `chat_session`, `chat_message` | Persistent multi-turn conversations |

#### Context Injection (как агент получает контекст)

При подготовке выполнения daemon инжектит:

1. **CLAUDE.md / AGENTS.md / GEMINI.md** (provider-specific) — meta-skill с identity + instructions + CLI reference
2. **Skills** → provider-native directory (`.claude/skills/`, `CODEX_HOME/skills/`, `.opencode/skills/` и т.д.)
3. **Repo checkout** — `multica repo checkout <url>` создаёт git worktree
4. **Workspace Context** — workspace-level system prompt для всех агентов

#### Real-time Layer

- WebSocket hub с room model (по workspace)
- 60+ event types (issue/agent/task/chat/inbox events)
- Push to React Query cache (patch) или invalidate + refetch

#### GC (Garbage Collection)

Daemon periodically scans workdir:
- Issue done/cancelled + TTL elapsed → remove
- Chat archived + TTL → remove
- Orphan dirs (no meta) + orphan TTL → remove
- Artifact cleanup (regenerable patterns) → selective remove

#### Сравнение с task-orchestrator

| Аспект | Multica | task-orchestrator |
|---|---|---|
| State store | PostgreSQL (28 таблиц) | JSONL files (audit trail) |
| Persistence | Full ACID, multi-user | File-based, single-user |
| Context injection | SKILL.md → workdir + AGENTS.md | Runner config + payload |
| Real-time | WebSocket (60+ event types) | Нет |
| Session reuse | Да (session_id + work_dir) | Нет |
| Garbage collection | Daemon GC loop (TTL-based) | Нет |

**Вердикт по оси:** State management Multica — полнофункциональная multi-user система с ACID, real-time и lifecycle management. task-orchestrator —轻量кий file-based подход для chain execution. Это **разные уровни**: task-orchestrator — execution engine, Multica — platform.

---

### Ось 3: Error Handling

**Тип:** Task-level failure classification + poisoned session detection + runtime health monitoring.

#### Task Failure

- **Статусы:** `completed` / `failed` / `cancelled`
- **Failure reason:** классифицируется daemon'ом:
  - `iteration_limit` — agent достиг лимита итераций
  - `agent_fallback_message` — agent выдал fallback-вывод вместо реального результата
  - `api_invalid_request` — LLM API отклонил запрос (400 + `invalid_request_error`) — conversation history poisoned
  - `agent_error` — generic fallback

#### Poisoned Session Detection

**Уникальный паттерн:** Multica классифицирует output и error agent CLI и определяет, можно ли безопасно возобновить session:

1. **Output-side poisoning:** agent «завершил» с коротким fallback-сообщением (< 320 chars) → классифицируется как poisoned
2. **Error-side poisoning:** LLM API вернул 400 + `invalid_request_error` → conversation history содержит неприемлемый контент, retry невозможен
3. **Результат:** следующий task для (agent, issue) стартует с **fresh session**, а не возобновляет poisoned

#### Runtime Health

- **Heartbeat:** daemon отправляет heartbeat каждые 15s (HTTP или WebSocket)
- **Offline detection:** server помечает runtime offline если heartbeat не поступил 45s
- **Sweeper goroutine** (каждые 30s):
  - Mark offline runtimes
  - Orphan task recovery: dispatched > 5min → failed; running > 2.5h → failed
  - Long-term offline GC: 7 days no heartbeat + no active agents → cleanup

#### Autopilot Admission Check

- Перед dispatch проверяет runtime online
- Если offline → run status = `skipped` с failure_reason
- Предотвращает накапливание doomed tasks

#### Runtime Recovery

- `handleRuntimeGone` — single recovery entry point для HTTP heartbeat, poller, и WS ack
- Stampede control: coalesce window (30s) + failure backoff (60s)
- Auto re-registration при удалении runtime server-side

#### Сравнение с task-orchestrator

| Аспект | Multica | task-orchestrator |
|---|---|---|
| Retry | На уровне task (re-claim) | На уровне chain step (retry с backoff) |
| Error classification | Да (4 категории, poisoned sessions) | Нет (все ошибки = retry) |
| Circuit breaker | Нет (runtime offline detection) | Да |
| Quality gates | Нет (нет post-execution validation) | Да (shell-based) |
| Stuck detection | Runtime sweeper (timeout-based) | Нет |
| Budget control | Нет | Да |
| Session poisoning | Да (уникальный паттерн) | Нет (нет session reuse) |

**Вердикт по оси:** Error handling Multica фокусируется на **task-level reliability** (poisoned sessions, runtime health, orphan recovery), а не на **step-level resilience**. Poisoned session detection — уникальный и ценный паттерн. Но нет retry с backoff, circuit breaker, quality gates — то, чем силён task-orchestrator. **Два проекта дополняют друг друга.**

---

### Ось 4: Extensibility

**Тип:** Skills (workspace-level SKILL.md) + 11 agent providers + autopilot + CLI + MCP.

#### Skills System

- **Workspace-level skills:** переиспользуемые markdown-документы + файлы
- **Per-agent assignment:** агенту назначаются skills из workspace pool
- **Provider-native injection:** при выполнении daemon пишет skills в provider-specific directories:
  - Claude Code → `.claude/skills/{name}/SKILL.md`
  - Codex → `CODEX_HOME/skills/{name}/`
  - OpenCode → `.opencode/skills/{name}/SKILL.md`
  - и т.д.
- **Import:** из URL (ClawHub, skills.sh, GitHub) через `multica skill import --url`
- **CLI управление:** `multica skill list|get|create|import|files upsert`

#### Agent Providers (11)

Claude Code, Codex, GitHub Copilot CLI, OpenClaw, OpenCode, Hermes, Gemini, Pi, Cursor Agent, Kimi, Kiro CLI — все auto-detected daemon'ом на $PATH.

#### Agent Configuration

- **Custom instructions** (system prompt)
- **Custom env** (API keys, base URLs)
- **Custom args** (model, thinking mode)
- **MCP config** (JSONB: MCP server list per agent)
- **Max concurrent tasks**
- **Visibility** (workspace/private)

#### Autopilot

- Cron и webhook триггеры для автоматического запуска агентов
- Template-based issue creation (title interpolation)
- Два режима: create_issue и run_only

#### CLI (comprehensive)

`multica` — полноценный CLI для управления всеми сущностями: workspace, issue, comment, agent, skill, autopilot, project, repo, runtime, attachment. Агенты **используют CLI внутри себя** для чтения/записи issue data.

#### MCP Support

Каждый агент может иметь свой MCP config (JSONB в `agent` таблице). Agent CLI использует MCP servers для расширения tool capabilities.

#### Сравнение с task-orchestrator

| Аспект | Multica | task-orchestrator |
|---|---|---|
| Agent/runner extensibility | 11 providers, auto-detect | 2 runners (pi, codex), config-based |
| Skills | Workspace-level, SKILL.md, import | Нет |
| CLI | Comprehensive (10+ commands) | CLI app (run chains) |
| Autopilot | Cron/webhook/API триггеры | Нет |
| MCP | Per-agent config | Нет |
| Plugin system | Нет | Нет |

**Вердикт по оси:** Extensibility Multica — **выше по горизонтали** (11 providers, skills, autopilot, MCP, comprehensive CLI) но **ниже по вертикали** (нет chain-level customisation, quality gates, budget policies). task-orchestrator глубже контролирует execution, но беднее в ecosystem integrations.

---

## 3. Сравнение с task-orchestrator

### Принципиальные отличия

| Критерий | Multica | task-orchestrator |
|---|---|---|
| **Core mission** | Project management platform для human+agent teams | Chain-level execution engine |
| **Оркестрация** | Issue → Task → Agent (1:1:1) | Chain → Steps → Runners (1:N:N) |
| **Granularity** | Issue-level (coarse) | Step-level (fine) |
| **Multi-user** | Да (workspace, roles, permissions) | Нет (single-user CLI) |
| **Persistence** | PostgreSQL, ACID | JSONL files |
| **Real-time** | WebSocket, 60+ event types | Нет |
| **Retry/resilience** | Task-level (claim rerun, poisoned sessions) | Step-level (retry + backoff + CB + QG) |
| **Budget control** | Нет | Да |
| **Skills** | Workspace-level SKILL.md | Нет |
| **Autopilot** | Cron/webhook/API | Нет |
| **UI** | Full web + desktop (Linear-like) | CLI only |

### Пересечение проблемных пространств

```
                    task-orchestrator         Multica
                    ┌──────────────┐     ┌──────────────────┐
Chain execution     │ ████████████ │     │                  │
Retry + CB + QG     │ ████████████ │     │                  │
Budget control      │ ████████████ │     │                  │
Fix iterations      │ ████████████ │     │                  │
                    │              │     │                  │
Issue management    │              │     │ ████████████     │
Multi-user teams    │              │     │ ████████████     │
Agent lifecycle     │              │     │ ████████████     │
Real-time UI        │              │     │ ████████████     │
Autopilot (cron)    │              │     │ ████████████     │
Skills library      │              │     │ ████████████     │
                    │              │     │                  │
Poisoned sessions   │              │     │ ██               │
Runtime health      │              │     │ ████             │
```

**Вывод:** Практически **нулевое пересечение**. Это ортогональные инструменты на разных уровнях:
- **Multica** — platform layer (project management, collaboration, lifecycle)
- **task-orchestrator** — execution engine (chain steps, retry, resilience)

---

## 4. Паттерны для заимствования

### 🟢 Quick wins (P2)

| Паттерн | Суть | Обоснование |
|---|---|---|
| **Poisoned session detection** | Классификация agent output/error для определения, можно ли возобновить session: `iteration_limit`, `agent_fallback_message`, `api_invalid_request` | Для fix_iterations: если agent зациклился и выдал fallback → не retry, а fresh start. Уникальный паттерн, не найденный в других исследованных проектах |
| **Runtime health monitoring (heartbeat)** | Периодический heartbeat с offline detection (45s timeout) + sweeper goroutine для orphan task recovery | Для distributed execution: если runner недоступен → не dispatch задачу. Аналог нашего circuit breaker, но на уровне runtime availability |
| **Admission check перед dispatch** | Проверка runtime online перед enqueue — если offline, run → `skipped` | Простой паттерн: не ставить задачу в очередь, если runner гарантированно не сможет её выполнить |
| **Autopilot (cron/webhook триггеры)** | Scheduled recurring tasks: cron → create issue → assign agent | Для CI/CD: periodic chain execution (ночной regression test, ежедневный code review) |

### 🟡 Среднесрочные (P3)

| Паттерн | Суть | Обоснование |
|---|---|---|
| **Session resumption** | Reuse session_id + work_dir для (agent, issue) → context continuity между runs | Для fix_iterations: предыдущий run оставил state на диске, следующий подхватывает. Но требует session concept в task-orchestrator |
| **Context injection pipeline** | Provider-native injection: CLAUDE.md → `.claude/`, AGENTS.md → `.agents/`, skills → provider dirs | Для runner configuration: стандартизировать injection mechanism для разных runners |
| **GC loop для workdir cleanup** | Periodic scan: done/cancelled + TTL → remove; orphan + orphan TTL → remove | Для длительного использования: cleanup артефактов chain execution |
| **Event bus (in-process)** | In-process event bus с publish/subscribe: 60+ event types, workspace-scoped rooms | Для observability: chain execution events → logging/metrics/notifications |

---

## 5. Вердикт

### 🟡 Заимствовать отдельные паттерны

**Multica не подходит ни как dependency, ни как reference architecture для task-orchestrator** — проекты решают принципиально разные задачи на разных уровнях абстракции:

- **Multica** — platform для управления **жизненным циклом задач** (issue → assign → execute → review), где AI-агенты — first-class participants
- **task-orchestrator** — engine для управления **выполнением цепочек шагов** (step → run → retry → quality gate), где AI-агенты — execution backends

**Наибольший интерес представляют:**
1. **Poisoned session detection** — уникальный паттерн error classification для agent sessions (не найден в 23 других исследованных проектах)
2. **Autopilot** (cron/webhook триггеры) — модель для scheduled chain execution
3. **Runtime health + admission check** — дополнение к circuit breaker на уровне runner availability

**Что НЕ стоит заимствовать:**
- Full PostgreSQL persistence — избыточно для chain execution engine
- WebSocket real-time layer — overkill для текущих нужд
- Issue/project management model — не наша domain
- Multi-user workspace isolation — не наша domain

---

## 6. Источники

1. [Multica GitHub Repository](https://github.com/multica-ai/multica) — README, исходный код
2. [Multica Website](https://multica.ai/) — landing page, product demo
3. [Multica Product Overview (internal doc)](https://github.com/multica-ai/multica/blob/main/docs/product-overview.md) — детальное описание всех модулей (983 строки)
4. [Multica Self-Hosting Guide](https://github.com/multica-ai/multica/blob/main/SELF_HOSTING.md) — архитектура развёртывания
5. [Multica AGENTS.md](https://github.com/multica-ai/multica/blob/main/AGENTS.md) — архитектурные правила
