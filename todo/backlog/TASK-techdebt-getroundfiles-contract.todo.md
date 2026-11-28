---
type: refactor
created: 2026-11-28 01:30:15 (1795829415)
updated: 2026-11-28 01:40:38 (1795830038)
due: 
started: 
completed: 
cancelled: 
value: V2
complexity: C2
priority: P3
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Архитектор Гэндальф (pi)
assignee: 
consultants: []
reviewer: 
approver: 
informed: []
branch: 
pr: 
status: backlog
---

# TASK-techdebt-getroundfiles-contract: Simplify getRoundFiles contract to list shape / сузить контракт getRoundFiles для снятия подавления argument.type

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Описание данных, которые программа возвращает при восстановлении динамического цикла, сейчас слишком неточное. Разработчики вынуждены отключать одну проверку статического анализатора, хотя фактические данные подходят для восстановления. Это снижает доверие к автоматическим проверкам и оставляет риск, что будущее несовместимое изменение останется незамеченным.

### Варианты или путь решения (Solution Sketch)
- Основной вариант: точно описать возвращаемый список записей раундов в доменном контракте и привести к нему инфраструктурную реализацию, после чего удалить ставшее ненужным подавление проверки.
- Согласованная граница преобразования — только публичный результат `ChainSessionReader::getRoundFiles()`: единственный `array_values()` меняет ключи с `1..n` на `0..n-1`, не меняя внутреннее хранение и формат `session.json`. Решение владельца Q1: смена публичных ключей допустима, поскольку единственный прод-потребитель `RunDynamicLoopService::initExecution()` ключи не использует.
- Каноническая форма записи по решению владельца Q2 — полная фактическая форма Writer/Reader. Type alias (`@psalm-type`/`@phpstan-type`) не вводится; инлайн-дублирование формы приемлемо для `C2`.

### Ожидаемый результат (Expected Result)
- Восстановление динамического цикла работает как прежде, а автоматическая проверка типов подтверждает совместимость без локального исключения. Разработчик видит точный контракт данных и может безопасно менять его реализацию.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как сопровождающий проекта, я хочу, чтобы `getRoundFiles()` возвращал статически проверяемый `list<array{...}>`, чтобы удалить подавление `argument.type` и не скрывать возможные несоответствия данных при восстановлении dynamic-цикла.

### Цель по SMART (Goal)
- **S — конкретно:** сузить PHPDoc-контракт `getRoundFiles()` до `list<array{...}>`, синхронизировать Domain, Infrastructure и тестовые реализации, обеспечить фактический list на границе возврата и удалить `@phpstan-ignore argument.type` в `RunDynamicLoopService`.
- **M — измеримо:** регрессионные тесты подтверждают `array_is_list($roundFiles)`, сохранение порядка и значений записей; PHPStan проходит без целевого подавления и без `ignore.unmatchedIdentifier`; PHPUnit, Psalm и полный `make check` зелёные.
- **A — достижимо:** изменения ограничены модулем DynamicLoop, его файловой реализацией сессии и связанными тестами; новые зависимости и изменение формата хранения не требуются.
- **R — релевантно:** задача закрывает конкретный техдолг из аудита подавлений и повышает надёжность контракта Domain ↔ Infrastructure без смены поведения восстановления.
- **T — ограничено во времени:** дедлайн и релиз не назначены (`P3`, `backlog`); после перевода в `todo` выполнить одной итерацией и одним отдельным PR. Q1–Q3 закрыты решениями владельца; блокирующих вопросов для активации и реализации не осталось.

## 2. Контекст и Границы (Context and Scope)

