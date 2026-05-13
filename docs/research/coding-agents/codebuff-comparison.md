# Codebuff — Исследование для интеграции как сабагент

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-13
**Объект:** Codebuff v1.0.0 CLI + `@codebuff/sdk` v0.10.7 (`github.com/CodebuffAI/codebuff`, TypeScript/Bun)
**Задача:** [TASK-research-codebuff](../../../todo/TASK-research-codebuff.todo.md)

---

## Сводка

Codebuff — мультиагентный AI-кодинг-ассистент (TypeScript, Apache-2.0, ~5k stars). Ключевая особенность: координирует специализированные агенты (File Picker → Planner → Editor → Reviewer) вместо одной модели. Предоставляет CLI (TUI-интерфейс на React/OpenTUI) и SDK (`@codebuff/sdk`) для программного управления. Кастомные агенты через TypeScript-определения в `.agents/`. Freebuff — бесплатная ad-supported версия.

**Архитектура:** TypeScript monorepo (Bun workspaces): `cli/` → `sdk/` → `packages/agent-runtime/` → `common/`. LLM-вызовы через Vercel AI SDK. Провайдер по умолчанию — OpenRouter (все модели).

**Ключевой вывод:** CLI — только TUI, без headless/JSON-режима. SDK предоставляет богатые event types и программный контроль, но требует Node.js/Bun wrapper вместо bash-скрипта `watch-subagent.sh`. Интеграция возможна через SDK-обёртку.

---

## Критерий 1. Системный промпт

### CLI

Codebuff CLI **не предоставляет** флагов для управления системным промптом (`--system-prompt`, `--append-system-prompt`). CLI представляет собой интерактивный TUI на React/OpenTUI. Доступные CLI-флаги:

| Флаг | Назначение |
|------|-----------|
| `codebuff "prompt"` | Начальный промпт (передаётся как user message) |
| `--agent <agent-id>` | Запуск конкретного агента |
| `--lite / --max / --plan` | Режимы работы агента |
| `--continue [id]` | Продолжение предыдущей сессии |
| `--cwd <dir>` | Рабочая директория |

Нет флагов `--system-prompt`, `--append-system-prompt`, `--output-format json` или `--print`.

### SDK

SDK позволяет задать системный промпт через `AgentDefinition`:

```typescript
const myAgent: AgentDefinition = {
  id: 'custom-reviewer',
  displayName: 'Code Reviewer',
  model: 'anthropic/claude-sonnet-4.5',
  systemPrompt: 'Background information for the agent.',
  instructionsPrompt: 'Instructions inserted after each user input.',
  stepPrompt: 'Prompt inserted at each agent step.',
  toolNames: ['read_files', 'run_terminal_command', 'end_turn'],
}

const client = new CodebuffClient({ apiKey: '...', cwd: '/project' })
await client.run({
  agent: myAgent,
  prompt: 'Review the authentication module',
  agentDefinitions: [myAgent],
})
```

Поле `systemPrompt` — фоновая информация. `instructionsPrompt` — основная инструкция, вставляется после каждого user input. `stepPrompt` — вставляется на каждом шаге.

### Контекстные файлы (Knowledge Files)

Codebuff автоматически обнаруживает knowledge-файлы с приоритетом:

1. `knowledge.md` (наивысший приоритет)
2. `AGENTS.md`
3. `CLAUDE.md`

Поиск — case-insensitive, по всему дереву проекта + домашняя директория (`~/.knowledge.md`, `~/.AGENTS.md`, `~/.CLAUDE.md`). Также поддерживается паттерн `*.knowledge.md` (например, `auth.knowledge.md`).

### Сравнение с Pi (`--system-prompt`, `--append-system-prompt`)

Pi позволяет **полностью заменить** и **дополнить** системный промпт через CLI. Codebuff не имеет аналогичных CLI-флагов. SDK позволяет задать системный промпт через `systemPrompt` и `instructionsPrompt` в `AgentDefinition`.

