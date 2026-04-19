# Архитектурное ревью task-orchestrator

**Роль:** Архитектор (Гэндальф)
**Дата:** 2026-04-19
**Объект:** `src/` (131 PHP-файлов), `config/services.yaml`, `docs/guide/architecture.md`
**Задача:** Полное архитектурное ревью по запросу Тимлида

---

## 1. Соблюдение границ слоёв

### ✅ Domain → «никто» (в целом)

**AgentRunner Domain** — чисто. Только PHP stdlib (`InvalidArgumentException`, `sprintf`, `Override`).

**Orchestrator Domain** — чисто по зависимостям от других слоёв. Нет ссылок на Application, Infrastructure, Integration-слой, Symfony или другие модули. Domain ссылается только на собственные интерфейсы в `Domain/Service/Integration/RunAgentServiceInterface` — это корректно, это внутренний интерфейс Domain.

**Подтверждённые проверки:**
- `grep 'Application\\' src/Module/*/Domain/` → 0 совпадений
- `grep 'Infrastructure\\' src/Module/*/Domain/` → 0 совпадений
- `grep 'Symfony\\' src/Module/*/Domain/` → 0 совпадений

### ✅ Application → Domain (корректно)

Все Application-сервисы и handler'ы используют только Domain-интерфейсы, VO, Enum и DTO. Application DTO (`OrchestrateChainCommand`, `RunAgentResultDto`, etc.) содержат только примитивы — не ссылаются на Domain типы. Подтверждено полной проверкой всех DTO/Command/Query файлов.

### ✅ Infrastructure → Domain (корректно)

Infrastructure реализует Domain-интерфейсы и использует Symfony/YAML/Process — всё в рамках своих полномочий. Нет ссылок на Application.

### ✅ Integration → Application AgentRunner + Domain Orchestrator (корректно)

`RunAgentService` и `AgentDtoMapper` корректно работают как ACL:
- Реализуют Domain-интерфейс `RunAgentServiceInterface`
- Делегируют в `AgentRunner\Application\UseCase\Command\RunAgent\RunAgentCommandHandler`
- Маппят VO между модулями

---

## 2. Domain: недетерминированные вызовы

### 🔴 AgentRunner Domain: `time()` в CircuitBreakerStateVo

**Файл:** `src/Module/AgentRunner/Domain/ValueObject/CircuitBreakerStateVo.php`
**Строки:** 79, 215

```php
$now = time();                    // line 79
return (time() - $this->lastFailureAt) >= ...  // line 215
```

`time()` — недетерминированная инфраструктурная функция. VO невозможно протестировать с предсказуемым временем без обёрток. Согласно документации: *«Только PHP std + Psr\Log\LoggerInterface»* — технически `time()` это PHP std, но это **side-effect**, нарушающий чистоту Domain.

**Рекомендация:** Ввести `ClockInterface` (Domain) с реализацией в Infrastructure.

### 🔴 Orchestrator Domain: `microtime(true)` и `date()` в 4 файлах

| Файл | Строка | Вызов |
|---|---|---|
| `Dynamic/RunDynamicLoopService.php` | 66, 419 | `microtime(true)` × 2 |
| `Dynamic/FormatDynamicJournalService.php` | 21-22, 61-62, 82-83 | `date()` × 6 |
| `Static/ExecuteStaticStepService.php` | 75, 77, 191, 200 | `microtime(true)` × 4 |
| `Static/RunStaticChainService.php` | 62, 313 | `microtime(true)` × 2 |

`microtime(true)` используется для замера длительностей внутри Domain-сервисов. `date()` формирует строки журнала с таймстемпами. Обе функции — non-deterministic side-effects.

**Рекомендация:**
- Для `microtime()`: передавать `$startTime` как параметр из Infrastructure/Application слоя или использовать `ClockInterface`.
- Для `date()`: передавать форматированную строку времени извне или через `ClockInterface`.

---

## 3. Integration-слой как ACL

### ✅ Основной путь RunAgent — корректен

```
Orchestrator Domain (RunAgentServiceInterface)
    ← Integration (RunAgentService + AgentDtoMapper)
        → AgentRunner Application (RunAgentCommandHandler)
```

Маппинг VO → DTO выполняется в `AgentDtoMapper` — ACL работает правильно.

### 🔴 GetRunners обходит Integration-слой

**Файл:** `src/Module/Orchestrator/Application/UseCase/Query/GetRunners/GetRunnersQueryHandler.php`
**Строки:** 7-8, 25-35

```php
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQuery as AgentRunnerGetRunnersQuery;
use TaskOrchestrator\Common\Module\AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQueryHandler as AgentRunnerGetRunnersQueryHandler;
```

