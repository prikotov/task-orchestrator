# Архитектурный дизайн: redesign агрегата DynamicLoopExecution под PHPMD

**Роль:** Архитектор Локи  
**Дата:** 2026-06-15  
**Объект:** `src/Module/DynamicLoop/Domain/Entity/DynamicLoopExecution.php`, модуль `DynamicLoop`, PR #261 `refactor/phpmd-baseline-elimination`  
**Задача:** спроектировать redesign (перепроектирование) in-memory aggregate (агрегата в памяти) `DynamicLoopExecution`, чтобы убрать последние 2 записи `phpmd.baseline.xml`: `TooManyPublicMethods` и `TooManyFields`.

---

## 0. Верифицированные факты и допущения

Проверено локально в ветке `refactor/phpmd-baseline-elimination`:

- `phpmd.baseline.xml` содержит 2 оставшиеся записи по `DynamicLoopExecution`: `TooManyPublicMethods` и `TooManyFields`.
- `phpmd.xml` в рабочем дереве уже содержит `exclude-pattern` для `bridge.php`; это незакоммиченная правка, не тронутая данным дизайном.
- При запуске PHPMD по `DynamicLoopExecution.php` с пустым baseline (базовой линией) воспроизводятся 2 нарушения:
  - `TooManyPublicMethods`: **14 public methods** при пороге **10**;
  - `TooManyFields`: **18 fields** при пороге **12**.
- `TooManyPublicMethods` берёт default (стандартные) свойства из `vendor/phpmd/phpmd/rulesets/codesize.xml`: `maxmethods=10`, `ignorepattern=(^(set|get|is|has|with))i`. `__construct` считается, `get*/set*/is*/has*/with*` не считаются.
- Запрошенный файл `docs/conventions/layers/domain/value_object.md` в репозитории отсутствует. Для правил `Value Object` использована действующая конвенция `docs/conventions/core_patterns/value-object.md`.
- `docs/conventions/layers/domain.md` существует, но пустой; применены `docs/conventions/layers/domain/entity.md`, `docs/conventions/core_patterns/value-object.md` и карта `docs/conventions/index.md`.

Внешние источники не использовались; анализ опирается на репозиторий и установленный `vendor/phpmd`.

### Прочитанные production call sites (места вызова)

- `src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopService.php`
- `src/Module/DynamicLoop/Domain/Service/Dynamic/ExecuteDynamicTurnService.php`
- `src/Module/DynamicLoop/Domain/Service/Dynamic/RecordDynamicRoundService.php`
- `src/Module/DynamicLoop/Domain/Service/Dynamic/CheckDynamicLoopBudgetService.php`
- `src/Module/DynamicLoop/Domain/Service/Dynamic/FinalizeDynamicLoopService.php`

### Прочитанные тесты с прямым/косвенным использованием execution state (состояния execution)

- `tests/Unit/Domain/Entity/DynamicLoopExecutionMaxTimeTest.php`
- `tests/Unit/Domain/Service/Chain/Dynamic/CheckDynamicLoopBudgetServiceTest.php`
- `tests/Unit/Domain/Service/Chain/Dynamic/ExecuteDynamicTurnServiceTest.php`
- `tests/Unit/Domain/Service/Chain/Dynamic/FinalizeDynamicLoopServiceTest.php`
- `tests/Unit/Domain/Service/Chain/Dynamic/RunDynamicLoopServiceFinalizeReserveTest.php`

---

## 1. Краткая сводка решения

Принять **вариант с owned mutable state components** (владельческими мутабельными компонентами состояния) внутри `DynamicLoopExecution`: вынести накопление метрик и список round results (результатов раундов) в `DynamicLoopMetrics`, а journal state (журнальное состояние) — в `DynamicLoopJournal`. Верхний aggregate (агрегат) сохраняет counters (счётчики), result state (результирующее состояние) и финальную сборку `DynamicLoopResultVo`, но больше не держит top-level public mutators (публичные мутации) `recordRound()`, `addRoleCost()`, `append*()`. Callers (места вызова) переходят на `$execution->getMetrics()->...` и `$execution->getJournal()->...`; read getters/setters (чтение/замена всего значения) на `DynamicLoopExecution` можно оставить как compatibility delegates (делегаты совместимости), потому что PHPMD их игнорирует и они минимизируют риск регрессии.

