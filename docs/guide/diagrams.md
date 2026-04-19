# Диаграммы

Mermaid-диаграммы Orchestrator. Рендерятся нативно в GitHub markdown preview.

## Component-диаграмма: два модуля

Обзор модулей AgentRunner и Orchestrator, их DDD-слоёв и связей через Integration-слой.

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
        RASI["RunAgentServiceInterface"]
        CL["ChainLoaderInterface"]
        PP["PromptProviderInterface"]
        QGI["QualityGateRunnerInterface"]
        RSC["RunStaticChainService"]
        RDLI["RunDynamicLoopServiceInterface"]
        VO_ORCH["Chain*-VOs"]
    end

    subgraph OrchInteg["Orchestrator · Integration"]
        RAS["RunAgentService"]
        ADM["AgentDtoMapper"]
    end

    subgraph OrchInfra["Orchestrator · Infrastructure"]
        YCL["YamlChainLoader"]
        QG["QualityGateRunner"]
        RPB["RolePromptBuilder"]
        RDLS["RunDynamicLoopAgentService"]
    end

    subgraph ARApp["AgentRunner · Application"]
        RACH["RunAgentCommandHandler"]
        GRACH["GetRunnersQueryHandler"]
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
    CH --> RDLI
    ESC --> RSC
    RSC --> RASI
    RSC --> QGI

    RASI -.->|impl| RAS
    RAS --> ADM
    RAS --> RACH
    ADM --> RACH

    RACH --> AR
    RACH --> REG_AR
    RACH --> RETRY_F

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
    style OrchInteg fill:#f3e5f5,stroke:#7b1fa2
    style OrchInfra fill:#fce4ec,stroke:#c62828
    style ARApp fill:#fff3e0,stroke:#ef6c00
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
        RASI["RunAgentServiceInterface"]
        CL["ChainLoaderInterface"]
        PP["PromptProviderInterface"]
        QGI["QualityGateRunnerInterface"]
        RSC["RunStaticChainService"]
        RDLI["RunDynamicLoopServiceInterface"]
        VO["Chain*-Value Objects"]
    end

    subgraph Integration["Orchestrator · Integration"]
        RAS["RunAgentService"]
        ADM["AgentDtoMapper"]
    end

    subgraph Infrastructure["Orchestrator · Infrastructure"]
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
    CH --> RDLI
    ESC --> RSC
    RSC --> RASI
    RSC --> QGI
    RASI -.->|impl| RAS
    RAS --> ADM
    CL -.->|impl| YCL
    PP -.->|impl| RPB
    QGI -.->|impl| QG
    RDLI -.->|impl| RDLS

    style Presentation fill:#e3f2fd,stroke:#1565c0
    style Application fill:#fff3e0,stroke:#e65100
    style Domain fill:#e8f5e9,stroke:#2e7d32
    style Integration fill:#f3e5f5,stroke:#7b1fa2
    style Infrastructure fill:#fce4ec,stroke:#c62828
```

## Class-диаграмма: Integration-слой (ACL)

Механизм связи между Orchestrator и AgentRunner через Integration-слой.

```mermaid
classDiagram
    direction LR

    class RunAgentServiceInterface {
        <<interface>>
        +run(ChainRunRequestVo, ChainRetryPolicyVo) ChainRunResultVo
    }

    class RunAgentService {
        -RunAgentCommandHandler runAgentHandler
        -AgentDtoMapper mapper
        +run(ChainRunRequestVo, ChainRetryPolicyVo) ChainRunResultVo
    }

    class AgentDtoMapper {
        +mapToRunAgentCommand(ChainRunRequestVo, ChainRetryPolicyVo) RunAgentCommand
        +mapFromRunAgentResultDto(RunAgentResultDto) ChainRunResultVo
    }

    class RunAgentCommandHandler {
        +__invoke(RunAgentCommand) RunAgentResultDto
    }

    class RunAgentCommand {
        +runnerName: string
        +role: string
        +task: string
        +systemPrompt: ?string
        +previousContext: ?string
        +model: ?string
        +tools: ?string
        +workingDir: ?string
        +timeout: int
        +retryMaxRetries: ?int
        +retryInitialDelayMs: int
        +retryMaxDelayMs: int
        +retryMultiplier: float
    }

    class RunAgentResultDto {
        +outputText: string
        +inputTokens: int
        +outputTokens: int
        +cost: float
        +exitCode: int
        +model: ?string
        +isError: bool
        +errorMessage: ?string
    }

    RunAgentServiceInterface <|.. RunAgentService : implements
    RunAgentService --> AgentDtoMapper : uses
    RunAgentService --> RunAgentCommandHandler : delegates to
    AgentDtoMapper --> RunAgentCommand : creates
    AgentDtoMapper --> RunAgentResultDto : reads

    note for RunAgentService "ACL: маппит Chain*-VO ↔ AgentRunner DTO\nДелегирует в AgentRunner Application"
    note for AgentDtoMapper "Stateless маппер\nOrchestrator Domain VO ↔ AgentRunner Application DTO"
