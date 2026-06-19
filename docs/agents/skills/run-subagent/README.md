# watch-subagent.sh

Обёртка запуска AI-агента (pi/codex) с контролем таймаутов и постмортем-диагностикой.

> Контракт для AI-агента (как запускать сабагента) — в [`SKILL.md`](SKILL.md).
> Этот документ — про внутренности: параметры, окружение, enforcement таймаутов,
> диагностику. Он не предназначен для загрузки в промпт агента.

## Расположение файлов

```
docs/agents/skills/run-subagent/
├── SKILL.md                      # контракт для AI-агента
├── README.md                     # этот документ (операторский справочник)
└── scripts/
    ├── watch-subagent.sh         # обёртка запуска (контрактная версия: v1)
    └── subagent_system.txt       # упрощённый системный промпт сабагента
```

Логи запусков — в `var/log/watch-subagent/` (gitignored).

## Параметры (полная справка)

| Параметр          | Сокращение | Описание                                                                       | По умолчанию |
|-------------------|------------|--------------------------------------------------------------------------------|--------------|
| `--soft-timeout`  | `-s`       | Целевое время задачи в секундах (обязателен). Превышение soft **убивает** запуск по умолчанию (страховка от сжигания токенов: pi крутит turn'ы без лимита). `WATCH_SOFT_WARN_ONLY=1` — только предупреждать | — |
| `--hard-timeout`  | `-m`       | Абсолютный максимум в секундах. Фактическое значение (`EFFECTIVE_HARD`) = `max(--hard-timeout, --soft-timeout × 2)` | 1800 |
| `--stall-timeout` | `-t`       | Нет событий N секунд → агент завис → завершить принудительно                   | 180          |
| `--output`        | `-o`       | Формат вывода через запятую: `raw`, `text`, `tools`, `files`                   | `raw`        |
| `--role-file`     | `-r`       | Путь к файлу описания роли (обязателен)                                        | —            |
| `--runner`        | —          | Раннер: `pi` или `codex` (приоритеты резолва см. ниже)                         | профиль роли, иначе `pi` |
| `--provider`      | —          | Провайдер для `pi` (только для pi)                                             | профиль роли |
| `--model`         | —          | Модель                                                                          | профиль роли |
| `--reasoning`     | —          | Reasoning/thinking effort (pi: `--thinking`, codex: `model_reasoning_effort=…`) | профиль роли |
| `[prompt text]`   | —          | Промпт. Если не указан — читается из stdin                                     | —            |

### Резолв `--runner`/`--provider`/`--model`/`--reasoning`

Приоритет: **CLI option → env → профиль роли → default**.

Профиль роли берётся из секции `roles.<role>.command` в `config/chains.yaml`
(имя роли вычисляется по `--role-file`, например
`docs/agents/roles/team/backend_developer_levsha.ru.md` → `backend_developer_levsha`).
Из `command` извлекаются runner (первый элемент), provider (`--provider`),
model (`--model`), reasoning (`--thinking`/`--reasoning` или
`model_reasoning_effort=…`).

Явные значения не затираются профилем роли. `provider`/`model`/`reasoning` из
профиля применяются как связанная группа с profile-runner: если раннер задан
явно и **отличается** от profile-runner — профильные provider/model/reasoning не
применяются.

## Переменные окружения

### Наследование от внешней обёртки

| Переменная  | Назначение |
|-------------|------------|
| `RUNNER`    | Раннер по умолчанию (`pi`/`codex`) |
| `PROVIDER`  | Провайдер pi по умолчанию |
| `MODEL`     | Модель по умолчанию |
| `REASONING` | Reasoning/thinking effort по умолчанию |

### Наблюдаемость

| Переменная              | Назначение |
|-------------------------|------------|
| `CHAINS_CONFIG`         | Путь к `chains.yaml` (default: `config/chains.yaml`) |
| `WATCH_LOG_DIR`         | Каталог run-логов (default: `var/log/watch-subagent`) |
| `WATCH_KEEP_TMP=1`      | Сохранять `events/` ВСЕГДА, даже при успехе (по умолчанию — только при сбое) |
| `WATCH_SOFT_WARN_ONLY=1`| soft-timeout только предупреждать, НЕ убивать (только для экспериментов) |
| `WATCH_WATCHER_INTERVAL`| Интервал опроса фонового watcher в секундах (default: 5) |

## Enforcement таймаутов

Запуск терминируется в трёх случаях: превышено целевое время (`soft`),
абсолютный потолок (`hard`), нет событий (`stall`), либо агент штатно завершился.

Реализовано **двумя механизмами** для надёжности:

1. **In-loop проверки** (в теле `while IFS= read -r -t "$STALL_TIMEOUT"`):
   soft/hard проверяются при каждом событии, stall — через `read -t`. Это
   основной путь на нормальном стриме.
2. **Фоновый watcher по wall-clock** — страховка. Отдельный подпроцесс,
   **не** блокирован в `read`, опрашивает каждые `WATCH_WATCHER_INTERVAL` сек.
   Нужен потому, что in-loop проверки исполняются, только когда из пайпа пришла
   строка: если `read` завис (наблюдалось на pi 0.79.6 — запуск вис до внешнего
   `SIGKILL`, теряя `RUN SUMMARY` и архив), in-loop проверки замирают. Watcher:
   - терминирует запуск по soft/hard/stall по часам;
   - **сам** пишет `RUN SUMMARY` (с `source=watcher`) и архивирует `events/`
     (trap `EXIT` основного процесса не сработает, пока `read` блокирован);
   - убивает дерево агента и основной процесс (`SIGTERM` → grace 2с → `SIGKILL`);
   - на нормальных запусках не вмешивается (in-loop срабатывают раньше) и
     останавливается в `cleanup`.

> `SIGKILL` обходит trap. Внешний `SIGKILL`, убивающий одновременно watcher и
> основной процесс, не позволит выполнить полный cleanup средствами bash. Все
> мягкие сигналы (`TERM`/`INT` и собственные soft/hard/stall) обрабатываются.

## Диагностика

Каждый запуск создаёт каталог `WATCH_LOG_DIR/<RUN_ID>/`, где
`RUN_ID = <YYYYMMDD_HHMMSS>-<runner>-<role-slug>-<pid>` (PID исключает коллизию
при параллельных запусках в одну секунду).

```
<RUN_ID>/
├── run.log              # всегда: RUN START, heartbeat, RUN SUMMARY
└── events/              # архивируется при неуспехе (или WATCH_KEEP_TMP=1)
    ├── events.ndjson    # полный поток событий
    ├── gaps.tsv         # ts<TAB>gap_s<TAB>event_type — паузы между событиями
    ├── runner.stderr    # stderr раннера
    ├── last_event       # ts последнего события (state для watcher)
    └── events.pipe      # именованный пайп
```

### `run.log`

Содержит строки: `=== RUN START ===`, параметры запуска,
`watcher_heartbeat elapsed=… stall_gap=… agent_alive=…` (каждые N сек),
при срабатывании — `watcher_fired reason=…`,
и финальный `=== RUN SUMMARY === … === END SUMMARY ===`.

**`watcher_heartbeat` — главная улика зависания:** растущий `stall_gap` при
`agent_alive=yes` означает «основной цикл завис, агент жив».

### `RUN SUMMARY`

```
ended=<ts> duration=<s> exit_code=<code> reason=<reason> source=<main|watcher>
agent_pid_at_exit=<pid|none>
events_total=<n> agent_end_count=<n> last_event_type=<type>
events_by_type: <top-8>
gaps_recorded=<n> max_gap=<s> avg_gap=<s>
```

### `reason` (коды завершения)

| reason | Значение |
|---|---|
| `success_agent_end` | Агент штатно завершился (`agent_end` / `turn.completed`) |
| `success_outfile`   | Pipe закрыт, но в дампе найден success-event |
| `soft_timeout`      | Превышено целевое время (`-s`) |
| `hard_timeout`      | Достигнут абсолютный потолок (`EFFECTIVE_HARD`) |
| `stall`             | Нет событий дольше `-t` |
| `runner_failed_after_agent_end` | Раннер упал после success-event |
| `runner_failed_pipe_closed` / `runner_failed` | Раннер закрыл pipe с ошибкой |
| `runner_failure_event_instream` / `runner_failure_event_outfile` | codex `turn.failed` |
| `missing_agent_end` | Pipe закрыт без success-event |
| `external_signal`   | Внешний `TERM`/`INT` (bash `timeout`, Ctrl-C) |
| `unknown`           | Не удалось определить |

`source=watcher` в `RUN SUMMARY` указывает, что терминирование выполнил фоновый
watcher (основной цикл был заблокирован).

## Внешний `timeout`

Если запуск обёрнут во внешний `timeout` (CI и т.п.) — ставьте его
`≥ --hard-timeout + 60с`. Иначе скрипт не успеет завершиться сам через
собственные таймауты, потеряет детальную причину (останется только
`reason=external_signal`).

## Версия контракта

`v1` — строка `# CONTRACT: v1` в шапке `scripts/watch-subagent.sh`. Перечень
совместимых изменений и миграций — в `SKILL.md`.
