# Архитектурный ревью: декомпозиция Orchestrator → {ChainDefinition, ChainExecution, DynamicLoop}

**Роль:** Архитектор Гэндальф (System Architect)
**Дата:** 2026-05-04
**Объект:** Модули `src/Module/ChainDefinition/`, `src/Module/ChainExecution/`, `src/Module/DynamicLoop/` — декомпозиция монолитного Orchestrator (~11573 LOC)
**Задача:** Архитектурный ревью разделения ответственности, изоляции Domain, Integration-слоя, дублирования VO, DI-конфигурации

---

## Вердикт: ⚠️ Request Changes

Декомпозиция проведена в правильном направлении: три модуля с чётким разделением ответственности (Definition / Execution / DynamicLoop). Domain-изоляция соблюдена безупречно. Однако обнаружены **критические нарушения** в Application-слое — прямые cross-module зависимости между Application-слоями модулей, которые должны идти через Integration.

---

## 1. Границы Bounded Contexts

### ⬆️ Overall: Правильное разделение

| Модуль | Ответственность | Файлов | Вердикт |
|--------|----------------|--------|---------|
| **ChainDefinition** | Загрузка, парсинг, валидация YAML chain definitions | 40 | ✅ Чистый bounded context |
| **ChainExecution** | Статическое + условное выполнение цепочек | 87 | ✅ Чистый bounded context |
| **DynamicLoop** | Фасилитаторный dynamic loop | 65 | ✅ Чистый bounded context |

Разделение корректно: каждый модуль владеет своим lifecycle. Definition — «что выполнять», Execution — «как выполнять статические/условные шаги», DynamicLoop — «как выполнять facilitated discussion».

---

## 2. Изоляция Domain

### ✅ Полное соответствие

Все три Domain-слоя имеют **ноль cross-module зависимостей**. Анализ `use`-операторов подтвердил:

| Модуль | Внешние use в Domain | Нарушения |
|--------|---------------------|-----------|
| ChainDefinition.Domain | `InvalidArgumentException`, `LogicException`, `Override` | ✅ Нет |
| ChainExecution.Domain | `Override`, `Psr\Log\LoggerInterface` | ✅ Нет |
| DynamicLoop.Domain | `Override`, `LogicException`, `Psr\Log\LoggerInterface` | ✅ Нет |

> Каждый Domain зависит только от PHP SPL и PSR-интерфейсов. Правило **Domain → nobody** соблюдено.

---

## 3. Находки

### 🔴 CRITICAL-1: ChainExecution.Application → ChainDefinition.Domain (прямая зависимость)

**Файлы:**
- `src/Module/ChainExecution/Application/Service/Chain/ExecutionStrategyInterface.php`
- `src/Module/ChainExecution/Application/Service/Chain/StaticExecutionStrategy.php`
- `src/Module/ChainExecution/Application/Service/Chain/ConditionalExecutionStrategy.php`
- `src/Module/ChainExecution/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommandHandler.php`

**Нарушение:** Application-слой ChainExecution напрямую импортирует типы из ChainDefinition.Domain:

```
ChainDefinition\Domain\ChainDefinitionInterface
ChainDefinition\Domain\Enum\ChainTypeEnum
ChainDefinition\Domain\ValueObject\StaticChainDefinitionVo
ChainDefinition\Domain\ValueObject\ConditionalChainDefinitionVo
ChainDefinition\Application\Service\Chain\ChainLoaderInterface
```

По конвенции (`docs/conventions/layers/layers.md`), Application может зависеть **только от своего Domain**. Cross-module доступ должен идти через Integration-слой.

**ChainLoaderInterface** из ChainDefinition.Application используется напрямую в `OrchestrateChainCommandHandler` — это Application→Application cross-module зависимость.

**Рекомендация:** Заменить прямые зависимости на Integration-интерфейсы, которые уже существуют:
- `ChainDefinitionProviderInterface` в ChainExecution.Domain — уже реализован через `ChainExecutionDefinitionMapper`
- Переработать `ExecutionStrategyInterface`, чтобы он принимал Execution-VO вместо `ChainDefinitionInterface`
- `OrchestrateChainCommandHandler` должен получать данные через `ChainDefinitionProviderInterface`, а не напрямую через `ChainLoaderInterface`

---

### 🔴 CRITICAL-2: DynamicLoop.Application → ChainExecution.Application (тесная связанность)

**Файлы:**
- `src/Module/DynamicLoop/Application/Service/DynamicExecutionStrategy.php`
- `src/Module/DynamicLoop/Application/Service/DispatchRoundEventService.php`
- `src/Module/DynamicLoop/Application/Service/DispatchSessionCompletedEventService.php`