### Оценка: ⚠️ Частичная поддержка

Нет CLI-флагов для управления системным промптом. SDK предоставляет полный контроль через `AgentDefinition`, но это требует программной интеграции, а не bash-скрипта.

---

## Критерий 2. Промпт агента / Роль

### Подход

Codebuff использует **систему кастомных агентов** через TypeScript-определения. Каждый агент — это файл в `.agents/` с типом `AgentDefinition`.

#### CLI

Флаг `--agent <agent-id>` позволяет запустить конкретного агента. Но нет механизма инъекции произвольного промпта роли через CLI.

#### SDK

Роль инжектируется через `AgentDefinition`:

```typescript
const backendDeveloper: AgentDefinition = {
  id: 'backend-dev',
  displayName: 'Backend Developer',
  model: 'anthropic/claude-sonnet-4.5',
  instructionsPrompt: `
    Ты — бэкенд-разработчик. Следуй DDD, Clean Architecture.
    Пиши код на PHP 8.4 / Symfony 8.0.
    // ... полный текст роли
  `,
  toolNames: ['read_files', 'write_file', 'str_replace', 'run_terminal_command', 'end_turn'],
  spawnableAgents: ['thinker', 'file_picker'],
}
```

#### Встроенные агенты

Codebuff поставляется с набором встроенных агентов:

| Агент | Назначение |
|-------|-----------|
| `base2` | Основной агент (base2, base2-max, base2-plan) |
| `editor` | Специализированный редактор с best-of-N |
| `file-explorer` | File Picker, Code Searcher, Directory Lister |
| `thinker` | Глубокое рассуждение |
| `reviewer` | Code review |
| `researcher` | Web search и docs search |
| `basher` | Выполнение терминальных команд |
| `context-pruner` | Сжатие контекста при превышении лимита |

#### `/init` команда

Команда `/init` в CLI создаёт стартовую структуру для кастомных агентов:

```
knowledge.md               # Контекст проекта
.agents/
└── types/                 # TypeScript type definitions
    ├── agent-definition.ts
    ├── tools.ts
    └── util-types.ts
```

### Изоляция ролей

✅ Полная изоляция: каждый агент имеет свой `id`, свою модель, свой набор инструментов, свой промпт. Агенты не пересекаются.

### Ограничения

- Нет CLI-флага для инъекции роли (в отличие от Pi `--append-system-prompt`)
- Роль определяется только через TypeScript-файлы `.agents/*.ts` или через SDK `agentDefinitions`

### Оценка: ⚠️ Частичная поддержка

Кастомные агенты через TypeScript — мощный механизм, но нет CLI-инъекции роли. Для сабагентной интеграции потребуется создавать `.agents/*.ts` файлы или передавать `agentDefinitions` через SDK.

---

## Критерий 3. Скиллы

### Поддержка Agent Skills standard

Codebuff реализует **Agent Skills standard** (agentskills.io) — скиллы в формате `<skill-name>/SKILL.md` с YAML frontmatter.

```yaml
---
name: my-skill
description: Description of the skill
license: MIT
metadata:
  key: value
---

# Skill content (Markdown)
```

### Загрузка скиллов

Функция `loadSkills()` ищет скиллы в директориях (последующие переопределяют предыдущие):

1. `~/.claude/skills/` (глобальные, Claude Code совместимые)
2. `~/.agents/skills/` (глобальные)
3. `{cwd}/.claude/skills/` (проектные, Claude Code совместимые)
4. `{cwd}/.agents/skills/` (проектные, наивысший приоритет)

SDK опция `skillsDir` позволяет указать произвольную директорию:

```typescript
const client = new CodebuffClient({
  apiKey: '...',
  cwd: '/project',
  skillsDir: '/path/to/skills',
})
```

### CLI управление скиллами

