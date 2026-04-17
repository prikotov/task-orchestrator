# Диаграммы

Mermaid-диаграммы Orchestrator. Рендерятся нативно в GitHub markdown preview.

## Component-диаграмма: два модуля

Обзор модулей AgentRunner и Orchestrator, их DDD-слоёв и связей через Port/Adapter.

```mermaid
graph TB
    subgraph Presentation["Presentation (host app)"]
        OC["OrchestrateCommand"]
        RC["RunCommand"]
        RNC["RunnersCommand"]
    end

    subgraph OrchApp["Orchestrator · Application"]
        CH["OrchestrateChainCommandHandler"]
        SCH["RunAgentCommandHandler"]
        GRH["GetRunnersQueryHandler"]
        RP["GenerateReportQueryHandler"]
        ESC["ExecuteStaticChainService"]
    end

    subgraph OrchDomain["Orchestrator · Domain"]
        direction TB
        PORT["AgentRunnerPortInterface"]
        REG_PORT["AgentRunnerRegistryPortInterface"]
        CL["ChainLoaderInterface"]
        PP["PromptProviderInterface"]
        QGI["QualityGateRunnerInterface"]
        RSC["RunStaticChainService"]
        RDLI["RunDynamicLoopServiceInterface"]
        VO_ORCH["Chain*-VOs"]
    end

    subgraph OrchInfra["Orchestrator · Infrastructure"]
        ADAPTER["AgentRunnerAdapter"]
        REG_ADAPTER["AgentRunnerRegistryAdapter"]
        MAPPER["AgentVoMapper"]
        YCL["YamlChainLoader"]
        QG["QualityGateRunner"]
        RPB["RolePromptBuilder"]
        RDLS["RunDynamicLoopAgentService"]
    end

    subgraph ARDomain["AgentRunner · Domain"]
        AR["AgentRunnerInterface"]
        REG_AR["AgentRunnerRegistryServiceInterface"]
        RETRY_F["RetryableRunnerFactoryInterface"]
        VO_AR["Agent*-VOs"]
    end

    subgraph ARInfra["AgentRunner · Infrastructure"]
        PI["PiAgentRunner"]
        RET["RetryingAgentRunner"]
        CB["CircuitBreakerAgentRunner"]
        RETRY_FI["RetryableRunnerFactory"]
    end

    OC -->|uses| CH
    RC -->|uses| SCH
    RNC -->|uses| GRH

    CH --> ESC
    CH --> CL
    CH --> REG_PORT
    CH --> RDLI
    ESC --> RSC
    RSC --> PORT
    RSC --> QGI

    PORT -.->|impl| ADAPTER
    REG_PORT -.->|impl| REG_ADAPTER
    ADAPTER --> MAPPER
    ADAPTER --> AR
    ADAPTER --> RETRY_F
    REG_ADAPTER --> REG_AR
    MAPPER --> VO_AR

    AR -.->|impl| PI
    AR -.->|impl| RET
    AR -.->|impl| CB
    RETRY_F -.->|impl| RETRY_FI
    CL -.->|impl| YCL
    PP -.->|impl| RPB
    QGI -.->|impl| QG
    RDLI -.->|impl| RDLS

    style Presentation fill:#e3f2fd,stroke:#1565c0
    style OrchApp fill:#fff3e0,stroke:#e65100
    style OrchDomain fill:#e8f5e9,stroke:#2e7d32
    style OrchInfra fill:#fce4ec,stroke:#c62828
    style ARDomain fill:#e8f5e9,stroke:#1b5e20
    style ARInfra fill:#fce4ec,stroke:#b71c1c
```

## Component-диаграмма слоёв (детализация)

Детальный обзор DDD-слоёв внутри каждого модуля. Сплошные стрелки — прямые зависимости, пунктирные — реализация интерфейса.

