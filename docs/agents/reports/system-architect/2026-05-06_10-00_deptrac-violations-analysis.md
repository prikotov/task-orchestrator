# Анализ Deptrac-violations: классификация и рекомендации

**Роль:** Архитектор Гэндальф
**Дата:** 2026-05-06
**Объект:** 15 Deptrac-violations после EPIC-refactor-responsibility-decomposition (PR#2/#3)
**Задача:** TASK-refactor-deptrac-decomposition-rules — анализ, нужно ли менять Deptrac-правила или устранять violations рефакторингом кода

---

## Контекст

После разделения монолитного модуля Orchestrator (11 573 LOC) на три модуля (ChainDefinition, ChainExecution, DynamicLoop) + AgentRunner, Deptrac обнаружил **15 violations**. Два кастомных правила из `prikotov/coding-standard`:

1. **CrossModuleDomainRule** — запрещает любые межмодульные зависимости, кроме `Integration → foreign Application`
2. **ServiceContractDependencyRule** — запрещает межмодульные сервисные зависимости и проверяет слой-соответствие при обращении к сервисным интерфейсам

Текущий `depfile.yaml` в корне проекта — минимальный, только импортирует базовые правила. Нарушать правила «локально» через skip_violations — не наш путь.

---

## Классификация Violations

### Группа 1: Presentation → чужой Application-сервис (1 violation)

| # | Depender | Dependent | Правило |
|---|----------|-----------|---------|
| 1 | `Console\Module\Orchestrator\Command\OrchestrateCommand` (Presentation) | `ChainExecution\Application\Service\ResolveExitCodeServiceInterface` | ServiceContractDependencyRule |

**Категория: A — устранить рефакторингом кода**

**Анализ:** `OrchestrateCommand` (Console/Presentation) инжектирует `ResolveExitCodeServiceInterface` из модуля ChainExecution. Presentation зависит от Application чужого модуля через сервисный интерфейс — `ServiceContractDependencyRule` это запрещает: Presentation-класс не входит в структуру `Common\Module\...` и не может зависеть от модульного сервиса.

**Решение:** Вынести `ResolveExitCodeServiceInterface` и его реализацию на уровень общего Application-слоя (например, `Common\Application\Service\`) либо создать facade/delegate в Console-приложении. Альтернатива — передавать exit code напрямую из `OrchestrateChainResultDto` без отдельного сервисного вызова: результат оркестрации уже содержит всё необходимое для определения exit code в Presentation.

**Оценка сложности:** 3/10 — локальное изменение, один класс.

---

### Группа 2: ChainExecution → AgentRunner через Application QueryHandler (2 violations)

| # | Depender | Dependent | Правило |
|---|----------|-----------|---------|
| 2 | `ChainExecution\Application\UseCase\Query\GetRunners\GetRunnersQueryHandler` | `AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQueryHandler` | CrossModuleDomainRule |
| 3 | `ChainExecution\Application\UseCase\Query\GetRunners\GetRunnersQueryHandler` | `AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQuery` | CrossModuleDomainRule |

**Категория: A — устранить рефакторингом кода**

**Анализ:** `GetRunnersQueryHandler` в ChainExecution делегирует вызов `GetRunnersQueryHandler` из AgentRunner. Это Application → Application между модулями, что запрещено. Единственный легальный путь — через Integration-слой.

**Решение:** Перенести `GetRunnersQueryHandler` из `ChainExecution\Application` в `ChainExecution\Integration\Listener\` (или `Integration\Service\`), откуда он будет вызывать AgentRunner через Integration → foreign Application. Либо: ChainExecution определяет собственный интерфейс `GetRunnersProviderInterface` в Domain\Service\Integration, а Integration-реализация делегирует в AgentRunner.Application.

**Оценка сложности:** 4/10 — паттерн Integration-маппера уже существует в проекте.

---

### Группа 3: Integration → ChainDefinition.Domain (2 violations)

| # | Depender | Dependent | Правило |
|---|----------|-----------|---------|
| 4 | `ChainExecution\Integration\Service\ChainDefinition\ChainExecutionDefinitionMapper` | `ChainDefinition\Domain\Contract\Chain\ChainLoaderInterface` | CrossModuleDomainRule |
| 5 | `DynamicLoop\Integration\Service\ChainDefinition\DynamicLoopDefinitionMapper` | `ChainDefinition\Domain\Contract\Chain\ChainLoaderInterface` | CrossModuleDomainRule |

**Категория: B — требуется корректировка правил**

**Анализ:** Оба маппера — Integration-слой ACL (Anti-Corruption Layer). Они реализуют порты `ChainDefinitionProviderInterface` из своего Domain\Service\Integration и транслируют ChainDefinition VO в собственные VO. Это **правильная** архитектура — Integration-маппер **должен** обращаться к чужому Domain для загрузки данных.

Однако `CrossModuleDomainRule` разрешает только `Integration → foreign Application`. Для ACL-маппера, который читает данные из чужого Domain (интерфейс + VO), это слишком строгое ограничение. Конвенция `layers.md` подтверждает: Integration может использовать «контракты и типы Domain (интерфейсы сервисов, VO/Enum/доменные DTO в сигнатурах)».

**Замечание:** `ChainLoaderInterface` находится в `ChainDefinition\Domain\Contract\Chain\`, что является Domain-интерфейсом. VO-зависимости ChainDefinition.Domain (BudgetVo, ChainStepVo и т.д.) уже исключены правилом `isSharedDataType()` — но `isSharedDataType()` проверяет только `ValueObject\`, `Enum\` и `Dto$`, а Contract-интерфейсы в `Domain\Contract\` не попадают под исключение.

**Решение:** Добавить в `CrossModuleDomainRule` исключение: `Integration → foreign Domain` разрешено, но **только для типов в `Domain\Contract\` и `Domain\Service\Integration\`** (порты, определённые для межмодульного взаимодействия). Это согласуется с конвенцией: Integration может обращаться к Domain другого модуля через контракты.

**Альтернатива (более чистая):** Определить «Integration-порты» в ChainDefinition.Application (а не Domain), но это нарушит DDD-принцип, что порты принадлежат Domain. Поэтому корректировка правила предпочтительнее.

**Оценка сложности:** 2/10 — добавить одно условие в CrossModuleDomainRule.

---

### Группа 4: DynamicLoop.Application → ChainExecution.Application (6 violations)

| # | Depender | Dependent | Правило |
|---|----------|-----------|---------|
| 6 | `DynamicLoop\Application\Service\DispatchRoundEventService` | `ChainExecution\Application\Event\OrchestrateRoundCompletedEvent` | CrossModuleDomainRule |
| 7 | `DynamicLoop\Application\Service\DispatchSessionCompletedEventService` | `ChainExecution\Application\Event\OrchestrateSessionCompletedEvent` | CrossModuleDomainRule |
| 8 | `DynamicLoop\Application\Service\DynamicExecutionStrategy` | `ChainExecution\Application\Contract\Chain\ExecutionStrategyInterface` | CrossModuleDomainRule |
| 9 | `DynamicLoop\Application\Service\DynamicExecutionStrategy` | `ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand` | CrossModuleDomainRule |
| 10 | `DynamicExecutionStrategy` → через `ExecutionStrategyInterface` | `OrchestrateChainCommand` (transitive) | CrossModuleDomainRule |
| 11 | `DynamicExecutionStrategy` → через `ExecutionStrategyInterface` | `OrchestrateChainCommand` (transitive, line 32) | CrossModuleDomainRule |

**Категория: A — устранить рефакторингом кода**

**Анализ:** Это самая серьёзная группа нарушений. DynamicLoop.Application напрямую зависит от ChainExecution.Application:

1. **DispatchRoundEventService** и **DispatchSessionCompletedEventService** создают конкретные event-классы из ChainExecution.Application. Domain DynamicLoop определил порты `RoundCompletedNotifierInterface` и `SessionCompletedNotifierInterface` — это правильно. Но Application-реализация этих портов конструирует чужой Event — это cross-module Application → Application.

2. **DynamicExecutionStrategy** реализует `ExecutionStrategyInterface` из ChainExecution.Application\Contract и работает с `OrchestrateChainCommand`/`OrchestrateChainResultDto` из ChainExecution. Это Application → Application через контракт.

**Решение (рекомендуется по группам):**

**4a. Event dispatch (violations #6, #7):**

DynamicLoop.Domain уже имеет `RoundCompletedNotifierInterface` / `SessionCompletedNotifierInterface` — порты определены верно. Проблема в том, что Application-реализация создаёт конкретный `OrchestrateRoundCompletedEvent` из ChainExecution.

Решение: перенести `DispatchRoundEventService` и `DispatchSessionCompletedEventService` из `DynamicLoop\Application\Service\` в `DynamicLoop\Integration\Service\`. Integration-слой — единственный легальный путь для межмодульных зависимостей. Integration → foreign Application разрешено правилами.

**4b. ExecutionStrategy (violations #8–#11):**

`DynamicExecutionStrategy` реализует `ExecutionStrategyInterface` из ChainExecution.Application\Contract. Контракт уже аннотирован как «расположен в Contract, а не Service, чтобы ServiceContractDependencyRule не считал его cross-module сервисом». Но `CrossModuleDomainRule` всё равно ловит его, потому что проверяет модуль+слой, а не паттерн Contract.

Решение: перенести `DynamicExecutionStrategy` из `DynamicLoop\Application\Service\` в `DynamicLoop\Integration\Service\`. По архитектурной логике: стратегия динамического выполнения — это Integration-адаптер, который реализует контракт ChainExecution и связывает DynamicLoop с ChainExecution. Это делает зависимость `Integration → foreign Application` легальной.

**Оценка сложности:** 6/10 — перемещение нескольких классов между слоями + обновление DI-конфигурации.

---

### Группа 5: DynamicLoop.Infrastructure → ChainExecution.Domain (3 violations)

| # | Depender | Dependent | Правило |
|---|----------|-----------|---------|
| 12 | `DynamicLoop\Infrastructure\Service\JsonlAuditLogger` | `ChainExecution\Domain\Contract\Chain\Audit\AuditLoggerInterface` | CrossModuleDomainRule |
| 13 | `DynamicLoop\Infrastructure\Service\RunDynamicLoopAgentService` | `ChainExecution\Domain\Contract\Agent\RunAgentServiceInterface` | CrossModuleDomainRule |
| 14 | `DynamicLoop\Infrastructure\Service\RunDynamicLoopAgentService` | `ChainExecution\Domain\Contract\Prompt\PromptProviderInterface` | CrossModuleDomainRule |

**Категория: B (частично) — комбинация рефакторинга и корректировки правил**

**Анализ:** DynamicLoop.Infrastructure реализует интерфейсы из ChainExecution.Domain\Contract:

1. **JsonlAuditLogger** реализует `AuditLoggerInterface` из ChainExecution.Domain — один физический класс обслуживает оба модуля. Infrastructure → foreign Domain.Contract для реализации интерфейса — это стандартный Port/Adapter.

2. **RunDynamicLoopAgentService** реализует `RunAgentServiceInterface` и `PromptProviderInterface` из ChainExecution.Domain\Contract — маппит DynamicLoop VO в ChainExecution VO и делегирует запуск агента.

Это violations двух типов одновременно:
- **CrossModuleDomainRule** не разрешает Infrastructure → foreign Domain
- Но `ServiceContractDependencyRule` разрешает Infrastructure реализовывать Domain-интерфейсы **внутри модуля**. Межмодульная реализация — отдельный вопрос.

**Архитектурная дилемма:** ChainExecution.Domain\Contract содержит интерфейсы, которые реализуются в двух местах:
- ChainExecution.Infrastructure (для static/conditional)
- DynamicLoop.Infrastructure (для dynamic)

Это означает, что контракты ChainExecution.Domain являются de facto **общими портами** для нескольких модулей.

**Решение (варианты):**

**Вариант B1 — Корректировка правила (рекомендую):** Добавить в `CrossModuleDomainRule` исключение: `Infrastructure → foreign Domain\Contract\` разрешено (реализация Port'ов через Adapter в чужом модуле). Это согласуется с Port/Adapter-паттерном и конвенцией `Infrastructure → Domain (контракты)`.

**Вариант B2 — Рефакторинг:** Перенести общие контракты (`AuditLoggerInterface`, `RunAgentServiceInterface`, `PromptProviderInterface`) в отдельный shared-модуль или в `Common\Domain\Shared\Contract\`. Но это создаёт новый модуль и может быть избыточным.

**Вариант B3 — Перемещение реализаций:** Перенести `JsonlAuditLogger` и `RunDynamicLoopAgentService` в ChainExecution.Infrastructure (модуль-владелец контрактов). Но тогда DynamicLoop.Infrastructure зависит от ChainExecution.Infrastructure, что хуже.

**Рекомендация:** Вариант B1 — Infrastructure → foreign Domain\Contract — это легальный Port/Adapter. Правило нужно скорректировать.

**Оценка сложности:** 2/10 (корректировка правила) или 7/10 (рефакторинг).

---

## Итоговая классификация

| Категория | Количество | Описание |
|-----------|-----------|----------|
| **A — рефакторинг кода** | **8 violations** | #1, #2, #3, #6, #7, #8, #9, #10–#11 |
| **B — корректировка правил** | **7 violations** | #4, #5, #12, #13, #14 + косвенно #8–#11 (после перемещения в Integration) |

---

## Заключение: прав ли Тимлид?

**Частично прав, частично нет.**

### В чём Тимлид прав ✅

1. **Основные правила Deptrac трогать НЕ нужно** — `CrossModuleDomainRule` и `ServiceContractDependencyRule` реализуют правильную архитектурную политику. Базовый `depfile.yaml` не требует изменений.

2. **Большинство violations (8 из 15) — реальные архитектурные долги**, которые нужно устранять рефакторингом:
   - Presentation, зависящий от чужого сервиса → вынести в общий слой
   - Application → чужой Application → перенести в Integration
   - DynamicLoop.Application, реализующий контракт ChainExecution → перенести в DynamicLoop.Integration

### В чём Тимлид не прав ❌

1. **Интеграция через ACL-мапперы (violations #4, #5) — Infrastructure → foreign Domain.Contract — требует корректировки правила.** Integration-мапперы, реализующие `ChainDefinitionProviderInterface`, **должны** иметь доступ к чужому Domain\Contract — это их прямое назначение (ACL). Текущее правило разрешает только `Integration → foreign Application`, но Integration-слой по конвенции `layers.md` может обращаться к Domain через «контракты и типы».

2. **Port/Adapter-паттерн между модулями (violations #12–#14) — Infrastructure, реализующая чужой Domain\Contract — тоже требует корректировки.** Когда ChainExecution.Domain\Contract определяет порт, а DynamicLoop.Infrastructure предоставляет Adapter — это правильная архитектура, запрещать её нельзя.

---

## Рекомендуемые изменения

### 1. Корректировка `CrossModuleDomainRule` (2 новых исключения)

```php
// Исключение 1: Integration → foreign Domain\Contract и Domain\Service\Integration
// Integration ACL-мапперы могут обращаться к чужим Domain-контрактам
if (
    $depender['layer'] === 'Integration' && $dependent['layer'] === 'Domain'
    && (str_starts_with($dependent['path'], 'Contract\\') 
        || str_starts_with($dependent['path'], 'Service\\Integration\\'))
) {
    return;
}

// Исключение 2: Infrastructure → foreign Domain\Contract (Port/Adapter)
// Adapter в одном модуле может реализовывать Port (интерфейс) из другого модуля
if (
    $depender['layer'] === 'Infrastructure' && $dependent['layer'] === 'Domain'
    && str_starts_with($dependent['path'], 'Contract\\')
) {
    return;
}
```

Эти два исключения закроют violations #4, #5, #12, #13, #14 (5 violations) — все являются легальными архитектурными паттернами.

### 2. Рефакторинг кода (8 violations)

| Violation | Действие |
|-----------|----------|
| #1 — OrchestrateCommand → ResolveExitCodeServiceInterface | Вынести resolve-логику в Presentation или общий Application-слой |
| #2, #3 — GetRunnersQueryHandler → AgentRunner.Application | Перенести в ChainExecution.Integration |
| #6, #7 — Dispatch*EventService | Перенести из DynamicLoop.Application в DynamicLoop.Integration |
| #8–#11 — DynamicExecutionStrategy | Перенести из DynamicLoop.Application в DynamicLoop.Integration |

### 3. Порядок выполнения

1. **Сначала** скорректировать `CrossModuleDomainRule` в `prikotov/coding-standard` — это закроет 5 violations
2. **Затем** выполнить рефакторинг кода для оставшихся 8 violations
3. **Параллельно** обновить DI-конфигурацию (services.yaml) после перемещения классов

---

## Риски и допущения

1. **Допущение:** Integration → foreign Domain.Contract считается легальным во всех случаях. Если появятся злоупотребления (Integration лезет в Domain.Entity), потребуется уточнение правила.
2. **Риск:** Перемещение DynamicExecutionStrategy в Integration может потребовать обновления существующих тестов.
3. **Риск:** Корректировка правила в `prikotov/coding-standard` — это отдельный пакет, нужен PR туда. До его мержа — использовать `skip_violations` для B-категории.

---

## Ссылки на источники 📚

1. `depfile.yaml` — конфигурация Deptrac проекта
2. `vendor/prikotov/coding-standard/config/deptrac/depfile.yaml` — базовые правила слоёв
3. `vendor/prikotov/coding-standard/src/Deptrac/CrossModuleDomainRule.php` — реализация правила
4. `docs/conventions/layers/layers.md` — конвенции взаимодействия слоёв
5. `docs/guide/architecture.md` — архитектурные принципы проекта
