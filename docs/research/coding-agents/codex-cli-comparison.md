# Codex CLI (OpenAI) — Исследование для интеграции как сабагент

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-09
**Объект:** Codex CLI v0.129.0 (`@openai/codex`, Rust/Node.js)
**Задача:** [TASK-research-codex-cli](../../../todo/done/TASK-research-codex-cli.todo.md)

---

## Сводка

Codex CLI — официальный терминальный AI-coding-agent от OpenAI, написанный на Rust, дистрибутируемый как npm-пакет `@openai/codex`. Версия 0.129.0, лицензия Apache-2.0. Codex — наш второй агент, который мы уже используем интерактивно. Данный отчёт оценивает его пригодность для запуска как сабагента с нашей системой ролей и скиллов.

---

## Критерий 1. Системный промпт

### Возможности

| Механизм | Поведение |
|----------|-----------|
| `model_instructions_file` в `~/.codex/config.toml` | **Полная замена** базового системного промпта. Файл читается и подставляется как `role: developer` сообщение. |
| `model_instructions_file` в `.codex/config.toml` (проектный) | Проектная конфигурация — более приоритетная. |
| `-c model_instructions_file="path"` (CLI override) | Переопределение через CLI-аргумент. |
| Дополнение (append) к системному промпту | **Не поддерживается** — нет аналога `--append-system-prompt` (как у Pi). Только полная замена через файл. |

### Примеры конфигурации

```toml
# ~/.codex/config.toml — глобальная замена
model_instructions_file = "/home/dp/.codex/no-base-instructions.md"
```

```toml
# .codex/config.toml — проектная замена
model_instructions_file = "docs/agents/codex-cli/system-prompt.md"
```

```bash
# CLI override
codex exec -c model_instructions_file='"/path/to/custom-prompt.md"' --json "Prompt"
```

### Наш подход

Текущий файл `~/.codex/no-base-instructions.md` содержит минимальный промпт:
```
Follow the user's instructions and visible project instructions. Keep responses concise and task-focused.
```

Это **заменяет** дефолтный многостраничный промпт Codex (содержащий инструкции по sandbox, tools, памяти и т.д.) на минимальный. Затем `AGENTS.md` проекта подставляется как `role: user` сообщение (см. Критерий 4).

### Сравнение с Pi

| Аспект | Pi (`--system-prompt` / `--append-system-prompt`) | Codex (`model_instructions_file`) |
|--------|---------------------------------------------------|-----------------------------------|
| Полная замена | ✅ `--system-prompt` | ✅ `model_instructions_file` |
| Дополнение | ✅ `--append-system-prompt` | ❌ Нет аналога |
| CLI inline | ✅ `--system-prompt "text"` | ❌ Только через файл |
| Файловая замена | ✅ `.pi/SYSTEM.md` | ✅ `model_instructions_file` в config.toml |

### Оценка: ⚠️ Частичная поддержка

Полная замена через файл работает надёжно. **Нет механизма дополнения** (append) — нельзя дописать к текущему промпту, только заменить целиком. Для инъекции роли нужно либо включать роль в файл `model_instructions_file`, либо передавать роль через user prompt (как часть промпта задачи). CLI inline замена невозможна — только через файл или config override.

---

## Критерий 2. Промпт агента / Роль

### Подход

Codex не имеет встроенного механизма «ролей» или «personality» как самостоятельной сущности. Однако:

| Механизм | Описание |
|----------|----------|
| `-p/--profile <CONFIG_PROFILE>` | Выбор профиля из `config.toml` — набор предустановленных параметров модели, sandbox, инструкций. |
| `personality` в config.toml | Экспериментальная фича (feature `personality` = stable). Задаёт «личность» агента: `pragmatic`, `friendly`, и т.д. Не позволяет загрузить кастомный файл роли. |
| `model_instructions_file` | Полная замена системного промпта — сюда можно встроить содержимое роли. |
| User prompt | Передача роли как части промпта задачи — модель получает инструкцию в `role: user` сообщении. |

### Профили (Profiles)

Профили описываются в `config.toml` как именованные секции с параметрами:

```toml
[profiles.backend_developer]
model = "o4-mini"
model_instructions_file = "/path/to/backend-role.md"
sandbox = "workspace-write"

[profiles.analyst]
model = "o3"
model_instructions_file = "/path/to/analyst-role.md"
sandbox = "read-only"
```

Запуск:
```bash
codex exec -p backend_developer --json --ephemeral "Task prompt"
```

### Инъекция роли

Поскольку нет `--append-system-prompt`, инъекция роли возможна двумя путями:

1. **Через `model_instructions_file`** — создать отдельный файл для каждой роли, включающий системный промпт + роль. Затем использовать профиль или `-c` override.

2. **Через user prompt** — передать инструкцию «Возьми на себя роль из файла: ...» в промпте задачи. Модель прочитает файл роли через tool `read`. Аналог нашему подходу с Pi, но через user prompt вместо system prompt.

```bash
# Подход через user prompt
codex exec --json --ephemeral --ignore-user-config \
  -c model_instructions_file='"/home/dp/.codex/no-base-instructions.md"' \
  "Возьми на себя роль из файла: docs/agents/roles/team/backend_developer_levsha.ru.md. Выполни задачу: todo/TASK-xxx.todo.md"
```

### Оценка: ⚠️ Частичная поддержка

Профили (`-p`) позволяют создать пресеты для разных ролей. Однако нет прямого механизма инъекции роли через system prompt (нет append). Роль инжектируется через user prompt — модель должна «догадаться» загрузить файл роли через `read`. Сравнение: Pi имеет `--append-system-prompt` — прямую инъекцию в system.

---

## Критерий 3. Скиллы

### Возможности

Codex имеет **встроенную систему скиллов** через механизм `SKILL.md`:

| Механизм | Описание |
|----------|----------|
| Системные скиллы | `~/.codex/skills/.system/` — встроенные: `imagegen`, `openai-docs`, `plugin-creator`, `skill-creator`, `skill-installer` |
| Пользовательские скиллы | `~/.codex/skills/` — кастомные скиллы (каталоги с `SKILL.md`) |
| Плагины | Скиллы из плагинов (`~/.codex/plugins/`) — например, GitHub-скиллы |
| Проектные скиллы | Не обнаружено автоматического сканирования проектной директории `docs/agents/skills/` |

### Механика (из `codex debug prompt-input`)

В системный промпт (developer message) добавляется `<skills_instructions>` — список доступных скиллов с `name`, `description` и `file` path. Модель загружает `SKILL.md` по требованию через tool `read`.

Формат описания скилла в промпте:
```
### Available skills
- imagegen: Generate or edit raster images... (file: /home/dp/.codex/skills/.system/imagegen/SKILL.md)
- openai-docs: Use when the user asks how to build with OpenAI products... (file: /home/dp/.codex/skills/.system/openai-docs/SKILL.md)
```

### Разные скиллы разным ролям

Прямого механизма назначения скиллов конкретной роли **нет**. Все скиллы из `~/.codex/skills/` доступны глобально. Однако:

1. **Можно создать изолированную конфигурацию** — использовать `--ignore-user-config` и `-c` для указания кастомной директории скиллов.
2. **Подход с user prompt** — в инструкции роли указать, какие скиллы использовать.
3. **Создать отдельные профили** с разными `CODEX_HOME` или `model_instructions_file`, но это громоздко.

### Оценка: ⚠️ Частичная поддержка

Codex имеет встроенную систему скиллов с auto-discovery из `~/.codex/skills/`. Но нет механизма:
- явного указания скиллов через CLI (аналог `--skill` у Pi)
- назначения разных скиллов разным ролям
- автосканирования проектной директории `docs/agents/skills/`

Все скиллы глобальны для пользователя. Управление доступностью — только через переструктурирование каталогов.

---

## Критерий 4. AGENTS.md (контекстные файлы)

### Возможности

| Механизм | Описание |
|----------|----------|
| Автообнаружение `AGENTS.md` | ✅ Codex автоматически загружает `AGENTS.md` из корня проекта и подставляет его содержимое в `role: user` сообщение |
| `--ignore-rules` | Не загружать `.rules` файлы (exec policy), но `AGENTS.md` загружается |
| `--ignore-user-config` | Не загружать `~/.codex/config.toml`, но `AGENTS.md` проекта загружается |
| `.codex/` директория | Проектная конфигурация (config.toml, rules/) — альтернатива глобальной |
| `codex.md` | Альтернативный формат инструкций проекта |