* **Где делаем:**
  * `src/Module/DynamicLoop/Domain/Service/Session/DynamicLoopSessionReaderInterface.php` — фактическое объявление `getRoundFiles()`; `DynamicLoopSessionLoggerInterface` наследует этот контракт, поэтому правка только агрегатного интерфейса недостаточна и может создать расхождение с reader-контрактом.
  * `src/Module/DynamicLoop/Domain/Service/Session/DynamicLoopSessionLoggerInterface.php` — проверить наследование целевого контракта; дублировать объявление метода без необходимости не следует.
  * `src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopService.php` — вызов `restoreFromRoundFiles($roundFiles)` и целевое подавление `argument.type` с поясняющим комментарием.
  * `src/Module/DynamicLoop/Domain/Entity/DynamicLoopExecution.php` — принимающий контракт `restoreFromRoundFiles(): list<array{...}>`; поведение восстановления не менять.
  * `src/Module/DynamicLoop/Infrastructure/Service/ChainSessionLogger.php`, `ChainSessionReader.php`, `ChainSessionWriter.php` — реализация возврата, PHPDoc полной формы записи и внутреннее хранение `$roundFiles[$step]`.
  * `tests/Integration/Application/UseCase/Command/OrchestrateChain/StubSessionLogger.php` и профильные Unit/Integration-тесты сессии и восстановления.
* **Текущее поведение:** доменный reader объявляет `@return array<int, array{is_facilitator: bool, ...}>`; `ChainSessionReader::getRoundFiles()` возвращает массив без переиндексации. `ChainSessionWriter` заполняет и восстанавливает его по ключу `$step`, включая нумерацию с `1`, поэтому фактический результат не всегда является `list`. `DynamicLoopExecution::restoreFromRoundFiles()` перебирает только значения и ожидает `list<array{...}>`; несоответствие сейчас скрыто `@phpstan-ignore argument.type`.
* **Прецеденты для переиспользования:** `tests/Unit/Domain/Entity/DynamicLoopExecutionRestoreTest.php` — проверка восстановления; `tests/Unit/Infrastructure/Service/Chain/ChainSessionWriterParticipantMetricsTest.php` — сценарии resume и текущие обращения к `$roundFiles[1]`, которые выявят смену ключей; `tests/Unit/Infrastructure/Service/Chain/ChainSessionLoggerResponsePathsTest.php` — контроль того, что внутренние step-ключи по-прежнему используются для response-путей.
* **Критичные конвенции:** [взаимодействие слоёв](../../docs/conventions/layers/layers.md), [Domain](../../docs/conventions/layers/domain.md), [Infrastructure](../../docs/conventions/layers/infrastructure.md), [тестирование](../../docs/conventions/testing/index.md). Domain остаётся независимым от Infrastructure; Infrastructure лишь реализует точный доменный контракт.
* **Границы (Out of Scope):** формат `session.json`, нумерация шагов и раундов, алгоритм восстановления и метрик, `getResponseFilePaths()`, прочие `@phpstan-ignore` и аудит `@psalm-suppress`, конфигурация `findUnusedPsalmSuppress`, новые DTO/VO и новые зависимости.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] **Т1:** контракт `list<array{...}>` с полной фактической array shape Writer/Reader объявлен в родительском `DynamicLoopSessionReaderInterface`; `DynamicLoopSessionLoggerInterface` наследует его и не переобъявляет `getRoundFiles()`. Форма совместима с параметром `DynamicLoopExecution::restoreFromRoundFiles()` без ослабления принимающего контракта до `mixed`.
- [ ] **Т1, Т4:** `ChainSessionLogger`, `ChainSessionReader` и `StubSessionLogger` имеют согласованный `@return list<...>`; `ChainSessionWriter::getRoundFiles()` и свойство `$roundFiles` сохраняют keyed-PHPDoc `array<int, array{...}>`. `ResumeStubSessionLogger` и мок-тесты Domain не изменяются, но профильные тесты Domain обязательно прогоняются.
- [ ] **Т2:** единственная нормализация — `array_values()` строго в `ChainSessionReader::getRoundFiles()`; дополнительных переиндексаций в Writer, Logger или Domain нет. Внутренний `$roundFiles[$step]`, запись `step` в `session.json` и фильтрация `getResponseFilePaths($upToStep)` не изменены.
- [ ] Порядок записей и содержимое каждой записи после преобразования сохранены; согласованное решением Q1 изменение числовых ключей `1..n` → `0..n-1` явно покрыто тестом.
- [ ] **Т3:** в `RunDynamicLoopService::initExecution()` удалены `/** @phpstan-ignore argument.type */` и устаревший `@techdebt`-блок; `@psalm-suppress ArgumentTypeCoercion` в итоговом diff не изменён. Новых подавлений и обходных `array_values()`/`@var` в Domain не добавлено.
- [ ] **Т5:** `ChainSessionWriterParticipantMetricsTest` адаптирован к публичным ключам (`$roundFiles[1]` → `$roundFiles[0]`); новый Unit-тест контракта проверяет пустую сессию, одну запись и непоследовательные step после resume: `array_is_list()`, порядок, равенство значений, сохранение `step` в `session.json` и фильтрацию `getResponseFilePaths()` по step. `DynamicLoopExecutionRestoreTest` не изменяется.
- [ ] Изменения оформлены отдельным PR от актуального `main`; задача перед merge переведена в `done` по регламенту.