**Нарушение:** DynamicLoop.Application напрямую зависит от **7 типов** ChainExecution.Application:

```
ChainExecution\Application\Service\Chain\ExecutionStrategyInterface
ChainExecution\Application\UseCase\Command\OrchestrateChain\DynamicRoundResultDto
ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand
ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto
ChainExecution\Application\Event\OrchestrateChain\OrchestrateRoundCompletedEvent
ChainExecution\Application\Event\OrchestrateChain\OrchestrateSessionCompletedEvent
```

Плюс из ChainDefinition.Domain:
```
ChainDefinition\Domain\ChainDefinitionInterface
ChainDefinition\Domain\Enum\ChainTypeEnum
ChainDefinition\Domain\ValueObject\DynamicChainDefinitionVo
```

DynamicLoop — **плагин ChainExecution** (реализует `ExecutionStrategyInterface`), но при этом связан с ним через Application-слой. Это создаёт двунаправленную связанность: ChainExecution содержит DTO для DynamicLoop (`DynamicRoundResultDto`), а DynamicLoop зависит от Command/Result ChainExecution.

**Рекомендация:**
1. **Вынести `ExecutionStrategyInterface` в отдельный shared-контракт** (например, `src/Shared/Contract/`) или в ChainDefinition.Domain как абстракцию, не привязанную к конкретному модулю выполнения
2. **Events** (`OrchestrateRoundCompletedEvent`, `OrchestrateSessionCompletedEvent`) — вынести в shared-слой или использовать Integration-паттерн: DynamicLoop.Domain определяет свои notifier-интерфейсы (уже есть `RoundCompletedNotifierInterface`, `SessionCompletedNotifierInterface`), а конкретные event-классы — detail реализации в Application/Integration
3. **Command/Result DTO** (`OrchestrateChainCommand`, `OrchestrateChainResultDto`) — вынести контракт в shared или перестроить через Integration-маппинг

---

### 🟡 MAJOR-1: Чрезмерное дублирование VO (13+ пар)

Структурно идентичные типы, отличающиеся только namespace:

| ChainDefinition.Domain | ChainExecution.Domain | DynamicLoop.Domain | Суть |
|------------------------|-----------------------|---------------------|------|
| `ChainStepTypeEnum` | `ChainStepTypeEnum` | — | Байт-в-байт идентичны |
| `ConditionOperatorEnum` | `ConditionOperatorEnum` | — | Байт-в-байт идентичны |
| `ConditionExpressionVo` | `ConditionExpressionVo` | — | Почти идентичны |
| `BudgetVo` | `ExecutionBudgetVo` | `DynamicLoopBudgetVo` | Структура идентична, BudgetVo имеет доп. методы |
| `ChainStepVo` | `ExecutionStepVo` | — | Структура идентична |
| `ChainRetryPolicyVo` | `ExecutionRetryPolicyVo` | `DynamicLoopRetryPolicyVo` | Структурные зеркала |
| `QualityGateVo` | `ExecutionQualityGateVo` | — | Структурные зеркала |
| `RoleConfigVo` | `ExecutionRoleConfigVo` | `DynamicLoopRoleConfigVo` | Структурные зеркала |
| `FallbackConfigVo` | `ExecutionFallbackConfigVo` | `DynamicLoopFallbackConfigVo` | Структурные зеркала |
| `FixIterationGroupVo` | `ExecutionFixIterationGroupVo` | — | Структурные зеркала |
| `ChainNotFoundException` | `ChainNotFoundException` | — | Разные base-классы |
| `NotFoundExceptionInterface` | `NotFoundExceptionInterface` | — | Идентичны |
| `RoleNotFoundException` | `RoleNotFoundException` | — | Идентичны |

**Оценка:** Дублирование VO на границах bounded contexts — это классический DDD-паттерн (Anti-Corruption Layer), и он **оправдан** для VO, которые содержат контекстно-специфичную бизнес-логику (например, `BudgetVo` в Definition имеет `fromArray()` для YAML-парсинга, а `ExecutionBudgetVo` — нет).

Однако **enum'ы** (`ChainStepTypeEnum`, `ConditionOperatorEnum`) и **exception'ы** не несут контекстно-специфичного поведения — их дублирование создаёт ненужную maintenance-нагрузку.

**Рекомендация:**
1. Вынести `ChainStepTypeEnum`, `ConditionOperatorEnum` в `src/Shared/Domain/Enum/` — они стабильны и не зависят от контекста
2. Exception'ы (`ChainNotFoundException`, `NotFoundExceptionInterface`, `RoleNotFoundException`) — вынести в `src/Shared/Domain/Exception/`
3. VO с поведенческими различиями — оставить как есть (оправданное дублирование)

