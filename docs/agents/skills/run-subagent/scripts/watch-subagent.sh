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
#   -s, --soft-timeout   — базовый таймаут в секундах (обязателен). ЦЕЛЕВОЕ время
#                          задачи: по умолчанию превышение soft УБИВАЕТ запуск
#                          (pi крутит turn'ы без лимита — soft это страховка от
#                          сжигания токенов). Env WATCH_SOFT_WARN_ONLY=1 → только
#                          предупреждать, не убивать (для экспериментов).
#   -m, --hard-timeout   — абсолютный максимум в секундах (default: 1800).
#   -t, --stall-timeout  — секунд без событий до признания зависания (default: 180).
#   -o, --output         — формат вывода через запятую: raw, text, tools, files (default: raw).
#   -r, --role-file <file> — путь к файлу описания роли (обязателен).
#   --runner <pi|codex>  — раннер (priority: CLI > env RUNNER > role profile > pi).
#   --provider <string>  — провайдер pi (priority: CLI > env PROVIDER > role profile).
#   --model <string>     — модель (priority: CLI > env MODEL > role profile).
#   --reasoning <string> — reasoning/thinking effort (priority: CLI > env REASONING > role profile).
#   [prompt text]        — промпт. Если не указан — читается из stdin.
#
# Переменные окружения (наблюдаемость):
#   WATCH_LOG_DIR          — каталог run-логов (default: var/log/watch-subagent).
#   WATCH_KEEP_TMP=1       — сохранять TMPDIR (events.ndjson, gaps.tsv, stderr)
#                            ВСЕГДА, даже при успехе (по умолчанию — только при сбое).
#   WATCH_SOFT_WARN_ONLY=1 — soft-timeout только предупреждает, НЕ убивает
#                            (по умолчанию soft-timeout УБИВАЕТ, т.к. pi крутит
#                            turn'ы без лимита и soft — основная защита от сжигания
#                            токенов). Использовать только для экспериментов.
#
# Переменные окружения (наследование от внешней обёртки):
#   RUNNER/PROVIDER/MODEL/REASONING — см. приоритеты выше.
#
# Наблюдаемость: каждый запуск пишет run-log в WATCH_LOG_DIR/<ts>-<runner>-<role>.log
# (RUN START с runner-cmd/timeouts, RUN SUMMARY с reason/duration/event-counts/
# max-gap). При ненормальном завершении (timeout/stall/missing_agent_end/внешний
# сигнал) TMPDIR архивируется в WATCH_LOG_DIR/<run-id>/events/ для постмортема.
#
# ⚠️ ВАЖНО про внешние обёртки (bash `timeout` и т.п.): НЕ оборачивайте запуск в
# внешний timeout МЕНЬШЕ hard-timeout скрипта — скрипт сам корректно завершится
# через свой stall/hard-timeout с правильным reason и архивом. Внешний kill
# раньше времени ловится (reason=external_signal), но теряет детальную причину.
# Если обёртка нужна — ставьте её timeout ≥ hard-timeout + 60с buffer.
#
# Выход:
#   stdout — отфильтрованный вывод (зависит от -o).
#   exit 0 — агент завершился сам (pi: agent_end, codex: turn.completed).
#   exit 1 — агент убит по таймауту или ошибке.

set -euo pipefail

HARD_TIMEOUT=1800
STALL_TIMEOUT=180
SOFT_TIMEOUT=""
OUTPUT="raw"
ROLE_FILE=""
RUNNER="${RUNNER:-}"
PROVIDER="${PROVIDER:-}"
MODEL="${MODEL:-}"
REASONING="${REASONING:-}"
RUNNER_EXPLICIT=false
PROVIDER_EXPLICIT=false
PROVIDER_CLI_EXPLICIT=false
MODEL_EXPLICIT=false
REASONING_EXPLICIT=false
[[ -n "$RUNNER" ]] && RUNNER_EXPLICIT=true
[[ -n "$PROVIDER" ]] && PROVIDER_EXPLICIT=true
[[ -n "$MODEL" ]] && MODEL_EXPLICIT=true
[[ -n "$REASONING" ]] && REASONING_EXPLICIT=true