Handler **напрямую** инжектит `AgentRunnerGetRunnersQueryHandler` и вызывает его, минуя Integration-слой. Это **прямое нарушение** правила: *«Application Orchestrator не зависит от Application AgentRunner (должно идти через Integration)»*.

**Рекомендация:** Создать `GetRunnersServiceInterface` (Orchestrator Domain), реализацию в `Integration\Service\AgentRunner\GetRunnersService.php` (ACL), и делегировать через неё.

---

## 4. Application Orchestrator → Application AgentRunner

### 🔴 Прямая зависимость (подтверждение п. 3)

Единственный нарушитель — `GetRunnersQueryHandler` (см. выше). Все остальные Application-сервисы Orchestrator идут через Domain-интерфейсы.

---

## 5. Размер и сложность Domain-сервисов

### 🟡 Тенденция к god-object

| Сервис | LOC | Зависимости | Методы | Комментарий |
|---|---|---|---|---|
| `RunDynamicLoopService` | 433 | 7 | ~15 | Оркестратор dynamic-цикла: координирует facilitator/participant turns, бюджет, аудит. Высокая цикломатическая сложность. |
| `RunStaticChainService` | 414 | 4 | ~12 | Аналогично для static-chain: шаги, итерации, бюджет. |
| `ExecuteDynamicTurnService` | 286 | 4 | 3 (+1 private) | Каждый метод 60-80 LOC — много адаптационной логики. |
| `OrchestrateChainCommandHandler` | 317 | 10 | ~10 | 10 зависимостей — признак превышения координационной ответственности. |
| `DynamicLoopExecution` | 295 | — | 25+ | Уже помечен `@todo`: *«Рассмотреть разделение на DynamicMetrics + DynamicJournal entity»*. |
| `StaticChainExecution` | 287 | — | 20+ | Бюджетная логика + навигация + итерации в одной сущности. |

**Рекомендация (пошагово):**
1. **P1:** Вынести timing-метрики из Domain-сервисов → `StopwatchInterface` (Domain) + реализация (Infrastructure).
2. **P2:** Разделить `DynamicLoopExecution` на `DynamicMetrics` + `DynamicJournal` (уже запланировано в `@todo`).
3. **P3:** Вынести бюджетную логику `StaticChainExecution::checkBudget*()` в отдельный Domain-сервис.
4. **P4:** Снизить число зависимостей `OrchestrateChainCommandHandler` через фасад или пул сервисов.

### 🟡 `RunDynamicLoopAgentServiceInterface` — 14+ параметров

**Файл:** `src/Module/Orchestrator/Domain/Service/Chain/Dynamic/RunDynamicLoopAgentServiceInterface.php`

Метод `runFacilitator()` принимает 14 скалярных параметров. Методы `runParticipant()` и `runFacilitatorFinalize()` — по 13-14. Это снижает читаемость и поддерживаемость.

**Рекомендация:** Ввести request VO (например, `FacilitatorRunRequestVo`, `ParticipantRunRequestVo`) для группировки параметров.

---

## 6. Дублирование DTO/VO между модулями

### 🟢 Обоснованное дублирование

| Orchestrator | AgentRunner | Статус |
|---|---|---|
| `Application/.../RunAgent/RunAgentCommand` (8 полей) | `Application/.../RunAgent/RunAgentCommand` (18 полей) | ✅ Обосновано: разные контексты, разные поля |
| `Application/.../RunAgent/RunAgentResultDto` (11 полей) | `Application/.../RunAgent/RunAgentResultDto` (11 полей) | ✅ Обосновано: ACL изоляция |
| `Application/.../GetRunners/RunnerDto` (2 поля) | `Application/.../GetRunners/RunnerDto` (2 поля) | 🟡 Идентичны — можно вынести в shared, но допустимо |
| `Application/.../GetRunners/GetRunnersQuery` | `Application/.../GetRunners/GetRunnersQuery` | 🟡 Идентичны |

Дублирование `RunnerDto` и `GetRunnersQuery` между модулями — концептуально корректно (каждый модуль владеет своими типами по ACL), но **фактически идентичные** структуры. Если Orchestrator GetRunners пойдёт через Integration (как должно быть), дублирование сохранится.

**Вердикт:** Допустимо по принципу ACL. Не требует немедленных действий.

---

## 7. DI-конфигурация (config/services.yaml)

### ✅ Корректные биндинги

