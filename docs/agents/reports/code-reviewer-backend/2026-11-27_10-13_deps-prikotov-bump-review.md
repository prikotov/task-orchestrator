# Полное ревью ветки task/deps-prikotov-bump (подъём prikotov/* + security-обновление symfony/twig)

**Роль:** Ревьювер Бэка Пуаро (code_reviewer_backend_puaro)
**Дата:** 2026-11-27
**Объект:** незакоммиченные изменения ветки `task/deps-prikotov-bump` (база HEAD `b1663539`): `.coding-standard.php` (+76/−?), `.github/workflows/secret-scan-pr-content.yml` (+30/−?), `composer.json` (2 констрейнта), `composer.lock` (28 пакетов), `docs/agents/reports/code-reviewer-backend/2026-11-26_21-05_watchsubagent-stall-flaky-review.md` (1 ссылка), `todo/TASK-chore-deps-prikotov-bump.todo.md` (+5), новый файл `todo/TASK-techdebt-vo-private-properties.todo.md`
**Задача:** [todo/done/TASK-chore-deps-prikotov-bump.todo.md](../../../../todo/done/TASK-chore-deps-prikotov-bump.todo.md) (перенесена в `done` после merge PR #514)

---

## Вердикт

**🟡 Одобрено после устранения двух minor-замечаний в файлах задач (строки ниже). Блокирующих замечаний нет.**

Существо изменений корректно: констрейнты и lock соответствуют постановке и решениям владельца 2026-11-27, посторонних обновлений нет, проверки не ослаблены, PHP-код проекта не правился, известное красное состояние (PHPCS, правило VO) честно зафиксировано и эскалировано. Замечания — только точность формулировок в файлах задач.

---

## 1. Соответствие диффа постановке и решениям владельца 2026-11-27

| Пункт | Факт | Статус |
|--|--|--|
| `prikotov/coding-standard` | констрейнт `^0.34.0` → `^0.35.0`, lock/установлено `v0.35.0`; `coding-standard-verify` — OK («0.35.0 is not behind the latest release 0.35.0») | ✅ |
| `prikotov/git-workflow` | `^0.4.0` → `^0.4.3`, lock/установлено `v0.4.3` (последний; в `composer outdated` отсутствует) | ✅ |
| `prikotov/todo-md` | `^0.7.0` без сдвига, lock `v0.7.0` не менялся — уже последний | ✅ |
| Security: 8× `symfony/*` | `config`, `expression-language`, `filesystem`, `framework-bundle`, `http-client`, `http-foundation`, `http-kernel`, `routing` → `v8.1.12` (было `v8.1.5`/`v8.1.6` — совпадает с комментарием задачи); констрейнты `composer.json` по symfony не менялись (`^8.0`) | ✅ |
| Security: `twig/twig` | `v3.28.0` → `v3.31.1`, констрейнт не менялся | ✅ |
| Отмена Won't Have | зафиксирована в разделе 9 и истории задачи со ссылкой на решение владельца 2026-11-27 | ✅ |
| Синхронизация шаблонов init-скриптами | `.github/workflows/secret-scan-pr-content.yml` **байт-в-байт идентичен** шаблону `vendor/prikotov/git-workflow/templates/workflows/secret-scan-pr-content.yml` (0.4.3, добавлен `api_retry` для title/body); `.coding-standard.php` — структура шаблона 0.35.0 (новая секция `metrics` совпадает с шаблоном, отличия только в комментариях) + сохранённая проектная секция `language`; `docs/conventions/` и `docs/git-workflow/` идентичны vendor-копиям (каталоги gitignored — `docs/.gitignore`, потому отсутствие диффа в git закономерно) | ✅ |
| Фикс битой ссылки | `docs/agents/reports/code-reviewer-backend/2026-11-26_21-05_watchsubagent-stall-flaky-review.md:6` — ссылка переведена на `todo/done/TASK-test-watchsubagent-stall-flaky.todo.md`; целевой файл существует, относительный путь (4 уровня вверх) корректен; `make md-links` — зелёный | ✅ |
| Эскалация правила VO | новая задача `todo/TASK-techdebt-vo-private-properties.todo.md` создана, `depends_on: TASK-chore-deps-prikotov-bump`, статус `todo`, обоснование ссылкой на п. 4.3 плана исходной задачи и прецедент 0.34.1 | ✅ |

## 2. composer.json / composer.lock

- `composer validate --strict --no-check-publish` — valid; `composer install --dry-run` — «Nothing to install, update or remove» (lock консистентен, установленное совпадает с lock).
- `composer audit` — **«No security vulnerability advisories found»** (0 advisory).
- Полная инвентаризация изменений lock (сравнение HEAD ↔ working tree, 28 пакетов): 3× `prikotov/*` (coding-standard, git-workflow; todo-md не менялся), 9 advisory-пакетов, транзитивные `symfony/cache`, `dependency-injection`, `var-dumper`, `finder`, `yaml` → `v8.1.12`; `error-handler`, `var-exporter` → `v8.1.10` (проверено через packagist: `v8.1.10` — последние выпущенные патчи этих компонентов); contracts `v3.7.x` → `v3.8.1`; polyfills (deepclone, mbstring, php85); dev-транзитивные `slevomat/coding-standard` `8.31.3` → `8.32.0` и `phpstan/phpdoc-parser` `2.4.1` → `2.4.2` (легитимны: `composer why` — slevomat требуется `prikotov/coding-standard ^8.0`, phpdoc-parser — slevomat/deptrac).
- **Посторонних обновлений нет**: пакеты без advisory (`symfony/console`, `lock`, `process`, `translation`, `twig-bundle`, phpunit, psalm-плагин и пр.) остались на прежних версиях — точно в рамках решения владельца «только advisory-пакеты». Добавленных/удалённых пакетов нет.
- Замечание по полноте летописи — см. 🟡-2.

## 3. Проверки не отключены и не ослаблены

- `.coding-standard.php`: программное сравнение секции `language` HEAD ↔ working tree — `paths`, `max_ratio` (0.08), `allowlist`, `exclude` **идентичны**; добавлена только `dictionary` (`hook`, `god object`) из шаблона — на ratio не влияет. Секция `metrics` — новинка шаблона 0.35.0, пороги идентичны шаблону, не ослаблены.
- `phpcs.xml.dist`, `psalm.xml`, `depfile.yaml`, `phpunit.xml.dist`, `Makefile` — **не изменялись**. Правило `ValueObjectStructureSniff` активно и срабатывает (172 ошибки) — не отключено, подавлений в коде нет.
- Workflow secret-scan: усиление (`api_retry`, падение после 3 неудач вместо продолжения на пустом файле), не ослабление; файл идентичен upstream-шаблону.

## 4. Файлы задач

- `TASK-chore-deps-prikotov-bump`: раздел 9 и история изменений соответствуют фактам (версии, команды, результаты проверок — перепроверено, совпадает вплоть до чисел phpunit). Статус `in_progress`, `pr:` пуст — корректно на текущий момент; по решению владельца перевод в `done` + перенос в `todo/done/` выполняется **при открытии PR** — напоминание исполнителю.
- `TASK-techdebt-vo-private-properties`: `make validate-todo` — 0 ошибок/предупреждений; список «Нарушители» (раздел 2) **пофайлово совпадает** с выводом `phpcs --report=summary` (26 файлов, 172 ошибки); ссылки на `docs/conventions/core-patterns/value-object.md` и `vendor/bin/phpstan` валидны (phpstan установлен, 2.2.13). Обоснование эскалации (объём, прецедент 0.34.1, п. 4.3 плана) — состоятельно.
- **Однако арифметика плана (раздел 4) противоречит собственному списку нарушителей и фактическому выводу PHPCS** — см. 🟡-1.

## 5. Известное состояние и отсутствие замалчиваний

- PHP-код проекта (`src/`, `apps/`, `tests/`) под новое правило **не правился**: `git diff HEAD -- src/ apps/ tests/` пуст.
- Красное состояние не замалчивается: 172 ошибки / 26 VO зафиксированы в обеих задачах, эскалированы отдельной задачей с приоритетом P1.

### Результаты проверок (прогнаны ревьювером локально 2026-11-27)

| Проверка | Результат |
|--|--|
| `vendor/bin/phpunit` | ✅ OK: 1779 тестов, 5335 assertions, 2 skipped (совпадает с заявленным) |
| `vendor/bin/psalm --no-cache` | ✅ No errors found (163 info — не ошибки) |
| `vendor/bin/phpcs -n src/` | 🔴 172 ошибки / 26 файлов — все `ValueObject properties must be private` (известное, эскалировано) |
| `vendor/bin/deptrac` | ✅ 0 errors, 0 warnings |
| `make phpstan` | ✅ No errors |
| `make phpmd` | ✅ No violations |
| `vendor/bin/coding-standard-verify` | ✅ OK |
| `vendor/bin/coding-standard-di-check` | ✅ OK (WARN о services.yaml — предсуществующий, не из этого диффа) |
| `make md-links` | ✅ All internal links valid |
| `make validate-todo` | ✅ оба файла задач: 0 errors, 0 warnings |
| `make validate-roles` | ✅ passed |
| `vendor/bin/validate-language` | ⚠ warning mode, 20 файлов выше ratio — предсуществующее состояние (секция `language` не менялась) |
| `composer audit` | ✅ 0 advisory |

Итог: подтверждено заявленное «`make check` красный **только** на PHPCS-правиле VO».

---

## Замечания (Change Request)

### 🟡-1. Некорректные числа в плане TASK-techdebt-vo-private-properties (minor, исправить до открытия PR)

**Файл:** `todo/TASK-techdebt-vo-private-properties.todo.md`

- **Строка 72** (п. 4.3): «ChainExecution: **16 VO (99 свойств)**» — фактически **15 VO, 98 ошибок** (сумма по списку нарушителей раздела 2 и вывод `phpcs --report=summary`: 13+2+5+3+7+4+10+5+8+9+3+1+17+7+4 = 98, файлов 15). Исполнитель будет искать несуществующий 16-й VO.
- **Строка 73** (п. 4.4): «DynamicLoop: 8 VO (**61 свойство**)» — фактически **62** (6+9+12+5+19+6+3+2 = 62).

Контрольная сумма 5+7+98+62 = 172 сходится; ошибочны только модульные итоги плана. Исправить на «15 VO (98 свойств)» и «8 VO (62 свойства)».

### 🟡-2. Неполный перечень транзитивных обновлений в летописи security-части (minor)

**Файл:** `todo/TASK-chore-deps-prikotov-bump.todo.md`, **строка 97** (раздел 9, «Итог выполнения 2026-11-27, security-часть»).

В скобках транзитивных обновлений не хватает фактически обновлённых `symfony/finder` (`v8.1.5` → `v8.1.12`) и `symfony/yaml` (`v8.1.11` → `v8.1.12`) — это **прямые** зависимости проекта (`^8.0`), подтянутые `--with-all-dependencies`; для аудита «посторонних обновлений нет» перечень должен быть полным. Также нигде не упомянуты dev-транзитивные `slevomat/coding-standard` `8.31.3` → `8.32.0` и `phpstan/phpdoc-parser` `2.4.1` → `2.4.2` (обновлены вместе с `prikotov/coding-standard`; легитимность подтверждена `composer why`). Дополнить перечень.

---

## Наблюдения (не замечания к этому диффу)

- `.github/workflows/secret-scan-pr-content.yml:95,99` — сбор комментариев PR по-прежнему идёт с `2>/dev/null || true` (пропуск при сбое API), что контрастирует с новым комментарием «Пропуск сбора контента недопустим». Строки **предсуществующие**, файл байт-в-байт совпадает с шаблоном `prikotov/git-workflow` 0.4.3 — правка в проекте сломала бы синхронизацию с шаблоном. Кандидат на issue/задачу в upstream-пакет `prikotov/git-workflow`, не в этом PR.

## Допущения и ограничения проверки

1. Число «34 advisory до обновления» ретроактивно не перепроверялось (аудит старого lock не запускался); проверено текущее состояние — 0 advisory.
2. Оценка «~90 файлов-потребителей (59 production)» в techdebt-задаче — оценка исполнителя, точно не верифицируется без выполнения рефакторинга; точные числа 26 VO / 172 ошибки верифицированы.
3. Полный `make check` единой командой не запускался (побочный эффект — перезапись маркера `.make-check.json` в ревьюируемом дереве); вместо этого прогнаны все его цели по отдельности — результат эквивалентен.
4. Ревью проводилось по незакоммиченному working tree ветки; коммитов с содержательными изменениями в ветке пока нет — при коммите состав файлов должен совпасть с проверенным (6 изменённых + 1 новый).

## Риски

- До merge `TASK-techdebt-vo-private-properties` `make check` остаётся красным на `main` (PHPCS) — принято владельцем, зафиксировано в задаче; CI-job маркера `local-check` для кодовых PR будет падать на этом барьере.
- Перевод задачи в `done` при открытии PR (решение владельца) означает, что критерий «make check зелёный» из DoD исходной задачи закрывается другой задачей — расхождение оформлено решением владельца явно, допустимо.

---

## Верификационное ревью

**Роль:** Ревьювер Бэка Пуаро (code_reviewer_backend_puaro)
**Дата:** 2026-11-27 10:18
**Объект:** доработка замечаний 🟡-1 и 🟡-2 в working tree ветки `task/deps-prikotov-bump` (база HEAD `b1663539`): `todo/TASK-techdebt-vo-private-properties.todo.md`, `todo/TASK-chore-deps-prikotov-bump.todo.md`
**Метод:** сверка формулировок с фактическим выводом `vendor/bin/phpcs --standard=phpcs.xml.dist -n --report=summary src/` и диффом `composer.lock` (HEAD ↔ working tree). Код ревьювером не правился — только верификация.

### 🟡-1. Модульные итоги плана TASK-techdebt-vo-private-properties — ✅ устранено

Фактический вывод PHPCS (прогнан 2026-11-27 10:18): **172 ошибки / 26 файлов**, все `ValueObject properties must be private`. Помодульная сверка:

| Модуль | Факт PHPCS (файлов / ошибок) | План задачи (раздел 4) | Статус |
|--|--|--|--|
| AgentRunner | 1 / 5 | п. 4.1: «1 VO (5 свойств)» | ✅ |
| ChainDefinition | 2 / 7 (3+4) | п. 4.2: «2 VO (7 свойств)» | ✅ |
| ChainExecution | **15 / 98** (13+2+5+3+7+4+10+5+8+9+3+1+17+7+4) | п. 4.3: «**15 VO (98 свойств)**» — исправлено с «16 VO (99 свойств)» | ✅ |
| DynamicLoop | **8 / 62** (6+9+12+5+19+6+3+2) | п. 4.4: «**8 VO (62 свойства)**» — исправлено с «61 свойство» | ✅ |
| Итого | 26 / 172 | 5+7+98+62 = 172 — контрольная сумма сходится, итог не менялся | ✅ |

Список «Нарушители» (раздел 2) по-прежнему пофайлово совпадает с выводом PHPCS (26 строк, числа идентичны). `make validate-todo` — 0 ошибок / 0 предупреждений.

### 🟡-2. Перечень транзитивных обновлений в разделе 9 TASK-chore-deps-prikotov-bump — ✅ устранено

Раздел 9 («Итог выполнения 2026-11-27, security-часть») дополнен абзацем «Полный перечень обновлений (для аудита посторонних)». Сверка с фактическим диффом `composer.lock` (HEAD ↔ working tree):

| Пакет | Заявлено в задаче | Факт lock (было → стало) | Статус |
|--|--|--|--|
| `symfony/finder` | `v8.1.5` → `v8.1.12`, прямая зависимость, `--with-all-dependencies` | `v8.1.5` → `v8.1.12` | ✅ |
| `symfony/yaml` | `v8.1.11` → `v8.1.12`, прямая зависимость, `--with-all-dependencies` | `v8.1.11` → `v8.1.12` | ✅ |
| `slevomat/coding-standard` | `8.31.3` → `8.32.0`, dev-транзитивный, вместе с `prikotov/coding-standard` | `8.31.3` → `8.32.0` | ✅ |
| `phpstan/phpdoc-parser` | `2.4.1` → `2.4.2`, dev-транзитивный, ссылка на `composer why` | `2.4.1` → `2.4.2` | ✅ |

Классификация корректна: `symfony/finder` и `symfony/yaml` — действительно прямые зависимости проекта (`composer.json`, `^8.0`), slevomat/phpdoc-parser — dev-транзитивные (легитимность подтверждена `composer why` в первичном ревью, раздел 2 отчёта). Перечень транзитивных обновлений теперь полный и совпадает с инвентаризацией lock из первичного ревью.

### Контроль отсутствия посторонних правок при доработке

- `git diff HEAD -- src/ apps/ tests/` — пуст: PHP-код по-прежнему не правился.
- Состав изменённых файлов не изменился относительно первичного ревью: 6 изменённых + 2 новых (отчёт ревью и techdebt-задача); доработка затронула только два файла задач.
- `make validate-todo` — оба файла задач: 0 errors, 0 warnings.

### Вердикт верификации

**🟢 Approve. Оба замечания (🟡-1, 🟡-2) устранены полностью и точно; новых замечаний нет.**

Числа плана techdebt-задачи теперь совпадают с фактическим выводом PHPCS вплоть до свойства; летопись транзитивных обновлений в разделе 9 полна и верифицирована по lock. Итоговый вердикт первичного ревью («одобрено после устранения двух minor-замечаний») повышен до окончательного: **изменения одобрены к коммиту и открытию PR**.

Напоминания исполнителю (не замечания, из первичного ревью):
1. При открытии PR — перевод TASK-chore-deps-prikotov-bump в `done`, перенос в `todo/done/`, актуализация ссылок (решение владельца 2026-11-27).
2. Состав коммита должен совпасть с проверенным деревом (6 изменённых + 2 новых файла, включая этот отчёт).
3. Красный PHPCS (172 ошибки VO) — известное принятое состояние до merge TASK-techdebt-vo-private-properties.

Проверки PHPUnit/Psalm повторно не прогонялись: доработка ограничена текстами файлов задач (`todo/*.md`), поведение кода, конфигурации и скрипты не затронуты — допустимое исключение по AGENTS.md («Предварительные проверки», п. 1); результаты первичного прогона (раздел «Результаты проверок») остаются в силе. PHPCS прогнан точечно как источник истины для верификации чисел замечания 🟡-1.
