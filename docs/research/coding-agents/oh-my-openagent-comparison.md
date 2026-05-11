# Oh My OpenAgent (OmO) — Исследование для интеграции как сабагент

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-11
**Объект:** Oh My OpenAgent v4.0.0 (`oh-my-opencode` / `oh-my-openagent`, TypeScript/Bun, plugin для OpenCode)
**Задача:** [TASK-research-oh-my-openagent](../../../todo/TASK-research-oh-my-openagent.todo.md)
**Базовый продукт:** OpenCode CLI v1.4.0+ — [исследование](./opencode-cli-comparison.md)

---

## Сводка

Oh My OpenAgent (OmO) — **plugin для OpenCode**, а не отдельный CLI-агент. Устанавливается поверх OpenCode через `bunx oh-my-openagent install` и регистрируется как plugin в `opencode.json`. Добавляет систему Discipline Agents (Sisyphus, Hephaestus, Prometheus, Oracle, Atlas и др.), Team Mode для параллельной мультиагентной координации, IntentGate, Hash-Anchored Edit Tool, Skill-Embedded MCPs, команду `/init-deep` и более 50 hooks. npm-пакет называется `oh-my-opencode` (переходный период). Лицензия **SUL-1.0** (Sustainable Use License) — **ограничивает коммерческое использование**.

**Важно:** Все базовые возможности OpenCode (75+ провайдеров, JSON-режим `opencode run --format json`, кастомные агенты `.opencode/agent/*.md`, автосканирование `.agents/skills/`, AGENTS.md, ACP-сервер) сохраняются. OmO работает **поверх** этих механизмов.

---

## Что нового OmO добавляет поверх OpenCode

| Фича | Описание |
|------|----------|
| **Discipline Agents** | 11 специализированных агентов с собственными промптами, моделями и permissions: Sisyphus, Hephaestus, Prometheus, Atlas, Oracle, Librarian, Explore, Metis, Momus, Multimodal-Looker, Sisyphus-Junior |
| **Category System** | Вместо указания модели — указывается категория задачи (`visual-engineering`, `ultrabrain`, `deep`, `quick`, `writing` и др.), система автоматически выбирает модель |
| **`ultrawork` / `ulw`** | One-word активация: агент автономно исследует кодовую базу, планирует и реализует задачу до завершения |
| **Team Mode** (v4.0, OFF по умолчанию) | Lead agent + до 8 параллельных members с общим mailbox, shared task list, tmux-визуализацией. 12 `team_*` инструментов |
| **IntentGate** | Классификация истинного намерения пользователя до начала работы (research, implementation, investigation, fix) |
| **Hash-Anchored Edit Tool** | `LINE#ID` — content hash для каждой строки, валидация перед применением edit. Предотвращает stale-line ошибки |
| **Skill-Embedded MCPs** | Скиллы могут нести собственные MCP-серверы, запускаемые по требованию и изолированные per-session |
| **`/init-deep`** | Автогенерация иерархических `AGENTS.md` по всему проекту (directory-specific context) |
| **Prometheus Planning** | Интервью-режим: стратегическое планирование через вопросы к пользователю перед реализацией |
| **Background Agents** | Параллельный запуск 5+ агентов с контролем concurrency per-provider/per-model |
| **Runtime Fallback** | Автоматическое переключение на резервные модели при ошибках API (429, 503, 529) |
| **50+ Hooks** | 52 базовых hook'а для контроля поведения агента (comment checker, todo enforcer, context window monitor и др.) |
| **`bunx oh-my-openagent run`** | Non-interactive runner с контролем завершения (ждёт пока все todos completed + background idle) |
| **Built-in MCPs** | Exa (web search), Context7 (docs), Grep.app (GitHub search) — включены по умолчанию |
| **LSP + AST-Grep Tools** | `lsp_rename`, `lsp_goto_definition`, `lsp_find_references`, `ast_grep_search/replace` |
| **Tmux Integration** | Визуализация background agents в отдельных tmux-панелях |
| **Claude Code Compatibility** | Загрузка hooks, commands, skills, agents, MCPs, plugins из `.claude/` директорий |

---

## Критерий 1. Системный промпт

### Базовые возможности OpenCode (сохраняются)

