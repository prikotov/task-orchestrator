# CLI-команды

> Консольные команды для управления оркестрацией AI-агентов.

## Установка

```bash
composer install
php vendor/bin/task-orchestrator list
```

## Команды

### `app:agent:orchestrate`

Основная команда оркестрации — запускает цепочку агентов (static или dynamic).

```bash
php vendor/bin/task-orchestrator <task> [options]
```

| Опция | Сокращение | Описание | По умолчанию |
|---|---|---|---|
| `--chain` | `-c` | Имя цепочки | `implement` |
| `--runner` | `-r` | Движок запуска агента | `pi` |
| `--model` | `-m` | Модель LLM | — |
| `--working-dir` | `-d` | Рабочая директория проекта | — |
| `--dry-run` | — | Показать план цепочки без запуска | — |
| `--timeout` | `-t` | Таймаут на один шаг (секунды) | `600` |
| `--topic` | — | Тема для dynamic-цепочки (по умолчанию = task) | — |
| `--max-rounds` | — | Макс. раундов (dynamic) | — |
| `--max-time` | — | Макс. время сессии в секундах (dynamic, переопределяет chains.yaml) | `3600` |
| `--facilitator` | — | Роль фасилитатора (dynamic) | — |
| `--participants` | — | Участники через запятую (dynamic) | — |
| `--resume` | — | Путь к директории сессии для resume | — |
| `--audit-log` | — | Путь к JSONL audit-логу | — |
| `--no-audit-log` | — | Отключить audit-логирование | — |
| `--report-format` | — | Формат отчёта: `text`, `json`, `none` | `text` |
| `--report-file` | — | Путь к файлу для записи отчёта | — |
| `--no-context-files` | — | Не загружать AGENTS.md/CLAUDE.md | — |
| `--validate-config` | — | Проверить конфигурацию без запуска | — |
| `--config` | — | Путь к файлу `chains.yaml` (переопределяет путь по умолчанию) | — |

**Примеры:**

```bash
# Запуск цепочки "implement" с задачей
php vendor/bin/task-orchestrator "Add user registration endpoint"

# Dry run — показать план без запуска
php vendor/bin/task-orchestrator "Refactor billing" --dry-run

# Dynamic-цепочка с кастомными участниками
php vendor/bin/task-orchestrator "Design API" -c dynamic --participants "architect,analyst" --max-rounds 5

# Resume прерванной сессии
php vendor/bin/task-orchestrator "Fix bug" --resume var/sessions/2026-04-16_abc123

# Запуск с кастомной моделью и audit-логом
php vendor/bin/task-orchestrator "Add tests" -m claude-4-sonnet --audit-log var/log/audit.jsonl

# Кастомный конфиг цепочек
task-orchestrator app:agent:orchestrate --config=path/to/chains.yaml "Задача"

# Валидация кастомного конфига
task-orchestrator app:agent:orchestrate --config=path/to/chains.yaml --validate-config "check"
```

**Exit codes:**

| Code | Constant | Meaning |
|------|----------|---------|
| `0` | `success` | Успех |
| `1` | `chainFailed` | Ошибка шага/агента |
| `3` | `chainNotFound` | Цепочка не найдена |
| `4` | `budgetExceeded` | Превышен бюджет |
| `5` | `invalidConfig` | Неверная конфигурация или аргументы |
| `6` | `timeout` | Превышен таймаут шага/раунда |

**Повторный запуск** заблокирован (mutex-lock). Если команда уже выполняется — повторный вызов будет пропущен с предупреждением.

---

### `app:agent:run`

Запуск одного агента с указанной ролью.

```bash
php vendor/bin/task-orchestrator app:agent:run --role=<role> --task=<task> [options]
```

| Опция | Сокращение | Описание | По умолчанию |
|---|---|---|---|
| `--role` | `-r` | Роль агента (например, `system_analyst`) | — (обязательный) |
| `--task` | `-t` | Задача для агента | — (обязательный) |
| `--runner` | — | Движок запуска | `pi` |
| `--model` | `-m` | Модель LLM | — |
| `--tools` | — | Список инструментов | — |
| `--working-dir` | `-d` | Рабочая директория | — |
| `--timeout` | — | Таймаут выполнения агента в секундах (0 = без лимита) | `300` |
| `--context` | — | Дополнительный контекст для агента (JSON-строка) | — |

**Примеры:**

```bash
# Запуск аналитика
php vendor/bin/task-orchestrator app:agent:run -r system_analyst -t "Analyze requirements for payment module"

# С кастомной моделью
php vendor/bin/task-orchestrator app:agent:run -r backend_developer -t "Implement DTO" -m claude-4-sonnet

# С таймаутом 600 секунд
php vendor/bin/task-orchestrator app:agent:run -r backend_developer -t "Implement DTO" --timeout 600

# С дополнительным контекстом
php vendor/bin/task-orchestrator app:agent:run -r backend_developer -t "Implement DTO" --context '{"project":"task-orchestrator","language":"PHP"}'

# Без лимита времени
php vendor/bin/task-orchestrator app:agent:run -r backend_developer -t "Refactor module" --timeout 0
```

Метрики (tokens, cost, turns) отображаются при запуске с `-v`.

---

### `app:agent:runners`

Показать список зарегистрированных движков и их доступность.

```bash
php vendor/bin/task-orchestrator app:agent:runners
```

Вывод — таблица с колонками `Runner` и `Status` (Available/Unavailable).