---

### 🟡 MAJOR-2: DynamicLoopDefinitionMapper не реализует интерфейс

**Файл:** `src/Module/DynamicLoop/Integration/Service/ChainDefinition/DynamicLoopDefinitionMapper.php`

В отличие от `ChainExecutionDefinitionMapper`, который реализует `ChainDefinitionProviderInterface` из ChainExecution.Domain, DynamicLoopDefinitionMapper — это plain-класс с методом `map()` без привязки к контракту DynamicLoop.Domain.

При этом в DynamicLoop.Domain **уже определён** `ChainDefinitionProviderInterface`:
```php
interface ChainDefinitionProviderInterface {
    public function loadDynamicChainConfig(string $chainName): DynamicLoopConfigVo;
}
```

Но маппер его **не реализует**. Более того, в `DynamicExecutionStrategy` маппер создается inline через `new DynamicLoopDefinitionMapper()` — это нарушает DI-принцип.

**Рекомендация:** Реализовать `ChainDefinitionProviderInterface` в `DynamicLoopDefinitionMapper` и использовать DI-инъекцию вместо `new`.

---

### 🟡 MAJOR-3: StaticExecutionDomain внутри ChainExecution — неестественная вложенность

**Файлы:** `src/Module/ChainExecution/Domain/Service/Static/`

Внутри ChainExecution.Domain создан sub-namespace `Static/` с 8 файлами:
- `CheckStaticBudgetService.php`
- `CheckStaticBudgetServiceInterface.php`
- `ExecuteStaticStepService.php`
- `FormatPromptServiceInterface.php`
- `QualityGateRunnerInterface.php`
- `ResolveChainRunnerServiceInterface.php`
- `RunAgentServiceInterface.php`
- `RunStaticChainService.php`
- `StaticAuditServiceInterface.php`

При этом есть параллельные интерфейсы на верхнем уровне ChainExecution.Domain:
- `Domain\Service\Integration\RunAgentServiceInterface` vs `Domain\Service\Static\RunAgentServiceInterface`
- `Domain\Service\Chain\Shared\PromptFormatterInterface` vs `Domain\Service\Static\FormatPromptServiceInterface`

**Проблема:** Два интерфейса с одинаковым именем `RunAgentServiceInterface` в разных namespace одного модуля. Integration/Service/AgentRunner/RunAgentService реализует оба, что создаёт путаницу.

**Рекомендация:** Провести ревью sub-namespace `Static/` — вынести его содержимое на уровень Domain/Service/ без вложенного sub-namespace, устранив дублирование имён интерфейсов.

---

### 🟢 MINOR-1: Psr\Log\LoggerInterface в Domain-сервисах

**Файлы:**
- `ChainExecution/Domain/Service/Static/RunStaticChainService.php`
- `ChainExecution/Domain/Service/Static/CheckStaticBudgetService.php`
- `ChainExecution/Domain/Service/Static/ExecuteStaticStepService.php`
- `DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopService.php`

По конвенции Domain → nobody. Psr\Log\LoggerInterface — это техническая зависимость. В строгом DDD Domain не должен знать о логировании.

**Рекомендация:** Ввести Domain-интерфейс `LoggerInterface` (или `DomainLoggerInterface`) в каждом модуле и маппить его на Psr\Log\LoggerInterface через Infrastructure/DI. Это низкоприоритетная задача — текущая реализация допустима как pragmatic tradeoff.

---

### 🟢 MINOR-2: services.yaml — корректна

DI-конфигурация (`config/services.yaml`) корректно отражает декомпозицию:

- ✅ Auto-discovery с правильными исключениями Domain-типов (Dto, Entity, Enum, Exception, ValueObject)
- ✅ Module-specific секции с комментариями-разделителями
- ✅ Domain Service aliases (Interface → Implementation) настроены правильно
- ✅ Tagged iterators для `orchestrator.execution_strategy` корректно подключают DynamicExecutionStrategy
- ✅ Параметры ($yamlPath, $rolesDir, $chainsSessionDir) привязаны к нужным сервисам

**Замечание:** `DynamicLoopDefinitionMapper` не имеет явного DI-алиаса для `ChainDefinitionProviderInterface`. Если `DynamicExecutionStrategy` продолжает использовать `new DynamicLoopDefinitionMapper()` inline, это — DI-нарушение.

---

### ℹ️ INFO-1: ChainDefinitionVo (555 LOC) — отложенный техдолг

