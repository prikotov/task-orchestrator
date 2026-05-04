# Критический анализ декомпозиции Orchestrator → {ChainDefinition, ChainExecution, DynamicLoop}: слепые зоны, неявные допущения, риски

**Роль:** Архитектор Локи (System Architect)
**Дата:** 2026-05-04
**Объект:** Модули `src/Module/ChainDefinition/`, `src/Module/ChainExecution/`, `src/Module/DynamicLoop/` — критический анализ слепых зон, не найденных Гэндальфом
**Задача:** Найти проблемы, невидимые из «чистой архитектуры» — runtime-риски, неявные допущения, operational gaps, альтернативы

---

## Постановка

Гэндальф провёл архитектурный ревью ([отчёт](../system-architect-gandalf/2026-05-04_architecture-review-responsibility-decomposition.md)) и нашёл 4 значимых проблемы (CRITICAL-1, CRITICAL-2, MAJOR-1, MAJOR-2). Его анализ сфокусирован на **статической структуре** — `use`-операторы, матрицы зависимостей, соответствие конвенциям слоёв.

Моя задача — найти то, что **невидимо на диаграммах**: runtime-паттерны, неявные контракты, edge cases при реальном выполнении, операционные риски и упущенные альтернативы.

---

## Рефлексия

- `Сложность запроса`: **8 из 10** — cross-module runtime analysis с изучением паттернов DI, маппинга, стратегии выполнения
- `Уровень контекста`: **9 из 10** — полный набор файлов предоставлен, есть отчёт Гэндальфа для сравнения
- `Риск ошибки`: **3 из 10** — анализ кода, а не генерация; риск — пропустить латентный паттерн

---

## 1. Что упустил Гэндальф

### 🔴 BLIND-1: `new DynamicLoopDefinitionMapper()` — Application создаёт Integration-объект inline

**Файл:** `src/Module/DynamicLoop/Application/Service/DynamicExecutionStrategy.php`, строка 178

```php
return (new DynamicLoopDefinitionMapper())->map($chain);
```

Гэндальф заметил, что маппер не реализует интерфейс (MAJOR-2). Но он **упустил, что маппер создаётся через `new` прямо в Application-слое**. Это не просто DI-нарушение — это **тройной архитектурный сбой**:

1. **Application → Integration прямая зависимость**: `DynamicExecutionStrategy` (Application) импортирует `DynamicLoopDefinitionMapper` (Integration). По конвенции Integration → Application, но не наоборот.
2. **Невозможность эволюции маппера**: Поскольку маппер создаётся через `new`, он **не может иметь зависимостей**. Если завтра понадобится логирование маппинга, кэширование, или замер производительности — придётся менять `DynamicExecutionStrategy`.
3. **Скрытый coupling с ChainDefinition.Domain**: Строка 22: `use DynamicChainDefinitionVo`. Метод `extractConfig()` проверяет `$chain instanceof DynamicChainDefinitionVo` и при положительном результате вызывает маппер. Это значит, что Application **знает о конкретном типе** из чужого Domain и **умеет его конвертировать**. Integration-маппер тут — просто прокладка.

**Runtime-сценарий**: Если YAML-файл содержит цепочку типа `dynamic`, `YamlChainLoader` возвращает `DynamicChainDefinitionVo`. `OrchestrateChainCommandHandler` передаёт его в `DynamicExecutionStrategy::execute()`. Стратегия видит, что это не `DynamicLoopConfigVo`, и создаёт `new DynamicLoopDefinitionMapper()`. Маппер вызывается **без кэша, без логирования, без обработки ошибок**.

**Сравнение с ChainExecution**: В `StaticExecutionStrategy` маппер инжектируется через DI (`private ChainExecutionDefinitionMapper $definitionMapper`). DynamicLoop — единственный модуль, где используется `new`.

---

### 🔴 BLIND-2: `assert()` для type-safety стратегий — защита, которая отключается в production

**Файлы:**
- `src/Module/ChainExecution/Application/Service/Chain/StaticExecutionStrategy.php:41`
- `src/Module/ChainExecution/Application/Service/Chain/ConditionalExecutionStrategy.php:46`

