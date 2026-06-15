# Code Review: редизайн агрегата DynamicLoopExecution

**Роль:** Ревьювер Бэка Пуаро
**Дата:** 2026-06-15
**Объект:** `src/Module/DynamicLoop/Domain/Entity/DynamicLoopExecution.php` + 2 новых компонента (`DynamicLoopMetrics`, `DynamicLoopJournal`) + 2 новых теста + 5 callers + `DynamicLoopExecutionMaxTimeTest` + `phpmd.baseline.xml`/`phpmd.xml`
**Задача:** PR #261 (epic `refactor/phpmd-baseline-elimination`), ветка `refactor/phpmd-baseline-elimination`. Ревью реализации Левша по принятому дизайну Локи (`docs/agents/reports/system-architect/2026-06-15_18-00_dynamicloop-aggregate-redesign.md`).

---

## Рефлексия запроса

- 🧩 **сложность запроса:** 5/10 — многоточечное ревью (8 объектов, 6 развилок дизайна, zero behavioral change), но скоуп чёткий и эталон зафиксирован.
- 🗂️ **уровень контекста:** 8/10 — предоставлены дизайн Локи, отчёт Левша, оригинал из HEAD, полный список объектов и чек-лист по секциям.
- 🛡️ **риск ошибки:** 3/10 — изменения локализованы в Domain-слое, все проверки (PHPUnit/Psalm/PHPMD/Deptrac) запускаемы и воспроизведены.

Запрос **не проблемный**.

📚 Внешние источники не использовались; анализ опирается на репозиторий, `vendor/phpmd` и `vendor/prikotov/coding-standard`.

---

## Воспроизведённые проверки (независимо от отчёта Левша)

| Проверка | Команда | Результат |
|---|---|---|
| PHPUnit | `vendor/bin/phpunit` | ✅ OK — 1030 tests, 2872 assertions |
| Psalm | `vendor/bin/psalm` | ✅ No errors found |
| Deptrac | `vendor/bin/deptrac analyse --config-file=depfile.yaml` | ✅ Violations: 0, Errors: 0, Warnings: 0 |
| PHPMD (полный, с пустым baseline) | `vendor/bin/phpmd analyze src --format=text --ruleset=phpmd.xml --baseline-file=phpmd.baseline.xml` | ✅ exit=0, 0 violations |
| PHPMD (3 целевых файла по отдельности) | `vendor/bin/phpmd analyze <file> --format=text --ruleset=phpmd.xml` | ✅ exit=0 на каждом |
| PHPCS (новые src + новые тесты) | `vendor/bin/phpcs --standard=phpcs.xml.dist ...` | ✅ exit=0 |

Все утверждения из отчёта Левша (раздел 4) подтверждены независимо.

---

## A. СООТВЕТСТВИЕ ДИЗАЙНУ ЛОКИ (6 развилок)

### A.1 — Metrics/Journal в `Domain\Entity` (НЕ ValueObject): **PASS**
- `DynamicLoopMetrics`: namespace `...\Domain\Entity`, путь `src/Module/DynamicLoop/Domain/Entity/DynamicLoopMetrics.php`.
- `DynamicLoopJournal`: namespace `...\Domain\Entity`, путь `src/Module/DynamicLoop/Domain/Entity/DynamicLoopJournal.php`.
- Ни один не в `Domain\ValueObject`. Соответствует ADR 3.2 / 3.4.

### A.2 — Mutable owned components, `@internal`: **PASS**
- Оба `final class` (НЕ `final readonly`).
- PHPDoc обоих: `@internal Owned by DynamicLoopExecution. Не самостоятельная сущность и не aggregate root.`
- Соответствует ADR 3.2.

### A.3 — Top-level write-методы удалены из aggregate: **PASS**
- `recordRound`, `addRoleCost`, `appendFacilitatorJournal`, `appendDiscussionHistory`, `appendFacilitatorSummary` — отсутствуют в `DynamicLoopExecution`.
- `grep` по `src/`, `apps/`, `tests/` подтверждает: ни одного остаточного вызова `$execution->recordRound(...)` / `$execution->addRoleCost(...)` / `$execution->append*(...)`.
- Методы существуют только на компонентах. Соответствует ADR 3.1.

### A.4 — `getMetrics()`/`getJournal()` возвращают mutable reference, matching `^get`: **PASS**
- Возвращают `$this->metrics` / `$this->journal` напрямую (мутабельная ссылка).
- PHPMD `ignorepattern` `(^(set|get|is|has|with))i` — не считаются. Соответствует ADR 3.1.