OmO работает поверх OpenCode, поэтому все механизмы системного промпта OpenCode доступны:
- Кастомные агенты `.opencode/agent/*.md` с YAML frontmatter
- `opencode.json` → `agent.<name>.prompt` с поддержкой `{file:path}`
- `opencode.json` → `instructions` для дополнительных файлов

### Новые возможности OmO

| Механизм | Поведение |
|----------|-----------|
| `agents.<name>.prompt` в `oh-my-openagent.jsonc` | Полная замена системного промпта агента. Поддерживает `file://` URI |
| `agents.<name>.prompt_append` | Дополнение системного промпта агента. Поддерживает `file://` URI |
| `categories.<name>.prompt_append` | Дополнение промпта для конкретной категории задач |
| Discipline Agent промпты | OmO предоставляет оптимизированные промпты для каждого из 11 агентов (Claude-optimized, GPT-optimized, dual-prompt) |

### Пример: инъекция роли через OmO config

```jsonc
// .opencode/oh-my-openagent.jsonc
{
  "agents": {
    "sisyphus": {
      "prompt_append": "file://./docs/agents/roles/team/system_analyst_sherlock.ru.md"
    },
    "oracle": {
      "prompt": "file://./docs/agents/roles/team/system_architect_gandalf.ru.md"
    }
  }
}
```

### Поддержка `file://` URI

```jsonc
{
  "agents": {
    "sisyphus": {
      "prompt_append": "file:///absolute/path/to/prompt.txt"
    },
    "oracle": {
      "prompt": "file://./relative/to/project/prompt.md"
    },
    "explore": {
      "prompt_append": "file://~/home/dir/prompt.txt"
    }
  }
}
```

Пути могут быть: абсолютные (`file:///abs/path`), относительные от корня проекта (`file://./rel/path`), от домашней директории (`file://~/home/path`).

### Оценка: ✅ Полная поддержка

OmO наследует все механизмы системного промпта OpenCode и добавляет `prompt` / `prompt_append` через конфигурацию plugin'а с поддержкой `file://` URI. Можно как полностью заменить, так и дополнить системный промпт любого Discipline Agent. Дополнительно — `prompt_append` на уровне категорий.

---

## Критерий 2. Промпт агента / Роль

### Подход

OmO предоставляет **11 преднастроенных Discipline Agents**, каждый с собственным системным промптом, оптимизированным под конкретную модель:

| Агент | Модель по умолчанию | Роль | Промпт |
|-------|---------------------|------|--------|
| **Sisyphus** | claude-opus-4-7 / kimi-k2.6 / glm-5 | Главный оркестратор | Claude-optimized |
| **Hephaestus** | gpt-5.5 (medium) | Автономный глубокий воркер | GPT-native |
| **Prometheus** | claude-opus-4-7 / gpt-5.5 | Стратегический планировщик | Dual-prompt (Claude/GPT) |
| **Atlas** | claude-sonnet-4-6 | Оркестратор выполнения планов | Dual-prompt |
| **Oracle** | gpt-5.5 | Архитектурный консультант (read-only) | GPT-native |
| **Librarian** | gpt-5.4-mini-fast | Поиск по документации | GPT-native |
| **Explore** | gpt-5.4-mini-fast | Быстрый поиск по коду | GPT-native |
| **Metis** | claude-sonnet-4-6 | Gap analyzer | Claude-optimized |
| **Momus** | gpt-5.5 | Безжалостный ревьюер | GPT-native |
| **Multimodal-Looker** | gpt-5.5 | Визуальный анализ | GPT-native |
| **Sisyphus-Junior** | _(из категории)_ | Executor делегированных задач | _(из категории)_ |

### Инъекция роли через конфигурацию

```jsonc
// .opencode/oh-my-openagent.jsonc
{
  "agents": {
    "sisyphus": {
      "prompt_append": "Возьми на себя роль из файла: docs/agents/roles/team/system_analyst_sherlock.ru.md"
    }
  }
}
```

### Запуск с конкретным агентом

```bash
# Через OmO runner
bunx oh-my-openagent run --agent sisyphus "Выполни задачу X"

# Через OpenCode CLI (базовый)
opencode run --agent sisyphus "Выполни задачу X"
```

### Dual-Prompt система