### 🟡 Желательно (Should Have)
- [ ] В комментарии у границы переиндексации кратко зафиксировать, что step-ключи остаются внутренней деталью хранения, а публичный контракт возвращает упорядоченный список значений.
- [ ] Полная array shape повторяется инлайн в необходимых PHPDoc без type alias: такое дублирование по решению Q2 приемлемо для `C2`; `@psalm-type`/`@phpstan-type` в этом PR не вводить.

### 🟢 Опционально (Could Have)
- [ ] **Т7:** при реализации выполнить поиск упоминаний `getRoundFiles()` и числовых ключей его результата в `docs/`, `README*` и `SKILL.md`; обновить только найденные описания контракта. При отсутствии внешних потребителей changelog не требуется (решение Q3).

### ⚫ Не будем делать (Won't Have)
- [ ] Не менять поведение восстановления, метрик, порядок записей, формат файлов сессии и сохранение `step`.
- [ ] Не ослаблять `DynamicLoopExecution::restoreFromRoundFiles()` и не заменять целевой `list` на общий `array`/`iterable`.
- [ ] Не трогать остальные подавления PHPStan/Psalm; в частности, не удалять `@psalm-suppress ArgumentTypeCoercion` в итоговом diff. Его локальная диагностика по Т6 и общий аудит Psalm не расширяют этот PR; найденный смежный техдолг фиксировать отдельной задачей.
- [ ] Не вводить type alias (`@psalm-type`/`@phpstan-type`) и не менять `ResumeStubSessionLogger`, мок-тесты Domain и `DynamicLoopExecutionRestoreTest`.
- [ ] Не добавлять fallback, DTO/VO, зависимости, глобальные настройки анализаторов или рефакторинг смежных сервисов.
- [ ] Не выпускать изменение вместе с другой задачей: нужен отдельный PR Domain + Infrastructure.

