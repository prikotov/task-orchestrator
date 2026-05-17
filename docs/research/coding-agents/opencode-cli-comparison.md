# OpenCode CLI — Исследование для интеграции как сабагент

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-09
**Объект:** OpenCode v1.3.17 (`opencode`, Go/Bun, SST/opencode-ai)
**Задача:** [TASK-research-opencode-cli](../../../todo/done/TASK-research-opencode-cli.todo.md)

---

## Сводка

OpenCode — мощный CLI/TUI AI-coding-agent от SST (opencode-ai), написанный на TypeScript/Bun (сервер) + Go (CLI-runner). Версия 1.3.17, лицензия MIT. Поддерживает 75+ LLM-провайдеров через интеграцию с models.dev, MCP-серверы, кастомные агенты через `.opencode/agent/*.md`, скиллы из `.claude/skills/` и `.agents/skills/`, ACP-сервер для программного управления. JSON-режим (`opencode run --format json`) обеспечивает стриминг событий для интеграции как сабагент.

---

## Критерий 1. Системный промпт

### Возможности

| Механизм | Поведение |
|----------|-----------|
| Кастомный агент (`.opencode/agent/<name>.md`) | Файл Markdown с YAML frontmatter + body = полный системный промпт для агента. Автообнаружение при старте. |
| `opencode.json` → `agent.<name>.prompt` | Системный промпт агента через конфигурационный файл. Поддерживает `{file:path}` для включения содержимого файла. |
| `opencode.json` → `instructions` | Массив файлов/URL/Glob-паттернов дополнительных инструкций. Загружаются как контекстные файлы. Поддерживает `{file:path}`, `{env:VAR}`, glob-паттерны. |
| `opencode.json` → `agent.<name>.tools` | Управление доступными инструментами агента (read, bash, edit, write, skill, grep, glob, task и др.). |

### Конфигурация агента (опциональный YAML frontmatter)

```yaml
---
name: "backend-developer"
tools:
  read: true
  grep: true
  glob: true
  write: true
  edit: true
  bash: true
  skill: true
permission:
  - permission: "edit"
    action: "allow"
    pattern: "src/**"
---
Ты — бэкендер Левша...
```

### Пример: агент из файла роли

```markdown
<!-- .opencode/agent/backend-developer.md -->
---
name: "backend-developer"
tools:
  read: true
  grep: true
  glob: true
  write: true
  edit: true
  bash: true
  skill: true
---

Ты — экспертный AI-ассистент. Следуй инструкциям из AGENTS.md.
```

Запуск:
```bash
opencode run --agent backend-developer "Выполни задачу X"
```

### Пример: инструкции через конфигурационный файл

```jsonc
// opencode.json
{
  "instructions": [
    "docs/agents/roles/team/backend_developer_levsha.ru.md",
    "AGENTS.md"
  ],
  "agent": {
    "sherlock": {
      "prompt": "{file:docs/agents/roles/team/system_analyst_sherlock.ru.md}",
      "tools": {
        "read": true,
        "bash": true,
        "write": true,
        "edit": false
      }
    }
  }
}
```

### Оценка: ✅ Полная поддержка

OpenCode предоставляет несколько механизмов для управления системным промптом: кастомные агенты (`.opencode/agent/*.md`), конфигурация через `opencode.json` с поддержкой `{file:path}`, и массив `instructions` для дополнительных файлов инструкций. Полная замена системного промпта — через агента. Дополнение — через `instructions`.

---

## Критерий 2. Промпт агента / Роль

### Подход

OpenCode поддерживает два уровня инъекции контекста роли:

1. **Кастомный агент** — определяет полный системный промпт, набор инструментов и permissions. Создаётся через `opencode agent create` или вручную в `.opencode/agent/*.md`.
2. **Инструкции** — дополнительные файлы инструкций через `instructions` в `opencode.json`. Файлы загружаются как контекст и добавляются к системному промпту.

### Создание агента

