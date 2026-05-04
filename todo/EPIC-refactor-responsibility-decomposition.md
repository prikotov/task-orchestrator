---
type: epic
created: 2026-05-04
value: V2
complexity: C4
priority: P1
author: Тимлид Алекс
assignee: Бэкендер Левша
status: in_progress
branch: refactor/responsibility-decomposition
pr:
---

# EPIC-refactor-responsibility-decomposition: Декомпозиция Orchestrator по ответственности

## 1. Concept and Goal (Концепция и цель)

### Story (Job Story)
Когда проект растёт и модуль Orchestrator достигает 11 573 LOC, я хочу разделить его на изолированные модули по ответственности, чтобы каждый модуль имел свой bounded context с Domain, не зависящим от других модулей.

### Goal (Цель по SMART)
Разделить `Orchestrator` (11 573 LOC) + `StaticExecution` (1 797 LOC) на 3 модуля: `ChainDefinition` (~2500 LOC), `ChainExecution` (~2800 LOC), `DynamicLoop` (~3000 LOC). Каждый модуль — изолированный bounded context. Domain модуля не зависит от Domain других модулей. Межмодульное взаимодействие — только через Integration-слой.

## 2. Context and Scope (Контекст и границы)

### Текущее состояние
- `src/Module/Orchestrator/` — 74 файла, 11 573 LOC
- `src/Module/StaticExecution/` — 22 файла, 1 797 LOC
- StaticExecution\Domain импортирует 13 VO из Orchestrator\Domain — нарушение конвенции «Domain → никто»

### In Scope (Что делаем)
- Переименование `Orchestrator` → `ChainDefinition` (ядро)
- Выделение `DynamicLoop` из `ChainDefinition` в отдельный модуль
- Вливание `StaticExecution` в `ChainExecution`
- Создание Integration-мапперов между модулями
- Перенос и адаптация тестов
- Обновление `services.yaml`, ADR, документации

### Out of Scope (Чего НЕ делаем)
- Не меняем CLI-команды (apps/console) — только namespace импортов
- Не добавляем новый функционал
- Не выделяем Reporting в отдельный модуль (500 LOC, остаётся в ChainExecution)
- Не удаляем ChainDefinitionVo (555 LOC) — отдельная задача после стабилизации
- Не создаём depfile.yaml — отдельная задача после завершения миграции

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Блокирующие требования)
- [ ] 3 модуля: ChainDefinition, ChainExecution, DynamicLoop — каждый со своим Domain
- [ ] Domain каждого модуля НЕ импортирует типы из Domain другого модуля (проверка grep)
- [ ] Все Integration-мапперы корректно переводят типы на границе
- [ ] Все существующие тесты проходят (unit + integration)
- [ ] Psalm проходит без ошибок
- [ ] CLI-команда `app:agent:orchestrate` работает для static/dynamic/conditional

### 🟡 Should Have (Важные требования)
- [ ] Обновлены ADR (новый ADR на декомпозицию)
- [ ] Обновлена документация (docs/guide/architecture.md)

### 🟢 Could Have (Желательно)
- [ ] Deptrac-правила для новых модулей (depfile.yaml)

### ⚫ Won't Have (Не в этот раз)
- Удаление ChainDefinitionVo (legacy-монолит)
- Выделение Reporting
- Переименование namespace `TaskOrchestrator\Common\` → что-либо другое

## 4. Solution Design (Техническое решение)

### Модули

```
ChainDefinition (~2500 LOC) — загрузка, валидация, VO определений
    Domain: ChainDefinitionInterface, ChainDefinitionValidator, YamlChainLoader-интерфейс,
            все Definition VO (15 шт), все Enum (3 шт), Domain DTO (2 шт)
    Infrastructure: YamlChainLoader
    Application: ListChains, LoadChain, ValidateChainConfig queries
    Зависит: НИ ОТ КОГО