```

## Class-диаграмма Domain-слоя Orchestrator

Интерфейсы, Value Objects и исключения Orchestrator Domain.

```mermaid
classDiagram
    direction LR

    class RunAgentServiceInterface {
        <<interface>>
        +run(ChainRunRequestVo, ChainRetryPolicyVo) ChainRunResultVo
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
        +maxContextLength: ?int
    }

    class ChainRunResultVo {
        +outputText: string
        +inputTokens: int
        +outputTokens: int
        +cost: float
        +exitCode: int
        +model: ?string
        +isError: bool
        +cacheReadTokens: int
        +cacheWriteTokens: int
        +turns: int
        +errorMessage: ?string
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

    RunAgentServiceInterface --> ChainRunRequestVo : takes
    RunAgentServiceInterface --> ChainRunResultVo : returns
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
    participant ESC as ExecuteStaticChainService
    participant RSC as RunStaticChainService
    participant STEP as ExecuteStaticStepService
    participant RASI as RunAgentServiceInterface
    participant QG as QualityGateRunnerInterface

    CLI->>H: __invoke(command)
    H->>CL: load(chainName)
    CL-->>H: ChainDefinitionVo (type=static)
    H->>ESC: execute(chain, ...)
    ESC->>RSC: execute(chain, ...)

    loop Для каждого ChainStepVo
        alt step.isAgent()
            RSC->>STEP: execute(step, ...)
            STEP->>RASI: run(ChainRunRequestVo, retryPolicy)
            RASI-->>STEP: ChainRunResultVo
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
    participant CBC as BuildDynamicContextService
    participant DLR as RunDynamicLoopService
    participant EDT as ExecuteDynamicTurnService
    participant DTA as RunDynamicLoopAgentServiceInterface
    participant RASI as RunAgentServiceInterface
    participant FPAR as FacilitatorResponseParser
    participant SL as ChainSessionLogger

    CLI->>H: __invoke(command)
    H->>CL: load(chainName)
    CL-->>H: ChainDefinitionVo (type=dynamic)
    H->>SL: startSession(chainName, topic, ...)
    H->>CBC: buildContext(chain, ...)
    CBC-->>H: DynamicChainContextVo
    H->>DLR: execute(chain, context, ...)

    loop round = 1..maxRounds
        alt Фасилитатор
            DLR->>EDT: runFacilitatorStep(chain, execution, ...)
            EDT->>DTA: runFacilitator(step, round, ...)
            DTA->>RASI: run(ChainRunRequestVo, retryPolicy)
            RASI-->>DTA: ChainRunResultVo
            DTA->>FPAR: parse(outputText)
            FPAR-->>DTA: FacilitatorResponseVo
            DTA-->>EDT: [ChainTurnResultVo, FacilitatorResponseVo]
            EDT-->>DLR: [ChainTurnResultVo, FacilitatorResponseVo]
        else Участник (next_role)
            DLR->>EDT: runParticipantStep(chain, execution, nextRole, ...)
            EDT->>DTA: runParticipant(step, round, role, ...)
            DTA->>RASI: run(ChainRunRequestVo, retryPolicy)
            RASI-->>DTA: ChainTurnResultVo
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

## Sequence: Integration → AgentRunner Application

Детализация вызова через Integration-слой при `RunAgentServiceInterface::run()`.

```mermaid
sequenceDiagram
    autonumber
    participant RSC as RunStaticChainService
    participant RASI as RunAgentServiceInterface
    participant RAS as RunAgentService
    participant ADM as AgentDtoMapper
    participant RACH as RunAgentCommandHandler
    participant REG as AgentRunnerRegistryServiceInterface
    participant FACTORY as RetryableRunnerFactoryInterface
    participant RUNNER as AgentRunnerInterface

    RSC->>RASI: run(ChainRunRequestVo, ChainRetryPolicyVo)
    RASI->>RAS: run(ChainRunRequestVo, ChainRetryPolicyVo)
    RAS->>ADM: mapToRunAgentCommand(ChainRunRequestVo, ChainRetryPolicyVo)
    ADM-->>RAS: RunAgentCommand
    RAS->>RACH: __invoke(RunAgentCommand)

    RACH->>REG: get(runnerName)
    REG-->>RACH: AgentRunnerInterface

    alt retryMaxRetries задан
        RACH->>FACTORY: createRetryableRunner(runner, retryPolicyVo)
        FACTORY-->>RACH: RetryingAgentRunner
        RACH->>RUNNER: [RetryingAgentRunner] run(AgentRunRequestVo)
    else retry не нужен
        RACH->>RUNNER: [AgentRunnerInterface] run(AgentRunRequestVo)
    end

    RUNNER-->>RACH: AgentResultVo
    RACH-->>RAS: RunAgentResultDto
    RAS->>ADM: mapFromRunAgentResultDto(RunAgentResultDto)
    ADM-->>RAS: ChainRunResultVo
    RAS-->>RASI: ChainRunResultVo
    RASI-->>RSC: ChainRunResultVo
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