Агенты Prometheus и Atlas автоматически переключают промпт в зависимости от модели: Claude-optimized (~1,100 строк) для Claude/GLM/Kimi, GPT-optimized (~300 строк XML-tagged) для GPT. Детекция через `isGptModel()`.

### Оценка: ✅ Полная поддержка

OmO превосходит базовый OpenCode по управлению ролями: 11 специализированных агентов с оптимизированными промптами, dual-prompt система, `prompt` / `prompt_append` через plugin config. Каждый агент имеет изолированные permissions и набор инструментов.

---

## Критерий 3. Скиллы

### Базовые возможности OpenCode (сохраняются)

- Автосканирование `.claude/skills/`, `.agents/skills/`, `.opencode/skill/`
- `skills.paths` в `opencode.json`
- Инструмент `skill` для загрузки по имени

### Новые возможности OmO

| Механизм | Поведение |
|----------|-----------|
| **Built-in Skills** | 7 предустановленных скиллов: `playwright`, `playwright-cli`, `agent-browser`, `dev-browser`, `git-master`, `frontend-ui-ux`, `review-work`, `ai-slop-remover` |
| **Skill-Embedded MCPs** | SKILL.md может объявлять MCP-серверы в YAML frontmatter. Серверы запускаются по требованию, изолированы per-session |
| **Category + Skill Combo** | При делегировании через `task()` можно указать `load_skills` — скиллы загружаются для subagent'а |
| **Custom Skills** | `.opencode/skills/*/SKILL.md` (проект), `~/.config/opencode/skills/*/SKILL.md` (глобальные) |
| **`disabled_skills`** | Отключение встроенных скиллов через конфигурацию |

### Skill-Embedded MCP (новое)

```yaml
# .opencode/skills/my-skill/SKILL.md
---
name: my-skill
description: My special custom skill
mcp:
  my-mcp:
    command: npx
    args: ["-y", "my-mcp-server"]
---

# My Skill Prompt

This content will be injected into the agent's system prompt.
```

MCP-серверы скиллов изолированы по ключу `${sessionID}:${skillName}:${serverName}`.

### Приоритет загрузки скиллов

```
project > opencode > user > builtin
```

### Разные скиллы разным ролям

Через `task(category="...", load_skills=["skill1", "skill2"])` можно указать конкретные скиллы для конкретного делегирования. Однако глобально скиллы по-прежнему загружаются для сессии — нет прямого CLI-механизма «этому агенту — эти скиллы, другому — те» без использования `task()`.

### Наша структура

Наши скиллы лежат в `docs/agents/skills/`. Для OmO (как и для OpenCode):
1. Добавить путь в `skills.paths` в `opencode.json` или `oh-my-openagent.jsonc`
2. Или создать симлинк `.agents/skills/` → `docs/agents/skills/`

### Оценка: ⚠️ Частичная поддержка (улучшена относительно OpenCode)

Skill-Embedded MCPs — значительное улучшение: скиллы теперь несут не только инструкции, но и MCP-инструменты. Category + Skill combo позволяет гибко комбинировать. Однако нет CLI-флага `--skill` (как у Pi/Hermes/Warp) — управление через конфигурацию.

---

## Критерий 4. AGENTS.md (контекстные файлы)

### Базовые возможности OpenCode (сохраняются)

- Автообнаружение `AGENTS.md` из корня проекта и parent directories
- Автообнаружение `CLAUDE.md`
- `instructions[]` для дополнительных файлов

### Новые возможности OmO

| Механизм | Поведение |
|----------|-----------|
| **`/init-deep`** | Автогенерация иерархических `AGENTS.md` по всей структуре проекта. Поддерживает `--create-new` и `--max-depth=N` |
| **Directory AGENTS.md Injection** | Hook `directory-agents-injector` — при чтении файла автоматически инжектирует все AGENTS.md от директории файла до корня проекта |
| **Conditional Rules** | `.claude/rules/*.md` с glob-паттернами — условные правила, инжектируемые при совпадении |
| **Claude Code Compatibility** | Загрузка `.claude/commands/`, `.claude/agents/`, `.claude/rules/` из коробки |

### Иерархический AGENTS.md (`/init-deep`)

```
project/
├── AGENTS.md              ← project-wide context
├── src/
│   ├── AGENTS.md          ← src-specific context
│   └── components/
│       └── AGENTS.md      ← component-specific context
```

