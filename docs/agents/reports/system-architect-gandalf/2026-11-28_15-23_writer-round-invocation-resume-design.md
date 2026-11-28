# Проектирование восстановления invocation записи раунда после resume

**Роль:** Архитектор Гэндальф
**Дата:** 2026-11-28
**Объект:** `ChainSessionWriter::parseRoundFiles()`, поток `resume` → `getRoundFiles()` → восстановление/повторная запись сессии; все инлайн-описания формы записи раунда
**Задача:** [TASK-techdebt-writer-round-shape-drift](../../../../todo/done/TASK-techdebt-writer-round-shape-drift.todo.md), этап DS по RACI 5.3, шаги 1–2 плана

---

## 1. Решение DS

**Восстанавливать `invocation` в `ChainSessionWriter::parseRoundFiles()`.** В `@return` метода добавить недостающее `invocation?: string` в конец канонической формы; в теле метода копировать сохранённое строковое значение из `$rd['invocation']` только при его наличии.

`DynamicLoopSessionReaderInterface` не меняется: его каноническая закрытая 18-полевая форма уже содержит `invocation?: string`. Обосновывать отсутствие поля после `resume` не требуется и не следует: это закрепило бы потерю ранее сохранённых данных при цикле «чтение — изменение состояния — повторная запись».

Решение относится только к полю `rounds[*].invocation`. Одноимённое верхнеуровневое поле `session.json` (`invocation`) и метод `logInvocation()` — отдельный поток данных, который данное решение не меняет.

## 2. Потоки данных и потребители после resume

### 2.1. Восстановление записи

1. `DynamicExecutionStrategy::resume()` вызывает `ChainSessionLogger::resumeSession()`.
2. `ChainSessionWriter::resumeSession()` передаёт `rounds` из `session.json` в `parseRoundFiles()` и сохраняет результат во внутреннем `$roundFiles`.
3. `ChainSessionLogger::getRoundFiles()` передаёт записи через `ChainSessionReader::getRoundFiles()`; `array_values()` меняет только ключи списка, значения записей сохраняет.
4. Единственный прод-потребитель публичного `getRoundFiles()` — `RunDynamicLoopService::initExecution()`: при `$startRound > 0` он передаёт список в `DynamicLoopExecution::restoreFromRoundFiles()`.

Поиск по `src/`, `apps/` и `tests/` других прод-вызовов публичного `getRoundFiles()` не выявил. Тестовые вызовы находятся в `ChainSessionWriterParticipantMetricsTest` и `ChainSessionReaderRoundFilesContractTest`.

### 2.2. Использование invocation потребителями записи

| Потребитель | Что читает | Влияние восстановления `invocation` |
| :--- | :--- | :--- |
| `RunDynamicLoopService::initExecution()` | Только передаёт записи дальше | Нет прямого доступа к `invocation`; сигнатура и управление не меняются |
| `DynamicLoopExecution::restoreFromRoundFiles()` | `round`, `role`, `is_facilitator`, метрики, модельные поля и кэш-токены | Поле не читается; метрики, `roleCosts`, счётчики раундов и шагов не меняются. Метод остаётся без изменений по границам задачи |
| `ChainSessionReader::getResponseFilePaths()` | `step`-ключи, `is_facilitator`, `response`, `role` | Поле не читается; `step`-ключирование и фильтрация путей не меняются |
| `ChainSessionReader::getRoundFiles()` | Запись целиком как значение | Дополнительный ключ сохраняется при `array_values()`; порядок и список не меняются |
| `ChainSessionWriter::writeSessionJson()` | Записи `$roundFiles`, включая условно `invocation` | Восстановленное значение сохраняется при следующей записи; сейчас оно удаляется, поскольку отсутствует во внутреннем состоянии |

Прямых прод-чтений `$round['invocation']` нет. Это не делает потерю допустимой: запись раунда является ещё и persisted state (сохранённым состоянием), которое Writer повторно сериализует.

### 2.3. Последствия текущего отсутствия поля (OBS-1)