```bash
# Интерактивное создание агента
opencode agent create

# Результат — файл .opencode/agent/<name>.md
```

### Запуск с конкретным агентом

```bash
# Через CLI-аргумент
opencode run --agent backend-developer "Выполни задачу X"

# Через JSON-режим
opencode run --format json --agent backend-developer "Выполни задачу X"
```

### Альтернативный подход: инструкции

```jsonc
// opencode.json — без создания агента
{
  "instructions": [
    "docs/agents/roles/team/backend_developer_levsha.ru.md"
  ]
}
```

### Сравнение с pi

| Параметр | Pi (`--append-system-prompt`) | OpenCode (`--agent` / `instructions`) |
|----------|------------------------------|---------------------------------------|
| Способ | CLI-аргумент + инструкция модели прочитать файл | Конфигурация агента или instructions-массив |
| Изоляция | Нет изоляции агентов | Полная изоляция: каждый агент — свои tools, permissions |
| CLI-переключение | Один CLI-аргумент | `--agent <name>` CLI-аргумент |
| Контекст роли | Модель загружает файл on-demand | Файл загружается на старте, попадает в системный промпт |

### Оценка: ✅ Полная поддержка

OpenCode превосходит pi по управлению ролями: кастомные агенты — это полноценные конфигурации с собственными системными промптами, наборами инструментов и permissions. Поддерживается переключение агентов через CLI (`--agent <name>`).

---

## Критерий 3. Скиллы

### Возможности

| Механизм | Поведение |
|----------|-----------|
| Автосканирование `.claude/skills/` и `.agents/skills/` | OpenCode автоматически сканирует `skills/**/SKILL.md` внутри `.claude/` и `.agents/` директорий (глобальных и проектных). |
| Автосканирование `.opencode/skill/` и `.opencode/skills/` | Внутренние директории OpenCode с `SKILL.md`. |
| `opencode.json` → `skills.paths` | Явные пути к дополнительным директориям скиллов. |
| Переменные окружения | `OPENCODE_DISABLE_CLAUDE_CODE_SKILLS` — отключает сканирование `.claude/skills/`. `OPENCODE_DISABLE_EXTERNAL_SKILLS` — отключает все внешние скиллы. |
| Инструмент `skill` | Агент может загружать скилл по имени через tool `skill`. Доступность настраивается через `tools.skill: true/false` в конфигурации агента. |

### Сканирование скиллов (источник: binary analysis)

```
EXTERNAL_DIRS = [".claude", ".agents"]
EXTERNAL_SKILL_PATTERN = "skills/**/SKILL.md"
OPENCODE_SKILL_PATTERN = "{skill,skills}/**/SKILL.md"

Локации сканирования:
1. ~/.claude/skills/**/SKILL.md  (глобальные)
2. ~/.agents/skills/**/SKILL.md  (глобальные)
3. .claude/skills/**/SKILL.md    (проектные, включая ancestor dirs)
4. .agents/skills/**/SKILL.md    (проектные, включая ancestor dirs)
5. .opencode/{skill,skills}/**/SKILL.md  (конфигурационные)
6. cfg.skills.paths[]            (явные пути)
```

### Примеры конфигурации

```jsonc
// opencode.json
{
  "skills": {
    "paths": [
      "docs/agents/skills/agent-report",
      "docs/agents/skills/run-pi-subagent"
    ]
  }
}
```

### Разные скиллы разным ролям

Частичная поддержка. Скиллы загружаются глобально для сессии. Однако:
- Можно отключить инструмент `skill` для конкретного агента (`tools.skill: false`).
- Можно отключить внешние скиллы через env и подключить только через `skills.paths`.
- Нет прямого механизма «этому агенту — эти скиллы, другому — те» без отключения tool `skill`.

### Наша структура

Наши скиллы лежат в `docs/agents/skills/`. OpenCode не автосканирует эту директорию. Для загрузки:
1. Добавить путь в `skills.paths` в `opencode.json`
2. Или создать симлинк `.agents/skills/` → `docs/agents/skills/`

