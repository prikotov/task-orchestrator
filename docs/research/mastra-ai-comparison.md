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
  core/                              Основной пакет (@mastra/core)
    src/
      agent/                         Агенты: LLM-сессии, инструменты, memory, voice
        agent.ts                     Agent class: generate/stream, tools, memory, processors
        agent.types.ts               Типы: AgentConfig, AgentExecutionOptions
        message-list/                MessageList: управление сообщениями, дедупликация
        trip-wire.ts                 TripWire: досрочная остановка с retry
      workflows/                     Workflows: step-based execution engine
        workflow.ts                  Workflow class: .then(), .branch(), .parallel(), .dowhile(), .dountil(), .foreach()
        step.ts                      Step interface + createStep() factory
        execution-engine.ts          ExecutionEngine: абстрактный класс для execution backends
        default.ts                   DefaultExecutionEngine: in-process выполнение
        handlers/                    control-flow, entry, sleep, step
        types.ts                     Типы: StepResult, WorkflowState, StepFlowEntry
      memory/                        Система памяти
        memory.ts                    MastraMemory: conversation history, semantic recall, working memory
        types.ts                     MemoryConfig, SemanticRecall, WorkingMemory, ObservationalMemory
      evals/                         Evaluation framework
        base.ts                      Scorer: judge-based, trajectory-based, custom
        types.ts                     ScorerConfig, ScorerRun, Trajectory
      tools/                         Инструменты агентов
        tool.ts                      Tool class + createTool() factory
        types.ts                     CoreTool, ToolExecutionContext
      processors/                    Processor pipeline (input/output processors)
        index.ts                     Processor interface: processInput, processOutputStream, processOutputResult
        runner.ts                    ProcessorRunner: orchestration of processor chains
      storage/                       Storage backends
        base.ts                      MastraCompositeStore: 15+ storage domains
        filesystem.ts                Filesystem-backed store
      mcp/                           Model Context Protocol
      vector/                        Vector store abstraction
      observability/                 OpenTelemetry tracing + spans
      rag/                           RAG pipeline (в packages/rag)
      llm/                           Model routing: 40+ провайдеров
  memory/                            Пакет @mastra/memory (deprecated, merged into core)
  rag/                               Пакет @mastra/rag
  evals/                             Пакет @mastra/evals
  server/                            HTTP server + API endpoints
  playground/                        Playground UI (Studio)
  deployer/                          Deployment adapters (Cloudflare, Vercel, etc.)
  cli/                               Mastra CLI
```

### Ключевые характеристики

| Характеристика | Значение |
|---|---|
| **Тип** | SDK-фреймворк (TypeScript), работает на уровне LLM API |
| **Модель выполнения (Agents)** | Agent loop (LLM → tool call → observation → LLM → ...) |
| **Модель выполнения (Workflows)** | Step-based: `.then()`, `.branch()`, `.parallel()`, `.dowhile()`, `.dountil()`, `.foreach()` |
| **State management** | Pluggable storage (LibSQL/SQLite, PostgreSQL, Cloudflare D1, Upstash) |
| **Провайдеры** | 40+ LLM-провайдеров через model router |
| **Расширяемость** | Processors (input/output), MCP-серверы, Tools, custom storage |
| **Memory** | Conversation history + Semantic recall (RAG) + Working memory + Observational memory |
| **Evals** | Judge-based, trajectory-based, custom scorers |
| **Human-in-the-loop** | Suspend/resume с persistence (бесконечная пауза) |
| **Лицензия** | Apache 2.0 (core) + Enterprise (ee/) |

### Основные компоненты

| Компонент | Назначение |
|---|---|
| [`packages/core/src/agent/agent.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/agent/agent.ts) | Agent: generate/stream, tools, memory, processors, network (multi-agent delegation) |
| [`packages/core/src/workflows/workflow.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/workflows/workflow.ts) | Workflow: step-based execution engine с chaining API (.then/.branch/.parallel) |
| [`packages/core/src/workflows/step.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/workflows/step.ts) | Step: единица выполнения в workflow, Zod-схемы для I/O |
| [`packages/core/src/memory/memory.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/memory/memory.ts) | MastraMemory: 4 типа памяти (conversation, semantic, working, observational) |
| [`packages/core/src/evals/base.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/evals/base.ts) | Evaluation: judge-based scoring, trajectory analysis |
| [`packages/core/src/processors/index.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/processors/index.ts) | Processors: pipeline для обработки input/output на уровне agent и workflow |
| [`packages/core/src/tools/tool.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/tools/tool.ts) | Tool: типизированные инструменты с Zod-схемами, suspend/resume |
| [`packages/core/src/storage/base.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/storage/base.ts) | MastraCompositeStore: 15+ доменов хранения (workflows, memory, agents, etc.) |
| [`packages/core/src/workflows/execution-engine.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/workflows/execution-engine.ts) | ExecutionEngine: абстракция для pluggable execution backends |

