# Factory Droid — Исследование для интеграции как сабагент

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-10
**Объект:** Factory Droid CLI v0.25.1 (proprietary, Factory AI)
**Задача:** [TASK-research-droid-agent](../../../todo/TASK-research-droid-agent.todo.md)

---

## Сводка

Factory Droid — проприетарный AI-агент кодинга от компании Factory AI (https://factory.ai). Позиционируется как «AI-powered development agent for your terminal». Версия 0.25.1. CLI-утилита `droid` с поддержкой кастомных агентов («droids»), скиллов, плагинов, MCP, BYOK. Запускается интерактивно или через `droid exec` для неинтерактивного использования в CI/CD. Лицензия проприетарная, платный начиная с $20/мес.

---

## Критерий 1. Системный промпт

### Возможности

Droid **не имеет** CLI-флагов для прямой замены или дополнения системного промпта (в отличие от Pi: `--system-prompt`, `--append-system-prompt`). Вместо этого используется система конфигурации и контекстных файлов.

| Механизм | Поведение |
|----------|-----------|
| `.factory/settings.json` | Глобальные и per-project настройки агента |
| `AGENTS.md` | Проектные инструкции, автоматически подгружаются (см. Критерий 4) |
| Custom Droids (файлы `.md`) | Специализированные подагенты с собственным промптом, моделью и правами (см. Критерий 2) |
| Hooks | Shell-команды для кастомизации поведения в определённые моменты |

### Примеры

Для программного управления контекстом агента через `droid exec` можно:
1. Поместить инструкции в `AGENTS.md` проекта (автозагрузка)
2. Создать Custom Droid с нужным системным промптом (файл markdown с frontmatter)
3. Использовать плагины (oh-my-droid и др.) для инъекции контекста

### Ограничения

Невозможно инлидить системный промпт через CLI-флаг (`--system-prompt` не поддерживается). Контекст роли необходимо передавать через:
- Пользовательское сообщение в `droid exec` (аналог user-prompt)
- Custom Droid файл
- AGENTS.md

### Оценка: ⚠️ Частичная поддержка

Нет прямого CLI-контроля над системным промптом. Кастомизация возможна через конфигурационные файлы и Custom Droids, но это менее гибко, чем у Pi с его `--system-prompt` / `--append-system-prompt`.

---

## Критерий 2. Промпт агента / Роль

### Custom Droids

Droid поддерживает **Custom Droids** — специализированные подагенты с собственными промптами, моделями и правами доступа к инструментам. Описание: «Create specialized subagents with their own prompts, tool access, and models that droid can delegate work to».

Формат Custom Droid — Markdown файл с YAML frontmatter:

```markdown
---
name: architect
description: Strategic Architecture & Debugging Advisor (Opus, READ-ONLY)
model: claude-opus-4-5-20251101
disallowedTools: Write, Edit
---

<Role>
Oracle - Strategic Architecture & Debugging Advisor
...
</Role>
```

### Поля Frontmatter

| Поле | Описание |
|------|----------|
| `name` | Идентификатор droid'а |
| `description` | Краткое описание (используется для делегирования) |
| `model` | Модель по умолчанию (например, `claude-opus-4-5-20251101`) |
| `disallowedTools` | Запрещённые инструменты (ограничение прав) |

### Делегирование

В интерактивном режиме Droid может делегировать задачи custom droids на основе их описаний. Oh-my-droid plugin расширяет это до 32 специализированных агентов с 3-уровневой маршрутизацией моделей (Haiku/Sonnet/Opus).

### Ограничения для сабагент-использования

При запуске через `droid exec` нет CLI-флага для указания конкретного Custom Droid. Роль можно инъектировать только через:
1. Пользовательское сообщение (user prompt)
2. AGENTS.md / контекстные файлы проекта
3. Настройки в `.factory/`

### Оценка: ⚠️ Частичная поддержка

Custom Droids — мощная система для интерактивного использования. Но для программного запуска как сабагент нет прямого CLI-флага для выбора роли или инъекции контекста роли.

---

## Критерий 3. Скиллы

### Возможности

Droid имеет встроенную систему скиллов. Документация: «Reusable capabilities that your AI agent invokes on demand».

| Механизм | Поведение |
|----------|-----------|
| `.agents/skills/` в проекте | Автосканирование скиллов по Agent Skills standard |
| `~/.factory/skills/` | Глобальные скиллы пользователя |
| Плагины | Плагины могут поставлять скиллы (`"skills": "./skills/"` в `plugin.json`) |
| SKILL.md формат | Agent Skills standard (frontmatter: name, description + тело промпта) |

### Формат скилла

```markdown
---
name: analyze
description: Deep analysis and investigation
---

# Deep Analysis Mode

[ANALYSIS MODE ACTIVATED]

## Objective
Conduct thorough analysis...
```

### Пример: oh-my-droid plugin

Oh-my-droid поставляет 37 скиллов, включая:
- `autopilot` — автономное выполнение
- `ultrawork` — параллелизм
- `ralph` — персистентное выполнение
- `ecomode` — экономия токенов
- `deepsearch` — глубокий поиск
- `code-review` — ревью кода
- `security-review` — security аудит

### Разные скиллы разным ролям

Через систему плагинов и Custom Droids можно управлять доступными скиллами. Однако нет CLI-управления (`--skill` флага) для `droid exec`.

### Оценка: ⚠️ Частичная поддержка

Скиллы поддерживаются (Agent Skills standard, автосканирование), но нет CLI-флага для управления ими при программном запуске через `droid exec`. Управление — только через файловую конфигурацию.

---

## Критерий 4. AGENTS.md (контекстные файлы)

### Возможности

Droid имеет **полноценную поддержку** AGENTS.md. Документация: «Teach agents everything they need to know about your project with a single Markdown file» (docs.factory.ai/cli/configuration/agents-md).

| Механизм | Поведение |
|----------|-----------|
| `AGENTS.md` в корне проекта | Автообнаружение и загрузка при старте |
| `.factory/settings.json` | Настройки, связанные с контекстом |
| Custom Droids | Дополнительный контекст через frontmatter + тело файла |

### Наш проект

При запуске из корня `/home/dp/MyProjects/task-orchestrator/` Droid автоматически загрузит наш `AGENTS.md`, что обеспечит модели доступ к конвенциям проекта.

### Оценка: ✅ Полная поддержка

AGENTS.md поддерживается из коробки, аналогично Pi и другим агентам.

---

## Критерий 5. Стандартная папка `.agents/skills/`

### Поддержка

Droid поддерживает автосканирование `.agents/skills/` по Agent Skills standard. Подтверждается:
1. Официальной документацией по skills (docs.factory.ai/cli/configuration/skills)
2. Форматом SKILL.md в oh-my-droid plugin
3. `plugin.json` с полем `"skills": "./skills/"`
4. Интеграцией в droid-acp и oh-my-droid

### Наша структура

Наши скиллы лежат в `docs/agents/skills/`, а не в `.agents/skills/`. Для Droid потребуется либо:
1. Создать symlink `.agents/skills/ → docs/agents/skills/`
2. Настроить через `.factory/settings.json`
3. Установить плагин с нужными скиллами

### Оценка: ⚠️ Поддерживается с настройкой

Стандарт `.agents/skills/` поддерживается из коробки. Наша структура `docs/agents/skills/` требует настройки (symlink или конфигурация).

---

## Критерий 6. Запуск как сабагент (JSON-режим)

### `droid exec`

Droid имеет специальный `droid exec` режим для неинтерактивного запуска. Документация: «Non-interactive execution mode for CI/CD pipelines and automation scripts».

| Опция | Поведение |
|-------|-----------|
| `droid exec --output-format json "prompt"` | JSON-вывод результата |
| `droid exec --input-format stream-jsonrpc --output-format stream-jsonrpc` | Полноценный JSON-RPC протокол через stdin/stdout |
| `--cwd <path>` | Рабочая директория |
| `--session-id <id>` | Возобновление сессии |
| `--reasoning-effort <level>` | Уровень reasoning (low/medium/high) |

### JSON-RPC протокол

При использовании `stream-jsonrpc` Droid общается через stdin/stdout по JSON-RPC 2.0 с Factory-расширениями:

```typescript
// Запрос к Droid
{
  "jsonrpc": "2.0",
  "factoryApiVersion": "1.0.0",
  "type": "request",
  "method": "...",
  "params": { ... },
  "id": "uuid"
}

// Ответ от Droid
{
  "jsonrpc": "2.0",
  "type": "response",
  "factoryApiVersion": "1.0.0",
  "id": "uuid",
  "result": { "sessionId": "...", "availableModels": [...] }
}

// Уведомление от Droid
{
  "jsonrpc": "2.0",
  "type": "notification",
  "factoryApiVersion": "1.0.0",
  "method": "droid.session_notification",
  "params": { "notification": { "type": "create_message", ... } }
}
```

### Типы уведомлений

| Тип | Описание |
|-----|----------|
| `session_start` | Старт сессии |
| `create_message` | Создание сообщения (assistant/user/system) |
| `tool_result` | Результат выполнения инструмента |
| `droid_working_state_changed` | Изменение состояния (idle/streaming_assistant_message) |
| `settings_updated` | Обновление настроек (model, reasoning, autonomy) |

### Инициализация сессии

При старте `droid exec --input-format stream-jsonrpc` возвращает:

```typescript
interface InitSessionResult {
  sessionId: string;
  session?: { messages: unknown[] };
  settings?: {
    modelId: string;
    reasoningEffort?: string;
    autonomyLevel?: string;  // "normal" | "spec" | "auto-low" | "auto-medium" | "auto-high"
  };
  availableModels: AvailableModel[];
}
```

### Уровни автономности

| Уровень | Поведение |
|---------|-----------|
| `normal` | Стандартный режим с подтверждениями |
| `spec` | Specification Mode: планирование → ревью → реализация |
| `auto-low` | Автоматический, минимальная автономность |
| `auto-medium` | Автоматический, средняя автономность |
| `auto-high` | Автоматический, максимальная автономность |

### Контроль таймаутов

`droid exec` не имеет встроенных таймаутов. Для контроля требуется обёртка:
- Наш `watch-subagent.sh` можно адаптировать для Droid
- JSON-RPC streaming позволяет отслеживать состояние `droid_working_state_changed`

### Пример запуска

```bash
# Простой JSON-вывод
droid exec --output-format json "Analyze the codebase architecture"

# JSON-RPC для глубокой интеграции (stdin/stdout pipe)
echo '{"jsonrpc":"2.0","method":"send_message","params":{"text":"Implement feature X"}}' | \
  droid exec --input-format stream-jsonrpc --output-format stream-jsonrpc

# С конкретной reasoning effort
droid exec --output-format json --reasoning-effort high "Debug the failing test"
```

### Docker-вариант (headless)

```bash
docker run --rm -it \
  -v "$(pwd)":/home/appuser/work \
  -e FACTORY_API_KEY="fk-..." \
  wuodan/factoryai-droid:latest \
  exec --output-format json "List files"
```

### Оценка: ✅ Полная поддержка

Droid имеет развитый программный интерфейс через `droid exec` с поддержкой JSON и JSON-RPC. Протокол документирован, есть GitHub Actions workflows. Аналог JSONL-стриминга Pi, но через JSON-RPC.

---

## Критерий 7. Токены и стоимость

### Доступные метрики

Droid отслеживает использование токенов в рамках сессии. Метрики хранятся в `~/.factory/sessions/<cwd>/<session-id>.settings.json`:

```typescript
interface FactorySessionSettings {
  assistantActiveTimeMs?: number;
  model?: string;
  reasoningEffort?: string;
  autonomyMode?: string;
  tokenUsage?: {
    inputTokens?: number;
    outputTokens?: number;
    cacheCreationTokens?: number;
    cacheReadTokens?: number;
    thinkingTokens?: number;
  };
}
```

### Доступ к метрикам

1. **JSON-RPC**: В уведомлениях `create_message` и `settings_updated` доступны данные о модели и reasoning
2. **Session files**: `~/.factory/sessions/<cwd>/<id>.settings.json` — полный отчёт после завершения
3. **Factory API**: REST API для сессий (docs.factory.ai/api-reference/sessions)

### Стоимость

- Подписка Factory AI (от $20/мес) включает определённый объём токенов
- При BYOK стоимость определяется провайдером API-ключа
- Enterprise: детальная аналитика по использованию (docs.factory.ai/enterprise/usage-cost-and-analytics)

### Ограничения

Нет вывода стоимости в $ за сессию в CLI (в отличие от Pi, который показывает `cost.input`, `cost.output`). Но есть все сырые метрики (inputTokens, outputTokens, thinkingTokens) для самостоятельного расчёта.

### Оценка: ⚠️ Частичная поддержка

Токены отслеживаются (input, output, cache, thinking), но нет автоматического расчёта стоимости в $. Метрики доступны через session files и Factory API, но не напрямую в stdout при `droid exec`.

---

## Критерий 8. Free tier

### Ценовая модель

| Параметр | Значение |
|----------|----------|
| Бесплатный тариф | **Отсутствует** |
| Минимальная подписка | $20/мес |
| Enterprise | По запросу |
| BYOK | Можно использовать свои API-ключи (тогда стоимость = стоимость провайдера) |
| Factory API Key | Требуется для всех операций (`FACTORY_API_KEY`) |

### Ограничения

- Droid — коммерческий продукт, **нет бесплатного тарифа**
- Для использования даже с BYOK требуется Factory API key (минимум подписка)
- Без подписки запуск невозможен (droid-acp даже инжектирует dummy key для WebSearch)
- Кредиты организации: Enterprise может устанавливать лимиты на пользователей

### Оценка: ❌ Нет бесплатного тарифа

Платный продукт от $20/мес. Без Factory подписки запуск невозможен.

---

## Критерий 9. Провайдеры и модели

### Factory-хостируемые модели

Droid поставляется с набором моделей, включённых в подписку Factory AI. Точный список зависит от тарифа.

### BYOK (Bring Your Own Key)

Документированные провайдеры BYOK (docs.factory.ai/cli/byok):

| Провайдер | Документация | Примечание |
|-----------|-------------|------------|
| OpenAI | `/cli/byok/openai-anthropic` | GPT серия |
| Anthropic | `/cli/byok/openai-anthropic` | Claude серия |
| Google Gemini | `/cli/byok/google-gemini` | Gemini серия |
| OpenRouter | `/cli/byok/openrouter` | Агрегатор, 200+ моделей |
| Ollama | `/cli/byok/ollama` | Локальные модели |
| Fireworks | `/cli/byok/fireworks` | Высокопроизводительный инференс |
| Groq | `/cli/byok/groq` | Ультрабыстрый инференс (LPU) |
| DeepInfra | `/cli/byok/deepinfra` | Open-source модели |
| HuggingFace | `/cli/byok/huggingface` | Модели с HF Hub |
| Baseten | `/cli/byok/baseten` | Enterprise-инференс кастомных моделей |

### Переключение моделей

```typescript
// Через JSON-RPC (droid exec stream-jsonrpc)
send("set_model", { modelId: "claude-opus-4-5-20251101" });

// Интерактивно
/model <model-name>

// Через droid exec
droid exec --reasoning-effort high "Complex task"
```

### Available Models (при инициализации сессии)

```typescript
interface AvailableModel {
  id: string;
  modelId?: string;
  modelProvider: string;
  displayName: string;
  shortDisplayName?: string;
  supportedReasoningEfforts: string[];
  defaultReasoningEffort: string;
  isCustom: boolean;
  noImageSupport?: boolean;
}
```

### Модели, наблюдаемые в oh-my-droid

| Модель | Провайдер | Уровень |
|--------|-----------|---------|
| Claude Opus 4.5 | Anthropic | HIGH (сложные задачи) |
| Claude Sonnet 4 | Anthropic | MEDIUM (стандартные) |
| Claude Haiku 3.5 | Anthropic | LOW (простые) |

### Mixed Models

Документированная функция (docs.factory.ai/cli/configuration/mixed-models) позволяет использовать разные модели для разных задач в рамках одной сессии.

### Оценка: ⚠️ Поддержка 10+ провайдеров, BYOK, локальные модели

Хороший набор провайдеров с полноценным BYOK. Ollama для локальных моделей. Однако точный список моделей зависит от подписки Factory.

---

## Критерий 10. Лицензия

### Информация

| Параметр | Значение |
|----------|----------|
| Продукт | Factory Droid CLI |
| Компания | Factory AI (https://factory.ai) |
| Лицензия | **Проприетарная** (unfree) |
| Исходный код | Закрытый, бинарная дистрибуция |
| Дистрибуция | `downloads.factory.ai/factory-cli/releases/<version>/<platform>/<arch>/droid` |
| Платформы | Linux (x64, arm64), macOS (x64, arm64) |
| Установка | `curl -fsSL https://app.factory.ai/cli \| sh` |

### Nix flake

В Nix-пакете: `license = lib.licenses.unfree;` — подтверждает проприетарную лицензию.

### API Terms

Использование регулируется Terms of Service Factory AI. Enterprise-функции: SSO, SCIM, compliance audit.

### Оценка: ❌ Проприетарная лицензия

Закрытый код, бинарная дистрибуция. Невозможно модифицировать, fork'нуть или использовать без подписки.

---

## Вердикт

### ⚠️ Частично подходит (Score: 6/10)

Factory Droid — мощный AI-coding-агент с развитой экосистемой (Custom Droids, Skills, Plugins, MCP, BYOK, JSON-RPC), но имеет критические ограничения для нашей архитектуры сабагентов.

**Сильные стороны:**
1. ✅ `droid exec` с JSON-RPC протоколом — программный запуск через stdin/stdout
2. ✅ AGENTS.md поддержка из коробки
3. ✅ Custom Droids с контролем модели и прав (disallowedTools)
4. ✅ Agent Skills standard (автосканирование `.agents/skills/`)
5. ✅ Система плагинов (oh-my-droid: 32 агента, 37 скиллов)
6. ✅ 10+ BYOK провайдеров (OpenAI, Anthropic, Google, Ollama, OpenRouter и др.)
7. ✅ GitHub Actions workflows из коробки
8. ✅ Autonomy levels (spec, auto-low/medium/high) для контроля поведения

**Критические ограничения:**
1. ❌ **Проприетарная лицензия** — закрытый код, невозможно fork/модифицировать
2. ❌ **Платный ($20+/мес)** — нет free tier, обязательна подписка
3. ⚠️ **Нет CLI-флагов для системного промпта** — нельзя инъектировать роль через `--system-prompt`
4. ⚠️ **Нет CLI-флагов для скиллов** — нельзя управлять через `--skill`
5. ⚠️ **Токены без стоимости в $** — есть input/output/thinking tokens, но нет автоматического расчёта стоимости

**Сравнение с Pi (наш текущий сабагент):**

| Аспект | Pi Coding Agent | Factory Droid |
|--------|-----------------|---------------|
| CLI-флаги системного промпта | ✅ `--system-prompt` + `--append-system-prompt` | ❌ Только через конфигурацию |
| CLI-флаги скиллов | ✅ `--skill`, `--no-skills` | ❌ Только через конфигурацию |
| Лицензия | ✅ MIT (open source) | ❌ Проприетарная |
| Free tier | ✅ Бесплатный (MIT) | ❌ $20+/мес |
| JSONL-режим | ✅ `--mode json --no-session` | ✅ `droid exec --output-format json/stream-jsonrpc` |
| AGENTS.md | ✅ Автообнаружение | ✅ Автообнаружение |
| Токены + стоимость | ✅ Полная телеметрия с $ | ⚠️ Токены есть, $ нет |
| Custom агенты | ❌ Нет встроенной системы | ✅ Custom Droids с frontmatter |

**Рекомендация:** Droid не подходит как прямой replacement для Pi в нашей системе сабагентов из-за отсутствия CLI-флагов для инъекции ролей и проприетарной лицензии. Однако подход Custom Droids (frontmatter с model, disallowedTools) может быть полезен как референс для расширения нашей системы ролей.

---

## Приложение А. Практические примеры запуска

### Запуск droid exec (простой JSON)

```bash
FACTORY_API_KEY=fk-xxx droid exec --output-format json "Analyze the codebase architecture"
```

### Запуск droid exec (JSON-RPC)

```bash
FACTORY_API_KEY=fk-xxx droid exec \
  --input-format stream-jsonrpc \
  --output-format stream-jsonrpc \
  --cwd /home/dp/MyProjects/task-orchestrator
```

### Запуск через Docker (headless)

```bash
docker run --rm -it \
  -v "$(pwd)":/home/appuser/work \
  -e FACTORY_API_KEY="fk-xxx" \
  wuodan/factoryai-droid:latest \
  exec --output-format json "Run tests and report results"
```

### Запуск через адаптер droid-acp (для ACP-клиентов)

```bash
FACTORY_API_KEY=fk-xxx npx droid-acp --reasoning-effort high
```

---

## Приложение Б. Структура конфигурации

```
~/.factory/
├── settings.json              # Глобальные настройки (модель, reasoning, autonomy)
├── sessions/                  # История сессий
│   └── <cwd-encoded>/
│       ├── <session-id>.jsonl         # Транскрипт сессии
│       └── <session-id>.settings.json # Настройки + токены
├── bin/                       # Дополнительные бинарники
├── mcp.json                   # MCP сервер конфигурация
└── skills/                    # Глобальные скиллы

<project>/
├── AGENTS.md                  # Проектные инструкции (автозагрузка)
├── .factory/
│   ├── settings.json          # Per-project настройки
│   ├── mcp.json               # Per-project MCP
│   └── droids/                # Custom Droids
│       └── architect.md       # Специализированный агент
├── .agents/skills/            # Скиллы проекта (Agent Skills standard)
│   └── my-skill/SKILL.md
└── .factory-plugin/           # Плагины
    └── plugin.json
```

---

## Приложение В. Пример Custom Droid

```markdown
---
name: backend-developer
description: Symfony/PHP/DDD backend developer agent
model: claude-sonnet-4-20250514
disallowedTools:
---

<Role>
You are an expert backend developer specializing in Symfony 8.0, PHP 8.4, DDD,
and Clean Architecture. Follow the conventions in AGENTS.md strictly.
</Role>

<Constraints>
- Never modify files in the Domain layer without tests
- Always follow PSR-12 coding standard
- Write unit tests for all new Domain/Application code
</Constraints>
```

---

## Источники

1. [Factory AI Docs — sitemap](https://docs.factory.ai/sitemap.xml) — карта документации, подтверждает существование всех описанных функций
2. [droid-acp (GitHub)](https://github.com/kingsword09/droid-acp) — ACP-адаптер, исходный код показывает JSON-RPC протокол Droid
3. [factoryai-droid-docker (GitHub)](https://github.com/Wuodan/factoryai-droid-docker) — Docker-обёртка, показывает установку и exec-режим
4. [oh-my-droid (GitHub)](https://github.com/MeroZemory/oh-my-droid) — Плагин с 32 агентами и 37 скиллами, показывает Custom Droids формат
5. [factory-cli-nix (GitHub)](https://github.com/GutMutCode/factory-cli-nix) — Nix-пакет, подтверждает версию 0.25.1 и proprietary лицензию
