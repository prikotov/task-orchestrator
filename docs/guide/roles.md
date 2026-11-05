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
| `commands_by_complexity` | нет | Отклонения от базовой команды: уровень → имя профиля или inline-команда. Применяются и `watch-subagent.sh` (навык run-subagent), и PHP-оркестратором `agent:orchestrate` |
| `fallback` | нет | Альтернативная команда при недоступности основного runner |

## Выбор команды по сложности задачи

Сложность задачи определяет, какая команда роли будет запущена. Её можно задать явно или прочитать из файла задачи:

- `watch-subagent.sh` (навык run-subagent) принимает `--task-id TASK-*` и читает `complexity` из соответствующего файла в `todo/`; явный `--complexity C0–C5` имеет приоритет.
- PHP-оркестратор `agent:orchestrate` принимает те же `--task-id` и `--complexity`: сложность вычисляется на CLI-границе (explicit > поле `complexity` файла задачи > `C3`) и применяется при маппинге цепочки, до запуска любого агента.

Если параметры не переданы или поле отсутствует, используется `C3`; отсутствующий или неоднозначный файл и недопустимое значение вызывают ошибку.

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
      C0: glm_flash_high
      C5: kimi_high
```

Базовая `command` или `command_profile` считается уровнем `C3`. Выбирается точный уровень либо ближайший настроенный уровень выше; если выше ничего нет — ближайший ниже. Поэтому `C0–C1` используют профиль из `C0`, `C2–C3` — базовый `glm_flash_max`, `C4–C5` — `kimi_high`. Крайние `C0` и `C5` удобно использовать для нижнего и верхнего профилей; промежуточные уровни также поддерживаются. Без отклонений базовая команда используется для всех уровней. Явные (inline) команды в `commands_by_complexity` также поддерживаются для обратной совместимости.

Отклонения `commands_by_complexity` применяют оба исполнителя: `watch-subagent.sh` (навык run-subagent) и PHP-оркестратор `agent:orchestrate` — правило выбора у них единое. В PHP отклонения резолвятся в `RoleConfigVo::resolveCommandByComplexity()` на ACL-границе мапперов ChainExecution/DynamicLoop: в Execution/DynamicLoop VO уходит уже выбранная целая команда. При загрузке YAML лоадер валидирует поле fail-fast: уровни строго `C0`–`C5`, значения — существующие профили из `command_profiles` либо непустые inline-команды; в `RoleConfigVo` сохраняются только полные команды.

## Маппинг имени → файл

- `system_analyst` → `system_analyst.ru.md`
- `backend_developer` → `backend_developer_levsha.ru.md`
- и т.д.

Путь к директории с ролями — параметр `%task_orchestrator.roles_dir%`.
