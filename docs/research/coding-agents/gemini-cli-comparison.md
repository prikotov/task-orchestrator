# Gemini CLI (Google) — Исследование для интеграции как сабагент

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-09
**Объект:** Gemini CLI v0.37.2 (`@google/gemini-cli`, TypeScript)
**Задача:** [TASK-research-gemini-cli](../../../todo/TASK-research-gemini-cli.todo.md)

---

## Сводка

Gemini CLI — официальный open-source терминальный AI-coding-agent от Google. Написан на TypeScript, дистрибутируется как npm-пакет `@google/gemini-cli`. Версия 0.37.2, лицензия Apache-2.0. Поддерживает бесплатный тариф (60 RPM / 1000 запросов в день через OAuth-авторизацию Google). Имеет встроенную систему скиллов, GEMINI.md как контекстные инструкции, и три формата вывода: `text`, `json`, `stream-json`.

---

## Критерий 1. Системный промпт

### Возможности

| Механизм | Поведение |
|----------|-----------|
| `GEMINI_SYSTEM_MD` (env var) | **Полная замена** дефолтного системного промпта на содержимое файла. Путь к файлу указывается в значении переменной. |
| `.gemini/system.md` (в проекте) | Дефолтный путь для замены системного промпта (используется, если `GEMINI_SYSTEM_MD` = `true`/`1`). |
| `GEMINI_SYSTEM_MD=false` / `0` | **Отключение** механизма замены — используется стандартный промпт. |
| GEMINI.md (контекстные файлы) | **Всегда добавляются** поверх системного промпта — даже при `GEMINI_SYSTEM_MD`. Неотключаемы. |
| Дополнение (append) к системному промпту | **Не поддерживается** — нет аналога `--append-system-prompt` (как у Pi). Только полная замена через `GEMINI_SYSTEM_MD`. |

### Примеры

```bash
# Полная замена системного промпта через env-переменную
GEMINI_SYSTEM_MD=/path/to/custom-system.md gemini -p "Prompt" -o json

# Использование .gemini/system.md (дефолтный путь) — включить через env
GEMINI_SYSTEM_MD=true gemini -p "Prompt" -o json

# Отключить кастомный системный промпт (вернуть дефолтный)
GEMINI_SYSTEM_MD=false gemini -p "Prompt" -o json
```

### Наш подход (для интеграции как сабагент)

```bash
# Создать файл с упрощённым системным промптом для сабагента
cat > scripts/gemini-subagent-system.md << 'EOF'
You are an AI coding agent operating as a sub-agent.
Follow the user's instructions and visible project instructions.
Keep responses concise and task-focused.
EOF

# Запуск сабагента с кастомным системным промптом
GEMINI_SYSTEM_MD=scripts/gemini-subagent-system.md \
  gemini -p "Возьми на себя роль из файла: docs/agents/roles/team/backend_developer_levsha.ru.md ..." -o stream-json --yolo
```

### Оценка: ⚠️ Частичная поддержка

Gemini CLI поддерживает **полную замену** системного промпта через `GEMINI_SYSTEM_MD`, но **не поддерживает дополнение** (`append`). Это ограничение: нельзя «дополнить» дефолтный промпт ролью — только полностью заменить его. GEMINI.md-файлы добавляются поверх в любом случае, что частично компенсирует отсутствие append.

---

## Критерий 2. Промпт агента / Роль

### Подход

Gemini CLI не имеет встроенного механизма «ролей». Роль можно инжектировать двумя способами:

1. **Через user prompt (`-p`):** Указать инструкцию загрузки роли в промпте. Модель прочитает файл роли через tool `read_file`.

```bash
gemini -p "Возьми на себя роль из файла: docs/agents/roles/team/backend_developer_levsha.ru.md. Выполни задачу: todo/TASK-xxx.todo.md" -o stream-json --yolo
```

2. **Через `GEMINI_SYSTEM_MD`:** Включить инструкцию загрузки роли в кастомный системный промпт.

