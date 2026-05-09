# Kilo Code CLI — Исследование для интеграции как сабагент

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-10
**Объект:** Kilo Code CLI v7.1.21 (`@kilocode/cli`, TypeScript/Bun, форк OpenCode)
**Задача:** [TASK-research-kilocode-cli](../../../todo/TASK-research-kilocode-cli.todo.md)

---

## Сводка

Kilo Code CLI — коммерческий форк OpenCode CLI от Kilo-Org, позиционирующий себя как «AI coding agent built for the terminal» с поддержкой 500+ моделей. Пакет `@kilocode/cli` v7.1.21, лицензия MIT. Предлагает свой облачный бэкенд (kilo.ai) для доступа к моделям через кредиты или BYOK, а также TUI-интерфейс и `kilo run` для одноразовых задач.

---

## Критерий 1. Системный промпт

### Возможности

| Механизм | Поведение |
|----------|-----------|
| Агенты `.kilo/agent/*.md` | Каждый агент — Markdown с YAML frontmatter, содержит `systemPrompt` (тело файла). Полная замена системного промпта для конкретного агента. |
| `--agent <name>` | Выбор агента при запуске `kilo run`. Позволяет переключать системный промпт. |
| `instructions` в `kilo.json` | Glob-паттерны для файлов инструкций — дополнение к промпту. |
| `.kilo/instructions.md` | Файл инструкций проекта — дополнение к промпту. |
| `AGENTS.md` | Автообнаружение, загружается как часть инструкций. |
| `kilo agent create` | Команда для интерактивного создания нового агента с системным промптом (модель сама генерирует). |

### Примеры CLI

```bash
# Запуск с конкретным агентом (его системный промпт из .kilo/agent/coder.md)
kilo run --agent coder "Реализуй задачу X"

# Запуск с моделью и агентом
kilo run --agent code -m anthropic/claude-sonnet-4 "Опиши архитектуру"
```

### Ограничения

- **Нет CLI-флага для прямой передачи системного промпта** (как `--system-prompt` в pi). Системный промпт определяется исключительно через файл агента `.kilo/agent/*.md`.
- **Нет `--append-system-prompt`** или аналогичного флага. Дополнения возможны только через `instructions` в конфигурации.
- **Нет `KILO_CONFIG_CONTENT`-механизма для роли** — можно передать весь конфиг через env, но это тяжеловесно.

### Сравнение с Pi

| Аспект | Pi | Kilo Code |
|--------|----|-----------|
| Полная замена промпта | `--system-prompt` | Файл агента `.kilo/agent/*.md` |
| Дополнение к промпту | `--append-system-prompt` | `instructions[]` в kilo.json |
| CLI-инъекция | Да (флаг) | Нет (только файл/конфиг) |

### Оценка: ⚠️ Частичная поддержка

Полная замена системного промпта доступна через файл агента, но нет CLI-флагов для инъекции в командной строке. Для каждого нового промпта/роли нужно создавать файл агента или использовать `instructions` в конфигурации.

---

## Критерий 2. Промпт агента / Роль

### Подход

Kilo Code реализует встроенную систему **агентов** (mode-based personas):

| Концепт | Описание |
|---------|----------|
| `.kilo/agent/*.md` | Markdown-файлы с YAML frontmatter. Каждый файл — отдельный агент со своим системным промптом, моделью, permissions. |
| `--agent <name>` | Выбор агента при запуске. |
| `kilo agent create` | Интерактивное создание агента (модель генерирует системный промпт на основе описания). |
| `kilo agent list` | Список доступных агентов. |

### Структура файла агента

```yaml
---
description: Backend developer for PHP/DDD projects
mode: subagent          # primary | subagent | all
model: anthropic/claude-sonnet-4  # опциональный override модели
steps: 25               # max agentic итераций
hidden: false           # скрыть из @ меню
color: "#FF5733"        # цвет в TUI
permission:             # права агента
  bash: allow
  edit:
    "src/**": allow
    "*": ask
---
You are an expert backend developer specializing in PHP 8.4, DDD, and Symfony 8.0.
Follow Clean Architecture principles...
```