---

## 2. Цели и метрики

| Метрика | Сейчас | Цель | После redesign (оценка) |
|---|---:|---:|---:|
| `DynamicLoopExecution` fields (поля) | 18 | `<= 12` | **11** |
| `DynamicLoopExecution` counted public methods (публичные методы, не matching ignorepattern) | 14 | `<= 10` | **9** |
| Новые нарушения `TooManyFields` | — | 0 | `DynamicLoopMetrics`: 6 fields; `DynamicLoopJournal`: 3 fields |
| Новые нарушения `TooManyPublicMethods` | — | 0 | `DynamicLoopMetrics`: 3 counted; `DynamicLoopJournal`: 4 counted |
| Behavioral change (изменение поведения) | — | 0 | `toLoopResultVo()` должен собрать те же значения в том же порядке |

Текущие counted public methods (14):

```text
__construct,
advanceStep, advanceRound, advanceParticipantRounds,
recordRound, addRoleCost,
appendFacilitatorJournal, appendDiscussionHistory, appendFacilitatorSummary,
markMaxRoundsReached, markBudgetWarning80Logged, markMaxTimeExceeded,
restoreFromRoundFiles,
toLoopResultVo
```

Целевые counted public methods в `DynamicLoopExecution` (9):

```text
__construct,
advanceStep, advanceRound, advanceParticipantRounds,
markMaxRoundsReached, markBudgetWarning80Logged, markMaxTimeExceeded,
restoreFromRoundFiles,
toLoopResultVo
```

Почему `getMetrics()` и `getJournal()` не добавляют нарушение: они matching `ignorepattern` `(^(set|get|is|has|with))i` и являются обычными accessors (аксессорами), но возвращают mutable reference (мутабельную ссылку) на owned component (владельческий компонент).

---

## 3. Mini-ADR по развилкам

### 3.1. Развилка 1: делегирующие методы vs переписывание callers

**Решение: вариант (б) — переписать callers на mutable sub-objects (мутабельные вложенные объекты), оставив только read/set delegates (делегаты чтения/замены) на aggregate.**

Production code должен заменить top-level write calls (верхнеуровневые вызовы записи):

```php
$execution->recordRound($roundResult);
$execution->addRoleCost($role, $cost);
$execution->appendFacilitatorJournal($entry);
$execution->appendDiscussionHistory($entry);
$execution->appendFacilitatorSummary($entry);
```

на:

```php
$execution->getMetrics()->recordRound($roundResult);
$execution->getMetrics()->addRoleCost($role, $cost);
$execution->getJournal()->appendFacilitatorJournal($entry);
$execution->getJournal()->appendDiscussionHistory($entry);
$execution->getJournal()->appendFacilitatorSummary($entry);
```

**Почему не вариант (а):** если оставить `recordRound()`, `addRoleCost()` и `append*()` как delegates (делегаты) в `DynamicLoopExecution`, counted public methods останутся **14**, то есть `TooManyPublicMethods` не устранится.

**Почему не полный вариант (в):** hybrid (гибрид) с частью делегатов создаёт нестабильный API: разработчик не понимает, что мутировать через aggregate, а что через component. Исключение допустимо только для `get*/set*`, потому что они уже были в API, игнорируются PHPMD и являются compatibility layer (слоем совместимости), а не новым behavior API (поведенческим API).

**Архитектурная цена:** aggregate отдаёт mutable reference (мутабельную ссылку). Это осознанно: `DynamicLoopExecution` уже является in-memory mutable entity (мутабельной сущностью в памяти), а services (сервисы) уже напрямую меняют его состояние. Новые sub-objects не расширяют полномочия caller-ов, а только переносят существующие мутации в более мелкие state components.

### 3.2. Развилка 2: sub-objects — Entity или Value Object

**Решение: не `Value Object`; использовать `Domain\Entity` owned mutable state components без суффикса `Vo`.**

Конвенция `Value Object` (`docs/conventions/core_patterns/value-object.md`) требует:

- `final readonly class`;
- отсутствие setters (сеттеров);
- pure methods (чистых методов) без побочных эффектов;
- equality by value (равенство по значению).

