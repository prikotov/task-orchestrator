# Архитектурный дизайн: redesign проверки инварианта fix-iterations в ChainDefinition

**Роль:** Архитектор Локи  
**Дата:** 2026-06-15  
**Объект:** `src/Module/ChainDefinition` (модуль ChainDefinition), PR #261 `refactor/phpmd-baseline-elimination` → `main`  
**Задача:** перепроектировать проверку инварианта `fix_iterations` после обнаруженного нарушения `helper.md` (бизнес-логика в `ChainFixIterationsValidatorHelper`)

---

## 0. Резюме решений

| Развилка | Решение | Коротко |
|---|---|---|
| Модель валидации | **(ii) `Specification` (спецификация) + внешняя `Domain\Factory\ChainDefinitionFactory` (доменная фабрика)** | Фабрика нужна: это единственный вариант, который одновременно соблюдает `Specification` через DI (внедрение зависимостей), не протаскивает сервисы в VO (Value Object, объект-значение) и убирает 3 копии проверки. |
| A: детальность сообщений | **(б) Детальные diagnostics (диагностика) остаются в `ChainDefinitionValidatorService`; фабрика кидает generic fail-fast (общее быстрое исключение)** | Не плодим второй источник подробных сообщений. Но надо явно проверить CLI `validate-config` (валидация конфига), потому что текущий handler сначала загружает VO и может не дойти до validator. |
| B: deprecated `ChainDefinitionVo` | **Не расширять deprecated class (устаревший класс) полноценной фабрикой; оставить compatibility shim (прослойку совместимости) на статических named constructors, но убрать helper и общий алгоритм держать только в specification** | `ChainDefinitionVo` уже на красной линии LongClass. Его нельзя спасать новой сложностью. Production call sites (боевые места вызова) должны уйти на `ChainDefinitionFactory`; deprecated путь — только BC (обратная совместимость) до удаления. |

Ключевой вывод: `ChainFixIterationsValidatorHelper` надо **удалить**. Бизнес-правило переносится в `FixIterationsReferenceIntegritySpecification` (bool-only), а выброс исключения — в `ChainDefinitionFactory` для актуальных sub-VO.

---

## 1. Верифицированные факты по текущему коду

Проверено локально в ветке `refactor/phpmd-baseline-elimination`:

- `src/Module/ChainDefinition/Domain/ValueObject/ChainDefinitionVo.php`: **521 raw LOC**, ~481 nonblank LOC; по задаче/эпикам зафиксирован PHPMD NLOC около **493**, то есть класс у порога `LongClass` 500.
- `StaticChainDefinitionVo.php`: **218 raw LOC**; проверка `fix_iterations` inline (встроенно) в `createFromSteps()` на строках ~65–99.
- `ConditionalChainDefinitionVo.php`: **222 raw LOC**; такая же inline-проверка в `createFromConditionalSteps()` на строках ~69–103.
- `DynamicChainDefinitionVo.php`: dynamic-цепь не содержит `fix_iterations`.
- `ChainDefinitionVo.php`: deprecated (устаревший) legacy VO вызывает `ChainFixIterationsValidatorHelper::assertValidReferences()` в `createLinearChain()`.
- `ChainFixIterationsValidatorHelper.php`: содержит доменную проверку, кидает `InvalidArgumentException` и потому нарушает `docs/conventions/core_patterns/helper.md`: helper (хелпер) допускает только технические преобразования, бизнес-логика запрещена.
- `phpmd.xml`: `StaticAccess exceptions` содержит FQCN helper-а: `TaskOrchestrator\Common\Module\ChainDefinition\Domain\Helper\ChainFixIterationsValidatorHelper`.
- `ChainDefinitionValidatorService::validate(ChainDefinitionInterface): list<ChainConfigViolationVo>` уже возвращает детальные нарушения, включая `fix_iteration group "%s" references unknown step "%s"`.
- `ValidateChainConfigQueryHandler` сейчас вызывает `$chainLoader->load()` **до** `$chainValidator->validate($chainVo)`. Это важная слепая зона: если loader (загрузчик) / factory (фабрика) бросит fail-fast exception (быстрое исключение), detailed validator path (путь детальной валидации) может не выполниться.

Дополнительно: запрошенный файл `docs/conventions/core_patterns/specification.md` в репозитории отсутствует. Для specification использована действующая конвенция `docs/conventions/layers/domain/specification.md`.

