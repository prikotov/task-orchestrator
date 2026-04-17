# CLI-команды

> Консольные команды для управления оркестрацией AI-агентов.

## Установка

```bash
composer install
php bin/console list
```

## Команды

### `app:agent:orchestrate`

Основная команда оркестрации — запускает цепочку агентов (static или dynamic).

```bash
php bin/console app:agent:orchestrate <task> [options]
```

| Опция | Сокращение | Описание | По умолчанию |
|---|---|---|---|
| `--chain` | `-c` | Имя цепочки | `implement` |
| `--runner` | `-r` | Движок запуска агента | `pi` |
| `--model` | `-m` | Модель LLM | — |
| `--working-dir` | `-d` | Рабочая директория проекта | — |
| `--dry-run` | — | Показать план цепочки без запуска | — |
| `--timeout` | `-t` | Таймаут на один шаг (секунды) | `1800` |
| `--topic` | — | Тема для dynamic-цепочки (по умолчанию = task) | — |
| `--max-rounds` | — | Макс. раундов (dynamic) | — |
| `--facilitator` | — | Роль фасилитатора (dynamic) | — |
| `--participants` | — | Участники через запятую (dynamic) | — |
| `--resume` | — | Путь к директории сессии для resume | — |
| `--audit-log` | — | Путь к JSONL audit-логу | — |
| `--no-audit-log` | — | Отключить audit-логирование | — |
| `--report-format` | — | Формат отчёта: `text`, `json`, `none` | `text` |
| `--report-file` | — | Путь к файлу для записи отчёта | — |

**Примеры:**

```bash
# Запуск цепочки "implement" с задачей
php bin/console app:agent:orchestrate "Add user registration endpoint"

# Dry run — показать план без запуска
php bin/console app:agent:orchestrate "Refactor billing" --dry-run

# Dynamic-цепочка с кастомными участниками
php bin/console app:agent:orchestrate "Design API" -c dynamic --participants "architect,analyst" --max-rounds 5

# Resume прерванной сессии
php bin/console app:agent:orchestrate "Fix bug" --resume var/sessions/2026-04-16_abc123

# Запуск с кастомной моделью и audit-логом
php bin/console app:agent:orchestrate "Add tests" -m claude-4-sonnet --audit-log var/log/audit.jsonl
```

**Повторный запуск** заблокирован (mutex-lock). Если команда уже выполняется — повторный вызов будет пропущен с предупреждением.

---

### `app:agent:run`

Запуск одного агента с указанной ролью.

```bash
php bin/console app:agent:run --role=<role> --task=<task> [options]
```

| Опция | Сокращение | Описание | По умолчанию |
|---|---|---|---|
| `--role` | `-r` | Роль агента (например, `system_analyst`) | — (обязательный) |
| `--task` | `-t` | Задача для агента | — (обязательный) |
| `--runner` | — | Движок запуска | `pi` |
| `--model` | `-m` | Модель LLM | — |
| `--tools` | — | Список инструментов | — |
| `--working-dir` | `-d` | Рабочая директория | — |

**Примеры:**

```bash
# Запуск аналитика
php bin/console app:agent:run -r system_analyst -t "Analyze requirements for payment module"

# С кастомной моделью
php bin/console app:agent:run -r backend_developer -t "Implement DTO" -m claude-4-sonnet
```

Метрики (tokens, cost, turns) отображаются при запуске с `-v`.

---

### `app:agent:runners`

Показать список зарегистрированных движков и их доступность.

```bash
php bin/console app:agent:runners
```

Вывод — таблица с колонками `Runner` и `Status` (Available/Unavailable).
