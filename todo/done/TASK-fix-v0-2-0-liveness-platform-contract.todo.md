---
type: fix
created: 2026-07-14
value: V4
complexity: C3
priority: P0
depends_on:
epic:
author: Аналитик (Шерлок)
assignee: Бэкендер (Левша)
branch: task/fix-v0-2-0-liveness-platform-contract
pr: https://github.com/prikotov/task-orchestrator/pull/308
status: done
---

# TASK-fix-v0-2-0-liveness-platform-contract: Зафиксировать безопасный платформенный контракт liveness

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

`ProcessLivenessWatcher` (наблюдатель активности процесса) сейчас не отличает корректное значение метрики `0`
от ситуации, когда метрику получить невозможно. Неуспешные вызовы `ps`, `pgrep` и чтение `/proc` нормализуются
в `0` или пустой список, после чего код всё равно считает отсутствие роста доказанным простоем. В результате активный
процесс может быть остановлен по `idle timeout` (тайм-ауту простоя), хотя сломалась или недоступна только телеметрия.

Дефект уже воспроизводится на Linux (Линукс) в официальном минимальном образе `php:8.4.1-cli`: в образе нет
`ps` и `pgrep`, поэтому тихий CPU-bound process (процесс, занятый вычислениями без вывода) длительностью 3 секунды
при `AGENT_RUNNER_IDLE_TIMEOUT_SEC=1` текущая реализация ошибочно завершает примерно за 1,5 секунды с
`waitFor() === false` и exit code (кодом завершения) `143`.

На macOS (Дарвин) команда `ps -o times=` не соответствует системному `ps`: Apple публикует поля `time` и
`cputime`, но не `times`; `/proc/<pid>/io` на macOS отсутствует. На обычном Windows (Виндоус) нет ни POSIX-команд
`ps`/`pgrep`, ни Linux procfs (виртуальной файловой системы `/proc`). На этих платформах текущие пробы также
схлопываются в нули и способны ложно остановить корректно работающего агента после порога простоя.

### Варианты или путь решения (Solution Sketch)

Оставить `ProcessLivenessWatcher` policy service (сервисом политики ожидания), а чтение ОС вынести в отдельный
[`Infrastructure Component`](../docs/conventions/core_patterns/component.md) с обязательным интерфейсом. Компонент
возвращает typed immutable snapshot/result (типизированный неизменяемый снимок/результат) со строгими внутренними
состояниями `ACTIVE`, `INACTIVE`, `UNKNOWN`. Linux procfs implementation (реализация Linux procfs) читает
монотонные CPU/IO-счётчики и direct children (непосредственные дочерние процессы) без внешних команд;
`Unavailable` implementation (реализация недоступной пробы) выбирается для остальных платформ через явно
внедрённое platform family (семейство ОС).

Состояние `UNKNOWN` навсегда отключает idle-kill для текущего `waitFor()`, сбрасывает baseline (базовую выборку),
но не отключает `Process::checkTimeout()`. Неожиданные исключения продолжают распространяться наружу, а оба runner
(раннера) обязаны синхронно остановить ещё живой agent process (процесс агента) в `finally`, не дожидаясь GC
(сборщика мусора).

### Ожидаемый результат (Expected Result)

Для `v0.2.0` адаптивное завершение по подтверждённому простою работает на Linux с доступным procfs и не зависит
от необъявленных системных пакетов `procps`. Если достоверная телеметрия недоступна, наблюдатель переходит в
`hard-cap-only` mode (режим только абсолютного тайм-аута): не останавливает процесс по предположению, но продолжает
вызывать Symfony `Process::checkTimeout()`. Неожиданные ошибки программирования или конфигурации не скрываются и
распространяются наружу по `fail-fast` contract (контракту немедленного отказа).

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)

> Когда оркестратор ждёт длительный процесс агента на разных операционных системах или в минимальном контейнере,
> я хочу, чтобы процесс завершался по idle timeout только при достоверно подтверждённом простое, а неисправность
> телеметрии не маскировалась под отсутствие активности, чтобы не получать ложные retry (повторы) и потерю результата.

### Goal (Цель по SMART)

До выпуска `v0.2.0` изменить платформенное поведение Infrastructure Service (инфраструктурного сервиса)
[`ProcessLivenessWatcher`](../docs/conventions/core_patterns/service.md) в соответствии с
[`Infrastructure` convention (конвенцией инфраструктурного слоя)](../docs/conventions/layers/infrastructure.md):

- отделить `available but idle` (доступная метрика без роста) от `unavailable` (метрика недоступна);
- отделить OS I/O (ввод-вывод ОС) в Infrastructure Component с интерфейсом и неизменяемыми DTO;
- оставить `ProcessLivenessWatcher` владельцем только loop policy (политики цикла), таймеров и решения об остановке;
- оставить адаптивный idle-kill (остановку по простою) только там, где пробы достоверны;
- сохранить абсолютный hard cap (жёсткий предел времени) на всех платформах;
- сохранить распространение неожиданных ошибок без `catch (\Throwable)`;
- гарантировать немедленный cleanup (очистку) процесса агента в Pi/Codex runner при ошибке пробы;
- подтвердить контракт автоматическими тестами и Linux smoke check (проверкой запуска) в минимальном
  `php:8.4.1-cli` без `ps`/`pgrep`, `pcntl` и runtime-установки `procps`.

Задача считается выполненной, когда все сценарии из разделов 3 и 5 проходят, а полный PHPUnit и Psalm зелёные.

## 2. Context and Scope (Контекст и Границы)

