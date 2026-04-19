# Архитектура

Библиотека следует Clean Architecture (луковичная архитектура): Domain → Application → Infrastructure/Integration. Presentation — в приложении-хосте (например, `apps/console/` в TasK).

Визуальный обзор слоёв, модулей и взаимодействий — в [Диаграммы](diagrams.md).

## Двухмодульная структура

Бандл состоит из двух модулей, каждый со своими DDD-слоями:

```
src/Module/
├── AgentRunner/                 # Модуль движка AI-агента
│   ├── Domain/                  # Контракт движка: AgentRunnerInterface, VO, Registry
│   ├── Application/             # Use cases: RunAgentCommandHandler, GetRunners
│   └── Infrastructure/          # Реализации: PiAgentRunner, Retry, Circuit Breaker
└── Orchestrator/                # Модуль оркестрации цепочек
    ├── Domain/                  # Бизнес-логика: Chain, Budget, Dynamic, Static
    ├── Application/             # Use cases, DTO, мапперы
    ├── Integration/             # ACL к AgentRunner: RunAgentService, AgentDtoMapper
    └── Infrastructure/          # YAML-загрузка, JSONL-лог, Session, Prompt
```

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

### Модуль Orchestrator

Отвечает за оркестрацию цепочек агентов (static/dynamic). Не зависит от конкретного движка — общается с AgentRunner через Integration-слой (ACL).

**Domain-слой:**
- `RunAgentServiceInterface` — интеграционный интерфейс запуска AI-агента (инкапсулирует retry)
- Сервисы Chain: Audit, Dynamic, Session, Shared, Static, Budget, Prompt
- Entity: `DynamicLoopExecution`, `StaticChainExecution`
- VO: `ChainRunRequestVo`, `ChainRunResultVo`, `ChainRetryPolicyVo`, `ChainTurnResultVo`, `ChainDefinitionVo`, и др.
- Enum: `ChainStepTypeEnum`, `ChainTypeEnum`
- Exception: `OrchestratorException`, `ChainNotFoundException`, `RoleNotFoundException`

**Application-слой:**
- Use cases: `OrchestrateChainCommandHandler`, `RunAgentCommandHandler`, `GetRunnersQueryHandler`, `GenerateReportQueryHandler`
- DTO: команды и результаты
- Service: `ExecuteStaticChainService`, `DispatchRoundEventService`
- Mapper: `ReportFormatMapperInterface`, `ReportJsonMapper`, `ReportTextMapper`

**Integration-слой (ACL к AgentRunner):**
- `RunAgentService` — реализует `RunAgentServiceInterface`, делегирует в AgentRunner Application
- `AgentDtoMapper` — маппер VO между Orchestrator Domain и AgentRunner Application DTO

**Infrastructure-слой:**
- Сервисы Chain: `YamlChainLoader`, `ChainSessionLogger`, `QualityGateRunner`, и др.

## Integration-слой между модулями

Модули связаны через **Integration-слой** Orchestrator (Clean Architecture). Orchestrator Domain определяет интерфейс `RunAgentServiceInterface` в `Domain/Service/Integration/`. Integration-слой реализует `RunAgentService`, который делегирует в AgentRunner Application.

```
Orchestrator Domain                     Integration Layer                     AgentRunner Application
─────────────────────                   ─────────────────────                 ────────────────────────
RunAgentServiceInterface  ←──────────   RunAgentService       ───────────>   RunAgentCommandHandler
(ChainRunRequestVo, ChainRunResultVo)   AgentDtoMapper         ───────────>   (RunAgentCommand, RunAgentResultDto)
```

### VO-маппинг на границе модулей

Каждый модуль имеет собственные VO. Маппинг выполняется в `AgentDtoMapper` (Integration):

| Orchestrator VO (Domain)          | AgentRunner Application DTO  |
|-----------------------------------|------------------------------|
| `ChainRunRequestVo`               | `RunAgentCommand`            |
| `ChainRunResultVo`                | `RunAgentResultDto`          |
| `ChainRetryPolicyVo`              | `RunAgentCommand` (поля retry) |

**Принцип:** Orchestrator Domain не зависит от AgentRunner. VO дублированы намеренно — каждый модуль владеет своими типами.

## Зависимости модулей и слоёв

| Откуда | Куда | Примечание |
|---|---|---|
| **Orchestrator** Domain | — | Только PHP std + `Psr\Log\LoggerInterface` |
| **Orchestrator** Application | **Orchestrator** Domain | Через интерфейсы и VOs |
| **Orchestrator** Integration | **Orchestrator** Domain (interfaces) | Реализует `RunAgentServiceInterface` |
| **Orchestrator** Integration | **AgentRunner** Application | Делегирует в `RunAgentCommandHandler` |
| **Orchestrator** Infrastructure | **Orchestrator** Domain (interfaces) | Реализует Domain-интерфейсы |
| **AgentRunner** Domain | — | Только PHP std + `Psr\Log\LoggerInterface` |
| **AgentRunner** Application | **AgentRunner** Domain | Через интерфейсы и VOs |
| **AgentRunner** Infrastructure | **AgentRunner** Domain (interfaces) | Реализует AgentRunnerInterface |
| Presentation | Application only | Внедряет use case handler'ы напрямую или через Bus |

