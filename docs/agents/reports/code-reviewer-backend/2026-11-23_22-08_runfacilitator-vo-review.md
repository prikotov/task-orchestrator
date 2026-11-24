# Ревью RV-F: ветка `task/runfacilitator-vo` (diff против HEAD)

**Роль:** Ревьювер Бэка Пуаро (code_reviewer_backend_puaro)
**Дата:** 2026-11-23
**Объект:** незакоммиченные изменения в рабочем дереве ветки `task/runfacilitator-vo` (diff против HEAD)
**Задача:** `todo/TASK-techdebt-runfacilitator-vo.todo.md` (type: refactor, RV-F по RACI)
**Примечание тимлида:** отчёт заархивирован Тимлидом Алексом дословно из вердикта сабагента (сабагент завершился без сохранения файла по скиллу `agent-report`).

---

**Роль:** Ревьювер Бэка Пуаро. Метод — статическое сопоставление diff с постановкой (`todo/TASK-techdebt-runfacilitator-vo.todo.md`, разделы 3 и 5) и Конвенциями. Прогоны не выполнялись по указанию заказчика: `make check` зелёный (PHPUnit 1773 теста / 5207 assertions), код не правился.

## Вердикт: 🔶 Change Request (запрос на доработку) — 2 замечания

Механика рефакторинга безупречна — мои «маленькие серые клеточки» сверили каждый аргумент. Но тестовая сетка безопасности, на которую ссылается постановка, существует лишь на бумаге, и один инвариант тихо меняет поведение на краевых входах. Разберём по порядку.

---

## Что проверено и подтверждено (трассировка к разделам 3 и 5)

| Критерий | Статус |
|---|---|
| 🔴 `runFacilitator()` ≤ 5 параметров с VO | ✅ 5 параметров (`step`, `round`, `facilitatorRole`, `topic`, `FacilitatorRunContextVo`), `RunDynamicLoopAgentServiceInterface.php:27` |
| 🔴 Все точки вызова обновлены | ✅ Grep по `src/`, `apps/`, `tests/`, `config/`: единственные вызовы `agentRunner->run*()` — `ExecuteDynamicTurnService.php:279, 342, 427`; иных реализаций интерфейса и вызовов нет |
| 🔴 Поведение сохранено (для валидных входов) | ✅ Порядок полей в конструкторах VO 1:1 совпадает с хвостом старых сигнатур; все вызовы переведены на именованные аргументы; дефолты (`command=[]`, `retryPolicy=null`, `taskAddendum=null`, `hasPreviousResponses=true`, `challenge=null`, `promptFile=null`) перенесены в VO без изменений; в Integration-реализации каждый геттер стоит ровно там, где стоял прежний параметр (включая порядок в `buildFacilitatorContext`, `buildParticipantUserPrompt`, `buildFinalizeContext`, `writePromptFile`, `DynamicLoopRunRequestVo`) |
| 🔴 `@techdebt` снят | ✅ Grep `techdebt|@todo` по `src/Module/DynamicLoop/` — чисто |
| 🟡 `runParticipant()` / 🟢 `runFacilitatorFinalize()` тем же паттерном | ✅ Решение зафиксировано в Comments задачи — допустимое оформление, не отклонение |
| Assertions существующих тестов не изменены | ✅ В `ExecuteDynamicTurnServiceTest.php:99–151` заменена только обвязка захвата (`assertCount(15)` → типизированный разбор); поведенческий `assertSame('Новая вводная от Лида', …)` не тронут, добавлены новые проверки идентификации — усиление, не ослабление |
| Конвенция VO (`core-patterns/value-object.md`) | ✅ `final readonly`, приватные типизированные свойства, постфикс `Vo`, `Domain/ValueObject/`, `InvalidArgumentException` на инвариант, `@param list<string>`, без I/O и побочных эффектов |
| Тесты VO | ✅ 3 новых теста: все геттеры, дефолты, инвариант (0 и отрицательный) — полное покрытие новых классов, расположение согласовано с существующими VO-тестами |
| Прочее | ✅ Строгие типы, `#[Override]` на месте, мёртвых импортов нет (`DynamicLoopRetryPolicyVo` в Integration-сервисе используется на строке 289), секретов и подавлений анализаторов нет, Change History актуален |

---

## Замечания (Change Requests)

### CR-1 🔴 Замена позиционных аргументов на VO в Integration-реализации не покрыта ни одним тестом; предпосылка раздела 7 задачи не подтвердилась

