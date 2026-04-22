# Исследование: OpenHands Software Agent SDK (Python)

> **Проект:** [github.com/OpenHands/software-agent-sdk](https://github.com/OpenHands/software-agent-sdk)
> **Дата анализа:** 2026-04-21
> **Язык:** Python 3.13
> **Лицензия:** MIT
> **Аналитик:** Технический писатель (Гермиона)

---

## 1. Обзор проекта

OpenHands SDK (ранее OpenDevin) — composable Python-библиотека для создания программных AI-агентов, работающих с кодом. SDK является «движком», на котором построены CLI, Local GUI и Cloud-версия OpenHands. Ключевая идея: **программное создание агентов** через composition (LLM + tools + context), запуск локально или в sandboxed-окружении (Docker/Kubernetes).

OpenHands SDK **не является** фреймворком chain-оркестрации. Это **SDK для построения и запуска отдельных AI-агентов** (code-reading, terminal, file-editing). В отличие от task-orchestrator, SDK не поддерживает YAML-цепочки, retry-механизмы на уровне шагов, circuit breaker, budget control или quality gates. Зато SDK предоставляет зрелую Action/Observation-модель, параллельное выполнение tools, context condensation, security-анализ и subagent-делегирование.

### Архитектура

```
openhands-sdk/openhands/sdk/
  agent/
    agent.py                 Agent: LLM + tools + context → agent loop
    acp_agent.py             ACPAgent: Agent Communication Protocol агент
    base.py                  AgentBase: абстрактный базовый класс
    critic_mixin.py          CriticMixin: iterative refinement через Critic
    parallel_executor.py     ParallelToolExecutor: concurrent tool calls
    response_dispatch.py     Классификация LLM-ответов (tool call / message / error)
    utils.py                 Утилиты: prepare_llm_messages, make_llm_completion
  conversation/
    conversation.py          Conversation: factory (Local / Remote)
    impl/
      local_conversation.py  LocalConversation: in-process выполнение
      remote_conversation.py RemoteConversation: WebSocket к Agent Server
    state.py                 ConversationState: events, iteration counter, execution status
    stuck_detector.py        StuckDetector: 4 активных + 1 TODO сценарий обнаружения зацикливания
    event_store.py           EventLog: file-backed persistence с file locking
    conversation_stats.py    ConversationStats: метрики диалога
    resource_lock_manager.py ResourceLockManager: блокировки ресурсов для parallel execution
    secret_registry.py       SecretRegistry: именованные секреты для агентов
    fifo_lock.py             FIFOLock: fair locking для concurrent access
    visualizer/              Conversation visualization (Rich-based)
  context/
    agent_context.py         AgentContext: system prompt, skills, condenser
    prompts/prompt.py        Jinja2-рендеринг шаблонов
    condenser/
      llm_summarizing_condenser.py   LLM-based summarization при overflow
      pipeline_condenser.py          Конвейер конденсеров
      no_op_condenser.py             NoOp-конденсер (passthrough)
    skills/                  Agent Skills: загрузка, валидация, injection
    view/
      view.py                View: snapshot событийного потока для LLM context
      manipulation_indices.py Границы атомарных unit'ов для condensation
      properties/            Свойства view: batch_atomicity, tool_call_matching
  event/
    base.py                  Event: базовый класс (id, timestamp, source)
    llm_convertible/
      action.py              ActionEvent: LLM → tool call
      observation.py         ObservationEvent: tool → результат
      message.py             MessageEvent: user / agent сообщения
      system.py              SystemPromptEvent
    conversation_state.py    ConversationErrorEvent, CondensationSummaryEvent
    condenser.py             Condensation, CondensationRequest
    streaming_delta.py       StreamingDelta: real-time LLM output
    token.py                 TokenEvent: vLLM token IDs
    user_action.py           UserRejectObservation
  llm/
    llm.py                   LLM: обёртка над LiteLLM (100+ провайдеров)
    llm_registry.py          LLMRegistry: именованные LLM-профили
    fallback_strategy.py     FallbackStrategy: fallback при transient errors
    llm_profile_store.py     LLMProfileStore: файловые LLM-профили (JSON)
    llm_response.py          LLMResponse: ответ LLM с метриками
    message.py               Message, MessageToolCall, TextContent, ThinkingBlock
    streaming.py             Streaming-поддержка
    auth/                    Аутентификация: credentials, OpenAI-specific
    exceptions/              Классификация ошибок LLM (transient, context window, malformed)
    mixins/                  FnCallConverter, FnCallExamples, NonNativeFC
    options/                 ChatOptions, ResponsesOptions
    router/                  LLM Router: multimodal, random
    utils/
      retry_mixin.py         RetryMixin: exponential backoff через tenacity
      metrics.py             Metrics: cost/token tracking
      model_features.py      Feature detection для LLM моделей
  tool/
    tool.py                  ToolDefinition[Action, Observation]: базовый класс + ToolAnnotations
    registry.py              Инструментный реестр (имена → классы)
    schema.py                Action / Observation / Schema (Pydantic)
    spec.py                  Tool, ExecutableTool Protocol
    builtins/                FinishTool, ThinkTool, InvokeSkillTool
  subagent/
    registry.py              Sub-agent реестр: register_agent, file-based agents
    schema.py                AgentDefinition: имя, tools, system_prompt, model, skills
    load.py                  Загрузка agents из Markdown-файлов (.agents/, .openhands/agents/)
  security/
    analyzer.py              SecurityAnalyzerBase: risk assessment для actions
    confirmation_policy.py   AlwaysConfirm / NeverConfirm / ConfirmRisky
    risk.py                  SecurityRisk enum: UNKNOWN / LOW / MEDIUM / HIGH
    llm_analyzer.py          LLMSecurityAnalyzer: LLM-based risk assessment
    ensemble.py              EnsembleSecurityAnalyzer: max-severity fusion
    grayswan/                GraySwan Cygnal API для AI safety monitoring
    defense_in_depth/
      pattern.py             PatternSecurityAnalyzer: regex-сигнатуры угроз
      policy_rails.py        Политики: fetch-to-exec, raw-disk-op, catastrophic-delete
  hooks/
    manager.py               HookManager: lifecycle hook orchestration
    executor.py              HookExecutor: запуск shell-скриптов как hooks
    config.py                HookConfig: загрузка hooks.json с matcher'ами
    conversation_hooks.py    Интеграция hooks в conversation lifecycle
    types.py                 HookEvent, HookEventType (6 типов событий)
  mcp/
    client.py                MCPClient: Model Context Protocol интеграция
    tool.py                  MCPToolDefinition: MCP-server tools → ToolDefinition
    definition.py            MCP-определения
  skills/
    skill.py                 Skill: SKILL.md парсинг, валидация
    fetch.py                 Загрузка skills из Git-репозиториев
    execute.py               Выполнение skill-команд
    trigger.py               Trigger matching для skill activation
  plugin/
    plugin.py                Plugin: комплексный пакет (skills + tools + hooks + agents + MCP)
    loader.py                Загрузка плагинов из Git-репозиториев
    source.py                PluginSource: описание источника плагина
  workspace/
    local.py                 LocalWorkspace: локальная FS
    workspace.py             Workspace factory
    remote/                  RemoteWorkspace: sandboxed (API remote)
  critic/
    base.py                  CriticBase: evaluate → CriticResult (score, feedback)
    result.py                CriticResult: score (0-1), message, metadata, visualization
    impl/
      api/critic.py          APIBasedCritic: внешний API для оценки качества
      agent_finished.py      AgentFinishedCritic
      empty_patch.py         EmptyPatchCritic: детекция отсутствия изменений
      pass_critic.py         PassCritic: всегда проходная оценка
  settings/
    model.py                 AgentSettings, ConversationSettings, VerificationSettings
  extensions/
    fetch.py                 Расширения: загрузка из URL
  git/
    git_diff.py              Git diff/patch utilities
    git_changes.py           Отслеживание изменений в git-репозитории
    cached_repo.py           Кэширование репозиториев
  io/
    base.py                  FileStore: абстракция файлового хранилища
    local.py                 LocalFileStore: локальная FS с file locking
    memory.py                InMemoryFileStore: in-memory хранилище
    cache.py                 CacheLayer: кэширование поверх FileStore
  logger/
    logger.py                Структурированное логирование (structlog)
    rolling.py               Rolling file handler
  marketplace/
    types.py                 Marketplace: типы для обмена skills/plugins
  observability/
    laminar.py               Laminar integration для tracing
  secret/
    secrets.py               Secret management для LLM API keys
  testing/
    test_llm.py              TestLLM: deterministic mock LLM для тестирования
  utils/
    async_executor.py        Async utilities
    cipher.py                Шифрование секретов
    models.py                OpenHandsModel, DiscriminatedUnionMixin
    и другие утилиты
openhands-tools/openhands/tools/
  terminal/                  TerminalTool: bash-выполнение (timeout, background jobs, tmux)
  file_editor/               FileEditorTool: read / edit / create файлы
  task_tracker/              TaskTrackerTool: TODO-менеджмент
  browser_use/               BrowserUseTool: веб-навигация (Playwright)
  delegate/                  DelegateTool: sub-agent делегирование
  apply_patch/               ApplyPatchTool: применение unified diff патчей
  glob/                      GlobTool: поиск файлов по glob-паттернам
  grep/                      GrepTool: полнотекстовый поиск (ripgrep-style)
  task/                      TaskTool: менеджмент задач (TaskManager)
  gemini/                    Gemini-специфичные инструменты (read_file, write_file, edit, list_directory)
  planning_file_editor/      PlanningFileEditorTool: планирование + редактирование
  tom_consult/               TomConsultTool: консультации с другим агентом
  preset/                    Preset-конфигурации агентов (default, gemini, gpt5, planning)
openhands-workspace/
  openhands/workspace/
    docker/                   Docker workspace: sandboxed выполнение в контейнерах
    apptainer/                Apptainer (Singularity) workspace
    cloud/                    Cloud workspace: удалённое выполнение через API
    remote_api/               Remote API workspace: абстракция над удалёнными средами
openhands-agent-server/
  openhands/agent_server/
    api.py                   FastAPI REST API для Agent Server
    docker/                  Dockerfile для sandboxed-выполнения
    conversation_service.py  Conversation lifecycle management
```

### Ключевые характеристики

| Характеристика | Значение |
|---|---|
| **Тип** | SDK для построения AI-агентов (Python-библиотека) |
| **Модель выполнения** | Agent loop (LLM → Action → Tool → Observation → LLM → ...) |
| **State management** | Event stream (file-backed event store) |
| **Провайдеры** | 100+ LLM через LiteLLM (Anthropic, OpenAI, Google, Bedrock, Azure и т.д.) |
| **Расширяемость** | Custom tools, MCP, Skills (SKILL.md), Plugins, Hooks, Sub-agents |
| **Sandboxing** | Docker, Kubernetes, Apptainer, API-remote workspaces (отдельный пакет openhands-workspace) |
| **Безопасность** | Security risk assessment, confirmation policies, defense-in-depth rails |
| **Observability** | Laminar, event streaming, conversation metrics |
| **Параллелизм** | ParallelToolExecutor (concurrent tool calls с resource locking) |

### Основные компоненты

| Компонент | Назначение |
|---|---|
| [`Agent`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/agent/agent.py) | Core: LLM + tools → agent loop, response dispatch, iterative refinement |
| [`Conversation`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/conversation/conversation.py) | Factory: создаёт LocalConversation или RemoteConversation |
| [`ToolDefinition`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/tool/tool.py) | Абстракция инструмента: Action + Observation + Executor + annotations |
| [`StuckDetector`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/conversation/stuck_detector.py) | 4 активных + 1 TODO сценарий обнаружения зацикливания (action-obs, action-error, monologue, alternating; context-window loop пока не реализован) |
| [`LLMSummarizingCondenser`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/context/condenser/llm_summarizing_condenser.py) | LLM-based summarization при переполнении context window (max_size/max_tokens/keep_first) |
| [`FallbackStrategy`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/llm/fallback_strategy.py) | Fallback на alternate LLM-profiles при transient errors |
| [`SecurityAnalyzerBase`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/security/analyzer.py) | Risk assessment: UNKNOWN / LOW / MEDIUM / HIGH → confirmation |
| [`CriticBase`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/critic/base.py) | Quality evaluation: score + feedback, iterative refinement |
| [`SubagentRegistry`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/subagent/registry.py) | Регистрация и создание sub-agents (file-based, plugin, programmatic) |
| [`HookManager`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/hooks/manager.py) | Pre/Post tool use hooks (shell-скрипты) |
| [`ParallelToolExecutor`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/agent/parallel_executor.py) | Concurrent tool execution с resource-level locking |

---

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | TasK Orchestrator | OpenHands SDK | Статус |
|---|---|---|---|
| **Цепочки шагов (chains)** | ✅ YAML chains, статические и динамические | ❌ Нет. Agent loop с tool calls | ✅ У нас есть |
| **Retry с backoff** | ✅ RetryingAgentRunner | ✅ RetryMixin (tenacity, exponential backoff, 5 attempts) | ✅ Паритет |
| **Circuit Breaker** | ✅ CircuitBreakerAgentRunner | ❌ Нет | ✅ У нас есть |
| **Quality Gates** | ✅ Shell-команды как проверки | ⚠️ CriticBase (LLM-based scoring, не shell-команды) | ✅ У нас есть (разная модель) |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based) | ⚠️ Token/cost tracking через metrics, но без явных budget лимитов | ✅ У нас лучше |
| **Итерационные циклы (fix_iterations)** | ✅ Группа шагов с max_iterations | ✅ IterativeRefinementConfig (critic-driven, max_iterations) | ✅ Паритет (разная модель) |
| **Fallback routing** | ✅ Per-step fallback runner | ✅ FallbackStrategy (LLM profile fallback при transient errors) | ✅ Паритет |
| **Audit Trail (JSONL)** | ✅ JsonlAuditLogger | ✅ Event stream (file-backed event store, JSON serialization) | ✅ Паритет |
| **Ролевые промпты** | ✅ .md файлы (18+ ролей) | ✅ Jinja2-шаблоны (system_prompt, model-specific) | ✅ Паритет |
| **Multiple runners** | ✅ Pi + Codex (через interface) | ✅ 100+ провайдеров через LiteLLM | ✅ Паритет |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❌ SDK с modular packages (sdk, tools, workspace, agent-server) | ✅ У нас лучше |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Composition pattern (Agent = LLM + tools + context) | ✅ Разные подходы |
| **YAML-конфигурация** | ✅ Chains + roles в YAML | ❌ Программная конфигурация (Python API) | ✅ Разные подходы |
| **Stuck detection (loop detection)** | ❌ Нет | ✅ StuckDetector: 4 активных паттерна зацикливания | 🟡 Позже |
| **Context condensation (summarization)** | ❌ Нет | ✅ LLMSummarizingCondenser, View snapshot | 🟡 Позже |
| **Security risk assessment** | ❌ Нет | ✅ SecurityRisk (UNKNOWN/LOW/MEDIUM/HIGH), LLM-based + ensemble | 🟡 Интересно |
| **Confirmation policies** | ❌ Нет | ✅ AlwaysConfirm / NeverConfirm / ConfirmRisky | 🟡 Интересно |
| **Parallel tool execution** | ❌ Нет (sequential chains) | ✅ ParallelToolExecutor с resource-level locking | 🟡 Интересно |
| **Sub-agent delegation** | ❌ Нет | ✅ SubagentRegistry, DelegateTool, file-based agents | 🟡 Позже |
| **Hooks (pre/post tool use)** | ❌ Нет | ✅ HookManager: 6 lifecycle event types с matcher-фильтрацией | 🟡 Интересно |
| **MCP-интеграция** | ❌ Нет | ✅ MCPClient, MCPToolDefinition | 🟡 Позже |
| **Skills (SKILL.md)** | ✅ Свои role .md файлы | ✅ Формализованные SKILL.md, discovery, validation, marketplace | ✅ Паритет |
| **Plugin система** | ❌ Нет | ✅ Plugin: skills + tools + hooks + MCP из Git-репозиториев | 🟡 Интересно |
| **Agent Server (remote execution)** | ❌ Нет | ✅ Agent Server: FastAPI + Docker sandboxing | 🟢 Не берём |
| **Sandboxing (Docker/K8s)** | ❌ Нет | ✅ Docker, Kubernetes, Apptainer, API-remote workspaces | 🟢 Не берём |
| **Critic (quality scoring)** | ❌ Нет | ✅ CriticBase: score + feedback, iterative refinement | 🟡 Интересно |
| **LLM Profile Store** | ❌ Нет | ✅ LLMProfileStore: файловые JSON-профили с параметрами LLM | 🟡 Интересно |
| **Conversation persistence** | ❌ Нет (in-memory + JSONL) | ✅ File-backed event store, persistence_dir | 🟡 Позже |
| **Conversation forking** | ❌ Нет | ✅ Fork conversation state | 🟢 Не берём |
| **Tool annotations (readOnlyHint и т.д.)** | ❌ Нет | ✅ ToolAnnotations (readOnly, destructive, idempotent, openWorld) | 🟡 Интересно |
| **Event streaming (delta)** | ❌ Нет | ✅ StreamingDelta для real-time вывода | 🟢 Не берём |