`DynamicLoopMetrics` и `DynamicLoopJournal` нарушают это намеренно: они делают `+=`, append (добавление в строку/список), накопление arrays (массивов). Значит, `*Vo` и `Domain\ValueObject` здесь неверны.

Строгая конвенция `Entity` говорит про идентификатор и ORM `*Model`, но в проекте уже есть локальный precedent (прецедент): `DynamicLoopExecution` и `StaticChainExecution` — **in-memory non-persistent entities** (неперсистентные сущности в памяти) без `Model` и без собственного id. Новые классы должны следовать именно этому локальному паттерну:

```text
src/Module/DynamicLoop/Domain/Entity/DynamicLoopMetrics.php
src/Module/DynamicLoop/Domain/Entity/DynamicLoopJournal.php
```

Важно: это не standalone Entity (самостоятельная сущность) и не aggregate root (корень агрегата). Это **owned component of DynamicLoopExecution** (владельческий компонент агрегата). Его identity (идентичность) — родительский `DynamicLoopExecution` + имя поля (`metrics`/`journal`). Поэтому:

- классы `final`;
- без repository (репозитория);
- без id;
- без `Model`;
- PHPDoc: `@internal Owned by DynamicLoopExecution`.

Mutable VO (мутабельный объект-значение) отклонён: он противоречит конвенциям и создаст ложный сигнал для следующих разработчиков.

### 3.3. Развилка 3: группировка

**Решение: вынести 6 metric-related fields (поля метрик) и 3 journal-related fields (поля журнала). Counters и result state оставить в aggregate.**

#### `DynamicLoopMetrics`

Поля:

```text
totalTime: float
totalInputTokens: int
totalOutputTokens: int
totalCost: float
roleCosts: array<string, float>
roundResults: list<DynamicRoundResultVo>
```

Методы:

```text
recordRound(DynamicRoundResultVo): void
addRoleCost(string, float): void
getRoundResults(): list<DynamicRoundResultVo>
getTotals(): array{time: float, in: int, out: int, cost: float}
getRoleCosts(): array<string, float>
getTotalCost(): float
```

`recordRound()` остаётся atomic (атомарным) относительно round result + aggregate totals: добавление результата и `total* += ...` живут в одном объекте, как сейчас.

#### `DynamicLoopJournal`

Поля:

```text
discussionHistory: string
facilitatorJournal: string
facilitatorSummary: string
```

Методы:

```text
appendFacilitatorJournal(string): void
setFacilitatorJournal(string): void
getFacilitatorJournal(): string
appendDiscussionHistory(string): void
setDiscussionHistory(string): void
getDiscussionHistory(): string
appendFacilitatorSummary(string): void
getFacilitatorSummary(): string
```

#### `DynamicLoopExecution` после группировки

Поля:

```text
metrics: DynamicLoopMetrics
journal: DynamicLoopJournal
step: int
round: int
participantRounds: int
synthesis: ?string
maxRoundsReached: bool
interruptionReason: ?string
budgetBreak: ?DynamicBudgetCheckVo
budgetWarning80Logged: bool
maxTimeExceeded: bool
```

Итого **11 fields**, ниже порога 12.

Почему не выносить `Counters` сейчас:

- PHPMD-цель уже достигнута без третьего класса;
- `advanceStep()/advanceRound()/advanceParticipantRounds()` являются orchestration control (управлением ходом цикла) и читаются в services (сервисах) как core lifecycle (жизненный цикл) execution;
- третий mutable component увеличит surface area (площадь API) и число изменяемых references (ссылок) без необходимости.

Почему не выносить `ResultState` сейчас:

- `toLoopResultVo()` — ответственность aggregate, потому что именно он знает завершение цикла, budget break (прерывание по бюджету), max time (максимальное время) и synthesis (синтез);
- result fields (результирующие поля) связаны с финальной DTO-сборкой, а не с самостоятельным доменным процессом.

### 3.4. Развилка 4: naming и размещение

**Решение:**

```text
src/Module/DynamicLoop/Domain/Entity/DynamicLoopMetrics.php
TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity\DynamicLoopMetrics

src/Module/DynamicLoop/Domain/Entity/DynamicLoopJournal.php
TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity\DynamicLoopJournal
```

Обоснование:

- `Domain\Entity`, потому что mutable state (мутабельное состояние), не `Domain\ValueObject`;
- без `Vo`, потому что не immutable VO;
- без `Model`, потому что не ORM/persistent (персистентная) entity;
- class name lengths (длины имён классов): `DynamicLoopMetrics` = 18, `DynamicLoopJournal` = 18, ниже `LongClassName` threshold (порог) 45;
- prefix `DynamicLoop` сохраняет модульный контекст и не конфликтует с `StaticChainExecution`.

Допустимый альтернативный naming, если reviewer (ревьюер) хочет подчеркнуть владение aggregate:

```text
DynamicLoopExecutionMetrics
DynamicLoopExecutionJournal
```

Оба имени тоже ниже порога 45. Я предпочитаю shorter names (короткие имена), потому что namespace уже ограничивает контекст.

### 3.5. Развилка 5: тесты

**Решение: добавить focused unit tests (точечные unit-тесты) для двух новых components и обновить минимально существующие tests/callers.**

Новые тесты:

```text
tests/Unit/Domain/Entity/DynamicLoopMetricsTest.php
tests/Unit/Domain/Entity/DynamicLoopJournalTest.php
```

`DynamicLoopMetricsTest`:

- default totals (нулевые totals) и empty lists/maps (пустые списки/карты);
- `recordRound()` добавляет `DynamicRoundResultVo` в конец списка и аккумулирует `time/in/out/cost`;
- несколько `recordRound()` сохраняют порядок `roundResults` и суммируют totals;
- `addRoleCost()` аккумулирует стоимость по role (роли), включая повторную роль;
- `getTotals()` возвращает array shape (форму массива) и порядок ключей **`time`, `in`, `out`, `cost`** как сейчас.

`DynamicLoopJournalTest`:

- constructor (конструктор) принимает initial discussion/facilitator journal (начальные журналы);
- `appendDiscussionHistory()`, `appendFacilitatorJournal()`, `appendFacilitatorSummary()` делают точное string concatenation (конкатенацию строк) без разделителей;
- `setDiscussionHistory()` и `setFacilitatorJournal()` полностью заменяют значение.

Обновить существующие тесты:

- `DynamicLoopExecutionMaxTimeTest`: заменить прямой `appendFacilitatorJournal()` на `$execution->getJournal()->appendFacilitatorJournal(...)` либо оставить assertion через `getFacilitatorJournal()`.
- Добавить/расширить test на `DynamicLoopExecution::toLoopResultVo()` с заранее записанными metrics через `getMetrics()->recordRound()` и budget break через `setBudgetBreak()`, чтобы зафиксировать byte-to-byte contract (контракт точного результата) по полям `DynamicLoopResultVo`.

Existing service tests (существующие сервисные тесты):

- `CheckDynamicLoopBudgetServiceTest`, `ExecuteDynamicTurnServiceTest`, `FinalizeDynamicLoopServiceTest`, `RunDynamicLoopServiceFinalizeReserveTest` должны остаться сценарно теми же. Меняются только production implementations (реализации) внутри сервисов; assertions (утверждения) через getters aggregate можно оставить.
- Если в ходе реализации backend решит заменить read delegates на прямой доступ к components, тогда service tests надо обновить точечно. Но я рекомендую read delegates сохранить, чтобы не раздувать diff (изменения).

### 3.6. Развилка 6: risks и blind spots

**Решение:** реализация безопасна при строгом запрете на top-level delegating write methods (верхнеуровневые делегирующие методы записи) и при PHPMD-проверке без baseline.

Главные blind spots:

1. **PHPMD ignorepattern can hide accidental API growth (рост API).**  
   `getMetrics()` и `getJournal()` не считаются. Это нормально, но нельзя добавлять в `DynamicLoopExecution` новые `append*()/record*/add*()` delegates — они снова поднимут count выше 10.

2. **Mutable reference leakage (утечка мутабельной ссылки).**  
   Caller получает ссылку на component и может мутировать state. Это уже модель текущего aggregate; mitigation (смягчение) — `@internal Owned by DynamicLoopExecution`, маленький API components и отсутствие setter-а для замены всего component.

3. **Value Object convention violation risk (риск нарушения конвенции VO).**  
   Не использовать `Vo` suffix (суффикс) и `Domain\ValueObject` для mutable classes.

