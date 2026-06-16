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
| `--stall-timeout` | `-t`       | Нет событий N секунд → агент завис → завершить принудительно | 180          |
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

Команда: `pi --mode json -p --no-session --system-prompt <file> [--provider <provider>] --append-system-prompt "Возьми на себя роль из файла: <role>"`

#### codex

```bash
scripts/watch-subagent.sh --runner codex -s 600 -r docs/agents/roles/team/system_architect_gandalf.ru.md <<'PROMPT'
<prompt>
PROMPT
```

Команда: `codex exec --json --dangerously-bypass-approvals-and-sandbox --skip-git-repo-check --ephemeral`

Системный промпт и роль передаются через `-c model_instructions_file=...` и `-c additional_instructions=...`.

### Контроль

1. `--soft-timeout` достигнут — **УБИВАЕТ** запуск (default). Превышение целевого времени = задача застряла или модель зациклилась (pi крутит turn'ы без лимита, сжигая токены). Env `WATCH_SOFT_WARN_ONLY=1` — только предупреждать, не убивать (для экспериментов).
2. Нет событий дольше `--stall-timeout` — агент завис → завершить.
3. `--hard-timeout` достигнут — завершить в любом случае (абсолютный потолок, страховка поверх soft).
4. Агент стримит события → ждать (каждая новая строка продлевает ожидание).

### Наблюдаемость и постмортем

Каждый запуск пишет run-log в `var/log/watch-subagent/<ts>-<runner>-<role>.log`
(каталог в `.gitignore`, runtime-данные):

- **RUN START**: runner-команда, provider/model, все timeout'ы, pid, размер промпта.
- **RUN SUMMARY**: `reason` (success_agent_end / stall / hard_timeout / missing_agent_end / external_signal / …), длительность, `events_total`, `agent_end_count`, `last_event_type`, распределение событий по типам, `max_gap`/`avg_gap` между событиями.

При ненормальном завершении (любой не-success reason, или env `WATCH_KEEP_TMP=1`)
TMPDIR архивируется в `var/log/watch-subagent/<run-id>/events/` — там лежат
полные `events.ndjson`, `gaps.tsv` (ts / gap-от-предыдущего-события / тип),
`runner.stderr`. Это позволяет постмортем-анализу понять, что именно делал агент
перед зависанием.

> ⚠️ **Известное ограничение (post-roadmap retro 2026-06-15):** pi зависает двумя
> паттернами. **(A) Молчание после tool-result:** pi отправляет tool-result в
> LLM-провайдера и ждёт ответа, который не приходит (обрыв прокси / timeout
> провайдера) — pi замолкает. `stall-timeout` (180с) должен ловить это, но `read -t`
> на pipe с pi-процессом **блокирует, не таймаутит** (микро-тест с subshell-writer
> работает, с pi — нет). **(B) Бесконечная активность:** pi крутит turn'ы без
> лимита (78 turn'ов, 7M токенов в одном зависании), не «решая» остановиться —
> этот паттерн ловится `soft-timeout` (этот PR: soft теперь убивает).
> Паттерн A (дешёвый по токенам, ~20K, но долгий по времени) требует `logical-stall`
> через `coproc`/фоновый reader — заведён как backlog-задача
> `TASK-fix-watch-subagent-logical-stall`.

### Внешние обёртки (bash `timeout`, CI)

🛑 **НЕ оборачивайте** запуск `watch-subagent.sh` во внешний `timeout` МЕНЬШЕ
`--hard-timeout` скрипта. Скрипт сам корректно завершается через свой
stall/hard-timeout с правильным `reason` и архивом улик. Внешний преждевременный
kill ловится (`reason=external_signal`, архив сохраняется), но **теряется детальная
причина** зависания.

Если внешняя обёртка обязательна (CI, ограничения сессии) — ставьте её timeout
**≥ hard-timeout + 60с buffer**, чтобы дать скрипту завершиться самому.

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
