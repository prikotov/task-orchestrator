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

Цепочки описываются в `chains.yaml` — две секции: `roles` (роли агентов) и `chains` (цепочки). Подробный формат — в [README.md](README.md).

```yaml
roles:
  analyst:
    prompt_file: prompts/analyst.md
    command: [pi, --mode, json, -p, --no-session, --model, gpt-4o, --system-prompt, "@system-prompt"]

  developer:
    prompt_file: prompts/developer.md
    command: [pi, --mode, json, -p, --no-session, --model, gpt-4o, --system-prompt, "@system-prompt"]

chains:
  implement:
    description: "Analyze → Implement → Review"
    steps:
      - { type: agent, role: analyst, name: analyze }
      - { type: agent, role: developer, name: implement }
      - { type: quality_gate, command: 'vendor/bin/phpunit', label: 'Tests', timeout_seconds: 120 }
    fix_iterations:
      - group: dev-review
        steps: [implement, review]
        max_iterations: 3

  brainstorm:
    type: dynamic
    facilitator: analyst
    participants: [developer, reviewer]
    max_rounds: 10
    prompts:
      brainstorm_system: prompts/brainstorm/system.txt
      facilitator_append: prompts/brainstorm/facilitator_append.txt
      facilitator_start: prompts/brainstorm/facilitator_start.txt
      facilitator_continue: prompts/brainstorm/facilitator_continue.txt
      facilitator_finalize: prompts/brainstorm/facilitator_finalize.txt
      participant_append: prompts/brainstorm/participant_append.txt
      participant_user: prompts/brainstorm/participant_user.txt
```

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
```

`<task>` обязателен, но при `--validate-config` игнорируется — подойдёт любая строка.

Exit codes: `0` — конфиг валиден, `5` — ошибки (подробности в выводе).

### План без запуска (dry-run)

```bash
task-orchestrator app:agent:orchestrate --dry-run "Создать REST API"
task-orchestrator app:agent:orchestrate --dry-run --chain=analyze "Анализ архитектуры"
```

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

Exit codes: `0` — успех, `1` — ошибка шага, `3` — цепочка не найдена, `4` — превышен бюджет, `5` — невалидный конфиг.

Отчёт — в выбранном формате (`text`/`json`; `none` — отключить). JSONL audit-log — в `var/agent_audit.jsonl` (если не отключён).
