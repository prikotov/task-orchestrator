# Архитектура

Библиотека следует Clean Architecture (луковичная архитектура): Domain → Application → Infrastructure/Integration. Presentation — в приложении-хосте (например, `apps/console/` в TasK).

Визуальный обзор слоёв, модулей и взаимодействий — в [Диаграммы](diagrams.md).

## Трёхмодульная структура

Бандл состоит из трёх модулей + модуля движка AI-агента, каждый со своими DDD-слоями:

```
src/Module/
├── AgentRunner/                 # Модуль движка AI-агента
│   ├── Domain/                  # Контракт движка: AgentRunnerInterface, VO, Registry
│   ├── Application/             # Use cases: RunAgentCommandHandler, GetRunners
│   └── Infrastructure/          # Реализации: PiAgentRunner, Retry, Circuit Breaker
├── ChainDefinition/             # Загрузка, валидация, VO определений цепочек
│   ├── Domain/                  # ChainLoaderInterface, ChainDefinitionVo, Enums
│   ├── Application/             # LoadChain, ListChains, ValidateChainConfig, LoadRawChain
│   └── Infrastructure/          # YamlChainLoader
├── ChainExecution/              # Выполнение static + conditional цепочек
│   ├── Domain/                  # ExecutionStrategy, Budget, Hooks, Audit, VO
│   ├── Application/             # OrchestrateChainCommandHandler, Static/Conditional стратегии
│   ├── Integration/             # ChainDefinitionProvider (← ChainDefinition.Application)
│   └── Infrastructure/          # Prompt, Audit, Hooks, Step execution
└── DynamicLoop/                 # Выполнение dynamic-циклов
    ├── Domain/                  # DynamicLoop entity, Session, Budget, Audit, VO
    ├── Application/             # DynamicExecutionStrategy, Round/Session events
    ├── Integration/             # ChainDefinitionProvider, RunAgentService (← ChainExecution.Application)
    └── Infrastructure/          # Session, Audit, Agent runner, Prompt
```

### Правило межмодульного взаимодействия

Модули взаимодействуют **только через Application-слой**: Integration обращается к foreign Application (QueryHandler / CommandHandler), а не к foreign Domain. Domain каждого модуля — чёрный ящик. Подробнее — в [ADR-011: Межмодульное взаимодействие через Application](../adr/011-cross-module-application-api.md).

### Модуль AgentRunner

Отвечает за запуск AI-агента через конкретный CLI-инструмент. Не знает об оркестрации и цепочках.

**Domain-слой:**
- `AgentRunnerInterface` — контракт движка: `run()`, `getName()`, `isAvailable()`
- `AgentRunnerRegistryService` — реестр name → AgentRunnerInterface
- `AgentRunnerRegistryServiceInterface` — интерфейс реестра
- `RetryableRunnerFactoryInterface` — фабрика retryable-обёртки
- VO: `AgentResultVo`, `AgentRunRequestVo`, `AgentTurnResultVo`, `RetryPolicyVo`, `CircuitBreakerStateVo`
- Enum: `CircuitStateEnum` (closed | half_open | open)
- Exception: `AgentException`, `RunnerNotFoundException`, `NotFoundExceptionInterface`

**Application-слой:**
- `RunAgentCommandHandler` — запуск агента с retry, выбор runner по имени из реестра
- `GetRunnersQueryHandler` — список доступных runner'ов
- `GetRunnerByNameQueryHandler` — получение runner по имени
- DTO: `RunAgentCommand`, `RunAgentResultDto`, `GetRunnersQuery`, `GetRunnersResultDto`

**Infrastructure-слой:**
- `PiAgentRunner` — реализация для pi CLI
- `PiJsonlParser` — парсер JSONL-вывода pi
- `RetryingAgentRunner` — обёртка с retry-policy (экспоненциальная задержка)
- `CircuitBreakerAgentRunner` — обёртка Circuit Breaker (closed → open → half_open)
- `RetryableRunnerFactory` — фабрика для создания retrying-обёртки

