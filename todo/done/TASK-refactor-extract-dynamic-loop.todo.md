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
branch: task/refactor-extract-dynamic-loop
pr: '#148'
status: done
---

# TASK-refactor-extract-dynamic-loop: Выделение модуля DynamicLoop из ChainDefinition

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда DynamicLoop (entity, 9 VO, 11+ domain-сервисов, session/audit/budget инфраструктура) составляет отдельный bounded context внутри модуля ChainDefinition, я хочу выделить его в изолированный модуль `DynamicLoop`, чтобы Domain DynamicLoop не зависел от Domain ChainDefinition, а взаимодействие шло только через Integration-слой.

### Goal (Цель по SMART)
Создать модуль `src/Module/DynamicLoop/` (~3000 LOC), перенести все Dynamic-специфичные файлы (entity, VO, сервисы, infrastructure, стратегии), создать Integration-маппер DynamicLoop ← ChainDefinition и `DynamicLoopAuditLoggerInterface`. После выделения: `DynamicLoop\Domain` не импортирует `ChainDefinition\Domain` (проверяется grep).

## 2. Context and Scope (Контекст и Границы)

### Где делаем

**Новый модуль:**
```
src/Module/DynamicLoop/
├── Domain/
│   ├── Entity/
│   │   └── DynamicLoopExecution.php                          ← перенос из ChainDefinition
│   ├── ValueObject/
│   │   ├── ChainSessionStateVo.php                            ← перенос из ChainDefinition
│   │   ├── ChainTurnResultVo.php                              ← перенос из ChainDefinition
│   │   ├── DynamicBudgetCheckVo.php                           ← перенос из ChainDefinition
│   │   ├── DynamicChainContextVo.php                           ← перенос из ChainDefinition
│   │   ├── DynamicLoopResultVo.php                            ← перенос из ChainDefinition
│   │   ├── DynamicRoundResultVo.php                           ← перенос из ChainDefinition
│   │   ├── FacilitatorResponseVo.php                          ← перенос из ChainDefinition
│   │   ├── FacilitatorTurnResultVo.php                        ← перенос из ChainDefinition
│   │   ├── TurnBreakVo.php                                    ← перенос из ChainDefinition
│   │   └── TurnContinueVo.php                                 ← перенос из ChainDefinition
│   └── Service/
│       ├── Audit/
│       │   └── DynamicLoopAuditLoggerInterface.php            ← СОЗДАЁТСЯ (новый)
│       ├── Dynamic/
│       │   ├── BuildDynamicContextServiceInterface.php        ← перенос из ChainDefinition
│       │   ├── BuildDynamicContextService.php                 ← перенос из ChainDefinition
│       │   ├── CheckDynamicLoopBudgetServiceInterface.php    ← перенос из ChainDefinition
│       │   ├── CheckDynamicLoopBudgetService.php              ← перенос из ChainDefinition
│       │   ├── ExecuteDynamicTurnServiceInterface.php        ← перенос из ChainDefinition
│       │   ├── ExecuteDynamicTurnService.php                  ← перенос из ChainDefinition
│       │   ├── FacilitatorResponseParserInterface.php        ← перенос из ChainDefinition
│       │   ├── FinalizeDynamicLoopServiceInterface.php       ← перенос из ChainDefinition
│       │   ├── FinalizeDynamicLoopService.php                ← перенос из ChainDefinition
│       │   ├── FormatDynamicJournalServiceInterface.php      ← перенос из ChainDefinition
│       │   ├── FormatDynamicJournalService.php               ← перенос из ChainDefinition
│       │   ├── RecordDynamicRoundServiceInterface.php        ← перенос из ChainDefinition
│       │   ├── RecordDynamicRoundService.php                 ← перенос из ChainDefinition
│       │   ├── RoundCompletedNotifierInterface.php            ← перенос из ChainDefinition
│       │   ├── RunDynamicLoopAgentServiceInterface.php       ← перенос из ChainDefinition
│       │   ├── RunDynamicLoopServiceInterface.php            ← перенос из ChainDefinition
│       │   ├── RunDynamicLoopService.php                     ← перенос из ChainDefinition
│       │   └── SessionCompletedNotifierInterface.php          ← перенос из ChainDefinition
│       ├── Session/
│       │   ├── ChainSessionLoggerInterface.php                ← перенос из ChainDefinition
│       │   ├── ChainSessionReaderInterface.php                ← перенос из ChainDefinition
│       │   └── ChainSessionWriterInterface.php                ← перенос из ChainDefinition
│       └── Budget/
│           └── CheckDynamicBudgetServiceInterface.php         ← перенос из ChainDefinition
├── Application/
│   ├── Service/
│   │   ├── DynamicExecutionStrategy.php                       ← перенос из ChainDefinition
│   │   ├── DispatchRoundEventService.php                     ← перенос из ChainDefinition
│   │   └── DispatchSessionCompletedEventService.php          ← перенос из ChainDefinition
│   └── UseCase/Command/OrchestrateChain/
│       ├── DynamicLoopResultDto.php                          ← перенос из ChainDefinition
│       ├── DynamicRoundResultDto.php                         ← перенос из ChainDefinition
│       └── FacilitatorTurnResultDto.php                      ← перенос из ChainDefinition
├── Infrastructure/
│   ├── Service/
│   │   ├── ChainSessionLogger.php                            ← перенос из ChainDefinition
│   │   ├── ChainSessionWriter.php                            ← перенос из ChainDefinition (в ChainSessionLogger?)
│   │   ├── ChainSessionReader.php                            ← перенос из ChainDefinition
│   │   ├── ChainSessionFileStorage.php                      ← перенос из ChainDefinition
│   │   ├── ChainSessionBudgetFormatter.php                  ← перенос из ChainDefinition
│   │   ├── CheckDynamicBudgetService.php                    ← перенос из ChainDefinition
│   │   ├── FacilitatorResponseParserService.php             ← перенос из ChainDefinition
│   │   ├── RunDynamicLoopAgentService.php                   ← перенос из ChainDefinition
│   │   ├── JsonlAuditLogger.php                             ← перенос из ChainDefinition (реализует DynamicLoopAuditLoggerInterface)
│   │   └── JsonlAuditLoggerFactory.php                      ← перенос из ChainDefinition
│   └── Service/Prompt/
│       └── PromptFormatterService.php                        ← перенос из ChainDefinition
├── Integration/
│   └── Service/
│       ├── ChainDefinition/
│       │   └── DynamicLoopDefinitionMapper.php               ← СОЗДАЁТСЯ (новый)
│       └── AgentRunner/
│           ├── RunAgentService.php                            ← перенос из ChainDefinition
│           └── AgentDtoMapper.php                             ← перенос из ChainDefinition
```