### Оценка: ⚠️ Частичная поддержка

Скиллы автосканируются из `.claude/skills/` и `.agents/skills/`, но нет механизма назначения разных наборов скиллов разным агентам (в отличие от pi с его `--skill` и `--no-skills`). Наша структура `docs/agents/skills/` требует явного указания через конфигурацию.

---

## Критерий 4. AGENTS.md (контекстные файлы)

### Возможности

| Механизм | Поведение |
|----------|-----------|
| Автообнаружение `AGENTS.md` | OpenCode автоматически загружает `AGENTS.md` из корня проекта и parent directories |
| Автообнаружение `CLAUDE.md` | Также загружает `CLAUDE.md` из проекта и `~/.claude/CLAUDE.md` глобально |
| `opencode.json` → `instructions` | Массив glob-паттернов/URL/путей для дополнительных инструкций |
| Команда `/init` | Автогенерация `AGENTS.md` на основе анализа кодовой базы |
| `OPENCODE_CONFIG_DIR`/AGENTS.md | Глобальный AGENTS.md в конфигурационной директории |

### Порядок загрузки контекстных файлов

1. `~/.opencode/AGENTS.md` или `{OPENCODE_CONFIG_DIR}/AGENTS.md` — глобальные инструкции
2. `~/.config/opencode/AGENTS.md` — глобальные (альтернативный путь)
3. `AGENTS.md` в проекте и ancestor directories
4. `CLAUDE.md` в проекте и ancestor directories
5. `~/.claude/CLAUDE.md` — глобальные Claude-инструкции
6. `instructions[]` из `opencode.json` — дополнительные файлы

### Нельзя отключить напрямую

В отличие от pi (`--no-context-files`), OpenCode не имеет CLI-флага для отключения загрузки контекстных файлов. Можно только убрать файлы физически.

### Примеры

```bash
# С автообнаружением (по умолчанию)
opencode run "Выполни задачу"

# Через инструкции — подключить дополнительный контекст
# (настройка в opencode.json)
```

### Оценка: ✅ Полная поддержка

OpenCode автоматически обнаруживает и загружает `AGENTS.md` из нашего проекта, что обеспечивает модели доступ к конвенциям. Дополнительно загружает `CLAUDE.md`, увеличивая совместимость. Команда `/init` помогает создать `AGENTS.md` для новых проектов.

---

## Критерий 5. Стандартная папка `.agents/skills/`

### Автосканирование

OpenCode **поддерживает** автосканирование `.agents/skills/` из коробки:

| Локация | Правила сканирования |
|---------|---------------------|
| `~/.agents/skills/` | `skills/**/SKILL.md` |
| `~/.claude/skills/` | `skills/**/SKILL.md` |
| `.agents/skills/` (в cwd и ancestor dirs) | `skills/**/SKILL.md` |
| `.claude/skills/` (в cwd и ancestor dirs) | `skills/**/SKILL.md` |
| `.opencode/{skill,skills}/` | `{skill,skills}/**/SKILL.md` |
| `skills.paths[]` из конфигурации | Явные пути |

### Наша структура

Наши скиллы лежат в `docs/agents/skills/`, а не в `.agents/skills/`. Для автосканирования нужно:
1. Создать симлинк: `.agents/skills/` → `docs/agents/skills/`
2. Или добавить путь в `skills.paths` в `opencode.json`

### Оценка: ✅ Поддерживается из коробки

Стандарт `.agents/skills/` поддерживается из коробки через `EXTERNAL_DIRS = [".claude", ".agents"]` с паттерном `skills/**/SKILL.md`. Наша структура требует симлинка или явного указания.

---

## Критерий 6. Запуск как сабагент (JSON-режим)

### Возможности

