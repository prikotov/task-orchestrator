# Архитектурное ревью: VO-рефакторинг RunDynamicLoopAgentServiceInterface (RV-A)

**Роль:** Архитектор Локи (system_architect_loki)
**Дата:** 2026-11-23
**Объект:** незакоммиченные изменения в рабочем дереве ветки `task/runfacilitator-vo` (diff против HEAD):
`src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopAgentServiceInterface.php`,
`src/Module/DynamicLoop/Domain/Service/Dynamic/ExecuteDynamicTurnService.php`,
`src/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentService.php`,
новые VO: `FacilitatorRunContextVo`, `ParticipantRunContextVo`, `FacilitatorFinalizeRunContextVo` (+3 unit-теста),
обвязка `ExecuteDynamicTurnServiceTest`, комментарии задачи.
**Задача:** `todo/TASK-techdebt-runfacilitator-vo.todo.md` (type: refactor, RV-A по RACI)

**Условия запуска:** предыдущий запуск прерван по таймауту. Длинные инструменты не перезапускались
(`make check` зелёный по отчёту исполнителя: PHPUnit 1773/5207 OK, Psalm OK, Deptrac 0/0).
Проведён только архитектурный анализ кода + точечный прогон 4 затронутых тестовых файлов (26 тестов / 100 assertions — OK).

---

## 1. Проверки и результаты

### 1.1. DDD-слои ✅
- Три новых VO размещены в `src/Module/DynamicLoop/Domain/ValueObject/` — соответствует
  `docs/conventions/core-patterns/value-object.md` (расположение Domain-VO).
- Зависимости VO: примитивы, `InvalidArgumentException`, `DynamicLoopRetryPolicyVo` (тот же слой) —
  в рамках разрешённого набора; Infrastructure/DI/глобальное состояние отсутствуют.
- `Integration/Service/ChainExecution/RunDynamicLoopAgentService` потребляет Domain-VO как часть
  контракта Domain-интерфейса — это предсуществующий паттерн модуля (так же потреблялись
  `DynamicLoopTurnResultVo`, `DynamicLoopRunRequestVo`), Deptrac подтверждает (0 ошибок / 0 предупреждений).

### 1.2. Границы модуля DynamicLoop ✅
- Все изменения ограничены модулем `DynamicLoop` и тестами; другие модули не затронуты.
- `RunDynamicLoopAgentServiceInterface` реализуется единственным классом (Integration) —
  контрактное изменение замкнуто внутри модуля (`grep` по `src/`, `apps/`, `config/`).
- Единственная точка вызова трёх методов — `ExecuteDynamicTurnService` (3 вызова, все обновлены).
  Транзитные контракты (`ExecuteDynamicTurnServiceInterface`, `RunDynamicLoopService`) не изменялись —
  верно, их сигнатуры уже короткие.

### 1.3. Дизайн VO ✅
- `final readonly`, приватные строго типизированные свойства, чистые геттеры, без побочных эффектов —
  по `docs/conventions/core-patterns/value-object.md`.
- Постфикс `Vo` в именах — по конвенции; именование согласовано с соседними VO модуля.
- Инвариант `timeout > 0` валидируется в конструкторе через `InvalidArgumentException` —
  практика, предписанная конвенцией VO; покрыт тестами (timeout = 0 и -1 → исключение) во всех трёх VO.
- Дефолты конструкторов воспроизводят дефолты старой сигнатуры (`command = []`, `retryPolicy = null`,
  `taskAddendum = null`, `hasPreviousResponses = true`, `challenge = null`, `promptFile = null`) —
  покрыто тестами `optionalFieldsHaveBackwardCompatibleDefaults`.

### 1.4. Контракт RunDynamicLoopAgentServiceInterface ✅
- Сигнатуры сокращены до 5 позиционных параметров (4 идентификации + VO) — критерий Must Have ≤ 5 выполнен.
- PHPDoc интерфейса обновлён: группы полей VO и семантика параметров описаны, `@techdebt` снят.
- Возвраты (`array{DynamicLoopTurnResultVo, FacilitatorResponseVo}`, `DynamicLoopTurnResultVo`) не менялись.