---

## 2. Mini-ADR: Fix-iterations invariant redesign

### Статус

**Accepted for implementation** (принято для реализации backend-разработчиком), если ревью подтвердит отсутствие требования сохранять детальные exception-сообщения в loader path (путь загрузчика).

### Context (контекст)

Правило `fix_iterations` доменное:

1. каждый step name (имя шага), указанный в `FixIterationGroupVo`, должен существовать среди именованных `ChainStepVo` текущей step-based chain (цепочки на шагах);
2. один step name не должен принадлежать нескольким `fix_iteration` groups (группам fix-итераций).

Сейчас это правило реализовано тремя копиями:

1. inline в `StaticChainDefinitionVo::createFromSteps()`;
2. inline в `ConditionalChainDefinitionVo::createFromConditionalSteps()`;
3. в `Domain\Helper\ChainFixIterationsValidatorHelper::assertValidReferences()` для deprecated `ChainDefinitionVo`.

Копия №3 нарушает helper convention (конвенцию helper): helper не должен содержать бизнес-логику и исключения доменных инвариантов.

### Decision (решение)

Создать bool-only specification (спецификацию только с bool-результатом) и external Domain Factory (внешнюю доменную фабрику):

- specification отвечает только на вопрос «инвариант выполнен?»;
- factory отвечает за создание актуальных `StaticChainDefinitionVo`, `ConditionalChainDefinitionVo`, `DynamicChainDefinitionVo` и за fail-fast exception (быстрое исключение) при нарушении инварианта;
- detailed diagnostics (подробная диагностика) остаётся в `ChainDefinitionValidatorService`.

### Consequences (последствия)

Плюсы:

- helper с бизнес-логикой удаляется;
- 3 алгоритмические копии проверки превращаются в 1 bool specification;
- выброс исключения переносится в разрешённый по конвенциям объект — Domain Factory;
- `StaticAccess` exception в `phpmd.xml` удаляется;
- `StaticChainDefinitionVo` и `ConditionalChainDefinitionVo` уменьшаются примерно на 25–35 NLOC каждый.

Минусы и цена:

- появляется новая `ChainDefinitionFactory` (~100–140 NLOC) и `FixIterationsReferenceIntegritySpecification` (~35–45 NLOC);
- если `validate-config` должен показывать детальные ошибки `fix_iterations`, текущий flow (поток выполнения) надо проверить отдельно: loader может упасть generic exception до `ChainDefinitionValidatorService`;
- deprecated `ChainDefinitionVo` остаётся на красной линии LongClass и должен изменяться минимально.

---

## 3. Решение по развилкам

### 3.1. Развилка: модель валидации

**Решение: вариант (ii) — `Specification` + внешняя `Domain\Factory\ChainDefinitionFactory`.**

#### Почему не (i) constructor-only (только конструктор)

На бумаге constructor-only выглядит DDD-каноном: VO сам защищает invariant (инвариант). Но в этом проекте есть ограничение конвенций:

- `Specification` должна использоваться через DI;
- `Value Object` не должен зависеть от service/specification (сервиса/спецификации), DI container (контейнера) или глобального состояния;
- передавать specification в constructor VO — загрязнить VO технической зависимостью;
- создавать specification через `new` внутри VO — обойти DI-конвенцию.

Значит, constructor-only здесь либо нарушит specification convention, либо value-object convention.

#### Почему фабрика всё-таки оправдана

Фабрика оправдана измеримо:

- сейчас **3 копии** одного алгоритма;
- production call sites (боевые места вызова) сконцентрированы в `YamlChainLoaderService` — 4 вызова, миграция локальная;
- фабрика централизует creation logic (логику создания) уже перегруженных VO: `SharedChainDefinitionVo`, step-based variants (варианты на шагах), dynamic variant (динамический вариант), retry/budget/timeout;
- спецификация корректно внедряется через DI;
- конструкторы sub-VO можно оставить `@internal` thin constructors (тонкими конструкторами) без бизнес-логики.

Это не «factory ради factory»: она нужна как boundary (граница), где DI-доменное правило встречается с immutable VO (неизменяемым объектом-значением).

#### Почему не (iii) hybrid (гибрид)