❌ Нет CLI-флагов для управления скиллами (`--skill`, `--no-skills`). Скиллы загружаются автоматически из стандартных директорий.

### Инструмент `skill`

Агенты имеют доступ к инструменту `skill`, который загружает скилл по запросу (lazy loading). Список доступных скиллов включается в описание инструмента.

### Разным ролям — разные скиллы

⚠️ Через SDK — каждый `AgentDefinition` может иметь свой набор инструментов, но скиллы загружаются глобально на уровне сессии. Фильтрация скиллов per-agent не предусмотрена.

### Оценка: ⚠️ Частичная поддержка

Agent Skills standard поддерживается полностью. Но нет CLI-управления скиллами и нет per-agent фильтрации. Глобальная загрузка скиллов ограничивает гибкость при мультиролевой интеграции.

---

## Критерий 4. AGENTS.md (контекстные файлы)

### Автообнаружение

✅ **Полная поддержка.** Codebuff автоматически обнаруживает knowledge-файлы:

| Файл | Приоритет | Область поиска |
|------|-----------|---------------|
| `knowledge.md` | Наивысший | Проект + домашняя директория |
| `AGENTS.md` | Средний | Проект + домашняя директория |
| `CLAUDE.md` | Низший | Проект + домашняя директория |
| `*.knowledge.md` | — | Проект (паттерн) |

Поиск case-insensitive (`KNOWLEDGE.md`, `knowledge.md`, `Knowledge.md` — все валидны).

### Механизм

Knowledge-файлы внедряются в session state как `knowledgeFiles`. Они доступны агенту через контекст на протяжении всей сессии.

### Сравнение с Pi

Pi обнаруживает `AGENTS.md` и `.pi/SYSTEM.md`. Codebuff обнаруживает оба + `CLAUDE.md` + `knowledge.md`. Совместимость с Claude Code по `CLAUDE.md`.

### Отключение через CLI

❌ Нет CLI-флага для отключения загрузки knowledge-файлов.

### Оценка: ✅ Полная поддержка

AGENTS.md обнаруживается автоматически с корректным приоритетом. Поддержка CLAUDE.md обеспечивает совместимость с Claude Code. Дополнительный паттерн `*.knowledge.md` даёт гибкость.

---

## Критерий 5. `.agents/skills/` автосканирование

### Поддержка

✅ **Полная поддержка.** `loadSkills()` автоматически сканирует 4 директории:

```typescript
function getDefaultSkillsDirs(cwd: string): string[] {
  return [
    path.join(home, '.claude', 'skills'),      // Глобальные Claude
    path.join(home, '.agents', 'skills'),       // Глобальные Codebuff
    path.join(cwd, '.claude', 'skills'),        // Проектные Claude
    path.join(cwd, '.agents', 'skills'),        // Проектные Codebuff
  ]
}
```

### Подключение `docs/agents/skills/`

Через symlink `.agents/skills/ → docs/agents/skills/` — стандартный подход.

Через SDK:

```typescript
const client = new CodebuffClient({
  skillsDir: 'docs/agents/skills',
})
```

### Встроенные скиллы

В репозитории Codebuff есть примеры скиллов: `.agents/skills/cleanup/`, `.agents/skills/meta/`, `.agents/skills/review/`.

### Оценка: ✅ Полная поддержка

Стандартные локации `.agents/skills/` и `.claude/skills/` сканируются автоматически. SDK позволяет указать произвольную директорию. Симлинк для подключения `docs/agents/skills/` работает из коробки.

---

## Критерий 6. Запуск как сабагент (JSON-режим)

### CLI

❌ **Нет JSON/JSONL-режима в CLI.** Codebuff CLI — интерактивный TUI на React/OpenTUI. Нет флагов:

- ❌ `--mode json`
- ❌ `--output-format stream-json`
- ❌ `--print`
- ❌ `--json`

### SDK — программный интерфейс

