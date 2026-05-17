# Pi Coding Agent — Исследование для интеграции как сабагент

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-09
**Объект:** Pi Coding Agent v0.74.0 (`@earendil-works/pi-coding-agent`, Node.js/TypeScript)
**Задача:** [TASK-research-pi-coding-agent](../../../todo/done/TASK-research-pi-coding-agent.todo.md)

---

## Сводка

Pi Coding Agent — минималистичный терминальный AI-coding-harness от Mario Zechner, форкнутый и доработанный как `@earendil-works/pi-coding-agent`. Версия 0.74.0, лицензия MIT. Pi — наш текущий основной сабагент, уже интегрированный через `watch-subagent.sh`. Данный отчёт служит референс-точкой для сравнения с остальными агентами.

---

## Критерий 1. Системный промпт

### Возможности

| Опция | Поведение |
|-------|-----------|
| `--system-prompt <text>` | **Полная замена** дефолтного системного промпта. Контекстные файлы (AGENTS.md) и скиллы всё равно добавляются поверх. |
| `--append-system-prompt <text>` | **Дополнение** к текущему системному промпту (repeatable — можно указать несколько раз). |
| `.pi/SYSTEM.md` | Файл в проекте — замена дефолтного промпта на уровне проекта. |
| `~/.pi/agent/SYSTEM.md` | Файл глобальный — замена дефолтного промпта на глобальном уровне. |
| `.pi/APPEND_SYSTEM.md` | Файл в проекте — дополнение к промпту. |
| `~/.pi/agent/APPEND_SYSTEM.md` | Глобальное дополнение к промпту. |

### Примеры CLI

```bash
# Полная замена системного промпта
pi --system-prompt "Ты — экспертный AI-ассистент." "Опиши архитектуру проекта"

# Дополнение к дефолтному промпту
pi --append-system-prompt "Следуй стандартам PSR-12." "Отрефактори код"

# Наш подход в watch-subagent.sh — замена + дополнение для роли
pi --mode json --no-session \
  --system-prompt "scripts/subagent_system.txt" \
  --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/backend_developer_levsha.ru.md"
```

### Оценка: ✅ Полная поддержка

Pi предоставляет все необходимые механизмы: полную замену, дополнение, файловое дополнение. Наш `watch-subagent.sh` использует `--system-prompt` для упрощённого системного промпта + `--append-system-prompt` для инъекции роли.

---

## Критерий 2. Промпт агента / Роль

### Подход

Pi не имеет встроенного механизма «ролей». Роль инжектируется через `--append-system-prompt`, что указывает модели прочитать файл роли через tool `read`:

```bash
pi --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/backend_developer_levsha.ru.md"
```

Модель самостоятельно прочитает файл роли при первом обращении через инструмент `read`.

### Альтернативные подходы

1. **Inline-содержимое файла роли** — передать содержимое файла напрямую в `--append-system-prompt "$(cat role.md)"`. Минус: раздувает системный промпт, тратит токены на каждый запрос.
2. **Через `@files`** — `pi @role.md "Prompt"`. Минус: содержимое файла попадает в user-message, а не в system prompt.
3. **Наш текущий подход (через инструкцию прочитать файл)** — оптимальный: экономит токены, модель загружает роль on-demand.

### Оценка: ✅ Полная поддержка (через --append-system-prompt)

Pi позволяет гибко инжектировать контекст роли. Наш подход с `--append-system-prompt "Возьми на себя роль из файла: ..."` работает надёжно.

---

## Критерий 3. Скиллы

### Возможности

| Опция | Поведение |
|-------|-----------|
| `--skill <path>` | Загрузить скилл (файл или директорию). Repeatable. |
| `--no-skills` | Отключить автосканирование скиллов. Явные `--skill` всё равно работают. |
| Автосканирование | Pi автоматически сканирует директории скиллов при запуске (см. Критерий 5). |

### Механика

1. При старте pi сканирует локации скиллов и извлекает `name` + `description`.
2. В системный промпт добавляются описания доступных скиллов в XML-формате.
3. При совпадении задачи модель загружает полный `SKILL.md` через `read`.
4. Скиллы регистрируются как команды `/skill:name`.

### Примеры

