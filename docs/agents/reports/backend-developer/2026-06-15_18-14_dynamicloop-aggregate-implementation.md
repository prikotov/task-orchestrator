# Реализация редизайна агрегата DynamicLoopExecution

**Роль:** Бэкендер Левша
**Дата:** 2026-06-15
**Объект:** `src/Module/DynamicLoop/Domain/Entity/DynamicLoopExecution.php`, модуль `DynamicLoop`, PR #261 `refactor/phpmd-baseline-elimination`
**Задача:** Точная реализация принятого дизайна Архитектора Локи (`docs/agents/reports/system-architect/2026-06-15_18-00_dynamicloop-aggregate-redesign.md`) — вынос метрик и журнала в owned mutable-компоненты для устранения последних 2 записей `phpmd.baseline.xml`.

---

## 1. Что создано

### `src/Module/DynamicLoop/Domain/Entity/DynamicLoopMetrics.php` (новый)

Owned mutable-компонент агрегата для накопления метрик и round results.

- **Поля (6):** `totalTime` (float=0.0), `totalInputTokens` (int=0), `totalOutputTokens` (int=0), `totalCost` (float=0.0), `roleCosts` (array<string,float>=[]), `roundResults` (list<DynamicRoundResultVo>=[])
- **Counted public methods (3):** `__construct`, `recordRound(DynamicRoundResultVo):void`, `addRoleCost(string,float):void`
- **Getters (ignored by PHPMD):** `getRoundResults()`, `getTotals()` (порядок ключей `time,in,out,cost`), `getRoleCosts()`, `getTotalCost()`
- `final class`, `@internal Owned by DynamicLoopExecution`
- `recordRound()` атомарен: append + accumulate выполняются в одном методе — инвариант сохранён

### `src/Module/DynamicLoop/Domain/Entity/DynamicLoopJournal.php` (новый)

Owned mutable-компонент агрегата для journal state.

- **Поля (3):** `discussionHistory` (string), `facilitatorJournal` (string), `facilitatorSummary` (string='')
- **Counted public methods (4):** `__construct(string='',string='')`, `appendFacilitatorJournal(string):void`, `appendDiscussionHistory(string):void`, `appendFacilitatorSummary(string):void`
- **Getters/Setters (ignored by PHPMD):** `getDiscussionHistory`/`setDiscussionHistory`, `getFacilitatorJournal`/`setFacilitatorJournal`, `getFacilitatorSummary`
- `final class`, `@internal Owned by DynamicLoopExecution`
- Append — чистая конкатенация без разделителей — инвариант сохранён

---

## 2. Что изменено

### `DynamicLoopExecution.php`

- **Удалено 9 полей:** `totalTime, totalInputTokens, totalOutputTokens, totalCost, roleCosts, roundResults, discussionHistory, facilitatorJournal, facilitatorSummary`
- **Добавлено 2 поля:** `private DynamicLoopMetrics $metrics`, `private DynamicLoopJournal $journal`
- **Итого полей: 11** (metrics, journal, step, round, participantRounds, synthesis, maxRoundsReached, interruptionReason, budgetBreak, budgetWarning80Logged, maxTimeExceeded) — ниже порога 12 ✓
- **Удалено 5 counted write-методов:** `recordRound, addRoleCost, appendFacilitatorJournal, appendDiscussionHistory, appendFacilitatorSummary`
- **Добавлено 2 ignored-аксессора:** `getMetrics(): DynamicLoopMetrics`, `getJournal(): DynamicLoopJournal` (matching ignorepattern `^get`)
- **Counted public methods: 9** (`__construct, advanceStep, advanceRound, advanceParticipantRounds, markMaxRoundsReached, markBudgetWarning80Logged, markMaxTimeExceeded, restoreFromRoundFiles, toLoopResultVo`) — ниже порога 10 ✓
- **Оставлены compatibility delegates (ignored ^get/^set):** `getRoundResults, getTotals, getRoleCosts, getTotalCost, getDiscussionHistory, setDiscussionHistory, getFacilitatorJournal, setFacilitatorJournal, getFacilitatorSummary` — минимизируют diff в read-side callers
- **`__construct`:** создаёт `new DynamicLoopMetrics()` и `new DynamicLoopJournal($initialDiscussionHistory, $initialFacilitatorJournal)`
- Удалён устаревший `@todo` про разделение (задача выполнена)

### Callers (5 файлов) — write-вызовы переведены на компоненты

| Файл | Изменение |
|---|---|
| `RecordDynamicRoundService.php` | `recordRound` → `getMetrics()->recordRound` |
| `ExecuteDynamicTurnService.php` | `addRoleCost` → `getMetrics()->addRoleCost` (×2); `appendDiscussionHistory` → `getJournal()->...` (×2); `appendFacilitatorJournal` → `getJournal()->...` (×2); `appendFacilitatorSummary` → `getJournal()->...` |
| `CheckDynamicLoopBudgetService.php` | `appendFacilitatorJournal` → `getJournal()->appendFacilitatorJournal` |
| `FinalizeDynamicLoopService.php` | `appendFacilitatorJournal` → `getJournal()->appendFacilitatorJournal` |
| `RunDynamicLoopService.php` | `appendFacilitatorJournal` (time reserve) → `getJournal()->appendFacilitatorJournal` |

Read-side (`getTotalCost`, `getRoleCosts`, `getFacilitatorJournal`, `getTotals`, `getRoundResults`, `getDiscussionHistory`, `setFacilitatorJournal` и др.) — **не тронут**, работают через delegates. Интерфейсы сервисов не менялись.

### `phpmd.baseline.xml`

Удалены 2 записи `DynamicLoopExecution` (`TooManyPublicMethods`, `TooManyFields`). Baseline пуст.

