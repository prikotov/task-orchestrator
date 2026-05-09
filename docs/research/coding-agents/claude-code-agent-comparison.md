# Claude Code — Исследование для интеграции как сабагент

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-09
**Объект:** Claude Code v2.1.138 (Anthropic, проприетарный CLI-агент, TypeScript/Node.js)
**Задача:** [TASK-research-claude-code-agent](../../../todo/TASK-research-claude-code-agent.todo.md)

---

## Сводка

Claude Code — официальный CLI-агент от Anthropic для agentic-кодинга в терминале, IDE и desktop app. Проприетарный продукт с закрытым исходным кодом. Работает исключительно с моделями Anthropic (Claude Sonnet, Opus, Haiku). Предыдущее исследование в [`claude-code-comparison.md`](../framework-comparisons/claude-code-comparison.md) оценивало Claude Code в контексте оркестрации — данный отчёт фокусируется на критериях кастомизации и интеграции как сабагент с нашей системой ролей и скиллов.

---

## Критерий 1. Системный промпт

### Возможности

| Опция | Поведение |
|-------|-----------|
| `--system-prompt <text>` | **Полная замена** дефолтного системного промпта. Контекстные файлы (CLAUDE.md) и скиллы всё равно добавляются поверх. |
| `--append-system-prompt <text>` | **Дополнение** к текущему системному промпту. |
| `CLAUDE.md` | Иерархические контекстные файлы — добавляются поверх системного промпта всегда. |
| `.claude/rules/*.md` | Контекстные правила с ленивой загрузкой. |

### Примеры CLI

```bash
# Полная замена системного промпта
claude -p --system-prompt "Ты — экспертный AI-ассистент." "Опиши архитектуру проекта"

# Дополнение к дефолтному промпту
claude -p --append-system-prompt "Следуй стандартам PSR-12." "Отрефактори код"

# Наш подход — замена + дополнение для роли (аналог watch-subagent.sh для pi)
claude -p --output-format json \
  --system-prompt "Ты — AI-ассистент, работающий как сабагент." \
  --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/backend_developer_levsha.ru.md" \
  "Выполни задачу: todo/TASK-feat-example.todo.md"
```

### Сравнение с Pi

| Аспект | Pi | Claude Code |
|--------|-----|-------------|
| Полная замена | `--system-prompt` | `--system-prompt` ✅ Идентично |
| Дополнение | `--append-system-prompt` | `--append-system-prompt` ✅ Идентично |
| Файловый промпт проекта | `.pi/SYSTEM.md` | `CLAUDE.md` |
| Файловое дополнение проекта | `.pi/APPEND_SYSTEM.md` | Нет прямого аналога (`.claude/rules/` частично) |
| Глобальный файловый промпт | `~/.pi/agent/SYSTEM.md` | `~/.claude/CLAUDE.md` |

### Оценка: ✅ Полная поддержка

Claude Code предоставляет те же базовые механизмы, что и Pi: полную замену через `--system-prompt` и дополнение через `--append-system-prompt`. API идентичен Pi, что упрощает миграцию `watch-subagent.sh`.

---

## Критерий 2. Промпт агента / Роль

### Подход

Как и Pi, Claude Code не имеет встроенного механизма «ролей». Роль инжектируется через `--append-system-prompt`, что указывает модели прочитать файл роли через tool `Read`:

```bash
claude -p --output-format json \
  --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/backend_developer_levsha.ru.md" \
  "Выполни задачу"
```

Модель самостоятельно прочитает файл роли при первом обращении через инструмент `Read`.

### Альтернативный подход: `--agents`

Claude Code предоставляет флаг `--agents <json>` для определения кастомных агентов:

```bash
claude -p --output-format json \
  --agents '{"reviewer": {"description": "Reviews code", "prompt": "You are a code reviewer"}}' \
  "Review the code"
```

Однако `--agents` создаёт sub-agent'ов (spawn через Agent tool), а не инжектирует роль в основную сессию. Для нашего сценария (одна роль на сессию) подход через `--append-system-prompt` предпочтительнее.

### Альтернативный подход: `.claude/agents/`

Claude Code поддерживает определение кастомных агентов через `.md` файлы с YAML frontmatter:

```markdown
---
name: backend-developer
description: Backend developer role
tools: Read, Write, Edit, Bash, Glob, Grep
model: sonnet
---

Возьми на себя роль из файла: docs/agents/roles/team/backend_developer_levsha.ru.md
```

Файл помещается в `.claude/agents/backend-developer.md` и доступен через `/agents` или Agent tool. Но это требует создания отдельного `.md` файла для каждой роли.

### Оценка: ✅ Полная поддержка (через --append-system-prompt)

Подход идентичен Pi. Наш текущий паттерн `--append-system-prompt "Возьми на себя роль из файла: ..."` работает без изменений.

---

## Критерий 3. Скиллы

### Возможности

| Источник | Поведение |
|----------|-----------|
| `~/.claude/skills/` | Глобальные скиллы (автообнаружение) |
| `.claude/skills/` | Проектные скиллы (автообнаружение) |
| Plugin skills | Скиллы из плагинов |
| Settings `skills[]` | Явные пути через конфигурацию |

### Формат SKILL.md

Claude Code использует [Agent Skills standard](https://agentskills.io/specification) — YAML frontmatter в SKILL.md:

```yaml
---
name: agent-report
description: Сохранение отчёта агента в файл
user_invocable: true
---

# Отчёт агента
...
```

Поддерживаемые поля frontmatter: `name`, `description`, `user_invocable`, `tools`, `model`, `context`, `license`, `metadata`.

### Механика

1. При старте Claude Code сканирует локации скиллов и извлекает `name` + `description`.
2. В системный промпт добавляются описания доступных скиллов.
3. При совпадении задачи модель загружает полный `SKILL.md` через `Read`.
4. Скиллы доступны через инструмент `Skill` или как slash-команды `/skill:name`.

### Примеры

```bash
# Скиллы загружаются автоматически из ~/.claude/skills/ и .claude/skills/
claude -p "Generate a report"

# Нет CLI-флага для явной загрузки конкретного скилла (в отличие от pi --skill)
# Явная загрузка возможна через:
# 1. Создание symlink в .claude/skills/
# 2. Plugin с skills/
# 3. Настройка путей в settings.json
```

### Разные скиллы разным ролям

⚠️ Частично. В отличие от Pi (`--skill` + `--no-skills`), Claude Code не имеет CLI-флагов для фильтрации скиллов на уровне запуска. Возможные подходы:

1. **Разные `.claude/skills/` через `--add-dir`** — но это не изолирует скиллы.
2. **Sub-agent'ы через `--agents`** — каждый sub-agent может иметь свой набор инструментов, но не отдельный набор скиллов.
3. **Plugin skills** — плагины могут включать skills, но управление на уровне CLI отсутствует.
4. **`--append-system-prompt`** — инструктировать модель использовать только определённые скиллы. Хрупкий подход.

### Сравнение с Pi

| Аспект | Pi | Claude Code |
|--------|-----|-------------|
| CLI `--skill <path>` | ✅ Repeatable | ❌ Нет |
| CLI `--no-skills` | ✅ Отключает автосканирование | ❌ Нет |
| Автообнаружение | ✅ `.agents/skills/`, `.pi/skills/` | ✅ `.claude/skills/`, `~/.claude/skills/` |
| Формат SKILL.md | ✅ Agent Skills standard | ✅ Agent Skills standard |
| Plugin skills | ❌ Нет плагинов | ✅ Через плагины |

### Оценка: ⚠️ Частичная поддержка

Скиллы поддерживаются через Agent Skills standard, формат SKILL.md идентичен. Но **отсутствуют CLI-флаги для управления скиллами** (`--skill`, `--no-skills`), что затрудняет назначение разных скиллов разным ролям через `watch-subagent.sh`.

---

## Критерий 4. AGENTS.md (контекстные файлы)

### Возможности

| Опция | Поведение |
|-------|-----------|
| Автообнаружение CLAUDE.md | ✅ Из коробки: global → project → directory-level |
| Автообнаружение AGENTS.md | ❌ **Не поддерживается** — только CLAUDE.md |
| `.claude/rules/*.md` | ✅ Контекстные правила с ленивой загрузкой |
| Авто-память | ✅ Между сессиями (learnings, build commands) |

### Иерархия загрузки CLAUDE.md

```
~/.claude/CLAUDE.md               → Global (все проекты)
project/CLAUDE.md                  → Project-level
project/src/CLAUDE.md              → Directory-level (ленивая загрузка)
project/src/auth/CLAUDE.md         → Subdirectory-level
project/.claude/rules/*.md         → Контекстные правила
```

### Проблема для нашей интеграции

Наш проект использует `AGENTS.md` в корне (`/home/dp/MyProjects/task-orchestrator/AGENTS.md`). Claude Code **не обнаруживает** `AGENTS.md` — только `CLAUDE.md`. Это означает, что проектные инструкции из `AGENTS.md` не попадут в контекст модели автоматически.

### Workaround

1. **Создать `CLAUDE.md`** в корне проекта, который ссылается на `AGENTS.md`:
   ```markdown
   Смотри инструкции проекта в AGENTS.md
   ```
   Модель прочитает `AGENTS.md` через инструмент `Read` при необходимости.

2. **Передать содержимое через `--append-system-prompt`**:
   ```bash
   claude -p --append-system-prompt "$(cat AGENTS.md)"
   ```
   Минус: раздувает системный промпт, тратит токены на каждый запрос.

3. **Создать символическую ссылку**: `ln -s AGENTS.md CLAUDE.md` — Claude Code загрузит CLAUDE.md → содержит содержимое AGENTS.md. Минус: поддержка двух файлов.

### Сравнение с Pi

| Аспект | Pi | Claude Code |
|--------|-----|-------------|
| AGENTS.md | ✅ Автообнаружение | ❌ Не поддерживается |
| CLAUDE.md | ✅ Автообнаружение | ✅ Автообнаружение |
| Отключение загрузки | `--no-context-files` | Нет прямого флага |

### Оценка: ⚠️ Частичная поддержка

Claude Code автоматически обнаруживает CLAUDE.md, но **не поддерживает AGENTS.md**. Для интеграции с нашим проектом требуется workaround (символическая ссылка, `--append-system-prompt` или отдельный CLAUDE.md).

---

## Критерий 5. Стандартная папка `.agents/skills/`

### Автосканирование

| Локация | Claude Code | Pi |
|---------|-------------|-----|
| `~/.claude/skills/` | ✅ Автосканирование | — |
| `.claude/skills/` (в cwd и ancestor dirs) | ✅ Автосканирование | — |
| `~/.agents/skills/` | ❌ Не сканируется | ✅ Директории с SKILL.md |
| `.agents/skills/` (в cwd и ancestor dirs) | ❌ Не сканируется | ✅ Директории с SKILL.md |
| `~/.pi/agent/skills/` | — | ✅ Автосканирование |
| `.pi/skills/` | — | ✅ Автосканирование |
| Settings `skills[]` | ❌ Нет (через plugin) | ✅ Glob-паттерны |
| CLI `--skill <path>` | ❌ Нет | ✅ Repeatable |
| Plugin skills | ✅ Через `.claude-plugin/` | ❌ Нет |

### Наша структура

Наши скиллы лежат в `docs/agents/skills/`, а не в `.claude/skills/`. Claude Code не автосканирует `docs/agents/skills/`. Для загрузки требуется:

1. **Символическая ссылка** — создать symlink в `.claude/skills/` на каждый скилл из `docs/agents/skills/`.
2. **Plugin** — создать плагин с нужными skills.
3. **Копирование** — нецелесообразно, дублирование.

### Оценка: ❌ Не поддерживается

Claude Code **не поддерживает** стандартную директорию `.agents/skills/`. Использует собственную структуру `.claude/skills/`. Наша директория `docs/agents/skills/` требует ручной настройки (symlink, plugin или копирование). В отличие от Pi, нет CLI-флага для явной загрузки скилла по пути.

---

## Критерий 6. Запуск как сабагент (JSON-режим)

### Возможности

| Опция | Поведение |
|-------|-----------|
| `--print` / `-p` | Non-interactive режим: stdin → agent loop → stdout |
| `--output-format json` | Единоразовый JSON-результат (весь ответ одним объектом) |
| `--output-format stream-json` | Real-time стриминг событий (JSONL) |
| `--output-format text` | Обычный текстовый вывод |
| `--max-turns <N>` | Лимит итераций agent loop |
| `--max-budget-usd <N>` | Лимит бюджета в USD (только print mode) |
| `--allowedTools <list>` | Ограничение доступных инструментов (whitelist) |
| `--tools <list>` | Явный список инструментов (строже, чем allowedTools) |
| `--permission-mode <mode>` | Режим разрешений: default, plan, acceptEdits, bypassPermissions, dontAsk |
| `--dangerously-skip-permissions` | Пропуск всех проверок разрешений |
| `--model <model>` | Выбор модели: sonnet, opus, или полное имя |
| `--fallback-model <model>` | Автоматический fallback при перегрузке |
| `--json-schema <schema>` | Валидация выхода по JSON Schema |
| `--resume` / `--continue` | Возобновление сессии |
| `--input-format stream-json` | Потоковый ввод через stdin |
| Exit codes | 0 = успех, 1 = ошибка, 2 = блокировка permission |

### Формат JSON-вывода

```json
{
    "type": "result",
    "subtype": "success",
    "is_error": false,
    "duration_ms": 15342,
    "duration_api_ms": 12100,
    "num_turns": 3,
    "result": "Текстовый результат агента...",
    "session_id": "uuid",
    "total_cost_usd": 0.0842,
    "usage": {
        "input_tokens": 24500,
        "cache_creation_input_tokens": 18000,
        "cache_read_input_tokens": 5000,
        "output_tokens": 3200,
        "server_tool_use": {
            "web_search_requests": 0,
            "web_fetch_requests": 0
        },
        "service_tier": "standard",
        "cache_creation": {
            "ephemeral_1h_input_tokens": 0,
            "ephemeral_5m_input_tokens": 18000
        }
    },
    "modelUsage": {
        "claude-sonnet-4-20250514": {
            "input_tokens": 24500,
            "output_tokens": 3200,
            "cache_creation_input_tokens": 18000,
            "cache_read_input_tokens": 5000
        }
    },
    "permission_denials": [],
    "uuid": "..."
}
```

### Формат stream-json

Real-time JSONL-стриминг событий (аналог `--mode json` в Pi). Поддерживает `--include-partial-messages` для получения partial content.

### Пример интеграции как сабагент

```bash
# Аналог нашего watch-subagent.sh для Pi
claude -p --output-format json \
  --system-prompt "scripts/subagent_system.txt" \
  --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/backend_developer_levsha.ru.md" \
  --max-turns 50 \
  --max-budget-usd 5.00 \
  --permission-mode acceptEdits \
  --tools "Read,Write,Edit,Bash,Glob,Grep" \
  <<< "Выполни задачу: todo/TASK-feat-example.todo.md"
```

### Управление таймаутами

⚠️ Claude Code **не имеет** встроенных CLI-флагов для таймаутов (в отличие от Pi). Доступные механизмы:

1. `--max-turns` — лимит итераций agent loop (косвенный таймаут)
2. `--max-budget-usd` — лимит бюджета (косвенный таймаут)
3. Внешний `timeout` — Unix-команда `timeout 600 claude -p ...`
4. `--fallback-model` — автоматический fallback при перегрузке основной модели

### Пример потока данных

```mermaid
sequenceDiagram
    participant Orchestrator
    participant wrapper.sh
    participant claude (--print --output-format json)
    participant Anthropic API

    Orchestrator->>wrapper.sh: Запуск с таймаутами и ролью
    wrapper.sh->>claude (--print --output-format json): claude -p --system-prompt + --append-system-prompt
    claude (--print --output-format json)->>claude (--print --output-format json): Загрузка CLAUDE.md, skills
    claude (--print --output-format json)->>Anthropic API: API-запрос
    Anthropic API-->>claude (--print --output-format json): Streaming-ответ
    Note over claude (--print --output-format json): Модель вызывает tools (Read, Bash, Edit, Write)
    claude (--print --output-format json)->>Anthropic API: Следующий API-запрос (tool results)
    Anthropic API-->>claude (--print --output-format json): Streaming-ответ
    claude (--print --output-format json)-->>wrapper.sh: JSON result (usage, cost, result)
    wrapper.sh->>wrapper.sh: Проверка exit code (0/1/2)
    wrapper.sh-->>Orchestrator: Финальный результат
```

### Сравнение с Pi

| Аспект | Pi | Claude Code |
|--------|-----|-------------|
| Non-interactive | `--print` / `-p` | `--print` / `-p` ✅ Идентично |
| JSONL-стриминг | `--mode json` (потоковый) | `--output-format stream-json` |
| JSON-результат | Из JSONL-событий | `--output-format json` (единый объект) |
| Ephemeral-режим | `--no-session` | По умолчанию (нет `--resume`/`--continue`) |
| Лимит итераций | `--max-turns` (через config) | `--max-turns` ✅ |
| Лимит бюджета | Нет | `--max-budget-usd` ✅ (только print) |
| Инструменты whitelist | `--tools` | `--tools` ✅ |
| JSON Schema валидация | Нет | `--json-schema` ✅ |
| RPC-режим | `--mode rpc` | Нет (но есть Agent SDK) |

### Оценка: ✅ Полная поддержка

Claude Code хорошо подходит для программного запуска через `--print`. JSON-вывод содержит полную телеметрию. `--max-budget-usd` и `--max-turns` обеспечивают guard rails. Дополнительные возможности: `--json-schema` (валидация выхода), `--fallback-model` (graceful degradation).

---

## Критерий 7. Токены и стоимость

### Доступные метрики

JSON-вывод (`--output-format json`) содержит полную телеметрию:

```typescript
interface Usage {
    input_tokens: number;                     // Входные токены
    output_tokens: number;                    // Выходные токены
    cache_creation_input_tokens: number;      // Токены записанные в кеш
    cache_read_input_tokens: number;          // Токены из кеша (чтение)
    server_tool_use: {
        web_search_requests: number;          // Количество WebSearch-запросов
        web_fetch_requests: number;           // Количество WebFetch-запросов
    };
    service_tier: string;                     // Уровень сервиса
    cache_creation: {
        ephemeral_1h_input_tokens: number;    // 1-часовой кеш
        ephemeral_5m_input_tokens: number;    // 5-минутный кеш
    };
}

interface Result {
    total_cost_usd: number;                   // Общая стоимость в USD
    duration_ms: number;                      // Длительность сессии
    duration_api_ms: number;                  // Длительность API-вызовов
    num_turns: number;                        // Количество итераций
    modelUsage: Record<string, Usage>;        // По-модельная разбивка
}
```

### Доступ через CLI

```bash
# Извлечь стоимость
claude -p --output-format json "prompt" 2>/dev/null | jq '.total_cost_usd'

# Извлечь usage
claude -p --output-format json "prompt" 2>/dev/null | jq '.usage'

# По-модельная разбивка
claude -p --output-format json "prompt" 2>/dev/null | jq '.modelUsage'
```

### В интерактивном режиме

Status line показывает: токены, стоимость, использование контекстного окна, текущую модель.

### Сравнение с Pi

| Аспект | Pi | Claude Code |
|--------|-----|-------------|
| Input/output токены | ✅ В JSONL | ✅ В JSON |
| Cache read/write | ✅ В JSONL | ✅ В JSON (детальнее: 1h/5m) |
| Стоимость | ✅ `cost` объект | ✅ `total_cost_usd` |
| По-модельная разбивка | ✅ В JSONL | ✅ `modelUsage` |
| Длительность | — | ✅ `duration_ms`, `duration_api_ms` |
| Количество итераций | ✅ По виткам | ✅ `num_turns` |

### Оценка: ✅ Полная поддержка

Полная телеметрия по токенам и стоимости. Детализация выше, чем у Pi: по-модельная разбивка, длительность API-вызовов, cache tiers (1h/5m).

---

## Критерий 8. Free tier

### Модели подписки

| План | Цена | Claude Code возможности |
|------|------|------------------------|
| Claude Pro | $20/мес | Ограниченное использование Claude Code (потребляет extra usage) |
| Claude Max | $100/мес | Значительно больше запросов |
| Claude Team | По тарифу | Корпоративное использование |
| Claude Enterprise | По тарифу | Enterprise-фичи, managed policies |
| API ключ | Pay-as-you-go | Полный контроль, биллинг по токенам |

### Бесплатный тариф

❌ **Бесплатного тарифа нет.** Claude Code требует:

1. **Подписку Claude** (Pro/Max/Team/Enterprise) — для OAuth-авторизации через `claude` CLI.
2. **Anthropic API ключ** — для pay-as-you-go использования.
3. **Amazon Bedrock / Google Vertex AI / Microsoft Azure** — для enterprise-пути.

### Лимиты при подписке

При использовании через подписку Claude (OAuth):
- Количество запросов ограничено fair use политикой
- Сложные задачи могут потреблять «extra usage» сверх базового тарифа
- Конкретные лимиты не публикуются (динамические, зависят от нагрузки)

При использовании через API ключ:
- Лимиты определяются Anthropic API rate limits
- Биллинг по фактическому потреблению токенов
- Полный контроль через `--max-budget-usd`

### Сравнение с Pi

| Аспект | Pi | Claude Code |
|--------|-----|-------------|
| Бесплатный инструмент | ✅ MIT, бесплатно | ❌ Требует подписку ($20+/мес) или API ключ |
| Бесплатные модели | ✅ Gemini free tier, Ollama | ❌ Нет |
| Минимальная стоимость | $0 (Ollama + Pi) | ~$20/мес (Pro) или API pay-as-you-go |

### Оценка: ❌ Нет бесплатного тарифа

Claude Code — платный продукт. Минимальная стоимость — подписка Claude Pro ($20/мес) или pay-as-you-go через API. В отличие от Pi (MIT, бесплатно) и Gemini CLI (60 RPM бесплатно), Claude Code не имеет бесплатного доступа.

---

## Критерий 9. Провайдеры и модели

### Поддерживаемые провайдеры

| Провайдер | Поддержка | Примечание |
|-----------|-----------|------------|
| Anthropic (direct) | ✅ | Claude серия: Sonnet 4, Opus 4, Haiku |
| Amazon Bedrock | ✅ | Enterprise-путь к Claude-моделям |
| Google Vertex AI | ✅ | Enterprise-путь к Claude-моделям |
| Microsoft Azure | ✅ | Enterprise-путь к Claude-моделям |
| OpenAI | ❌ | Не поддерживается |
| Google Gemini | ❌ | Не поддерживается |
| Mistral | ❌ | Не поддерживается |
| DeepSeek | ❌ | Не поддерживается |
| xAI (Grok) | ❌ | Не поддерживается |
| Ollama | ❌ | Не поддерживается |
| LM Studio | ❌ | Не поддерживается |
| vLLM | ❌ | Не поддерживается |
| OpenRouter | ❌ | Не поддерживается |
| Groq | ❌ | Не поддерживается |

### Доступные модели

| Модель | Контекст | Thinking | Стоимость (input/output per 1M tokens) |
|--------|----------|----------|---------------------------------------|
| claude-sonnet-4 | 200K | ✅ | $3 / $15 |
| claude-opus-4 | 200K | ✅ | $15 / $75 |
| claude-haiku | 200K | ❌ | $0.80 / $4 |
| claude-opus-4-5 | 1M | ✅ | TBD (новая модель) |

### BYOK (Bring Your Own Key)

⚠️ Частично. BYOK поддерживается **только для Anthropic API ключей**. Нельзя подключить API-ключ OpenAI, Google или другого провайдера.

```bash
# Через API ключ
export ANTHROPIC_API_KEY="sk-ant-..."
claude -p "prompt"

# Через кастомный base URL (для ZAI, Bedrock, Vertex)
export ANTHROPIC_BASE_URL="https://api.z.ai/api/anthropic"
export ANTHROPIC_AUTH_TOKEN="..."
claude -p "prompt"
```

### Переключение моделей

```bash
# CLI
claude -p --model sonnet "prompt"
claude -p --model opus "prompt"
claude -p --model claude-sonnet-4-20250514 "prompt"
claude -p --fallback-model sonnet --model opus "prompt"

# Settings
{
    "model": "claude-sonnet-4-20250514"
}
```

### Сравнение с Pi

| Аспект | Pi | Claude Code |
|--------|-----|-------------|
| Количество провайдеров | 20+ | 1 (Anthropic) |
| Локальные модели | ✅ Ollama, LM Studio, vLLM | ❌ |
| BYOK | ✅ Любой провайдер | ⚠️ Только Anthropic |
| Переключение провайдеров | ✅ CLI + settings | ⚠️ Только Anthropic модели |
| Enterprise | ✅ Bedrock, Vertex, Azure | ✅ Bedrock, Vertex, Azure |

### Оценка: ⚠️ Один провайдер, ограниченный BYOK

Claude Code работает **исключительно с моделями Anthropic**. Это архитектурное ограничение, а не конфигурационное. Enterprise-пути (Bedrock, Vertex, Azure) предоставляют доступ к тем же Claude-моделям, но через другие инфраструктуры. Нет поддержки локальных моделей или сторонних провайдеров.

---

## Критерий 10. Лицензия

### Информация

| Параметр | Значение |
|----------|----------|
| Продукт | Claude Code |
| Разработчик | Anthropic PBC |
| Лицензия | **Проприетарная** |
| Исходный код | Закрытый |
| Репозиторий | https://github.com/anthropics/claude-code (только README, examples, plugins) |
| Условия использования | [Commercial Terms of Service](https://www.anthropic.com/legal/commercial-terms) |

### LICENSE.md (из репозитория)

```
© Anthropic PBC. All rights reserved.
Use is subject to Anthropic's Commercial Terms of Service.
```

### Условия

Проприетарная лицензия означает:
- ❌ Нельзя модифицировать исходный код (закрыт)
- ❌ Нельзя распространять
- ❌ Нельзя форкать
- ✅ Коммерческое использование (в рамках ToS)
- ❌ Нет гарантии стабильности API/CLI

### Сравнение с Pi

| Аспект | Pi | Claude Code |
|--------|-----|-------------|
| Лицензия | MIT | Проприетарная |
| Исходный код | Открытый | Закрытый |
| Модифицируемость | ✅ Полная | ❌ Нет |
| Форк | ✅ | ❌ |
| Vendor lock-in | ❌ | ✅ |

### Оценка: ❌ Проприетарная лицензия

Claude Code — проприетарный продукт с закрытым исходным кодом. В отличие от Pi (MIT), Codex CLI (Apache-2.0) и Gemini CLI (Apache-2.0), Claude Code не даёт свободы модификации и создаёт vendor lock-in на Anthropic.

---

## Вердикт

### ⚠️ Частично подходит (Score: 7/10)

Claude Code **частично подходит** для использования как сабагент с нашей системой ролей и скиллов. CLI API для системного промпта (`--system-prompt` + `--append-system-prompt`) идентичен Pi, что упрощает адаптацию `watch-subagent.sh`. JSON-режим (`--print --output-format json`) предоставляет полную телеметрию. Но есть существенные ограничения.

### Сильные стороны

1. **Идентичный API системного промпта** — `--system-prompt` и `--append-system-prompt` работают так же, как в Pi. Миграция `watch-subagent.sh` минимальна.
2. **Богатый JSON-вывод** — полная телеметрия (токены, стоимость, по-модельная разбивка, длительность, количество итераций).
3. **Guard rails** — `--max-budget-usd`, `--max-turns`, `--tools`, `--permission-mode` для контроля выполнения.
4. **JSON Schema валидация** — `--json-schema` позволяет валидировать выход агента.
5. **Agent Skills standard** — поддержка формата SKILL.md, совместимого с Pi.
6. **Fallback модель** — `--fallback-model` для graceful degradation.
7. **Hooks system** — 20+ lifecycle events для monitoring и control.

### Существенные ограничения

1. **Только Anthropic модели** — нет поддержки OpenAI, Google, Ollama, LM Studio или других провайдеров. Architectural lock-in.
2. **Нет AGENTS.md** — Claude Code обнаруживает только CLAUDE.md, не AGENTS.md. Требуется workaround (symlink, `--append-system-prompt`, или отдельный CLAUDE.md).
3. **Нет `.agents/skills/`** — собственная структура `.claude/skills/` вместо стандартной. Нет CLI-флагов `--skill` / `--no-skills` для управления скиллами.
4. **Проприетарная лицензия** — закрытый исходный код, vendor lock-in на Anthropic.
5. **Нет бесплатного тарифа** — минимальная стоимость $20/мес (Pro) или API pay-as-you-go.
6. **Нет CLI для управления скиллами** — нельзя передать `--skill <path>` при запуске, только через настройки или плагины.

### Рекомендация

Claude Code может использоваться как **дополнительный** сабагент (не замена Pi) для задач, где требуется именно Claude-модель. Интеграция через `watch-subagent.sh` или аналог реализуема, но требует учёта ограничений:

- Создать `CLAUDE.md` → symlink на `AGENTS.md` или содержимое
- Создать symlink'и в `.claude/skills/` на `docs/agents/skills/*/SKILL.md`
- Адаптировать `watch-subagent.sh` для `claude -p --output-format json`

---

## Приложение А. Практические примеры запуска

### Запуск Бэкендера Левши (адаптация watch-subagent.sh)

```bash
# Адаптация watch-subagent.sh для Claude Code
timeout 600 claude -p --output-format json \
  --system-prompt "Ты — AI-ассистент, работающий как сабагент в проекте task-orchestrator." \
  --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/backend_developer_levsha.ru.md" \
  --append-system-prompt "Следуй инструкциям из AGENTS.md в корне проекта." \
  --max-turns 50 \
  --max-budget-usd 5.00 \
  --permission-mode acceptEdits \
  --tools "Read,Write,Edit,Bash,Glob,Grep" \
  <<< "Выполни задачу: todo/TASK-feat-example.todo.md."
```

### Запуск Аналитика (только чтение)

```bash
timeout 300 claude -p --output-format json \
  --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/system_analyst_sherlock.ru.md" \
  --permission-mode plan \
  --max-turns 30 \
  --max-budget-usd 2.00 \
  <<< "Проанализируй требования для новую фичу"
```

### Запуск Ревьювера (read-only + tools filter)

```bash
timeout 300 claude -p --output-format json \
  --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/code_reviewer_backend_puaro.ru.md" \
  --tools "Read,Bash,Glob,Grep" \
  --permission-mode plan \
  --max-turns 30 \
  <<< "Проведи ревью PR #123"
```

### Запуск с JSON Schema валидацией

```bash
claude -p --output-format json \
  --json-schema '{"type":"object","properties":{"verdict":{"type":"string"},"score":{"type":"number"}},"required":["verdict"]}' \
  --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/qa_backend_house.ru.md" \
  <<< "Проверь тесты и верни вердикт"
```

---

## Приложение Б. Сравнение с Pi по ключевым критериям

| # | Критерий | Pi | Claude Code | Разница |
|---|----------|-----|-------------|---------|
| 1 | Системный промпт | ✅ `--system-prompt` + `--append-system-prompt` | ✅ `--system-prompt` + `--append-system-prompt` | **Идентично** |
| 2 | Роль | ✅ Через `--append-system-prompt` | ✅ Через `--append-system-prompt` | **Идентично** |
| 3 | Скиллы | ✅ `--skill` + `--no-skills` + auto | ⚠️ Auto только, нет CLI | **Pi лучше**: CLI-управление |
| 4 | AGENTS.md | ✅ Автообнаружение | ❌ Только CLAUDE.md | **Pi лучше**: поддерживает оба |
| 5 | `.agents/skills/` | ✅ Из коробки | ❌ `.claude/skills/` | **Pi лучше**: стандарт |
| 6 | JSON-режим | ✅ `--mode json` JSONL | ✅ `--print --output-format json` | **Паритет**: разные форматы |
| 7 | Токены/стоимость | ✅ В JSONL | ✅ В JSON (детальнее) | **Claude Code лучше**: modelUsage |
| 8 | Free tier | ✅ MIT, бесплатно | ❌ $20+/мес | **Pi лучше**: бесплатно |
| 9 | Провайдеры | ✅ 20+ провайдеров | ⚠️ Только Anthropic | **Pi лучше**: мультипровайдер |
| 10 | Лицензия | ✅ MIT | ❌ Проприетарная | **Pi лучше**: open source |

---

## Источники

1. [Claude Code — официальная документация](https://code.claude.com/docs/en/overview) — обзор, установка, использование
2. [Claude Code — CLI Reference](https://code.claude.com/docs/en/cli-reference) — флаги CLI, headless mode, exit codes
3. [Claude Code — GitHub](https://github.com/anthropics/claude-code) — README, examples, plugins, LICENSE
4. [Claude Code — Hooks Reference](https://code.claude.com/docs/en/hooks) — hooks system, 20+ lifecycle events
5. `claude --help` — CLI-параметры (v2.0.55 / v2.1.138)
