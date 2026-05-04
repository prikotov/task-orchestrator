# Аудит размещения классов по слоям

**Дата:** 2026-05-04
**Ревьювер:** Пуаро (Backend Code Reviewer)
**Область:** 3 модуля — ChainDefinition (40 файлов), ChainExecution (88 файлов), DynamicLoop (66 файлов)
**Всего файлов:** 194
**Правила:** `docs/conventions/layers/layers.md`

---

## ChainDefinition (40 файлов)

### Domain (25 файлов)

- ✅ `ChainDefinitionInterface` — интерфейс определения цепочки, корректный Domain contract
- ✅ `Contract/Chain/ChainLoaderInterface` — интерфейс загрузки цепочек, Domain port
- ✅ `Enum/ChainStepTypeEnum` — enum типа шага, Domain
- ✅ `Enum/ChainTypeEnum` — enum типа цепочки, Domain
- ✅ `Enum/ConditionOperatorEnum` — enum оператора условия, Domain
- ✅ `Exception/ChainNotFoundException` — domain exception, Domain
- ✅ `Exception/NotFoundExceptionInterface` — marker interface, Domain
- ✅ `Exception/OrchestratorException` — base exception, Domain
- ✅ `Exception/RoleNotFoundException` — domain exception, Domain
- ✅ `Service/Chain/ChainDefinitionValidator` — domain service (чистая валидация VO без I/O), Domain
- ✅ `ValueObject/BudgetVo` — VO бюджетных ограничений, Domain
- ✅ `ValueObject/ChainConfigViolationVo` — VO нарушения конфигурации, Domain
- ✅ `ValueObject/ChainDefinitionVo` — VO определения цепочки (@deprecated), Domain
- ✅ `ValueObject/ChainRetryPolicyVo` — VO retry-политики, Domain
- ✅ `ValueObject/ChainStepVo` — VO шага цепочки, Domain
- ✅ `ValueObject/ConditionalChainDefinitionVo` — VO conditional-цепочки, Domain
- ✅ `ValueObject/ConditionExpressionVo` — VO условного выражения, Domain
- ✅ `ValueObject/DynamicChainDefinitionVo` — VO dynamic-цепочки, Domain
- ✅ `ValueObject/FallbackConfigVo` — VO конфигурации fallback, Domain
- ✅ `ValueObject/FixIterationGroupVo` — VO группы итераций, Domain
- ✅ `ValueObject/PromptConfigurationVo` — VO конфигурации промптов, Domain
- ✅ `ValueObject/QualityGateVo` — VO quality gate, Domain
- ✅ `ValueObject/RoleConfigVo` — VO конфигурации роли, Domain
- ✅ `ValueObject/SharedChainDefinitionVo` — VO shared kernel, Domain
- ✅ `ValueObject/StaticChainDefinitionVo` — VO static-цепочки, Domain

### Application (14 файлов)

- ✅ `Dto/ChainConfigViolationDto` — DTO нарушения, Application transport object
- ✅ `Dto/ChainDefinitionDto` — DTO определения цепочки, Application transport object
- ✅ `Dto/ChainStepDto` — DTO шага, Application transport object
- ✅ `Mapper/ChainConfigViolationDtoMapper` — mapper Domain VO → Application DTO, Application
- ✅ `Mapper/ChainDefinitionDtoMapper` — mapper Domain VO → Application DTO, Application
- ✅ `UseCase/Query/Chain/ListChains/ListChainsQuery` — Query DTO, Application
- ✅ `UseCase/Query/Chain/ListChains/ListChainsResult` — Result DTO, Application
- ✅ `UseCase/Query/Chain/ListChains/ListChainsQueryHandler` — Query handler, Application
- ✅ `UseCase/Query/Chain/LoadChain/LoadChainQuery` — Query DTO, Application
- ✅ `UseCase/Query/Chain/LoadChain/LoadChainResult` — Result DTO, Application
- ✅ `UseCase/Query/Chain/LoadChain/LoadChainQueryHandler` — Query handler, Application
- ✅ `UseCase/Query/Chain/ValidateChainConfig/ValidateChainConfigQuery` — Query DTO, Application
- ✅ `UseCase/Query/Chain/ValidateChainConfig/ValidateChainConfigResult` — Result DTO, Application
- ✅ `UseCase/Query/Chain/ValidateChainConfig/ValidateChainConfigQueryHandler` — Query handler, Application