- Все 20 Domain-интерфейсов Orchestrator → явные alias на реализации
- Все 3 Domain-интерфейса AgentRunner → alias
- `AgentRunnerRegistryService` — `!tagged_iterator agent.runner` ✅
- `ChainSessionLogger` → 3 интерфейса (Logger, Reader, Writer) ✅
- `AuditLoggerInterface` → factory pattern ✅
- `ReportResultFactory` → mapper map ✅
- Bundle-параметры корректно пробрасываются в Infrastructure-сервисы ✅

### 🟡 `RoundCompletedNotifierInterface` → Application-реализация

```yaml
RoundCompletedNotifierInterface:
    alias: DispatchRoundEventService  # Application Service
```

По конвенции Infrastructure реализует Domain-интерфейсы. Здесь Application-сервис реализует Domain-интерфейс. Это работает, но нарушает паттерн. Обоснование: Domain не должен знать о PSR EventDispatcher, а реализация — чисто Application-concern.

**Вердикт:** Допустимое исключение. Domain-интерфейс `RoundCompletedNotifierInterface` спроектирован именно для этого — быть шлюзом между Domain и Application event system.

### ✅ Auto-discovery исключения

Корректно исключены: Dto, Entity, Enum, Exception, ValueObject, DependencyInjection, Symfony Bundle, RetryingAgentRunner, CircuitBreakerAgentRunner (не регистрируются как сервисы — это декораторы/обёртки).

---

## 8. Соответствие кода документации (docs/guide/architecture.md)

### ✅ Двухмодульная структура — совпадает

Каталоги `AgentRunner` и `Orchestrator` с подкаталогами Domain/Application/Infrastructure(+/Integration) полностью соответствуют документации.

### ✅ VO-маппинг на границе модулей — реализован

`AgentDtoMapper` маппит `ChainRunRequestVo` → `RunAgentCommand` и `RunAgentResultDto` → `ChainRunResultVo`, как задокументировано.

### 🟡 Расхождение: Orchestrator/Application/UseCase/Query/GetRunners

Документация не описывает `GetRunners` в Orchestrator Application (только в AgentRunner Application). Наличие этого handler'а в Orchestrator не задокументировано и нарушает Integration-паттерн.

### ✅ Dependency matrix — выполняется (кроме GetRunners)

Матрица зависимостей из `layers.md` выполняется для всех файлов кроме `GetRunnersQueryHandler`.

---

## Сводка по категориям

| Категория | Кол-во |
|---|---|
| 🔴 **Критические нарушения** | 3 |
| 🟡 **Предупреждения (god-object, DI-паттерн, параметры)** | 4 |
| 🟢 **Допустимые решения** | 2 |
| ✅ **Полное соответствие** | 8 |

---

## Приоритезированный план действий

### P0 — Критические (архитектурные нарушения)

1. **Рефакторинг `GetRunnersQueryHandler` через Integration-слой**
   - Создать `GetRunnersServiceInterface` в `Orchestrator/Domain/Service/Integration/`
   - Создать ACL-реализацию в `Orchestrator/Integration/Service/AgentRunner/GetRunnersService.php`
   - Переписать handler на использование интерфейса
   - Обновить `services.yaml`

2. **Избавить Domain от `microtime(true)` и `time()`**
   - Ввести `ClockInterface` (Orchestrator Domain) с методом `now(): float` и `formatDate(string $format): string`
   - Реализовать `SystemClock` (Infrastructure)
   - Заменить `microtime(true)` / `date()` во всех 4 файлах Orchestrator Domain
   - Аналогично для `time()` в `CircuitBreakerStateVo` (AgentRunner Domain)

### P1 — Важные (качество кода)

3. **Разделить `DynamicLoopExecution`** (уже отмечено в `@todo`) — на `DynamicMetrics` + `DynamicJournal` + `DynamicState`.

4. **Ввести request VO для `RunDynamicLoopAgentServiceInterface`** — `FacilitatorRunRequestVo`, `ParticipantRunRequestVo` с группировкой 14 параметров.

### P2 — Улучшения

5. **Вынести бюджетную логику из `StaticChainExecution`** в отдельный Domain-сервис `StaticBudgetService`.

6. **Снизить связность `OrchestrateChainCommandHandler`** — 10 зависимостей. Рассмотреть фасад для session-операций.

7. **Обновить `docs/guide/architecture.md`** — добавить `GetRunners` в Orchestrator Application после рефакторинга.

---

*«Архитектура в целом здорова — двухмодульная структура, ACL-маппинг, DI-изоляция работают как задумано. Но `time()`/`microtime()` в Domain — это трещина, которая расширяется с каждым тестом. А `GetRunnersQueryHandler`, обходящий Integration — это прямой путь к сцеплению модулей. Устранить P0 — и система будет достойна звания Production-ready.»*