SDK предоставляет полноценный программный интерфейс с богатой системой событий:

```typescript
const client = new CodebuffClient({
  apiKey: 'your-api-key',
  cwd: '/project',
})

const result = await client.run({
  agent: 'base', // или кастомный AgentDefinition
  prompt: 'Fix the SQL injection vulnerability',
  maxAgentSteps: 20,
  signal: AbortSignal.timeout(120_000), // Таймаут через AbortSignal
  handleEvent: (event) => {
    // Structured events
    switch (event.type) {
      case 'start':
        console.log('Agent started', event.agentId)
        break
      case 'text':
        console.log('Text:', event.text)
        break
      case 'tool_call':
        console.log('Tool:', event.toolName, event.input)
        break
      case 'tool_result':
        console.log('Result:', event.toolName, event.output)
        break
      case 'subagent_start':
        console.log('Subagent:', event.agentType, event.displayName)
        break
      case 'subagent_finish':
        console.log('Subagent done:', event.agentType)
        break
      case 'finish':
        console.log('Cost:', event.totalCost)
        break
      case 'error':
        console.error('Error:', event.message)
        break
    }
  },
  handleStreamChunk: (chunk) => {
    if (typeof chunk === 'string') {
      process.stdout.write(chunk) // Raw text streaming
    } else if (chunk.type === 'subagent_chunk') {
      // Subagent text stream
    } else if (chunk.type === 'reasoning_chunk') {
      // Reasoning stream
    }
  },
})

// result: RunState = { sessionState, output }
// output: { type: 'lastMessage' | 'allMessages' | 'structuredOutput' | 'error', value }
```

### Типы событий (PrintModeEvent)

| Событие | Поля | Описание |
|---------|------|---------|
| `start` | `agentId`, `messageHistoryLength` | Старт агента |
| `text` | `text`, `agentId` | Текстовый вывод |
| `tool_call` | `toolCallId`, `toolName`, `input`, `agentId`, `parentAgentId` | Вызов инструмента |
| `tool_result` | `toolCallId`, `toolName`, `output`, `parentAgentId` | Результат инструмента |
| `subagent_start` | `agentId`, `agentType`, `displayName`, `onlyChild`, `params`, `prompt` | Старт сабагента |
| `subagent_finish` | `agentId`, `agentType`, `displayName` | Завершение сабагента |
| `reasoning_delta` | `text`, `ancestorRunIds`, `runId` | Поток рассуждений |
| `finish` | `agentId`, `totalCost` | Завершение с стоимостью |
| `error` | `message` | Ошибка |

### Таймауты

Через `AbortSignal`:

```typescript
const controller = new AbortController()
setTimeout(() => controller.abort(), 120_000) // 2 минуты

await client.run({
  signal: controller.signal,
  // ...
})
```

### Сессии и продолжение

`RunState` от предыдущего вызова можно передать как `previousRun` для продолжения сессии:

```typescript
const result1 = await client.run({ prompt: 'Add auth', ... })
const result2 = await client.run({ prompt: 'Now add tests', previousRun: result1, ... })
```

### Structured Output

`AgentDefinition.outputMode` поддерживает:

- `'last_message'` (default) — последнее сообщение агента
- `'all_messages'` — все сообщения, включая tool calls
- `'structured_output'` — JSON объект по `outputSchema`

### Ограничения для сабагентной интеграции

1. **Нет CLI JSON-режима** — нельзя обернуть через `watch-subagent.sh` (bash)
2. **Требуется Node.js/Bun wrapper** — SDK работает только в JS/TS runtime
3. **API key обязателен** — нет anonymous/ephemeral режима

### Практический пример wrapper (Node.js)

