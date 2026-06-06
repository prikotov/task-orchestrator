# План релиза v0.1.23

## Метаданные

- Тег релиза: `v0.1.23`
- Линия релиза: `release/0.1`
- Исходная ветка: `release/0.1` (refactor commit `0602297` до release commit)
- Ответственный: dp
- Плановая дата deploy: 2026-06-06

## Состав

- Предыдущий production tag: `v0.1.22`
- Включённые PR: #249
- Включённые задачи:
  - `todo/done/TASK-refactor-domain-service-chain-definition-path.todo.md`
- Основные изменения:
  - контракты ChainExecution и DynamicLoop для загрузки ChainDefinition перенесены из технического пути `Domain/Service/Integration` в бизнес-контекст `Domain/Service/ChainDefinition`
  - обновлены imports, DI aliases, unit-тесты и актуальная документация
  - прикладной architecture unit test для проверки конвенции не добавлен: контроль таких правил должен жить в `prikotov/coding-conventions`
- Вне состава релиза:
  - runtime behavior приложения не меняется
  - миграции данных не требуются
  - breaking changes отсутствуют

## Риски

- Основные риски: низкие — изменение затрагивает namespace/path и DI aliases, без изменения поведения выполнения цепочек
- Наличие миграций данных: нет
- Риск окна несовместимости: нет
- Breaking changes: нет

## Проверки перед deploy

- [x] `make check` — зелёный (`PHPUnit`: 955 tests, 2701 assertions)
- [ ] CI `test` — ожидается в PR релиза
- [ ] CI `phar-smoke` — ожидается в PR релиза

## Порядок deploy

1. Библиотека (Packagist) — по тегу `v0.1.23`
2. Phar asset — через GitHub Actions workflow `Release Phar` по тегу `v0.1.23`

## Post-check

- Проверить, что GitHub Release `v0.1.23` создан
- Проверить, что workflow `Release Phar` завершился успешно для тега `v0.1.23`
- Проверить, что GitHub Release `v0.1.23` содержит asset `task-orchestrator.phar`
- Скачать asset `task-orchestrator.phar` из GitHub Release `v0.1.23` и проверить команду: `php task-orchestrator.phar --version`

## Действия при проблеме после релиза

- Стратегия исправления: patch release v0.1.24
- Ответственный инженер: dp