4. **Deptrac compatibility (совместимость Deptrac).**  
   `Domain\Entity` → `Domain\Entity` и `Domain\Entity` → `Domain\ValueObject` разрешены. Новые classes в `Domain` не должны ссылаться на Application/Infrastructure.

5. **Serialization/persist (сериализация/персистентность).**  
   `DynamicLoopExecution` не персистентный. `restoreFromRoundFiles()` восстанавливает только counters из session files (файлов сессии), не metrics/journal. Redesign не должен добавлять serialization hooks (хуки сериализации) или storage contract (контракт хранения).

6. **`toLoopResultVo()` exactness (точность).**  
   Риск — нарушить порядок `roundResults` или array shape `getTotals()`. Mitigation — отдельные tests на `DynamicLoopMetrics` и contract test на `DynamicLoopExecution::toLoopResultVo()`.

---

## 4. Итоговая структура

### 4.1. Новые классы

#### `DynamicLoopMetrics`

**Путь:** `src/Module/DynamicLoop/Domain/Entity/DynamicLoopMetrics.php`  
**NLOC estimate:** 60–80  
**Fields:** 6  
**Counted public methods:** 3 (`__construct`, `recordRound`, `addRoleCost`)  
**Назначение:** owned mutable component (владельческий мутабельный компонент) для round results (результатов раундов), total metrics (итоговых метрик) и role costs (стоимости по ролям).

Контракт:

```php
final class DynamicLoopMetrics
{
    /** @return list<DynamicRoundResultVo> */
    public function getRoundResults(): array;

    /** @return array{time: float, in: int, out: int, cost: float} */
    public function getTotals(): array;

    /** @return array<string, float> */
    public function getRoleCosts(): array;

    public function getTotalCost(): float;
    public function recordRound(DynamicRoundResultVo $roundResult): void;
    public function addRoleCost(string $role, float $cost): void;
}
```

#### `DynamicLoopJournal`

**Путь:** `src/Module/DynamicLoop/Domain/Entity/DynamicLoopJournal.php`  
**NLOC estimate:** 45–65  
**Fields:** 3  
**Counted public methods:** 4 (`__construct`, `appendFacilitatorJournal`, `appendDiscussionHistory`, `appendFacilitatorSummary`)  
**Назначение:** owned mutable component для discussion history (истории обсуждения), facilitator journal (журнала фасилитатора) и facilitator summary (краткой сводки фасилитатора).

Контракт:

```php
final class DynamicLoopJournal
{
    public function __construct(
        string $discussionHistory = '',
        string $facilitatorJournal = '',
    );

    public function getDiscussionHistory(): string;
    public function setDiscussionHistory(string $history): void;
    public function appendDiscussionHistory(string $entry): void;

    public function getFacilitatorJournal(): string;
    public function setFacilitatorJournal(string $journal): void;
    public function appendFacilitatorJournal(string $entry): void;

    public function getFacilitatorSummary(): string;
    public function appendFacilitatorSummary(string $entry): void;
}
```

### 4.2. Изменения `DynamicLoopExecution`

Новые fields:

```php
private DynamicLoopMetrics $metrics;
private DynamicLoopJournal $journal;
```

Удалить fields:

```text
totalTime, totalInputTokens, totalOutputTokens, totalCost, roleCosts,
roundResults,
discussionHistory, facilitatorJournal, facilitatorSummary
```

Добавить ignored accessors (аксессоры, игнорируемые PHPMD):

```php
public function getMetrics(): DynamicLoopMetrics;
public function getJournal(): DynamicLoopJournal;
```

Удалить counted write methods из aggregate:

```text
recordRound
addRoleCost
appendFacilitatorJournal
appendDiscussionHistory
appendFacilitatorSummary
```

Оставить compatibility delegates (по желанию backend-разработчика, рекомендую оставить):

```text
getRoundResults, getTotals, getRoleCosts, getTotalCost,
getDiscussionHistory, getFacilitatorJournal, getFacilitatorSummary,
setDiscussionHistory, setFacilitatorJournal
```

Они matching ignorepattern (начинаются с `get`/`set`) и позволяют не переписывать read-side (сторону чтения) в сервисах.

`toLoopResultVo()` должен брать данные из metrics:

```php
return new DynamicLoopResultVo(
    roundResults: $this->metrics->getRoundResults(),
    totalTime: $this->metrics->getTotals()['time'],
    totalInputTokens: $this->metrics->getTotals()['in'],
    totalOutputTokens: $this->metrics->getTotals()['out'],
    totalCost: $this->metrics->getTotals()['cost'],
    // result state без изменений
);
```

Рекомендация: внутри `toLoopResultVo()` сохранить locals (локальные переменные) для totals, чтобы не вызывать `getTotals()` 4 раза:

```php
$totals = $this->metrics->getTotals();
```

Это не меняет output (вывод) и уменьшает noise (шум).

### 4.3. Изменения callers

Production files (5):

1. `RecordDynamicRoundService.php`
   - `recordRound()` → `getMetrics()->recordRound()`.
2. `ExecuteDynamicTurnService.php`
   - `addRoleCost()` → `getMetrics()->addRoleCost()`;
   - `appendDiscussionHistory()` → `getJournal()->appendDiscussionHistory()`;
   - `appendFacilitatorJournal()` → `getJournal()->appendFacilitatorJournal()`;
   - `appendFacilitatorSummary()` → `getJournal()->appendFacilitatorSummary()`.
3. `CheckDynamicLoopBudgetService.php`
   - `appendFacilitatorJournal()` → `getJournal()->appendFacilitatorJournal()`;
   - budget reads can stay as `getTotalCost()/getRoleCosts()` delegates.
4. `FinalizeDynamicLoopService.php`
   - `appendFacilitatorJournal()` → `getJournal()->appendFacilitatorJournal()`;
   - `setFacilitatorJournal()` can stay as aggregate delegate.
5. `RunDynamicLoopService.php`
   - `appendFacilitatorJournal()` in time reserve flow → `getJournal()->appendFacilitatorJournal()`.

Interfaces do not change.

---

## 5. Контрольный список для Левша

1. **Перед правками**
   - Убедиться, что работа идёт в ветке `refactor/phpmd-baseline-elimination`, не в `main`.
   - Не трогать уже существующие незакоммиченные изменения `phpmd.xml` / `phpmd.baseline.xml` сверх удаления финальных baseline entries после реализации.

2. **Создать components**
   - Добавить `DynamicLoopMetrics` в `Domain\Entity`.
   - Добавить `DynamicLoopJournal` в `Domain\Entity`.
   - Добавить PHPDoc `@internal Owned by DynamicLoopExecution`.
   - Не использовать suffix `Vo`, не размещать в `Domain\ValueObject`.

3. **Перенести fields**
   - Перенести accumulators + `roundResults` в `DynamicLoopMetrics`.
   - Перенести journal strings в `DynamicLoopJournal`.
   - В `DynamicLoopExecution::__construct()` создать оба components:
     - `new DynamicLoopMetrics()`;
     - `new DynamicLoopJournal($initialDiscussionHistory, $initialFacilitatorJournal)`.

4. **Сузить behavior API aggregate**
   - Удалить из `DynamicLoopExecution` methods:
     - `recordRound()`;
     - `addRoleCost()`;
     - `appendFacilitatorJournal()`;
     - `appendDiscussionHistory()`;
     - `appendFacilitatorSummary()`.
   - Добавить `getMetrics()` и `getJournal()`.
   - Оставить read/set delegates (`getTotals()`, `getRoundResults()`, `getFacilitatorJournal()`, `setFacilitatorJournal()` и т.п.) для compatibility.

5. **Обновить callers**
   - Переписать write calls на `getMetrics()` / `getJournal()` в 5 production files.
   - Не менять signatures (сигнатуры) сервисов и interfaces.

6. **Зафиксировать exact result contract**
   - `toLoopResultVo()` должен вернуть те же поля `DynamicLoopResultVo`, в том же порядке constructor arguments (аргументов конструктора), с теми же defaults (значениями по умолчанию):
     - `budgetExceeded`: `$this->budgetBreak?->budgetExceeded ?? false`;
     - `budgetLimit`: `$this->budgetBreak?->budgetLimit ?? 0.0`;
     - `budgetExceededRole`: `$this->budgetBreak?->budgetExceededRole`;
     - `maxTimeExceeded`: `$this->maxTimeExceeded`.

