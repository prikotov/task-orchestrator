# План релиза v0.1.22

## Метаданные

- Тег релиза: `v0.1.22`
- Линия релиза: `release/0.1`
- Исходная ветка: `release/0.1` (fix commit `038189f` до release commit)
- Ответственный: dp
- Плановая дата deploy: 2026-06-05

## Состав

- Предыдущий production tag: `v0.1.21`
- Включённые PR: #246
- Основные изменения:
  - добавлен end-to-end Phar smoke до tag: `make phar-smoke` и `bin/phar-smoke` проверяют собранный `task-orchestrator.phar`
  - CI запускает `test` и `phar-smoke` для веток `main` и `release/**`
  - workflow `Release Phar` использует `make phar-smoke`, больше не продолжает релиз при smoke failure и получает `permissions: contents: write` для публикации asset
  - Symfony service discovery использует `%task_orchestrator.package_dir%` и исключает `src/**/Resources/`, чтобы bridge-файлы не регистрировались как сервисы в Phar context
  - добавлен integration test на исключение `Resources/bridge.php` из service discovery
  - устранён root cause серии релизов `v0.1.18`-`v0.1.21`: разные failures Phar build выявлялись только после tag, потому что не было end-to-end smoke перед релизом
- Вне состава релиза:
  - runtime behavior приложения не меняется
  - миграции данных не требуются
  - breaking changes отсутствуют

## Риски

- Основные риски: низкие — изменение затрагивает operational/release workflow и Symfony service discovery для Phar build, а не пользовательское поведение приложения
- Наличие миграций данных: нет
- Риск окна несовместимости: нет
- Breaking changes: нет

## Проверки перед deploy

- [x] `make check` — зелёный (`PHPUnit`: 955 tests, 2701 assertions)
- [x] `make phar-smoke` с Box 4.7.0 — OK
- [x] `composer install --no-dev --optimize-autoloader` + `make phar-smoke` — OK
- [x] CI `test` — pass
- [x] CI `phar-smoke` — pass

## Порядок deploy

1. Библиотека (Packagist) — по тегу `v0.1.22`
2. Phar asset — через GitHub Actions workflow `Release Phar` по тегу `v0.1.22`

## Post-check

- Проверить, что GitHub Release `v0.1.22` создан
- Проверить, что workflow `Release Phar` завершился успешно для тега `v0.1.22`
- Проверить, что GitHub Release `v0.1.22` содержит asset `task-orchestrator.phar`
- Скачать asset `task-orchestrator.phar` из GitHub Release `v0.1.22` и проверить команду: `php task-orchestrator.phar --version`

## Действия при проблеме после релиза

- Стратегия исправления: patch release v0.1.23
- Ответственный инженер: dp