# Определяем пути относительно расположения скрипта
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../../.." && pwd)"
SYSTEM_PROMPT_FILE="$SCRIPT_DIR/subagent_system.txt"
CHAINS_CONFIG="${CHAINS_CONFIG:-$PROJECT_ROOT/config/chains.yaml}"

while [[ $# -gt 0 ]]; do
    case "$1" in
        -s|--soft-timeout)  SOFT_TIMEOUT="$2"; shift 2 ;;
        -m|--hard-timeout)  HARD_TIMEOUT="$2"; shift 2 ;;
        -t|--stall-timeout) STALL_TIMEOUT="$2"; shift 2 ;;
        -o|--output)        OUTPUT="$2"; shift 2 ;;
        -r|--role-file)     ROLE_FILE="$2"; shift 2 ;;
        --runner)           RUNNER="$2"; RUNNER_EXPLICIT=true; shift 2 ;;
        --provider)         PROVIDER="$2"; PROVIDER_EXPLICIT=true; PROVIDER_CLI_EXPLICIT=true; shift 2 ;;
        --model)            MODEL="$2"; MODEL_EXPLICIT=true; shift 2 ;;
        --reasoning)        REASONING="$2"; REASONING_EXPLICIT=true; shift 2 ;;
        -h|--help)
            echo "Использование: $0 -s <soft-timeout> [options] [prompt text]"
            echo "  -s, --soft-timeout   базовый таймаут в секундах (обязателен)"
            echo "  -m, --hard-timeout   абсолютный максимум в секундах (default: 1800)"
            echo "  -t, --stall-timeout  секунд без событий до зависания (default: 180)"
            echo "  -o, --output         формат вывода через запятую: raw, text, tools, files (default: raw)"
            echo "  -r, --role-file <file> путь к файлу описания роли (обязателен)"
            echo "  --runner <pi|codex>  раннер (priority: CLI > env RUNNER > role profile > pi)"
            echo "  --provider <string>  провайдер pi (priority: CLI > env PROVIDER > role profile)"
            echo "  --model <string>     модель (priority: CLI > env MODEL > role profile)"
            echo "  --reasoning <string> reasoning effort (priority: CLI > env REASONING > role profile)"
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

derive_role_name() {
    local role_file="$1"
    local file_name role_name
    file_name="$(basename "$role_file")"
    role_name="${file_name%.md}"
    role_name="${role_name%.[a-z][a-z]}"
    echo "$role_name"
}

load_role_profile() {
    local role_name="$1"

    [[ -f "$CHAINS_CONFIG" ]] || return 0
    command -v php >/dev/null 2>&1 || return 0

    ROLE_PROFILE_PROJECT_ROOT="$PROJECT_ROOT" \
    ROLE_PROFILE_CONFIG="$CHAINS_CONFIG" \
    ROLE_PROFILE_NAME="$role_name" \
    php <<'PHP'
<?php

declare(strict_types=1);

$projectRoot = getenv('ROLE_PROFILE_PROJECT_ROOT') ?: '';
$configPath = getenv('ROLE_PROFILE_CONFIG') ?: '';
$roleName = getenv('ROLE_PROFILE_NAME') ?: '';
$autoload = $projectRoot . '/vendor/autoload.php';

if ($projectRoot === '' || $configPath === '' || $roleName === '' || !is_file($autoload) || !is_file($configPath)) {
    exit(0);
}

require $autoload;

try {
    $config = \Symfony\Component\Yaml\Yaml::parseFile($configPath);
} catch (\Throwable) {
    exit(0);
}

$command = $config['roles'][$roleName]['command'] ?? [];
if (!is_array($command)) {
    $command = [];
}

$command = array_map(
    static fn(mixed $value): string => is_scalar($value) ? (string) $value : '',
    array_values($command),
);

$extractOption = static function (array $command, array $options): string {
    foreach ($command as $index => $argument) {
        foreach ($options as $option) {
            if ($argument === $option && isset($command[$index + 1])) {
                return $command[$index + 1];
            }

            $prefix = $option . '=';
            if (str_starts_with($argument, $prefix)) {
                return substr($argument, strlen($prefix));
            }
        }
    }

    return '';
};

$extractRunner = static function (array $command): string {
    $candidate = $command[0] ?? '';
    if ($candidate === '' || str_starts_with($candidate, '-')) {
        return '';
    }

    return basename($candidate);
};

$extractReasoningConfig = static function (string $value): string {
    if (preg_match('/model_reasoning_effort\s*=\s*["\']?([^"\'\s]+)["\']?/', $value, $matches) === 1) {
        return $matches[1];
    }

    return '';
};

$extractReasoning = static function (array $command) use ($extractOption, $extractReasoningConfig): string {
    $reasoning = $extractOption($command, ['--reasoning', '--thinking']);
    if ($reasoning !== '') {
        return trim($reasoning, "\"'");
    }

    foreach ($command as $index => $argument) {
        if ($argument === '-c' && isset($command[$index + 1])) {
            $reasoning = $extractReasoningConfig($command[$index + 1]);
            if ($reasoning !== '') {
                return $reasoning;
            }
        }

        $reasoning = $extractReasoningConfig($argument);
        if ($reasoning !== '') {
            return $reasoning;
        }
    }

    return '';
};

echo $extractRunner($command), "\n";
echo trim($extractOption($command, ['--provider']), "\"'"), "\n";
echo trim($extractOption($command, ['--model']), "\"'"), "\n";
echo $extractReasoning($command), "\n";
PHP
}