## 4. План реализации (Implementation Plan)
1. [ ] Принять закрытые решения владельца как исходные условия DS: смена публичных ключей `1..n` → `0..n-1` допустима, единственный прод-потребитель `RunDynamicLoopService::initExecution()` использует только значения; повторное согласование Q1 не требуется. **Проверка:** реализация соответствует `list` и не содержит альтернативного общего `array<int, ...>` для публичного reader-контракта.
2. [ ] **Т1.** Обновить полную фактическую array shape Writer/Reader только в каноническом `DynamicLoopSessionReaderInterface::getRoundFiles()`; `DynamicLoopSessionLoggerInterface` оставить наследником без переобъявления. **Обоснование:** единый родительский контракт исключает расхождение reader- и logger-интерфейсов. Type alias не вводить, инлайн-дублирование полной формы допустимо для `C2`. **Проверка:** PHPStan и Psalm подтверждают совместимость объявлений и отсутствие противоречивого переобъявления.
3. [ ] **Т2.** Выполнить ровно один `array_values()` в `ChainSessionReader::getRoundFiles()`; `ChainSessionWriter::getRoundFiles()` и свойство `$roundFiles` оставить с keyed-PHPDoc `array<int, array{...}>`. **Обоснование:** нормализация только на границе чтения предотвращает регрессию `getResponseFilePaths()` и фильтрации по step. **Проверка:** diff и поиск подтверждают единственную точку нормализации; Unit-тесты подтверждают неизменные step в `session.json` и response-фильтрацию.
4. [ ] **Т4.** Синхронизировать `@return list<...>` в `StubSessionLogger`; явно не править `ResumeStubSessionLogger`, поскольку он наследует уже исправленный метод и не переобъявляет его. Мок-тесты Domain не менять, но обязательно прогнать. **Обоснование:** тестовый двойник должен соответствовать родительскому контракту без лишнего расширения diff. **Проверка:** Integration-тесты стабов и профильные Domain-тесты зелёные, в diff нет необоснованных изменений этих файлов.
5. [ ] **Т5.** Адаптировать обращения `$roundFiles[1]` → `$roundFiles[0]` в `ChainSessionWriterParticipantMetricsTest`. Добавить новый Unit-тест контракта для пустой сессии, одной записи и непоследовательных step после resume: проверить `array_is_list()`, порядок, равенство значений, сохранение `step` в `session.json` и фильтрацию `getResponseFilePaths()` по step. `DynamicLoopExecutionRestoreTest` не менять. **Обоснование:** тесты различают допустимую смену публичных ключей и недопустимую потерю данных или внутренних step. **Проверка:** названные Unit-тесты и существующие тесты восстановления/response-путей проходят.
6. [ ] **Т3.** Удалить целевое `@phpstan-ignore argument.type` и устаревший `@techdebt`-блок из `RunDynamicLoopService::initExecution()`; `@psalm-suppress ArgumentTypeCoercion` оставить в итоговом diff без изменений. **Обоснование:** устраняется только скрывающее несоответствие PHPStan-подавление, без расширения scope на Psalm. **Проверка:** PHPStan при `reportUnmatchedIgnoredErrors: true` не сообщает ни `argument.type`, ни `ignore.unmatchedIdentifier`; Psalm зелёный.
7. [ ] Обязательный чек-поинт DS/IM: полная фактическая форма Writer/Reader должна без подавлений assign'иться в принимающую `list<array{...}>` форму `DynamicLoopExecution::restoreFromRoundFiles()` на **обоих** анализаторах — Psalm и PHPStan. **Обоснование:** локальная совместимость только одного анализатора не подтверждает контракт. **Проверка:** оба прогона зелёные; при несовместимости остановиться и эскалировать владельцу этапа, не меняя молчаливо каноническую или принимающую форму.
8. [ ] **Т6.** Локально проверить необходимость `@psalm-suppress ArgumentTypeCoercion`: временно снять тег, прогнать Psalm и обязательно вернуть тег; результат внести в историю задачи. **Обоснование:** диагностика отделяет устаревшее подавление от необходимого, не включая смежный техдолг в PR. **Проверка:** итоговый diff не меняет этот тег, история содержит результат; если подавление лишнее, создать отдельную техдолг-задачу и не включать его удаление в этот PR.
9. [ ] **Т7 (Could Have).** Найти упоминания контракта в `docs/`, `README*` и `SKILL.md`; при наличии устаревших описаний обновить их. **Обоснование:** смена публичных ключей не должна оставить противоречивую документацию. **Проверка:** результат поиска зафиксирован; changelog при отсутствии внешних потребителей не добавляется.
10. [ ] Выполнить все команды из Verification, обновить критерии и историю задачи, оформить отдельный PR и пройти функциональное и архитектурное ревью по RACI для `refactor`.

