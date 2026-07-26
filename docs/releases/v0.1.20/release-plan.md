# План релиза v0.1.20

## Метаданные

- Тег релиза: `v0.1.20`
- Линия релиза: `release/0.1`
- Исходная ветка: `release/0.1` (fix commit `1ada354` до release commit)
- Ответственный: dp
- Плановая дата deploy: 2026-06-05

## Состав

- Включённые PR: #241
- Основные изменения:
  - workflow `Release Phar` использует фактический путь к сгенерированному Phar (`bin/task-orchestrator.phar`) для smoke-теста и загрузки артефакта (upload asset)
  - устранено несовпадение путей (path mismatch): `box compile --no-config` генерирует `bin/task-orchestrator.phar`, а workflow ранее искал `task-orchestrator.phar` в root
- Вне состава релиза:
  - поведение приложения в рантайме (runtime behavior) не меняется
  - миграции данных не требуются
  - breaking changes отсутствуют

## Риски

- Основные риски: низкие — изменение затрагивает только release workflow и публикацию Phar-артефакта
- Наличие миграций данных: нет
- Риск окна несовместимости: нет
- Breaking changes: нет

## Проверки перед deploy

- [x] разбор YAML средством Symfony YAML — OK
- [x] `make check` — зелёный (`PHPUnit`: 954 tests, 2697 assertions)
- [x] CI `test` — пройден (pass)

## Порядок deploy

1. Библиотека (Packagist) — по тегу `v0.1.20`
2. Phar-артефакт — через workflow GitHub Actions `Release Phar` по тегу `v0.1.20`

## Проверка после deploy

- Проверить, что GitHub Release `v0.1.20` создан
- Проверить, что workflow `Release Phar` завершился успешно для тега `v0.1.20`
- Проверить, что сгенерированный Phar `bin/task-orchestrator.phar` создан в рабочей области workflow (workspace) и загружен в GitHub Release как релизный `.phar`-артефакт
- Проверить smoke command: `php bin/task-orchestrator.phar --version` в рабочей области workflow (workspace); после скачивания релизного артефакта — `php task-orchestrator.phar --version` или команда с фактическим именем `.phar` файла

## Действия при проблеме после релиза

- Стратегия исправления: patch release v0.1.21
- Ответственный инженер: dp
