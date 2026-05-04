---
type: refactor
created: 2026-05-04
value: V2
complexity: C3
priority: P1
depends_on: TASK-refactor-namespace-chain-definition
epic: EPIC-refactor-responsibility-decomposition
author: Аналитик (Шерлок)
assignee: Бэкендер (Левша)
branch: task/refactor-merge-static-execution
pr: '#149'
status: done
---

# TASK-refactor-merge-static-execution: Вливание StaticExecution в ChainExecution

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда StaticExecution (22 файла, 1797 LOC) является отдельным модулем, но его Domain импортирует 13 VO из ChainDefinition.Domain (нарушение «Domain → никто»), а по ответственности это часть execution bounded context, я хочу влить StaticExecution в ChainExecution (модуль, выделенный из ChainDefinition), чтобы устранить межмодульные нарушения и получить единый execution модуль.

### Goal (Цель по SMART)
1. Переименовать ChainDefinition (после выделения DynamicLoop) в `ChainExecution` — модуль выполнения static/conditional цепочек.
2. Перенести все файлы StaticExecution в ChainExecution, обновив namespace.
3. Создать Integration-маппер ChainExecution ← ChainDefinition (Definition VO → Execution VO).
4. Удалить модуль `src/Module/StaticExecution/`.
5. После вливания: `ChainExecution\Domain` НЕ импортирует `ChainDefinition\Domain` (проверка grep).

## 2. Context and Scope (Контекст и Границы)

### Где делаем

**Предусловие:** TASK-refactor-extract-dynamic-loop завершена. Модуль `src/Module/ChainDefinition/` больше не содержит Dynamic-файлов.

**Шаг 1: Переименование ChainDefinition → ChainExecution для execution-части:**

После выделения DynamicLoop, в ChainDefinition остаются:
- **Definition VO** (остаются в новом модуле ChainDefinition): `StaticChainDefinitionVo`, `DynamicChainDefinitionVo`, `ConditionalChainDefinitionVo`, `SharedChainDefinitionVo`, `ChainStepVo`, `BudgetVo`, `RoleConfigVo`, `ChainDefinitionVo` (legacy), `FixIterationGroupVo`, `FallbackConfigVo`, `QualityGateVo`, `PromptConfigurationVo`, `ChainRetryPolicyVo`, `ChainConfigViolationVo`, `ConditionExpressionVo`
- **Enums** (остаются в ChainDefinition): `ChainTypeEnum`, `ChainStepTypeEnum`, `ConditionOperatorEnum`
- **Interfaces** (остаются в ChainDefinition): `ChainDefinitionInterface`
- **Services** (остаются в ChainDefinition): `ChainDefinitionValidator`
- **Infrastructure** (остаётся в ChainDefinition): `YamlChainLoader`
- **Application queries** (остаются в ChainDefinition): ListChains, LoadChain, ValidateChainConfig

**Что становится ChainExecution** (переносится из ChainDefinition + StaticExecution):
- **Strategies**: `ExecutionStrategyInterface`, `StaticExecutionStrategy`, `ConditionalExecutionStrategy`
- **Domain VO (execution-specific)**: `ChainRunRequestVo`, `ChainRunResultVo`, `FallbackAttemptVo`, `HookResultVo`, `QualityGateResultVo`, `ConditionalStepResultVo`
- **Domain services**: `EvaluateConditionService` (+interface), `HookExecutorInterface`, `ExecuteConditionalStepServiceInterface`, `PromptFormatterInterface`, `PromptProviderInterface`
- **Application**: `OrchestrateChainCommandHandler`, `OrchestrateChainCommand`, `OrchestrateChainResultDto`, `StepResultDto`, `RunAgentCommandHandler`, `RunAgentCommand`, `RunAgentResultDto`, `ResolveExitCodeService` (+interface), Reporting queries, Dispatch-сервисы (если остались), DTO/Mapper для reporting
- **Infrastructure**: `ShellHookExecutor`, `ExecuteConditionalStepService`, `PromptFormatterService`(?), `RolePromptBuilder`
- **Integration**: `RunAgentService`, `AgentDtoMapper` (ChainExecution ← AgentRunner)
- **Events + Enums**: `OrchestrateExitCodeEnum`, `ReportFormatEnum`, OrchestrateChain events