Hybrid выглядит безопаснее, но фактически даёт две точки инварианта: constructor и factory. Это повышает риск divergence (расхождения) и снова поднимает вопрос DI в VO. Если factory является обязательным creation boundary (границей создания), constructor guard (защита конструктора) становится дублирующей защитой, а не архитектурной необходимостью.

**Итог:** фабрика нужна, но ровно одна — `ChainDefinitionFactory`, без набора per-VO factories (фабрик на каждый VO).

---

### 3.2. Развилка A: детальность сообщений об ошибках

**Решение: вариант (б) — detailed diagnostics (подробная диагностика) в `ChainDefinitionValidatorService`, fail-fast factory exception (быстрое исключение фабрики) generic.**

#### Почему не собирать детали в factory

Если factory начнёт повторно собирать:

- group name (имя группы),
- missing step name (имя отсутствующего шага),
- duplicate group name (имя дублирующей группы),

то получится второй detailed validation path (путь детальной валидации), параллельный `ChainDefinitionValidatorService`. Это ровно та же ловушка, что и текущий helper: правило начнёт жить в нескольких форматах и тестах.

Factory должна отвечать только за invariant enforcement (защиту инварианта):

```text
Chain "<name>": fix_iterations must reference existing named steps and each step name must belong to at most one fix_iteration group.
```

Сообщение намеренно generic (общее): оно подтверждает нарушение инварианта, но не пытается быть UI-report (пользовательским отчётом).

#### Что сделать с `ChainDefinitionValidatorService`

Оставить его владельцем detailed diagnostics:

- текущий unknown-step message (сообщение про неизвестный шаг) сохранить, тест `ChainDefinitionValidatorTest` не ломать;
- добавить диагностику duplicate membership (принадлежность шага нескольким groups), если бизнес хочет сохранить прежний уровень деталей из exception path;
- не переносить detailed message formatting (форматирование подробных сообщений) в factory/constructor.

Рекомендуемый new diagnostic (новая диагностика):

```text
fix_iteration step "<stepName>" belongs to multiple groups ("<firstGroup>" and "<secondGroup>").
```

#### Слепая зона

`ValidateChainConfigQueryHandler` сейчас сначала вызывает loader (загрузчик), а потом validator (валидатор). После перехода на generic fail-fast в factory возможен UX-regression (регресс пользовательского опыта): команда validation (валидация) может показать generic exception вместо detailed violations (подробных нарушений).

На ревью нужно проверить один из сценариев:

1. **Приемлем generic fail-fast в loader path.** Тогда detailed validator остаётся для internal diagnostics/tests (внутренней диагностики/тестов).
2. **Нужна детальная CLI-валидация.** Тогда нужен отдельный validation-only path (путь только для валидации), который создаёт candidate object (кандидат-объект) без factory guard или валидирует parsed parts (распарсенные части) до factory. Это отдельная архитектурная доработка, не надо маскировать её детальными exception-ами в factory.

Я не рекомендую решать эту слепую зону дублированием detailed pass (подробного прохода) внутри factory.

---

### 3.3. Развилка B: deprecated `ChainDefinitionVo`

**Решение: оставить deprecated `ChainDefinitionVo` как compatibility shim, но удалить helper и не добавлять новую подробную логику.**

Практический контракт:

- `ChainDefinitionVo` остаётся `@deprecated`; усилить PHPDoc: «Use `ChainDefinitionFactory` + specialized sub-VO (`StaticChainDefinitionVo`, `ConditionalChainDefinitionVo`, `DynamicChainDefinitionVo`)».
- Не мигрировать production code (боевой код) обратно на `ChainDefinitionVo`.
- Убрать `use ChainFixIterationsValidatorHelper`.
- В legacy static factories (устаревших статических фабричных методах) не держать алгоритм `nameMap + allGroupStepNames`; использовать общий `FixIterationsReferenceIntegritySpecification` или делегировать в минимальный private guard (приватную защиту) с generic exception.
- Не добавлять в `ChainDefinitionVo` detailed pass (подробный проход) и новые публичные API.

Почему не полноценная legacy-фабрика:

- чтобы external factory создавала `ChainDefinitionVo`, пришлось бы раскрывать private constructor (приватный конструктор) deprecated класса или добавлять internal factory hook (внутренний hook фабрики);
- цена — дополнительные методы/LOC ради класса, который уже помечен на удаление;
- `ChainDefinitionVo` уже на красной линии PHPMD LongClass: текущие изменения должны быть минимальны.