### Правило: Domain не зависит ни от кого

Оба модуля следуют принципу: Domain-слой не содержит зависимостей на другие слои или сторонние библиотеки (кроме `Psr\Log\LoggerInterface`).

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

### Orchestrator

```
src/Module/Orchestrator/
├── Domain/
│   ├── Dto/
│   │   ├── ChainResultAuditDto.php                      # параметры logChainResult
│   │   └── StepAuditStatusDto.php                       # isError-статус шага
│   ├── Entity/
│   │   ├── DynamicLoopExecution.php                     # in-memory сущность dynamic-цикла
│   │   └── StaticChainExecution.php                     # in-memory сущность static-цепочки
│   ├── Enum/
│   │   ├── ChainStepTypeEnum.php                        # agent | quality_gate
│   │   └── ChainTypeEnum.php                            # static | dynamic
│   ├── Exception/
│   │   ├── ChainNotFoundException.php
│   │   ├── NotFoundExceptionInterface.php               # маркерный интерфейс
│   │   ├── OrchestratorException.php                    # базовый exception модуля
│   │   └── RoleNotFoundException.php
│   ├── Service/
│   │   ├── Budget/
│   │   │   └── CheckDynamicBudgetServiceInterface.php
│   │   ├── Chain/
│   │   │   ├── Audit/
│   │   │   │   ├── AuditLoggerInterface.php
│   │   │   │   └── AuditLoggerFactoryInterface.php
│   │   │   ├── Dynamic/
│   │   │   │   ├── BuildDynamicContextServiceInterface.php
│   │   │   │   ├── BuildDynamicContextService.php
│   │   │   │   ├── ExecuteDynamicTurnService.php
│   │   │   │   ├── FormatDynamicJournalServiceInterface.php
│   │   │   │   ├── FormatDynamicJournalService.php
│   │   │   │   ├── RecordDynamicRoundServiceInterface.php
│   │   │   │   ├── RecordDynamicRoundService.php
│   │   │   │   ├── RunDynamicLoopAgentServiceInterface.php
│   │   │   │   ├── RunDynamicLoopServiceInterface.php
│   │   │   │   └── RunDynamicLoopService.php
│   │   │   ├── Session/
│   │   │   │   ├── ChainSessionLoggerInterface.php
│   │   │   │   ├── ChainSessionReaderInterface.php
│   │   │   │   └── ChainSessionWriterInterface.php
│   │   │   ├── Shared/
│   │   │   │   ├── ChainLoaderInterface.php
│   │   │   │   ├── FacilitatorResponseParserInterface.php
│   │   │   │   ├── PromptFormatterInterface.php
│   │   │   │   ├── QualityGateRunnerInterface.php
│   │   │   │   ├── ResolveChainRunnerServiceInterface.php
│   │   │   │   └── RoundCompletedNotifierInterface.php
│   │   │   └── Static/
│   │   │       ├── CheckStaticBudgetServiceInterface.php
│   │   │       ├── CheckStaticBudgetService.php
│   │   │       ├── ExecuteStaticStepService.php
│   │   │       └── RunStaticChainService.php
│   │   ├── Integration/
│   │   │   └── RunAgentServiceInterface.php              # интеграционный интерфейс
│   │   └── Prompt/
│   │       └── PromptProviderInterface.php
│   └── ValueObject/
│       ├── BudgetVo.php
│       ├── ChainDefinitionVo.php
│       ├── ChainRetryPolicyVo.php
│       ├── ChainRunRequestVo.php
│       ├── ChainRunResultVo.php
│       ├── ChainSessionStateVo.php
│       ├── ChainStepVo.php
│       ├── ChainTurnResultVo.php
│       ├── DynamicBudgetCheckVo.php
│       ├── DynamicChainContextVo.php
│       ├── DynamicLoopResultVo.php
│       ├── DynamicRoundResultVo.php
│       ├── DynamicTurnResultVo.php
│       ├── FacilitatorResponseVo.php
│       ├── FacilitatorTurnResultVo.php
│       ├── FallbackAttemptVo.php
│       ├── FallbackConfigVo.php
│       ├── FixIterationGroupVo.php
│       ├── QualityGateResultVo.php
│       ├── QualityGateVo.php
│       ├── RoleConfigVo.php
│       ├── StaticChainResultVo.php
│       ├── StaticProcessResultVo.php
│       └── StaticStepResultVo.php
├── Application/
│   ├── Enum/
│   │   └── ReportFormatEnum.php                         # text | json
│   ├── Event/OrchestrateChain/
│   │   ├── OrchestrateRoundCompletedEvent.php
│   │   └── OrchestrateSessionCompletedEvent.php
│   ├── Mapper/
│   │   ├── ReportFormatMapperInterface.php
│   │   ├── ReportJsonMapper.php
│   │   └── ReportTextMapper.php
│   ├── Service/Chain/
│   │   ├── ExecuteStaticChainService.php
│   │   ├── ExecuteStaticChainServiceInterface.php
│   │   └── DispatchRoundEventService.php
│   └── UseCase/
│       ├── Command/
│       │   ├── OrchestrateChain/
│       │   │   ├── DynamicLoopResultDto.php
│       │   │   ├── DynamicRoundResultDto.php
│       │   │   ├── FacilitatorTurnResultDto.php
│       │   │   ├── OrchestrateChainCommand.php
│       │   │   ├── OrchestrateChainCommandHandler.php
│       │   │   ├── OrchestrateChainResultDto.php
│       │   │   └── StepResultDto.php
│       │   └── RunAgent/
│       │       ├── RunAgentCommand.php
│       │       ├── RunAgentCommandHandler.php
│       │       └── RunAgentResultDto.php
│       └── Query/
│           ├── GenerateReport/
│           │   ├── GenerateReportQuery.php
│           │   ├── GenerateReportQueryHandler.php
│           │   ├── GenerateReportResultDto.php
│           │   └── ReportResultFactory.php
│           └── GetRunners/
│               ├── GetRunnersQuery.php
│               ├── GetRunnersQueryHandler.php
│               └── RunnerDto.php
├── Integration/
│   └── Service/
│       └── AgentRunner/
│           ├── RunAgentService.php                       # реализует RunAgentServiceInterface
│           └── AgentDtoMapper.php                        # маппер VO Orchestrator ↔ AgentRunner DTO
└── Infrastructure/
    └── Service/
        ├── Chain/
        │   ├── ChainSessionLogger.php
        │   ├── CheckDynamicBudgetService.php
        │   ├── FacilitatorResponseParserService.php
        │   ├── JsonlAuditLogger.php
        │   ├── JsonlAuditLoggerFactory.php
        │   ├── PromptFormatterService.php
        │   ├── QualityGateRunner.php
        │   ├── ResolveChainRunnerService.php
        │   ├── RunDynamicLoopAgentService.php
        │   └── YamlChainLoader.php
        └── Prompt/
            └── RolePromptBuilder.php
```