### Инъекция роли (наш use case)

Для интеграции с нашей системой ролей нужно:

1. **Создать файл агента** `.kilo/agent/<role-name>.md` для каждой роли:
```markdown
---
description: Backend Developer Levsha
mode: subagent
---
Возьми на себя роль из файла: docs/agents/roles/team/backend_developer_levsha.ru.md
```

2. **Запуск через `--agent`**:
```bash
kilo run --agent backend-levsha "Выполни задачу: todo/TASK-xxx.todo.md"
```

### Оценка: ⚠️ Частичная поддержка (через файлы агентов)

Kilo Code имеет встроенную систему агентов, но инъекция роли требует предварительного создания файла агента на диске. Нельзя передать роль «на лету» через CLI-флаг. Для нашего workflow нужно создать файлы агентов для каждой роли.

---

## Критерий 3. Скиллы

### Возможности

| Механизм | Описание |
|----------|----------|
| `.kilo/skill/*/SKILL.md` | Локация скиллов в проекте (или глобально `~/.config/kilo/`) |
| `.kilo/skills/*/SKILL.md` | Альтернативная локация |
| `.claude/skills/*/SKILL.md` | Поддержка Claude Code-совместимой структуры |
| `.agents/skills/*/SKILL.md` | Поддержка стандарта Agent Skills |
| `skills.paths[]` в kilo.json | Явные пути к каталогам скиллов |
| `skills.urls[]` в kilo.json | URL для удалённых скиллов |
| Env `KILO_DISABLE_EXTERNAL_SKILLS` | Отключить внешние скиллы |
| Env `KILO_DISABLE_CLAUDE_CODE_SKILLS` | Отключить Claude Code-скиллы |

### Механика

1. При старте kilo сканирует все локации скиллов.
2. Каждый `SKILL.md` парсится — извлекаются `name` и `description` из frontmatter.
3. Скиллы привязаны к **глобальной сессии**, а не к конкретному агенту.
4. Нет CLI-флага для явного указания скилла при запуске (как `--skill` в pi).

### Назначение разных скиллов разным ролям

**Не поддерживается.** Все скиллы загружаются глобально для сессии. Нельзя дать разным агентам разные наборы скиллов. Можно только отключить скиллы целиком через env.

### Оценка: ⚠️ Частичная поддержка

Скиллы поддерживаются и совместимы со стандартом Agent Skills (`SKILL.md` с frontmatter). Однако нет CLI-управления набором скиллов и нет назначения скиллов конкретным агентам.

---

## Критерий 4. AGENTS.md (контекстные файлы)

### Возможности

| Механизм | Поведение |
|----------|-----------|
| `AGENTS.md` | Автообнаружение в корне проекта. Загружается как инструкции. |
| `.kilo/instructions.md` | Файл инструкций проекта. |
| `instructions[]` в `kilo.json` | Glob-паттерны для файлов инструкций. |
| `CLAUDE.md` | Поддерживается как альтернатива. |
| Env `KILO_CONFIG_DIR` | Можно указать кастомную директорию конфигурации. |

### Порядок загрузки

1. Глобальный `~/.config/kilo/kilo.json` → `instructions`
2. Глобальный `~/.config/kilo/AGENTS.md`
3. Проектный `AGENTS.md` (в cwd и parent directories)
4. `.kilo/instructions.md`
5. Glob-паттерны из `instructions[]`

### Можно ли отключить?

Нет прямого флага отключения (как `--no-context-files` в pi). Однако можно не создавать `AGENTS.md` и не указывать `instructions[]`.

### Оценка: ✅ Полная поддержка

AGENTS.md автоматически обнаруживается и загружается. Поддерживаются дополнительные источники инструкций через `instructions[]` и `.kilo/instructions.md`.

---

## Критерий 5. Стандартная папка `.agents/skills/`

### Автосканирование

Kilo Code **поддерживает** автосканирование `.agents/skills/` из коробки:

| Локация | Правила сканирования |
|---------|---------------------|
| `~/.claude/skills/*/SKILL.md` | Глобальные Claude-скиллы |
| `~/.agents/skills/*/SKILL.md` | Глобальные Agent Skills |
| `.claude/skills/*/SKILL.md` | Проектные Claude-скиллы (cwd + ancestors) |
| `.agents/skills/*/SKILL.md` | Проектные Agent Skills (cwd + ancestors) |
| `.kilo/skill/*/SKILL.md` | Проектные Kilo-скиллы |
| `.kilo/skills/*/SKILL.md` | Альтернативная локация |
| `skills.paths[]` в kilo.json | Явные пути |

### Наша структура (`docs/agents/skills/`)

Наша структура `docs/agents/skills/` **не автосканируется**. Для загрузки нужно добавить в `kilo.json`:

```jsonc
{
  "skills": {
    "paths": ["docs/agents/skills/"]
  }
}
```

### Оценка: ✅ Поддерживается

Стандарт `.agents/skills/` поддерживается из коробки. Наша нестандартная структура `docs/agents/skills/` требует явного указания через `skills.paths`.

---

## Критерий 6. Запуск как сабагент (JSON-режим)

### Возможности

| Опция | Поведение |
|-------|-----------|
| `kilo run "prompt"` | Non-interactive — один запрос и выход (аналог `--print` в pi) |
| `--format json` | JSON-режим: события выводятся как JSON-объекты (newline-delimited) |
| `--auto` | Auto-approve all permissions (для autonomous/pipeline использования) |
| `kilo serve` | Headless сервер (HTTP API для глубокой интеграции) |
| `kilo acp` | Agent Client Protocol сервер |
| `kilo export <sessionId>` | Экспорт сессии в JSON |
| `kilo attach <url>` | Подключение к удалённому kilo-серверу |
| `--continue` | Продолжить последнюю сессию |
| `--session <id>` | Продолжить конкретную сессию |
| `--fork` | Форк сессии при продолжении |
| `--dir <path>` | Указать рабочий каталог |

### Формат JSON-вывода (`--format json`)

```bash
kilo run --format json --auto "Опиши архитектуру проекта" 2>/dev/null
```

Kilo Code использует streaming JSON events (newline-delimited JSON). Структура событий включает информацию о message, tool calls, usage.

### Таймауты

Нет встроенных CLI-флагов для таймаутов. Таймауты нужно контролировать внешним скриптом (аналог `watch-subagent.sh`):

```bash
timeout 600 kilo run --format json --auto --agent backend-levsha "Выполни задачу"
```

### Пример запуска как сабагент

```bash
# Наш подход (через обёрточный скрипт)
timeout 600 kilo run --format json --auto \
  --agent backend-levsha \
  -m zai/glm-5.1 \
  "Выполни задачу: todo/TASK-xxx.todo.md" 2>/dev/null
```

### Сравнение с Pi

| Аспект | Pi | Kilo Code |
|--------|----|-----------| 
| JSON-режим | `--mode json` (JSONL) | `--format json` |
| Ephemeral | `--no-session` | По умолчанию (kilo run) |
| RPC-режим | `--mode rpc` | `kilo serve`, `kilo acp` |
| Таймауты | Нет встроенных | Нет встроенных |
| Auto-approve | Нет | `--auto` |

### Оценка: ⚠️ Частичная поддержка

`kilo run --format json --auto` обеспечивает non-interactive запуск с JSON-выводом. Однако JSON-формат менее документирован, чем у pi (JSONL-спецификация не опубликована). Нет `--no-session` (но `kilo run` по умолчанию ephemeral). Для production-использования нужно изучить структуру JSON events.

---

## Критерий 7. Токены и стоимость

### Доступные метрики

Kilo Code отслеживает usage на уровне API-вызовов:

```typescript
// Внутренняя структура usage
{
  inputTokens: number;
  outputTokens: number;
  totalTokens: number;
  reasoningTokens: number;
  cachedInputTokens: number;
}
```

### CLI-доступ

```bash
# Статистика по сессиям
kilo stats              # Все время
kilo stats --days 7     # Последние 7 дней
kilo stats --models     # С разбивкой по моделям
kilo stats --tools 10   # Топ-10 инструментов
kilo stats --project .  # Текущий проект
```

