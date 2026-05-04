# Архитектурное ревью: DynamicExecutionStrategy

**Роль:** Архитектор Гэндальф
**Дата:** 2026-04-30
**Объект:** `src/Module/ChainDefinition/Application/Service/Chain/DynamicExecutionStrategy.php` (293 строки)
**Задача:** Архитектурный аудит: правильность слоя, DDD-соответствие, необходимость расщепления

---

## Классификация запроса

🧩 сложность запроса: 6 из 10 — требуется анализ конвенций, сравнение с аналогами в кодовой базе, проверка матрицы зависимостей.

🗂️ уровень контекста: 9 из 10 — предоставлены конкретные файлы, полная документация по слоям, ADR-006.

🛡️️ риск ошибки: 2 из 10 — анализ без изменений в коде.

---

## Вердикт: **Оставить в Application, точечно доработать**

DynamicExecutionStrategy корректно расположен в Application-слое. Нарушений DDD-зависимостей нет. 293 строки обоснованы inherent-сложностью dynamic-цикла (session + resume). Расщепление ухудшит читаемость. Есть одна неконсистентность — прямой `EventDispatcherInterface` вместо Domain-интерфейса.

---

## Детальный анализ по каждому вопросу

### 1. Правильно ли что DynamicExecutionStrategy лежит в Application-слое?

**Да, правильно.**

Согласно конвенциям (`docs/conventions/layers/application.md`), Application-слой выполняет:

| Назначение Application | Что делает DynamicExecutionStrategy |
|---|---|
| **Оркестрация бизнес-операций** — координирует несколько операций домена в единый процесс | `execute()` / `resume()` координируют: session start → context build → loop run → finalize → event dispatch |
| **Преобразование данных** — маппинг DTO между слоями | `toResultDto()` / `toRoundResultDtos()`: `DynamicLoopResultVo` → `OrchestrateChainResultDto` |
| **Координирует через абстракции** — не содержит бизнес-логику | Все бизнес-решения делегированы в Domain-сервисы через Domain-интерфейсы |

Класс является Strategy-реализацией, введённой по ADR-006. Его роль — Application-level оркестратор behavioural path «dynamic chain». Это абсолютно тот же паттерн, что и `ExecuteStaticChainService` (Application) и `DispatchRoundEventService` (Application).

**Аналог в кодовой базе:** `ExecuteStaticChainService` (97 строк, `Application/Service/Chain/`) делает то же самое для static-цепочки:
- делегирует в Domain-сервис `RunStaticChainService`
- маппит `StaticChainResultVo` → `OrchestrateChainResultDto`
- тот же слой, тот же паттерн

### 2. 293 строки — это Application-ответственность или Infrastructure?

**Application-ответственность.** Разберём по обязанностям:

| Обязанность | Строк | Application или Infrastructure | Обоснование |
|---|---|---|---|
| `execute()` — оркестрация | ~80 | ✅ Application | Последовательность Domain-вызовов — классический CommandHandler workflow |
| `resume()` — оркестрация | ~60 | ✅ Application | Симметрично execute(), ADR-006 явно предусматривает resume() |
| `supports()` | ~5 | ✅ Application | Routing-логика стратегии |
| `runDynamicLoop()` | ~15 | ✅ Application | Thin wrapper для Domain-сервиса, добавляет defaults |
| `finalizeSession()` | ~30 | ✅ Application | Определяет причину завершения (decision logic), делегирует в Domain-сервисы |
| `resolveAuditLogger()` | ~10 | ✅ Application | Решает *когда* создавать logger — это application-level routing, не infrastructure |
| `dispatchCompletedEvent()` | ~20 | ⚠️ Application (с нюансом) | См. п. 4 ниже — неконсистентность с `RoundCompletedNotifierInterface` |
| `toResultDto()` / `toRoundResultDtos()` | ~40 | ✅ Application | VO→Dto mapping — прямая обязанность Application по конвенциям |
| Импорты + константы + заголовок | ~33 | — | — |

**Infrastructure-ответственности нет.** Все реализации (JSONL-логгер, YAML-загрузчик, session writer) — в Infrastructure-слое. DynamicExecutionStrategy работает *только* через Domain-интерфейсы.

### 3. Есть ли нарушение DDD-слоёв?

**Нет нарушений.** Проверка по матрице зависимостей (`docs/conventions/layers/layers.md`):

| Зависимость | Слой | Разрешено? |
|---|---|---|
| `ChainTypeEnum` | Domain Enum | ✅ Application → Domain |
| `ChainDefinitionVo`, `DynamicLoopResultVo`, `DynamicRoundResultVo`, `DynamicChainContextVo` | Domain VO | ✅ Application → Domain |
| `ChainSessionLoggerInterface` | Domain Service | ✅ Application → Domain |
| `BuildDynamicContextServiceInterface` | Domain Service | ✅ Application → Domain |
| `RunDynamicLoopServiceInterface` | Domain Service | ✅ Application → Domain |
| `AuditLoggerFactoryInterface`, `AuditLoggerInterface` | Domain Service | ✅ Application → Domain |
| `EventDispatcherInterface` | PSR-14 (std) | ✅ PSR — допустимо |
| `OrchestrateChainCommand`, `OrchestrateChainResultDto`, `DynamicRoundResultDto` | Application DTO | ✅ Application → Application |
| `OrchestrateSessionCompletedEvent` | Application Event | ✅ Application → Application |

**Application → Domain** — единственное направление зависимостей. Infrastructure, Integration, Presentation — не используются напрямую.

**VO→Dto mapping (Domain → Application)** — допустимо и прямо предписано конвенциями: «Мапперы расположены в `Application\Mapper\*`» и «Преобразование данных: маппинг DTO между слоями».