```php
assert($chain instanceof StaticChainDefinitionVo);
```

Гэндальф не заметил, что **единственная проверка типа** входящего `ChainDefinitionInterface` — это `assert()`, который:
- **Отключается при `zend.assertions = -1`** (стандартная конфигурация production)
- Не бросает исключение при несовпадении типа в production
- Приводит к **silent type mismatch**: если `supports()` вернул `true` по ошибке, стратегия получит чужой тип и упадёт с непонятной ошибкой глубже в бизнес-логике

**Runtime-сценарий**: Кто-то добавляет новый тип цепочки (например, `parallelType`), забывает зарегистрировать стратегию, но `supports()` в `StaticExecutionStrategy` по какой-то причине совпадает. В dev assert поймает, в production — нет. Результат: executing dynamic chain with static strategy → непредсказуемое поведение.

**Рекомендация**: Заменить `assert()` на явную проверку с бросанием `LogicException`:
```php
if (!$chain instanceof StaticChainDefinitionVo) {
    throw new LogicException(sprintf('Expected StaticChainDefinitionVo, got %s', $chain::class));
}
```

---

### 🔴 BLIND-3: `OrchestrateChainResultDto` — union-type DTO, объединяющий несовместимые контракты

**Файл:** `src/Module/ChainExecution/Application/UseCase/Command/OrchestrateChain/OrchestrateChainResultDto.php`

```php
public function __construct(
    public array $stepResults = [],      // static/conditional
    public array $roundResults = [],     // dynamic
    public float $totalTime = 0.0,
    // ... shared metrics ...
    public ?string $synthesis = null,         // dynamic-only
    public bool $maxRoundsReached = false,    // dynamic-only
    public ?string $sessionDir = null,        // dynamic-only
    public int $totalIterations = 0,          // static-only
    // ...
)
```

Гэндальф не заметил, что **один DTO обслуживает три разных типа результатов** с mutually exclusive полями. Проблема:

1. **Consumer не знает, какие поля валидны**: Вызвал `OrchestrateChainResultDto` — и должен догадаться, что `stepResults` пуст для dynamic, а `roundResults` пуст для static. Никакого type-level контракта.
2. **DynamicLoop-специфичные поля утекли в ChainExecution**: `synthesis`, `maxRoundsReached`, `sessionDir` — это domain DynamicLoop, но они определены в ChainExecution.Application.
3. **Обратная зависимость**: `DynamicRoundResultDto` живёт в ChainExecution.Application, но заполняется только в DynamicLoop.Application. ChainExecution.Application **содержит DTO, которые не создаёт**.

**Что будет при добавлении нового типа**: Каждое новое execution strategy добавит свои поля в этот DTO. Он превратится в God Object.

---

### 🟡 BLIND-4: Стратегия диспетчеризации — хрупкий O(n) `supports()` iteration

**Файл:** `src/Module/ChainExecution/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommandHandler.php`

```php
private function resolveStrategy(ChainDefinitionInterface $chain): ExecutionStrategyInterface
{
    foreach ($this->strategies as $strategy) {
        if ($strategy->supports($chain)) {
            return $strategy;
        }
    }
    throw new LogicException(...);
}
```

Гэндальф не проанализировал **runtime-паттерн** диспетчеризации:

1. **Порядок стратегий не определён**: `iterable<ExecutionStrategyInterface>` из tagged iterator. Symfony не гарантирует порядок. Если две стратегии поддерживают один тип — результат непредсказуем.
2. **Нет compile-time guarantee**: Можно добавить новый `ChainTypeEnum`, забыть стратегию — и узнать об этом только в runtime через `LogicException('No execution strategy found')`.
3. **Стратегии знают о ChainDefinition.Domain**: `supports()` проверяет `$chain->getType() === ChainTypeEnum::staticType` — значит каждая стратегия импортирует `ChainTypeEnum` из чужого модуля. Это **routing logic**, которая должна быть централизована.