ChainExecution (~2800 LOC) — выполнение static + conditional, hooks, reporting
    Domain: ExecutionStrategyInterface, ChainLoaderInterface,
            ChainRunRequestVo, ChainRunResultVo, FallbackAttemptVo, HookResultVo,
            QualityGateResultVo, ConditionalStepResultVo,
            ConditionExpressionVo (для Conditional),
            EvaluateConditionService, ExecuteConditionalStepServiceInterface,
            HookExecutorInterface, PromptFormatterInterface, PromptProviderInterface,
            RunAgentServiceInterface (Integration-интерфейс для вызова AgentRunner)
    Application: StaticExecutionStrategy, ConditionalExecutionStrategy,
                 OrchestrateChainCommandHandler, RunAgentCommandHandler,
                 Reporting queries (GenerateReport, GetRunners)
    Infrastructure: ShellHookExecutor, ExecuteConditionalStepService,
                    PromptFormatterService, RolePromptBuilder
    Integration: ← ChainDefinition (маппер: Definition VO → Execution VO),
                 ← AgentRunner (RunAgentService + AgentDtoMapper)
    + вливается StaticExecution (1797 LOC):
      Domain: StaticChainExecution entity, RunStaticChainService, ExecuteStaticStepService,
              CheckStaticBudgetService, свои интерфейсы
      Application: ExecuteStaticChainService
      Infrastructure: (через Integration StaticExecution)
    Зависит: ChainDefinition, AgentRunner (через Integration)

DynamicLoop (~3000 LOC) — выполнение dynamic, session, audit, budget
    Domain: DynamicLoopExecution entity,
            ChainSessionStateVo, DynamicChainContextVo, DynamicLoopResultVo,
            DynamicRoundResultVo, FacilitatorResponseVo, FacilitatorTurnResultVo,
            DynamicBudgetCheckVo, TurnBreakVo, TurnContinueVo, ChainTurnResultVo,
            все Dynamic-сервисы (10 интерфейсов + 5 реализаций),
            Session-интерфейсы (3), Audit-интерфейсы (2),
            CheckDynamicBudgetServiceInterface,
            RunDynamicLoopAgentServiceInterface (для Integration)
    Application: DynamicExecutionStrategy, DispatchRoundEventService,
                 DispatchSessionCompletedEventService
    Infrastructure: ChainSessionLogger, ChainSessionWriter, ChainSessionReader,
                    ChainSessionFileStorage, ChainSessionBudgetFormatter,
                    CheckDynamicBudgetService, FacilitatorResponseParserService,
                    RunDynamicLoopAgentService, JsonlAuditLogger, JsonlAuditLoggerFactory
    Integration: ← ChainDefinition (маппер: Definition VO → DynamicLoop VO),
                 ← AgentRunner (RunAgentService + AgentDtoMapper)
    Зависит: ChainDefinition, AgentRunner (через Integration)