### 2.1. Где делаем

- `src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php` — основной runtime contract
  (контракт исполнения) и policy service. Он не читает procfs и не исполняет внешние команды.
- Новый `ProcessLivenessProbeComponentInterface` (рабочее имя интерфейса компонента) и две реализации в
  `src/Module/AgentRunner/Infrastructure/Component/`: Linux procfs и `Unavailable`. Имена/namespace должны
  соответствовать [`Component convention`](../docs/conventions/core_patterns/component.md).
- Typed immutable probe snapshot/result оформляются как `final readonly` Infrastructure
  [`DTO`](../docs/conventions/core_patterns/dto.md); фиксированное состояние `ACTIVE`/`INACTIVE`/`UNKNOWN` — как
  Infrastructure [`Enum`](../docs/conventions/core_patterns/enum.md) либо эквивалентный строго типизированный
  закрытый контракт без строковых magic values (магических значений).
- Явный platform-family provider/selector (поставщик/селектор семейства ОС) в composition root (точке сборки):
  Linux implementation и `Unavailable` implementation выбираются по внедрённому значению, а не через скрытое
  чтение глобального состояния внутри watcher/component.
- `src/Module/AgentRunner/Infrastructure/Service/Pi/PiAgentRunnerService.php` и
  `src/Module/AgentRunner/Infrastructure/Service/Codex/CodexAgentRunnerService.php` — минимальная гарантия cleanup
  процесса агента в `finally` при любом неожиданном исключении пробы без изменения публичного runner contract.
- Unit tests (модульные тесты) отдельно проверяют policy с fake clock/sleeper/probe (поддельными часами,
  ожиданием и пробой), без запуска реальных процессов. Real-process scenarios (сценарии с реальными процессами)
  располагаются в Integration tests (интеграционных тестах) и Linux smoke check по
  [`Testing convention`](../docs/conventions/testing/index.md).
- PHPDoc `ProcessLivenessWatcher` — краткая матрица платформ и политика ошибок.

### 2.2. Наблюдаемое текущее поведение

1. `readSingleCpuTime()` выполняет Unix shell command (команду оболочки)
   `ps -o times= -p <pid> 2>/dev/null`; отсутствие команды, ошибка поля или нечисловой вывод превращаются в `0`.
2. `readSingleIo()` превращает недоступный `/proc/<pid>/io` или ошибку чтения в `0`.
3. `childPids()` превращает отсутствие `pgrep`, ошибку команды и корректное отсутствие дочерних процессов в один
   результат `[]`; причины не различаются.
4. `waitFor()` после этих результатов всё равно сравнивает счётчики и по истечении порога вызывает
   `Process::stop(2)`. Следовательно, `telemetry unavailable` (телеметрия недоступна) интерпретируется как
   `confirmed idle` (подтверждённый простой).
5. Сумма метрик текущего PID и его direct children (непосредственных дочерних процессов) не является монотонной:
   когда ребёнок завершается, его накопленные счётчики исчезают из суммы. Простое сравнение агрегатов может не
   заметить следующего активного ребёнка. Изменение набора PID должно считаться активностью или приводить к
   безопасному сбросу baseline (базовой выборки).
6. Внешние `shell_exec()` probes (пробы) не имеют собственного timeout (тайм-аута): зависшая системная команда
   способна заблокировать и idle policy, и регулярную проверку hard cap. Контракт закрывает этот класс ошибок
   полным удалением внешних команд из liveness path (пути контроля активности).
7. `PiAgentRunnerService` и `CodexAgentRunnerService` в `finally` сейчас останавливают только proxy bridge
   (прокси-мост). При `Error`/другом неожиданном исключении из пробы агентский `Process` остаётся жив до вызова
   Symfony destructor (деструктора) сборщиком мусора; детерминированной немедленной гарантии cleanup нет.

### 2.3. Исторический контекст и `catch (\Throwable)`

- PR #301 выделил общую liveness-логику в `ProcessLivenessWatcher`.
- PR #302 добавил учёт direct children для устранения ложного idle-kill во время tool calls (вызовов инструментов).
- В промежуточном commit (коммите) `a565242` PR #304 приватные обёртки временно ловили `\Throwable` и возвращали
  fallback values (резервные значения). Пользователь верно указал, что это скрывает ошибки.
- Commit `bd6e00c` до merge (слияния) PR #304 удалил все такие catch-блоки. В текущем `main` broad catch
  (широкого перехвата) уже нет, а тест
  `waitForPropagatesUnexpectedThrowableFromLivenessProbeAndRestoresErrorHandler` проверяет распространение `Error`
  и восстановление предыдущего error handler (обработчика ошибок).

Эта задача **не должна повторно «исправлять» уже удалённый catch**. Она обязана сохранить достигнутый fail-fast
инвариант и устранить другой дефект: смешение ожидаемой недоступности платформенной пробы с корректным нулём.

### 2.4. Целевая архитектура и внутренний state contract (контракт состояний)

#### Разделение ответственности

1. **`ProcessLivenessWatcher` остаётся policy service (сервисом политики).** Он владеет циклом ожидания,
   `lastActivity`, baseline, idle threshold, вызовами `Process::checkTimeout()`, решением `stop()` и возвратом
   `true/false`. В нём нет `shell_exec`, procfs parsing (разбора procfs) и выбора команды ОС.
2. **Отдельный Infrastructure Component (инфраструктурный компонент)** за один вызов получает снимок активности
   PID и возвращает строго типизированный результат через обязательный `*ComponentInterface`. Минимально нужны:
   Linux procfs implementation и `Unavailable` implementation.