### 4. Нужно ли расщеплять?

**Нет, расщепление ухудшит код.** Обоснование:

**Аргументы за текущий вид:**

1. **Единый behavioural path.** Dynamic chain execution — один scenario (use case). Разделение session start / loop run / finalize на отдельные классы создаст фрагментацию без gain: каждый «кусочек» бессмысленен без остальных.

2. **Сравнение с аналогами.** `ExecuteStaticChainService` (97 строк) делает то же самое для static. Разница в объёме объяснима: dynamic chain имеет resume, session lifecycle и facilitator loop — inherent complexity.

3. **Тестируемость.** Один класс с 5 моками тестируется как один unit. Разделение на 3 класса = 3 test suite + интеграционный тест для их взаимодействия.

4. **Размер оправдан.** Из 293 строк: ~33 — импорты/заголовок, ~40 — VO→Dto mapping (механический), ~15 — supports() + wrapper. Содержательная логика — ~200 строк. Это нормально для Application-оркестратора с двумя entry-points (execute + resume).

**Что точно НЕ нужно делать:**

- ❌ Выносить в Infrastructure — класс не содержит технических реализаций, только оркестрацию через Domain-интерфейсы.
- ❌ Выносить в Domain — класс зависит от Application DTO и PSR EventDispatcher, что нарушит «Domain не зависит ни от кого».
- ❌ Выносить mapping в отдельный mapper-класс — при текущем размере (40 строк) это overengineering. Если mapping вырастет — тогда да.

### 5. Альтернативы: анализ

#### Альтернатива A: Infrastructure-реализация

**Отвергнута.** DynamicExecutionStrategy не реализует Domain-интерфейс — он реализует `ExecutionStrategyInterface` (Application). Его зависимость от Application DTO (`OrchestrateChainResultDto`) и Application Event (`OrchestrateSessionCompletedEvent`) делает Infrastructure-размещение невозможным: Infrastructure → Application запрещено матрицей.

```
Infrastructure → Application  ❌ ЗАПРЕЩЕНО
```

#### Альтернатива B: Domain-service

**Отвергнута.** Domain не зависит от Application DTO и PSR EventDispatcher. Размещение в Domain потребует:
- убрать VO→Dto mapping (кто будет делать?)
- убрать EventDispatcher (кто будет диспатчить?)
- передать эти обязанности наверх — в CommandHandler, который снова станет God-object

Это ровно то, от чего избавил ADR-006.

#### Альтернатива C: Расщепление на DynamicSessionOrchestrator + DynamicResultMapper

**Отвергнута.** Искусственное разделение: mapper (40 строк) не имеет самостоятельной ценности, session orchestrator без mapper'а не сможет вернуть результат. Сложность понимания потока данных возрастёт.

---

## Найденные проблемы

### 🟡 Проблема 1: Неконсистентность event dispatch (средняя серьёзность)

**Суть:** Для round-completion событий существует чистый паттерн:

```
Domain: RoundCompletedNotifierInterface (interface)
Application: DispatchRoundEventService (implementation, wraps PSR EventDispatcher)
```

Для session-completion событий этот паттерн **нарушен** — `DynamicExecutionStrategy` напрямую инжектит `Psr\EventDispatcher\EventDispatcherInterface` и диспатчит `OrchestrateSessionCompletedEvent`.

**Почему это проблема:**
- Нарушает установленный в проекте паттерн (Domain interface → Application implementation)
- Strategy знает об Application Event (`OrchestrateSessionCompletedEvent`) — это нормально для Application, но делает класс менее тестируемым (нужен mock PSR dispatcher вместо простого notifier mock)
- Nullable-зависимость `?EventDispatcherInterface` — workaround, указывающий на отсутствие Domain-контракта

**Рекомендация:** Создать `SessionCompletedNotifierInterface` в `Domain/Service/Chain/Shared/` и `DispatchSessionCompletedEventService` в `Application/Service/Chain/` — симметрично `RoundCompletedNotifierInterface` / `DispatchRoundEventService`.

### 🟢 Замечание 2: FQCN в type hint (косметическое)

В методе `runDynamicLoop()` используется full-qualified class name вместо import:

```php
    \TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\DynamicChainContextVo $context,
```

**Рекомендация:** Добавить `use`-импорт в заголовок файла.

### 🟢 Замечание 3: Nullable EventDispatcher (связано с проблемой 1)

```php
private ?EventDispatcherInterface $eventDispatcher = null,
```

Nullable-инъекция — это workaround для необязательности событий. При введении `SessionCompletedNotifierInterface` (с always-инжекцией) эта проблема уйдёт автоматически.

---

## Итоговая оценка

| Критерий | Оценка | Комментарий |
|---|---|---|
| Правильность слоя | ✅ Корректно | Application — оркестратор use case path |
| DDD-зависимости | ✅ Без нарушений | Application → Domain только |
| Размер | ✅ Приемлемо | 293 строк, обосновано inherent complexity |
| Единая ответственность | ✅ соблюдена | «Dynamic chain execution lifecycle» |
| Консистентность паттернов | ⚠️ Одна неконсистентность | Прямой EventDispatcher вместо Domain interface |
| Тестируемость | ✅ Хорошая | 5 Domain-моков, чистый unit |

## Рекомендации (приоритет)

1. **Создать `SessionCompletedNotifierInterface`** в Domain и обёртку в Application — устраняет неконсистентность с `RoundCompletedNotifierInterface`. Priority: medium. Можно сделать отдельной задачей.
2. **Заменить FQCN на import** — косметика, можно при следующем touch файла.
3. **Не расщеплять, не перемещать.** Архитектура корректна.