```bash
GEMINI_SYSTEM_MD=scripts/gemini-subagent-system.md \
  gemini -p "Возьми на себя роль из файла: docs/agents/roles/team/backend_developer_levsha.ru.md ..." -o stream-json --yolo
```

3. **Inline-содержимое файла роли** — передать содержимое файла напрямую в промпт. Минус: раздувает промпт, тратит токены.

### Ограничение

В отличие от Pi, у Gemini CLI нет `--append-system-prompt`. Роль можно инжектировать только через user prompt или полную замену системного промпта.

### Оценка: ⚠️ Частичная поддержка

Инъекция роли возможна через user prompt, но менее элегантна, чем у Pi (`--append-system-prompt`). Модель должна « voluntarily » прочитать файл роли через `read_file` — дополнительный виток (turn) и токены.

---

## Критерий 3. Скиллы

### Возможности

| Механизм | Поведение |
|----------|-----------|
| `.gemini/skills/<name>/SKILL.md` (проект) | Скиллы уровня проекта — автосканирование. |
| `.agents/skills/<name>/SKILL.md` (проект) | Скиллы из стандартной директории `.agents/skills/` — автосканирование. |
| `~/.gemini/skills/<name>/SKILL.md` (глобально) | Глобальные пользовательские скиллы. |
| `~/.agents/skills/<name>/SKILL.md` (глобально) | Глобальные `.agents/skills/` — автосканирование. |
| `gemini skills install <source>` | Установка скилла из git-репозитория или локального пути. |
| `gemini skills link <path>` | Символическая ссылка на скилл (для разработки). |
| `gemini skills enable/disable <name>` | Управление доступностью скиллов. |
| Extensions | Расширения могут добавлять свои скиллы. |

### Формат SKILL.md

