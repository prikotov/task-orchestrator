# Исследование: CrewAI, LangGraph, AutoGen — три Python multi-agent фреймворка

> **Проекты:**
> - [github.com/crewAIInc/crewAI](https://github.com/crewAIInc/crewAI) (★ 49k, MIT)
> - [github.com/langchain-ai/langgraph](https://github.com/langchain-ai/langgraph) (★ 30k, MIT)
> - [github.com/microsoft/autogen](https://github.com/microsoft/autogen) (★ 57k, CC-BY-4.0 / MIT)
>
> **Дата анализа:** 2026-04-21
> **Язык:** Python (все три)
> **Аналитик:** Технический писатель (Гермиона)

---

## 1. Обзор проектов

### 1.1 CrewAI

CrewAI — fast, standalone Python multi-agent фреймворк, построенный с нуля (без зависимости от LangChain). Две основные модели:

1. **Crews** — команды AI-агентов с ролевой автономией (Agent → Task → Crew). Агентам назначаются роли (`role`, `goal`, `backstory`), задачи (`description`, `expected_output`) объединяются в Crew с процессом `sequential` или `hierarchical` (manager).
2. **Flows** — event-driven workflow: декораторы `@start`, `@listen`, `@router` связывают методы в направленный граф выполнения. Flows = production-grade контроль, Crews = автономная коллаборация. Обе модели комбинируются.

**Архитектура:**

```
lib/crewai/src/crewai/
  agent/                     Agent: role, goal, backstory, tools, LLM
  agents/                    Agent builders, caching, parser
  crew.py                    Crew: orchestrates agents + tasks
  task.py                    Task: description, expected_output, agent assignment
  process.py                 Process: sequential | hierarchical
  flow/
    flow.py                  Flow: event-driven workflow engine
    flow_context.py          Execution context (state propagation)
    persistence/             Flow state persistence
    visualization/           Flow graph visualization
  llm.py / llms/             LLM abstraction (multi-provider)
  memory/                    Short-term + long-term memory
  knowledge/                 RAG integration
  mcp/                       MCP (Model Context Protocol) integration
  tools/                     Built-in + custom tool framework
  security/                  Fingerprint, security config
  skills/                    Agent Skills (SKILL.md-based)
  state/                     Checkpoint configuration
  telemetry/                 Usage metrics (PostHog)
```

**Ключевые характеристики:**

| Характеристика | Значение |
|---|---|
| **Тип** | Multi-agent фреймворк (role-based + event-driven) |
| **Модель выполнения** | Sequential / Hierarchical (Crews) + Event-driven DAG (Flows) |
| **State management** | In-memory + checkpoint persistence (SQLite) |
| **Провайдеры** | OpenAI, Anthropic, Google, Ollama, 20+ через LiteLLM |
| **Расширяемость** | Custom tools, Skills (SKILL.md), MCP, RAG, Flows |
| **Человеческий контроль** | Human feedback в Crews и Flows |
| **Обучение** | 100,000+ сертифицированных разработчиков |
| **Лицензия** | MIT |

---

### 1.2 LangGraph

LangGraph — low-level orchestration framework для построения stateful AI-агентов и multi-step workflows. Построен на абстракции directed graph (узлы = функции, рёбра = переходы), вдохновлён Pregel/Apache Beam. Часть экосистемы LangChain; требует `langchain-core` как обязательную зависимость, но может использоваться без полного набора LangChain.

**Архитектура:**

```
libs/langgraph/langgraph/
  graph/
    state.py                 StateGraph: граф с shared state
    _branch.py               Conditional branching
    _node.py                 Node definitions
    message.py               Message graph utilities
  pregel/                    Pregel execution engine (supersteps)
  channels/                  State channels: reducers, aggregation
  func/                      Functional API (@entrypoint, @task)
  errors.py                  Error types
  types.py                   RetryPolicy, Send, Command
  callbacks.py               Execution callbacks
  runtime.py                 Runtime context injection

libs/checkpoint/             State persistence (отдельный пакет)
  langgraph/checkpoint/
    base/                    Base checkpointer interface
    memory/                  In-memory checkpointer
  langgraph/store/           Long-term key-value store
    base/
    memory/

libs/checkpoint-sqlite/      SQLite-backed checkpoint (отдельный пакет)
libs/checkpoint-postgres/    PostgreSQL-backed checkpoint (отдельный пакет)
```

**Ключевые характеристики:**

| Характеристика | Значение |
|---|---|
| **Тип** | Low-level orchestration framework (graph-based) |
| **Модель выполнения** | Directed graph (StateGraph): nodes → conditional edges → superstep execution |
| **State management** | TypedDict state + reducer functions, checkpoint persistence (memory / SQLite / PostgreSQL) |
| **Провайдеры** | Любые через langchain-core (обязательная зависимость) или прямые API |
| **Расширяемость** | Subgraphs, branches, Send (map-reduce), human-in-the-loop interrupts |
| **Человеческий контроль** | Interrupts + state inspection/modification |
| **Durable execution** | Checkpoints + replay, survive failures |
| **Лицензия** | MIT |

---

### 1.3 AutoGen (Microsoft)

AutoGen — фреймворк для multi-agent AI-приложений от Microsoft Research. Слоистая архитектура: Core API (event-driven runtime) → AgentChat API (high-level patterns) → Extensions (LLM clients, code execution). **⚠️ Maintenance mode** — новые проекты рекомендуется начинать с Microsoft Agent Framework (MAF).

**Архитектура:**

```
python/packages/
  autogen-core/src/autogen_core/
    _agent.py                BaseAgent: message handler
    _agent_runtime.py        AgentRuntime: message passing, event-driven
    _routed_agent.py         RoutedAgent: type-based message routing
    _single_threaded_agent_runtime.py  Single-threaded runtime
    _topic.py                Topic-based pub/sub
    _subscription.py         Subscription management
    _intervention.py         Intervention/hook mechanism
    code_executor/           Code execution sandbox
    memory/                  Memory management
    models/                  Model client abstractions
    tools/                   Tool framework

  autogen-agentchat/src/autogen_agentchat/
    agents/                  AssistantAgent, CodeExecutorAgent, ...
    teams/_group_chat/
      _base_group_chat.py        Base group chat orchestration
      _base_group_chat_manager.py  Manager: turn tracking, termination
      _round_robin_group_chat.py   Round-robin orchestration
      _selector_group_chat.py      LLM-based speaker selection
      _swarm_group_chat.py         Swarm (handoff-based) orchestration
      _magentic_one/               Magentic-One: generalist multi-agent team
      _graph/                      Graph-based orchestration (DAG)
    conditions/              Termination conditions (max_turns, text_mention, ...)
    state/                   Team state management
    tools/                   AgentTool (wrap agent as tool)

  autogen-ext/               Extensions: OpenAI client, MCP, Docker code execution
  autogen-studio/            No-code GUI for prototyping
```

**Ключевые характеристики:**

| Характеристика | Значение |
|---|---|
| **Тип** | Multi-agent framework (event-driven + conversation-based) |
| **Модель выполнения** | Event-driven (Core API) / Group Chat patterns (AgentChat) / Graph (DAG) |
| **State management** | Message thread per group chat, model_context per agent |
| **Провайдеры** | OpenAI, Azure, Google, Anthropic через extensions |
| **Расширяемость** | Custom agents, tools, group chat managers, subscriptions |
| **Человеческий контроль** | Human-in-the-loop через intervention hooks |
| **Кросс-язычность** | Python + .NET |
| **Статус** | ⚠️ Maintenance mode → Microsoft Agent Framework (MAF) |
| **Лицензия** | CC-BY-4.0 (docs) / MIT (code) |

---

## 2. Сравнительная таблица: три фреймворка vs. task-orchestrator

| Функция | Task Orchestrator | CrewAI | LangGraph | AutoGen |
|---|---|---|---|---|
| **Язык** | PHP 8.4 | Python | Python | Python + .NET |
| **Модель оркестрации** | Chain (sequential/dynamic) | Sequential / Hierarchical (Crews) + Event-driven (Flows) | Directed graph (StateGraph) + superstep execution | Event-driven (Core) / Group chat (AgentChat) / Graph |
| **State management** | In-memory + JSONL audit | In-memory + checkpoint (SQLite) | TypedDict + reducers + checkpoint (memory/SQLite/PostgreSQL) | Message thread + model context |
| **Error handling** | Retry + Circuit Breaker | Basic retry (LLM level) | RetryPolicy per node, durable execution | CancellationToken, exception propagation |
| **Quality Gates** | ✅ Shell-команды | ❌ Нет встроенных | ❌ Нет встроенных | ❌ Нет встроенных |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based) | ⚠️ Cost tracking (records, no limits) | ❌ Нет | ❌ Нет |
| **Итерационные циклы** | ✅ fix_iterations (max_iterations) | ⚠️ Hierarchical process (manager retries) | ✅ Cycles в графе (conditional edge назад) | ⚠️ max_turns в group chat |
| **Fallback routing** | ✅ Per-step fallback runner | ❌ Нет (ручное переключение) | ✅ Conditional edges | ❌ Нет |
| **Circuit Breaker** | ✅ 3-state (closed/open/half-open) | ❌ Нет | ❌ Нет | ❌ Нет |
| **Audit Trail** | ✅ JSONL | ⚠️ Event bus + telemetry | ⚠️ Checkpoint history | ⚠️ Logging |
| **Ролевые промпты** | ✅ .md файлы (18+ ролей) | ✅ role/goal/backstory в YAML | ❌ Системный промпт = node function | ✅ system_message per agent |
| **Multiple runners** | ✅ Pi + Codex (через interface) | ✅ Multi-provider (LiteLLM) | ✅ Любые через langchain-core Runnable | ✅ Multi-provider (extensions) |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❌ Плоская структура (lib/crewai/) | ❌ Flat module (libs/langgraph/) | ⚠️ Слоистая (core/agentchat/ext) |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Прямой вызов | ❌ Node functions | ✅ RoutedAgent + subscriptions |
| **Human-in-the-loop** | ❌ Нет | ✅ Human feedback | ✅ Interrupts + state modification | ✅ Intervention hooks |
| **Memory** | ❌ Нет | ✅ Short-term + long-term + RAG | ✅ Short-term (state) + long-term (store) | ✅ model_context + memory module |
| **Code execution** | ❌ Внешние CLI-tools | ⚠️ Custom tools | ⚠️ External tools | ✅ Built-in sandbox (Docker) |
| **MCP support** | ❌ Нет | ✅ MCP integration | ⚠️ Через LangChain tools | ✅ MCP workbench |
| **Durable execution** | ❌ Нет | ⚠️ Checkpoint persistence | ✅ Checkpoint + replay + resume | ❌ Нет |
| **Parallel execution** | ❌ Sequential only | ⚠️ Concurrent tasks в Crew | ✅ Send (map-reduce), fan-out/fan-in | ✅ Async agents |
| **Multi-agent collaboration** | ❌ Single chain | ✅ Crew (role-based team) | ✅ Subgraphs, multi-agent patterns | ✅ Group chat (round-robin, selector, swarm) |
| **Graph visualization** | ❌ Нет | ✅ Flow visualization | ✅ LangGraph Studio | ✅ AutoGen Studio |
| **Статус проекта** | Активный | Активный, commercial | Активный, enterprise | ⚠️ Maintenance mode |

---

## 3. Что полезно взять и почему

### 3.1 🟡 Graph-based conditional routing (LangGraph)

**Что у них:** LangGraph строит directed graph, где переходы между узлами определяются функциями-предикатами:

```python
from langgraph.graph import StateGraph, START, END

graph = StateGraph(State)
graph.add_node("developer", developer_node)
graph.add_node("reviewer", reviewer_node)
graph.add_node("fixer", fixer_node)

graph.add_edge(START, "developer")
graph.add_edge("developer", "reviewer")
graph.add_conditional_edges("reviewer", should_fix,
    {"fix": "fixer", "done": END})
graph.add_edge("fixer", "reviewer")  # Cycle back
```

**Почему нам интересно:** Наши fix_iterations — это простая группа шагов с лимитом. Graph-модель позволяет описывать более сложные циклы: условные переходы, branching, fan-out/fan-in. Сейчас это R&D, но если chain-модель станет ограничением — LangGraph показывает path forward.

**Отличие от нашей реализации:**
- У нас: YAML chain с линейными шагами + fix_iterations как group
- У них: произвольный directed graph с conditional edges и cycles

---

### 3.2 🟡 Durable Execution / Checkpointing (LangGraph)

**Что у них:** LangGraph поддерживает checkpoint persistence — состояние графа сохраняется после каждого superstep. При сбое выполнение можно возобновить с последнего checkpoint:

```python
from langgraph.checkpoint.sqlite import SqliteSaver

checkpointer = SqliteSaver.from_conn_string(":memory:")
graph = app.compile(checkpointer=checkpointer)

# При сбое — возобновляем с checkpoint:
config = {"configurable": {"thread_id": "thread-1"}}
result = graph.invoke({"input": "..."}, config)
```

**Почему нам интересно:** Для длинных цепочек (implement → review → fix → review → test → ...) при сбое на шаге 4 можно возобновить с шага 3, а не начинать сначала. Наш JSONL audit trail записывает что произошло, но не позволяет replay.

---

### 3.3 🟡 Hierarchical Orchestration с Manager (CrewAI)

**Что у них:** В режиме `Process.hierarchical` CrewAI автоматически создаёт manager-агента, который:
- Планирует порядок выполнения задач
- Делегирует задачи агентам
- Проверяет результаты
- Принимает решение о retry или переходе к следующей задаче

```python
crew = Crew(
    agents=[researcher, writer, editor],
    tasks=[research_task, write_task, edit_task],
    process=Process.hierarchical,  # Manager auto-created
    manager_llm="gpt-4o",
)
```

**Почему нам интересно:** Наш подход — статическая YAML chain, где порядок шагов определён заранее. Hierarchical delegation — это dynamic routing на основе результата предыдущего шага. Для сложных задач это может быть эффективнее фиксированных цепочек.

---

### 3.4 🟡 Event-driven Flow Engine (CrewAI)

**Что у них:** CrewAI Flows — event-driven workflow с декораторами:

```python
from crewai.flow.flow import Flow, listen, start, router

class MyFlow(Flow):
    @start()
    def begin(self): return {"data": "..."}

    @listen(begin)
    def process(self, data): return {"result": "..."}

    @router(process)
    def route(self, result):
        if result["ok"]: return "success"
        return "retry"

    @listen("success")
    def finalize(self, data): ...

    @listen("retry")
    def retry_step(self, data): ...
```

**Почему нам интересно:** Event-driven модель хорошо сочетается с нашим decorator pattern. Можно реализовать события на уровне chain executor (step_completed, step_failed, budget_exceeded) и дать возможность подписчикам реагировать.

---

### 3.5 🟡 Multi-agent Collaboration Patterns (AutoGen)

**Что у них:** AutoGen предоставляет несколько готовых паттернов group chat:

| Паттерн | Описание |
|---|---|
| `RoundRobinGroupChat` | Агенты говорят по очереди |
| `SelectorGroupChat` | LLM выбирает следующего спикера |
| `SwarmGroupChat` | Агент передаёт контроль (handoff) другому |
| `MagenticOne` | Generalist team: orchestrator + web surfer + coder + file surfer |

**Почему нам интересно:** Если task-orchestrator будет развиваться в сторону multi-agent (несколько runner'ов с разными ролями одновременно), эти паттерны — готовые модели взаимодействия. Сейчас мы single-chain, но swarm/handoff модель может быть полезна для dynamic chains.

---

### 3.6 🟡 Termination Conditions (AutoGen)

**Что у них:** Автоматическое определение момента остановки:

```python
from autogen_agentchat.conditions import (
    TextMentionTermination,
    MaxMessageTermination,
    TokenUsageTermination,
    SourceMatchTermination,
    TimeoutTermination,
)

termination = (
    TextMentionTermination("TERMINATE") |
    MaxMessageTermination(10) |
    TokenUsageTermination(max_total_token=10000)
)
```

Комбинируются через `|` (OR) и `&` (AND).

**Почему нам интересно:** У нас есть `max_iterations` для fix_iterations и `BudgetVo` для бюджетного контроля. Дополнительные условия остановки (timeout, token limit, keyword-based) могут обогатить нашу модель.

---

### 3.7 🟡 Memory System (CrewAI / LangGraph)

**Что у них:** Оба фреймворка поддерживают многоуровневую память:
- **Short-term** — контекст текущей сессии/выполнения
- **Long-term** — персистентное хранение между запусками (SQLite / PostgreSQL)
- **RAG** — поиск по knowledge base для обогащения промптов

**Почему нам интересно:** Наш оркестратор stateless (in-memory + JSONL). Memory позволит цепочкам «учиться» на предыдущих запусках: кэшировать результаты типовых задач, накапливать контекст проекта. Пока это P3/R&D.

---

### 3.8 🟡 Loop Detection через Termination Conditions (AutoGen)

**Что у них:** AutoGen не имеет явного loop detection, но комбинация `MaxMessageTermination` + `TimeoutTermination` предотвращает бесконечные циклы в group chat. LangGraph имеет `RetryPolicy` с max_attempts.

**Почему нам интересно:** Мы уже идентифицировали loop detection (из исследования Crush) как P2. Паттерн AutoGen — декларативные условия остановки — более гибкий, чем хардкод window size.

---

## 4. Что НЕ берём и почему

### 4.1 🟢 Python как язык реализации

Все три фреймворка — Python. Task-orchestrator — PHP/Symfony. Мы не можем использовать их как dependency или library. Единственный вариант — заимствование паттернов на архитектурном уровне.

### 4.2 🟢 LLM-level integration (LLM абстракции)

CrewAI, LangGraph, AutoGen — все работают на уровне прямых вызовов LLM API. Наш оркестратор работает на уровне runner'ов (pi, codex), которые сами управляют LLM-взаимодействием. Разный уровень абстракции.

### 4.3 🟢 Code Execution Sandbox (AutoGen)

AutoGen имеет встроенный sandbox для выполнения кода (Docker). Это valuable для агента-кодера, но наш оркестратор делегирует выполнение runner'ам. Sandbox — забота runner'а, не оркестратора.

### 4.4 🟢 No-code GUI / Studio (AutoGen Studio, LangGraph Studio)

Визуальные интерфейсы для прототипирования. Красиво, но не подходит для CLI-first Symfony bundle. Наш UI — Symfony Console commands.

### 4.5 🟢 Cloud / SaaS (CrewAI Enterprise, LangSmith)

Все три предлагают cloud-решения для deployment и monitoring. Task-orchestrator — self-contained Symfony bundle. Cloud deployment — отдельная область.

### 4.6 🟢 LangChain Dependency (LangGraph)

LangGraph требует `langchain-core` как обязательную зависимость (указано в `pyproject.toml`). Хотя README утверждает «может использоваться standalone», это означает «без полного набора LangChain», а не без зависимостей вообще. Мы не используем LangChain и не хотим эту зависимость.

### 4.7 🟢 Maintenance Mode (AutoGen)

AutoGen в maintenance mode. Microsoft рекомендует переход на Microsoft Agent Framework. Заимствовать паттерны из проекта без будущего развития — рискованно. Паттерны group chat базовые и не зависят от конкретной реализации.

---

## 5. Сводка рекомендаций

| Фича | Источник | Приоритет | Обоснование |
|---|---|---|---|
| Chain orchestration (static + dynamic) | — | ✅ Уже есть | Core-функциональность task-orchestrator |
| Retry + Circuit Breaker | Bernstein | ✅ Уже есть | Устойчивость при сбоях |
| Quality Gates | Bernstein | ✅ Уже есть | Автоматическая проверка кода |
| Budget control | Bernstein | ✅ Уже есть | Предотвращение runaway spending |
| Fix iterations | Bernstein | ✅ Уже есть | Closed-loop цикл разработки |
| JSONL Audit Trail | Bernstein | ✅ Уже есть | Воспроизводимость и отладка |
| Declarative termination conditions | AutoGen | 🟡 P2 | Timeout, token limit, keyword — обогащение модели остановки |
| Graph-based conditional routing | LangGraph | 🟡 P3 | Для сложных dynamic chains, если YAML станет ограничением |
| Checkpoint / Durable execution | LangGraph | 🟡 P3 | Resume после сбоя для длинных цепочек |
| Hierarchical orchestration | CrewAI | 🟡 P3 | Dynamic delegation вместо статических chains |
| Event-driven architecture | CrewAI Flows | 🟡 P3 | Events на уровне chain executor |
| Multi-agent patterns | AutoGen | 🟡 P3 | Swarm / handoff для будущих dynamic chains |
| Memory system | CrewAI / LangGraph | 🟡 P3 | Кэширование, обучение на предыдущих запусках |
| Python integration | Все три | 🟢 — | Разный язык, не dependency |
| LLM абстракции | Все три | 🟢 — | Разный уровень (runner vs LLM) |
| Code execution sandbox | AutoGen | 🟢 — | Задача runner'ов |
| GUI / Studio | AutoGen, LangGraph | 🟢 — | Разная парадигма |
| Cloud / SaaS | Все три | 🟢 — | Self-contained bundle |
| LangChain dependency | LangGraph | 🟢 — | Не используем LangChain |
| Maintenance mode | AutoGen | 🟢 — | Проект заморожен |

---

## 6. Указатель источников для деталей

### CrewAI

- [`lib/crewai/src/crewai/crew.py`](https://github.com/crewAIInc/crewAI/blob/main/lib/crewai/src/crewai/crew.py) — Crew: orchestration of agents + tasks, sequential/hierarchical processes
- [`lib/crewai/src/crewai/process.py`](https://github.com/crewAIInc/crewAI/blob/main/lib/crewai/src/crewai/process.py) — Process enum: sequential | hierarchical
- [`lib/crewai/src/crewai/task.py`](https://github.com/crewAIInc/crewAI/blob/main/lib/crewai/src/crewai/task.py) — Task: description, expected_output, agent assignment, guardrails
- [`lib/crewai/src/crewai/flow/flow.py`](https://github.com/crewAIInc/crewAI/blob/main/lib/crewai/src/crewai/flow/flow.py) — Flow: event-driven workflow engine (@start, @listen, @router)
- [`lib/crewai/src/crewai/skills/`](https://github.com/crewAIInc/crewAI/blob/main/lib/crewai/src/crewai/skills/) — Agent Skills framework (SKILL.md)
- [`lib/crewai/src/crewai/memory/`](https://github.com/crewAIInc/crewAI/blob/main/lib/crewai/src/crewai/memory/) — Memory: short-term, long-term, entity
- [docs.crewai.com](https://docs.crewai.com) — Официальная документация
- [README.md](https://github.com/crewAIInc/crewAI/blob/main/README.md) — Overview, features, comparison with LangGraph

### LangGraph

- [`libs/langgraph/langgraph/graph/state.py`](https://github.com/langchain-ai/langgraph/blob/main/libs/langgraph/langgraph/graph/state.py) — StateGraph: directed graph with shared state, compile() → CompiledStateGraph
- [`libs/langgraph/langgraph/graph/_branch.py`](https://github.com/langchain-ai/langgraph/blob/main/libs/langgraph/langgraph/graph/_branch.py) — Conditional branching
- [`libs/langgraph/langgraph/pregel/`](https://github.com/langchain-ai/langgraph/blob/main/libs/langgraph/langgraph/pregel/) — Pregel execution engine (supersteps)
- [`libs/langgraph/langgraph/types.py`](https://github.com/langchain-ai/langgraph/blob/main/libs/langgraph/langgraph/types.py) — RetryPolicy, Send, Command, CachePolicy
- [`libs/checkpoint/`](https://github.com/langchain-ai/langgraph/blob/main/libs/checkpoint/) — Checkpoint persistence (базовый интерфейс, memory, store)
- [`libs/checkpoint-sqlite/`](https://github.com/langchain-ai/langgraph/blob/main/libs/checkpoint-sqlite/) — SQLite-backed checkpoint store
- [`libs/checkpoint-postgres/`](https://github.com/langchain-ai/langgraph/blob/main/libs/checkpoint-postgres/) — PostgreSQL-backed checkpoint store
- [docs.langchain.com/langgraph](https://docs.langchain.com/oss/python/langgraph/overview) — Официальная документация

### AutoGen

- [`python/packages/autogen-core/src/autogen_core/_agent_runtime.py`](https://github.com/microsoft/autogen/blob/main/python/packages/autogen-core/src/autogen_core/_agent_runtime.py) — AgentRuntime: message passing, event-driven execution
- [`python/packages/autogen-core/src/autogen_core/_routed_agent.py`](https://github.com/microsoft/autogen/blob/main/python/packages/autogen-core/src/autogen_core/_routed_agent.py) — RoutedAgent: type-based message routing
- [`python/packages/autogen-agentchat/src/autogen_agentchat/teams/_group_chat/`](https://github.com/microsoft/autogen/blob/main/python/packages/autogen-agentchat/src/autogen_agentchat/teams/_group_chat/) — Group chat patterns: round-robin, selector, swarm, Magentic-One, graph
- [`python/packages/autogen-agentchat/src/autogen_agentchat/conditions/`](https://github.com/microsoft/autogen/blob/main/python/packages/autogen-agentchat/src/autogen_agentchat/conditions/) — Termination conditions (max_turns, text_mention, token_usage, timeout)
- [`python/packages/autogen-agentchat/src/autogen_agentchat/tools/`](https://github.com/microsoft/autogen/blob/main/python/packages/autogen-agentchat/src/autogen_agentchat/tools/) — AgentTool: wrap agent as tool for orchestration
- [microsoft.github.io/autogen](https://microsoft.github.io/autogen/) — Официальная документация
- [github.com/microsoft/agent-framework](https://github.com/microsoft/agent-framework) — Microsoft Agent Framework (successor)

---

📚 **Источники:**
1. [github.com/crewAIInc/crewAI](https://github.com/crewAIInc/crewAI) — репозиторий CrewAI
2. [github.com/langchain-ai/langgraph](https://github.com/langchain-ai/langgraph) — репозиторий LangGraph
3. [github.com/microsoft/autogen](https://github.com/microsoft/autogen) — репозиторий AutoGen
4. [docs.crewai.com](https://docs.crewai.com) — документация CrewAI
5. [docs.langchain.com/oss/python/langgraph](https://docs.langchain.com/oss/python/langgraph/overview) — документация LangGraph
