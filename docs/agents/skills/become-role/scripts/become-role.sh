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

# Каталог пакета (через симлинк .agents/ pwd -P даёт физический путь к пакету);
# bin/task-orchestrator сам определяет контекст (standalone vs vendor/host).
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
PACKAGE_ROOT="$(cd "$SCRIPT_DIR/../../../../.." && pwd)"
TASK_ORCH_BIN="$PACKAGE_ROOT/bin/task-orchestrator"

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
else
    ROLE_NAME="$ARG"
fi

# agent:role-skills через bin/task-orchestrator (host-aware). --format=json:
# {role, role_file, skills, catalog}. bin/task-orchestrator делает fail-fast
# при ошибках (роль/skill не найдены, цикл depends_on) — ненулевой exit.
if ! OUTPUT="$("$TASK_ORCH_BIN" agent:role-skills "$ROLE_NAME" --format=json)"; then
    echo "Ошибка: не удалось получить данные роли \"${ROLE_NAME}\"." >&2
    exit 1
fi

ROLE_FILE="$(jq -r '.role_file' <<< "$OUTPUT")"
CATALOG="$(jq -r '.catalog' <<< "$OUTPUT")"

echo "Роль: ${ROLE_NAME}"
echo "Файл роли: ${ROLE_FILE}"
echo ""
echo "$CATALOG"
