# Полное ревью ветки task/deps-update-all (обновление всех зависимостей в рамках констрейнтов)

**Роль:** Ревьювер Бэка Пуаро (code_reviewer_backend_puaro)
**Дата:** 2026-11-27
**Объект:** незакоммиченные изменения ветки `task/deps-update-all` (база HEAD `d1f50a89`) — `composer.lock` (+322/−364: 45 пакетов обновлено, 1 удалён, добавленных нет), `src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php` (+9/−1), `tests/Unit/Infrastructure/Service/AgentRunner/Lifecycle/RunAgentProcessLifecycleServiceTest.php` (11 вхождений переименования helper), `todo/TASK-chore-deps-update-all.todo.md`, `.make-check.json`
**Задача:** [todo/TASK-chore-deps-update-all.todo.md](../../../../todo/TASK-chore-deps-update-all.todo.md)
**Тип прохода:** полное RV — соответствие постановке и скоупу, минимальность правок, обоснованность подавления PHPStan, качество правки теста, полнота файла задачи, конвенции проекта
**Вердикт:** 🔴 **REQUEST CHANGES** — 1 блокирующее замечание (CR-1), 1 важное (CR-2), 5 косметических/процессных (CR-3…CR-7)

**Верификационный проход 2026-11-27 (после доработки):** ✅ **APPROVE** — все 7 замечаний закрыты, остатки — только ниты без блокировки, подробности в [§11](#11-верификационное-ревью-2026-11-27-2225)

Существо правок корректно и технически состоятельно: `composer.json` не тронут (контрольная сумма `content-hash` lock не изменилась), посторонних обновлений нет, dev-снапшоты заменены стабильными тегами, подавление PHPStan действительно необходимо и **не маскирует ошибку** — это подтверждено трассировкой теста и экспериментом с копией файла без подавления; переименование helper — чистый rename без изменения семантики. Блокирует приёмку только неполнота обновления lock и неверная запись о его полноте в файле задачи.

---

## 0. Контекст и метод

Метод: статическое сопоставление diff и постановки + эмпирические проверки (точечные прогоны затронутых тестов, PHPStan, обратимые dry-run и API-срезы реестров пакетов). Рабочее дерево ревьювером **не изменялось**: коммитов нет, правок кода нет; `md5sum composer.lock` до/после `composer update --dry-run` совпадает.

| Эксперимент | Цель | Результат |
|--|--|--|
| PHPStan на dist-конфиге | проверить заявленный зелёный анализ | `[OK] No errors` |
| PHPStan на конфиге-наследнике с `reportUnmatchedIgnoredErrors: true` | проверить, что новое подавление **сматчено**, и найти «мёртвые» подавления | новое подавление сматчено; 4 чужих подавления не сматчены (предсуществующие, см. CR-2) |
| PHPStan на копии `ProcessLivenessWatcher.php` в `/tmp` **без** строки `// @phpstan-ignore` | доказать, что подавление необходимо и точно нацелено | `/tmp/puaro-probe/ProcessLivenessWatcher.php:104: Negated boolean expression is always false. [identifier=booleanNot.alwaysFalse]` — идентификатор и строка совпадают |
| Минимальное репро в `/tmp` (`if ($process->isRunning()) { if (!$process->isRunning()) … }`) | понять природу правила | то же нарушение `booleanNot.alwaysFalse` → PHPStan обобщает повторный вызов чистым (детерминированным); для опроса внешнего OS-состояния это ложное срабатывание |
| Трассировка `waitForNaturalExitImmediatelyBeforeIdleStopReturnsNaturally` | проверить тезис «опрос контрактный» | подтверждён: `finish()` вызывается в колбэке фейковых часов, т.е. **между** вторым и третьим опросом; решает именно третий опрос (см. §3) |
| `composer outdated --direct` (текущий срез), `composer prohibits`, `composer why-not` | проверить утверждение §9 о полноте обновления | **утверждение неверно**: 6 прямых пакетов `symfony/*` отстают на patch (см. CR-1) |
| `composer update … --dry-run` для 6 пакетов `symfony/*` | проверить, что обновление возможно | `Lock file operations: 0 installs, 7 updates, 0 removals` — конфликтов нет, `composer.lock` не изменён |
| Packagist API: время публикации `v8.1.13` | отделить «не успело выйти» от «пропущено» | `symfony/dependency-injection` — **2026-11-25T12:51Z** (за 2 суток до обновления); `filesystem`/`http-client`/`yaml` — 2026-11-27T13:27Z; `finder`/`http-foundation` — 14:07Z; `http-kernel` — 14:48Z (`composer.lock` записан 14:28Z, маркер `make check` — 14:58Z) |
| Packagist API: версии `psalm/plugin-symfony` | проверить тезис «5.3.0 — последняя для Psalm 6» | подтверждён: `v5.3.0 psalm=^6 \|\| dev-master`, `v5.4.0 psalm=^7.0.0-beta24`, `v5.16.3 psalm=^7.13@stable` |

Полный прогон `make check` ревьювером не дублировался целиком — прогнаны все цели по отдельности (результаты в §6) и полный набор тестов.

---

## 1. Соответствие постановке и скоупу

| Требование постановки | Факт | Статус |
|--|--|--|
| Скоуп: `composer.lock` + только оправданные правки совместимости | изменено ровно 5 файлов: `composer.lock`, `ProcessLivenessWatcher.php`, тест-lifecycle, файл задачи, маркер `.make-check.json` | ✅ |
| `composer.json` **не должен меняться** | не изменён; `content-hash` lock идентичен (`c21f731ba34256979b24efa32d2ad702` до/после) | ✅ |
| Никаких правок бизнес-логики | в `ProcessLivenessWatcher.php` код не менялся — добавлен только комментарий и трейлинг-подавление; поведение подтверждено тестами | ✅ |
| `composer validate --strict` | `./composer.json is valid` | ✅ |
| `composer audit` | `No security vulnerability advisories found` | ✅ |
| `make check` зелёный | все цели зелёные (см. §6) | ✅ |
| «`composer.lock` содержит актуальные версии всех зависимостей в рамках констрейнтов» | **не выполнено**: 6 прямых пакетов `symfony/*` отстают на patch при отсутствии блокирующих констрейнтов | 🔴 CR-1 |
| Won't Have: не тянуть Psalm 7 / `dev-master` | `vimeo/psalm` 6.x-dev → 6.21.4 (стабильный тег линии 6.x), `psalm/plugin-symfony` 5.3.0 — не двигался | ✅ |
| Won't Have: без массового рефакторинга под новые предупреждения | правка одна, точечная; глобальный `treatPhpDocTypesAsCertain: false` не вводился (правильное решение — не ослаблять анализ ради одной строки) | ✅ |

Проверено, что `.make-check.json` — не «посторонняя» правка: файл перезаписывается целью `make check` (рецепт в `Makefile:127-131`) и проверяется CI-джобой `bin/local-check-proof`, то есть входит в штатный след задачи.

---

## 2. `composer.lock`: инвентаризация и сверка с разделом 9 файла задачи

Сравнение `HEAD:composer.lock` ↔ рабочее дерево:

- **45 обновлений, 0 добавлений, 1 удаление** — совпадает с заявленным в §9.
- Удалён `theseer/tokenizer 2.0.1` — причина техническая и легитимная: `phpunit/php-code-coverage` 14.5.2 убрал его из `require` (токенизация через `nikic/php-parser ^5.9`); в проекте прямых ссылок на пакет нет (`grep` по `src/ tests/ apps/ config/ composer.json` — пусто).
- Замена dev-снапшотов стабильными тегами: `phpmd 3.x-dev → 3.0.0`, `pdepend 3.x-dev → 3.1.0`, `vimeo/psalm 6.x-dev → 6.21.4`. В новом lock **не осталось ни одного `*-dev`** — канон `prefer-stable` соблюдён.
- `plugin-api-version` (2.9.0), `platform`/`platform-dev` — не менялись.
- `packages` 45 / `packages-dev` 83 (было 84) — согласовано с удалением.
- Смена `dist.url`/`source` — только штатные zipball/`tree`-ссылки новых версий, посторонних хостов нет.
- Сверка заявленных в §9 прямых зависимостей: `symfony/console`, `lock`, `process`, `translation` → 8.1.12, `monolog-bridge` → 8.1.10, `twig-bundle` → 8.1.9, `symfony/monolog-bundle` 4.0.2 → 4.1.0, `phpunit/phpunit` 13.3.3 → 13.4.8, `phpstan/phpstan` 2.2.13 → 2.3.7, `deptrac/deptrac` 4.7.1 → 4.7.2 — **всё совпадает** (единственная неточность формулировки: `monolog-bridge` — транзитивный, не прямой; см. CR-5).
- Тезис §9 про `psalm/plugin-symfony` проверен по реестру: `v5.4.0+` требует `vimeo/psalm ^7`, `5.3.0` — последняя на линии 6.x. Тезис верен, риск из §7 постановки не материализовался.

**Найденное расхождение (CR-1).** На момент среза 6 прямых пакетов `symfony/*` имеют доступный patch `v8.1.13`, конфликтов нет:

| Пакет | В lock | Доступно | `v8.1.13` опубликован (UTC) |
|--|--|--|--|
| `symfony/dependency-injection` | `v8.1.12` (`composer.lock:723`) | `v8.1.13` | **2026-11-25T12:51Z** (за 2 суток до обновления) |
| `symfony/filesystem` | `v8.1.12` (`composer.lock:1197`) | `v8.1.13` | 2026-11-27T13:27Z |
| `symfony/finder` | `v8.1.12` (`composer.lock:1268`) | `v8.1.13` | 2026-11-27T14:07Z |
| `symfony/http-client` | `v8.1.12` (`composer.lock:1479`) | `v8.1.13` | 2026-11-27T13:27Z |
| `symfony/http-kernel` | `v8.1.12` (`composer.lock:1740`) | `v8.1.13` | 2026-11-27T14:48Z |
| `symfony/yaml` | `v8.1.12` (`composer.lock:3530`) | `v8.1.13` | 2026-11-27T13:27Z |
| `symfony/http-foundation` (транзитивный) | `v8.1.12` | `v8.1.13` | 2026-11-27T14:07Z |

`composer update symfony/dependency-injection symfony/filesystem symfony/finder symfony/http-client symfony/http-kernel symfony/yaml --with-all-dependencies --dry-run` → `0 installs, 7 updates, 0 removals`, `composer audit` при этом остаётся чистым.

---

## 3. Подавление PHPStan: обоснованность и отсутствие маскировки

**Необходимость доказана.** Копия файла без строки подавления (в `/tmp`, рабочее дерево не тронуто) даёт ровно `booleanNot.alwaysFalse` на строке 104 — то есть подавление нацелено на реально существующее срабатывание с правильным идентификатором и правильной строкой, а не «на всякий случай».

**Причина срабатывания — ложное обобщение анализатора, а не дефект кода.** Минимальное репро в `/tmp` подтвердило: PHPStan считает повторный вызов `Process::isRunning()` в одном scope детерминированным (`if ($process->isRunning()) { if (!$process->isRunning()) … }` → то же нарушение). В действительности `Symfony\Component\Process\Process::isRunning()` при каждом вызове обновляет состояние OS-процесса (`vendor/symfony/process/Process.php:923-932`, вызов `updateStatus()`), поэтому два последовательных опроса могут законно разойтись.

**Подавление не маскирует ошибку — опрос контрактно нагружен.** Трассировка `tests/Unit/Infrastructure/Service/AgentRunner/ProcessLivenessWatcherTest.php:212-237`:
- `ProcessLivenessClockFake::now()` (`…Test.php:456-462`) вызывает колбэк `onNow`, который делает `$process->finish()` при `time > 1.0`;
- этот колбэк вычисляется в условии idle-блока (`ProcessLivenessWatcher.php:95`), то есть **после** второго опроса (`:77`) и **до** третьего (`:104`);
- следовательно, решает именно третий опрос: при его удалении выполнится `$process->stop(2)` и будет возвращён `false`, что ломает ассерты `assertTrue($completed)` и `assertSame(0, $process->stopCalls)`.

Вывод: удаление проверки как «мёртвого кода» (к чему формально подталкивает текст предупреждения) **недопустимо** — это изменит контракт `waitFor()` («естественное завершение → `true` без `stop()`») и сломает тест. Значит, точечное подавление — соразмерное решение, а отказ от глобального `treatPhpDocTypesAsCertain: false` — правильный (не ослабляет анализ проекта ради одной строки).

**Замечание к оформлению (CR-2):** конвенция проекта требует для подавлений не только причины, но и техдолг-задачи — `docs/guide/review-functional.md:25` и чек-лист там же, `docs/guide/review-architectural.md:19`, `docs/conventions/ops/phpmd-suppressions-guidelines.md` (раздел «Общие правила»). Ни новой задачи в `todo/`, ни зафиксированного «осознанного отступления» с утверждением тимлида в файле задачи нет.

---

## 4. Переименование helper в тесте lifecycle

- Причина подтверждена: в установленном `phpunit/phpunit 13.4.8` метод `TestCase::runLifecycle()` объявлен `final public` (`vendor/phpunit/phpunit/src/Framework/TestCase.php:381`) — приватный одноимённый helper в наследнике даёт фатальную ошибку. Переименование обязательно.
- Правка — **чистый rename**: изменено только имя (объявление `…Test.php:560` + 10 вызовов), сигнатура с параметрами по умолчанию (`$command`, `$resetParser`, `$buildResult`, `$workingDir`, `$requestTimeout = 300`) и тело не тронуты; ассерты в тестах не менялись.
- Полнота: вхождений `runLifecycle(` в `src/`, `apps/`, `tests/` больше нет; `runLifecycleScenario` встречается 11 раз (объявление + 10 вызовов) — совпадает с §9 файла задачи. Коллизии с методами PHPUnit нет.
- Семантика сохранена: затронутый файл — `OK (29 tests, 56 assertions)`; полный набор — `OK (1782 tests, 5380 assertions, 2 skipped)`.
- Докблок helper-а по-прежнему описывает фактическое поведение, устаревших упоминаний имени нет.

Замечаний нет.

---

## 5. Файл задачи: полнота и точность

Положительно: обязательные разделы на месте, `todo-md validate` — 0 ошибок/предупреждений; `status: in_progress`, `branch`, `assignee` заполнены; критерии, план и история изменений отражают фактическое состояние; незакрытый пункт DoD помечен честно с указанием ответственного за коммит/PR; риск §7 корректно закрыт фактом (плагин не двигался).

Требует правок:
- 🔴 **CR-1** — §9 строка 101: утверждение «`composer outdated --direct` показывает только `psalm/plugin-symfony` и `vimeo/psalm`» не соответствует действительности (проверено: 8 строк, включая 6 прямых `symfony/*`); вместе с этим не закрыт ожидаемый результат «актуальные версии».
- 🟢 **CR-5** — мелкие неточности формулировок и неполнота инвентаризации (детали в §7).
- 🟢 **CR-7** — метаданные: под ревью уместен `status: review` и заполненный `reviewer`.

---

## 6. Прогон проверок (ревьювером локально, 2026-11-27)

| Проверка | Результат |
|--|--|
| `vendor/bin/phpstan analyse` (dist) | ✅ `[OK] No errors` |
| `vendor/bin/psalm --no-cache` | ✅ `No errors found!` (163 info — предсуществующие, столько же было до обновления) |
| `vendor/bin/phpunit` | ✅ `OK, but some tests were skipped!` — 1782 теста, 5380 ассертов, 2 skipped |
| `vendor/bin/phpunit …/RunAgentProcessLifecycleServiceTest.php` | ✅ `OK (29 tests, 56 assertions)` |
| `vendor/bin/phpcs -n src/` | ✅ нарушений нет |
| `vendor/bin/phpmd analyze src …` | ✅ `No violations` |
| `vendor/bin/deptrac` | ✅ 0 errors, 0 warnings |
| `php vendor/…/validate-md-links` | ✅ все внутренние ссылки валидны |
| `vendor/bin/todo-md validate todo/TASK-chore-deps-update-all.todo.md` | ✅ 0 errors, 0 warnings |
| `php bin/validate-roles` | ✅ passed |
| `vendor/bin/validate-language` | ⚠ warning mode: 20 файлов выше порога — предсуществующее состояние (не из этого диффа) |
| `vendor/bin/coding-standard-verify` | ✅ OK (0.35.0 — не отстаёт) |
| `vendor/bin/coding-standard-di-check` | ✅ OK (WARN о `services.yaml` — предсуществующий) |
| `composer validate --strict` | ✅ valid |
| `composer audit` | ✅ 0 advisory |
| `composer install --dry-run` | ✅ `Nothing to install, update or remove` |

Заявленное «`make check` зелёный» подтверждено.

---

## 7. Замечания (Change Request)

### 🔴 CR-1 (блокирующее). Обновление неполное: 6 прямых пакетов `symfony/*` отстают на patch + неверная запись об этом в файле задачи

- `composer.lock:723` `symfony/dependency-injection` `v8.1.12` → доступен `v8.1.13` (опубликован **2026-11-25T12:51Z**, за двое суток до обновления).
- `composer.lock:1197` `symfony/filesystem`, `:1268` `symfony/finder`, `:1479` `symfony/http-client`, `:1740` `symfony/http-kernel`, `:3530` `symfony/yaml` — все `v8.1.12` → доступен `v8.1.13` (публикация 2026-11-27T13:27Z…14:48Z; `http-kernel` — до записи маркера `make check` в 14:58Z).
- Транзитивно: `symfony/http-foundation` `v8.1.12` → `v8.1.13`.
- Конфликтов нет: `composer update … --dry-run` даёт `0 installs, 7 updates, 0 removals`; `composer audit` остаётся чистым; констрейнты `composer.json` (`^8.0`) не меняются.
- `todo/TASK-chore-deps-update-all.todo.md:101` — утверждение «После обновления `composer outdated --direct` показывает только `psalm/plugin-symfony` … и `vimeo/psalm`» **неверно**: текущий вывод содержит ещё 6 прямых `symfony/*` (8 строк всего). Для задачи, чья цель — «lock содержит актуальные версии всех зависимостей в рамках констрейнтов», это прямое отклонение от ожидаемого результата и одновременно недостоверная запись в летописи.

**Что требуется (любой из вариантов, предпочтителен первый; исполнитель — Бэкендер Тони):**
1. Выполнить `composer update` (или точечно `composer update symfony/dependency-injection symfony/filesystem symfony/finder symfony/http-client symfony/http-kernel symfony/yaml --with-all-dependencies`), обновить §9 фактическими версиями и перепрогнать `make check`.
2. Если обновление сознательно откладывается — зафиксировать это в §9 как **осознанное отступление** с причиной и утверждением тимлида (формат — `docs/guide/review-functional.md`, раздел «Отклонение»), а также убрать/исправить неверное утверждение о выводе `composer outdated --direct`.

### 🟡 CR-2 (важное). Подавление PHPStan не оформлено по конвенции

- `src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php:104` (`// @phpstan-ignore booleanNot.alwaysFalse`) и `todo/TASK-chore-deps-update-all.todo.md:104`.
- Конвенция требует для подавлений причину **и** техдолг-задачу: прямое правило для `@phpstan-ignore*` — `docs/guide/review-functional.md:25` (и чек-лист там же), архитектурный трек — `docs/guide/review-architectural.md:19`; смежные правила подавлений сформулированы для PHPMD (`docs/conventions/ops/phpmd-suppressions-guidelines.md`, раздел «Общие правила», в т.ч. «Если подавление временное, создаётся/обновляется отдельная `todo/TASK-*` задача») — логика та же. Причина в коде есть — задачи/утверждённого отступления нет.
- Дополнительный аргумент из этой же проверки: в проекте уже есть **4 «мёртвых» подавления**, которые никто не видит из-за `reportUnmatchedIgnoredErrors: false` (`phpstan.neon.dist:11`): `src/Module/ChainExecution/Application/Mapper/ReportJsonMapper.php:56`, `src/Module/DynamicLoop/Domain/Entity/DynamicLoopExecution.php:297`, `:298`, `src/Module/DynamicLoop/Domain/Service/Dynamic/FinalizeDynamicLoopService.php:95`. Новое подавление в такой конфигурации со временем может так же молча устареть.

**Что требуется:** одна техдолг-задача в `todo/` (например, `TASK-techdebt-phpstan-suppressions-audit`) с планом: снять/актуализировать 4 мёртвых подавления, перепроверить новое подавление `booleanNot.alwaysFalse` при следующем обновлении PHPStan, оценить `reportUnmatchedIgnoredErrors: false`. Либо — зафиксировать осознанное отступление с утверждением тимлида. При выборе варианта с задачей — добавить в комментарий ссылку вида `@techdebt TASK-… (2026-11-27)` по правилу маркировки временных решений из `AGENTS.md`.

### 🟢 CR-3 (косметическое). Неточный прецедент в обосновании подавления

- `src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php:103` и `todo/…todo.md:104` ссылаются на прецеденты `nullsafe.neverNull` и `property.unusedType`. Факт: `nullsafe.neverNull` сегодня **не сматчен ни разу** (3 «мёртвых» подавления, см. CR-2), то есть прецедентом быть не может.
- **Что требуется:** заменить на фактические живые прецеденты — `property.unusedType` (`src/Module/AgentRunner/Infrastructure/Service/Codex/HttpsProxyBridge.php:41`) и `nullCoalesce.offset` (`…/HttpsProxyBridge.php:176`), либо упомянуть только `property.unusedType`.

### 🟢 CR-4 (косметическое). Комментарий занимает 8 строк на одну строку подавления

- `src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php:96-103` — в горячем цикле опроса 8 строк комментария подряд читаются тяжелее самого кода.
- **Что требуется:** сократить до 3–4 строк, сохранив два обязательных тезиса: (1) `isRunning()` опрашивает внешнее OS-состояние, повторный вызов в одной итерации законно может измениться; (2) проверка перед `stop()` контрактна — тест `waitForNaturalExitImmediatelyBeforeIdleStopReturnsNaturally`. Ссылку на прецеденты после CR-3 всё равно нужно править.

### 🟢 CR-5 (косметическое). Файл задачи: неточности формулировок и неполная инвентаризация диффа

- `todo/…todo.md:99` — рубрика «Прямые зависимости» включает `symfony/monolog-bridge` (транзитивный, через `symfony/monolog-bundle`); `symfony/twig-bridge` (8.1.12) не упомянут вовсе.
- `todo/…todo.md:98` — удаление `theseer/tokenizer` зафиксировано без причины; стоит добавить: «`phpunit/php-code-coverage` 14.5.2 больше не требует `theseer/tokenizer` (токенизация через `nikic/php-parser ^5.9`)».
- `todo/…todo.md:59` — перечень целей `make check` неполон: пропущены первые два шага цели `Makefile:121-124` — `coding-standard-verify` и `coding-standard-di-check`.
- `todo/…todo.md:75` — в инвентаризации диффа не указаны `.make-check.json` (маркер локального прогона, обязателен для CI-джобы `local-check-proof`) и сам файл задачи; после коммита пункт нужно закрыть.

### 🟢 CR-6 (процессное). Маркер `.make-check.json` придётся перегенерировать после коммита

- `.make-check.json:1` содержит `"commit":"d1f50a89…"` — это текущий HEAD, при этом проверки прогонялись на **грязном** дереве. `bin/local-check-proof` сравнивает `commit` маркера с `HEAD^` ветки PR: если закоммитить изменения одним коммитом и приложить маркер в текущем виде, CI упадёт.
- **Что требуется:** после коммита кода (и после исправления по CR-1) перезапустить `make check` и закоммитить маркер последним коммитом ветки — как и предписано в `Makefile:129-131`.

### 🟢 CR-7 (процессное). Метаданные задачи на время ревью

- `todo/…todo.md:18` — `reviewer` пуст; на период проверки уместно `todo-md review <ID>` (`status: review`) и `reviewer: Ревьювер Бэка Пуаро (pi)` — по `todo/AGENTS.md:114`.
- Перед запросом апрува: `todo-md done <ID>` (требует заполненного `pr`), перенос в `todo/done/`, обновление ссылок — `todo/AGENTS.md:115-118`, `AGENTS.md` (раздел «Работа с задачами»).

---

## 8. Что подтверждено положительно

- Скоуп минимален: `composer.json` не тронут, `content-hash` не изменился, посторонних правок и посторонних обновлений пакетов нет.
- Замена dev-снапшотов (`phpmd`, `pdepend`, `vimeo/psalm`) стабильными тегами — улучшение воспроизводимости, а не «понижение».
- Утверждения §9 о версиях прямых зависимостей и о причине «неподъёма» `psalm/plugin-symfony` проверены и верны (включая конфликт `^7.13@stable` против проектного `^6.16`).
- Подавление PHPStan — соразмерное, точечное, с причиной в терминах контракта; альтернативы (снятие проверки, глобальное ослабление `treatPhpDocTypesAsCertain`) хуже или недопустимы.
- Переименование helper — чистый rename, семантика и покрытие сохранены, полный набор тестов зелёный.
- Все заявленные проверки воспроизведены; скрытых ослаблений порогов и отключений правил нет.

---

## 9. Допущения и ограничения

- Времена публикации пакетов — по данным Packagist API на 2026-11-27T15:05Z; локальная зона — UTC+07:00 (`todo`-метки «21:43» = 14:43Z).
- Сетевые срезы (`composer outdated`, `prohibits`, `why-not`, `--dry-run`) выполнены в режиме чтения; `composer.lock` не изменялся (проверено `md5sum`).
- Природа срабатывания PHPStan описана как «обобщение повторного вызова чистым» — вывод сделан по минимальному репро; внутренние механизмы вывода чистоты в 2.3.7 не вскрывались (для целей ревью достаточно воспроизводимости).
- Полный `make check` целиком (одной командой) не запускался; прогнаны все его цели по отдельности + полный PHPUnit — расхождений быть не должно, но факт `make`-обвязки ревьювером не переподтверждён.
- Ревьювер не исправлял код, не делал коммитов и не пушил; рабочее дерево содержит только 5 исходных изменённых файлов + этот отчёт.

---

## 10. Источники

- [Packagist API: symfony/yaml](https://repo.packagist.org/p2/symfony/yaml.json) — время публикации `v8.1.13`.
- [Packagist API: psalm/plugin-symfony](https://repo.packagist.org/p2/psalm/plugin-symfony.json) — констрейнты `vimeo/psalm` по версиям.
- [PHPStan: booleanNot.alwaysFalse](https://phpstan.org/error-identifiers/booleanNot.alwaysFalse) — трактовка идентификатора.

---

## Чек-лист функционального ревью

- [x] Изменения сопоставлены с постановкой и критериями приёмки — есть 1 отклонение (CR-1)
- [x] Отклонения оформлены допустимым способом — **нет**, требуется CR-1/CR-2
- [x] Уязвимостей нет; секреты не протекают (`composer audit` — 0 advisory)
- [x] Строгие типы; мёртвого кода и магических чисел в диффе нет
- [x] Именование и PHPDoc соответствуют конвенциям (rename helper, комментарий на русском)
- [ ] Подавления анализаторов имеют причину — да; **техдолг-задачу — нет** (CR-2) → **закрыто в доработке, см. §11**
- [x] Тесты соответствуют конвенциям; ложного покрытия нет; поведение подтверждено полным прогоном

---

## 11. Верификационное ревью (2026-11-27 22:25)

**Роль:** Ревьювер Бэка Пуаро (code_reviewer_backend_puaro)
**Дата:** 2026-11-27 22:25 (+07:00), 15:25Z
**Объект:** те же незакоммиченные изменения ветки `task/deps-update-all` (база HEAD `d1f50a89`) **после доработки по CR-1…CR-7**: `composer.lock` (+376/−418; **54 обновления** вместо 45, 0 добавлений, 1 удаление), `src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php` (+7/−1), `tests/Unit/.../RunAgentProcessLifecycleServiceTest.php` (11/11), `todo/TASK-chore-deps-update-all.todo.md` (+26/−13), `.make-check.json` (1/1) + 2 новых файла: `todo/backlog/TASK-techdebt-phpstan-suppressions-audit.todo.md`, этот отчёт
**Тип прохода:** верификационный — проверялось **только устранение замечаний §7**; полное ревью не повторялось, новый файл отчёта не создавался
**Вердикт:** ✅ **APPROVE** (одобрено). Блокирующих и важных замечаний не осталось; ниже — 4 остатка уровня «нит» и 2 процессных условия, которые закрываются автором до/после коммита и приёмки не блокируют

### 11.1 Результаты по замечаниям

| Замечание | Уровень | Результат |
|--|--|--|
| CR-1. Неполное обновление lock + неверная запись в §9 | 🔴 блокирующее | ✅ **устранено** |
| CR-2. Подавление PHPStan без техдолг-задачи | 🟡 важное | ✅ **устранено** |
| CR-3. Мёртвый прецедент `nullsafe.neverNull` | 🟢 косметическое | ✅ **устранено** |
| CR-4. Комментарий на 8 строк | 🟢 косметическое | ✅ **устранено в существе** (нит 2) |
| CR-5. Неточности §9 и неполная инвентаризация диффа | 🟢 косметическое | ✅ **устранено** (нит 3) |
| CR-6. Маркер `.make-check.json` перегенерировать после коммита | 🟢 процессное | ⏳ **ожидаемо открыто** — по природе закрывается только после коммита (см. 11.6) |
| CR-7. Метаданные задачи на время ревью | 🟢 процессное | ✅ **reviewer заполнен**; нит 4 — `status` не переведён в `review` |

### 11.2 Подробности проверки

**CR-1 — устранено.** Повторный `composer update` выполнен, lock содержит актуальные версии в рамках констрейнтов:

- Инвентаризация `HEAD:composer.lock` ↔ рабочее дерево: **54 обновления, 0 добавлений, 1 удаление** (`theseer/tokenizer 2.0.1`), `+376/−418` строк; `composer.json` не тронут, `content-hash` идентичен (`c21f731ba34256979b24efa32d2ad702`), `plugin-api-version` 2.9.0, `packages` 45 / `packages-dev` 83. Ни одного `*-dev` в lock не осталось.
- Шесть прямых пакетов из CR-1 закрыты: `symfony/dependency-injection`, `filesystem`, `finder`, `http-client`, `http-kernel`, `yaml` — все `v8.1.12` → **`v8.1.13`**. Дополнительно подтянуты `symfony/http-foundation`, `string`, `cache`, `expression-language` → `v8.1.13`.
- `composer outdated --direct` на текущем lock: **две строки** — `psalm/plugin-symfony 5.3.0 → 5.16.3` и `vimeo/psalm 6.21.4 → 7.22.1`. Обе вне констрейнтов: `composer why-not psalm/plugin-symfony 5.16.3` → `requires vimeo/psalm (^7.13@stable)` против проектного `^6.16`; `composer prohibits vimeo/psalm 7.22.1` → требует `psalm/plugin-symfony (^5.14)`. **В рамках констрейнтов `--direct` чист** — критерий «lock содержит актуальные версии» выполнен.
- §9 файла задачи: прежнее неверное утверждение заменено на фактическое и перепроверено дословно против вывода команды («показывает только `psalm/plugin-symfony` (5.16.3, вне констрейнтов — требует Psalm 7) и `vimeo/psalm` (7.22.1, вне констрейнтов — Won't Have)»).
- Сводка §9 сверена с lock построчно: `phpunit 13.3.3 → 13.4.8`, `phpstan 2.2.13 → 2.3.7`, `deptrac 4.7.1 → 4.7.2`, `phpmd 3.x-dev → 3.0.0`, `pdepend 3.x-dev → 3.1.0`, `vimeo/psalm 6.x-dev → 6.21.4`, `symfony/monolog-bundle 4.0.2 → 4.1.0` — совпадает; «54 пакета» — совпадает.

**CR-2 — устранено.** Создан `todo/backlog/TASK-techdebt-phpstan-suppressions-audit.todo.md`:

- метаданные корректны: `status: backlog`, `branch`/`pr` пусты, `author: Ревьювер Бэка Пуаро (pi)`, `todo-md validate` → 0 ошибок / 0 предупреждений;
- содержание соответствует требованию: 4 подтверждённых «мёртвых» подавления с адресами, 4 живых (включая новое `booleanNot.alwaysFalse`), план (временное включение `reportUnmatchedIgnoredErrors: true` → зачистка → решение по флагу), DoD и риски;
- полный инвентарь `@phpstan-ignore` в `src/` (8 мест) воспроизведён ревьювером — совпадает;
- в комментарий подавления добавлена ссылка `// @techdebt TASK-techdebt-phpstan-suppressions-audit (2026-11-27)`, то есть соблюдено правило `AGENTS.md` «помечай временное решение `@techdebt` с датой и причиной».

**CR-3 — устранено.** В `ProcessLivenessWatcher.php:100-101` и в §9 файла задачи прецедент `nullsafe.neverNull` (мёртвый) заменён на фактические живые подавления `property.unusedType` (`HttpsProxyBridge.php:41`) и `nullCoalesce.offset` (`:176`). Проверка под `reportUnmatchedIgnoredErrors: true` подтверждает: оба сматчены, несматченными остались ровно 4 прежних «мёртвых» подавления — новых мёртвых не появилось.

**CR-4 — устранено в существе.** Комментарий сокращён с 8 до 6 строк (`:96-101`) — при том, что в него добавлены два новых обязательных элемента (`@techdebt`-ссылка из CR-2 и исправленные прецеденты из CR-3). Оба обязательных тезиса сохранены: (1) `isRunning()` опрашивает внешнее OS-состояние и повторный вызов в одной итерации законно может измениться; (2) проверка контрактна — ссылка на тест `waitForNaturalExitImmediatelyBeforeIdleStopReturnsNaturally`. Буквальный ориентир «3–4 строки» не достигнут (нит 2).

**CR-5 — устранено.** Проверено по пунктам:

- `symfony/monolog-bridge 8.1.6 → 8.1.10` перенесён в транзитивные с указанием источника — подтверждено: `composer why symfony/monolog-bridge` → `symfony/monolog-bundle v4.1.0 requires symfony/monolog-bridge (^7.3 || ^8.0)`; в `composer.json` пакета нет.
- `symfony/twig-bridge 8.1.5 → 8.1.12` добавлен в транзитивные — значение совпадает с lock.
- Причина удаления `theseer/tokenizer` зафиксирована и проверена эмпирически: в `HEAD:composer.lock` его требовал только `phpunit/php-code-coverage 14.3.2`; в новом lock `php-code-coverage 14.5.2` требует `nikic/php-parser ^5.9.0`, и `require` на `theseer/tokenizer` нет ни у одного пакета. Формулировка задачи точна.
- Перечень целей `make check` (строка 59) сверен с рецептом `Makefile:121-133`: добавлены пропущенные ранее `coding-standard-verify` и `coding-standard-di-check`, остальные 10 целей совпадают с фактическим рецептом — перечень полный (12 позиций).
- Инвентаризация диффа (строка 75) включает `composer.lock` (с оговоркой «`composer.json` не менялся»), `ProcessLivenessWatcher.php`, тест-lifecycle, `.make-check.json` (с обоснованием роли в CI-джобе `local-check-proof`), файл задачи и новый backlog-файл. Сверено с `git diff --numstat` — состав совпадает, посторонних файлов нет.

**CR-7 — reviewer заполнен.** `reviewer: Ревьювер Бэка Пуаро (pi)` проставлен; `todo-md validate` для файла задачи — 0 ошибок / 0 предупреждений.

### 11.3 Позитивные подтверждения верификации

- **Подавление PHPStan осталось живым и точно нацеленным** после сдвига строк (комментарий сокращён → подавление переехало со строки 104 на 102): прогон с `reportUnmatchedIgnoredErrors: true` не выдаёт `ignore.unmatchedIdentifier` для `ProcessLivenessWatcher.php:102`; единственные 4 несматченных — прежние «мёртвые» (принадлежат `TASK-techdebt-phpstan-suppressions-audit`).
- **Поведение не изменилось:** `ProcessLivenessWatcherTest` — `OK (13 tests, 38 assertions)`, `RunAgentProcessLifecycleServiceTest` — `OK (29 tests, 56 assertions)`, полный набор — `OK (1782 tests, 5380 assertions, 2 skipped)` — те же счётчики, что и в полном ревью.
- **Рабочее дерево ревьювером не изменено:** `git status --porcelain` до и после прохода идентичен (5 изменённых файлов + 2 новых); `composer install --dry-run` → `Nothing to install, update or remove` (lock консистентен); коммитов и push не было.

### 11.4 Прогоны верификационного прохода (2026-11-27, 22:15–22:25 +07)

| Проверка | Результат |
|--|--|
| `vendor/bin/phpstan analyse` (dist) | ✅ `[OK] No errors` |
| `vendor/bin/phpstan analyse` c `reportUnmatchedIgnoredErrors: true` (конфиг в `/tmp`, репозиторий не тронут) | ⚠️ ровно 4 прежних «мёртвых» подавления; новое — сматчено |
| `vendor/bin/psalm --no-cache` | ✅ `No errors found!` (163 info — предсуществующие) |
| `vendor/bin/phpunit` | ✅ 1782 теста, 5380 ассертов, 2 skipped |
| `vendor/bin/phpunit` (2 затронутых файла) | ✅ 13/38 и 29/56 |
| `vendor/bin/phpcs -n src/` | ✅ нарушений нет |
| `vendor/bin/phpmd analyze src …` | ✅ 0 violations |
| `vendor/bin/deptrac analyse` | ✅ 0 errors, 0 warnings |
| `php vendor/…/validate-md-links` | ✅ 544 файла, все внутренние ссылки валидны |
| `vendor/bin/todo-md validate` (файл задачи и backlog-файл) | ✅ 0 error / 0 warning в обоих |
| `php bin/validate-roles` | ✅ passed |
| `vendor/bin/validate-language` | ⚠️ 20 файлов выше порога — предсуществующее, не из этого диффа |
| `vendor/bin/coding-standard-verify` | ✅ OK (0.35.0 — не отстаёт) |
| `vendor/bin/coding-standard-di-check` | ✅ OK (WARN о `services.yaml` — предсуществующий) |
| `composer validate --strict` | ✅ valid |
| `composer audit` | ✅ 0 advisory |
| `composer install --dry-run` | ✅ `Nothing to install, update or remove` |
| `composer outdated --direct` | ✅ только `psalm/plugin-symfony` и `vimeo/psalm` — оба вне констрейнтов |

### 11.5 Остатки, не блокирующие одобрение

1. **[нит, backlog-файл]** `todo/backlog/TASK-techdebt-phpstan-suppressions-audit.todo.md:64` (раздел 3) адресует новое подавление как `ProcessLivenessWatcher.php:104`; после сокращения комментария оно на строке **102**. Адрес стоит поправить при первом касании задачи (остальные 7 адресов сверены и верны).
2. **[нит, комментарий]** Ориентир CR-4 «3–4 строки» буквально не выполнен: 6 строк. Содержательно каждая строка несёт обязательный элемент (причина, контракт, тест, `@techdebt`, прецеденты), поэтому замечание считаю закрытым, а не блокирующим.
3. **[нит, §9 файла задачи]** Перечисление прямых `symfony/*` сгруппировано по факту обновления; не изменявшиеся прямые компоненты (`symfony/config` 8.1.12, `symfony/framework-bundle` 8.1.12, `symfony/event-dispatcher` 8.1.5, `symfony/dotenv` 8.1.6) не перечислены. Утверждение при этом не ложно (речь об итогах обновления), а полнота проверяется командой `composer outdated --direct`, которая чиста.
4. **[нит, метаданные]** `status: in_progress` — по `todo/AGENTS.md` (п. 4) на время проверки/доработок статус переводится в `review` (`php vendor/bin/todo-md review <ID>`). Ревьювером не менялся (правки метаданных — за исполнителем).

### 11.6 Процессные условия перед приёмкой (не блокируют одобрение ревью)

1. **CR-6 остаётся открытым по природе:** `.make-check.json` ссылается на `d1f50a89` (текущий HEAD) при грязном дереве. После коммита код-правок нужно перезапустить `make check` и закоммитить маркер **последним** коммитом ветки — `bin/local-check-proof` сравнивает `commit` маркера с `HEAD^` и при несовпадении валит CI-джобу. Текущее состояние ожидаемо и не является дефектом доработки.
2. **Порядок закрытия задачи:** после зелёного CI — `todo-md done` (требует заполненного `pr`), перенос в `todo/done/`, обновление ссылок; ссылки на файл задачи из этого отчёта и из `TASK-techdebt-phpstan-suppressions-audit` при переносе обновить.

### 11.7 Границы верификации

- Полное ревью не повторялось: проверялось только устранение CR-1…CR-7; всё подтверждённое в §1–§8 остаётся в силе (правки вне заявленных замечаний в доработке не появились — состав диффа тот же плюс новый backlog-файл).
- Сетевые срезы (`composer outdated`, `prohibits`, `why-not`, `install --dry-run`) — режим чтения, 2026-11-27 15:20–15:25Z; `composer.lock` не изменялся (состав диффа идентичен до/после).
- Ревьювер не исправлял код, не делал коммитов и не пушил; единственное изменение в дереве от этого прохода — данный раздел отчёта.

**Итоговый вердикт: ✅ APPROVE.** CR-1 и CR-2 (блокирующее и важное) закрыты по существу и подтверждены прогонами; CR-3…CR-5 и CR-7 закрыты, остатки — ниты 1–4; CR-6 закрывается автором после коммита по регламенту.