---

## 3. Что полезно взять и почему

### 3.1 🟡 Stuck Detector — обнаружение зацикливания (`conversation/stuck_detector.py`)

**Что у них:** `StuckDetector` анализирует последние N событий (default: 20) и обнаруживает 4 активных паттерна зацикливания + 1 нереализованный:

1. **Repeating action-observation**: одинаковые Action + Observation повторяются N раз подряд
2. **Repeating action-error**: одинаковый Action приводит к ошибке N раз подряд
3. **Monologue**: агент повторяет одно и то же сообщение без вмешательства пользователя
4. **Alternating action-observation**: паттерн A→B→A→B→A→B (чередование двух пар)
5. **Context window error loop**: повторяющиеся ошибки context window после condensation — **⚠️ не реализовано** (`_is_stuck_context_window_error` всегда возвращает `False`, блокируется [issue #282](https://github.com/OpenHands/agent-sdk/issues/282))

```python
class StuckDetector:
    def __init__(self, state, thresholds=None):
        self.state = state
        self.thresholds = thresholds or StuckDetectionThresholds()
        # Defaults: action_observation=4, action_error=3,
        #           monologue=3, alternating_pattern=6

    def is_stuck(self) -> bool:
        events = list(self.state.events[-MAX_EVENTS_TO_SCAN_FOR_STUCK_DETECTION:])
        # Only look after last user message
        # Check all 4 active stuck patterns
        ...
```

**Почему нам интересно:** При итерационных циклах (fix_iterations) в task-orchestrator — если агент «зацикливается» (повторяет одни и те же ошибки), нужен механизм обнаружения и остановки. У нас пока нет защиты от такого сценария. Конкретно паттерн #1 (repeating action-observation) и #2 (repeating action-error) наиболее актуальны для наших chain-шагов.

**Отличие от нашей реализации:**
- У нас: нет stuck detection — max_iterations единственный ограничитель
- У них: декларативные thresholds + событийный анализ

---

### 3.2 🟡 Context Condensation — LLM-суммаризация при overflow (`context/condenser/`)

**Что у них:** Когда event stream превышает лимит (max_events или max_tokens), `LLMSummarizingCondenser`:
1. Берёт «забытые» события (за пределами keep_first + tail)
2. Вызывает отдельный LLM для суммаризации
3. Заменяет забытые события на CondensationSummaryEvent
4. Продолжает работу с компактным контекстом

```python
class LLMSummarizingCondenser(RollingCondenser):
    llm: LLM
    max_size: int = 240          # max events before condensation
    max_tokens: int | None = None
    keep_first: int = 2          # events to always keep
    minimum_progress: float = 0.1  # min fraction to condense
```

Также есть `View` — snapshot событийного потока, который вычисляет effective context для LLM-вызова, с учётом condensation и манипуляций (edit ranges, tool call matching).

**Почему нам интересно:** Для длинных цепочек (implement → review → fix → review → ...) контекст может расти. Condensation позволяет работать с arbitrarily длинными сессиями. Для будущих dynamic loops — критически важно.

**Отличие от Crush:** Crush делает auto-summarization тоже, но OpenHands SDK формализовал это через отдельную абстракцию (Condenser) с pipeline-подходом.

---

### 3.3 🟡 Security Risk Assessment + Confirmation Policies (`security/`)

**Что у них:** Двухуровневая система безопасности:

**Уровень 1 — Risk Assessment:** `SecurityAnalyzerBase` оценивает каждое действие:
- `LLMSecurityAnalyzer`: LLM оценивает risk inline (как часть tool call)
- `PatternSecurityAnalyzer`: regex-сигнатуры известных угроз (shell-destructive, code-exec, network-to-exec)
- `GraySwanAnalyzer`: эвристический анализ через GraySwan Cygnal API
- `PolicyRailSecurityAnalyzer`: структурные правила (fetch-to-exec, raw-disk-op, catastrophic-delete)
- `EnsembleSecurityAnalyzer`: max-severity fusion нескольких анализаторов
- Результат: `SecurityRisk` enum (UNKNOWN / LOW / MEDIUM / HIGH)

**Уровень 2 — Confirmation Policy:**
- `AlwaysConfirm`: всё требует подтверждения
- `NeverConfirm`: автonomic mode
- `ConfirmRisky`: подтверждение только при risk ≥ threshold (default: HIGH)

```python
class ConfirmRisky(ConfirmationPolicyBase):
    threshold: SecurityRisk = SecurityRisk.HIGH
    confirm_unknown: bool = True

    def should_confirm(self, risk: SecurityRisk) -> bool:
        if risk == SecurityRisk.UNKNOWN:
            return self.confirm_unknown
        return risk.is_riskier(self.threshold)  # reflexive: HIGH.is_riskier(HIGH) == True
```

**Почему нам интересно:** Если task-orchestrator будет выполнять автономные цепочки (без участия человека), нужен контроль: какие команды можно выполнять, какие файлы редактировать. Двухуровневая модель (risk assessment + confirmation policy) — хорошо структурированный подход.

---

### 3.4 🟡 Critic + Iterative Refinement (`critic/`)

**Что у них:** `CriticBase` — абстракция для оценки качества действий агента:

```python
class CriticBase(ABC):
    mode: Literal["finish_and_message", "all_actions"] = "finish_and_message"
    iterative_refinement: IterativeRefinementConfig | None  # auto-retry

    def evaluate(self, events, git_patch=None) -> CriticResult:
        """Возвращает score (0-1) + feedback"""
        ...
```

`IterativeRefinementConfig` автоматически повторяет задачу, если critic score ниже порога:
- `success_threshold: float = 0.6` — порог успешности
- `max_iterations: int = 3` — максимум итераций

Реализации:
- `APIBasedCritic` — вызов внешнего API для оценки
- `AgentFinishedCritic` — детекция завершения агентом
- `EmptyPatchCritic` — детекция отсутствия изменений в коде

**Почему нам интересно:** Концептуально похоже на наши fix_iterations, но с LLM-based scoring вместо shell-команд quality gates. Комбинация critic + iterative refinement — мощный паттерн: agent делает работу → critic оценивает → если score < threshold → agent дорабатывает.

**Отличие от нашей реализации:**
- У нас: fix_iterations с quality gates (shell-команды) — детерминированные проверки
- У них: critic (LLM-based scoring) — вероятностная оценка качества
- Оба подхода взаимодополняющие

---

### 3.5 🟡 Fallback Strategy — LLM Profile Fallback (`llm/fallback_strategy.py`)

**Что у них:** `FallbackStrategy` при transient error (rate limit, timeout, connection error) автоматически переключается на alternate LLM-профили:

```python
class FallbackStrategy(BaseModel):
    fallback_llms: list[str]  # Profile names, loaded from LLMProfileStore
    profile_store_dir: str | Path | None = None

    def try_fallback(self, primary_model, primary_error, metrics, call_fn):
        for fb_llm in self._iter_fallbacks():
            try:
                result = call_fn(fb_llm)
                metrics.merge(fb_llm.metrics)  # merge cost/tokens
                return result
            except Exception:
                continue
        return None
```

Отдельно — `LLMProfileStore`: файловые JSON-профили с параметрами LLM (model, temperature, base_url и т.д.), которые можно переключать runtime.

**Почему нам интересно:** У нас есть fallback через `FallbackAgentRunner`, но он работает на уровне runner'ов. У OpenHands — на уровне LLM-профилей с metrics merging. Идея файловых LLM-профилей — удобна для конфигурации разных моделей под разные задачи.

---

### 3.6 🟡 Tool Annotations — декларативные свойства инструментов (`tool/tool.py`)

**Что у них:** Каждый инструмент имеет `ToolAnnotations`:

```python
class ToolAnnotations(BaseModel):
    readOnlyHint: bool = False       # не изменяет окружение
    destructiveHint: bool = True     # может разрушать данные
    idempotentHint: bool = False     # повторный вызов = no-op
    openWorldHint: bool = True       # взаимодействует с внешним миром
```

Эти аннотации используются:
- `ParallelToolExecutor` для решений о параллелизме
- `SecurityAnalyzer` для risk assessment
- MCP-экспортом для совместимости

**Почему нам интересно:** Декларативные свойства инструментов — элегантный способ выразить constraints. Для наших runner'ов можно добавить аннотации: `readOnly`, `idempotent` — чтобы chain executor мог принимать решения о параллелизме и fallback.

---

### 3.7 🟡 Hooks System — shell-скрипты на lifecycle events (`hooks/`)

**Что у них:** Система hooks, привязанных к lifecycle events (6 типов):

- **PreToolUse**: shell-скрипт запускается перед tool execution; может **заблокировать** действие
- **PostToolUse**: shell-скрипт запускается после; только наблюдение
- **UserPromptSubmit**: обработка пользовательского ввода (может блокировать)
- **SessionStart**: при начале conversation
- **SessionEnd**: при завершении conversation
- **Stop**: при остановке агента (может предотвратить остановку)

```json
{
  "stop": [
    {
      "matcher": "*",
      "hooks": [
        {
          "type": "command",
          "command": "hook_scripts/on_stop.sh",
          "timeout": 600
        }
      ]
    }
  ],
  "pre_tool_use": [
    {
      "matcher": "terminal",
      "hooks": [
        {"command": "hook_scripts/block_dangerous.sh", "timeout": 30}
      ]
    }
  ]
}
```

Каждый hook-тип может иметь `matcher` для фильтрации по имени инструмента (`"*"` — все). Async hooks поддерживаются, но **не могут блокировать** операции в PreToolUse.

**Почему нам интересно:** Схожая идея с我们的 hooks в AGENTS.md, но formalized как executable scripts. Для task-orchestrator: pre/post hooks на уровне chain steps (перед/после выполнения шага) — мощный extension point. Пример: pre-step hook проверяет lint, post-step hook отправляет уведомление.

---

### 3.8 🟡 Parallel Tool Execution с Resource Locking (`agent/parallel_executor.py`)

**Что у них:** `ParallelToolExecutor` выполняет несколько tool calls concurrently с resource-level locking:

```python
class ParallelToolExecutor:
    def __init__(self, max_workers=1, lock_manager=None): ...

    def execute_batch(self, action_events, tool_runner, tools=None):
        # 1. Resolve declared_resources для каждого action
        # 2. Lock shared resources (file:/path, terminal:session)
        # 3. Execute concurrently (ThreadPoolExecutor)
        # 4. Return results in original order
```

Инструменты декларируют ресурсы через `declared_resources()`:
- `DeclaredResources(keys=("file:/a.py",), declared=True)` — lock file
- `DeclaredResources(keys=(), declared=True)` — safe, no locking
- `DeclaredResources(keys=(), declared=False)` — unknown, serialize

**Почему нам интересно:** Для цепочек, где несколько шагов можно выполнить параллельно (например, lint + type-check + tests). Resource locking предотвращает конфликты при параллельном доступе к FS.

---

### 3.9 🟡 Sub-agent Delegation — файловые и программные агенты (`subagent/`)

**Что у них:** Система регистрации и делегирования sub-агентов:

1. **Программная регистрация**: `register_agent(name, factory_func, description)`
2. **Файловые агенты**: Markdown-файлы с YAML frontmatter (`.agents/agents/*.md` и `.openhands/agents/*.md`) → auto-discovery
3. **Plugin-агенты**: из Git-репозиториев

```markdown
---
name: security_expert
description: "Expert in security analysis"
tools: [terminal, file_editor]
model: inherit
skills: [code-style-guide]
---

You are a cybersecurity expert. Always consider security implications...
```

**DelegateTool** позволяет агенту делегировать задачу sub-агенту через tool call.

**Почему нам интересно:** Для будущих multi-agent сценариев: main agent делегирует sub-tasks специализированным агентам. Файловый формат (Markdown с YAML frontmatter) для определения агентов — удобен для конфигурации без кода.

---

## 4. Что НЕ берём и почему

### 4.1 🟢 Agent Server / Docker Sandboxing

OpenHands SDK имеет полноценный Agent Server (FastAPI) для sandboxed-выполнения в Docker/Kubernetes/Apptainer. Наш оркестратор работает как CLI-утилита в existing окружении. Sandboxing — задача CI/CD инфраструктуры, не оркестратора.

### 4.2 🟢 Browser Tool (Playwright)

OpenHands SDK включает browser-use для веб-навигации. Наш оркестратор — для code-related задач (implement → review → fix). Взаимодействие с браузером — за пределами наших use cases.

### 4.3 🟢 Conversation Forking

OpenHands поддерживает forking — ветвление conversation state для exploration. Наш оркестратор выполняет линейные цепочки. Forking добавляет complexity без clear benefit для наших задач.

### 4.4 🟢 Event Streaming (StreamingDelta)

Real-time streaming partial LLM output — полезно для interactive UI, но не для автоматического pipeline. Наш audit trail (JSONL) — post-factum.

### 4.5 🟢 MCP (Model Context Protocol) Integration

MCP — мощный протокол расширения, но добавляет зависимость на external server lifecycle. Для pipeline-оркестратора — overhead. Если понадобится, добавим через отдельный runner.

### 4.6 🟢 Plugin System (Git-based)

Полноценная plugin system с загрузкой из Git-репозиториев — overengineering для нашего stage. Наши runner'ы расширяются через interface + YAML-конфигурацию.

### 4.7 🟢 LiteLLM Dependency

OpenHands SDK завязан на LiteLLM для multi-provider поддержки. Наш оркестратор работает через runner'ы (pi, codex), каждый runner сам общается с конкретным API. Нам не нужна прослойка над LLM API.

---

## 5. Сводка рекомендаций

| Фича | Приоритет | Обоснование |
|---|---|---|
| Chain orchestration | ✅ Уже есть | Core-функциональность task-orchestrator |
| Retry + Circuit Breaker | ✅ Уже есть | Устойчивость при сбоях |
| Quality Gates (shell) | ✅ Уже есть | Автоматическая проверка кода |
| Budget control | ✅ Уже есть | Предотвращение runaway spending |
| Fix iterations | ✅ Уже есть | Closed-loop цикл разработки |
| Stuck detection (4 паттерна) | 🟡 P2 | Защита от зацикливания в итерационных циклах |
| Security risk assessment | 🟡 P2 | Для автономного выполнения (без человека) |
| Confirmation policies | 🟡 P2 | Управление разрешениями при autonomous execution |
| Context condensation | 🟡 P3 | Для длинных dynamic loops |
| Tool annotations | 🟡 P3 | Декларативные свойства для runner'ов |
| Critic (LLM-based quality scoring) | 🟡 P3 | Вероятностная оценка качества (дополнение к shell gates) |
| LLM Profile Store | 🟡 P3 | Файловые конфигурации LLM-параметров |
| Hooks (pre/post step) | 🟡 P3 | Extension points для chain steps |
| Parallel tool execution | 🟡 P3 | Для независимых шагов в chain |
| Sub-agent delegation | 🟡 P3 | Для будущих multi-agent сценариев |
| Agent Server / Sandboxing | 🟢 — | Задача CI/CD инфраструктуры |
| Browser tool | 🟢 — | Не наши use cases |
| Conversation forking | 🟢 — | Overengineering |
| MCP integration | 🟢 — | Overhead для pipeline |
| Plugin system | 🟢 — | Overengineering для нашего stage |
| Event streaming | 🟢 — | Нужно только для interactive UI |

---

## 6. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории OpenHands Software Agent SDK:

- [`openhands-sdk/openhands/sdk/agent/agent.py`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/agent/agent.py) — Agent: agent loop, response dispatch, iterative refinement, critic mixin
- [`openhands-sdk/openhands/sdk/conversation/stuck_detector.py`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/conversation/stuck_detector.py) — StuckDetector: 4 активных + 1 TODO паттерн зацикливания
- [`openhands-sdk/openhands/sdk/context/condenser/llm_summarizing_condenser.py`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/context/condenser/llm_summarizing_condenser.py) — LLM-based context condensation
- [`openhands-sdk/openhands/sdk/security/analyzer.py`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/security/analyzer.py) — Security risk assessment: abstract base + implementations
- [`openhands-sdk/openhands/sdk/security/confirmation_policy.py`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/security/confirmation_policy.py) — Confirmation policies (Always/Never/Risky)
- [`openhands-sdk/openhands/sdk/critic/base.py`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/critic/base.py) — Critic + IterativeRefinement
- [`openhands-sdk/openhands/sdk/llm/fallback_strategy.py`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/llm/fallback_strategy.py) — FallbackStrategy: LLM profile fallback при transient errors
- [`openhands-sdk/openhands/sdk/llm/utils/retry_mixin.py`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/llm/utils/retry_mixin.py) — RetryMixin: tenacity exponential backoff
- [`openhands-sdk/openhands/sdk/tool/tool.py`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/tool/tool.py) — ToolDefinition: Action/Observation, annotations, executor
- [`openhands-sdk/openhands/sdk/subagent/registry.py`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/subagent/registry.py) — Sub-agent registry: register_agent, file-based agents
- [`openhands-sdk/openhands/sdk/agent/parallel_executor.py`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/agent/parallel_executor.py) — ParallelToolExecutor: concurrent execution с resource locking
- [`openhands-sdk/openhands/sdk/hooks/manager.py`](https://github.com/OpenHands/software-agent-sdk/blob/main/openhands-sdk/openhands/sdk/hooks/manager.py) — HookManager: pre/post tool use hooks
- [`AGENTS.md`](https://github.com/OpenHands/software-agent-sdk/blob/main/AGENTS.md) — Репозиторий-уровневые инструкции для AI-агентов
- [`README.md`](https://github.com/OpenHands/software-agent-sdk/blob/main/README.md) — Документация SDK: quick start, examples, architecture
- [OpenHands SDK Documentation](https://docs.openhands.dev/sdk) — Полная документация SDK

---

📚 **Источники:**
1. [github.com/OpenHands/software-agent-sdk](https://github.com/OpenHands/software-agent-sdk) — репозиторий SDK
2. [docs.openhands.dev/sdk](https://docs.openhands.dev/sdk) — официальная документация SDK
3. [arxiv.org/abs/2511.03690](https://arxiv.org/abs/2511.03690) — технический отчёт OpenHands
4. [github.com/OpenHands/OpenHands](https://github.com/OpenHands/OpenHands) — основной репозиторий (GUI, Cloud)
5. [SWE-Bench Benchmark](https://www.swebench.com/) — OpenHands SDK: 77.6% на SWE-Bench