```typescript
// codebuff-wrapper.ts
import { CodebuffClient } from '@codebuff/sdk'

const role = process.env.ROLE_FILE
  ? await import(process.env.ROLE_FILE)
  : null

const client = new CodebuffClient({
  apiKey: process.env.CODEBUFF_API_KEY!,
  cwd: process.env.PROJECT_DIR!,
  skillsDir: process.env.SKILLS_DIR,
})

const controller = new AbortController()
const timeout = parseInt(process.env.TIMEOUT_S || '120')
setTimeout(() => controller.abort(), timeout * 1000)

const result = await client.run({
  agent: role?.default ?? 'base',
  prompt: await readStdin(),
  agentDefinitions: role ? [role.default] : undefined,
  maxAgentSteps: 20,
  signal: controller.signal,
  handleEvent: (event) => {
    process.stdout.write(JSON.stringify(event) + '\n')
  },
})

process.stdout.write(JSON.stringify({ type: 'result', output: result.output }) + '\n')
```

### Оценка: ⚠️ Частичная поддержка

SDK предоставляет богатые структурированные события (tool calls, subagent tracking, cost, reasoning). Но нет CLI JSON/JSONL-режима. Интеграция с `watch-subagent.sh` невозможна — требуется Node.js/Bun wrapper. Это существенно отличает Codebuff от Pi, Qwen Code и Claude Code, которые поддерживают JSON-режим через CLI.

---

## Критерий 7. Токены и стоимость

### SDK

Событие `finish` содержит `totalCost: number`:

```typescript
handleEvent: (event) => {
  if (event.type === 'finish') {
    console.log('Total cost:', event.totalCost)
  }
}
```

### Session State

В `AgentState` отслеживаются:

- `contextTokenCount: number` — обновляется на каждом шаге через `/api/v1/token-count`
- `creditsUsed: number` — кредиты, использованные агентом
- `directCreditsUsed: number` — прямые кредиты

### Разбивка по моделям

❌ Нет per-model разбивки в SDK events. Модели маршрутизируются сервером (OpenRouter → конкретный провайдер). Детальная статистика — на серверной стороне (BigQuery `traces` таблица).

### В CLI

Команда `/usage` в CLI показывает использование кредитов. CLI отслеживает `totalCost` и отображает пользователю.

### Ограничения

- Нет детальной разбивки input/output/cache токенов в SDK events
- Стоимость выражается в кредитах Codebuff, не в USD напрямую (кроме `totalCost`)
- `totalCost` — единое число без разбивки по моделям

### Оценка: ⚠️ Частичная поддержка

`totalCost` в событии `finish` и `contextTokenCount` в session state дают базовую телеметрию. Но нет детальной разбивки по моделям, input/output/cache токенам, стоимости в USD. Существенно беднее, чем Pi (`cost: {input, output, cacheRead, cacheWrite}`).

---

## Критерий 8. Free tier / стоимость

### Codebuff

| Параметр | Значение |
|----------|---------|
| Подписка | Кредитная система |
| Бесплатные кредиты | 500 при регистрации |
| Срок действия кредитов | Бессрочно |
| BYOK | Через OpenRouter API key |
| Claude OAuth | Бесплатно (свой Claude subscription) |
| ChatGPT OAuth | Бесплатно (свой ChatGPT subscription) |

### Freebuff

Отдельный npm-пакет `freebuff` — бесплатная ad-supported версия:

- Нет подписки, кредитов, конфигурации
- Модели: DeepSeek V4 Pro, Kimi K2.6, MiniMax M2.7, Gemini 3.1 Flash Lite
- Реклама в CLI
- Ограничен по странам
- Отдельный бинарник и веб-приложение

### BYOK

Пользователь может подключить:

1. **OpenRouter API key** — доступ ко всем моделям OpenRouter
2. **Claude OAuth** — прямое подключение Claude subscription (без траты Codebuff кредитов)
3. **ChatGPT OAuth** — прямое подключение ChatGPT subscription

### Ollama / LM Studio

❌ Нет прямой поддержки локальных моделей. Codebuff маршрутизирует через OpenRouter или прямые API провайдеров. Для локальных моделей потребуется OpenRouter-compatible proxy или форк.