### Bundle Infrastructure

```
src/
├── DependencyInjection/
│   ├── TaskOrchestratorExtension.php                    # Extension для параметров bundle
│   └── Configuration.php                               # TreeBuilder-валидация
├── Infrastructure/Symfony/
│   └── TaskOrchestratorBundle.php                       # Symfony Bundle
config/
└── services.yaml                                       # DI-конфигурация
```

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

## Symfony Bundle

Интеграция в проект осуществляется через `TaskOrchestratorBundle`:

```php
// apps/console/config/bundles.php
return [
    // ...
    \TaskOrchestrator\Common\Infrastructure\Symfony\TaskOrchestratorBundle::class => ['all' => true],
];
```

### Bundle-параметры

| Параметр | Описание |
|---|---|
| `%task_orchestrator.roles_dir%` | Путь к role prompt файлам |
| `%task_orchestrator.chains_yaml%` | Путь к YAML-конфигурации цепочек |
| `%task_orchestrator.audit_log_path%` | Путь к JSONL audit log |
| `%task_orchestrator.chains_session_dir%` | Путь к каталогу сессий оркестрации |
| `%task_orchestrator.base_path%` | Корень проекта для path relativization |

### Конфигурация

```yaml
# config/packages/task_orchestrator.yaml
task_orchestrator:
    roles_dir: '%kernel.project_dir%/docs/agents/roles/team'
    chains_yaml: '%kernel.project_dir%/apps/console/config/agent_chains.yaml'
    audit_log_path: '%kernel.project_dir%/var/log/agent_audit.jsonl'
    chains_session_dir: '%kernel.project_dir%/var/agent/chains'
    base_path: '%kernel.project_dir%'
```

## Мультидвижковая архитектура

- `AgentRunnerInterface` — контракт движка: `run()`, `getName()`, `isAvailable()`
- `AgentRunnerRegistryService` — реестр name → AgentRunnerInterface
- `PiAgentRunner` — реализация для pi CLI
- `RetryingAgentRunner` — обёртка с retry-policy (экспоненциальная задержка)
- `CircuitBreakerAgentRunner` — обёртка Circuit Breaker (closed → open → half_open)
- `RetryableRunnerFactory` — фабрика для создания retrying-обёртки
- Новый движок: создать класс, реализующий `AgentRunnerInterface`, зарегистрировать в `config/services.yaml` с тегом `agent.runner`

Подробнее о retry и circuit breaker — в [Надёжность](reliability.md).
