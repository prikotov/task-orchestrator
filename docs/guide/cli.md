# CLI-команды

> Консольные команды для управления оркестрацией AI-агентов.

## Установка

```bash
composer install
php vendor/bin/task-orchestrator list
```

## Команды

### `agent:orchestrate`

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
| `--timeout` | `-t` | Таймаут на один шаг (секунды). При явном указании переопределяет `chain.timeout` | из `chain.timeout`, иначе `600` (static/dynamic) |
| `--topic` | — | Тема для dynamic-цепочки (по умолчанию = task) | — |
| `--max-rounds` | — | Макс. раундов (dynamic) | — |
| `--max-time` | — | Макс. время сессии в секундах (dynamic). При явном указании переопределяет `chain.max_time` | из `chain.max_time`, иначе `3600` |
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
php vendor/bin/task-orchestrator agent:orchestrate "Add user registration endpoint"

# Dry run — показать план без запуска
php vendor/bin/task-orchestrator agent:orchestrate "Refactor billing" --dry-run

# Dynamic-цепочка с кастомными участниками
php vendor/bin/task-orchestrator agent:orchestrate "Design API" -c dynamic --participants "architect,analyst" --max-rounds 5

# Resume прерванной сессии
php vendor/bin/task-orchestrator agent:orchestrate "Fix bug" --resume var/sessions/2026-04-16_abc123

# Запуск с кастомной моделью и audit-логом
php vendor/bin/task-orchestrator agent:orchestrate "Add tests" -m claude-4-sonnet --audit-log var/log/audit.jsonl

# Кастомный конфиг цепочек
php vendor/bin/task-orchestrator agent:orchestrate --config=path/to/chains.yaml "Задача"

# Валидация кастомного конфига
php vendor/bin/task-orchestrator agent:orchestrate --config=path/to/chains.yaml --validate-config "check"
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

### Проверка запуска ролей из chains.yaml (`validate:connectivity`)

Проверяет, что top-level `roles` из `chains.yaml` запускаются и возвращают непустой stdout.
Команда читает только секцию `roles`, резолвит `@system-prompt`/`@append-system-prompt`, запускает каждую `command` как argv array (без shell-строки) и добавляет user prompt последним argv-аргументом: `Ответь ровно ok без Markdown.`

```bash
php vendor/bin/task-orchestrator validate:connectivity [options]
```

| Опция | Сокращение | Описание | По умолчанию |
|---|---|---|---|
| `--config` | — | Путь к файлу `chains.yaml` | `config/chains.yaml` из конфигурации |
| `--role` | — | Проверить только одну роль | — |
| `--timeout` | — | Таймаут на одну роль в секундах, положительное целое | `30` |
| `--dry-run` | — | Показать роли и resolved command preview (превью разрешённой команды) без запуска процессов | — |

Успех роли: exit code процесса `0`, stdout после `trim()` не пустой, timeout не сработал.
Вывод — таблица `Role | Status | Time | Error`.

**Примеры:**

```bash
# Проверить все роли из конфига по умолчанию
php vendor/bin/task-orchestrator validate:connectivity

# Показать команды без запуска LLM/процессов
php vendor/bin/task-orchestrator validate:connectivity --dry-run

# Проверить одну роль
php vendor/bin/task-orchestrator validate:connectivity --role=backend_developer_tony

# Кастомный конфиг и таймаут
php vendor/bin/task-orchestrator validate:connectivity --config=path/to/chains.yaml --timeout=60
```

**Exit codes:**

| Code | Meaning |
|------|---------|
| `0` | Все выбранные роли ответили успешно |
| `1` | Хотя бы одна роль завершилась с ошибкой, timeout, пустым stdout или входные параметры невалидны |

---

### `agent:run`

Запуск одного агента с указанной ролью.