### Модуль ChainDefinition

Отвечает за загрузку, валидацию и определение цепочек. Не зависит ни от кого — единственный независимый модуль.

**Domain-слой:**
- `ChainLoaderInterface` — контракт загрузки цепочек
- VO: `ChainDefinitionVo`, `SharedChainDefinitionVo`, `ChainStepVo`, и др.
- Enum: `ChainStepTypeEnum`, `ChainTypeEnum`
- Exception: `ChainNotFoundException`, `OrchestratorException`

**Application-слой:**
- Use cases: `LoadChainQueryHandler`, `ListChainsQueryHandler`, `ValidateChainConfigQueryHandler`, `LoadRawChainQueryHandler`
- DTO: Query/Result

**Infrastructure-слой:**
- `YamlChainLoader` — загрузка из YAML-файлов

### Модуль ChainExecution

Отвечает за выполнение static и conditional цепочек. Зависит от ChainDefinition (через Integration) и AgentRunner (через Integration).

**Domain-слой:**
- `RunAgentServiceInterface` — интеграционный интерфейс запуска AI-агента
- `AuditLoggerInterface` — Port для audit-логирования
- `PromptProviderInterface` — Port для системных промптов
- Сервисы: Budget, Hooks, Static, Conditional, Prompt, Audit
- Entity: `StaticChainExecution`
- VO: `ChainRunRequestVo`, `ChainRunResultVo`, `FallbackAttemptVo`, и др.

**Application-слой:**
- Use cases: `OrchestrateChainCommandHandler` (диспетчер стратегий), `RunAgentCommandHandler`, `RunAgentQueryHandler`, `GetPromptFilePathQueryHandler`, `GenerateReportQueryHandler`
- Стратегии: `ExecutionStrategyInterface`, `StaticExecutionStrategy`, `ConditionalExecutionStrategy`
- Сервисы: `ExecuteStaticChainService`, `DynamicExecutionStrategy` (делегирует в DynamicLoop через Integration)
- DTO: команды и результаты

**Integration-слой:**
- `ChainExecutionDefinitionMapper` — загрузка и маппинг определений из ChainDefinition (через `LoadRawChainQueryHandler` — foreign Application)
- `RunAgentService` — реализует `RunAgentServiceInterface`, делегирует в AgentRunner Application
- `StaticAuditService` — реализует Audit-Port

**Infrastructure-слой:**
- `JsonlAuditLogger` — JSONL audit-логгер (реализует `AuditLoggerInterface`)
- `RolePromptBuilder`, `ExecuteConditionalStepService`, `ResolveChainRunnerService`

### Модуль DynamicLoop

Отвечает за выполнение dynamic-циклов (session, round, context, facilitator). Зависит от ChainDefinition (через Integration) и ChainExecution (через Integration → foreign Application).

**Domain-слой:**
- `RunDynamicLoopAgentServiceInterface` — Port запуска агента
- `DynamicLoopAuditLoggerInterface` — Port audit-логирования
- `ChainDefinitionProviderInterface` — Port получения определений цепочек
- Сервисы: Dynamic, Session, Budget, Audit
- Entity: `DynamicLoopExecution`
- VO: `DynamicRoundResultVo`, `DynamicLoopResultVo`, `ChainSessionStateVo`, и др.

**Application-слой:**
- Сервисы: `DynamicExecutionStrategy`, `DispatchRoundEventService`, `DispatchSessionCompletedEventService`

**Integration-слой:**
- `DynamicLoopDefinitionMapper` — загрузка и маппинг определений из ChainDefinition (через `LoadRawChainQueryHandler` — foreign Application)
- `RunDynamicLoopAgentService` — реализует `RunDynamicLoopAgentServiceInterface`, делегирует в `RunAgentQueryHandler` (ChainExecution.Application) и `GetPromptFilePathQueryHandler`

