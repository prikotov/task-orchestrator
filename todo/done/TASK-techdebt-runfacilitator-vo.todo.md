---
type: refactor
created: 2026-11-23 19:30:07 (1795437007)
due: 
started: 2026-11-23 21:39:12 (1795444752)
completed: 2026-11-23 22:38:45 (1795448325)
cancelled: 
value: V2
complexity: C2
priority: P3
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Алекс (pi)
assignee: Бэкендер Левша (pi)
consultants: []
reviewer: 
approver: 
informed: []
branch: task/runfacilitator-vo
pr: https://github.com/prikotov/task-orchestrator/pull/493
status: done
---

# TASK-techdebt-runfacilitator-vo: Инкапсулировать хвост сигнатуры runFacilitator() в VO

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Метод `RunDynamicLoopAgentServiceInterface::runFacilitator()` принимает **15 позиционных скалярных параметров** — классический long parameter list. Хвост сигнатуры (промпты `brainstormSystemPrompt`/`facilitatorAppendPrompt`/`facilitatorStartPrompt`/`facilitatorContinuePrompt`, `facilitatorSummary`, `responseFilesList`, `timeout`, `command`, `retryPolicy`, `taskAddendum`) — связный контекст одного раунда, который передаётся единым блоком через все точки вызова.
- Замечание внесено ревьювером Бэка Пуаро при ревью resume-фикса (2026-11-22) и подтверждено в ревью PR #490 (RV-F/RV-A); в коде стоит `@techdebt 2026-11-23`.
- Риск: при добавлении нового параметра легко перепутать позиции однотипных строк (четыре промпта подряд) — компилятор это не поймает.

### Варианты или путь решения (Solution Sketch)
- Выделить VO (например, `FacilitatorRunContextVo`) в `DynamicLoop/Domain/ValueObject/` и инкапсулировать в него хвост сигнатуры (промпты, summary, список файлов, timeout, command, retryPolicy, taskAddendum), оставив в сигнатуре 2–4 позиционных параметра идентификации (`$step`, `$round`, `$facilitatorRole`, `$topic`).
- Обновить реализацию `RunDynamicLoopAgentService`, транзитные интерфейсы (`ExecuteDynamicTurnServiceInterface`) и точки вызова (`RunDynamicLoopService`, `ExecuteDynamicTurnService`).
- Аналогично рассмотреть `runParticipant()` (13 параметров) — если паттерн ложится, применить тот же VO-подход в том же PR; если нет — отдельным решением.

### Ожидаемый результат (Expected Result)
- Сигнатура `runFacilitator()` — не более 4–5 параметров, из них один VO; PHPMD-нарушений по сигнатуре нет; `@techdebt` снят.
- Поведение dynamic-цикла не изменилось: все существующие тесты зелёные без правок assertions.

## 1. Концепция и Цель (Concept and Goal)
- Устранить long parameter list в контракте запуска facilitator'а: связный контекст раунда — один объект, а не 15 позиционных аргументов; снизить риск перепутать однотипные строковые параметры при расширении.

