---
name: task-orchestrator
description: Запуск оркестрации AI-агентов по цепочке: static/dynamic chains, quality gates, отчёты, resume
---

# Use Task Orchestrator

Инструкция для AI-агента по запуску оркестрации цепочек через `task-orchestrator`.

## Когда использовать

- Пользователь просит запустить цепочку, оркестрировать задачу
- Нужно выполнить задачу через последовательность AI-агентов
- Требуется dynamic-обсуждение (brainstorm, code review)
- Нужно проверить конфигурацию цепочек

## Конфигурация

Цепочки описываются в `chains.yaml` — две секции: `roles` (роли агентов) и `chains` (цепочки). Подробный формат и описание всех полей — в [README.md](README.md). Полный пример конфига — в [assets/chains-example.yaml](assets/chains-example.yaml).

## Синтаксис

```bash
task-orchestrator app:agent:orchestrate [options] [--] <task>
```

`<task>` — описание задачи для агентов. Обязательный позиционный аргумент.

## Опции

| Опция | Сокращение | Описание | По умолчанию |
|-------|------------|----------|--------------|
| `--chain` | `-c` | Имя цепочки из `chains.yaml` | `implement` |
| `--working-dir` | `-d` | Рабочая директория | Текущая |
| `--timeout` | `-t` | Таймаут на шаг (секунды) | `1800` |
| `--dry-run` | — | Показать план без запуска | — |
| `--config` | — | Путь к файлу `chains.yaml` (переопределяет путь по умолчанию) | — |
| `--validate-config` | — | Проверить конфигурацию без запуска | — |
| `--resume` | — | Путь к директории сессии для resume | — |
| `--report-format` | — | Формат отчёта: `text`, `json`, `none` | `text` |
| `--report-file` | — | Путь к файлу для записи отчёта | stdout |
| `--no-audit-log` | — | Отключить audit-логирование | — |
| `--no-context-files` | — | Не загружать AGENTS.md/CLAUDE.md | — |

Dynamic-цепочки дополнительно:

| Опция | Описание | По умолчанию |
|-------|----------|--------------|
| `--topic` | Тема обсуждения | = `<task>` |
| `--max-rounds` | Максимум раундов | Из конфига |
| `--facilitator` | Роль фасилитатора | Из конфига |
| `--participants` | Участники через запятую | Из конфига |

## Примеры

### Проверка конфигурации

```bash
# Все цепочки
task-orchestrator app:agent:orchestrate --validate-config "check"

# Конкретная цепочка
task-orchestrator app:agent:orchestrate --validate-config --chain=implement "check"

# С кастомным конфигом
task-orchestrator app:agent:orchestrate --config=path/to/chains.yaml --validate-config "check"
```

`<task>` обязателен, но при `--validate-config` игнорируется — подойдёт любая строка.

Exit codes: `0` — конфиг валиден, `5` — ошибки (подробности в выводе).

### План без запуска (dry-run)

```bash
task-orchestrator app:agent:orchestrate --dry-run "Создать REST API"
task-orchestrator app:agent:orchestrate --dry-run --chain=analyze "Анализ архитектуры"
```

### Кастомный конфиг цепочек

```bash
# Указать произвольный chains.yaml
task-orchestrator app:agent:orchestrate --config=/path/to/chains.yaml "Задача"

# Валидация кастомного конфига
task-orchestrator app:agent:orchestrate --config=/path/to/chains.yaml --validate-config "check"
```

Без `--config` используется путь по умолчанию (из Symfony-конфигурации). Несуществующий файл → exit code `5`.

### Static-цепочка

```bash
# Полный цикл реализации (implement)
task-orchestrator app:agent:orchestrate "Создать endpoint POST /users"

# Анализ без реализации
task-orchestrator app:agent:orchestrate --chain=analyze "Проанализировать архитектуру"

# Срочный фикс
task-orchestrator app:agent:orchestrate --chain=hotfix "Исправить NPE в UserService"

# JSON-отчёт в файл
task-orchestrator app:agent:orchestrate --report-format=json --report-file=report.json "Задача"

# С увеличенным таймаутом
task-orchestrator app:agent:orchestrate --timeout=3600 "Сложная задача"
```

### Dynamic-цепочка (brainstorm)

```bash
# С defaults из конфига
task-orchestrator app:agent:orchestrate --chain=brainstorm "Архитектура платёжного модуля"

# Переопределить участников
task-orchestrator app:agent:orchestrate --chain=brainstorm --participants=dev1,dev2 "Тема"
```

### Resume прерванной цепочки

```bash
task-orchestrator app:agent:orchestrate --resume=var/agent/chains/implement_2026-04-24_12-30 "Продолжить"
```

## Результат

CLI выводит ход выполнения: роль, runner, токены, стоимость, время.

Exit codes:

| Code | Meaning |
|------|---------|
| `0` | Успех |
| `1` | Ошибка шага/агента |
| `3` | Цепочка не найдена |
| `4` | Превышен бюджет |
| `5` | Неверная конфигурация или аргументы |
| `6` | Превышен таймаут шага/раунда |

Отчёт — в выбранном формате (`text`/`json`; `none` — отключить). JSONL audit-log — в `var/agent_audit.jsonl` (если не отключён).
