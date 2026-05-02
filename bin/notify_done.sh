#!/bin/sh
# ──────────────────────────────────────────────────────────────────────────────
# notify_done.sh — CESP-aware post_step hook для task-orchestrator
#
# Вызывается после завершения шага цепочки.
# Отправляет desktop-уведомление + звук через peon-ping (CESP) или canberra.
#
# CESP Event Mapping:
#   HOOK_EXIT_CODE=0  → task.complete
#   HOOK_EXIT_CODE≠0  → task.error
#
# Env vars (передаются автоматически task-orchestrator):
#   HOOK_CHAIN_NAME   — имя цепочки
#   HOOK_STEP_NAME    — имя шага
#   HOOK_ROLE         — роль агента
#   HOOK_EXIT_CODE    — exit code шага (0 = успех)
#   HOOK_DURATION     — длительность шага в секундах
#
# Требования (одно из):
#   - peon-ping (рекомендуется): brew install PeonPing/tap/peon-ping
#     или curl -fsSL https://raw.githubusercontent.com/PeonPing/peon-ping/main/install.sh | bash
#   - canberra-gtk-play (fallback, только task.complete)
#
# Пример использования в config/chains.yaml:
#   - type: agent
#     role: backend_developer_levsha
#     name: implement
#     post_step: "bin/notify_done.sh"
#
# Спецификация CESP: https://openpeon.com/spec
# Звуковые пакеты:   https://openpeon.com/packs
# ──────────────────────────────────────────────────────────────────────────────

set -eu

chain="${HOOK_CHAIN_NAME:-unknown}"
step="${HOOK_STEP_NAME:-?}"
role="${HOOK_ROLE:-?}"
exit_code="${HOOK_EXIT_CODE:-?}"
duration="${HOOK_DURATION:-?}"

# ─── Формируем сообщение ─────────────────────────────────────────────────────

if [ "$exit_code" = "0" ]; then
    status_icon="✅"
    cesp_event="task.complete"
else
    status_icon="❌ (exit $exit_code)"
    cesp_event="task.error"
fi

title="Task Orchestrator"
message="$chain → $step ($role) $status_icon ${duration}s"

# ─── Desktop-уведомление ─────────────────────────────────────────────────────

if command -v notify-send >/dev/null 2>&1; then
    notify-send -u normal -t 4000 "$title" "$message" 2>/dev/null || true
fi

# ─── Звук: CESP через peon-ping (приоритет) ──────────────────────────────────
# peon-ping понимает CESP-события и играет соответствующие звуки из активного
# звукового пакета (Warcraft Peon, GLaDOS, StarCraft и др.)
# Пакеты: https://openpeon.com/packs

if command -v peon-ping >/dev/null 2>&1; then
    peon-ping "$cesp_event" 2>/dev/null || true
# ─── Звук: fallback через canberra-gtk-play ──────────────────────────────
elif command -v canberra-gtk-play >/dev/null 2>&1; then
    if [ "$cesp_event" = "task.complete" ]; then
        canberra-gtk-play -i complete 2>/dev/null || true
    else
        canberra-gtk-play -i dialog-error 2>/dev/null || true
    fi
fi

# ─── Лог в stderr (подхватывается task-orchestrator в hook stdout/stderr) ─────

echo "[$(date '+%H:%M:%S')] $title: $message ($cesp_event)" >&2