| Опция | Поведение |
|-------|-----------|
| `opencode run --format json` | JSON-стриминг событий в stdout |
| `opencode run --format json --attach <url>` | Подключение к удалённому/работающему серверу |
| `opencode serve` | Headless HTTP/WebSocket сервер для программного управления |
| `opencode acp` | ACP (Agent Client Protocol) сервер |
| `opencode run --dir <path>` | Запуск в указанной директории |
| `opencode run -f <file>` | Прикрепить файлы к сообщению |

### Формат JSON-событий

```json
{"type":"step_start","timestamp":...,"sessionID":"ses_...","part":{...,"type":"step-start"}}
{"type":"text","timestamp":...,"sessionID":"ses_...","part":{...,"type":"text","text":"..."}}
{"type":"step_finish","timestamp":...,"sessionID":"ses_...","part":{"type":"step-finish","reason":"stop","tokens":{"total":16472,"input":1,"output":6,"reasoning":1,"cache":{"write":0,"read":16464}},"cost":0}}
```

### Структура токенов в step_finish

```typescript
interface StepFinish {
  type: "step-finish";
  reason: string;          // "stop", "tool-calls", etc.
  tokens: {
    total: number;
    input: number;
    output: number;
    reasoning: number;
    cache: {
      write: number;
      read: number;
    };
  };
  cost: number;            // Стоимость в USD
}
```

### Пример запуска как сабагент

```bash
# Простой JSON-режим
opencode run --format json --model zai-coding-plan/glm-4.5-flash "Опиши архитектуру проекта"

# С конкретным агентом
opencode run --format json --agent backend-developer "Выполни задачу"

# С указанием директории и модели
opencode run --format json --dir /path/to/project --model anthropic/claude-sonnet-4 "Prompt"

# Headless-сервер для программного управления
opencode serve --port 4096
# Затем подключение:
opencode run --format json --attach http://localhost:4096 "Prompt"
```

### Ограничения по сравнению с pi

1. **Нет ephemeral-режима** (`--no-session` в pi). OpenCode всегда создаёт сессию в SQLite-БД.
2. **Нет явного timeout-контроля** через CLI. Нужен внешний wrapper (timeout, watch-subagent.sh).
3. **Формат событий беднее** чем pi — нет `tool_execution_start/update/end`, есть только `step_start`, `text`, `step_finish`.
4. **Нет pipe-ввода** (`<<< "prompt"`). Prompt передаётся как positional argument.

### Оценка: ⚠️ Частичная поддержка

JSON-режим работает и обеспечивает структурированный вывод с метриками токенов и стоимости. Однако формат беднее, чем pi: нет событий tool-вызовов, нет ephemeral-режима, нет таймаутов. Для интеграции как сабагент потребуется wrapper-скрипт, аналогичный `watch-subagent.sh`.

---

## Критерий 7. Токены и стоимость

### Доступные метрики

В каждом событии `step_finish` доступен объект `tokens`:

```json
{
  "tokens": {
    "total": 16472,
    "input": 1,
    "output": 6,
    "reasoning": 1,
    "cache": {
      "write": 0,
      "read": 16464
    }
  },
  "cost": 0
}
```

### CLI: команда stats

```bash
# Статистика за 7 дней
opencode stats --days 7 --models 5

# Вывод:
# OVERVIEW: Sessions, Messages, Days
# COST & TOKENS: Total Cost, Avg Cost/Day, Input, Output, Cache Read, Cache Write
```

### CLI: команда export

```bash
# Экспорт сессии в JSON
opencode export <sessionID>
```

### Хранение

Данные хранятся в SQLite-БД (`~/.local/share/opencode/opencode.db`). Команда `opencode stats` агрегирует данные по сессиям, дням, моделям, инструментам.

### Оценка: ✅ Полная поддержка

Полная телеметрия по токенам (input, output, reasoning, cache read/write) и стоимости доступна через JSON-события в реальном времени. Агрегированная статистика — через `opencode stats`. Данные персистентны в SQLite.

---

## Критерий 8. Free tier

### OpenCode как продукт

OpenCode — **open-source** (MIT) CLI-инструмент, сам по себе полностью бесплатный. Стоимость определяется провайдером LLM.

