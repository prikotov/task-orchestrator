#!/usr/bin/env bash
#
# become-role.sh <role|file> — войти в роль: путь к файлу роли и её skills.
#
# Аргумент — имя роли (snake_case, например team_lead_alex) ИЛИ путь к файлу
# роли (например docs/agents/roles/team/team_lead_alex.ru.md). Скрипт сам
# разбирает, что передано.
#
# Выводит на stdout относительный путь к файлу роли (от project root) и каталог
# её skills (<available_skills>). Агент сам читает файл роли через read — скрипт
# не выводит содержимое роли.
#
# Поиск role-file делегирован в PHP (bin/task-orchestrator agent:role-skills):
# это работает и в самом task-orchestrator, и в host-проекте — локатор корректно
# резолвит host-роли через Kernel.
#
# Exit: 0 — успех; 1 — роль не найдена или ошибка получения skills.

set -euo pipefail

if [[ $# -lt 1 || -z "${1:-}" ]]; then
    echo "Использование: $0 <role|file>" >&2
    echo "  <role>  — имя роли (snake_case), например team_lead_alex" >&2
    echo "  <file>  — путь к файлу роли, например docs/agents/roles/team/team_lead_alex.ru.md" >&2
    exit 1
fi

ARG="$1"

# Физический путь ведёт в пакет и нужен для его CLI. Логический путь сохраняет
# установленный `.agents/skills/become-role` и позволяет восстановить Composer-host,
# даже когда агент по инструкции перешёл в каталог skill через симлинк.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
SCRIPT_DIR_LOGICAL="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -L)"
PACKAGE_ROOT="$(cd "$SCRIPT_DIR/../../../../.." && pwd)"
TASK_ORCH_BIN="$PACKAGE_ROOT/bin/task-orchestrator"
HOST_SKILL_SUFFIX="/.agents/skills/become-role/scripts"
PROJECT_ROOT=""

if [[ "$SCRIPT_DIR_LOGICAL" == *"$HOST_SKILL_SUFFIX" ]]; then
    PROJECT_ROOT="${SCRIPT_DIR_LOGICAL%"$HOST_SKILL_SUFFIX"}"
fi

# Runtime-привязка PHAR: `agent:init` из PHAR-дистрибутива создаёт управляемую
# копию skill (она не принадлежит package tree и сама путь к архиву не найдёт)
# и записывает рядом с корнем skill физический путь task-orchestrator.phar.
# source/Composer-установка файла не создаёт и использует штатный
# bin/task-orchestrator пакета.
PHAR_BINDING_FILE="$SCRIPT_DIR/../.phar-binding"
PHAR_PATH=""

if [[ -f "$PHAR_BINDING_FILE" ]]; then
    PHAR_PATH="$(<"$PHAR_BINDING_FILE")"

    # Пустая или повреждённая привязка (файл есть, физического пути нет) —
    # не fallback на отсутствующий host bin/task-orchestrator: управляемая
    # копия без рабочей привязки неработоспособна, источник один — повторная
    # установка из актуального расположения PHAR.
    if [[ -z "${PHAR_PATH//[[:space:]]/}" ]]; then
        echo "Ошибка: runtime-привязка PHAR пуста или повреждена: ${PHAR_BINDING_FILE}." >&2
        echo "Выполните повторную установку из актуального расположения PHAR: agent:init --force." >&2
        exit 1
    fi
fi

# PHAR перемещён или удалён после установки: привязка устарела, обновить её
# может только повторная установка из нового расположения (agent:init --force).
if [[ -n "$PHAR_PATH" && ! -f "$PHAR_PATH" ]]; then
    echo "Ошибка: PHAR из runtime-привязки не найден: ${PHAR_PATH}." >&2
    echo "Выполните повторную установку из нового расположения PHAR: agent:init --force." >&2
    exit 1
fi

# Имя роли из basename файла роли: team_lead_alex.ru.md → team_lead_alex.
role_name_from_file() {
    local name
    name="$(basename "$1")"          # team_lead_alex.ru.md
    name="${name%.md}"               # team_lead_alex.ru
    name="${name%.[a-z][a-z]}"       # team_lead_alex (убрать суффикс локали)
    echo "$name"
}

if [[ -f "$ARG" ]]; then
    ROLE_NAME="$(role_name_from_file "$ARG")"
elif [[ -n "$PROJECT_ROOT" && -f "$PROJECT_ROOT/$ARG" ]]; then
    ROLE_NAME="$(role_name_from_file "$PROJECT_ROOT/$ARG")"
else
    ROLE_NAME="$ARG"
fi

# CLI-запуск agent:role-skills: в управляемой PHAR-копии — через привязанный
# архив (php <phar-path>), иначе штатный bin/task-orchestrator пакета.
run_cli() {
    if [[ -n "$PHAR_PATH" ]]; then
        php "$PHAR_PATH" "$@"
    else
        "$TASK_ORCH_BIN" "$@"
    fi
}

run_role_skills() {
    if [[ -n "$PROJECT_ROOT" ]]; then
        (
            cd "$PROJECT_ROOT"
            run_cli agent:role-skills "$ROLE_NAME" --format=json
        )

        return
    fi

    run_cli agent:role-skills "$ROLE_NAME" --format=json
}

# agent:role-skills через CLI пакета (host-aware): bin/task-orchestrator в
# source/Composer либо привязанный PHAR в управляемой копии. --format=json:
# {role, role_file, skills, catalog}. CLI делает fail-fast при ошибках
# (роль/skill не найдены, цикл depends_on) — ненулевой exit.
if ! OUTPUT="$(run_role_skills)"; then
    echo "Ошибка: не удалось получить данные роли \"${ROLE_NAME}\"." >&2
    exit 1
fi

if ! RENDERED="$(php -r '
try {
    $payload = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);

    if (
        !is_array($payload)
        || !isset($payload["role_file"], $payload["catalog"])
        || !is_string($payload["role_file"])
        || !is_string($payload["catalog"])
    ) {
        throw new UnexpectedValueException("Unexpected agent:role-skills payload.");
    }

    printf("Файл роли: %s\n\n%s", $payload["role_file"], $payload["catalog"]);
} catch (Throwable) {
    fwrite(STDERR, "Ошибка: некорректный JSON от agent:role-skills.\n");
    exit(1);
}
' <<< "$OUTPUT")"; then
    echo "Ошибка: не удалось обработать данные роли \"${ROLE_NAME}\"." >&2
    exit 1
fi

echo "Роль: ${ROLE_NAME}"
printf '%s\n' "$RENDERED"