При чтении `components/Button.tsx` автоматически инжектируются все три AGENTS.md снизу вверх.

### Отключение

Hook `directory-agents-injector` автоматически отключается на OpenCode 1.1.37+ (нативная поддержка AGENTS.md). Нет CLI-флага для прямого отключения — только через `disabled_hooks`.

### Оценка: ✅ Полная поддержка (улучшена относительно OpenCode)

OmO добавляет `/init-deep` для автоматической генерации иерархических AGENTS.md и directory-based injection — при чтении файла инжектируется контекст всех AGENTS.md вверх по дереву. Это значительное улучшение для крупных проектов.

---

## Критерий 5. Стандартная папка `.agents/skills/`

### Поддержка

Базовое автосканирование OpenCode полностью сохраняется:

| Локация | Автосканирование |
|---------|------------------|
| `.agents/skills/` | ✅ `skills/**/SKILL.md` |
| `~/.agents/skills/` | ✅ Глобальные |
| `.claude/skills/` | ✅ Claude Code compat |
| `.opencode/skills/` | ✅ OpenCode native |
| `~/.config/opencode/skills/` | ✅ Пользовательские |

### Skill-Embedded MCPs (новое)

OmO добавляет возможность объявления MCP-серверов внутри SKILL.md через YAML frontmatter. Это **новый уровень интеграции**: скилл приносит не только инструкции, но и инструменты, которые автоматически доступны агенту.

### Наша структура

Нужен симлинк `.agents/skills/` → `docs/agents/skills/` или `skills.paths` в конфигурации. Аналогично базовому OpenCode.

### Оценка: ✅ Поддерживается из коробки (с улучшениями)

Стандарт `.agents/skills/` поддерживается. OmO дополнительно добавляет Skill-Embedded MCPs — скиллы могут приносить собственные MCP-серверы, что устраняет проблему «context bloat» от глобальных MCP.

---

## Критерий 6. Запуск как сабагент (JSON-режим)

### OmO Runner: `bunx oh-my-openagent run`

OmO добавляет собственный non-interactive runner:

```bash
bunx oh-my-openagent run <message> [options]
```

| Опция | Поведение |
|-------|-----------|
| `-a, --agent <name>` | Агент (default: Sisyphus) |
| `-m, --model <provider/model>` | Переопределение модели |
| `-d, --directory <path>` | Рабочая директория |
| `-p, --port <port>` | Порт сервера |
| `--attach <url>` | Подключение к работающему серверу |
| `--on-complete <command>` | Shell-команда после завершения |
| `--json` | Структурированный JSON-результат |
| `--session-id <id>` | Возобновление существующей сессии |
| `--verbose` | Полный поток событий |

### Критерии завершения

OmO runner **ждёт завершения** обоих условий:
1. Все todos completed или cancelled
2. Все background child sessions idle

Это значительное улучшение относительно базового OpenCode, где нет контроля завершения.

### Разрешение агентов

Порядок: `--agent` → `OPENCODE_DEFAULT_AGENT` → `default_run_agent` в config → `Sisyphus`

### Базовый OpenCode JSON-режим (сохраняется)

```bash
opencode run --format json --agent sisyphus "Prompt"
```

### Team Mode для параллельного запуска

```jsonc
// oh-my-openagent.jsonc
{
  "team_mode": {
    "enabled": true,
    "max_parallel_members": 4,
    "tmux_visualization": true
  }
}
```

Team Mode предоставляет 12 `team_*` инструментов: `team_create`, `team_delete`, `team_send_message`, `team_task_create/list/update/get`, `team_status`, `team_list`, `team_shutdown_request`, `team_approve_shutdown`, `team_reject_shutdown`.

### Ограничения (унаследованы от OpenCode)

1. **Нет ephemeral-режима** — сессия сохраняется в SQLite. Однако OmO runner контролирует завершение по критериям todos+background.
2. **Нет встроенного timeout-контроля** через CLI. Нужен внешний wrapper.
3. **Формат событий** — `--json` выводит структурированный результат, но формат беднее чем JSONL у Pi.

### Оценка: ⚠️ Частичная поддержка (улучшена относительно OpenCode)

