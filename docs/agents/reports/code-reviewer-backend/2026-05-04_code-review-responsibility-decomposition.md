# Code Review: Декомпозиция Orchestrator → {ChainDefinition, ChainExecution, DynamicLoop}

**Роль:** Ревьювер Бэка Пуаро
**Дата:** 2026-05-04
**Объект:** Ветка `refactor/responsibility-decomposition` — PR #147, #148, #149 (все слиты). Модули `ChainDefinition`, `ChainExecution`, `DynamicLoop`.
**Задача:** [EPIC-refactor-responsibility-decomposition](../../../todo/EPIC-refactor-responsibility-decomposition.md)

---

## Вердикт: **Request Changes** ⚠️

Декомпозиция модуля Orchestrator выполнена на высоком инженерном уровне. Domain-изоляция соблюдена. Тесты зелёные, Psalm чист. Однако обнаружено **2 критических** и **4 major** нарушения, которые необходимо устранить до мержа.

---

## Находки

### 🔴 Critical-1: Application → Integration — нарушение матрицы зависимостей

**Файлы:**
- `src/Module/ChainExecution/Application/Service/Chain/ConditionalExecutionStrategy.php` (строка 22)
- `src/Module/ChainExecution/Application/Service/Chain/StaticExecutionStrategy.php` (строка 19)
- `src/Module/DynamicLoop/Application/Service/DynamicExecutionStrategy.php` (строка 27)

**Суть:** Все три стратегии выполнения (Application-слой) напрямую зависят от Integration-классов мапперов:
- `ConditionalExecutionStrategy` → `ChainExecutionDefinitionMapper` (Integration)
- `StaticExecutionStrategy` → `ChainExecutionDefinitionMapper` (Integration)
- `DynamicExecutionStrategy` → `DynamicLoopDefinitionMapper` (Integration) — причём через `new DynamicLoopDefinitionMapper()` inline (!)

**Нарушение конвенции:** Матрица зависимостей в `docs/conventions/layers/layers.md` прямо указывает: `Application → Integration = ❌`. Application может зависеть только от Domain.