3. **Выбор реализации явный.** Значение platform family внедряется из composition root/DI (точки сборки/контейнера).
   Ни watcher, ни тестируемая policy не определяют ОС скрытым выполнением команды; Linux выбирает procfs component,
   Darwin/Windows/BSD/Solaris/Unknown — `Unavailable` component.
4. **Snapshot/result неизменяемы.** Данные счётчиков, отсортированный набор PID и следующее состояние baseline
   переносятся `final readonly` DTO без файлового I/O и логики. Result содержит typed state
   (типизированное состояние) и следующий snapshot либо явное отсутствие snapshot.
5. **Время и ожидание внедряются.** Policy получает fakeable clock/sleeper contracts (подменяемые часы/ожидание),
   чтобы Unit tests не запускали процессы и не зависели от `microtime()`/`usleep()` реального времени.

#### Внутренние состояния

| State (состояние) | Строгое значение | Действие policy |
|---|---|---|
| `ACTIVE` | Полная валидная выборка сопоставима с baseline и хотя бы один счётчик вырос; либо изменился набор PID; либо это первая полная выборка для нового baseline | Обновить `lastActivity`, сохранить новый snapshot |
| `INACTIVE` | Полная валидная выборка сопоставима с baseline, набор PID тот же, ни один счётчик не вырос | Сохранить snapshot; при превышении idle threshold разрешён `stop()` |
| `UNKNOWN` | Платформа не поддерживает пробу или хотя бы одна обязательная часть снимка недоступна/недостоверна | Немедленно очистить baseline, навсегда отключить idle-kill в текущем `waitFor()`, продолжать hard-cap loop |

Переход из `UNKNOWN` обратно в adaptive mode в пределах того же `waitFor()` запрещён: поздняя «успешная» выборка
не восстанавливает доказательность уже разорванной последовательности. Новый вызов `waitFor()` начинает независимый
цикл и может снова выбрать доступную пробу.

#### Детерминированный PID race contract (контракт гонок PID)

- Если процесс завершился между первым `isRunning()` и `getPid()`, повторная проверка показывает
  `isRunning() === false`: `waitFor()` возвращает `true`, `stop()` не вызывается, probe component не вызывается.
- Если `getPid() === null`, но повторная проверка всё ещё показывает живой процесс, это `UNKNOWN`: baseline
  очищается, текущий цикл навсегда становится hard-cap-only.
- Если основной PID завершился во время чтения procfs, watcher повторно проверяет `isRunning()`: мёртвый процесс
  означает нормальное завершение `true` без `stop()`; всё ещё живой процесс при недостоверном снимке означает
  `UNKNOWN`.
- Исчезновение direct child между чтением `children` и его `stat/io` не кодируется нулём и не бросает случайную
  parsing error (ошибку разбора): результат выборки становится `UNKNOWN`, если невозможно построить полный
  сопоставимый snapshot. Текущая глубина остаётся ровно один уровень direct children.

### 2.5. Платформенный контракт `v0.2.0`

| Platform (платформа) | Статус `v0.2.0` | Idle liveness (контроль простоя) | Обязательное поведение |
|---|---|---|---|
| Linux с читаемым procfs | Release-supported (поддерживается выпуском) | Полный: основной PID + direct children, CPU/IO | Без зависимости от `ps`, `pgrep` или пакета `procps`; подтверждённый idle может остановить процесс |
| Linux без полной/читаемой procfs-телеметрии | Degraded but safe (ограниченно, но безопасно) | Отключён для текущего ожидания | Перейти в hard-cap-only mode, не принимать `0` за доказательство простоя |
| macOS / `PHP_OS_FAMILY=Darwin` | Runtime-supported (исполнение поддерживается) | В `v0.2.0` не гарантируется, hard-cap-only | Не выполнять Linux/Unix-пробы и не завершать процесс по недоказанному idle |
| Windows | Best-effort (без гарантий CI) согласно distribution RFC | Hard-cap-only | Не выполнять POSIX-команды и не читать `/proc`; оставить Symfony hard cap |
| BSD / Solaris / Unknown | Compatibility fallback (режим совместимости) | Hard-cap-only | Безопасно ждать до завершения или hard cap |

Полная адаптивная телеметрия macOS/Windows не является условием выпуска `v0.2.0`. Выбран приоритет безопасности:
ложно **не** распознать зависание и дождаться hard cap лучше, чем уничтожить активный процесс и потерять результат.

### 2.6. Fail-open / fail-closed / fail-fast contract (контракт отказов)

Термины в этой задаче используются строго в следующем смысле:

- **Fail-open для liveness:** `UNKNOWN` навсегда отключает idle-kill для текущего `waitFor()`, очищает baseline,
  но цикл продолжает регулярно вызывать `Process::checkTimeout()` до естественного завершения или hard cap.
- **Fail-closed только для подтверждённого idle:** `Process::stop(2)` и `waitFor() === false` допустимы только после
  последовательности валидных сопоставимых samples (выборок), которые не показывают активности дольше порога.
- **Fail-fast для неожиданных ошибок:** `Error`, `TypeError`, `LogicException`, `RuntimeException` и иной
  неожиданный `Throwable` не
  преобразуются в `0`, `[]`, `null`, `false` или успешный результат; они распространяются наружу согласно
  [`Exception convention`](../docs/conventions/core_patterns/exception.md). Предыдущий PHP error handler всегда
  восстанавливается через `finally`, если временный handler вообще остаётся в реализации.