### Порядок загрузки (из `codex debug prompt-input`)

Из дампа `prompt-input` видно, что Codex формирует промпт из нескольких блоков:

1. **`role: developer`** — системные инструкции:
   - `<permissions instructions>` — sandbox, escalation, approved rules
   - `<apps_instructions>` — коннекторы (GitHub)
   - `<skills_instructions>` — доступные скиллы
   - `<plugins_instructions>` — доступные плагины

2. **`role: user`** — контекст:
   - `# AGENTS.md instructions for /path` — полное содержимое `AGENTS.md` проекта, обёрнутое в `<INSTRUCTIONS>`
   - `<environment_context>` — cwd, shell, дата, timezone

3. **`role: user`** — собственно промпт пользователя

### Пример

```bash
# С автообнаружением (по умолчанию) — codex найдёт AGENTS.md
codex exec --json --ephemeral "Выполни задачу"

# Без пользовательского конфига (но AGENTS.md загрузится)
codex exec --json --ephemeral --ignore-user-config "Выполни задачу"

# Без rules (exec policy rules)
codex exec --json --ephemeral --ignore-rules "Выполни задачу"
```

### Оценка: ✅ Полная поддержка

Codex автоматически обнаруживает и загружает `AGENTS.md` из корня проекта. Содержимое `AGENTS.md` подставляется как `role: user` сообщение. Это гарантирует, что модель получает контекст проекта без дополнительных настроек. Можно отключить через комбинацию флагов, но нет единого флага «не загружать AGENTS.md».

---

## Критерий 5. Стандартная папка `.agents/skills/`

### Автосканирование

Codex **не поддерживает** автосканирование `.agents/skills/` или `docs/agents/skills/` из коробки.

| Локация | Поддержка сканирования |
|---------|----------------------|
| `~/.codex/skills/` | ✅ Системные + пользовательские скиллы |
| `~/.codex/skills/.system/` | ✅ Встроенные скиллы |
| `.codex/skills/` (в проекте) | ❌ Не обнаружено автосканирования |
| `.agents/skills/` | ❌ Не поддерживается |
| `docs/agents/skills/` | ❌ Не поддерживается |

### Наша структура

Наши скиллы лежат в `docs/agents/skills/`. Codex не автосканирует эту директорию. Для загрузки скиллов нужно:

1. **Скопировать скиллы** в `~/.codex/skills/` — глобально для всех проектов.
2. **Создать символьную ссылку** `~/.codex/skills/project-name → docs/agents/skills/`.
3. **Подход с user prompt** — в инструкции роли/задачи указать путь к `SKILL.md`, модель прочитает через `read`.

### Оценка: ❌ Не поддерживается

Стандарт `.agents/skills/` не поддерживается. Проектная директория скиллов также не автосканируется. Требуется ручное размещение в `~/.codex/skills/` или инъекция через промпт.

---

## Критерий 6. Запуск как сабагент (JSON-режим)

### Возможности

| Опция | Поведение |
|-------|-----------|
| `codex exec` | Неинтерактивный режим — выполняет задачу и завершается |
| `--json` | Вывод событий как JSONL (JSON Lines) в stdout |
| `--ephemeral` | Не сохранять сессию на диск — изолированный запуск |
| `-o / --output-last-message <FILE>` | Записать последнее сообщение агента в файл |
| `--output-schema <FILE>` | JSON Schema для валидации финального ответа |
| `-c key=value` | Переопределение любого параметра config |
| `-p <profile>` | Выбор профиля конфигурации |
| `-s <sandbox>` | Режим sandbox: `read-only`, `workspace-write`, `danger-full-access` |
| `-C <DIR>` | Указание рабочего каталога |
| `--skip-git-repo-check` | Разрешить запуск вне git-репозитория |
| `--ignore-user-config` | Не загружать пользовательский config |
| `--ignore-rules` | Не загружать rules-файлы |
| stdin / аргумент | Промпт передаётся как аргумент или через stdin (pipe) |

