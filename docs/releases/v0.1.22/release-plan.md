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
  - добавлен сквозной (end-to-end) Phar smoke до tag: `make phar-smoke` и `bin/phar-smoke` проверяют собранный `task-orchestrator.phar`
  - CI запускает `test` и `phar-smoke` для веток `main` и `release/**`
  - workflow `Release Phar` использует `make phar-smoke`, больше не продолжает релиз при сбое smoke (smoke failure) и получает `permissions: contents: write` для публикации артефакта
  - обнаружение сервисов Symfony (service discovery) использует `%task_orchestrator.package_dir%` и исключает `src/**/Resources/`, чтобы bridge-файлы не регистрировались как сервисы в Phar context
  - добавлен integration test на исключение `Resources/bridge.php` из service discovery
  - устранена первопричина (root cause) серии релизов `v0.1.18`-`v0.1.21`: разные сбои сборки Phar (Phar build failures) выявлялись только после tag, потому что не было сквозного (end-to-end) smoke перед релизом
- Вне состава релиза:
  - поведение приложения в рантайме (runtime behavior) не меняется
  - миграции данных не требуются
  - breaking changes отсутствуют

## Риски

- Основные риски: низкие — изменение затрагивает операционный/релизный workflow (operational/release workflow) и обнаружение сервисов Symfony (service discovery) для сборки Phar (Phar build), а не пользовательское поведение приложения
- Наличие миграций данных: нет
- Риск окна несовместимости: нет
- Breaking changes: нет

## Проверки перед deploy

- [x] `make check` — зелёный (`PHPUnit`: 955 tests, 2701 assertions)
- [x] `make phar-smoke` с Box 4.7.0 — OK
- [x] `composer install --no-dev --optimize-autoloader` + `make phar-smoke` — OK
- [x] CI `test` — пройден (pass)
- [x] CI `phar-smoke` — пройден (pass)

## Порядок deploy

1. Библиотека (Packagist) — по тегу `v0.1.22`
2. Phar-артефакт — через workflow GitHub Actions `Release Phar` по тегу `v0.1.22`

## Проверка после deploy

- Проверить, что GitHub Release `v0.1.22` создан
- Проверить, что workflow `Release Phar` завершился успешно для тега `v0.1.22`
- Проверить, что GitHub Release `v0.1.22` содержит артефакт `task-orchestrator.phar`
- Скачать артефакт `task-orchestrator.phar` из GitHub Release `v0.1.22` и проверить команду: `php task-orchestrator.phar --version`

## Действия при проблеме после релиза

- Стратегия исправления: patch release v0.1.23
- Ответственный инженер: dp