**Альтернатива**: Strategy registry с явным маппингом `ChainTypeEnum → ExecutionStrategyInterface::class`. Регистрация в DI, а не iteration.

---

### 🟡 BLIND-5: Dual-path в `extractConfig()` — type sniffing как anti-pattern

**Файл:** `src/Module/DynamicLoop/Application/Service/DynamicExecutionStrategy.php`, строки 169–181

```php
private function extractConfig(ChainDefinitionInterface $chain): DynamicLoopConfigVo
{
    if ($chain instanceof DynamicLoopConfigVo) {
        return $chain;
    }
    if ($chain instanceof DynamicChainDefinitionVo) {
        return (new DynamicLoopDefinitionMapper())->map($chain);
    }
    throw new LogicException(...);
}
```

Гэндальф увидел, что `DynamicLoopDefinitionMapper` не реализует интерфейс. Но он не заметил **архитектурный паттерн**: метод делает type sniffing на три варианта, причём:

1. **`DynamicLoopConfigVo`** — уже сконвертированный тип (из DI/кэша?)
2. **`DynamicChainDefinitionVo`** — «сырой» тип из ChainDefinition.Domain
3. **Любой другой** — `LogicException`

Это означает, что **иногда стратегия получает уже сконвертированный конфиг, а иногда — нет**. Когда именно — зависит от caller'а. `OrchestrateChainCommandHandler` всегда передаёт сырой тип (из `ChainLoaderInterface::load()`). Но если кто-то вызовет стратегию напрямую с `DynamicLoopConfigVo` — она примет его без маппинга.

**Вопрос**: Кто и когда передаёт `DynamicLoopConfigVo` напрямую? Если ответ «никто» — то первая ветка мёртвый код. Если «иногда» — то есть undocumented code path.

---

### 🟡 BLIND-6: Отсутствие Deptrac-конфигурации в проекте

Гэндальф нашёл 4 нарушения слоёв. Я проверил — в проекте **нет `depfile.yaml`**. Deptrac доступен через `vendor/bin/deptrac`, но использует конфигурацию из `vendor/prikotov/coding-standard/config/deptrac/depfile.yaml`.

Это значит:
- **Нарушения, найденные обоими архитекторами, не могут быть пойманы автоматически** в CI
- Нет custom rules для модульных границ (ChainDefinition ↔ ChainExecution ↔ DynamicLoop)
- Каждый cross-module import в Application-слое — silent, пока кто-то не сделает ручной аудит

**Рекомендация**: Создать `depfile.yaml` с правилами, запрещающими:
- `ChainExecution.Application` → `ChainDefinition.*`
- `DynamicLoop.Application` → `ChainExecution.Application.*`
- `DynamicLoop.Application` → `ChainDefinition.Domain.*`

---

## 2. Runtime-риски

### 🟡 RUNTIME-1: Маппинг без валидации — молчаливая потеря данных

Оба маппера (`ChainExecutionDefinitionMapper`, `DynamicLoopDefinitionMapper`) выполняют **чисто структурный маппинг** без валидации результата.

Пример: `mapStep()` в `ChainExecutionDefinitionMapper`:
```php
return new ExecutionStepVo(
    type: ChainStepTypeEnum::from($step->getType()->value),
    // ... 14 полей ...
);
```

**Риски**:
1. `ChainStepTypeEnum::from()` бросает `ValueError` при неизвестном значении — но ошибка будет содержать enum value, а не имя цепочки/шага. Диагностика в production будет мучительной.
2. Если `ChainDefinitionVo` добавит новое поле, а `ExecutionStepVo` его не примет — маппер **молча проигнорирует** (нет required-параметра в конструкторе → нет ошибки компиляции).
3. Рекурсивный `mapBudget()` с `perRoleBudgets` — при глубокой вложенности потенциальен stack overflow, хотя на практике маловероятно.