### Infrastructure (1 файл)

- ✅ `Service/Chain/YamlChainLoader` — реализация ChainLoaderInterface, Infrastructure (чтение YAML, зависимость на Symfony YAML)

---

## ChainExecution (88 файлов)

### Domain (48 файлов)

#### Entity (1)

- ✅ `Entity/StaticChainExecution` — in-memory entity с бизнес-правилами навигации (бюджет, итерации), Domain

#### Value Object (22)

- ✅ `ValueObject/ChainRunRequestVo` — VO запроса на запуск агента, Domain
- ✅ `ValueObject/ChainRunResultVo` — VO результата агента, Domain
- ✅ `ValueObject/ConditionalStepResultVo` — VO результата conditional-шага, Domain
- ✅ `ValueObject/ConditionExpressionVo` — VO условного выражения, Domain
- ✅ `ValueObject/ExecutionBudgetVo` — VO бюджетных ограничений (execution view), Domain
- ✅ `ValueObject/ExecutionConditionalChainConfigVo` — VO конфигурации conditional-цепочки (execution view), Domain
- ✅ `ValueObject/ExecutionFallbackConfigVo` — VO fallback конфигурации (execution view), Domain
- ✅ `ValueObject/ExecutionFixIterationGroupVo` — VO группы итераций (execution view), Domain
- ✅ `ValueObject/ExecutionQualityGateVo` — VO quality gate (execution view), Domain
- ✅ `ValueObject/ExecutionRetryPolicyVo` — VO retry-политики (execution view), Domain
- ✅ `ValueObject/ExecutionRoleConfigVo` — VO конфигурации роли (execution view), Domain
- ✅ `ValueObject/ExecutionStaticChainConfigVo` — VO конфигурации static-цепочки (execution view), Domain
- ✅ `ValueObject/ExecutionStepVo` — VO шага (execution view), Domain
- ✅ `ValueObject/FallbackAttemptVo` — VO результата fallback runner'а, Domain
- ✅ `ValueObject/HookResultVo` — VO результата hook'а, Domain
- ✅ `ValueObject/QualityGateResultVo` — VO результата quality gate, Domain
- ✅ `ValueObject/StaticChainAuditVo` — VO аудита static-цепочки, Domain
- ✅ `ValueObject/StaticChainResultVo` — VO агрегированного результата static-цепочки, Domain
- ✅ `ValueObject/StaticProcessResultVo` — VO результата обработки шага, Domain
- ✅ `ValueObject/StaticStepAuditVo` — VO аудита шага, Domain
- ✅ `ValueObject/StaticStepResultVo` — VO результата шага static-цепочки, Domain
- 🟡 `ValueObject/ConditionExpressionVo` — **дублирует** ChainDefinition\Domain\ValueObject\ConditionExpressionVo. Оправдано изоляцией модулей (ChainExecution не должен зависеть от ChainDefinition.Domain), но стоит рассмотреть shared-kernel extraction если дублирование растёт

#### Enum (2)

- 🟡 `Enum/ChainStepTypeEnum` — **дублирует** ChainDefinition\Domain\Enum\ChainStepTypeEnum. Аналогично ConditionExpressionVo — оправдано модульной изоляцией
- 🟡 `Enum/ConditionOperatorEnum` — **дублирует** ChainDefinition\Domain\Enum\ConditionOperatorEnum. Там же

#### Exception (3)

- 🟡 `Exception/ChainNotFoundException` — **дублирует** ChainDefinition\Domain\Exception\ChainNotFoundException. Обоснование то же, но маркерный NotFoundExceptionInterface duplicated
- 🟡 `Exception/NotFoundExceptionInterface` — **дублирует** ChainDefinition\Domain\Exception\NotFoundExceptionInterface
- 🟡 `Exception/RoleNotFoundException` — **дублирует** ChainDefinition\Domain\Exception\RoleNotFoundException

#### Dto (2)

- ✅ `Dto/ChainResultAuditDto` — DTO для передачи метрик audit-логгеру (используется в Domain Contract), Domain
- ✅ `Dto/StepAuditStatusDto` — DTO статуса шага для audit-лога, Domain

#### Contract (3)

- ✅ `Contract/Agent/RunAgentServiceInterface` — порт запуска AI-агента, Domain contract
- ✅ `Contract/Chain/Audit/AuditLoggerInterface` — порт audit-логгера, Domain contract
- ✅ `Contract/Prompt/PromptProviderInterface` — порт провайдера промптов, Domain contract

