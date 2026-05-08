# Исследование: Sandcastle — TypeScript-библиотека для оркестрации AI-агентов в песочницах

> **Проект:** [github.com/mattpocock/sandcastle](https://github.com/mattpocock/sandcastle)
> **Дата анализа:** 2026-05-07
> **Язык:** TypeScript (Node.js)
> **Лицензия:** MIT
> **Версия:** 0.5.9
> **Аналитик:** Аналитик (Шерлок)

---

## 1. Обзор проекта

Sandcastle — **TypeScript-библиотека для оркестрации AI-кодинг-агентов в изолированных песочницах** от Matt Pocock (`@ai-hero/sandcastle`). Ключевая идея: один вызов `sandcastle.run()` — и библиотека берёт на себя создание песочницы, управление ветками git, запуск агента, сбор коммитов и слияние обратно.

> ⚠️ **Примечание:** Sandcastle — не фреймворк для построения агентов, не workflow engine и не SDK для LLM API. Это **инфраструктурный слой** (sandbox orchestration library), который управляет окружением (Docker/Podman/Vercel VM), ветками (git worktrees) и жизненным циклом запуска внешних AI-агентов (Claude Code, Codex, Pi, OpenCode).

Архитектура Sandcastle принципиально отличается от task-orchestrator: Sandcastle работает на уровне **sandbox lifecycle management** (создание контейнера → копирование файлов → запуск агента → сбор коммитов → cleanup), а task-orchestrator работает на уровне **chain orchestration** (YAML-цепочки → шаги → retry → circuit breaker → quality gates → бюджетный контроль).

### Архитектура

```
┌─────────────────────────────────────────────────────────────┐
│ User Script (.sandcastle/main.ts) │
│ • Вызов run() / createSandbox() / createWorktree() │
│ • Конфигурация: agent, sandbox, branchStrategy, prompt │
│ • Templates: simple-loop, sequential-reviewer, │
│ parallel-planner, parallel-planner-with-review │
└──────────────────────────┬──────────────────────────────────┘
 │
 ▼
┌─────────────────────────────────────────────────────────────┐
│ run() / interactive() (run.ts) │
│ • Валидация опций (branchStrategy, prompt, output) │
│ • Разрешение env (.sandcastle/.env + process.env) │
│ • Prompt resolution (inline vs file + {{KEY}} substitution)│
│ • Формирование Display layer (file logging / stdout TUI) │
│ • Effect-пайплайн: SandboxFactory → Orchestrator → Result │
└──────────────────────────┬──────────────────────────────────┘
 │
 ▼
┌─────────────────────────────────────────────────────────────┐
│ Orchestrator (Orchestrator.ts) │
│ • Цикл итераций (1..maxIterations) │
│ • На каждую итерацию: withSandbox → withSandboxLifecycle │
│ • Prompt preprocessing (!`command` expansion внутри sandbox)│
│ • Agent invocation (AgentProvider.buildPrintCommand) │
│ • Stream parsing (text / tool_call / result / session_id) │
│ • Completion signal detection (<promise>COMPLETE</promise>) │
│ • Idle timeout (default 10 min) + periodic warnings │
│ • Session capture (Claude Code JSONL → host) │
└──────────────────────────┬──────────────────────────────────┘
 │
 ┌───────────┴───────────┐
 ▼ ▼
┌────────────────────┐ ┌────────────────────────────────────┐
│ SandboxFactory │ │ SandboxLifecycle │
│ (SandboxFactory) │ │ (SandboxLifecycle.ts) │
│ • Worktree mgmt │ │ • Git setup (safe.directory, │
│ • Sandbox start │ │ user.name/email propagation) │
│ • Branch cleanup │ │ • Hooks execution (host + sandbox)│
│ │ │ • Commit collection (git rev-list) │
│ │ │ • Merge-to-head (temp → HEAD) │
│ │ │ • Worktree cleanup (preserve if │
│ │ │ dirty, remove if clean) │
└────────┬───────────┘ └────────────────────────────────────┘
 │
 ▼
┌─────────────────────────────────────────────────────────────┐
│ Sandbox Providers (pluggable interface) │
│ • docker() — bind-mount (Docker Desktop) │
│ • podman() — bind-mount (rootless) │
│ • vercel() — isolated (Firecracker microVM) │
│ • daytona() — isolated (Daytona SDK) │
│ • noSandbox() — host-direct (interactive only) │
│ • Custom: createBindMountSandboxProvider / │
│ createIsolatedSandboxProvider │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ Agent Providers (pluggable interface) │
│ • claudeCode(model, {effort, env, captureSessions}) │
│ • codex(model, {effort, env}) │
│ • pi(model, {env}) │
│ • opencode(model, {variant, env}) │
│ │
│ Interface: buildPrintCommand, parseStreamLine, │
│ buildInteractiveArgs, parseSessionUsage │
└─────────────────────────────────────────────────────────────┘
```

### Ключевые характеристики

| Характеристика | Значение |
| --- | --- |
| **Тип** | Sandbox orchestration library для AI-кодинг-агентов |
| **Модель выполнения** | Итеративный запуск агента в песочнице (agent invocation loop) |
| **Поддерживаемые агенты** | Claude Code, Codex, Pi, OpenCode — через AgentProvider interface |
| **Управление состоянием** | Git worktrees + commit collection (без persistent DB) |
| **Песочницы** | Docker (bind-mount), Podman (bind-mount), Vercel (isolated VM), Daytona (isolated), custom |
| **Расширяемость** | AgentProvider (4 встроенных + custom), SandboxProvider (4 встроенных + 2 factory), hooks, templates |
| **Стратегии ветвления** | Head (direct write), merge-to-head (temp branch → merge → cleanup), branch (explicit named) |
| **Обработка ошибок** | Typed error hierarchy (20+ error types), idle timeout, AbortSignal support, worktree preservation on failure |
| **Язык реализации** | TypeScript, Effect-TS (functional effect system), Vitest |

---

## 2. Возможности оркестрации — обзор

| Функция | Sandcastle |
| --- | --- |
| **DAG / Workflow engine** | ❌ Только линейные/динамические цепочки |
| **Итерационные циклы** | ✅ maxIterations (до N запусков агента в sandbox) |
| **Sandbox isolation** | ✅ Docker/Podman/Vercel/Daytona с SELinux, network, UID/GID alignment |
| **Branch management** | ✅ 3 стратегии: head, merge-to-head, branch; git worktrees |
| **AgentProvider interface** | ✅ AgentProvider (buildPrintCommand + parseStreamLine) |
| **SandboxProvider interface** | ✅ Pluggable: BindMount / Isolated / NoSandbox + factory functions |
| **Structured output** | ✅ Output.object({tag, schema}) / Output.string({tag}) — Zod-валидация |
| **Completion signal** | ✅ `<promise>COMPLETE</promise>` — early termination по маркеру в stdout |
| **Prompt template engine** | ✅ {{KEY}} substitution + !`command` expansion (внутри sandbox) + built-in SOURCE_BRANCH/TARGET_BRANCH |
| **Hooks (lifecycle)** | ✅ host.onWorktreeReady, host.onSandboxReady, sandbox.onSandboxReady |
| **Session persistence** | ✅ Claude Code session JSONL capture/resume, token usage extraction |
| **Parallel execution** | ✅ Promise.allSettled в шаблонах (parallel-planner) |
| **Multi-agent patterns** | ✅ Templates: sequential-reviewer (implement → review), parallel-planner (plan → execute N → merge) |
| **Error classification** | ✅ Typed error hierarchy: AgentError, AgentIdleTimeoutError, ExecError, SyncError, WorktreeError и др. (20+) |
| **Context compression** | ❌ Нет |
| **DDD-архитектура** | ❌ Effect-TS service layer (Context.Tag + Layer) |
| **Decorator pattern** | ❌ Прямой вызов Effect-пайплайна |
| **Idle timeout** | ✅ Default 10 min, configurable, periodic warnings |
| **AbortSignal support** | ✅ run(), interactive(), hooks — все поддерживают AbortSignal |
| **Worktree preservation** | ✅ Dirty worktree сохраняется при failure, clean — auto-remove |
| **JSONL audit trail** | ⚠️ Session JSONL (только Claude Code), не audit trail в нашем смысле |

---

## 3. Оркестрационные возможности

### 3.1 🟡 Sandbox Provider Interface (SandboxProvider.ts)

**Что у них:** Plug-and-play интерфейс для песочниц двух типов:

```typescript
// Bind-mount: host → container (Docker, Podman)
interface BindMountSandboxProvider {
 tag: "bind-mount";
 name: string;
 env: Record<string, string>;
 sandboxHomedir: string | undefined;
 create(options: BindMountCreateOptions): Promise<BindMountSandboxHandle>;
}

// Isolated: собственная FS, sync через copyIn/copyOut (Vercel VM, Daytona)
interface IsolatedSandboxProvider {
 tag: "isolated";
 name: string;
 env: Record<string, string>;
 create(options: IsolatedCreateOptions): Promise<IsolatedSandboxHandle>;
}
```

Оба handle предоставляют `exec()`, `copyFileOut()`, `close()`. Bind-mount дополнительно `copyFileIn()`, isolated — `copyIn()` (directory support). Factory functions: `createBindMountSandboxProvider()`, `createIsolatedSandboxProvider()`.

**Оркестрационная значимость:** Провайдеры разделены на два типа по модели изоляции файловой системы: bind-mount (разделяемая FS с host) и isolated (независимая FS, sync через copyIn/copyOut). Поддержка 4 runtime (Docker, Podman, Vercel Firecracker VM, Daytona) + 2 factory-функции для custom-провайдеров. Оба handle предоставляют единый контракт: `exec()`, `copyFileOut()`, `close()` — caller не зависит от конкретного типа sandbox.

### 3.2 🟡 Branch Strategy + Git Worktree Management (WorktreeManager.ts)

**Что у них:** Три стратегии управления ветками:

| Стратегия | Поведение | Bind-mount | Isolated |
| --- | --- | --- | --- |
| `head` | Прямая запись в host WD, нет worktree | Default | N/A |
| `merge-to-head` | Temp branch → merge → HEAD → cleanup | Supported | Default |
| `branch` | Explicit named branch | Supported | Supported |

Worktree management: `create()`, `pruneStale()`, `hasUncommittedChanges()`, `remove()`. Stale worktrees auto-pruned перед созданием новых. Dirty worktrees preserved при ошибке (with review/cleanup instructions).

**Оркестрационная значимость:** Git worktrees обеспечивают файловую изоляцию при параллельных запусках агентов в одном репозитории. Реализация включает: auto-pruning stale worktrees (по convention naming), сохранение dirty worktrees при ошибке (с инструкциями для ручного review/cleanup), и merge-to-head стратегию с error recovery (временная ветка → merge → HEAD → cleanup). Сравнение: Archon (`IIsolationProvider`) предоставляет абстракцию изоляции без встроенного управления ветками; Sandcastle реализует полный lifecycle от создания worktree до merge и cleanup.

### 3.3 🟡 Structured Output (Output.ts + extractStructuredOutput.ts)

**Что у них:** Типизированный вывод из agent stdout через XML-теги:

```typescript
// Schema-validated JSON payload
const result = await run({
 agent: claudeCode("claude-opus-4-6"),
 sandbox: docker(),
 prompt: "...",
 output: Output.object({
 tag: "result",
 schema: z.object({ summary: z.string(), score: z.number() }),
 }),
});
console.log(result.output.summary); // typed as string
```

Ограничение: `maxIterations === 1` и тег должен быть в промпте. Также `Output.string({ tag })` для plain string extraction.

**Оркестрационная значимость:** Дополнение к quality gates: вместо shell-команды для проверки — agent сам возвращает структурированный результат. Архитектурное решение (ADR 0010): Sandcastle сознательно не инжектит тег в промпт — caller обязан инструктировать агента. Для PHP: JSON Schema валидация через Symfony Validator.

### 3.4 🟡 Completion Signal (early termination)

**Что у них:** Agent эмитит `<promise>COMPLETE</promise>` в stdout — оркестратор останавливает iteration loop:

```typescript
// Default: "<promise>COMPLETE</promise>"
// Custom:
completionSignal: "DONE",
// Multiple signals:
completionSignal: ["TASK_COMPLETE", "TASK_ABORTED"],
```

Matched signal возвращается в `result.completionSignal`. Чистый termination signal — без payload, в отличие от structured output.

**Оркестрационная значимость:** Аналог `until_bash` из Archon, но на уровне agent output. Усиление fix_iterations: если agent сигнализирует завершение раньше max_iterations, loop останавливается. В отличие от until_bash (deterministic shell check), completion signal — probabilistic (agent может забыть эмитить).

### 3.5 🟡 Prompt Template Engine (PromptPreprocessor.ts + PromptArgumentSubstitution.ts)

**Что у них:** Двухфазная обработка промптов:

1. **{{KEY}} substitution** — на host, до sandbox: `promptArgs: { ISSUE: "42" }` → заменяет `{{ISSUE}}` в промпте
2. **!\`command\` expansion** — внутри sandbox, после onSandboxReady: `` !`gh issue list --json` `` → заменяется на stdout команды

Built-in аргументы: `{{SOURCE_BRANCH}}`, `{{TARGET_BRANCH}}` (auto-injected, cannot override). Inline prompts (`prompt: "..."`) skip всю обработку — передаются агенту как есть.

**Оркестрационная значимость:** Для chain templates — richer prompt templating чем простой payload. Dynamic context injection через shell commands — способ обогатить промпт актуальными данными (issue list, git log, test results) без хардкода.

### 3.6 🟡 Typed Error Hierarchy (errors.ts)

**Что у них:** 20+ typed error classes через Effect-TS `Data.TaggedError`:

| Категория | Ошибки |
| --- | --- |
| Agent | `AgentError`, `AgentIdleTimeoutError` |
| Sandbox | `ExecError`, `CopyError`, `DockerError`, `PodmanError` |
| Git | `SyncError`, `WorktreeError`, `WorktreeTimeoutError` |
| Prompt | `PromptError`, `PromptExpansionTimeoutError` |
| Lifecycle | `HookTimeoutError`, `GitSetupTimeoutError`, `ContainerStartTimeoutError` |
| Session | `SessionCaptureError` |
| Timeout | `CommitCollectionTimeoutError`, `CopyToWorktreeTimeoutError`, `MergeToHostTimeoutError`, `SyncInTimeoutError` |

Все ошибки включают контекст: command, timeoutMs, path, sessionId. `AgentIdleTimeoutError` и `AgentError` содержат `preservedWorktreePath` — для programmatic recovery.

**Оркестрационная значимость:** Модель для нашей error classification. Tagged discriminated union (через `type` поле или `instanceof`) — PHP аналог: enum-backed exception hierarchy. Timeout-специфичные ошибки — хорошо для circuit breaker: timeout ≠ server error.

### 3.7 🟡 Multi-Agent Templates (src/templates/)

**Что у них:** 5 готовых шаблонов оркестрации:

| Шаблон | Фаза 1 | Фаза 2 | Фаза 3 |
| --- | --- | --- | --- |
| `blank` | — | — | — |
| `simple-loop` | Agent pick issue → implements → signals complete (completion signal) | — | — |
| `sequential-reviewer` | Implementer (Sonnet, maxIterations=100) | Reviewer (Sonnet, maxIterations=1, shared sandbox) | — |
| `parallel-planner` | Planner (Opus): structured `<plan>` JSON | N executors (Sonnet) parallel via Promise.allSettled, each on own branch | Merger (Sonnet): объединяет результаты |
| `parallel-planner-with-review` | Planner (Opus): structured `<plan>` JSON | N executors + N reviewers (Sonnet) parallel | Merger (Sonnet): объединяет результаты |

Ключевые паттерны:
- **Plan → Execute → Merge** — 3-phase pipeline с LLM-генерированным планом
- **Promise.allSettled** — один упавший агент не отменяет остальных
- **Per-agent branch** — каждый агент работает на своей ветке
- **Structured plan output** — `<plan>` JSON из planning agent → driver parsing → parallel execution
- **createSandbox()** для shared state между implementer и reviewer (sequential-reviewer)

**Оркестрационная значимость:** Шаблоны реализуют три паттерна координации нескольких агентов: итеративный loop (simple-loop), последовательный pipeline с разделённым sandbox (sequential-reviewer), параллельный plan-execute-merge (parallel-planner). В parallel-planner planning-агент генерирует JSON-план (`<plan>` тег), драйвер парсит его и запускает N execution-агентов через `Promise.allSettled` — один упавший агент не отменяет остальных. Каждый агент работает на отдельной git-ветке. В sequential-reviewer implementer и reviewer разделяют одну песочницу (через `createSandbox()`), reviewer видит все изменения implementer'а.

### 3.8 🟡 Effect-TS Functional Architecture

**Что у них:** Весь core построен на Effect-TS — функциональной системе эффектов:

- **Context.Tag + Layer** — dependency injection (замена Symfony DI)
- **Effect.gen** — do-notation для composition (аналог pipeline)
- **Effect.acquireUseRelease** — resource-safe lifecycle (sandbox create → use → cleanup)
- **TaggedError** — typed error channels (ошибки не теряются)
- **Deferred** — cooperative cancellation (AbortSignal racing)

`run()` — единственная async-функция в public API. Внутри сразу делегирует к Effect pipeline.

**Оркестрационная значимость:** Architectural reference для PHP: Effect-TS решает те же проблемы, что и DDD layers, но функционально.

---

## 4. Прочие возможности (вне оркестрации)

### 4.1 🟢 Agent Loop (прямое LLM API взаимодействие)

Sandcastle делегирует LLM-взаимодействие внешним агентам (Claude Code, Codex, Pi, OpenCode) через subprocess. Task-orchestrator делегирует runner'ам. Оба — оркестраторы, не LLM-клиенты. Разный уровень: Sandcastle управляет sandbox+branch, мы — chain+retry+CB+gates.

### 4.2 🟢 Effect-TS как runtime dependency

Effect-TS — мощный, но чуждый PHP-экосистеме фреймворк. Наши слои Domain/Application/Infrastructure + Symfony DI решают те же задачи проще для нашей аудитории.

### 4.3 🟢 Dockerfile / Container Image Management

`sandcastle init` scaffold'ает Dockerfile, `sandcastle docker build-image` — build. Управление Docker-образами — responsibility runner'ов, не оркестратора.

### 4.4 🟢 TUI / Interactive Mode

`interactive()` — интерактивная сессия с агентом через terminal UI (Clack). Task-orchestrator — batch pipeline, не interactive tool.

### 4.5 🟢 Session Resume (Claude Code --resume)

Resume предыдущей Claude Code сессии — специфично для Claude Code провайдера. Не применимо к абстрактным runner'ам.

### 4.6 🟢 Codex / Pi / OpenCode Agent Providers

Конкретные реализации AgentProvider — не переносимы в PHP. Переносим только интерфейс (AgentProvider → AgentRunnerInterface).

---

## 5. Сводка по оркестрации

| Возможность | Статус в продукте | Описание |
| --- | --- | --- |
| Sandbox Provider Interface | 🟡 P2 | Для CI/CD: Docker/Podman isolation runner'ов. Абстракция от Sandcastle + Codex — наиболее зрелая |
| Branch Strategy / Git Worktrees | 🟡 P3 | Для параллельных chain runs в одном репозитории. Подтверждено Archon + Sandcastle |
| Structured Output (schema validation) | 🟡 P2 | Дополнение к quality gates: agent возвращает типизированный JSON вместо текста |
| Completion Signal (early termination) | 🟡 P2 | Усиление fix_iterations: agent сигнализирует завершение раньше max_iterations |
| Prompt Template Engine ({{KEY}} + !`cmd`) | 🟡 P2 | Richer prompt templating для chain steps |
| Typed Error Hierarchy | 🟡 P2 | Error classification: timeout ≠ server error ≠ agent error. Дополнение к retry + CB |
| Multi-Agent Templates (plan → execute → merge) | 🟡 P3 | Модель для будущих dynamic chains: LLM-планирование → параллельное выполнение |
| Hooks (lifecycle) | 🟡 P3 | Pre/post execution hooks — дополнение к decorator pattern |
| Idle Timeout | 🟡 P2 | Защита от зависших агентов: configurable timeout с periodic warnings |
| AbortSignal / Cancellation | 🟡 P3 | Cooperative cancellation для долгих chain runs |
| Worktree Preservation | 🟡 P3 | Dirty state preservation при ошибке — для отладки failed chains |
| Agent Loop / LLM API | 🟢 — | Разный уровень абстракции |
| Effect-TS | 🟢 — | Чуждый PHP-экосистеме |
| TUI / Interactive Mode | 🟢 — | Разная парадигма (batch pipeline vs. interactive) |
| Container Image Management | 🟢 — | Responsibility runner'ов |

---

## 6. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории Sandcastle:

- [`README.md`](https://github.com/mattpocock/sandcastle/blob/main/README.md) — полная документация: API reference, sandbox providers, branch strategies, templates, hooks (1212 строк)
- [`src/run.ts`](https://github.com/mattpocock/sandcastle/blob/main/src/run.ts) — Main `run()` function: option validation, env resolution, prompt resolution, Effect pipeline assembly (~612 LOC)
- [`src/Orchestrator.ts`](https://github.com/mattpocock/sandcastle/blob/main/src/Orchestrator.ts) — Core orchestration: iteration loop, agent invocation, idle timeout, completion signal, session capture (~350 LOC)
- [`src/AgentProvider.ts`](https://github.com/mattpocock/sandcastle/blob/main/src/AgentProvider.ts) — AgentProvider interface + 4 implementations: claudeCode, codex, pi, opencode. Stream parsing per provider (~300 LOC)
- [`src/SandboxProvider.ts`](https://github.com/mattpocock/sandcastle/blob/main/src/SandboxProvider.ts) — SandboxProvider interface: BindMount, Isolated, NoSandbox. Branch strategy types. Factory functions (~250 LOC)
- [`src/SandboxFactory.ts`](https://github.com/mattpocock/sandcastle/blob/main/src/SandboxFactory.ts) — Sandbox lifecycle: worktree creation, container start, git mount resolution, cleanup (~610 LOC)
- [`src/SandboxLifecycle.ts`](https://github.com/mattpocock/sandcastle/blob/main/src/SandboxLifecycle.ts) — Git setup, hooks execution, commit collection, merge-to-head (~493 LOC)
- [`src/errors.ts`](https://github.com/mattpocock/sandcastle/blob/main/src/errors.ts) — Typed error hierarchy: 20+ error classes, timeout helpers (~150 LOC)
- [`src/WorktreeManager.ts`](https://github.com/mattpocock/sandcastle/blob/main/src/WorktreeManager.ts) — Git worktree CRUD: create, pruneStale, hasUncommittedChanges, remove, branch detection
- [`src/templates/parallel-planner/main.mts`](https://github.com/mattpocock/sandcastle/blob/main/src/templates/parallel-planner/main.mts) — Plan → Execute N parallel → Merge template
- [`src/templates/sequential-reviewer/main.mts`](https://github.com/mattpocock/sandcastle/blob/main/src/templates/sequential-reviewer/main.mts) — Implement → Review with shared sandbox
- [`CONTEXT.md`](https://github.com/mattpocock/sandcastle/blob/main/CONTEXT.md) — Глоссарий: терминология проекта (sandbox, host, agent, branch strategy, iteration, task, completion signal)
- [`CHANGELOG.md`](https://github.com/mattpocock/sandcastle/blob/main/CHANGELOG.md) — История версий (0.5.x), патчи и фичи
- [`package.json`](https://github.com/mattpocock/sandcastle/blob/main/package.json) — Метаданные: `@ai-hero/sandcastle`, dependencies (Effect-TS, Vitest)

📚 **Источники:**
1. [github.com/mattpocock/sandcastle](https://github.com/mattpocock/sandcastle) — репозиторий проекта
2. [README.md](https://github.com/mattpocock/sandcastle/blob/main/README.md) — полная документация API, sandbox providers, templates
3. [CONTEXT.md](https://github.com/mattpocock/sandcastle/blob/main/CONTEXT.md) — глоссарий и терминология проекта
4. [docs/adr/](https://github.com/mattpocock/sandcastle/tree/main/docs/adr) — Architectural Decision Records (14 ADR)
5. [sandcastle.dev](https://sandcastle.dev) — сайт проекта (Matt Pocock / AI Hero)