### Текущее поведение
Все Dynamic-файлы находятся в `src/Module/ChainDefinition/` (после TASK-refactor-namespace-chain-definition). DynamicExecutionStrategy делает `assert($chain instanceof DynamicChainDefinitionVo)` — hard dependency на ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo.

### Границы (Out of Scope)
- ❌ Не меняем ChainDefinition.Domain (только убираем из него Dynamic-файлы)
- ❌ Не трогаем StaticExecution
- ❌ Не трогаем Conditional-логику (остаётся в ChainDefinition)
- ❌ Не трогаем CLI-команды (apps/console) — только обновляем импорты
- ❌ Не удаляем ChainDefinitionVo (legacy)
- ❌ Не меняем AuditLoggerInterface в ChainDefinition.Domain (он остаётся для static/conditional, DynamicLoop получит свой)

### Ключевые контракты

#### Integration-маппер: DynamicLoop ← ChainDefinition

DynamicLoop.Domain не импортирует ChainDefinition.Domain. Вместо этого Integration-маппер переводит типы:

| ChainDefinition.Domain VO | → | DynamicLoop.Domain VO (или тип) |
|---|---|---|
| `DynamicChainDefinitionVo` | → | `DynamicLoopConfigVo` (новый VO в DynamicLoop.Domain) |
| `SharedChainDefinitionVo` | → | Извлечение полей: name, budget, timeout, maxTime, roles, roleConfig |
| `BudgetVo` | → | `DynamicLoopBudgetVo` (новый VO) или inline-поля в `DynamicLoopConfigVo` |
| `RoleConfigVo` | → | `DynamicLoopRoleConfigVo` (новый VO) или inline |