Pi/Codex runner не ловят и не нормализуют исходный probe Throwable (исключение пробы), но в своём `finally`
синхронно вызывают `stop()` для всё ещё живого agent `Process`. После выхода из `run()` PID должен быть мёртв сразу,
без ожидания деструктора/GC; cleanup не должен подменять identity/message/trace (идентичность/сообщение/трассировку)
исходной ошибки.

`ProcessTimedOutException` от `Process::checkTimeout()` не является ошибкой пробы: она распространяется без подмены
в `ProcessLivenessWatcher`, а Pi/Codex runner (раннеры Pi/Codex) сохраняют существующий маппинг в timed-out result
(результат тайм-аута).

### 2.7. Классификация ожидаемых ситуаций

| Ситуация | Классификация | Результат |
|---|---|---|
| Platform family (семейство ОС) не Linux | Поддерживаемое отсутствие adaptive probe | Hard-cap-only с первой итерации |
| `/proc` не смонтирован, закрыт политикой `hidepid` или обязательный файл нельзя прочитать | Probe unavailable (проба недоступна) | Hard-cap-only для текущего ожидания |
| `/proc/<pid>/stat`, `io` или `children` обрезан, malformed (испорчен), содержит пропущенное/нечисловое поле либо число больше `PHP_INT_MAX` | `UNKNOWN`, а не числовой `0`/переполнение | Очистить baseline; hard-cap-only; не делать idle-kill |
| Procfs read (чтение procfs) даёт warning из-за permission/race (прав/гонки) при всё ещё живом основном PID | Ожидаемая недоступность выборки | `UNKNOWN`; warning не превращается в неожиданный broad catch |
| У процесса корректно нет direct children | Валидный пустой набор | Продолжить сравнение метрик основного PID |
| Ребёнок появился или завершился между samples | Валидное изменение topology (топологии) | Считать активностью/сбросить baseline; не сравнивать несопоставимые агрегаты |
| Ребёнок завершился между перечислением PID и чтением его файла | Ожидаемая race condition (гонка) | Не считать нулём; неполная выборка → `UNKNOWN` |
| Процесс завершился между `isRunning()` и `getPid()` | Нормальная PID race | Повторно подтвердить смерть, вернуть `true`, никогда не вызывать `stop()` |
| Живой `Process` временно вернул `pid=null` | Недоказуемая liveness | `UNKNOWN`; hard-cap-only, без `stop()` по idle |
| `Process::checkTimeout()` достиг hard cap | Ожидаемый абсолютный тайм-аут | Распространить `ProcessTimedOutException` |
| Компонент неожиданно бросил `Error`/`TypeError`/`LogicException`/`RuntimeException` | Programming/configuration error (ошибка кода/конфигурации) | Остановить живой agent Process в runner `finally`; распространить тот же объект ошибки с trace |

### 2.8. Out of Scope (Вне задачи)

- Полная adaptive liveness implementation (реализация адаптивной активности) для macOS или Windows.
- Добавление macOS/Windows CI matrix (матрицы CI); RFC отдельно фиксирует отсутствие Windows CI до `v1.0`.
- Рекурсивный учёт всех descendants (потомков) глубже direct children; текущая граница одного уровня сохраняется.
- Изменение process-group/job-object termination (завершения группы процессов) поверх поведения Symfony
  `Process::stop()`. Минимальный `finally` cleanup живого agent Process в обоих runner входит в задачу.
- Изменение публичных контрактов Pi/Codex runner, `AgentResultVo`, JSONL или текстов ошибок раннеров; внутренний
  `finally` cleanup публичный результат не меняет.
- Изменение значений/семантики `AGENT_RUNNER_IDLE_TIMEOUT_SEC` и `AGENT_RUNNER_HARD_TIMEOUT_SEC`.
- Переименование `ProcessLivenessWatcher` или общий архитектурный рефакторинг AgentRunner за пределами обязательного
  Component interface, typed DTO/state, platform selection и deterministic cleanup (детерминированной очистки).
- Публичная compatibility matrix (матрица совместимости) во всех README/guide — это отдельная утверждённая задача
  `TASK-docs-v0-2-0-compatibility-and-operations`; здесь обновляется только PHPDoc затронутого runtime-кода.
- Live calls (живые вызовы) внешних AI-провайдеров: корректность должна подтверждаться локальными процессами без
  сети и секретов.
- Создание дополнительных todo-задач: все перечисленные обязательные изменения атомарно входят в эту задачу.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [ ] Создать отдельный Infrastructure `ProcessLivenessProbe*ComponentInterface` и внедрять его в
  `ProcessLivenessWatcher`; watcher остаётся policy service и не содержит platform I/O (платформенного ввода-вывода).
- [ ] Реализовать минимум две реализации интерфейса: Linux procfs component и `Unavailable` component. Выбор
  выполняется по явно внедрённому platform family из composition root; скрытый вызов `PHP_OS_FAMILY` внутри policy,
  эвристика по доступности команды или guessed default (предположенное значение) запрещены.
- [ ] Ввести typed immutable `final readonly` snapshot/result DTO и typed state со значениями ровно
  `ACTIVE`, `INACTIVE`, `UNKNOWN`; недоступность не кодируется обычными `0`, `[]` или `null`, неотличимыми от
  валидного результата.
- [ ] Реализовать state semantics (семантику состояний) раздела 2.4: первая полная выборка/смена PID topology —
  `ACTIVE`, сопоставимая выборка без роста — `INACTIVE`, любая неполная/недостоверная выборка — `UNKNOWN`.