---

## 3. Тесты

### `tests/Unit/Domain/Entity/DynamicLoopMetricsTest.php` (новый, 5 тестов)

- defaults (нулевые totals, пустые списки/карты)
- `recordRound()` append + accumulate
- multiple `recordRound()` — порядок + сумма
- `addRoleCost()` — аккумулирование по роли (включая повтор)
- `getTotals()` — точный порядок ключей `time,in,out,cost`

### `tests/Unit/Domain/Entity/DynamicLoopJournalTest.php` (новый, 7 тестов)

- constructor defaults
- constructor с initial values
- append-конкатенация без разделителей (3 поля)
- set — полная замена (discussionHistory, facilitatorJournal)

### `tests/Unit/Domain/Entity/DynamicLoopExecutionMaxTimeTest.php` (обновлён)

- `appendFacilitatorJournal` → `getJournal()->appendFacilitatorJournal`

---

## 4. Результаты проверок

| Проверка | Результат |
|---|---|
| **PHPUnit** (весь suite) | ✅ OK — 1030 tests, 2872 assertions |
| **Psalm** | ✅ No errors found (0 errors в src/) |
| **PHPMD** (`make phpmd`, 3 прогона) | ✅ No violations (baseline пустой) |
| **PHPMD direct** (DynamicLoopExecution, Metrics, Journal без baseline) | ✅ 0 violations (exit=0 на каждом файле) |
| **Deptrac** | ✅ Violations: 0, Errors: 0, Warnings: 0 |
| **PHPCS** (все изменённые/новые src + test файлы) | ✅ 0 errors |

---

## 5. Подтверждение zero behavioral change

`toLoopResultVo()` собирает `DynamicLoopResultVo` byte-to-byte идентично оригиналу:

```php
$totals = $this->metrics->getTotals();

return new DynamicLoopResultVo(
    roundResults: $this->metrics->getRoundResults(),  // === бывший $this->roundResults (тот же список, тот же порядок)
    totalTime: $totals['time'],                        // === бывший $this->totalTime
    totalInputTokens: $totals['in'],                   // === бывший $this->totalInputTokens
    totalOutputTokens: $totals['out'],                 // === бывший $this->totalOutputTokens
    totalCost: $totals['cost'],                        // === бывший $this->totalCost
    synthesis: $this->synthesis,                       // без изменений
    maxRoundsReached: $this->maxRoundsReached,         // без изменений
    interruptionReason: $this->interruptionReason,     // без изменений
    budgetExceeded: $this->budgetBreak?->budgetExceeded ?? false,   // @phpstan-ignore сохранён
    budgetLimit: $this->budgetBreak?->budgetLimit ?? 0.0,           // @phpstan-ignore сохранён
    budgetExceededRole: $this->budgetBreak?->budgetExceededRole,    // без изменений
    maxTimeExceeded: $this->maxTimeExceeded,           // без изменений
);
```

Отображение полей: `getTotals()` возвращает `['time' => totalTime, 'in' => totalInputTokens, 'out' => totalOutputTokens, 'cost' => totalCost]` — порядок ключей и значения 1:1.

---

## 6. Метрики PHPMD (финальные)

| Класс | Поля | Counted public methods | Порог |
|---|---:|---:|---:|
| `DynamicLoopExecution` | **11** | **9** | fields ≤ 12, methods ≤ 10 |
| `DynamicLoopMetrics` | **6** | **3** | fields ≤ 12, methods ≤ 10 |
| `DynamicLoopJournal` | **3** | **4** | fields ≤ 12, methods ≤ 10 |

Все три класса — без нарушений при пустом baseline.

---

## 7. Соответствие дизайну Локи

- ✅ Owned mutable components в `Domain\Entity`, БЕЗ суффикса `Vo`, БЕЗ `Domain\ValueObject`
- ✅ `@internal Owned by DynamicLoopExecution` в PHPDoc обоих компонентов
- ✅ Top-level write-методы (`recordRound`, `addRoleCost`, `append*`) удалены из aggregate — не делегируют
- ✅ `getMetrics()/getJournal()` возвращают mutable reference (осознанное решение по ADR 3.1)
- ✅ Пороги `phpmd.xml` не менялись
- ✅ Deptrac: новые Domain\Entity → только primitives + `DynamicRoundResultVo` (Domain\ValueObject)
- ✅ `restoreFromRoundFiles()` не тронут — восстанавливает только counters
- ✅ Интерфейсы сервисов не менялись

---

## 8. Коммит

Git commit/push не выполнялся — Тимлид закоммитит.

---

## 9. Файлы

**Созданы:**
- `src/Module/DynamicLoop/Domain/Entity/DynamicLoopMetrics.php`
- `src/Module/DynamicLoop/Domain/Entity/DynamicLoopJournal.php`
- `tests/Unit/Domain/Entity/DynamicLoopMetricsTest.php`
- `tests/Unit/Domain/Entity/DynamicLoopJournalTest.php`

**Изменены:**
- `src/Module/DynamicLoop/Domain/Entity/DynamicLoopExecution.php`
- `src/Module/DynamicLoop/Domain/Service/Dynamic/RecordDynamicRoundService.php`
- `src/Module/DynamicLoop/Domain/Service/Dynamic/ExecuteDynamicTurnService.php`
- `src/Module/DynamicLoop/Domain/Service/Dynamic/CheckDynamicLoopBudgetService.php`
- `src/Module/DynamicLoop/Domain/Service/Dynamic/FinalizeDynamicLoopService.php`
- `src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopService.php`
- `tests/Unit/Domain/Entity/DynamicLoopExecutionMaxTimeTest.php`
- `phpmd.baseline.xml`