---

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | TasK Orchestrator | Mastra AI | Статус |
|---|---|---|---|
| **Цепочки шагов (chains)** | ✅ YAML chains, статические и динамические | ✅ Workflow: `.then()` chaining, typed I/O per step | ✅ Паритет (разный подход) |
| **Conditional branching** | ❌ Нет (только линейные цепочки) | ✅ `.branch()` с condition functions | 🟡 Позже |
| **Parallel execution** | ❌ Нет (последовательное выполнение) | ✅ `.parallel()` — все шаги параллельно | 🟡 Позже |
| **Циклы (loops)** | ✅ fix_iterations с max_iterations | ✅ `.dowhile()`, `.dountil()` с condition function | ✅ Паритет |
| **Map/reduce (foreach)** | ❌ Нет | ✅ `.foreach()` с concurrency control | 🟡 Позже |
| **Retry с backoff** | ✅ RetryingAgentRunner (exponential backoff) | ⚠️ `retryConfig: { attempts, delay }` (простой retry, без exponential backoff) | ✅ У нас лучше |
| **Circuit Breaker** | ✅ CircuitBreakerAgentRunner | ❌ Нет | ✅ У нас есть |
| **Quality Gates** | ✅ Shell-команды как проверки | ⚠️ Scorers (eval-based), но не gate-проверки | ✅ У нас лучше |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based) | ❌ Нет встроенного budget control | ✅ У нас есть |
| **Fallback routing** | ✅ Per-step fallback runner | ⚠️ Model fallback через массив моделей | ✅ У нас лучше |
| **Audit Trail (JSONL)** | ✅ JsonlAuditLogger | ⚠️ OpenTelemetry tracing + storage persistence | ✅ У нас лучше |
| **Ролевые промпты** | ✅ .md файлы (18+ ролей) | ⚠️ `instructions` в Agent constructor (строка) | ✅ У нас лучше |
| **Multiple runners** | ✅ Pi + Codex (через interface) | ✅ 40+ провайдеров через model router | ✅ Паритет (разный уровень) |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❌ Монорепозиторий, пакетная структура | ✅ У нас лучше |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Прямой вызов + processors pipeline | ✅ У нас лучше |
| **YAML-конфигурация** | ✅ Chains + roles в YAML | ❌ Программная конфигурация (TypeScript) | ✅ Разный подход |
| **Human-in-the-loop** | ❌ Нет | ✅ Suspend/resume с persistence, resume из другого процесса | 🟡 Позже |
| **Session persistence** | ❌ Нет (in-memory) | ✅ Pluggable storage (LibSQL, PostgreSQL, D1, Upstash) | 🟡 Позже |
| **Memory system** | ❌ Нет | ✅ 4 типа: conversation history, semantic recall, working memory, observational memory | 🟡 Интересно |
| **RAG** | ❌ Нет | ✅ Встроенный RAG pipeline (document processing, vector store) | 🟢 Не берём |
| **Evaluation framework** | ❌ Нет | ✅ Judge-based + trajectory scorers с LLM-as-judge | 🟡 Позже |
| **MCP-протокол** | ❌ Нет | ✅ MCP servers (author + consume) | 🟡 Позже |
| **Processor pipeline** | ❌ Нет | ✅ Input/Output processors с 6 фазами (input, inputStep, outputStream, outputResult, outputStep) | 🟡 Интересно |
| **Agent delegation (network)** | ❌ Нет | ✅ Agent network: delegation с message filtering, hooks | 🟡 Позже |
| **Observability** | ❌ Нет (только JSONL audit) | ✅ OpenTelemetry tracing, spans, custom exporters | 🟡 Позже |
| **TripWire** | ❌ Нет | ✅ Досрочная остановка workflow/agent с retry hint | 🟡 Интересно |
| **Sleep/SleepUntil** | ❌ Нет | ✅ `.sleep(duration)`, `.sleepUntil(date)` — durable timers | 🟢 Не берём |
| **Typed I/O per step** | ⚠️ JSON-контекст | ✅ Zod-схемы для inputSchema/outputSchema каждого шага | 🟡 Интересно |
| **Step retries per-step** | ⚠️ На уровне runner | ✅ `retries` на уровне Step (configurable per step) | ✅ Паритет |
| **Workflow nesting** | ❌ Нет | ✅ Workflow как Step (nesting через `createStep(workflow)`) | 🟡 Позже |
| **Model routing** | ❌ Нет (через runners) | ✅ Model router: 40+ провайдеров, string-based model IDs | 🟢 Не берём |
| **Server/API** | ❌ CLI-only | ✅ HTTP server, REST API, streaming (SSE) | 🟢 Не берём |