```mermaid
graph TB
    subgraph Presentation["Presentation (host app)"]
        OC["OrchestrateCommand"]
        RC["RunCommand"]
        RNC["RunnersCommand"]
    end

    subgraph Application["Orchestrator · Application"]
        CH["OrchestrateChainCommandHandler"]
        SCH["RunAgentCommandHandler"]
        GRH["GetRunnersQueryHandler"]
        RP["GenerateReportQueryHandler"]
        ESC["ExecuteStaticChainService"]
    end

    subgraph Domain["Orchestrator · Domain"]
        direction TB
        PORT["AgentRunnerPortInterface"]
        REG_PORT["AgentRunnerRegistryPortInterface"]
        CL["ChainLoaderInterface"]
        PP["PromptProviderInterface"]
        QGI["QualityGateRunnerInterface"]
        RSC["RunStaticChainService"]
        RDLI["RunDynamicLoopServiceInterface"]
        VO["Chain*-Value Objects"]
    end

    subgraph Infrastructure["Orchestrator · Infrastructure"]
        ADAPTER["AgentRunnerAdapter"]
        REG_ADAPTER["AgentRunnerRegistryAdapter"]
        YCL["YamlChainLoader"]
        QG["QualityGateRunner"]
        RPB["RolePromptBuilder"]
        RDLS["RunDynamicLoopAgentService"]
    end

    OC -->|uses| CH
    RC -->|uses| SCH
    RNC -->|uses| GRH
    CH --> ESC
    CH --> CL
    CH --> REG_PORT
    CH --> RDLI
    ESC --> RSC
    RSC --> PORT
    RSC --> QGI
    REG_PORT -.->|resolves| PORT
    PORT -.->|impl| ADAPTER
    REG_PORT -.->|impl| REG_ADAPTER
    CL -.->|impl| YCL
    PP -.->|impl| RPB
    QGI -.->|impl| QG
    RDLI -.->|impl| RDLS

    style Presentation fill:#e3f2fd,stroke:#1565c0
    style Application fill:#fff3e0,stroke:#e65100
    style Domain fill:#e8f5e9,stroke:#2e7d32
    style Infrastructure fill:#fce4ec,stroke:#c62828
```

## Class-диаграмма: Port/Adapter

Механизм Port/Adapter между Orchestrator и AgentRunner.

```mermaid
classDiagram
    direction LR

    class AgentRunnerPortInterface {
        <<interface>>
        +getName() string
        +isAvailable() bool
        +run(ChainRunRequestVo, ChainRetryPolicyVo) ChainRunResultVo
    }

    class AgentRunnerRegistryPortInterface {
        <<interface>>
        +get(string name) AgentRunnerPortInterface
        +getDefault() AgentRunnerPortInterface
        +list() array
    }

    class AgentRunnerAdapter {
        -AgentRunnerInterface runner
        -RetryableRunnerFactoryInterface factory
        -AgentVoMapper mapper
        +getName() string
        +isAvailable() bool
        +run(ChainRunRequestVo, ChainRetryPolicyVo) ChainRunResultVo
    }

    class AgentRunnerRegistryAdapter {
        -AgentRunnerRegistryServiceInterface registry
        -RetryableRunnerFactoryInterface factory
        -AgentVoMapper mapper
        -array portCache
        +get(string) AgentRunnerPortInterface
        +getDefault() AgentRunnerPortInterface
        +list() array
    }

    class AgentVoMapper {
        +mapToAgentRequest(ChainRunRequestVo) AgentRunRequestVo
        +mapFromAgentResult(AgentResultVo) ChainRunResultVo
        +mapFromAgentTurnResult(AgentTurnResultVo) ChainTurnResultVo
        +mapToAgentRetryPolicy(ChainRetryPolicyVo) RetryPolicyVo
    }

    class AgentRunnerInterface {
        <<interface>>
        +getName() string
        +isAvailable() bool
        +run(AgentRunRequestVo) AgentResultVo
    }

    class AgentRunnerRegistryServiceInterface {
        <<interface>>
        +get(string name) AgentRunnerInterface
        +getDefault() AgentRunnerInterface
        +list() array
    }

    AgentRunnerPortInterface <|.. AgentRunnerAdapter : implements
    AgentRunnerRegistryPortInterface <|.. AgentRunnerRegistryAdapter : implements
    AgentRunnerAdapter --> AgentVoMapper : uses
    AgentRunnerAdapter --> AgentRunnerInterface : delegates to
    AgentRunnerAdapter --> RetryableRunnerFactoryInterface : uses
    AgentRunnerRegistryAdapter --> AgentVoMapper : uses
    AgentRunnerRegistryAdapter --> AgentRunnerRegistryServiceInterface : delegates to

    note for AgentRunnerAdapter "Маппит Chain*-VO ↔ Agent*-VO\nИнкапсулирует retry через RetryableRunnerFactory"
    note for AgentVoMapper "Stateless маппер\nChain*-VO ↔ Agent*-VO"
```