### Формат JSONL-событий

```
{"type":"thread.started","thread_id":"uuid"}
{"type":"turn.started"}
{"type":"turn.completed",...}   // или {"type":"turn.failed","error":{...}}
```

В отличие от Pi, Codex имеет более простой набор событий: `thread.started`, `turn.started`, `turn.completed`, `turn.failed`, `error`. Нет детальных событий `message_start/update/end`, `tool_execution_start/update/end` как у Pi.

### Примеры запуска

```bash
# Базовый неинтерактивный запуск с JSONL-выводом
codex exec --json --ephemeral "Опиши архитектуру проекта"

# Через pipe
echo "Опиши архитектуру проекта" | codex exec --json --ephemeral -

# С записью финального ответа в файл
codex exec --json --ephemeral -o /tmp/result.md "Выполни задачу"

# Read-only sandbox (для аналитика/ревьювера)
codex exec --json --ephemeral -s read-only "Проведи ревью"

# С кастомной моделью
codex exec --json --ephemeral -c model='"o4-mini"' "Быстрый анализ"

# Без пользовательского конфига (изоляция)
codex exec --json --ephemeral --ignore-user-config \
  -c model_instructions_file='"/path/to/role-prompt.md"' \
  "Task prompt"

# С output-schema (структурированный ответ)
codex exec --json --ephemeral --output-schema schema.json "Task"
```

### Контроль таймаутов

Codex не имеет встроенных таймаутов для exec-режима. Внешний таймаут реализуется через wrapper-скрипт:

```bash
timeout 600 codex exec --json --ephemeral "Task" || echo "TIMEOUT"
```

Для полноценного контроля (stall detection, soft/hard timeout) требуется wrapper, аналогичный нашему `watch-subagent.sh` для Pi.

### Mermaid-диаграмма потока данных

```mermaid
sequenceDiagram
    participant Orchestrator
    participant wrapper.sh
    participant codex exec --json
    participant OpenAI API

    Orchestrator->>wrapper.sh: Запуск с таймаутом и ролью
    wrapper.sh->>codex exec --json: codex exec --json --ephemeral + config overrides
    codex exec --json->>codex exec --json: Загрузка AGENTS.md, skills, rules
    codex exec --json->>OpenAI API: API-запрос (Responses API)
    OpenAI API-->>codex exec --json: Streaming-ответ
    codex exec --json-->>wrapper.sh: JSONL-события (thread.started, turn.started, turn.completed/failed)
    wrapper.sh->>wrapper.sh: Фильтрация и обработка
    codex exec --json-->>wrapper.sh: thread.completed / turn.failed
    wrapper.sh-->>Orchestrator: Финальный результат (exit code)
```

### Оценка: ⚠️ Частичная поддержка

Codex имеет неинтерактивный режим с JSONL-выводом и ephemeral-изоляцией. Однако:
1. **Событийная модель беднее**, чем у Pi — нет детальных событий по tool execution.
2. **Нет встроенных таймаутов** — требуется внешний wrapper.
3. **Нет pipe-управления** (named pipes) для параллельного чтения/записи.
4. `--output-schema` — полезная фича для структурированного ответа, отсутствующая у Pi.
5. `-o` (output-last-message) — удобный механизм извлечения финального ответа.

---

## Критерий 7. Токены и стоимость

### Доступные метрики

Из `config.toml` (секция `[tui]`):

```toml
[tui]
status_line = ["model-with-reasoning", "git-branch", "context-used", "five-hour-limit",
               "weekly-limit", "context-window-size", "used-tokens",
               "total-input-tokens", "total-output-tokens", "context-remaining"]
```

| Метрика | Описание |
|---------|----------|
| `context-used` | Доля использованного контекста |
| `five-hour-limit` | Лимит на 5-часовое окно |
| `weekly-limit` | Недельный лимит |
| `used-tokens` | Общее число токенов |
| `total-input-tokens` | Входные токены |
| `total-output-tokens` | Выходные токены |
| `context-window-size` | Размер контекстного окна |
| `context-remaining` | Оставшийся контекст |