- **Подтверждённая потеря при повторной записи.** `DynamicExecutionStrategy::resume()` после `resumeSession()` вызывает `setBudget()`, затем `logInvocation()`. Оба метода при активной сессии вызывают `writeSessionJson()`. Поскольку `parseRoundFiles()` не вернул `invocation`, условие `isset($files['invocation'])` ложно и прежние `rounds[*].invocation` исчезают из `session.json` уже в начале возобновления.
- **Асимметрия двух потоков полной формы.** Свежий `logRound()` сохраняет строку, если аргумент не `null`; восстановленный `parseRoundFiles()` её отбрасывает. После `resume` Reader формально возвращает допустимую, но более узкую запись без сохранённого значения.
- **Доменный результат не восстанавливает invocation отдельно.** `restoreFromRoundFiles()` создаёт `DynamicRoundResultVo` без аргумента `invocation`, поэтому его значение остаётся `null`. Настоящее решение намеренно не расширяется на это поведение: метод и DTO-маппинг вне границ задачи; `ReportJsonMapper` поле `invocation` в JSON-отчёт не выводит.
- **Метрики и orchestration (оркестрация) сейчас не зависят от поля.** Его восстановление не меняет стоимость, токены, модельные поля, выбор следующей роли или условие завершения.

## 3. Сверка всех инлайн-копий с канонической формой

Канон: `DynamicLoopSessionReaderInterface::getRoundFiles()` — 18 полей: 14 обязательных и четыре опциональных (`response?`, `error?`, `error_message?`, `invocation?`). Сверено содержимое каждой `array{system: ...}` как строка, без нормализации.

SHA-256 канонической формы: `962df0944d5cb85887fcf086480642353e684a69a5d55f89ca95d4f4f0253bb2`.

| № | Объявление | Полей | Результат |
| ---: | :--- | ---: | :--- |
| 1 | `DynamicLoopSessionReaderInterface.php:32`, `@return getRoundFiles()` | 18 | Байт-в-байт канон |
| 2 | `ChainSessionReader.php:19`, `@param getResponseFilePaths()` | 18 | Байт-в-байт канон |
| 3 | `ChainSessionReader.php:52`, `@param getRoundFiles()` | 18 | Байт-в-байт канон |
| 4 | `ChainSessionReader.php:54`, `@return getRoundFiles()` | 18 | Байт-в-байт канон |
| 5 | `ChainSessionLogger.php:167`, `@return getRoundFiles()` | 18 | Байт-в-байт канон |
| 6 | `ChainSessionWriter.php:27`, `@var $roundFiles` | 18 | Байт-в-байт канон |
| 7 | `ChainSessionWriter.php:239`, `@return getRoundFiles()` | 18 | Байт-в-байт канон |
| 8 | `StubSessionLogger.php:124`, `@return getRoundFiles()` | 18 | Байт-в-байт канон |
| 9 | `ChainSessionWriter.php:264`, `@return parseRoundFiles()` | 17 | **Единственный дрейф:** отсутствует `invocation?`, дополнительных полей нет |

Итог подтверждает ревью PR #523: **восемь канонических копий идентичны**, дрейф ровно один. Всего сейчас девять объявлений полной формы, включая дрейфующее. После реализации решения все девять должны быть идентичны канону; открытая принимающая форма `DynamicLoopExecution::restoreFromRoundFiles()` в этот список не входит и не меняется.

SHA-256 текущей 17-полевой формы: `9e60512c7a6b5dfa77ad8db18a99beb1b590236ba777aec0eeca04c776039370`.

## 4. Проектная инструкция для реализации

Изменения ограничить `src/Module/DynamicLoop/Infrastructure/Service/ChainSessionWriter.php` и целевым тестом:

1. В `@return parseRoundFiles()` добавить `invocation?: string` после `error_message?: string`, скопировав каноническую форму без перестановки остальных полей.
2. После существующих условных блоков `response`, `error`, `error_message` добавить условное восстановление:
   - условие: значение существует и `is_string($rd['invocation'])`;
   - действие: `$roundFile['invocation'] = $rd['invocation'];`.
3. Не вводить `''` или иное значение по умолчанию. Для старой записи без поля ключ остаётся отсутствующим; пустая строка, если она была сохранена штатным `logRound()`, восстанавливается как строка.
4. Не менять `writeSessionJson()`: существующий условный блок уже сериализует восстановленное значение.
5. Не менять Reader-интерфейс, `getRoundFiles()`, `logRound()`, формат `session.json`, `restoreFromRoundFiles()`, метрики, `step`-ключирование и подавления `RunDynamicLoopService`.