Gemini CLI использует [Agent Skills standard](https://agentskills.io/specification) — тот же формат, что и Pi:

```markdown
---
name: my-skill
description: Description of the skill
---
# My Skill
Instructions...
```

### Механика

1. При старте Gemini CLI сканирует директории скиллов.
2. Метаданные (name, description) добавляются в системный промпт.
3. При совпадении задачи модель загружает полный `SKILL.md` через `read_file`.

### Разные скиллы разным ролям

**Частично.** Скиллы привязаны к проекту (`.gemini/skills/`, `.agents/skills/`) или глобально (`~/.gemini/skills/`, `~/.agents/skills/`). Нет CLI-флага для фильтрации скиллов при запуске (аналог `--skill` в Pi или `--no-skills`). Для разных ролей придётся либо:
- Управлять через `gemini skills enable/disable` перед запуском
- Использовать разные рабочие директории с разным набором скиллов

### Наша структура (`docs/agents/skills/`)

Наши скиллы лежат в `docs/agents/skills/`, а не в `.gemini/skills/` или `.agents/skills/`. Для подключения:

1. **`gemini skills link`** — создать симлинк на каждый скилл
2. **Скопировать** скиллы в `.gemini/skills/` или `.agents/skills/`

### Оценка: ⚠️ Частичная поддержка

Gemini CLI поддерживает Agent Skills standard с автосканированием `.agents/skills/`. Однако нет CLI-флага для явного указания скиллов при запуске (`--skill`) и нет флага отключения автосканирования (`--no-skills`). Назначение разных скиллов разным ролям затруднено.

---

## Критерий 4. AGENTS.md (контекстные файлы)

### Возможности

| Механизм | Поведение |
|----------|-----------|
| GEMINI.md (автообнаружение) | **Основной формат** — автоматически обнаруживается при старте. |
| `context.fileName` в settings.json | Настройка имён файлов контекста — можно добавить `AGENTS.md`. |
| Обход директорий вверх | Сканирует от cwd вверх до git root или boundary markers. |
| Глобальный `~/.gemini/GEMINI.md` | Глобальные инструкции пользователя (память). |

### Поддержка AGENTS.md

Gemini CLI **по умолчанию загружает только GEMINI.md**. Но через настройку `context.fileName` в `.gemini/settings.json` можно добавить `AGENTS.md`:

```json
{
  "context": {
    "fileName": ["GEMINI.md", "AGENTS.md"]
  }
}
```

После этого оба файла (`GEMINI.md` и `AGENTS.md`) будут автоматически обнаруживаться и загружаться.

### Отключение контекстных файлов

Прямого флага `--no-context-files` (как у Pi) **нет**. GEMINI.md-файлы всегда добавляются поверх системного промпта.

### Порядок загрузки

1. `~/.gemini/GEMINI.md` — глобальный (память пользователя)
2. Родительские директории от cwd вверх (до git root)
3. `.gemini/GEMINI.md` в проекте (при наличии)

### Пример

```bash
# С автообнаружением GEMINI.md и AGENTS.md (нужна настройка в settings.json)
gemini -p "Prompt" -o json

# Файлы проекта: /home/dp/MyProjects/task-orchestrator/GEMINI.md
#               + /home/dp/MyProjects/task-orchestrator/AGENTS.md (если настроено)
```

### Оценка: ⚠️ Частичная поддержка

GEMINI.md поддерживается из коробки. AGENTS.md — через настройку `context.fileName`. Нет флага отключения контекстных файлов из CLI (только через удаление файлов).

---

## Критерий 5. Стандартная папка `.agents/skills/`

### Автосканирование

Gemini CLI **поддерживает** автосканирование `.agents/skills/` из коробки:

| Локация | Правила сканирования |
|---------|---------------------|
| `~/.gemini/skills/` | Директории с `SKILL.md` |
| `~/.agents/skills/` | Директории с `SKILL.md` |
| `.gemini/skills/` (в cwd и ancestor dirs) | Директории с `SKILL.md` |
| `.agents/skills/` (в cwd и ancestor dirs) | Директории с `SKILL.md` |
| `gemini skills install/link` | Явная установка или симлинк |

### Подтверждение тестированием

Скиллы из `.agents/skills/` обнаруживаются автоматически и попадают в системный промпт. Проверено: модель видит и называет скиллы из обоих расположений (`.gemini/skills/` и `.agents/skills/`).

### Наша структура (`docs/agents/skills/`)

Автосканирование `docs/agents/skills/` **не поддерживается** — только стандартные пути. Для подключения наших скиллов:

1. **`gemini skills link docs/agents/skills/agent-report --scope workspace`** — симлинк
2. **Скопировать** в `.gemini/skills/` или `.agents/skills/`

### Оценка: ✅ Поддерживается

Стандарт `.agents/skills/` поддерживается из коробки. Наша нестандартная структура `docs/agents/skills/` требует подключения через `link` или копирование.

---

## Критерий 6. Запуск как сабагент (JSON-режим)

### Возможности

| Опция | Поведение |
|-------|-----------|
| `-o json` / `--output-format json` | Выводит итоговый результат как JSON-объект (response + stats). |
| `-o stream-json` | Стриминг событий в JSONL-формате (init, message, tool_use, tool_result, result). |
| `-p "prompt"` / `--prompt` | Non-interactive (headless) режим — один запрос и выход. |
| `--yolo` | Авто-подтверждение всех действий (YOLO mode). |
| `--approval-mode auto_edit` | Авто-подтверждение только операций редактирования. |
| `--approval-mode plan` | Read-only режим (только чтение). |
| `-m <model>` | Выбор модели. |
| `--sandbox` | Изоляция выполнения в sandbox. |

### Формат JSON (итоговый)

```json
{
  "session_id": "uuid",
  "response": "Текстовый ответ агента",
  "stats": {
    "models": {
      "gemini-3-flash-preview": {
        "api": { "totalRequests": 3, "totalErrors": 0, "totalLatencyMs": 15234 },
        "tokens": { "input": 2010, "prompt": 9792, "candidates": 1, "total": 9859, "cached": 7782, "thoughts": 66, "tool": 0 },
        "roles": { "main": { "totalRequests": 3, ... } }
      }
    },
    "tools": {
      "totalCalls": 5, "totalSuccess": 5, "totalFail": 0, "totalDurationMs": 3200,
      "totalDecisions": { "accept": 0, "reject": 0, "modify": 0, "auto_accept": 5 },
      "byName": { "read_file": { "count": 3, "totalDurationMs": 200 }, "write_file": { "count": 2, "totalDurationMs": 3000 } }
    },
    "files": { "totalLinesAdded": 45, "totalLinesRemoved": 12 }
  }
}
```

### Формат stream-json (JSONL)

```
{"type":"init","timestamp":"...","session_id":"uuid","model":"gemini-3-flash-preview"}
{"type":"message","timestamp":"...","role":"user","content":"Prompt"}
{"type":"message","timestamp":"...","role":"assistant","content":"...","delta":true}
{"type":"tool_use","timestamp":"...","tool_name":"read_file","tool_id":"...","parameters":{"file_path":"/path"}}
{"type":"tool_result","timestamp":"...","tool_id":"...","status":"success","output":"..."}
{"type":"result","timestamp":"...","status":"success","stats":{...}}
```

### Контроль таймаутов

Встроенного CLI-таймаута **нет**. Таймауты контролируются внешним процессом:

```bash
# Через timeout (Linux/macOS)
timeout 600 gemini -p "Prompt" -o stream-json --yolo

# В watch-subagent.sh
timeout ${HARD_TIMEOUT} gemini -p "$PROMPT" -o stream-json --yolo
```

### Пример потока данных

```mermaid
sequenceDiagram
    participant Orchestrator
    participant wrapper.sh
    participant gemini (-o stream-json --yolo)
    participant Gemini API

    Orchestrator->>wrapper.sh: Запуск с таймаутом и ролью
    wrapper.sh->>gemini (-o stream-json --yolo): gemini -p "prompt" -o stream-json --yolo
    gemini (-o stream-json --yolo)->>gemini (-o stream-json --yolo): Загрузка скиллов, GEMINI.md
    gemini (-o stream-json --yolo)->>Gemini API: API-запрос
    Gemini API-->>gemini (-o stream-json --yolo): Streaming-ответ
    gemini (-o stream-json --yolo)-->>wrapper.sh: JSONL-события (init, message, tool_use, tool_result, result)
    wrapper.sh->>wrapper.sh: Парсинг stream-json событий
    Note over gemini (-o stream-json --yolo): Модель вызывает tools (read_file, write_file, shell)
    gemini (-o stream-json --yolo)-->>wrapper.sh: result event (status: success/error)
    wrapper.sh-->>Orchestrator: Финальный результат (exit 0 = успех, exit 124 = таймаут)
```

### Оценка: ✅ Полная поддержка

Gemini CLI хорошо подходит для запуска как сабагент. `stream-json` обеспечивает стриминг событий в реальном времени, `--yolo` отключает интерактивные подтверждения, `-p` — non-interactive режим. Телеметрия богата: токены, tool calls, файловые изменения, latency.

---

## Критерий 7. Токены и стоимость

### Доступные метрики

В JSON/stream-json выводе доступен объект `stats`:

```typescript
interface ModelStats {
  api: {
    totalRequests: number;    // Всего API-запросов
    totalErrors: number;      // Ошибки
    totalLatencyMs: number;   // Общая задержка
  };
  tokens: {
    input: number;            // Входные токены (за вычетом кеша)
    prompt: number;           // Полный размер промпта
    candidates: number;       // Выходные токены (candidates)
    total: number;            // Всего токенов
    cached: number;           // Токены из кеша
    thoughts: number;         // Токены thinking
    tool: number;             // Токены tool calls
  };
}

interface ToolStats {
  totalCalls: number;
  totalSuccess: number;
  totalFail: number;
  totalDurationMs: number;
  totalDecisions: { accept, reject, modify, auto_accept };
  byName: Record<string, { count, totalDurationMs }>;
}

interface FileStats {
  totalLinesAdded: number;
  totalLinesRemoved: number;
}
```

### Расчёт стоимости

Gemini CLI **не рассчитывает стоимость** в денежном выражении — только токены. Для расчёта стоимости нужно знать тарифы Gemini API:

| Метрика | Описание |
|---------|----------|
| `tokens.input` | Входные токены (оплачиваемые, за вычетом кеша) |
| `tokens.cached` | Токены из контекстного кеша (более дешёвые) |
| `tokens.candidates` | Выходные токены (оплачиваемые) |
| `tokens.thoughts` | Токены thinking (учитываются в billing) |

### Извлечение метрик

```bash
# Из JSON вывода
gemini -p "Prompt" -o json 2>/dev/null | jq '.stats.models'

# Из stream-json — последний result event
gemini -p "Prompt" -o stream-json 2>/dev/null | jq 'select(.type == "result") | .stats'
```

### Оценка: ✅ Полная поддержка

Полная телеметрия по токенам (input, output, cached, thoughts, tool), API-запросам, latency и файловым изменениям. Стоимость в деньгах не рассчитывается, но все данные для расчёта доступны.

---

## Критерий 8. Free tier

### Gemini CLI как продукт

Gemini CLI — **open-source** (Apache-2.0) CLI-инструмент. Сам по себе бесплатный. Стоимость определяется тарифами Google Gemini API.

### Бесплатный тариф (OAuth-авторизация)

| Параметр | Значение |
|----------|----------|
| Авторизация | OAuth (личный Google-аккаунт) — «Sign in with Google» |
| RPM (requests/min) | **60** |
| Запросов в день | **1,000** |
| Модели | Gemini 3 Pro Preview, Gemini 3 Flash Preview, Gemini 2.5 Pro, Gemini 2.5 Flash |
| Контекст | 1M токенов |

### Бесплатный тариф (API key)

| Параметр | Значение |
|----------|----------|
| Авторизация | `GEMINI_API_KEY` (бесплатный ключ из Google AI Studio) |
| Запросов в день | **1,000** (Gemini 3 — mix flash и pro) |
| Модели | Gemini 3 + все модели Gemini 2.5 |
| Usage-based billing | Платное расширение при превышении лимитов |

### Vertex AI

Enterprise-вариант с биллингом через Google Cloud. Подключается через `GOOGLE_API_KEY` или Application Default Credentials.

### AI Credits

Gemini CLI поддерживает **AI credits** — платные кредиты для превышения бесплатных лимитов. Настройка через `settings.billing.overageStrategy`: `ask` / `always` / `never`.

### Оценка: ✅ Щедрый бесплатный тариф

60 RPM и 1000 запросов/день — значительно больше, чем у большинства конкурентов. Доступ к новейшим моделям Gemini 3. Для использования как сабагент — более чем достаточно.

---

## Критерий 9. Провайдеры и модели

### Поддерживаемые провайдеры

Gemini CLI — **эксклюзивно привязан к Google Gemini API**. Другие провайдеры (OpenAI, Anthropic, Ollama и т.д.) **не поддерживаются**.

| Провайдер | Механизм подключения |
|-----------|---------------------|
| **Google Gemini API** (бесплатный) | OAuth или `GEMINI_API_KEY` |
| **Google Vertex AI** | `GOOGLE_API_KEY` + `GOOGLE_CLOUD_PROJECT` + `GOOGLE_CLOUD_LOCATION` |
| **Google Cloud Shell** | Автоматическая аутентификация (`CLOUD_SHELL=true`) |
| **Application Default Credentials** | `GEMINI_CLI_USE_COMPUTE_ADC=true` |

### Доступные модели

| Модель | Описание |
|--------|----------|
| `gemini-3-pro-preview` | Флагманская модель Gemini 3 (preview) |
| `gemini-3.1-pro-preview` | Gemini 3.1 Pro (preview) |
| `gemini-3-flash-preview` | Быстрая модель Gemini 3 (preview) |
| `gemini-3.1-flash-lite-preview` | Лёгкая модель Gemini 3.1 (preview) |
| `gemini-2.5-pro` | Стабильная Gemini 2.5 Pro |
| `gemini-2.5-flash` | Стабильная Gemini 2.5 Flash |
| `gemini-2.5-flash-lite` | Лёгкая Gemini 2.5 Flash Lite |
| `auto` | Автоматический выбор (Gemini 3 → Gemini 2.5) |
| `pro` | Алиас для Pro-модели |
| `flash` | Алиас для Flash-модели |
| `flash-lite` | Алиас для Flash Lite-модели |

### BYOK (Bring Your Own Key)

Частично. «Свой ключ» — это `GEMINI_API_KEY` или `GOOGLE_API_KEY` (для Vertex AI). Другие провайдеры не поддерживаются.

### Локальные модели (Ollama, LM Studio)

**Не поддерживаются.** Gemini CLI работает исключительно с Google Gemini API.

### Переключение моделей

```bash
# CLI
gemini -m gemini-2.5-pro -p "Prompt"
gemini -m flash -p "Prompt"           # алиас
gemini -m gemini-3-flash-preview -p "Prompt"

# Settings.json
{
  "model": {
    "name": "gemini-3-flash-preview"
  }
}
```

### Оценка: ❌ Только Google Gemini

Gemini CLI поддерживает **исключительно** модели Google Gemini. Нет поддержки OpenAI, Anthropic, Ollama, LM Studio или других провайдеров. Это фундаментальное ограничение — привязка к одному провайдеру.

---

## Критерий 10. Лицензия

### Информация

| Параметр | Значение |
|----------|----------|
| Пакет | `@google/gemini-cli` |
| Организация | Google (`google-gemini`) |
| Лицензия | **Apache-2.0** |
| Репозиторий | https://github.com/google-gemini/gemini-cli |
| Язык | TypeScript |

### Условия

Apache-2.0 разрешает:
- Коммерческое использование
- Модификацию
- Распространение
- Патентное использование
- Private use

Требования:
- Включить копию лицензии
- Уведомление об авторских правах
- Указать изменения в файле

### Оценка: ✅ Open source, Apache-2.0

---

## Вердикт

### ⚠️ Частично подходит (Score: 6/10)

Gemini CLI **частично подходит** для использования как сабагент с нашей системой ролей и скиллов. Имеет сильные стороны как самостоятельный coding-агент, но несколько ограничений для нашей архитектуры сабагентов.

### Сильные стороны

1. **Щедрый бесплатный тариф** — 60 RPM / 1000 запросов/день с доступом к Gemini 3
2. **stream-json** — качественный JSONL-стриминг для интеграции как сабагент
3. **Система скиллов** — поддержка Agent Skills standard, автосканирование `.agents/skills/`
4. **Богатая телеметрия** — токены (input, output, cached, thoughts), API calls, latency, файловые изменения
5. **`--yolo` / `--approval-mode`** — гибкое управление подтверждениями
6. **Apache-2.0** — открытая лицензия
7. **GEMINI.md + AGENTS.md** — автообнаружение контекстных файлов проекта
8. **1M контекст** — большой контекстный window у Gemini 3

### Ключевые ограничения

1. **❌ Только Google Gemini** — нет поддержки других провайдеров (OpenAI, Anthropic, Ollama). Привязка к одному вендору.
2. **⚠️ Нет `--append-system-prompt`** — нельзя дополнить системный промпт. Только полная замена через `GEMINI_SYSTEM_MD`.
3. **⚠️ Нет `--skill` / `--no-skills`** — нельзя явно указать скиллы при запуске. Нет фильтрации для разных ролей.
4. **⚠️ Нет `--no-context-files`** — нельзя отключить загрузку GEMINI.md из CLI.
5. **⚠️ Роль через user prompt** — инъекция роли менее элегантна, чем у Pi.

### Сравнение с Pi (текущий сабагент)

| Критерий | Pi | Gemini CLI |
|----------|-----|------------|
| Системный промпт (замена) | ✅ `--system-prompt` | ✅ `GEMINI_SYSTEM_MD` |
| Системный промпт (append) | ✅ `--append-system-prompt` | ❌ Нет |
| Скиллы (CLI-фильтрация) | ✅ `--skill`, `--no-skills` | ❌ Нет |
| AGENTS.md | ✅ Из коробки | ⚠️ Через settings |
| JSON-режим | ✅ `--mode json` | ✅ `-o stream-json` |
| Провайдеры | ✅ 20+ провайдеров | ❌ Только Google |
| Free tier | Зависит от провайдера | ✅ 60 RPM / 1000/день |
| Лицензия | MIT | Apache-2.0 |

### Рекомендация

Gemini CLI стоит рассмотреть как **дополнительного** сабагента для задач, где:
- Достаточно Google Gemini моделей (gemini-3-flash-preview быстр и дёшев)
- Важен бесплатный тариф (продакшн-нагрузка без затрат)
- Не нужна гибкая инъекция ролей и скиллов

Но **не заменяет** Pi как основной сабагент из-за:
- Привязки к Google Gemini
- Отсутствия `--append-system-prompt`
- Отсутствия CLI-фильтрации скиллов

---

## Приложение А. Практические примеры запуска

### Запуск как сабагент (non-interactive, JSON output)

```bash
# Базовый запуск
gemini -p "Проанализируй архитектуру проекта" -o json --yolo

# С кастомной моделью
gemini -m gemini-3-flash-preview -p "Найди баги в tests/" -o json --yolo

# С кастомным системным промптом
GEMINI_SYSTEM_MD=scripts/gemini-subagent-system.md \
  gemini -p "Выполни задачу: todo/TASK-xxx.todo.md" -o json --yolo
```

### Запуск со stream-json для мониторинга в реальном времени

```bash
# Запуск с таймаутом и stream-json
timeout 600 gemini -p "Реализуй фичу X" -o stream-json --yolo 2>/dev/null | \
  while IFS= read -r line; do
    event=$(echo "$line" | jq -r '.type')
    case "$event" in
      tool_use)   echo "TOOL: $(echo "$line" | jq -r '.tool_name')" ;;
      tool_result) echo "RESULT: $(echo "$line" | jq -r '.status')" ;;
      result)     echo "DONE: $(echo "$line" | jq -r '.status')" ;;
    esac
  done
```

### Read-only режим (анализ / ревью)

```bash
# Plan mode — только чтение
gemini -p "Проведи ревью архитектуры" --approval-mode plan -o json
```

### Запуск с инъекцией роли

```bash
# Через user prompt
gemini -p "Возьми на себя роль из файла: docs/agents/roles/team/code_reviewer_backend_puaro.ru.md
Проведи ревью PR #123" -o stream-json --yolo
```

---

## Приложение Б. Настройка проекта для Gemini CLI

### `.gemini/settings.json` — конфигурация проекта

```json
{
  "context": {
    "fileName": ["GEMINI.md", "AGENTS.md"]
  },
  "model": {
    "name": "gemini-3-flash-preview"
  }
}
```

### Подключение скиллов из `docs/agents/skills/`

```bash
# Создать симлинки для нужных скиллов
gemini skills link docs/agents/skills/agent-report --scope workspace
gemini skills link docs/agents/skills/run-pi-subagent --scope workspace
```

---

## Источники

1. `gemini --help` — CLI-параметры (v0.37.2)
2. [Gemini CLI — GitHub](https://github.com/google-gemini/gemini-cli) — репозиторий и README
3. [Gemini CLI — документация](https://geminicli.com/docs/) — официальная документация
4. Исследование исходного кода (`bundle/chunk-6JWICRU7.js`, `bundle/chunk-5OOT636U.js`, `bundle/chunk-4UMQLF27.js`)
5. Практическое тестирование: JSON, stream-json, скиллы, GEMINI_SYSTEM_MD, контекстные файлы