## 2. Контекст и Границы (Context and Scope)
*   **Где делаем:** `src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopAgentServiceInterface.php`; `src/Module/DynamicLoop/Domain/Service/Dynamic/ExecuteDynamicTurnServiceInterface.php`; `src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopService.php`; `src/Module/DynamicLoop/Domain/Service/Dynamic/ExecuteDynamicTurnService.php`; `src/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentService.php`; новый VO в `src/Module/DynamicLoop/Domain/ValueObject/`.
*   **Текущее поведение:** функциональность корректна; долю — чисто структурная (техдолг из ревью).
*   **Точка входа долга:** `@techdebt 2026-11-23` на `runFacilitator()` (установлен в PR #490).
*   **Критичные конвенции:** `docs/conventions/core-patterns/value-object.md` (или ближайший по Conventions паттерн VO), `docs/conventions/index.md`.
*   **Границы (Out of Scope):** изменение поведения dynamic-цикла, рендеринга промптов и resume-логики; рефакторинг `runParticipant()`, если VO-подход не ложится (зафиксировать решение в задаче); трогать другие модули.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [ ] `runFacilitator()`: хвост сигнатуры инкапсулирован в VO, в сигнатуре осталось ≤ 5 параметров.
- [ ] Все точки вызова обновлены; поведение сохранено — тесты зелёные без правок assertions (правки фикстур/обвязки допустимы).
- [ ] `@techdebt` с метода снят (долг устранён).
- [ ] `make check` зелёный.

### 🟡 Желательно (Should Have)
- [ ] `runParticipant()` приведён к тому же паттерну (если решение согласовано при реализации).

### 🟢 Опционально (Could Have)
- [ ] Проверка аналогичных длинных сигнатур в модуле DynamicLoop и точечная инкапсуляция.

### ⚫ Не будем делать (Won't Have)
- [ ] Смена семантики параметров, порядка раундов и форматов промптов.
- [ ] Введение обратно несовместимых изменений публичных контрактов за пределами модуля DynamicLoop без обновления всех точек вызова в одном PR.

## 4. План реализации (Implementation Plan)
1. [ ] Спроектировать VO: состав полей = хвост сигнатуры, иммутабельность, `final readonly`.
2. [ ] Обновить интерфейсы и реализацию; транзитно — `ExecuteDynamicTurnServiceInterface`.
3. [ ] Обновить вызовы (`RunDynamicLoopService`, `ExecuteDynamicTurnService`) и тесты (конструкторная/аргументная обвязка).
4. [ ] Снять `@techdebt`, прогнать `make check`.

## 5. Критерии приёмки (Definition of Done)
- [ ] Сигнатура `runFacilitator()` ≤ 5 параметров с VO; PHPMD 0 нарушений.
- [ ] `vendor/bin/phpunit` и `make check` зелёные; assertions существующих тестов не изменены.
- [ ] `@techdebt` снят; трассировка в этой задаче (Change History) актуальна.

## 6. Самопроверка (Verification)
```bash
make check                                            # lint + валидация + тесты
php vendor/bin/todo-md validate todo/TASK-techdebt-runfacilitator-vo.todo.md
```

## 7. Риски и зависимости (Risks and Dependencies)
- `runFacilitator()` — контракт в цикле resume динамических цепочек: ошибка в порядке полей VO изменит поведение resume; закрывать существующими integration-тестами dynamic/resume-цепочек.
- VO объединяет параметры с разными жизненными циклами (промпты vs runtime-опции): при проектировании не смешивать группы без необходимости.

## 8. Источники (Sources)
- [ ] Замечание ревью resume-фикса (Пуаро, 2026-11-22) — зафиксировано в `todo/done/TASK-refactor-overlength-services.todo.md`, раздел 9.
- [ ] Ревью PR #490: RV-F (Пуаро) и RV-A (Локи) — наблюдение «techdebt без задачи» устранено созданием этой задачи.
- [ ] Отчёты ревью: `docs/agents/reports/system-architect-loki/2026-11-23_18-45…`, `…_19-01…`.

## 9. Комментарии (Comments)
- Задача создана по явному подтверждению владельца (2026-11-23, сессия merge PR #490): «заведи».
- Исполнитель и ревьювер по RACI для `refactor`: Бэкендер (реализация), Ревьювер Бэка (RV-F) + Архитектор (RV-A).
- Решение при реализации (2026-11-23, Бэкендер Левша): VO-подход применён ко всем трём методам интерфейса `RunDynamicLoopAgentServiceInterface` — `runFacilitator()` (Must), `runParticipant()` и `runFacilitatorFinalize()` (Should/Could, паттерн ложится — сигнатуры однотипные). Каждый метод получил свой VO (`FacilitatorRunContextVo`, `ParticipantRunContextVo`, `FacilitatorFinalizeRunContextVo`) и сократился до 5 позиционных параметров идентификации (step, round, role, topic + VO). Частичная инкапсуляция оставила бы интерфейс неоднородным.
- Инвариант VO: `timeout > 0` (InvalidArgumentException при нарушении) — остальные поля валидации не требуют (summary/responseFilesList могут быть пустыми легитимно, taskAddendum nullable).
- Тест `runFacilitatorTurnForwardsTaskAddendumToAgentRunner`: обвязка захвата аргументов заменена (`assertCount(15)` → типизированный разбор VO); поведенческая проверка переноса taskAddendum (`assertSame`) не изменена.
- Транзитный контракт изменений не потребовал: `ExecuteDynamicTurnServiceInterface` (`runFacilitatorTurn`/`runParticipantTurn`/…) и `RunDynamicLoopService` уже работают короткими сигнатурами (конфигурационные VO цепи); хвост сигнатуры agent runner'а собирается внутри `ExecuteDynamicTurnService` — правки ограничены `RunDynamicLoopAgentServiceInterface`, `ExecuteDynamicTurnService` и Integration-реализацией `RunDynamicLoopAgentService`.
- Верификация после возобновления (прерывание по таймауту на финальной проверке, 2026-11-23): критерии раздела 5 подтверждены — три метода по 5 параметров с VO, `@techdebt` в модуле DynamicLoop отсутствует (grep чист), поведенческие assertions существующих тестов не изменены (правка только обвязки, см. выше).
- `make check` — зелёный: PHPStan OK, Psalm OK (покрытие типов 98.9%), PHPMD 0 нарушений, PHPCS OK, Deptrac 0 ошибок/0 предупреждений, PHPUnit 1773 теста / 5207 assertions / 2 skipped.
- 1 deprecation в PHPUnit не связана с задачей: `tests/Unit/Domain/Service/Chain/Dynamic/RunDynamicLoopServiceFinalizeReserveTest.php:151` — `ReflectionMethod::setAccessible()` deprecated с PHP 8.5 (не имеет эффекта с PHP 8.1). Файл вне изменений задачи, воспроизводится на базе `main` — по решению владельца не чинится, зафиксирована как предсуществующая (кандидат в отдельный техдолг).
- Осознанное отступление (утверждено тимлидом Алексом, 2026-11-23): инвариант `timeout > 0` в run-контекстах (`FacilitatorRunContextVo`, `ParticipantRunContextVo`, `FacilitatorFinalizeRunContextVo`) добавляет fail-fast — значения `timeout <= 0` из CLI (`--timeout`) или role config теперь кидают `InvalidArgumentException` при старте turn (в т.ч. resume), тогда как раньше проходили транзитом в runner без валидации. Принято, потому что: (1) значения `timeout <= 0` не являются документированным контрактом; (2) процесс всё равно жил под hard cap `ProcessLivenessWatcher` (`max(0, 1800)`); (3) fail-fast согласован с AGENTS.md («немедленно завершай работу с ошибкой») и конвенцией VO. Код менять не требуется — оформление по запросу ревью (CR-2, RV-F).

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-23 19:30:07 (1795437007) | Тимлид Алекс (pi) | Создание задачи |
| 2026-11-23 21:39:12 (1795444752) | Бэкендер Левша (pi) | Старт задачи (ветка `task/runfacilitator-vo`) |
| 2026-11-23 21:46:58 (1795445218) | Бэкендер Левша (pi) | Реализация: 3 VO (`FacilitatorRunContextVo`, `ParticipantRunContextVo`, `FacilitatorFinalizeRunContextVo`) в `DynamicLoop/Domain/ValueObject/`; сигнатуры `runFacilitator()`/`runParticipant()`/`runFacilitatorFinalize()` сокращены до 5 параметров; `@techdebt` снят; обновлены `ExecuteDynamicTurnService` и Integration-реализация `RunDynamicLoopAgentService`; тесты: 3 новых VO-теста + адаптация обвязки `ExecuteDynamicTurnServiceTest`; `make check` — зелёный. Статус остаётся in_progress до PR/CI (коммит выполняет владелец) |
| 2026-11-23 21:58:34 (1795445914) | Бэкендер Левша (pi) | Верификация после возобновления (предыдущий запуск прерван по таймауту): сверено с разделом 5 — сигнатуры 3 методов ≤ 5 параметров с VO, `@techdebt` снят, поведенческие assertions существующих тестов не изменены; `make check` зелёный (PHPStan/Psalm/PHPMD/PHPCS/Deptrac — 0 нарушений; PHPUnit 1773/5207, 2 skipped); 1 PHPUnit deprecation (`ReflectionMethod::setAccessible()`, PHP 8.5) предсуществующая на `main`, вне изменений задачи — не чинится, зафиксирована. Коммит выполняет владелец |
| 2026-11-23 22:38:45 (1795448325) | Тимлид Алекс (pi) | Задача переведена в done после RV-F/RV-A апрувов и зелёного make check; PR #493 создан (метка pi), ветка task/runfacilitator-vo опубликована. Неблокирующая рекомендация ревью (finalize/participant-кейсы с ненулевой retryPolicy) — кандидат в будущий техдолг |
| 2026-11-23 22:28:14 (1795447694) | Бэкендер Левша (pi) | Доработка по результатам ревью (итерация 2, RV-F Пуаро: 2026-11-23_22-08_runfacilitator-vo-review): CR-1 закрыт модульным тестом `tests/Unit/Common/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentServiceTest.php` — 6 тестов / 128 assertions на стабах (`RunAgentQueryHandler`, `DynamicLoopSessionWriterInterface`, `FacilitatorResponseParserInterface`, `QueryBusComponentInterface`, `DynamicLoopPromptFormatterInterface`): закреплены порядок аргументов `buildFacilitatorContext`/`buildParticipantUserPrompt`/`buildFinalizeContext` (start vs continue, summary, responseFilesList, taskAddendum), поля `DynamicLoopRunRequestVo`, маппинг в `ChainRunRequestVo` (вкл. retry policy и error-path); эффективность проверки подтверждена мутацией (перестановка start/continue ловится). CR-2 оформлен как осознанное отступление (раздел 9, утверждено тимлидом). RV-A Локи — одобрено без замечаний (отчёт `docs/agents/reports/system-architect-loki/2026-11-23_22-19_runfacilitator-vo-architecture-review.md`). `make check` зелёный. Коммит выполняет владелец |
