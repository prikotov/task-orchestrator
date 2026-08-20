# План релиза v0.2.2

## Метаданные

- Тег релиза: `v0.2.2`
- Линия релиза: `release/0.2`
- Исходное изменение: PR #313 (docs-only, cherry-pick из `main`, merge commit `a4705b4`)
- Merge-back в `main`: не требуется — исправление уже в `main` (PR #313 слит до выпуска)
- Ответственный: pi (Тимлид)
- Плановая дата deploy: 2026-07-17

## Состав

- Docs-only: путь к скрипту навыка `become-role` приведён к относительному (от каталога skill) по стандарту Agent Skills, чтобы резолвился в любом контексте (standalone / Composer-host / pi / codex); в `SKILL-CREATION` добавлены ссылки на стандарт как источник истины.
- Runtime contract не изменился: PHP >= 8.4.1, `ext-openssl` и `ext-zlib`.
- Другие изменения в релиз не входят.

## Риски

- Основной риск: нет — изменения только в документации skills; production-код и поведение не затронуты.
- Наличие миграций данных: нет.
- Порядок применения миграций: N/A.
- Риск окна несовместимости: отсутствует — runtime contract и пользовательские данные не меняются.

## Порядок deploy

1. Убедиться, что `make check`, Composer-host gate и PHAR build/smoke успешны на `release/0.2`.
2. Создать и отправить tag `v0.2.2`.
3. Дождаться exact PHP 8.4.1 PHAR smoke в workflow `Release Phar` и публикации `task-orchestrator.phar` в GitHub Release.
4. Канал публикации — только GitHub Release с PHAR; Composer-установка через VCS/path repository, Packagist не используется.

## Проверки перед deploy

- [ ] `make check` успешно выполнён.
- [ ] `composer-host-smoke` подтверждает production-установку.
- [ ] Tag `v0.2.2` отсутствует до завершения всех проверок.

## Проверки после deploy

- GitHub Release содержит tag `v0.2.2` и asset `task-orchestrator.phar`.
- `git describe --tags` показывает `v0.2.2`.

## Действия при проблеме после релиза

- Откат immutable tag не используется.
- Recovery: создать ветку `hotfix/0.2.3-<short-description>` от `v0.2.2`, исправить дефект и выпустить `v0.2.3`.

## Заметки

- Исправление уже присутствует в `main` (PR #313); cherry-pick выполнен в `release/0.2` только для выпуска patch release.
