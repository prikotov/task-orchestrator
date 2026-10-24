# Исследование: Pi Durable (долговечное исполнение агентов)

**Источник:** [Earendil Works, Pi Durable, earendil.com/posts/pi-durable](https://earendil.com/posts/pi-durable/)  
**Дата первоисточника:** пост о пакете `@earendil-works/pi-durable` (статус experimental на момент чтения)  
**Дата анализа:** 2026-10-24  
**Эпик:** [EPIC-research-orchestration-articles](../../../todo/EPIC-research-orchestration-articles.todo.md)  
**Задача:** [TASK-research-pi-durable](../../../todo/done/TASK-research-pi-durable.todo.md)  
**Аналитик:** Аналитик (Шерлок)

---

## 0. Важное уточнение по первоисточнику

Это не теоретическая статья о паттернах, а **документация и анонс работающей библиотеки**: `@earendil-works/pi-durable` (TypeScript, каталог `packages/durable` в репозитории pi). Для трека это самый близкий к нам тип источника: не пересказ идей, а публичный контракт API, в котором паттерны оркестрации (durable tasks, saga-компенсация, failFast, replay-семантика) выражены кодом.

Две системные оговорки для вердиктов:

1. **Экспериментальный статус.** Автор прямо пишет: «Pi Durable is experimental, and the API might still change». Брать не API, а семантику и инварианты.
2. **Другая модель исполнения.** Pi Durable — долгоживущий Node-процесс с собственным хранилищем (harness, каркас, живёт месяцами). task-orchestrator — разовый PHP-процесс, запускаемый CLI-командой. Механизмы «на ходу» (malleable, фоновая компакция) не переносятся буквально — переносим принципы и те из них, что работают на нашей модели.

Сравнений с Temporal и другими workflow-движками автор не приводит; внутренняя реализация хранилища (журнал, протокол восстановления) в посте не раскрыта.

## 1. Краткий вывод

**Вердикт по статье:** `study` (изучить и проектировать) как основное направление — durable execution (долговечное исполнение) — следующая ступень зрелости нашего оркестратора после retry/circuit breaker; `apply` для отдельных принципов, переносимых уже сейчас (replay-классификация шагов, failFast-контракт для будущих параллельных шагов, «ничего не удаляется» для журналов).

Статья закрывает дыру, которую не закрыл ни один исследованный источник трека: **живая реализация saga-компенсации** (компенсация отменённой задачи с откатом побочных эффектов каскадом снизу вверх) и **replay-семантики** (какие вызовы после сбоя можно повторять, а какие — никогда). В [The Orchestrator's Tax](orchestrator-tax-research.md) компенсация и saga отсутствовали как тема; здесь они реализованы на конкретном примере оплаты: `abort` делает `refund`, идемпотентный ключ `payment-${task.id}` гарантирует «the card is only charged once».

**Главная идея для task-orchestrator:** «every step of a run is a task that stores a checkpoint before it moves on» — каждый шаг исполнения это задача с чекпойнтом (контрольной точкой) до перехода к следующему шагу; после сбоя процесса новый процесс открывает то же хранилище и продолжает незавершённые задачи с их чекпойнтов. У нас шаги цепочки не переживают смерть процесса — это главный gap, который подсвечивает статья.

## 2. Методология эпика: 6 критериев

| Критерий | Оценка | Вывод для task-orchestrator |
|---|---:|---|
| 1. Тезис / проблема | ✅ | Долговечность агента и состояния приложения: переживание сбоя процесса, транзакции, задачи, подзадачи, смена кода на ходу. |
| 2. Паттерн / концепция | ✅ | Durable tasks с чекпойнтами; replay-семантика (`safe` / no-replay); saga-компенсация bottom-up; ожидание задач с `failFast`; документы-состояние в атомарных коммитах со стенограммой; компакция как задача; подагент как беседа. |
| 3. Domain (область) | ✅ | AI-agent harness (каркас для построения агентов) — наш домен в точности; не микросервисная теория. |
| 4. Failure handling (обработка сбоев) | ✅ | Ядро источника: crash recovery, повтор только безопасных вызовов, компенсация, failFast, таймеры, переживающие рестарт, частичный ответ помечается aborted. |
| 5. Маппинг на архитектуру | ✅ | Механизмы ложатся на `ChainExecution` (статусы шагов, чекпойнты), `AgentRunner` (replay-семантика retry), `DynamicLoop` (session/journal как протохранилище), `ChainDefinition` (retry policy, when, postStep); слабее — на `GitIdentity`. Требует адаптации из-за разницы моделей исполнения. |
| 6. Применяемость | ✅ | `study` для архитектурных механизмов (resume, компенсация, документы), `apply` для принципов (классификация повторов, failFast-контракт, nothing-deleted). Числа-пороги не копировать. |

## 3. Содержание первоисточника

Главные тезисы поста (формулировки автора):

1. **Шаг = задача с чекпойнтом.** «In Pi Durable, every step of a run is a task that stores a checkpoint before it moves on. If the process dies, a new process opens the same storage, finds the unfinished tasks, and continues each one from its last checkpoint».
2. **Replay-семантика вызовов.** «Every tool call runs as its own durable task, and its intent is stored before it runs. After a crash, a tool reruns only if it says that is safe. Otherwise the model is told the call was interrupted, with the output stored so far, and decides what to do». Инструмент с побочными эффектами (`deploy`) помечается комментарием «interrupted by a crash is reported to the model, never repeated».
3. **Оборванный запрос модели.** «A model request that was cut off is sent again; the partial answer stays in the transcript, marked as aborted».
4. **Встроенные задачи harness'а.** «The harness runs conversations with built-in tasks: one for each model request, one for each tool call, and one for compaction» — и расширения получают тот же механизм: «a checkpoint after every step, timers that survive restarts, and waiting on other tasks».
5. **Транзакции.** Инструменты читают и пишут через `api.commit`; «Tools read records through a transaction too. One that writes nothing stores nothing». Решение и запись атомарны: внутри одной транзакции ищется существующий подагент и создаётся новый только если его нет.
6. **Документы — долговечное состояние приложения.** «Documents are typed JSON stored next to the transcript and changed in the same atomic commits, so the state never disagrees with the transcript that produced it». У документа заданы `kind`, `version`, `scope`, `history: "rewindable"`, `fork` (например `asOf` — «a fork starts with the todos its parent had at the fork entry»).
7. **Saga-компенсация.** Задача `Payment`: фаза `charge` с идемпотентным ключом «if a crash reruns this phase, the card is only charged once»; `abort` делает `bank.refund` и фиксирует `status: "aborted"`. Каскад: «Aborting a task aborts what it owns, bottom-up, so every task cleans up its own effects first, and a task only finishes once the work it owns has finished».
8. **Ожидание задач.** `status: "waiting"` + `checkpoint` + `on: payments` + `policy: "failFast"` — «Run no code until every payment is done. The first failed payment aborts the others».
9. **Подагент = беседа.** «The subagent is a conversation like any other, so it survives a crash, counts its own cost, and a UI can show it under the call». Подагенту дают «a smaller model and its own instructions» и сужают инструменты; владение — `ownership: { kind: "task", taskId }` («aborting the call aborts the subagent»).
10. **Права инструментов у беседы.** «The thread may search, but not deploy» — инструмент можно убрать у конкретной беседы (`tools: { remove: [deploy] }`).
11. **Контекст.** Компакция — «a task like any other, and it runs while the conversation keeps going»; пороги `reserveTokens: 16384` (следующий запрос ждёт сводку) и `backgroundTokens: 32768` (столько до границы — сводка стартует в фоне); ручной `compact` с собственными инструкциями; `reset()`/handoff начинает новый контекст, но «nothing is deleted» — «a second tool can still search everything before the handoff» (инструмент `search_history`).
12. **Malleable (изменяемость).** «We want to change the code of a running agent without stopping it… A tool call that is already running finishes on the code it started with; the next call uses the new code». «Conversations store extension and tool names, never code, so after a restart they pick up whatever the new process installs».
13. **Среда исполнения.** `env` строится из рабочей директории беседы: «Your `env` function builds the environment for every tool call, from the conversation's working directory, so each conversation can run in a different place»; «the harness to run on one machine while its tools run on another»; sandbox (песочница) — тоже документ.

## 4. Маппинг механизмов на task-orchestrator

| # | Механизм Pi Durable | Компоненты task-orchestrator | Текущее состояние | Вердикт | Effort (усилие) |
|---:|---|---|---|---|---|
| 1 | Чекпойнт после каждого шага + resume незавершённых задач после сбоя процесса | `src/Module/ChainExecution/Application/Service/ExecuteStaticChainService.php`, `src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopService.php`, session storage `src/Module/DynamicLoop/Infrastructure/Service/ChainSession*` | Состояние исполнения живёт в памяти процесса; JSONL audit и session journal пишутся, но возобновления прерванной цепочки нет — сбой = полный перезапуск | `study` — главный gap; нужен дизайн durable-журнала шагов и команды resume | High |
| 2 | Replay-семантика: повтор только `safe` (чтение), побочные эффекты — не повторять, а отчитаться | `src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunnerService.php`, `src/Module/ChainDefinition/Domain/ValueObject/ChainRetryPolicyVo.php`, `ChainStepVo` | Retry перезапускает шаг целиком; классификация ошибок есть (`ErrorClassificationVo`), но признака «повтор шага безопасен» (шаг только читает vs меняет репозиторий/GitHub) в конфиге нет | `apply` — принцип: явная replay-классификация шагов в `ChainDefinition` (read-only шаги можно перезапускать, мутирующие — только после проверки идемпотентности) | Medium |
| 3 | Идемпотентные ключи побочных эффектов (`payment-${task.id}`) | Правила агентов `AGENTS.md`, `docs/git-workflow/*`, потенциально `ChainStepVo` | Идемпотентность побочных эффектов агента (коммиты, PR) держится на дисциплине процесса, а не на механизме | `study` — как требование к mutating-шагам при будущей компенсации | Low |
| 4 | Saga-компенсация: `abort` → откат (refund), каскад bottom-up, «every task cleans up its own effects first» | `src/Module/ChainExecution/Application/Service/Chain/StaticExecutionStrategyService.php`, `ExecuteStaticChainService`, `ChainStepVo` (`postStep` — ближайшая точка расширения) | Сбой шага останавливает цепочку без отката выполненного; fix-итерации у quality gate есть, компенсирующих шагов нет | `study` — компенсирующие шаги в `ChainDefinition` (например, `compensate:` у шага) — прямое продолжение темы saga из методологии эпика | Medium/High |
| 5 | Ожидание задач: `waiting` + checkpoint фаз + `policy: "failFast"` | Backlog: `todo/backlog/TASK-feat-parallel-execution.todo.md`, `todo/backlog/TASK-feat-dag-orchestration.todo.md` | Параллельного исполнения шагов пока нет; контракт остановки «первого сбоя» не определён | `apply` — взять семантику `failFast` (первый сбой прерывает остальные, компенсация каскадом) как проектный контракт для parallel/DAG-задач ещё до реализации | Low (дизайн-заметки) |
| 6 | Подагент = беседа с владением, своей стоимостью, переживанием сбоя | `src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopAgentService.php`, `DynamicLoopSessionStateVo`, `src/Module/AgentRunner/Application/UseCase/Command/RunAgent/RunAgentCommandHandler.php` | Участники dynamic loop уже имеют отдельные сессии/journal и метрики (`DynamicLoopMetrics`); владения с каскадной отменой нет | `study` — ownership-модель для будущих подагентов | Medium |
| 7 | Сужение прав инструментов у беседы («may search, but not deploy») | `src/Module/ChainDefinition/Domain/ValueObject/ChainStepVo.php` (`noContextFiles`), `RoleConfigVo` | Изоляция есть на уровне ролей и контекстных файлов; per-step удаление инструментов у агента нет | `study` — расширение контракта шага | Medium |
| 8 | Компакция контекста: фоновая задача с порогами, ручной `compact`, «nothing is deleted» + `search_history` | `src/Module/AgentRunner/Application/UseCase/Command/RunAgent/RunAgentCommandHandler.php` (`maxContextLength` — усечение), `BuildDynamicContextService` | Контекст обрезается по длине без сводки; сжатие «старое → summary» нет; журналы не удаляются, но поиска по ним как инструмента агента нет | `study` — summary-компакция вместо грубого усечения; числа 16384/32768 не копировать | Medium/High |
| 9 | Документы: типизированное состояние приложения в атомарных коммитах со стенограммой; fork `asOf` | Session/journal: `ChainSessionWriter`/`ChainSessionReader`, `JsonlAuditLogger` | Состояние сессии и стенограммы пишутся рядом, но без транзакционных гарантий согласованности; состояния приложения в сессии нет | `study` — при дизайне durable-журнала (см. #1) заложить единый коммит «стенограмма + состояние» | High (вместе с #1) |
| 10 | Malleable: реестр меняется на ходу; «conversations store extension and tool names, never code» | `config/chains.yaml`, `config/modules.php`, пересборка контейнера | У нас конфигурация подхватывается при новом запуске процесса; долгоживущих сессий с горячей заменой нет | `skip` для буквального переноса; принцип «хранить имена, не код» — в audit-записи (записывать имя/версию цепочки и раннера, не снапшот) | Low |
| 11 | `env` из рабочей директории беседы; harness и инструменты на разных машинах; sandbox — документ | Backlog: `todo/backlog/TASK-feat-docker-sandboxing.todo.md` | Исполнение всегда локально относительно оркестратора; сэндбоксинг в backlog | `study` — «sandbox как часть состояния сессии» полезен для будущей изоляции параллельных агентов | Medium |
| 12 | «Nothing is deleted»: история доступна целиком, поиск по ней | Audit/session logs, отчёты `GenerateReportQueryHandler` | Журналы не удаляются — совпадает с принципом; добавить поиск по журналу сессии как утилиту можно дёшево | `apply` — принцип уже соблюдается; фиксировать как инвариант (не удалять JSONL-журналы при компакции отчётов) | Low |

## 5. Маппинг на модули проекта

### AgentRunner

Ближайший к replay-семантике (механизм #2): `RetryingAgentRunnerService` повторяет шаг по `RetryPolicyVo` с классификацией ошибок, `CircuitBreakerAgentRunnerService` переключает на fallback-раннер. Компонент `Infrastructure/Component/ProcessLiveness/` отслеживает живость процесса раннера.

**Gap по статье:** наш retry не различает «повтор безопасен» (шаг только читал) и «повтор удвоит побочный эффект» (агент уже закоммитил/создал PR). Классификация есть у ошибок, но нет у самих шагов. Pi Durable решает это признаком `replay: "safe"` у инструмента — нам соответствует явная replay-классификация шага в `ChainDefinition`.

### ChainExecution

Ядро для механизмов #1, #4, #5: `OrchestrateChainCommandHandler` выбирает стратегию, `RunStaticChainService` ведёт step context, бюджет, fix-итерации, хуки; `ConditionalExecutionStrategyService` управляет `when`-условиями.

**Gap:** состояние выполнения — в памяти; JSONL audit пишется как след, но не как источник возобновления. Сбой процесса посреди цепочки не восстанавливается. `postStep`-хуки — готовая точка для компенсирующих шагов, но семантики компенсации (что откатывать при сбое шага N после успешных шагов 1..N-1) нет.

### DynamicLoop

Ближе всего к «состояние рядом со стенограммой» (механизмы #6, #9): session state, journal, audit-логгер уже разделены по фазам, `DynamicLoopMetrics` считает стоимость, сессия прерывается с причиной (`agent_error`, `timeout`).

**Gap:** сессия переживает свои внутренние сбои (шаг → фиксация → следующий раунд), но не переживает смерть оркестратора; состояние сессии и journal согласованы процедурно, а не транзакционно.

### ChainDefinition

Декларативный контракт шага: тип (`agent`, `quality_gate`, `tool`), роль, раннер, retry policy, `noContextFiles`, `when`, `postStep`, fix-итерации.

**Gap под механизмы #2, #4, #7:** в контракте шага нет replay-классификации (read-only / mutating), компенсирующего шага и сужения инструментов. Это естественное место для расширения схемы `chains.yaml` — до какой-либо движковой работы.

### GitIdentity

Обеспечивает идентичность бота для GitHub-операций. Прямого отношения к durability нет, но косвенно важен для механизма #3: идемпотентность побочных эффектов агента (не создать дубль PR при повторе шага) опирается на предсказуемую идентичность и повторяемые ключи (названия веток, заголовки PR).

## 6. Failure handling: что уже закрыто и что нет

| Риск | У нас закрыто? | Комментарий |
|---|---:|---|
| Временный сбой раннера | ✅ | Retry с классификацией в `RetryingAgentRunnerService`. |
| Недоступность провайдера/раннера | ✅ | Circuit breaker + fallback. |
| Превышение бюджета | ✅ | Static и dynamic budget checks. |
| Ошибка/таймаут агента в dynamic loop | ✅ | Сессия прерывается с причиной `agent_error`/`timeout`. |
| Смерть процесса оркестратора посреди цепочки | ❌ | Нет durable-журнала шагов и команды resume; запуск начинается заново (главный gap статьи). |
| Повтор мутирующего шага = двойной побочный эффект | ⚠️ | Retry повторяет шаг целиком; защита — только дисциплина процесса (AGENTS.md запрещает автокоммиты). |
| Откат выполненных шагов при позднем сбое (компенсация) | ❌ | Компенсирующих шагов нет; `postStep` — точка расширения. |
| Остановка «первого сбоя» в параллельных шагах | ⚠️ | Параллельного исполнения ещё нет; контракт (`failFast` + каскадная компенсация) стоит зафиксировать до реализации. |
| Переполнение контекста модели | ⚠️ | Усечение по `maxContextLength` без сводки; фоновая компакция с summary отсутствует. |
| Согласованность состояния сессии и журнала | ⚠️ | Процедурная, без атомарного коммита «стенограмма + состояние». |

## 7. Проверка гипотезы: durable execution vs наша модель отказоустойчивости

| Критерий | Pi Durable | task-orchestrator сейчас |
|---|---|---|
| Единица устойчивости | Задача (task) с чекпойнтом после каждого шага | Вызов раннера (retry/circuit breaker внутри одного запуска) |
| Что переживает сбой | Процесс умирает → новый процесс открывает хранилище и продолжает | Ничего на уровне цепочки: перезапуск команды с нуля |
| Повтор вызова | Только `replay: "safe"`; мутирующие — «never repeated», модель получает отчёт о прерывании | По retry policy без различения безопасности повтора |
| Откат побочных эффектов | Saga-компенсация bottom-up (refund) | Нет |
| Модельный запрос после обрыва | Отправляется снова; частичный ответ в стенограмме с пометкой aborted | Повтор шага создаёт новую попытку; пометки частичного результата нет |
| Состояние приложения | Документы в атомарных коммитах со стенограммой | Сессии/журналы рядом, без транзакционности |

**Вывод:** Pi Durable не меняет модель координации — это по-прежнему централизованный orchestrator (в терминах [The Orchestrator's Tax](orchestrator-tax-research.md)); durability добавляет вертикаль «состояние», не отменяя orchestrator. Наша ставка на централизованный оркестратор валидируется ещё раз; статья указывает следующий уровень зрелости: **устойчивость к смерти самого оркестратора**, а не только к сбоям раннеров.

## 8. Что делаем правильно / gaps / не применимо

### Уже делаем правильно

- Устойчивость уровня вызова: retry с классификацией ошибок, circuit breaker с fallback, бюджеты, прерывание dynamic loop с явной причиной.
- Audit/session журналы пишутся и не удаляются — совпадает с принципом «nothing is deleted».
- Метрики стоимости у dynamic loop (`DynamicLoopMetrics`) — аналог «counts its own cost» у подагентов.
- Отдельное хранение сессий/журналов по фазам — заготовка под durable-журнал.

### Gaps

1. Нет durable-журнала шагов и команды `resume` для прерванной цепочки — сбой оркестратора теряет прогресс.
2. Нет replay-классификации шагов (read-only / mutating) — retry может удвоить побочный эффект.
3. Нет компенсирующих шагов и каскадной отмены (saga) — поздний сбой не откатывает выполненное.
4. Нет контракта остановки параллельных шагов (`failFast`) — надо зафиксировать до реализации parallel/DAG.
5. Контекст обрезается, а не сжимается: нет summary-компакции и поиска по истории как инструмента агента.
6. Состояние сессии и стенограмма согласованы процедурно, без атомарного коммита.

### Не применимо из этой статьи

- Malleable (горячая замена кода реестра) — у нас нет долгоживущих процессов; конфиг подхватывается новым запуском.
- Фоновая компакция «во время беседы» — наша беседа (шаг агента) коротка; переносима только идея summary вместо усечения.
- Числовые пороги `16384`/`32768` — параметры примера автора, зависят от модели.
- Буквальный перенос API (`defineTask`, `defineDoc`) — экспериментальный TypeScript-контракт; переносима семантика.

## 9. Рекомендации

| # | Рекомендация | Тип | Effort | Приоритет |
|---:|---|---|---|---|
| 1 | Зафиксировать контракт replay-классификации шагов в `ChainDefinition` (`read-only` / `mutating`): read-only шаги ретраятся смело, mutating — только с явной идемпотентностью. | `apply` | Medium | P1 |
| 2 | В дизайн parallel/DAG-задач заложить семантику `failFast`: первый сбой прерывает остальные шаги + каскадная компенсация выполненных. | `apply` | Low (дизайн) | P1 |
| 3 | Спроектировать durable-журнал шагов цепочки + команду `resume` (возобновление прерванной цепочки с последнего зафиксированного шага) — отдельная research/feat-задача. | `study` | High | P2 |
| 4 | Спроектировать компенсирующие шаги (`compensate:` у шага, каскад bottom-up) — прямое применение saga из методологии эпика. | `study` | Medium/High | P2 |
| 5 | Заменить грубое усечение контекста (`maxContextLength`) на summary-компакцию: старые результаты шагов сжимаются в сводку, полный журнал остаётся доступным. | `study` | Medium/High | P2 |
| 6 | В audit-записи хранить имена/версии цепочки и раннеров, а не снапшоты конфигурации (принцип «store names, never code»). | `apply` | Low | P3 |
| 7 | Для mutating-шагов требовать идемпотентные ключи (предсказуемые имена веток/заголовки PR) — расширить правила процесса. | `study` | Low | P3 |

## 10. Итоговый вердикт

**Итог:** `study` для архитектурных механизмов (durable-журнал с resume, saga-компенсация, документы-состояние, компакция), `apply` для принципов и контрактов (replay-классификация шагов, `failFast` для параллельных шагов, «store names, never code», «nothing is deleted»), `skip` для malleable и буквального переноса API.

Статья — первый источник трека, где оркестрационные паттерны из классики (saga, compensation, durable execution) показаны работающим кодом в домене AI-агентов. Она валидирует нашу ставку на централизованный оркестратор и одновременно поднимает планку: устойчивость к сбоям раннеров — необходимый, но не достаточный уровень; следующий — устойчивость самого процесса оркестрации.

Главный практический принцип: **повтор после сбоя — это решение с последствиями, а не рефлекс: read-only повторяется свободно, мутирующее — только с идемпотентным ключом, а при сомнении — не повторять, а отчитаться и откатить.**