**Ограничение:** это legacy exception (наследованное исключение из правила), а не новый рекомендуемый pattern (паттерн). Все новые/боевые call sites должны идти через `ChainDefinitionFactory`.

Если ревьюер трактует hard constraint (жёсткое ограничение) буквально и для deprecated class тоже, допустимый fallback (запасной вариант): перенести generic fix-iterations guard из static factory в private constructor `ChainDefinitionVo::__construct()`. Это удовлетворит «исключение кидает constructor VO», но добавит несколько NLOC к классу у порога 500; делать только если PHPMD остаётся зелёным.

---

## 4. Дизайн-контракт для Backend Developer

### 4.1. Новая specification

**Файл:** `src/Module/ChainDefinition/Domain/Specification/Chain/FixIterationsReferenceIntegritySpecification.php`  
**FQCN:** `TaskOrchestrator\Common\Module\ChainDefinition\Domain\Specification\Chain\FixIterationsReferenceIntegritySpecification`

```php
final readonly class FixIterationsReferenceIntegritySpecification
{
    /**
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     */
    public function isSatisfiedBy(array $steps, array $fixIterations): bool;
}
```

Контракт поведения:

- `fixIterations === []` → `true`;
- build `nameMap` (карта имён) из `ChainStepVo::getName()` только для non-null names (не-null имён);
- для каждого `FixIterationGroupVo::getStepNames()`:
  - если step name отсутствует в `nameMap` → `false`;
  - если step name уже встречался в другой group → `false`;
- иначе `true`;
- никаких exception (исключений), никаких message (сообщений), никаких I/O.

### 4.2. Новая factory

**Файл:** `src/Module/ChainDefinition/Domain/Factory/ChainDefinitionFactory.php`  
**FQCN:** `TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainDefinitionFactory`

```php
final readonly class ChainDefinitionFactory
{
    public function __construct(
        private FixIterationsReferenceIntegritySpecification $fixIterationsReferenceIntegritySpecification,
    ) {
    }

    /**
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     * @param array<string, RoleConfigVo> $roles
     */
    public function createFromSteps(
        string $name,
        string $description,
        array $steps,
        array $fixIterations = [],
        array $roles = [],
        ?ChainRetryPolicyVo $defaultRetryPolicy = null,
        ?BudgetVo $budget = null,
        ?int $timeout = null,
    ): StaticChainDefinitionVo;

    /**
     * @param list<ChainStepVo> $steps
     * @param list<FixIterationGroupVo> $fixIterations
     * @param array<string, RoleConfigVo> $roles
     */
    public function createFromConditionalSteps(
        string $name,
        string $description,
        array $steps,
        array $fixIterations = [],
        array $roles = [],
        ?ChainRetryPolicyVo $defaultRetryPolicy = null,
        ?BudgetVo $budget = null,
        ?int $timeout = null,
    ): ConditionalChainDefinitionVo;

    /**
     * @param list<string> $participants
     * @param array<string, RoleConfigVo> $roles
     */
    public function createFromDynamic(
        string $name,
        string $description,
        string $facilitator,
        array $participants,
        int $maxRounds,
        string $brainstormSystemPrompt,
        string $facilitatorAppendPrompt,
        string $facilitatorStartPrompt,
        string $facilitatorContinuePrompt,
        string $facilitatorFinalizePrompt,
        string $participantAppendPrompt,
        string $participantUserPrompt,
        array $roles = [],
        ?ChainRetryPolicyVo $defaultRetryPolicy = null,
        ?BudgetVo $budget = null,
        ?int $timeout = null,
        ?int $maxTime = null,
    ): DynamicChainDefinitionVo;
}
```

Внутренние private methods (приватные методы), если нужны:

```php
/**
 * @param list<ChainStepVo> $steps
 * @param list<FixIterationGroupVo> $fixIterations
 */
private function assertStepBasedInvariant(string $name, array $steps, array $fixIterations): void;

/** @param array<string, RoleConfigVo> $roles */
private function createSharedDefinition(
    string $name,
    string $description,
    ChainTypeEnum $type,
    ?BudgetVo $budget,
    ?int $timeout,
    ?int $maxTime,
    array $roles,
): SharedChainDefinitionVo;
```

Exception contract (контракт исключений):