**Рекомендация**: Добавить guard-проверки в мапперы с информативными ошибками:
```php
try {
    $type = ChainStepTypeEnum::from($step->getType()->value);
} catch (\ValueError $e) {
    throw new RuntimeException(
        sprintf('Unknown step type "%s" in chain "%s", step "%s"', 
            $step->getType()->value, $chainName, $step->getName() ?? 'unnamed'),
        0, $e
    );
}
```

---

### 🟡 RUNTIME-2: Кэширование YamlChainLoader — singleton с mutable state

**Файл:** `src/Module/ChainDefinition/Infrastructure/Service/Chain/YamlChainLoader.php`

```php
private ?array $chains = null;

public function overridePath(string $yamlPath): void
{
    $this->yamlPath = $yamlPath;
    $this->chains = null;
}
```

YamlChainLoader — **singleton через DI** (Symfony default `autowire: true`). Он кэширует все цепочки в `$chains` при первом `load()`. Это значит:

1. При `overridePath()` кэш сбрасывается, но **весь файл парсится заново** при следующем `load()`.
2. Если YAML-файл изменился на диске между запросами (в long-running процессе), кэш не обновится.
3. `loadAll()` парсит **все** цепочки, даже если нужна одна — O(all chains) вместо O(1).

Для CLI-приложения это не критично (один запрос → один процесс). Но если проект перейдёт на HTTP — кэш протухнет.

---

### 🟢 RUNTIME-3: `ChainDefinitionVo` всё ещё может вернуться из YamlChainLoader

`ChainDefinitionVo` помечен `@deprecated`, но `YamlChainLoader` его уже не создаёт — использует `StaticChainDefinitionVo::create()`, `DynamicChainDefinitionVo::create()`, `ConditionalChainDefinitionVo::create()`.

Однако: если где-то остался код, создающий `ChainDefinitionVo` напрямую (в тестах, в кастомных загрузчиках), и он попадёт в `resolveStrategy()` — **ни одна стратегия не поддержит его**, потому что `supports()` проверяет `$chain->getType()`, а стратегии сравнивают с `ChainTypeEnum::staticType/dynamicType/conditionalType`. `ChainDefinitionVo` может иметь корректный `getType()`, но не будет `instanceof StaticChainDefinitionVo` → assert в стратегии упадёт (в dev) или приведёт к silent bug (в production).

**Риск**: Низкий (YamlChainLoader не создаёт ChainDefinitionVo), но **нет guard'а** на входе в стратегию.

---

## 3. Масштабируемость

### 🟡 SCALE-1: DynamicLoop (65 файлов, 5488 LOC) — близок к порогу дальнейшего расщепления

DynamicLoop.Domain.Service.Dynamic содержит **14 файлов** — это sub-domain «dynamic execution engine» внутри DynamicLoop. При росте до ~80 файлов модуль станет кандидатом на расщепление:

```
DynamicLoop (текущий, 65 файлов)
├── Domain/Service/Dynamic/  (14 файлов — ядро loop execution)
├── Domain/Service/Session/  (3 файла — session management)
├── Domain/Service/Audit/    (2 файла — audit logging)
└── Domain/Service/Budget/   (1 файл — budget check)
```

**Триггер для split**: когда `Domain/Service/Dynamic/` достигнет 20+ файлов или когда появится второй тип loop'а (например, debate mode с двумя сторонами).

**Куда расщеплять**: Выделить `DynamicLoopSession` (session management + audit) из `DynamicLoopCore` (execution engine). Но сейчас — преждевременно.

---

### 🟡 SCALE-2: При добавлении нового типа цепочки — 6+ точек изменений

Чтобы добавить `ParallelChain` (параллельное выполнение):

1. `ChainTypeEnum` — добавить `parallelType`
2. `ParallelChainDefinitionVo` — новый VO в ChainDefinition.Domain
3. `YamlChainLoader` — парсинг нового типа
4. `ParallelExecutionStrategy` — стратегия в новом модуле или ChainExecution
5. `OrchestrateChainResultDto` — новые поля (parallelResults, mergedOutput?)
6. `services.yaml` — регистрация стратегии
7. Deptrac (если появится) — новые правила

