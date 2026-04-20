#!/usr/bin/env bash
#
# watch-subagent.sh — запуск pi --mode json с контролем таймаутов по потоку событий.
#
# Использование:
#   ./watch-subagent.sh -s <soft-timeout> [options] <<'PROMPT'
#   <prompt>
#   PROMPT
#
# Параметры:
#   -s, --soft-timeout   — базовый таймаут в секундах (обязателен).
#   -m, --hard-timeout   — абсолютный максимум в секундах (default: 1200).
#   -t, --stall-timeout  — секунд без событий до признания зависания (default: 120).
#   -o, --output         — формат вывода через запятую: raw, text, tools, files (default: raw).
#   -r, --role-file <file> — путь к файлу описания роли (обязателен).
#   [prompt text]        — промпт. Если не указан — читается из stdin.
#
# Выход:
#   stdout — отфильтрованный вывод (зависит от -o).
#   exit 0 — агент завершился сам (agent_end).
#   exit 1 — агент убит по таймауту или ошибке.

set -euo pipefail

HARD_TIMEOUT=1200
STALL_TIMEOUT=120
SOFT_TIMEOUT=""
OUTPUT="raw"
ROLE_FILE=""

# Определяем пути относительно расположения скрипта
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SYSTEM_PROMPT_FILE="$SCRIPT_DIR/subagent_system.txt"

while [[ $# -gt 0 ]]; do
    case "$1" in
        -s|--soft-timeout)  SOFT_TIMEOUT="$2"; shift 2 ;;
        -m|--hard-timeout)  HARD_TIMEOUT="$2"; shift 2 ;;
        -t|--stall-timeout) STALL_TIMEOUT="$2"; shift 2 ;;
        -o|--output)        OUTPUT="$2"; shift 2 ;;
        -r|--role-file)    ROLE_FILE="$2"; shift 2 ;;
        -h|--help)
            echo "Использование: $0 -s <soft-timeout> [options] [prompt text]"
            echo "  -s, --soft-timeout   базовый таймаут в секундах (обязателен)"
            echo "  -m, --hard-timeout   абсолютный максимум в секундах (default: 1200)"
            echo "  -t, --stall-timeout  секунд без событий до зависания (default: 120)"
            echo "  -o, --output         формат вывода через запятую: raw, text, tools, files (default: raw)"
            echo "  -r, --role-file <file> путь к файлу описания роли (обязателен)"
            exit 0
            ;;
        *) break ;;
    esac
done

PROMPT=""
if [[ $# -ge 1 ]]; then
    PROMPT="$*"
elif [[ ! -t 0 ]]; then
    PROMPT=$(cat)
fi

if [[ -z "$SOFT_TIMEOUT" ]]; then
    echo "Ошибка: -s/--soft-timeout обязателен" >&2
    exit 1
fi

if [[ -z "$PROMPT" ]]; then
    echo "Ошибка: промпт не задан" >&2
    exit 1
fi

if [[ -z "$ROLE_FILE" ]]; then
    echo "Ошибка: -r/--role-file обязателен" >&2
    exit 1
fi

if [[ ! -f "$ROLE_FILE" ]]; then
    echo "Ошибка: файл роли не найден: $ROLE_FILE" >&2
    exit 1
fi
IFS=',' read -ra FORMATS <<< "$OUTPUT"
VALID="raw text tools files"
for fmt in "${FORMATS[@]}"; do
    fmt=$(xargs <<< "$fmt")  # trim
    if ! echo "$VALID" | grep -qw "$fmt"; then
        echo "Ошибка: неизвестный формат вывода '$fmt' (допустимо: raw, text, tools, files)" >&2
        exit 1
    fi
done

has_format() {
    for fmt in "${FORMATS[@]}"; do
        [[ "$(xargs <<< "$fmt")" == "$1" ]] && return 0
    done
    return 1
}

filter_text() {
    jq -r 'select(.type == "message_end" and .message.role == "assistant")
           | .message.content[]
           | select(.type == "text")
           | .text' "$1" 2>/dev/null || true
}

filter_tools() {
    jq -c 'select(.type == "tool_execution_start")
           | {toolName, args}' "$1" 2>/dev/null || true
}

filter_files() {
    jq -c 'select(.type == "tool_execution_start"
                  and (.toolName == "edit" or .toolName == "write"))
           | {toolName, args}' "$1" 2>/dev/null || true
}

TMPDIR=$(mktemp -d)
PIPE="$TMPDIR/events.pipe"
OUTFILE="$TMPDIR/events.ndjson"
mkfifo "$PIPE"

PI_PID=""

cleanup() {
    [[ -n "$PI_PID" ]] && kill "$PI_PID" 2>/dev/null || true
    rm -rf "$TMPDIR"
}
trap cleanup EXIT

# Парсим список форматов

# Формируем команду pi с упрощённым системным промптом и ролью
PI_CMD=(pi --mode json --no-session --system-prompt "$SYSTEM_PROMPT_FILE")
PI_CMD+=(--append-system-prompt "Возьми на себя роль из файла: $ROLE_FILE")
"${PI_CMD[@]}" <<< "$PROMPT" > "$PIPE" 2>/dev/null &
PI_PID=$!

START_TIME=$(date +%s)
last_event_time=$START_TIME
STREAM_RAW=false
has_format raw && STREAM_RAW=true

while IFS= read -r -t "$STALL_TIMEOUT" line; do
    echo "$line" >> "$OUTFILE"

    last_event_time=$(date +%s)

    # raw — стримим на stdout сразу
    $STREAM_RAW && echo "$line"

    # Агент завершился сам
    if [[ "$line" == *"agent_end"* ]]; then
        # Выводим все не-raw форматы
        if has_format text; then filter_text "$OUTFILE"; fi
        if has_format tools; then filter_tools "$OUTFILE"; fi
        if has_format files; then filter_files "$OUTFILE"; fi
        wait "$PI_PID" 2>/dev/null || true
        exit 0
    fi

    # Проверяем жёсткий таймаут
    now=$(date +%s)
    elapsed=$((now - START_TIME))

    if [[ $elapsed -ge $HARD_TIMEOUT ]]; then
        echo '{"type":"_watch_timeout","reason":"hard","elapsed":'${elapsed}'}' >&2
        exit 1
    fi

done < "$PIPE"

# read вернул ошибку — либо stall, либо pipe закрылся
now=$(date +%s)
elapsed=$((now - last_event_time))

if [[ $elapsed -ge $STALL_TIMEOUT ]]; then
    echo '{"type":"_watch_timeout","reason":"stall","stalled":'${elapsed}'}' >&2
    exit 1
fi

# Pipe закрылся — pi завершился нормально
if has_format text; then filter_text "$OUTFILE"; fi
if has_format tools; then filter_tools "$OUTFILE"; fi
if has_format files; then filter_files "$OUTFILE"; fi
wait "$PI_PID" 2>/dev/null || true
exit 0