**Infrastructure-слой:**
- `JsonlAuditLogger` — JSONL audit-логгер (реализует `DynamicLoopAuditLoggerInterface`)
- `ChainSessionLogger`, `ChainSessionReader`, `ChainSessionWriter`, `CheckDynamicBudgetService`, `FacilitatorResponseParserService`

## ExecutionStrategy Pattern

Оркестрация использует **Strategy** для разделения поведенческих путей static/dynamic/conditional цепочек. Паттерн введён в ADR-006.

**Как работает:**

1. `OrchestrateChainCommandHandler` — чистый диспетчер. Две зависимости: `ChainDefinitionProviderInterface` + `iterable<ExecutionStrategyInterface>` (tagged iterator).
2. Стратегия определяется через `supports(ChainDefinitionVo): bool` — каждая проверяет тип цепочки.
3. Найденная стратегия выполняет `execute()` или `resume()`.

**Стратегии:**

| Стратегия | Модуль | Тип цепочки | Описание |
|---|---|---|---|
| `StaticExecutionStrategy` | ChainExecution | static | Делегирует в `ExecuteStaticChainServiceInterface`. Resume не поддерживает. |
| `ConditionalExecutionStrategy` | ChainExecution | conditional | Условное ветвление шагов. Resume не поддерживает. |
| `DynamicExecutionStrategy` | DynamicLoop | dynamic | Полный цикл: session, context, loop, finalize, DTO-маппинг, event dispatch. |

**DI-конфигурация:**