- [ ] После первого `UNKNOWN` немедленно очистить baseline и навсегда отключить idle-kill только для текущего
  `waitFor()`; последующие успешные samples не включают adaptive mode обратно.
- [ ] Полностью удалить из liveness production/test path внешние `shell_exec`, `ps`, `pgrep`, POSIX redirection и
  зависимость от `procps`. Это одновременно устраняет риск зависшей команды без timeout.
- [ ] Linux procfs component читает CPU/IO основного PID и direct children ровно одного уровня через
  `/proc/<pid>/stat`, `/proc/<pid>/io`, `/proc/<pid>/task/<pid>/children`; `pcntl` не используется ни в runtime,
  ни для создания тестовых детей.
- [ ] Linux reader (читатель Linux) строго обрабатывает edge cases: `stat` с пробелами/скобками в process name
  (имени процесса), обрезанные/лишние поля, отсутствующие `rchar`/`wchar`, пустой валидный `children`, malformed
  child PID, permission denied, файл исчез между проверкой/чтением, parent/child race и счётчик больше
  `PHP_INT_MAX`. Невалидное/переполненное значение не кастуется и даёт `UNKNOWN`.
- [ ] Сравнивать метрики по идентичному отсортированному набору PID, а не только по общей сумме. Появление/исчезновение
  PID считается `ACTIVE` и создаёт новый baseline; recursive descendants (рекурсивные потомки) не добавляются.
- [ ] Реализовать PID race contract раздела 2.4: смерть между `isRunning()`/`getPid()` возвращает `true` без
  `stop()`; живой процесс с `pid=null` переводит ожидание в `UNKNOWN`/hard-cap-only.
- [ ] Во всех `UNKNOWN`/unsupported режимах на каждой итерации регулярно вызывать `Process::checkTimeout()`;
  отсутствие adaptive liveness никогда не отключает hard cap.
- [ ] Сохранить публичную семантику `waitFor(Process): bool`: `true` только при самостоятельном завершении,
  `false` только после подтверждённого `INACTIVE` дольше порога и фактической остановки процесса.
- [ ] `Error`, `TypeError`, `LogicException`, `RuntimeException` из probe component распространяются как исходный
  объект без нормализации. Не добавлять `catch (\Throwable)`/catch базовых PHP exception; временный error handler,
  если он останется, обязательно восстанавливается в `finally`.
- [ ] В обоих `PiAgentRunnerService` и `CodexAgentRunnerService` добавить минимальный agent-process cleanup в
  `finally`: если probe Throwable покидает watcher, ещё живой `Process` синхронно останавливается до выхода из
  `run()`. Исходный Throwable не заменяется; PID мёртв сразу, без ожидания Symfony destructor/GC.
- [ ] Сохранить без изменения `resolveHardCap()` и `getIdleThreshold()` за пределами необходимой адаптации тестов.
- [ ] Обновить PHPDoc `ProcessLivenessWatcher`: Linux adaptive mode, non-Linux hard-cap-only fallback и три режима
  отказа (fail-open/fail-closed/fail-fast) описаны без заявления неподтверждённой поддержки.
- [ ] Unit tests policy (модульные тесты политики) используют fake probe/clock/sleeper и Process test double
  (тестовый двойник), не запускают реальные процессы, не вызывают private methods и не анализируют исходный текст.
- [ ] Реальные CPU-only/direct-child/PID-cleanup scenarios находятся в Integration tests/Linux smoke, используют
  `proc_open`/Symfony Process вместо `pcntl` и запускаются с `--fail-on-skipped`.
- [ ] Exact Linux smoke (точная Linux-проверка) проходит в `php:8.4.1-cli` без установки `procps`: CPU-only parent
  и parent с активным direct child переживают idle threshold и завершаются успешно; пропуск любого теста валит smoke.

### 🟡 Should Have (Желательно)

- [ ] Разместить component DTO/state рядом с Infrastructure Component и не выносить чисто техническую модель в
  Domain/Application.
- [ ] Сохранить production poll interval (интервал опроса), но передавать его policy через тестируемый sleeper
  contract без реального ожидания в Unit tests.
- [ ] Сохранить отсутствие заметного роста CPU/IO самого оркестратора и не перечитывать неизменяемые platform
  capabilities на каждой итерации.

### 🟢 Could Have (Опционально)

- [ ] Однократная debug/warning telemetry (диагностическая запись) о переходе в hard-cap-only mode, если её можно
  добавить через уже существующий logging contract (контракт журналирования) без изменения публичных контрактов.

### ⚫ Won't Have (Не будем делать)

- [ ] Не считать отсутствие stdout/stderr само по себе доказательством зависания: агент может корректно выполнять
  длительное вычисление или ждать внешний ответ без вывода.
- [ ] Не оставлять и не добавлять ни одной fallback command (резервной команды) для liveness: external-command
  probing (проверка внешней командой) удаляется полностью, а не получает timeout-обёртку.
- [ ] Не подавлять неожиданные ошибки ради продолжения adaptive idle-kill.
- [ ] Не добавлять `pcntl`, системные зависимости `procps`, PowerShell scripts (скрипты PowerShell) или сторонние
  библиотеки.
- [ ] Не расширять cleanup дальше синхронного `Process::stop()` в `finally`: process groups/job objects и recursive
  descendants остаются вне задачи.

## 4. Implementation Plan (План реализации)