apply_role_profile_defaults() {
    local role_name="$1"
    local profile_output profile_runner profile_provider profile_model profile_reasoning
    local apply_profile_pair=true

    profile_output="$(load_role_profile "$role_name" 2>/dev/null || true)"
    profile_runner="$(sed -n '1p' <<< "$profile_output")"
    profile_provider="$(sed -n '2p' <<< "$profile_output")"
    profile_model="$(sed -n '3p' <<< "$profile_output")"
    profile_reasoning="$(sed -n '4p' <<< "$profile_output")"

    if [[ "$RUNNER_EXPLICIT" == true && "$RUNNER" != "$profile_runner" ]]; then
        apply_profile_pair=false
    fi

    if [[ "$RUNNER_EXPLICIT" == false && -z "$RUNNER" && -n "$profile_runner" ]]; then
        RUNNER="$profile_runner"
    fi

    if [[ "$apply_profile_pair" == true && "$PROVIDER_EXPLICIT" == false && -z "$PROVIDER" && -n "$profile_provider" ]]; then
        PROVIDER="$profile_provider"
    fi

    if [[ "$apply_profile_pair" == true && "$MODEL_EXPLICIT" == false && -z "$MODEL" && -n "$profile_model" ]]; then
        MODEL="$profile_model"
    fi

    if [[ "$apply_profile_pair" == true && "$REASONING_EXPLICIT" == false && -z "$REASONING" && -n "$profile_reasoning" ]]; then
        REASONING="$profile_reasoning"
    fi
}

ROLE_NAME="$(derive_role_name "$ROLE_FILE")"
apply_role_profile_defaults "$ROLE_NAME"

if [[ -z "$RUNNER" ]]; then
    RUNNER="pi"
fi

case "$RUNNER" in
    pi|codex) ;;
    *)
        echo "Ошибка: неизвестный раннер '$RUNNER' (допустимо: pi, codex)" >&2
        exit 1
        ;;
esac

if [[ "$RUNNER" != "pi" && "$PROVIDER_CLI_EXPLICIT" == true && -n "$PROVIDER" ]]; then
    echo "Ошибка: --provider поддерживается только для раннера pi" >&2
    exit 1
fi
if [[ "$RUNNER" != "pi" ]]; then
    PROVIDER=""
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

# ============================================================================
# Per-runner: построение команды
# ============================================================================

RUNNER_CMD=()

