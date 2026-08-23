---
name: run-subagent
description: Запуск сабагента через pi/codex для автономного выполнения задачи в изолированном контексте с контролем и фильтрацией вывода
---

# Run Pi Subagent

Запуск подчинённого AI-агента через `pi --mode json` или `codex exec --json` для автономного выполнения задачи.

## Когда использовать

- Нужно делегировать задачу сабагенту (реализация, ревью, доработка, тестирование, документация)

## Как использовать

```bash
scripts/watch-subagent.sh -s <soft-timeout> [options] <<'PROMPT'
<prompt>
PROMPT
```

Скрипт `watch-subagent.sh` лежит рядом с этим SKILL.md в папке `scripts/`.

Сабагент запускается с **упрощённым системным промптом** (`scripts/subagent_system.txt`) — без лишних ссылок на документацию pi, только базовая роль AI-ассистента.

Параметры:

| Параметр          | Сокращение | Описание                                                     | По умолчанию |
|-------------------|------------|--------------------------------------------------------------|--------------|
| `--soft-timeout`  | `-s`       | Базовый таймаут в секундах (обязателен). Целевое время задачи; превышение soft **убивает** запуск по умолчанию (страховка от сжигания токенов: pi крутит turn'ы без лимита) | —            |
| `--hard-timeout`  | `-m`       | Абсолютный максимум в секундах                               | 1800         |
| `--stall-timeout` | `-t`       | Нет событий N секунд → агент завис. **Не убивает**, если процесс активен (CPU/IO) — см. `WATCH_STALL_RESPECT_LIVENESS` | 360 для всех раннеров |
| `--output`        | `-o`       | Формат вывода через запятую (см. ниже)                       | `raw`        |
| `--role-file`     | `-r`       | Путь к файлу описания роли (обязателен)                      | —            |
| `--runner`        | —          | Раннер: `pi` или `codex` (env `RUNNER`)                      | `roles.<role>.command[0]`, иначе `pi` |
| `--provider`      | —          | Provider (провайдер) для `pi` (env `PROVIDER`)               | `roles.<role>.command --provider`, иначе — |
| `--model`         | —          | Модель (env `MODEL`)                                         | `roles.<role>.command --model`, иначе — |
| `--reasoning`     | —          | Reasoning/thinking effort (pi: `--thinking`, codex: `-c model_reasoning_effort=...`) | `roles.<role>.command`, иначе — |
| `[prompt text]`   | —          | Промпт. Если не указан — читается из stdin                   | —            |

### Профиль делегирования роли

Если `--runner`/`RUNNER`, `--provider`/`PROVIDER`, `--model`/`MODEL`, `--reasoning`/`REASONING` не заданы явно,
скрипт берёт значения из `config/chains.yaml` секции `roles.<role>.command`.
Имя роли вычисляется по `--role-file`: например,
`docs/agents/roles/team/backend_developer_levsha.ru.md` → `backend_developer_levsha`.

Приоритеты резолва (resolution priority):

1. CLI option (опция CLI): `--runner`, `--provider`, `--model`, `--reasoning`.
2. Env (переменная окружения): `RUNNER`, `PROVIDER`, `MODEL`, `REASONING`.
3. Role delegation profile (профиль делегирования роли): `roles.<role>.command`.
4. Default (значение по умолчанию): `pi` только для runner.

Из `command` извлекаются:
- runner — первый элемент команды, например `codex` в `command: [codex, exec, ...]`;
- provider — значение после `--provider` (применяется только для `pi`);
- model — значение после `--model`;
- reasoning — значение после `--thinking`/`--reasoning` или `model_reasoning_effort=...`.

Явные значения не затираются профилем роли.
`provider`/`model`/`reasoning` из профиля роли применяются как связанная группа с profile runner (раннером профиля):
- если `runner` не задан явно и берётся из профиля роли — profile `provider`/`model`/`reasoning` применяются;
- если `runner` задан явно через CLI/env и совпадает с profile runner — profile `provider`/`model`/`reasoning` можно применять как defaults (значения по умолчанию);
- если `runner` задан явно через CLI/env и отличается от profile runner — profile `provider`/`model`/`reasoning` не применяются; они остаются пустыми, пока не заданы явно через CLI/env.

### Раннеры

#### pi (default или профиль роли)

```bash
scripts/watch-subagent.sh -s 600 -r docs/agents/roles/team/backend_developer_levsha.ru.md <<'PROMPT'
<prompt>
PROMPT
```

Команда: `pi --mode json -p --system-prompt <file> [--provider <provider>] --append-system-prompt "Возьми на себя роль из файла: <role>"`; Pi сохраняет сессию в `~/.pi/agent/sessions`.

#### codex

```bash
scripts/watch-subagent.sh --runner codex -s 600 -r docs/agents/roles/team/system_architect_gandalf.ru.md <<'PROMPT'
<prompt>
PROMPT
```

Команда: `codex exec --json --dangerously-bypass-approvals-and-sandbox --skip-git-repo-check -o <run-dir>/last_message.txt`

Системный промпт и роль передаются через `-c model_instructions_file=...` и `-c additional_instructions=...`.
Rollout-журналы сохраняются в `~/.codex/sessions`; `--ephemeral` добавляется только при `WATCH_CODEX_EPHEMERAL=1`.
Последнее сообщение агента записывается в `last_message.txt` в каталоге запуска.

### Контроль

| Ограничение | Значение по умолчанию | Правило |
|-------------|------------------------|---------|
| `--soft-timeout` | задаётся обязательно | По достижении завершает запуск; `WATCH_SOFT_WARN_ONLY=1` оставляет только предупреждение. |
| `--hard-timeout` | 1800 секунд | Абсолютный потолок: завершает запуск в любом случае. |
| `--stall-timeout` | 360 секунд для всех раннеров | При отсутствии событий завершает только простаивающий процесс; активный процесс (CPU/IO) ждёт до hard-лимита. |
| PHP idle-порог | 330 секунд для всех раннеров | `AGENT_RUNNER_IDLE_TIMEOUT_SEC` ограничивает подтверждённый простой процесса в PHP-раннере. |

Модели любых провайдеров могут молчать до ~5 минут в фазе ожидания или
восстановления (stream idle budget). Общие пороги stall 360 секунд и PHP idle
330 секунд не прерывают штатные автоповторы преждевременно.

Env `WATCH_STALL_RESPECT_LIVENESS=0` отключает liveness-проверку для stall и
всегда завершает процесс по stall (старое поведение). По умолчанию при отсутствии
событий процесс, активный по CPU/IO, ждёт до `--hard-timeout`. Проверка требует
Linux (`/proc`). Пользователь может явно задать любое значение `-t`.

Переменные окружения управления запуском:

| Переменная | По умолчанию | Назначение |
|------------|---------------|------------|
| `WATCH_CODEX_EPHEMERAL` | `0` | `1` добавляет `--ephemeral` и отключает сохранение rollout-журнала codex |
| `WATCH_STALL_RESPECT_LIVENESS` | `1` | `0` отключает liveness-проверку и всегда завершает процесс по stall |
| `WATCH_SOFT_WARN_ONLY` | `0` | `1` превращает soft-timeout в предупреждение |
| `WATCH_KEEP_TMP` | `0` | `1` сохраняет дамп `events/` и при успехе |
| `WATCH_WATCHER_INTERVAL` | `5` | Интервал опроса фонового наблюдателя в секундах |

### Логи и отладка

Каждый запуск создаёт свой каталог в `var/log/watch-subagent/`:
`<YYYYMMDD_HHMMSS>-<runner>-<role-slug>-<pid>/` (`runner` = `pi`|`codex`,
`role-slug` — имя файла роли без локали и `.md`, `<pid>` — PID процесса;
PID исключает коллизию при параллельных/повторных запусках в одну секунду).

Внутри: `run.log` (статус запуска — start, summary, reason) всегда; при
ненормальном завершении добавляется `events/` (полный дамп: `events.ndjson`,
`gaps.tsv`, `runner.stderr`) для разбора причин. Пример:
`var/log/watch-subagent/20260616_120323-pi-backend-developer-levsha-12345/run.log`.

Env `WATCH_KEEP_TMP=1` — сохранять `events/` и для успешных запусков.

Env `WATCH_CODEX_EPHEMERAL=1` — не сохранять rollout-журнал codex. По умолчанию
журнал остаётся в `~/.codex/sessions`; его ротация не входит в задачу.

Env `WATCH_STALL_RESPECT_LIVENESS=0` — отключить liveness-gate для stall-timeout: при тишине убивать всегда (старое поведение). По умолчанию (`1`) при молчании в потоке процесс не убивается, если он активен (грузит CPU/IO): watcher ждёт до `--hard-timeout`. Требует Linux (`/proc`).

Если оборачиваете запуск во внешний `timeout` (CI и т.п.) — ставьте его
≥ `--hard-timeout` + 60с, иначе скрипт не успеет завершиться сам и потеряет
детальную причину.

### Формат вывода (`--output`)

Через запятую можно комбинировать: `-o text,files`, `-o text,tools`.

| Значение | Что выводится                                |
|----------|----------------------------------------------|
| `raw`    | Полный поток JSON-событий в реальном времени |
| `text`   | Только финальный текстовый ответ сабагента   |
| `tools`  | Список вызванных инструментов (имя + args)   |
| `files`  | Список созданных/отредактированных файлов    |

### Роль (`--role-file`)

Обязательный параметр. Принимает путь к файлу описания роли (относительно корня проекта или абсолютный).
В системный промпт добавляется инструкция: `Возьми на себя роль из файла: <путь>` — модель сама прочитает файл через `read`.

Файлы ролей: `docs/agents/roles/team/`.

### Примеры

```bash
# Делегирование Бэкендеру (runner/model/reasoning берутся из config/chains.yaml, default runner = pi)
scripts/watch-subagent.sh -s 600 -r docs/agents/roles/team/backend_developer_levsha.ru.md <<'PROMPT'
Выполни задачу: todo/TASK-feat-example.todo.md.
Следуй инструкциям из секции 'Инструкции для сабагента' в файле задачи и AGENTS.md.
PROMPT
```

```bash
# Делегирование Архитектору через профиль роли: если roles.system_architect_gandalf.command начинается с codex,
# явный --runner не нужен.
scripts/watch-subagent.sh -s 600 -r docs/agents/roles/team/system_architect_gandalf.ru.md <<'PROMPT'
Выполни задачу: todo/TASK-feat-example.todo.md.
PROMPT
```

```bash
# Явный override (переопределение) сильнее профиля роли
RUNNER=codex MODEL=o3 scripts/watch-subagent.sh --runner pi --model gpt-4o-mini -s 600 \
    -r docs/agents/roles/team/system_architect_gandalf.ru.md <<'PROMPT'
Проверь реализацию без изменения файлов.
PROMPT
```

Примеры с reasoning:
```bash
# pi с thinking level
scripts/watch-subagent.sh --reasoning high -s 600 -r docs/agents/roles/team/backend_developer_levsha.ru.md <<'PROMPT'
<prompt>
PROMPT
```

```bash
# codex с указанием модели и reasoning
scripts/watch-subagent.sh --runner codex --model o3 --reasoning high -s 600 \
    -r docs/agents/roles/team/backend_developer_levsha.ru.md <<'PROMPT'
Проанализируй структуру src/Domain/ и предложи рефакторинг.
PROMPT
```

```bash
# Через env-переменные
RUNNER=codex MODEL=o3 scripts/watch-subagent.sh -s 600 \
    -r docs/agents/roles/team/backend_developer_levsha.ru.md <<'PROMPT'
Реализуй фичу X.
PROMPT
```

```bash
# Ответ + какие файлы менялись (pi)
scripts/watch-subagent.sh -s 600 -o text,files -r docs/agents/roles/team/backend_developer_levsha.ru.md <<'PROMPT'
Реализуй фичу X в src/Domain/...
PROMPT
```

## Результат

- Команда завершается с кодом 0 при успехе
- Формат вывода определяется ключом `--output`
- Ключевые события в режиме `raw`: `agent_start`, `turn_start/end`, `message_start/end`, `tool_execution_start/end`, `agent_end`
- Контрактная версия скрипта: `v1` (строка `# CONTRACT: v1` в шапке)
