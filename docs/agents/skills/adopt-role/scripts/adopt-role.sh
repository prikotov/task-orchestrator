#!/usr/bin/env bash
#
# adopt-role.sh — вход в роль агента: резолвит файл роли и объявляет её skills.
#
# Универсальный механизм «adopt-role»: агент вызывает этот скрипт, чтобы
# одновременно получить путь к файлу роли (для чтения через read) и каталог
# skills роли (XML-блок `<available_skills>` для контекста). Работает и в pi,
# и в codex, т.к. опирается только на чтение файлов и bin/console.
#
# Использование:
#   scripts/adopt-role.sh <role>
#
# Где <role> — имя роли (snake_case), как в config/chains.yaml `roles.<role>`
# и имя файла роли без локали (например, team_lead_alex).
#
# Выход (stdout) — блок для контекста агента:
#   - заголовок роли и абсолютный путь к её файлу (агент читает через read);
#   - XML-каталог skills роли (с развёрнутыми depends_on).
#
# Exit:
#   0 — успех; 1 — роль не найдена или ошибка резолвинга.

set -euo pipefail

if [[ $# -lt 1 || -z "${1:-}" ]]; then
    echo "Использование: $0 <role>" >&2
    echo "  <role> — имя роли (snake_case), например team_lead_alex" >&2
    exit 1
fi

ROLE_NAME="$1"

# Пути вычисляются от расположения скрипта (как в watch-subagent.sh).
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../../.." && pwd)"
ROLES_DIR="${PROJECT_ROOT}/docs/agents/roles/team"
CONSOLE="${PROJECT_ROOT}/bin/console"

# Поиск файла роли: предпочтение .ru.md, затем .md, затем любой .<locale>.md.
find_role_file() {
    local role="$1"
    local candidate

    for candidate in \
        "${ROLES_DIR}/${role}.ru.md" \
        "${ROLES_DIR}/${role}.md"; do
        if [[ -f "$candidate" ]]; then
            realpath "$candidate"
            return 0
        fi
    done

    # Fallback: любой <role>.<locale>.md.
    local found
    found="$(find "$ROLES_DIR" -maxdepth 1 -type f -name "${role}.*.md" 2>/dev/null | head -1 || true)"
    if [[ -n "$found" ]]; then
        realpath "$found"
        return 0
    fi

    return 1
}

ROLE_FILE="$(find_role_file "$ROLE_NAME")" || {
    echo "Ошибка: файл роли \"${ROLE_NAME}\" не найден в ${ROLES_DIR}." >&2
    exit 1
}

echo "# Роль: ${ROLE_NAME}"
echo ""
echo "Файл роли (прочитай полностью через read перед началом работы):"
echo "  ${ROLE_FILE}"
echo ""

# Каталог skills роли (XML-блок). bin/console делает fail-fast при ошибках
# (несуществующий skill, цикл depends_on) — выводит ошибку в stderr и exit 1.
if ! "$CONSOLE" agent:role-skills "$ROLE_NAME" --format=block; then
    echo "Ошибка: не удалось развернуть skills роли \"${ROLE_NAME}\"." >&2
    exit 1
fi
