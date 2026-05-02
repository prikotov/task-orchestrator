#!/bin/sh
# ──────────────────────────────────────────────────────────────────────────────
# notify.sh — CESP-aware post_step hook для task-orchestrator
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
# Звук (приоритет):
#   1. peon-ping (CESP) — голоса из игровых звуковых пакетов
#      Установка: curl -fsSL https://raw.githubusercontent.com/PeonPing/peon-ping/main/install.sh | bash
#      Пакеты:    https://openpeon.com/packs
#   2. canberra-gtk-play — системные звуки freedesktop (fallback)
#
# Пример использования в config/chains.yaml:
#   - type: agent
#     role: backend_developer_levsha
#     name: implement
#     post_step: "bin/notify.sh"
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

# ─── Звук ─────────────────────────────────────────────────────────────────────

peon_dir="$HOME/.claude/hooks/peon-ping"

play_peon_sound() {
    # Читаем активный пакет из config.json, выбираем случайный звук категории
    config="$peon_dir/config.json"
    packs_dir="$peon_dir/packs"

    if [ ! -f "$config" ] || [ ! -d "$packs_dir" ]; then
        return 1
    fi

    # Получаем имя активного пакета
    active_pack=$(python3 -c "
import json, sys
try:
    cfg = json.load(open('$config'))
    print(cfg.get('default_pack', cfg.get('active_pack', 'peon')))
except: print('peon')
" 2>/dev/null) || active_pack="peon"

    manifest="$packs_dir/$active_pack/openpeon.json"
    if [ ! -f "$manifest" ]; then
        return 1
    fi

    # Выбираем случайный звук для категории
    sound_file=$(python3 -c "
import json, random, sys
try:
    m = json.load(open('$manifest'))
    sounds = m.get('categories', {}).get('$1', {}).get('sounds', [])
    if sounds:
        print(random.choice(sounds)['file'])
except: pass
" 2>/dev/null) || return 1

    if [ -z "$sound_file" ]; then
        return 1
    fi

    full_path="$packs_dir/$active_pack/$sound_file"
    if [ ! -f "$full_path" ]; then
        return 1
    fi

    # Играем через доступный плеер
    if command -v pw-play >/dev/null 2>&1; then
        pw-play "$full_path" 2>/dev/null
    elif command -v paplay >/dev/null 2>&1; then
        paplay "$full_path" 2>/dev/null
    elif command -v aplay >/dev/null 2>&1; then
        aplay -q "$full_path" 2>/dev/null
    elif command -v ffplay >/dev/null 2>&1; then
        ffplay -nodisp -autoexit -loglevel quiet "$full_path" 2>/dev/null
    else
        return 1
    fi
}

# Приоритет: peon-ping (CESP) → canberra-gtk-play (fallback)
if [ -d "$peon_dir" ]; then
    play_peon_sound "$cesp_event" || {
        # Fallback на canberra
        if command -v canberra-gtk-play >/dev/null 2>&1; then
            if [ "$cesp_event" = "task.complete" ]; then
                canberra-gtk-play -i complete 2>/dev/null || true
            else
                canberra-gtk-play -i dialog-error 2>/dev/null || true
            fi
        fi
    }
elif command -v canberra-gtk-play >/dev/null 2>&1; then
    if [ "$cesp_event" = "task.complete" ]; then
        canberra-gtk-play -i complete 2>/dev/null || true
    else
        canberra-gtk-play -i dialog-error 2>/dev/null || true
    fi
fi

# ─── Лог в stderr (подхватывается task-orchestrator в hook stdout/stderr) ─────

echo "[$(date '+%H:%M:%S')] $title: $message ($cesp_event)" >&2
