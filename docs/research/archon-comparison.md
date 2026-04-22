# Исследование: Archon — workflow-движок для AI-кодинг-агентов (TypeScript/Bun)

> **Проект:** [github.com/coleam00/Archon](https://github.com/coleam00/Archon)
> **Дата анализа:** 2026-04-21
> **Язык:** TypeScript (Bun runtime)
> **Лицензия:** MIT
> **Версия:** 0.3.6
> **Аналитик:** Технический писатель (Гермиона)

---

## 1. Обзор проекта

Archon — платформо-независимый **workflow-движок для AI-кодинг-агентов**. Ключевая идея: определять процессы разработки как YAML-воркфлоу (DAG) — планирование, имплементация, валидация, код-ревью, создание PR — и выполнять их надёжно и воспроизводимо.

> «Like what Dockerfiles did for infrastructure and GitHub Actions did for CI/CD — Archon does for AI coding workflows. Think n8n, but for software development.»

Архитектура Archon в корне отличается от task-orchestrator: Archon не работает напрямую с LLM API — он **оркестирует внешние AI-ассистенты** (Claude Code, Codex CLI, Pi Coding Agent) через их subprocess SDK. Каждый «узел» (node) воркфлоу — это полноценная сессия AI-ассистента с инструментами, MCP-серверами, хуками и skill'ами.

### Предыдущая версия (v1)

Оригинальный Archon (Python) представлял собой систему управления задачами + RAG. Полностью сохранён на ветке `archive/v1-task-management-rag`. Нынешний Archon (v2) — полная переработка на TypeScript с нуля.

### Архитектура

```
┌─────────────────────────────────────────────────────────────┐
│  Platform Adapters (Web UI, CLI, Telegram, Slack,          │
│                    Discord, GitHub)                         │
│   • IPlatformAdapter interface                              │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                     Orchestrator                            │
│          (Message Routing & Context Management)             │
│   • Route slash commands → Command Handler                  │
│   • Route AI queries → AI Router → Workflow/Command         │
│   • Session lifecycle, streaming, workflow events           │
└──────────────┬──────────────────────────┬───────────────────┘
               │                          │
       ┌───────┴────────┐         ┌───────┴────────┐
       ▼                ▼         ▼                ▼
┌───────────┐  ┌────────────┐  ┌─────────────────────────────┐
│  Command  │  │  Workflow  │  │    AI Agent Providers        │
│  Handler  │  │  Executor  │  │   (Claude / Codex / Pi)      │
│  (Slash)  │  │  (DAG)     │  │   IAgentProvider interface   │
└───────────┘  └────────────┘  └─────────────────────────────┘
       │              │                      │
       └──────────────┴──────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│              Isolation (IIsolationProvider)                  │
│         Git Worktrees / Container / VM                       │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│              SQLite / PostgreSQL (7 Tables)                  │
│   Codebases • Conversations • Sessions • Workflow Runs      │
│    Isolation Environments • Messages • Workflow Events       │
└─────────────────────────────────────────────────────────────┘
```

### Ключевые характеристики

| Характеристика | Значение |
|---|---|
| **Тип** | Workflow-движок (DAG) для AI-кодинг-агентов |
| **Модель выполнения** | DAG (nodes + depends_on) + topological layers + parallel execution |
| **AI-провайдеры** | Claude Code SDK, Codex CLI, Pi Coding Agent (через subprocess) |
| **State management** | SQLite (default) / PostgreSQL (7 таблиц) |
| **Workflow-формат** | YAML-файлы (`.archon/workflows/`) |
| **Расширяемость** | IPlatformAdapter, IAgentProvider, IIsolationProvider, MCP, Hooks, Skills, Commands |
| **Изоляция** | Git worktrees (по умолчанию) — каждый run в своём worktree |
| **Интерфейсы** | Web UI, CLI, Telegram, Slack, Discord, GitHub Webhooks |
| **Платформы** | macOS, Linux, Windows (WSL), Docker |

### Основные пакеты (monorepo)

| Пакет | Назначение |
|---|---|
| [`@archon/core`](https://github.com/coleam00/Archon/tree/main/packages/core) | Orchestrator, Command Handler, DB operations, Session management, Workflow operations |
| [`@archon/workflows`](https://github.com/coleam00/Archon/tree/main/packages/workflows) | DAG Executor, Loader, Router, Validator, Event Emitter, Condition Evaluator |
| [`@archon/providers`](https://github.com/coleam00/Archon/tree/main/packages/providers) | IAgentProvider: Claude, Codex, Pi (community) — subprocess-based streaming |
| [`@archon/isolation`](https://github.com/coleam00/Archon/tree/main/packages/isolation) | IIsolationProvider: WorktreeProvider (git worktree isolation) |
| [`@archon/adapters`](https://github.com/coleam00/Archon/tree/main/packages/adapters) | IPlatformAdapter: Telegram, Slack, GitHub, Discord, Web |
| [`@archon/server`](https://github.com/coleam00/Archon/tree/main/packages/server) | Hono HTTP server: REST API, SSE streaming, Web UI |
| [`@archon/web`](https://github.com/coleam00/Archon/tree/main/packages/web) | Web Dashboard: Chat, Workflow Builder (drag-and-drop), Monitoring |
| [`@archon/git`](https://github.com/coleam00/Archon/tree/main/packages/git) | Git operations: clone, worktree, branch management |
| [`@archon/paths`](https://github.com/coleam00/Archon/tree/main/packages/paths) | Path resolution: workspaces, artifacts, logs, config |

---

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | TasK Orchestrator | Archon | Статус |
|---|---|---|---|
| **Цепочки шагов (chains)** | ✅ YAML chains, статические и динамические | ✅ YAML DAG workflows (nodes + depends_on) | ✅ Паритет (разный подход) |
| **DAG (направленный ациклический граф)** | ❌ Только линейные/динамические цепочки | ✅ Полноценный DAG с topological layers, parallel execution | 🟡 Интересно |
| **Условное ветвление** | ✅ Динамические chains (DynamicChainResolver) | ✅ `when:` expressions + `trigger_rule` на каждом node | ✅ Паритет |
| **Retry с backoff** | ✅ RetryingAgentRunner | ✅ Двухуровневый retry (SDK 3 попытки + Node 3 попытки), exponential backoff | ✅ Паритет |
| **Circuit Breaker** | ✅ CircuitBreakerAgentRunner | ❌ Нет (только retry + fallback model) | ✅ У нас есть |
| **Quality Gates** | ✅ Shell-команды как проверки | ✅ Bash-узлы + `until_bash` в loops + approval nodes | ✅ Паритет (разный подход) |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based) | ✅ `maxBudgetUsd` per node (Claude only) + cost tracking per session | ✅ Паритет |
| **Итерационные циклы (fix_iterations)** | ✅ Группа шагов с max_iterations | ✅ Loop nodes: `loop.prompt` + `loop.until` + `loop.max_iterations` | ✅ Паритет |
| **Fallback routing** | ✅ Per-step fallback runner | ✅ `fallbackModel` per node / per workflow (Claude only) | ✅ Паритет |
| **Human-in-the-loop** | ❌ Нет | ✅ Approval nodes (approve/reject) + Interactive loops + `$LOOP_USER_INPUT` | 🟡 Интересно |
| **Isolation (git worktrees)** | ❌ Нет (работает в текущем checkout) | ✅ Каждый workflow run = git worktree (IIsolationProvider) | 🟡 Интересно |
| **Session persistence** | ❌ In-memory | ✅ SQLite/PostgreSQL: сессии, сообщения, workflow events | 🟡 Позже |
| **DAG Resume on Failure** | ❌ Нет | ✅ Автоматический resume с пропуском завершённых узлов | 🟡 Интересно |
| **Ролевые промпты** | ✅ .md файлы (18+ ролей) | ✅ Markdown Commands (`.archon/commands/`) + variable substitution | ✅ Паритет |
| **Multiple runners** | ✅ Pi + Codex (через interface) | ✅ Claude + Codex + Pi (через IAgentProvider registry) | ✅ Паритет |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❌ Monorepo packages (core/workflows/providers/adapters) | ✅ У нас лучше |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Прямой вызов через provider registry | ✅ У нас лучше |
| **Structured output** | ❌ Нет (текстовый вывод runner'ов) | ✅ `output_format` (JSON Schema) — SDK-enforced на Claude/Codex, best-effort на Pi | 🟡 Интересно |
| **Tool control (hooks)** | ❌ Нет | ✅ Per-node SDK hooks (PreToolUse, PostToolUse, 17+ событий) | 🟡 Позже |
| **Tool restrictions** | ❌ Нет | ✅ `allowed_tools` / `denied_tools` per node | 🟡 Позже |
| **MCP-протокол** | ❌ Нет | ✅ Per-node MCP server configs (JSON) | 🟡 Позже |
| **Skills system** | ✅ Свои role .md файлы | ✅ SKILL.md standard + marketplace (skills.sh) | ✅ Паритет |
| **Inline sub-agents** | ❌ Нет | ✅ `agents:` в YAML — inline определения sub-agent'ов для map-reduce | 🟡 Интересно |
| **Artifact chain** | ❌ Нет (контекст через JSONL) | ✅ `$ARTIFACTS_DIR` + `$nodeId.output` substitution | 🟡 Интересно |
| **Per-node provider/model** | ❌ Нет (один runner на цепочку) | ✅ `provider:` и `model:` на каждом node | 🟡 Интересно |
| **Streaming modes** | ✅ Через runner output | ✅ Stream / Batch (per-platform) + SSE + structured events | ✅ Паритет |
| **AI Router** | ❌ Нет (явный выбор chain) | ✅ AI Router: natural language → workflow/command matching | 🟡 Интересно |
| **Platform adapters** | ❌ Только CLI (Symfony Console) | ✅ Web UI, CLI, Telegram, Slack, Discord, GitHub webhooks | 🟢 Не берём |
| **Web UI / Dashboard** | ❌ Нет | ✅ Chat, Workflow Builder (drag-and-drop), Monitoring | 🟢 Не берём |
| **Audit Trail (JSONL)** | ✅ JsonlAuditLogger | ✅ JSONL logs (`~/.archon/workspaces/.../logs/`) + DB events | ✅ Паритет |
| **Cancel/Abandon workflow** | ❌ Нет | ✅ Cancel nodes + `/workflow cancel|abandon` + in-flight abort | 🟡 Позже |
| **Sandbox mode** | ❌ Нет | ✅ OS-level filesystem/network restrictions per node (Claude only) | 🟡 Позже |
| **Context management** | ❌ In-memory, runner-managed | ✅ `context: fresh|shared` per node + plan-to-execute transitions | 🟡 Интересно |
| **Error classification** | ⚠️ Basic (retry on failure) | ✅ FATAL / TRANSIENT / UNKNOWN с разными стратегиями retry | 🟡 Интересно |
| **Env vars injection** | ❌ Нет | ✅ Codebase-scoped env vars + per-provider env config | 🟡 Позже |
| **Script nodes (deterministic)** | ✅ Shell-команды как шаги | ✅ Bash nodes + Script nodes (Bun/Python) — без AI, бесплатно | ✅ Паритет |

---

## 3. Что полезно взять и почему

### 3.1 🟡 DAG-based Workflow Execution (`packages/workflows/src/dag-executor.ts`)

**Что у них:** Workflows определяются как направленный ациклический граф (DAG) с узлами и зависимостями. Независимые узлы выполняются параллельно через `Promise.allSettled`. Топологические слои строятся алгоритмом Кана (Kahn's algorithm).

```yaml
nodes:
  - id: classify
    command: classify-issue
    output_format:
      type: object
      properties:
        type: { type: string, enum: [BUG, FEATURE] }

  - id: fix-bug
    command: fix-bug
    depends_on: [classify]
    when: "$classify.output.type == 'BUG'"

  - id: build-feature
    command: build-feature
    depends_on: [classify]
    when: "$classify.output.type == 'FEATURE'"

  - id: pr
    command: create-pr
    depends_on: [fix-bug, build-feature]
    trigger_rule: none_failed_min_one_success
```

**Почему нам интересно:** Наша модель оркестрации — линейные цепочки (static/dynamic). DAG-подход позволяет параллельное выполнение независимых шагов (например, параллельный review разными агентами) и условное ветвление. Однако, перенос DAG в PHP — значительная задача, требующая отдельных обоснований.

**Отличие от нашей реализации:**
- У нас: цепочка = последовательность шагов (возможно динамическая)
- У них: workflow = DAG-граф с топологической сортировкой и параллельным выполнением слоёв

### 3.2 🟡 Loop Nodes — итеративное выполнение с сигналом завершения (`packages/workflows/src/dag-executor.ts`)

**Что у них:** Loop-узлы повторяют промпт до выполнения одного из условий:
1. LLM выдаёт `<promise>SIGNAL</promise>` (completion signal)
2. `until_bash` — shell-скрипт выходит с кодом 0
3. `max_iterations` достигнут (узел падает с ошибкой)

```yaml
- id: implement
  loop:
    prompt: |
      Read the PRD and implement the next unfinished story.
      Validate your changes before committing.
      When all stories are done: <promise>COMPLETE</promise>
    until: COMPLETE
    max_iterations: 15
    fresh_context: true   # Каждый iteration = новая сессия
    until_bash: "bun run test"   # Deterministic exit check
```

**Почему нам интересно:** Похож на наш `fix_iterations`, но богаче:
- `fresh_context: true` — каждый iteration начинается с чистой сессии (agent читает state с диска)
- `until_bash` — детерминированная проверка завершения (тесты прошли → стоп)
- `interactive: true` — пауза между итерациями для ввода пользователя
- Двойная стратегия выхода: и по сигналу LLM, и по bash-проверке

### 3.3 🟡 Human-in-the-Loop: Approval Nodes + Interactive Loops

**Что у них:** Два механизма для участия человека в workflow:

**Approval Node** — пауза для ревью с approve/reject:
```yaml
- id: review-gate
  approval:
    message: "Review the plan before proceeding."
    capture_response: true
    on_reject:
      prompt: "Revise based on feedback: $REJECTION_REASON"
      max_attempts: 3
  depends_on: [plan]
```

**Interactive Loop** — итеративный цикл с обратной связью:
```yaml
- id: refine
  loop:
    prompt: "User feedback: $LOOP_USER_INPUT. Apply and improve."
    until: APPROVED
    interactive: true
    gate_message: "Review and provide feedback."
```

**Почему нам интересно:** Для сценариев, когда task-orchestrator запускается частично автономно, но критические точки требуют подтверждения. Например: план создан → человек проверяет → реализация началась.

### 3.4 🟡 Isolation через Git Worktrees (`packages/isolation/`)

**Что у них:** Каждый workflow run автоматически создаёт git worktree — изолированную копию репозитория:
- Параллельные runs не конфликтуют
- Рабочая ветка остаётся чистой
- Проваленные runs не оставляют «мусор»

```typescript
interface IIsolationProvider {
  create(request: IsolationRequest): Promise<IsolatedEnvironment>;
  destroy(envId: string, options?: DestroyOptions): Promise<DestroyResult>;
  get(envId: string): Promise<IsolatedEnvironment | null>;
  list(codebaseId: string): Promise<IsolatedEnvironment[]>;
  healthCheck(envId: string): Promise<boolean>;
}
```

**Почему нам интересно:** Для автономного выполнения цепочек (особенно `fix_iterations`) нужна гарантия, что параллельные run'ы не сломают друг другу файлы. В PHP нет worktrees нативно, но концепция ценная.

### 3.5 🟡 Error Classification (FATAL / TRANSIENT / UNKNOWN)

**Что у них:** Трёхуровневая классификация ошибок с разными стратегиями retry:

```typescript
| Class     | Examples                              | Retried?                   |
|-----------|---------------------------------------|----------------------------|
| FATAL     | Auth failure, permission denied       | Never (even with all)      |
| TRANSIENT | Process crash, rate limit, timeout    | Yes (default)              |
| UNKNOWN   | Unrecognized error messages           | No (unless on_error: all)  |
```

**Почему нам интересно:** У нас `RetryingAgentRunner` делает retry на любой ошибке. Добавление классификации позволило бы не тратить попытки retry на заведомо неисправимые ошибки (401, 403).

### 3.6 🟡 DAG Resume on Failure — автоматическое восстановление

**Что у них:** Если workflow-выполнение падает, следующий запуск автоматически:
1. Ищет предыдущий failed run на том же рабочем пути
2. Загружает `node_completed` events
3. Пропускает уже завершённые узлы
4. Выполняет только failed и невыполненные

**Почему нам интересно:** Для длинных цепочек (plan → implement → review → fix → review → PR) при сбое на шаге 4 не хочется повторять шаги 1-3. У нас retry на уровне отдельного шага, но нет resume на уровне цепочки.

### 3.7 🟡 `$nodeId.output` — межузловая коммуникация через output substitution

**Что у них:** Выход каждого узла доступен downstream через `$nodeId.output`:

```yaml
- id: classify
  command: classify-issue
  output_format:
    type: object
    properties:
      type: { type: string, enum: [BUG, FEATURE] }

- id: implement
  command: implement
  depends_on: [classify]
  # В команде доступно: $classify.output.type
```

Поддерживается dot-notation для JSON-полей: `$classify.output.type`, `$classify.output.severity`.

**Почему нам интересно:** У нас шаги коммуницируют через JSONL audit trail и контекст runner'а. Явная подстановка output позволила бы downstream-шагам работать со структурированными результатами предыдущих шагов.

### 3.8 🟡 `output_format` — Structured JSON Output

**Что у них:** Узлы могут объявить JSON Schema, и AI-ассистент вернёт структурированный JSON:

```yaml
- id: classify
  command: classify-issue
  output_format:
    type: object
    properties:
      type: { type: string, enum: [BUG, FEATURE] }
      severity: { type: string, enum: [low, medium, high] }
    required: [type]
```

SDK-enforced на Claude/Codex; best-effort на Pi (schema добавляется в промпт, JSON извлекается из ответа).

**Почему нам интересно:** Для quality gates и условного ветвления нужен предсказуемый формат вывода. Сейчас мы полагаемся на текстовый вывод runner'ов и shell-проверки.

### 3.9 🟡 Per-Node Provider/Model Override

**Что у них:** Каждый узел может использовать своего AI-провайдера и модель:

```yaml
nodes:
  - id: classify
    prompt: "Classify this issue"
    model: haiku           # Быстрая дешёвая модель

  - id: deep-review
    command: thorough-review
    provider: claude
    model: opus            # Мощная дорогая модель
```

**Почему нам интересно:** В текущей архитектуре task-orchestrator цепочка использует один runner. Возможность менять runner/model на уровне шага (например, дешёвая модель для классификации, дорогая для кодогенерации) — ценная оптимизация.

---

## 4. Что НЕ берём и почему

### 4.1 🟢 Subprocess-based AI Execution

Archon запускает AI-ассистенты (Claude Code, Codex CLI) как subprocess через SDK. Это принципиально другая модель: Archon не работает с LLM API напрямую — он делегирует полноценному AI-ассистенту с файловым доступом, инструментами, MCP.

Task-orchestrator работает через runner'ы (pi, codex), которые сами управляют LLM-взаимодействием. Нам не нужна subprocess-оркестрация — мы на уровень выше.

### 4.2 🟢 Platform Adapters (Telegram, Slack, Discord, GitHub)

Archon — мультплатформенный чат-бот с webhooks, polling, SSE. Task-orchestrator — CLI-утилита для автоматического выполнения цепочек. Разные парадигмы.

### 4.3 🟢 Web UI / Dashboard / Drag-and-Drop Workflow Builder

Визуальный редактор воркфлоу и dashboard для мониторинга — отличный UX, но не входит в scope task-orchestrator. Наш целевой сценарий: YAML → CLI → результат.

### 4.4 🟢 SQLite/PostgreSQL Persistence

Archon хранит всё в 7 таблицах БД: сессии, сообщения, workflow runs, events. Для интерактивного мультплатформенного чата это оправдано. Наш оркестратор запускается, выполняет цепочку, завершается. In-memory + JSONL audit trail достаточно.

### 4.5 🟢 AI Router (Natural Language → Workflow Matching)

Archon использует AI-роутер: пользователь пишет «fix issue #42», роутер подбирает подходящий workflow/command. Мы используем явный выбор цепочки через CLI.

### 4.6 🟢 Session Transitions / Immutable Sessions

Архитектура сессий Archon (immutable sessions, parent_session_id, transition triggers: `first-message`, `plan-to-execute`) — сложная система для интерактивных диалогов. Для нашего одноразового pipeline — overengineering.

### 4.7 🟢 Per-Node Hooks (17+ SDK events)

Хуки Claude SDK (PreToolUse, PostToolUse, Notification, Stop и т.д.) — мощный механизм контроля AI-ассистента на уровне tool call. Но это специфично для subprocess-based модели. Наши runner'ы управляют tool call сами.

### 4.8 🟢 Per-Node MCP Servers

MCP-конфигурация на уровне узла — great для интерактивного агента, но overhead для нашего pipeline. Если понадобится, добавим через runner.

### 4.9 🟢 Sandbox Mode

OS-level filesystem/network restrictions (Claude only) — полезно для безопасности при выполнении непроверенного кода. Для нашего controlled pipeline с проверенными runner'ами — пока не актуально.

---

## 5. Сводка рекомендаций

| Фича | Приоритет | Обоснование |
|---|---|---|
| Chain orchestration (YAML chains) | ✅ Уже есть | Core-функциональность task-orchestrator |
| Retry + Circuit Breaker | ✅ Уже есть | Устойчивость при сбоях |
| Quality Gates | ✅ Уже есть | Автоматическая проверка кода |
| Budget control | ✅ Уже есть | Предотвращение runaway spending |
| Fix iterations | ✅ Уже есть | Closed-loop цикл разработки |
| Conditional branching | ✅ Уже есть | DynamicChainResolver |
| DAG-based workflow (parallel execution) | 🟡 P3 | Значительное архитектурное изменение, нужна отдельная задача |
| Loop nodes с `until_bash` | 🟡 P2 | Усиление fix_iterations: детерминированная проверка завершения |
| Loop nodes с `fresh_context` | 🟡 P2 | Каждый iteration с чистым контекстом (agent читает state с диска) |
| Human-in-the-loop (approval gates) | 🟡 P3 | Для сценариев частично автономного выполнения |
| Error classification (FATAL/TRANSIENT/UNKNOWN) | 🟡 P2 | Умный retry: не тратить попытки на неисправимые ошибки |
| DAG Resume on Failure | 🟡 P3 | Resume на уровне цепочки при сбое |
| `$nodeId.output` substitution | 🟡 P2 | Явная передача данных между шагами |
| `output_format` (structured JSON) | 🟡 P3 | Предсказуемый формат вывода для quality gates |
| Per-node provider/model override | 🟡 P3 | Оптимизация: дешёвая модель для простых шагов |
| Isolation (git worktrees) | 🟡 P3 | Для параллельного выполнения цепочек |
| Subprocess AI execution | 🟢 — | Разный уровень абстракции |
| Platform adapters (Telegram, Slack, etc.) | 🟢 — | Разная парадигма (chat vs. pipeline) |
| Web UI / Dashboard | 🟢 — | Не scope task-orchestrator |
| SQLite/PostgreSQL persistence | 🟢 — | In-memory + JSONL достаточно |
| AI Router | 🟢 — | Явный выбор цепочки через CLI |
| Per-node hooks (17+ SDK events) | 🟢 — | Специфично для subprocess модели |
| Per-node MCP servers | 🟢 — | Если нужно — через runner |
| Session transitions | 🟢 — | Overengineering для pipeline |

---

## 6. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории Archon:

- [`packages/workflows/src/dag-executor.ts`](https://github.com/coleam00/Archon/blob/main/packages/workflows/src/dag-executor.ts) — DAG Executor: topological layers, parallel execution, node retry, cancel/abort, loop node dispatch, idle timeout
- [`packages/workflows/src/schemas/dag-node.ts`](https://github.com/coleam00/Archon/blob/main/packages/workflows/src/schemas/dag-node.ts) — Zod-схемы: DagNode discriminated union, BashNode, LoopNode, ApprovalNode, CancelNode, ScriptNode
- [`packages/workflows/src/condition-evaluator.ts`](https://github.com/coleam00/Archon/blob/main/packages/workflows/src/condition-evaluator.ts) — `when:` condition evaluator: string/numeric operators, compound expressions (`&&`, `||`)
- [`packages/providers/src/types.ts`](https://github.com/coleam00/Archon/blob/main/packages/providers/src/types.ts) — IAgentProvider, MessageChunk, ProviderCapabilities, NodeConfig, SendQueryOptions
- [`packages/providers/src/registry.ts`](https://github.com/coleam00/Archon/blob/main/packages/providers/src/registry.ts) — Provider registry: registerBuiltinProviders, registerCommunityProviders
- [`packages/isolation/src/providers/worktree.ts`](https://github.com/coleam00/Archon/blob/main/packages/isolation/src/providers/worktree.ts) — WorktreeProvider: create/destroy/adopt git worktrees
- [`packages/core/src/orchestrator/orchestrator.ts`](https://github.com/coleam00/Archon/blob/main/packages/core/src/orchestrator/orchestrator.ts) — Orchestrator: message routing, session lifecycle, streaming, context injection
- [`packages/core/src/state/session-transitions.ts`](https://github.com/coleam00/Archon/blob/main/packages/core/src/state/session-transitions.ts) — Session state machine: TransitionTrigger types, plan-to-execute detection
- [`packages/workflows/src/executor-shared.ts`](https://github.com/coleam00/Archon/blob/main/packages/workflows/src/executor-shared.ts) — classifyError (FATAL/TRANSIENT/UNKNOWN), detectCompletionSignal, loadCommandPrompt, variable substitution
- [`packages/workflows/src/loader.ts`](https://github.com/coleam00/Archon/blob/main/packages/workflows/src/loader.ts) — Workflow loader: YAML parsing, cycle detection, validation
- [`packages/workflows/src/router.ts`](https://github.com/coleam00/Archon/blob/main/packages/workflows/src/router.ts) — AI Router: natural language → workflow/command matching
- [`README.md`](https://github.com/coleam00/Archon/blob/main/README.md) — Обзор проекта, архитектура, quick start, примеры workflows

📚 **Источники:**
1. [github.com/coleam00/Archon](https://github.com/coleam00/Archon) — репозиторий проекта
2. [archon.diy](https://archon.diy) — документация (The Book of Archon, Guides, API Reference)
3. [archon.diy/docs/reference/architecture](https://archon.diy/docs/reference/architecture/) — полная архитектура, интерфейсы, data flow
4. [archon.diy/docs/guides/authoring-workflows](https://archon.diy/docs/guides/authoring-workflows/) — справочник по YAML workflow DSL
5. [archon.diy/docs/guides/loop-nodes](https://archon.diy/docs/guides/loop-nodes/) — loop nodes: итеративное выполнение с completion signals
