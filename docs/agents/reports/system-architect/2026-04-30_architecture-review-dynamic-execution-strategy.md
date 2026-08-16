# Архитектурное ревью: DynamicExecutionStrategy

**Роль:** Архитектор Локи
**Дата:** 2026-04-30
**Объект:** `src/Module/ChainDefinition/Application/Service/Chain/DynamicExecutionStrategy.php` (293 строки)
**Задача:** Архитектурное ревью — placement, DDD-чистота, альтернативы

---

## Рефлексия

🧩 **Сложность запроса:** 6/10 — один файл, но анализ требует погружения в конвенции, сравнения с sibling-классами и понимания семантики слоёв.
🗂️ **Уровень контекста:** 8/10 — владелец проекта сформулировал конкретные вопросы и сомнения, есть ADR-006, есть конвенции.
🛡️ **Риск ошибки:** 3/10 — задача аналитическая, не требует изменений в коде.

**Вывод:** запрос не проблемный. Перехожу к анализу.

---

## 1. Placement: Application или Infrastructure?

### Аргументы ЗА Application

1. **Реализует интерфейс `ExecutionStrategyInterface`**, который находится в `Application/Service/Chain/` и оперирует Application-типами (`OrchestrateChainCommand`, `OrchestrateChainResultDto`). Интерфейс по конвенции Application Service: «интерфейс и реализация в Application».

2. **Не содержит бизнес-логику** — делегирует всё в Domain-сервисы (`RunDynamicLoopServiceInterface`, `BuildDynamicContextServiceInterface`, `ChainSessionLoggerInterface`). Сама стратегия — чистая оркестрация: разруба→вызов→маппинг→результат.

3. **DTO-маппинг VO→Dto** — это каноническая обязанность Application-слоя по конвенции: «преобразование DTO между слоями (Presentation → Application → Domain и обратно)». `ExecuteStaticChainService` делает то же самое — и лежит в Application.

4. **Event dispatch** — `DispatchRoundEventService` уже лежит в Application и выполняет аналогичную роль (диспатчит Application Event через PSR `EventDispatcherInterface`).

### Аргументы ПРОТИВ Application

1. **293 строки** — это значительно больше, чем «обычный» Application Service. StaticExecutionStrategy — 54 строки. ExecuteStaticChainService — 94 строки. DynamicExecutionStrategy втрое-впятеро объёмнее.

2. **Session lifecycle management** (`startSession`, `resumeSession`, `setBudget`, `logInvocation`, `completeSession`, `interruptSession`) — оркестрация с сохранением состояния между вызовами. Это не «вызови один Domain-сервис и верни результат», а полноценный workflow с 6+ вызовами session-инфраструктуры.

3. **`?EventDispatcherInterface $eventDispatcher = null`** — nullable-зависимость от `Psr\EventDispatcher`. По конвенции Application «запрещено зависеть от Infrastructure или Integration слоёв напрямую». PSR-интерфейсы — gray area, но DispatchRoundEventService решает эту же задачу через Domain-интерфейс `RoundCompletedNotifierInterface`. Здесь — прямой injection.

4. **`AuditLoggerFactoryInterface` → `resolveAuditLogger()`** — принятие решения о создании/пропуске audit logger. Это техническое разрешение инфраструктурной зависимости (фабрика логгера + файловый путь `audit.jsonl`).

### Вердикт Локи: **Application — правильно, но с нюансами**

Placement в Application **семантически корректен**. Стратегия — это Application Service, который оркестрирует Domain-сервисы и делает VO→DTO маппинг. Это полностью соответствует конвенции:

> «Application сервис — класс, реализующий оркестрацию бизнес-операций на уровне приложения. Координирует выполнение сценариев использования, объединяя несколько операций домена в единый процесс, но не содержит бизнес-логику.»

Проблема не в *слое*, а в **объёме ответственности одного класса**.

---

## 2. Семантическая нагрузка: скрытые нарушения

Последовательно прохожу по обязанностям DynamicExecutionStrategy и проверяю на DDD-чистоту:

### 2.1. Разруба параметров (fallback chain) — ✅ Чисто

```php
$facilitatorRole = $command->facilitator ?? $chain->getFacilitator() ?? 'team_lead';
$participants = $command->participants ?? $chain->getParticipants();
$timeout = $command->timeout ?? $chain->getTimeout() ?? self::DEFAULT_DYNAMIC_TIMEOUT;
```

Это Application-level resolution: «CLI override → chain config → default». Не бизнес-логика. Аналог валидации входных данных — нормально для Application.

### 2.2. Session lifecycle — ⚠️ Серая зона

Стратегия управляет полным lifecycle сессии: `startSession` → `logInvocation` → `setBudget` → `completeSession`/`interruptSession`. Сама по себе оркестрация — нормально для Application. Но **порядок вызовов критичен**: если `startSession` прошёл, а `setBudget` нет — сессия в неконсистентном состоянии.

Это не нарушение DDD-слоёв, но это **транзакционная семантика** без явного управления. Application-слой по конвенции «управляет границами транзакций» — здесь этого нет, но это не urgent-проблема (сессия in-memory, не БД).