Из них 3 — в разных модулях. Это **высокая стоимость расширения**, которая растёт с каждым новым типом.

**Альтернатива**: Plugin-архитектура, где каждый тип цепочки — отдельный бандл с auto-registration через compiler pass.

---

## 4. Operational Risk — Partial Failure

### 🟡 OPS-1: Нет транзакционности между модулями при resume

DynamicLoop поддерживает `resume()` через session state. Но **ChainExecution не поддерживает resume** для static/conditional — `resume()` бросает `LogicException`.

Если система упала посреди выполнения static-цепочки:
- Нет механизма resume
- Нет audit trail (static audit — fire-and-forget)
- Результаты уже выполненных шагов потеряны

Если система упала посреди dynamic-loop:
- Session state сохранён в файловой системе
- `resume()` прочитает state и продолжит
- Audit trail — в JSONL

**Асимметрия**: DynamicLoop — stateful с recovery, ChainExecution — stateless без recovery. Гэндальф не отметил это как архитектурное решение с operational implications.

---

### 🟡 OPS-2: Events — fire-and-forget без guaranteed delivery

`DispatchRoundEventService` и `DispatchSessionCompletedEventService` диспатчат события через PSR `EventDispatcherInterface`. Это **synchronous, in-process** диспетчеризация:

- Если listener бросит exception → вся цепочка упадёт
- Нет очереди, нет retry, нет persistence events
- Если процесс упадёт между диспетчеризацией и обработкой — событие потеряно

Для CLI-приложения это допустимо. Но если events используются для метрик, биллинга или мониторинга — нужна guaranteed delivery.

---

## 5. Неявные допущения

### ASSUMPTION-1: `ChainLoaderInterface::load()` всегда возвращает корректный тип

Все стратегии предполагают, что если `getType() === staticType`, то объект `instanceof StaticChainDefinitionVo`. Это допущение **не проверяется** на уровне интерфейса. `ChainDefinitionInterface` не гарантирует, что `getType()` соответствует конкретному классу.

**Контрпример**: Можно создать `ChainDefinitionVo` (legacy) с `type = static`, но он не будет `instanceof StaticChainDefinitionVo`. Assert поймает это только в dev.

---

### ASSUMPTION-2: Маппинг VO — identity-preserving

Оба маппера предполагают, что `ChainStepTypeEnum::from($step->getType()->value)` всегда успешен — то есть **enum values идентичны** между ChainDefinition и ChainExecution. Это верно сегодня, но это **implicit contract**, не enforced:

```
ChainDefinition.Domain.Enum.ChainStepTypeEnum::agent → 'agent'
ChainExecution.Domain.Enum.ChainStepTypeEnum::agent → 'agent'
```

Если кто-то переименует значение в одном из enum'ов — маппинг молча сломается в runtime с `ValueError`.

---

### ASSUMPTION-3: Или единственный execution entry point

`OrchestrateChainCommandHandler` — единственный entry point для выполнения всех цепочек. Но `ExecutionStrategyInterface::execute()` — публичный метод, который можно вызвать напрямую, минуя handler. При прямом вызове:
- Не будет загрузки цепочки через ChainLoader
- Не будет resolve стратегии
- Маппинг может не произойти

Это **недокументированный contract**: стратегии ожидают, что им передадут правильный тип, но это не enforced.

---

### ASSUMPTION-4: `services.yaml` auto-tagging — нет duplicate detection

`services.yaml` содержит `_instanceof` auto-tagging для `ExecutionStrategyInterface`:
```yaml
TaskOrchestrator\Common\Module\ChainExecution\Application\Service\Chain\ExecutionStrategyInterface:
  tags: ['orchestrator.execution_strategy']
```

И одновременно **manual tag** для `DynamicExecutionStrategy`:
```yaml
TaskOrchestrator\Common\Module\DynamicLoop\Application\Service\DynamicExecutionStrategy:
  tags: ['orchestrator.execution_strategy']
```

