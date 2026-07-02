# become-role — техническая справка

Технические детали skill `become-role`. Сама инструкция для агента — в [`SKILL.md`](SKILL.md).

## Как это работает

`become-role.sh <role|file>` — обёртка над CLI `bin/console agent:role-skills`. Аргумент — имя роли или путь к файлу роли (скрипт сам разбирает).

`become-role.sh`:
1. Находит файл роли (`docs/agents/roles/team/<role>.ru.md` → `<role>.md` → любой `<role>.<locale>.md`) и выводит его **относительный путь** (от project root).
2. Вызывает `agent:role-skills`, который читает frontmatter роли (`skills:`), транзитивно разворачивает `depends_on` и формирует XML-блок `<available_skills>` (формат Agent Skills / pi): для каждого skill — `name`, `description`, абсолютный `location` его `SKILL.md`.

Скрипт **не выводит содержимое роли** — только путь и XML-блок skills. Агент сам читает файл роли через `read` (получает личность: personality, экспертизу, стиль) и по описанию открывает нужный `SKILL.md` — только подходящий к задаче, не все сразу.

Роль без `skills:` в frontmatter → только путь к файлу роли (XML-блок skills пуст).

## Настройка (после клонирования)

Каталог `.agents/` находится в `.gitignore` — он создаётся per-environment. Чтобы pi/codex увидели `become-role` как нативный skill (через кросс-клиентскую конвенцию `.agents/skills/`), один раз выполни:

```bash
bin/console agent:init
```

Команда создаёт симлинк `.agents/skills/become-role` → `docs/agents/skills/become-role` (идемпотентна, `--force` заменяет некорректный). Проверь: `ls .agents/skills/` должен показывать `become-role`. Для host-проектов — `php vendor/bin/task-orchestrator agent:init`.

## Альтернативный вызов (без скрипта)

```bash
bin/console agent:role-skills <role> --format=block
```

Форматы: `block` (XML `<available_skills>`), `list` (`name — description` + `location`), `json` (`{role, skills[], catalog}`).

Отличие от `become-role.sh`: `agent:role-skills` выводит **только XML-блок skills**. `become-role.sh` = путь к файлу роли + XML-блок skills (агент читает роль сам по пути).

## Поведение при ошибках (fail-fast)

При ошибках скрипт/команда завершаются ненулёвым кодом — агент должен остановиться и сообщить ошибку, не угадывая путь или имя:

- роль не найдена — нет файла `<role>.*.md` в `docs/agents/roles/team/`;
- skill не найден — роль декларирует skill, которого нет в `docs/agents/skills/`;
- цикл `depends_on` — циклическая зависимость между skills роли.

## Принцип

`become-role` — единственный **общий** skill (виден всем агентам через `.agents/skills/`). Он резолвит skills конкретной роли из её frontmatter и объявляет их в контексте, поэтому role-специфичные skills **не нужно** размещать в автозагружаемых локациях (`.pi/skills/`, `.codex/skills/`), где их увидели бы все роли. Изоляция контекста по роли достигается промптом (агент сам вызывает `become-role` для нужной роли), а не фильтрацией автозагрузки.