**Файл:** `src/Module/ChainDefinition/Domain/ValueObject/ChainDefinitionVo.php`

Класс корректно помечен `@deprecated` и имеет замену в виде специализированных sub-VO:
- `StaticChainDefinitionVo`
- `DynamicChainDefinitionVo`
- `ConditionalChainDefinitionVo`

Все три реализуют `ChainDefinitionInterface` и используют `SharedChainDefinitionVo` через `getSharedDefinition()`.

**Риск:** `ChainDefinitionVo` содержит монолитные фабричные методы `createFromSteps()`, `createFromDynamic()`, `createFromConditionalSteps()`, которые, вероятно, ещё используются в `YamlChainLoader` или тестах.

**Рекомендация:** Создать задачу на удаление `ChainDefinitionVo` после миграции всех потребителей на специализированные VO. Оценка: 2-3 часа работы.

---

### ℹ️ INFO-2: Integration → Application паттерн соблюдён

Проверка Integration-слоев подтвердила корректность:

| Integration-сервис | Вызывает | По конвенции |
|-------------------|----------|-------------|
| `ChainExecutionDefinitionMapper` | `ChainLoaderInterface` (ChainDefinition.Application) | ✅ Integration→Application |
| `AgentDtoMapper` + `RunAgentService` | `RunAgentCommand/Result` (AgentRunner.Application) | ✅ Integration→Application |
| `DynamicLoopDefinitionMapper` | `DynamicChainDefinitionVo` (ChainDefinition.Domain) | ✅ Integration→Domain contracts |
| `StaticAuditService` | `AuditLoggerInterface` (ChainExecution.Domain) | ✅ Integration→Domain contracts |
| `FormatPromptService` | `PromptFormatterInterface` (ChainExecution.Domain) | ✅ Integration→Domain contracts |

---

## 4. Рекомендации (приоритизированные)

### P0 — Критические (до следующего merge)

| # | Рекомендация | Оценка | Файлы |
|---|-------------|--------|-------|
| R1 | Устранить Application→Application/Domain cross-module зависимости в ChainExecution.Application | 4h | ExecutionStrategyInterface, CommandHandler, стратегии |
| R2 | Устранить DynamicLoop.Application → ChainExecution.Application coupling | 4h | DynamicExecutionStrategy, Dispatch*EventService |
| R3 | Исправить `DynamicLoopDefinitionMapper`: реализовать интерфейс, убрать `new` inline | 1h | DynamicLoopDefinitionMapper, DynamicExecutionStrategy |

### P1 — Важные (следующий спринт)

| # | Рекомендация | Оценка | Файлы |
|---|-------------|--------|-------|
| R4 | Вынести shared enum/exception в `src/Shared/Domain/` | 2h | ChainStepTypeEnum, ConditionOperatorEnum, *NotFoundException |
| R5 | Устранить sub-namespace `Static/` внутри ChainExecution.Domain — плоская структура | 3h | Domain/Service/Static/* → Domain/Service/* |
| R6 | Вынести `ExecutionStrategyInterface` в shared-контракт или ChainDefinition.Domain | 2h | ExecutionStrategyInterface |

### P2 — Улучшения (backlog)

| # | Рекомендация | Оценка | Файлы |
|---|-------------|--------|-------|
| R7 | Удалить `ChainDefinitionVo` после миграции потребителей | 3h | ChainDefinitionVo, YamlChainLoader |
| R8 | Ввести Domain-интерфейс для Logger (убрать Psr\Log из Domain) | 2h | *Service.php в Domain |

---

## 5. Вывод

Декомпозиция монолита Orchestrator на три модуля — **архитектурно верное решение**. Разделение ответственности проведено чисто, Domain-изоляция безупречна. Integration-мапперы на границах модулей работают корректно и выполняют роль ACL.

Однако **Application-слой не прошёл через ту же дисциплину**, что и Domain. Critical-нарушения (cross-module Application→Application/Application→Domain зависимости) — это прямой результат того, что ExecutionStrategy и CommandHandler остались в Application-слое с прямыми ссылками на ChainDefinition типы вместо того, чтобы пройти через Integration-слой.

**Резюме-метафора:** Мы построили три дома с чистыми фундаментами (Domain), но провели между ними коридоры через Application-этаж, минуя Integration-крыльцо. Нужно перестроить входы.

**Вердикт:** ⚠️ **Request Changes** — после устранения P0-нарушений (R1–R3) декомпозиция готова к approve.

---

*Отчёт подготовлен Архитектором Гэндальфом на основе анализа 192 PHP-файлов в 3 модулях, конфигурации DI и конвенций слоёв.*