### Бесплатные модели (OpenCode Zen)

OpenCode предоставляет собственные бесплатные модели через провайдер `opencode` (OpenCode Zen):

| Модель | Провайдер | Стоимость |
|--------|-----------|-----------|
| `opencode/big-pickle` | OpenCode Zen (Anthropic-based) | Бесплатно |
| `opencode/gpt-5-nano` | OpenCode Zen (OpenAI-based) | Бесплатно |
| `opencode/minimax-m2.5-free` | OpenCode Zen | Бесплатно |
| `opencode/nemotron-3-super-free` | OpenCode Zen | Бесплатно |
| `opencode/qwen3.6-plus-free` | OpenCode Zen | Бесплатно |

### Ограничения free tier

- Модели OpenCode Zen имеют ограниченный контекст и могут иметь rate limits
- Качество бесплатных моделей может быть ниже premium (Claude, GPT-5)

### Бесплатные внешние провайдеры

| Провайдер | Бесплатные возможности |
|-----------|----------------------|
| Google Gemini API | Free tier: Gemini 2.5 Flash — 15 RPM |
| Ollama / LM Studio | Полностью бесплатно при локальных моделях |
| Cloudflare Workers AI | Бесплатный tier |

### Наша конфигурация

Текущая конфигурация использует провайдера `zai-coding-plan` (Z.AI Coding Plan) с моделью по умолчанию.

### Оценка: ✅ Бесплатный инструмент, 5 встроенных бесплатных моделей

OpenCode MIT-лицензирован и предоставляет 5 бесплатных моделей из коробки (через OpenCode Zen). Для продвинутых задач необходим платный провайдер или локальные модели.

---

## Критерий 9. Провайдеры и модели

### Поддерживаемые провайдеры

