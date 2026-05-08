# Исследование: Hermes Agent — self-improving AI agent (Nous Research)

> **Проект:** [github.com/NousResearch/hermes-agent](https://github.com/NousResearch/hermes-agent)
> **Дата анализа:** 2026-05-07
> **Язык:** Python
> **Лицензия:** MIT
> **Версия:** v0.12.0+
> **Аналитик:** Аналитик (Шерлок)

---

## 1. Обзор проекта

Hermes Agent — **self-improving AI agent** от Nous Research, позиционируемый как «агент, который растёт вместе с вами». Ключевая идея: замкнутый цикл обучения (closed learning loop) — агент создаёт навыки из опыта, улучшает их при использовании, побуждает себя к сохранению знаний, ищет в собственных прошлых диалогах и углубляет модель пользователя между сессиями.

> ⚠️ **Примечание:** Проект вырос из OpenClaw (TypeScript, Node.js). Hermes — полная переработка на Python с нуля, включающая встроенную миграцию из OpenClaw (`hermes claw migrate`). Репозиторий набрал 136K+ stars, 21K+ forks — один из самых популярных AI-agent проектов на GitHub.

Архитектура Hermes в корне отличается от task-orchestrator: Hermes — **полноценный интерактивный AI-ассистент** с прямым доступом к LLM API (200+ моделей через OpenRouter, Nous Portal, Anthropic, OpenAI, NVIDIA NIM и др.), инструментам (40+), браузеру, файловой системе, терминалу, мессенджерам (Telegram, Discord, Slack, WhatsApp, Signal) и persistent-памяти. Task-orchestrator — оркестратор поверх runner'ов, не работающий с LLM API напрямую.

### Архитектура

```
┌─────────────────────────────────────────────────────────────┐
│ Presentation Layer │
│ TUI (Ink/React) │ CLI (prompt_toolkit) │ Web UI │
│ Gateway (Telegram, Discord, Slack, WhatsApp, Signal, ...) │
└──────────────────────────┬──────────────────────────────────┘
 │
 ▼
┌─────────────────────────────────────────────────────────────┐
│ AIAgent (run_agent.py) │
│ Core conversation loop (~12k LOC) │
│ • Agent loop: LLM → tool call → result → LLM → ... │
│ • Context compression (auto-compact) │
│ • Budget tracking, credential pool, error classification │
│ • Subagent spawning (delegate_task) │
└──────────────────────────┬──────────────────────────────────┘
 │
 ┌───────────┴───────────┐
 ▼ ▼
┌────────────────────┐ ┌────────────────────────────────────┐
│ Tool Registry │ │ Agent Internals │
│ (tools/registry) │ │ error_classifier.py │
│ 40+ tools, │ │ context_compressor.py (~1500 LOC) │
│ toolsets system, │ │ credential_pool.py │
│ MCP integration │ │ memory_manager.py │
│ Skills (SKILL.md) │ │ prompt_builder.py │
│ │ │ rate_limit_tracker.py │
│ │ │ context_references.py │
│ │ │ curator.py (skill improvement) │
└────────┬───────────┘ └────────────────────────────────────┘
 │
 ▼
┌─────────────────────────────────────────────────────────────┐
│ Terminal Backends (tools/environments/) │
│ local │ docker │ ssh │ singularity │ modal │ daytona │ │
│ vercel_sandbox │
└──────────────────────────┬──────────────────────────────────┘
 │
 ▼
┌─────────────────────────────────────────────────────────────┐
│ SQLite (session DB with FTS5 search) │
│ ~/.hermes/ (config, memory, skills, checkpoints)│
└─────────────────────────────────────────────────────────────┘
```

### Ключевые характеристики

| Характеристика | Значение |
| --- | --- |
| **Тип** | Интерактивный AI-агент с closed learning loop |
| **Модель выполнения** | Agent loop (LLM → tool call → result → LLM → ...) |
| **AI-провайдеры** | 200+ через OpenRouter, Nous Portal, Anthropic, OpenAI, Bedrock, Gemini, NVIDIA NIM, Xiaomi MiMo, z.ai/GLM, Kimi/Moonshot, MiniMax, Hugging Face, llama.cpp, и др. |
| **State management** | SQLite (FTS5 session search) + file-based memory (MEMORY.md, USER.md) + Honcho dialectic user modeling |
| **Контекст** | Auto-compaction с structured summarization, tool result pruning, anti-thrashing protection |
| **Расширяемость** | 40+ tools, MCP, SKILL.md (agentskills.io standard), Plugin system (memory/context_engine/model-providers/kanban/observability), Toolset system |
| **Терминальные бэкенды** | 7: local, Docker, SSH, Singularity, Modal (serverless), Daytona, Vercel Sandbox |
| **Мессенджеры** | 15+: Telegram, Discord, Slack, WhatsApp, Signal, Matrix, Mattermost, Email, SMS, DingTalk, WeCom, Weixin, Feishu, QQBot, BlueBubbles |
| **Безопасность** | Context file injection scanning, path security, command approval, file safety, tirith security |
| **Мультиагентность** | Subagent spawning (delegate_task), Kanban board coordination, Mixture-of-Agents |

### Основные модули

| Модуль | Назначение |
| --- | --- |
| `run_agent.py` (~12k LOC) | AIAgent — core conversation loop, retry, budget, context management |
| `model_tools.py` | Tool orchestration, `discover_builtin_tools()`, `handle_function_call()` |
| `toolsets.py` | Toolset definitions — compositional grouping of tools |
| `cli.py` (~11k LOC) | HermesCLI — interactive CLI with prompt_toolkit |
| `hermes_state.py` | SessionDB — SQLite session store с FTS5 search |
| `agent/error_classifier.py` | API error classification taxonomy (20+ failover reasons) |
| `agent/context_compressor.py` (~1500 LOC) | Context compaction: structured summarization, tool result pruning |
| `agent/credential_pool.py` | Multi-credential pool с rotation strategies |
| `agent/memory_manager.py` | Persistent memory orchestration (Honcho, mem0, supermemory plugins) |
| `agent/curator.py` | Autonomous skill creation and improvement |
| `agent/prompt_builder.py` | System prompt assembly: identity, skills, context files, memory |
| `tools/delegate_tool.py` | Subagent spawning: parallel execution, orchestrator/leaf roles |
| `tools/checkpoint_manager.py` | Filesystem snapshots via shared shadow git store |
| `tools/environments/` | 7 terminal backends (local, Docker, SSH, Modal, Daytona, Singularity, Vercel Sandbox) |
| `cron/` | Built-in cron scheduler for automations |
| `gateway/` | Messaging gateway: Telegram, Discord, Slack, WhatsApp, Signal и др. |
| `plugins/` | Plugin system: memory, context_engine, model-providers, kanban, observability |

---

## 2. Возможности оркестрации — обзор

| Функция | Hermes Agent |
| --- | --- |
| **DAG / Workflow engine** | ❌ Только линейные/динамические цепочки |
| **Retry с backoff** | ✅ Встроенный retry с credential rotation и error classification |
| **Бюджетный контроль** | ✅ Iteration budget + token tracking + cost display (`/usage`) |
| **Итерационные циклы (fix_iterations)** | ⚠️ Agent loop с `max_iterations` (90 default), но без явного «fix loop» |
| **Fallback routing** | ✅ Credential pool rotation + fallback model + multi-provider support |
| **Error classification** | ✅ 20+ failover reasons: auth, billing, rate_limit, overloaded, timeout, context_overflow, model_not_found, format_error, provider_policy_blocked и др. |
| **Credential rotation** | ✅ Multi-credential pool: fill_first, round_robin, random, least_used |
| **Context compression** | ✅ Structured summarization (14-section template), tool result pruning, anti-thrashing, iterative summary updates |
| **Sub-agents** | ✅ `delegate_task`: parallel spawning, orchestrator/leaf roles, depth control (max 3), blocked tools list, timeout per child |
| **Kanban multi-agent** | ✅ Shared SQLite board, worker spawning, heartbeat, blocking, parent-child handoffs |
| **Skills system** | ✅ SKILL.md (agentskills.io standard), autonomous creation, self-improvement (curator), Skills Hub marketplace |
| **Memory (persistent)** | ✅ MEMORY.md + USER.md + Honcho dialectic modeling + FTS5 session search + memory provider plugins |
| **Session persistence** | ✅ SQLite + FTS5 search across sessions + session resume |
| **Filesystem checkpoints** | ✅ Shadow git store, per-turn snapshots, rollback to any checkpoint |
| **Terminal backends** | ✅ 7 backends: local, Docker, SSH, Singularity, Modal, Daytona, Vercel Sandbox |
| **MCP** | ✅ MCP client integration |
| **Messaging platforms** | ✅ 15+ платформ: Telegram, Discord, Slack, WhatsApp, Signal, Matrix и др. |
| **Cron scheduling** | ✅ Built-in cron: natural language → scheduled execution → platform delivery |
| **Context file injection scanning** | ✅ Prompt injection detection (11 threat patterns), invisible unicode detection |
| **DDD-архитектура** | ❌ Monorepo modules (agent/, tools/, gateway/, plugins/) |
| **Decorator pattern** | ❌ Прямой вызов через tool registry |
| **Structured output** | ❌ Нет (текстовый вывод runner'ов) |
| **Rate limit tracking** | ✅ x-ratelimit-* header parsing (RPM/TPM per minute/hour), `/usage` display |
| **Audit Trail (JSONL)** | ✅ JSONL logs + SQLite session DB + trajectory export |
| **Plugin system** | ✅ plugins/: memory, context_engine, model-providers, kanban, observability, image_gen, achievements |
| **AGENTS.md support** | ✅ Hierarchical discovery (`.hermes.md` / `HERMES.md`), injection scanning |

---

## 3. Оркестрационные возможности

### 3.1 🟡 Error Classification Taxonomy (`agent/error_classifier.py`)

**Что у них:** Централизованная классификация API-ошибок с 20+ `FailoverReason`:

```python
class FailoverReason(enum.Enum):
 auth = "auth" # 401/403 — refresh/rotate
 auth_permanent = "auth_permanent" # Auth failed after refresh — abort
 billing = "billing" # 402 — rotate immediately
 rate_limit = "rate_limit" # 429 — backoff then rotate
 overloaded = "overloaded" # 503/529 — backoff
 server_error = "server_error" # 500/502 — retry
 timeout = "timeout" # Connection timeout — rebuild client
 context_overflow = "context_overflow" # Context too large — compress
 model_not_found = "model_not_found" # 404 — fallback model
 provider_policy_blocked = "provider_policy_blocked"
 # + format_error, thinking_signature, long_context_tier и др.
```

Каждая классификация содержит recovery hints: `retryable`, `should_compress`, `should_rotate_credential`, `should_fallback`.

**Оркестрационная значимость:** Классификация ошибок управляет поведением agent loop: каждая категория определяет конкретную стратегию восстановления — `retryable` вызывает повторный запрос с backoff, `should_rotate_credential` переключает API key, `should_compress` запускает context compression, `should_fallback` переводит на резервную модель. Например, `context_overflow` → compression + retry, `rate_limit` → backoff + credential rotation, `auth_permanent` → немедленный abort. Это превращает error handling из «retry на всё» в детерминированную таблицу решений, где каждая категория ошибок mapped на конкретное действие.

### 3.2 🟡 Structured Context Compression (`agent/context_compressor.py`, ~1500 LOC)

**Что у них:** Многофазный контекстный компрессор:

1. **Pre-pass: tool result pruning** — замена старых tool results на информативные summaries (`[terminal] ran 'npm test' → exit 0, 47 lines output`), дедупликация идентичных результатов, truncation больших tool_call arguments
2. **Tail protection by token budget** — protect head (system prompt + first exchange) + N токенов tail; boundary alignment для tool_call/result pairs
3. **Structured LLM summarization** — 14-секционный шаблон (Active Task, Goal, Completed Actions, Active State, In Progress, Blocked, Key Decisions, Resolved Questions, Pending User Asks, Relevant Files, Remaining Work, Critical Context)
4. **Iterative summary updates** — при повторной компрессии обновляет предыдущую summary вместо создания с нуля
5. **Anti-thrashing protection** — если две последовательные компрессии сэкономили <10% каждая, skip compression
6. **Tool pair integrity** — cleanup orphaned tool_call/tool_result pairs после компрессии
7. **Last user message anchoring** — гарантия, что последнее сообщение пользователя всегда в protected tail (bug #10896)

**Оркестрационная значимость:** Самый продвинутый context compressor из всех исследованных проектов. Конкретные паттерны для заимствования:
- **Structured summary template** — 14-секционный формат для передачи контекста между итерациями fix_iterations
- **Anti-thrashing** — защита от бесполезной компресии 
- **Tool result deduplication** — актуально для fix_iterations, где agent может повторять одни и те же команды

### 3.3 🟡 Subagent Delegation (`tools/delegate_tool.py`)

**Что у них:** Иерархическое порождение подагентов с изолированным контекстом:

```python
delegate_task(
 goal="Implement auth module",
 tasks=["JWT tokens", "Session management", "Tests"], # batch/parallel mode
 role="orchestrator" | "leaf", # orchestrator может порождать дочерних
 max_spawn_depth=1, # до 3 уровней вложенности
 max_concurrent_children=3, # параллельное выполнение
 child_timeout_seconds=600, # timeout на каждого child
 toolsets=["terminal", "file"], # restricted toolset
)
```

Ключевые механизмы:
- **DELEGATE_BLOCKED_TOOLS** — `delegate_task`, `clarify`, `memory`, `send_message`, `execute_code` (no recursive delegation, no user interaction, no shared memory writes)
- **Orchestrator/Leaf roles** — orchestrator может порождать subagents, leaf — нет
- **ThreadPoolExecutor** — параллельное выполнение через Python threads
- **Subagent approval callbacks** — auto-deny (default) / auto-approve для dangerous commands в subagent threads

**Оркестрационная значимость:** Ближайший аналог для нашего будущего sub-agent pattern. Ключевая идея: **blocked tools list** — subagent не должен иметь инструменты, позволяющие ему взаимодействовать с пользователем, модифицировать shared state или порождать своих потомков.

### 3.4 🟡 Credential Pool с Multi-Strategy Rotation (`agent/credential_pool.py`)

**Что у них:** Пул credentials (API ключей) для одного провайдера с 4 стратегиями:

| Стратегия | Поведение |
| --- | --- |
| `fill_first` | Использовать первый доступный, пока не исчерпан |
| `round_robin` | Циклическое переключение |
| `random` | Случайный выбор |
| `least_used` | Наименее использованный credential |

Exhausted credentials охлаждаются 1 час (override от провайдера). Поддержка OAuth + API key credentials.

Механика rotation:
```python
class CredentialPool:
    def __init__(self, credentials: list[Credential], strategy: RotationStrategy):
        self.credentials = credentials
        self.strategy = strategy
        self.exhausted: dict[str, datetime] = {}  # credential_id → cooldown_until

    def acquire(self) -> Credential | None:
        """Возвращает первый доступный credential согласно стратегии."""
        # fill_first: linear scan до первого не-exhausted
        # round_robin: cycle через credentials, skip exhausted
        # least_used: sort по usage_count, pick min
        # random: choice из не-exhausted

    def mark_exhausted(self, credential_id: str, cooldown_seconds: int = 3600):
        """Исключает credential из rotation на cooldown период."""
        self.exhausted[credential_id] = utcnow() + timedelta(seconds=cooldown_seconds)
```

Типичный flow: `acquire()` → API call → если 429 → `mark_exhausted()` → `acquire()` снова с другим credential. Если все exhausted — wait до ближайшего cooldown истечения.

**Оркестрационная значимость:** Credential pool реализует паттерн «circuit breaker на уровне ключей»: каждый ключ имеет individual cooldown, а пул как целое деградирует gracefully — от N доступных ключей до 0 с понятным ожиданием восстановления. Стратегии rotation позволяют балансировать между равномерным износом (`round_robin`, `least_used`) и минимизацией переключений (`fill_first`).

### 3.5 🟡 Filesystem Checkpoints (`tools/checkpoint_manager.py`)

**Что у них:** Автоматические снапшоты рабочего каталога через shadow git store:

```
~/.hermes/checkpoints/
 store/ — single bare git repo (deduplication across projects)
 refs/hermes/<hash16> — per-project branch tip
 indexes/<hash16> — per-project git index
 projects/<hash16>.json — {workdir, created_at, last_touch}
```

Создаются перед file-mutating operations (`write_file`, `patch`, `terminal` с destructive flags). Поддержка rollback к любому checkpoint. Auto-prune stale/orphan snapshots.

**Оркестрационная значимость:** Для автономных fix_iterations — гарантия, что можно откатить изменения агента. Реализация через git — изящная: нулевой overhead для неизменённых файлов.

### 3.6 🟡 Context File Injection Scanning (`agent/prompt_builder.py`)

**Что у них:** Сканирование AGENTS.md, SOUL.md и других context files на prompt injection:

```python
_CONTEXT_THREAT_PATTERNS = [
 (r'ignore\s+(previous|all|above)\s+instructions', "prompt_injection"),
 (r'do\s+not\s+tell\s+the\s+user', "deception_hide"),
 (r'system\s+prompt\s+override', "sys_prompt_override"),
 (r'disregard\s+(your|all)\s+(instructions|rules)', "disregard_rules"),
 # + 6 more patterns + invisible unicode detection
]
```

Блокированные файлы заменяются на `[BLOCKED: <filename> contained potential prompt injection ...]`.

**Оркестрационная значимость:** Hermes загружает произвольные `.md` файлы с диска (AGENTS.md, SOUL.md, SKILL.md, контекстные файлы проекта) в system prompt. Без сканирования любой из этих файлов становится вектором prompt injection — злоумышленный `.hermes.md` в клонированном репозитории может переопределить поведение агента. 11 regex-паттернов + invisible unicode detection — эвристическая защита, которая не требует отдельной модели и добавляет <1ms overhead. Ложноположительные срабатывания заменяют файл на `[BLOCKED: ...]` вместо краша, сохраняя агент работоспособным.

### 3.7 🟡 Rate Limit Header Tracking (`agent/rate_limit_tracker.py`)

**Что у них:** Парсинг 12 x-ratelimit-* headers из API responses:

```python
@dataclass
class RateLimitState:
 requests_min: RateLimitBucket # RPM cap
 requests_hour: RateLimitBucket # RPH cap
 tokens_min: RateLimitBucket # TPM cap
 tokens_hour: RateLimitBucket # TPH cap
 # limit, remaining, reset_seconds для каждого bucket
```

Отображение через `/usage` slash command.

**Оркестрационная значимость:** Дополнение к circuit breaker: отслеживание rate limits позволяет превентивно переключаться на fallback runner до получения 429, а не реактивно.

### 3.8 🟡 Kanban Multi-Agent Coordination (`tools/kanban_tools.py` + `plugins/kanban/`)

**Что у них:** Shared SQLite board для координации нескольких agent-воркеров:

- **Инструменты:** `kanban_show`, `kanban_complete`, `kanban_block`, `kanban_heartbeat`, `kanban_comment`, `kanban_create`, `kanban_link`
- **Жизненный цикл:** Orient (прочитать задачу) → Execute (выполнить) → Complete/Block (завершить/заблокировать)
- **Parent-child handoffs:** summary + metadata передаются от родителя к дочернему worker'у
- **Worker spawning:** агенты запускаются с `$HERMES_KANBAN_TASK` env var

**Оркестрационная значимость:** Модель координации для будущих multi-agent scenarios. Конкретный паттерн: **kanban board как shared coordination surface** — agents не общаются напрямую, а координируются через общую доску задач. Аналогия с нашими YAML chains, но для параллельного выполнения.

---

## 4. Прочие возможности (вне оркестрации)

### 4.1 🟢 Agent Loop (прямое LLM API взаимодействие)

Hermes работает напрямую с LLM API через 10+ провайдеров. Task-orchestrator работает через runner'ы (pi, codex), которые сами управляют LLM-взаимодействием. Разные уровни абстракции — мы не дублируем LLM interaction.

### 4.2 🟢 Messaging Gateway (15+ платформ)

Telegram, Discord, Slack, WhatsApp, Signal, Matrix и др. — это presentation-слой интерактивного агента. Task-orchestrator — CLI pipeline. Разные парадигмы.

### 4.3 🟢 Web UI / TUI / Ink React Interface

Визуальный интерфейс агента (terminal UI на React/Ink, web dashboard) — не scope task-orchestrator.

### 4.4 🟢 Browser Automation Tools

Встроенный браузер (CamoFox, CDP) для web-автоматизации — не относится к оркестрации цепочек.

### 4.5 🟢 Voice / TTS / Image Generation

Текст-в-речь, генерация изображений — фичи интерактивного ассистента, не pipeline-оркестратора.

### 4.6 🟢 OpenClaw Migration (`hermes claw migrate`)

Инструмент миграции из OpenClaw — специфичен для пользовательской базы Hermes.

### 4.7 🟢 RL Training Environments (Atropos)

Batch trajectory generation, Atropos RL environments — исследовательский кейс, не связанный с оркестрацией.

### 4.8 🟢 Plugin System в текущем виде

Plugin architecture (memory, context_engine, model-providers) — мощная, но overengineering для pipeline-оркестратора. Мы расширяем через runner interface.

### 4.9 🟢 Cron Scheduling

Встроенный cron для automations — полезен для пользователя, но не относится к core pipeline execution. Если потребуется, реализуем через external scheduler.

---

## 5. Сводка по оркестрации

| Возможность | Статус в продукте | Описание |
| --- | --- | --- |
| Error classification (20+ categories) | 🟡 P2 | Наиболее развитая система из исследованных. Начать с 6 категорий: auth, rate_limit, server_error, context_overflow, model_not_found, unknown |
| Credential pool / API key rotation | 🟡 P2 | Дополнение к CB: rotation вместо retry при rate_limit |
| Context compression (structured summary) | 🟡 P3 | 14-секционный summary template для передачи контекста в fix_iterations |
| Subagent delegation (blocked tools, depth control) | 🟡 P3 | Для будущих dynamic chains: «chain внутри chain» с изолированным контекстом |
| Filesystem checkpoints (shadow git) | 🟡 P3 | Rollback для fix_iterations — гарантия восстановления при ошибке |
| Context file injection scanning | 🟡 P2 | Сканирование .md файлов на prompt injection (11 threat patterns) |
| Rate limit header tracking | 🟡 P3 | Превентивное переключение runner до получения 429 |
| Kanban multi-agent coordination | 🟡 P3 | Модель для будущих multi-agent scenarios |
| Tool result deduplication | 🟡 P3 | Для fix_iterations: не повторять одинаковые tool calls в контексте |
| Agent loop (direct LLM API) | 🟢 — | Разный уровень абстракции |
| Messaging gateway (15+ platforms) | 🟢 — | Разная парадигма (interactive agent vs. CLI pipeline) |
| Web UI / TUI | 🟢 — | Не scope task-orchestrator |
| Browser automation | 🟢 — | Не scope |
| Voice / TTS / Image gen | 🟢 — | Не scope |
| RL training environments | 🟢 — | Не scope |
| Plugin system | 🟢 — | Overengineering для pipeline |
| Cron scheduling | 🟢 — | Если нужно — через external scheduler |

---

## 6. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории Hermes Agent:

- [`run_agent.py`](https://github.com/NousResearch/hermes-agent/blob/main/run_agent.py) — AIAgent class: core conversation loop (~12k LOC), retry logic, budget tracking, context management
- [`agent/error_classifier.py`](https://github.com/NousResearch/hermes-agent/blob/main/agent/error_classifier.py) — Error classification taxonomy: 20+ FailoverReason, provider-specific patterns, ClassifiedError с recovery hints
- [`agent/context_compressor.py`](https://github.com/NousResearch/hermes-agent/blob/main/agent/context_compressor.py) — Context compressor (~1500 LOC): structured summarization, tool result pruning, anti-thrashing, iterative updates
- [`agent/credential_pool.py`](https://github.com/NousResearch/hermes-agent/blob/main/agent/credential_pool.py) — Multi-credential pool: 4 rotation strategies, exhausted TTL, OAuth support
- [`agent/memory_manager.py`](https://github.com/NousResearch/hermes-agent/blob/main/agent/memory_manager.py) — Memory orchestration: MemoryProvider interface, streaming context scrubber
- [`agent/prompt_builder.py`](https://github.com/NousResearch/hermes-agent/blob/main/agent/prompt_builder.py) — System prompt assembly: context file injection scanning (11 threat patterns), skill discovery
- [`agent/rate_limit_tracker.py`](https://github.com/NousResearch/hermes-agent/blob/main/agent/rate_limit_tracker.py) — Rate limit header parsing: RPM/TPM per minute/hour
- [`tools/delegate_tool.py`](https://github.com/NousResearch/hermes-agent/blob/main/tools/delegate_tool.py) — Subagent spawning: parallel execution, orchestrator/leaf roles, depth control, blocked tools
- [`tools/checkpoint_manager.py`](https://github.com/NousResearch/hermes-agent/blob/main/tools/checkpoint_manager.py) — Filesystem snapshots via shared shadow git store
- [`tools/kanban_tools.py`](https://github.com/NousResearch/hermes-agent/blob/main/tools/kanban_tools.py) — Kanban multi-agent coordination: show/complete/block/heartbeat/comment/create/link
- [`toolsets.py`](https://github.com/NousResearch/hermes-agent/blob/main/toolsets.py) — Toolset system: compositional grouping of 40+ tools
- [`AGENTS.md`](https://github.com/NousResearch/hermes-agent/blob/main/AGENTS.md) — Development guide: project structure, AIAgent internals, CLI architecture
- [`README.md`](https://github.com/NousResearch/hermes-agent/blob/main/README.md) — Project overview, features, quick install, CLI reference

📚 **Источники:**
1. [github.com/NousResearch/hermes-agent](https://github.com/NousResearch/hermes-agent) — репозиторий проекта (136K+ stars)
2. [hermes-agent.nousresearch.com/docs](https://hermes-agent.nousresearch.com/docs/) — документация (Architecture, Tools, Skills, Memory, MCP, Cron, Security)
3. [hermes-agent.nousresearch.com/docs/developer-guide/architecture](https://hermes-agent.nousresearch.com/docs/developer-guide/architecture) — архитектура, agent loop, key classes
4. [hermes-agent.nousresearch.com/docs/user-guide/features/skills](https://hermes-agent.nousresearch.com/docs/user-guide/features/skills) — Skills system: procedural memory, Skills Hub
5. [agentskills.io](https://agentskills.io) — открытый стандарт для agent skills (используется Hermes)