OmO runner добавляет контроль завершения (todos + background idle), выбор агента через `--agent`, `--on-complete` для post-processing. Team Mode — уникальная возможность для параллельной мультиагентной работы. Однако нет ephemeral-режима, нет таймаутов через CLI, формат `--json` беднее чем JSONL-стриминг Pi. Для интеграции как сабагент потребуется wrapper-скрипт.

---

## Критерий 7. Токены и стоимость

### Базовые возможности OpenCode (сохраняются)

- Токены (input, output, reasoning, cache read/write) в `step_finish` JSON-событиях
- `cost` в USD в каждом событии
- `opencode stats` — агрегированная статистика
- Данные персистентны в SQLite

### Новые возможности OmO

| Механизм | Поведение |
|----------|----------|
| **context-window-monitor hook** | Мониторинг использования context window и трекинг потребления токенов в реальном времени |
| **preemptive-compaction hook** | Проактивная компакция сессий до достижения лимитов токенов. Сохраняет критический контекст (через `compaction-context-injector`) |
| **dynamic_context_pruning** | (экспериментальное) Автоматическая обрезка старых tool outputs для управления context window: дедупликация, supersede_writes, purge_errors |

### Управление токенами

```jsonc
// oh-my-openagent.jsonc
{
  "experimental": {
    "dynamic_context_pruning": {
      "enabled": true,
      "strategies": {
        "deduplication": { "enabled": true },
        "supersede_writes": { "enabled": true, "aggressive": false },
        "purge_errors": { "enabled": true, "turns": 5 }
      }
    }
  }
}
```

### Оценка: ✅ Полная поддержка (с улучшениями)

Базовая телеметрия OpenCode (токены + стоимость) полностью сохраняется. OmO добавляет proactive context management — мониторинг, компакцию, pruning — что критично для мультиагентных сценариев с высоким потреблением токенов.

---

## Критерий 8. Free tier

### OpenCode Zen (базовые бесплатные модели)

OmO наследует все бесплатные модели OpenCode:

| Модель | Провайдер | Стоимость |
|--------|-----------|----------|
| `opencode/big-pickle` | OpenCode Zen (GLM 4.6) | Бесплатно |
| `opencode/gpt-5-nano` | OpenCode Zen | Бесплатно |
| `opencode/minimax-m2.7` | OpenCode Zen | Бесплатно |
| `opencode/kimi-k2.5-free` | OpenCode Zen | Бесплатно |

### OpenCode Go ($10/мес)

Опциональная подписка, добавляющая Kimi K2.6, GLM-5.1, MiniMax M2.7, Qwen 3.5 Plus.

### Бесплатные провайдеры

| Провайдер | Бесплатные возможности |
|-----------|----------------------|
| Google Gemini API | Free tier: Gemini 3 Flash — ограниченный RPM |
| OpenCode Zen | 4+ бесплатных моделей |
| Ollama / LM Studio | Полностью бесплатно при локальных моделях |

### Лицензионное ограничение (SUL-1.0)

**Важно:** OmO лицензирован под SUL-1.0 (Sustainable Use License), который:
- Разрешает использование только для **некоммерческих целей** или **внутреннего бизнес-использования**
- Запрещает коммерческое распространение
- Запрещает sublicense и transfer

### Оценка: ⚠️ Бесплатный инструмент, НО лицензия ограничивает коммерческое использование

Сам инструмент бесплатен, 4+ бесплатных модели из коробки. Однако лицензия SUL-1.0 ограничивает коммерческое использование — см. Критерий 10.

---

## Критерий 9. Провайдеры и модели

### Базовые возможности OpenCode (сохраняются)

75+ провайдеров через models.dev, полный BYOK, локальные модели (Ollama, LM Studio).

### OmO: Agent-Model Matching

Ключевое нововведение OmO — автоматический подбор модели для каждого агента:

| Агент | Цепочка провайдеров |
|-------|---------------------|
| **Sisyphus** | anthropic\|github-copilot\|opencode/claude-opus-4-7 (max) → opencode-go/kimi-k2.6 → kimi-for-coding/k2p5 → openai\|github-copilot\|opencode/gpt-5.5 (medium) → zai-coding-plan\|opencode/glm-5 → opencode/big-pickle |
| **Hephaestus** | gpt-5.5 (medium) only |
| **Oracle** | openai\|github-copilot\|opencode/gpt-5.5 (high) → google\|github-copilot\|opencode/gemini-3.1-pro (high) → anthropic\|github-copilot\|opencode/claude-opus-4-7 (max) → opencode-go/glm-5.1 |
| **Explore** | openai/gpt-5.4-mini-fast → opencode-go/qwen3.5-plus → vercel/minimax-m2.7-highspeed → opencode-go\|vercel/minimax-m2.7 → claude-haiku-4-5 → gpt-5.4-nano |

### Category System

8 встроенных категорий с автоматическим выбором модели:

| Категория | Модель по умолчанию |
|-----------|---------------------|
| `visual-engineering` | google/gemini-3.1-pro (high) |
| `ultrabrain` | openai/gpt-5.5 (xhigh) |
| `deep` | openai/gpt-5.5 (medium) |
| `quick` | openai/gpt-5.4-mini |
| `unspecified-high` | anthropic/claude-opus-4-7 (max) |
| `writing` | google/gemini-3-flash |

### Runtime Fallback

Автоматическое переключение при ошибках API:

```jsonc
{
  "runtime_fallback": {
    "enabled": true,
    "retry_on_errors": [400, 429, 503, 529],
    "max_fallback_attempts": 3,
    "cooldown_seconds": 60
  }
}
```

### Кастомизация моделей

```jsonc
{
  "agents": {
    "sisyphus": { "model": "kimi-for-coding/k2p5" },
    "prometheus": { "model": "openai/gpt-5.5" }
  },
  "categories": {
    "git": {
      "model": "opencode/gpt-5-nano",
      "description": "All git operations"
    }
  }
}
```

### Поддерживаемые подписки

OmO интегрирует 9 подписок/провайдеров: Claude Pro/Max, ChatGPT Plus, Gemini, GitHub Copilot, OpenCode Zen, Z.ai Coding Plan, OpenCode Go, Kimi for Coding, Vercel AI Gateway.

### Оценка: ✅ Поддержка 75+ провайдеров + Agent-Model Matching

OmO наследует все провайдеры OpenCode и добавляет интеллектуальную маршрутизацию: каждый агент получает оптимальную модель с fallback-цепочкой. Category System устраняет необходимость ручного выбора модели.

---

## Критерий 10. Лицензия

### SUL-1.0 (Sustainable Use License)

| Параметр | Значение |
|----------|----------|
| Пакет | `oh-my-opencode` (npm) / `oh-my-openagent` (dual-publish) |
| Автор | YeonGyu-Kim (code-yeongyu) |
| Лицензия | **SUL-1.0** (Sustainable Use License) |
| Репозиторий | https://github.com/code-yeongyu/oh-my-openagent |
| Язык | TypeScript (Bun) — plugin для OpenCode |

### Ограничения SUL-1.0

| Право | Допускается |
|-------|-------------|
| Некоммерческое использование | ✅ |
| Личное использование | ✅ |
| Внутреннее бизнес-использование | ✅ (internal business purposes) |
| Коммерческое распространение | ❌ |
| Sublicense | ❌ |
| Transfer | ❌ |
| Модификация | ✅ (с уведомлением) |
| Derivative works | ✅ (для внутреннего/некоммерческого) |
| Патенты | ✅ (royalty-free, non-exclusive) |

### Ключевые ограничения для нас

1. **Нельзя продавать** OmO как часть коммерческого продукта
2. **Нельзя распространять** в коммерческих целях (только бесплатно для некоммерческих)
3. **Внутреннее бизнес-использование** — разрешено. Это означает, что мы МОЖЕМ использовать OmO для разработки task-orchestrator внутри команды
4. **Derivative works** — разрешены для внутреннего использования, но нельзя коммерчески распространять
5. **Termination** — нарушение лицензии автоматически прекращает действие лицензии. Повторное нарушение — permanently

### Сравнение с MIT (OpenCode)

| Право | MIT (OpenCode) | SUL-1.0 (OmO) |
|-------|---------------|----------------|
| Коммерческое использование | ✅ Без ограничений | ⚠️ Только internal business |
| Распространение | ✅ | ❌ Только некоммерческое |
| Sublicense | ✅ | ❌ |
| Модификация | ✅ | ✅ С уведомлением |
| Патенты | Неявно | ✅ Явно |