### A.5 — Read/set delegates сохранены (compatibility): **PASS**
- Сохранены на `DynamicLoopExecution`: `getRoundResults`, `getTotals`, `getRoleCosts`, `getTotalCost`, `getDiscussionHistory`, `setDiscussionHistory`, `getFacilitatorJournal`, `setFacilitatorJournal`, `getFacilitatorSummary`.
- Все делегируют в `metrics`/`journal`. Read-side в callers не тронут (подтверждено git diff). Соответствует ADR 3.1.

### A.6 — Counters и result state остались в aggregate: **PASS**
- В `DynamicLoopExecution` остались: `step`, `round`, `participantRounds`, `synthesis`, `maxRoundsReached`, `interruptionReason`, `budgetBreak`, `budgetWarning80Logged`, `maxTimeExceeded`.
- `advanceStep`/`advanceRound`/`advanceParticipantRounds`, `restoreFromRoundFiles`, `toLoopResultVo` — в aggregate. Соответствует ADR 3.3.

**Итог A: PASS по всем 6 развилкам.**

---

## B. ZERO BEHAVIORAL CHANGE (сверка с `git show HEAD:...`)

### B.1 — `toLoopResultVo()` byte-to-byte: **PASS**
Сверка поле-в-поле (новый → оригинал):

| Поле `DynamicLoopResultVo` | Новый код | Оригинал (HEAD) | Эквивалентность |
|---|---|---|---|
| `roundResults` | `$this->metrics->getRoundResults()` | `$this->roundResults` | ✅ тот же список, тот же порядок |
| `totalTime` | `$totals['time']` | `$this->totalTime` | ✅ = `metrics.totalTime` |
| `totalInputTokens` | `$totals['in']` | `$this->totalInputTokens` | ✅ |
| `totalOutputTokens` | `$totals['out']` | `$this->totalOutputTokens` | ✅ |
| `totalCost` | `$totals['cost']` | `$this->totalCost` | ✅ |
| `synthesis` | `$this->synthesis` | `$this->synthesis` | ✅ без изменений |
| `maxRoundsReached` | `$this->maxRoundsReached` | то же | ✅ |
| `interruptionReason` | `$this->interruptionReason` | то же | ✅ |
| `budgetExceeded` | `$this->budgetBreak?->budgetExceeded ?? false` | то же | ✅ + `@phpstan-ignore` сохранён |
| `budgetLimit` | `$this->budgetBreak?->budgetLimit ?? 0.0` | то же | ✅ + `@phpstan-ignore` сохранён |
| `budgetExceededRole` | `$this->budgetBreak?->budgetExceededRole` | то же | ✅ |
| `maxTimeExceeded` | `$this->maxTimeExceeded` | то же | ✅ |

Порядок named arguments в конструкторе `DynamicLoopResultVo` идентичен. `@phpstan-ignore nullsafe.neverNull` комментарии сохранены.

### B.2 — `getTotals()` порядок ключей `time, in, out, cost`: **PASS**
- `DynamicLoopMetrics::getTotals()`: `['time' => ..., 'in' => ..., 'out' => ..., 'cost' => ...]`.
- Delegate `DynamicLoopExecution::getTotals()` → `$this->metrics->getTotals()` (без перестроения массива).
- Тест `getTotalsReturnsExactKeyOrderTimeInOutCost` верифицирует `array_keys()` через `assertSame`.

### B.3 — `recordRound()` атомарность (append + accumulate в одном порядке): **PASS**
Реализация в `DynamicLoopMetrics` посимвольно идентична оригиналу:
```
$this->roundResults[] = $roundResult;
$this->totalTime += $roundResult->duration;
$this->totalInputTokens += $roundResult->inputTokens;
$this->totalOutputTokens += $roundResult->outputTokens;
$this->totalCost += $roundResult->cost;
```
Порядок `+=` сохранён.

### B.4 — `addRoleCost()` аккумулирование: **PASS**
Идентично: `$this->roleCosts[$role] = ($this->roleCosts[$role] ?? 0.0) + $cost`.

### B.5 — `append*` — чистая конкатенация без разделителей: **PASS**
Все три (`appendDiscussionHistory`, `appendFacilitatorJournal`, `appendFacilitatorSummary`) — `.= $entry`, без вставки разделителей.