### 1.5. Риск resume-контракта и порядка полей VO ✅ (закрыт)
Ключевой риск задачи (§7: «ошибка в порядке полей VO изменит поведение resume») проверен построчно:
- Порядок параметров конструкторов всех трёх VO **1:1 совпадает** со старым позиционным хвостом
  (сверено с diff по каждому из трёх методов: промпты → workingDir → состояние → timeout → command → retryPolicy → taskAddendum).
- Все конструирования VO и все вызовы методов используют **именованные аргументы** — транспозиция позиционно невозможна.
- Тестовый callback заменён с `assertCount(15)` на типизированную сигнатуру — перепутанные позиции поймает компилятор/psalm.
- Семантика `taskAddendum` (null → блок не рендерится) перенесена без изменений; поведенческая проверка
  `runFacilitatorTurnForwardsTaskAddendumToAgentRunner` (`assertSame` на addendum) не изменена — правка только обвязки.

### 1.6. Сохранение поведения ✅
- Асимметрия retryPolicy сохранена точно: facilitator-хелпер передаёт методный `$retryPolicy`,
  participant/finalize — `$chain->getDefaultRetryPolicy()` (сверено по diff).
- `hasPreviousResponses: $prevResponsePaths !== []`, `command: $partRoleConfig?->getCommand() ?? []`,
  `timeout: $partRoleConfig?->getTimeout() ?? $context->timeout` — маппинг в Integration 1:1
  (каждый геттер VO ↔ прежний параметр с тем же именем, проверено по всем трём методам реализации).

---

## 2. Наблюдения (неблокирующие)

### 🟡 O-1. Новый fail-fast путь по timeout (принято, зафиксировано)
- Файл: `src/Module/DynamicLoop/Domain/ValueObject/FacilitatorRunContextVo.php:47-49` (аналогично два других VO).
- Инвариант `timeout > 0` добавляет поведенческую дельту: значения `timeout <= 0` из CLI (`--timeout`)
  или role config (через `DynamicExecutionStrategy`, `?:`-цепочка без валидации) теперь кидают
  `InvalidArgumentException` в ходе turn-исполнения (в т.ч. при resume), где раньше молча проходили в runner.
- Оценка: fail-fast согласован с AGENTS.md («завершай работу с ошибкой») и конвенцией VO; решение уже
  зафиксировано в задаче (раздел 9). **Замечаний к правкам не предъявляю** — принимаю как осознанный инвариант.

### 🟡 O-2. Дупликация общих полей в трёх VO (кандидат в будущее)
- 6 полей/геттеров продублированы в каждом VO: `brainstormSystemPrompt`, `workingDir`, `responseFilesList`,
  `timeout`, `command`, `retryPolicy`. При появлении четвёртого вида запуска стоит рассмотреть вложение
  общего под-VO (runtime-опции). Сейчас — преждевременно (YAGNI), рефакторинг не требуется.

### 🟢 O-3. Документационная мелочь (не требует правок)
- В PHPDoc конструкторов VO описаны только неочевидные параметры (workingDir, command, retryPolicy и т.п.),
  строки промптов раскрыты только в классовом комментарии. Допустимо; при расширении VO стоит описывать все параметры.

---

## 3. Вердикт

**✅ Изменения одобрены (RV-A: approve).** Блокирующих замечаний нет. Must Have задачи выполнены:
VO-инкапсуляция хвоста сигнатуры (≤ 5 параметров), `@techdebt` снят, точки вызова обновлены, поведение
сохранено (правки тестов — только обвязка), дизайн VO соответствует Конвенциям, слои и границы модуля
не нарушены. Риск порядка полей VO закрыт именованными аргументами и типизированным callback-тестом.

Наблюдения O-1…O-3 — информационные, правок не требуют. Дальнейшие шаги — по регламенту задачи:
коммит/PR выполняет владелец, статус задачи переводится после CI.
