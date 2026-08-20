# План релиза v0.2.0

## Метаданные

- Тег релиза: `v0.2.0`
- Линия релиза: `release/0.2`
- Исходная ветка: `main` (состояние после merge PR #308)
- Ответственный: Тимлид Алекс
- Плановая дата deploy: 2026-07-15

## Состав

- Релиз включает изменения после `v0.1.24`, в том числе поддержку GitHub App identity, универсальную загрузку role skills, PHAR-safe module registration, потоковую обработку JSONL, liveness-adaptive timeouts и исправления static-chain system prompt.
- Финальная стабилизация релизного контракта выполнена в PR #306, #307 и #308.
- Runtime contract: PHP >= 8.4.1, `ext-openssl` и `ext-zlib`.
- Вне состава релиза: полная установка `become-role` из PHAR; продолжение вынесено в [`TASK-feat-phar-full-become-role-install`](../../../todo/backlog/TASK-feat-phar-full-become-role-install.todo.md).

## Риски

- Основной риск совместимости: окружения с PHP < 8.4.1 либо без `ext-openssl`/`ext-zlib` не смогут установить релиз через Composer.
- PHAR limitation: `agent:init` не устанавливает `become-role` из PHAR. Для этого сценария до реализации backlog-задачи требуется Composer-дистрибуция.
- Наличие миграций данных: нет.
- Порядок применения миграций: N/A.
- Риск окна несовместимости: отсутствует — миграций и поэтапного переключения сервисов нет.

## Порядок deploy

1. Убедиться, что Composer-host, PHAR и liveness gates успешны на `release/0.2`.
2. Создать и отправить tag `v0.2.0`.
3. Дождаться успешного выполнения workflow `Release Phar` и публикации `task-orchestrator.phar` в GitHub Release.
4. Проверить release notes и опубликованный PHAR asset.
5. Канал публикации — только GitHub Release с PHAR; Composer-установка доступна через VCS/path repository, Packagist не используется.

## Проверки перед deploy

- [x] `make check` успешно выполнен: PHPUnit — 1411 tests / 3813 assertions; PHPStan, Psalm, Deptrac, PHPMD, PHPCS, MD links и validators — OK.
- [x] `composer-host-smoke` подтверждает production-установку пакета в host-проект.
- [x] PR #309 CI run `29430683170`: exact PHP 8.4.1 production install и PHAR smoke — OK.
- [x] `liveness-smoke` подтверждает Linux procfs contract на host и в `php:8.4.1` без зависимости от `ps`, `pgrep` и procps.
- [x] `composer check-platform-reqs --no-dev` подтверждает PHP >= 8.4.1, `ext-openssl` и `ext-zlib`.
- [x] Tag `v0.2.0` отсутствует до завершения всех проверок.

## Проверки после deploy

- GitHub Release содержит tag `v0.2.0` и asset `task-orchestrator.phar`.
- `php task-orchestrator.phar --version` завершается успешно на поддерживаемой платформе.
- Composer-host установка выполняет `agent:init`, а установленный `become-role` доступен проекту.
- PHAR корректно сообщает об ограничении `agent:init` и рекомендует Composer-дистрибуцию.
- Основные сценарии `agent:orchestrate` работают для static, conditional и dynamic chains.
- Build version и git SHA: `git describe --tags` показывает `v0.2.0`.

## Действия при проблеме после релиза

- Откат immutable tag не используется.
- Recovery: создать ветку `hotfix/0.2.1-<short-description>` от `v0.2.0`, исправить дефект и выпустить `v0.2.1`.
- Ответственный инженер: Тимлид Алекс.
- Канал коммуникации / задача: `todo/` + GitHub Issue.

## Заметки

- PR #306 зафиксировал runtime contract и точную проверку PHP 8.4.1.
- PR #307 разделил Composer-host и PHAR contracts; ограничение PHAR по установке `become-role` является осознанным.
- PR #308 сделал liveness probing platform-safe и добавил отдельный smoke gate.