- empty steps (пустые шаги): сохранить сообщение текущего уровня точности:
  - `Chain "%s" must have at least one step.`
- invalid fix_iterations: generic:
  - `Chain "%s": fix_iterations must reference existing named steps and each step name must belong to at most one fix_iteration group.`
- dynamic guard (защита dynamic): сохранить текущие сообщения для facilitator/participants/prompts, так как они не конфликтуют с рассматриваемой specification.

### 4.3. Изменения VO constructors / static factories

#### `StaticChainDefinitionVo`

**Файл:** `src/Module/ChainDefinition/Domain/ValueObject/StaticChainDefinitionVo.php`

- Конструктор оставить thin constructor (тонкий конструктор), добавить `@internal Use ChainDefinitionFactory::createFromSteps()`.
- Из `createFromSteps()` убрать inline algorithm `nameMap + allGroupStepNames`.
- Рекомендуемый вариант: mark as deprecated (пометить устаревшим) and delegate to factory-compatible path (делегировать в фабричный путь) или оставить только для BC с generic guard через specification. Production code не должен его вызывать после миграции.

#### `ConditionalChainDefinitionVo`

**Файл:** `src/Module/ChainDefinition/Domain/ValueObject/ConditionalChainDefinitionVo.php`

- Аналогично `StaticChainDefinitionVo`.
- Конструктор `@internal Use ChainDefinitionFactory::createFromConditionalSteps()`.
- Убрать inline algorithm.

#### `DynamicChainDefinitionVo`

**Файл:** `src/Module/ChainDefinition/Domain/ValueObject/DynamicChainDefinitionVo.php`

- Конструктор `@internal Use ChainDefinitionFactory::createFromDynamic()`.
- Dynamic не участвует в `fix_iterations`; изменений по specification нет.
- Внутренние call sites должны перейти на factory для единообразия.

#### Deprecated `ChainDefinitionVo`

**Файл:** `src/Module/ChainDefinition/Domain/ValueObject/ChainDefinitionVo.php`

- Удалить import helper-а:
  - `TaskOrchestrator\Common\Module\ChainDefinition\Domain\Helper\ChainFixIterationsValidatorHelper`
- Не копировать обратно `nameMap + allGroupStepNames`.
- Для legacy `createLinearChain()` использовать общий `FixIterationsReferenceIntegritySpecification` с generic exception или, если потребуется буквальное соблюдение hard constraint, перенести этот generic guard в private constructor.
- Не добавлять detailed exception messages.
- Не увеличивать класс больше чем на 3–5 NLOC без повторного PHPMD замера.

### 4.4. Удаление helper и phpmd exception

Удалить файл:

- `src/Module/ChainDefinition/Domain/Helper/ChainFixIterationsValidatorHelper.php`

Удалить из `phpmd.xml`:

```xml
<property name="exceptions" value="TaskOrchestrator\Common\Module\ChainDefinition\Domain\Helper\ChainFixIterationsValidatorHelper"/>
```

Если после удаления exceptions list (список исключений) пустой — оставить property с пустым значением нельзя без проверки PHPMD. Лучше удалить property или заменить на актуальный список, если появятся другие legitimate exceptions (обоснованные исключения).

### 4.5. Call-site migrations

#### `YamlChainLoaderService`

**Файл:** `src/Module/ChainDefinition/Infrastructure/Service/Chain/YamlChainLoaderService.php`

Добавить DI dependency (зависимость через DI):

```php
public function __construct(
    string $yamlPath,
    private readonly ChainDefinitionFactory $chainDefinitionFactory,
) {
    // existing constructor logic
}
```

Заменить вызовы:

- строка ~152: `ConditionalChainDefinitionVo::createFromConditionalSteps(...)` → `$this->chainDefinitionFactory->createFromConditionalSteps(...)`
- строка ~164: `StaticChainDefinitionVo::createFromSteps(...)` → `$this->chainDefinitionFactory->createFromSteps(...)`
- строка ~196: `ConditionalChainDefinitionVo::createFromConditionalSteps(...)` → `$this->chainDefinitionFactory->createFromConditionalSteps(...)`
- строка ~295: `DynamicChainDefinitionVo::createFromDynamic(...)` → `$this->chainDefinitionFactory->createFromDynamic(...)`