#### Service (15)

- ✅ `Service/Condition/EvaluateConditionServiceInterface` — интерфейс, Domain
- ✅ `Service/Condition/EvaluateConditionService` — чистая логика (string ops, сравнение), Domain service
- ✅ `Service/Chain/ChainConfigMapperInterface` — интерфейс маппинга (реализация в Integration), Domain contract
- ✅ `Service/Chain/Hook/HookExecutorInterface` — интерфейс hook execution, Domain port
- ✅ `Service/Chain/Shared/PromptFormatterInterface` — интерфейс форматирования промптов, Domain port
- ✅ `Service/Integration/ChainDefinitionProviderInterface` — интерфейс провайдера ChainDefinition, Domain port
- ✅ `Service/Static/CheckStaticBudgetServiceInterface` — интерфейс, Domain
- ✅ `Service/Static/CheckStaticBudgetService` — чистая логика бюджетных проверок, Domain service
- ✅ `Service/Static/ExecuteStaticStepService` — **оркестрация шага** (форматирование промпта → запуск агента → fallback → quality gate)
- ✅ `Service/Static/FormatPromptServiceInterface` — интерфейс, Domain port
- ✅ `Service/Static/QualityGateRunnerInterface` — интерфейс, Domain port
- ✅ `Service/Static/ResolveChainRunnerServiceInterface` — интерфейс, Domain port
- ✅ `Service/Static/StaticAuditServiceInterface` — интерфейс, Domain port
- 🔴 `Service/Static/RunStaticChainService` — **оркестратор** линейного выполнения шагов с итерациями, бюджетом, хуками. Координирует 4+ сервисов. По сути это Application-level use case. Однако: не зависит от Application-слоя, работает только с Domain-типами. **Вопрос спорный** — см. анализ ниже
- 🔴 `Service/Static/RunAgentServiceInterface` — **дублирует** Contract/Agent/RunAgentServiceInterface с идентичной сигнатурой. Два интерфейса с одинаковым методом `run()` — это явное дублирование, которое запутывает. Один из них нужно удалить
- ✅ `Service/ExecuteConditionalStepServiceInterface` — интерфейс выполнения conditional-шага, Domain port

### Application (29 файлов)

#### Enum (2)

- ✅ `Enum/OrchestrateExitCodeEnum` — enum exit codes CLI-команды, Application (контракт Presentation ↔ Application)
- ✅ `Enum/ReportFormatEnum` — enum формата отчёта, Application

#### Event (2)

- ✅ `Event/OrchestrateChain/OrchestrateRoundCompletedEvent` — event round-завершения, Application event
- ✅ `Event/OrchestrateChain/OrchestrateSessionCompletedEvent` — event session-завершения, Application event

#### Contract (1)

- ✅ `Contract/Chain/ExecutionStrategyInterface` — интерфейс стратегии выполнения, Application contract (реализуется другими модулями через DynamicExecutionStrategy)

#### Mapper (3)

- ✅ `Mapper/ReportFormatMapperInterface` — интерфейс маппера формата отчёта, Application
- ✅ `Mapper/ReportJsonMapper` — маппер DTO → JSON, Application
- ✅ `Mapper/ReportTextMapper` — маппер DTO → текст, Application

#### Service (5)

- ✅ `Service/Chain/StaticExecutionStrategy` — стратегия выполнения static-цепочки, Application (делегирует в Domain-сервисы и Integration-мапперы)
- ✅ `Service/Chain/ConditionalExecutionStrategy` — стратегия выполнения conditional-цепочки, Application
- ✅ `Service/ExecuteStaticChainService` — Application-обёртка над Domain RunStaticChainService, Application
- ✅ `Service/ExecuteStaticChainServiceInterface` — интерфейс обёртки, Application
- ✅ `Service/ResolveExitCodeService` — маппинг Domain-исключений в exit codes, Application
- ✅ `Service/ResolveExitCodeServiceInterface` — интерфейс, Application

#### UseCase Command (8)

