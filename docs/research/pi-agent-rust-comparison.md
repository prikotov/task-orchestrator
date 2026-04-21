# Исследование: Dicklesworthstone/pi_agent_rust — AI Coding Agent CLI (Rust)

> **Проект:** [github.com/Dicklesworthstone/pi_agent_rust](https://github.com/Dicklesworthstone/pi_agent_rust)
> **Дата анализа:** 2026-04-21
> **Язык:** Rust (2024 edition, nightly)
> **Лицензия:** MIT + Rider
> **Аналитик:** Технический писатель (Гермиона)

---

## 1. Обзор проекта

pi_agent_rust — высокопроизводительный AI-кодинг-агент CLI, порт оригинального TypeScript Pi Agent ([badlogic/pi](https://github.com/badlogic/pi)). Ключевая идея: single binary с мгновенным стартом, низким потреблением памяти, streaming-first архитектурой и продвинутой системой расширений с capability-gated security.

pi_agent_rust **не является** фреймворком оркестрации агентов. Это **одноагентный CLI-инструмент** для интерактивной работы разработчика с LLM через терминал. В отличие от task-orchestrator, pi_agent_rust не поддерживает цепочки шагов, retry-механизмы с backoff, circuit breaker, budget control, quality gates или итерационные циклы (fix_iterations).

### Архитектура

```
src/
├── main.rs                  CLI entry point (clap), режимы (interactive/print/rpc)
├── agent.rs                 Agent loop: LLM → tool call → LLM → ... (332KB)
├── agent_cx.rs              AgentCx: capability-scoped async context
├── cli.rs                   CLI-аргументы (clap derive)
├── config.rs                Settings load/merge (JSON), precedence chain
├── auth.rs                  API key storage, provider credential resolution
├── models.rs                Model registry + resolver (162KB)
├── model.rs                 Message/content types, StreamEvent
├── provider.rs              Provider trait + Context/StreamOptions
├── providers/               11 native провайдеров (Anthropic, OpenAI, Gemini, Cohere, Azure, Bedrock, Vertex, Copilot, GitLab)
├── tools.rs                 8 встроенных инструментов (read/write/edit/bash/grep/find/ls/hashline_edit)
├── session.rs               JSONL session persistence (v3, tree branching)
├── session_index.rs         SQLite session index (metadata, search)
├── session_store_v2.rs      V2 sidecar store (segmented log + offset index)
├── session_sqlite.rs        SQLite session backend (optional)
├── compaction.rs            Context compaction: token estimation, cut-point, summarization
├── compaction_worker.rs     Background compaction worker
├── extensions.rs            Extension system: policy, trust lifecycle, hostcall mesh (1.9MB)
├── extensions_js.rs         QuickJS runtime bridge, hostcalls, Node shims (987KB)
├── extension_*.rs           Extension validation, scoring, licensing, conformance и т.д.
├── hostcall_*.rs            Hostcall lanes: AMAC, io_uring, queue, S3-FIFO, trace JIT
├── permissions.rs           Persistent capability decisions (allow/deny per extension)
├── scheduler.rs             Deterministic event loop для JS runtime
├── resources.rs             Skills, prompts, themes, extensions: discovery + loading
├── package_manager.rs       Package lifecycle (install, remove, update)
├── sse.rs                   Custom SSE parser (streaming responses)
├── interactive.rs           TUI: Bubble Tea v2 (charmed_rust)
├── rpc.rs                   RPC mode: JSON protocol over stdin/stdout
├── autocomplete.rs          Context-aware autocomplete (@files, /commands)
├── tui.rs                   TUI rendering helpers (rich_rust)
└── error.rs                 Structured error types (thiserror)
```

### Ключевые характеристики

| Характеристика | Значение |
|---|---|
| **Тип** | CLI-агент (TUI), одноагентный |
| **Модель выполнения** | Agent loop (LLM → tool call → LLM → ...) с max 50 итераций |
| **State management** | JSONL (v3, tree branching) + SQLite index + optional SQLite backend |
| **Провайдеры** | 11 native (Anthropic, OpenAI Chat/Responses, Gemini, Cohere, Azure, Bedrock, Vertex, Copilot, GitLab) + extension-provided |
| **Расширяемость** | Extensions (QuickJS/WASM), Skills (SKILL.md), Prompt templates, Packages |
| **Интерфейс** | Interactive TUI (Bubble Tea v2) / Print mode / RPC mode / SDK |
| **Безопасность** | Capability-gated hostcalls, trust lifecycle, command-level exec mediation |
| **Бинарный размер** | <22MB (CI-gated budget) |
| **Unsafe код** | Forbidden (`#![forbid(unsafe_code)]`) |

### Основные компоненты

| Компонент | Назначение |
|---|---|
| [`src/agent.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/agent.rs) | Agent loop: streaming, tool execution, message queue, abort/timeout |
| [`src/provider.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/provider.rs) | Provider trait: async streaming, Context (Cow-optimized), ToolDef |
| [`src/tools.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/tools.rs) | 8 инструментов: read, write, edit, bash, grep, find, ls, hashline_edit |
| [`src/session.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/session.rs) | JSONL v3: tree branching, compaction, autosave, durability modes |
| [`src/compaction.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/compaction.rs) | Auto-compaction: token estimation, cut-point, LLM summarization |
| [`src/extensions.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/extensions.rs) | Extension system: QuickJS runtime, capability policy, trust lifecycle |
| [`src/permissions.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/permissions.rs) | Persistent permission store: per-extension allow/deny decisions |
| [`src/config.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/config.rs) | Configuration: precedence chain (CLI → env → project → global → defaults) |
| [`src/resources.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/resources.rs) | Resource discovery: skills, prompts, themes, extensions |
| [`src/sse.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/sse.rs) | Custom SSE parser: UTF-8, chunk boundaries, BOM handling |
| [`src/scheduler.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/scheduler.rs) | Deterministic event loop для QuickJS: microtasks, macrotasks, timers |

---

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | TasK Orchestrator | pi_agent_rust | Статус |
|---|---|---|---|
| **Цепочки шагов (chains)** | ✅ YAML chains, статические и динамические | ❌ Agent loop (LLM → tool call → ...) без декларативных цепочек | ✅ У нас есть |
| **Retry с backoff** | ✅ RetryingAgentRunner | ⚠️ Basic retry (max_retries=3, exponential backoff в config) | ✅ У нас лучше (decorator pattern) |
| **Circuit Breaker** | ✅ CircuitBreakerAgentRunner | ❌ Нет | ✅ У нас есть |
| **Quality Gates** | ✅ Shell-команды как проверки | ❌ Нет | ✅ У нас есть |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based) | ❌ Только cost tracking (session metrics), без лимитов/блокировок | ✅ У нас есть |
| **Итерационные циклы (fix_iterations)** | ✅ Группа шагов с max_iterations | ❌ Нет (agent loop повторяет tool calls, но не «developer → reviewer → developer») | ✅ У нас есть |
| **Fallback routing** | ✅ Per-step fallback runner | ⚠️ Model auto-select fallback (3 модели в цепочке), но не per-step | ✅ У нас лучше |
| **Audit Trail (JSONL)** | ✅ JsonlAuditLogger | ✅ JSONL session v3 (tree-structured) + session index (SQLite) | ✅ Паритет (у них богаче) |
| **Ролевые промпты** | ✅ .md файлы (18+ ролей) | ✅ System prompt + skills (SKILL.md) + prompt templates | ✅ У нас лучше (множество ролей) |
| **Multiple runners** | ✅ Pi + Codex (через interface) | ✅ 11 native провайдеров + extension-provided | ✅ У них шире |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❌ Плоская структура `src/` (все в одном crate) | ✅ У нас лучше |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Прямой вызов Provider | ✅ У нас лучше |
| **YAML-конфигурация** | ✅ Chains + roles в YAML | ✅ settings.json (JSON) | ✅ Паритет (разные форматы) |
| **Session persistence** | ❌ In-memory + JSONL audit | ✅ JSONL v3 (tree branching) + SQLite index + v2 sidecar | 🟡 Интересно |
| **Auto-compaction** | ❌ Нет | ✅ Token estimation, cut-point, LLM summarization, background worker | 🟡 Интересно |
| **Extension system** | ❌ Нет | ✅ QuickJS/WASM, capability-gated hostcalls, trust lifecycle | 🟡 Позже |
| **Permission system** | ❌ Нет | ✅ Per-extension capability decisions, persistent, scoped by version | 🟡 Позже |
| **Tool parallelism** | ❌ Нет | ✅ MAX_CONCURRENT_TOOLS=8, read-only tools параллельно | 🟡 Интересно |
| **RPC mode** | ❌ Нет | ✅ JSON protocol over stdin/stdout (для IDE integrations) | 🟢 Не берём |
| **TUI** | ❌ CLI Symfony Console | ✅ Bubble Tea v2 (charmed_rust) + rich_rust | 🟢 Не берём |
| **Custom SSE parser** | ❌ Нет (зависит от runner) | ✅ Zero-copy, UTF-8 aware, chunk boundary handling | 🟢 Не берём |
| **Context file discovery** | ✅ AGENTS.md, role .md | ✅ AGENTS.md, + стандарт agentskills.io | ✅ Паритет |
| **Structured concurrency** | ❌ Нет (PHP/Symfony) | ✅ asupersync: structured cancellation, capability context | 🟢 Разный стек |
| **Retry config** | ✅ В YAML, per-step | ✅ В settings.json (global: enabled, max_retries, base/max delay) | ✅ Паритет |
| **Streaming** | ✅ Через runner | ✅ Native SSE parser, streaming-first architecture | ✅ Паритет |
| **Cost tracking** | ✅ Через budget/check | ✅ Per-session token/cost metrics (session_metrics.rs) | ✅ Паритет |

---

## 3. Что полезно взять и почему

### 3.1 🟡 Auto-Compaction — управление контекстом при длинных сессиях (`src/compaction.rs`)

**Что у них:** Когда суммарное число токенов (prompt + completion) приближается к context window модели, агент автоматически:

1. Оценивает текущее потребление токенов (conservative estimate: ~3 chars/token + image estimates)
2. Выбирает cut-point (предпочитает user-turn boundaries, сохраняет `keep_recent_tokens`)
3. Вызывает LLM для суммаризации «отброшенной» части
4. Заменяет старую историю на summary + недавние сообщения
5. Работает в background (`compaction_worker.rs`)

```rust
pub struct ResolvedCompactionSettings {
    pub enabled: bool,
    pub context_window_tokens: u32,  // 128K default
    pub reserve_tokens: u32,          // ~8% of context window
    pub keep_recent_tokens: u32,      // ~10% of context window
}
```

**Почему нам интересно:** Для длинных цепочек (implement → review → fix → review → ...) с большим количеством шагов, контекст может расти. Если chain step передаёт весь контекст в runner — auto-compaction позволяет работать с arbitrarily длинными цепочками.

**Отличие от нашей реализации:** У нас нет механизма compaction. Цепочки конечные (max_iterations), поэтому переполнение контекста менее вероятно. Но для future dynamic loops может стать актуальным.

---

### 3.2 🟡 Session Persistence — JSONL v3 с tree branching (`src/session.rs`)

**Что у них:** Sessions хранятся как JSONL файлы с tree-structured историей:

- Каждая запись имеет `parent_id` → образует дерево
- Branching: пользователь может «откатиться» и пойти по другому пути
- Session index (SQLite): метаданные для быстрого поиска
- V2 sidecar store: segmented log + offset index для O(index+tail) resume
- Durability modes: `strict` / `balanced` / `throughput`

**Почему нам интересно:** Наш JSONL audit trail — append-only log. Tree-structured sessions позволяют:
- Ветвление выполнения (альтернативные пути в chain)
- Откат к предыдущему состоянию (undo шага)
- Параллельные ветки экспериментов

**Отличие от нашей реализации:** У нас `JsonlAuditLogger` — однонаправленный лог. Tree branching — это более богатая модель, но и более сложная в реализации.

---

### 3.3 🟡 Extension System — QuickJS/WASM с capability policy (`src/extensions.rs`)

**Что у них:** Многоуровневая система расширений:

1. **Runtime tiers:** QuickJS (JS/TS), Native descriptors (JSON), WASM (future)
2. **Capability-gated hostcalls:** `tool`, `exec`, `http`, `session`, `ui`, `events`
3. **Trust lifecycle:** `pending` → `acknowledged` → `trusted` → `killed`
4. **Command-level exec mediation:** блокировка опасных shell-команд до spawn
5. **Policy profiles:** `safe` / `balanced` / `permissive`
6. **Runtime risk ledger:** hash-linked, tamper-evident audit trail
7. **Hostcall reactor mesh:** deterministic shard routing, backpressure

**Почему нам интересно:** Если task-orchestrator будет поддерживать пользовательские runners/tools — нужна система контроля, что именно runner может делать. Capability-gated подход — лучшая практика для extension security.

**Отличие:** У нас runners реализуют `AgentRunnerInterface` — PHP-интерфейс. Нет capability model, trust lifecycle или policy enforcement.

---

### 3.4 🟡 Permission System — persistent decisions (`src/permissions.rs`)

**Что у них:** Persistent storage для capability decisions:

```rust
pub struct PersistedDecision {
    pub capability: String,     // "exec", "http", etc.
    pub allow: bool,
    pub decided_at: String,     // ISO-8601
    pub expires_at: Option<String>,
    pub version_range: Option<String>,  // semver range
}
```

- Ключ: `(extension_id, capability)`
- Scoping: по версии расширения (semver range)
- Expiry: опциональный TTL для decisions
- Atomic write: temp file + rename

**Почему нам интересно:** Для автономного выполнения цепочек (без участия человека) — нужна система: какие команды можно выполнять, какие файлы редактировать. Persistent decisions позволяют один раз дать разрешение и не спрашивать каждый раз.

---

### 3.5 🟡 Tool Parallelism — параллельное выполнение read-only tools (`src/agent.rs`)

**Что у них:** Agent loop поддерживает параллельное выполнение tool calls:

```rust
const MAX_CONCURRENT_TOOLS: usize = 8;
```

- Read-only tools (`read`, `grep`, `find`, `ls`) выполняются параллельно
- Write tools (`write`, `edit`, `bash`) — последовательно
- Tool trait имеет метод `is_read_only()` → `bool`

**Почему нам интересно:** В наших цепочках все шаги выполняются последовательно. Если несколько шагов в chain независимы — можно выполнять их параллельно. Это особенно полезно для dynamic chains, где LLM может вызывать несколько инструментов одновременно.

---

### 3.6 🟡 Retry Configuration — global settings с exponential backoff (`src/config.rs`)

**Что у них:** Retry настраивается глобально в `settings.json`:

```json
{
  "retry": {
    "enabled": true,
    "max_retries": 3,
    "base_delay_ms": 1000,
    "max_delay_ms": 30000
  }
}
```

**Почему нам интересно:** У нас retry реализован через `RetryingAgentRunner` decorator. Конфигурация pi_agent_rust — более прозрачная (явные параметры). Наш подход через decorator pattern — более гибкий (per-step, per-runner), но конфигурация в YAML может быть менее очевидной.

---

### 3.7 🟡 Deterministic Scheduler — для extension runtime (`src/scheduler.rs`)

**Что у них:** Deterministic event loop для QuickJS runtime с формальными инвариантами:

- **I1:** At most one macrotask per tick
- **I2:** Microtasks drain to empty after each macrotask
- **I3:** Timers with equal deadlines fire in increasing seq order
- **I4:** Hostcall completions enqueue macrotasks, never re-enter
- **I5:** Total ordering by sequence number

**Почему нам интересно:** Концепция формальных инвариантов для execution loop применима к нашему chain executor. Мы могли бы формализовать инварианты выполнения цепочек: «каждый шаг получает результат предыдущего», «budget проверяется до и после каждого шага» и т.д.

---

## 4. Что НЕ берём и почему

### 4.1 🟢 TUI (Bubble Tea v2 / rich_rust)

pi_agent_rust — интерактивный TUI-клиент с богатым интерфейсом. Task-orchestrator — CLI-утилита для автоматического выполнения цепочек. Разные парадигмы: интерактивный чат vs. автоматизированный pipeline.

### 4.2 🟢 Custom SSE Parser

pi_agent_rust реализует собственный SSE parser для streaming LLM responses. Наш оркестратор работает через runner'ы (pi, codex), каждый из которых сам управляет streaming. Нам не нужен собственный SSE parser.

### 4.3 🟢 RPC Mode / SDK

pi_agent_rust поддерживает RPC mode (JSON over stdin/stdout) и Rust SDK для IDE-интеграций. Task-orchestrator — Symfony Console command. RPC/SDK для IDE — другая область.

### 4.4 🟢 Structured Concurrency (asupersync)

asupersync — Rust-специфичный async runtime с structured concurrency. Наш стек — PHP/Symfony. Параллелизм в PHP реализуется через process-based подход, не async runtime.

### 4.5 🟢 QuickJS/WASM Extension Runtime

Встроенный JS/WASM runtime для расширений — это полноценная VM. Для PHP/Symfony бандла это не применимо. Расширяемость runner'ов через interface достаточна.

### 4.6 🟢 Model Registry (11+ провайдеров)

pi_agent_rust поддерживает 11 native провайдеров LLM. Наш оркестратор общается с LLM через runner'ы (pi, codex), которые сами работают с конкретными API. Нам не нужна собственная абстракция над провайдерами.

### 4.7 🟢 Performance Engineering (LTO, jemalloc, binary size)

Rust-специфичные оптимизации (LTO, jemalloc, single binary, <22MB) не применимы к PHP/Symfony стеку. Наша «performance» — это быстрое выполнение цепочек, не startup time.

### 4.8 🟢 Conformance Testing Pipeline (224 extension test suite)

Система из 224 тестов для валидации extension compatibility. Для наших runner'ов достаточно PHPUnit-тестов с моками.

---

## 5. Сводка рекомендаций

| Фича | Приоритет | Обоснование |
|---|---|---|
| Chain orchestration | ✅ Уже есть | Core-функциональность task-orchestrator |
| Retry + Circuit Breaker | ✅ Уже есть | Устойчивость при сбоях |
| Quality Gates | ✅ Уже есть | Автоматическая проверка кода |
| Budget control | ✅ Уже есть | Предотвращение runaway spending |
| Fix iterations | ✅ Уже есть | Closed-loop цикл разработки |
| Fallback routing | ✅ Уже есть | Per-step fallback runner |
| Audit Trail | ✅ Уже есть | JSONL audit logger |
| Auto-compaction | 🟡 P3 | Для длинных dynamic loops, не критично сейчас |
| Session persistence (tree) | 🟡 P3 | Для branching и undo в цепочках |
| Extension/Permission system | 🟡 P3 | Для автономного выполнения и custom runners |
| Tool parallelism | 🟡 P2 | Параллельное выполнение read-only шагов в chain |
| Retry config transparency | 🟡 P3 | Явные параметры (base_delay_ms, max_delay_ms) |
| Deterministic execution invariants | 🟡 P3 | Формализация инвариантов chain executor |
| TUI | 🟢 — | Разная парадигма |
| SSE parser | 🟢 — | Задача runner'ов |
| RPC/SDK | 🟢 — | Не нужно для pipeline |
| asupersync runtime | 🟢 — | Разный стек |
| QuickJS/WASM runtime | 🟢 — | Не применимо к PHP |
| Model registry | 🟢 — | Задача runner'ов |
| Perf engineering | 🟢 — | Разный стек |
| Conformance testing pipeline | 🟢 — | PHPUnit достаточно |

---

## 6. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории pi_agent_rust:

- [`src/agent.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/agent.rs) — Agent loop: streaming, tool execution, message queue, abort/timeout, concurrent tools
- [`src/provider.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/provider.rs) — Provider trait: async streaming, Context (Cow-optimized), StreamOptions, ToolDef
- [`src/tools.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/tools.rs) — 8 инструментов: Tool trait, read-only parallelism, truncation, process tree cleanup
- [`src/session.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/session.rs) — JSONL v3: tree branching, compaction, autosave, durability modes
- [`src/compaction.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/compaction.rs) — Auto-compaction: token estimation, cut-point, LLM summarization
- [`src/extensions.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/extensions.rs) — Extension system: capability policy, trust lifecycle, hostcall mesh
- [`src/permissions.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/permissions.rs) — Persistent permission store: per-extension decisions, semver scoping, expiry
- [`src/config.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/config.rs) — Configuration: precedence chain, retry settings, compaction settings
- [`src/scheduler.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/scheduler.rs) — Deterministic event loop: formal invariants I1–I5
- [`src/resources.rs`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/src/resources.rs) — Resource discovery: skills, prompts, themes, extensions, collision detection
- [`README.md`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/README.md) — Full feature documentation: modes, tools, extensions, configuration
- [`AGENTS.md`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/AGENTS.md) — Architecture overview, key files, performance targets, testing
- [`PROPOSED_ARCHITECTURE.md`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/PROPOSED_ARCHITECTURE.md) — Module layout, data flow, storage strategy
- [`EXTENSIONS.md`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/EXTENSIONS.md) — Extension architecture: runtime tiers, connector model, event loop bridge
- [`Cargo.toml`](https://github.com/Dicklesworthstone/pi_agent_rust/blob/main/Cargo.toml) — Dependencies, features, release profile

---

📚 **Источники:**
1. [github.com/Dicklesworthstone/pi_agent_rust](https://github.com/Dicklesworthstone/pi_agent_rust) — репозиторий проекта
2. [github.com/badlogic/pi](https://github.com/badlogic/pi) — оригинальный TypeScript Pi Agent
3. [agentskills.io](https://agentskills.io) — стандарт Agent Skills (SKILL.md)
4. [github.com/Dicklesworthstone/asupersync](https://github.com/Dicklesworthstone/asupersync) — structured concurrency runtime
5. [github.com/Dicklesworthstone/rich_rust](https://github.com/Dicklesworthstone/rich_rust) — terminal UI library (Rust port of Rich)