**Исправление:** Объявить интерфейсы мапперов в Domain-слое каждого модуля (например, `ChainConfigMapperInterface` в `ChainExecution\Domain\Service\Integration\`), инжектировать через DI, а Integration-классам реализовать эти интерфейсы.

В `DynamicExecutionStrategy` additionally: `new DynamicLoopDefinitionMapper()` — прямое создание Integration-объекта, что категорически запрещено. Инжектировать через конструктор.

---

### 🔴 Critical-2: DynamicLoop → ChainExecution Application-слой — нарушение изоляции модулей

**Файл:** `src/Module/DynamicLoop/Application/Service/DynamicExecutionStrategy.php`

**Суть:** DynamicLoop.Application импортирует 4 типа из ChainExecution.Application:
```php
use ChainExecution\Application\Service\Chain\ExecutionStrategyInterface;
use ChainExecution\Application\UseCase\Command\OrchestrateChain\DynamicRoundResultDto;
use ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use ChainExecution\Application\UseCase\Command\OrchestrateChain\OrchestrateChainResultDto;
```

А также `DispatchRoundEventService` и `DispatchSessionCompletedEventService` из DynamicLoop.Application импортируют event-классы из `ChainExecution\Application\Event\`.

По конвенции, межмодульное взаимодействие возможно только через Integration → Application (чужого модуля). DynamicLoop.Application → ChainExecution.Application — **прямое нарушение**.

**Исправление:** Вынести общий контракт `ExecutionStrategyInterface`, `OrchestrateChainCommand`, `OrchestrateChainResultDto`, `DynamicRoundResultDto` и event-классы в:
- Domain ChainExecution (интерфейс стратегии и VO-типы, если ChainExecution — «владелец» контракта), либо
- Общий shared-контракт, либо
- Integration-слой DynamicLoop с wrapper-типами.

Минимальный вариант: `ExecutionStrategyInterface` и DTO, которые использует DynamicExecutionStrategy, перенести в Domain ChainExecution (это допустимо — Integration-слой DynamicLoop может зависеть от Domain ChainExecution). Тогда DynamicLoop.Integration реализует интерфейс, а DynamicLoop.Application зависит только от своего Domain.

---

### 🟡 Major-1: DynamicLoop.Infrastructure зависит от ChainExecution.Domain — обратная зависимость

**Файлы:**
- `src/Module/DynamicLoop/Infrastructure/Service/JsonlAuditLogger.php` — реализует `AuditLoggerInterface` и использует `ChainRunResultVo`, `ChainResultAuditDto` из ChainExecution.Domain
- `src/Module/DynamicLoop/Infrastructure/Service/RunDynamicLoopAgentService.php` — зависит от `ChainExecution\Domain\Service\Integration\RunAgentServiceInterface`, `ChainExecution\Domain\Service\Prompt\PromptProviderInterface`, создаёт `ChainRunRequestVo`

**Суть:** DynamicLoop.Infrastructure → ChainExecution.Domain допустимо по матрице (Infrastructure → Domain ✅), но в EPIC декларировано:
> ChainExecution → DynamicLoop = FORBIDDEN  
> DynamicLoop → ChainExecution = FORBIDDEN

Фактически DynamicLoop.Infrastructure имеет 5 зависимостей на ChainExecution.Domain. Если это осознанное решение (общий AgentRunner integration), его нужно зафиксировать в EPIC и ADR.

**Рекомендация:** Либо признать однонаправленную зависимость DynamicLoop → ChainExecution как осознанный architectural decision (зафиксировать в ADR), либо вынести общие контракты (`RunAgentServiceInterface`, `PromptProviderInterface`, `AuditLoggerInterface`, общие VO) в ChainExecution.Domain (и отразить в Deptrac-правилах).

---

### 🟡 Major-2: Дублирование VO и Enum между модулями

**Дублированные типы (отличаются только namespace):**
- `ChainStepTypeEnum` — ChainDefinition.Domain ↔ ChainExecution.Domain
- `ConditionOperatorEnum` — ChainDefinition.Domain ↔ ChainExecution.Domain
- `ConditionExpressionVo` — ChainDefinition.Domain ↔ ChainExecution.Domain (ChainExecution-версия имеет дополнительный `fromComponents()`)
- `ChainNotFoundException` — ChainDefinition.Domain ↔ ChainExecution.Domain (разные base-классы: OrchestratorException vs RuntimeException)
- `RoleNotFoundException` — ChainDefinition.Domain ↔ ChainExecution.Domain
- `NotFoundExceptionInterface` — ChainDefinition.Domain ↔ ChainExecution.Domain
- `DynamicRoundResultDto` — ChainExecution.Application ↔ DynamicLoop.Application (полные копии)
- `DynamicLoopResultDto` / `FacilitatorTurnResultDto` — ChainExecution.Application ↔ DynamicLoop.Application

**Риск:** При эволюции типы разъедутся. Конвенция требует, что каждый модуль владеет своими типами, но для идентичных Enum/Exception это избыточный бойлерплейт.

**Рекомендация:** Для дублированных Enum (`ChainStepTypeEnum`, `ConditionOperatorEnum`) рассмотреть вынесение в ChainDefinition.Domain (как «мастер» справочников) с доступом через Integration-маппинг. Для Exception — оставить в каждом модуле, но унифицировать иерархию (базовый класс). Для DTO — это осознанное дублирование на границе модулей (пока допустимо, но нужен комментарий в PHPDoc).

---

### 🟡 Major-3: Dead code — `ChainDefinitionProviderInterface` не используется

**Файлы:**
- `src/Module/DynamicLoop/Domain/Service/Integration/ChainDefinitionProviderInterface.php` — интерфейс объявлен, но не реализован и не инжектирован нигде
- `src/Module/ChainExecution/Domain/Service/Integration/ChainDefinitionProviderInterface.php` — объявлен и реализован `ChainExecutionDefinitionMapper`, но сам интерфейс не затребован ни одним клиентом (стратегии зависят от конкретного класса-маппера, а не от интерфейса)

**Суть:** Интерфейсы провайдеров созданы правильно, но не инжектируются — стратегии зависят от конкретных Integration-классов (см. Critical-1). Если исправить Critical-1, эти интерфейсы обретут смысл.

**Рекомендание:** После исправления Critical-1, инжектировать именно `ChainDefinitionProviderInterface`, а не конкретные мапперы.

---

### 🟡 Major-4: `RunAgentServiceInterface` дублирован внутри ChainExecution.Domain

**Файлы:**
- `src/Module/ChainExecution/Domain/Service/Integration/RunAgentServiceInterface.php`
- `src/Module/ChainExecution/Domain/Service/Static/RunAgentServiceInterface.php`

**Суть:** Два интерфейса с **идентичной сигнатурой** `run(ChainRunRequestVo, ?ExecutionRetryPolicyVo): ChainRunResultVo` в разных namespace. Integration-реализация `RunAgentService` в `Integration/Service/AgentRunner/` реализует оба (через делегирование).

Это legacy от вливания StaticExecution. Дублирование запутывает.

**Рекомендация:** Удалить `Domain\Service\Static\RunAgentServiceInterface`, оставить только `Domain\Service\Integration\RunAgentServiceInterface`, обновить `ExecuteStaticStepService` и `services.yaml`.

---

### 🟢 Minor-1: Dead import в тесте — `ConditionalStepService`

**Файл:** `tests/Integration/Application/UseCase/Command/OrchestrateChain/ConditionalChainIntegrationTest.php` (строка 32)

**Суть:** `use TaskOrchestrator\Common\Module\ChainExecution\Infrastructure\Service\Chain\ConditionalStepService;` — класс `ConditionalStepService` не существует (переименован в `ExecuteConditionalStepService`). `#[CoversClass(ConditionalStepService::class)]` ссылается на несуществующий класс.

PHPUnit проходит, потому что `CoversClass` с несуществующим классом не вызывает фатальную ошибку в PHPUnit 10, но Psalm/Deptrac могут не покрыть этот путь корректно.

**Исправление:** Заменить на `ExecuteConditionalStepService::class`.

---

### 🟢 Minor-2: `DynamicLoopDefinitionMapper` не реализует `ChainDefinitionProviderInterface`

**Файл:** `src/Module/DynamicLoop/Integration/Service/ChainDefinition/DynamicLoopDefinitionMapper.php`

**Суть:** В ChainExecution маппер `ChainExecutionDefinitionMapper implements ChainDefinitionProviderInterface`. В DynamicLoop маппер не реализует соответствующий интерфейс из `DynamicLoop\Domain\Service\Integration\ChainDefinitionProviderInterface`.

**Исправление:** После исправления Critical-1 (когда интерфейс начнёт использоваться) добавить `implements ChainDefinitionProviderInterface`.

---

### 🟢 Minor-3: PHPDoc-комментарии в VO ссылаются на чужой модуль

**Файлы:** `ChainExecution\Domain\ValueObject\Execution*.php` (9 файлов)

**Суть:** Комментарии вида `Маппится из ChainDefinition\Domain\ValueObject\...` — технически правильные, но создают неявную связность через документацию. Если ChainDefinition изменит VO, комментарий станет неактуальным.

**Рекомендование:** Допустимо оставить, но可以考虑 убрать конкретные ссылки на namespace ChainDefinition, оставив только «Маппится через Integration-маппер».

---

### ℹ️ Info-1: Старые модули удалены корректно

`src/Module/Orchestrator/` и `src/Module/StaticExecution/` удалены — соответствует EPIC DoD.

### ℹ️ Info-2: Тесты и статический анализ

- PHPUnit: **843 тестов, 2294 assertions** — ✅ все зелёные
- Psalm: **No errors found** — ✅
- Покрытие integration-тестами: static, conditional, dynamic, resume — ✅
- Unit-тесты: DynamicLoop, ChainDefinition, ChainExecution — ✅

### ℹ️ Info-3: Отсутствуют тесты на Integration-мапперы

Нет unit-тестов на `ChainExecutionDefinitionMapper` и `DynamicLoopDefinitionMapper`. Мапперы покрываются косвенно через integration-тесты стратегий, но прямое покрытие маппинга Definition VO → Execution VO отсутствует.

**Рекомендация:** Добавить unit-тесты на мапперы (минимум happy path + null-кейсы для optional полей).

### ℹ️ Info-4: Documentation не обновлена

`docs/guide/architecture.md` по-прежнему описывает двухмодульную структуру (AgentRunner + Orchestrator). Должно быть обновлено после завершения EPIC (задача PR#4).

---

## Scope Validation (Соответствие задачам)

| Критерий EPIC DoD | Статус | Комментарий |
|---|---|---|
| 3 модуля со своим Domain | ✅ | ChainDefinition, ChainExecution, DynamicLoop |
| Domain ≠ Domain другого модуля | ✅ | grep подтверждает — только комментарии |
| Integration-мапперы корректны | ⚠️ | Работают, но нарушают слой (Critical-1) |
| Все тесты проходят | ✅ | 843 тестов |
| Psalm проходит | ✅ | No errors |
| CLI app:agent:orchestrate | ✅ (косвенно) | Проверено через integration-тесты |
| Удалён Orchestrator | ✅ | |
| Удалён StaticExecution | ✅ | |
| Deptrac-правила | ⬜ | Отдельная задача (PR#4) |
| ADR обновлён | ⬜ | Отдельная задача (PR#4) |
| architecture.md обновлён | ⬜ | Отдельная задача (PR#4) |

---

## Рекомендации (приоритизированные)

1. **[Critical-1]** Перевести стратегии на Domain-интерфейсы мапперов вместо прямых зависимостей на Integration-классы. Заменить `new DynamicLoopDefinitionMapper()` на DI-инъекцию.
2. **[Critical-2]** Вынести общий контракт стратегий (ExecutionStrategyInterface, Command/Result DTO, events) в Domain ChainExecution или Integration-слой. Устранить DynamicLoop.Application → ChainExecution.Application.
3. **[Major-4]** Удалить дублированный `Static\RunAgentServiceInterface`, унифицировать на `Integration\RunAgentServiceInterface`.
4. **[Major-1]** Зафиксировать зависимость DynamicLoop.Infrastructure → ChainExecution.Domain в EPIC и ADR (или вынести общие контракты).
5. **[Major-3]** После исправления Critical-1 подключить `ChainDefinitionProviderInterface` в стратегиях через DI.
6. **[Minor-1]** Исправить dead import `ConditionalStepService` → `ExecuteConditionalStepService` в тесте.
7. **[Info-3]** Добавить unit-тесты на Integration-мапперы.

---

## Вывод

Декомпозиция по ответственности — это верный архитектурный шаг. Три модуля вместо одного монолита дают ясные границы bounded context'ов. Domain-изоляция соблюдена безупречно. Инженерное качество кода высокое: readonly-классы, строгая типизация, PHPDoc.

Однако декомпозиция не доведена до конца в «горизонтальном» срезе — межмодульные контракты (ExecutionStrategy, Command/Result DTO, events) остались «протянуты» через Application-слой напрямую, минуя Integration/Domain-абстракции. Это нарушает матрицу зависимостей конвенции и создаст проблемы при добавлении новых стратегий или модулей.

**Оценка: 7/10** — крепкая работа, но требует доработки по архитектурным контрактам перед мержем.

---

*«Маленькие серые клеточки никогда не ошибаются. Ошибаются только люди, которые не дают им времени поработать.»*
*— Эркюль Пуаро*
