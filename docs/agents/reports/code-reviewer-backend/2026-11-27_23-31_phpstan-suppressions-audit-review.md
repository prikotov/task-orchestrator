# Полное ревью ветки task/phpstan-suppressions-audit (аудит подавлений PHPStan)

**Роль:** Ревьювер Бэка Пуаро (code_reviewer_backend_puaro)
**Дата:** 2026-11-27
**Объект:** незакоммиченные изменения ветки `task/phpstan-suppressions-audit` (база `3df4c65d`), 9 файлов:
`phpstan.neon.dist` (+1/−1), `src/Module/AgentRunner/Infrastructure/Service/Codex/HttpsProxyBridge.php` (+20/−1),
`src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php` (+2/−2),
`src/Module/ChainExecution/Application/Mapper/ReportJsonMapper.php` (−1),
`src/Module/DynamicLoop/Domain/Entity/DynamicLoopExecution.php` (+2/−2),
`src/Module/DynamicLoop/Domain/Service/Dynamic/FinalizeDynamicLoopService.php` (+1/−1),
`src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopService.php` (+7/−0),
`todo/TASK-techdebt-phpstan-suppressions-audit.todo.md` (+27/−18), `.make-check.json` (маркер `make check`)
**Задача:** [todo/TASK-techdebt-phpstan-suppressions-audit.todo.md](../../../../todo/TASK-techdebt-phpstan-suppressions-audit.todo.md)
**Тип прохода:** полное ревью — полнота против постановки, конвенции подавлений, отсутствие изменений поведения, риск переключения `reportUnmatchedIgnoredErrors`, консистентность файла задачи, стиль и язык комментариев
**Вердикт:** 🟡 **REQUEST CHANGES** — 1 обязательное замечание (**CR-1**: расхождение файла задачи с фактическим состоянием кода, правится одной строкой) и 4 рекомендации (**CR-2**…**CR-5**).

