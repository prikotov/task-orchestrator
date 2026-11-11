# План релиза v0.15.0

## Метаданные

- Тег: `v0.15.0`.
- Тип: `minor` — содержит `BREAKING CHANGE` (обязательные параметры запуска `watch-subagent.sh`); при текущей линии `0.x` несовместимость повышает `minor`.
- База: актуальная `main` после слияния #479 (`2ede9128`).
- Ветка: `release/0.15.0`.
- PR подготовки: создаётся из этой ветки.
- Ответственный: владелец проекта.
- Плановая дата: 2026-11-11.

## Состав

- #479 — `fix(run-subagent)` с `BREAKING CHANGE`: обязателен один из параметров `--task-id`/`--complexity` при запуске `watch-subagent.sh`; некорректное значение `--complexity` отклоняется до запуска; в `RUN START` run-log добавлена строка `complexity=<C0–C5> source=<explicit|task-file> task_id=<ID|none>`; документация скиллов `run-subagent`, `task-via-subagents`, `epic-via-subagents`, `docs/guide/roles.md` и `CHANGELOG.md` синхронизированы; задача `TASK-fix-watch-subagent-complexity-required`.
- Подготовительный PR с `CHANGELOG.md`, этим планом и релизной задачей `TASK-release-v0-15-0-preparation`.

## Риски и несовместимость

- Несовместимость: потребители, вызывающие `watch-subagent.sh` без параметров сложности (вне-задачные запуски — ретро, диагностики, внешние обёртки), получат ошибку запуска. Миграция: передавать `--task-id TASK-*` (для задач с файлом) или `--complexity C0–C5` (для остальных запусков).
- Изменения только в `docs/**` (скиллы, guide), `tests/**`, `CHANGELOG.md`, `todo/**`; PHP-код `src/`, конфигурации и зависимости не затронуты.
- PHAR меняется содержимым скилла (`docs/agents/skills/run-subagent/**`) и bash-скрипта; размер практически не меняется.
- Миграции данных и новые обязательные переменные окружения не требуются.
- Задача-последствие `todo/backlog/TASK-fix-orchestrate-complexity-required.todo.md` (фолбэк `C3` в `agent:orchestrate`) сознательно не входит в этот релиз.

## Порядок публикации

1. Слить подготовительный PR в `main` после одобрения владельца и подтверждения слияния.
2. Проверить совпадение содержимого merge commit и одобренной вершины `release/0.15.0`.
3. Выполнить проверки и `make phar-e2e` на проверенном merge commit.
4. Создать и опубликовать только тег `v0.15.0` на проверенном merge commit.
5. Дождаться workflow `Release Phar` и проверить GitHub Release.

## Проверки перед публикацией

```bash
composer validate --strict
vendor/bin/phpunit
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
make phar-smoke PHAR_EXPECTED_VERSION=dev
make phar-e2e
```

Ожидаемый результат: код завершения `0`, рабочее дерево чистое.

Известное отклонение: локальный `make phpmd` падает на ранее существовавших замечаниях `ExcessiveClassLength` (`src/Kernel.php`, `YamlChainLoaderService.php`) — файлы релизом не затронуты, замечания присутствуют начиная с v0.14.0; CI репозитория зелёный.

## Проверки после публикации

- `gh release view v0.15.0 --json tagName,assets` — релиз содержит PHAR-asset.
- Workflow `Release Phar` для тега успешен.
- `git rev-list -n 1 v0.15.0` возвращает проверенный merge commit.

## Действия при проблеме

- Откат и перемещение тега не используются.
- Проблема исправляется отдельным patch release (`release/0.15.1`) через PR по сценарию hotfix.
- Ответственный: владелец проекта; координация — в задаче на исправление.
