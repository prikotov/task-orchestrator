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
├── agent/ # Agent: LLM-сессия, tools, memory, hooks, guardrails
│ └── agent.py # Agent dataclass (1729 строк): generate/run, tools, memory, reasoning
├── team/ # Team: multi-agent coordination
│ ├── team.py # Team dataclass: members, mode (coordinate/route/broadcast/tasks)
│ └── mode.py # TeamMode enum: coordinate, route, broadcast, tasks
├── workflow/ # Workflow: structured step-based execution
│ ├── workflow.py # Workflow class: compose steps, loops, parallels, routers
│ ├── step.py # Step: единица работы (agent/team/executor/workflow), HITL, retries
│ ├── loop.py # Loop: итеративное выполнение с end_condition (callable/CEL)
│ ├── parallel.py # Parallel: параллельное выполнение независимых шагов
│ ├── router.py # Router: динамический выбор шага (selector/CEL/HITL)
│ ├── condition.py # Condition: условное ветвление (if/else)
│ └── steps.py # Steps: последовательная цепочка шагов
├── memory/ # Memory Manager: user memories, CRUD, semantic search
├── compression/ # Compression Manager: LLM-based сжатие tool call results
├── knowledge/ # Knowledge / RAG: vector search, document processing
├── guardrails/ # Guardrails: PII detection, prompt injection, OpenAI moderation
├── eval/ # Evaluation: pre/post checks для agent/team
├── db/ # Database: 12+ адаптеров (PostgreSQL, SQLite, MySQL, Redis, MongoDB, DynamoDB, Firestore, ...)
├── models/ # Model integration: 40+ провайдеров (OpenAI, Anthropic, Google, Groq, Mistral, xAI, Ollama, ...)
│ └── fallback.py # FallbackConfig: error-specific model routing (on_error/on_rate_limit/on_context_overflow)
├── os/ # AgentOS: FastAPI runtime с API endpoints, WebSocket, tracing
├── tools/ # Tools framework: Toolkit, Function, MCP integration
├── skills/ # Skills: structured instructions and reference docs
├── session/ # Session management: AgentSession, TeamSession, WorkflowSession
├── tracing/ # Tracing: spans, traces, observability
├── hooks/ # Hooks: lifecycle events
├── approval/ # Approval system: runtime approval enforcement
├── scheduler/ # Scheduler: background execution
├── reasoning/ # Reasoning: step-by-step problem solving
├── learn/ # Learning Machine: extract learnings from runs
├── culture/ # Cultural Knowledge: domain-specific knowledge base
└── vectordb/ # Vector DB abstraction
```

### Ключевые характеристики

| Характеристика | Значение |
| --- | --- |
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
| --- | --- |
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

## 2. Возможности оркестрации — обзор

| Функция | Agno |
| --- | --- |
| **Цепочки шагов (chains)** | ✅ Workflow: Steps (последовательная цепочка) |
| **Conditional branching** | ✅ Condition (if/else) + Router (selector/CEL) |
| **Parallel execution** | ✅ Parallel — параллельное выполнение |
| **Циклы (loops)** | ✅ Loop с max_iterations + end_condition (callable/CEL) |
| **Retry с backoff** | ⚠️ `max_retries` на Step (без backoff, но есть FallbackConfig) |
| **Quality Gates** | ⚠️ Guardrails (PII, prompt injection, moderation) + Evals (pre/post checks) |
| **Fallback routing** | ✅ FallbackConfig: on_error/on_rate_limit/on_context_overflow с callback |
| **Audit Trail (JSONL)** | ✅ Tracing (spans, traces) + session persistence |
| **Ролевые промпты** | ⚠️ `instructions` в Agent constructor (строка) + Skills |
| **Multiple runners** | ✅ 40+ провайдеров через model integration |
| **DDD-архитектура** | ❌ Монорепозиторий, пакетная структура |
| **Decorator pattern** | ❌ Прямой вызов + hooks |
| **YAML-конфигурация** | ⚠️ AgentOS поддерживает YAML config для runtime |
| **Human-in-the-loop** | ✅ 3 режима: confirmation, user_input, output_review + CEL + HITL retry + timeout |
| **Session persistence** | ✅ Pluggable storage (12+ адаптеров) |
| **Memory system** | ✅ MemoryManager: user memories, CRUD, semantic search, agentic memory |
| **RAG / Knowledge** | ✅ Knowledge + VectorDB + agentic knowledge filters |
| **Evaluation framework** | ✅ BaseEval: abstract pre/post checks (sync + async) |
| **Compression** | ✅ CompressionManager: LLM-based сжатие tool call results при overflow |
| **CEL expressions** | ✅ Common Expression Language для conditions/routers/loops |
| **Approval system** | ✅ Runtime approval enforcement |
| **Guardrails** | ✅ PII detection, prompt injection, OpenAI moderation |
| **Multi-agent Teams** | ✅ 4 режима: coordinate, route, broadcast, tasks |
| **Nested workflows** | ✅ Workflow как Step (до 10 уровней вложенности) |
| **Tool hooks** | ✅ tool_hooks: middleware вокруг tool calls |
| **Reasoning mode** | ✅ Step-by-step reasoning с отдельной reasoning_model |
| **Runtime (FastAPI)** | ✅ AgentOS: REST API, WebSocket, horizontal scaling |
| **Scheduler** | ✅ Background execution с расписанием |
| **Learning** | ✅ LearningMachine: extract learnings from runs |

---

## 3. Оркестрационные возможности

### 3.1 🟡 Workflow Engine с Loop, Parallel, Router, Condition (`workflow/`)

**Что у них:** Agno предоставляет 6 строительных блоков для workflows:

| Блок | Назначение |
| --- | --- |
| `Step` | Единица работы (agent/team/executor/workflow) |
| `Steps` | Последовательная цепочка шагов |
| `Loop` | Итерации с end_condition (callable/CEL) |
| `Parallel` | Параллельное выполнение независимых шагов |
| `Router` | Динамический выбор шага (selector/CEL/HITL) |
| `Condition` | Условное ветвление (if/else) |

```python
# Пример workflow
workflow = Workflow(
 name="code-review",
 steps=[
 implement_step, # Step
 Loop( # Loop с end_condition
 steps=[review_step, fix_step],
 end_condition=lambda outputs: outputs[-1].success,
 max_iterations=5,
 ),
 Parallel(lint_step, type_check_step), # Parallel
 Router( # Router
 selector=lambda inp: deploy_step if inp.success else notify_step,
 choices=[deploy_step, notify_step],
 ),
 ],
)
```

**Вложенные workflows:** Step может ссылаться на другой Workflow (до 10 уровней вложенности, ограничение `_MAX_NESTED_WORKFLOW_DEPTH`). Контекст передаётся через `StepInput/StepOutput`, изоляция через отдельный `WorkflowSession`.

> ⚠️ **Архитектурные компромиссы:**
> - Композиция через плоский список (heterogeneous blocks в `steps=[]`) даёт гибкость, но лишает возможности статической валидации структуры workflow — ошибки в композиции обнаруживаются только в runtime.
> - Parallel выполняет ветви одновременно, но нет DAG (направленный ациклический граф) — отсутствует механизм зависимостей между параллельными ветвями с частичным порядком выполнения.
> - Лимит вложенности (`_MAX_NESTED_WORKFLOW_DEPTH = 10`) — hardcoded, не настраивается потребителем API.
> - Для простых последовательных цепочек (Step + Steps + Loop) API surface из 6 блоков избыточен.

---

### 3.2 🟡 TeamMode — 4 режима multi-agent coordination (`team/`)

**Что у них:** Team с 4 режимами выполнения:

| Режим | Описание |
| --- | --- |
| `coordinate` | Supervisor pattern: leader выбирает members, формулирует задачи, синтезирует ответы |
| `route` | Router pattern: leader маршрутизирует к специалисту, возвращает ответ напрямую |
| `broadcast` | Broadcast pattern: задача делегируется всем members одновременно |
| `tasks` | Autonomous: leader декомпозирует цели в shared task list, делегирует, повторяет до завершения |

**Ключевые параметры Team:**
- `max_iterations: int = 10` — лимит итераций (safety bound)
- `share_member_interactions: bool` — пересылать ли взаимодействия между members
- `add_team_history_to_members: bool` — передавать ли team-level историю
- `delegate_to_all_members: bool` — делегировать задачу всем members одновременно
- `determine_input_for_members: bool` — leader решает input для каждого member

**Оркестрационная значимость:** `coordinate` — классический supervisor pattern с LLM-лидером. `route` — простейший routing к одному specialist, полезен при разнородных задачах. `broadcast` — параллельный запрос всех members, эффективен при 3+ agents. `tasks` — наиболее сложный режим: leader выполняет autonomous decomposition цели в task list, делегирует tasks members, отслеживает прогресс — требует LLM-in-the-loop для декомпозиции, что делает его применимым только при наличии модели с достаточным reasoning capacity.

> ⚠️ **Архитектурные ограничения:**
> - Все режимы, кроме `broadcast`, делегируют решение о маршрутизации LLM: `coordinate` — LLM выбирает member и формулирует задачу, `route` — LLM определяет специалиста, `tasks` — LLM декомпозирует цель. Качество координации **полностью** зависит от reasoning capacity модели.
> - При ошибке LLM-маршрутизации (выбран неправильный member) нет детерминированного fallback — опции: повторная итерация (расход tokens) или прекращение выполнения.
> - `broadcast` при 2 members эквивалентен параллельному вызову без координации.
> - `tasks` mode требует значительного token consumption: LLM-лидер генерирует и отслеживает task list в каждом цикле.

---

### 3.3 🟡 FallbackConfig — error-specific model routing (`models/fallback.py`)

**Что у них:** Конфигурация fallback с разделением по типу ошибки:

```python
FallbackConfig(
 on_error=[Claude(id="claude-sonnet-4")], # Общий fallback
 on_rate_limit=[OpenAIChat(id="gpt-4o-mini")], # При 429 → дешёвая модель
 on_context_overflow=[Claude(id="claude-sonnet-4")], # При context overflow → модель с большим окном
 callback=lambda primary, fallback, error: log(...), # Callback при активации
)
```

**Умная маршрутизация:**
- `on_rate_limit` → fallback на дешёвую/другую модель при 429
- `on_context_overflow` → fallback на модель с большим context window
- `on_error` → общий fallback при 5xx/network errors
- Приоритет: error-specific → general (только для retryable ошибок)

**Оркестрационная значимость:** Error-specific fallback — паттерн, дополняющий retry и circuit breaker. Вместо единого fallback-механизма (при любой ошибке → альтернативный обработчик), Agno классифицирует ошибки и маршрутизирует к разным fallback-моделям: rate limit → дешёвая модель с другим провайдером, context overflow → модель с большим context window, 5xx/network → надежная резервная модель. Приоритет: error-specific fallback → general fallback.

> ⚠️ **Архитектурные ограничения:**
> - FallbackConfig работает **только на уровне модели** — замена одного LLM на другой в рамках текущего вызова. Не предоставляет: step-level retry с другой конфигурацией шага, workflow-level fallback (путь A → при ошибке путь B), cross-agent fallback (агент A → при ошибке агент B).
> - Для корректной работы error-specific fallback необходима классификация ошибок (rate limit ≠ timeout ≠ malformed output ≠ context overflow) и явная модель ошибок. Без классификации fallback-логика становится неуправляемой.
> - FallbackConfig не включает retry с exponential backoff — это отдельная механика на уровне Step (`max_retries` без backoff).

---

### 3.4 🟡 HITL — 3 режима Human-in-the-Loop (`workflow/step.py`)

**Что у них:** Каждый Step может требовать участия человека в 3 режимах:

| Режим | Параметры | Поведение |
| --- | --- | --- |
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

**Оркестрационная значимость:** Production-ready HITL — востребованная функция для автономных цепочек. Три режима покрывают основные сценарии: confirm → input → review. Callable в `requires_output_review` позволяет условный review (только если результат не устраивает).

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

**`forward_iteration_output: bool = False`** — если True, output каждой итерации передаётся как input следующей, образуя цепочку `output[i] → input[i+1]`. При False каждая итерация получает исходный input loop.

**Оркестрационная значимость:** Комбинация `max_iterations` + `end_condition` — стандартный паттерн для итеративных процессов: верхняя граница (safety) + содержательная проверка (semantics). CEL-выражения позволяют декларативно описать условие без кода. `forward_iteration_output` обеспечивает контекстную связность между итерациями — каждый шаг получает результат предыдущего.

---

### 3.6 🟡 CompressionManager — LLM-based сжатие (`compression/manager.py`)

**Что у них:** Автоматическое сжатие tool call results при context overflow:

```python
CompressionManager(
 model=Claude(id="claude-sonnet-4"),
 compress_tool_results=True, # Включить сжатие
 compress_tool_results_limit=2000, # Порог по токенам
 compress_token_limit=1000, # Целевой размер после сжатия
)
```

**Подход:** LLM-суммаризация с explicit preservation rules:
- Сохранять: факты, числа, даты, entities, identifiers
- Сжимать: описания, пояснения, списки
- Удалять: вступления, filler, форматирование, redundancy

**Оркестрационная значимость:** В итеративных loops с tool calls контекст растёт монотонно. LLM-based сжатие tool results — компромисс между потерей информации и переполнением context window: сохраняются факты/числа/идентификаторы, удаляются filler и redundancy.

> ⚠️ **Trade-off:** LLM-based сжатие добавляет один дополнительный LLM API call на каждый цикл компрессии — дополнительная latency (время ответа модели сжатия) и стоимость (token consumption модели сжатия). Альтернатива — auto-summarization всего диалога — более агрессивный подход с большей потерей контекста, но без отдельного вызова на каждый tool result.

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

**Оркестрационная значимость:** Tool hooks — middleware-паттерн на уровне tool calls. Позволяет реализовать cross-cutting concerns (логирование, валидация, rate limiting, аудит) без модификации логики tool'а. Composable: несколько hooks выполняются последовательно, формируя pipeline.

> ⚠️ **Слепая зона:** В middleware-цепочке hook'ов не специфицирована семантика ошибок. Если pre-hook выбрасывает исключение — прерывается ли tool call? Если post-hook падает — сохраняется ли результат tool call? Без явного контракта error handling поведение зависит от имплементации хука.

---

### 3.8 🟡 Guardrails — pre/post execution checks (`guardrails/`)

**Что у них:** 3 встроенных guardrail:

| Guardrail | Назначение |
| --- | --- |
| `PIIDetectionGuardrail` | Обнаружение персональных данных (PII) |
| `PromptInjectionGuardrail` | Обнаружение prompt injection атак |
| `OpenAIModerationGuardrail` | Модерация контента через OpenAI API |

**Механика:** Guardrails реализуют `BaseGuardrail` и могут использоваться как `pre_hooks` / `post_hooks`. Выбрасывают исключение при нарушении.

**Оркестрационная значимость:** Guardrails — «pre-flight checks» перед выполнением шага и «post-flight checks» после. Для production-систем: проверка входных данных (PII, prompt injection) перед отправкой к модели и модерация выходных данных. Дополняет evaluation framework, обеспечивая безопасность на уровне данных, а не только качества результата.

> ⚠️ **Ограничение:** Guardrails — реактивный механизм (detect → reject), а не превентивный (sanitize → transform). Они выбрасывают исключение при нарушении, но не преобразуют вход в безопасную форму. Для production-сценариев может потребоваться отдельный слой нормализации.

---

### 3.9 🔴 Error propagation и state flow — архитектурный пробел

В §3.1–§3.8 описаны индивидуальные строительные блоки workflow engine, но не раскрыты два ключевых cross-cutting вопроса: **как распространяются ошибки** между блоками и **как передаётся состояние**.

**Error propagation:**

| Сценарий | Документировано? | Ожидаемое поведение |
| --- | --- | --- |
| Step failure внутри Steps | Частично (`on_error` на Step) | `fail` → остановка цепочки, `skip` → пропуск, `pause` → HITL |
| Step failure внутри Loop | Нет | Неясно: повторяется ли итерация, пропускается ли шаг или loop прерывается |
| Одна ветвь Parallel завершилась с ошибкой | Нет | Неясно: продолжают ли другие ветви работу? Каков итоговый результат Parallel? |
| Router не смог выбрать шаг | Нет | Неясно: fallback-шаг? Исключение? |
| Nested workflow error | Нет | Неясно: bubbling до родительского workflow или изоляция? |

**State flow:**

- StepInput / StepOutput — типизированные обёртки для передачи данных между шагами. Контент может быть строкой или структурой.
- В Parallel — нет документированного механизма для доступа к результату одной ветви из другой (shared state). Каждая ветвь получает одинаковый input.
- В Loop — `forward_iteration_output` управляет передачей output между итерациями, но нет документированного способа передать данные из loop наружному workflow, кроме как через последний StepOutput.
- В Router — selector-функция получает input, но не имеет доступа к результатам предыдущих шагов workflow (только к текущему контексту).

> ⚠️ **Архитектурная оценка:** Отсутствие документированной модели error propagation — значительный пробел для orchestration engine. Без чётких контрактов (какая ошибка куда распространяется, как изолируются сбои, как определяется итоговый статус составного блока) поведение при частичных отказах непредсказуемо. Для production-систем это требует либо эмпирического тестирования каждого сценария, либо чтения исходного кода `workflow/`.

## 4. Прочие возможности (вне оркестрации)

### 4.1 🟢 AgentOS Runtime (FastAPI)

Agno включает полноценный FastAPI-сервер с REST API, WebSocket, per-user/session isolation, horizontal scaling. Если понадобится API-доступ — это отдельный проект.

### 4.2 🟢 Knowledge / RAG / VectorDB

Agno имеет встроенный RAG pipeline с vector search. Для оркестратора цепочек это out of scope — RAG должен быть на уровне runner'ов или отдельного сервиса.

### 4.3 🟢 Reasoning Mode

Agno поддерживает step-by-step reasoning с отдельной reasoning_model/reasoning_agent. Это возможность уровня LLM, не связанная с оркестрацией цепочек.

### 4.4 🟢 Scheduler

Background execution с расписанием. Не актуально для CLI-утилиты — запуск по расписанию можно сделать через cron/CI.

### 4.5 🟢 Culture / LearningMachine

Cultural Knowledge и LearningMachine — Agno-специфичные фичи для domain-specific knowledge и learnings. Не применимы к нашему подходу.

### 4.6 🟢 CEL (Common Expression Language) как зависимость

CEL — мощный, но добавляет зависимость на Google CEL evaluator. Для наших целей достаточно callable-условий и простых выражений в YAML.

---

## 5. Сводка по оркестрации

| Возможность | Статус в продукте | Описание |
| --- | --- | --- |
| Loop с end_condition (callable) | 🟡 P2 | Усиление fix_iterations: детерминированная проверка завершения вместо одного лишь max_iterations |
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
