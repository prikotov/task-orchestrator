#!/usr/bin/env bash
# CONTRACT: v1
#
# watch-subagent.sh — запуск AI-агента (pi/codex) с контролем таймаутов по потоку событий.
#
# Использование:
#   ./watch-subagent.sh -s <soft-timeout> --runner <pi|codex> [options] <<'PROMPT'
#   <prompt>
#   PROMPT
#
# Параметры:
#   -s, --soft-timeout   — базовый таймаут в секундах (обязателен).
#   -m, --hard-timeout   — абсолютный максимум в секундах (default: 1200).
#   -t, --stall-timeout  — секунд без событий до признания зависания (default: 120).
#   -o, --output         — формат вывода через запятую: raw, text, tools, files (default: raw).
#   -r, --role-file <file> — путь к файлу описания роли (обязателен).
#   --runner <pi|codex>  — раннер (default: pi; env RUNNER).
#   --model <string>     — модель (env MODEL).
#   --reasoning <string> — reasoning effort для codex (→ -c model_reasoning_effort=...).
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
RUNNER="${RUNNER:-pi}"
MODEL="${MODEL:-}"
REASONING=""

# Определяем пути относительно расположения скрипта
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SYSTEM_PROMPT_FILE="$SCRIPT_DIR/subagent_system.txt"

while [[ $# -gt 0 ]]; do
    case "$1" in
        -s|--soft-timeout)  SOFT_TIMEOUT="$2"; shift 2 ;;
        -m|--hard-timeout)  HARD_TIMEOUT="$2"; shift 2 ;;
        -t|--stall-timeout) STALL_TIMEOUT="$2"; shift 2 ;;
        -o|--output)        OUTPUT="$2"; shift 2 ;;
        -r|--role-file)     ROLE_FILE="$2"; shift 2 ;;
        --runner)           RUNNER="$2"; shift 2 ;;
        --model)            MODEL="$2"; shift 2 ;;
        --reasoning)        REASONING="$2"; shift 2 ;;
        -h|--help)
            echo "Использование: $0 -s <soft-timeout> [options] [prompt text]"
            echo "  -s, --soft-timeout   базовый таймаут в секундах (обязателен)"
            echo "  -m, --hard-timeout   абсолютный максимум в секундах (default: 1200)"
            echo "  -t, --stall-timeout  секунд без событий до зависания (default: 120)"
            echo "  -o, --output         формат вывода через запятую: raw, text, tools, files (default: raw)"
            echo "  -r, --role-file <file> путь к файлу описания роли (обязателен)"
            echo "  --runner <pi|codex>  раннер (default: pi; env RUNNER)"
            echo "  --model <string>     модель (env MODEL)"
            echo "  --reasoning <string> reasoning effort для codex"
            echo "  -h, --help           эта справка"
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

case "$RUNNER" in
    pi|codex) ;;
    *)
        echo "Ошибка: неизвестный раннер '$RUNNER' (допустимо: pi, codex)" >&2
        exit 1
        ;;
esac

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

# ============================================================================
# Per-runner: построение команды
# ============================================================================

RUNNER_CMD=()

build_runner_command() {
    case "$RUNNER" in
        pi)
            RUNNER_CMD=(pi --mode json --no-session --system-prompt "$SYSTEM_PROMPT_FILE")
            [[ -n "$MODEL" ]] && RUNNER_CMD+=(--model "$MODEL")
            RUNNER_CMD+=(--append-system-prompt "Возьми на себя роль из файла: $ROLE_FILE")
            ;;
        codex)
            RUNNER_CMD=(codex exec --json --dangerously-bypass-approvals-and-sandbox --skip-git-repo-check --ephemeral)
            [[ -n "$MODEL" ]] && RUNNER_CMD+=(--model "$MODEL")
            [[ -n "$REASONING" ]] && RUNNER_CMD+=(-c "model_reasoning_effort=$REASONING")
            # codex использует системный промпт через instructions
            RUNNER_CMD+=(-c "model_instructions_file=$SYSTEM_PROMPT_FILE")
            RUNNER_CMD+=(-c "additional_instructions=Возьми на себя роль из файла: $ROLE_FILE")
            ;;
    esac
}

# ============================================================================
# Per-runner: фильтрация вывода
# ============================================================================

# --- pi ---

filter_text_pi() {
    jq -r 'select(.type == "message_end" and .message.role == "assistant")
           | .message.content[]
           | select(.type == "text")
           | .text' "$1" 2>/dev/null || true
}

filter_tools_pi() {
    jq -c 'select(.type == "tool_execution_start")
           | {toolName, args}' "$1" 2>/dev/null || true
}

filter_files_pi() {
    jq -c 'select(.type == "tool_execution_start"
                  and (.toolName == "edit" or .toolName == "write"))
           | {toolName, args}' "$1" 2>/dev/null || true
}

# --- codex ---

filter_text_codex() {
    # @todo Формат JSONL codex может отличаться — уточнить после реальных тестов
    jq -r 'select(.type == "message_end" and .message.role == "assistant")
           | .message.content[]
           | select(.type == "text")
           | .text' "$1" 2>/dev/null || true
}

filter_tools_codex() {
    # @todo Формат JSONL codex может отличаться — уточнить после реальных тестов
    jq -c 'select(.type == "tool_execution_start")
           | {toolName, args}' "$1" 2>/dev/null || true
}