**Шаг 2: Вливание StaticExecution:**

| StaticExecution файл (22 файла) | → | ChainExecution расположение |
|---|---|---|
| `Application\Service\ExecuteStaticChainServiceInterface` | → | `ChainExecution\Application\Service\` |
| `Application\Service\ExecuteStaticChainService` | → | `ChainExecution\Application\Service\` |
| `Domain\Entity\StaticChainExecution` | → | `ChainExecution\Domain\Entity\` |
| `Domain\Service\CheckStaticBudgetServiceInterface` | → | `ChainExecution\Domain\Service\Static\` |
| `Domain\Service\CheckStaticBudgetService` | → | `ChainExecution\Domain\Service\Static\` |
| `Domain\Service\ExecuteStaticStepService` | → | `ChainExecution\Domain\Service\Static\` |
| `Domain\Service\FormatPromptServiceInterface` | → | `ChainExecution\Domain\Service\Prompt\` |
| `Domain\Service\QualityGateRunnerInterface` | → | `ChainExecution\Domain\Service\QualityGate\` |
| `Domain\Service\ResolveChainRunnerServiceInterface` | → | `ChainExecution\Domain\Service\Agent\` |
| `Domain\Service\RunAgentServiceInterface` | → | `ChainExecution\Domain\Service\Integration\` (или удалить, если ChainExecution.Domain уже имеет свой) |
| `Domain\Service\RunStaticChainService` | → | `ChainExecution\Domain\Service\Static\` |
| `Domain\Service\StaticAuditServiceInterface` | → | **УДАЛИТЬ** — заменить прямой инъекцией `AuditLoggerInterface` |
| `Domain\ValueObject\StaticChainAuditVo` | → | **УДАЛИТЬ** — заменить ChainResultAuditDto (ChainExecution.Domain\Dto\) |
| `Domain\ValueObject\StaticChainResultVo` | → | `ChainExecution\Domain\ValueObject\` |
| `Domain\ValueObject\StaticProcessResultVo` | → | `ChainExecution\Domain\ValueObject\` |
| `Domain\ValueObject\StaticStepAuditVo` | → | **УДАЛИТЬ** — заменить StepAuditStatusDto |
| `Domain\ValueObject\StaticStepResultVo` | → | `ChainExecution\Domain\ValueObject\` |
| `Infrastructure\Service\QualityGateRunner` | → | `ChainExecution\Infrastructure\Service\QualityGate\` |
| `Infrastructure\Service\ResolveChainRunnerService` | → | `ChainExecution\Infrastructure\Service\Agent\` |
| `Integration\Service\AgentRunner\RunAgentService` | → | **УДАЛИТЬ** — ChainExecution уже имеет свой Integration\RunAgentService |
| `Integration\Service\Audit\StaticAuditService` | → | **УДАЛИТЬ** — больше не нужен (AuditLoggerInterface используется напрямую) |
| `Integration\Service\Prompt\FormatPromptService` | → | `ChainExecution\Infrastructure\Service\Prompt\` (или Integration) |

### Integration-маппер: ChainExecution ← ChainDefinition

ChainExecution.Domain не импортирует ChainDefinition.Domain. Маппер переводит:

| ChainDefinition.Domain VO | → | ChainExecution.Domain VO |
|---|---|---|
| `StaticChainDefinitionVo` | → | `ExecutionStaticChainConfigVo` (новый VO) |
| `ConditionalChainDefinitionVo` | → | `ExecutionConditionalChainConfigVo` (новый VO) |
| `SharedChainDefinitionVo` | → | Извлечение полей: name, budget, timeout, maxTime, roles |
| `ChainStepVo` | → | `ExecutionStepVo` (новый VO) или использование через интерфейс |
| `BudgetVo` | → | `ExecutionBudgetVo` (новый VO) или inline-поля |
| `RoleConfigVo` | → | `ExecutionRoleConfigVo` (новый VO) или inline-поля |
| `FixIterationGroupVo` | → | `ExecutionFixIterationGroupVo` (новый VO) |
| `FallbackConfigVo` | → | `ExecutionFallbackConfigVo` (новый VO) |
| `QualityGateVo` | → | `ExecutionQualityGateVo` (новый VO) |
| `ChainRetryPolicyVo` | → | `ExecutionRetryPolicyVo` (новый VO) |
| `ConditionExpressionVo` | → | `ExecutionConditionExpressionVo` (новый VO) |
| `PromptConfigurationVo` | → | `ExecutionPromptConfigVo` (новый VO) |

**Integration-интерфейс в ChainExecution.Domain:**
```php
// ChainExecution\Domain\Service\Integration\ChainDefinitionProviderInterface.php
interface ChainDefinitionProviderInterface
{
    public function loadChainForExecution(string $chainName): ExecutionChainConfigVo;
}
```

### Текущее поведение
- `StaticExecution\Domain` импортирует 13 VO из `ChainDefinition\Domain` — прямое нарушение конвенции «Domain → никто»
- `StaticExecution\Integration\Service\AgentRunner\RunAgentService` делегирует в `ChainDefinition\Integration\Service\AgentRunner\RunAgentService`
- `StaticExecution\Integration\Service\Audit\StaticAuditService` маппит StaticStepResultVo → ChainRunResultVo
- `StaticExecution\Integration\Service\Prompt\FormatPromptService` делегирует в ChainDefinition.Infrastructure

### Границы (Out of Scope)
- ❌ Не трогаем DynamicLoop
- ❌ Не трогаем CLI-команды (apps/console) — только обновляем импорты
- ❌ Не удаляем ChainDefinitionVo (legacy)
- ❌ Не меняем AuditLoggerInterface в ChainDefinition.Domain

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] Модуль `src/Module/ChainExecution/` создан (или ChainDefinition переименован) со слоями Domain/Application/Infrastructure/Integration
- [ ] Модуль `src/Module/ChainDefinition/` содержит только Definition VO, enums, ChainDefinitionInterface, ChainDefinitionValidator, YamlChainLoader, query handlers
- [ ] Модуль `src/Module/StaticExecution/` **удалён**
- [ ] Все файлы StaticExecution перенесены в ChainExecution, namespace обновлён
- [ ] `ChainExecution\Domain` НЕ импортирует `ChainDefinition\Domain` (проверка grep)
- [ ] Созданы Execution-VO: `ExecutionStaticChainConfigVo`, `ExecutionConditionalChainConfigVo` (+ внутренние VO для steps, budget, roles, retryPolicy, conditions и др.)
- [ ] Создан `ChainDefinitionProviderInterface` (ChainExecution.Domain\Service\Integration\)
- [ ] Создан Integration-маппер `ChainExecution.Integration\Service\ChainDefinition\ChainExecutionDefinitionMapper`
- [ ] Удалены: `StaticAuditServiceInterface`, `StaticAuditService`, `StaticChainAuditVo`, `StaticStepAuditVo` — заменены прямой инъекцией `AuditLoggerInterface`
- [ ] Удалён StaticExecution Integration RunAgentService — ChainExecution имеет свой
- [ ] `RunStaticChainService` переписан: вместо ChainDefinition VO использует Execution VO
- [ ] `config/services.yaml` обновлён: ChainExecution services, alias-ы; StaticExecution удалён
- [ ] Все тесты перенесены/обновлены
- [ ] `vendor/bin/phpunit` → OK
- [ ] `vendor/bin/psalm` → OK

### 🟡 Should Have (Желательно)
- [ ] Тесты на Integration-маппер `ChainExecutionDefinitionMapper`
- [ ] Тесты на новые Execution VO

### 🟢 Could Have (Опционально)
- [ ] Unit-тесты на `ExecutionStaticChainConfigVo`, `ExecutionConditionalChainConfigVo`

### ⚫ Won't Have (Не будем делать)
- Не меняем ChainDefinition.Domain VO (Definition VO остаются как есть)
- Не трогаем DynamicLoop
- Не удаляем ChainDefinitionVo (legacy)
- Не меняем AuditLoggerInterface

## 4. Implementation Plan (План реализации)

1. [ ] Создать ветку `task/refactor-merge-static-execution` от `main`
2. [ ] **Разделить ChainDefinition на два модуля:**
   - Оставить `src/Module/ChainDefinition/` — только Definition: VO, enums, interfaces, validator, YamlChainLoader, query handlers (ListChains, LoadChain, ValidateChainConfig)
   - Создать `src/Module/ChainExecution/` — execution: strategies, handlers, execution VO, services, infrastructure, integration
3. [ ] Перенести execution-специфичные файлы из ChainDefinition в ChainExecution:
   - Strategies (3 файла), CommandHandler (OrchestrateChain, RunAgent), Query handlers (GenerateReport, GetRunners)
   - Domain VO: ChainRunRequestVo, ChainRunResultVo, FallbackAttemptVo, HookResultVo, QualityGateResultVo, ConditionalStepResultVo
   - Domain DTO: ChainResultAuditDto, StepAuditStatusDto
   - Domain services: EvaluateConditionService (+interface), HookExecutorInterface, ExecuteConditionalStepServiceInterface, PromptFormatterInterface, PromptProviderInterface, ResolveExitCodeServiceInterface
   - Infrastructure: ShellHookExecutor, ExecuteConditionalStepService, RolePromptBuilder, PromptFormatterService
   - Integration: RunAgentService, AgentDtoMapper
   - Events, Enums (OrchestrateExitCodeEnum, ReportFormatEnum), DTO/Mapper для reporting
4. [ ] Создать Execution VO (замена ChainDefinition VO):
   - `ExecutionStaticChainConfigVo`, `ExecutionConditionalChainConfigVo`
   - `ExecutionStepVo`, `ExecutionBudgetVo`, `ExecutionRoleConfigVo`, `ExecutionRetryPolicyVo`
   - `ExecutionFixIterationGroupVo`, `ExecutionFallbackConfigVo`, `ExecutionQualityGateVo`
   - `ExecutionConditionExpressionVo`, `ExecutionPromptConfigVo`
5. [ ] Создать `ChainDefinitionProviderInterface` в ChainExecution.Domain\Service\Integration\
6. [ ] Создать `ChainExecutionDefinitionMapper` в ChainExecution.Integration\Service\ChainDefinition\
7. [ ] Обновить стратегии: `StaticExecutionStrategy.execute()` получает `ExecutionStaticChainConfigVo` вместо `StaticChainDefinitionVo`; аналогично для `ConditionalExecutionStrategy`
8. [ ] **Влить StaticExecution в ChainExecution:**
   - Перенести StaticChainExecution entity → ChainExecution.Domain\Entity\
   - Перенести Static-сервисы (RunStaticChainService, ExecuteStaticStepService, CheckStaticBudgetService и др.) → ChainExecution.Domain\Service\Static\
   - Перенести Static VO (StaticChainResultVo, StaticStepResultVo, StaticProcessResultVo) → ChainExecution.Domain\ValueObject\
   - Обновить namespace всех перенесённых файлов
9. [ ] **Устранить StaticAuditServiceIntegration:**
   - `RunStaticChainService` инжектирует `AuditLoggerInterface` напрямую (как domain-сервис)
   - Удалить `StaticAuditServiceInterface`, `StaticAuditService`, `StaticChainAuditVo`, `StaticStepAuditVo`
   - Обновить `RunStaticChainService` — вместо `$auditService->logStepResult(StaticStepResultVo)` вызывать `$auditLogger->logStepResult(ChainRunResultVo)` с маппингом StaticStepResultVo → ChainRunResultVo внутри RunStaticChainService
10. [ ] **Устранить StaticExecution Integration RunAgentService:**
    - Удалить `StaticExecution\Integration\Service\AgentRunner\RunAgentService`
    - Удалить `StaticExecution\Domain\Service\RunAgentServiceInterface`
    - `ExecuteStaticStepService` инжектирует ChainExecution.Domain\Service\Integration\RunAgentServiceInterface напрямую
11. [ ] Перенести Infrastructure: QualityGateRunner, ResolveChainRunnerService → ChainExecution.Infrastructure\Service\
12. [ ] Перенести FormatPromptService → ChainExecution (Infrastructure или Integration)
13. [ ] Обновить `config/services.yaml`:
    - Добавить ChainExecution auto-discovery, alias-ы
    - Обновить тег execution_strategy
    - Удалить все StaticExecution-записи
14. [ ] Перенести/обновить тесты:
    - StaticExecution тесты → ChainExecution namespace
    - Обновить mock'и, stub'ы, импорты
15. [ ] Обновить импорты в apps/console (4 файла)
16. [ ] Удалить директорию `src/Module/StaticExecution/`
17. [ ] Запустить `vendor/bin/phpunit` + `vendor/bin/psalm`
18. [ ] Grep-проверки изоляции

## 5. Definition of Done (Критерии приёмки)

- [ ] Модуль `src/Module/ChainExecution/` существует
- [ ] Модуль `src/Module/StaticExecution/` **удалён**
- [ ] Модуль `src/Module/ChainDefinition/` содержит только Definition: VO, enums, interfaces, validator, loader, queries
- [ ] `grep -r 'ChainDefinition\\Domain' src/Module/ChainExecution/Domain/` → 0 результатов
- [ ] `grep -r 'Module\\StaticExecution\\' src/` → 0 результатов
- [ ] `grep -r 'Module\\StaticExecution\\' tests/` → 0 результатов
- [ ] `grep -r 'Module\\StaticExecution\\' config/` → 0 результатов
- [ ] `ChainDefinitionProviderInterface` создан, Integration-маппер реализует его
- [ ] `vendor/bin/phpunit` → OK
- [ ] `vendor/bin/psalm` → OK
- [ ] CLI `app:agent:orchestrate` работает для static/conditional/dynamic

## 6. Verification (Самопроверка)

```bash
# Изоляция Domain
grep -r 'ChainDefinition\\Domain' src/Module/ChainExecution/Domain/ --include='*.php'