Тег `orchestrator.execution_strategy` регистрируется **container-wide** (на уровне всего контейнера) в `Kernel::build()` через `registerForAutoconfiguration()`, а не module-local `_instanceof` (последнее работало только в пределах своего файла и ломалось в PHAR — см. [ADR-012, раздел PHAR-переносимость](../adr/012-module-configuration-convention.md#phar-переносимость-эволюция-auto-discovery-вариант-4)). Поэтому любая реализация `ExecutionStrategyInterface` тегируется автоматически, в каком бы модуле она ни лежала. Tagged iterator (итератор по тегам) для `OrchestrateChainCommandHandler` остаётся явным определением в модульной конфигурации ChainExecution:

```yaml
# src/Module/ChainExecution/Resource/config/services.yaml
services:
    TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommandHandler:
        arguments:
            $strategies: !tagged_iterator orchestrator.execution_strategy
```

`DynamicExecutionStrategy` реализован в модуле DynamicLoop (контракт `ExecutionStrategyInterface` лежит в ChainExecution). С container-wide autoconfiguration он тегируется автоматически. В `DynamicLoop/services.yaml` на нём оставлен явный `tags: ['orchestrator.execution_strategy']` как опциональная перестраховка (explicit-wins):

```yaml
# src/Module/DynamicLoop/Resource/config/services.yaml
services:
    TaskOrchestrator\Common\Module\DynamicLoop\Integration\Service\ChainExecution\DynamicExecutionStrategy:
        tags: ['orchestrator.execution_strategy']
```

Ранее этот явный тег был **обязателен**, потому что `_instanceof` действовал только в пределах своего файла конфигурации и не тегировал cross-module implementation (реализацию в другом модуле). После перехода на container-wide autoconfiguration это ограничение снято.

**Почему Strategy, а не if/switch:** Handler не знает о типах цепочек. Новая стратегия — новый класс + тег, handler не меняется.

## Integration-слой между модулями

Модули связаны через **Integration-слой**, который обращается к чужому **Application** (QueryHandler / CommandHandler), а не к чужому Domain. Модель формализована в [ADR-011](../adr/011-cross-module-application-api.md).

### Правило: Integration → foreign Application

```
Integration-слой модуля A
  → foreign Application (QueryHandler / CommandHandler модуля B)
    → foreign Domain (модуля B)
```

**Integration никогда не обращается к foreign Domain напрямую.**

### Примеры Integration → foreign Application

#### ChainExecution ← ChainDefinition

```
ChainExecution.Integration.ChainExecutionDefinitionMapper
  → ChainDefinition.Application.LoadRawChainQueryHandler     ✓ foreign Application

ChainExecution.Integration.ChainExecutionDefinitionMapper
  → ChainDefinition.Domain.ChainLoaderInterface              ✗ foreign Domain (ЗАПРЕЩЕНО)
```

#### DynamicLoop ← ChainExecution

```
DynamicLoop.Integration.RunDynamicLoopAgentService
  → ChainExecution.Application.RunAgentQueryHandler          ✓ foreign Application

DynamicLoop.Integration.RunDynamicLoopAgentService
  → ChainExecution.Domain.RunAgentServiceInterface           ✗ foreign Domain (ЗАПРЕЩЕНО)
```

### VO-маппинг на границе модулей

Каждый модуль имеет собственные VO. Integration-слой маппит VO при пересечении границы.

**Принцип:** Domain каждого модуля не зависит от Domain других модулей. VO дублированы намеренно — каждый модуль владеет своими типами.

### Deptrac-верификация

`CrossModuleDomainRule` автоматически верифицирует модель:

```bash
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
# → 0 violations = модель соблюдается
```

## Зависимости модулей и слоёв

### Внутримодульные зависимости

| Откуда | Куда | Примечание |
|---|---|---|
| Domain (любой модуль) | — | Только PHP std + `Psr\Log\LoggerInterface` |
| Application | Domain (свой модуль) | Через интерфейсы и VO |
| Integration | Domain (свой модуль, interfaces) | Реализует Port'ы своего Domain |
| Infrastructure | Domain (свой модуль, interfaces) | Реализует Domain-интерфейсы |

### Межмодульные зависимости

| Откуда | Куда | Примечание |
|---|---|---|
| ChainExecution.Integration | ChainDefinition.Application | `LoadRawChainQueryHandler` — загрузка определений |
| DynamicLoop.Integration | ChainDefinition.Application | `LoadRawChainQueryHandler` — загрузка определений |
| DynamicLoop.Integration | ChainExecution.Application | `RunAgentQueryHandler`, `GetPromptFilePathQueryHandler` |
| ChainExecution.Integration | AgentRunner.Application | `RunAgentCommandHandler` — запуск агента |
| DynamicLoop.Integration | AgentRunner.Application | (через ChainExecution) |

**Межмодульное правило:** Integration → foreign Application (QueryHandler/CommandHandler). Запрещено Integration → foreign Domain.

### Внешние зависимости

| Откуда | Куда | Примечание |
|---|---|---|
| Presentation | Application only | Внедряет use case handler'ы напрямую или через Bus |

### Правило: Domain не зависит ни от кого

Все модули следуют принципу: Domain-слой не содержит зависимостей на другие слои или сторонние библиотеки (кроме `Psr\Log\LoggerInterface`).

### Почему CommandHandler для оркестрации

Оркестрация запускает AI-агентов (side effects: выполнение shell-команд, запись файлов, трата токенов).
Поэтому `OrchestrateChainCommandHandler` и `RunAgentCommandHandler` используют Command pattern.
CommandHandler может возвращать DTO — это допустимо для CQRS с side effects.

### Почему QueryHandler для runners и reports

`GetRunnersQueryHandler` и `GenerateReportQueryHandler` — readonly-операции без side effects.
Они используют Query pattern.

## Структура каталогов

### AgentRunner

```
src/Module/AgentRunner/
├── Application/
│   └── UseCase/
│       ├── Command/
│       │   └── RunAgent/
│       │       ├── RunAgentCommand.php                    # DTO команды запуска
│       │       ├── RunAgentCommandHandler.php             # обработчик: выбор runner + retry
│       │       └── RunAgentResultDto.php                  # DTO результата
│       └── Query/
│           ├── GetRunnerByName/
│           │   ├── GetRunnerByNameQuery.php
│           │   └── GetRunnerByNameQueryHandler.php
│           └── GetRunners/
│               ├── GetRunnersQuery.php
│               ├── GetRunnersQueryHandler.php
│               ├── GetRunnersResultDto.php
│               └── RunnerDto.php
├── Domain/
│   ├── Enum/
│   │   └── CircuitStateEnum.php                         # closed | half_open | open
│   ├── Exception/
│   │   ├── AgentException.php                           # базовый exception
│   │   ├── NotFoundExceptionInterface.php               # маркерный интерфейс
│   │   └── RunnerNotFoundException.php
│   ├── Service/
│   │   ├── AgentRunnerInterface.php                     # run(), getName(), isAvailable()
│   │   ├── AgentRunnerRegistryService.php               # name → AgentRunnerInterface
│   │   ├── AgentRunnerRegistryServiceInterface.php
│   │   └── RetryableRunnerFactoryInterface.php          # фабрика retryable-обёртки
│   └── ValueObject/
│       ├── AgentResultVo.php
│       ├── AgentRunRequestVo.php
│       ├── AgentTurnResultVo.php
│       ├── CircuitBreakerStateVo.php
│       └── RetryPolicyVo.php
└── Infrastructure/
    └── Service/
        ├── CircuitBreakerAgentRunner.php                # обёртка Circuit Breaker
        ├── Pi/
        │   ├── PiAgentRunner.php
        │   └── PiJsonlParser.php
        ├── RetryableRunnerFactory.php                   # фабрика RetryingAgentRunner
        └── RetryingAgentRunner.php                      # обёртка с retry-policy
```

### ChainDefinition

```
src/Module/ChainDefinition/
├── Domain/
│   ├── Dto/
│   │   ├── ChainResultAuditDto.php
│   │   └── StepAuditStatusDto.php
│   ├── Enum/
│   │   ├── ChainStepTypeEnum.php                        # agent | quality_gate
│   │   └── ChainTypeEnum.php                            # static | dynamic | conditional
│   ├── Exception/
│   │   ├── ChainConfigViolationVo.php
│   │   ├── ChainNotFoundException.php
│   │   └── OrchestratorException.php
│   ├── Service/
│   │   └── Chain/
│   │       └── ChainLoaderInterface.php                # контракт загрузки цепочек
│   └── ValueObject/
│       ├── BudgetVo.php
│       ├── ChainDefinitionVo.php
│       ├── ChainStepVo.php
│       ├── PromptConfigurationVo.php
│       ├── RoleConfigVo.php
│       ├── SharedChainDefinitionVo.php
│       └── ... (definition VO)
├── Application/
│   └── UseCase/
│       └── Query/Chain/
│           ├── ListChains/
│           ├── LoadChain/
│           ├── LoadRawChain/                           # API для Integration других модулей
│           └── ValidateChainConfig/
└── Infrastructure/
    └── Service/Chain/
        └── YamlChainLoader.php                         # загрузка из YAML
```

### ChainExecution

```
src/Module/ChainExecution/
├── Domain/
│   ├── Entity/
│   │   └── StaticChainExecution.php
│   ├── Exception/
│   │   ├── NotFoundExceptionInterface.php
│   │   └── RoleNotFoundException.php
│   ├── Service/
│   │   ├── Agent/
│   │   │   └── RunAgentServiceInterface.php            # интеграционный Port
│   │   ├── Chain/Audit/
│   │   │   ├── AuditLoggerInterface.php
│   │   │   └── AuditLoggerFactoryInterface.php
│   │   ├── Hook/
│   │   │   └── HookExecutorInterface.php
│   │   ├── Prompt/
│   │   │   └── PromptProviderInterface.php
│   │   ├── Static/
│   │   │   ├── CheckStaticBudgetServiceInterface.php
│   │   │   ├── ExecuteStaticStepService.php
│   │   │   ├── FormatPromptServiceInterface.php
│   │   │   ├── ResolveChainRunnerServiceInterface.php
│   │   │   └── RunStaticChainService.php
│   │   └── Integration/
│   │       └── ChainDefinitionProviderInterface.php    # Port загрузки определений
│   └── ValueObject/
│       ├── ChainRunRequestVo.php
│       ├── ChainRunResultVo.php
│       ├── ExecutionRetryPolicyVo.php
│       ├── HookResultVo.php
│       └── ... (execution VO)
├── Application/
│   ├── Service/Chain/
│   │   ├── ConditionalExecutionStrategy.php
│   │   ├── ExecuteStaticChainService.php
│   │   ├── ExecutionStrategyInterface.php
│   │   └── StaticExecutionStrategy.php
│   └── UseCase/
│       ├── Command/
│       │   ├── OrchestrateChain/                       # диспетчер стратегий
│       │   └── RunAgent/                                # запуск агента с промптом
│       └── Query/
│           ├── Agent/RunAgent/                          # API для DynamicLoop Integration
│           ├── Prompt/GetPromptFilePath/                # API для DynamicLoop Integration
│           └── GenerateReport/
├── Integration/
│   └── Service/
│       ├── AgentRunner/
│       │   ├── RunAgentService.php                      # → AgentRunner.Application
│       │   └── AgentDtoMapper.php
│       ├── Audit/
│       │   └── StaticAuditService.php
│       └── ChainDefinition/
│           └── ChainExecutionDefinitionMapper.php      # → ChainDefinition.Application
└── Infrastructure/
    └── Service/
        ├── Audit/
        │   └── JsonlAuditLogger.php                    # implements AuditLoggerInterface
        ├── Agent/
        │   └── ResolveChainRunnerService.php
        ├── Chain/
        │   └── ExecuteConditionalStepService.php
        └── Prompt/
            └── RolePromptBuilder.php
```

### DynamicLoop

```
src/Module/DynamicLoop/
├── Domain/
│   ├── Dto/
│   │   └── DynamicLoopAuditDto.php
│   ├── Entity/
│   │   └── DynamicLoopExecution.php
│   ├── Service/
│   │   ├── Audit/
│   │   │   └── DynamicLoopAuditLoggerInterface.php    # свой Port
│   │   ├── Budget/
│   │   │   └── CheckDynamicBudgetServiceInterface.php
│   │   ├── Dynamic/
│   │   │   ├── BuildDynamicContextServiceInterface.php
│   │   │   ├── RunDynamicLoopAgentServiceInterface.php
│   │   │   └── RunDynamicLoopServiceInterface.php
│   │   ├── Session/
│   │   │   ├── ChainSessionLoggerInterface.php
│   │   │   ├── ChainSessionReaderInterface.php
│   │   │   └── ChainSessionWriterInterface.php
│   │   └── Integration/
│   │       └── ChainDefinitionProviderInterface.php    # Port загрузки определений
│   └── ValueObject/
│       ├── DynamicLoopResultVo.php
│       ├── DynamicRoundResultVo.php
│       ├── ChainSessionStateVo.php
│       └── ... (dynamic VO)
├── Application/
│   └── Service/
│       ├── DispatchRoundEventService.php
│       ├── DispatchSessionCompletedEventService.php
│       └── DynamicExecutionStrategy.php
├── Integration/
│   └── Service/
│       ├── ChainDefinition/
│       │   └── DynamicLoopDefinitionMapper.php         # → ChainDefinition.Application
│       └── ChainExecution/
│           └── RunDynamicLoopAgentService.php          # → ChainExecution.Application
└── Infrastructure/
    └── Service/
        ├── ChainSessionLogger.php
        ├── ChainSessionReader.php
        ├── ChainSessionWriter.php
        ├── ChainSessionFileStorage.php
        ├── CheckDynamicBudgetService.php
        ├── FacilitatorResponseParserService.php
        ├── JsonlAuditLogger.php                       # implements DynamicLoopAuditLoggerInterface
        └── JsonlAuditLoggerFactory.php
```

### DI Infrastructure

```
src/
├── Kernel.php                                       # Symfony Kernel: BaseKernel + MicroKernelTrait + ModuleKernelTrait
├── Component/
│   ├── ModuleSystem/                                # ModuleInterface, ModuleServiceRegistrar, ModuleCompilerPass, ModuleKernelTrait
│   └── Clock/                                        # SystemClock (PSR-20 Psr\Clock\ClockInterface)
config/
├── bundles.php                                      # Реестр bundles (FrameworkBundle, TwigBundle, MonologBundle)
├── modules.php                                      # Реестр доменных модулей: GitIdentity, AgentRunner, ChainDefinition, ChainExecution, DynamicLoop
├── packages/                                        # Конфигурация bundles (framework, twig, translation, monolog)
├── services.yaml                                    # Общие компоненты + alias Psr\Clock\ClockInterface + импорт console_services.yaml
└── console_services.yaml                             # Presentation-слой apps/console (команды, EventDispatcher, Lock)

src/Module/<Name>/
├── <Name>Module.php                                 # ModuleInterface: пути модуля и Resource/config
└── Resource/config/services.yaml                     # DI-конфигурация конкретного модуля
```

Контейнер собирается `Kernel` через `MicroKernelTrait` (`config/packages/*` + `config/services.yaml`) и
`ModuleKernelTrait`. `config/modules.php` содержит 5 модулей: `GitIdentity`, `AgentRunner`,
`ChainDefinition`, `ChainExecution`, `DynamicLoop`. Для каждого модуля `ModuleKernelTrait`
регистрирует `ModuleCompilerPass`, который подгружает `Resource/config/services.yaml` модуля, а
затем запускает `ModuleServiceRegistrar` — PHAR-safe auto-discovery сервисов через
`RecursiveDirectoryIterator` (подробности ниже).

Общий `config/services.yaml` остаётся тонким: импортирует `console_services.yaml`, объявляет общий alias `Psr\Clock\ClockInterface` → `SystemClock` (сам `SystemClock` — единственный concrete-сервис `src/Component/` — задан явным определением). Алиасы интерфейсов, scalar arguments (скалярные аргументы), tagged iterators (итераторы по тегам) конкретных модулей живут в модульных `src/Module/<Name>/Resource/config/services.yaml`. Никаких операторов `resource:`/`exclude:` в корневых YAML не осталось — все они ломались в PHAR через `GlobResource` (см. ниже) и заменены на PHAR-safe регистрацию.

Package root (каталог пакета с `src/`, `apps/`, `config/`) разрешается через `Kernel::getPackageDir() = dirname(__DIR__)` — CWD-независимо (наследуемый `getProjectDir()` в PHAR даёт неверный `phar://.../src`, т.к. `composer.json` не упакован в PHAR). `getPackageDir()` используется для `config/modules.php`, параметра `task_orchestrator.package_dir` и базовых путей PHAR-safe регистрации.

### PHAR-safe регистрация сервисов модуля

Auto-discovery классов модуля выполняется **не** оператором Symfony `resource:`/`exclude:`, а
программно через `ModuleServiceRegistrar` (`src/Component/ModuleSystem/DependencyInjection/`).
Причина: `GlobResource` (механизм `resource:`) возвращает 0 файлов по путям `phar://` и
молча опустошал DI-контейнер собранного PHAR. Полное обоснование и рассмотренные альтернативы —
в [ADR-012, раздел PHAR-переносимость](../adr/012-module-configuration-convention.md#phar-переносимость-эволюция-auto-discovery-вариант-4).

Как это работает:

- **Конфигурация из модуля.** `ModuleServiceRegistrar` берёт namespace и exclude-пути из контракта
  модуля — `ModuleInterface::getServiceNamespace()` и `getServiceExcludePaths()` (базовый набор —
  константа `DEFAULT_SERVICE_EXCLUDE_PATHS`). Сами операторы `resource:`/`exclude:` в модульных
  `services.yaml` отсутствуют.
- **Явные определения побеждают (explicit-wins).** Регистратор запускается **после** загрузки
  `services.yaml`, поэтому alias'ы, scalar-аргументы, tagged iterators и service maps из YAML
  имеют приоритет: регистратор не перетирает уже заданный `Definition`/`Alias`.
- **Container-wide autoconfiguration вместо `_instanceof`.** Теги интерфейсов (`agent.runner`,
  `orchestrator.execution_strategy`, `chain_execution.step_runner`) регистрируются в `Kernel::build()`
  через `registerForAutoconfiguration()` и применяются ко всем `autoconfigured`-сервисам независимо
  от модуля и способа регистрации. Module-local `_instanceof` больше не используется.
- **За пределами доменных модулей.** Тот же `ModuleServiceRegistrar` (generic: параметр `serviceDir`, опция `public`) применяется в `Kernel::registerConsoleServices()` для регистрации команд и подписчиков `apps/console/src/Module/*/Command|EventSubscriber/` (теги `console.command`/`kernel.event_subscriber` добавляются container-wide autoconfig Symfony).

Параметры `task_orchestrator.*` задаются в `Kernel::getKernelParameters()` на самом раннем этапе.
Модульные файлы используют собственные параметры `module.<name>.*`, при необходимости ссылаясь на
`task_orchestrator.*` как на источник. Параметр `base_path`/`roles_dir`/`chains_yaml` — с
dual-context resolution (разрешение путей для двух контекстов: standalone и vendor-binary).

### Presentation-слой (в приложении-хосте)

```
# Пример: apps/console/ в TasK
apps/console/src/Module/Agent/
├── AgentModule.php
├── Command/
│   ├── OrchestrateCommand.php
│   ├── RunCommand.php
│   └── RunnersCommand.php
├── EventSubscriber/
│   └── OrchestrateEventSubscriber.php                  # обработка событий раунда
└── Resource/config/services.yaml

apps/console/config/agent_chains.yaml
```

## Конфигурация

Инструмент настраивается через параметры, которые передаются при запуске (`bin/task-orchestrator`).

### Параметры

| Параметр | Описание |
|---|---|
| `roles_dir` | Путь к role prompt файлам (`.md`) |
| `chains_yaml` | Путь к YAML-конфигурации цепочек |
| `chains_session_dir` | Путь к каталогу сессий оркестрации |
| `base_path` | Корень проекта для path relativization |

### Значения по умолчанию

```yaml
task_orchestrator:
    roles_dir: '<package_root>/docs/agents/roles/team'
    chains_yaml: '<package_root>/config/chains.yaml'
    chains_session_dir: '<package_root>/var/sessions'
    base_path: '<package_root>'
```

## Мультидвижковая архитектура

- `AgentRunnerInterface` — контракт движка: `run()`, `getName()`, `isAvailable()`
- `AgentRunnerRegistryService` — реестр name → AgentRunnerInterface
- `PiAgentRunner` — реализация для pi CLI
- `RetryingAgentRunner` — обёртка с retry-policy (экспоненциальная задержка)
- `CircuitBreakerAgentRunner` — обёртка Circuit Breaker (closed → open → half_open)
- `RetryableRunnerFactory` — фабрика для создания retrying-обёртки
- Новый движок: создать класс в модуле `AgentRunner`, реализующий `AgentRunnerInterface`. Тег
  `agent.runner` ставится автоматически через container-wide autoconfiguration (`Kernel::build()` →
  `registerForAutoconfiguration()`) — единообразно для классов модуля `AgentRunner` и любых
  реализаций в других модулях; общий `config/services.yaml` для этого не изменяется. Module-local
  `_instanceof` больше не используется (см. [ADR-012, раздел PHAR-переносимость](../adr/012-module-configuration-convention.md#phar-переносимость-эволюция-auto-discovery-вариант-4)).

Подробнее о retry и circuit breaker — в [Надёжность](reliability.md).

## Deptrac

Архитектурные правила зависимостей между слоями верифицируются через [Deptrac](https://github.com/qossmic/deptrac). Конфигурация — `depfile.yaml`. Результат: **0 violations**.

Deptrac гарантирует, что Domain не зависит от Application/Infrastructure, Integration не нарушает границы, и слои соблюдаются.
