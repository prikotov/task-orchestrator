# Nanocoder — Исследование для интеграции как сабагент

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-07-28
**Объект:** Nanocoder v1.29.0 (`@nanocollective/nanocoder`, TypeScript/Node.js, 2.3k★ GitHub)
**Задача:** [TASK-research-nanocoder](../../../todo/TASK-research-nanocoder.todo.md)

---

## Сводка

Nanocoder — это **open-source terminal coding agent** от [Nano Collective](https://nanocollective.org) (community collective, не компания). Написан на TypeScript (React + Ink.js для TUI). Лицензия MIT. Позиционируется как **local-first, privacy-respecting** альтернатива Claude Code / Gemini CLI: «Bring your own model, keep your code on your machine, and owe nothing to anyone». Нет платных уровней, нет телеметрии, нет закрытых фич.

Архитектурно Nanocoder — это **автономный CLI-процесс** (не daemon-gateway как OpenClaw). Запускается интерактивно (`nanocoder`) или в non-interactive `run`-режиме для CI/скриптов. Поддерживает нативный tool calling с **XML fallback** для моделей без нативной поддержки, MCP-серверы, и **file-based custom commands/subagents/tools** через единую «skills»-модель. BYOM: Ollama, OpenRouter, любой OpenAI-compatible API, плюс нативные Anthropic / Google Gemini / GitHub Copilot SDK-провайдеры.

**Ключевое отличие от Pi/omp:** Nanocoder — независимый local-first «nano»-кандидат (не Claw-семейство). Имеет `--json` headless-режим и ACP-стриминг, но использует собственный формат скиллов (`.nanocoder/skills/`), а не стандарт `.agents/skills/`, и инъекция системного промпта — через config-файл, а не через CLI-флаг.

---

## Критерий 1. Системный промпт

### Возможности

| Механизм | Поведение |
|----------|-----------|
| `agents.config.json` → `nanocoder.systemPrompt.content` | Inline-текст промпта. `mode: "replace"` — полная замена; `mode: "append"` — дополнение поверх built-in. |
| `agents.config.json` → `nanocoder.systemPrompt.file` | Путь к `.md`-файлу (относительно CWD или абсолютный). Загружается, если `content` не задан. |
| `mode: "replace"` | Полностью переопределяет built-in промпт. **Пропускает** `## SYSTEM INFORMATION` и авто-подключение `AGENTS.md`. |
| `mode: "append"` | Дополняет built-in промпт пользовательским контентом в конце. |

### Архитектура

Системный промпт Nanocoder формируется из **config-файла** (`agents.config.json`), а не из CLI-аргументов. Config ищется в порядке: project-level (`./agents.config.json`) → user-level (`~/.config/nanocoder/agents.config.json` на Linux) → `NANOCODER_CONFIG_DIR` (полный override).

```json
{
  "nanocoder": {
    "systemPrompt": {
      "mode": "append",
      "content": "Always respond in British English."
    }
  }
}
```

```json
{
  "nanocoder": {
    "systemPrompt": {
      "mode": "replace",
      "file": "./.nanocoder/system-prompt.md"
    }
  }
}
```

**Нет CLI-флагов** для замены или дополнения системного промпта (`--system-prompt`, `--append-system-prompt`). Кастомизация возможна только через редактирование config-файла перед запуском. Tool-определения всё равно инжектируются для провайдеров без нативного tool calling.

### Сравнение с Pi

| Возможность | Pi | Nanocoder |
|-------------|-----|-----------|
| Полная замена через CLI-флаг | `--system-prompt <text>` | ❌ Нет |
| Дополнение через CLI-флаг | `--append-system-prompt <text>` | ❌ Нет |
| Файловая замена (config) | `.pi/SYSTEM.md` | `systemPrompt.file` (config) ✅ |
| Файловое дополнение | `.pi/APPEND_SYSTEM.md` | `systemPrompt.mode: append` (config) ✅ |

### Оценка: ⚠️ Частичная поддержка

Полная замена и дополнение системного промпта доступны через config (`agents.config.json` → `systemPrompt`), но **нет CLI-флагов** для динамической инъекции при запуске сабагента. Для каждого вызова потребуется предварительно записать/переопределить config-файл, что менее гибко, чем Pi с `--system-prompt`.

---

## Критерий 2. Промпт агента / Роль

### Подход

Nanocoder реализует **subagents** — специализированных AI-агентов, которым главный агент делегирует задачи. Каждый subagent работает в **изолированном контексте** со своим системным промптом, отфильтрованным набором tools и опционально другой моделью/провайдером. Главному агенту возвращается только финальный результат.

Subagent определяется как markdown-файл с frontmatter:

```markdown
<!-- .nanocoder/agents/code-reviewer.md -->
---
name: code-reviewer
description: Reviews code for bugs, security issues, and style problems
provider: ollama
model: ministral-3:3b
contextWindow: 16384
tools:
  - read_file
  - search_file_contents
  - find_files
  - list_directory
---

You are a code review specialist. When given a file or directory to review...
```

Тело файла после frontmatter = системный промпт subagent'а.

### Изоляция ролей

| Возможность | Статус |
|-------------|--------|
| Изолированный контекст | ✅ Каждый subagent — отдельный conversation |
| Отфильтрованные tools | ✅ Frontmatter `tools: []` (allow-list) + `disallowedTools: []` |
| Другая модель/провайдер | ✅ `provider:` + `model:` per-subagent (например, local Ollama для research) |
| Параллельное выполнение | ✅ До 5 subagents concurrently |
| Built-in `explore` | ✅ Codebase exploration agent (read-only tools) |

### Ограничения

1. **Нет CLI-инъекции роли** — нельзя передать роль-файл в команде запуска (`nanocoder run --role ...`). Роли предопределены файлами в `.nanocoder/agents/`.
2. **File-based** — создаётся через `/agents create <name>` или ручное размещение `.md`-файла.
3. **Главный агент решает сам** — делегирование происходит автоматически через `agent` tool; нельзя принудительно запустить subagent извне.

### Оценка: ⚠️ Частичная поддержка (через subagents)

Механизм subagents даёт реальную изоляцию ролей (отдельный контекст, tools, модель), но это **file-based конфигурация**, а не динамическая CLI-инъекция. Роль нельзя передать при запуске — её нужно предварительно разместить в `.nanocoder/agents/`. Для нашего паттерна `watch-subagent.sh` это означает: роль нужно записать в файл до запуска.

---

## Критерий 3. Скиллы

### Возможности

Nanocoder реализует единую **«skills»-модель** — зонтик для custom commands, subagents, custom tools и event-driven triggers. Скиллы бывают двух форм:

| Форма | Локация | Описание |
|-------|---------|----------|
| Single-file | `.nanocoder/commands/*.md`, `.nanocoder/agents/*.md`, `.nanocoder/tools/*.md` | Один `.md` с frontmatter |
| Bundle | `.nanocoder/skills/<name>/` с `skill.yaml` | Команда + subagent + tools + event subscriptions в одном артефакте |

```yaml
# .nanocoder/skills/k8s/skill.yaml
name: k8s
description: Kubernetes operational helpers.
version: 0.2.0
subscribe:
  - kind: file.changed
    target: agent:k8s-agent
    paths: ["k8s/**/*.yaml"]
tools_visibility:
  default: scoped
```

Управление: `/skills`, `/skills show`, `/skills create`, `/skills check`, `/skills promote/demote` (project ↔ global). Загрузка из 3 уровней: project (`.nanocoder/`) → personal (`~/.config/nanocoder/`) → built-in.

### Отсутствующие возможности

1. **Нет стандарта Agent Skills (`SKILL.md`)** — Nanocoder использует **собственный формат** (`skill.yaml` + frontmatter `.md`), не кросс-агентный [agentskills.io](https://agentskills.io/specification).
2. **Нет CLI-флага `--skill`** — нельзя динамически загрузить скилл при запуске сабагента.
3. **Нет `--no-skills`** — нельзя отключить автосканирование из CLI.
4. **Per-role filtering** — subagent'ы получают scoped tools от sibling-bundle, но сами скиллы **все глобальны** (нет per-invocation allow-list через CLI).

### Оценка: ⚠️ Частичная поддержка

Мощная собственная skills-система (commands + subagents + tools + cron/file-triggers), но **не совместима со стандартом `SKILL.md`** и не имеет CLI-управления. Для нашего стенда это означает: скиллы из `docs/agents/skills/` (формат `SKILL.md`) не загрузятся — требуется конвертация в формат `.nanocoder/`.

---

## Критерий 4. AGENTS.md (контекстные файлы)

### Возможности

| Механизм | Поведение |
|----------|-----------|
| `/init` | Анализирует проект и генерирует `AGENTS.md` (project-specific prompt). `/init --force` — регенерация; `/init --lean` — без merge `CLAUDE.md`. |
| Авто-загрузка `AGENTS.md` | Файл из CWD-проекта автоматически загружается каждую сессию и добавляется в системный промпт. |
| `CLAUDE.md` | Распознаётся и мержится в `AGENTS.md` при `/init`. В репозитории Nanocoder оба файла присутствуют. |
| `systemPrompt.mode: "replace"` | Косвенное отключение — `replace` пропускает авто-подключение `AGENTS.md`. |

### Архитектура

При стандартном запуске Nanocoder загружает `AGENTS.md` из **рабочей директории проекта** (project-level) и инжектирует в системный промпт. Это даёт модели контекст о конвенциях, структуре и тулинге проекта. Сам репозиторий Nanocoder содержит `AGENTS.md` (с пометкой «generated by Nanocoder») и `CLAUDE.md`.

### Ограничения

1. **Нет CLI-флага отключения** — нет аналога Pi `--no-context-files`. Отключить можно только через `systemPrompt.mode: "replace"` (косвенно).
2. **Walk-up discovery не задокументирован** — Pi сканирует `AGENTS.md` вверх до git root; для Nanocoder это явно не описано (загрузка из project CWD).

### Оценка: ⚠️ Частичная поддержка

`AGENTS.md` автоматически загружается из проекта, `CLAUDE.md` распознаётся — базовая поддержка есть. Но **нет CLI-флага для отключения** (только косвенно через config), и walk-up discovery не подтверждён.

---

## Критерий 5. Стандартная папка `.agents/skills/`

### Автосканирование

Nanocoder **НЕ поддерживает** стандартную кросс-агентную папку `.agents/skills/`. Скиллы загружаются **только из собственных локаций:

| Локация | Описание |
|---------|----------|
| `.nanocoder/skills/` | Project bundle-skills (`skill.yaml`) |
| `.nanocoder/commands/`, `.nanocoder/agents/`, `.nanocoder/tools/` | Single-file skills (flat) |
| `~/.config/nanocoder/skills/` (+ flat dirs) | Personal/global level |
| Built-in | Поставляются с nanocoder |

Никакого сканирования `.agents/skills/`, `.claude/skills/` или аналогов не задокументировано. Эквивалента `skills.paths` / `extraDirs` (как у OpenCode/Kilo/OpenClaw) для добавления произвольных директорий также не обнаружено.

### Наша структура

Наши скиллы лежат в `docs/agents/skills/` в формате `SKILL.md`. Nanocoder не автосканирует ни `.agents/skills/`, ни `docs/agents/skills/`, и формат `SKILL.md` не совпадает с собственным форматом (`skill.yaml` + frontmatter). Для использования скиллов потребуется **конвертация и размещение** в `.nanocoder/`.

### Оценка: ❌ Не поддерживается

Стандартная папка `.agents/skills/` **не поддерживается**. Есть собственный эквивалент `.nanocoder/skills/`, но он несовместим с кросс-агентным стандартом и нашим форматом `SKILL.md`.

---

## Критерий 6. Запуск как сабагент (JSON-режим)

### CLI-команды

```bash
# Non-interactive run — авто-execute, выход по завершении
nanocoder run "Add error handling to src/api.ts"

# Headless JSON-вывод — единый JSON-объект в stdout
nanocoder --plain --json run "Add error handling to src/api.ts"

# Skip directory trust prompt (CI/CD)
nanocoder --trust-directory --json run "your prompt"

# ACP-сервер — стриминг JSON-RPC over stdin/stdout (для Zed/редакторов)
nanocoder --acp
```

### JSON-режим (`--json`)

Флаг `--json` (alias `--output-format json`) работает только с `run`. Выводит **единый JSON-объект** в stdout по завершении (не стриминг JSONL-событий):

```json
{
  "outcome": "success",
  "finalText": "...",
  "reasoning": "...",
  "toolCalls": [
    {
      "name": "edit_file",
      "arguments": { "path": "src/api.ts" },
      "result": "...",
      "error": null
    }
  ],
  "modifiedFiles": ["src/api.ts"]
}
```

- `outcome` — `"success"` / `"tool-approval-required"` / `"error"`
- `finalText` — финальный ответ модели
- `reasoning` — accumulated thinking/reasoning (или `null`)
- `toolCalls` — все tool calls за прогон с arguments + result/error
- `modifiedFiles` — deduplicated список изменённых файлов

Весь human-readable output (баннеры, стриминг токенов, tool one-liners) при `--json` перенаправляется в `stderr`, оставляя `stdout` чистым для piping:

```bash
nanocoder --plain --json run "refactor the auth module" | jq .finalText
```

`--json` **несовместим** с `--acp` и `--vscode`.

### ACP-режим (`--acp`) — стриминг JSON-RPC

`nanocoder --acp` запускает [Agent Client Protocol](https://agentclientprotocol.com)-сервер: JSON-RPC over stdin/stdout. Это **полноценный стриминг-протокол** с:
- Стримингом ответов (включая reasoning/thinking)
- Tool call cards (с before/after diff для file edits)
- Permission prompts (respecting development modes)
- Model display и переключением
- `ask_user` (selection-only)

ACP-сессии стартуют в `auto-accept`. История сессии — in-memory (перезапуск = пустая нить).

### Таймауты и лимиты

| Механизм | Поведение |
|----------|-----------|
| `NANOCODER_MAX_TURNS` / `nanocoder.headless.maxTurns` | Лимит LLM-turns для headless (`--plain`) и ACP. Default: **200**. При достижении — strips tools + просит финальный ответ (не error). |
| `requestTimeout` (provider config) | Timeout запроса к провайдеру. Default: 120 000 ms. `-1` = отключить. |
| `socketTimeout` (provider config) | Socket-level timeout. |
| `NANOCODER_DEFAULT_SHUTDOWN_TIMEOUT` | Graceful shutdown timeout. Default: 5000 ms. |
| `--trust-directory` | Skip first-run trust prompt для CI (ephemeral — не пишет в preferences). |

### Ограничения для сабагента

1. **Нет стриминга JSONL-событий** — `--json` даёт только финальный объект, а не поток `turn_start`/`tool_execution_start`/`message_update` как Pi `--mode json`. Нельзя мониторить прогресс в реальном времени.
2. **Нет ephemeral `--no-session`** — сессии auto-save (default: `autoSave: true`, каждые 30 сек, retention 30 дней). Для `run` это single-shot, но сессия всё равно сохраняется.
3. **Нет pipe/RPC-контроля** — кроме ACP (который editor-oriented). Нет аналога Pi `--mode rpc` для generic-оркестрации (ACP — ближайший эквивалент).
4. **Нет wall-clock hard timeout** — есть turn-лимиты, но нет внешнего `--max-time` флага для всего прогона (требуется внешний wrapper типа `watch-subagent.sh`).

### Пример потока данных

```mermaid
sequenceDiagram
    participant Orchestrator
    participant nanocoder (--json run)
    participant LLM Provider

    Orchestrator->>nanocoder (--json run): nanocoder --plain --json --trust-directory run "prompt"
    nanocoder (--json run)->>nanocoder (--json run): Загрузка config, AGENTS.md, skills
    nanocoder (--json run)->>LLM Provider: API-запрос (до maxTurns=200)
    LLM Provider-->>nanocoder (--json run): Streaming-ответ + tool calls
    nanocoder (--json run)->>nanocoder (--json run): Tool execution (auto-accept по умолчанию)
    nanocoder (--json run)-->>Orchestrator: Единый JSON-объект (finalText, toolCalls, modifiedFiles)
    Note over Orchestrator: Нет стриминга событий, нет мониторинга прогресса в реальном времени
```

### Оценка: ⚠️ Частичная поддержка

JSON-режим **подтверждён** (`--json` с `run`) — это снимает блокер, который утопил OpenClaw. Но это **финальный JSON-объект**, а не стриминг JSONL-событий: нельзя отслеживать прогресс в реальном времени, нельзя детектировать stall. ACP-режим (`--acp`) даёт полноценный стриминг JSON-RPC, но ориентирован на редакторы. Нет ephemeral `--no-session`. Для интеграции через `watch-subagent.sh` потребуется адаптация: stall-detection работать не будет (нет потока событий), но финальный результат и список tool calls доступны.

---

## Критерий 7. Токены и стоимость

### Доступные метрики

| Источник | Что показывает |
|----------|----------------|
| `/usage` | Визуальный breakdown использования контекста (interactive) |
| `/status` | Текущий provider, model, context usage |
| File Explorer | Token estimates для добавляемых файлов |
| Subagent progress | Estimated token count |
| `tiktoken` (dependency) | Tokenization для context management |

### JSON-вывод

В output-объекте `--json` (`{outcome, finalText, reasoning, toolCalls[], modifiedFiles[]}`) **нет полей token usage или cost**. Структурированная телеметрия токенов/стоимости в headless-выводе отсутствует.

### Сравнение с Pi

| Метрика | Pi | Nanocoder |
|---------|-----|-----------|
| Токены в JSONL/JSON | ✅ `usage.input/output/cacheRead/cacheWrite/totalTokens` | ❌ Нет в `--json` output |
| Стоимость в $ | ✅ `cost` object | ❌ Не подтверждено |
| Per-model разбивка | ✅ | ❌ |
| Контекст-менеджмент | ✅ | ✅ `/usage`, auto-compact, `NANOCODER_CONTEXT_LIMIT` |

### Оценка: ⚠️ Частичная поддержка

Токенизация (tiktoken) используется внутренне для context management (`/usage`, auto-compact, file explorer estimates), но **в headless `--json`-выводе нет объекта `usage`/`cost`**. Нельзя программно извлечь затраты токенов/стоимость из `nanocoder --json run`. Стоимость в $ не подтверждена.

---

## Критерий 8. Free tier / Лицензия / BYOK / Ollama

### Nanocoder как продукт

Nanocoder — **полностью бесплатный** (MIT), community collective. Нет платных уровней, нет telemetry, нет gated фич. «Privacy-respecting, local-first, and open for all».

### BYOM (Bring Your Own Model) — core principle

| Возможность | Статус |
|-------------|--------|
| BYOM | ✅ Ключевой принцип — «Bring your own model» |
| Ollama | ✅ First-class provider (отдельная doc-страница) |
| LM Studio | ✅ |
| llama.cpp | ✅ |
| vLLM | ✅ |
| LocalAI | ✅ |
| MLX Server | ✅ Apple Silicon |
| Atomic Chat | ✅ |
| llama-swap | ✅ |
| No telemetry | ✅ Локальные модели — данные не покидают машину |
| No paid tiers | ✅ «owe nothing to anyone» |

### Оценка: ✅ Бесплатный инструмент, local-first, BYOM

Это **сильнейшая сторона** Nanocoder. Local-first BYOM — основа архитектуры, а не опция. Нет телеметрии, нет gated-фич, MIT. Идеально для offline/local-only сценариев.

---

## Критерий 9. Провайдеры и модели

### Поддерживаемые провайдеры (~25+)

**Локальные (8):** Ollama, llama.cpp, LM Studio, Atomic Chat, MLX Server, vLLM, LocalAI, llama-swap.

**Cloud OpenAI-compatible (10+):** OpenRouter, Requesty, Together AI, OpenAI, Mistral AI, GitHub Models, Poe, Atlas Cloud (300+ моделей), Z.ai, Z.ai Coding.

**Native SDK (7):** Anthropic Claude (`@ai-sdk/anthropic`), Google Gemini (`@ai-sdk/google`), GitHub Copilot (device OAuth), ChatGPT/Codex (browser login), Kimi Code, MiniMax Coding, Thesean AI.

**Custom:** любой OpenAI-compatible API через `baseUrl` + `apiKey`.

### Конфигурация

```json
{
  "nanocoder": {
    "providers": [
      {
        "name": "Local Ollama",
        "baseUrl": "http://localhost:11434/v1",
        "models": ["qwen2.5-coder:7b"],
        "contextWindow": 32768,
        "requestTimeout": -1
      },
      {
        "name": "OpenRouter",
        "baseUrl": "https://openrouter.ai/api/v1",
        "apiKey": "${OPENROUTER_API_KEY}",
        "sdkProvider": "openai-compatible",
        "models": ["anthropic/claude-sonnet-4-20250514"]
      }
    ]
  }
}
```

### Ключевые особенности

| Особенность | Описание |
|-------------|----------|
| Vercel AI SDK | `@ai-sdk/openai-compatible`, `@ai-sdk/google`, `@ai-sdk/anthropic` |
| Native tool calling + XML fallback | Для провайдеров без нативной поддержки — XML-based tool calling |
| `disableTools` / `disableToolModels` | Отключение tool calling per-provider или per-model |
| MCP (Model Context Protocol) | `@modelcontextprotocol/sdk` — подключение внешних tools |
| `/model-database` | Browse моделей OpenRouter (поиск, фильтр open/proprietary) |
| Env substitution | `$VAR`, `${VAR}`, `${VAR:-default}` в config |
| `NANOCODER_PROVIDERS` env | JSON-override провайдеров (highest precedence) |
| Per-model context window | `contextWindows[model]` overrides |

### BYOK

Полная поддержка. API-ключи через: env-переменные, `${VAR}` substitution в config, `NANOCODER_PROVIDERS`/`NANOCODER_PROVIDERS_FILE` overrides. Local-провайдеры не требуют ключей.

### Оценка: ✅ 25+ провайдеров, BYOM, local-first, MCP

Отличная поддержка провайдеров с акцентом на **local-first**: 8 локальных runner'ов (Ollama, llama.cpp, LM Studio, vLLM, ...) + OpenRouter + native Anthropic/Google/Copilot/Codex. MCP расширяет tools. Native tool calling с XML fallback — работает даже с моделями без нативной поддержки.

---

## Критерий 10. Лицензия

### Информация

| Параметр | Значение |
|----------|----------|
| Пакет | `@nanocollective/nanocoder` (npm) |
| Лицензия | **MIT** |
| Репозиторий | https://github.com/Nano-Collective/nanocoder |
| Автор | [Nano Collective](https://nanocollective.org) — community collective |
| Звёзды | 2.3k★ GitHub (2285 stars, 222 forks) |
| Тип | TypeScript (99%), React + Ink.js |
| Установка | npm, Homebrew, Nix Flakes |
| Спонсоры | Atlas Cloud (community-funded, non-profit) |

### Условия

MIT-лицензия (Copyright (c) 2026 Nano Collective). Разрешает коммерческое использование, модификацию, распространение, private use. Нет vendor lock-in — local-first, не требует cloud backend. Нет telemetry.

### Оценка: ✅ Open source, MIT — максимальная свобода

---

## Вердикт

### ⚠️ Частично подходит (Score: 7/10, ∑ = 22/30)

Nanocoder — **крепкий local-first кандидат**, который **очищает критический блокер K6** (наличие `--json` headless-режима + ACP-стриминг), утопивший OpenClaw. Это делает его пригодным для программного запуска как сабагент, но с адаптациями.

### Сводка оценок

| Критерий | Оценка | Балл |
|----------|--------|------|
| К1. Системный промпт | ⚠️ Config-only, нет CLI-флагов | 2 |
| К2. Роль | ⚠️ Subagents с изоляцией, но file-based | 2 |
| К3. Скиллы | ⚠️ Собственный формат, не `SKILL.md`, нет CLI | 2 |
| К4. AGENTS.md | ⚠️ Авто-загрузка + CLAUDE.md, нет CLI-disable | 2 |
| К5. `.agents/skills/` | ❌ Не поддерживается, только `.nanocoder/` | 1 |
| К6. JSON-режим | ⚠️ `--json` (финальный объект) + ACP-стриминг, нет ephemeral | 2 |
| К7. Токены/стоимость | ⚠️ Внутренние, нет в `--json` output | 2 |
| К8. Free/BYOM/Ollama | ✅ Local-first, MIT, нет telemetry | 3 |
| К9. Провайдеры | ✅ 25+ провайдеров, local-first, MCP | 3 |
| К10. Лицензия | ✅ MIT, open source, no lock-in | 3 |
| **Итого** | | **22** |

### Причины вердикта «Частично»

| # | Проблема | Влияние |
|---|----------|---------|
| 1 | **`--json` = финальный объект, не стриминг** | Нет мониторинга прогресса в реальном времени; stall-detection через `watch-subagent.sh` не сработает |
| 2 | **Нет ephemeral `--no-session`** | Сессии сохраняются; изоляция между вызовами сабагентов только через разные CWD/config |
| 3 | **Config-only system prompt** | Роль/промпт инъектируются через `agents.config.json`, не через CLI — менее гибко для динамического назначения |
| 4 | **Собственный формат скиллов** | `SKILL.md` из `docs/agents/skills/` не загрузится; нужна конвертация в `.nanocoder/skills/` |
| 5 | **Нет `.agents/skills/` стандарта** | Кросс-агентная совместимость скиллов отсутствует |
| 6 | **Нет token/cost в `--json`** | Нельзя программно извлечь usage из headless-вывода |

### Сильные стороны

1. **Local-first BYOM** — 8 локальных runner'ов (Ollama, llama.cpp, LM Studio, vLLM, MLX, ...), нет telemetry, данные не покидают машину
2. **`--json` headless-режим** — clean JSON в stdout для CI/автоматизации (снимает блокер OpenClaw)
3. **ACP-стриминг** — полноценный JSON-RPC over stdin/stdout (ближайший аналог Pi `--mode rpc`)
4. **Subagents с изоляцией** — отдельный контекст, tools allow-list, per-subagent модель/провайдер, parallel execution (до 5)
5. **Native tool calling + XML fallback** — работает с моделями без нативной tool-поддержки
6. **MCP-поддержка** — расширение tools через Model Context Protocol
7. **Headless turn-limits** — `maxTurns` (default 200) с graceful деградацией (strips tools → final answer)
8. **MIT, community collective** — non-profit, open, no vendor lock-in
9. **Turn-limits + provider timeouts** — bounded cost для unattended runs

### Паттерны для заимствования

| Паттерн | Описание |
|---------|----------|
| **Skills = umbrella model** | Commands + subagents + tools + event triggers в одном bundle с `skill.yaml`-манифестом |
| **Event subscriptions** | `file.changed` и `schedule.cron` triggers, fire subagents через per-project daemon |
| **Headless graceful degradation** | При `maxTurns` — strips tools и просит финальный ответ (не discard работы) |
| **Per-project daemon** | Detached процесс для cron/file-watch triggers, AF_UNIX socket IPC |

---

## Приложение А. Практические примеры запуска

### Non-interactive JSON-запуск (CI/скрипты)

```bash
# Базовый headless запуск с JSON-выводом
nanocoder --plain --json --trust-directory \
  --provider openrouter --model google/gemini-3.1-flash \
  run "Analyze the auth module and report issues" | jq .finalText

# Local Ollama, без trust-prompt (CI)
nanocoder --plain --json --trust-directory \
  --provider ollama --model qwen2.5-coder:7b --context-max 128k \
  run "Refactor src/api.ts" 2>/dev/null | jq '{outcome, modifiedFiles}'
```

### С предзаписанным config (роль через systemPrompt)

```bash
# Перед запуском — записать роль в config
cat > ./agents.config.json <<'CFG'
{
  "nanocoder": {
    "systemPrompt": {
      "mode": "append",
      "file": "./.nanocoder/role-backend-developer.md"
    },
    "alwaysAllow": ["read_file", "search_file_contents", "find_files"]
  }
}
CFG

nanocoder --plain --json --trust-directory run "Implement the task"
```

### ACP-стриминг (ближайший аналог Pi `--mode rpc`)

```bash
# Запуск как ACP-сервер (обычно спавнит редактор, но можно драйвить оркестратором)
nanocoder --acp --provider openrouter --model anthropic/claude-sonnet-4-20250514
# → JSON-RPC over stdin/stdout со стримингом, tool cards, diffs
```

### Plan-mode (read-only ревью)

```bash
nanocoder --mode plan --plain --json --trust-directory \
  run "Review PR #123 for security issues" 2>/dev/null | jq .finalText
```

---

## Приложение Б. Сравнение с Pi/omp (local-first) и OpenClaw (лёгкость)

### vs Pi / omp (local-first-фокус)

| Критерий | Pi/omp | Nanocoder |
|----------|--------|-----------|
| Тип | CLI-coding agent (Rust-core для omp) | CLI-coding agent (TS/React+Ink) |
| Local-first | ✅ Ollama/LM Studio/vLLM через `models.json` | ✅ **Native** — 8 локальных runner'ов first-class |
| BYOM-принцип | BYOK (опция) | **BYOM (идеология)** — core positioning |
| JSON-стриминг | `--mode json` (JSONL события) | `--json` (финальный объект) + ACP (стриминг) |
| Ephemeral | `--no-session` | ❌ Нет |
| System prompt (CLI) | `--system-prompt` + `--append-system-prompt` | ❌ Только config |
| Skills standard | `SKILL.md` (agentskills.io) | ❌ Собственный `.nanocoder/skills/` |
| `.agents/skills/` | ✅ | ❌ |
| Telemetry в JSON | ✅ usage + cost | ❌ Нет usage/cost в `--json` |
| Скиллы per-role | `--skill` + `--no-skills` | ❌ Все глобальны |
| Провайдеры | 20+ (omp 40+) | 25+ (вкл. 8 local) |

**Вывод:** Nanocoder сильнее в **pure-local/offline** сценариях (native Ollama/llama.cpp, нет telemetry, community non-profit), но слабее в **subagent-оркестрации** (нет стриминга JSONL, нет ephemeral, нет CLI prompt-injection, нет `SKILL.md`-стандарта).

### vs OpenClaw (лёгкость / блокер K6)

| Критерий | OpenClaw | Nanocoder |
|----------|----------|-----------|
| Архитектура | Gateway daemon + RPC-клиент | **Автономный CLI-процесс** |
| JSON-режим | ❌ Нет (gateway RPC, plain text) | ✅ `--json` (финальный объект) + ACP |
| Headless run | ❌ Требует gateway | ✅ `nanocoder run` автономно |
| Ephemeral | ❌ | ❌ |
| CLI prompt injection | ❌ | ❌ (config-only) |
| `.agents/skills/` | ✅ workspace-scoped | ❌ |
| Провайдеры | 40+ | 25+ |
| Вердикт | ❌ 4/10 (блокер K6) | ⚠️ 7/10 (K6 очищен) |

**Вывод:** Nanocoder **существенно превосходит OpenClaw** для сабагент-интеграции: автономный CLI-процесс (не требует gateway), реальный `--json` headless-режим, turn-лимиты. Блокер K6 (отсутствие JSON-режима), утопивший OpenClaw, **отсутствует**.

---

## Источники

1. [Nanocoder — GitHub (Nano-Collective/nanocoder)](https://github.com/Nano-Collective/nanocoder) — README, структура репозитория, 2.3k★
2. [@nanocollective/nanocoder — npm](https://www.npmjs.com/package/@nanocollective/nanocoder) — v1.29.0, MIT, dependencies
3. [docs.nanocollective.org/nanocoder — Getting Started / CLI Options](https://raw.githubusercontent.com/Nano-Collective/nanocoder/main/docs/getting-started/index.md) — `--json`, `--plain`, `--acp`, `run`, `--mode`, `--trust-directory`
4. [docs — Commands Reference](https://raw.githubusercontent.com/Nano-Collective/nanocoder/main/docs/features/commands.md) — Non-Interactive Mode, JSON Output shape
5. [docs — Features / Skills](https://raw.githubusercontent.com/Nano-Collective/nanocoder/main/docs/features/skills.md) — `.nanocoder/skills/`, `skill.yaml`, event subscriptions
6. [docs — Features / Subagents](https://raw.githubusercontent.com/Nano-Collective/nanocoder/main/docs/features/subagents.md) — isolated context, per-agent model/tools, parallel execution
7. [docs — Features / ACP](https://raw.githubusercontent.com/Nano-Collective/nanocoder/main/docs/features/acp.md) — JSON-RPC over stdin/stdout streaming
8. [docs — Configuration](https://raw.githubusercontent.com/Nano-Collective/nanocoder/main/docs/configuration/index.md) — `systemPrompt`, `headless.maxTurns`, `autoCompact`, `disabledTools`
9. [docs — Configuration / Preferences](https://raw.githubusercontent.com/Nano-Collective/nanocoder/main/docs/configuration/preferences.md) — session settings, preferences
10. [docs — Configuration / Providers](https://raw.githubusercontent.com/Nano-Collective/nanocoder/main/docs/configuration/providers/index.md) — 25+ провайдеров, local/cloud/native, BYOK
11. [LICENSE.md](https://raw.githubusercontent.com/Nano-Collective/nanocoder/main/LICENSE.md) — MIT License (Copyright (c) 2026 Nano Collective)
12. [AGENTS.md (repo)](https://raw.githubusercontent.com/Nano-Collective/nanocoder/main/AGENTS.md) — auto-generated project context, CLAUDE.md merge