filter_files_codex() {
    # @todo Формат JSONL codex может отличаться — уточнить после реальных тестов
    jq -c 'select(.type == "tool_execution_start"
                  and (.toolName == "edit" or .toolName == "write"))
           | {toolName, args}' "$1" 2>/dev/null || true
}

# --- Dispatchers ---

filter_text() {
    case "$RUNNER" in
        pi)    filter_text_pi "$@" ;;
        codex) filter_text_codex "$@" ;;
    esac
}

filter_tools() {
    case "$RUNNER" in
        pi)    filter_tools_pi "$@" ;;
        codex) filter_tools_codex "$@" ;;
    esac
}

filter_files() {
    case "$RUNNER" in
        pi)    filter_files_pi "$@" ;;
        codex) filter_files_codex "$@" ;;
    esac
}

# ============================================================================
# Подготовка и запуск
# ============================================================================

TMPDIR=$(mktemp -d)
PIPE="$TMPDIR/events.pipe"
OUTFILE="$TMPDIR/events.ndjson"
mkfifo "$PIPE"

AGENT_PID=""

# Рекурсивно собрать все PID-потомки процесса
_get_descendants() {
    local pid=$1
    local children
    children=$(pgrep -P "$pid" 2>/dev/null || true)
    for child in $children; do
        echo "$child"
        _get_descendants "$child"
    done
}

# Убиваем агент и ВСЕ его дочерние процессы (включая orphaned)
kill_agent_tree() {
    if [[ -n "$AGENT_PID" ]]; then
        # 1. SIGTERM (даём шанс graceful shutdown)
        kill "$AGENT_PID" 2>/dev/null || true
        # 2. Ждём до 3 сек
        local waited=0
        while [[ $waited -lt 3 ]] && kill -0 "$AGENT_PID" 2>/dev/null; do
            sleep 1
            waited=$((waited + 1))
        done
        # 3. Собираем ВСЕХ потомков (pgrep -P рекурсивно)
        local all_pids
        all_pids=$(_get_descendants "$AGENT_PID")
        # 4. SIGKILL всех потомков (младшие → старшие)
        for pid in $(echo "$all_pids" | tac); do
            kill -9 "$pid" 2>/dev/null || true
        done
        # 5. SIGKILL агент
        kill -9 "$AGENT_PID" 2>/dev/null || true
        AGENT_PID=""
    fi
}

cleanup() {
    kill_agent_tree
    rm -rf "$TMPDIR"
}
trap cleanup EXIT

# Формируем команду раннера
build_runner_command

# Запускаем
"${RUNNER_CMD[@]}" <<< "$PROMPT" > "$PIPE" 2>/dev/null &
AGENT_PID=$!

# Даём агенту 5 сек на запуск, проверяем что он жив
sleep 0.2
if ! kill -0 "$AGENT_PID" 2>/dev/null; then
    echo "{\"type\":\"_watch_error\",\"reason\":\"${RUNNER}_start_failed\"}" >&2
    exit 1
fi

# Вычисляем таймауты: soft — основной, hard — абсолютный потолок
# SOFT_TIMEOUT обязателен, hard не может быть < soft
# Если -m не задан явно, hard = max(soft*2, 1200)
EFFECTIVE_HARD=${HARD_TIMEOUT}
if [[ -n "$SOFT_TIMEOUT" ]]; then
    computed_hard=$((SOFT_TIMEOUT * 2))
    if [[ $computed_hard -gt $EFFECTIVE_HARD ]]; then
        EFFECTIVE_HARD=$computed_hard
    fi
fi

START_TIME=$(date +%s)
last_event_time=$START_TIME
STREAM_RAW=false
has_format raw && STREAM_RAW=true

while IFS= read -r -t "$STALL_TIMEOUT" line; do
    echo "$line" >> "$OUTFILE"

    last_event_time=$(date +%s)
    now=$last_event_time
    elapsed=$((now - START_TIME))

    # raw — стримим на stdout сразу
    $STREAM_RAW && echo "$line"

    # Агент завершился сам
    if [[ "$line" == *"agent_end"* ]]; then
        # Выводим все не-raw форматы
        if has_format text; then filter_text "$OUTFILE"; fi
        if has_format tools; then filter_tools "$OUTFILE"; fi
        if has_format files; then filter_files "$OUTFILE"; fi
        wait "$AGENT_PID" 2>/dev/null || true
        exit 0
    fi

    # Проверяем soft timeout — предупреждаем, но НЕ убиваем
    if [[ -n "$SOFT_TIMEOUT" ]] && [[ $elapsed -ge $SOFT_TIMEOUT ]]; then
        # Пишем предупреждение один раз (флаг)
        if [[ -z "${_SOFT_WARNED:-}" ]]; then
            _SOFT_WARNED=1
            echo '{"type":"_watch_timeout","reason":"soft","elapsed":'${elapsed}',"limit":'${SOFT_TIMEOUT}'}' >&2
        fi
    fi

    # Проверяем жёсткий таймаут — УБИВАЕМ
    if [[ $elapsed -ge $EFFECTIVE_HARD ]]; then
        echo '{"type":"_watch_timeout","reason":"hard","elapsed":'${elapsed}',"limit":'${EFFECTIVE_HARD}'}' >&2
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

# Pipe закрылся — агент завершился нормально
if has_format text; then filter_text "$OUTFILE"; fi
if has_format tools; then filter_tools "$OUTFILE"; fi
if has_format files; then filter_files "$OUTFILE"; fi
wait "$AGENT_PID" 2>/dev/null || true
exit 0