### B.6 — `restoreFromRoundFiles()` — алгоритм не тронут: **PASS**
Метод остался в `DynamicLoopExecution`, тело идентично HEAD. Восстанавливает только `round`/`participantRounds` из `$roundFiles`.

### B.7 — `__construct` — initial values прокидываются корректно: **PASS**
- `new DynamicLoopMetrics()` — без аргументов, все accumulators = 0 / `[]`.
- `new DynamicLoopJournal($initialDiscussionHistory, $initialFacilitatorJournal)` — конструктор Journal: `$discussionHistory` ← 1-й аргумент, `$facilitatorJournal` ← 2-й аргумент. `facilitatorSummary` = `''` по default-свойству.
- Соответствует оригиналу: `$this->discussionHistory = $initialDiscussionHistory; $this->facilitatorJournal = $initialFacilitatorJournal;`.

**Итог B: PASS по всем 7 пунктам.**

---

## C. КОНВЕНЦИИ `entity.md` / `value-object.md` для owned components

### C.1 — `final` классы: **PASS**
Оба `final class`.

### C.2 — В правильном слое (`Domain\Entity`): **PASS**

### C.3 — `@internal` PHPDoc: **PASS**

### C.4 — Mutable (НЕ `readonly`): **PASS**

### C.5 — Нет repository/id/Model (in-memory, owned): **PASS**
Нет `#[ORM\Entity]`, нет `IdTrait`/`UuidTrait`, нет `Model` постфикса, нет репозитория.

### C.6 — Не нарушают `value-object.md`: **PASS**
Нет `Vo` суффикса, не в `Domain\ValueObject`, мутабельны → не подпадают под VO-конвенцию. ADR 3.2 явно обосновал: `+=`, append, накопление массивов несовместимы с `final readonly`.

### C.REMARK — Отступление от строгих правил `entity.md`
Конвенция `entity.md` формально требует: постфикс `Model`, идентификатор (int/uuid), `#[ORM\Entity]`. Эти правила ориентированы на **persistence-oriented domain** (Doctrine ORM). Новые классы — **in-memory owned components** без персистентности. Они следуют **локальному прецеденту**: сам `DynamicLoopExecution` (и `StaticChainExecution`) уже являются in-memory non-persistent entities без `Model`/id. Отступление документировано в дизайне Локи (ADR 3.2) и не введено Левша — оно консистентно с существующей кодовой базой. **Не блокирующее.**

**Итог C: PASS (с документированным REMARK об отступлении от persistence-ориентированных правил entity.md — обосновано локальным прецедентом).**

---

## D. ПОТЕНЦИАЛЬНЫЕ ДЕФЕКТЫ / РИСКИ

### D.1 — Deptrac: `Domain\Entity` → только Domain primitives/VO: **PASS**
- `DynamicLoopMetrics` → `DynamicRoundResultVo` (`DomainVo`). Ruleset: `Domain` → `DomainVo` разрешено.
- `DynamicLoopJournal` → только примитивы (`string`).
- Deptrac: 0 violations.