**Integration-интерфейс в DynamicLoop.Domain:**
```php
// DynamicLoop\Domain\Service\Integration\ChainDefinitionProviderInterface.php
interface ChainDefinitionProviderInterface
{
    public function loadDynamicChainConfig(string $chainName): DynamicLoopConfigVo;
}
```

**Integration-реализация:**
```php
// DynamicLoop\Integration\Service\ChainDefinition\DynamicLoopDefinitionMapper.php
// Вызывает ChainDefinition\Application\UseCase\Query\LoadChain\LoadChainQueryHandler
// Маппит DynamicChainDefinitionVo → DynamicLoopConfigVo
```

#### DynamicLoopAuditLoggerInterface

Проблема: `AuditLoggerInterface` (ChainDefinition.Domain) использует `ChainRunResultVo` в сигнатуре. Если AuditLoggerInterface останется в ChainDefinition.Domain — DynamicLoop.Domain будет зависеть от ChainDefinition.Domain.

**Решение:** DynamicLoop.Domain определяет свой интерфейс:

```php
// DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerInterface.php
interface DynamicLoopAuditLoggerInterface
{
    public function logChainStart(string $chainName, string $task): void;
    public function logStepStart(string $chainName, int $stepNumber, string $role, string $runner): void;
    public function logStepResult(string $chainName, int $stepNumber, string $role, string $runner, DynamicRoundResultVo $result, float $durationMs): void;
    public function logChainResult(DynamicLoopResultVo $result, string $completionReason): void;
}
```

`JsonlAuditLogger` (Infrastructure) реализует **оба** интерфейса: `AuditLoggerInterface` (для static/conditional) и `DynamicLoopAuditLoggerInterface` (для dynamic).

#### RunDynamicLoopServiceInterface — адаптация

Текущий `RunDynamicLoopServiceInterface` импортирует `DynamicChainDefinitionVo` и `DynamicChainContextVo` из ChainDefinition.Domain. После выделения:
- `DynamicChainContextVo` → переносится в DynamicLoop.Domain (уже в scope)
- `DynamicChainDefinitionVo` → заменяется на `DynamicLoopConfigVo` (создаётся в DynamicLoop.Domain)

#### Integration: DynamicLoop ← AgentRunner