### В JSON-режиме

Метрики usage доступны в JSON events (внутри message events), аналогично pi. Точная структура событий требует изучения на практике.

### Оценка: ✅ Полная поддержка

CLI-команда `kilo stats` даёт доступ к истории использования. Usage-метрики доступны в JSON events при `--format json`. Поддержка `--models` флага даёт по-модельную разбивку.

---

## Критерий 8. Free tier

### Kilo Code как продукт

Kilo Code CLI — **MIT-лицензия**, сам по себе бесплатный. Но доступ к моделям через Kilo Cloud требует регистрации:

| Опция | Описание |
|-------|----------|
| Kilo Credits | Регистрация бесплатна, кредиты для доступа к моделям. «Sign up for free to continue and explore 500 other models. Takes 2 minutes, no credit card required.» |
| BYOK | Bring Your Own Key — подключение API-ключей провайдеров. Бесплатно с локальными моделями. |
| `kilo-auto/free` | Бесплатная модель по умолчанию для анонимных пользователей. |
| `kilo-auto/balanced` | Модель по умолчанию для зарегистрированных пользователей. |

### Бесплатные провайдеры

| Провайдер | Бесплатные возможности |
|-----------|----------------------|
| Ollama / LM Studio | Полностью бесплатно при локальных моделях |
| Google Gemini API | Free tier: Gemini 2.5 Flash — 15 RPM |
| OpenRouter | Некоторые модели бесплатны |

### Наша конфигурация

Используется провайдер `openai-compatible` с ZAI API (GLM-5.1). Стоимость определяется тарифами ZAI.

### Оценка: ✅ Бесплатный инструмент, стоимость зависит от провайдера

MIT-лицензия, BYOK, поддержка локальных моделей. Kilo Credits предоставляют бесплатный доступ к части моделей.

---

## Критерий 9. Провайдеры и модели

### Поддерживаемые провайдеры

**AI SDK (встроенные):**
- Anthropic (Claude)
- OpenAI (GPT)
- OpenAI-compatible (любой OpenAI-совместимый API)
- OpenRouter (агрегатор)

**Kilo Cloud (через kilo.ai API):**
- 500+ моделей через Kilo Credits или BYOK
- Включает: Google Gemini, DeepSeek, Mistral, Alibaba/Qwen, xAI/Grok, Fireworks, Cloudflare, и многие другие

**Конфигурируемые через `kilo.json`:**

```jsonc
{
  "provider": {
    "anthropic": {
      "options": {
        "apiKey": "sk-...",
        "baseURL": "https://custom.endpoint/v1"
      }
    },
    "openai-compatible": {
      "options": {
        "apiKey": "...",
        "baseURL": "https://api.z.ai/api/coding/paas/v4"
      }
    }
  },
  "disabled_providers": ["openai"],
  "enabled_providers": ["anthropic", "openai-compatible"]
}
```

### BYOK (Bring Your Own Key)

Да, полная поддержка. API-ключи передаются через:
- `provider.*.options.apiKey` в `kilo.json`
- `kilo auth login` (OAuth для Anthropic/OpenAI)
- Environment variables

### Локальные модели

Через `openai-compatible` провайдер:
- Ollama: `baseURL: "http://localhost:11434/v1"`
- LM Studio: `baseURL: "http://localhost:1234/v1"`
- vLLM: любой OpenAI-совместимый endpoint

### Переключение

```bash
# CLI
kilo run -m anthropic/claude-sonnet-4 "prompt"
kilo run -m zai/glm-5.1 "prompt"

# Конфигурация
{
  "model": "zai/glm-5.1"
}
```

### Сравнение с Pi

| Аспект | Pi | Kilo Code |
|--------|----|-----------| 
| Встроенные провайдеры | 20+ (явные) | 4 (AI SDK) + Kilo Cloud |
| BYOK | Да | Да |
| Локальные модели | models.json | openai-compatible provider |
| Переключение | `--provider`/`--model` | `-m provider/model` |

