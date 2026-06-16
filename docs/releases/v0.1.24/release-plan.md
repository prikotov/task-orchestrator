# План релиза v0.1.24

## Метаданные

- Тег релиза: `v0.1.24`
- Линия релиза: `release/0.1`
- Исходная ветка: `main` (через интеграционный merge `c280b1a`)
- Ответственный: Тимлид Алекс
- Плановая дата deploy: 2026-06-16

## Состав

- Включённые PR (с `v0.1.23`): #252, #253, #254, #258, #259, #261, #262, #263, #265, #266, #267 + стабилизация релизов v0.1.18–v0.1.23 (#237–#251), интегрированная в `release/0.1`.
- Включённые задачи: `TASK-fix-cli-default-timeout-overrides-chain` (done), `EPIC-refactor-phpmd-baseline-elimination` (done, PR #261), fix-iterations sync (#262, #263), ретро post-roadmap cleanup (#265).
- Вне состава релиза: `TASK-fix-static-timeout-default-300` (backlog — возвращает intended 300s default для static-цепочек с осознанным behavior change), `TASK-fix-watch-subagent-logical-stall` (backlog — паттерн зависания pi «молчание после tool-result»), остальные backlog-задачи.

## Риски

- Основные риски: изменение hard default для static-цепочек без `chain.timeout` — **поведенчески нейтрально** (CR-1 вариант a: `DEFAULT_STATIC_TIMEOUT` поднят с 300 до 600, чтобы сохранить фактическое поведение; intended 300 вынесен в `TASK-fix-static-timeout-default-300`). Регрессии production-поведения нет.
- Наличие миграций данных: нет.
- Порядок применения миграций: N/A.
- Риск окна несовместимости: отсутствует — все изменения back-compat, контракт CLI (`--timeout`/`--max-time`) усилился (раньше молча затирал config, теперь уважает).
- Замечания по обратной совместимости: users, полагавшиеся на «CLI default затирает chain.timeout» (негласное поведение), должны передавать `--timeout`/`--max-time` явно. Это documented fix longstanding-бага.

## Порядок deploy

1. Tag `v0.1.24` → Phar build (CI `Release Phar` workflow, включает `phar-smoke`).
2. GitHub Release с notes (CHANGELOG + ссылки на PR).
3. (Опционально) Packagist auto-update.

## Проверки перед deploy

- [x] Тег релиза запушен в `origin` (после этого плана).
- [x] `make check` зелёный на `release/0.1` (PHPUnit 1060/2936, Psalm/PHPStan/Deptrac/PHPMD/PHPCS/MD-Links/validate-todo/validate-roles — OK; `phar-smoke` включён).
- [x] `composer.lock` соответствует `composer.json` (`composer validate` OK).
- Подготовлены обязательные изменения в env: N/A.
- В документе зафиксированы миграции, порядок их применения и риск окна несовместимости: миграций нет.

## Проверки после deploy

- Основные пользовательские сценарии: `agent:orchestrate --chain=<chain>` (static/conditional/dynamic), resume, `--validate-config`, `validate:connectivity`, brainstorm-цепочки.
- Логи: `var/log/watch-subagent/<run-id>/run.log` (для subagent runs); audit JSONL для цепочек.
- Build version и git SHA: `git describe --tags` → `v0.1.24`.

## Действия при проблеме после релиза

- Откат: не используется (production deploy по immutable tag).
- Стратегия исправления: `hotfix/x.y.z-<desc>` от `v0.1.24` → patch release `v0.1.25`.
- Ответственный инженер: Тимлид Алекс.
- Канал коммуникации / задача: `todo/` + GitHub Issue.

## Заметки

- Релиз кумулятивный: `release/0.1` отставала от `main` на 6 release-prep коммитов (v0.1.18–v0.1.23), которые ранее не были смержены обратно в `main`. Этот релиз закрывает расхождение — merge-back в `main` вернёт release-метаданные.
- `TASK-fix-static-timeout-default-300` (backlog) — следующий релиз вернёт intended 300s default для static-цепочек с осознанным behavior change в CHANGELOG и migration-hint.