### D.2 — Mutable reference leakage — нет лишних setter'ов на aggregate: **PASS**
- Нет `setMetrics()` / `setJournal()` на `DynamicLoopExecution` — нельзя заменить компонент целиком.
- Только `getMetrics()` / `getJournal()` (read-only accessor, возвращающий reference).
- Соответствует mitigation ADR 3.1 / 3.6 (blind spot #2).

### D.3 — PHPMD metrics под пороги: **PASS**

| Класс | Поля | Counted public methods | Порог |
|---|---:|---:|---|
| `DynamicLoopExecution` | **11** | **9** | fields ≤ 12, methods ≤ 10 |
| `DynamicLoopMetrics` | **6** | **3** | fields ≤ 12, methods ≤ 10 |
| `DynamicLoopJournal` | **3** | **4** | fields ≤ 12, methods ≤ 10 |

Подсчёт counted methods verified: `__construct` + методы НЕ matching `(^(set|get|is|has|with))i`. Все три класса — с запасом.

### D.4 — Новые PHPMD violations на новых классах: **PASS**
PHPMD напрямую на 3 файла → exit=0 на каждом.

### D.5 — Callers: все write-вызовы переведены, read-side не сломан: **PASS**
- 5 callers обновлены (git diff сверен):
  - `RecordDynamicRoundService`: `recordRound` → `getMetrics()->recordRound` (×1).
  - `ExecuteDynamicTurnService`: `addRoleCost` → `getMetrics()->addRoleCost` (×2); `appendDiscussionHistory` → `getJournal()->...` (×2); `appendFacilitatorJournal` → `getJournal()->...` (×2); `appendFacilitatorSummary` → `getJournal()->...` (×1).
  - `CheckDynamicLoopBudgetService`: `appendFacilitatorJournal` → `getJournal()->...` (×1).
  - `FinalizeDynamicLoopService`: `appendFacilitatorJournal` → `getJournal()->...` (×1).
  - `RunDynamicLoopService`: `appendFacilitatorJournal` → `getJournal()->...` (×1).
- Read-side (`getTotalCost`, `getRoleCosts`, `getFacilitatorJournal`, `getTotals`, `getRoundResults`, `getDiscussionHistory`, `setFacilitatorJournal`) — не тронут, работают через delegates.
- Интерфейсы сервисов не менялись.

### D.6 — Psalm-типы корректны, `getTotals()` array shape типизирован: **PASS**
- `@return array{time: float, in: int, out: int, cost: float}` — в обоих `DynamicLoopMetrics::getTotals()` и delegate `DynamicLoopExecution::getTotals()`.
- Psalm: No errors found.

### D.7 — `toLoopResultVo`: `getTotals()` вызван ОДИН раз: **PASS**
```php
$totals = $this->metrics->getTotals();
```
Локальная переменная, используется 4 раза. Соответствует рекомендации Локи (раздел 4.2).

### D.8 — Тесты не tautological: **PASS**
Тесты проверяют реальное поведение: accumulate (значения+порядок), concat (точные строки), key order (`array_keys`), set replace, defaults. Не тавтология.

**Итог D: PASS по всем 8 пунктам.**

---

## E. КАЧЕСТВО ТЕСТОВ

### E.1 — Все ветвления покрыты: **PASS**
- `DynamicLoopMetricsTest` (5 тестов): defaults, single `recordRound`, multiple `recordRound` (порядок+сумма), `addRoleCost` (вкл. повтор роли), `getTotals` key order.
- `DynamicLoopJournalTest` (7 тестов): constructor defaults, constructor с initial values, 3× append concat, 2× set replace.

### E.2 — Edge cases: **PASS**
- Multiple `recordRound` — порядок + сумма: ✅ (`multipleRecordRoundsPreserveOrderAndSumTotals`).
- `addRoleCost` повтор роли: ✅ (`addRoleCostAccumulatesPerRole`: facilitator ×2 → 4.0).
- `getTotals` key order: ✅ (`getTotalsReturnsExactKeyOrderTimeInOutCost`).
- append concat: ✅ (3 отдельных теста).
- set replace: ✅ (2 отдельных теста).

### E.3 — Контрактный тест на `toLoopResultVo()` после `getMetrics()->recordRound()`: **REMARK**
Локи (ADR 3.5) и чек-лист Левша (пункт 7) рекомендовали добавить **unit-level** контрактный тест: записать rounds через `getMetrics()->recordRound()` + budget break через `setBudgetBreak()`, затем проверить `toLoopResultVo()` byte-to-byte по всем полям `DynamicLoopResultVo`.

**Этот тест НЕ добавлен как отдельный unit-тест.** `DynamicLoopExecutionMaxTimeTest` покрывает `toLoopResultVo()` только по полям `maxTimeExceeded` и `synthesis` — **не** по `roundResults`/`totalTime`/`totalInputTokens`/`totalOutputTokens`/`totalCost`.

**Однако** cross-component поток данных покрыт на **integration-уровне**: `tests/Integration/.../DynamicChainIntegrationTest.php` (строки 137–155) с точными assertion'ами:
- `assertCount(2, $result->roundResults)` + порядок (`roundResults[0].round/role/isFacilitator/outputText`).
- `assertSame(5.5, $result->totalTime)`, `assertSame(500, $result->totalInputTokens)`, `assertSame(250, $result->totalOutputTokens)`, `assertSame(0.03, $result->totalCost)`.

Таким образом, **риск регрессии cross-component data flow (metrics → toLoopResultVo) митигирован integration-тестом**. Отсутствие отдельного unit-теста — **REMARK, не блокирующий**: поведение верифицировано, просто не на изолированном unit-уровне, как рекомендовал Локи.

### E.4 — Нет хрупких тестов (float precision): **PASS**
- `multipleRecordRoundsPreserveOrderAndSumTotals`: cost = 1.0 + 0.5 + 0.25 = **1.75**; time = 2.5 + 1.5 + 0.5 = **4.5**. Оба значения точно представимы в IEEE 754 double → `assertSame` безопасен.
- Левша упомянул правку 1.4 → 1.75: изначальное 1.4 неточно в double, финальные 1.75/4.5 — точны. Исправление обосновано.

### E.PRE-EXISTING — PHPCS в `DynamicLoopExecutionMaxTimeTest.php`
PHPCS сообщает 1 ERROR: "Use statements should be sorted alphabetically" (`PHPUnit\Framework\Attributes\CoversClass` должен идти перед `TaskOrchestrator\...`). **Это нарушение предсуществующее** — use-statements идентичны в HEAD (git diff касается только строки 79: `appendFacilitatorJournal` → `getJournal()->appendFacilitatorJournal`). **Не введено этим PR.** Новые файлы (`DynamicLoopMetricsTest`, `DynamicLoopJournalTest`, `DynamicLoopMetrics`, `DynamicLoopJournal`, `DynamicLoopExecution`) — PHPCS-чистые.

**Итог E: PASS с одним REMARK (отсутствие unit-level контрактного теста на toLoopResultVo — покрыто integration-тестом).**

---

## F. CONSISTENCY с ChainStepParser/ChainStepFactory редизайном

**PASS.** Разные паттерны для разных случаев — обосновано:

- **`ChainStepFactory`** (из того же PR): `final readonly`, Vo-семантика — **immutable creation**, корректно как VO.
- **`DynamicLoopMetrics` / `DynamicLoopJournal`**: mutable `final class` — **mutable accumulation** (`+=`, append, накопление массивов), корректно как Entity.

`final readonly` VO для аккумуляторов был бы архитектурно ошибочен: каждая операция `recordRound()`/`append*()` требовала бы создания нового экземпляра, что противоречит модели in-memory aggregate, где services уже напрямую мутируют состояние. Выбор mutable обоснован (ADR 3.2), не нарушает `value-object.md` (классы не имеют `Vo` суффикса, не в `Domain\ValueObject`).

---

## СВОДКА ВЕРДИКТОВ

| Секция | Вердикт |
|---|---|
| A. Соответствие дизайну Локи (6 развилок) | **PASS** (все 6) |
| B. Zero behavioral change (7 пунктов) | **PASS** (все 7) |
| C. Конвенции entity.md / value-object.md | **PASS** (REMARK: отступление от persistence-правил entity.md обосновано локальным прецедентом) |
| D. Дефекты / риски (8 пунктов) | **PASS** (все 8) |
| E. Качество тестов | **PASS** (REMARK: отсутствие unit-level контрактного теста на toLoopResultVo — покрыто integration-тестом) |
| F. Consistency с ChainStepFactory | **PASS** |

---

## ФИНАЛЬНЫЙ ВЕРДИКТ: **APPROVE**

Реализация строго соответствует принятому дизайну Локи по всем 6 развилкам; zero behavioral change подтверждён byte-to-byte сверкой с оригиналом из HEAD; все инструментальные проверки (PHPUnit 1030/2872, Psalm 0 errors, Deptrac 0 violations, PHPMD 0 violations при пустом baseline, PHPCS чисто на новых файлах) воспроизведены независимо и зелёные. Два REMARK не блокирующие и не требуют правок в рамках данного PR.

### REMARK'и (информационно, не блокирующие)
1. **E.3:** Рекомендованный Локи (ADR 3.5) отдельный unit-level контрактный тест на `toLoopResultVo()` (с `getMetrics()->recordRound()` + `setBudgetBreak()`) не добавлен. Cross-component поток покрыт integration-тестом `DynamicChainIntegrationTest` (строки 137–155) с точными assertion'ами по `roundResults`/`totalTime`/`totalInputTokens`/`totalOutputTokens`/`totalCost`.
2. **C.REMARK:** `entity.md` формально требует `Model`-постфикс и идентификатор (ориентировано на Doctrine ORM). Новые классы — in-memory owned components без персистентности, следующие локальному прецеденту `DynamicLoopExecution`/`StaticChainExecution`. Отступление документировано в дизайне.
3. **E.PRE-EXISTING:** В `DynamicLoopExecutionMaxTimeTest.php` есть предсуществующее PHPCS-нарушение (сортировка use-statements) — не введено этим PR, не требует правок в рамках данной задачи.

### Дефекты и нарушения конвенций, требующие исправления: **отсутствуют.**