- **Где:** `src/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentService.php:53–60` (`buildFacilitatorContext`), `:123–129` (`buildParticipantUserPrompt`), `:183–187` (`buildFinalizeContext`) и проброс геттеров в `DynamicLoopRunRequestVo` (строки ~85, ~152, ~212).
- **Факт:** ни один тест не инстанцирует `RunDynamicLoopAgentService` (grep `new RunDynamicLoopAgentService|RunDynamicLoopAgentService::class` по `tests/` — пусто). Integration-тесты dynamic/resume-цепочек подменяют `RunDynamicLoopServiceInterface` стабом (`tests/Integration/Application/UseCase/Command/OrchestrateChain/DynamicChainIntegrationTest.php:61`, `StubDynamicLoopService.php`) — изменённый код в них **не исполняется**. Раздел 7 задачи предписывает закрывать риск «ошибка в порядке полей VO изменит поведение resume» именно этими тестами — предпосылка ложна.
- **Усугубляет:** единственный захватывающий тест (`ExecuteDynamicTurnServiceTest.php:112–129`) закрепляет лишь `taskAddendum` и 4 идентификационных параметра. Перестановка `facilitatorStartPrompt` ↔ `facilitatorContinuePrompt` или `summary` ↔ `responseFilesList` в `ExecuteDynamicTurnService.php:431–441` не будет поймана **нигде**. Маппинг сверен статически — он корректен, но рефакторинг такого рода должен оставлять после себя закреплённый контракт, а не надежду на ревьювера.
- **Запрос (любой из вариантов):**
  1. Добавить модульный тест `RunDynamicLoopAgentService` со стабами зависимостей (`RunAgentQueryHandler`, `DynamicLoopSessionWriterInterface`, `FacilitatorResponseParserInterface`, `QueryBusComponentInterface`, `DynamicLoopPromptFormatterInterface`), закрепляющий порядок аргументов `buildFacilitatorContext`/`buildParticipantUserPrompt`/`buildFinalizeContext` и поля `DynamicLoopRunRequestVo` для всех трёх методов; **или** расширить захват в `ExecuteDynamicTurnServiceTest` до всех полей VO;
  2. поскольку дыра предсуществующая — допустимо оформить её техдолгом: отдельная задача в `todo/` + запись в Comments этой задачи с утверждением тимлида (по правилам `docs/guide/review-functional.md`). Молча оставить нельзя.

### CR-2 🟡 Инвариант `timeout > 0` меняет поведение на ранее проходивших входах — оформить как осознанное отступление

- **Где:** `FacilitatorRunContextVo.php:48–49`, `ParticipantRunContextVo.php:49–50`, `FacilitatorFinalizeRunContextVo.php:42–43`.
- **Факт:** раньше `timeout ≤ 0` доходил до runner'а: CLI `--timeout=0` кастуется без валидации (`apps/console/src/Module/Orchestrator/Command/OrchestrateCommand.php:359–364`), YAML `timeout: 0` проходит загрузчик без проверки (`YamlChainLoaderService.php:174, 330`), `DynamicLoopContextVo::$timeout` инварианта не имеет. Процесс при этом жил под hard cap (`ProcessLivenessWatcher::resolveHardCap` — `max(0, 1800)`). Теперь — `InvalidArgumentException` на первом же ходу. Для валидных значений поведение идентично, но Won't Have задачи гласит «Смена семантики параметров» — а семантика краевого входа изменилась.
- **Оформление:** инвариант записан в Comments как решение исполнителя, однако запись не фиксирует сам факт изменения поведения для `timeout ≤ 0` и не содержит утверждения тимлида — по `review-functional.md` это ещё не «осознанное отступление».
- **Запрос:** дополнить запись в задаче: (а) явно указать, что `timeout ≤ 0` ранее проходил транзитом, теперь fail-fast; (б) причина (значения ≤ 0 не являются документированным контрактом, hard cap всё равно доминировал); (в) утверждение тимлида. Код менять не требуется — fail-fast здесь разумнее прежнего молчаливого проглатывания, я лишь требую порядка в документах.

---

## Допущения и риски

- **Допущение:** результату `make check` (PHPMD 0 нарушений, Psalm/PHPStan OK) доверяю по указанию заказчика; статически подтвердил лишь отсутствие мёртвых импортов и соответствие конвенциям.
- **Риск CR-1:** до его закрытия корректность маппинга держится только на статическом разборе ревьювера — при следующем расширении VO страховки не будет.
- **Риск CR-2:** сценарий `--timeout=0` у пользователя, полагавшегося на «0 = без таймаута», теперь упадёт на старте цикла; после оформления отступления это станет принятым решением.
- Вне объёма ревью: предсуществующая deprecation `ReflectionMethod::setAccessible()` (`RunDynamicLoopServiceFinalizeReserveTest.php:151`) — уже зафиксирована в задаче, замечание не выставляется.

**Итог:** после закрытия CR-1 (тест либо оформленный техдолг) и CR-2 (оформленное отступление) — готов одобрить. Код выполнен аккуратно: симметрия сигнатур и чистота VO выше всяких похвал. Но порядок должен быть не только в коде, но и в бумагах. 🎩