`services.yaml` скорее всего не требует ручной регистрации: `TaskOrchestrator\Common\` auto-discovery исключает `Domain/ValueObject`, `Domain/Enum`, `Domain/Exception`, но не исключает `Domain/Factory` и `Domain/Specification`. На ревью проверить `bin/console debug:container ChainDefinitionFactory` или интеграционный тест контейнера, если есть.

#### Tests (тесты)

Обновить/добавить:

- `tests/Unit/Domain/Specification/Chain/FixIterationsReferenceIntegritySpecificationTest.php`
  - valid references → `true`;
  - empty fixIterations → `true`;
  - unknown step → `false`;
  - duplicate step across groups → `false`.
- `tests/Unit/Domain/Factory/ChainDefinitionFactoryTest.php`
  - creates static chain;
  - creates conditional chain;
  - creates dynamic chain;
  - empty steps throws current message;
  - invalid fixIterations throws generic message;
  - duplicate fixIterations throws generic message.
- `tests/Unit/Domain/Service/Chain/ChainDefinitionValidatorTest.php`
  - сохранить assertion на `references unknown step`;
  - добавить duplicate group diagnostic, если сохраняем прежнюю детальность;
  - обновить setup: validator может остаться без dependencies или получить specification, если backend решит использовать spec как fast pre-check.
- `tests/Unit/Application/Mapper/ChainDefinitionDtoMapperTest.php`
  - заменить static factory calls на `ChainDefinitionFactory` helper в тесте.
- `tests/Unit/Infrastructure/Service/Chain/YamlChainLoaderTest.php`
  - обновить construction/mocks, если тест вручную создаёт `YamlChainLoaderService`;
  - добавить проверку, что invalid fix_iterations из YAML падает generic factory exception или проходит detailed validation path — выбрать один ожидаемый UX.
- `tests/Unit/Domain/ValueObject/ChainDefinitionVoTest.php`
  - убрать `#[CoversClass(ChainFixIterationsValidatorHelper::class)]`;
  - заменить на `#[CoversClass(FixIterationsReferenceIntegritySpecification::class)]`, если legacy path использует spec напрямую;
  - не расширять тесты deprecated класса beyond BC (сверх обратной совместимости).

---

## 5. Как устраняется дублирование

Текущее состояние:

```text
StaticChainDefinitionVo::createFromSteps()          ~30 NLOC algorithm
ConditionalChainDefinitionVo::createFromConditionalSteps() ~30 NLOC algorithm
ChainFixIterationsValidatorHelper::assertValidReferences() ~30 NLOC algorithm + exceptions
ChainDefinitionValidatorService::validateStepBasedChain() detailed diagnostics path
```

Целевое состояние:

```text
FixIterationsReferenceIntegritySpecification::isSatisfiedBy() ~35–45 NLOC bool-only algorithm
ChainDefinitionFactory::assertStepBasedInvariant()             ~8–12 NLOC exception boundary
ChainDefinitionValidatorService::validateStepBasedChain()      detailed diagnostics owner
```

То есть guard algorithm copies (копии алгоритма защиты) **3 → 1**. Detailed diagnostics остаётся отдельной ответственностью и не считается копией guard boundary, но это место нужно держать синхронным тестами.

---

## 6. Проверка по конвенциям

### `FixIterationsReferenceIntegritySpecification`

Конвенция: `docs/conventions/layers/domain/specification.md`.

Почему подходит:

- формализует бизнес-правило домена;
- stateless (без состояния);
- `isSatisfiedBy()` возвращает только `bool`;
- не делает I/O;
- работает только с domain values (доменными значениями): `ChainStepVo`, `FixIterationGroupVo`.

### `ChainDefinitionFactory`

Конвенция: `docs/conventions/core_patterns/factory.md`.

Почему подходит:

- создание VO уже не помещается в «просто constructor» из-за `SharedChainDefinitionVo`, разных chain types (типов цепочек) и invariant guard (защиты инварианта);
- factory возвращает domain VO;
- factory может через DI получить specification;
- при нарушении инварианта кидает `InvalidArgumentException`;
- не делает I/O и не ходит во внешние сервисы.

### `StaticChainDefinitionVo` / `ConditionalChainDefinitionVo` / `DynamicChainDefinitionVo`

Конвенция: `docs/conventions/core_patterns/value-object.md`.

Почему подходит:

