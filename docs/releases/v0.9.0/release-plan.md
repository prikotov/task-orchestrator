# План релиза v0.9.0

## Метаданные

- Тег релиза: `v0.9.0`.
- Тип повышения версии: `minor`.
- Основание: поставляется новая функциональность для подготовки задач и эпиков, без несовместимых изменений.
- Источник релиза: `main`; локальная политика `docs/releases/RELEASE-POLICY.md` имеет приоритет над общим руководством и не предусматривает `release/0.9`.
- Зафиксированная база состава до подготовки: `97c4facbbdffd1d7f985bd6d422fa61180c55e1b`.
- Последний опубликованный тег: `v0.8.2`.
- Рабочая ветка подготовки: `task/release-v0-9-0`.
- Запрос на слияние подготовки: будет указан после его создания; релиз, тег и слияние этим планом не выполняются.

## Состав и границы

Сравнение: [`v0.8.2...v0.9.0`](https://github.com/prikotov/task-orchestrator/compare/v0.8.2...v0.9.0).

В релиз входят только:

- [#400](https://github.com/prikotov/task-orchestrator/pull/400), коммит `314de39a728d298f17c4553a21f2fc81d02e345d`: навыки `task-definition`, `epic-definition`, `epic-decomposition` и общий протокол в Composer-пакете; SDLC и RACI в Composer-пакете и PHAR.
- [#401](https://github.com/prikotov/task-orchestrator/pull/401), коммит `97c4facbbdffd1d7f985bd6d422fa61180c55e1b`: уточнение ретроспектив и контроля согласованного объёма.
- Подготовительный запрос на слияние с `CHANGELOG.md`, этим планом и задачей `TASK-release-v0-9-0-preparation`.

Не включать будущий релиз todo-md, его поля участников или изменения зависимости `prikotov/todo-md` (остаётся `^0.0`). Не менять README, роли, навыки, код, упаковку, конфигурацию или зависимости.

## Совместимость и установка

- CLI- и PHP-контракты, конфигурация и зависимости не изменялись; миграции и новые переменные окружения не нужны.
- Три навыка и общий протокол поставляются только в Composer-пакете и используются как единый набор: `task-definition` содержит общий протокол, а `epic-definition` и `epic-decomposition` ссылаются на него.
- Роль читает эти навыки из установленного Composer-пакета; ручная установка не нужна. В PHAR из нового SDLC/RACI поставляются только `docs/agents/workflow/sdlc.md` и `docs/agents/raci-matrix.md`; из навыков в нём по-прежнему есть только `become-role`.
- Локальные копии трёх навыков существуют только если их создал пользователь; этот релиз их не устанавливает, не обновляет и не перезаписывает. `agent:init --force` управляет только `become-role` и не относится к этим трём навыкам.

## Проверки перед тегом

Выполнять после слияния подготовительного запроса на точной принятой вершине `main`, а не на этой ветке:

```bash
git switch main
git pull --ff-only origin main
git status --short
git rev-parse HEAD
composer validate --strict
make check
make phar-e2e
```

На момент подготовки не выполнены: `make check` и `make phar-e2e`; они не заменяются проверками документации и должны быть повторены на принятом `main`.

## Тег, публикация и проверки после тега

1. После зелёных проверок и явного разрешения пользователя создать неизменяемый аннотированный тег на проверенной вершине `main`:

   ```bash
   git tag -a v0.9.0 -m "chore(release): v0.9.0"
   ```

2. Отправить только этот тег по утверждённому HTTPS-рецепту GitHub App. Не создавать GitHub Release вручную: push тега запускает `Release Phar`, который сам создаёт GitHub Release и загружает `task-orchestrator.phar`.
3. Дождаться успешного `Release Phar`. Проверить именно опубликованный asset и Composer-установку:

   ```bash
   gh run list --workflow 'Release Phar' --event push --limit 1
   gh run watch <workflow-run-id> --exit-status
   gh release view v0.9.0 --json tagName,assets
   release_dir="$(mktemp -d)"
   gh release download v0.9.0 --pattern task-orchestrator.phar --dir "$release_dir"
   test -s "$release_dir/task-orchestrator.phar"
   php "$release_dir/task-orchestrator.phar" --version
   php -r '$phar = new Phar($argv[1]); foreach (["docs/agents/workflow/sdlc.md", "docs/agents/raci-matrix.md"] as $path) { if (!isset($phar[$path])) { fwrite(STDERR, "Missing: $path\\n"); exit(1); } }' "$release_dir/task-orchestrator.phar"
   composer_dir="$(mktemp -d)"
   composer init --working-dir="$composer_dir" --name=release-check/task-orchestrator --no-interaction
   composer require --working-dir="$composer_dir" --no-interaction prikotov/task-orchestrator:v0.9.0
   php "$composer_dir/vendor/bin/task-orchestrator" --version
   test -f "$composer_dir/vendor/prikotov/task-orchestrator/docs/agents/skills/task-definition/SKILL.md"
   test -f "$composer_dir/vendor/prikotov/task-orchestrator/docs/agents/skills/task-definition/references/definition-protocol.md"
   test -f "$composer_dir/vendor/prikotov/task-orchestrator/docs/agents/skills/epic-definition/SKILL.md"
   test -f "$composer_dir/vendor/prikotov/task-orchestrator/docs/agents/skills/epic-decomposition/SKILL.md"
   test -f "$composer_dir/vendor/prikotov/task-orchestrator/docs/agents/workflow/sdlc.md"
   test -f "$composer_dir/vendor/prikotov/task-orchestrator/docs/agents/raci-matrix.md"
   ```

   Обе команды `--version` должны сообщить `0.9.0`.
4. Не изменять и не перемещать тег. Если после выпуска обнаружена ошибка, подготовить отдельный patch из `main` через Pull Request; исправление не вносить в `v0.9.0`.

## Риски

- Между подготовкой и тегированием `main` может измениться; состав, проверки и тег повторно привязываются к фактически принятой вершине.
- PHAR публикуется только workflow после push тега. Ручной GitHub Release заранее создаёт риск расхождения артефакта и тега.
- Локальные копии трёх навыков, созданные пользователем, не входят в управление выпуском и не должны молча изменяться.