`RunAgentService` и `AgentDtoMapper` переносятся в `DynamicLoop\Integration\Service\AgentRunner\`. Они используют `ChainRunRequestVo`/`ChainRunResultVo` из ChainDefinition.Domain — **это нарушение**, требующее создания DynamicLoop-собственных VO (`DynamicLoopRunRequestVo`, `DynamicLoopRunResultVo`) или признания, что эти VO должны жить в DynamicLoop.Domain (если они используются только Dynamic-кодом).

**Анализ:** `ChainRunRequestVo` и `ChainRunResultVo` используются в:
- DynamicLoop: `RunDynamicLoopAgentServiceInterface`, `ExecuteDynamicTurnService`, `RecordDynamicRoundService`
- ChainDefinition: `RunAgentServiceInterface` (для static/conditional)
- StaticExecution: `RunAgentServiceInterface` (импорт из ChainDefinition.Domain)

**Решение:** Создать `DynamicLoop\Domain\ValueObject\DynamicLoopRunRequestVo` и `DynamicLoop\Domain\ValueObject\DynamicLoopRunResultVo` в DynamicLoop.Domain. Integration-маппер `AgentDtoMapper` в DynamicLoop маппит эти VO → AgentRunner Application DTO. ChainDefinition.Domain сохраняет свои `ChainRunRequestVo`/`ChainRunResultVo` для static/conditional.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] Модуль `src/Module/DynamicLoop/` создан со слоями Domain/Application/Infrastructure/Integration
- [ ] Все Dynamic-специфичные файлы перенесены из ChainDefinition в DynamicLoop (namespace обновлён)
- [ ] `DynamicLoop\Domain` НЕ импортирует `ChainDefinition\Domain` (проверка grep)
- [ ] Создан `DynamicLoopConfigVo` (DynamicLoop.Domain) — копия полей `DynamicChainDefinitionVo`, без зависимости от ChainDefinition VO
- [ ] Создан `ChainDefinitionProviderInterface` (DynamicLoop.Domain\Service\Integration) — Integration-интерфейс для загрузки конфигурации
- [ ] Создан `DynamicLoopDefinitionMapper` (DynamicLoop.Integration\Service\ChainDefinition\) — Integration-маппер
- [ ] Создан `DynamicLoopAuditLoggerInterface` (DynamicLoop.Domain\Service\Audit\)
- [ ] `JsonlAuditLogger` реализует оба интерфейса: `AuditLoggerInterface` + `DynamicLoopAuditLoggerInterface`
- [ ] Созданы `DynamicLoopRunRequestVo` и `DynamicLoopRunResultVo` (DynamicLoop.Domain)
- [ ] `DynamicLoop\Integration\Service\AgentRunner\` — маппит DynamicLoop VO → AgentRunner DTO
- [ ] `config/services.yaml` обновлён: DynamicLoop services, alias-ы, тег execution_strategy
- [ ] Все тесты перенесены/обновлены: namespace DynamicLoop, mock'и обновлены
- [ ] `vendor/bin/phpunit` → OK
- [ ] `vendor/bin/psalm` → OK

### 🟡 Should Have (Желательно)
- [ ] Тесты на Integration-маппер `DynamicLoopDefinitionMapper`
- [ ] Тесты на `DynamicLoopAuditLoggerInterface` → `JsonlAuditLogger` реализацию

### 🟢 Could Have (Опционально)
- [ ] Unit-тесты на `DynamicLoopConfigVo`

### ⚫ Won't Have (Не будем делать)
- Не меняем ChainDefinition.Domain VO (BudgetVo, SharedChainDefinitionVo и др. остаются как есть)
- Не удаляем AuditLoggerInterface из ChainDefinition.Domain (он нужен для static/conditional)
- Не трогаем StaticExecution
- Не трогаем ConditionalExecutionStrategy

## 4. Implementation Plan (План реализации)

1. [ ] Создать ветку `task/refactor-extract-dynamic-loop` от `main`
2. [ ] Создать структуру каталогов `src/Module/DynamicLoop/{Domain,Application,Infrastructure,Integration}/`
3. [ ] Перенести Dynamic entity + VO (10 файлов) → DynamicLoop.Domain, обновить namespace
4. [ ] Перенести Dynamic domain-сервисы (18 файлов: интерфейсы + реализации) → DynamicLoop.Domain\Service\Dynamic\, обновить namespace
5. [ ] Перенести Session-интерфейсы (3 файла) → DynamicLoop.Domain\Service\Session\
6. [ ] Перенести Budget-интерфейс (1 файл) → DynamicLoop.Domain\Service\Budget\
7. [ ] Создать `DynamicLoopAuditLoggerInterface` в DynamicLoop.Domain\Service\Audit\
8. [ ] Создать `DynamicLoopConfigVo` в DynamicLoop.Domain\ValueObject\ (поля: facilitator, participants, maxRounds, budget, timeout, maxTime, name, roles, roleConfigs, promptConfiguration)
9. [ ] Создать `DynamicLoopRunRequestVo` + `DynamicLoopRunResultVo` в DynamicLoop.Domain\ValueObject\
10. [ ] Создать `ChainDefinitionProviderInterface` в DynamicLoop.Domain\Service\Integration\
11. [ ] Перенести `DynamicExecutionStrategy` → DynamicLoop.Application\Service\, обновить namespace + импорты (DynamicChainDefinitionVo → DynamicLoopConfigVo)
12. [ ] Перенести Dispatch-сервисы (2 файла) → DynamicLoop.Application\Service\
13. [ ] Перенести Dynamic DTO (3 файла) → DynamicLoop.Application\UseCase\Command\
14. [ ] Перенести Infrastructure-сервисы (11 файлов) → DynamicLoop.Infrastructure\Service\, обновить namespace + реализацию DynamicLoopAuditLoggerInterface
15. [ ] Создать `DynamicLoopDefinitionMapper` в DynamicLoop.Integration\Service\ChainDefinition\
16. [ ] Перенести `RunAgentService` + `AgentDtoMapper` → DynamicLoop.Integration\Service\AgentRunner\, обновить на DynamicLoop VO
17. [ ] Обновить `config/services.yaml`: добавить DynamicLoop auto-discovery, alias-ы, обновить тег execution_strategy
18. [ ] Перенести/обновить тесты: Dynamic-тесты → namespace DynamicLoop
19. [ ] Обновить импорты в ChainDefinition (удалить Dynamic-файлы из namespace)
20. [ ] Обновить импорты в apps/console (DynamicExecutionStrategy namespace)
21. [ ] Запустить `vendor/bin/phpunit` + `vendor/bin/psalm`
22. [ ] Grep-проверка: `grep -r 'ChainDefinition\\Domain' src/Module/DynamicLoop/Domain/` → пусто

## 5. Definition of Done (Критерии приёмки)

- [ ] Модуль `src/Module/DynamicLoop/` существует со слоями Domain/Application/Infrastructure/Integration
- [ ] `grep -r 'ChainDefinition\\Domain' src/Module/DynamicLoop/Domain/` → 0 результатов
- [ ] `grep -r 'ChainDefinition\\Domain' src/Module/DynamicLoop/Application/` → 0 результатов (Application тоже не зависит напрямую)
- [ ] `DynamicLoopConfigVo` создан, `DynamicExecutionStrategy` использует его вместо `DynamicChainDefinitionVo`
- [ ] `DynamicLoopAuditLoggerInterface` создан, `JsonlAuditLogger` реализует оба интерфейса
- [ ] `ChainDefinitionProviderInterface` создан, `DynamicLoopDefinitionMapper` реализует его
- [ ] `vendor/bin/phpunit` → OK
- [ ] `vendor/bin/psalm` → OK
- [ ] CLI `app:agent:orchestrate --chain=dynamic_test_chain` работает

## 6. Verification (Самопроверка)

```bash
# Проверка изоляции Domain
grep -r 'ChainDefinition\\Domain' src/Module/DynamicLoop/Domain/ --include='*.php'
grep -r 'ChainDefinition\\Domain' src/Module/DynamicLoop/Application/ --include='*.php'