### Оценка: ⚠️ Частичная поддержка

Freebuff — полностью бесплатный. Codebuff — кредитная система с 500 бесплатными кредитами. BYOK через OpenRouter и OAuth подключения. Но нет прямой поддержки Ollama/LM Studio.

---

## Критерий 9. Провайдеры и модели

### Основной провайдер: OpenRouter

Codebuff использует **OpenRouter** как основной маршрутизатор моделей. Это даёт доступ ко **всем моделям**, доступным на OpenRouter (сотни моделей).

### Прямые подключения

| Провайдер | Механизм | Модели |
|-----------|---------|--------|
| **OpenRouter** | API key / Codebuff backend | Все модели OpenRouter |
| **Anthropic** | Claude OAuth | Все Claude модели |
| **OpenAI** | ChatGPT OAuth | Все GPT модели |
| **Fireworks AI** | Server-side routing | Поддерживаемые модели |
| **SiliconFlow** | Server-side routing | Поддерживаемые модели |
| **CanopyWave** | Server-side routing | Поддерживаемые модели |

### Доступные модели (через AgentDefinition)

В type definitions перечислены рекомендованные модели:

**OpenAI:** gpt-5.3, gpt-5.3-codex, gpt-5.2, gpt-5.1, gpt-5-mini, gpt-5-nano

**Anthropic:** claude-sonnet-4.6, claude-opus-4.7, claude-opus-4.6, claude-haiku-4.5, claude-sonnet-4.5

**Google:** gemini-3.1-pro, gemini-3-pro, gemini-3-flash, gemini-2.5-pro, gemini-2.5-flash

**xAI:** grok-4-fast, grok-4.1-fast, grok-code-fast-1

**Qwen:** qwen3-max, qwen3-coder-plus, qwen3-coder, qwen3-coder-flash

**DeepSeek:** deepseek-v4-pro, deepseek-v4-flash, deepseek-r1-0528

**Другие:** kimi-k2, kimi-k2.6, glm-5, glm-4.7, minimax-m2.7

