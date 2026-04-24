---
name: task-orchestrator
description: Запуск оркестрации AI-агентов по цепочке: static/dynamic chains, quality gates, отчёты, resume
---

# Use Task Orchestrator

Инструкция для AI-агента по запуску оркестрации цепочек через `task-orchestrator`.

## Когда использовать

- Пользователь просит «запусти цепочку», «оркестрируй задачу», «запусти оркестрацию»
- Нужно выполнить задачу через последовательность AI-агентов
- Требуется запустить dynamic-обсуждение (brainstorm, code review)
- Нужно проверить конфигурацию цепочек без запуска

## Как использовать

### Шаг 1: Проверить конфигурацию (опционально)

Перед первым запуском или после изменения `chains.yaml` — проверить конфиг:

```bash
task-orchestrator app:agent:orchestrate --validate-config "check"
```

Опции:

| Опция | Описание | По умолчанию |
|-------|----------|--------------|
| `--validate-config` | Проверить конфиг без запуска | — |
| `--chain <name>` | Проверить конкретную цепочку | Все цепочки |

Примеры:

```bash
# Проверить все цепочки
task-orchestrator app:agent:orchestrate --validate-config "check"

# Проверить конкретную цепочку
task-orchestrator app:agent:orchestrate --validate-config --chain=implement "check"
```

Exit codes: 0 — конфиг валиден, 5 — ошибки (подробности в выводе).

### Шаг 2: Посмотреть план (dry-run)

Показать последовательность шагов без запуска агентов:

```bash
task-orchestrator app:agent:orchestrate --dry-run --chain=implement "Создать REST API"
```

Опции:

| Опция | Описание | По умолчанию |
|-------|----------|--------------|
| `--dry-run` | Показать план без запуска | — |
| `--chain <name>` | Имя цепочки | `implement` |

Примеры:

```bash
# План для цепочки implement
task-orchestrator app:agent:orchestrate --dry-run "Рефакторинг модуля X"

# План для цепочки analyze
task-orchestrator app:agent:orchestrate --dry-run --chain=analyze "Анализ архитектуры"
```

### Шаг 3: Запустить static-цепочку

```bash
task-orchestrator app:agent:orchestrate --chain=<name> "<задача>"
```

Параметры:

| Параметр | Описание | Обязательный |
|----------|----------|--------------|
| `task` | Описание задачи для агентов | Да |

Опции:

| Опция | Сокращение | Описание | По умолчанию |
|-------|------------|----------|--------------|
| `--chain` | `-c` | Имя цепочки из `chains.yaml` | `implement` |
| `--working-dir` | `-d` | Рабочая директория | Текущая |
| `--timeout` | `-t` | Таймаут на шаг (секунды) | `1800` |
| `--report-format` | | Формат отчёта: `text`, `json`, `none` | `text` |
| `--report-file` | | Путь к файлу для записи отчёта | stdout |
| `--no-audit-log` | | Отключить audit-логирование | — |
| `--no-context-files` | | Не загружать AGENTS.md/CLAUDE.md | — |

Примеры:

```bash
# Полный цикл реализации (implement)
task-orchestrator app:agent:orchestrate "Создать endpoint POST /users"

# Анализ без реализации
task-orchestrator app:agent:orchestrate --chain=analyze "Проанализировать architecture"

# Срочный фикс
task-orchestrator app:agent:orchestrate --chain=hotfix "Исправить NPE в UserService"

# JSON-отчёт в файл
task-orchestrator app:agent:orchestrate --chain=implement --report-format=json --report-file=report.json "Задача"

# С увеличенным таймаутом
task-orchestrator app:agent:orchestrate --timeout=3600 "Сложная задача"
```

### Шаг 4: Запустить dynamic-цепочку

Dynamic-цепочки (brainstorm) запускаются так же, но поддерживают дополнительные опции:

```bash
task-orchestrator app:agent:orchestrate --chain=brainstorm "Тема обсуждения"
```

Дополнительные опции:

| Опция | Описание | По умолчанию |
|-------|----------|--------------|
| `--topic` | Тема для обсуждения | = task |
| `--max-rounds` | Максимум раундов | Из конфига |
| `--facilitator` | Роль фасилитатора | Из конфига |
| `--participants` | Участники через запятую | Из конфига |

Примеры:

```bash
# Brainstorm с defaults
task-orchestrator app:agent:orchestrate --chain=brainstorm "Архитектура платёжного модуля"

# Переопределить участников
task-orchestrator app:agent:orchestrate --chain=brainstorm --participants=dev1,dev2 "Тема"
```

### Шаг 5: Resume прерванной цепочки

Если цепочка прервалась — можно возобновить из последнего шага:

```bash
task-orchestrator app:agent:orchestrate --resume=<path-to-session-dir> "<задача>"
```

Пример:

```bash
task-orchestrator app:agent:orchestrate --resume=var/agent/chains/implement_2026-04-24_12-30 "Продолжить"
```

## Результат

- CLI выводит ход выполнения каждого шага: роль, runner, токены, стоимость, время
- Итоговый отчёт в выбранном формате (text/json)
- Exit code: 0 — успех, 1 — ошибка, 3 — цепочка не найдена, 5 — невалидный конфиг
- JSONL audit-log в `var/agent_audit.jsonl` (если не отключён)