- ✅ `UseCase/Command/OrchestrateChain/OrchestrateChainCommand` — Command DTO, Application
- ✅ `UseCase/Command/OrchestrateChain/OrchestrateChainCommandHandler` — Command handler (диспетчер стратегий), Application
- ✅ `UseCase/Command/OrchestrateChain/OrchestrateChainResultDto` — Result DTO, Application
- ✅ `UseCase/Command/OrchestrateChain/StepResultDto` — DTO результата шага, Application
- ✅ `UseCase/Command/OrchestrateChain/DynamicRoundResultDto` — DTO результата раунда, Application
- ✅ `UseCase/Command/RunAgent/RunAgentCommand` — Command DTO, Application
- ✅ `UseCase/Command/RunAgent/RunAgentCommandHandler` — Command handler, Application
- ✅ `UseCase/Command/RunAgent/RunAgentResultDto` — Result DTO, Application

#### UseCase Query (8)

- ✅ `UseCase/Query/GenerateReport/GenerateReportQuery` — Query DTO, Application
- ✅ `UseCase/Query/GenerateReport/GenerateReportQueryHandler` — Query handler, Application
- ✅ `UseCase/Query/GenerateReport/GenerateReportResultDto` — Result DTO, Application
- ✅ `UseCase/Query/GenerateReport/ReportResultFactory` — фабрика результатов, Application
- ✅ `UseCase/Query/GetRunners/GetRunnersQuery` — Query DTO, Application
- ✅ `UseCase/Query/GetRunners/GetRunnersQueryHandler` — Query handler, Application
- 🟡 `UseCase/Query/GetRunners/RunnerDto` — DTO в UseCase/Query. Технически Application DTO, но命名 convention обычно кладёт DTO в Dto/, а не в UseCase/. Допустимо, но нарушает единообразие с другими DTO

### Infrastructure (6 файлов)

- ✅ `Service/Agent/ResolveChainRunnerService` — реализация ResolveChainRunnerServiceInterface (fallback logic через агента), Infrastructure
- ✅ `Service/Chain/ExecuteConditionalStepService` — реализация ExecuteConditionalStepServiceInterface (Symfony Process для quality gate), Infrastructure
- ✅ `Service/Chain/Hook/ShellHookExecutor` — реализация HookExecutorInterface (Symfony Process), Infrastructure
- ✅ `Service/Chain/PromptFormatterService` — реализация PromptFormatterInterface (string ops, без I/O — но расположение Infrastructure корректно т.к. это реализация Domain interface)
- ✅ `Service/Prompt/RolePromptBuilder` — реализация PromptProviderInterface (чтение .md файлов с диска), Infrastructure
- ✅ `Service/QualityGate/QualityGateRunner` — реализация QualityGateRunnerInterface (Symfony Process), Infrastructure

### Integration (5 файлов)

- ✅ `Service/AgentRunner/AgentDtoMapper` — ACL-маппер ChainExecution VO ↔ AgentRunner Application DTO, Integration
- 🟡 `Service/AgentRunner/RunAgentService` — делегирует из Domain\Service\Static\RunAgentServiceInterface в Domain\Contract\Agent\RunAgentServiceInterface. Технически это proxy/adapter, не полноценный ACL-маппер. **Содержит бизнес-логику: нет.** Но существование двух RunAgentServiceInterface (один в Contract, один в Service/Static) делает этот слой запутанным
- ✅ `Service/ChainDefinition/ChainExecutionDefinitionMapper` — ACL-маппер ChainDefinition.Domain VO → ChainExecution.Domain VO. Реализует и ChainConfigMapperInterface, и ChainDefinitionProviderInterface. Корректный Integration
- ✅ `Service/Prompt/FormatPromptService` — делегирует FormatPromptServiceInterface → PromptFormatterInterface, Integration (proxy)
- ✅ `Service/Audit/StaticAuditService` — маппит StaticExecution VO → ChainExecution Domain DTO и делегирует в AuditLoggerInterface, Integration

---

## DynamicLoop (66 файлов)

### Domain (48 файлов)

#### Entity (1)

- ✅ `Entity/DynamicLoopExecution` — in-memory entity состояния dynamic-цикла, Domain

#### Dto (2)

- ✅ `Dto/DynamicLoopAuditDto` — DTO для audit-лога dynamic-цикла, Domain (используется в Domain Port)
- ✅ `Dto/DynLoopStepAuditDto` — DTO статуса шага для audit, Domain

#### Value Object (18)

