# Исследование: Agno (бывший Phi) — Python-фреймворк для AI-агентов

> **Проект:** [github.com/agno-agi/agno](https://github.com/agno-agi/agno)
> **Дата анализа:** 2026-04-22
> **Версия:** 2.5.17
> **Язык:** Python (>=3.7)
> **Лицензия:** Apache License 2.0
> **Аналитик:** Технический писатель (Гермиона)

---

## 1. Обзор проекта

Agno — Python-фреймворк для построения AI-powered приложений. Предлагает три уровня абстракции: **Framework** (агенты, команды, workflows), **Runtime** (FastAPI-бэкенд для production), **Control Plane** (AgentOS UI для мониторинга и управления).

Agno **не является** оркестратором внешних CLI-ассистентов (как task-orchestrator). Это **SDK-фреймворк**, работающий на уровне прямых LLM API. Agno управляет потоком выполнения между агентами и шагами, но вызовы LLM происходят внутри процесса.

### Архитектура

```
libs/agno/agno/
├── agent/                     # Agent: LLM-сессия, tools, memory, hooks, guardrails
│   └── agent.py               # Agent dataclass (1729 строк): generate/run, tools, memory, reasoning
├── team/                      # Team: multi-agent coordination
│   ├── team.py                # Team dataclass: members, mode (coordinate/route/broadcast/tasks)
│   └── mode.py                # TeamMode enum: coordinate, route, broadcast, tasks
├── workflow/                  # Workflow: structured step-based execution
│   ├── workflow.py            # Workflow class: compose steps, loops, parallels, routers
│   ├── step.py                # Step: единица работы (agent/team/executor/workflow), HITL, retries
│   ├── loop.py                # Loop: итеративное выполнение с end_condition (callable/CEL)
│   ├── parallel.py            # Parallel: параллельное выполнение независимых шагов
│   ├── router.py              # Router: динамический выбор шага (selector/CEL/HITL)
│   ├── condition.py           # Condition: условное ветвление (if/else)
│   └── steps.py               # Steps: последовательная цепочка шагов
├── memory/                    # Memory Manager: user memories, CRUD, semantic search
├── compression/               # Compression Manager: LLM-based сжатие tool call results
├── knowledge/                 # Knowledge / RAG: vector search, document processing
├── guardrails/                # Guardrails: PII detection, prompt injection, OpenAI moderation
├── eval/                      # Evaluation: pre/post checks для agent/team
├── db/                        # Database: 12+ адаптеров (PostgreSQL, SQLite, MySQL, Redis, Mongo, DynamoDB, Firestore, ...)
├── models/                    # Model integration: 40+ провайдеров (OpenAI, Anthropic, Google, Groq, Mistral, xAI, Ollama, ...)
│   └── fallback.py            # FallbackConfig: error-specific model routing (on_error/on_rate_limit/on_context_overflow)
├── os/                        # AgentOS: FastAPI runtime с API endpoints, WebSocket, tracing
├── tools/                     # Tools framework: Toolkit, Function, MCP integration
├── skills/                    # Skills: structured instructions and reference docs
├── session/                   # Session management: AgentSession, TeamSession, WorkflowSession
├── tracing/                   # Tracing: spans, traces, observability
├── hooks/                     # Hooks: lifecycle events
├── approval/                  # Approval system: runtime approval enforcement
├── scheduler/                 # Scheduler: background execution
├── reasoning/                 # Reasoning: step-by-step problem solving
├── learn/                     # Learning Machine: extract learnings from runs
├── culture/                   # Cultural Knowledge: domain-specific knowledge base
└── vectordb/                  # Vector DB abstraction
```

### Ключевые характеристики

| Характеристика | Значение |
|---|---|
| **Тип** | SDK-фреймворк (Python), работает на уровне LLM API |
| **Модель выполнения (Agents)** | Agent loop (LLM → tool call → observation → LLM → ...) |
| **Модель выполнения (Teams)** | 4 режима: coordinate (supervisor), route (router), broadcast (all members), tasks (autonomous decomposition) |
| **Модель выполнения (Workflows)** | Step-based: Step, Steps, Loop, Parallel, Router, Condition + вложенные workflows |
| **State management** | Pluggable storage (12+ адаптеров: PostgreSQL, SQLite, MySQL, Redis, MongoDB, DynamoDB, Firestore, GCS, ...) |
| **Провайдеры** | 40+ LLM-провайдеров |
| **Расширяемость** | Tools, MCP, Skills, Guardrails, Evals, Hooks, custom DB, custom compression |
| **Memory** | MemoryManager: user memories (CRUD), semantic search, agentic memory (agent manages own memories) |
| **Compression** | LLM-based сжатие tool call results при context overflow |
| **HITL** | Подтверждение (requires_confirmation), пользовательский ввод (requires_user_input), output review, CEL-выражения |
| **Fallback** | Error-specific routing: on_error, on_rate_limit, on_context_overflow с callback |
| **Guardrails** | PII detection, prompt injection detection, OpenAI moderation |
| **Evaluation** | Pre/post checks (sync + async) для Agent/Team |
| **Runtime** | FastAPI (AgentOS): REST API, WebSocket, per-user/session isolation, tracing |
| **Лицензия** | Apache 2.0 |

### Основные компоненты

| Компонент | Назначение |
|---|---|
| [`agent/agent.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/agent/agent.py) | Agent: LLM-сессия, tools, memory, hooks (pre/post), guardrails, evals, reasoning, compression |
| [`team/team.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/team/team.py) | Team: multi-agent coordination с 4 режимами выполнения (coordinate/route/broadcast/tasks) |
| [`team/mode.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/team/mode.py) | TeamMode enum: coordinate (supervisor), route (router), broadcast (all), tasks (autonomous) |
| [`workflow/workflow.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/workflow/workflow.py) | Workflow: compose step/loop/parallel/router/condition, nested workflows, HITL, session persistence |
| [`workflow/step.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/workflow/step.py) | Step: agent/team/executor/workflow, max_retries, HITL (confirmation, user input, output review) |
| [`workflow/loop.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/workflow/loop.py) | Loop: итерации с end_condition (callable/CEL), forward_iteration_output, HITL |
| [`workflow/parallel.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/workflow/parallel.py) | Parallel: параллельное выполнение независимых шагов |
| [`workflow/router.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/workflow/router.py) | Router: динамический выбор шага (selector/CEL/HITL) |
| [`workflow/condition.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/workflow/condition.py) | Condition: условное ветвление (if/else) |
| [`models/fallback.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/models/fallback.py) | FallbackConfig: error-specific routing (on_error/on_rate_limit/on_context_overflow) |
| [`memory/manager.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/memory/manager.py) | MemoryManager: user memories CRUD, semantic search, agentic memory |
| [`compression/manager.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/compression/manager.py) | CompressionManager: LLM-based сжатие tool call results |
| [`guardrails/`](https://github.com/agno-agi/agno/tree/main/libs/agno/agno/guardrails) | Guardrails: PII, prompt injection, OpenAI moderation |
| [`eval/base.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/eval/base.py) | BaseEval: abstract pre/post checks (sync + async) |
| [`os/app.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/os/app.py) | AgentOS: FastAPI runtime, REST API, WebSocket, per-user/session isolation |
| [`db/base.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/db/base.py) | BaseDb: abstract storage (sessions, memory, traces, evals, knowledge, approvals, ...) |
| [`approval/`](https://github.com/agno-agi/agno/tree/main/libs/agno/agno/approval) | Approval system: runtime enforcement, approval workflows |
| [`learn/`](https://github.com/agno-agi/agno/tree/main/libs/agno/agno/learn) | LearningMachine: extract learnings from runs, persist to DB |

---

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | TasK Orchestrator | Agno | Статус |
|---|---|---|---|
| **Цепочки шагов (chains)** | ✅ YAML chains, статические и динамические | ✅ Workflow: Steps (последовательная цепочка) | ✅ Паритет |
| **Conditional branching** | ❌ Нет | ✅ Condition (if/else) + Router (selector/CEL) | 🟡 Позже |
| **Parallel execution** | ❌ Нет | ✅ Parallel — параллельное выполнение | 🟡 Позже |
| **Циклы (loops)** | ✅ fix_iterations с max_iterations | ✅ Loop с max_iterations + end_condition (callable/CEL) | ✅ Паритет, у них богаче |
| **Retry с backoff** | ✅ RetryingAgentRunner (exponential backoff) | ⚠️ `max_retries` на Step (без backoff, но есть FallbackConfig) | ⚠️ Разные стратегии (retry-same vs fallback-to-alternative) |
| **Circuit Breaker** | ✅ CircuitBreakerAgentRunner | ❌ Нет | ✅ У нас есть |
| **Quality Gates** | ✅ Shell-команды как проверки | ⚠️ Guardrails (PII, prompt injection, moderation) + Evals (pre/post checks) | ✅ Разный фокус |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based) | ❌ Нет встроенного budget control | ✅ У нас есть |
| **Fallback routing** | ✅ Per-step fallback runner | ✅ FallbackConfig: on_error/on_rate_limit/on_context_overflow с callback | 🟡 У них богаче (error-specific) |
| **Audit Trail (JSONL)** | ✅ JsonlAuditLogger | ✅ Tracing (spans, traces) + session persistence | ✅ Паритет (разный подход) |
| **Ролевые промпты** | ✅ .md файлы (18+ ролей) | ⚠️ `instructions` в Agent constructor (строка) + Skills | ✅ У нас лучше |
| **Multiple runners** | ✅ Pi + Codex (через interface) | ✅ 40+ провайдеров через model integration | ✅ Паритет (разный уровень) |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❌ Монорепозиторий, пакетная структура | ⚠️ Разные типы проектов (приложение vs SDK) |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Прямой вызов + hooks | ✅ У нас лучше |
| **YAML-конфигурация** | ✅ Chains + roles в YAML | ⚠️ AgentOS поддерживает YAML config для runtime | ✅ Разный подход |
| **Human-in-the-loop** | ❌ Нет | ✅ 3 режима: confirmation, user_input, output_review + CEL + HITL retry + timeout | 🟡 Позже |
| **Session persistence** | ❌ Нет (in-memory) | ✅ Pluggable storage (12+ адаптеров) | 🟡 Предусловие для Loop/Parallel (см. §7) |
| **Memory system** | ❌ Нет | ✅ MemoryManager: user memories, CRUD, semantic search, agentic memory | 🟡 Интересно |
| **RAG / Knowledge** | ❌ Нет | ✅ Knowledge + VectorDB + agentic knowledge filters | 🟢 Не берём |
| **Evaluation framework** | ❌ Нет | ✅ BaseEval: abstract pre/post checks (sync + async) | 🟡 Позже |
| **Compression** | ❌ Нет | ✅ CompressionManager: LLM-based сжатие tool call results при overflow | 🟡 Интересно |
| **CEL expressions** | ❌ Нет | ✅ Common Expression Language для conditions/routers/loops | 🟡 Интересно, но нельзя выразить в YAML |
| **Approval system** | ❌ Нет | ✅ Runtime approval enforcement | 🟡 Позже |
| **Guardrails** | ❌ Нет | ✅ PII detection, prompt injection, OpenAI moderation | 🟡 Позже |
| **Multi-agent Teams** | ❌ Нет | ✅ 4 режима: coordinate, route, broadcast, tasks | 🟡 Позже |
| **Nested workflows** | ❌ Нет | ✅ Workflow как Step (до 10 уровней вложенности) | 🟡 Позже |
| **Tool hooks** | ❌ Нет | ✅ tool_hooks: middleware вокруг tool calls | 🟡 Интересно |
| **Reasoning mode** | ❌ Нет | ✅ Step-by-step reasoning с отдельной reasoning_model | 🟢 Не берём |
| **Runtime (FastAPI)** | ❌ CLI-only | ✅ AgentOS: REST API, WebSocket, horizontal scaling | 🟢 Не берём |
| **Scheduler** | ❌ Нет | ✅ Background execution с расписанием | 🟢 Не берём |
| **Learning** | ❌ Нет | ✅ LearningMachine: extract learnings from runs | 🟡 Интересно |

---

## 3. Что полезно взять и почему

### 3.1 🟡 Workflow Engine с Loop, Parallel, Router, Condition (`workflow/`)

**Что у них:** Agno предоставляет 6 строительных блоков для workflows:

| Блок | Назначение | Аналог в task-orchestrator |
|---|---|---|
| `Step` | Единица работы (agent/team/executor/workflow) | Шаг chain |
| `Steps` | Последовательная цепочка шагов | Static chain |
| `Loop` | Итерации с end_condition (callable/CEL) | fix_iterations (у нас беднее — нет end_condition) |
| `Parallel` | Параллельное выполнение | ❌ Нет |
| `Router` | Динамический выбор шага | ❌ Нет |
| `Condition` | Условное ветвление (if/else) | ❌ Нет |

```python
# Пример workflow
workflow = Workflow(
    name="code-review",
    steps=[
        implement_step,                          # Step
        Loop(                                    # Loop с end_condition
            steps=[review_step, fix_step],
            end_condition=lambda outputs: outputs[-1].success,
            max_iterations=5,
        ),
        Parallel(lint_step, type_check_step),    # Parallel
        Router(                                  # Router
            selector=lambda inp: deploy_step if inp.success else notify_step,
            choices=[deploy_step, notify_step],
        ),
    ],
)
```

**Вложенные workflows:** Step может ссылаться на другой Workflow (до 10 уровней вложенности, ограничение `_MAX_NESTED_WORKFLOW_DEPTH`). Контекст передаётся через `StepInput/StepOutput`, изоляция через отдельный `WorkflowSession`.

> ⚠️ **Риск overengineering:** Сейчас у нас 1 тип цепочки (static + dynamic brainstorm). Внедрение 6 блоков workflow — минимум 3× рост кодовой базы. Без реальной user story на Parallel/Router это premature abstraction. Рекомендация: внедрять по одному блоку по мере появления задач.



---

### 3.2 🟡 TeamMode — 4 режима multi-agent coordination (`team/`)

**Что у них:** Team с 4 режимами выполнения:

| Режим | Описание | Аналог |
|---|---|---|
| `coordinate` | Supervisor pattern: leader выбирает members, формулирует задачи, синтезирует ответы | Dynamic chain с facilitator |
| `route` | Router pattern: leader маршрутизирует к специалисту, возвращает ответ напрямую | ❌ Нет |
| `broadcast` | Broadcast pattern: задача делегируется всем members одновременно | ❌ Нет (Parallel) |
| `tasks` | Autonomous: leader декомпозирует цели в shared task list, делегирует, циклит до завершения | Dynamic loop (ближайший аналог) |

**Ключевые параметры Team:**
- `max_iterations: int = 10` — лимит итераций (как наш max_iterations)
- `share_member_interactions: bool` — пересылать ли взаимодействия между members
- `add_team_history_to_members: bool` — передавать ли team-level историю
- `delegate_to_all_members: bool` — делегировать всем (аналог broadcast)
- `determine_input_for_members: bool` — leader решает input для каждого member

**Почему нам интересно:** `route` mode — это routing к конкретному runner (аналог нашего `resolve_runner`). `coordinate` — наш dynamic loop с facilitator. `tasks` mode с autonomous decomposition — следующий шаг для dynamic chains.

> ⚠️ **Риск premature abstraction:** Сейчас у нас ровно 2 runner'а (Pi + Codex). Broadcast (все runners одновременно) при 2 runners — бессмысленно. Route — тривиально. Tasks mode не реализуем без LLM-in-the-loop для декомпозиции, а LLM-вызовы делает runner, не оркестратор. Рекомендация: возвращаться к TeamMode когда появится 4+ runners.

---

### 3.3 🟡 FallbackConfig — error-specific model routing (`models/fallback.py`)

**Что у них:** Конфигурация fallback с разделением по типу ошибки:

```python
FallbackConfig(
    on_error=[Claude(id="claude-sonnet-4")],         # Общий fallback
    on_rate_limit=[OpenAIChat(id="gpt-4o-mini")],     # При 429 → дешёвая модель
    on_context_overflow=[Claude(id="claude-sonnet-4")], # При context overflow → модель с большим окном
    callback=lambda primary, fallback, error: log(...),  # Callback при активации
)
```

**Умная маршрутизация:**
- `on_rate_limit` → fallback на дешёвую/другую модель при 429
- `on_context_overflow` → fallback на модель с большим context window
- `on_error` → общий fallback при 5xx/network errors
- Приоритет: error-specific → general (только для retryable ошибок)

**Почему нам интересно:** Наш `CircuitBreakerAgentRunner` защищает от cascade failures, но не переключает на альтернативный runner. Agno показывает, как добавить **error-specific fallback**: при rate limit → дешёвый runner, при timeout → быстрый runner, при context overflow → runner с другим model. Это дополнение к нашему circuit breaker.

> ⚠️ **Требуется domain model:** Наш `FallbackConfigVo` — массив CLI-аргументов без типа ошибки. Для error-specific fallback нужна классификация: rate limit ≠ timeout ≠ malformed output ≠ context overflow. Без модели ошибок реализация начнётся с неправильной абстракции.

---

### 3.4 🟡 HITL — 3 режима Human-in-the-Loop (`workflow/step.py`)

**Что у них:** Каждый Step может требовать участия человека в 3 режимах:

| Режим | Параметры | Поведение |
|---|---|---|
| **Confirmation** | `requires_confirmation=True`, `confirmation_message`, `on_reject` (skip/cancel) | Пауза перед выполнением, user подтверждает или отклоняет |
| **User Input** | `requires_user_input=True`, `user_input_schema` (name, type, description, required) | Пауза для ввода данных пользователем |
| **Output Review** | `requires_output_review=True` (bool или callable), `output_review_message` | Пауза после выполнения, user ревьюит результат |

**Дополнительные параметры:**
- `hitl_max_retries: int = 3` — лимит повторных запросов к человеку
- `hitl_timeout: Optional[int]` — таймаут ожидания ответа
- `on_timeout: "cancel" | "skip" | "approve"` — поведение при таймауте
- `on_error: "fail" | "skip" | "pause"` — при ошибке: fail/skip/pause (HITL)

**Поддержка CEL-выражений** для conditions и routers в HITL-режиме:

```python
# Router с HITL — user выбирает шаг из доступных
Router(
    choices=[deploy_step, notify_step],
    requires_user_input=True,
    user_input_message="Choose deployment strategy",
)
```

**Почему нам интересно:** Production-ready HITL — востребованная функция для автономных цепочек. Три режима покрывают основные сценарии: confirm → input → review. Callable в `requires_output_review` позволяет условный review (только если результат не устраивает).

> ⚠️ **Архитектурное ограничение:** task-orchestrator — CLI-утилита. HITL в CLI означает блокировку терминала и невозможность запуска в CI/CD. Agno решает это через FastAPI runtime (WebSocket, REST API), который мы не берём. HITL без runtime — ограниченная фича. Реалистичный сценарий: интерактивный режим при ручном запуске, skip при CI/CD.

---

### 3.5 🟡 Loop с end_condition — CEL-выражения и callable (`workflow/loop.py`)

**Что у них:** Loop поддерживает 3 способа определения условия завершения:

```python
Loop(
    steps=[review_step, fix_step],
    max_iterations=5,

    # Вариант 1: callable
    end_condition=lambda outputs: outputs[-1].success,

    # Вариант 2: CEL-выражение (Common Expression Language)
    end_condition='all_success && current_iteration >= 2',

    # Вариант 3: None — только max_iterations
)
```

**CEL-переменные для loops:**
- `current_iteration` — номер итерации (1-indexed)
- `max_iterations` — максимум
- `all_success` — все шаги успешны
- `last_step_content` — контент последнего шага
- `step_outputs` — map имени шага → контент

**`forward_iteration_output: bool = False`** — если True, output каждой итерации передаётся как input следующей (как наш fix_iterations).

**Почему нам интересно:** Прямое усиление нашего `fix_iterations`: сейчас только `max_iterations`, а с `end_condition` — детерминированная проверка завершения. CEL-выражения позволяют декларативно описать условие без кода. Аналог Archon `until_bash`, но более общий (не только shell).

> ⚠️ **CEL vs YAML:** CEL отброшен как зависимость (§4.6), но callable нельзя положить в YAML — наш core формат конфигурации. Нужно решить: либо DSL для условий в YAML (свой мини-CEL), либо callable регистрируется через service container, либо `end_condition` выражается как shell-команда (как `until_bash` у Archon). Третий вариант — самый совместимый с текущей архитектурой.

---

### 3.6 🟡 CompressionManager — LLM-based сжатие (`compression/manager.py`)

**Что у них:** Автоматическое сжатие tool call results при context overflow:

```python
CompressionManager(
    model=Claude(id="claude-sonnet-4"),
    compress_tool_results=True,               # Включить сжатие
    compress_tool_results_limit=2000,         # Порог по токенам
    compress_token_limit=1000,                # Целевой размер после сжатия
)
```

**Подход:** LLM-суммаризация с explicit preservation rules:
- Сохранять: факты, числа, даты, entities, identifiers
- Сжимать: описания, пояснения, списки
- Удалять: вступления, filler, форматирование, redundancy

**Почему нам интересно:** Для длинных dynamic loops контекст растёт. Сжатие tool results — самый простой первый шаг: не сжимать весь контекст, а только output tool calls. Менее радикально, чем auto-summarization всего диалога.

---

### 3.7 🟡 Tool Hooks — middleware вокруг tool calls (`agent/agent.py`)

**Что у них:** Agent поддерживает `tool_hooks` — middleware-функции, вызываемые вокруг каждого tool call:

```python
Agent(
    tool_hooks=[log_tool_call, validate_tool_input, rate_limit_tool],
)
```

**Pre/post hooks:**
- `pre_hooks` — функции, вызываемые до выполнения (после загрузки сессии)
- `post_hooks` — функции, вызываемые после output (до возврата ответа)
- В hooks можно передавать `BaseGuardrail` и `BaseEval` — guardrails и evals работают как hooks

**Почему нам интересно:** Аналог нашего decorator pattern, но на уровне tool calls. Для chain executor `tool_hooks` может заменить декораторы для per-step middleware (логирование, валидация, rate limiting).

---

### 3.8 🟡 Guardrails — pre/post execution checks (`guardrails/`)

**Что у них:** 3 встроенных guardrail:

| Guardrail | Назначение |
|---|---|
| `PIIDetectionGuardrail` | Обнаружение персональных данных (PII) |
| `PromptInjectionGuardrail` | Обнаружение prompt injection атак |
| `OpenAIModerationGuardrail` | Модерация контента через OpenAI API |

**Механика:** Guardrails реализуют `BaseGuardrail` и могут использоваться как `pre_hooks` / `post_hooks`. Выбрасывают исключение при нарушении.

**Почему нам интересно:** Guardrails — это «pre-flight checks» перед выполнением шага. Для production: проверка входных данных (нет ли PII, не содержит ли prompt injection) перед отправкой в runner. Дополняет наши post-execution quality gates.

---

## 4. Что НЕ берём и почему

### 4.1 🟢 AgentOS Runtime (FastAPI)

Agno включает полноценный FastAPI-сервер с REST API, WebSocket, per-user/session isolation, horizontal scaling. Наш оркестратор — CLI-утилита, HTTP-сервер не нужен. Если понадобится API-доступ — это отдельный проект.

### 4.2 🟢 Knowledge / RAG / VectorDB

Agno имеет встроенный RAG pipeline с vector search. Для оркестратора цепочек это out of scope — RAG должен быть на уровне runner'ов или отдельного сервиса.

### 4.3 🟢 Reasoning Mode

Agno поддерживает step-by-step reasoning с отдельной reasoning_model/reasoning_agent. Это LLM-level feature, не связанная с оркестрацией цепочек.

### 4.4 🟢 Scheduler

Background execution с расписанием. Не актуально для CLI-утилиты — запуск по расписанию можно сделать через cron/CI.

### 4.5 🟢 Culture / LearningMachine

Cultural Knowledge и LearningMachine — Agno-специфичные фичи для domain-specific knowledge и learnings. Не применимы к нашему подходу.

### 4.6 🟢 CEL (Common Expression Language) как зависимость

CEL — мощный, но добавляет зависимость на Google CEL evaluator. Для наших целей достаточно callable-условий и простых выражений в YAML.

---

## 5. Сводка рекомендаций

| Фича | Приоритет | Обоснование |
|---|---|---|
| Chain orchestration | ✅ Уже есть | Core-функциональность task-orchestrator |
| Retry + Circuit Breaker | ✅ Уже есть | Устойчивость при сбоях |
| Quality Gates (shell) | ✅ Уже есть | Автоматическая проверка кода |
| Budget control | ✅ Уже есть | Предотвращение runaway spending |
| Fix iterations | ✅ Уже есть | Closed-loop цикл разработки |
| Audit trail (JSONL) | ✅ Уже есть | Полный лог выполнения |
| Fallback routing | ✅ Уже есть | Переключение runner'ов при сбоях |
| Loop с end_condition (callable) | 🟡 P2 | Усиление fix_iterations: детерминированная проверка завершения вместо только max_iterations |
| Error-specific fallback (on_rate_limit/on_timeout) | 🟡 P2 | Дополнение к circuit breaker: fallback на другой runner по типу ошибки |
| Conditional branching в chains | 🟡 P2 | Условное ветвление в YAML chains (концепция из Condition) |
| Tool hooks (per-step middleware) | 🟡 P2 | Альтернатива decorator pattern для per-step логирования/валидации |
| Human-in-the-loop (confirmation/output review) | 🟡 P3 | Для автономных цепочек с контрольными точками |
| Parallel execution в chains | 🟡 P3 | Параллельное выполнение независимых шагов |
| Team routing (route mode) | 🟡 P3 | Динамический routing к конкретному runner по типу задачи |
| Compression (tool results) | 🟡 P3 | LLM-based сжатие tool results при context overflow |
| Guardrails (pre-flight checks) | 🟡 P3 | Проверка входных данных перед отправкой в runner |
| Evaluation (pre/post checks) | 🟡 P3 | LLM-based оценка качества, дополнение к shell-based quality gates |
| Nested workflows | 🟡 P3 | Вложенные цепочки (chain как шаг другой chain) |
| AgentOS Runtime | 🟢 — | CLI-утилита, не нужен |
| Knowledge / RAG | 🟢 — | Out of scope |
| Reasoning mode | 🟢 — | LLM-level feature |
| Scheduler | 🟢 — | cron/CI |
| CEL expressions | 🟢 — | Избыточная зависимость, callable достаточно |

---

## 6. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории Agno:

- [`libs/agno/agno/agent/agent.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/agent/agent.py) — Agent: generate/run, tools, memory, hooks, guardrails, evals, reasoning, compression (1729 строк)
- [`libs/agno/agno/team/team.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/team/team.py) — Team: multi-agent coordination с 4 режимами (coordinate/route/broadcast/tasks)
- [`libs/agno/agno/team/mode.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/team/mode.py) — TeamMode enum
- [`libs/agno/agno/workflow/workflow.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/workflow/workflow.py) — Workflow: compose steps/loops/parallels/routers, nested workflows
- [`libs/agno/agno/workflow/step.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/workflow/step.py) — Step: agent/team/executor/workflow, max_retries, HITL
- [`libs/agno/agno/workflow/loop.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/workflow/loop.py) — Loop: итерации с end_condition (callable/CEL), forward_iteration_output
- [`libs/agno/agno/workflow/parallel.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/workflow/parallel.py) — Parallel: параллельное выполнение
- [`libs/agno/agno/workflow/router.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/workflow/router.py) — Router: динамический выбор шага (selector/CEL/HITL)
- [`libs/agno/agno/workflow/condition.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/workflow/condition.py) — Condition: условное ветвление
- [`libs/agno/agno/models/fallback.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/models/fallback.py) — FallbackConfig: error-specific routing
- [`libs/agno/agno/memory/manager.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/memory/manager.py) — MemoryManager: user memories, semantic search
- [`libs/agno/agno/compression/manager.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/compression/manager.py) — CompressionManager: LLM-based сжатие
- [`libs/agno/agno/guardrails/`](https://github.com/agno-agi/agno/tree/main/libs/agno/agno/guardrails) — Guardrails: PII, prompt injection, moderation
- [`libs/agno/agno/eval/base.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/eval/base.py) — BaseEval: abstract pre/post checks
- [`libs/agno/agno/os/app.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/os/app.py) — AgentOS: FastAPI runtime
- [`libs/agno/agno/db/base.py`](https://github.com/agno-agi/agno/blob/main/libs/agno/agno/db/base.py) — BaseDb: abstract storage (12+ адаптеров)

---

📚 **Источники:**
1. [github.com/agno-agi/agno](https://github.com/agno-agi/agno) — репозиторий проекта
2. [docs.agno.com](https://docs.agno.com) — официальная документация
3. [www.agno.com](https://www.agno.com) — сайт проекта
4. [agno.com/first-agent](https://docs.agno.com/first-agent) — Quickstart
5. [github.com/agno-agi/agno/tree/main/cookbook](https://github.com/agno-agi/agno/tree/main/cookbook) — Cookbook с примерами
