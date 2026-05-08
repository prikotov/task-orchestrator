# Исследование: OpenCode — терминальный AI-ассистент на Go

> **Проект:** [github.com/opencode-ai/opencode](https://github.com/opencode-ai/opencode)
> **Дата анализа:** 2026-05-08
> **Язык:** Go 1.24.0
> **Лицензия:** MIT
> **Версия:** последняя (архивирован, development продолжен как [Charmbracelet Crush](https://github.com/charmbracelet/crush))
> **Аналитик:** Аналитик (Шерлок)

---

## 1. Обзор проекта

OpenCode — **терминальный AI-ассистент на Go** для разработки: TUI-интерфейс поверх множества LLM-провайдеров (OpenAI, Anthropic, Google Gemini, AWS Bedrock, Azure, Groq, OpenRouter, xAI, GitHub Copilot, VertexAI, self-hosted) с интеграцией инструментов (bash, file ops, grep/glob, LSP diagnostics, Sourcegraph, MCP), управлением сессиями и file versioning.

> ⚠️ **Важно:** Проект **архивирован** (README: «This repository is no longer maintained and has been archived for provenance. The project has continued under the name [Crush](https://github.com/charmbracelet/crush)»). OpenCode — предшественник Crush (строка #1 в сводной таблице). Исследование проводилось по финальному состоянию репозитория opencode-ai/opencode.

Архитектура OpenCode принципиально отличается от task-orchestrator: OpenCode — **интерактивный CLI-agent** (TUI, single user, real-time conversation), а task-orchestrator — **batch chain orchestrator** (YAML-цепочки, retry, circuit breaker, quality gates). OpenCode работает на уровне прямых LLM API (agent loop), task-orchestrator — на уровне оркестрации runner'ов.

### Архитектура

```
┌─────────────────────────────────────────────────────────────────┐
│  cmd/ (Cobra CLI)                                               │
│  • opencode              — TUI mode (Bubble Tea)                │
│  • opencode -p "..."     — Non-interactive prompt mode          │
│  • opencode -d           — Debug mode                           │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│  App (internal/app/app.go)                                      │
│  • Инициализация: Sessions, Messages, History, Permissions,     │
│    LSP Clients, CoderAgent                                      │
│  • RunNonInteractive() — single prompt → session → agent → exit │
│  • Shutdown() — cancel watchers, close LSP clients              │
└──────────────────────────┬──────────────────────────────────────┘
                           │
              ┌────────────┴─────────────┐
              ▼                          ▼
┌──────────────────────┐  ┌──────────────────────────────────────┐
│  TUI (internal/tui/) │  │  Agent (internal/llm/agent/)         │
│  • Bubble Tea        │  │  • Service interface:                 │
│  • Chat page         │  │    Run(ctx, sessionID, content)       │
│  • Editor (vim-like) │  │    Cancel(sessionID)                  │
│  • Session dialog    │  │    Summarize(ctx, sessionID)          │
│  • Model dialog      │  │  • Agent types:                       │
│  • Permission dialog │  │    coder (full tools)                 │
│  • Custom commands   │  │    task (read-only tools)             │
│  • Logs page         │  │    title (session title generation)   │
│                      │  │    summarizer (context compaction)    │
└──────────────────────┘  │  • Agent loop:                        │
                          │    for {                               │
                          │      stream LLM → collect events      │
                          │      if tool_use → execute tools       │
                          │      if end_turn → return response    │
                          │    }                                   │
                          └──────────────┬────────────────────────┘
                                         │
                     ┌───────────────────┼───────────────────┐
                     ▼                   ▼                   ▼
        ┌─────────────────┐  ┌──────────────────┐  ┌──────────────────┐
        │  Provider        │  │  Tools            │  │  MCP Tools       │
        │  (provider/)     │  │  (tools/)         │  │  (mcp-tools.go)  │
        │  • Anthropic     │  │  • bash (banned   │  │  • stdio         │
        │  • OpenAI        │  │    + safe read-   │  │  • SSE           │
        │  • Gemini        │  │    only lists)    │  │  • Permission    │
        │  • Bedrock       │  │  • glob, grep, ls │  │    per MCP tool  │
        │  • Azure         │  │  • view, write    │  │                  │
        │  • Groq          │  │  • edit, patch    │  │  auto-discovery  │
        │  • OpenRouter    │  │  • fetch          │  │  from config     │
        │  • xAI           │  │  • sourcegraph    │  └──────────────────┘
        │  • Copilot       │  │  • diagnostics    │
        │  • VertexAI      │  │  • agent (sub-    │
        │  • Local         │  │    task deleg.)   │
        └─────────────────┘  └──────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  Persistence Layer (SQLite via sqlc + goose migrations)          │
│  ┌───────────────┐  ┌──────────────┐  ┌──────────────────────┐  │
│  │  sessions      │  │  messages    │  │  files               │  │
│  │  • id (PK)     │  │  • id (PK)   │  │  • id (PK)           │  │
│  │  • parent_id   │  │  • session_id│  │  • session_id (FK)   │  │
│  │  • title       │  │  • role      │  │  • path              │  │
│  │  • tokens/cost │  │  • parts     │  │  • content           │  │
│  │  • summary_msg │  │  • model     │  │  • version           │  │
│  │               │  │  • finished   │  │                      │  │
│  └───────────────┘  └──────────────┘  └──────────────────────┘  │
│  Triggers: auto-update message_count, updated_at                 │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  Supporting Services                                             │
│  • LSP Client (internal/lsp/) — multi-language, diagnostics     │
│  • Permission (internal/permission/) — per-tool allow/deny      │
│  • PubSub Broker (internal/pubsub/) — typed generic events      │
│  • History (internal/history/) — file versioning (initial → vN) │
│  • Config (internal/config/) — JSON + env vars, multi-provider  │
│  • Logging (internal/logging/) — structured, panic recovery     │
└─────────────────────────────────────────────────────────────────┘
```

### Ключевые характеристики

| Характеристика | Значение |
|---|---|
| **Тип** | Терминальный AI-ассистент (CLI-agent с TUI) |
| **Модель выполнения** | Agent loop (LLM → tool call → observation → LLM → ...) с streaming |
| **Поддерживаемые провайдеры** | Anthropic, OpenAI, Gemini, Bedrock, Azure, VertexAI, Groq, OpenRouter, xAI, Copilot, Local (self-hosted) — 11 провайдеров |
| **State management** | Persistent (SQLite: sessions + messages + files с versioning) |
| **Инструменты** | 12 встроенных: bash, glob, grep, ls, view, write, edit, patch, fetch, sourcegraph, diagnostics, agent |
| **Агенты** | 4 типа: coder (full), task (read-only), title (summary), summarizer (compaction) |
| **MCP** | stdio + SSE, auto-discovery, permission per tool |
| **LSP** | Multi-language, diagnostics exposed to AI |
| **Расширяемость** | MCP servers, LSP servers, custom commands (Markdown), config-driven providers |
| **Error handling** | Basic: panic recovery, ErrRequestCancelled, ErrSessionBusy, context cancellation |
| **Permission system** | Per-tool per-session: allow/deny/auto-approve, banned commands, safe read-only list |
| **Auto-compact** | LLM-суммаризация при 95% context window, summary → новая сессия |
| **Context injection** | contextPaths: OpenCode.md, CLAUDE.md, .cursorrules, copilot-instructions.md и др. (11 путей) |
| **Cost tracking** | Per-session: cost per 1M tokens (input/output/cached), accumulated |
| **File versioning** | initial → v1 → v2 ... с SQLite хранением |
| **Sub-agents** | agent tool: Coder → Task (read-only: glob, grep, ls, view, sourcegraph) |
| **Custom commands** | Markdown files с $NAME placeholder arguments, user/project scope |
| **Non-interactive mode** | `opencode -p "..."` — single prompt, auto-approve, JSON/text output |
| **Язык реализации** | Go 1.24.0, Bubble Tea (TUI), Cobra (CLI), Viper (config), sqlc (DB), goose (migrations) |

---

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | Task Orchestrator | OpenCode | Статус |
|---|---|---|---|
| **Цепочки шагов (chains)** | ✅ YAML chains, статические и динамические | ❌ Нет (один agent loop per session, нет multi-step DSL) | ✅ У нас есть |
| **Retry с backoff** | ✅ RetryingAgentRunner | ❌ Нет (нет retry — прямой вызов provider, единственная fallback: provider не найден → error) | ✅ У нас есть |
| **Circuit Breaker** | ✅ CircuitBreakerAgentRunner | ❌ Нет | ✅ У нас есть |
| **Quality Gates** | ✅ Shell-команды как проверки | ❌ Нет (нет post-condition checks) | ✅ У нас есть |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based) | ⚠️ Cost tracking per session (без лимита), auto-compact при 95% context window | ✅ У нас есть |
| **Итерационные циклы** | ✅ fix_iterations с max_iterations | ✅ Agent loop (LLM → tool → LLM до end_turn) — но не настраиваемый | ✅ Паритет (разные подходы) |
| **Fallback routing** | ✅ Per-step fallback runner | ❌ Нет (один provider per agent, динамическое переключение только через UI) | ✅ У нас есть |
| **Agent loop** | ❌ Нет (runner'ы инкапсулируют) | ✅ Core execution model: streaming LLM → tool calls → responses | — Разный уровень |
| **TUI / Interactive mode** | ❌ Нет (batch pipeline) | ✅ Bubble Tea TUI: chat, editor, session dialog, model selection | — Разная парадигма |
| **Multi-provider support** | ⚠️ Через runner'ы (pi, codex) | ✅ 11 провайдеров, config-driven, model override per agent | 🟡 Интересно |
| **Sub-agent delegation** | ❌ Нет | ✅ agent tool: Coder → Task (read-only tools, isolated session, cost propagation) | 🟡 Интересно |
| **Permission system** | ❌ Нет | ✅ Per-tool per-session: banned commands, safe read-only, auto-approve для non-interactive | 🟡 Интересно |
| **Auto-compact** | ❌ Нет | ✅ LLM-суммаризация при 95% context window, summary → new session | 🟡 Интересно |
| **LSP integration** | ❌ Нет | ✅ Multi-language, diagnostics exposed to AI through tool | 🟡 Интересно |
| **MCP support** | ❌ Нет | ✅ stdio + SSE, auto-discovery from config, permission per tool | 🟡 Интересно |
| **File versioning** | ❌ Нет | ✅ initial → v1 → v2 ... с SQLite, version tracking per file per session | 🟡 Интересно |
| **Custom commands** | ❌ Нет | ✅ Markdown files с $NAME placeholders, user/project scope | 🟡 Интересно |
| **Context injection** | ⚠️ Простой payload | ✅ contextPaths (11 путей): OpenCode.md, CLAUDE.md, .cursorrules, copilot-instructions.md — auto-loaded | 🟡 Интересно |
| **Non-interactive mode** | ✅ CLI execution (batch) | ✅ `opencode -p "..." —auto-approve, JSON/text output` | ✅ Паритет |
| **PubSub broker** | ❌ Нет | ✅ Typed generic Broker[T] для event-driven communication | 🟡 Интересно |
| **Session persistence** | ❌ In-memory | ✅ SQLite: sessions + messages + files, parent-child sessions | 🟡 Интересно |
| **Cost tracking** | ✅ BudgetVo (cost-based) | ✅ Per-session: cost per 1M tokens (in/out/cached), accumulated | ✅ Паритет (у нас есть budget limit) |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❌ Плоская internal/ структура (app, config, db, llm, lsp, message, permission, session, tui) | ✅ У нас лучше |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Прямой вызов Agent.Service → Provider | ✅ У нас лучше |
| **JSONL audit trail** | ✅ JsonlAuditLogger | ⚠️ Debug logging в файл (опционально), не audit trail | ✅ У нас лучше |
| **Error classification** | ⚠️ Basic (retry on failure) | ❌ Нет (только ErrRequestCancelled, ErrSessionBusy, context.Canceled) | ✅ У нас лучше |

---

## 3. Что полезно взять и почему

### 3.1 🟡 Sub-agent Delegation через Tool (agent-tool.go)

**Что у них:** Coder-агент имеет инструмент `agent`, который создаёт Task-агента с read-only инструментами (glob, grep, ls, view, sourcegraph):

```go
// agent-tool.go: Run()
agent, _ := NewAgent(config.AgentTask, b.sessions, b.messages, TaskAgentTools(b.lspClients))
session, _ := b.sessions.CreateTaskSession(ctx, call.ID, sessionID, "New Agent Session")
done, _ := agent.Run(ctx, session.ID, params.Prompt)
result := <-done
// Cost propagation: parent cost += child cost
parentSession.Cost += updatedSession.Cost
```

Ключевые характеристики:
- Task-агент — **stateless**: один вызов, один ответ, нет multi-turn
- Task-агент — **read-only**: нет bash, edit, write, patch (только поиск и чтение)
- Cost propagation: стоимость sub-agent добавляется к родительской сессии
- Session hierarchy: parent_session_id связывает sub-agent сессию с основной
- Concurrent invocation: промпт coder-агента инструктирует «launch multiple agents concurrently»

**Почему нам интересно:** Модель для sub-agent pattern в chain execution — «chain внутри chain» с изолированным контекстом и cost propagation. Реализовано проще, чем в Codex (mailbox) или Kilo Code (permission inheritance), но покрывает основной use case: делегирование поиска/анализа подчинённому агенту.

### 3.2 🟡 Permission System (permission/permission.go)

**Что у них:** Трёхуровневая модель разрешений:

1. **Banned commands** (bash tool): hardcoded список (curl, wget, nc, telnet, lynx, chrome...)
2. **Safe read-only commands** (bash tool): whitelist (ls, echo, pwd, git status, go test...) — auto-approved без диалога
3. **Per-session permissions** (permission service): grant/deny per tool+action+session+path, persistent в рамках сессии

```go
// Non-interactive mode: auto-approve everything
a.Permissions.AutoApproveSession(sess.ID)

// Interactive mode: user decides per request
p := b.permissions.Request(permission.CreatePermissionRequest{
    SessionID: sessionID, ToolName: "bash", Action: "execute",
    Description: "Execute command: " + params.Command,
})
```

**Почему нам интересно:** Простая и эффективная модель для CI/CD: banned prefix + safe whitelist + per-session persistent. Не требует Docker. Дополнение к нашим quality gates: pre-execution фильтрация вместо post-execution проверки.

### 3.3 🟡 Auto-Compact (agent.go: Summarize)

**Что у них:** При достижении 95% context window — LLM-суммаризация всей истории:

```go
// summarizer.go: Summarize()
summarizePrompt := "Provide a detailed but concise summary of our conversation above.
Focus on information that would be helpful for continuing the conversation, including
what we did, what we're doing, which files we're working on, and what we're going to do next."
response, _ := a.summarizeProvider.SendMessages(ctx, msgsWithPrompt, make([]tools.BaseTool, 0))
// Summary → new session, old session marked with SummaryMessageID
oldSession.SummaryMessageID = msg.ID
```

Особенности:
- Отдельный `summarizer` agent (может использовать другую модель)
- Summary message становится первым сообщением в продолженной сессии (role → User)
- Trigger: автоматический (95% context) или ручной (built-in command «Compact Session»)

**Почему нам интересно:** Практически аналогичный паттерн уже исследован у Crush (строка #1), Claude Code, Codex и др. OpenCode подтверждает тренд. Для task-orchestrator — актуально для длинных dynamic loops и fix_iterations: при context overflow → summarization → продолжение с чистым контекстом.

### 3.4 🟡 Context Injection из Multiple Paths (prompt.go)

**Что у них:** Автозагрузка контекстных файлов из 11 стандартных путей:

```go
var defaultContextPaths = []string{
    ".github/copilot-instructions.md",
    ".cursorrules", ".cursor/rules/",
    "CLAUDE.md", "CLAUDE.local.md",
    "opencode.md", "opencode.local.md",
    "OpenCode.md", "OpenCode.local.md",
    "OPENCODE.md", "OPENCODE.local.md",
}
```

Обработка: concurrent file reading (goroutines + WaitGroup + channel), deduplication (case-insensitive), результат → injection в system prompt.

**Почему нам интересно:** Подтверждение тренда: CLAUDE.md / OpenCode.md / AGENTS.md — стандарт контекста для AI-агентов. Cross-tool compatibility: OpenCode читает CLAUDE.md и .cursorrules — не только свой формат. Для chain templates — модель context discovery: загрузка файлов проекта в payload автоматически.

### 3.5 🟡 PubSub Broker (pubsub/broker.go)

**Что у них:** Typed generic broker для event-driven communication:

```go
type Broker[T any] struct {
    subs      map[chan Event[T]]struct{}
    mu        sync.RWMutex
    done      chan struct{}
}

// Используется для: AgentEvent, Session, File, PermissionRequest
// Publish/Subscribe с context cancellation, buffered channels, shutdown
```

Применение: все сервисы (Agent, Session, History, Permission) — pubsub subscribers. TUI подписывается на события и обновляет UI.

**Почему нам интересно:** Архитектурный паттерн для loosely-coupled communication между слоями. В task-orchestrator можно применить для chain executor events (step started/completed/failed) — observer pattern без жесткой связности.

### 3.6 🟡 File Versioning (history/file.go)

**Что у них:** Version tracking для файлов, модифицированных в рамках сессии:

- initial → v1 → v2 → ... (auto-increment)
- SQLite storage: `UNIQUE(path, session_id, version)`
- Transaction с retry при UNIQUE constraint violation
- Экспозиция через TUI: file change tracking, diff view

**Почему нам интересно:** Для chain execution — rollback capability: если цепочка модифицировала файлы и упала на шаге N, можно откатиться к версии до начала цепочки. Простая реализация без git dependency.

### 3.7 🟡 Provider Factory с Config-Driven Model Selection (provider/provider.go + config/config.go)

**Что у них:** Единая фабрика Provider, создающая правильный клиент по ModelProvider:

```go
func NewProvider(providerName models.ModelProvider, opts ...ProviderClientOption) (Provider, error) {
    switch providerName {
    case models.ProviderAnthropic: return &baseProvider[AnthropicClient]{...}
    case models.ProviderOpenAI: return &baseProvider[OpenAIClient]{...}
    case models.ProviderGROQ: return &baseProvider[OpenAIClient]{...} // reuse OpenAI compatible
    case models.ProviderOpenRouter: return &baseProvider[OpenAIClient]{...} // reuse OpenAI compatible
    case models.ProviderLocal: return &baseProvider[OpenAIClient]{...} // reuse OpenAI compatible
    // ...
    }
}
```

Особенности:
- Generic `baseProvider[C ProviderClient]` — parameterised по типу клиента
- GROQ, OpenRouter, xAI, Local — reuse OpenAI client с разными base URLs
- Config validation: auto-fallback на следующий доступный provider при ошибке
- Per-agent model override: coder → Claude 4 Sonnet, task → Claude 4 Sonnet, title → cheap model

**Почему нам интересно:** Модель для нашей конфигурации runner'ов: config-driven selection, provider fallback, per-step model override. Generic factory с type parameter — элегантная альтернатива decorator chain для provider selection.

### 3.8 🟡 Custom Commands с Named Arguments (dialog/custom_commands.go)

**Что у них:** Markdown-файлы как макросы с $NAME placeholder'ами:

```markdown
# Fetch Context for Issue $ISSUE_NUMBER
RUN gh issue view $ISSUE_NUMBER --json title,body,comments
RUN git grep --author="$AUTHOR_NAME" -n .
```

- User scope: `$XDG_CONFIG_HOME/opencode/commands/` (prefix `user:`)
- Project scope: `<PROJECT>/.opencode/commands/` (prefix `project:`)
- Nested: `git/commit.md` → `user:git:commit`
- Arguments: prompted в TUI, replaced перед отправкой

**Почему нам интересно:** Аналог slash commands из Claude Code. Для chain templates — reusable prompt templates с параметрами. Расширение текущего payload-механизма: `{{ISSUE_NUMBER}}` → значение из chain config.

---

## 4. Что НЕ берём и почему

### 4.1 🟢 TUI / Bubble Tea Layer

TUI — это presentation layer для интерактивного использования. Task-orchestrator — batch pipeline. Разная парадигма: user-in-the-loop vs. autonomous execution. Bubble Tea framework (Go) не переносим в PHP.

### 4.2 🟢 LSP Integration

LSP diagnostics — мощный инструмент для кодинга, но responsibility runner'ов, не оркестратора. Runner (pi, codex) сам управляет LSP при необходимости. Оркестратор не должен знать про language servers.

### 4.3 🟢 Agent Loop (прямое LLM API взаимодействие)

OpenCode работает на уровне прямых LLM API (streaming через Anthropic/OpenAI SDK). Task-orchestrator делегирует runner'ам. Разный уровень абстракции: OpenCode = agent runtime, мы = orchestrator поверх agent runtimes.

### 4.4 🟢 SQLite Session Persistence

SQLite — хороший выбор для single-user CLI, но не для multi-tenant оркестратора. Наша JSONL audit log — легковеснее и лучше подходит для batch pipeline.

### 4.5 🟢 Image/Attachment Support

OpenCode поддерживает MIME attachments в сообщениях (binary content). Для chain-оркестрации это не актуально — шаги обмениваются структурированными payload, не медиа.

### 4.6 🟢 Sourcegraph Tool

Sourcegraph integration для code search across public repos — специфичная фича, не связанная с оркестрацией. Может быть полезна на уровне runner'ов.

---

## 5. Сводка рекомендаций

| Фича | Приоритет | Обоснование |
|---|---|---|
| Chain orchestration (YAML chains) | ✅ Уже есть | Core-функциональность task-orchestrator |
| Retry + Circuit Breaker + Quality Gates | ✅ Уже есть | Устойчивость при сбоях — ключевые отличия |
| Budget control | ✅ Уже есть | Предотвращение runaway spending |
| Sub-agent delegation (agent tool) | 🟡 P3 | Модель для sub-agent pattern: Coder → Task с read-only tools, cost propagation, session hierarchy. Простая реализация, покрывает основной use case. Подтверждено Codex, Claude Code, Kilo Code, Hermes Agent |
| Permission system (banned + safe + per-session) | 🟡 P2 | Простой и эффективный exec policy для CI/CD: banned prefix + safe whitelist + per-session persistent. Не требует Docker |
| Auto-compact (95% context → summarization) | 🟡 P3 | Подтверждённый тренд (9/22 проекта). Для длинных dynamic loops и fix_iterations |
| Context injection (multi-path discovery) | 🟡 P2 | Cross-tool compatibility: CLAUDE.md, OpenCode.md, .cursorrules. Для chain templates — auto-loading project context |
| PubSub broker (typed generic events) | 🟡 P3 | Loosely-coupled event system: chain events, step lifecycle. Архитектурный паттерн |
| File versioning (initial → vN) | 🟡 P3 | Rollback capability для failed chains. Простая альтернатива git-based rollback |
| Provider factory (config-driven, generic) | 🟡 P2 | Модель для runner configuration: config-driven selection, provider fallback, per-step model override |
| Custom commands ($NAME placeholders) | 🟡 P2 | Reusable prompt templates с параметрами. Расширение payload-механизма для chain templates |
| TUI / Bubble Tea | 🟢 — | Разная парадигма (interactive vs. batch) |
| LSP integration | 🟢 — | Responsibility runner'ов |
| Agent loop (LLM API) | 🟢 — | Разный уровень абстракции |
| SQLite persistence | 🟢 — | Single-user, не multi-tenant |
| Image/attachment support | 🟢 — | Не актуально для chain payloads |
| Sourcegraph tool | 🟢 — | Не связано с оркестрацией |

---

## 6. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории OpenCode (архивирован):

- [`README.md`](https://github.com/opencode-ai/opencode/blob/main/README.md) — полная документация: features, configuration, tools, MCP, LSP, custom commands, keyboard shortcuts (~600 строк)
- [`internal/llm/agent/agent.go`](https://github.com/opencode-ai/opencode/blob/main/internal/llm/agent/agent.go) — Core agent: Service interface, Run(), processGeneration(), streamAndHandleEvents(), Summarize(), TrackUsage() (~490 LOC)
- [`internal/llm/agent/agent-tool.go`](https://github.com/opencode-ai/opencode/blob/main/internal/llm/agent/agent-tool.go) — Sub-agent tool: agent → Task delegation, cost propagation, session hierarchy (~120 LOC)
- [`internal/llm/agent/tools.go`](https://github.com/opencode-ai/opencode/blob/main/internal/llm/agent/tools.go) — Tool sets: CoderAgentTools (12+MCP), TaskAgentTools (5 read-only) (~40 LOC)
- [`internal/llm/agent/mcp-tools.go`](https://github.com/opencode-ai/opencode/blob/main/internal/llm/agent/mcp-tools.go) — MCP integration: stdio/SSE client, auto-discovery, permission per tool (~160 LOC)
- [`internal/llm/provider/provider.go`](https://github.com/opencode-ai/opencode/blob/main/internal/llm/provider/provider.go) — Provider interface + factory: 11 providers, generic baseProvider[C] (~180 LOC)
- [`internal/llm/tools/tools.go`](https://github.com/opencode-ai/opencode/blob/main/internal/llm/tools/tools.go) — BaseTool interface: Info(), Run(), ToolResponse, ToolCall (~70 LOC)
- [`internal/llm/tools/bash.go`](https://github.com/opencode-ai/opencode/blob/main/internal/llm/tools/bash.go) — Bash tool: banned commands, safe read-only, permission, persistent shell, output truncation (~250 LOC)
- [`internal/llm/prompt/prompt.go`](https://github.com/opencode-ai/opencode/blob/main/internal/llm/prompt/prompt.go) — Context injection: multi-path discovery, concurrent file reading, deduplication (~100 LOC)
- [`internal/llm/prompt/coder.go`](https://github.com/opencode-ai/opencode/blob/main/internal/llm/prompt/coder.go) — Coder system prompts: Anthropic vs OpenAI variants, environment info, LSP info (~200 LOC)
- [`internal/permission/permission.go`](https://github.com/opencode-ai/opencode/blob/main/internal/permission/permission.go) — Permission service: per-session persistent, auto-approve, PubSub integration (~100 LOC)
- [`internal/session/session.go`](https://github.com/opencode-ai/opencode/blob/main/internal/session/session.go) — Session service: CRUD, parent-child, cost/tokens tracking (~130 LOC)
- [`internal/config/config.go`](https://github.com/opencode-ai/opencode/blob/main/internal/config/config.go) — Config management: JSON + env, multi-provider defaults, validation, runtime update (~480 LOC)
- [`internal/pubsub/broker.go`](https://github.com/opencode-ai/opencode/blob/main/internal/pubsub/broker.go) — Typed generic PubSub broker: Subscribe/Publish/Shutdown (~120 LOC)
- [`internal/app/app.go`](https://github.com/opencode-ai/opencode/blob/main/internal/app/app.go) — Application init + RunNonInteractive: single prompt mode, auto-approve (~180 LOC)
- [`internal/db/migrations/20250424200609_initial.sql`](https://github.com/opencode-ai/opencode/blob/main/internal/db/migrations/20250424200609_initial.sql) — Schema: sessions, messages, files tables, triggers
- [`opencode-schema.json`](https://github.com/opencode-ai/opencode/blob/main/opencode-schema.json) — JSON Schema для конфигурации: agents, providers, MCP, LSP, TUI themes
- [`go.mod`](https://github.com/opencode-ai/opencode/blob/main/go.mod) — Dependencies: Bubble Tea, Cobra, Viper, sqlc, goose, mcp-go, anthropic-sdk, openai-go

📚 **Источники:**
1. [github.com/opencode-ai/opencode](https://github.com/opencode-ai/opencode) — репозиторий проекта (архивирован)
2. [README.md](https://github.com/opencode-ai/opencode/blob/main/README.md) — полная документация API, tools, MCP, LSP, configuration
3. Исходный код: internal/llm/agent/, internal/llm/provider/, internal/llm/tools/, internal/permission/, internal/config/ — детальный анализ архитектуры
4. [github.com/charmbracelet/crush](https://github.com/charmbracelet/crush) — продолжение проекта (Crush, строка #1 в сводной таблице)
5. [crush-comparison.md](./crush-comparison.md) — отчёт по Crush (преемник OpenCode)