- ✅ `ValueObject/DynamicBudgetCheckVo` — результат проверки бюджета, Domain
- ✅ `ValueObject/DynamicLoopBudgetVo` — VO бюджета (копия ChainDefinition\BudgetVo), Domain
- ✅ `ValueObject/DynamicLoopConfigVo` — VO конфигурации dynamic-цикла, Domain
- ✅ `ValueObject/DynamicLoopContextVo` — VO контекста dynamic-цикла, Domain
- ✅ `ValueObject/DynamicLoopFallbackConfigVo` — VO fallback (копия ChainDefinition\FallbackConfigVo), Domain
- ✅ `ValueObject/DynamicLoopPromptConfigVo` — VO промпт-конфигурации (копия ChainDefinition\PromptConfigurationVo), Domain
- ✅ `ValueObject/DynamicLoopResultVo` — результат dynamic-цикла, Domain
- ✅ `ValueObject/DynamicLoopRetryPolicyVo` — VO retry-политики (копия ChainDefinition\ChainRetryPolicyVo), Domain
- ✅ `ValueObject/DynamicLoopRoleConfigVo` — VO конфигурации роли (копия ChainDefinition\RoleConfigVo), Domain
- ✅ `ValueObject/DynamicLoopRunRequestVo` — VO запроса на запуск агента (копия ChainExecution\ChainRunRequestVo), Domain
- ✅ `ValueObject/DynamicLoopRunResultVo` — VO результата агента (копия ChainExecution\ChainRunResultVo), Domain
- ✅ `ValueObject/DynamicLoopSessionStateVo` — VO состояния сессии, Domain
- ✅ `ValueObject/DynamicLoopTurnResultVo` — VO результата хода агента, Domain
- ✅ `ValueObject/DynamicRoundResultVo` — VO результата раунда, Domain
- ✅ `ValueObject/FacilitatorResponseVo` — VO ответа фасилитатора, Domain
- ✅ `ValueObject/FacilitatorTurnResultVo` — VO результата хода фасилитатора, Domain
- ✅ `ValueObject/TurnBreakVo` — сигнал прерывания (discriminated union), Domain
- ✅ `ValueObject/TurnContinueVo` — сигнал продолжения (discriminated union), Domain

#### Service (27)

- ✅ `Service/Audit/DynamicLoopAuditLoggerFactoryInterface` — фабрика audit-логгера, Domain port
- ✅ `Service/Audit/DynamicLoopAuditLoggerInterface` — интерфейс audit-логгера, Domain port
- ✅ `Service/Budget/CheckDynamicLoopBudgetServiceInterface` — интерфейс проверки бюджета, Domain port
- ✅ `Service/Dynamic/BuildDynamicContextServiceInterface` — интерфейс, Domain
- ✅ `Service/Dynamic/BuildDynamicContextService` — чистая логика сборки контекста, Domain service
- ✅ `Service/Dynamic/CheckDynamicLoopBudgetServiceInterface` — интерфейс (в Dynamic/ —高层次 wrapper), Domain
- ✅ `Service/Dynamic/CheckDynamicLoopBudgetService` — делегирует в Budget\CheckDynamicLoopBudgetServiceInterface + sessionWriter, Domain service
- ✅ `Service/Dynamic/ExecuteDynamicTurnServiceInterface` — интерфейс, Domain
- ✅ `Service/Dynamic/ExecuteDynamicTurnService` — оркестрация turn'а (facilitator/participant routing, journal, budget, error handling). Domain (аналогично RunStaticChainService — не зависит от Application)
- ✅ `Service/Dynamic/FacilitatorResponseParserInterface` — интерфейс парсера, Domain port
- ✅ `Service/Dynamic/FinalizeDynamicLoopServiceInterface` — интерфейс, Domain
- ✅ `Service/Dynamic/FinalizeDynamicLoopService` — финализация цикла, Domain service
- ✅ `Service/Dynamic/FormatDynamicJournalServiceInterface` — интерфейс, Domain
- ✅ `Service/Dynamic/FormatDynamicJournalService` — форматирование журнала, Domain service
- ✅ `Service/Dynamic/RecordDynamicRoundServiceInterface` — интерфейс, Domain
- ✅ `Service/Dynamic/RecordDynamicRoundService` — запись раунда, Domain service
- ✅ `Service/Dynamic/RoundCompletedNotifierInterface` — callback-интерфейс для Application event dispatch, Domain port
- ✅ `Service/Dynamic/RunDynamicLoopAgentServiceInterface` — интерфейс запуска агентов, Domain port
- ✅ `Service/Dynamic/RunDynamicLoopServiceInterface` — интерфейс координатора, Domain
- ✅ `Service/Dynamic/RunDynamicLoopService` — **координатор** dynamic-цикла. Аналогично RunStaticChainService — оркестрирует несколько Domain-сервисов. Domain (зависимости только на Domain-типы)
- ✅ `Service/Dynamic/SessionCompletedNotifierInterface` — callback-интерфейс, Domain port
- ✅ `Service/Integration/ChainDefinitionProviderInterface` — интерфейс загрузки ChainDefinition, Domain port
- ✅ `Service/Session/DynamicLoopSessionLoggerInterface` — агрегатный интерфейс logger'а, Domain port
- ✅ `Service/Session/DynamicLoopSessionReaderInterface` — интерфейс чтения сессии, Domain port
- ✅ `Service/Session/DynamicLoopSessionWriterInterface` — интерфейс записи сессии, Domain port
- ✅ `Service/Shared/DynamicLoopPromptFormatterInterface` — интерфейс форматирования промптов, Domain port
- ✅ `Service/DynamicLoopConfigMapperInterface` — интерфейс маппинга (реализация в Integration), Domain contract

