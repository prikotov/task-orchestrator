# Исследование: Charmbracelet Crush — терминальный AI-агент (Go)

> **Проект:** [github.com/charmbracelet/crush](https://github.com/charmbracelet/crush)
> **Дата анализа:** 2026-04-21
> **Язык:** Go
> **Лицензия:** FSL-1.1-MIT (Functional Source License, MIT через 2 года)
> **Аналитик:** Технический писатель (Гермиона)

---

## 1. Обзор проекта

Crush — терминальный AI-кодинг-ассистент от Charmbracelet (авторы Bubbletea, Lip Gloss, VHS). Ключевая идея: интерактивный TUI-клиент для работы с LLM-моделями в контексте проекта, с доступом к файловой системе, инструментам, LSP и MCP-серверам.

Crush **не является** фреймворком оркестрации агентов. Это **одноагентный CLI-инструмент** для интерактивной работы разработчика с LLM. В отличие от task-orchestrator, Crush не поддерживает цепочки шагов, retry-механизмы, circuit breaker, budget control или quality gates.

### Архитектура

```
main.go                                CLI entry point (cobra)
internal/
  app/app.go                           Top-level wiring: DB, config, agents, LSP, MCP, events
  cmd/                                 CLI commands (root, run, login, models, stats, sessions)
  config/                              Config: crush.json загрузка, provider, model resolution
  agent/
    agent.go                           SessionAgent: запуск LLM-сессий, tool calls, summarization
    coordinator.go                     Coordinator: управление агентами, моделями, провайдерами
    prompts.go                         Загрузка Go-template системных промптов
    templates/                         coder.md.tpl, task.md.tpl, summary.md, title.md
    tools/                             Все встроенные инструменты (bash, edit, view, grep, glob и т.д.)
      mcp/                             MCP-клиент интеграция
  session/                             Session CRUD (SQLite)
  message/                             Message model, content types
  db/                                  SQLite через sqlc + миграции
  lsp/                                 LSP client manager, auto-discovery
  ui/                                  Bubble Tea v2 TUI
  permission/                          Tool permission checking, allow-lists
  skills/                              Skill discovery (SKILL.md), загрузка, дедупликация
  shell/                               Bash execution с background job support
  event/                               Telemetry (PostHog)
  pubsub/                              Внутренний pub/sub для кросс-компонентного обмена
  filetracker/                         Отслеживание изменённых файлов в сессии
  history/                             Prompt history
```

### Ключевые характеристики

| Характеристика | Значение |
|---|---|
| **Тип** | CLI-агент (TUI), одноагентный |
| **Модель выполнения** | Agent loop (LLM → tool call → LLM → ...) |
| **State management** | SQLite (сессии, сообщения, файл-трекинг) |
| **Провайдеры** | 20+ LLM-провайдеров (Anthropic, OpenAI, Google, Bedrock, Azure, OpenRouter и т.д.) |
| **Расширяемость** | MCP-серверы, Agent Skills (SKILL.md), LSP |
| **Интерфейс** | Bubble Tea v2 TUI, интерактивный терминал |
| **Платформы** | macOS, Linux, Windows, FreeBSD, Android |

### Основные компоненты

| Компонент | Назначение |
|---|---|
| [`internal/agent/agent.go`](https://github.com/charmbracelet/crush/blob/main/internal/agent/agent.go) | SessionAgent: LLM-сессия, streaming, tool calls, auto-summarization |
| [`internal/agent/coordinator.go`](https://github.com/charmbracelet/crush/blob/main/internal/agent/coordinator.go) | Coordinator: модели, провайдеры, инструменты, sub-agent'ы |
| [`internal/skills/skills.go`](https://github.com/charmbracelet/crush/blob/main/internal/skills/skills.go) | Agent Skills: обнаружение, парсинг, валидация SKILL.md |
| [`internal/permission/permission.go`](https://github.com/charmbracelet/crush/blob/main/internal/permission/permission.go) | Permission: проверка разрешений для tool calls |
| [`internal/config/config.go`](https://github.com/charmbracelet/crush/blob/main/internal/config/config.go) | Конфигурация: providers, models, LSP, MCP, options |
| [`internal/session/session.go`](https://github.com/charmbracelet/crush/blob/main/internal/session/session.go) | Session CRUD (SQLite), todos, cost tracking |
| [`internal/agent/tools/`](https://github.com/charmbracelet/crush/blob/main/internal/agent/tools/) | ~25 инструментов (bash, edit, view, grep, glob, write, fetch и т.д.) |

---

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | TasK Orchestrator | Crush | Статус |
|---|---|---|---|
| **Цепочки шагов (chains)** | ✅ YAML chains, статические и динамические | ❌ Нет. Agent loop с tool calls | ✅ У нас есть |
| **Retry с backoff** | ✅ RetryingAgentRunner | ❌ Нет явного retry (только retry при 401) | ✅ У нас есть |
| **Circuit Breaker** | ✅ CircuitBreakerAgentRunner | ❌ Нет | ✅ У нас есть |
| **Quality Gates** | ✅ Shell-команды как проверки | ❌ Нет | ✅ У нас есть |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based) | ⚠️ Cost tracking (только запись, без лимитов) | ✅ У нас лучше |
| **Итерационные циклы (fix_iterations)** | ✅ Группа шагов с max_iterations | ❌ Нет | ✅ У нас есть |
| **Fallback routing** | ✅ Per-step fallback runner | ❌ Нет (пользователь переключает модель вручную) | ✅ У нас есть |
| **Audit Trail (JSONL)** | ✅ JsonlAuditLogger | ⚠️ Логирование в `.crush/logs/`, не JSONL | ✅ У нас есть |
| **Ролевые промпты** | ✅ .md файлы (18+ ролей) | ✅ Go-template промпты (coder, task) | ✅ У нас лучше |
| **Multiple runners** | ✅ Pi + Codex (через interface) | ✅ 20+ провайдеров через `charm.land/fantasy` | ✅ Паритет |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❌ Плоская структура `internal/` | ✅ У нас лучше |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Прямой вызов | ✅ У нас лучше |
| **YAML-конфигурация** | ✅ Chains + roles в YAML | ✅ crush.json (JSON) | ✅ Паритет |
| **Session persistence** | ❌ Нет (in-memory) | ✅ SQLite: сессии, сообщения, миграции | 🟡 Позже |
| **LSP-интеграция** | ❌ Нет | ✅ LSP Manager, auto-discovery, diagnostics/references | 🟢 Не берём |
| **MCP-протокол** | ❌ Нет | ✅ stdio, HTTP, SSE транспорта | 🟡 Позже |
| **Agent Skills (SKILL.md)** | ✅ Свои role .md файлы | ✅ Обнаружение SKILL.md, валидация, дедупликация | ✅ Паритет |
| **TUI-интерфейс** | ❌ CLI Symfony Console | ✅ Bubble Tea v2, богатый TUI | 🟢 Не берём |
| **Permission system** | ❌ Нет | ✅ Allow-list, persistent grants, auto-approve | 🟡 Позже |
| **Context file discovery** | ✅ AGENTS.md, role .md | ✅ AGENTS.md, CRUSH.md, CLAUDE.md, GEMINI.md, .cursorrules | 🟡 Интересно |
| **Auto-summarization** | ❌ Нет | ✅ При превышении context window | 🟡 Позже |
| **Sub-agent / multi-agent** | ❌ Нет | ⚠️ Заложена архитектура (coordinator, но 1 агент) | 🟡 Позже |
| **Cost tracking per session** | ✅ Через budget/check | ✅ Session.Cost, per-session accumulation | ✅ Паритет |
| **Tool self-documentation** | ❌ Нет | ✅ Каждый инструмент = .go + .md файл | 🟡 Интересно |
| **Loop detection** | ❌ Нет | ✅ Повторяющиеся tool calls → стоп | 🟡 Интересно |

---

## 3. Что полезно взять и почему

### 3.1 🟡 Agent Skills — стандарт SKILL.md (`internal/skills/`)

**Что у них:** Каждая «способность» агента — это папка с `SKILL.md` (YAML frontmatter + markdown body). Система автоматически обнаруживает навыки в нескольких местах:

```yaml
# SKILL.md
---
name: shell-builtins
description: Use when creating a new shell builtin command for Crush
---
# Shell Builtins
Crush's shell uses `mvdan.cc/sh/v3`...
```

**Механика обнаружения:**
- Global: `~/.config/crush/skills/`, `~/.config/agents/skills/`
- Project: `.agents/skills/`, `.crush/skills/`, `.claude/skills/`, `.cursor/skills/`
- Встроенные (embedded): `internal/skills/builtin/*/SKILL.md`

**Валидация:** имя = имя папки, regex на имя, max description, дедупликация (user > builtin).

**Почему нам интересно:** Наша система ролей (.md файлы) концептуально похожа, но менее формализована. Стандарт [agentskills.io](https://agentskills.io) может стать основой для унификации discovery и валидации.

**Отличие от нашей реализации:**
- У нас: role .md = просто промпт, загружается по имени из YAML
- У них: SKILL.md = формализованный навык с YAML frontmatter, валидацией, иерархией (builtin vs user), auto-injection в system prompt

---

### 3.2 🟡 Loop Detection — обнаружение повторяющихся tool calls (`internal/agent/agent.go`)

**Что у них:** В `StopWhen` агент останавливается, если обнаруживает повторяющиеся tool calls:

```go
StopWhen: []fantasy.StopCondition{
    func(steps []fantasy.StepResult) bool {
        return hasRepeatedToolCalls(steps, loopDetectionWindowSize, loopDetectionMaxRepeats)
    },
},
```

**Почему нам интересно:** При итерационных циклах (fix_iterations) если агент «зацикливается» — бесконечно повторяет одни и те же действия — нужен механизм обнаружения и остановки. У нас пока нет защиты от такого сценария.

---

### 3.3 🟡 Auto-summarization при переполнении контекста (`internal/agent/agent.go`)

**Что у них:** Когда суммарное число токенов (prompt + completion) приближается к context window, агент автоматически:
1. Останавливает выполнение
2. Вызывает `Summarize()` — создаёт summary через LLM
3. Заменяет историю сообщений на summary
4. Продолжает работу с новым контекстом

```go
StopWhen: []fantasy.StopCondition{
    func(_ []fantasy.StepResult) bool {
        cw := int64(largeModel.CatwalkCfg.ContextWindow)
        tokens := currentSession.CompletionTokens + currentSession.PromptTokens
        remaining := cw - tokens
        // Если осталось меньше порога → summarize
        ...
    },
},
```

**Почему нам интересно:** Для длинных цепочек (implement → review → fix → review → ...) контекст может расти. Auto-summarization позволяет работать с arbitrarily длинными сессиями.

**Отличие:** У нас цепочки конечные (max_iterations), поэтому переполнение контекста менее вероятно, но для будущих dynamic loops может стать актуальным.

---

### 3.4 🟡 Permission System (`internal/permission/`)

**Что у них:** Система разрешений для tool calls с несколькими уровнями:

1. **Skip all** (`--yolo`): все tool calls выполняются без запроса
2. **Allow-list** (config): `"allowed_tools": ["view", "ls", "grep"]` — автоматическое разрешение
3. **Session-level**: после разрешения действия для tool+path — запоминается в сессии
4. **Per-request**: pub/sub → UI показывает диалог → пользователь подтверждает

**Почему нам интересно:** Если task-orchestrator будет выполнять автономные цепочки (без участия человека), нужна система контроля: какие команды можно выполнять, какие файлы можно редактировать.

---

### 3.5 🟡 Context File Discovery — множественные форматы контекста (`internal/config/`)

**Что у них:** Crush автоматически обнаруживает контекстные файлы проекта:

```go
var defaultContextPaths = []string{
    ".github/copilot-instructions.md",
    ".cursorrules",
    ".cursor/rules/",
    "CLAUDE.md",
    "GEMINI.md",
    "crush.md",
    "AGENTS.md",
    // ...
}
```

**Почему нам интересно:** Мы уже используем `AGENTS.md`, но можем добавить поддержку и других контекстных файлов для совместимости с разными AI-инструментами.

---

### 3.6 🟡 Tool Self-Documentation (`internal/agent/tools/`)

**Что у них:** Каждый инструмент — это два файла:
- `tool_name.go` — реализация (Go код)
- `tool_name.md` — описание для LLM (встраивается в system prompt)

Описание инструмента — это markdown с примерами использования, который LLM получает как часть контекста.

**Почему нам интересно:** Формализация описания инструментов через .md файлы — хороший паттерн. Мы уже используем .md для промптов ролей, но могли бы формализовать описание runners/tools аналогично.

---

## 4. Что НЕ берём и почему

### 4.1 🟢 TUI-интерфейс (Bubble Tea v2)

Crush — интерактивный TUI-клиент. Task-orchestrator — CLI-утилита для автоматического выполнения цепочек. Разные парадигмы: интерактивный чат vs. автоматизированный pipeline.

### 4.2 🟢 LSP-интеграция

LSP (Language Server Protocol) даёт Crush diagnostics, references, code intelligence. Это valuable для интерактивного кодинг-ассистента, но не для оркестратора цепочек — наши цепочки запускают внешние CLI-инструменты (pi, codex), которые сами работают с кодом.

### 4.3 🟢 SQLite Session Persistence

Crush хранит все сессии и сообщения в SQLite. Для интерактивного чата это оправдано. Наш оркестратор запускается, выполняет цепочку, завершается. In-memory + JSONL audit trail достаточно.

### 4.4 🟢 Multi-provider abstraction (charm.land/fantasy)

Crush абстрагирует 20+ провайдеров через библиотеку `fantasy`. Наш оркестратор работает через runner'ы (pi, codex), каждый из которых сам общается с конкретным API. Нам не нужна собственная абстракция над провайдерами — это задача runner'ов.

### 4.5 🟢 MCP (Model Context Protocol) поддержка

MCP — протокол расширения возможностей агента через внешние серверы. Для интерактивного ассистента — отлично. Для автоматического pipeline — overhead. Если понадобится, добавим через отдельный runner.

### 4.6 🟢 Telemetry (PostHog)

Crush собирает метрики использования. Не актуально для open-source CLI-утилиты.

---

## 5. Сводка рекомендаций

| Фича | Приоритет | Обоснование |
|---|---|---|
| Chain orchestration | ✅ Уже есть | Core-функциональность task-orchestrator |
| Retry + Circuit Breaker | ✅ Уже есть | Устойчивость при сбоях |
| Quality Gates | ✅ Уже есть | Автоматическая проверка кода |
| Budget control | ✅ Уже есть | Предотвращение runaway spending |
| Fix iterations | ✅ Уже есть | Closed-loop цикл разработки |
| Agent Skills (SKILL.md) | 🟡 P2 | Формализация discovery и валидации ролей |
| Loop detection | 🟡 P2 | Защита от зацикливания в итерационных циклах |
| Auto-summarization | 🟡 P3 | Для длинных dynamic loops |
| Permission system | 🟡 P3 | Для автономного выполнения (без человека) |
| Context file discovery | 🟡 P3 | Совместимость с разными AI-инструментами |
| Tool self-documentation | 🟡 P3 | Формализация описания runners/tools |
| TUI | 🟢 — | Разная парадигма |
| LSP integration | 🟢 — | Задача runner'ов, не оркестратора |
| SQLite persistence | 🟢 — | In-memory + JSONL достаточно |
| Multi-provider abstraction | 🟢 — | Задача runner'ов |
| MCP support | 🟢 — | Не нужно для pipeline |
| Telemetry | 🟢 — | Не актуально |

---

## 6. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории Crush:

- [`internal/agent/agent.go`](https://github.com/charmbracelet/crush/blob/main/internal/agent/agent.go) — SessionAgent: agent loop, streaming, tool calls, auto-summarization, loop detection
- [`internal/agent/coordinator.go`](https://github.com/charmbracelet/crush/blob/main/internal/agent/coordinator.go) — Coordinator: управление моделями, провайдерами, инструментами, sub-agent'ами
- [`internal/skills/skills.go`](https://github.com/charmbracelet/crush/blob/main/internal/skills/skills.go) — Agent Skills: обнаружение, парсинг, валидация, дедупликация SKILL.md
- [`internal/permission/permission.go`](https://github.com/charmbracelet/crush/blob/main/internal/permission/permission.go) — Permission system: allow-list, persistent grants, auto-approve
- [`internal/config/config.go`](https://github.com/charmbracelet/crush/blob/main/internal/config/config.go) — Конфигурация: providers, models, context file paths
- [`internal/session/session.go`](https://github.com/charmbracelet/crush/blob/main/internal/session/session.go) — Session persistence (SQLite), todos, cost tracking
- [`internal/agent/templates/coder.md.tpl`](https://github.com/charmbracelet/crush/blob/main/internal/agent/templates/coder.md.tpl) — Системный промпт для coder-агента (Go template)
- [`AGENTS.md`](https://github.com/charmbracelet/crush/blob/main/AGENTS.md) — Документация для AI-агентов (архитектура, style guide, commands)
- [`README.md`](https://github.com/charmbracelet/crush/blob/main/README.md) — Документация: features, конфигурация, MCP, LSP, skills
- [`schema.json`](https://github.com/charmbracelet/crush/blob/main/schema.json) — JSON Schema для crush.json конфигурации

---

📚 **Источники:**
1. [github.com/charmbracelet/crush](https://github.com/charmbracelet/crush) — репозиторий проекта
2. [agentskills.io](https://agentskills.io) — стандарт Agent Skills
3. [charm.land](https://charm.land) — экосистема Charmbracelet
4. [FSL-1.1-MIT License](https://github.com/charmbracelet/crush/blob/main/LICENSE.md) — лицензия