# StaticExecution удалён
find src/Module/StaticExecution -type f 2>/dev/null | wc -l  # → 0

# Тесты
vendor/bin/phpunit
vendor/bin/psalm

# Функциональная проверка
vendor/bin/phpunit tests/Integration/Application/UseCase/Command/OrchestrateChain/StaticChainIntegrationTest.php
vendor/bin/phpunit tests/Integration/Application/UseCase/Command/OrchestrateChain/ConditionalChainIntegrationTest.php
```

## 7. Risks and Dependencies (Риски и зависимости)

### Риски

| Риск | Вероятность | Влияние | Митигация |
|------|-------------|---------|-----------|
| `RunStaticChainService` (224 LOC) использует 7+ VO из ChainDefinition.Domain — цена маппинга высокая | Высокая | Высокое | Детальный анализ до старта; при >12 VO для маппинга — рассмотреть упрощение (схлопнуть группы VO) |
| `ExecuteStaticStepService` использует `ChainRunRequestVo`, `ChainRunResultVo`, `FallbackAttemptVo`, `RoleConfigVo` — эти VO нужны и в ChainExecution, и в DynamicLoop | Средняя | Среднее | ChainExecution владеет ChainRunRequestVo/ChainRunResultVo; DynamicLoop имеет свои DynamicLoop-аналоги |
| Устранение StaticAuditService → RunStaticChainService должен маппить StaticStepResultVo → ChainRunResultVo inline | Высокая | Низкое | Добавить приватный метод mapper (~30 LOC) в RunStaticChainService |
| Объём Execution VO (~12 новых VO) — существенный бойлерплейт | — | Ожидаемое | ~800-1000 LOC новых VO + ~200 LOC маппер |
| ConditionalExecutionStrategy глубоко использует ChainStepVo, ConditionExpressionVo, SharedChainDefinitionVo | Высокая | Высокое | Все три VO маппятся в Execution VO через Integration |
| Integration-тесты (StaticChainIntegrationTest, ConditionalChainIntegrationTest) собирают контейнер со старыми namespace | Высокая | Среднее | Обновить тестовую конфигурацию и stub'ы |

### Зависимости
- **Зависит от:** TASK-refactor-namespace-chain-definition (завершена)
- **Зависит от (рекомендуется):** TASK-refactor-extract-dynamic-loop (чтобы работать с Clean ChainDefinition без Dynamic-файлов)
- **Блокирует:** TASK-refactor-deptrac-decomposition-rules

### Порядок относительно TASK-refactor-extract-dynamic-loop
Обе задачи (TASK-refactor-extract-dynamic-loop и TASK-refactor-merge-static-execution) зависят от TASK-refactor-namespace-chain-definition и могут выполняться параллельно после её завершения. Однако рекомендуется сначала выполнить TASK-refactor-extract-dynamic-loop (чтобы ChainDefinition после выделения DynamicLoop содержал только Definition + Execution-остатки, которые легче разделить).

## 8. Sources (Источники)

- [Brainstorm #6 protocol](../var/sessions/brainstorm/2026-05-04_01-59-17/discussion_history.md) — раунды 1-7
- [ADR-006: ExecutionStrategy](../docs/adr/006-execution-strategy-composition.md)
- [ADR-007: VO ACL Boundary](../docs/adr/007-vo-acl-boundary.md) — паттерн ACL для VO
- [ADR-009: Dynamic Split Decision](../docs/adr/009-dynamic-split-decision.md) — суперседируется
- [Конвенции: layers.md](../docs/conventions/layers/layers.md) — Domain → никто
- [Конвенции: integration.md](../docs/conventions/layers/integration.md)

## 9. Comments (Комментарии)

### Почему StaticExecution вливается, а DynamicLoop выделяется
StaticExecution (1797 LOC) — это реализация execution bounded context: линейное выполнение шагов, retry, budget, audit. По ответственности он тождествен conditional execution. Выделение его в отдельный модуль было артефактом декомпозиции по типу стратегии, а не по ответственности. DynamicLoop (3000 LOC) — принципиально другой bounded context (фасилитаторный многораундовый диалог), который заслуживает отдельного модуля.

### Почему ChainDefinition → ChainExecution (rename)
После выделения DynamicLoop и перенос execution-логики в отдельный модуль, оставшийся ChainDefinition содержит только Definition VO, enums, interfaces, validator, YamlChainLoader и query handlers — это чистый Definition bounded context. Execution bounded context получает новое имя ChainExecution.

### Scope новых VO (оценка)
- ExecutionStaticChainConfigVo — ~180 LOC
- ExecutionConditionalChainConfigVo — ~160 LOC
- ExecutionStepVo — ~100 LOC
- ExecutionBudgetVo — ~60 LOC
- ExecutionRoleConfigVo — ~50 LOC
- ExecutionRetryPolicyVo — ~50 LOC
- ExecutionFixIterationGroupVo — ~40 LOC
- ExecutionFallbackConfigVo — ~40 LOC
- ExecutionQualityGateVo — ~60 LOC
- ExecutionConditionExpressionVo — ~50 LOC
- ExecutionPromptConfigVo — ~50 LOC
- ChainDefinitionProviderInterface — ~20 LOC
- ChainExecutionDefinitionMapper — ~250 LOC
- Итого: ~1110 LOC бойлерплейта

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-04 | Аналитик (Шерлок) | Создание задачи |