### Application (6 файлов)

- ✅ `Service/DispatchRoundEventService` — Application-реализация RoundCompletedNotifierInterface, Application
- ✅ `Service/DispatchSessionCompletedEventService` — Application-реализация SessionCompletedNotifierInterface, Application
- ✅ `Service/DynamicExecutionStrategy` — стратегия выполнения dynamic-цепочки, Application (реализует ExecutionStrategyInterface из ChainExecution Application)
- ✅ `UseCase/Command/OrchestrateChain/DynamicLoopResultDto` — DTO результата, Application
- ✅ `UseCase/Command/OrchestrateChain/DynamicRoundResultDto` — DTO результата раунда, Application
- ✅ `UseCase/Command/OrchestrateChain/FacilitatorTurnResultDto` — DTO результата хода фасилитатора, Application

### Infrastructure (11 файлов)

- ✅ `Service/ChainSessionBudgetFormatter` — форматирование бюджетных данных для JSON, Infrastructure (реализует Domain VO → array)
- ✅ `Service/ChainSessionFileStorage` — абстракция файловых операций, Infrastructure
- ✅ `Service/ChainSessionLogger` — фасад над Writer/Reader, Infrastructure (реализует DynamicLoopSessionLoggerInterface)
- ✅ `Service/ChainSessionReader` — чтение состояния сессии, Infrastructure
- ✅ `Service/ChainSessionWriter` — запись событий сессии, Infrastructure
- ✅ `Service/CheckDynamicBudgetService` — реализация Budget\CheckDynamicLoopBudgetServiceInterface, Infrastructure
- ✅ `Service/FacilitatorResponseParserService` — реализация FacilitatorResponseParserInterface, Infrastructure
- ✅ `Service/JsonlAuditLogger` — реализация AuditLoggerInterface + DynamicLoopAuditLoggerInterface, Infrastructure
- ✅ `Service/JsonlAuditLoggerFactory` — реализация DynamicLoopAuditLoggerFactoryInterface, Infrastructure
- 🟡 `Service/PromptFormatterService` — реализация DynamicLoopPromptFormatterInterface. Стринг-операции, без I/O. Мог бы быть Domain-сервисом, но Infrastructure допустимо (реализует Domain interface по конвенции)
- 🟡 `Service/RunDynamicLoopAgentService` — реализация RunDynamicLoopAgentServiceInterface. Зависит от ChainExecution\Domain\Contract\Agent\RunAgentServiceInterface и ChainExecution\Domain\Contract\Prompt\PromptProviderInterface. **Infrastructure модуля DynamicLoop зависит от Domain контрактов другого модуля (ChainExecution)** — это допустимо (Infrastructure → Domain), но переходит границу модулей. Строго говоря, нужно Integration-обёртку, но на практике контрактный интерфейс достаточен

### Integration (1 файл)

- ✅ `Service/ChainDefinition/DynamicLoopDefinitionMapper` — ACL-маппер ChainDefinition.Domain VO → DynamicLoop.Domain VO. Корректный Integration

---

## Summary

### Статистика

| Модуль | ✅ correct | 🔴 wrong | 🟡 questionable |
|--------|-----------|---------|-----------------|
| ChainDefinition (40) | 40 | 0 | 0 |
| ChainExecution (88) | 80 | 2 | 6 |
| DynamicLoop (66) | 62 | 0 | 4 |
| **Итого (194)** | **182** | **2** | **10** |

