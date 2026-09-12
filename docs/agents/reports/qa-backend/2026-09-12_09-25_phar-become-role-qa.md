# QA-отчёт: полная поддержка `agent:init`/`become-role` в PHAR

**Роль:** Тестировщик Бэка Хаус (qa_backend_house)
**Дата:** 2026-09-12
**Объект:** ветка `task/phar-full-become-role-install`, все незакоммиченные изменения задачи; реальная PHAR-сборка Box 4.7.0 (PHP 8.4.25, Fedora Linux 6.x); ручные сценарии в изолированных временных каталогах
**Задача:** [`todo/TASK-feat-phar-full-become-role-install.todo.md`](../../../../todo/TASK-feat-phar-full-become-role-install.todo.md)
**Code review:** одобрен без блокеров — [`docs/agents/reports/code-reviewer-backend/2026-09-12_09-10_phar-become-role-review.md`](../code-reviewer-backend/2026-09-12_09-10_phar-become-role-review.md)

---

## Вердикт

❌ **QA не одобряет в текущем виде: один дефект уровня High (QA-1).**

Функциональность установки `become-role` из PHAR в статичном расположении архива работает безупречно — все сценарии матрицы состояний, симлинк-защиты, идемпотентность, `--force`, runtime-привязка и `become-role.sh` подтверждены на реально собранном PHAR. Но задекларированный Must Have-контракт «перемещение PHAR лечится повторным `agent:init --force` из нового расположения» **не работает в дефолтном окружении** из-за персистентного кеша контейнера (детали ниже). Автотесты этот слой не покрывают, smoke изолирует кеш и тоже не видит проблему.

## Выполненные автоматические проверки

| Проверка | Результат |
|---|---|
| `vendor/bin/phpunit` (полный) | ✅ 1554 теста, 4384 assertion, 2 skipped (PharSmokeScriptTest корректно скипает build-фазу при локальном Box) |
| Точечно: `tests/Unit/Console/Module/Orchestrator/Command/InitCommandTest.php`, `tests/Integration/Module/Orchestrator/Command/InitCommandTest.php`, `tests/Integration/Module/Orchestrator/Skill/InstallBecomeRoleServiceTest.php`, `tests/Integration/Bin/PharSmokeScriptTest.php` | ✅ 50 тестов, 155 assertion |
| `vendor/bin/psalm` | ✅ No errors found |
| `PHAR_EXPECTED_VERSION=dev make phar-smoke` (реальная сборка Box) | ✅ версия `dev`, команды из checkout и временного CWD, полный контракт `agent:init` |
| `make composer-host-smoke` | ✅ Composer-ветка: относительный симлинк в физическом host |

## Ручные сценарии (изолированные каталоги `/tmp`, без внешних сервисов и секретов)

PHAR собран отдельно (`box compile`), перенесён во внешний каталог (`/tmp/.../tools/`), все host-каталоги создавались с нуля.

| # | Сценарий | Результат |
|---|---|---|
| 1 | Содержимое PHAR: `docs/agents/skills/become-role/` | ✅ `SKILL.md` (4850 B), `README.md` (7526 B), `scripts/become-role.sh` (6017 B); sha256 всех трёх идентичен git-дереву; лишних файлов нет |
| 2 | Первая установка `agent:init` в чистом host | ✅ exit 0, обычный каталог (не симлинк), полный комплект файлов, привязка `.phar-binding` = физический путь PHAR, права файлов 0644 |
| 3 | Идемпотентный повтор | ✅ exit 0, «уже установлен», inode и mtime `SKILL.md` не изменились |
| 4 | Tamper `SKILL.md` без `--force` | ✅ exit 1, подсказка `--force`, без `phar://` в выводе, target не изменён |
| 5 | `--force` после tamper | ✅ exit 0, содержимое восстановлено, привязка цела |
| 6 | Лишний файл в target + `--force` | ✅ удалён, дерево = ожидаемому |
| 7 | `bash .agents/skills/become-role/scripts/become-role.sh backend_developer_levsha` | ✅ exit 0, роль и файл роли выведены; CLI запущен по привязке |
| 8 | `become-role.sh <file>` для host-роли (роль существовала до первого PHAR-запуска) | ✅ exit 0 |
| 9 | Повреждённая привязка (путь к несуществующему PHAR) | ✅ exit 1, явная диагностика + подсказка `agent:init --force` |
| 10 | Пустая привязка | ⚠️ см. QA-2 (подтверждение CR-2): невнятная диагностика |
| 11 | Установка из другого PHAR без/с `--force` при живом старом | ❌ см. QA-1: ложное «уже установлен», привязка пишется на старый путь |
| 12 | Перемещение PHAR (`mv`) + `agent:init --force` из нового расположения | ❌ см. QA-1: exit 1 «skill не найден или неполон в PHAR» |
| 13 | target = симлинк на внешний каталог, без `--force` | ✅ exit 1 конфликт, симлинк и назначение не тронуты |
| 14 | target = симлинк, `--force` | ✅ сам симлинк заменён каталогом; назначение не прочитано/не изменено (sentinel-файл цел, в target только ожидаемое дерево) |
| 15 | `.agents` = симлинк (без и с `--force`) | ✅ exit 1 «.agents-структура недопустима», guard не обходится `--force`, по назначению 0 объектов |
| 16 | `.agents/skills` = симлинк | ✅ аналогично, 0 объектов по назначению |
| 17 | Артефакты staging/backup во всех host после всех сценариев | ✅ 0 артефактов `.become-role.*` |
| 18 | Мусор в `/tmp` после smoke | ✅ `phar-smoke` и `composer-host-smoke` чистят за собой (trap) |