```

### Integration-мапперы

1. **ChainExecution\Integration ← ChainDefinition**: маппинг `StaticChainDefinitionVo` (Definition) → `ExecutionStaticChainConfigVo` (Execution) и `ConditionalChainDefinitionVo` → `ExecutionConditionalChainConfigVo`
2. **DynamicLoop\Integration ← ChainDefinition**: маппинг `DynamicChainDefinitionVo` (Definition) → `DynamicLoopConfigVo` (DynamicLoop)
3. **ChainExecution\Integration ← AgentRunner**: уже существует (RunAgentService + AgentDtoMapper)
4. **DynamicLoop\Integration ← AgentRunner**: уже существует (RunAgentService + AgentDtoMapper)

### AuditLoggerInterface

`AuditLoggerInterface` использует `ChainRunResultVo` в сигнатуре и нужен DynamicLoop. Решение:
- DynamicLoop.Domain определяет свой `DynamicLoopAuditLoggerInterface` с VO DynamicLoop в сигнатуре
- ChainExecution.Domain определяет свой `StepAuditLoggerInterface` с ChainRunResultVo
- Infrastructure: `JsonlAuditLogger` реализует оба интерфейса

### Deptrac-правила (после стабилизации)

```
ChainDefinition\Domain → nothing
ChainExecution\Domain → nothing
DynamicLoop\Domain → nothing
ChainExecution\Integration → ChainDefinition\Domain, AgentRunner\Application
DynamicLoop\Integration → ChainDefinition\Domain, AgentRunner\Application
ChainExecution\* → ChainDefinition\Domain, ChainExecution\Domain (только через Integration)
DynamicLoop\* → ChainDefinition\Domain, DynamicLoop\Domain (только через Integration)
ChainExecution → DynamicLoop = FORBIDDEN
DynamicLoop → ChainExecution = FORBIDDEN
```

## 5. Implementation Plan (План реализации)

### PR#1: Namespace-миграция Orchestrator → ChainDefinition
- [ ] [TASK-refactor-namespace-chain-definition](TASK-refactor-namespace-chain-definition.todo.md) — Механический rename `Orchestrator` → `ChainDefinition` во всех файлах, тестах, services.yaml, docs. Без изменения логики. `StaticExecution` пока ссылается на старый namespace — правим импорты. **Не зависит от других задач — выполняется первой.**

### PR#2: Выделение DynamicLoop
- [ ] [TASK-refactor-extract-dynamic-loop](TASK-refactor-extract-dynamic-loop.todo.md) — Создать модуль `DynamicLoop`, перенести все Dynamic-специфичные файлы (entity, VO, сервисы, infrastructure, стратегии). Создать Integration-маппер DynamicLoop ← ChainDefinition. Создать DynamicLoopAuditLoggerInterface. Обновить services.yaml. **Зависит от PR#1. ~600 LOC новых файлов.**

### PR#3: Вливание StaticExecution + Integration-мапперы
- [ ] [TASK-refactor-merge-static-execution](TASK-refactor-merge-static-execution.todo.md) — Влить StaticExecution в ChainExecution. Перенести файлы, обновить namespace. Создать Integration-маппер ChainExecution ← ChainDefinition (Definition VO → Execution VO). Удалить старый модуль StaticExecution. Обновить services.yaml. **Зависит от PR#1. Рекомендуется после PR#2. ~1110 LOC новых файлов.**

### PR#4: Deptrac + документация
- [ ] [TASK-refactor-deptrac-decomposition-rules](TASK-refactor-deptrac-decomposition-rules.todo.md) — Создать depfile.yaml с правилами для ChainDefinition, ChainExecution, DynamicLoop, AgentRunner. Обновить ADR (суперседировать ADR-009, создать ADR-011), architecture.md. **Зависит от PR#2 и PR#3. Только конфигурация и документация.**

## 6. Definition of Done (Критерии приёмки эпика)
- [ ] Все Must Have требования выполнены
- [ ] `grep -r 'ChainDefinition\\Domain' src/Module/DynamicLoop/Domain/` → пусто
- [ ] `grep -r 'ChainDefinition\\Domain' src/Module/ChainExecution/Domain/` → пусто
- [ ] `grep -r 'DynamicLoop\\Domain' src/Module/ChainExecution/` → пусто
- [ ] `grep -r 'ChainExecution\\Domain' src/Module/DynamicLoop/` → пусто
- [ ] `vendor/bin/phpunit` → OK
- [ ] `vendor/bin/psalm` → OK
- [ ] Удалён модуль `src/Module/StaticExecution/`
- [ ] Удалён модуль `src/Module/Orchestrator/`

## 7. Release Notes and Deployment (Инструкция по релизу)
- [ ] Обычный merge, нет миграций БД, нет feature flags
- [ ] Breaking change: namespace `Orchestrator` → `ChainDefinition`, `StaticExecution` → `ChainExecution`

## 8. Risks and Dependencies (Риски и зависимости)
- **Размер миграции**: ~96 файлов меняют namespace. Механическая работа, но объёмная.
- **Integration-мапперы**: цена ~350-500 LOC бойлерплейта. Конечная и предсказуемая.
- **AuditLoggerInterface split**: +1 интерфейс, +адаптация 8 Dynamic-файлов. ~150 LOC.
- **Тесты**: integration-тесты используют `Orchestrator` в namespace — нужно обновить.
- **Brainstorm protocol**: `var/sessions/brainstorm/2026-05-04_01-59-17/`
- **Предыдущие brainstorm-ы**: 5 сессий (protocols в `var/sessions/brainstorm/2026-05-03_*`) — привели к решению «декомпозиция по стратегии нежизнеспособна»

## 9. Sources (Источники)
- [Brainstorm #6 protocol](../var/sessions/brainstorm/2026-05-04_01-59-17/discussion_history.md) — декомпозиция по ответственности
- [Brainstorm #3 result](../var/sessions/brainstorm/2026-05-03_11-03-40/result.md) — LOC-анализ
- [ADR-006](../docs/adr/006-execution-strategy-composition.md) — ExecutionStrategy
- [ADR-009](../docs/adr/009-dynamic-split-decision.md) — Dynamic split
- [Конвенции: layers.md](../docs/conventions/layers/layers.md)
- [Конвенции: service.md](../docs/conventions/core_patterns/service.md)

## 10. Comments (Комментарии)
- Неймспейс: `TaskOrchestrator\Common\Module\ChainDefinition\`, `ChainExecution\`, `DynamicLoop\`
- Цепочка вызовов: CLI → ChainExecution\Application\OrchestrateChainCommandHandler → ExecutionStrategyInterface → (Static|Conditional) или DynamicLoop через Integration
- ChainDefinition — единственный «provider», от него зависят оба execution-модуля, но он ни от кого не зависит

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-04 | Тимлид Алекс | Создание эпика |