1. [ ] Выполнить reverse briefing (обратное подтверждение) по обязательной архитектуре: watcher-policy,
   Component interface, Linux/Unavailable implementations, immutable DTO/state, injected platform family,
   fake clock/sleeper и runner cleanup.
2. [ ] Написать deterministic Unit tests (детерминированные модульные тесты) policy с fake component,
   clock/sleeper и Process double: `ACTIVE`, `INACTIVE`, permanent `UNKNOWN`, hard cap в каждом режиме,
   PID races и запрет `stop()` при естественном завершении. Убедиться, что регрессии падают на старом коде.
3. [ ] Создать Infrastructure Component contract и `final readonly` snapshot/result DTO + typed state; настроить DI
   так, чтобы platform family передавался явно и выбирал Linux procfs/Unavailable implementations.
4. [ ] Реализовать Linux procfs reader без внешних commands/`pcntl`, включая строгий парсинг `stat`, `io`,
   `children`, overflow detection (обнаружение переполнения), permissions и races. Покрыть parser/component Unit tests
   синтетическими fixtures (фикстурами), не реальными процессами.
5. [ ] Перевести `ProcessLivenessWatcher` на Component result и state machine; удалить из него `shell_exec`, `ps`,
   `pgrep`, файловый parsing и глобальное определение платформы. При `UNKNOWN` очистить baseline и навсегда оставить
   текущий wait в hard-cap-only.
6. [ ] Реализовать детерминированные `isRunning()`/`getPid()` race branches и сравнение per-PID snapshot
   (снимка по PID) со сбросом baseline при topology change.
7. [ ] Добавить в `finally` обоих Pi/Codex runner остановку ещё живого agent Process. Добавить regression tests,
   где probe бросает `Error`, `TypeError`, `LogicException`, `RuntimeException`: исходная ошибка выходит наружу,
   PID уже мёртв без GC.
8. [ ] Перенести все real-process tests из Unit bucket (набора модульных тестов) в Integration bucket; заменить
   `pcntl_fork` на `proc_open`/Symfony Process и покрыть CPU-only parent, idle parent, active direct child,
   последовательную смену детей и Linux race cases.
9. [ ] Обновить PHPDoc и запустить exact `php:8.4.1-cli` smoke без `procps`, с `--fail-on-skipped`; подтвердить
   отсутствие `ps`/`pgrep`, успех CPU-only и direct-child scenarios и регулярный hard cap при `UNKNOWN`.
10. [ ] Выполнить целевые PHPUnit, полный PHPUnit, Psalm, `make check`, todo validator и `git diff --check`;
    зафиксировать команды/результаты в PR без создания новых задач.

## 5. Definition of Done (Критерии приёмки)

### 5.1. Architecture contract (архитектурный контракт)

- [ ] `ProcessLivenessWatcher` зависит от отдельного Infrastructure Component interface и содержит только policy:
  цикл, baseline/state, clock/sleeper, hard cap и решение об остановке.
- [ ] Есть Linux procfs и `Unavailable` implementations; выбор подтверждён тестами явной инъекции platform family
  для Linux, Darwin, Windows и Unknown без подмены глобальной ОС.
- [ ] Snapshot/result — `final readonly` typed DTO; состояния `ACTIVE`, `INACTIVE`, `UNKNOWN` нельзя представить
  произвольной строкой или двусмысленным `null/0/[]`.
- [ ] В liveness production path отсутствуют `shell_exec`, `ps`, `pgrep`, POSIX redirection, `pcntl` и runtime
  dependency на `procps`; риск тайм-аута внешней probe command закрыт удалением команд.

### 5.2. Deterministic policy Unit tests (детерминированные модульные тесты политики)

- [ ] Unit tests watcher используют fake probe/clock/sleeper и Process double; они не стартуют OS process
  (процесс ОС), не спят по реальному времени и не зависят от текущей платформы CI.
- [ ] `ACTIVE` обновляет `lastActivity`/baseline; сопоставимый `INACTIVE` после порога вызывает ровно один `stop()`
  и возвращает `false`; topology change считается `ACTIVE`.
- [ ] Первый `UNKNOWN` очищает baseline и необратимо переводит только текущий `waitFor()` в hard-cap-only; даже
  последующие `ACTIVE` results не включают idle-kill обратно.
- [ ] В каждом цикле `UNKNOWN`, включая Darwin/Windows/Unknown, `checkTimeout()` вызывается регулярно; hard-cap
  `ProcessTimedOutException` не превращается в idle result.
- [ ] Exit между `isRunning()` и `getPid()` возвращает `true` и оставляет `stop()` невызванным; live `pid=null`
  даёт permanent `UNKNOWN`; смерть во время probe также возвращает `true` без `stop()`.
- [ ] Unit bucket не содержит прежних real-process/`pcntl_fork` scenarios; они перенесены в Integration.

### 5.3. Linux procfs Component tests (тесты компонента Linux procfs)

- [ ] Валидные fixtures подтверждают CPU/IO основного PID, валидный пустой `children`, direct-child snapshot и
  одинаковый отсортированный PID set (набор PID).
- [ ] Отдельными тестами покрыты: process name со скобками/пробелами в `stat`, truncated/malformed `stat`,
  отсутствующий или malformed `rchar/wchar`, malformed/overflow child PID, counter overflow больше `PHP_INT_MAX`,
  permission/read failure и исчезновение parent/child file во время выборки.
- [ ] Невалидный/переполненный counter не кастуется в `int`; неполная выборка возвращает `UNKNOWN`, а не нулевую
  активность. Корректный пустой список детей остаётся валидным состоянием.
