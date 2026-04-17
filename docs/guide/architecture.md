# Архитектура

Библиотека следует DDD-слоям: Domain → Application → Infrastructure. Presentation — в приложении-хосте (например, `apps/console/` в TasK).

Визуальный обзор слоёв и взаимодействий — в [Диаграммы](diagrams.md).

## Структура модуля

```
packages/TaskOrchestrator/src/
├── Domain/
│   ├── Dto/
│   │   ├── ChainResultAuditDto.php                      # параметры logChainResult (stepsCount, totalCost, …)
│   │   └── StepAuditStatusDto.php                        # isError-статус шага для chain_result-записи
│   ├── Enum/
│   │   ├── ChainStepTypeEnum.php                         # agent | quality_gate
│   │   ├── ChainTypeEnum.php                             # static | dynamic
│   │   └── CircuitStateEnum.php                          # closed | half_open | open
│   ├── Exception/
│   │   ├── AgentException.php                            # базовый exception библиотеки
│   │   ├── ChainNotFoundException.php
│   │   ├── NotFoundExceptionInterface.php                # маркерный интерфейс
│   │   ├── RoleNotFoundException.php
│   │   └── RunnerNotFoundException.php
│   ├── Service/
│   │   ├── AgentRunner/
│   │   │   ├── AgentRunnerInterface.php                  # run(), getName(), isAvailable()
│   │   │   ├── AgentRunnerRegistryService.php            # name → AgentRunnerInterface
│   │   │   ├── AgentRunnerRegistryServiceInterface.php
│   │   │   └── RetryableRunnerFactoryInterface.php       # фабрика retryable-обёртки
│   │   ├── Budget/
│   │   │   └── CheckDynamicBudgetServiceInterface.php
│   │   ├── Chain/
│   │   │   ├── AuditLoggerInterface.php                # logChainStart/StepStart/StepResult/ChainResult
│   │   │   ├── AuditLoggerFactoryInterface.php         # create(filePath): AuditLoggerInterface
│   │   │   ├── BuildDynamicContextServiceInterface.php  # сбор контекста dynamic-раунда
│   │   │   ├── BuildDynamicContextService.php
│   │   │   ├── ChainLoaderInterface.php
│   │   │   ├── ChainSessionLoggerInterface.php         # логирование сессии (JSONL)
│   │   │   ├── ChainSessionReaderInterface.php         # чтение сессии (resume)
│   │   │   ├── ChainSessionWriterInterface.php         # запись сессии
│   │   │   ├── CheckStaticBudgetServiceInterface.php   # проверка бюджета static-цепочки
│   │   │   ├── CheckStaticBudgetService.php
│   │   │   ├── ExecuteDynamicTurnService.php           # выполнение одного хода dynamic-цепочки
│   │   │   ├── ExecuteStaticStepService.php            # выполнение одного agent-шага
│   │   │   ├── FacilitatorResponseParserInterface.php  # парсинг ответа фасилитатора
│   │   │   ├── FormatDynamicJournalServiceInterface.php
│   │   │   ├── FormatDynamicJournalService.php
│   │   │   ├── PromptFormatterInterface.php            # форматирование промпта шага
│   │   │   ├── QualityGateRunnerInterface.php          # run(QualityGateVo): QualityGateResultVo
│   │   │   ├── RecordDynamicRoundServiceInterface.php  # запись раунда dynamic-цепочки
│   │   │   ├── RecordDynamicRoundService.php
│   │   │   ├── ResolveChainRunnerServiceInterface.php  # резолв runner для шага (fallback)
│   │   │   ├── RoundCompletedNotifierInterface.php     # уведомление о завершении раунда
│   │   │   ├── RunDynamicLoopServiceInterface.php      # цикл dynamic-цепочки
│   │   │   ├── RunDynamicLoopService.php
│   │   │   ├── RunDynamicLoopAgentServiceInterface.php  # интерфейс запуска агента в dynamic-цикле
│   │   │   └── RunStaticChainService.php               # выполнение static-цепочки
│   │   └── Prompt/
│   │       └── PromptProviderInterface.php
│   ├── Entity/
│   │   ├── DynamicLoopExecution.php                     # in-memory сущность dynamic-цикла
│   │   └── StaticChainExecution.php                     # in-memory сущность static-цепочки
│   └── ValueObject/
│       ├── AgentResultVo.php
│       ├── AgentRunRequestVo.php
│       ├── AgentTurnResultVo.php                        # результат одного хода агента
│       ├── BudgetVo.php                                 # maxCostTotal, maxCostPerStep, perRole
│       ├── ChainDefinitionVo.php                        # type, facilitator, participants, maxRounds, fixIterations
│       ├── ChainSessionStateVo.php                      # состояние сессии для resume
│       ├── ChainStepVo.php                              # type (agent/quality_gate), name, role | command
│       ├── CircuitBreakerStateVo.php                    # state, failureCount, lastFailureTime
│       ├── DynamicBudgetCheckVo.php                     # результат проверки бюджета dynamic
│       ├── DynamicChainContextVo.php                     # контекст dynamic-раунда
│       ├── DynamicLoopResultVo.php                      # результат dynamic-цикла (Domain)
│       ├── DynamicRoundResultVo.php                     # метрики dynamic-раунда (Domain)
│       ├── DynamicTurnResultVo.php                      # результат dynamic-хода (Domain)
│       ├── FacilitatorResponseVo.php                    # next_role | done + synthesis
│       ├── FacilitatorTurnResultVo.php                  # результат хода фасилитатора (Domain)
│       ├── FallbackAttemptVo.php                        # runner, model, result fallback-попытки
│       ├── FallbackConfigVo.php                         # fallback command для роли
│       ├── FixIterationGroupVo.php                      # group, stepNames, maxIterations
│       ├── QualityGateVo.php                            # command, label, timeoutSeconds
│       ├── QualityGateResultVo.php                      # label, passed, exitCode, output, durationMs
│       ├── RetryPolicyVo.php                            # maxRetries, initialDelayMs, multiplier
│       ├── RoleConfigVo.php                             # promptFile, command, fallback
│       ├── StaticChainResultVo.php                      # результат static-цепочки (Domain)
│       ├── StaticProcessResultVo.php                    # результат процесса (Domain)
│       └── StaticStepResultVo.php                       # результат шага static-цепочки (Domain)
├── Application/
│   ├── Command/
│   │   └── CommandInterface.php                         # маркерный интерфейс
│   ├── Query/
│   │   └── QueryInterface.php                           # маркерный интерфейс
│   ├── Enum/
│   │   └── ReportFormatEnum.php                         # text | json
│   ├── Event/OrchestrateChain/
│   │   ├── OrchestrateRoundCompletedEvent.php           # событие: раунд dynamic завершён
│   │   └── OrchestrateSessionCompletedEvent.php         # событие: сессия завершена
│   ├── Mapper/
│   │   ├── ReportFormatMapperInterface.php              # маппинг DTO → формат отчёта
│   │   ├── ReportJsonMapper.php
│   │   └── ReportTextMapper.php
│   ├── Service/
│   │   └── Chain/
│   │       ├── ExecuteStaticChainService.php            # оркестрация static-цепочки
│   │       ├── ExecuteStaticChainServiceInterface.php
│   │       ├── DispatchRoundEventService.php            # диспетчеризация событий раунда
│   └── UseCase/
│       ├── Command/                                     # Commands (изменение state / side effects)
│       │   ├── OrchestrateChain/
│       │   │   ├── DynamicLoopResultDto.php              # результат цикла dynamic-цепочки
│       │   │   ├── DynamicRoundResultDto.php             # метрики одного раунда
│       │   │   ├── FacilitatorTurnResultDto.php          # результат хода фасилитатора
│       │   │   ├── OrchestrateChainCommand.php
│       │   │   ├── OrchestrateChainCommandHandler.php    # static + dynamic маршрутизация
│       │   │   ├── OrchestrateChainResultDto.php
│       │   │   └── StepResultDto.php                    # iterationNumber, iterationWarning, passed, exitCode, label
│       │   └── RunAgent/
│       │       ├── RunAgentCommand.php
│       │       ├── RunAgentCommandHandler.php
│       │       └── RunAgentResultDto.php
│       └── Query/                                       # Queries (чтение / без side effects)
│           ├── GenerateReport/
│           │   ├── GenerateReportQuery.php
│           │   ├── GenerateReportQueryHandler.php
│           │   ├── GenerateReportResultDto.php
│           │   └── ReportResultFactory.php
│           └── GetRunners/
│               ├── GetRunnersQuery.php
│               ├── GetRunnersQueryHandler.php
│               └── RunnerDto.php
├── Infrastructure/
│   ├── Service/
│   │   ├── AgentRunner/
│   │   │   ├── CircuitBreakerAgentRunner.php            # обёртка Circuit Breaker
│   │   │   ├── Pi/
│   │   │   │   ├── PiAgentRunner.php
│   │   │   │   └── PiJsonlParser.php
│   │   │   ├── RetryableRunnerFactory.php               # фабрика RetryingAgentRunner
│   │   │   └── RetryingAgentRunner.php                  # обёртка с retry-policy
│   │   ├── Chain/
│   │   │   ├── ChainSessionLogger.php                  # логирование сессии
│   │   │   ├── CheckDynamicBudgetService.php            # проверка бюджета dynamic
│   │   │   ├── FacilitatorResponseParserService.php     # парсинг ответа фасилитатора
│   │   │   ├── JsonlAuditLogger.php                    # JSONL audit trail (FILE_APPEND | LOCK_EX)
│   │   │   ├── JsonlAuditLoggerFactory.php             # фабрика AuditLoggerInterface
│   │   │   ├── PromptFormatterService.php               # форматирование промптов
│   │   │   ├── QualityGateRunner.php                   # Symfony Process, выполнение shell-команд
│   │   │   ├── ResolveChainRunnerService.php            # резолв runner + fallback
│   │   │   ├── RunDynamicLoopAgentService.php           # запуск агента в dynamic-цикле
│   │   │   └── YamlChainLoader.php
│   │   └── Prompt/
│   │       └── RolePromptBuilder.php
│   └── Symfony/
│       └── TaskOrchestratorBundle.php                   # Symfony Bundle для интеграции
├── DependencyInjection/
│   ├── TaskOrchestratorExtension.php                    # Extension для параметров bundle
│   └── Configuration.php                               # TreeBuilder-валидация
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

## Зависимости слоёв

| Откуда | Куда | Примечание |
|---|---|---|
| Domain | — | Только PHP std + `Psr\Log\LoggerInterface` |
| Application | Domain | Через интерфейсы и VOs |
| Infrastructure | Domain (interfaces only) | Реализует интерфейсы Domain |
| Presentation | Application only | Внедряет use case handler'ы напрямую или через Bus |
| Integration | — | Пуст (пока нет межмодульного взаимодействия) |

### Почему CommandHandler для оркестрации

Оркестрация запускает AI-агентов (side effects: выполнение shell-команд, запись файлов, трата токенов).
Поэтому `OrchestrateChainCommandHandler` и `RunAgentCommandHandler` используют Command pattern.
CommandHandler может возвращать DTO — это допустимо для CQRS с side effects.

### Почему QueryHandler для runners и reports

`GetRunnersQueryHandler` и `GenerateReportQueryHandler` — readonly-операции без side effects.
Они используют Query pattern.

## Symfony Bundle

Интеграция в проект осуществляется через `TaskOrchestratorBundle`:

```php
// apps/console/config/bundles.php
return [
    // ...
    \TaskOrchestrator\Infrastructure\Symfony\TaskOrchestratorBundle::class => ['all' => true],
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
- Новый движок: создать класс, реализующий `AgentRunnerInterface`, зарегистрировать в `config/services.yaml`

Подробнее о retry и circuit breaker — в [Надёжность](reliability.md).