**Актуальный статус (2026-11-27 23:41):** ✅ **APPROVE** — CR-1, CR-3 и CR-5 закрыты по результатам верификационного прохода, см. [§11](#11-верификационное-ревью).

По существу работа выполнена корректно: все 4 «мёртвых» подавления сняты, 4 живых перепроверены и оформлены, поведение кода не изменено ни в одном байте логики, `reportUnmatchedIgnoredErrors: true` держит анализ зелёным, все заявленные в задаче числа воспроизводятся. Блокирует приёмку только внутреннее противоречие в тексте задачи (и связанная с ним потеря живого прецедента в комментарии) — не код.

---

## 0. Контекст и метод

Метод: статическое сопоставление diff с постановкой + независимое воспроизведение всех заявленных фактов (прогоны анализаторов, инвентаризация подавлений на базе и в рабочем дереве, контрольный эксперимент на копии файла в `/tmp`). **Рабочее дерево ревьювером не изменялось**: правок кода, коммитов и повторной генерации маркера нет; единственный созданный артефакт — этот отчёт.

| Эксперимент | Цель | Результат |
|--|--|--|
| `vendor/bin/phpstan analyse` на текущем `phpstan.neon.dist` (`reportUnmatchedIgnoredErrors: true`) | проверить заявленный зелёный анализ и отсутствие несматченных подавлений | ✅ `[OK] No errors` — все 4 оставшихся `@phpstan-ignore` **сматчены** (при `true` любой несматченный подавлял бы прогон) |
| `git grep 'phpstan-ignore'` на базе `3df4c65d` | проверить заявленный исходный инвентарь «8 подавлений» | ✅ ровно 8 строк с `@phpstan-ignore` в `src/` |
| `rg 'phpstan-ignore' src/` в рабочем дереве | проверить итоговый инвентарь «4 живых» | ✅ ровно 4: `HttpsProxyBridge.php:53`, `HttpsProxyBridge.php:195`, `ProcessLivenessWatcher.php:102`, `RunDynamicLoopService.php:120` |
| `vendor/bin/psalm --no-progress` | проверить, что правки PHPDoc не сломали Psalm | ✅ `No errors found!` (163 info-замечания — предсуществующие, не ошибки) |
| Копия `src/` в `/tmp/puaro-probe` + сужение `@var resource\|false\|null` → `@var resource\|null` | проверить обоснование подавления `property.unusedType` (заявлено «сужение ломает Psalm») | ✅ заявление подтверждено: 3 ошибки Psalm — `DocblockTypeContradiction` на составном условии в `isRunning()` и 2× `RedundantConditionGivenDocblockType` на проверке в `killProcess()` |
| Трассировка `start()` в `HttpsProxyBridge` | проверить тезис «ветка `false` до присвоения бросает `RuntimeException`» | ✅ корректно: `$this->process = $process;` стоит **после** `if ($process === false) { throw … }` — тип `resource\|false\|null` действительно содержит неиспользуемую ветку `false` |
| Сверка `getRoundFiles()` ↔ `restoreFromRoundFiles()` | проверить обоснование подавления `argument.type` | ✅ обоснование точное: интерфейс объявляет `@return array<int, array{is_facilitator: bool, ...}>`, метод ожидает `@param list<array{round?: int, …}>` — форма не выводится статически |
| `vendor/bin/phpunit` | проверить заявленные числа DoD | ✅ точно совпало: `Tests: 1782, Assertions: 5380, Skipped: 2` |
| Цели `deptrac`, `phpmd`, `phpcs` (6 изменённых файлов), `md-links`, `validate-todo` (файл задачи) | проверить заявленный зелёный `make check` по частям | ✅ все зелёные (детали — [§8](#8-фактические-результаты-проверок)) |

Полный `make check` ревьювером не дублировался целиком: прогнаны все цели по отдельности (кроме `coding-standard-verify`/`coding-standard-di-check` — это бинарники, не цели Makefile) и полный набор тестов. Причина отказа от единого прогона: `make check` перезаписывает маркер `.make-check.json` — это изменило бы проверяемое рабочее дерево.

---

## 1. Полнота реализации против постановки

| Требование постановки | Факт | Статус |
|--|--|--|
| Снять 4 подтверждённых «мёртвых» подавления (`nullsafe.neverNull` ×3, `return.type` ×1) | удалены ровно 4 строки: `ReportJsonMapper.php:55` (`return.type`), `DynamicLoopExecution.php:297`, `:298`, `FinalizeDynamicLoopService.php:95` (`nullsafe.neverNull`) | ✅ |
| Перепроверить 4 живых подавления: необходимость, идентификатор, оформление | все 4 сматчены (см. §0), идентификаторы точны; у трёх добавлены причина и `@techdebt` (`property.unusedType`, `nullCoalesce.offset`, `argument.type`), у `booleanNot.alwaysFalse` причина была | ✅ (частность — **CR-1**) |
| Принять и зафиксировать решение по `reportUnmatchedIgnoredErrors` | `true` выставлен в [phpstan.neon.dist:11](../../../../phpstan.neon.dist), решение записано в §3 (Must Have), §4 (план, п. 4) и §5 (DoD) файла задачи | ✅ |
| Ни одного «мёртвого» подавления в `src/` | при `true` несматченных нет — прогон зелёный | ✅ |
| `vendor/bin/phpstan analyse` и полный `make check` зелёные | PHPStan — `[OK] No errors`; Psalm/Deptrac/PHPMD/PHPCS/PHPUnit — зелёные (§8) | ✅ |
| «Не менять проверяемый код» (вне границ — правки, меняющие поведение) | diff содержит только PHPDoc, комментарии, конфиг-флаг и файл задачи; ни одной изменённой инструкции (см. §3) | ✅ |

Отклонений по поведению, scope и критериям приёмки нет. Единственная несогласованность — тексты, а не реализация.

---

## 2. Соблюдение конвенций по подавлениям

Правила: [docs/guide/review-functional.md](../../../guide/review-functional.md) (строка 25 и чек-лист) — «причина + техдолг-задача»; [docs/conventions/ops/phpmd-suppressions-guidelines.md](../../../conventions/ops/phpmd-suppressions-guidelines.md) — общие правила подавлений (причина в терминах контракта, локальность, связь с `todo/TASK-*`).

| Подавление | Причина (в терминах контракта) | Идентификатор точен | `@techdebt` + дата | Локальность | Итог |
|--|--|--|--|--|--|
| `HttpsProxyBridge.php:53` `property.unusedType` | расхождение анализаторов: сужение PHPDoc ломает Psalm | ✅ | ✅ `:51` | поле класса | ✅ |
| `HttpsProxyBridge.php:195` `nullCoalesce.offset` | защитная ветка `?? false` против `TypeError` | ✅ | ✅ `:193` | выражение | ✅ |
| `ProcessLivenessWatcher.php:102` `booleanNot.alwaysFalse` | повторный опрос внешнего OS-состояния | ✅ | ✅ `:100` | условие | ✅ |
| `RunDynamicLoopService.php:120` `argument.type` | слабый контракт `getRoundFiles()` ↔ `list<array{…}>` | ✅ | ✅ `:118` | вызов | ✅ |

Что подтвердилось отдельно:

- Причины сформулированы через контракт/ограничение анализатора, а не «чтобы прошёл линтер» — конвенция соблюдена.
- Оба «защитных» обоснования проверены по коду: снятие `??` в `HttpsProxyBridge.php:195` действительно даёт `TypeError` в методе с типом возврата `bool` (обращение к offset у `false` даёт `null` + warning), а `false` в `@var` у свойства действительно недостижимо.
- `@phpstan-ignore` расположен последним тегом PHPDoc-блока свойства (`HttpsProxyBridge.php:39-54`) — то есть привязан к объявлению; при `reportUnmatchedIgnoredErrors: true` сматчен (проверено прогоном).
- Правило «подавление описывается в терминах домена» выполнено: комментарии объясняют, **что именно** заглушается и почему сужение/снятие недопустимо сейчас.
- Комментарии не дублируют идентификаторы «всухую»: рядом с каждым `@phpstan-ignore` есть объяснение, за что он отвечает.

---

## 3. Отсутствие изменений поведения

Проверено построчно: из diff убраны все строки-комментарии и обёртки PR; остаются только:

```
.make-check.json                   1/1   — маркер make check (артефакт, см. CR-4)
phpstan.neon.dist                  1/1   — reportUnmatchedIgnoredErrors: false → true
HttpsProxyBridge.php              20/1   — PHPDoc свойства + комментарий у return (1 удалена — тег @var в старой строке)
ProcessLivenessWatcher.php         2/2   — текст комментария @techdebt
ReportJsonMapper.php               0/1   — удалён мёртвый /** @phpstan-ignore return.type */
DynamicLoopExecution.php           2/2   — удалены хвостовые @phpstan-ignore в двух строках
FinalizeDynamicLoopService.php     1/1   — то же в одной строке
RunDynamicLoopService.php          7/0   — комментарий перед вызовом
todo/…audit.todo.md               27/18  — файл задачи
```

Ни одной изменённой инструкции, сигнатуры, типа, значения по умолчанию, порядка аргументов или конфигурации рантайма (флаг PHPStan — dev-конфиг анализатора, в рантайм не попадает). Тесты не правились и не требуются: изменения чисто документационно-конфигурационные (исключение 2 из раздела «Предварительные проверки» AGENTS.md), тем не менее полный набор тестов прогнан — зелёный.

Выражения, из которых сняты подавления (`?->isBudgetExceeded() ?? false`, `?->getBudgetLimit() ?? 0.0`, `json_encode(..., JSON_THROW_ON_ERROR)`), остались байт-в-байт: удалены только хвостовые комментарии и один тэг-комментарий перед `return`.

---

## 4. Решение `reportUnmatchedIgnoredErrors: true` — оценка рисков

**Оценка: решение верное, риски приемлемые, остаточных блокеров нет.**

| Контур | Влияние | Оценка |
|--|--|--|
| CI | `.github/workflows/ci.yml` **не запускает** PHPStan/Psalm: джобы — PHPUnit, `liveness-smoke`, PHAR-smoke, `composer-host-smoke` и `local-check` (проверка маркера). Нового источника красного CI флаг не создаёт | 🟢 риск отсутствует |
| Локальный гейт разработчика | любое новое `@phpstan-ignore`, которое не срабатывает, теперь валит `make check` сообщением `ignore.unmatchedIdentifier`. Это и есть цель: «мёртвые» подавления больше не накапливаются молча | 🟢 целевое поведение |
| Обновления PHPStan | смена семантики идентификаторов (прецедент — 2.3.7 и `booleanNot.alwaysFalse`) может «внезапно» сделать живое подавление мёртвым и уронить локальный прогон. Диагностика ранняя и однозначная, лечение — удалить/уточнить подавление; риск зафиксирован в §7 файла задачи | 🟡 управляемый (регламент: прогонять `make check` при обновлении анализатора) |
| Baseline / `ignoreErrors` | в проекте нет `phpstan-baseline.neon`, секции `ignoreErrors` в конфиге нет — рассинхронизировать нечего, кроме 4 инлайн-подавлений | 🟢 риск отсутствует |
| Потребители пакета | `phpstan.neon.dist` — dev-конфиг самого репозитория; на анализ в проектах-потребителях не влияет, обратная совместимость не затрагивается | 🟢 риск отсутствует |
| Хрупкость подавления в PHPDoc | если при рефакторинге блока `HttpsProxyBridge.php:39-54` тег `@phpstan-ignore` перестанет быть привязан к объявлению свойства, прогон упадёт заметно, а не «молча» | 🟢 приемлемо |

Единственное, чего не хватает для будущих разработчиков, — «рецепта» на случай провала: сообщение PHPStan однозначно, но регламент («удалить мёртвое подавление либо уточнить идентификатор; при обновлении PHPStan перепроверить 4 подавления») живёт только в §7 файла задачи, который после закрытия уедет в `todo/done/`. Это рекомендация **CR-5**, не блокер: `docs/guide/troubleshooting.md` сейчас PHPStan вообще не упоминает, так что «сломать нечего».

---

## 5. Консистентность файла задачи

| Утверждение в файле задачи | Проверка | Итог |
|--|--|--|
| `phpstan.neon.dist:11` — место флага | строка 11, позиция не сдвинулась | ✅ |
| «полный инвентарь — 8 подавлений» | `git grep` на базе `3df4c65d` → 8 | ✅ |
| Адреса «мёртвых» (снимок до аудита): `ReportJsonMapper.php:55` (срабатывание на `:56`), `DynamicLoopExecution.php:297`, `:298`, `FinalizeDynamicLoopService.php:95` | совпадают с базой; пояснение §9 про разницу «строка подавления ↔ строка срабатывания» корректно и объясняет расхождение с отчётом-источником (там `:56`) | ✅ |
| Адреса «живых» в Must Have: `HttpsProxyBridge.php:41`, `:176`, `ProcessLivenessWatcher.php:102`, `RunDynamicLoopService.php:113` | совпадают с базой | ✅ |
| Адреса после аудита (§9): `HttpsProxyBridge.php:53`, `:195`, `ProcessLivenessWatcher.php:102`, `RunDynamicLoopService.php:120` | совпадают с рабочим деревом (проверено `rg -n`) | ✅ |
| «8 → 4 живых» | 8 − 4 = 4 | ✅ |
| «0 ошибок PHPStan, несматченных подавлений нет» | прогон: `[OK] No errors` | ✅ |
| «1782 теста, 5380 ассертов, 2 skipped» | прогон PHPUnit: точно эти числа | ✅ |
| «все 4 оставшихся подавления имеют причину и `@techdebt`-ссылку» | `rg '@techdebt' src/` → 4 записи, по одной на подавление | ✅ |
| Ссылка на отчёт-источник `docs/agents/reports/code-reviewer-backend/2026-11-27_22-07_deps-update-all-review.md` | файл существует, CR-2/§3 действительно про эти 4 подавления | ✅ |
| `ProcessLivenessWatcher.php:102` — «актуализирована ссылка на прецеденты (убран **снятый** `property.unusedType`)» | `property.unusedType` **не снят** — он живой в `HttpsProxyBridge.php:53`; строка 107 того же файла задачи прямо говорит, что он «оставлен с документированной причиной». Ссылка в коде удалена, прецедент — нет | ❌ **CR-1** |
| DoD: «В `src/` нет подавлений без причины и без ссылки на задачу/прецедент» | фактически проверены только `@phpstan-ignore`; в `src/` остаются 7 `@psalm-suppress` без `@techdebt` (`src/Kernel.php:91`, `src/Component/ModuleSystem/ModuleInterface.php:49` и др.) | ⚠️ **CR-5** |

Валидация структуры: `php vendor/bin/todo-md validate todo/TASK-techdebt-phpstan-suppressions-audit.todo.md` → `✓ 0 error(s), 0 warning(s)`.

---

## 6. Стиль кода и комментариев

- **Язык.** Все новые комментарии — по-русски, с техническими терминами в исходном написании (`PHPStan`, `Psalm`, `docblock`, `stub`, `guard`, `offset`, `TypeError`). Это соответствует практике проекта (ср. `src/Module/ChainDefinition/Domain/Factory/ChainStepFactory.php:15` — «guard-инварианты»): граница validate-language (`docs/`, `README.md`, `AGENTS.md`) на `src/` не распространяется, поэтому метрика не страдает; англицизмов «сверх допустимого» (обычные английские слова вместо русских) в новых строках нет.
- **PHPDoc-гигиена.** Информативные блоки, `@param`/`@var` сохранены, `@phpstan-ignore` — последним тегом; вложенные `//`-комментарии внутри метода оформлены как объяснение перед строкой с подавлением.
- **Дисциплина ссылок.** Хорошо, что адресация в новых комментариях даётся **без** номеров строк (`HttpsProxyBridge.php`, «выше по методу») — предыдущая версия ссылалась на `HttpsProxyBridge.php:41,176` и устарела бы при первом же сдвиге. Это правильный паттерн, его стоит удержать при закрытии **CR-1**.
- **PHPCS** по 6 изменённым файлам: чисто (0 замечаний).
- Неточность формулировки (не стиль, а точность) — в описании поведения Psalm: см. **CR-3**.

---

## 7. Замечания

### CR-1 [🟡 обязательное] Файл задачи и комментарий противоречат друг другу

**Файл/строка:** [todo/TASK-techdebt-phpstan-suppressions-audit.todo.md:64](../../../../todo/TASK-techdebt-phpstan-suppressions-audit.todo.md) (и связанная правка в `src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php:99-100`).

**Проблема.** В задаче записано: «✅ оставлено, причина уже была; актуализирована ссылка на прецеденты (**убран снятый** `property.unusedType`)». Факт: `property.unusedType` **не снят** — он живой и подавляется в `src/Module/AgentRunner/Infrastructure/Service/Codex/HttpsProxyBridge.php:53` (сматчен при `reportUnmatchedIgnoredErrors: true`, подтверждено прогоном). Более того, строка 107 того же файла задачи утверждает обратное: «`property.unusedType` из исходного списка «живых» **оставлен** с документированной причиной после эксперимента с сужением PHPDoc». Итог: внутреннее противоречие задачи и потеря в комментарии `ProcessLivenessWatcher.php` **живого** прецедента (сейчас там остался только `nullCoalesce.offset`).

Скорее всего, следствие порядка работы: эксперимент с сужением PHPDoc сначала «снял» подавление, комментарий переписали, затем сужение откатили, а комментарий и текст задачи — нет.

**Требуемое изменение (одно из двух, на выбор):**

1. Оставить код как есть, а в задаче заменить основание: `:64` → «…актуализирована ссылка на прецедент: оставлен живой `nullCoalesce.offset`, устаревшие номера строк (`:41,176`) убраны — актуальные адреса в §9»; **или**
2. Вернуть в `ProcessLivenessWatcher.php:99-100` упоминание второго живого прецедента без номеров строк, например:
   `// @techdebt TASK-techdebt-phpstan-suppressions-audit (2026-11-27); прецеденты:` / `// property.unusedType, nullCoalesce.offset (HttpsProxyBridge.php).`
   и убрать из `:64` фразу про «снятый `property.unusedType`».

Остальные места задачи (`:107`, `:108`) при этом не меняются — они корректны.

### CR-2 [🟡 рекомендация] Адресат `@techdebt` — закрываемая задача

**Файлы/строки:** `src/Module/AgentRunner/Infrastructure/Service/Codex/HttpsProxyBridge.php:51-52`, `src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php:100-101`, `src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopService.php:118-119`.

**Проблема.** Все 4 маркера ссылаются на **эту же** задачу, которая по завершении уедет в `todo/done/`. Для трёх подавлений (`property.unusedType`, `nullCoalesce.offset`, `booleanNot.alwaysFalse`) плана снятия нет и не будет: это осознанные постоянные отступления (расхождение анализаторов и защитный guard), а не техдолг. Конвенция ([review-functional.md:25](../../../guide/review-functional.md), [phpmd-suppressions-guidelines.md](../../../conventions/ops/phpmd-suppressions-guidelines.md)) говорит про «техдолг-задачу с планом снятия»; ссылка на закрытый аудит формально эту роль не исполняет.

**Предлагаемое действие (не блокирует merge):**

1. Для `argument.type` — завести отдельную задачу (например, `TASK-techdebt-dynamicloop-roundfiles-contract`: сужение `DynamicLoopSessionLoggerInterface::getRoundFiles()` до `list<array{…}>`, Domain+Infrastructure) и переадресовать маркер на неё; в §9 файла задачи эта идея уже сформулирована как «потенциальный техдолг».
2. Для трёх постоянных подавлений — добавить в файл задачи короткий раздел «Осознанные отступления» (причина, почему снятие невозможно без смены контракта/поведения анализаторов, кто утвердил), чтобы `@techdebt`-ссылка вела на зафиксированное **решение**, а не на «план снятия». Утверждение тимлида фиксируется фактом приёмки PR.

Замечание снято, если хотя бы один из двух пунктов выполнен как явная запись; тег `@techdebt` при этом допустим (прецедент `src/Module/ChainExecution/Domain/Entity/StaticChainExecution.php:248` — `@techdebt` с датой и причиной без номера задачи).

### CR-3 [🟢 неточность] Идентификаторы Psalm в комментарии

**Файл/строка:** `src/Module/AgentRunner/Infrastructure/Service/Codex/HttpsProxyBridge.php:44-45`.

**Проблема.** Комментарий утверждает, что при сужении PHPDoc «guard'ы `is_resource()` становятся `RedundantConditionGivenDocblockType`». Контрольный прогон на копии (`/tmp/puaro-probe`, `@var resource|null`, подавление снято) даёт **3** замечания: одно `DocblockTypeContradiction` (составное условие в `isRunning()`) и два `RedundantConditionGivenDocblockType` (проверка в `killProcess()`). Существо обоснования верное, но будущий сопровождающий, повторяя эксперимент, увидит идентификатор, которого нет в комментарии, и усомнится в причине.

**Предлагаемое действие:** добавить один идентификатор через слэш — «…ломает Psalm (`DocblockTypeContradiction`, `RedundantConditionGivenDocblockType` на guard'ах `is_resource()`)…». Правка косметическая, на вердикт не влияет.

### CR-4 [🟢 процесс] Маркер `.make-check.json` и порядок коммитов

**Файл:** `.make-check.json` (в diff — `commit: 3df4c65d` = текущий HEAD).

Маркер — легитимный артефакт (не шум): его записывает цель `check` (`Makefile:127-131`), а CI-джоба `local-check` сверяет его с `HEAD^` через [bin/local-check-proof](../../../../bin/local-check-proof).

**Риск при упаковке PR:** запись `3df4c65d` корректна только если все изменения уйдут **одним** коммитом вместе с маркером (тогда `HEAD^` = `3df4c65d`). Если правки (особенно после закрытия CR-1/CR-3) разложить на несколько коммитов, маркер станет невалидным и CI-джоба `local-check` упадёт с требованием перегенерировать. Сейчас изменения вообще не закоммичены — маркер фиксирует прогон на HEAD с грязным деревом.

**Действие:** после финального кодового коммита перезапустить `make check` и закоммитить обновлённый маркер последним коммитом PR.

### CR-5 [🟢 рекомендация] Скоуп DoD шире фактического аудита

**Файл/строка:** [todo/TASK-techdebt-phpstan-suppressions-audit.todo.md:81](../../../../todo/TASK-techdebt-phpstan-suppressions-audit.todo.md).

**Проблема.** Критерий «В `src/` нет подавлений без причины и без ссылки на задачу/прецедент по конвенции» помечен `[x]`, но проверены только подавления PHPStan. В `src/` остаются **7** тегов `@psalm-suppress` без `@techdebt` (`src/Kernel.php:91`, `src/Component/ModuleSystem/ModuleInterface.php:49`, `.../Pi/PiAgentRunnerService.php:69`, `.../StaticExecutionStrategyService.php:77`, `.../RunStaticChainService.php:74` и `:93`, `.../RunDynamicLoopService.php:98`) — они предсуществующие и вне скоупа задачи, но формулировка критерия читается как «все подавления».

**Предлагаемое действие:** сузить формулировку до «подавлений PHPStan» (или добавить «; подавления Psalm — вне скоупа задачи»).

**Бонус-рекомендация (отдельная будущая задача).** Симметричный контроль для Psalm включается одной строкой в [psalm.xml](../../../../psalm.xml): `findUnusedPsalmSuppress="true"` — это даст для `@psalm-suppress` то же, что `reportUnmatchedIgnoredErrors: true` даёт для `@phpstan-ignore`, и заодно высветит 7 неаннотированных подавлений. В скоуп текущей задачи не входит (и в постановке её нет) — фиксирую как кандидата в техдолг, а не как замечание.

---

## 8. Фактические результаты проверок

| Проверка | Команда | Результат |
|--|--|--|
| PHPStan | `vendor/bin/phpstan analyse --no-progress` | `[OK] No errors` (уровень 8, `reportUnmatchedIgnoredErrors: true`) |
| Psalm | `vendor/bin/psalm --no-progress` | `No errors found!`; 163 info-замечания, типизация 98.97% |
| PHPUnit | `vendor/bin/phpunit` | `OK, but some tests were skipped! Tests: 1782, Assertions: 5380, Skipped: 2` |
| Deptrac | `make deptrac` | `Errors 0`, `Warnings 0` (Uncovered 208, Allowed 1735) |
| PHPMD | `make phpmd` | `No violations.` (409 файлов) |
| PHPCS | `vendor/bin/phpcs --standard=phpcs.xml.dist` по 6 изменённым `src/`-файлам | 0 замечаний |
| md-links | `make md-links` | 544 файла, все внутренние ссылки валидны |
| validate-roles | `make validate-roles` | All checks passed |
| validate-todo (файл задачи) | `php vendor/bin/todo-md validate todo/TASK-techdebt-phpstan-suppressions-audit.todo.md` | `0 error(s), 0 warning(s)` |
| validate-language | `make validate-language` | warning mode: 20 файлов документации превышают порог — предсуществующее, правки задачи документов не касаются (`docs/agents/reports` исключён из проверки) |
| Контрольный эксперимент Psalm | копия `src/` в `/tmp/puaro-probe` с `@var resource\|null` | 3 ошибки: `DocblockTypeContradiction` + 2× `RedundantConditionGivenDocblockType` → обоснование подавления подтверждено |

---

## 9. Что проверено и замечаний не вызвало

- Снятие именно **мёртвых** подавлений: при `true` ни одно из снятых правил не срабатывает, иных ошибок не появилось — значит, маскировки реальной проблемы не было.
- Удаление `return.type` над `json_encode(..., JSON_THROW_ON_ERROR)` корректно: PHPStan выводит `non-falsy-string`, тип возврата `string` удовлетворяется.
- Удаление `nullsafe.neverNull` в `DynamicLoopExecution.php:297-298` и `FinalizeDynamicLoopService.php:95` не задело логику: выражения не изменились, `?? false`/`?? 0.0` сохранены как поведенческая защита.
- Новые комментарии не вводят «договорных» утверждений о поведении, которых нет в коде (единственное исключение — точность идентификаторов Psalm, CR-3).
- Адресация без номеров строк в новых комментариях — правильный паттерн против устаревания (см. §6).
- Файл задачи структурно валиден, история изменений дополнена, MoSCoW/план/DoD отмечены согласованно с фактами (кроме CR-1/CR-5).
- Секретов, данных внешних сервисов, отладочных артефактов и временного кода без маркировки в diff нет.

**Риски, которые остаются вне скоупа этой задачи** (фиксирую, чтобы не потерять): 7 `@psalm-suppress` без причины/техдолг-ссылки в `src/`; отсутствие `findUnusedPsalmSuppress` в `psalm.xml`; слабый контракт `DynamicLoopSessionLoggerInterface::getRoundFiles()` (кандидат в отдельную задачу — см. CR-2).

---

## 10. Итог

Аудит выполнен полно и аккуратно: 4 «мёртвых» подавления сняты, 4 живых подтверждены экспериментально и оформлены по конвенции, `reportUnmatchedIgnoredErrors: true` оставлен осознанно и не создаёт CI-риска, поведение кода не изменено, все заявленные числа и адреса воспроизводятся.

**Вердикт: 🟡 REQUEST CHANGES (мягкий).** К приёмке нужно закрыть **CR-1** — одна строка либо в файле задачи, либо в комментарии `ProcessLivenessWatcher.php`; это устранение противоречия «снят/не снят `property.unusedType`». CR-2…CR-5 — рекомендации: их выполнение улучшает прослеживаемость, но приёмку не блокирует. После закрытия CR-1 (и, желательно, CR-4 при разбиении на несколько коммитов) — **APPROVE**.

---

## 11. Верификационное ревью

**Роль:** Ревьювер Бэка Пуаро (code_reviewer_backend_puaro)
**Дата:** 2026-11-27 23:41 (1795797673)
**Тип прохода:** верификация устранения замечаний после доработки (CR-1, CR-3, CR-5) — полное ревью не повторялось, вне названных замечаний код не пересматривался.
**Объект:** те же незакоммиченные изменения ветки `task/phpstan-suppressions-audit`, база/HEAD `3df4c65d`, 9 изменённых файлов + этот отчёт.
**Метод:** статическое сопоставление формулировок с фактическим состоянием кода + независимое воспроизведение спорных утверждений. Рабочее дерево ревьювером **не изменялось**: правок, коммитов, повторной генерации маркера `.make-check.json` нет; эксперимент Psalm выполнялся в одноразовом `git worktree` в `/tmp` (создан и удалён, `git status` до и после совпадает).
**Вердикт:** ✅ **APPROVE** — все три проверяемых замечания устранены, фактических расхождений между кодом, комментариями и файлом задачи по ним не осталось.

### 11.1 Сводка

| Замечание | Что требовалось | Факт | Результат |
|--|--|--|--|
| **CR-1** [🟡 обязательное] | Убрать противоречие «убран **снятый** `property.unusedType`»: либо вернуть живой прецедент в комментарий `ProcessLivenessWatcher.php:99-100`, либо переписать строку 64 файла задачи | комментарий называет **оба** живых прецедента без номеров строк; строка 64 задачи переформулирована без слова «снятый» и больше не противоречит §9 | ✅ **устранено** |
| **CR-3** [🟢 неточность] | Назвать в комментарии `HttpsProxyBridge.php:44-47` оба идентификатора Psalm | в комментарии указаны `DocblockTypeContradiction` **и** `RedundantConditionGivenDocblockType` | ✅ **устранено** |
| **CR-5** [🟢 рекомендация] | Сузить формулировку DoD до подавлений `@phpstan-ignore` (Psalm — вне скоупа) | DoD прямо ограничен `@phpstan-ignore` с оговоркой «подавления Psalm — вне скоупа этой задачи» | ✅ **устранено** |

### 11.2 CR-1 — противоречие «снят/не снят `property.unusedType`» — ✅ устранено

Исполнитель применил **оба** варианта, предложенных в §7, то есть закрыл противоречие с двух сторон сразу (это не хуже, а лучше требуемого минимума — комментарий и файл задачи согласованы между собой).

**Код** — `src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php:100-101`, текущее состояние:

```php
// @techdebt TASK-techdebt-phpstan-suppressions-audit (2026-11-27); прецеденты:
// property.unusedType, nullCoalesce.offset (HttpsProxyBridge.php).
```

Проверка точности:

| Утверждение комментария | Проверка | Итог |
|--|--|--|
| `property.unusedType` — живой прецедент | `rg -n 'phpstan-ignore' src/` → `HttpsProxyBridge.php:53` (`property.unusedType`); прогон PHPStan при `reportUnmatchedIgnoredErrors: true` зелёный, значит подавление **срабатывает** | ✅ |
| `nullCoalesce.offset` — живой прецедент | тот же инвентарь → `HttpsProxyBridge.php:195`; сматчен тем же прогоном | ✅ |
| Оба прецедента — там же, где указано (`HttpsProxyBridge.php`) | оба `@phpstan-ignore` физически в `Codex/HttpsProxyBridge.php` | ✅ |
| Ссылка без номеров строк | `git diff` фиксирует `(HttpsProxyBridge.php:41,176)` → `(HttpsProxyBridge.php)`; устаревшие адреса `:41,176` (после аудита это `:53` и `:195`) удалены | ✅ |

Дополнительно: удаление номеров строк закрывает и латентный дефект, отмеченный в §6 — адреса в комментарии больше не разъедутся при следующем сдвиге. Потеря живого прецедента, на которую указывал CR-1 (в комментарии оставался только `nullCoalesce.offset`), устранена: оба идентификатора снова на месте.

**Файл задачи** — [todo/TASK-techdebt-phpstan-suppressions-audit.todo.md:64](../../../../todo/TASK-techdebt-phpstan-suppressions-audit.todo.md), текущее состояние:

> ✅ оставлено, причина уже была; актуализирована ссылка на прецеденты: живые `property.unusedType` и `nullCoalesce.offset` (оба оставлены в этом аудите), устаревшие номера строк убраны — актуальные адреса в §9;

Проверка точности каждого тезиса:

| Тезис строки 64 | Проверка | Итог |
|--|--|--|
| «живые `property.unusedType` и `nullCoalesce.offset`» | оба сматчены при `reportUnmatchedIgnoredErrors: true` | ✅ |
| «оба оставлены в этом аудите» | совпадает с §9 (`:53`, `:195`) и с Must Have (`:62`, `:63` — «✅ оставлено») | ✅ |
| «устаревшие номера строк убраны» | `git diff` — номера удалены | ✅ |
| «актуальные адреса в §9» | §9 перечисляет `HttpsProxyBridge.php:53` и `:195` — совпадает с `rg -n` по рабочему дереву | ✅ |
| Отсутствие слова «снятый» применительно к `property.unusedType` | фразы нет; противоречие со §9 («оставлен с документированной причиной») снято | ✅ |

### 11.3 CR-3 — идентификаторы Psalm в комментарии — ✅ устранено

`src/Module/AgentRunner/Infrastructure/Service/Codex/HttpsProxyBridge.php:42-45`, текущее состояние:

```text
 * PHPStan считает false в @var неиспользуемым (ветка false proc_open бросает
 * RuntimeException до присвоения) и требует сузить тип до resource|null, но
 * сужённый docblock ломает Psalm: guard'ы is_resource() дают
 * DocblockTypeContradiction и RedundantConditionGivenDocblockType.
```

**Независимое воспроизведение эксперимента** (одноразовый `git worktree` на `3df4c65d`, в него скопированы файлы рабочего дерева; `@var resource|false|null` → `@var resource|null`, `@phpstan-ignore property.unusedType` снят; `vendor/bin/psalm --no-progress`):

| Идентификатор | Где срабатывает | Кол-во |
|--|--|--|
| `DocblockTypeContradiction` | `HttpsProxyBridge.php:181:40` — `if ($this->process === null \|\| !is_resource($this->process))` в `isRunning()` | 1 |
| `RedundantConditionGivenDocblockType` | `HttpsProxyBridge.php:343:13` и `:343:40` — `if ($this->process !== null && is_resource($this->process))` в `killProcess()` | 2 |

Итог прогона: `3 errors found`, `163 other issues` (info — предсуществующие). То есть оба идентификатора действительно возникают именно на guard'ах `is_resource()`, как теперь и написано в комментарии; формулировка соответствует рекомендованной в §7. Утверждение «подавление заглушает только замечание PHPStan» тоже верно: под `resource|false|null` основной прогон Psalm чистый.

Точность выше уровня «двух идентификаторов»: `DocblockTypeContradiction` возникает на составном условии (сужение после `=== null`), а `RedundantConditionGivenDocblockType` — на паре проверок в `killProcess()`. Комментарий формулирует это обобщённо («guard'ы `is_resource()` дают …»), но ошибочным не является: все три срабатывания вызваны этими guard'ами.

### 11.4 CR-5 — скоуп DoD — ✅ устранено

[todo/TASK-techdebt-phpstan-suppressions-audit.todo.md:81](../../../../todo/TASK-techdebt-phpstan-suppressions-audit.todo.md), текущее состояние:

> В `src/` нет `@phpstan-ignore`-подавлений без причины и без ссылки на задачу/прецедент по конвенции (подавления Psalm — вне скоупа этой задачи). ✅ 2026-11-27: все 4 оставшихся `@phpstan-ignore` имеют причину и `@techdebt`-ссылку на эту задачу.

| Проверка | Факт | Итог |
|--|--|--|
| Скоуп критерия сужен | «`@phpstan-ignore`-подавлений», плюс явная оговорка про Psalm | ✅ |
| Число «4 оставшихся» | `rg -n 'phpstan-ignore' src/` → 4 (`ProcessLivenessWatcher.php:102`, `HttpsProxyBridge.php:53`, `:195`, `RunDynamicLoopService.php:120`) | ✅ |
| У каждого — причина | комментарии-обоснования: `HttpsProxyBridge.php:42-47`, `:188-192`, `ProcessLivenessWatcher.php:96-99`, `RunDynamicLoopService.php:113-117` | ✅ |
| У каждого — `@techdebt`-ссылка на эту задачу | `HttpsProxyBridge.php:51`, `:193`, `ProcessLivenessWatcher.php:100`, `RunDynamicLoopService.php:118` | ✅ |
| Psalm-подавления действительно вне скоупа | в `src/` остаются 7 тегов `@psalm-suppress` без `@techdebt` (восьмое совпадение `rg` — `RunDynamicLoopService.php:119`, это текстовое упоминание внутри нового `@techdebt`-комментария, а не тег) | ✅ оговорка корректна |

### 11.5 Остаточные наблюдения (приёмку не блокируют)

- **N-1 [🟢 nit, файл задачи]** — строка 62 (`HttpsProxyBridge.php:41` — `property.unusedType`) называет только `RedundantConditionGivenDocblockType`, тогда как комментарий в коде (`:44-45`) и воспроизведённый эксперимент дают ещё и `DocblockTypeContradiction`. Утверждение не ложное (названный идентификатор действительно срабатывает), но неполное; скоуп CR-3 ограничивался комментарием в коде, поэтому отдельного прохода не требую. Если файл задачи будет ещё правиться — уместно выровнять формулировку одной строкой; повторного ревью не требует.
- **CR-4 [🟢 процесс] остаётся в силе** — `.make-check.json` содержит `commit 3df4c65d` (HEAD) и `utc 2026-11-27 16:24:38`, то есть фиксирует прогон **до** правок доработки, а изменения по-прежнему не закоммичены. Перед упаковкой PR: перезапустить `make check` и закоммитить маркер последним коммитом, иначе CI-джоба `local-check` (сверка с `HEAD^`) упадёт. На вердикт не влияет: все цели, которых касаются правки, прогнаны ревьювером заново (§11.6).
- **CR-2 [🟢 рекомендация]** — в скоуп доработки не входила, не проверялась; остаётся рекомендацией.
- Процессное: задача всё ещё `status: in_progress` и лежит в `todo/`; по регламенту после зелёного CI и до запроса апрува её нужно перевести в `done` и перенести в `todo/done/` (с актуализацией ссылок).

### 11.6 Проверки, выполненные при верификации

| Проверка | Команда | Результат |
|--|--|--|
| PHPStan (затронутые правками комментарии/PHPDoc + привязка `@phpstan-ignore`) | `vendor/bin/phpstan analyse --no-progress` | `[OK] No errors` → при `reportUnmatchedIgnoredErrors: true` все 4 подавления сматчены, новых «мёртвых» правки не создали |
| Psalm | `vendor/bin/psalm --no-progress` | `No errors found!`, 163 info-замечания (предсуществующие), типизация 98.97 % |
| PHPUnit | `vendor/bin/phpunit` | `OK, but some tests were skipped! Tests: 1782, Assertions: 5380, Skipped: 2` — совпадает с заявленным в DoD |
| PHPCS | `vendor/bin/phpcs --standard=phpcs.xml.dist` по 2 изменённым файлам | 0 замечаний |
| validate-todo | `php vendor/bin/todo-md validate todo/TASK-techdebt-phpstan-suppressions-audit.todo.md` | `0 error(s), 0 warning(s)` |
| Эксперимент Psalm (CR-3) | `git worktree` в `/tmp` + `@var resource|null` + снятое подавление | 3 ошибки: 1× `DocblockTypeContradiction`, 2× `RedundantConditionGivenDocblockType` → комментарий точен; worktree удалён |
| Отсутствие поведенческих правок | `git diff -U0 -- 'src/**/*.php'` с отфильтрованными строками-комментариями | остались только удалённые хвостовые `// @phpstan-ignore …` (выражения байт-в-байт те же); ни одна инструкция не изменена |

`make check` целиком не дублировался (перезапись `.make-check.json` изменила бы проверяемое дерево — см. CR-4); затронутые правками цели прогнаны по отдельности.

### 11.7 Итог верификации

CR-1 (обязательное) — **устранено**: комментарий `ProcessLivenessWatcher.php:100-101` снова называет живой `property.unusedType` (без номеров строк), строка 64 файла задачи переформулирована и согласована с §9 и Must Have. CR-3 — **устранено**: оба идентификатора Psalm названы и независимо воспроизведены. CR-5 — **устранено**: DoD ограничен `@phpstan-ignore` с явной оговоркой про Psalm.

**Вердикт верификационного прохода: ✅ APPROVE.** Остаток для доработки отсутствует; к исполнению — только процессные шаги: CR-4 (перегенерировать маркер `make check` последним коммитом) и перевод задачи в `done`/`todo/done/` перед запросом апрува. N-1 — косметика, при желании правится попутно.