# План релиза v0.1.18

## Метаданные

- Тег релиза: `v0.1.18`
- Линия релиза: `release/0.1`
- Исходная ветка: `release/0.1` (commit `e43bc4b` до release commit)
- Ответственный: dp
- Плановая дата deploy: 2026-06-04

## Состав

- Включённые PR: #231, #232, #233, #234, #235, #236
- Основные изменения:
  - `validate:connectivity` — проверка запуска ролей из `chains.yaml` без запуска цепочки
  - усиление валидатора AI role files
  - поддержка role runner profiles при делегировании сабагентов
  - стабилизация flaky static-chain metrics integration test
  - обновление `prikotov/*` tooling dependencies и Symfony security patches
- Вне состава релиза: остальные изменения после `release/0.1` на момент подготовки релиза отсутствуют

## Риски

- Основные риски: низкие/средние — добавлена новая CLI-команда и обновлены зависимости безопасности
- Наличие миграций данных: нет
- Риск окна несовместимости: нет
- Breaking changes: нет

## Проверки перед deploy

- [x] `composer audit --locked` — no security vulnerability advisories found
- [x] `make check` — зелёный
- [ ] `make tests-e2e` — target отсутствует в текущем Makefile

## Порядок deploy

1. Библиотека (Packagist) — по тегу `v0.1.18`
2. Phar asset — через GitHub Actions workflow `Release Phar` по тегу `v0.1.18`

## Post-check

- Проверить, что GitHub Release `v0.1.18` создан
- Проверить, что workflow `Release Phar` завершился, а `task-orchestrator.phar` приложен к релизу или зафиксирован warning
- Проверить smoke command: `php task-orchestrator.phar --version`

## Действия при проблеме после релиза

- Стратегия исправления: patch release v0.1.19
- Ответственный инженер: dp