### Оценка: ⚠️ Частичная поддержка

Kilo Code поддерживает ключевых провайдеров через AI SDK (Anthropic, OpenAI, OpenRouter) + OpenAI-compatible для остальных. Но менее гибко, чем Pi с 20+ явными провайдерами. Kilo Cloud частично компенсирует это через 500+ моделей.

---

## Критерий 10. Лицензия

### Информация

| Параметр | Значение |
|----------|----------|
| Пакет | `@kilocode/cli` |
| Организация | Kilo-Org |
| Лицензия | **MIT** |
| Репозиторий | https://github.com/Kilo-Org/kilocode |
| Форк от | OpenCode CLI (opencode-ai) |

### Условия

MIT-лицензия разрешает:
- Коммерческое использование
- Модификацию
- Распространение
- Private use

### Оценка: ✅ Open source, MIT — максимальная свобода

---

## Вердикт

### ⚠️ Частично подходит (Score: 6/10)

Kilo Code CLI **частично подходит** для использования как сабагент с нашей системой ролей и скиллов. Основные ограничения связаны с отсутствием CLI-инъекции системного промпта и ролей.

### Сильные стороны

1. **Встроенная система агентов** (`.kilo/agent/*.md`) — близка к нашей концепции ролей
2. **AGENTS.md** автоматически обнаруживается — модель получает контекст проекта
3. **Agent Skills standard** поддерживается (`.agents/skills/*/SKILL.md`)
4. **MIT-лицензия** — открытый исходный код
5. **`kilo run --format json --auto`** — non-interactive запуск с JSON-выводом
6. **500+ моделей** через Kilo Cloud + BYOK
7. **ACP (Agent Client Protocol)** — для глубокой интеграции как сабагент

### Критические ограничения для нашего use case

1. **Нет CLI-флагов для системного промпта** — нельзя инъектировать роль «на лету» через `--system-prompt` / `--append-system-prompt`. Требуется создание файлов агентов на диске для каждой роли.
2. **Нет CLI-управления скиллами** — нет аналога `--skill` / `--no-skills`. Скиллы глобальны для сессии.
3. **Нет назначения скиллов агентам** — все скиллы доступны всем агентам. Нельзя ограничить набор скиллов для конкретной роли.
4. **JSON-формат менее документирован** — нет публичной спецификации JSONL-событий (в отличие от pi, где есть `docs/json.md`).
5. **Нет встроенных таймаутов** — нужен внешний `timeout` или wrapper-скрипт.
6. **Форк OpenCode** — развитие зависит от Kilo-Org, могут быть divergence с upstream.

### Сравнительная таблица с Pi

| Аспект | Pi | Kilo Code | Вердикт |
|--------|----|-----------|---------|
| Системный промпт через CLI | ✅ `--system-prompt` + `--append` | ❌ Только файл агента | Pi лучше |
| Роль через CLI | ✅ `--append-system-prompt` | ⚠️ Только `--agent <file>` | Pi лучше |
| CLI-управление скиллами | ✅ `--skill` / `--no-skills` | ❌ Нет | Pi лучше |
| Скиллы для разных агентов | ✅ Разные наборы | ❌ Глобальные | Pi лучше |
| AGENTS.md | ✅ Автообнаружение | ✅ Автообнаружение | Равно |
| JSON-режим | ✅ JSONL-стриминг | ⚠️ JSON, слабо документирован | Pi лучше |
| Ephemeral-режим | ✅ `--no-session` | ✅ `kilo run` по умолчанию | Равно |
| Провайдеры | ✅ 20+ явных | ⚠️ 4 AI SDK + Kilo Cloud | Pi лучше |
| Токены/стоимость | ✅ В JSONL + footer | ✅ `kilo stats` | Равно |
| Лицензия | ✅ MIT | ✅ MIT | Равно |

### Рекомендация

Kilo Code CLI не рекомендуется как основной сабагент для нашей системы. Pi остаётся предпочтительным выбором благодаря CLI-флагам для инъекции ролей и управления скиллами. Kilo Code может быть рассмотрен как **альтернативный** сабагент для задач, где нужна специфическая модель из Kilo Cloud, или когда нужна ACP-интеграция.

