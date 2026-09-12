# Code review: полная поддержка `agent:init`/`become-role` в PHAR

**Роль:** Ревьювер Бэка Пуаро (code_reviewer_backend_puaro)
**Дата:** 2026-09-12
**Объект:** ветка `task/phar-full-become-role-install`, все незакоммиченные изменения (22 изменённых файла + новые `apps/console/src/Module/Orchestrator/Skill/`, `tests/Integration/Module/Orchestrator/Skill/`, архитектурный отчёт, задача из `todo/backlog/`)
**Задача:** [`todo/TASK-feat-phar-full-become-role-install.todo.md`](../../../../todo/TASK-feat-phar-full-become-role-install.todo.md)
**Архитектурное решение:** [`docs/agents/reports/system-architect/2026-09-12_07-43_phar-become-role-design.md`](../system-architect/2026-09-12_07-43_phar-become-role-design.md)

---

## Вердикт

✅ **Одобряю.** Блокирующих замечаний нет. Три необязательных замечания уровня Minor/Nit и одно процессное напоминание перечислены ниже — на слияние не влияют.

## Проверенные аспекты

### 1. Соответствие задаче и архитектурному решению

- Все Must-требования реализованы и подтверждены проверяемыми артефактами: PHAR содержит полный каталог skill (`box.json.dist`: `docs/agents/skills/become-role`); механизм выбирается по `%task_orchestrator.is_phar%`; runtime-привязка `.phar-binding` создаётся только в PHAR-ветке; симлинки на `phar://` не создаются.
- Реализация соответствует отчёту архитектора по всем ключевым пунктам: специализированный Presentation-сервис (не универсальный установщик), полное сравнение материализованного дерева (относительные пути + типы + sha256), материализация в уникальном staging рядом с target, двухфазная замена `target → backup → staging → target` с откатом, native `rename` без copy-fallback, честное ограничение гарантий (не заявляется абсолютная атомарность).
- Out of Scope соблюдён: source/Composer-симлинк не изменён (сравнение флоу со старым `InitCommand` подтверждает сохранение сообщений и поведения), другие команды не затронуты, fallback `symlink → copy` отсутствует.

### 2. Границы Presentation/Kernel и DI

- `InitCommand` — тонкая транспортная граница по конвенции [console-command](../../../../docs/conventions/layers/presentation/console-command.md): `final`, `#[AsCommand]`, разбор `--force` до вызова, `LockFactory` (разрешённая зависимость Presentation), вывод через `SymfonyStyle`, корректные коды завершения, освобождение блокировки в `finally`.
- `InstallBecomeRoleServiceInterface`/`InstallBecomeRoleService` в Presentation (`apps/console/src/Module/Orchestrator/Skill/`) — соответствует решению архитектора; именование `Install + BecomeRole + Service` — по конвенции Service; явный alias `Interface -> Implementation` в `config/services.yaml` — по конвенции.
- `BecomeRoleInstallResultDto` — `final readonly`, только данные, без методов — по конвенции DTO. `BecomeRoleInstallOutcomeEnum` — backed string enum без логики, маппинг исход → код завершения в команде — по конвенции Enum.
- `src/Kernel.php` — только публикация параметра `task_orchestrator.phar_path` через `Phar::running(false)` (без парсинга `phar://`), с guard `class_exists(\Phar::class)`. Вне PHAR — `null`.
- `bin/task-orchestrator` — PHAR-контекст через `Phar::running(false)`, host-project CWD semantics как в vendor-контексте, плюс защита от `getcwd() === false`. Регрессии standalone/vendor-режимов нет.
- Deptrac: 0 ошибок. Слои Domain/Application/Infrastructure не затронуты.

### 3. Безопасность файловых операций, staging/backup/rollback, гонки