## Class-диаграмма Domain-слоя Orchestrator

Интерфейсы, Value Objects и исключения Orchestrator Domain.

```mermaid
classDiagram
    direction LR

    class AgentRunnerPortInterface {
        <<interface>>
        +getName() string
        +isAvailable() bool
        +run(ChainRunRequestVo, ChainRetryPolicyVo) ChainRunResultVo
    }

    class AgentRunnerRegistryPortInterface {
        <<interface>>
        +get(string name) AgentRunnerPortInterface
        +getDefault() AgentRunnerPortInterface
        +list() array
    }

    class ChainLoaderInterface {
        <<interface>>
        +load(string name) ChainDefinitionVo
        +list() array
    }

    class PromptProviderInterface {
        <<interface>>
        +getPrompt(string role) string
        +getPromptFilePath(string role) string
        +roleExists(string role) bool
        +getAvailableRoles() array
    }

    class ChainRunRequestVo {
        +role: string
        +task: string
        +systemPrompt: ?string
        +previousContext: ?string
        +model: ?string
        +tools: ?string
        +workingDir: ?string
        +timeout: int
        +command: list~string~
        +runnerArgs: list~string~
    }

    class ChainRunResultVo {
        +outputText: string
        +inputTokens: int
        +outputTokens: int
        +cost: float
        +exitCode: int
        +model: ?string
        +isError: bool
        +createFromSuccess(...)$ ChainRunResultVo
        +createFromError(...)$ ChainRunResultVo
    }

    class ChainDefinitionVo {
        +name: string
        +type: ChainTypeEnum
        +steps: list~ChainStepVo~
        +facilitator: ?string
        +participants: list~string~
        +maxRounds: int
        +budget: ?BudgetVo
        +isDynamic() bool
        +createFromSteps(...)$ self
        +createFromDynamic(...)$ self
    }

    class ChainStepVo {
        +type: ChainStepTypeEnum
        +role: ?string
        +runner: string
        +name: ?string
        +command: string
        +label: string
        +isAgent() bool
        +isQualityGate() bool
        +agent(...)$ self
        +qualityGate(...)$ self
    }

    class ChainNotFoundException {
        +__construct(string chainName)
    }
    class RoleNotFoundException {
        +__construct(string role)
    }
    class OrchestratorException {
        <<base exception>>
    }

    AgentRunnerPortInterface --> ChainRunRequestVo : takes
    AgentRunnerPortInterface --> ChainRunResultVo : returns
    AgentRunnerRegistryPortInterface --> AgentRunnerPortInterface : manages
    ChainLoaderInterface --> ChainDefinitionVo : loads
    ChainDefinitionVo --> ChainStepVo : contains

    note for ChainNotFoundException "extends OrchestratorException\nimplements NotFoundExceptionInterface"
    note for RoleNotFoundException "extends OrchestratorException\nimplements NotFoundExceptionInterface"
```

## Class-диаграмма Domain-слоя AgentRunner

Интерфейсы и Value Objects модуля AgentRunner.

```mermaid
classDiagram
    direction LR

    class AgentRunnerInterface {
        <<interface>>
        +getName() string
        +isAvailable() bool
        +run(AgentRunRequestVo) AgentResultVo
    }

    class AgentRunnerRegistryServiceInterface {
        <<interface>>
        +get(string name) AgentRunnerInterface
        +getDefault() AgentRunnerInterface
        +list() array
    }

    class RetryableRunnerFactoryInterface {
        <<interface>>
        +createRetryableRunner(AgentRunnerInterface, RetryPolicyVo) AgentRunnerInterface
    }

    class AgentRunRequestVo {
        +role: string
        +task: string
        +systemPrompt: ?string
        +previousContext: ?string
        +model: ?string
        +tools: ?string
        +workingDir: ?string
        +timeout: int
        +command: list~string~
        +runnerArgs: list~string~
        +withTruncatedContext() self
    }

    class AgentResultVo {
        +outputText: string
        +inputTokens: int
        +outputTokens: int
        +cost: float
        +exitCode: int
        +model: ?string
        +isError: bool
        +createFromSuccess(...)$ self
        +createFromError(...)$ self
    }

    class AgentTurnResultVo {
        +agentResult: AgentResultVo
        +duration: float
        +userPrompt: ?string
        +systemPrompt: ?string
        +invocation: ?string
    }

    class RetryPolicyVo {
        +maxRetries: int
        +initialDelayMs: int
        +maxDelayMs: int
        +multiplier: float
    }

    class CircuitBreakerStateVo {
        +state: CircuitStateEnum
        +failureCount: int
        +lastFailureTime: ?float
    }

    class RunnerNotFoundException {
        +__construct(string runnerName)
    }

    AgentRunnerInterface --> AgentRunRequestVo : takes
    AgentRunnerInterface --> AgentResultVo : returns
    AgentRunnerRegistryServiceInterface --> AgentRunnerInterface : manages
    AgentTurnResultVo --> AgentResultVo : contains
    RetryableRunnerFactoryInterface --> AgentRunnerInterface : creates

    note for RunnerNotFoundException "extends AgentException\nimplements NotFoundExceptionInterface"
```