7. **Тесты**
   - Добавить `DynamicLoopMetricsTest` и `DynamicLoopJournalTest`.
   - Обновить `DynamicLoopExecutionMaxTimeTest` для journal append через `getJournal()`.
   - Добавить contract test на `toLoopResultVo()` после `getMetrics()->recordRound()`.
   - Убедиться, что service tests зелёные без сценарных изменений.

8. **PHPMD cleanup**
   - После реализации удалить 2 entries `DynamicLoopExecution` из `phpmd.baseline.xml`.
   - Запустить PHPMD без baseline или с временно пустым baseline для `DynamicLoopExecution.php`, чтобы увидеть реальный count.
   - Не менять пороги `phpmd.xml`.

9. **Финальные проверки**
   - `vendor/bin/phpunit`
   - `vendor/bin/psalm`
   - Желательно дополнительно: `vendor/bin/phpmd analyze src --format text --ruleset phpmd.xml --no-progress` или проектный эквивалент.

---

## 6. Риски и mitigation

| Риск | Что может сломаться | Mitigation |
|---|---|---|
| Возврат mutable reference через `getMetrics()/getJournal()` | Caller может мутировать component вне ожидаемого места | Компоненты маленькие, `final`, `@internal`, API только под текущие операции; не добавлять generic setters для whole component |
| Случайное сохранение delegating write methods в aggregate | `TooManyPublicMethods` останется 14 или станет >10 | Запретить top-level `recordRound/addRoleCost/append*`; проверить PHPMD без baseline |
| Ошибка в `toLoopResultVo()` | Output dynamic loop изменится | Contract test с round results, totals, budget break, max time |
| Неверный тип sub-object как `Vo` | Нарушение VO convention | Размещать в `Domain\Entity`, без suffix `Vo`, объяснить в PHPDoc |
| Рост новых классов под PHPMD | Новые violations | Metrics 6 fields/3 counted; Journal 3 fields/4 counted; не добавлять лишние behavior methods |
| Deptrac violation | Domain component случайно потянет Infrastructure/Application | В новых classes использовать только primitives и Domain VOs (`DynamicRoundResultVo`) |
| Resume/session behavior | `restoreFromRoundFiles()` начнёт трогать не те counters | Оставить method в aggregate без изменения алгоритма |
| Tests masking behavior | Сервисные тесты не ловят exact DTO | Добавить direct entity contract test на `toLoopResultVo()` |

---

## 7. Won't Have / вне scope

- Не менять thresholds (пороги) `phpmd.xml`.
- Не переименовывать rules (`TooManyPublicMethods`, `TooManyFields`) и не добавлять suppressions (подавления).
- Не делать immutable redesign (иммутабельное перепроектирование) `DynamicLoopExecution`: это изменит модель выполнения и потребует массового переписывания services.
- Не выносить `Counters` в отдельный component: цель PHPMD достигается без этого.
- Не выносить `ResultState` в отдельный component: это усложнит `toLoopResultVo()` и финализацию без необходимости.
- Не добавлять persistence/serialization (персистентность/сериализацию) для `DynamicLoopExecution`, `DynamicLoopMetrics`, `DynamicLoopJournal`.
- Не менять формат session files (файлов сессии), audit DTO (DTO аудита) и output DTO (DTO результата).
- Не делать broad refactoring (широкий рефакторинг) `DynamicLoop` services.

---

## 8. Финальный контракт решения

Backend implementation (реализация backend) должна закончиться состоянием:

```text
DynamicLoopExecution
  fields: 11
  counted public methods: 9
  owns DynamicLoopMetrics and DynamicLoopJournal
  still builds DynamicLoopResultVo exactly as before

DynamicLoopMetrics
  mutable, Domain\Entity owned component
  fields: 6
  counted public methods: 3

DynamicLoopJournal
  mutable, Domain\Entity owned component
  fields: 3
  counted public methods: 4
```

Я бы отклонил implementation PR (PR реализации), если увижу:

- `DynamicLoopMetricsVo` / `DynamicLoopJournalVo` с mutable methods;
- оставленные `DynamicLoopExecution::recordRound()`, `addRoleCost()`, `append*()` delegates;
- изменение `toLoopResultVo()` output contract;
- изменение `phpmd.xml` thresholds вместо устранения причины;
- третий/четвёртый component без измеримой причины.