- Симлинки нигде не разыменовываются: `guardRealParents()` (lstat-семантика для `.agents`/`.agents/skills`), `is_link`-проверки в `copyTree`/`collectSnapshot`, `targetExists()` = `is_link || file_exists`, target-симлинк при `--force` перемещается сам (`Filesystem::remove` для симлинка делает `unlink` ссылки — подтверждено по `vendor/symfony/filesystem/Filesystem.php::doRemove`).
- Коммит-фаза — только native `@rename` соседних путей одного раздела; заявление об отказе от `Filesystem::rename` подтверждено vendor-кодом (fallback на `mirror()+remove()` при неудаче rename каталога).
- Откат: при неудаче `staging → target` — возврат `backup → target`; при неудаче отката backup сохраняется с явной диагностикой пути восстановления. Staging чистится в `finally` при любом исходе; backup удаляется только после успешного переключения (неудачное удаление не отменяет установку и дополняет сообщение).
- Диагностики не раскрывают абсолютный `phar://`-путь источника (ошибка чтения каталога в `copyTree` оперирует относительным путём `SKILL_RELATIVE_PATH`) — подтверждено тестами `assertStringNotContainsString('phar://')` и smoke-проверкой.
- Гонки: кооперативный параллелизм закрыт `LockFactory` (`LOCK_RESOURCE`); TOCTOU с внешними процессами честно исключён из гарантий (риски задачи). Идентификация staging/backup через `random_bytes(8)` достаточна.
- Идемпотентность честная: сравнение по полному дереву, а не по sentinel-файлу; `mtime`/executable-bit исключены из эквивалентности осознанно (запуск через `bash`).

### 4. PHAR runtime-binding и host-project CWD

- `.phar-binding` — единственный служебный файл внутри exact target; хеш в expected-снапшоте согласован с содержимым (`hash('sha256', $pharPath)` = `hash_file` от записанной строки).
- `become-role.sh`: привязка используется только при её наличии; отсутствие PHAR по привязанному пути — fail-fast с понятным следующим шагом (`agent:init --force` из нового расположения). Смена физического пути PHAR корректно даёт конфликт при повторном `agent:init` и обновление привязки с `--force` (тест `installRequiresForceWhenPharMovedAndForceUpdatesBinding`).
- CWD-семантика PHAR (= vendor-режим) подтверждена smoke-запуском из изолированного `init-host`.

### 5. Source/Composer regression

- Флоу symlink-ветки перенесён без изменений: те же проверки (`SKILL.md`), те же сообщения, относительный симлинк через `Path::makeRelative`, `alreadyInstalled`/`conflict`/`--force`. Интеграционный `tests/Integration/Module/Orchestrator/Command/InitCommandTest.php` сохранён как регрессионный (реальный сервис + реальная ФС).

### 6. Тестовое покрытие и smoke-проверки

- Unit `InitCommandTest` (8 тестов): делегирование, `--force`, `alreadyInstalled` → SUCCESS, conflict → FAILURE, fatal → FAILURE, занятая блокировка (install не вызывается), освобождение блокировки.
- Integration `InstallBecomeRoleServiceTest` (20 тестов): первая установка + привязка; идемпотентность (inode + mtime); конфликты (лишний/изменённый/отсутствующий файл, target-симлинк, target-файл, внутренний симлинк); `--force` (дерево, симлинк-объект, файл-объект, обновление привязки); guard-родители (`.agents`/`.agents/skills` симлинк/файл); неполный источник без частичного target; симлинк в источнике; ошибка чтения каталога с устойчивой диагностикой; read-only родитель; откат при неудачном переключении; сохранение backup при невозможности отката; отсутствие артефактов.
- Тестовый шов `protected renamePath()` с инъекцией отказа 2-го/3-го rename — управляемый и локальный; класс не `final` задокументирован намеренно (конвенция Service требования `final` не содержит).
- `bin/phar-smoke` переделан с негативного fail-fast на полный позитивный контракт: установка, побайтовая проверка содержимого и привязки, идемпотентность через inode, конфликт, `--force`, отсутствие staging/backup-артефактов, реальный запуск `become-role.sh` из изолированного host-каталога; очистка через `trap cleanup EXIT`. Тестовая роль `backend_developer_levsha` действительно без `skills:` (проверен frontmatter) — заявление в комментарии корректно.

### 7. Документация

Согласованно обновлены: `README.md`/`README.en.md`/`README.zh.md` (матрицы без устаревшего «Not supported», `.phar-binding`-ограничение), `docs/guide/cli.md` (механизмы установки, пример PHAR из `~/tools`, запуск через `bash`, обновлённые коды завершения), `docs/guide/troubleshooting.md` (старые сборки ≤ v0.6.0 / перемещённый PHAR / конфликт без `--force`), `docs/agents/skills/become-role/{README,SKILL}.md`, `docs/agents/skills/task-orchestrator/README.md`, `CHANGELOG.md` (Unreleased), ссылка в `docs/releases/v0.2.0/release-plan.md`. Устаревших утверждений о недоступности PHAR `agent:init` поиском не найдено.

### 8. Выполненные проверки (текущее состояние ветки)