### Доступ через JSONL

В JSONL-потоке exec-режима метрики токенов **не наблюдаются** в событиях `thread.started` / `turn.started` / `turn.completed`. В TUI-режиме метрики отображаются в status line.

Это означает, что для программного извлечения телеметрии по токенам из exec-режима может потребоваться дополнительный парсинг или доступ к логам.

### Сравнение с Pi

| Аспект | Pi | Codex CLI |
|--------|-----|-----------|
| Токены в JSONL | ✅ `usage` в `message_end` / `agent_end` | ⚠️ Метрики в TUI status line, JSONL — не подтверждено |
| Стоимость | ✅ `cost` с разбивкой | ❌ Только лимиты (5-hour, weekly) |
| Детализация | По-витковая (per-turn) | Суммарная в TUI |

### Оценка: ⚠️ Частичная поддержка

Метрики токенов и лимитов доступны в TUI. Доступ через JSONL в exec-режиме не подтверждён — требуется дополнительное исследование или разбор логов. В отличие от Pi, нет детальной по-витковой телеметрии с разбивкой стоимости.

---

## Критерий 8. Free tier

### Codex CLI как продукт

Codex CLI — **open-source** (Apache-2.0) инструмент. Сам по себе бесплатный. Стоимость определяется провайдером LLM.

### OpenAI Free Tier

| Аспект | Описание |
|--------|----------|
| Codex через ChatGPT | Требует подписку ChatGPT Plus ($20/мес) или Pro ($200/мес) |
| API через `OPENAI_API_KEY` | Pay-as-you-go pricing (GPT-4.1, o3, o4-mini) |
| Модели с reasoning | o3, o4-mini — с настройкой `model_reasoning_effort` |
| Бесплатные модели | Нет бесплатных моделей через OpenAI API |

### Бесплатные опции

| Провайдер | Бесплатно |
|-----------|-----------|
| Ollama (через `--oss --local-provider ollama`) | ✅ Локальные модели |
| LM Studio (через `--oss --local-provider lmstudio`) | ✅ Локальные модели |
| Google Gemini API free tier | ❌ Требует BYOK через `--oss` |

### Наша конфигурация

Текущая конфигурация использует модель `gpt-5.5` через ChatGPT подписку (OAuth-авторизация через `codex login`).

### Оценка: ⚠️ Частичная поддержка

CLI-инструмент бесплатный (Apache-2.0), но для использования с моделями OpenAI требуется платная подписка ChatGPT ($20+/мес) или API-ключ с pay-as-you-go. Бесплатный запуск возможен только с локальными моделями через `--oss`.

---

## Критерий 9. Провайдеры и модели

### Поддерживаемые провайдеры

**Основной провайдер: OpenAI**

Codex CLI изначально работает с OpenAI Responses API через ChatGPT OAuth-авторизацию:

```bash
codex login   # OAuth через chatgpt.com
codex exec --json "prompt"  # Использует модель из config
```

**Open-source провайдеры (через `--oss`):**

```bash
# С выбором провайдера
codex exec --oss --local-provider ollama "prompt"
codex exec --oss --local-provider lmstudio "prompt"
```

**BYOK (Bring Your Own Key):**

Через `model_providers` в `config.toml`:

```toml
[model_providers.openai-api]
name = "OpenAI API"
base_url = "https://api.openai.com/v1/"
env_key = "OPENAIAPI_KEY"
wire_api = "responses"
```

Поддерживается подключение любого OpenAI-совместимого API через `base_url`.

### Переключение моделей

```bash
# CLI
codex exec -m o4-mini --json "prompt"
codex exec -m gpt-4.1 --json "prompt"
codex exec -m o3 --json "prompt"

# Config
model = "gpt-5.5"
model_reasoning_effort = "xhigh"

# Профиль
codex exec -p analyst --json "prompt"
```

### Текущие доступные модели (наша конфигурация)

| Модель | Провайдер | Примечание |
|--------|-----------|------------|
| gpt-5.5 | ChatGPT OAuth | Текущая по умолчанию |
| gpt-5.4 | ChatGPT OAuth | Рекомендуемая миграция |
| gpt-5.3-codex | ChatGPT OAuth | Кодекс-оптимизированная |
| o4-mini | ChatGPT OAuth / API | Быстрая, дешёвая |
| o3 | ChatGPT OAuth / API | С reasoning |