Проверка типов здесь необходима, поскольку `$roundsData` объявлен как `list<array<string, mixed>>`; она одновременно защищает канонический `invocation?: string` и не требует подавлений Psalm/PHPStan.

## 5. Требуемые тесты реализации

Добавить целевой roundtrip-тест (круговой тест «запись — чтение»), предпочтительно в `tests/Unit/Infrastructure/Service/Chain/ChainSessionReaderRoundFilesContractTest.php` рядом с существующим resume-контрактом:

1. Записать раунд с непустым `invocation` через `logRound()` и зафиксировать состояние.
2. Создать новый `ChainSessionLogger`, выполнить `resumeSession()`.
3. Проверить `getRoundFiles()[0]['invocation']` — исходная строка восстановлена.
4. Вызвать `updateSessionState()` либо иной существующий триггер записи без добавления нового раунда.
5. Повторно декодировать `session.json` и проверить `rounds[0]['invocation']` — значение пережило повторную запись.

Существующий сценарий старой сессии без модельных полей должен остаться зелёным; при желании усилить его `assertArrayNotHasKey('invocation', $round)`, чтобы явно закрепить обратную совместимость опционального поля. Отдельно прогнать `tests/Unit/Infrastructure/Service/Chain/`, resume-сценарии, Psalm, PHPStan и полный PHPUnit по плану задачи.

## 6. Отклонённая альтернатива и риски

### Обосновать отсутствие invocation в Reader — отклонено

- Поле уже присутствует в каноне и сохраняется в файле; отсутствие после `resume` — дефект полноты восстановления, а не осознанное ограничение потребителя.
- Отсутствие текущего доменного читателя не оправдывает удаление persisted state при следующей записи.
- Документированное расхождение сохранило бы разные фактические формы для свежей и восстановленной записи и поддерживало бы именно тот тихий дрейф, который задача устраняет.

### Остаточные риски и ограничения

- Решение устраняет потерю в записи Writer/Reader, но **не** переносит `invocation` в восстановленные `DynamicRoundResultVo`; это отдельное поведение, запрещённое к изменению текущими границами.
- Как и прежде, повреждённое нестроковое значение из вручную изменённого `session.json` не попадёт в типизированную запись. Штатный Writer такие значения не создаёт; новый fallback (резервный вариант) не вводится.
- Инлайн-дублирование остаётся осознанным решением предыдущего DS. После правки нужно повторить побайтовую сверку всех девяти объявлений; псевдонимы типов и DTO/VO не вводить.

## 7. Допущения, блокеры и проверки этапа

- Допущение: источником истины для строки `invocation` является штатный `logRound(?string $invocation)`; отсутствие ключа и `null` означают отсутствие данных.
- Блокеров нет. Решение принято владельцем этапа DS (Архитектор, RACI 5.3); дополнительного согласования не требуется.
- Код и тесты на этапе DS не изменялись. Выполнены поиск потребителей и механическая побайтовая сверка форм; PHPUnit, Psalm и PHPStan не запускались, поскольку изменения ограничены проектной документацией и файлом задачи.
- `php vendor/bin/todo-md validate TASK-techdebt-writer-round-shape-drift`: 0 ошибок, 0 предупреждений. Точечная проверка ссылок в задаче и настоящем отчёте: все внутренние ссылки валидны.
- Полный `make md-links` выявил одну предсуществующую битую ссылку в `docs/agents/team-retro/2026-11-28_11-27-getroundfiles-contract-task.md:65` на `todo/backlog/TASK-techdebt-writer-round-shape-drift.todo.md`, хотя задача находится в активном `todo/`. Этот сторонний архивный документ в рамках DS не изменялся.
- `make validate-language`: 20 предсуществующих предупреждений в других документах; предупреждений по настоящему отчёту и задаче нет.
- Внешние источники не использовались; основание — кодовая база и отчёты ревью PR #523: `docs/agents/reports/system-architect/2026-11-28_10-39_getroundfiles-contract-arch-review.md` (Л-4) и `docs/agents/reports/code-reviewer-backend/2026-11-28_10-19_getroundfiles-contract-review.md` (OBS-1).