### Оценка: ⚠️ Open source с ограничениями

SUL-1.0 — это **не MIT и не Apache-2.0**. Лицензия разрешает внутреннее бизнес-использование, что покрывает наш сценарий (разработка task-orchestrator). Но **коммерческое распространение запрещено** — мы не сможем включить OmO в коммерческий продукт или продавать услуги на его основе.

---

## Вердикт

### ⚠️ Частично подходит (Score: 7/10)

Oh My OpenAgent **частично подходит** для использования как сабагент с нашей системой ролей и скиллов. Это мощный plugin, значительно расширяющий возможности OpenCode, но с двумя критическими ограничениями: лицензия SUL-1.0 и отсутствие полноценного JSONL-стриминга.

**Сильные стороны:**
1. 11 Discipline Agents с оптимизированными промптами и permissions — значительно превосходит базовый OpenCode
2. Category System — интеллектуальная маршрутизация задач к моделям
3. Team Mode — уникальная возможность параллельной мультиагентной координации
4. `/init-deep` — автогенерация иерархических AGENTS.md
5. Skill-Embedded MCPs — скиллы приносят собственные MCP-серверы
6. `bunx oh-my-openagent run` — non-interactive runner с контролем завершения
7. IntentGate — классификация намерений до начала работы
8. Hash-Anchored Edit Tool — предотвращение stale-line ошибок
9. 50+ hooks — тонкий контроль поведения агентов
10. Runtime Fallback — автоматическое переключение моделей при ошибках
11. Claude Code Compatibility — загрузка конфигураций из `.claude/`

**Критические ограничения:**
1. **Лицензия SUL-1.0** — запрещает коммерческое распространение. Для внутреннего использования — ОК, для коммерческого продукта — НЕТ
2. **JSON-режим беднее** чем Pi — нет детальных событий tool-вызовов в `--json` runner'е
3. **Нет ephemeral-режима** — сессия всегда сохраняется в SQLite
4. **Нет встроенного timeout-контроля** через CLI

**Ограничения (управляемые):**
5. Нет CLI-флага `--skill` — управление через конфигурацию
6. Нет CLI-флага `--append-system-prompt` — через plugin config (`prompt_append`)
7. Зависимость от OpenCode как базового продукта — обновления OpenCode могут ломать plugin

**Потенциал:**
OmO — самый функциональный plugin для OpenCode на рынке. Discipline Agents, Category System и Team Mode — это паттерны, которые стоит изучить для нашей системы оркестрации. Однако для прямой интеграции как сабагента OmO уступает Pi из-за лицензионных ограничений и бедного JSON-формата.

**Рекомендация:**
- **Для внутреннего использования** (разработка, тестирование) — подходит, с wrapper-скриптом аналогичным `watch-subagent.sh`
- **Для коммерческого продукта** — не подходит из-за SUL-1.0
- **Для заимствования паттернов** — Discipline Agents, Category System, Team Mode, Skill-Embedded MCPs — все эти концепции ценны для архитектуры task-orchestrator

---

## Приложение А. Практические примеры запуска

### Запуск через OmO runner

```bash
# Простой non-interactive запуск
bunx oh-my-openagent run "Выполни задачу X"

# С конкретным агентом и моделью
bunx oh-my-openagent run --agent sisyphus --model anthropic/claude-sonnet-4 "Реализуй feature"

# JSON-результат
bunx oh-my-openagent run --json --agent sisyphus "Проверь тесты"

# С post-processing
bunx oh-my-openagent run --on-complete "notify-send done" "Длинная задача"
```

### Запуск через базовый OpenCode CLI

```bash
# JSON-режим (базовый OpenCode)
opencode run --format json --agent sisyphus "Выполни задачу X"
```

### Конфигурация для конкретной роли

```jsonc
// .opencode/oh-my-openagent.jsonc
{
  "agents": {
    "sisyphus": {
      "prompt_append": "file://./docs/agents/roles/team/backend_developer_levsha.ru.md",
      "model": "anthropic/claude-sonnet-4"
    }
  }
}
```

### Team Mode: параллельный запуск