## 5. Критерии приёмки (Definition of Done)
- [ ] **Т1:** `DynamicLoopSessionReaderInterface::getRoundFiles()` объявляет `list` с полной фактической array shape Writer/Reader; публичная цепочка `ChainSessionLogger` → `ChainSessionReader` возвращает тот же `list`. `DynamicLoopSessionLoggerInterface` только наследует контракт, type alias отсутствуют.
- [ ] **Т2:** в `ChainSessionReader::getRoundFiles()` находится единственный `array_values()` нормализации; `ChainSessionWriter::getRoundFiles()` и `$roundFiles` сохраняют keyed-PHPDoc `array<int, array{...}>`, внутренние step-ключи не переиндексированы.
- [ ] **Т5:** новый Unit-тест для пустой сессии, одной записи и непоследовательных step после resume подтверждает `array_is_list($result) === true`, исходный порядок и равенство значений; `ChainSessionWriterParticipantMetricsTest` использует новые публичные индексы `0..n-1`.
- [ ] Сценарий resume восстанавливает те же раунды, метрики и состояние, что до изменения; тесты подтверждают фильтрацию `getResponseFilePaths($upToStep)` по step и сохранение исходного `step` в `session.json`. `DynamicLoopExecutionRestoreTest` не изменён.
- [ ] **Т3:** целевое `@phpstan-ignore argument.type` и устаревший `@techdebt`-блок отсутствуют; `@psalm-suppress ArgumentTypeCoercion` в итоговом diff не изменён. `vendor/bin/phpstan analyse --no-progress` завершается с `No errors`, включая отсутствие несматченного подавления.
- [ ] Полная форма Writer/Reader совместима с `DynamicLoopExecution::restoreFromRoundFiles()` одновременно по результатам Psalm и PHPStan без новых подавлений; несовместимость, если возникла, эскалирована владельцу, а не скрыта правкой контракта.
- [ ] **Т4:** `StubSessionLogger` имеет согласованный `@return list<...>`; `ResumeStubSessionLogger` и мок-тесты Domain не изменены, соответствующие Integration/Domain-тесты прогнаны успешно.
- [ ] **Т6:** результат временной проверки необходимости `@psalm-suppress ArgumentTypeCoercion` записан в историю задачи, тег возвращён; при обнаружении лишнего подавления создана отдельная техдолг-задача без расширения PR.
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` успешны; полный `make check` успешен, регрессий в смежных модулях нет.
- [ ] **Т7 (Could Have):** если опциональное требование выполняется, поиск в `docs/`, `README*` и `SKILL.md` проведён, найденные упоминания контракта обновлены; при отсутствии внешних потребителей changelog не добавляется. Отдельный PR содержит только эту задачу.

## 6. Самопроверка (Verification)

Состояние после доработки постановки: код и тесты не изменялись, реализация не начата, задача предназначена для `backlog`. По Правилу A команды реализации ниже обязательны для исполнителя, но на этапе постановки не прогонялись; `make check` также не прогонялся. PHPUnit и Psalm не запускались при доработке файла по исключению для изменений только документации.

```bash
# Выполнено 2026-11-28 01:45 UTC: 0 error(s), 0 warning(s).
php vendor/bin/todo-md validate TASK-techdebt-getroundfiles-contract