- [ ] Появление/исчезновение direct child создаёт новый baseline/`ACTIVE`; descendants глубже одного уровня не
  читаются и тестом зафиксированы как out of scope.

### 5.4. Real-process Integration and exact Linux smoke (интеграция и точная Linux-проверка)

- [ ] Тихий CPU-only process работает дольше малого idle threshold и завершается самостоятельно:
  `waitFor() === true`, exit code `0`.
- [ ] Действительно idle process без активности дольше порога останавливается: `waitFor() === false`, процесс больше
  не запущен.
- [ ] Родитель с активным direct child и последовательностью короткоживущих direct children переживает idle
  threshold и завершается успешно; fixtures используют `proc_open`/Symfony Process, не `pcntl`.
- [ ] Exact smoke запускает эти Linux scenarios в неизменённом `php:8.4.1-cli`, где отсутствуют `ps`, `pgrep` и
  `pcntl`; `procps` не устанавливается ни до, ни во время проверки.
- [ ] Smoke и целевой Integration PHPUnit запущены с `--fail-on-skipped`; skipped/incomplete/risky test делает
  проверку красной. В PR зафиксированы exact command (точная команда), image tag и итог.

### 5.5. Probe error cleanup in both runners (очистка при ошибке пробы)

- [ ] Для каждого из `Error`, `TypeError`, `LogicException`, `RuntimeException` тест подтверждает fail-fast:
  Pi/Codex runner распространяет тот же объект/сообщение/trace, не создаёт `AgentResultVo` fallback.
- [ ] В обоих runner `finally` ещё живой agent `Process` остановлен до выхода из `run()`; Integration test проверяет
  PID сразу после перехвата исключения и не запускает GC для получения зелёного результата.
- [ ] Cleanup не меняет обычные success, confirmed-idle, hard-cap и `ProcessSignaledException` paths; proxy bridge
  cleanup остаётся гарантированным.
- [ ] Ни один новый catch-блок не ловит `\Throwable`, базовый `\RuntimeException` или другой базовый PHP exception;
  предыдущий error handler восстанавливается, если реализация его временно устанавливает.

### 5.6. Quality gates (проверки качества)

- [ ] Целевые Unit/Integration PHPUnit проходят с `--fail-on-skipped` и без flaky timing (нестабильности времени).
- [ ] Полный `vendor/bin/phpunit` проходит.
- [ ] `vendor/bin/psalm` завершается с `0 errors`.
- [ ] `make check` и `git diff --check` проходят.
- [ ] PHPDoc отражает фактическую, а не предполагаемую platform support (поддержку платформ).

## 6. Verification (Самопроверка)

```bash
vendor/bin/phpunit --fail-on-skipped \
  tests/Unit/Infrastructure/Service/AgentRunner/ProcessLivenessWatcherTest.php \
  tests/Unit/Infrastructure/Component/AgentRunner/
vendor/bin/phpunit --fail-on-skipped \
  tests/Integration/Infrastructure/Service/AgentRunner/
vendor/bin/phpunit
vendor/bin/psalm
TMPDIR=/tmp/task-orchestrator-v0-2-0-liveness-check make check
php vendor/prikotov/todo-md/bin/todo-md-validate todo/TASK-fix-v0-2-0-liveness-platform-contract.todo.md
git diff --check
```

Обязательный exact Linux smoke выполняется без `composer install`, установки extensions (расширений) или системных
пакетов внутри контейнера; используется уже проверенный workspace (рабочий каталог) и read-only mount
(монтирование только для чтения):

```bash
docker run --rm \
  --entrypoint sh \
  --volume "$PWD:/app:ro" \
  --workdir /app \
  php:8.4.1-cli \
  -lc '
    set -eu
    test "$(php -r "echo PHP_OS_FAMILY;")" = Linux
    ! command -v ps
    ! command -v pgrep
    ! php -r "exit(function_exists(\"pcntl_fork\") ? 0 : 1);"
    php vendor/bin/phpunit \
      --do-not-cache-result \
      --fail-on-skipped \
      --fail-on-incomplete \
      --fail-on-risky \
      tests/Integration/Infrastructure/Service/AgentRunner/ProcessLivenessWatcherLinuxIntegrationTest.php
  '
```

Exact path (точный путь) Integration test можно уточнить при реализации, но PR обязан привести фактически
выполненную команду и доказать пять фактов:

1. `PHP_OS_FAMILY=Linux`;
2. `ps` отсутствует;
3. `pgrep` отсутствует;
4. `pcntl_fork` отсутствует, а тесты не пропущены;
5. CPU-only parent и parent с активным direct child живут дольше idle threshold и завершаются с
   `waitFor() === true`.

## 7. Risks and Dependencies (Риски и зависимости)

- **Осознанный trade-off (компромисс):** на macOS/Windows и при недоступной procfs зависший процесс ждёт hard cap
  (по умолчанию до 1800 секунд), зато активный процесс не уничтожается на основании отсутствующей телеметрии.
- **Procfs permissions (права procfs):** контейнерные политики `hidepid`, namespace (пространство имён) и гонки
  завершения PID могут сделать часть файлов недоступной. Контракт требует безопасной деградации, а не ложного `0`.
- **Procfs parsing (разбор procfs):** `/proc/<pid>/stat` содержит process name в скобках и позиционные поля;
  наивный `preg_split` ломается на пробелах/скобках. `io`/`children` допускают большие decimal counters (десятичные
  счётчики); unchecked cast (непроверенное приведение) способен переполниться. Эти случаи блокируют acceptance.