Полный список — на [openrouter.ai/models](https://openrouter.ai/models).

### Per-agent модель

✅ Каждый `AgentDefinition` может указать свою модель:

```typescript
const filePicker: AgentDefinition = {
  id: 'file-picker',
  model: 'google/gemini-2.5-flash', // Быстрая модель для поиска файлов
  // ...
}

const coder: AgentDefinition = {
  id: 'coder',
  model: 'anthropic/claude-sonnet-4.5', // Мощная модель для кодинга
  // ...
}
```

### Provider Options

`AgentDefinition.providerOptions` позволяет контролировать маршрутизацию OpenRouter:

```typescript
providerOptions: {
  order: ['anthropic', 'openai'],
  allow_fallbacks: true,
  data_collection: 'deny',
  sort: 'latency',
  max_price: { completion: 0.01 },
}
```

### Локальные модели

❌ Нет прямой поддержки Ollama, LM Studio, vLLM. Требуется OpenRouter-совместимый прокси или форк SDK.

### Оценка: ⚠️ Частичная поддержка

OpenRouter даёт доступ к 100+ моделям через единый API. Per-agent модель — мощная функция для мультиагентной архитектуры. Но нет прямой поддержки локальных моделей. Общее количество провайдеров: 5+ через server-side routing + все через OpenRouter.

---

## Критерий 10. Лицензия

### Codebuff

| Параметр | Значение |
|----------|---------|
| Лицензия | Apache-2.0 |
| Исходный код | ✅ Полностью открытый (TypeScript) |
| Форк | ✅ Допускается |
| Коммерческое использование | ✅ Допускается |
| Attribution | Требуется (NOTICE файл) |
| Vendor lock-in | ⚠️ Зависимость от Codebuff backend для LLM routing |

### Freebuff

Лицензия: MIT (указана в README).

### Монорепозиторий

Все компоненты в одном репозитории: `cli/`, `sdk/`, `web/`, `agents/`, `common/`. Можно полностью self-host (Next.js web server + PostgreSQL + OpenRouter key).

### Зависимость от Codebuff Cloud

⚠️ SDK и CLI требуют API key от Codebuff. LLM-запросы маршрутизируются через Codebuff backend (`POST /api/v1/chat/completions`). Это создаёт зависимость от Codebuff cloud, хотя исходный код сервера открыт и можно self-host.

### Оценка: ✅ Полная поддержка

Apache-2.0 — пермиссивная open-source лицензия. Исходный код полностью открыт, включая серверную часть. Self-host возможен. Единственная оговорка — практическая зависимость от Codebuff cloud для LLM routing.

---

## Дополнение. Мультиагентная архитектура

### Паттерн: File Picker → Planner → Editor → Reviewer

Codebuff реализует паттерн **специализированных агентов**, где каждый этап обработки выполняется отдельным агентом с оптимальной моделью:

```
┌─────────────┐     ┌───────────┐     ┌──────────┐     ┌──────────┐
│ File Picker │────▶│  Planner  │────▶│  Editor  │────▶│ Reviewer │
│ (fast model)│     │ (strong)  │     │ (strong) │     │ (fast)   │
└─────────────┘     └───────────┘     └──────────┘     └──────────┘
```

Координация через инструмент `spawn_agents` — родительский агент запускает дочерних:

```typescript
// Внутри базового агента (упрощённо):
yield {
  toolName: 'spawn_agents',
  input: {
    agents: [
      { agent_type: 'file_picker', prompt: 'Find auth-related files' },
    ],
  },
}
```

### handleSteps — программное управление

`AgentDefinition.handleSteps` — TypeScript generator function, позволяющая программно контролировать шаги агента:

```typescript
handleSteps: function* ({ agentState, prompt, logger }) {
  // Шаг 1: Получить diff
  yield { toolName: 'run_terminal_command', input: { command: 'git diff' } }
  
  // Шаг 2: Дать модели обработать
  yield 'STEP_ALL'
  
  // Шаг 3: Установить результат
  yield { toolName: 'set_output', input: { output: 'Done' } }
}
```

Этот паттерн — **mix of AI generation with programmatic control** — уникальная особенность Codebuff, отсутствующая у других исследованных агентов.

### Agent Store

Codebuff имеет реестр опубликованных агентов ([codebuff.com/store](https://www.codebuff.com/store)). Агенты публикуются через CLI команду `codebuff publish <agent-id>`.

### Оценка паттерна

Мультиагентный подход Codebuff **интересен как паттерн** для task-orchestrator:

| Аспект | Codebuff | task-orchestrator |
|--------|---------|-------------------|
| Координация агентов | `spawn_agents` tool | Chain Execution |
| Выбор модели per-agent | `AgentDefinition.model` | Роль → выбор провайдера |
| Программное управление | `handleSteps` generator | Нет (декларативный) |
| Изоляция агентов | Полная (отдельный context) | Полная (отдельный subprocess) |

---

## Итоговая таблица

| # | Критерий | Оценка | Обоснование |
|---|---------|--------|-------------|
| 1 | Системный промпт | ⚠️ (2) | SDK: `systemPrompt`/`instructionsPrompt`. Нет CLI-флагов. |
| 2 | Роль агента | ⚠️ (2) | Кастомные агенты через TypeScript. `--agent` CLI. Нет `--append-system-prompt`. |
| 3 | Скиллы | ⚠️ (2) | Agent Skills standard. Нет `--skill` CLI. Нет per-agent фильтрации. |
| 4 | AGENTS.md | ✅ (3) | Автообнаружение: knowledge.md > AGENTS.md > CLAUDE.md. Case-insensitive. |
| 5 | `.agents/skills/` | ✅ (3) | Автосканирование `.agents/skills/` + `.claude/skills/`. SDK `skillsDir`. |
| 6 | JSON-режим | ⚠️ (2) | SDK: богатые события. CLI: нет JSON/JSONL. Требуется Node.js wrapper. |
| 7 | Токены/стоимость | ⚠️ (2) | `totalCost` в finish, `contextTokenCount`. Нет детальной разбивки. |
| 8 | Free tier | ⚠️ (2) | Freebuff бесплатный, Codebuff кредиты (500 free). BYOK через OpenRouter. Нет Ollama. |
| 9 | Провайдеры | ⚠️ (2) | OpenRouter (100+ моделей) + OAuth. Нет локальных моделей напрямую. |
| 10 | Лицензия | ✅ (3) | Apache-2.0. Полный исходный код. Self-host возможен. |
| | **Сумма** | **23/30** | |

---

## Вердикт

### ⚠️ Частично подходит (6/10)

**Обоснование:**

Codebuff — мощный мультиагентный кодинг-ассистент с уникальной архитектурой (File Picker → Planner → Editor → Reviewer), богатым SDK и поддержкой Agent Skills standard. Однако **для сценария сабагентной интеграции через bash-wrapper (watch-subagent.sh) он не подходит** из-за отсутствия CLI JSON/JSONL-режима.

| Преимущество | Описание |
|-------------|----------|
| Богатый SDK | Structured events, streaming, subagent tracking, AbortSignal |
| Agent Skills standard | Совместимость с нашей системой скиллов |
| AGENTS.md + CLAUDE.md | Автообнаружение из коробки |
| `.agents/skills/` | Сканирование стандартных локаций |
| Per-agent модель | Разным агентам — разные модели |
| handleSteps | Программное управление агентами |
| Apache-2.0 | Open source, self-host |
| OpenRouter | Доступ к 100+ моделям |

| Ограничение | Описание |
|------------|---------|
| ❌ Нет CLI JSON-режима | Невозможно интегрировать через `watch-subagent.sh` |
| ❌ Нет `--system-prompt` CLI | Инъекция роли только через SDK/TypeScript |
| ❌ Нет `--skill` CLI | Управление скиллами только программно |
| ❌ Нет Ollama/LM Studio | Зависимость от cloud-провайдеров |
| ⚠️ Codebuff Cloud | Практическая зависимость от backend для LLM routing |
| ⚠️ Требуется Node.js wrapper | Дополнительный слой интеграции |

### Альтернативный путь интеграции

Вместо bash-wrapper можно создать **Node.js adapter**:

```typescript
// bin/codebuff-adapter.ts
// Читает stdin → вызывает CodebuffClient.run() → пишет JSONL в stdout
// API-совместим с watch-subagent.sh по формату событий
```

Это потребует:
1. Установку `@codebuff/sdk` в проект
2. Node.js/Bun runtime в среде выполнения
3. Codebuff API key (или OpenRouter key)

### Рекомендация

Codebuff представляет интерес как **архитектурный референс** (мультиагентная координация, handleSteps, Agent Store), но **не рекомендуется для приоритетной интеграции** как сабагент. CLI без JSON-режима — критическое ограничение.

**Приоритет интеграции:** Tier 4 (исследовательский) — для будущей мультиагентной оркестрации через SDK, не через bash-wrapper.

---

## Источники

1. [Codebuff GitHub Repository](https://github.com/CodebuffAI/codebuff) — исходный код, README, docs/
2. [Codebuff Website](https://codebuff.com) — pricing, features, Agent Store
3. [Codebuff SDK on npm](https://www.npmjs.com/package/@codebuff/sdk) — SDK package
4. [Freebuff on npm](https://www.npmjs.com/package/freebuff) — Free version
5. [OpenRouter Models](https://openrouter.ai/models) — поддерживаемые модели
