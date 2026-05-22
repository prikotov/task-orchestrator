# План релиза v0.1.6

## Метаданные

- Тег релиза: `v0.1.6`
- Линия релиза: `release/0.1`
- Исходная ветка: `release/0.1` (commit `67f1cce`)
- Ответственный: dp
- Плановая дата deploy: 2026-05-22

## Состав

- Включённые PR: #219 (rename InMemoryMetricsCollector → InMemoryMetricsCollectorService)
- Включённые задачи: нет задач в todo/ (refactor-only)
- Вне состава релиза: всё остальное

## Риски

- Основные риски: минимальные — переименование класса без изменения поведения, bump coding-standard до ^0.16.0
- Наличие миграций данных: нет
- Порядок применения миграций: N/A
- Риск окна несовместимости: есть — внешний код, зависящий от `InMemoryMetricsCollector`, сломается (переименование класса и namespace)
- Замечания по обратной совместимости: `InMemoryMetricsCollector` → `InMemoryMetricsCollectorService`, namespace изменён с `Infrastructure\Metrics` на `Infrastructure\Service\Metrics`

## Порядок deploy

1. Библиотека (Packagist) — по тегу `v0.1.6`

## Проверки перед deploy

- [x] Тег релиза запушен в `origin`
- [x] PHPUnit: 926 tests, 2549 assertions — OK
- [x] Psalm: OK
- [x] PHPStan: OK
- [x] PHPCS: OK
- [x] Deptrac: 0 violations
- [x] `make check`: все проверки пройдены

## Проверки после deploy

- `composer require prikotov/task-orchestrator:^0.1.6` — устанавливается без конфликтов

## Действия при проблеме после релиза

- Откат: не используется
- Стратегия исправления: patch release v0.1.7
- Ответственный инженер: dp

## Заметки

- Единственный change с v0.1.5: rename `InMemoryMetricsCollector` → `InMemoryMetricsCollectorService` для соответствия PHPCS конвенции Service-именования