| Проверка | Результат |
|---|---|
| `vendor/bin/phpunit` | OK: 1554 тестов, 4384 assertion, 2 skipped (root-guard тесты `markTestSkipped` — корректный skip при запуске от root) |
| `vendor/bin/psalm` | Ошибок нет |
| `make phpstan` | OK |
| `vendor/bin/deptrac analyse` | 0 errors |
| `make phpcs` (src/) | OK |
| `make md-links` / `validate-todo` / `validate-language` | OK (language — warning-mode, новые файлы в списке превышений отсутствуют) |
| `git diff --check` | OK |
| `PHAR_EXPECTED_VERSION=dev make phar-smoke` (реальная сборка Box 4.7.0) | ✓ установка, идемпотентность, конфликт, `--force`, `become-role.sh` из изолированного host-каталога |

## Замечания (не блокирующие)

### CR-1 — Nit (комментарий): неточное обоснование chmod в `copyTree`

**Файл:** `apps/console/src/Module/Orchestrator/Skill/Service/InstallBecomeRoleService.php`, метод `copyTree()` (~строка 445, комментарий перед `chmod($to, 0644)`).
**Обоснование:** Комментарий утверждает «Symfony Filesystem::copy сохраняет режим источника (как cp): без нормализации управляемая копия стала бы read-only». По vendor-коду (`vendor/symfony/filesystem/Filesystem.php::copy()`) блок `chmod(fileperms($origin))` выполняется только при `$originIsLocal`; для `phar://`-источника (`stream_is_local() === false`) chmod/touch не выполняются вовсе — целевой файл создаётся `fopen('w')` с umask-режимом. Итоговое поведение сервиса корректно и детерминировано в обоих случаях (явный `chmod 0644` — правильная нормализация), неточен только аргумент в комментарии.
**Проверка:** `sed -n '/public function copy/,/^    }/p' vendor/symfony/filesystem/Filesystem.php` — условие `$originIsLocal`.
**Рекомендация:** уточнить формулировку (нормализация гарантирует 0644 независимо от режима источника/обёртки), можно в рамках следующего касания файла.

### CR-2 — Nit (диагностика): пустая runtime-привязка в `become-role.sh` уводит на несуществующий binary

**Файл:** `docs/agents/skills/become-role/scripts/become-role.sh`, строки ~46–58.
**Обоснование:** при существующем, но пустом/повреждённом `.phar-binding` `PHAR_PATH` остаётся пустым, скрипт уходит на `$TASK_ORCH_BIN`, которого в managed-копии нет → пользователь получает невнятное «Ошибка: не удалось получить данные роли» вместо прямой диагностики повреждённой привязки. Кейс маловероятен (файл создаётся только сервисом с непустым содержимым и включён в сравнение дерева, так что повторный `agent:init` его восстановит через `--force`).
**Рекомендация (опционально):** различать «привязки нет» (source/Composer — штатно) и «привязка пуста/битая» (явная ошибка с подсказкой `agent:init --force`).

### CR-3 — Process (не код): статус задачи

**Файл:** `todo/TASK-feat-phar-full-become-role-install.todo.md`.
**Обоснование:** код готов и проверен, но `status: in_progress`; по регламенту `todo/AGENTS.md` перед передачей на приёмку/CI статус переводится в `review` (`php vendor/bin/todo-md review`), поле `pr` заполняется после пуша ветки.
**Рекомендация:** при фиксации результатов ревью перевести статус; в DoD остаётся незакрытым только пункт о полном `make check` по вентилю релиза (локальный прогон всех составных проверок — зелёный).

### CR-4 — Observation (техдолг вне задачи): проверки не покрывают `apps/console`

**Файл:** `Makefile` (`phpcs`: только `src/`; `phpmd`: только `src`).
**Обоснование:** предсуществующее нарушение PHPCS (сортировка use) в `apps/console/src/Module/AgentRole/Command/RoleSkillsCommand.php` не ловится `make check`; файл в этой задаче не менялся, новые файлы задачи чисты.
**Рекомендация:** отдельная задача техдолга о расширении зон `phpcs`/`phpmd` на `apps/` (с фиксом накопленных нарушений) — вне этого PR.

## Итог

Реализация добротная: матрица PHAR-состояний закрыта тестами по всем веткам, включая инъекцию отказов rename; безопасности файловых операций уделено внимание на уровне, превышающем типичный для таких задач; документация синхронизирована по всем точкам упоминания; ни одного лишнего изменения в диффе не найдено. Замечания CR-1/CR-2 — необязательные полировки, CR-3/CR-4 — процессные. Слияние возможно после перевода задачи в `review`/`done` и пуша ветки (merge — только по явному подтверждению пользователя).
