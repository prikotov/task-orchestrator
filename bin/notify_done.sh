#!/bin/sh
# ──────────────────────────────────────────────────────────────────────────────
# notify_done.sh — post_step hook для task-orchestrator
#
# Вызывается после завершения шага цепочки.
# Отправляет desktop-уведомление через notify-send + звук через canberra-gtk-play.
#
# Env vars (передаются автоматически task-orchestrator):
#   HOOK_CHAIN_NAME   — имя цепочки
#   HOOK_STEP_NAME    — имя шага
#   HOOK_ROLE         — роль агента
#   HOOK_EXIT_CODE    — exit code шага (0 = успех)
#   HOOK_DURATION     — длительность шага в секундах
#
# Пример использования в config/chains.yaml:
#   - type: agent
#     role: backend_developer_levsha
#     name: implement
#     post_step: "bin/notify_done.sh"
# ──────────────────────────────────────────────────────────────────────────────

set -eu

chain="${HOOK_CHAIN_NAME:-unknown}"
step="${HOOK_STEP_NAME:-?}"
role="${HOOK_ROLE:-?}"
exit_code="${HOOK_EXIT_CODE:-?}"
duration="${HOOK_DURATION:-?}"

# Формируем сообщение
if [ "$exit_code" = "0" ]; then
    status="✅"
else
    status="❌ (exit $exit_code)"
fi

title="Task Orchestrator"
message="$chain → $step ($role) $status ${duration}s"

# Desktop-уведомление (Linux)
if command -v notify-send >/dev/null 2>&1; then
    notify-send -u normal -t 4000 "$title" "$message" 2>/dev/null || true
fi

# Звук
if command -v canberra-gtk-play >/dev/null 2>&1; then
    canberra-gtk-play -i complete 2>/dev/null || true
fi

# Лог в stderr (подхватывается task-orchestrator в hook stdout/stderr)
echo "[$(date '+%H:%M:%S')] $title: $message" >&2