### Сравнение с Pi

| Аспект | Pi | Codex CLI |
|--------|-----|-----------|
| Провайдеры | 20+ (Anthropic, Google, OpenAI, DeepSeek, Groq, ...) | OpenAI + OSS (Ollama, LM Studio) + BYOK |
| Локальные модели | ✅ Ollama, LM Studio, vLLM | ✅ Ollama, LM Studio (`--oss`) |
| Переключение | `--provider` / `--model` / settings | `-m` / config / profile |
| Агрегаторы (OpenRouter) | ✅ | ❌ (только через BYOK base_url) |

### Оценка: ⚠️ Частичная поддержка

Codex CLI изначально привязан к OpenAI. Поддержка open-source провайдеров через `--oss` ограничена Ollama и LM Studio. BYOK позволяет подключить произвольный OpenAI-совместимый API, но нет встроенной поддержки 20+ провайдеров как у Pi.

---

## Критерий 10. Лицензия

### Информация

| Параметр | Значение |
|----------|----------|
| Пакет | `@openai/codex` |
| Версия | 0.129.0 (установлена), 0.130.0 (latest) |
| Лицензия | **Apache-2.0** |
| Репозиторий | https://github.com/openai/codex |
| Язык | Rust (ядро) + Node.js (дистрибуция) |

### Условия

Apache-2.0 разрешает:
- Коммерческое использование
- Модификацию
- Распространение
- Private use
- Патентное использование

Требования:
- Включить копию лицензии
- Указать изменения (при модификации)
- NOTICE file (если есть)

### Оценка: ✅ Open source, Apache-2.0

Apache-2.0 — пермиссивная лицензия, совместимая с MIT. Нет ограничений на коммерческое использование.

---

## Вердикт

### ⚠️ Частично подходит (Score: 6/10)

Codex CLI **частично подходит** для использования как сабагент с нашей системой ролей и скиллов. Он уже используется интерактивно, но его архитектура хуже адаптирована к запуску как сабагент, чем Pi.

### Сильные стороны

1. **Автообнаружение AGENTS.md** — модель автоматически получает контекст проекта
2. **Ephemeral-режим** (`--ephemeral`) — изолированный запуск без сохранения сессии
3. **Профили** (`-p`) — пресеты конфигурации для разных ролей
4. **JSONL-стриминг** (`--json`) — программный доступ к событиям
5. **Output schema** (`--output-schema`) — валидация ответа по JSON Schema
6. **Output last message** (`-o`) — извлечение финального ответа в файл
7. **Apache-2.0** — open source, пермиссивная лицензия
8. **Встроенные скиллы** — система auto-discovery для `~/.codex/skills/`
9. **OpenAI модели** — доступ к gpt-5.x, o3, o4-mini через ChatGPT подписку

### Ключевые ограничения (vs Pi)

1. **Нет `--append-system-prompt`** — нельзя дополнить системный промпт. Только полная замена через файл. Для инъекции роли — только через user prompt.
2. **Нет `--skill` CLI** — нельзя явно загрузить скилл. Все скиллы глобальны.
3. **Нет автосканирования проектных скиллов** — `.agents/skills/` и `docs/agents/skills/` не обнаруживаются.
4. **Бедная JSONL-телеметрия** — нет детальных событий по tool execution, по-витковых токенов.
5. **Нет встроенных таймаутов** — требуется внешний wrapper.
6. **Ограниченные провайдеры** — OpenAI + OSS + BYOK, нет 20+ провайдеров.
7. **Роль через user prompt** — менее надёжно, чем через system prompt (Pi).

### Рекомендации

1. **Использовать Codex как интерактивный агент** — его сильная сторона TUI-режим с approval, sandbox и rich status line.
2. **Pi — основной сабагент** — для программного запуска через `watch-subagent.sh` Pi значительно лучше адаптирован.
3. **Codex как backup-сабагент** — возможен через wrapper, но потребует компенсации отсутствующих фич (append, skill CLI, таймауты).