OpenCode использует интеграцию с [models.dev](https://models.dev) для каталога провайдеров. Поддержка 75+ провайдеров через встроенные SDK:

**API-ключи (встроенные провайдеры):**

| Провайдер | Env Variable | API |
|-----------|-------------|-----|
| Anthropic | `ANTHROPIC_API_KEY` | Messages API |
| OpenAI | `OPENAI_API_KEY` | Chat Completions / Responses |
| Google Gemini | `GOOGLE_GENERATIVE_AI_API_KEY` | Generative AI |
| Google Vertex AI | `GOOGLE_VERTEX_API_KEY` | Enterprise Google |
| Azure OpenAI | `AZURE_API_KEY` | Enterprise OpenAI |
| Amazon Bedrock | `AWS_ACCESS_KEY_ID` + `AWS_SECRET_ACCESS_KEY` | Enterprise (Claude, GPT через AWS) |
| OpenRouter | `OPENROUTER_API_KEY` | Агрегатор провайдеров |
| Mistral | Встроенная поддержка | Mistral серии |
| AI Gateway | `AI_GATEWAY_API_KEY` | Маршрутизация |

**OpenCode Zen (собственный):**

| Модель | Контекст | Max-out | Cost |
|--------|----------|---------|------|
| big-pickle | 200K | 128K | $0 |
| gpt-5-nano | — | — | $0 |
| minimax-m2.5-free | — | — | $0 |
| nemotron-3-super-free | — | — | $0 |
| qwen3.6-plus-free | — | — | $0 |

**Текущие доступные модели (наша конфигурация — Z.AI Coding Plan):**

| Провайдер | Модель | Контекст | Max-out |
|-----------|--------|----------|---------|
| zai-coding-plan | glm-4.5 — glm-5.1 | — | — |
| opencode | big-pickle, gpt-5-nano, *-free | — | — |

### BYOK (Bring Your Own Key)

Да, полная поддержка. API-ключи передаются через:
- Environment variables (рекомендуемый способ)
- `opencode providers login` — OAuth для подписочных сервисов
- `opencode.json` → `provider.<name>.apiKey` (поддерживает `{env:VAR}`)
- CLI: `opencode providers list` для проверки

### Переключение моделей

```bash
# CLI
opencode run --model anthropic/claude-sonnet-4 "Prompt"
opencode run --model openai/gpt-5 "Prompt"
opencode run --model zai-coding-plan/glm-5.1 "Prompt"

# С reasoning variant
opencode run --model anthropic/claude-sonnet-4 --variant high "Prompt"
```

### Локальные модели

OpenCode поддерживает Ollama, LM Studio и другие OpenAI-совместимые API через:
1. Конфигурацию кастомного провайдера в `opencode.json`
2. MCP-серверы для расширенной интеграции
3. Подключение через AI Gateway

### Оценка: ✅ Поддержка 75+ провайдеров, BYOK, OpenCode Zen

OpenCode поддерживает более 75 провайдеров через models.dev. Есть собственные бесплатные модели через OpenCode Zen. Полный BYOK через environment variables. Локальные модели подключаются через кастомные провайдеры.

---

## Критерий 10. Лицензия

### Информация

| Параметр | Значение |
|----------|----------|
| Пакет | `opencode` (npm/binary) |
| Организация | SST (opencode-ai / anomalyco) |
| Лицензия | **MIT** |
| Репозиторий | https://github.com/sst/opencode |
| Язык | TypeScript (Bun) + Go (CLI runner) |

### Условия

MIT-лицензия разрешает:
- Коммерческое использование
- Модификацию
- Распространение
- Private use

### Оценка: ✅ Open source, MIT — максимальная свобода

---

## Вердикт

### ⚠️ Частично подходит (Score: 7/10)

OpenCode CLI **частично подходит** для использования как сабагент с нашей системой ролей и скиллов. Имеет ряд серьёзных преимуществ перед pi, но также и ограничения в JSON-режиме интеграции.

**Сильные стороны:**
1. Полноценная система кастомных агентов (`.opencode/agent/*.md`) с изоляцией tools и permissions
2. Конфигурация `instructions` для инъекции произвольных файлов инструкций
3. 75+ провайдеров через models.dev + 5 бесплатных моделей через OpenCode Zen
4. MIT-лицензия
5. Автообнаружение AGENTS.md и CLAUDE.md
6. Автосканирование `.agents/skills/` и `.claude/skills/`
7. Полная телеметрия токенов и стоимости
8. ACP (Agent Client Protocol) сервер для программного управления
9. MCP (Model Context Protocol) интеграция

**Ограничения (по сравнению с pi):**
1. JSON-режим беднее: нет событий tool-вызовов (только `step_start`, `text`, `step_finish`)
2. Нет ephemeral-режима (`--no-session`) — сессия всегда сохраняется в SQLite
3. Нет встроенного контроля таймаутов через CLI
4. Нет pipe-ввода (`<<<`) — prompt передаётся только как positional argument
5. Нет механизма назначения разных скиллов разным агентам (в отличие от `--skill`/`--no-skills` в pi)
6. Нет CLI-аргумента для дополнения системного промпта (`--append-system-prompt`)
7. Нет CLI-аргумента для прямой передачи системного промпта (`--system-prompt`) — только через конфигурацию агента

**Потенциал:**
OpenCode превосходит pi в управлении агентами (кастомные агенты с permissions), в количестве провайдеров и бесплатных моделях. Однако для интеграции как сабагент через JSON-режим он пока уступает pi из-за бедного формата событий и отсутствия ephemeral-режима. Если SST добавит расширенный JSON-формат (tool events, session events) и ephemeral-режим, OpenCode станет равным или превосходящим pi.

---

## Приложение А. Практические примеры запуска

### Запуск с кастомным агентом

```bash
# Создать агента
cat > .opencode/agent/backend-developer.md << 'EOF'
---
name: "backend-developer"
tools:
  read: true
  grep: true
  glob: true
  write: true
  edit: true
  bash: true
  skill: true
---

Ты — бэкендер Левша. Следуй инструкциям из AGENTS.md.
EOF

# Запустить
opencode run --agent backend-developer "Выполни задачу: todo/TASK-feat-example.todo.md"
```

### Запуск в JSON-режиме

```bash
opencode run --format json --agent backend-developer "Проверь тесты" 2>/dev/null | \
  python3 -c "
import sys, json
for line in sys.stdin:
    obj = json.loads(line.strip())
    if obj['type'] == 'step_finish':
        tokens = obj['part']['tokens']
        print(f'Tokens: {tokens[\"total\"]} (in: {tokens[\"input\"]}, out: {tokens[\"output\"]})')
        print(f'Cost: \${obj[\"part\"][\"cost\"]}')
"
```

### Запуск через ACP-сервер

```bash
# Запустить headless-сервер
opencode serve --port 4096 &

# Подключиться к серверу
opencode run --format json --attach http://localhost:4096 "Prompt"
```

---

## Приложение Б. Примеры конфигурации для разных ролей

### Конфигурация для Аналитика (только чтение)

```markdown
<!-- .opencode/agent/analyst.md -->
---
name: "analyst"
tools:
  read: true
  grep: true
  glob: true
  bash: true
  write: false
  edit: false
  skill: false
  task: false
---

Ты — Аналитик Шерлок. Превращаешь бизнес-требования в технические постановки.
Следуй инструкциям из AGENTS.md.
```

### Конфигурация для Ревьювера (только чтение)

```markdown
<!-- .opencode/agent/reviewer.md -->
---
name: "reviewer"
tools:
  read: true
  grep: true
  glob: true
  bash: true
  write: false
  edit: false
  skill: false
  task: false
---

Ты — Ревьювер Пуаро. Проверяешь код на соответствие стандартам.
Следуй инструкциям из AGENTS.md.
```

### Конфигурация для Бэкендера (полный набор)

```markdown
<!-- .opencode/agent/backend-dev.md -->
---
name: "backend-dev"
tools:
  read: true
  grep: true
  glob: true
  write: true
  edit: true
  bash: true
  skill: true
  task: true
---

Ты — Бэкендер Левша. Реализуешь серверную логику (DDD, PHP).
Следуй инструкциям из AGENTS.md.
```

---

## Приложение В. Сравнение OpenCode vs Pi по ключевым критериям

| Критерий | Pi | OpenCode | Преимущество |
|----------|----|---------|--------------|
| Системный промпт | `--system-prompt`, `--append-system-prompt` | Агенты `.opencode/agent/*.md`, `instructions[]` | OpenCode (структурированнее) |
| Роль | `--append-system-prompt` + read file | `--agent <name>` | OpenCode (изоляция) |
| Скиллы | `--skill`, `--no-skills`, автосканирование | Автосканирование, `skills.paths` | Pi (гибче CLI-управление) |
| AGENTS.md | Авто + `--no-context-files` | Авто + `instructions[]` | Ничья |
| `.agents/skills/` | Автосканирование | Автосканирование | Ничья |
| JSON-режим | `--mode json`, `--no-session`, богатые события | `--format json`, нет ephemeral, бедные события | Pi (значительно) |
| Токены/стоимость | Полная телеметрия JSONL | Полная телеметрия JSON + stats CLI | Ничья |
| Free tier | MIT, стоимость зависит от LLM | MIT, 5 бесплатных моделей Zen | OpenCode |
| Провайдеры | 20+ провайдеров | 75+ провайдеров + Zen | OpenCode (значительно) |
| Лицензия | MIT | MIT | Ничья |

---

## Источники

1. `opencode --help`, `opencode run --help` — CLI-параметры (v1.3.17)
2. `opencode debug config`, `opencode debug skill` — внутренняя конфигурация
3. Binary analysis (`strings $(which opencode)`) — исходный код для сканирования скиллов, конфигурации агентов
4. [OpenCode — GitHub](https://github.com/sst/opencode) — лицензия MIT, репозиторий
5. [models.dev](https://models.dev) — каталог провайдеров LLM
