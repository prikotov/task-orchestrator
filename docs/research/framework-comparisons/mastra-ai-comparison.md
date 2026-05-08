# Исследование: Mastra AI — TypeScript-фреймворк для AI-агентов и workflows

> **Проект:** [github.com/mastra-ai/mastra](https://github.com/mastra-ai/mastra)
> **Дата анализа:** 2026-04-21
> **Язык:** TypeScript (Node.js/Bun)
> **Лицензия:** Apache License 2.0 (core) + Mastra Enterprise License (`ee/` директории)
> **Аналитик:** Технический писатель (Гермиона)

---

## 1. Обзор проекта

Mastra — TypeScript-фреймворк для построения AI-powered приложений и агентов. Создан командой Gatsby (Y Combinator W25). Ключевая идея: унифицированный TypeScript-стек для прототипирования и production-ready AI-приложений с интеграцией в React, Next.js, Node.js или standalone-сервер.

Mastra **не является** оркестратором внешних CLI-ассистентов (как task-orchestrator). Это **SDK-фреймворк**, работающий на уровне прямых LLM API. В отличие от task-orchestrator, который управляет цепочками внешних runner'ов (pi, codex), Mastra работает внутри процесса, напрямую вызывая LLM-провайдеров через абстракцию AI SDK.

### Архитектура

```
packages/
 core/ Основной пакет (@mastra/core)
 src/
 agent/ Агенты: LLM-сессии, инструменты, memory, voice
 agent.ts Agent class: generate/stream, tools, memory, processors
 agent.types.ts Типы: AgentConfig, AgentExecutionOptions
 message-list/ MessageList: управление сообщениями, дедупликация
 trip-wire.ts TripWire: досрочная остановка с retry
 workflows/ Workflows: step-based execution engine
 workflow.ts Workflow class: .then(), .branch(), .parallel(), .dowhile(), .dountil(), .foreach()
 step.ts Step interface + createStep() factory
 execution-engine.ts ExecutionEngine: абстрактный класс для execution backends
 default.ts DefaultExecutionEngine: in-process выполнение
 handlers/ control-flow, entry, sleep, step
 types.ts Типы: StepResult, WorkflowState, StepFlowEntry
 memory/ Система памяти
 memory.ts MastraMemory: conversation history, semantic recall, working memory
 types.ts MemoryConfig, SemanticRecall, WorkingMemory, ObservationalMemory
 evals/ Evaluation framework
 base.ts Scorer: judge-based, trajectory-based, custom
 types.ts ScorerConfig, ScorerRun, Trajectory
 tools/ Инструменты агентов
 tool.ts Tool class + createTool() factory
 types.ts CoreTool, ToolExecutionContext
 processors/ Processor pipeline (input/output processors)
 index.ts Processor interface: processInput, processOutputStream, processOutputResult
 runner.ts ProcessorRunner: orchestration of processor chains
 storage/ Storage backends
 base.ts MastraCompositeStore: 15+ storage domains
 filesystem.ts Filesystem-backed store
 mcp/ Model Context Protocol
 vector/ Vector store abstraction
 observability/ OpenTelemetry tracing + spans
 rag/ RAG pipeline (в packages/rag)
 llm/ Model routing: 40+ провайдеров
 memory/ Пакет @mastra/memory (расширяет MastraMemory из core, v1.16.0)
 rag/ Пакет @mastra/rag
 evals/ Пакет @mastra/evals
 server/ HTTP server + API endpoints
 playground/ Playground UI (Studio)
 deployer/ Deployment adapters (Cloudflare, Vercel, etc.)
 cli/ Mastra CLI
```

### Ключевые характеристики

| Характеристика | Значение |
| --- | --- |
| **Тип** | SDK-фреймворк (TypeScript), работает на уровне LLM API |
| **Модель выполнения (Agents)** | Agent loop (LLM → tool call → observation → LLM → ...) |
| **Модель выполнения (Workflows)** | Step-based: `.then()`, `.branch()`, `.parallel()`, `.dowhile()`, `.dountil()`, `.foreach()` |
| **State management** | Pluggable storage (22+ адаптеров: LibSQL, PostgreSQL, MongoDB, Redis, ClickHouse, Cloudflare D1, Upstash, Pinecone, Qdrant и др.) |
| **Провайдеры** | 40+ LLM-провайдеров через model router |
| **Расширяемость** | Processors (input/output), MCP-серверы, Tools, custom storage |
| **Memory** | Conversation history + Semantic recall (RAG) + Working memory + Observational memory |
| **Evals** | Judge-based, trajectory-based, custom scorers |
| **Human-in-the-loop** | Suspend/resume с persistence (бесконечная пауза) |
| **Лицензия** | Apache 2.0 (core) + Enterprise (ee/) |

### Основные компоненты

| Компонент | Назначение |
| --- | --- |
| [`packages/core/src/agent/agent.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/agent/agent.ts) | Agent: generate/stream, tools, memory, processors, network() для multi-agent delegation |
| [`packages/core/src/loop/network/index.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/loop/network/index.ts) | Network loop: multi-agent collaboration, routing, delegation hooks |
| [`packages/core/src/workflows/workflow.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/workflows/workflow.ts) | Workflow: step-based execution engine с chaining API (.then/.branch/.parallel) |
| [`packages/core/src/workflows/step.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/workflows/step.ts) | Step: единица выполнения в workflow, Zod-схемы для I/O |
| [`packages/core/src/memory/memory.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/memory/memory.ts) | MastraMemory: 4 типа памяти (conversation, semantic, working, observational) |
| [`packages/core/src/evals/base.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/evals/base.ts) | Evaluation: judge-based scoring, trajectory analysis |
| [`packages/core/src/processors/index.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/processors/index.ts) | Processors: 6-фазная обработка input/output (processInput, processInputStep, processOutputStream, processOutputResult, processOutputStep, processAPIError) |
| [`packages/core/src/tools/tool.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/tools/tool.ts) | Tool: типизированные инструменты с Zod-схемами, suspend/resume |
| [`packages/core/src/storage/base.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/storage/base.ts) | MastraCompositeStore: 15+ доменов хранения (workflows, memory, agents, etc.) |
| [`packages/core/src/workflows/execution-engine.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/workflows/execution-engine.ts) | ExecutionEngine: абстракция для pluggable execution backends |

---

## 2. Возможности оркестрации — обзор

| Функция | Mastra AI |
| --- | --- |
| **Цепочки шагов (chains)** | ✅ Workflow: `.then()` chaining, typed I/O per step |
| **Conditional branching** | ✅ `.branch()` с condition functions |
| **Parallel execution** | ✅ `.parallel()` — все шаги параллельно |
| **Циклы (loops)** | ✅ `.dowhile()`, `.dountil()` с condition function |
| **Map/reduce (foreach)** | ✅ `.foreach()` с concurrency control |
| **Retry с backoff** | ⚠️ `retryConfig: { attempts, delay }` (простой retry, без exponential backoff) |
| **Quality Gates** | ⚠️ Scorers (eval-based), но не gate-проверки |
| **Fallback routing** | ⚠️ Model fallback через массив моделей |
| **Audit Trail (JSONL)** | ⚠️ OpenTelemetry tracing + storage persistence |
| **Ролевые промпты** | ⚠️ `instructions` в Agent constructor (строка) |
| **Multiple runners** | ✅ 40+ провайдеров через model router |
| **DDD-архитектура** | ❌ Монорепозиторий, пакетная структура |
| **Decorator pattern** | ❌ Прямой вызов + processors pipeline |
| **YAML-конфигурация** | ❌ Программная конфигурация (TypeScript) |
| **Human-in-the-loop** | ✅ Suspend/resume с persistence, resume из другого процесса |
| **Session persistence** | ✅ Pluggable storage (LibSQL, PostgreSQL, D1, Upstash) |
| **Memory system** | ✅ 4 типа: conversation history, semantic recall, working memory, observational memory |
| **RAG** | ✅ Встроенный RAG pipeline (document processing, vector store) |
| **Evaluation framework** | ✅ Judge-based + trajectory scorers с LLM-as-judge |
| **MCP-протокол** | ✅ MCP servers (author + consume) |
| **Processor pipeline** | ✅ Input/Output processors с 6 фазами (input, inputStep, outputStream, outputResult, outputStep, apiError) |
| **Agent delegation (network)** | ✅ Agent network loop: routing agent + sub-agents, delegation hooks (onDelegationStart/Complete), messageFilter |
| **Observability** | ✅ OpenTelemetry tracing, spans, custom exporters |
| **TripWire** | ✅ Досрочная остановка workflow/agent с retry hint |
| **Sleep/SleepUntil** | ✅ `.sleep(duration)`, `.sleepUntil(date)` — durable timers |
| **Typed I/O per step** | ✅ Zod-схемы для inputSchema/outputSchema каждого шага |
| **Step retries per-step** | ✅ `retries` на уровне Step (configurable per step) |
| **Workflow nesting** | ✅ Workflow как Step (nesting через `createStep(workflow)`) |
| **Model routing** | ✅ Model router: 40+ провайдеров, string-based model IDs |
| **Server/API** | ✅ HTTP server, REST API, streaming (SSE) |

---

## 3. Оркестрационные возможности

### 3.1 🟡 Step-based Workflow Engine с chaining API (`packages/core/src/workflows/`)

**Что у них:** Mastra предоставляет fluent API для описания workflow:

```typescript
const workflow = new Workflow({
 name: 'code-review',
 inputSchema: z.object({ prompt: z.string() }),
 outputSchema: z.object({ result: z.string() }),
})
 .then(implementStep)
 .then(reviewStep)
 .branch([
 [({ getStepResult }) => getStepResult(reviewStep).approved, deployStep],
 [() => true, fixStep],
 ])
 .then(finalStep);

workflow.run({ prompt: "Implement feature X" });
```

**Ключевые конструкции:**
- `.then(step)` — последовательное выполнение
- `.parallel([step1, step2])` — параллельное выполнение
- `.branch([[cond, step], ...])` — условное ветвление
- `.dowhile(step, condition)` / `.dountil(step, condition)` — циклы
- `.foreach(step, { concurrency })` — map/reduce
- `.sleep(duration)` / `.sleepUntil(date)` — durable timers

**Особенности реализации:** Chaining API — immutable: каждый вызов `.then()`, `.branch()`, `.parallel()` возвращает новый объект Workflow, оригинал не мутируется. Workflow компилируется в execution plan при вызове `.run()` — это позволяет валидировать граф (обнаруживать unreachable-шаги, циклы без условия выхода) до старта выполнения. Parallel-шаги выполняются через `Promise.allSettled` — результат содержит как успешные, так и failed-ответы, что позволяет обрабатывать частичные сбои без прерывания всего workflow.

---

### 3.2 🟡 Observational Memory — четырёхуровневая система памяти (`packages/core/src/memory/`)

**Что у них:** Четыре типа памяти для агентов:

1. **Conversation history** (`lastMessages: N`) — последние N сообщений из текущего треда
2. **Semantic recall** (RAG) — векторный поиск релевантных сообщений из прошлых диалогов через embeddings
3. **Working memory** — структурированный persistent-контекст (markdown template или Zod-схема), который агент обновляет в процессе работы
4. **Observational memory** — Observer agent извлекает observations из диалогов, Reflector agent сжимает их при росте

```typescript
const memory = new Memory({
 options: {
 lastMessages: 10,
 semanticRecall: { topK: 3, messageRange: 2 },
 workingMemory: {
 enabled: true,
 scope: 'resource',
 template: '# User Profile\n- **Name**:\n- **Preferences**:',
 },
 observationalMemory: true,
 },
 vector: new PgVector({ connectionString: DB_URL }),
 embedder: 'openai/text-embedding-3-small',
});
```

**Оркестрационная значимость:** Для длинных цепочек (implement → review → fix → review → ...) контекст может расти. Observational memory автоматически сжимает историю, сохраняя ключевые observations. Это альтернатива auto-summarization из Crush, но более продвинутая (Observer + Reflector агенты).

---

### 3.3 🟡 TripWire — досрочная остановка с retry hint (`packages/core/src/agent/trip-wire.ts`)

**Что у них:** Processor может прервать выполнение workflow/agent, выбросив TripWire:

```typescript
// Внутри processor:
abort("Quality check failed", { retry: true, metadata: { reason: "code style violation" } });

// Workflow status = 'tripwire' (не 'failed')
// Step result содержит tripwire-информацию
```

**Особенности:**
- TripWire — это **отдельный статус** (`tripwire`), не `failed`
- Может содержать hint `retry: true` для автоматического повторного выполнения
- Metadata позволяет передать причину в retry-цикл
- Процессор может принять решение на основе выходных данных агента

**Оркестрационная значимость:** Это паттерн промежуточный между обычным retry и quality gate. Processor может прервать выполнение на основе анализа результата LLM (не только shell-команды). ---

### 3.4 🟡 Processor Pipeline — 6-фазная обработка input/output (`packages/core/src/processors/`)

**Что у них:** Процессоры — это middleware-паттерн для перехвата и модификации данных на разных фазах выполнения:

```typescript
interface Processor {
 processInput(ctx): Promise<...>; // До LLM: модификация входных сообщений
 processInputStep(ctx): Promise<...>; // До каждого LLM-вызова: model, tools, toolChoice
 processOutputStream(ctx): Promise<...>; // Потоковая обработка output chunks
 processOutputResult(ctx): Promise<...>; // После LLM: модификация результата
 processOutputStep(ctx): Promise<...>; // После каждого LLM-вызова
 processAPIError(ctx): Promise<...>; // При ошибке LLM API: retry/reject
}
```

**Встроенные процессоры:**
- `SkillsProcessor` — инжекция навыков из workspace
- `WorkspaceInstructionsProcessor` — инструкции из workspace
- `TokenLimiterProcessor` — лимит токенов
- `ObservationalMemoryProcessor` — observational memory (Observer + Reflector)
- Memory-процессоры (conversation history, semantic recall, working memory)

**Особенности реализации:** Processor pipeline построен по принципу middleware-цепочки: каждый processor может модифицировать контекст и передать управление следующему. Порядок регистрации процессоров определяет порядок выполнения. Процессоры привязываются к конкретному Agent, а не к глобальному реестру — разные агенты могут иметь разные pipelines. Фаза `processAPIError` позволяет процессору решать, повторять ли LLM-вызов или прервать — это встроенная альтернатива external retry logic.

---

### 3.5 🟡 Suspend/Resume — Human-in-the-Loop с persistence (`packages/core/src/workflows/`)

**Что у них:** Workflow может быть приостановлен (suspend) и возобновлён (resume) из другого процесса:

```typescript
// Внутри step:
const input = await suspend({ question: "Approve this change?" }, { resumeLabel: "approval" });
// input = данные от человека (resume payload)

// Возобновление из другого процесса:
workflow.run({ resumePayload: { approved: true }, steps: ["stepId"] });
```

**Механика:**
- Workflow state сохраняется в storage при suspend
- Resume может произойти через часы/дни/недели
- Поддерживает `resumeLabel` для мульти-точек resume
- Работает с любым storage backend (LibSQL, PostgreSQL)

**Оркестрационная значимость:** Для автономных цепочек с контрольными точками (например: реализовать → дождаться ревью человека → исправить). Это функционал, которого у нас нет, но он востребован для production-сценариев.

---

### 3.6 🟡 Typed I/O per Step с Zod-схемами (`packages/core/src/workflows/step.ts`)

**Что у них:** Каждый шаг (Step) в workflow имеет типизированные input/output через Zod-схемы:

```typescript
const reviewStep = createStep({
 id: 'review',
 inputSchema: z.object({ code: z.string() }),
 outputSchema: z.object({ approved: z.boolean(), comments: z.array(z.string()) }),
 execute: async ({ inputData }) => {
 // inputData: { code: string } — типизация через Zod
 return { approved: true, comments: [] };
 },
});
```

**Оркестрационная значимость:** Наш YAML chain передаёт контекст через общий JSON-объект. Типизация каждого шага через Zod-схемы даёт: валидацию I/O, автогенерацию документации, type safety при chaining. Аналог в PHP — Symfony Form types или JSON Schema.

---

### 3.7 🟡 Evaluation Framework (`packages/core/src/evals/`)

**Что у них:** Встроенный evaluation framework с тремя подходами:

1. **Judge-based scorers** — LLM оценивает качество результата по критериям
2. **Trajectory scorers** — анализ последовательности действий агента
3. **Custom scorers** — произвольные метрики

```typescript
const scorer = new Scorer({
 id: 'code-quality',
 description: 'Evaluates code quality',
 judge: { model: 'openai/gpt-4o', instructions: 'Rate code quality 1-10' },
 type: 'agent',
});
```

**Интеграция с workflows:** Scorers можно привязать к отдельным шагам:

```typescript
createStep({
 id: 'implement',
 scorers: [codeQualityScorer],
 // ...
});
```

**Оркестрационная значимость:** Это «LLM-based quality gate» — оценка качества не через shell-команды, а через LLM-as-judge. Дополняет наши shell-based quality gates для случаев, когда формальная проверка невозможна.

---

### 3.8 🟡 Agent Network — Multi-agent collaboration (`packages/core/src/agent/`, `packages/core/src/loop/network/`)

**Что у них:** Агент может выполнять сетевой цикл (network loop), в котором routing-агент делегирует задачи sub-агентам:

```typescript
// Определение координатора с sub-агентами
const coordinator = new Agent({
 name: 'coordinator',
 model: 'openai/gpt-4o',
 instructions: 'Coordinate between specialist agents...',
});

// Запуск network loop — routing-агент автоматически делегирует sub-агентам
const result = await coordinator.network('Analyze and fix this code', {
 maxSteps: 10,
 teams: { coder: coderAgent, reviewer: reviewerAgent },
 delegation: {
 onDelegationStart: async (ctx) => {
 // Фильтрация, модификация промпта, rejection
 return { proceed: true };
 },
 onDelegationComplete: async (ctx) => {
 // Feedback, остановка обработки
 return { stop: false };
 },
 messageFilter: ({ messages }) => {
 // Какие сообщения parent передаёт sub-agent
 return messages.slice(-5);
 },
 },
});
```

**Механика:**
- `agent.network(messages, options)` — запуск multi-agent collaboration loop
- Routing-агент автоматически выбирает sub-агентов из `teams`
- Delegation hooks: `onDelegationStart` (reject/modify), `onDelegationComplete` (feedback/stop)
- `messageFilter`: контроль какие сообщения parent agent передаёт sub-agent
- Max steps для предотвращения бесконечной делегации
- Suspend/resume поддерживается и в network loop

**Особенности реализации:** Network loop управляет жизненным циклом делегирования: routing-агент получает задачу, выбирает подходящего sub-агента, делегирует через hooks и собирает результат. `messageFilter` позволяет ограничить контекст, передаваемый sub-агенту, — это критично для управления токенами и предотвращения leakage данных между агентами. `maxSteps` ограничивает количество циклов делегирования и предотвращает бесконечные петли. Делегирование поддерживает suspend/resume — human-in-the-loop может быть встроен в любой точке multi-agent цикла.

---

## 4. Прочие возможности (вне оркестрации)

### 4.1 🟢 Model Router (40+ провайдеров)

Mastra абстрагирует 40+ LLM-провайдеров через string-based model IDs (`'openai/gpt-4o'`, `'anthropic/claude-3.5'`). Нам не нужна собственная абстракция над провайдерами — это задача runner'ов.

### 4.2 🟢 RAG Pipeline

Mastra имеет встроенный RAG pipeline (document processing, chunking, vector store, retrieval). Для оркестратора цепочек это out of scope — RAG должен быть на уровне runner'ов или отдельного сервиса.

### 4.3 🟢 Server/API Layer

Mastra включает HTTP server с REST API, streaming (SSE), и deployment adapters (Cloudflare Workers, Vercel). ### 4.4 🟢 Playground UI (Mastra Studio)

Визуальный playground для тестирования агентов и workflows. Не актуально для CLI-утилиты.

### 4.5 🟢 Deployment Adapters

Cloudflare Workers, Vercel, Docker deployment. Не актуально — наш бандл подключается к Symfony-приложению.

### 4.6 🟢 Voice/TTS

Mastra имеет встроенную поддержку voice (text-to-speech, speech-to-text). Не актуально для оркестратора цепочек разработки.

### 4.7 🟢 Browser Integration

Mastra имеет browser automation для агентов (web browsing, scraping). Не актуально для code orchestration.

---

## 5. Сводка по оркестрации

| Возможность | Статус в продукте | Описание |
| --- | --- | --- |
| Typed I/O per step (schema validation) | 🟡 P2 | Валидация входных/выходных данных каждого шага через JSON Schema |
| Processor pipeline (input/output middleware) | 🟡 P2 | Альтернатива decorator pattern, более granular контроль |
| TripWire (LLM-based quality abort) | 🟡 P2 | Прерывание выполнения на основе LLM-оценки, дополнение к shell-based quality gates |
| Conditional branching в chains | 🟡 P2 | Условное ветвление в YAML chains (концепция из `.branch()`) |
| LLM-based evaluation (scorers) | 🟡 P2 | LLM-as-judge для оценки качества, дополнение к shell-based quality gates |
| Observational memory (auto-compression) | 🟡 P3 | Для длинных dynamic loops с memory |
| Suspend/resume (human-in-the-loop) | 🟡 P3 | Для автономных цепочек с контрольными точками |
| Parallel execution в chains | 🟡 P3 | Параллельное выполнение независимых шагов |
| Agent delegation (multi-agent) | 🟡 P3 | Для будущих dynamic chains с sub-agent'ами |
| Foreach (map/reduce) в chains | 🟡 P3 | Обработка массивов данных в цепочках |
| Workflow nesting | 🟡 P3 | Вложенные цепочки (chain как шаг другой chain) |
| Model Router | 🟢 — | Задача runner'ов |
| RAG pipeline | 🟢 — | Out of scope |
| Server/API | 🟢 — | CLI-утилита |
| Playground UI | 🟢 — | Не актуально |
| Deployment adapters | 🟢 — | Symfony bundle |
| Voice/TTS | 🟢 — | Не актуально |
| Browser integration | 🟢 — | Не актуально |

---

## 6. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории Mastra AI:

- [`packages/core/src/workflows/workflow.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/workflows/workflow.ts) — Workflow class: chaining API (.then/.branch/.parallel/.dowhile/.dountil/.foreach), run, suspend/resume
- [`packages/core/src/workflows/step.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/workflows/step.ts) — Step interface, createStep() factory, ExecuteFunction
- [`packages/core/src/workflows/execution-engine.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/workflows/execution-engine.ts) — ExecutionEngine: абстрактный класс для pluggable backends
- [`packages/core/src/workflows/default.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/workflows/default.ts) — DefaultExecutionEngine: in-process выполнение, retry logic
- [`packages/core/src/agent/agent.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/agent/agent.ts) — Agent: generate/stream, tools, memory, processors, network() для multi-agent delegation
- [`packages/core/src/loop/network/index.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/loop/network/index.ts) — Network loop: routing, delegation, suspend/resume
- [`packages/core/src/memory/memory.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/memory/memory.ts) — MastraMemory: 4 типа памяти, conversation history, semantic recall, working memory
- [`packages/core/src/memory/types.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/memory/types.ts) — MemoryConfig, SemanticRecall, WorkingMemory, ObservationalMemory, VectorIndexConfig
- [`packages/core/src/evals/base.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/evals/base.ts) — Scorer: judge-based, trajectory-based evaluation
- [`packages/core/src/processors/index.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/processors/index.ts) — Processor interface: 6-фазная обработка input/output
- [`packages/core/src/tools/tool.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/tools/tool.ts) — Tool: типизированные инструменты с Zod-схемами
- [`packages/core/src/storage/base.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/storage/base.ts) — MastraCompositeStore: 15+ storage domains
- [`packages/core/src/agent/trip-wire.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/agent/trip-wire.ts) — TripWire: досрочная остановка с retry hint
- [`AGENTS.md`](https://github.com/mastra-ai/mastra/blob/main/AGENTS.md) — Документация для AI-агентов (архитектура, структура)
- [`README.md`](https://github.com/mastra-ai/mastra/blob/main/README.md) — Обзор: features, установка, документация

---

📚 **Источники:**
1. [github.com/mastra-ai/mastra](https://github.com/mastra-ai/mastra) — репозиторий проекта
2. [mastra.ai/docs](https://mastra.ai/docs) — официальная документация
3. [mastra.ai/docs/workflows/overview](https://mastra.ai/docs/workflows/overview) — документация workflows
4. [mastra.ai/docs/agents/overview](https://mastra.ai/docs/agents/overview) — документация agents
5. [mastra.ai/docs/memory/overview](https://mastra.ai/docs/memory/overview) — документация memory system
