# Роли

Роли конфигурируются в секции `roles` YAML-конфигурации (параметр `%task_orchestrator.chains_yaml%`).
Каждая роль ссылается на `.md` файл и определяет CLI-команду для запуска.

## Конфигурация роли

```yaml
roles:
  system_analyst:
    prompt_file: docs/agents/roles/team/system_analyst.ru.md
    system_prompt_file: prompts/subagent/base_system.md
    command:
      - pi
      - --mode
      - json
      - -p
      - --model
      - glm-4.7
      - --system-prompt
      - "@system-prompt"         # резолвится в system_prompt_file
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
| `prompt_file` | да | Путь к .md файлу роли. При наличии `system_prompt_file` передаётся через `--append-system-prompt` |
| `system_prompt_file` | нет | Основной системный промпт. Без него `prompt_file` заменяет системный промпт, как раньше |
| `command` | нет | Явная CLI-команда; считается базовой командой уровня `C3` |
| `command_profile` | нет | Имя профиля из верхнеуровневой `command_profiles`; альтернатива явной `command` |
| `commands_by_complexity` | нет | Отклонения от базовой команды: уровень → имя профиля |
| `fallback` | нет | Альтернативная команда при недоступности основного runner |

## Выбор команды по сложности задачи

Если текст запуска содержит идентификатор `TASK-*`, оркестратор читает `complexity` из соответствующего файла в `todo/`. При отсутствии поля используется `C3`; отсутствующий или неоднозначный файл и недопустимое значение вызывают ошибку.

```yaml
command_profiles:
  glm_flash_high:
    command: [pi, -p, --model, glm-5.3-flash, --thinking, high]
  glm_flash_max:
    command: [pi, -p, --model, glm-5.3-flash, --thinking, max]
  kimi_high:
    command: [pi, -p, --model, k3.1-pro, --thinking, high]

roles:
  backend_developer_levsha:
    prompt_file: docs/agents/roles/team/backend_developer_levsha.ru.md
    command_profile: glm_flash_max
    commands_by_complexity:
      C1: glm_flash_high
      C4: kimi_high
```

Базовая `command` или `command_profile` считается уровнем `C3`. Выбирается точный уровень либо ближайший настроенный уровень выше; если выше ничего нет — ближайший ниже. Поэтому `C0–C1` используют `C1`, `C2–C3` — базовый `glm_flash_max`, `C4–C5` — `kimi_high`. Без отклонений базовая команда используется для всех уровней. Явные команды в `commands_by_complexity` также поддерживаются для обратной совместимости.

## Маппинг имени → файл

- `system_analyst` → `system_analyst.ru.md`
- `backend_developer` → `backend_developer_levsha.ru.md`
- и т.д.

Путь к директории с ролями — параметр `%task_orchestrator.roles_dir%`.
