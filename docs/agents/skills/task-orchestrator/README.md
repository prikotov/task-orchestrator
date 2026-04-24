# Install Task Orchestrator

Инструкция по установке `task-orchestrator` — CLI-утилиты для оркестрации AI-агентов.

## Требования

- **PHP >= 8.4**
- **Composer** (для варианта A)

## Вариант A: Composer (рекомендуется)

Глобальная установка (CLI доступен везде):

```bash
composer global require prikotov/task-orchestrator
```

В существующий проект:

```bash
composer require prikotov/task-orchestrator
```

Если Composer не установлен — см. [официальную инструкцию](https://getcomposer.org/).

Проверка:

```bash
~/.composer/vendor/bin/task-orchestrator --version   # global
vendor/bin/task-orchestrator --version                # project
```

## Вариант B: Phar (альтернатива)

Скачать из [GitHub Releases](https://github.com/prikotov/task-orchestrator/releases):

```bash
curl -L -o task-orchestrator.phar https://github.com/prikotov/task-orchestrator/releases/latest/download/task-orchestrator.phar
chmod +x task-orchestrator.phar
mv task-orchestrator.phar /usr/local/bin/task-orchestrator
task-orchestrator --version
```

> **Note:** Phar публикуется на best-effort основе. Для автообновления используйте Composer.

## Первый запуск

Проверить конфигурацию цепочек:

```bash
task-orchestrator app:agent:orchestrate --validate-config "check"
```

Запустить цепочку:

```bash
task-orchestrator app:agent:orchestrate "Ваша задача"
```

## Формат конфигурации (`chains.yaml`)

Конфигурация состоит из двух секций: `roles` (роли агентов) и `chains` (цепочки).

### Роль

Роль описывает, кого вызывать и с какой моделью:

```yaml
roles:
  developer:
    prompt_file: prompts/developer.md      # Файл системного промпта
    command:                                # CLI-команда запуска агента
      - pi
      - --mode
      - json
      - -p
      - --no-session
      - --model
      - gpt-4o
      - --system-prompt
      - "@system-prompt"                   # Резолвится в путь к prompt_file
```

| Поле | Описание | Обязательное |
|------|----------|--------------|
| `prompt_file` | Путь к файлу системного промпта (.md) | Да |
| `command` | Массив аргументов CLI-команды | Да |
| `fallback.command` | Запасная команда при недоступности основной | Нет |

`@system-prompt` в `command` автоматически заменяется на путь к файлу из `prompt_file`.

### Static-цепочка

Фиксированная последовательность шагов:

```yaml
chains:
  implement:
    description: "Полный цикл реализации"
    retry_policy:                              # Опционально — на уровне цепочки
      max_retries: 3
      initial_delay_ms: 1000
      max_delay_ms: 30000
      multiplier: 2.0
    steps:
      - type: agent
        role: analyst
        name: analyze                          # Опционально — имя шага для fix_iterations
      - type: agent
        role: developer
        name: implement
        retry_policy:                          # Опционально — переопределение на уровне шага
          max_retries: 5
      - type: quality_gate                     # Shell-команда (pass/fail, не прерывает цепочку)
        command: 'vendor/bin/phpcs'
        label: 'PHP CodeSniffer'
        timeout_seconds: 60
    fix_iterations:                            # Опционально — циклы доработки
      - group: dev-review
        steps: [implement, review]
        max_iterations: 3
    budget:                                    # Опционально — ограничения стоимости (USD)
      max_cost_total: 5.0
      max_cost_per_step: 1.5
```

**Типы шагов:**

| Тип | `type:` | Описание |
|-----|---------|----------|
| Agent | `agent` | Вызов AI-агента в указанной роли |
| Quality gate | `quality_gate` | Shell-команда валидации (fail = warning) |

### Dynamic-цепочка

Фасилитатор выбирает, кто говорит в каждом раунде:

```yaml
chains:
  brainstorm:
    type: dynamic
    description: "Фасилитируемый brainstorm"
    facilitator: team_lead
    participants: [architect, developer]
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

| Поле | Описание | Обязательное |
|------|----------|--------------|
| `type` | `dynamic` | Да |
| `facilitator` | Роль фасилитатора | Да |
| `participants` | Список ролей-участников | Да |
| `max_rounds` | Максимум раундов | Да |
| `prompts` | Шаблоны промптов для фасилитатора и участников | Да |

### Полный пример

```yaml
roles:
  analyst:
    prompt_file: prompts/analyst.md
    command: [pi, --mode, json, -p, --no-session, --model, gpt-4o, --system-prompt, "@system-prompt"]

  developer:
    prompt_file: prompts/developer.md
    command: [pi, --mode, json, -p, --no-session, --model, gpt-4o, --system-prompt, "@system-prompt"]

  reviewer:
    prompt_file: prompts/reviewer.md
    command: [pi, --mode, json, -p, --no-session, --model, gpt-4o, --system-prompt, "@system-prompt"]

chains:
  implement:
    description: "Analyze → Implement → Review"
    steps:
      - { type: agent, role: analyst, name: analyze }
      - { type: agent, role: developer, name: implement }
      - { type: agent, role: reviewer, name: review }
    fix_iterations:
      - group: dev-review
        steps: [implement, review]
        max_iterations: 3

  brainstorm:
    type: dynamic
    description: "Brainstorm"
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

## Troubleshooting

| Проблема | Решение |
|----------|---------|
| `php: command not found` | Установить PHP 8.4+, добавить в `$PATH` |
| `composer: command not found` | Установить Composer: [getcomposer.org](https://getcomposer.org) |
| `task-orchestrator: command not found` | Добавить `~/.composer/vendor/bin` в `$PATH` или использовать полный путь |
| `Could not find package` | Пакет не опубликован на Packagist — использовать Phar |
| `Chain not found` | Проверить путь к `chains.yaml` в конфигурации |