- остаются immutable readonly VO;
- не получают DI dependencies;
- constructors становятся thin/internal;
- invariant enforcement переносится на разрешённую внешнюю factory boundary.

### `ChainFixIterationsValidatorHelper`

Конвенция: `docs/conventions/core_patterns/helper.md`.

Почему **не** подходит:

- содержит бизнес-правило домена;
- кидает domain invariant exception;
- не является техническим преобразованием.

Решение: удалить.

---

## 7. Оценка влияния на PHPMD baseline

Ожидаемое влияние положительное.

### Уменьшение

- `StaticChainDefinitionVo`: убрать inline algorithm ~25–35 NLOC; класс станет примерно **160–175 NLOC** вместо ~196 nonblank.
- `ConditionalChainDefinitionVo`: аналогично **165–180 NLOC** вместо ~200 nonblank.
- Удаляется `ChainFixIterationsValidatorHelper` (**68 raw LOC**, ~63 nonblank LOC).
- Удаляется `StaticAccess exception` из `phpmd.xml`.

### Увеличение

- новая specification: ~35–45 NLOC, далеко от thresholds (порогов);
- новая factory: ~100–140 NLOC, далеко от LongClass/LongMethod при условии, что private methods короткие;
- `ChainDefinitionValidatorService`: +10–20 NLOC, если добавить duplicate diagnostic.

### Главный риск

`ChainDefinitionVo` deprecated: текущий класс **521 raw / ~481 nonblank**, исторический PHPMD NLOC около **493** при пороге 500. Любое расширение больше 3–5 NLOC рискованно.

Рекомендация backend-разработчику:

- не добавлять detailed validation в `ChainDefinitionVo`;
- не раскрывать legacy factory methods;
- после изменения обязательно запустить `vendor/bin/phpmd src text phpmd.xml` или `make check` path (если PHPMD входит в локальный набор), плюс обязательные `vendor/bin/phpunit` и `vendor/bin/psalm` перед финальным отчётом.

---

## 8. Риски и что проверить на ревью

1. **UX-regression in `validate-config` (регресс валидации конфига).**  
   Проверить, что пользователь либо получает ожидаемый generic exception, либо есть отдельный detailed validation path. Не притворяться, что `ChainDefinitionValidatorService` сработает, если loader упал раньше.

2. **Deprecated static factories (устаревшие статические фабрики) могут остаться обходным путём.**  
   Production code должен быть мигрирован на `ChainDefinitionFactory`. Static factories оставить только как BC shim или пометить `@deprecated`.

3. **Divergence между spec и validator (расхождение спецификации и валидатора).**  
   Добавить shared test fixtures (общие тестовые сценарии): valid, unknown step, duplicate step. Specification false должен соответствовать наличию validator violations.

4. **PHPMD LongClass on `ChainDefinitionVo`.**  
   Любое добавление в deprecated class измерять. Не добавлять verbose PHPDoc (многословные PHPDoc) внутрь него.

5. **DI autowiring (автосвязывание DI).**  
   Проверить, что `ChainDefinitionFactory` и `FixIterationsReferenceIntegritySpecification` попали в container (контейнер). `Domain/Specification` и `Domain/Factory` не исключены текущим `services.yaml`.

6. **Exception message tests (тесты сообщений исключений).**  
   Текущие tests могут ожидать detailed text (`unknown step name`, group names). Их надо перенести на validator tests; factory tests должны ожидать generic message.

7. **Удаление `phpmd.xml` exception.**  
   После удаления helper-а в `StaticAccess exceptions` не должен остаться мёртвый FQCN.

---

## 9. Финальный контракт решения

Backend должен реализовать не «новый helper под другим именем», а разнести ответственности:

```text
Specification = bool-only domain rule.
Factory = allowed exception boundary + creation of current VO.
ValidatorService = detailed diagnostics for UI/validation reports.
Deprecated ChainDefinitionVo = minimal BC shim, no new detailed logic.
```

Я бы отклонил PR, если увижу:

- `Specification::assert*()` или exception внутри specification;
- `Domain\Helper` с бизнес-правилом;
- factory, которая форматирует detailed diagnostics по group/step и дублирует validator;
- рост `ChainDefinitionVo` выше PHPMD threshold ради deprecated пути;
- оставленный `ChainFixIterationsValidatorHelper` в `phpmd.xml`.