### 2.3. `finalizeSession()` — расчёт reason — ⚠️ Серая зона

```php
$reason = $loopResult->budgetExceeded
    ? 'budget_exceeded'
    : ($loopResult->maxTimeExceeded
        ? 'max_time_exceeded'
        : ($synthesis !== null
            ? ($loopResult->maxRoundsReached ? 'max_rounds_reached' : 'facilitator_done')
            : ($loopResult->interruptionReason ?? 'no_synthesis')));
```

Это **бизнес-правило** (определение причины завершения сессии), но оно выражено через тернарный каскад в Application-сервисе. По конвенции «Application не содержит бизнес-логику — только вызывает». Это место стоит вынести в Domain — например, метод `DynamicLoopResultVo::getCompletionReason(): string`.

### 2.4. DTO-маппинг (VO → DTO) — ✅ Чисто

`toResultDto()` и `toRoundResultDtos()` — механический маппинг полей. Аналогично `ExecuteStaticChainService::toResultDto()`. Это каноническая обязанность Application. По конвенции Mapper — «final readonly class», но здесь маппинг — private-методы внутри сервиса, что допустимо для простого field-by-field mapping.

**Но:** если раунды начнут обрастать трансформациями (например, форматирование `outputText`, расчёт производных полей) — стоит выделить отдельный Mapper-класс.

### 2.5. Event dispatch — ⚠️ Нарушение паттерна

DynamicExecutionStrategy инжектит `?EventDispatcherInterface` напрямую. В том же каталоге `DispatchRoundEventService` решает аналогичную задачу через Domain-интерфейс `RoundCompletedNotifierInterface`. Это **асимметрия паттернов**: round-level события диспатчатся через Domain interface → Application adapter, а session-level событие — через прямой PSR injection.

По матрице зависимостей: `Application → Infrastructure: ❌`. PSR-интерфейсы — не Infrastructure, но это **нарушение принципа**, заложенного в `DispatchRoundEventService`: Domain определяет контракт уведомления, Application реализует.

### 2.6. Audit logger resolution — ✅ Приемлемо

`resolveAuditLogger()` — технический helper, использующий Domain-интерфейс `AuditLoggerFactoryInterface`. Решение «создать логгер или вернуть null» — Application-level concern. Нормально.

---

## 3. Нужно ли расщеплять?

### Текущая структура обязанностей

| Обязанность | Метод | Строки | Слой-семантика |
|---|---|---|---|
| Разруба параметров | `execute()`, `resume()` | ~40 | Application ✅ |
| Session lifecycle | `execute()`, `resume()` | ~30 | Application (оркестрация) ✅ |
| Запуск dynamic loop | `runDynamicLoop()` | ~8 | Application (делегация) ✅ |
| Finalize + reason | `finalizeSession()` | ~25 | **Domain concern** ⚠️ |
| DTO mapping | `toResultDto()`, `toRoundResultDtos()` | ~45 | Application ✅ |
| Event dispatch | `dispatchCompletedEvent()` | ~20 | Application (но нарушает паттерн) ⚠️ |
| Audit logger | `resolveAuditLogger()` | ~8 | Application ✅ |
| Resume state restore | `resume()` | ~40 | Application ✅ |

### Вердикт: расщеплять **не нужно**, но нужно почистить

Класс — это **один Application Service с одной ответственностью** (оркестрация dynamic-цепочки). Он не God-object: все методы private, все зависимости через DI, бизнес-логика делегирована. 293 строки — это upper bound для Application Service, но не за гранью.

Расщепление на отдельные классы (mapper, event dispatcher, finalizer) создаст 4 класса по 60-80 строк вместо одного по 293 — и каждый нужно будет связывать через DI. Это overengineering при текущем масштабе.

---

## 4. Что бы я предложил (конкретные правки)

### P1: Вынести расчёт completion reason в Domain

`DynamicLoopResultVo` — идеальное место:

```php
// Domain/ValueObject/DynamicLoopResultVo.php
public function getCompletionReason(): string
{
    if ($this->budgetExceeded) {
        return 'budget_exceeded';
    }
    if ($this->maxTimeExceeded) {
        return 'max_time_exceeded';
    }
    if ($this->synthesis !== null) {
        return $this->maxRoundsReached ? 'max_rounds_reached' : 'facilitator_done';
    }
    return $this->interruptionReason ?? 'no_synthesis';
}
```

Это бизнес-правило (как классифицировать завершение dynamic-цикла) — ему место в Domain.

### P2: Унифицировать event dispatch через Domain-интерфейс

Создать `SessionCompletedNotifierInterface` в `Domain/Service/Chain/Dynamic/`:

```php
interface SessionCompletedNotifierInterface
{
    public function notifySessionCompleted(
        string $status,
        ?string $completionReason,
        int $totalRounds,
        float $totalTime,
        int $totalInputTokens,
        int $totalOutputTokens,
        float $totalCost,
        ?string $synthesis,
        ?string $sessionDir,
        bool $budgetExceeded = false,
        float $budgetLimit = 0.0,
        ?string $budgetExceededRole = null,
    ): void;
}
```