### 🔴 Wrong (2) — требуют исправления

1. **ChainExecution: дублирование `RunAgentServiceInterface`**
   - `Domain/Contract/Agent/RunAgentServiceInterface` — оригинальный порт
   - `Domain/Service/Static/RunAgentServiceInterface` — дубликат с идентичной сигнатурой
   - **Рекомендация:** Удалить `Service/Static/RunAgentServiceInterface`. Все зависимости (ExecuteStaticStepService, ResolveChainRunnerService, Integration/AgentRunner/RunAgentService) перевести на `Contract/Agent/RunAgentServiceInterface`.

2. **ChainExecution/Integration: `RunAgentService` как proxy без маппинга**
   - `Integration/Service/AgentRunner/RunAgentService` реализует `Service/Static/RunAgentServiceInterface` и делегирует в `Contract/Agent/RunAgentServiceInterface` — это не Integration (нет трансформации типов, нет ACL). Это proxy-паттерн, вызванный существованием двух интерфейсов.
   - **Рекомендация:** После удаления дублирующего интерфейса (п.1), этот класс либо упростится до одного адаптера, либо будет удалён если `Contract/Agent/RunAgentServiceInterface` реализуется напрямую через DI.

### 🟡 Questionable (10) — допустимо, но стоит обсудить

1. **ChainExecution.Domain: `RunStaticChainService`** — оркестратор, координирующий 4+ сервисов. Формально это Application-level логика, но класс зависит только от Domain-типов и не знает о Application-слое. Размещение в Domain допустимо как «domain service implementation» (калькулятор/спецификация выполнения), но при росте сложности лучше перенести в Application.

2. **ChainExecution.Domain: дублирующиеся Enums** (`ChainStepTypeEnum`, `ConditionOperatorEnum`) — продиктованы модульной изоляцией (ChainExecution не зависит от ChainDefinition.Domain). Оправдано архитектурно, но при появлении 4+ модулей стоит выделить shared kernel.

3. **ChainExecution.Domain: дублирующиеся Exceptions** (`ChainNotFoundException`, `NotFoundExceptionInterface`, `RoleNotFoundException`) — та же ситуация. При масштабировании выделить общий `Common\Domain\Exception\`.

4. **ChainExecution.Domain: дублирующийся `ConditionExpressionVo`** — аналогично enums. Обосновано модульной изоляцией.

5. **ChainExecution.Application: `RunnerDto` в UseCase/Query/** — нарушает naming convention (DTO обычно в `Application/Dto/`). Допустимо, но лучше перенести в `Application/Dto/RunnerDto.php` для единообразия.

6. **DynamicLoop.Infrastructure: `RunDynamicLoopAgentService`** зависит от ChainExecution\Domain контрактов. Строго говоря, cross-module Infrastructure→Domain через контракты допустимо (Infrastructure → Domain). Но для чистоты можно было бы ввести Integration-обёртку. На текущем этапе допустимо.

7. **DynamicLoop.Infrastructure: `PromptFormatterService`** — string-операции без I/O. Мог бы быть Domain, но как реализация Domain interface в Infrastructure — корректно.

8. **DynamicLoop.Domain: `CheckDynamicLoopBudgetServiceInterface` дублируется** — один в `Service/Budget/`, другой в `Service/Dynamic/`. `Budget/` — низкоуровневый интерфейс (реализуется Infrastructure). `Dynamic/` — high-level wrapper (реализуется Domain service). Это разделение ответственности, не дублирование, но именование может запутывать.

---

## Приоритеты исправлений

### P0 — Немедленно

- Удалить дублирующийся `Domain/Service/Static/RunAgentServiceInterface` в ChainExecution и консолидировать все ссылки на `Domain/Contract/Agent/RunAgentServiceInterface`.

### P1 — Следующий спринт

- Обсудить с архитектором стратегию для shared kernel (Enums, Exceptions, базовые VO). При текущих 3 модулях дублирование терпимо; при добавлении 4-го модуля станет проблемой.

### P2 — При удобном случае

- Перенести `RunnerDto` из `UseCase/Query/GetRunners/` в `Application/Dto/`.
- Рассмотреть ренейминг `Budget/CheckDynamicLoopBudgetServiceInterface` → `CheckDynamicBudgetPortInterface` или аналогичный, чтобы чётко разделить low-level port и high-level domain service.
