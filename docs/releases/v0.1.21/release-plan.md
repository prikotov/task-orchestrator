# План релиза v0.1.21

## Метаданные

- Тег релиза: `v0.1.21`
- Линия релиза: `release/0.1`
- Исходная ветка: `release/0.1` (fix commit `e987ebd` до release commit)
- Ответственный: dp
- Плановая дата deploy: 2026-06-05

## Состав

- Предыдущий production tag: `v0.1.20`
- Включённые PR: #243
- Основные изменения:
  - workflow `Release Phar` использует `box compile --config=box.json.dist` вместо сборки без конфигурации
  - `box.json.dist` теперь учитывается при сборке Phar, поэтому в asset попадают `config/services.yaml`, `config/console_services.yaml`, `config/chains.yaml` и другие файлы из конфигурации Box
  - устранён root cause релиза `v0.1.20`: правильный путь Phar уже использовался, но сборка через `--no-config` игнорировала `box.json.dist`; из-за отсутствия `config/services.yaml` smoke падал с `FileLocatorFileNotFoundException`
- Вне состава релиза:
  - runtime behavior приложения не меняется
  - миграции данных не требуются
  - breaking changes отсутствуют

## Риски

- Основные риски: низкие — изменение затрагивает только release workflow и состав Phar asset
- Наличие миграций данных: нет
- Риск окна несовместимости: нет
- Breaking changes: нет

## Проверки перед deploy

- [x] YAML parse via Symfony YAML — OK
- [x] `--no-config` отсутствует в workflow
- [x] `box compile --config=box.json.dist` согласован с output `task-orchestrator.phar`
- [x] `make check` — зелёный (`PHPUnit`: 954 tests, 2697 assertions)
- [x] CI `test` — pass

## Порядок deploy

1. Библиотека (Packagist) — по тегу `v0.1.21`
2. Phar asset — через GitHub Actions workflow `Release Phar` по тегу `v0.1.21`

## Post-check

- Проверить, что GitHub Release `v0.1.21` создан
- Проверить, что workflow `Release Phar` завершился успешно для тега `v0.1.21`
- Проверить, что GitHub Release `v0.1.21` содержит asset `task-orchestrator.phar`
- Скачать asset `task-orchestrator.phar` и проверить smoke command: `php task-orchestrator.phar --version`

## Действия при проблеме после релиза

- Стратегия исправления: patch release v0.1.22
- Ответственный инженер: dp
