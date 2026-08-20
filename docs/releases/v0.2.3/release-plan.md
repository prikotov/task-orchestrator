# План релиза v0.2.3

## Метаданные

- Тег релиза: `v0.2.3`
- Линия релиза: `release/0.2`
- Исходное изменение: PR #315 (cherry-pick из `main`, merge commit `d05d460`)
- Merge-back в `main`: не требуется — исправление уже в `main` (PR #315 слит до выпуска)
- Ответственный: pi (Тимлид)
- Плановая дата deploy: 2026-07-17

## Состав

- Каталог skills `become-role` (`FormatSkillCatalogService`) и `become-role/SKILL.md` теперь объясняют, как резолвить относительные пути внутри skill (`scripts/`, `references/`) через `<location>` (подставлять каталог skill перед путём), чтобы агенты не искали скрипты skill в корне проекта в host-установках.
- Поведение runtime не изменилось: меняется только текст промпта каталога и инструкция SKILL.md. Runtime contract: PHP >= 8.4.1, `ext-openssl`, `ext-zlib`.
- Другие изменения в релиз не входят.

## Риски

- Основной риск: нет — меняется только текст инструкции; production-код (кроме строковой константы промпта) и поведение не затронуты.
- Наличие миграций данных: нет.
- Порядок применения миграций: N/A.
- Риск окна несовместимости: отсутствует.

## Порядок deploy

1. Убедиться, что `make check`, Composer-host gate и PHAR build/smoke успешны на `release/0.2`.
2. Создать и отправить tag `v0.2.3`.
3. Дождаться exact PHP 8.4.1 PHAR smoke в workflow `Release Phar` и публикации `task-orchestrator.phar` в GitHub Release.
4. Канал публикации — только GitHub Release с PHAR; Composer-установка через VCS/path repository, Packagist не используется.

## Проверки перед deploy

- [ ] `make check` успешно выполнён.
- [ ] `composer-host-smoke` подтверждает production-установку.
- [ ] Tag `v0.2.3` отсутствует до завершения всех проверок.

## Проверки после deploy

- GitHub Release содержит tag `v0.2.3` и asset `task-orchestrator.phar`.
- `git describe --tags` показывает `v0.2.3`.

## Действия при проблеме после релиза

- Откат immutable tag не используется.
- Recovery: создать ветку `hotfix/0.2.4-<short-description>` от `v0.2.3`, исправить дефект и выпустить `v0.2.4`.

## Заметки

- Исправление уже присутствует в `main` (PR #315); cherry-pick выполнен в `release/0.2` только для выпуска patch release.