---

## QA-1 — High (блокирует): повторная установка из перемещённого PHAR не работает из-за персистентного кеша контейнера

**Контракт:** Must Have п.3 задачи — «Перемещение или удаление PHAR после установки требует повторного `agent:init --force` из нового расположения». Публичная документация (`docs/guide/troubleshooting.md`, README) повторяет это как путь восстановления.

**Воспроизведение** (дефолтное окружение, без `APP_CACHE_DIR`):

```bash
mkdir /tmp/host && cd /tmp/host
php /tmp/tools/task-orchestrator.phar agent:init            # exit 0, привязка = /tmp/tools/...
mv /tmp/tools/task-orchestrator.phar /tmp/tools2/task-orchestrator.phar
php /tmp/tools2/task-orchestrator.phar agent:init --force
```

**Expected:** exit 0, `.agents/skills/become-role/.phar-binding` = `/tmp/tools2/task-orchestrator.phar`, `become-role.sh` работает.

**Actual:** exit 1:

```
[ERROR] Встроенный skill become-role не найден или неполон в PHAR (обязательны
        SKILL.md и scripts/become-role.sh). Обновите PHAR-дистрибутив.
```

Установка не выполняется; диагностика **ложно обвиняет дистрибутив** (архив полный и корректный).

**Сопутствующее проявление (старый PHAR не удалялся, запуск копии из нового пути):**
- `agent:init` без `--force` → **exit 0, ложное «уже установлен»** (ожидался конфликт: привязка указывает на другой физический файл);
- `agent:init --force` → exit 0, но привязка **перезаписывается на путь старого PHAR**, `become-role.sh` продолжает использовать старый архив.

**Root cause:** параметры `task_orchestrator.package_dir` и `task_orchestrator.phar_path` вычисляются один раз при первой компиляции контейнера и замораживаются в персистентном кеше `/tmp/task-orchestrator/<xxh64(projectRoot)>/var/cache/task-orchestrator/<version>/<env>` (`src/Kernel.php`: `getCacheDir()` → `writableRoot()`). Кеш-ключ зависит только от projectRoot, не от физического пути PHAR: после перемещения архива скомпилированный контейнер продолжает ссылаться на мёртвый `phar://<старый-путь>/...`.

**Почему не поймали:** integration-тест `installRequiresForceWhenPharMovedAndForceUpdatesBinding` проверяет сервис напрямую (внеконтейнерная инъекция `pharPath`); `bin/phar-smoke` специально задаёт свежий `APP_CACHE_DIR` каждому сценарию и не содержит сценария перемещения PHAR. Слой контейнера/кеша не покрыт никем.

**Workaround (недокументированный):** `rm -rf /tmp/task-orchestrator/<hash>` → после этого `--force` из нового расположения работает корректно (проверено: привязка обновилась, `become-role.sh` зелёный).

**Severity: High.** Публичный Must Have-контракт задачи неработоспособен в дефолтном окружении; диагностика вводит в заблуждение; восстановление требует недокументированной чистки системного каталога.

**Рекомендации (любая из сторон):**
- сделать производные от `Phar::running()` параметры runtime-значениями по образцу `task_orchestrator.locale` (env-процессор, `src/Kernel.php`) — тогда кеш не замораживает пути; или включить физический путь PHAR в ключ кеш-каталога;
- краткосрочно: в `bin/phar-smoke` добавить сценарий перемещения PHAR (`mv` + переустановка из нового пути без `APP_CACHE_DIR`-изоляции кеша между «перемещениями»), чтобы поймать регрессию.

## QA-2 — Minor (подтверждает CR-2 ревью): пустая runtime-привязка даёт невнятную диагностику

**Воспроизведение:** `: > .agents/skills/become-role/.phar-binding && bash .agents/skills/become-role/scripts/become-role.sh backend_developer_levsha`

**Actual:** exit 1, stderr:

```
.agents/skills/become-role/scripts/become-role.sh: строка 87: /tmp/host/bin/task-orchestrator: Нет такого файла или каталога
Ошибка: не удалось получить данные роли "backend_developer_levsha".
```

