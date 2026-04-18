# Диаграммы

Mermaid-диаграммы Orchestrator. Рендерятся нативно в GitHub markdown preview.

## Component-диаграмма слоёв

Обзор DDD-слоёв и их связи. Сплошные стрелки — прямые зависимости, пунктирные — реализация интерфейса.

```mermaid
graph TB
    subgraph Presentation["Presentation (host app)"]
        OC["OrchestrateCommand"]
        RC["RunCommand"]
        RNC["RunnersCommand"]
    end

    subgraph Application["Application"]
        CH["OrchestrateChainCommandHandler"]
        SCH["RunAgentCommandHandler"]
        GRH["GetRunnersQueryHandler"]
        RP["GenerateReportQueryHandler"]
        ESC["ExecuteStaticChainService"]
    end

    subgraph Domain["Domain"]
        direction TB
        CL["ChainLoaderInterface"]
        AR["AgentRunnerInterface"]
        REG["AgentRunnerRegistryServiceInterface"]
        PP["PromptProviderInterface"]
        QGI["QualityGateRunnerInterface"]
        RSC["RunStaticChainService"]
        RDLI["RunDynamicLoopServiceInterface"]
        VO["Value Objects"]
    end

    subgraph Infrastructure["Infrastructure"]
        YCL["YamlChainLoader"]
        PI["PiAgentRunner"]
        RET["RetryingAgentRunner"]
        CB["CircuitBreakerAgentRunner"]
        QG["QualityGateRunner"]
        RPB["RolePromptBuilder"]
        RDLS["RunDynamicLoopService"]
    end

    OC -->|uses| CH
    RC -->|uses| SCH
    RNC -->|uses| GRH
    CH --> ESC
    CH --> CL
    CH --> REG
    CH --> RDLI
    ESC --> RSC
    RSC --> AR
    RSC --> QGI
    REG -.->|resolves| AR
    AR -.->|impl| PI
    AR -.->|impl| RET
    AR -.->|impl| CB
    CL -.->|impl| YCL
    PP -.->|impl| RPB
    QGI -.->|impl| QG
    RDLI -.->|impl| RDLS

    style Presentation fill:#e3f2fd,stroke:#1565c0
    style Application fill:#fff3e0,stroke:#e65100
    style Domain fill:#e8f5e9,stroke:#2e7d32
    style Infrastructure fill:#fce4ec,stroke:#c62828
```

## Class-диаграмма Domain-слоя

Интерфейсы, Value Objects и исключения Domain-слоя.

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
    class RunnerNotFoundException {
        +__construct(string runnerName)
    }

    AgentRunnerInterface --> AgentRunRequestVo : takes
    AgentRunnerInterface --> AgentResultVo : returns
    AgentRunnerRegistryServiceInterface --> AgentRunnerInterface : manages
    ChainLoaderInterface --> ChainDefinitionVo : loads
    ChainDefinitionVo --> ChainStepVo : contains

    note for ChainNotFoundException "extends AgentException\nimplements NotFoundExceptionInterface"
    note for RoleNotFoundException "extends AgentException\nimplements NotFoundExceptionInterface"
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
    participant REG as AgentRunnerRegistryService
    participant ESC as ExecuteStaticChainService
    participant RSC as RunStaticChainService
    participant STEP as ExecuteStaticStepService
    participant RUN as AgentRunnerInterface
    participant QG as QualityGateRunnerInterface

    CLI->>H: __invoke(command)
    H->>CL: load(chainName)
    CL-->>H: ChainDefinitionVo (type=static)
    H->>REG: get(runnerName)
    REG-->>H: AgentRunnerInterface
    H->>ESC: execute(chain, runner, ...)
    ESC->>RSC: execute(chain, runner, ...)

    loop Для каждого ChainStepVo
        alt step.isAgent()
            RSC->>STEP: execute(step, runner, previousContext)
            STEP->>RUN: run(AgentRunRequestVo)
            RUN-->>STEP: AgentResultVo
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
    participant REG as AgentRunnerRegistryService
    participant CBC as BuildDynamicContextService
    participant DLR as RunDynamicLoopService
    participant EDT as ExecuteDynamicTurnService
    participant DTA as RunDynamicLoopAgentService
    participant RUN as AgentRunnerInterface
    participant FPAR as FacilitatorResponseParser
    participant SL as ChainSessionLogger

    CLI->>H: __invoke(command)
    H->>CL: load(chainName)
    CL-->>H: ChainDefinitionVo (type=dynamic)
    H->>SL: startSession(chainName, topic, ...)
    H->>CBC: buildContext(chain, ...)
    CBC-->>H: DynamicChainContextVo
    H->>REG: get(runnerName)
    REG-->>H: AgentRunnerInterface
    H->>DLR: execute(chain, runner, context, ...)

    loop round = 1..maxRounds
        alt Фасилитатор
            DLR->>EDT: runFacilitatorStep(chain, runner, execution, ...)
            EDT->>DTA: runFacilitator(step, round, runner, ...)
            DTA->>RUN: run(AgentRunRequestVo)
            RUN-->>DTA: AgentResultVo
            DTA->>FPAR: parse(outputText)
            FPAR-->>DTA: FacilitatorResponseVo
            DTA-->>EDT: [AgentTurnResultVo, FacilitatorResponseVo]
            EDT-->>DLR: [AgentTurnResultVo, FacilitatorResponseVo]
        else Участник (next_role)
            DLR->>EDT: runParticipantStep(chain, runner, execution, nextRole, ...)
            EDT->>DTA: runParticipant(step, round, runner, role, ...)
            DTA->>RUN: run(AgentRunRequestVo)
            RUN-->>DTA: AgentTurnResultVo
            DTA-->>EDT: AgentTurnResultVo
            EDT-->>DLR: AgentTurnResultVo
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
