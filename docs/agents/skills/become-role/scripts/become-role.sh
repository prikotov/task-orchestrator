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

run_role_skills() {
    if [[ -n "$PROJECT_ROOT" ]]; then
        (
            cd "$PROJECT_ROOT"
            "$TASK_ORCH_BIN" agent:role-skills "$ROLE_NAME" --format=json
        )

        return
    fi

    "$TASK_ORCH_BIN" agent:role-skills "$ROLE_NAME" --format=json
}

# agent:role-skills через bin/task-orchestrator (host-aware). --format=json:
# {role, role_file, skills, catalog}. bin/task-orchestrator делает fail-fast
# при ошибках (роль/skill не найдены, цикл depends_on) — ненулевой exit.
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
