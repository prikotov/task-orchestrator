# become-role — техническая справка

Технические детали skill `become-role`. Сама инструкция для агента — в [`SKILL.md`](SKILL.md).

## Как это работает

`.agents/skills/become-role/scripts/become-role.sh <role|file>` — установленная обёртка над CLI-командой `agent:role-skills`. Аргумент — имя роли или путь к файлу роли (скрипт сам разбирает).

`become-role.sh`:
1. В Composer-host восстанавливает корень host-проекта по логическому пути установленного `.agents/skills/become-role`, даже если текущий каталог физически находится внутри `vendor/` после перехода по симлинку.
2. Находит файл роли (`docs/agents/roles/team/<role>.ru.md` → `<role>.md` → любой `<role>.<locale>.md`) и выводит его **относительный путь** (от project root).
3. Вызывает `agent:role-skills`, который читает frontmatter роли (`skills:`), транзитивно разворачивает `depends_on` и формирует XML-блок `<available_skills>` (формат Agent Skills / pi): для каждого skill — `name`, `description`, абсолютный `location` его `SKILL.md`.

Скрипт **не выводит содержимое роли** — только путь и XML-блок skills. Агент сам читает файл роли через `read` (получает личность: personality, экспертизу, стиль) и по описанию открывает нужный `SKILL.md` — только подходящий к задаче, не все сразу.

Роль без `skills:` в frontmatter → только путь к файлу роли (XML-блок skills пуст).

## Установка и запуск

Каталог `.agents/` находится в `.gitignore` — он создаётся отдельно для каждого окружения. Чтобы pi/codex увидели `become-role` как нативный skill через кросс-клиентскую конвенцию `.agents/skills/`, один раз выполни подходящую команду.

В source checkout (локальной копии исходников):

```bash
bin/console agent:init
```

В host-проекте с Composer-установкой:

```bash
php vendor/bin/task-orchestrator agent:init
```

Команда создаёт относительный симлинк `.agents/skills/become-role` на skill внутри пакета. Она идемпотентна; `--force` заменяет некорректный симлинк. После установки запускай скрипт из корня skill, как требует `SKILL.md`:

```bash
cd .agents/skills/become-role
scripts/become-role.sh <role|file>
```

Обёртка сохраняет корень host-проекта и передаёт его CLI неявно через рабочий каталог. Контейнер CLI хранится отдельно от кеша host-приложения: `<host>/var/cache/task-orchestrator/<version>/<env>`. Поэтому обновление пакета не требует ручной очистки общего `<host>/var/cache/<env>`.

В `v0.2.0` установка поддерживается только для Source/Composer. PHAR — secondary/best-effort канал: `agent:init` зарегистрирован, но завершается с кодом `1` до записи файлов и рекомендует Composer; `--force` не меняет это поведение и не создаёт `.agents`. Подробная матрица возможностей приведена в [CLI guide](../../../guide/cli.md#agentinit).

## Альтернативный вызов (без скрипта)

```bash
# Source checkout
bin/console agent:role-skills <role> --format=block

# Composer host
php vendor/bin/task-orchestrator agent:role-skills <role> --format=block
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