`DynamicExecutionStrategy` реализует `ExecutionStrategyInterface`, поэтому она будет **затэгирована дважды`** — через `_instanceof` и через manual tag. Symfony deduplicates tagged iterators, поэтому runtime-проблемы нет. Но это **source of confusion** для будущих разработчиков: нужно ли manual tag или auto-tag достаточен?

---

## 6. Альтернативы — была ли лучше другая декомпозиция?

### Альтернатива A: Vertical Slices по типу цепочки

```
StaticChains/   — definition loading + execution для static
ConditionalChains/ — definition loading + execution для conditional
DynamicChains/  — definition loading + execution для dynamic (facilitated discussion)
```

**Плюсы:**
- Нулевой cross-module coupling — каждый slice автономен
- Добавление нового типа — один новый slice, без изменения существующих
- Проще тестировать — каждый slice self-contained

**Минусы:**
- Дублирование definition loading logic (YAML parsing, validation)
- Общая конфигурация (roles, budget) — дублируется в каждом slice
- Нет единого entry point — нужен dispatcher/routing сверху

**Вердикт**: Vertical slices лучше для **независимых** типов с divergent evolution. Для текущей ситуации (3 типа с shared definition loading) — хуже, потому что definition logic стабильна и не diverгирует.

---

### Альтернатива B: Pipeline/Chain of Responsibility

```
Load → Validate → Map → Execute → Report
```

Каждый этап — middleware с `handle(Request, Closure): Response`.

**Плюсы:**
- Explicit flow — видно каждый шаг
- Легко добавить middleware (logging, metrics, caching)
- Natural resume: middleware может сохранять state

**Минусы:**
- Over-engineering для текущих 3 типов
- Middleware contract слишком generic — теряется type safety
- Сложнее тестировать комбинации middleware

**Вердикт**: Интересно, но преждевременно. Подойдёт, если количество типов вырастет до 7+ и pipeline-логика станет сложной.

---

### Альтернатива C: Текущая декомпозиция + устранение coupling (рекомендуемая)

Оставить 3 модуля, но **исправить coupling**:

1. **ExecutionStrategyInterface → shared contract** (или в ChainDefinition.Domain как абстракция над «стратегией выполнения»)
2. **OrchestrateChainResultDto → split на StaticResultDto / DynamicResultDto** с общим интерфейсом
3. **DynamicLoopDefinitionMapper → DI + implements interface**
4. **Добавить depfile.yaml** для автоматического контроля

**Вердикт**: Текущая декомпозиция — **наилучший баланс** для текущего масштаба. Проблемы — не в разделении, а в coupling на Application-уровне. Гэндальф прав в диагнозе, но мой анализ показывает, что **runtime-паттерны** (inline `new`, `assert()`, union DTO) — не менее опасны, чем статические violations.

---

## 7. Сводная карта находок

| # | Серьёзность | Категория | Найти | Гэндальф видел? |
|---|------------|-----------|-------|----------------|
| BLIND-1 | 🔴 CRITICAL | Architecture | `new DynamicLoopDefinitionMapper()` inline — Application→Integration violation | Частично (DI, но не runtime) |
| BLIND-2 | 🔴 CRITICAL | Runtime | `assert()` для type-safety — отключается в production | Нет |
| BLIND-3 | 🔴 CRITICAL | Design | `OrchestrateChainResultDto` — union-type DTO, mutual exclusive fields | Нет |
| BLIND-4 | 🟡 MAJOR | Runtime | O(n) strategy iteration без порядка и guarantees | Нет |
| BLIND-5 | 🟡 MAJOR | Design | `extractConfig()` type sniffing — undocumented dual path | Нет |
| BLIND-6 | 🟡 MAJOR | Tooling | Нет `depfile.yaml` — violations не детектируются автоматически | Нет |
| RUNTIME-1 | 🟡 MAJOR | Runtime | Маппинг без валидации — silent data loss | Нет |
| RUNTIME-2 | 🟢 MINOR | Runtime | YamlChainLoader singleton с mutable state | Нет |
| RUNTIME-3 | 🟢 MINOR | Runtime | ChainDefinitionVo может протечь в стратегию | Нет |
| SCALE-1 | 🟡 MAJOR | Scalability | DynamicLoop близок к порогу split (65 файлов, 5488 LOC) | Нет |
| SCALE-2 | 🟡 MAJOR | Scalability | Новый тип цепочки = 6+ точек изменений | Нет |
| OPS-1 | 🟡 MAJOR | Operational | Асимметрия resume: DynamicLoop vs ChainExecution | Нет |
| OPS-2 | 🟢 MINOR | Operational | Events fire-and-forget без guaranteed delivery | Нет |
| ASSUMPTION-1 | ℹ️ INFO | Implicit | `getType()` соответствует конкретному классу | Нет |
| ASSUMPTION-2 | ℹ️ INFO | Implicit | Enum values идентичны между модулями | Нет |
| ASSUMPTION-3 | ℹ️ INFO | Implicit | Стратегии вызываются только через CommandHandler | Нет |
| ASSUMPTION-4 | 🟢 MINOR | DI | Double tagging (auto + manual) для DynamicExecutionStrategy | Нет |

**Итого**: Гэндальф нашёл 4 проблемы (статический анализ). Локи нашёл 17 проблем, из которых **3 CRITICAL**, **7 MAJOR**, **4 MINOR**, **3 INFO**. 16 из 17 — не замечены Гэндальфом.

---

## 8. Приоритизированные рекомендации (дополнение к Гэндальфу)

### P0 — Критические (до merge)

| # | Рекомендация | Оценка | Связь с Гэндальфом |
|---|-------------|--------|-------------------|
| L1 | Убрать `new DynamicLoopDefinitionMapper()`: DI + implements interface | 2h | Усиливает MAJOR-2 Гэндальфа |
| L2 | Заменить `assert()` на explicit type check с `LogicException` | 0.5h | Новое |
| L3 | Устранить `DynamicExecutionStrategy::extractConfig()` type sniffing — получать конфиг через DI-injected provider | 1h | Усиливает MAJOR-2 Гэндальфа |

### P1 — Важные (следующий спринт)

| # | Рекомендация | Оценка | Связь с Гэндальфом |
|---|-------------|--------|-------------------|
| L4 | Split `OrchestrateChainResultDto` на typed variants с общим интерфейсом | 3h | Новое |
| L5 | Добавить guard валидацию в мапперы с контекстными ошибками | 2h | Новое |
| L6 | Создать `depfile.yaml` с правилами cross-module boundaries | 2h | Новое |
| L7 | Убрать manual tag для DynamicExecutionStrategy из services.yaml (auto-tag sufficient) | 0.5h | Новое |

### P2 — Улучшения (backlog)

| # | Рекомендация | Оценка |
|---|-------------|--------|
| L8 | Document implicit assumptions (ASSUMPTION-1..4) в ADR | 1h |
| L9 | Добавить integration-test для полного цикла: YAML → Load → Map → Execute → Result | 3h |
| L10 | Задокументировать триггер для DynamicLoop split (20+ файлов в Dynamic/) | 0.5h |

---

## 9. Итог

Гэндальф построил корректную статическую модель декомпозиции и нашёл нарушения в матрице зависимостей. Его диагноз верен: «Мы построили три дома с чистыми фундаментами, но провели коридоры через Application-этаж».

Мой анализ показывает, что **коридоры не просто идут через Application — они реализованы с runtime-дефектами**: inline `new` вместо DI, `assert()` вместо валидации, union DTO вместо typed results, O(n) диспетчеризация без guaranteed matching. Эти дефекты не видны на диаграммах слоёв, но проявятся при реальном выполнении.

**Красивая диаграмма зависимостей. А теперь покажи, что произойдёт, если assert отключён, маппер встретит неизвестный enum value, а стратегия получила не тот тип цепочки. Ответ: непредсказуемое поведение в production.**

---

*Отчёт подготовлен Архитектором Локи на основе анализа 192 PHP-файлов, DI-конфигурации, runtime-паттернов и отчёта Гэндальфа. 17 находок, из которых 16 — слепые зоны предыдущего анализа.*