- **Child topology (топология дочерних процессов):** агрегат меняется немонотонно; сравнение только общей суммы без
  идентичности PID снова создаст false idle.
- **Component selection (выбор компонента):** неверный DI alias (псевдоним DI) может включить Linux reader на
  Darwin/Windows. Platform family должен быть явно инъецирован и покрыт тестами всех веток.
- **Exception cleanup (очистка при исключении):** вызов `stop()` в `finally` обязан убить живой PID, но не должен
  заменить исходный probe Throwable собственной ошибкой cleanup; тест проверяет identity исходного исключения.
- **Testing split (разделение тестов):** policy Unit tests обязаны использовать fake clock/sleeper и не могут
  маскировать race реальными задержками; только Integration/smoke используют процессы и ограниченный временной запас.
- **False-green smoke (ложнозелёная проверка):** platform-dependent test может быть незаметно skipped. Поэтому exact
  container command обязательно использует `--fail-on-skipped` и дополнительно запрещает incomplete/risky results.
- **External-command hang (зависание внешней команды):** оборачивать `ps/pgrep` тайм-аутом недостаточно и запрещено;
  риск считается закрытым только после полного удаления command probes из production/tests.
- **Platform claims (заявления поддержки):** публичные README будут синхронизированы отдельной задачей
  `TASK-docs-v0-2-0-compatibility-and-operations`; до неё нельзя заявлять full adaptive support для Darwin/Windows.
- **Release dependency (зависимость выпуска):** задача блокирует финальную `TASK-release-v0-2-0-preparation`, но не
  зависит от незавершённых функциональных PR и может выполняться независимо от них.

## 8. Sources (Источники)

### Repository sources (Источники репозитория)

- [`ProcessLivenessWatcher.php`](../src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php).
- [`ProcessLivenessWatcherTest.php`](../tests/Unit/Infrastructure/Service/AgentRunner/ProcessLivenessWatcherTest.php).
- [CLI distribution RFC (RFC дистрибуции CLI)](../docs/research/rfc/cli-distribution-rfc.md) — Composer full,
  PHAR best-effort, отсутствие Windows CI до `v1.0`.
- PR #301: https://github.com/prikotov/task-orchestrator/pull/301
- PR #302: https://github.com/prikotov/task-orchestrator/pull/302
- PR #304: https://github.com/prikotov/task-orchestrator/pull/304
- Commit с временным broad catch: `a565242a4b01e176a9adf903cf6e506faf29df08`.
- Commit с удалением broad catch: `bd6e00cdc616f3c347e30670cbb60b7644cf567d`.

### External primary sources (Внешние первичные источники)

- [PHP predefined constants](https://www.php.net/manual/en/reserved.constants.php) — допустимые значения
  `PHP_OS_FAMILY`.
- [Linux kernel procfs documentation](https://docs.kernel.org/filesystems/proc.html) — `/proc/<pid>/io` и
  `/proc/<pid>/task/<tid>/children`, включая семантику первого уровня и гонок.
- [Apple `ps` manual source](https://github.com/apple-oss-distributions/adv_cmds/blob/main/ps/ps.1) и
  [Apple `ps` keyword table](https://github.com/apple-oss-distributions/adv_cmds/blob/main/ps/keyword.c) — поле
  `time`/alias `cputime`; поля `times` нет.
- [Apple `pgrep` manual source](https://github.com/apple-oss-distributions/adv_cmds/blob/main/pkill/pkill.1) —
  `-P ppid` существует на macOS, но это не делает Linux-only набор проб переносимым из-за `ps times` и `/proc`.
- [Symfony Process documentation](https://symfony.com/doc/current/components/process.html) — portability
  (переносимость) `Process`, absolute timeout (абсолютный тайм-аут), idle timeout по выводу и `stop()`.

## 9. Comments (Комментарии)

### Проверенные факты анализа

- Текущий `main` на commit `194854f3bebd01eec7a9c0b15c7de687d7c089e2` не содержит `catch (\Throwable)`
  в `ProcessLivenessWatcher`.
- Локальный `vendor/symfony/process` подтверждает: `checkTimeout()` останавливает процесс и бросает
  `ProcessTimedOutException`; на Windows `Process::stop()` использует `taskkill /F /T`, но Windows adaptive probe
  проектом не тестируется и в scope задачи не входит.
- В локально доступном официальном image (образе) `php:8.4.1-cli`: `PHP_OS_FAMILY=Linux`, `ps=MISSING`,
  `pgrep=MISSING`, `/proc/self/io` читается.
- На текущем коде в этом image тихий CPU-bound process с плановым временем 3 секунды и idle threshold 1 секунда
  воспроизвёл дефект: `completed=false`, `successful=false`, elapsed `1.51s`, exit code `143`.

## Change History (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-14 | Аналитик (Шерлок) | Создана P0-постановка для выпуска `v0.2.0`: зафиксированы воспроизводимый platform bug, матрица Linux/macOS/Windows, fail-open/fail-closed/fail-fast contract, тесты, DoD и границы задачи. |
| 2026-07-14 | Аналитик (Шерлок) | По архитектурному и QA-аудиту complexity повышена до C3; Component interface + Linux/Unavailable implementations, immutable snapshot/result, `ACTIVE/INACTIVE/UNKNOWN`, permanent UNKNOWN, PID races, procfs edge cases, fake clock/sleeper Unit policy, exact `php:8.4.1-cli --fail-on-skipped` smoke и немедленный Pi/Codex process cleanup при probe Throwable переведены в обязательный контракт. |