---

## Приложение А. Практические примеры запуска

### Запуск Codex как сабагент (аналитик, read-only)

```bash
codex exec --json --ephemeral -s read-only \
  -c model_instructions_file='"/home/dp/.codex/no-base-instructions.md"' \
  "Возьми на себя роль из файла: docs/agents/roles/team/system_analyst_sherlock.ru.md
   Выполни анализ архитектуры модуля Orchestrator."
```

### Запуск Codex как сабагент (бэкендер, workspace-write)

```bash
codex exec --json --ephemeral -s workspace-write \
  -o /tmp/codex-result.md \
  "Возьми на себя роль из файла: docs/agents/roles/team/backend_developer_levsha.ru.md
   Выполни задачу: todo/TASK-xxx.todo.md"
```

### Запуск через wrapper с таймаутом

```bash
timeout 600 codex exec --json --ephemeral -s workspace-write \
  --ignore-user-config \
  -c model_instructions_file='"/home/dp/.codex/no-base-instructions.md"' \
  "Task prompt" 2>/dev/null | jq -c 'select(.type == "turn.completed")'
```

### Запуск с профилем

```bash
codex exec --json --ephemeral -p backend_developer \
  -o /tmp/result.md "Выполни задачу"
```

---

## Приложение Б. Примеры конфигурации для разных ролей

### Конфигурация config.toml с профилями для ролей

```toml
# ~/.codex/config.toml

# Глобальные настройки
model = "gpt-5.5"
model_instructions_file = "/home/dp/.codex/no-base-instructions.md"

# Профиль: Аналитик (read-only)
[profiles.analyst]
model = "o4-mini"
sandbox = "read-only"
model_instructions_file = "/home/dp/.codex/roles/analyst-instructions.md"

# Профиль: Бэкендер (workspace-write)
[profiles.backend_developer]
model = "gpt-5.5"
sandbox = "workspace-write"
model_instructions_file = "/home/dp/.codex/roles/backend-instructions.md"

# Профиль: Ревьювер (read-only)
[profiles.reviewer]
model = "o3"
sandbox = "read-only"
model_instructions_file = "/home/dp/.codex/roles/reviewer-instructions.md"
```

### Содержимое role-instructions.md (Бэкендер)

```markdown
# Системный промпт для роли Бэкендер Левша

Следуй инструкциям из файла роли: docs/agents/roles/team/backend_developer_levsha.ru.md
Загрузи этот файл при старте.

Follow the user's instructions and visible project instructions. Keep responses concise and task-focused.
```

---

## Приложение В. Сравнительная таблица Codex CLI vs Pi

| Критерий | Pi | Codex CLI | Комментарий |
|----------|-----|-----------|-------------|
| Системный промпт | ✅ replace + append | ⚠️ replace only | Pi гибче |
| Роль | ✅ `--append-system-prompt` | ⚠️ user prompt / profile | Pi прямее |
| Скиллы | ✅ `--skill`, auto-scan | ⚠️ global only | Pi управляемее |
| AGENTS.md | ✅ auto + `--no-context-files` | ✅ auto | На равных |
| `.agents/skills/` | ✅ auto-scan | ❌ нет | Pi лучше |
| JSONL-режим | ✅ `--mode json`, rich events | ⚠️ `--json`, basic events | Pi детальнее |
| Токены | ✅ per-turn в JSONL | ⚠️ TUI only | Pi лучше |
| Free tier | ✅ MIT + free providers | ⚠️ Apache-2.0 + paid | Pi гибче |
| Провайдеры | ✅ 20+ | ⚠️ OpenAI + OSS + BYOK | Pi богаче |
| Лицензия | ✅ MIT | ✅ Apache-2.0 | На равных |

---

## Источники

1. `codex --help` — CLI-параметры (v0.129.0)
2. `codex exec --help` — неинтерактивный режим
3. `codex debug prompt-input` — рендер промпта, видимого модели (подтверждает структуру developer/user сообщений)
4. `~/.codex/config.toml` — реальная конфигурация пользователя
5. [Codex CLI — GitHub](https://github.com/openai/codex) — репозиторий и документация