build_runner_command() {
    case "$RUNNER" in
        pi)
            RUNNER_CMD=(pi --mode json -p --no-session --system-prompt "$SYSTEM_PROMPT_FILE")
            [[ -n "$PROVIDER" ]] && RUNNER_CMD+=(--provider "$PROVIDER")
            [[ -n "$MODEL" ]] && RUNNER_CMD+=(--model "$MODEL")
            [[ -n "$REASONING" ]] && RUNNER_CMD+=(--thinking "$REASONING")
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

apply_proxy_env_defaults() {
    local proxy="${ALL_PROXY:-${all_proxy:-}}"

    if [[ -z "$proxy" ]]; then
        return 0
    fi

    export HTTP_PROXY="${HTTP_PROXY:-$proxy}"
    export HTTPS_PROXY="${HTTPS_PROXY:-$proxy}"
    export http_proxy="${http_proxy:-$proxy}"
    export https_proxy="${https_proxy:-$proxy}"
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
    jq -Rrn '
        def item_text($item):
            if (($item.type // "") != "agent_message") then
                ""
            elif (($item.text // "") != "") then
                $item.text
            elif (($item.content // null) | type) == "array" then
                [$item.content[]? | select(type == "object" and (.type // "") == "text") | (.text // "")]
                | join("")
            elif (($item.content // null) | type) == "string" then
                $item.content
            else
                ""
            end;

        def turn_text($event):
            [($event.turn.items // [])[]? | select((.type // "") == "agent_message") | item_text(.) | select(. != "")]
            | last // "";

        reduce (inputs | fromjson? | select(type == "object")) as $event ({item: "", turn: ""};
            if (($event.type // "") == "item.completed") then
                (item_text($event.item // {}) as $text
                 | if $text != "" and .turn == "" then .item = $text else . end)
            elif (($event.type // "") == "turn.completed") then
                (turn_text($event) as $text
                 | if $text != "" then .turn = $text else . end)
            else
                .
            end
        )
        | if .turn != "" then .turn else .item end' "$1" 2>/dev/null || true
}

filter_tools_codex() {
    jq -Rrc 'fromjson?
           | select(type == "object" and .type == "item.completed")
           | .item as $item
           | select(($item.type // "") | test("tool|function_call|custom_tool_call|local_shell_call|command_execution"))
           | {
               toolName: ($item.name // $item.call_type // $item.type),
               args: ($item.arguments // $item.input // $item)
             }' "$1" 2>/dev/null || true
}

filter_files_codex() {
    jq -Rrc 'fromjson?
           | select(type == "object" and .type == "item.completed")
           | .item as $item
           | select(($item.type // "") | test("tool|function_call|custom_tool_call|local_shell_call|command_execution"))
           | {
               toolName: ($item.name // $item.call_type // $item.type),
               args: ($item.arguments // $item.input // $item)
             }
           | select((((.toolName | tostring) + " " + (.args | tostring)) | test("apply_patch|edit|write|file"; "i")))' "$1" 2>/dev/null || true
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

# --- Наблюдаемость (observability) -----------------------------------------
# Run-log и архив TMPDIR помогают постмортем-анализу зависаний: pi иногда не
# доходит до agent_end на длинных задачах (network stall провайдера, обрыв
# стрима), и без логов невозможно понять, что именно произошло.
# Логи пишутся в var/ (gitignored), контракт CLI не меняется.
LOG_DIR="${WATCH_LOG_DIR:-$PROJECT_ROOT/var/log/watch-subagent}"
RUN_TS=$(date +%Y%m%d_%H%M%S)
RUN_ROLE_SLUG=$(basename "$ROLE_FILE" | sed 's/\.[a-z][a-z]\.md$//' | tr -c '[:alnum:]' '-' | tr -s '-' | sed 's/^-//;s/-$//')
RUN_ID="${RUN_TS}-${RUNNER}-${RUN_ROLE_SLUG}-$$"
# Один запуск = один каталог: run.log и (при сбое) events/ лежат вместе.
# PID в RUN_ID исключает коллизию: два запуска в одну секунду с тем же
# runner+role не перемешают логи/дампы друг друга.
RUN_DIR="$LOG_DIR/${RUN_ID}"
RUN_LOG="$RUN_DIR/run.log"
_EXIT_REASON="unknown"
mkdir -p "$RUN_DIR"

log_run() {
    # Best-effort запись в run-log (не падать, если файл недоступен).
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >> "$RUN_LOG" 2>/dev/null || true
}

log_run "=== RUN START ==="
log_run "runner=$RUNNER role=$ROLE_FILE role_slug=$RUN_ROLE_SLUG"
log_run "provider=${PROVIDER:-<none>} model=${MODEL:-<none>} reasoning=${REASONING:-<none>}"
log_run "soft_timeout=${SOFT_TIMEOUT}s hard_timeout=${HARD_TIMEOUT}s stall_timeout=${STALL_TIMEOUT}s output=${OUTPUT}"
log_run "effective_hard=${EFFECTIVE_HARD:-<computed-later>}s"
log_run "run_log=$RUN_LOG"
log_run "watch_keep_tmp=${WATCH_KEEP_TMP:-0}"

TMPDIR=$(mktemp -d)
PIPE="$TMPDIR/events.pipe"
OUTFILE="$TMPDIR/events.ndjson"
ERRFILE="$TMPDIR/runner.stderr"
GAPS_FILE="$TMPDIR/gaps.tsv"   # ts<TAB>gap_since_prev_event_s<TAB>event_type — для анализа пауз
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

wait_agent() {
    local exit_code=0

    if [[ -n "$AGENT_PID" ]]; then
        set +e
        wait "$AGENT_PID" 2>/dev/null
        exit_code=$?
        set -e
        AGENT_PID=""
    fi

    return "$exit_code"
}

print_runner_error() {
    local reason="$1"
    local exit_code="$2"

    echo "{\"type\":\"_watch_error\",\"reason\":\"${reason}\",\"runner\":\"${RUNNER}\",\"exit_code\":${exit_code}}" >&2

    if [[ -s "$ERRFILE" ]]; then
        echo "--- ${RUNNER} stderr (last 80 lines) ---" >&2
        tail -n 80 "$ERRFILE" >&2
    fi
}

is_runner_success_event() {
    local line="$1"

    case "$RUNNER" in
        pi)
            [[ "$line" == *"agent_end"* ]]
            ;;
        codex)
            jq -Rre 'fromjson? | select(type == "object" and .type == "turn.completed")' <<< "$line" >/dev/null 2>&1
            ;;
    esac
}

is_runner_failure_event() {
    local line="$1"

    case "$RUNNER" in
        pi)
            return 1
            ;;
        codex)
            jq -Rre 'fromjson? | select(type == "object" and .type == "turn.failed")' <<< "$line" >/dev/null 2>&1
            ;;
    esac
}

outfile_has_success_event() {
    case "$RUNNER" in
        pi)
            grep -q 'agent_end' "$OUTFILE" 2>/dev/null
            ;;
        codex)
            jq -Rre 'fromjson? | select(type == "object" and .type == "turn.completed")' "$OUTFILE" >/dev/null 2>&1
            ;;
    esac
}

outfile_has_failure_event() {
    case "$RUNNER" in
        pi)
            return 1
            ;;
        codex)
            jq -Rre 'fromjson? | select(type == "object" and .type == "turn.failed")' "$OUTFILE" >/dev/null 2>&1
            ;;
    esac
}

print_filtered_output() {
    if has_format text; then filter_text "$OUTFILE"; fi
    if has_format tools; then filter_tools "$OUTFILE"; fi
    if has_format files; then filter_files "$OUTFILE"; fi
}

cleanup() {
    local exit_code=$?
    # Сохраняем reason до того, как kill_agent_tree затрёт $?.
    local reason="${_EXIT_REASON:-unknown}"
    local now_epoch end_ts duration
    now_epoch=$(date +%s)
    end_ts=$(date '+%Y-%m-%d %H:%M:%S')
    duration=$((now_epoch - ${START_TIME:-0}))

    # Сначала освобождаем ресурсы агента.
    kill_agent_tree

    # --- Run summary (best-effort, не падать при ошибках записи) ---
    log_run "=== RUN SUMMARY ==="
    log_run "ended=$end_ts duration=${duration}s exit_code=$exit_code reason=$reason"
    log_run "agent_pid_at_exit=${AGENT_PID:-none}"
    if [[ -f "${OUTFILE:-}" ]]; then
        local total agent_end_count last_type
        total=$(wc -l < "$OUTFILE" 2>/dev/null || echo 0)
        agent_end_count=$(grep -c '"agent_end"' "$OUTFILE" 2>/dev/null || echo 0)
        last_type=$(tail -1 "$OUTFILE" 2>/dev/null | jq -r '.type // "?"' 2>/dev/null || echo "?")
        log_run "events_total=$total agent_end_count=$agent_end_count last_event_type=$last_type"
        log_run "events_by_type: $(jq -r '.type' "$OUTFILE" 2>/dev/null | sort | uniq -c | sort -rn | head -8 | tr '\n' '|' | sed 's/|$//')"
    fi
    if [[ -f "${GAPS_FILE:-}" ]]; then
        local max_gap gaps_count avg_gap
        gaps_count=$(wc -l < "$GAPS_FILE" 2>/dev/null || echo 0)
        max_gap=$(awk -F'\t' '{print $2}' "$GAPS_FILE" 2>/dev/null | sort -rn | head -1)
        avg_gap=$(awk -F'\t' '{s+=$2; n++} END {if(n>0) printf "%d", s/n; else print 0}' "$GAPS_FILE" 2>/dev/null)
        log_run "gaps_recorded=$gaps_count max_gap=${max_gap:-0}s avg_gap=${avg_gap:-0}s"
    fi
    log_run "=== END SUMMARY ==="

    # --- Archive TMPDIR on failure (or if WATCH_KEEP_TMP=1) ---
    # Сохраняем улики (events.ndjson, gaps.tsv, runner.stderr) для постмортема,
    # если агент не завершился нормально. Успешные запуски не архивируем
    # (кроме WATCH_KEEP_TMP=1), чтобы не копить гигабайты. run.log уже в RUN_DIR.
    if [[ "$reason" != success_* ]] || [[ "${WATCH_KEEP_TMP:-0}" == "1" ]]; then
        if cp -r "$TMPDIR" "$RUN_DIR/events" 2>/dev/null; then
            log_run "events_archived_to=$RUN_DIR/events"
            echo "[watch-subagent] run archived (reason=$reason): $RUN_DIR/events" >&2
        fi
    fi

    rm -rf "$TMPDIR"
}
trap cleanup EXIT

# Внешний kill (bash timeout, Ctrl-C, OOM-killer) — фиксируем reason,
# иначе cleanup увидит unknown и нельзя отличить «скрипт сам упал» от «убили снаружи».
trap '_EXIT_REASON="external_signal"; exit 143' TERM
trap '_EXIT_REASON="external_signal"; exit 130' INT

# Формируем команду раннера
build_runner_command
apply_proxy_env_defaults

# Запускаем
"${RUNNER_CMD[@]}" <<< "$PROMPT" > "$PIPE" 2> "$ERRFILE" &
AGENT_PID=$!
log_run "agent_started pid=$AGENT_PID prompt_bytes=$(printf '%s' "$PROMPT" | wc -c)"
log_run "runner_cmd: ${RUNNER_CMD[*]}"

# Вычисляем таймауты: soft — основной, hard — абсолютный потолок
# SOFT_TIMEOUT обязателен, hard не может быть < soft
# Если -m не задан явно, hard = max(soft*2, 1800)
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

    now=$(date +%s)
    event_gap=$((now - last_event_time))
    event_type=$(jq -r '.type // "?"' <<< "$line" 2>/dev/null || echo "?")
    # Записываем паузу между событиями для анализа stall'ов (best-effort).
    if [[ -n "${GAPS_FILE:-}" ]]; then
        printf '%s\t%s\t%s\n' "$now" "$event_gap" "$event_type" >> "$GAPS_FILE"
    fi
    last_event_time=$now
    elapsed=$((now - START_TIME))

    # raw — стримим на stdout сразу
    $STREAM_RAW && echo "$line"

    # Агент завершился сам
    if is_runner_success_event "$line"; then
        runner_exit_code=0
        wait_agent || runner_exit_code=$?
        if [[ $runner_exit_code -ne 0 ]]; then
            if [[ "$RUNNER" == "pi" ]]; then
                print_runner_error "runner_failed_after_agent_end" "$runner_exit_code"
            else
                print_runner_error "runner_failed_after_success_event" "$runner_exit_code"
            fi
            _EXIT_REASON="runner_failed_after_agent_end"
            exit 1
        fi

        _EXIT_REASON="success_agent_end"
        print_filtered_output
        exit 0
    fi

    if is_runner_failure_event "$line"; then
        runner_exit_code=0
        wait_agent || runner_exit_code=$?
        print_runner_error "runner_failed_event" "$runner_exit_code"
        _EXIT_REASON="runner_failure_event_instream"
        exit 1
    fi

    # Проверяем soft timeout — целевое время задачи.
    # ПО УМОЛЧАНИЮ УБИВАЕТ: превышение soft = задача застряла/модель зациклилась
    # (pi крутит turn'ы без лимита, см. retro 2026-06-15). Убийство на soft-timeout
    # экономит токены vs ожидание hard-timeout (раньше soft только предупреждал,
    # и pi сжигал токены ещё soft→hard минут).
    # Env WATCH_SOFT_WARN_ONLY=1 возвращает старое поведение (только warning) для
    # экспериментов/длинных задач, где soft — мягкий ориентир.
    if [[ -n "$SOFT_TIMEOUT" ]] && [[ $elapsed -ge $SOFT_TIMEOUT ]]; then
        if [[ "${WATCH_SOFT_WARN_ONLY:-0}" == "1" ]]; then
            if [[ -z "${_SOFT_WARNED:-}" ]]; then
                _SOFT_WARNED=1
                echo '{"type":"_watch_timeout","reason":"soft","elapsed":'${elapsed}',"limit":'${SOFT_TIMEOUT}'}' >&2
            fi
        else
            echo '{"type":"_watch_timeout","reason":"soft","elapsed":'${elapsed}',"limit":'${SOFT_TIMEOUT}'}' >&2
            _EXIT_REASON="soft_timeout"
            exit 1
        fi
    fi

    # Проверяем жёсткий таймаут — УБИВАЕМ
    if [[ $elapsed -ge $EFFECTIVE_HARD ]]; then
        echo '{"type":"_watch_timeout","reason":"hard","elapsed":'${elapsed}',"limit":'${EFFECTIVE_HARD}'}' >&2
        _EXIT_REASON="hard_timeout"
        exit 1
    fi
done < "$PIPE"

# read вернул ошибку — либо stall, либо pipe закрылся
now=$(date +%s)
elapsed=$((now - last_event_time))

if [[ $elapsed -ge $STALL_TIMEOUT ]]; then
    echo '{"type":"_watch_timeout","reason":"stall","stalled":'${elapsed}',"limit":'${STALL_TIMEOUT}'}' >&2
    _EXIT_REASON="stall"
    exit 1
fi

# Pipe закрылся без agent_end — считаем запуск некорректным
runner_exit_code=0
wait_agent || runner_exit_code=$?
if [[ $runner_exit_code -ne 0 ]]; then
    print_runner_error "runner_failed" "$runner_exit_code"
    _EXIT_REASON="runner_failed_pipe_closed"
    exit 1
fi

if outfile_has_success_event; then
    _EXIT_REASON="success_outfile"
    print_filtered_output
    exit 0
fi

if outfile_has_failure_event; then
    print_runner_error "runner_failed_event" "$runner_exit_code"
    _EXIT_REASON="runner_failure_event_outfile"
    exit 1
fi

if [[ "$RUNNER" == "pi" ]]; then
    print_runner_error "missing_agent_end" "$runner_exit_code"
else
    print_runner_error "missing_success_event" "$runner_exit_code"
fi
_EXIT_REASON="missing_agent_end"
exit 1