Реализовать в Application (аналог `DispatchRoundEventService`):

```php
final readonly class DispatchSessionCompletedEventService implements SessionCompletedNotifierInterface
{
    public function __construct(private EventDispatcherInterface $eventDispatcher) {}
    // ...
}
```

Убрать `?EventDispatcherInterface` из конструктора `DynamicExecutionStrategy`. Это:
- убирает прямую зависимость от PSR;
- делает стратегию симметричной с Domain-паттерном round-events;
- упрощает тестирование (мок интерфейса вместо nullable PSR).

### P3 (опционально): Вынести VO→DTO маппинг в отдельный Mapper

Если ожидается рост трансформаций в `toRoundResultDtos()` — выделить `DynamicRoundResultMapper` в `Application/Mapper/`. Сейчас это premature, но держать в голове.

---

## 5. Альтернатива: Infrastructure placement

### Гипотеза: «перенести в Infrastructure»

DynamicExecutionStrategy управляет session persistence, audit log, event dispatch — это инфраструктурные concerns. Пусть Infrastructure реализует Domain interface.

### Опровержение:

1. **Интерфейс `ExecutionStrategyInterface`** оперирует Application DTO (`OrchestrateChainCommand`, `OrchestrateChainResultDto`). Если реализация в Infrastructure — Infrastructure зависит от Application DTO. По матрице зависимостей: `Infrastructure → Application: ❌`.

2. **ExecuteStaticChainService** — sibling, делает VO→DTO mapping, лежит в Application. Асимметрия placement'а двух стратегий одного интерфейса — это красный флаг.

3. **Infrastructure = технические детали** (файловый I/O, БД, HTTP). DynamicExecutionStrategy не реализует Domain-интерфейс — она *оркестрирует* Domain-сервисы и делает маппинг. Это Application по семантике.

**Вердикт:** Infrastructure — неправильный слой. Application — правильно.

---

## 6. Сводка находок

| # | Серьёзность | Находка | Рекомендация |
|---|---|---|---|
| 1 | 🟡 | Расчёт completion reason — бизнес-правило в Application | Вынести `getCompletionReason()` в `DynamicLoopResultVo` |
| 2 | 🟡 | Прямой injection `?EventDispatcherInterface` — нарушает паттерн round-events | Создать `SessionCompletedNotifierInterface` + Application-реализацию |
| 3 | 🟢 | 293 строк — upper bound, но не God-object | Мониторить; при росте >350 — выделить Mapper |
| 4 | ✅ | Placement в Application — семантически корректен | Без изменений |
| 5 | ✅ | VO→DTO mapping — канонический Application concern | Без изменений |
| 6 | ✅ | Session lifecycle orchestration — допустима в Application | Без изменений |
| 7 | ✅ | Dependency direction: Application → Domain only — чисто | Без изменений |

---

## 7. Ответы на конкретные вопросы

### Q1: Правильно ли DynamicExecutionStrategy лежит в Application-слое?
**Да.** Это Application Service по конвенции: оркестрирует Domain-сервисы, делает VO→DTO mapping, не содержит бизнес-логику. Альтернатива (Infrastructure) нарушает матрицу зависимостей.

### Q2: 292 строки с session management, DTO mapping, event dispatch — это Application или Infrastructure?
**Application.** Session management — оркестрация через Domain-интерфейс (`ChainSessionLoggerInterface`). DTO mapping — канонический Application concern. Event dispatch — Application concern, но нужно унифицировать паттерн.

### Q3: Есть ли скрытые нарушения DDD-слоёв?
**Да, два серых зоны:** (1) расчёт completion reason — бизнес-правило, не принадлежит Application; (2) прямой PSR injection вместо Domain-интерфейса — нарушает принцип, заложенный в DispatchRoundEventService. Оба — не критичные, но стоят исправления.

### Q4: Нужно ли расщеплять?
**Нет.** Один класс — одна ответственность (оркестрация dynamic-цепочки). Расщепление создаст overengineering. Но два точечных извлечения (reason → Domain, event → Domain interface) уменьшат объём до ~260 строк.

### Q5: Альтернативы?
Основная альтернатива (Infrastructure) отвергнута — нарушает матрицу зависимостей. Вторичная (расщепление на 3-4 класса) отвергнута — overengineering. **Лучшая альтернатива: точечные правки P1+P2 без смены placement'а.**

---

## Ссылки

1. [`docs/conventions/layers/application.md`](../../conventions/layers/application.md) — конвенция Application-слоя
2. [`docs/conventions/layers/layers.md`](../../conventions/layers/layers.md) — матрица зависимостей
3. [`docs/conventions/core-patterns/service.md`](../../conventions/core-patterns/service.md) — конвенция Application Service
4. [`docs/adr/006-execution-strategy-composition.md`](../../adr/006-execution-strategy-composition.md) — ADR-006: Strategy Composition
5. [`docs/guide/architecture.md`](../../guide/architecture.md) — архитектура модуля Orchestrator