```bash
php vendor/bin/task-orchestrator agent:run --role=<role> --task=<task> [options]
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
php vendor/bin/task-orchestrator agent:run -r system_analyst -t "Analyze requirements for payment module"

# С кастомной моделью
php vendor/bin/task-orchestrator agent:run -r backend_developer -t "Implement DTO" -m claude-4-sonnet

# С таймаутом 600 секунд
php vendor/bin/task-orchestrator agent:run -r backend_developer -t "Implement DTO" --timeout 600

# С дополнительным контекстом
php vendor/bin/task-orchestrator agent:run -r backend_developer -t "Implement DTO" --context '{"project":"task-orchestrator","language":"PHP"}'

# Без лимита времени
php vendor/bin/task-orchestrator agent:run -r backend_developer -t "Refactor module" --timeout 0
```

Метрики (tokens, cost, turns) отображаются при запуске с `-v`.

---

### `agent:runners`

Показать список зарегистрированных движков и их доступность.

```bash
php vendor/bin/task-orchestrator agent:runners
```

Вывод — таблица с колонками `Runner` и `Status` (Available/Unavailable).

---

### `agent:init`

Устанавливает общий skill `become-role` в host-проект: создаёт симлинк в `<project>/.agents/skills/`, чтобы AI-инструменты (pi, codex и др.) видели его как нативный skill через кросс-клиентскую конвенцию `.agents/skills/`. Сам skill остаётся внутри пакета task-orchestrator.

Команда идемпотентна: повторный запуск безопасен. Для source checkout (локальной копии исходников) используйте:

```bash
bin/console agent:init [--force]
```

В Composer host-проекте запускайте после `composer install`:

```bash
php vendor/bin/task-orchestrator agent:init [--force]
```

После успешной установки вызывайте skill только по установленному пути:

```bash
.agents/skills/become-role/scripts/become-role.sh <role|file>
```

#### Матрица возможностей `v0.2.0`

| Возможность | Source/Composer | PHAR |
|---|---|---|
| Регистрация команды `agent:init` | Да | Да |
| Установка `become-role` | Поддерживается полностью | Не поддерживается: fail-fast с кодом `1` до любых файловых записей |
| Запуск установленного `become-role` | `.agents/skills/become-role/scripts/become-role.sh <role\|file>` | Недоступен |

PHAR остаётся secondary/best-effort каналом. В PHAR `agent:init` и `agent:init --force` не создают и не изменяют `.agents`, завершаются с кодом `1` и рекомендуют Composer с рабочей командой `php vendor/bin/task-orchestrator agent:init`.

| Опция | Описание |
|---|---|
| `--force`, `-f` | Пересоздать симлинк, если он существует и некорректен |

**Exit codes:** `0` — успех (или уже установлен); `1` — PHAR не поддерживает установку, skill не найден в пакете либо обнаружен конфликт без `--force`.

---

### `agent:role-skills`

Резолвит skills (навыки) роли и выводит их каталог для включения в system prompt агента. Используется мета-скиллом `become-role` для динамического объявления skills роли в контексте (универсально для pi и codex).

```bash
php vendor/bin/task-orchestrator agent:role-skills <role> [--format=block|list|json]
```

| Аргумент/опция | Описание | По умолчанию |
|---|---|---|
| `role` (аргумент) | Имя роли (snake_case), как в `config/chains.yaml` `roles.<role>` и имя файла роли без локали | — (обязательный) |
| `--format` | Формат вывода: `block` (XML-каталог `<available_skills>`), `list`, `json` | `block` |

Каталог разворачивает транзитивные зависимости skills (`depends_on` в frontmatter `SKILL.md`): зависимости помещаются перед зависящими от них skills, дубликаты исключаются. Если роль не декларирует skills — выводится пустая строка (по стандарту Agent Skills пустой блок не выводится).

**Примеры:**

```bash
# XML-каталог skills тимлида для system prompt
php vendor/bin/task-orchestrator agent:role-skills team_lead_alex --format=block

# Человекочитаемый список
php vendor/bin/task-orchestrator agent:role-skills team_lead_alex --format=list

# JSON (имя, описание, путь каждого skill + готовый catalog)
php vendor/bin/task-orchestrator agent:role-skills team_lead_alex --format=json
```

**Exit codes:**

| Code | Meaning |
|------|---------|
| `0` | Успех |
| `1` | Роль или её skill не найдены, цикл `depends_on` (fail-fast) |
| `2` | Неверное значение `--format` |