# Тесты
vendor/bin/phpunit
vendor/bin/psalm

# Функциональная проверка
# Запустить integration-тест DynamicChainIntegrationTest
vendor/bin/phpunit tests/Integration/Application/UseCase/Command/OrchestrateChain/DynamicChainIntegrationTest.php
```

## 7. Risks and Dependencies (Риски и зависимости)

### Риски

| Риск | Вероятность | Влияние | Митигация |
|------|-------------|---------|-----------|
| `RunDynamicLoopService` (210 LOC) глубоко завязан на `DynamicChainDefinitionVo` — цена маппинга выше оценки | Средняя | Среднее | Детальный анализ `RunDynamicLoopService::execute()` перед стартом; если нужно >10 полей — расширить DynamicLoopConfigVo |
| `DynamicChainContextVo` содержит `PromptConfigurationVo` (ChainDefinition VO) — circular dependency | Средняя | Высокое | Либо создать `DynamicLoopPromptConfigVo` (дублирование), либо извлечь поля inline в DynamicLoopConfigVo |
| `RecordDynamicRoundService` создаёт `ChainRunResultVo::createFromError()` / `createFromSuccess()` — фабричные методы чужого VO | Высокая | Высокое | Заменить на `DynamicLoopRunResultVo` с аналогичными named constructors |
| Integration-тесты DynamicChainIntegrationTest собирают контейнер с ChainDefinition — нужны обновления stub'ов | Высокая | Среднее | Обновить stub-сервисы и конфигурацию теста |
| Объём новых VO (DynamicLoopConfigVo, DynamicLoopRunRequestVo, DynamicLoopRunResultVo, DynamicLoopBudgetVo, DynamicLoopRoleConfigVo, DynamicLoopPromptConfigVo) — 6 новых VO | — | Ожидаемое | ~300-400 LOC бойлерплейта, конечная цена |

### Зависимости
- **Зависит от:** TASK-refactor-namespace-chain-definition (должна быть завершена; работать с namespace ChainDefinition)
- **Блокирует:** TASK-refactor-deptrac-decomposition-rules (нужен для Deptrac-правил)

## 8. Sources (Источники)

- [Brainstorm #6 protocol](../var/sessions/brainstorm/2026-05-04_01-59-17/discussion_history.md) — раунды 1-7: декомпозиция, AuditLogger, Shared Kernel
- [ADR-006: ExecutionStrategy](../docs/adr/006-execution-strategy-composition.md) — контракт стратегий
- [ADR-007: VO ACL Boundary](../docs/adr/007-vo-acl-boundary.md) — паттерн ACL для VO
- [ADR-009: Dynamic Split Decision](../docs/adr/009-dynamic-split-decision.md) — суперседируется
- [Конвенции: layers.md](../docs/conventions/layers/layers.md) — Domain → никто
- [Конвенции: integration.md](../docs/conventions/layers/integration.md) — Integration → Application, Domain (контракты)

## 9. Comments (Комментарии)

### Связь с ADR-009
ADR-009 принял решение «Dynamic остаётся в Orchestrator». Данный эпик (EPIC-refactor-responsibility-decomposition) суперседирует ADR-009, так как изменились вводные: вместо декомпозиции по стратегии применяем декомпозицию по ответственности, и DynamicLoop — отдельный bounded context.

### AuditLoggerInterface — архитектурный паттерн
Разделение AuditLoggerInterface на два модульных интерфейса (ChainDefinition + DynamicLoop) с одной Infrastructure-реализацией (`JsonlAuditLogger`) — это паттерн **Port/Adapter**: каждый модуль определяет свой Port (интерфейс), Infrastructure предоставляет один Adapter (класс), реализующий оба Port'а.

### Scope новых VO (оценка)
- `DynamicLoopConfigVo` — ~150 LOC (facilitator, participants, maxRounds, shared fields)
- `DynamicLoopRunRequestVo` — ~100 LOC (runner, prompt, context, timeout, retryPolicy)
- `DynamicLoopRunResultVo` — ~120 LOC (output, tokens, cost, error, timedOut + named constructors)
- `ChainDefinitionProviderInterface` — ~20 LOC
- `DynamicLoopDefinitionMapper` — ~180 LOC (маппинг ChainDefinition VO → DynamicLoop VO)
- `DynamicLoopAuditLoggerInterface` — ~30 LOC
- Итого новых файлов: ~600 LOC

## Инструкции для сабагента

**Ветка:** task/refactor-extract-dynamic-loop (уже создана и активна)
**PR:** будет создан после реализации

### Порядок действий
1. Переключись в ветку `task/refactor-extract-dynamic-loop`: `git checkout task/refactor-extract-dynamic-loop`
2. Реализуй задачу согласно описанию в todo/TASK-refactor-extract-dynamic-loop.todo.md
3. Следуй [Конвенциям](../docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `vendor/bin/phpunit` и `vendor/bin/psalm`.
6. Сделай `git push`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-04 | Аналитик (Шерлок) | Создание задачи |
