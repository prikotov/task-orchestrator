# План релиза v0.1.19

## Метаданные

- Тег релиза: `v0.1.19`
- Линия релиза: `release/0.1`
- Исходная ветка: `release/0.1` (dependency commit `ccdf1cb` до release commit)
- Ответственный: dp
- Плановая дата deploy: 2026-06-04

## Состав

- Включённые PR: #238
- Основные изменения:
  - обновлён `prikotov/git-workflow`: `v0.1.0` → `v0.2.0`
  - поднято ограничение версии (constraint) Composer: `^0.1.0` → `^0.2.0`
  - подтверждено, что остальные прямые (direct) зависимости `prikotov/*` уже актуальны (latest)
- Вне состава релиза:
  - исправление workflow `Release Phar` для устранения несовпадения путей smoke/build (path mismatch)

## Риски

- Основные риски: низкие — изменение затрагивает dev tooling, runtime-поведение приложения не меняется
- Наличие миграций данных: нет
- Риск окна несовместимости: нет
- Breaking changes: нет

## Проверки перед deploy

- [x] `composer outdated 'prikotov/*' --direct` — все актуальны (all up to date)
- [x] `composer validate --strict` — OK
- [x] `composer audit --locked` — рекомендаций нет (no advisories)
- [x] `make check` — зелёный (`PHPUnit`: 954 tests, 2697 assertions)

## Порядок deploy

1. Библиотека (Packagist) — по тегу `v0.1.19`
2. Phar-артефакт — через workflow GitHub Actions `Release Phar` по тегу `v0.1.19`

## Проверка после deploy

- Проверить, что GitHub Release `v0.1.19` создан
- Проверить, что workflow `Release Phar` завершился, а `task-orchestrator.phar` приложен к релизу
- Если Phar-артефакт не приложен, зафиксировать предупреждение (warning): на `v0.1.18` GitHub Release создавался, но артефакт мог не приложиться из-за несовпадения путей smoke/build (path mismatch); в `v0.1.19` это не исправлялось
- Проверить smoke command: `php task-orchestrator.phar --version`

## Действия при проблеме после релиза

- Стратегия исправления: patch release v0.1.20
- Ответственный инженер: dp
