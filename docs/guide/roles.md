# Роли

Роли конфигурируются в секции `roles` YAML-конфигурации (параметр `%task_orchestrator.chains_yaml%`).
Каждая роль ссылается на `.md` файл и определяет CLI-команду для запуска.

## Конфигурация роли

```yaml
roles:
  system_analyst:
    prompt_file: docs/agents/roles/team/system_analyst.ru.md
    command:
      - pi
      - --mode
      - json
      - -p
      - --model
      - glm-4.7
      - --system-prompt
      - "@system-prompt"         # резолвится в содержимое prompt_file
      - --append-system-prompt
      - "@append-system-prompt" # опционально, не у всех ролей
      - --tools
      - "read,grep,find,ls"
    fallback:                       # опционально
      command:
        - codex
        - --model
        - gpt-4o
        - --full-auto
        - --system-prompt
        - "@system-prompt"
```

**Поля конфигурации роли:**

| Поле | Обязательное | Описание |
|---|---|---|
| `prompt_file` | да | Путь к .md файлу роли (system prompt) |
| `command` | да | CLI-команда для запуска агента. `@system-prompt` резолвится в содержимое файла |
| `commands_by_complexity` | нет | Частичный набор команд по уровням `C0–C5`; имеет приоритет над `command` |
| `fallback` | нет | Альтернативная команда при недоступности основного runner |

## Выбор команды по сложности задачи

Если текст запуска содержит идентификатор `TASK-*`, оркестратор читает `complexity` из соответствующего файла в `todo/`. При отсутствии поля используется `C3`; отсутствующий или неоднозначный файл и недопустимое значение вызывают ошибку.

```yaml
roles:
  backend_developer_levsha:
    prompt_file: docs/agents/roles/team/backend_developer_levsha.ru.md
    commands_by_complexity:
      C1: [pi, -p, --model, glm-5.3-flash, --thinking, high]
      C3: [pi, -p, --model, glm-5.3-flash, --thinking, max]
      C4: [pi, -p, --model, k3.1-pro, --thinking, high]
```

Выбирается точный уровень либо ближайший настроенный уровень выше. Если выше ничего нет, выбирается ближайший уровень ниже. Поэтому в примере `C0–C1` используют `C1`, `C2–C3` — `C3`, `C4–C5` — `C4`. Если настроена только `C1`, она используется для всех уровней. Без `commands_by_complexity` одиночная `command` работает как раньше.

## Маппинг имени → файл

- `system_analyst` → `system_analyst.ru.md`
- `backend_developer` → `backend_developer_levsha.ru.md`
- и т.д.

Путь к директории с ролями — параметр `%task_orchestrator.roles_dir%`.