```jsonc
// .omo/teams/analysis-team/config.json
{
  "name": "analysis-team",
  "description": "Parallel code analysis",
  "lead": { "kind": "subagent_type", "subagent_type": "sisyphus" },
  "members": [
    { "kind": "category", "name": "security", "category": "deep", "prompt": "Audit security patterns." },
    { "kind": "category", "name": "performance", "category": "quick", "prompt": "Find performance bottlenecks." }
  ]
}
```

---

## Приложение Б. Сравнение OpenCode vs OmO по ключевым отличиям

| Критерий | OpenCode (базовый) | OmO (plugin) | Дельта |
|----------|--------------------|--------------|--------|
| Агенты | Кастомные `.opencode/agent/*.md` | 11 Discipline Agents + кастомные | +11 преднастроенных |
| Промпты | Один промпт на агента | Dual-prompt (Claude/GPT auto-switch) | +dual-prompt |
| Делегирование | `--agent` или task tool | Category System (`visual-engineering`, `deep`, `quick`...) | +Category routing |
| Параллельность | Background tasks | Background + Team Mode (8 параллельных members) | +Team Mode |
| MCP | Глобальные MCP | Global + Skill-Embedded MCP | +Skill-Embedded MCP |
| Контроль завершения | Нет | OmO runner: todos + background idle | +Завершение |
| AGENTS.md | Автообнаружение | Авто + `/init-deep` (иерархический) | +init-deep |
| Edit Tool | Стандартный edit | Hash-Anchored (`LINE#ID`) | +Hash validation |
| IntentGate | Нет | Да — классификация намерений | +IntentGate |
| Hooks | Нет | 50+ hooks | +50 hooks |
| Non-interactive runner | `opencode run` | `bunx oh-my-openagent run` с `--json` | Улучшенный |
| Лицензия | MIT | SUL-1.0 (non-commercial) | -Более строгая |

---

## Приложение В. Паттерны OmO, ценные для нашей архитектуры

### 1. Discipline Agents (Sisyphus/Hephaestus/Prometheus)

**Паттерн:** Специализированные агенты с оптимизированными промптами под конкретные модели.

**Параллель с нашей системой:** Наши роли (`system_analyst_sherlock`, `backend_developer_levsha` и др.) — аналогичный подход, но OmO идёт дальше: каждый Discipline Agent имеет:
- Оптимизированный промпт под конкретную модель (Claude vs GPT)
- Собственную цепочку fallback-моделей
- Набор tools и permissions

**Заимствование:** Dual-prompt система — промпт роли может иметь Claude- и GPT-версии.

### 2. Category System

**Паттерн:** Вместо указания модели — указывается тип задачи, система маршрутизирует.

**Заимствование:** Category можно маппить на наши роли: `visual-engineering` → Frontend UI/UX, `deep` → Backend Developer, `quick` → Quick Fix.

### 3. Team Mode

**Паттерн:** Lead + параллельные members с общим mailbox и task list.

**Заимствование:** Архитектура Team Mode близка к нашему vision мультиагентной оркестрации. Shared mailbox + shared task list — хороший паттерн для координации.

### 4. Skill-Embedded MCPs

**Паттерн:** Скиллы несут собственные MCP-серверы, изолированные per-session.

**Заимствование:** В нашей системе скиллы (`docs/agents/skills/*/SKILL.md`) могут объявлять необходимые инструменты, которые автоматически подключаются при активации скилла.

### 5. IntentGate

**Паттерн:** Классификация намерения пользователя до начала работы.

**Заимствование:** Оркестратор (Тимлид Алекс) может классифицировать тип запроса перед делегированием конкретной роли.

---

## Источники

1. [Oh My OpenAgent — GitHub](https://github.com/code-yeongyu/oh-my-openagent) — репозиторий, README, документация
2. [OpenCode CLI — исследование](./opencode-cli-comparison.md) — базовый продукт (MIT)
3. [LICENSE.md](https://github.com/code-yeongyu/oh-my-openagent/blob/dev/LICENSE.md) — полный текст SUL-1.0
4. [docs/guide/installation.md](https://github.com/code-yeongyu/oh-my-openagent/blob/dev/docs/guide/installation.md) — установка и конфигурация
5. [docs/reference/features.md](https://github.com/code-yeongyu/oh-my-openagent/blob/dev/docs/reference/features.md) — полный справочник фич

