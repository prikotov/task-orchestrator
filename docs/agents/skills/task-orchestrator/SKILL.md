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
- Нужно проверить, что настроенные роли запускаются и отвечают

## Конфигурация

Цепочки описываются в `chains.yaml` — две секции: `roles` (роли агентов) и `chains` (цепочки). Подробный формат и описание всех полей — в [README.md](README.md). Полный пример конфига — в [assets/chains-example.yaml](assets/chains-example.yaml).

## Синтаксис

```bash
php vendor/bin/task-orchestrator agent:orchestrate [options] [--] <task>
```

`<task>` — описание задачи для агентов. Обязательный позиционный аргумент.

## Опции

| Опция | Сокращение | Описание | По умолчанию |
|-------|------------|----------|--------------|
| `--chain` | `-c` | Имя цепочки из `chains.yaml` | `implement` |
| `--working-dir` | `-d` | Рабочая директория | Текущая |
| `--timeout` | `-t` | Таймаут на шаг (секунды) | `600` |
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
| `--max-time` | Макс. время сессии в секундах | `3600` |
| `--facilitator` | Роль фасилитатора | Из конфига |
| `--participants` | Участники через запятую | Из конфига |

## Примеры

### Проверка конфигурации

```bash
# Все цепочки
php vendor/bin/task-orchestrator agent:orchestrate --validate-config "check"

# Конкретная цепочка
php vendor/bin/task-orchestrator agent:orchestrate --validate-config --chain=implement "check"

# С кастомным конфигом
php vendor/bin/task-orchestrator agent:orchestrate --config=path/to/chains.yaml --validate-config "check"
```

`<task>` обязателен, но при `--validate-config` игнорируется — подойдёт любая строка.

Exit codes: `0` — конфиг валиден, `5` — ошибки (подробности в выводе).

### Проверка запуска ролей из chains.yaml (`validate:connectivity`)

```bash
# Показать роли и команды без запуска процессов
php vendor/bin/task-orchestrator validate:connectivity --dry-run

# Проверить все роли из config/chains.yaml
php vendor/bin/task-orchestrator validate:connectivity

# Проверить одну роль с кастомным таймаутом
php vendor/bin/task-orchestrator validate:connectivity --role=backend_developer_tony --timeout=60
```

Команда читает top-level `roles`, резолвит `@system-prompt`/`@append-system-prompt`, запускает каждую `command` как argv array и добавляет минимальный user prompt `Ответь ровно ok без Markdown.` последним argv-аргументом. Exit codes: `0` — все роли OK, `1` — есть fail/timeout/empty output/invalid input.

### План без запуска (dry-run)

```bash
php vendor/bin/task-orchestrator agent:orchestrate --dry-run "Создать REST API"
php vendor/bin/task-orchestrator agent:orchestrate --dry-run --chain=analyze "Анализ архитектуры"
```

### Кастомный конфиг цепочек

```bash
# Указать произвольный chains.yaml
php vendor/bin/task-orchestrator agent:orchestrate --config=/path/to/chains.yaml "Задача"

# Валидация кастомного конфига
php vendor/bin/task-orchestrator agent:orchestrate --config=/path/to/chains.yaml --validate-config "check"
```

Без `--config` используется путь по умолчанию (из Symfony-конфигурации). Несуществующий файл → exit code `5`.

### Static-цепочка

```bash
# Полный цикл реализации (implement)
php vendor/bin/task-orchestrator agent:orchestrate "Создать endpoint POST /users"

# Анализ без реализации
php vendor/bin/task-orchestrator agent:orchestrate --chain=analyze "Проанализировать архитектуру"

# Срочный фикс
php vendor/bin/task-orchestrator agent:orchestrate --chain=hotfix "Исправить NPE в UserService"

# JSON-отчёт в файл
php vendor/bin/task-orchestrator agent:orchestrate --report-format=json --report-file=report.json "Задача"

# С увеличенным таймаутом
php vendor/bin/task-orchestrator agent:orchestrate --timeout=600 "Сложная задача"
```

### Dynamic-цепочка (brainstorm)

```bash
# С defaults из конфига
php vendor/bin/task-orchestrator agent:orchestrate --chain=brainstorm "Архитектура платёжного модуля"

# Переопределить участников
php vendor/bin/task-orchestrator agent:orchestrate --chain=brainstorm --participants=dev1,dev2 "Тема"
```

### Resume прерванной цепочки

```bash
php vendor/bin/task-orchestrator agent:orchestrate --resume=var/agent/chains/implement_2026-04-24_12-30 "Продолжить"
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