Скрипт с пустой привязкой уходит на `bin/task-orchestrator` host-проекта, которого в managed-копии нет. Никакой угрозы записи/чтения вне проекта нет, exit корректный; страдает только диагностика. Небезопасность отсутствует, кейс маловероятен (файл создаётся только сервисом и участвует в побайтовом сравнении — повторный `agent:init` честно даёт конфликт, `--force` лечит, проверено). Severity: Minor. Рекомендация — как в CR-2: различать «привязки нет» (source/Composer — штатно) и «привязка пуста/битая» (явная ошибка с подсказкой `--force`).

## QA-3 — Observation (вне scope задачи): заморозка `roles_dir` в кеш скрывает host-роли

Та же механика кеша: если первый запуск PHAR в host произошёл **до** появления `docs/agents/roles/team/` в host, параметр `task_orchestrator.roles_dir` заморожен на `phar://...` — host-роли, созданные позже, не резолвятся `agent:role-skills` (искать только внутри PHAR) до чистки кеша. Продемонстрировано: host1 (роль после первого запуска) — роли host невидимы; host2 (роль до первого запуска) — резолвятся корректно. `become-role.sh <file>` для host-ролей из PHAR работает только во втором случае.

Поведение предсуществующее (задача Kernel-кеш не меняла, контракты `agent:role-skills` — out of scope), но оно ограничивает практическую ценность заявленной job story («настраивать роли и навыки без Composer-дистрибутива»). Рекомендация: отдельная задача техдолга (runtime-резолв layout-параметров или инвалидация кеша при смене layout) — не блокер этого PR.

## Source/Composer регрессии

✅ `tests/Integration/Module/Orchestrator/Command/InitCommandTest.php` (относительный симлинк, идемпотентность, конфликт/`--force`) — зелёный; `make composer-host-smoke` на физической Composer-копии — зелёный;fallback «симлинк → копия» отсутствует, как требует задача.

## Качество тестов и smoke

**Сильные стороны:**
- 21 integration-тест сервиса закрывает всю матрицу PHAR-состояний: конфликт-вариации (лишний/изменённый/отсутствующий файл, target-симлинк, target-файл, внутренний симлинк), guard-родители, неполный источник без частичного target, read-only, устойчивая диагностика без `phar://` и без абсолютного источника, rollback через инъекцию отказа rename (protected-шов `renamePath()`), сохранение backup при невозможности отката, отсутствие артефактов.
- Honest skips: root-guard тесты честно `markTestSkipped` при правах root.
- `bin/phar-smoke`: позитивный контракт вместо старого fail-fast; trap-очистка, изоляция кеша per-scenario, точная проверка версии, проверка inode-идемпотентности, конфликт, `--force`, содержимое привязки, реальный `bash become-role.sh` из произвольного CWD.

**Слабые места (не блокеры):**
1. Слой контейнера/кеша PHAR не покрыт ничем — именно там прячется QA-1. Integration-тест перемещения проверяет сервис напрямую.
2. Измеримость покрытия: `#[CoversClass(InitCommand::class)]` в integration-тесте команды исключает `InstallBecomeRoleService` из отчёта покрытия, хотя symlink-ветка выполняется именно там. Замер (pcov, только три целевых тест-файла): `InitCommand` 100% строк; `InstallBecomeRoleService` 74% строк в отчёте по `CoversClass`-фильтру, из них symlink-ветка (строки 101–142) реально покрыта, но невидима для отчёта. Рекомендация: добавить `CoversClass(InstallBecomeRoleService::class)` в integration-тест команды.
3. Непокрытые defensive-ветки сервиса: `pharPath` null/пустой (стр. 157), mismatch staging (205), IOException при удалении backup (285), отказ chmod (470), отказ commitRename (490). Частично достижимы тестами; 490 семантически эквивалентен проверенной инъекции `renamePath=false`. Для Presentation-слоя допустимо (конвенционные 80% формально относятся к Domain/Application), но желательно добить до 80%+.

## Резюме

| Область | Статус |
|---|---|
| PHAR-сборка и ресурсы | ✅ |
| `agent:init`: установка/идемпотентность/конфликт/`--force` (статичный PHAR) | ✅ |
| `become-role.sh` через bash | ✅ |
| Симлинк-защиты (target + родители) | ✅ |
| staging/backup/rollback, артефакты | ✅ |
| Source/Composer регрессии | ✅ |
| Привязка: повреждённая | ✅ (явная диагностика) |
| Привязка: пустая | ⚠️ QA-2 (Minor, = CR-2) |
| **Перемещение PHAR → `--force` из нового расположения** | ❌ **QA-1 (High)** |
| Автопроверки (phpunit/psalm/smoke) | ✅ |

**Решение QA:** устранить QA-1 (или, по решению тимлида, явно понизить контракт перемещения и задокументировать workaround с чисткой кеша — но текущая формулировка Must Have п.3 не выполняется). QA-2 — желательно в рамках задачи, допустимо отдельной полировкой. QA-3 — отдельная задача техдолга вне этого PR.