---

## Приложение А. Практические примеры запуска

### Запуск Backend Developer (через файл агента)

```bash
# 1. Создать файл агента один раз
cat > .kilo/agent/backend-levsha.md <<'EOF'
---
description: Backend Developer Levsha for PHP/DDD
mode: subagent
---
Возьми на себя роль из файла: docs/agents/roles/team/backend_developer_levsha.ru.md
Следуй инструкциям из AGENTS.md.
EOF

# 2. Запуск
kilo run --format json --auto \
  --agent backend-levsha \
  -m zai/glm-5.1 \
  "Выполни задачу: todo/TASK-xxx.todo.md"
```

### Запуск в JSON-режиме (одноразовый)

```bash
kilo run --format json --auto \
  -m zai/glm-5.1 \
  "Проанализируй архитектуру проекта" 2>/dev/null | jq -c '.'
```

### Запуск через ACP (для глубокой интеграции)

```bash
# Запустить ACP-сервер
kilo acp --port 4096 &

# Подключиться к нему
kilo attach http://localhost:4096
```

### Статистика использования

```bash
kilo stats --days 7 --models --project .
```

---

## Приложение Б. Структура конфигурации Kilo Code

### Конфигурационные файлы

```
.kilo/
├── kilo.json              # Основной конфиг проекта
├── instructions.md        # Дополнительные инструкции
├── agent/                 # Агенты (роли)
│   ├── coder.md
│   ├── architect.md
│   └── reviewer.md
├── command/               # Пользовательские команды
│   ├── test.md
│   └── review.md
├── skill/                 # Скиллы
│   └── my-skill/
│       └── SKILL.md
└── themes/                # Кастомные темы TUI
```

### Ключевые поля kilo.json

```jsonc
{
  "$schema": "https://app.kilo.ai/config.json",
  "model": "zai/glm-5.1",
  "small_model": "zai/glm-4.5-air",
  "default_agent": "code",
  "instructions": ["docs/conventions/*.md"],  // glob-паттерны
  "permission": { /* ... */ },
  "provider": {
    "openai-compatible": {
      "options": {
        "apiKey": "...",
        "baseURL": "..."
      }
    }
  },
  "skills": {
    "paths": ["docs/agents/skills/"],
    "urls": []
  },
  "mcp": { /* MCP-серверы */ },
  "plugin": [],
  "snapshot": true,
  "share": "manual",
  "compaction": {
    "auto": true,
    "prune": true
  }
}
```

---

## Приложение В. Mermaid-диаграмма: Поток данных при запуске как сабагент

```mermaid
sequenceDiagram
    participant Orchestrator
    participant wrapper.sh
    participant kilo run --format json
    participant LLM Provider

    Orchestrator->>wrapper.sh: Запуск с таймаутом и ролью
    wrapper.sh->>kilo run --format json: kilo run --format json --auto --agent <role>
    kilo run --format json->>kilo run --format json: Загрузка агента, скиллов, AGENTS.md
    kilo run --format json->>LLM Provider: API-запрос
    LLM Provider-->>kilo run --format json: Streaming-ответ
    kilo run --format json-->>wrapper.sh: JSON events (message, tool_call, usage)
    Note over kilo run --format json): Модель вызывает tools (read, bash, edit, write)
    kilo run --format json-->>wrapper.sh: Финальный JSON event
    wrapper.sh->>wrapper.sh: Обработка таймаута
    wrapper.sh-->>Orchestrator: Результат (exit 0 = успех, exit 124 = таймаут)
```

---

## Источники

1. `kilo --help`, `kilo run --help` — CLI-параметры (v7.1.21)
2. Встроенный skill `kilo-config` — полная документация по конфигурации (извлечено из бинарника)
3. [Kilo Code GitHub](https://github.com/Kilo-Org/kilocode) — репозиторий
4. [Kilo Docs](https://kilo.ai/docs) — документация
5. `package.json` — метаданные пакета, лицензия MIT
