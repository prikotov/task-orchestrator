# План релиза v0.2.1

## Метаданные

- Тег релиза: `v0.2.1`
- Линия релиза: `release/0.2`
- Исходная ветка: `hotfix/0.2.1-phar-release-version` (PR #310, merge `ac9b969`)
- Merge-back в `main`: PR #311 (merged до выпуска)
- Ответственный: Тимлид Алекс
- Плановая дата deploy: 2026-07-16

## Состав

- Hotfix исправляет определение версии в PHAR и Composer-installed binaries: релиз v0.2.0 ошибочно показывал fallback-версию `1.0.0.0` вместо версии пакета (#310).
- Runtime contract не изменился: PHP >= 8.4.1, `ext-openssl` и `ext-zlib`.
- Другие функциональные изменения в релиз не входят.

## Риски

- Основной риск: расхождение версии между tag, Composer metadata и собранным PHAR.
- Наличие миграций данных: нет.
- Порядок применения миграций: N/A.
- Риск окна несовместимости: отсутствует — runtime contract и пользовательские данные не меняются.

## Порядок deploy

1. Убедиться, что `make check`, Composer-host gate и PHAR build/smoke из CI PR #310 успешны на `release/0.2`.
2. Создать и отправить tag `v0.2.1`.
3. Дождаться exact PHP 8.4.1 PHAR smoke в workflow `Release Phar` и публикации `task-orchestrator.phar` в GitHub Release.
4. Проверить точную версию Composer-installed binary и опубликованного PHAR.
5. Канал публикации — только GitHub Release с PHAR; Composer-установка доступна через VCS/path repository, Packagist не используется.

## Проверки перед deploy

- [x] `make check` успешно выполнен: PHPUnit — 1462 tests / 3902 assertions; PHPStan, Psalm, Deptrac, PHPMD, PHPCS, MD links и validators — OK.
- [x] `composer-host-smoke` подтверждает production-установку и точную версию `0.2.1` Composer-installed binary.
- [ ] Окончательный exact PHP 8.4.1 PHAR gate выполняется в tag workflow: CI PR #310 успешно выполнил PHAR build/smoke, но точная проверка версии `0.2.1` остаётся за workflow тега.
- [x] `composer check-platform-reqs --no-dev` подтверждает неизменный runtime contract: PHP >= 8.4.1, `ext-openssl` и `ext-zlib`.
- [x] Tag `v0.2.1` отсутствует до завершения всех проверок.

## Проверки после deploy

- GitHub Release содержит tag `v0.2.1` и asset `task-orchestrator.phar`.
- `php task-orchestrator.phar --version` возвращает точно `Task Orchestrator 0.2.1`.
- Composer-installed binary возвращает точно `Task Orchestrator 0.2.1`.
- Build version и git SHA: `git describe --tags` показывает `v0.2.1`.

## Действия при проблеме после релиза

- Откат immutable tag не используется.
- Recovery: создать ветку `hotfix/0.2.2-<short-description>` от `v0.2.1`, исправить дефект и выпустить `v0.2.2`.
- Ответственный инженер: Тимлид Алекс.
- Канал коммуникации / задача: `todo/` + GitHub Issue.

## Заметки

- PR #310 устраняет fallback `1.0.0.0` и синхронизирует версию PHAR/Composer binaries с package version.
- PR #311 выполнил обязательный merge-back hotfix в `main` до выпуска.