## Sequence: оркестрация static-цепочки

Линейное выполнение шагов с поддержкой итерационных циклов и quality gates.

```mermaid
sequenceDiagram
    autonumber
    participant CLI as OrchestrateCommand
    participant H as OrchestrateChainCommandHandler
    participant CL as ChainLoaderInterface
    participant REG as AgentRunnerRegistryPortInterface
    participant ESC as ExecuteStaticChainService
    participant RSC as RunStaticChainService
    participant STEP as ExecuteStaticStepService
    participant PORT as AgentRunnerPortInterface
    participant QG as QualityGateRunnerInterface

    CLI->>H: __invoke(command)
    H->>CL: load(chainName)
    CL-->>H: ChainDefinitionVo (type=static)
    H->>REG: get(runnerName)
    REG-->>H: AgentRunnerPortInterface
    H->>ESC: execute(chain, runner, ...)
    ESC->>RSC: execute(chain, runner, ...)

    loop Для каждого ChainStepVo
        alt step.isAgent()
            RSC->>STEP: execute(step, runner, previousContext)
            STEP->>PORT: run(ChainRunRequestVo, retryPolicy)
            PORT-->>STEP: ChainRunResultVo
            STEP-->>RSC: StaticStepResultVo
        else step.isQualityGate()
            RSC->>QG: run(QualityGateVo)
            QG-->>RSC: QualityGateResultVo
        end
        Note over RSC: Проверка budget, логирование, фиксация контекста
    end

    Note over RSC: fix_iterations: возврат к началу группы

    RSC-->>ESC: StaticChainResultVo
    ESC-->>H: OrchestrateChainResultDto
    H-->>CLI: result
```

## Sequence: оркестрация dynamic-цепочки

Фасилитатор решает в рантайме, кому дать слово. Цикл завершается когда фасилитатор возвращает `{done: true}`.

```mermaid
sequenceDiagram
    autonumber
    participant CLI as OrchestrateCommand
    participant H as OrchestrateChainCommandHandler
    participant CL as ChainLoaderInterface
    participant REG as AgentRunnerRegistryPortInterface
    participant CBC as BuildDynamicContextService
    participant DLR as RunDynamicLoopService
    participant EDT as ExecuteDynamicTurnService
    participant DTA as RunDynamicLoopAgentServiceInterface
    participant PORT as AgentRunnerPortInterface
    participant FPAR as FacilitatorResponseParser
    participant SL as ChainSessionLogger

    CLI->>H: __invoke(command)
    H->>CL: load(chainName)
    CL-->>H: ChainDefinitionVo (type=dynamic)
    H->>SL: startSession(chainName, topic, ...)
    H->>CBC: buildContext(chain, ...)
    CBC-->>H: DynamicChainContextVo
    H->>REG: get(runnerName)
    REG-->>H: AgentRunnerPortInterface
    H->>DLR: execute(chain, runner, context, ...)

    loop round = 1..maxRounds
        alt Фасилитатор
            DLR->>EDT: runFacilitatorStep(chain, runner, execution, ...)
            EDT->>DTA: runFacilitator(step, round, runner, ...)
            DTA->>PORT: run(ChainRunRequestVo, retryPolicy)
            PORT-->>DTA: ChainRunResultVo
            DTA->>FPAR: parse(outputText)
            FPAR-->>DTA: FacilitatorResponseVo
            DTA-->>EDT: [ChainTurnResultVo, FacilitatorResponseVo]
            EDT-->>DLR: [ChainTurnResultVo, FacilitatorResponseVo]
        else Участник (next_role)
            DLR->>EDT: runParticipantStep(chain, runner, execution, nextRole, ...)
            EDT->>DTA: runParticipant(step, round, runner, role, ...)
            DTA->>PORT: run(ChainRunRequestVo, retryPolicy)
            PORT-->>DTA: ChainTurnResultVo
            DTA-->>EDT: ChainTurnResultVo
            EDT-->>DLR: ChainTurnResultVo
        end
        Note over DLR: Проверка budget (CheckDynamicBudgetService), запись раунда (RecordDynamicRoundService)
        alt FacilitatorResponse.done = true
            Note over DLR: synthesis получен → выход из цикла
        end
    end

    alt maxRounds достигнут без synthesis
        Note over DLR: ExecuteFinalizeTurn → финальный вызов фасилитатора
    end

    DLR-->>H: DynamicLoopResultVo
    H->>SL: completeSession(synthesis, metrics, ...)
    H->>H: dispatchCompletedEvent()
    H-->>CLI: OrchestrateChainResultDto
```