# Не выполнено на этапе постановки (Правило A); обязательно при реализации.
vendor/bin/phpunit tests/Unit/Infrastructure/Service/Chain/
vendor/bin/phpunit tests/Unit/Domain/Entity/DynamicLoopExecutionRestoreTest.php
vendor/bin/phpunit tests/Unit/Domain/Service/Chain/Dynamic/
vendor/bin/phpunit tests/Integration/Application/UseCase/Command/OrchestrateChain/
vendor/bin/phpunit
vendor/bin/phpstan analyse --no-progress
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
make check
php vendor/bin/todo-md validate TASK-techdebt-getroundfiles-contract
```

## 7. Риски и зависимости (Risks and Dependencies)

Открытых вопросов нет: Q1–Q3 закрыты решениями владельца этапа (`A = Тимлид`) и не блокируют активацию задачи.

- **Q1 — закрыт:** смена публичных ключей `1..n` → `0..n-1` допустима. Единственный прод-потребитель `RunDynamicLoopService::initExecution()` ключи не использует. Контракт — `list` через единственный `array_values()` в `ChainSessionReader::getRoundFiles()`.
- **Q2 — закрыт:** каноническая форма — полная фактическая форма Writer/Reader. Type alias (`@psalm-type`/`@phpstan-type`) в этом PR не вводить; инлайн-дублирование форм приемлемо для `C2`. Совместимость полной формы с `restoreFromRoundFiles()` обязательно проверить на Psalm и PHPStan; при несовместимости — эскалация владельцу.
- **Q3 — закрыт требованием Т7:** при реализации ищутся упоминания контракта в `docs/`, `README*` и `SKILL.md`; changelog не требуется при отсутствии внешних потребителей.
- Риск ложного «refactor»: переиндексация меняет ключи возвращаемого массива, хотя восстановление по значениям остаётся прежним. Тест обязан различать неизменность бизнес-результата и намеренное изменение публичной формы контейнера.
- Риск регрессии response-путей: безусловная переиндексация внутреннего `$roundFiles` сломает сопоставление `$step <= $upToStep` и поле `step` в `session.json`; поэтому внутреннее хранение вне границ изменения.
- Зависимостей от задач и эпиков нет. Связанная завершённая задача — источник аудита, не `depends_on`.

## 8. Источники (Sources)
- [Задача-источник, раздел 9 «Комментарии»](../done/TASK-techdebt-phpstan-suppressions-audit.todo.md) — потенциальный техдолг по сужению контракта.
- [Отчёт ревью аудита подавлений](../../docs/agents/reports/code-reviewer-backend/2026-11-27_23-31_phpstan-suppressions-audit-review.md) — CR-2 (отдельная задача и переадресация техдолга) и CR-5 (не расширять scope на Psalm).
- [Руководство по созданию задач](../../docs/todo-md/AGENTS_TASK_WRITING_GUIDE.md) и [шаблон](../../docs/todo-md/templates/task.md).
- [RACI, раздел 5.3](../../docs/agents/raci-matrix.md) — автор отвечает за RF; DS, RV-F и RV-A обязательны при реализации.
- Код и тесты: пути, перечисленные в разделе 2; фактическое хранение по `$step` подтверждено в `ChainSessionWriter::logRound()` и `parseRoundFiles()`.

## 9. Комментарии (Comments)
- **Уточнение исходного запроса:** метод называется через `DynamicLoopSessionLoggerInterface`, но его PHPDoc фактически объявлен в родительском `DynamicLoopSessionReaderInterface`; задача намеренно включает каноническое объявление и всю цепочку реализации.
- **Согласованная точка нормализации:** `ChainSessionReader::getRoundFiles()`, а не Domain и не внутреннее хранение Writer. Решение Q1 закрыто; единственный `array_values()` обязателен именно на этой границе чтения.
- **Решение Q3:** поиск и возможная правка документации выполняются по Т7 как Could Have; запись в changelog не требуется при отсутствии внешних потребителей.
- **Приоритет:** остаётся `P3` по решению владельца этапа (`A = Тимлид`). Аргумент консультанта за `P2` учтён, но очередность не повышена: это локальный техдолг без наблюдаемого нарушения восстановления, назначенного релиза и дедлайна.
- Существующий `@psalm-suppress ArgumentTypeCoercion` в `RunDynamicLoopService` не является целью этого PR. По Т6 его необходимость проверяется только временным локальным снятием и прогоном Psalm с обязательным возвратом тега; результат фиксируется в истории. Если тег лишний, его удаление выносится в отдельную техдолг-задачу.
- Номера строк из исходного аудита (`RunDynamicLoopService`, примерно 113–120) являются снимком и могут сместиться; ориентир для реализации — сам вызов и тег, а не номер строки.

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-28 01:30:15 (1795829415) | Архитектор Гэндальф (pi) | Создание задачи |
| 2026-11-28 01:32:35 (1795829555) | Архитектор Гэндальф (pi) | Черновик постановки для backlog: уточнены канонический интерфейс, list-нормализация, границы Domain/Infrastructure, тесты и открытые вопросы Q1–Q3 |
| 2026-11-28 01:40:38 (1795830038) | Архитектор Гэндальф (pi) | Доработка по результатам консультации Бэкендера на этапе RF (RACI 5.3): интегрированы Т1–Т7; Q1–Q3 закрыты решениями владельца (A = Тимлид), приоритет P3 подтверждён |