```bash
# Загрузить конкретный скилл
pi --skill docs/agents/skills/agent-report/SKILL.md

# Загрузить несколько скиллов
pi --skill docs/agents/skills/run-pi-subagent/SKILL.md \
   --skill docs/agents/skills/agent-report/SKILL.md

# Отключить автосканирование, загрузить только явные
pi --no-skills --skill docs/agents/skills/agent-report/SKILL.md
```

### Разные скиллы разным ролям

Да, поддерживается. Каждому вызову `watch-subagent.sh` можно передать свой набор скиллов через `--skill`, а автосканирование отключить через `--no-skills`. Это позволяет давать разным ролям разные возможности.

### Оценка: ✅ Полная поддержка

Pi реализует [Agent Skills standard](https://agentskills.io/specification) с развитым механизмом обнаружения, загрузки и управления скиллами. Поддерживается назначение разных скиллов разным ролям.

---

## Критерий 4. AGENTS.md (контекстные файлы)

### Возможности

| Опция | Поведение |
|-------|-----------|
| Автообнаружение | Pi автоматически загружает `AGENTS.md` и `CLAUDE.md` при старте |
| `--no-context-files` (`-nc`) | Полностью отключает загрузку контекстных файлов |

### Порядок загрузки

Pi загружает контекстные файлы из:

1. `~/.pi/agent/AGENTS.md` — глобальные инструкции
2. Родительские директории от cwd вверх (до git root или корня ФС)
3. Текущая директория (`AGENTS.md` или `CLAUDE.md`)

### Взаимодействие с системным промптом

При `--system-prompt` (замена промпта) контекстные файлы **всё равно** добавляются поверх. Это гарантирует, что проектные инструкции всегда доступны модели.

### Примеры

```bash
# С автообнаружением (по умолчанию) — pi найдёт AGENTS.md в корне проекта
pi --mode json --no-session "Выполни задачу"

# Без контекстных файлов
pi --no-context-files --mode json "Проанализируй код"
```

### Оценка: ✅ Полная поддержка

Pi автоматически обнаруживает и загружает `AGENTS.md` из нашего проекта (`/home/dp/MyProjects/task-orchestrator/AGENTS.md`), что обеспечивает модели доступ к конвенциям проекта без дополнительных настроек.

---

## Критерий 5. Стандартная папка `.agents/skills/`

### Автосканирование

Pi **поддерживает** автосканирование `.agents/skills/` из коробки, но с нюансами:

| Локация | Правила сканирования |
|---------|---------------------|
| `~/.pi/agent/skills/` | Root `.md` файлы + директории с `SKILL.md` |
| `~/.agents/skills/` | **Только** директории с `SKILL.md` (root `.md` игнорируются) |
| `.pi/skills/` | Root `.md` файлы + директории с `SKILL.md` |
| `.agents/skills/` (в cwd и ancestor dirs) | **Только** директории с `SKILL.md` (root `.md` игнорируются) |
| `settings.json → skills[]` | Явные пути (glob-паттерны) |
| `--skill <path>` | Явный путь (repeatable) |

### Наша структура

Наши скиллы лежат в `docs/agents/skills/`, а не в `.agents/skills/`. Pi не автосканирует `docs/agents/skills/`. Для загрузки используется:

1. **Явный `--skill`** — в команде запуска
2. **Settings** — добавить `"skills": ["docs/agents/skills/*"]` в `.pi/settings.json`

### Оценка: ✅ Поддерживается с минимальной настройкой

Стандарт `.agents/skills/` поддерживается из коробки. Наша структура `docs/agents/skills/` требует явного указания через `--skill` или `settings.json`. Это осознанный выбор — мы не хотим автозагрузки всех скиллов для каждого сабагента.

---

## Критерий 6. Запуск как сабагент (JSON-режим)

### Возможности

| Опция | Поведение |
|-------|-----------|
| `--mode json` | Все события сессии выводятся как JSONL (JSON Lines) в stdout |
| `--no-session` | Ephemeral режим — сессия не сохраняется |
| `--mode rpc` | RPC-режим через stdin/stdout (для глубокой интеграции) |
| `--print` (`-p`) | Non-interactive — один запрос и выход |

### Формат JSONL

Первая строка — заголовок сессии:
```json
{"type":"session","version":3,"id":"uuid","timestamp":"...","cwd":"/path"}
```

Далее — поток событий: `agent_start`, `turn_start/end`, `message_start/update/end`, `tool_execution_start/update/end`, `agent_end`.

### Наша интеграция (watch-subagent.sh)

```bash
pi --mode json --no-session \
  --system-prompt "$SYSTEM_PROMPT_FILE" \
  --append-system-prompt "Возьми на себя роль из файла: $ROLE_FILE" \
  <<< "$PROMPT"
```

Скрипт `watch-subagent.sh` обеспечивает:
- **Soft timeout** (`-s`) — базовый таймаут
- **Hard timeout** (`-m`) — абсолютный максимум (default: 1200s)
- **Stall timeout** (`-t`) — нет событий N секунд → агент завис → kill (default: 120s)
- **Фильтрация вывода** (`-o`) — raw / text / tools / files
- **Pipe-управление** — named pipe для параллельного чтения и записи

### Пример потока данных

```mermaid
sequenceDiagram
    participant Orchestrator
    participant watch-subagent.sh
    participant pi (--mode json)
    participant LLM Provider

    Orchestrator->>watch-subagent.sh: Запуск с таймаутами и ролью
    watch-subagent.sh->>pi (--mode json): pi --mode json --no-session + system-prompt
    pi (--mode json)->>pi (--mode json): Загрузка скиллов, AGENTS.md, роли
    pi (--mode json)->>LLM Provider: API-запрос
    LLM Provider-->>pi (--mode json): Streaming-ответ
    pi (--mode json)-->>watch-subagent.sh: JSONL-события (agent_start, message_update, tool_execution_start, ...)
    watch-subagent.sh->>watch-subagent.sh: Фильтрация (text/tools/files/raw)
    Note over pi (--mode json): Модель вызывает tools (read, bash, edit, write)
    pi (--mode json)-->>watch-subagent.sh: agent_end event
    watch-subagent.sh-->>Orchestrator: Финальный результат (exit 0 = успех, exit 1 = таймаут)
```

### Оценка: ✅ Полная поддержка

Pi идеально подходит для запуска как сабагент. JSONL-стрим позволяет отслеживать прогресс в реальном времени, `--no-session` обеспечивает изоляцию, а наш `watch-subagent.sh` надёжно контролирует таймауты.

---

## Критерий 7. Токены и стоимость

### Доступные метрики

В каждом `message` в JSONL-потоке присутствует объект `usage`:

```typescript
interface Usage {
  input: number;         // Входные токены
  output: number;        // Выходные токены
  cacheRead: number;     // Токены из кеша (чтение)
  cacheWrite: number;    // Токены записанные в кеш
  totalTokens: number;   // Общее количество токенов
  cost: {
    input: number;       // Стоимость входных
    output: number;      // Стоимость выходных
    cacheRead: number;   // Стоимость чтения из кеша
    cacheWrite: number;  // Стоимость записи в кеш
  }
}
```

### Доступ через JSONL-события

Метрики доступны в событиях `message_end` и `agent_end`:

```bash
# Извлечь usage из agent_end
pi --mode json "prompt" 2>/dev/null | jq 'select(.type == "agent_end") | .messages[-1].usage'
```

### В интерактивном режиме

Footer pi показывает: рабочий каталог, имя сессии, токены/кеш, стоимость, использование контекста, текущую модель.

### Оценка: ✅ Полная поддержка

Полная телеметрия по токенам и стоимости доступна через JSONL-события. Можно извлечь как суммарные метрики за сессию, так и по-витковые.

---

## Критерий 8. Free tier

### Pi как продукт

Pi — **open-source** (MIT) CLI-инструмент, сам по себе полностью бесплатный. Стоимость определяется провайдером LLM, а не pi.

### Бесплатные модели / провайдеры

| Провайдер | Бесплатные возможности |
|-----------|----------------------|
| OpenAI Codex | ChatGPT Plus/Pro подписка ($20/$200/мес). Бесплатного тарифа нет. |
| Claude Pro/Max | Подписка ($20/$100/мес). Третий-party harness usage списывает extra usage. |
| GitHub Copilot | Требует Copilot-подписку. Бесплатно для verified students/oss. |
| Google Gemini API | Free tier: Gemini 2.5 Flash — 15 RPM, 1M tokens/min. Gemini 2.5 Pro — ограниченный free tier. |
| Ollama / LM Studio | Полностью бесплатно при локальных моделях (GPU + RAM). |
| Cloudflare Workers AI | Бесплатный tier (1000 нейронов/день). |

### Наша конфигурация

Текущая конфигурация использует провайдера `zai` (GLM-5.1) с моделью по умолчанию. Стоимость определяется тарифами ZAI.

### Оценка: ✅ Бесплатный инструмент, стоимость зависит от провайдера

Pi сам по себе бесплатный (MIT). Для бесплатного использования подходят Google Gemini API free tier или локальные модели через Ollama/LM Studio.

---

## Критерий 9. Провайдеры и модели

### Поддерживаемые провайдеры

**Подписочные (OAuth):**
- ChatGPT Plus/Pro (Codex) — OpenAI
- Claude Pro/Max — Anthropic
- GitHub Copilot

**API-ключи:**

| Провайдер | API | Примечание |
|-----------|-----|------------|
| Anthropic | Messages API | Claude серии |
| OpenAI | Completions / Responses | GPT серии |
| Google Gemini | Generative AI | Gemini серии |
| DeepSeek | — | DeepSeek-V3/R1 |
| Mistral | — | Mistral серии |
| Groq | — | Быстрый инференс |
| Cerebras | — | Быстрый инференс |
| xAI (Grok) | — | Grok серии |
| Fireworks | — | Open-source модели |
| OpenRouter | — | Агрегатор провайдеров |
| ZAI | — | GLM серии |
| Cloudflare (AI Gateway / Workers AI) | — | Маршрутизация + Workers AI |
| Azure OpenAI | — | Enterprise OpenAI |
| Amazon Bedrock | — | Enterprise (Claude, GPT через AWS) |
| Hugging Face | — | Open-source модели |
| Xiaomi MiMo | — | Китайский провайдер |
| MiniMax | — | Китайский провайдер |
| Moonshot AI | — | Китайский провайдер |
| Kimi For Coding | — | Китайский провайдер |
| OpenCode Zen/Go | — | Альтернативные |
| Google Vertex AI | — | Enterprise Google |

**Кастомные провайдеры (через `models.json`):**
- Ollama (локальные модели)
- LM Studio (локальные модели)
- vLLM (локальные модели)
- Любой OpenAI/Anthropic/Google-совместимый API

### BYOK (Bring Your Own Key)

Да, полная поддержка. API-ключи передаются через:
- Environment variables
- `auth.json` (с поддержкой shell-команд для keychains)
- `--api-key` флаг
- `models.json` для кастомных провайдеров

### Переключение

```bash
# CLI
pi --provider google --model gemini-2.5-pro
pi --model anthropic/claude-sonnet-4
pi --model sonnet:high  # с thinking level

# Settings
{
  "defaultProvider": "anthropic",
  "defaultModel": "claude-sonnet-4-20250514"
}
```

### Текущие доступные модели (наша конфигурация)

| Провайдер | Модель | Контекст | Max-out | Thinking |
|-----------|--------|----------|---------|----------|
| openai-codex | gpt-5.1 — gpt-5.5 | 272K | 128K | yes |
| zai | glm-4.5-air — glm-5.1 | 131K–200K | 98K–131K | yes |

### Оценка: ✅ Поддержка 20+ провайдеров

Pi поддерживает более 20 провайдеров с полным BYOK. Локальные модели (Ollama, LM Studio, vLLM) подключаются через `models.json`. Переключение провайдеров/моделей — через CLI или settings.

---

## Критерий 10. Лицензия

### Информация

| Параметр | Значение |
|----------|----------|
| Пакет | `@earendil-works/pi-coding-agent` |
| Upstream | `@mariozechner/pi-coding-agent` (Mario Zechner / badlogic) |
| Форк | `@earendil-works/pi-mono` → `packages/coding-agent` |
| Лицензия | **MIT** |
| Репозиторий | https://github.com/earendil-works/pi-mono |
| Upstream репозиторий | https://github.com/badlogic/pi-mono |

### Условия

MIT-лицензия разрешает:
- Коммерческое использование
- Модификацию
- Распространение
- Private use

Единственное требование — включить копию лицензии и уведомление об авторских правах.

### Оценка: ✅ Open source, MIT — максимальная свобода

---

## Вердикт

### ✅ Подходит (Score: 10/10)

Pi Coding Agent **идеально подходит** для использования как сабагент с нашей системой ролей и скиллов. Подтверждено боевой эксплуатацией в проекте task-orchestrator.

**Сильные стороны:**
1. Гибкая система системного промпта (замена + дополнение + файлы)
2. JSONL-стриминг для интеграции (`--mode json`)
3. Ephemeral-режим (`--no-session`) для изоляции сабагентов
4. Полная поддержка Agent Skills standard (автосканирование, on-demand загрузка)
5. Автообнаружение AGENTS.md — модель получает контекст проекта без настройки
6. 20+ провайдеров LLM, BYOK, локальные модели
7. MIT-лицензия
8. Богатая телеметрия (токены, стоимость, контекст)
9. Extension-система для кастомизации

**Незначительные ограничения:**
1. Роль инжектируется через `--append-system-prompt` + инструкцию модели прочитать файл — модель может не прочитать файл роли в редких случаях (не наблюдалось на практике)
2. Автосканирование `.agents/skills/` не покрывает нашу структуру `docs/agents/skills/` — требуется явная загрузка через `--skill` или settings

---

## Приложение А. Практические примеры запуска

### Запуск Бэкендера Левши (текущий подход)

```bash
scripts/watch-subagent.sh -s 600 \
  -r docs/agents/roles/team/backend_developer_levsha.ru.md <<'PROMPT'
Выполни задачу: todo/TASK-feat-example.todo.md.
Следуй инструкциям из секции 'Инструкции для сабагента' в файле задачи и AGENTS.md.
PROMPT
```

### Запуск с конкретным скиллом

```bash
scripts/watch-subagent.sh -s 600 \
  -r docs/agents/roles/team/technical_writer_hermione.ru.md \
  -o text <<'PROMPT'
Обнови документацию модуля Orchestrator.
PROMPT
```

### Запуск в JSON-режиме напрямую (без watch-subagent.sh)

```bash
pi --mode json --no-session \
  --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/qa_backend_house.ru.md" \
  <<< "Проверь тесты в tests/Unit/" 2>/dev/null | jq -c 'select(.type == "message_end")'
```

### Read-only режим (ревью без правок)

```bash
pi --mode json --no-session --tools read,grep,find,ls \
  --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/code_reviewer_backend_puaro.ru.md" \
  <<< "Проведи ревью PR #123"
```

---

## Приложение Б. Примеры конфигурации для разных ролей

### Конфигурация pi для Аналитика (только чтение)

```bash
pi --mode json --no-session \
  --tools read,bash \
  --no-skills \
  --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/system_analyst_sherlock.ru.md"
```

### Конфигурация pi для Архитектора (чтение + запись отчётов)

```bash
pi --mode json --no-session \
  --tools read,bash,write \
  --no-skills \
  --skill docs/agents/skills/agent-report/SKILL.md \
  --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/system_architect_gandalf.ru.md"
```

### Конфигурация pi для Ревьювера (только чтение, с tools filter)

```bash
pi --mode json --no-session \
  --tools read,bash \
  --no-skills \
  --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/code_reviewer_backend_puaro.ru.md"
```

### Конфигурация pi для Бэкендера (полный набор)

```bash
pi --mode json --no-session \
  --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/backend_developer_levsha.ru.md"
```

---

## Источники

1. `pi --help` — CLI-параметры (v0.74.0)
2. [Pi docs: providers.md](https://github.com/earendil-works/pi-mono/blob/main/packages/coding-agent/docs/providers.md) — провайдеры и аутентификация
3. [Pi docs: skills.md](https://github.com/earendil-works/pi-mono/blob/main/packages/coding-agent/docs/skills.md) — система скиллов
4. [Pi docs: json.md](https://github.com/earendil-works/pi-mono/blob/main/packages/coding-agent/docs/json.md) — JSON-режим
5. [Pi docs: models.md](https://github.com/earendil-works/pi-mono/blob/main/packages/coding-agent/docs/models.md) — кастомные модели (Ollama, LM Studio, vLLM)