## Sequence: Port → Adapter → AgentRunner

Детализация вызова через Port/Adapter при `AgentRunnerPortInterface::run()`.

```mermaid
sequenceDiagram
    autonumber
    participant RSC as RunStaticChainService
    participant PORT as AgentRunnerPortInterface
    participant ADAPTER as AgentRunnerAdapter
    participant MAPPER as AgentVoMapper
    participant FACTORY as RetryableRunnerFactoryInterface
    participant RUNNER as AgentRunnerInterface

    RSC->>PORT: run(ChainRunRequestVo, ChainRetryPolicyVo)
    PORT->>ADAPTER: run(ChainRunRequestVo, ChainRetryPolicyVo)
    ADAPTER->>MAPPER: mapToAgentRequest(ChainRunRequestVo)
    MAPPER-->>ADAPTER: AgentRunRequestVo
    ADAPTER->>MAPPER: mapToAgentRetryPolicy(ChainRetryPolicyVo)
    MAPPER-->>ADAPTER: RetryPolicyVo or null

    alt retryPolicy !== null
        ADAPTER->>FACTORY: createRetryableRunner(runner, retryPolicyVo)
        FACTORY-->>ADAPTER: RetryingAgentRunner
        ADAPTER->>RUNNING: [RetryingAgentRunner] run(AgentRunRequestVo)
    else retryPolicy === null
        ADAPTER->>RUNNING: [AgentRunnerInterface] run(AgentRunRequestVo)
    end

    RUNNER-->>ADAPTER: AgentResultVo
    ADAPTER->>MAPPER: mapFromAgentResult(AgentResultVo)
    MAPPER-->>ADAPTER: ChainRunResultVo
    ADAPTER-->>PORT: ChainRunResultVo
    PORT-->>RSC: ChainRunResultVo
```

## Flowchart: PiAgentRunner

Внутренний поток `PiAgentRunner::run()` — разрешение команды, формирование аргументов, запуск процесса, парсинг результата.

```mermaid
flowchart TD
    A["run(AgentRunRequestVo)"] --> B{"command задан?"}
    B -->|Нет| C["Команда по умолчанию:<br/>pi --mode json -p --no-session"]
    B -->|Да| D["Использовать command из RoleConfig"]
    C --> E["resolveCommandFiles()<br/>@path → содержимое файла"]
    D --> E
    E --> F{"model задан?<br/>и нет --model в command?"}
    F -->|Да| G["Добавить --model"]
    F -->|Нет| H{"tools задан?"}
    G --> H
    H -->|"tools=''"| I["Добавить --no-tools"]
    H -->|"tools!=null"| J["Добавить --tools"]
    H -->|null| K{"systemPrompt задан?"}
    I --> K
    J --> K
    K -->|Да| L["Добавить --system-prompt"]
    K -->|Нет| M["Формирование user-промпта"]
    L --> M
    M --> N["Запуск Symfony Process"]
    N --> O{"Успешно?"}
    O -->|Да| P["PiJsonlParser.parse(stdout)"]
    P --> Q["AgentResultVo::createFromSuccess()"]
    O -->|Timeout| R["AgentResultVo::createFromError('timed out')"]
    O -->|Ошибка| S["AgentResultVo::createFromError(stderr)"]

    style A fill:#e3f2fd,stroke:#1565c0
    style Q fill:#e8f5e9,stroke:#2e7d32
    style R fill:#fff3e0,stroke:#e65100
    style S fill:#fce4ec,stroke:#c62828
```
