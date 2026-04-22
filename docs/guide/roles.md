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
      - --no-session
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
| `fallback` | нет | Альтернативная команда при недоступности основного runner |

## Маппинг имени → файл

- `system_analyst` → `system_analyst.ru.md`
- `backend_developer` → `backend_developer_levsha.ru.md`
- и т.д.

Путь к директории с ролями — параметр `%task_orchestrator.roles_dir%`.
