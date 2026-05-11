---
name: run-pi-subagent
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
| `--soft-timeout`  | `-s`       | Базовый таймаут в секундах (обязателен)                      | —            |
| `--hard-timeout`  | `-m`       | Абсолютный максимум в секундах                               | 1200         |
| `--stall-timeout` | `-t`       | Нет событий N секунд → агент завис → завершить принудительно | 120          |
| `--output`        | `-o`       | Формат вывода через запятую (см. ниже)                       | `raw`        |
| `--role-file`     | `-r`       | Путь к файлу описания роли (обязателен)                      | —            |
| `--runner`        | —          | Раннер: `pi` или `codex` (env `RUNNER`)                      | `pi`         |
| `--model`         | —          | Модель (env `MODEL`)                                         | —            |
| `--reasoning`     | —          | Reasoning/thinking effort (pi: `--thinking`, codex: `-c model_reasoning_effort=...`) | —            |
| `[prompt text]`   | —          | Промпт. Если не указан — читается из stdin                   | —            |

### Приоритет параметров

Флаги командной строки приоритетнее env-переменных:
- `--runner` → env `RUNNER` → default `pi`
- `--model` → env `MODEL` → default (не задан)

### Раннеры

#### pi (default)

```bash
scripts/watch-subagent.sh -s 600 -r docs/agents/roles/team/backend_developer_levsha.ru.md <<'PROMPT'
<prompt>
PROMPT
```

Команда: `pi --mode json --no-session --system-prompt <file> --append-system-prompt "Возьми на себя роль из файла: <role>"`

#### codex

```bash
scripts/watch-subagent.sh --runner codex -s 600 -r docs/agents/roles/team/backend_developer_levsha.ru.md <<'PROMPT'
<prompt>
PROMPT
```

Команда: `codex exec --json --dangerously-bypass-approvals-and-sandbox --skip-git-repo-check --ephemeral`

Системный промпт и роль передаются через `-c model_instructions_file=...` и `-c additional_instructions=...`.

### Контроль

1. Нет событий дольше `--stall-timeout` — агент завис → завершить
2. `--hard-timeout` достигнут — завершить в любом случае
3. Агент стримит события → ждать (каждая новая строка продлевает ожидание)

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
# Делегирование Бэкендеру (pi, default)
scripts/watch-subagent.sh -s 600 -r docs/agents/roles/team/backend_developer_levsha.ru.md <<'PROMPT'
Выполни задачу: todo/TASK-feat-example.todo.md.
Следуй инструкциям из секции 'Инструкции для сабагента' в файле задачи и AGENTS.md.
PROMPT
```

```bash
# Делегирование через codex
scripts/watch-subagent.sh --runner codex -s 600 -r docs/agents/roles/team/backend_developer_levsha.ru.md <<'PROMPT'
Выполни задачу: todo/TASK-feat-example.todo.md.
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