---

## 3. Что полезно взять и почему

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
  .then(finalStep;

workflow.run({ prompt: "Implement feature X" });
```

**Ключевые конструкции:**
- `.then(step)` — последовательное выполнение
- `.parallel([step1, step2])` — параллельное выполнение
- `.branch([[cond, step], ...])` — условное ветвление
- `.dowhile(step, condition)` / `.dountil(step, condition)` — циклы
- `.foreach(step, { concurrency })` — map/reduce

**Почему нам интересно:** Наша YAML-based chain модель ограничена линейными цепочками + fix_iterations. Mastra показывает, как можно добавить conditional branching и parallel execution без полной миграции на DAG. Однако перенос chaining API в PHP требует значительных усилий — TypeScript fluent API активно использует generics и type inference.

**Отличие от нашей реализации:**
- У нас: YAML chain = декларативное описание, выполняется последовательно
- У них: programmatic chaining = императивное описание с типизацией, поддерживает branching/parallel

---

### 3.2 🟡 Observational Memory — трёхуровневая система памяти (`packages/core/src/memory/`)

**Что у них:** Три уровня памяти для агентов:

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

**Почему нам интересно:** Для длинных цепочек (implement → review → fix → review → ...) контекст может расти. Observational memory автоматически сжимает историю, сохраняя ключевые observation. Это альтернатива auto-summarization из Crush, но более продвинутая (Observer + Reflector агенты).

**Отличие:** У нас цепочки конечные (max_iterations), а Mastra ориентирована на бесконечные диалоги. Но для будущих dynamic loops с memory концепция актуальна.

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

**Почему нам интересно:** Это паттерн промежуточный между обычным retry и quality gate. Processor может прервать выполнение на основе анализа результата LLM (не только shell-команды). У нас quality gates работают через shell-выходные коды, а TripWire позволяет LLM-based оценку качества.

---

### 3.4 🟡 Processor Pipeline — 6-фазная обработка input/output (`packages/core/src/processors/`)

**Что у них:** Процессоры — это middleware-паттерн для перехвата и модификации данных на разных фазах выполнения:

```typescript
interface Processor {
  processInput(ctx): Promise<...>;        // До LLM: модификация входных сообщений
  processInputStep(ctx): Promise<...>;    // До каждого LLM-вызова: model, tools, toolChoice
  processOutputStream(ctx): Promise<...>; // Потоковая обработка output chunks
  processOutputResult(ctx): Promise<...>; // После LLM: модификация результата
  processOutputStep(ctx): Promise<...>;   // После каждого LLM-вызова
}
```

**Встроенные процессоры:**
- `SkillsProcessor` — инжекция навыков из workspace
- `WorkspaceInstructionsProcessor` — инструкции из workspace
- `TokenLimiterProcessor` — лимит токенов
- Memory-процессоры (conversation history, semantic recall, working memory)

**Почему нам интересно:** Мы используем decorator pattern для runner'ов (retry, circuit breaker, budget). Processor pipeline — это альтернативный паттерн с более granular контролем (6 фаз вместо одного intercept). Для будущих dynamic chains processor pipeline может заменить декораторы.

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

**Почему нам интересно:** Для автономных цепочек с контрольными точками (например: реализовать → дождаться ревью человека → исправить). Это функционал, которого у нас нет, но он востребован для production-сценариев.

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

**Почему нам интересно:** Наш YAML chain передаёт контекст через общий JSON-объект. Типизация каждого шага через Zod-схемы даёт: валидацию I/O, автогенерацию документации, type safety при chaining. Аналог в PHP — Symfony Form types или JSON Schema.

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

**Почему нам интересно:** Это «LLM-based quality gate» — оценка качества не через shell-команды, а через LLM-as-judge. Дополняет наши shell-based quality gates для случаев, когда формальная проверка невозможна.

---

### 3.8 🟡 Agent Network — Delegation с hooks (`packages/core/src/agent/`)

**Что у них:** Агент может делегировать задачи другим агентам через tools:

```typescript
const coordinator = new Agent({
  name: 'coordinator',
  tools: { specialist: specialistAgent.asTool() },
  // или через network:
  model: 'openai/gpt-4o',
});

// Delegation hooks:
coordinator.onDelegationStart((ctx) => {
  // Фильтрация сообщений, модификация промпта
  return { proceed: true, modifiedPrompt: "..." };
});
```

**Механика:**
- Агент может быть обёрнут как tool (`agent.asTool()`)
- Delegation hooks: `onDelegationStart`, `onDelegationComplete`
- Message filter: какие сообщения parent agent передаёт sub-agent
- Max iterations для предотвращения бесконечной делегации

**Почему нам интересно:** Это паттерн multi-agent orchestration, которого у нас нет. Для будущих dynamic chains концепция delegation + hooks может стать основой.

---

## 4. Что НЕ берём и почему

### 4.1 🟢 Model Router (40+ провайдеров)

Mastra абстрагирует 40+ LLM-провайдеров через string-based model IDs (`'openai/gpt-4o'`, `'anthropic/claude-3.5'`). Наш оркестратор работает через runner'ы (pi, codex), каждый из которых сам общается с конкретным API. Нам не нужна собственная абстракция над провайдерами — это задача runner'ов.

### 4.2 🟢 RAG Pipeline

Mastra имеет встроенный RAG pipeline (document processing, chunking, vector store, retrieval). Для оркестратора цепочек это out of scope — RAG должен быть на уровне runner'ов или отдельного сервиса.

### 4.3 🟢 Server/API Layer

Mastra включает HTTP server с REST API, streaming (SSE), и deployment adapters (Cloudflare Workers, Vercel). Наш оркестратор — CLI-утилита, HTTP-сервер не нужен.

### 4.4 🟢 Playground UI (Mastra Studio)

Визуальный playground для тестирования агентов и workflows. Не актуально для CLI-утилиты.

### 4.5 🟢 Deployment Adapters

Cloudflare Workers, Vercel, Docker deployment. Не актуально — наш бандл подключается к Symfony-приложению.

### 4.6 🟢 Voice/TTS

Mastra имеет встроенную поддержку voice (text-to-speech, speech-to-text). Не актуально для оркестратора цепочек разработки.

### 4.7 🟢 Browser Integration

Mastra имеет browser automation для агентов (web browsing, scraping). Не актуально для code orchestration.

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
- [`packages/core/src/agent/agent.ts`](https://github.com/mastra-ai/mastra/blob/main/packages/core/src/agent/agent.ts) — Agent: generate/stream, tools, memory, processors, network delegation
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
