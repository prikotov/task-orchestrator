# Исследование: OpenCode (anomalyco) — open source AI-coding agent

> **Проект:** [github.com/anomalyco/opencode](https://github.com/anomalyco/opencode)
> **Дата анализа:** 2026-05-08
> **Язык:** TypeScript (Bun, Effect-TS)
> **Лицензия:** MIT
> **Версия:** v1.14.41
> **Аналитик:** Аналитик (Шерлок)

---

## 1. Обзор проекта

OpenCode — **наиболее популярный open source AI-coding agent** (156K+ звёзд, 18K+ форков) от компании Anomaly. Включает TUI-клиент, Desktop App, VS Code / JetBrains / Zed extensions. Реализует модель `LLM → tool call → observation → LLM → ...` с встроенными агентами (build, plan, general, explore, compaction), поддержкой subagents через `task` tool, provider-agnostic подходом (23+ LLM провайдера через AI SDK), MCP, Skills, плагинами, git worktrees и клиент-серверной архитектурой.

> ⚠️ **Важно:** OpenCode (`anomalyco/opencode`) — НЕ `opencode-ai/opencode` (Go, архивирован, продолжен как Crush). Это TypeScript/Bun проект, активный, с релизами почти каждый день.

Архитектура OpenCode принципиально отличается от task-orchestrator: OpenCode — **интерактивный AI-ассистент** с direct LLM API calls (через Vercel AI SDK), agent modes и permission system. Task-orchestrator — **batch chain orchestrator**, который управляет выполнением внешних runner'ов через YAML-цепочки. Разные уровни абстракции: OpenCode работает на уровне agent loop + tool calls, task-orchestrator — на уровне chain steps + retry + circuit breaker + quality gates.

### Архитектура

```
┌─────────────────────────────────────────────────────────────┐
│  Presentation Layer                                          │
│  • TUI (terminal user interface, Solid.js через @opentui)    │
│  • Desktop App (Tauri/Electron)                              │
│  • VS Code / JetBrains / Zed Extensions                      │
│  • HTTP API (Hono) + WebSocket sync                          │
│  • ACP (Agent Client Protocol) — stdio JSON-RPC              │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  Session Layer (session/)                                    │
│  • Session CRUD (create/fork/list/remove)                    │
│  • MessageV2 (user/assistant/tool/compaction parts)          │
│  • SessionPrompt.prompt() — основной entry point             │
│  • SessionPrompt.loop() — agentic loop (LLM → tools → LLM)  │
│  • RunState — mutual exclusion per session (Runner)          │
│  • Status: idle / busy / retry                               │
│  • Cost tracking (getUsage: tokens + cost per call)          │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  Agent Loop (session/processor.ts + session/llm.ts)          │
│  • Stream processing (start → text/tool/reasoning → finish)  │
│  • Tool lifecycle: pending → running → completed/error       │
│  • Doom loop detection (3 repeated identical tool calls)     │
│  • Retry policy (exponential backoff + Retry-After headers)  │
│  • Context overflow → auto-compaction                        │
│  • Permission checks per tool call                           │
│  • AbortSignal support                                       │
└──────────────────────────┬──────────────────────────────────┘
                           │
               ┌───────────┴───────────┐
               ▼                       ▼
┌────────────────────┐   ┌────────────────────────────────────┐
│  Agent Service     │   │  Tool Registry                     │
│  (agent/agent.ts)  │   │  (tool/registry.ts)                │
│  • build (primary) │   │  • shell, read, write, edit,       │
│  • plan (primary)  │   │    apply_patch, glob, grep          │
│  • general (sub)   │   │  • task (subagent delegation)       │
│  • explore (sub)   │   │  • todo, question, skill, plan      │
│  • compaction      │   │  • webfetch, websearch, lsp         │
│  • title, summary  │   │  • Custom: .opencode/tool/*.ts      │
│  • Custom agents   │   │  • Plugin tools                     │
│    (.opencode/      │   │  • MCP tools                       │
│     agent/*.md)     │   │                                    │
│  • AI-generated    │   │                                    │
│    agents          │   │                                    │
└────────┬───────────┘   └────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│  Provider Layer (provider/)                                  │
│  • 23+ LLM провайдеров через Vercel AI SDK                   │
│  • Anthropic, OpenAI, Google, Azure, Bedrock, Groq, ...     │
│  • Model-specific prompts (anthropic.txt, gpt.txt, ...)      │
│  • Cost calculation per provider (input/output/cache/reason)  │
│  • Variant system (cheapest/flash/default/progressive)       │
│  • GitHub Copilot provider (OAuth + OpenAI-compatible)       │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  Cross-cutting Services                                      │
│  • Permission (allow/ask/deny per tool + glob patterns)      │
│  • Compaction (auto-summarization при context overflow)      │
│  • Skills (SKILL.md discovery из .opencode/skills/)          │
│  • Plugins (JS/TS модули, hooks, custom tools)               │
│  • MCP (Model Context Protocol)                              │
│  • LSP (Language Server Protocol, встроенный)                 │
│  • Snapshot (file diff tracking per step)                    │
│  • Worktree (git worktree create/remove/reset)               │
│  • Sync (event-sourced state через SQLite + sync events)     │
│  • Bus (publish/subscribe event bus)                         │
│  • Storage (SQLite через Drizzle ORM)                        │
│  • Effect-TS (Context.Tag + Layer — dependency injection)    │
└─────────────────────────────────────────────────────────────┘
```

### Ключевые характеристики

| Характеристика | Значение |
|---|---|
| **Тип** | CLI-agent + Desktop App + SDK (AI coding assistant) |
| **Модель выполнения** | Agent loop (LLM → tool call → observation → LLM → ...) |
| **Агенты** | 7 built-in (build/plan/general/explore/compaction/title/summary) + custom (.opencode/agent/*.md) + AI-generated |
| **State management** | Persistent (SQLite через Drizzle ORM + event-sourced sync) |
| **LLM провайдеры** | 23+ (через Vercel AI SDK: Anthropic, OpenAI, Google, Azure, Bedrock, Groq, Mistral, xAI, Cerebras, Cohere, DeepInfra, Together, Perplexity, Alibaba, Venice, GitLab, OpenRouter, ...) |
| **Расширяемость** | Custom agents (MD), Skills (SKILL.md), Plugins (JS/TS), MCP, Custom tools (.opencode/tool/*.ts), ACP (Agent Client Protocol) |
| **Error handling** | Error classification (ContextOverflow/API 5xx/FreeUsageLimitError/GoUsageLimitError/rate limit) + exponential backoff + Retry-After header parsing + doom loop detection |
| **Permission system** | allow/ask/deny per tool + glob patterns + inherited для subagents + session-level overrides |
| **Context management** | Auto-compaction (7-секционный structured summary template) + pruning (tool output truncation) + preserve recent turns |
| **Subagents** | task tool (isolated session, permission inheritance, no recursive delegation, resume по task_id) |
| **Git Worktrees** | create/remove/reset + auto-branch + start command execution + snapshot tracking |
| **Язык реализации** | TypeScript, Bun runtime, Effect-TS (functional effect system), Vercel AI SDK, Solid.js (TUI), Hono (HTTP) |

---

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | Task Orchestrator | OpenCode | Статус |
|---|---|---|---|
| **Цепочки шагов (chains)** | ✅ YAML chains, статические и динамические | ❌ Нет (один agent loop per session, нет multi-step DSL) | ✅ У нас есть |
| **Retry с backoff** | ✅ RetryingAgentRunner | ✅ SessionRetry.policy — exponential backoff + Retry-After header parsing + error classification | ✅ У нас есть (OpenCode детальнее) |
| **Circuit Breaker** | ✅ CircuitBreakerAgentRunner | ❌ Нет (retry — максимум) | ✅ У нас есть |
| **Quality Gates** | ✅ Shell-команды как проверки | ❌ Нет (agent сам решает) | ✅ У нас есть |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based) | ⚠️ Cost tracking per session/message, но без budget limit | ✅ У нас есть |
| **Итерационные циклы** | ✅ fix_iterations с max_iterations | ✅ Agent steps (maxSteps) — forced text-only response при достижении лимита | ✅ Паритет |
| **Fallback routing** | ✅ Per-step fallback runner | ❌ Нет (один provider/model per session) | ✅ У нас есть |
| **Doom loop detection** | ❌ Нет | ✅ 3 повторяющихся идентичных tool call → permission ask | 🟡 Интересно |
| **Permission system** | ❌ Нет | ✅ allow/ask/deny per tool + glob patterns + inherited + session-level overrides | 🟡 Интересно |
| **Agent modes** | ❌ Нет (только runner types) | ✅ 7 built-in агентов + custom + AI-generated; primary/subagent/hidden режимы | 🟡 Интересно |
| **Subagents (task tool)** | ❌ Нет | ✅ Isolated session + permission inheritance + resume по task_id + no recursive delegation | 🟡 Интересно |
| **Context compaction** | ❌ Нет | ✅ Auto-compaction с 7-секционным structured template + pruning + preserve recent turns | 🟡 Интересно |
| **Git worktrees** | ❌ Нет | ✅ Create/remove/reset + auto-branch + start scripts + snapshot tracking | 🟡 Интересно |
| **Cost tracking** | ❌ Нет (budget control только) | ✅ Per-message cost + tokens (input/output/reasoning/cache read/cache write) + provider-specific pricing | 🟡 Интересно |
| **Skills (SKILL.md)** | ❌ Нет | ✅ Discovery из .opencode/skills/, .claude/skills/, .agents/skills/ + remote URLs + permission filtering | 🟡 Интересно |
| **Custom agents** | ❌ Нет | ✅ Markdown-файлы (.opencode/agent/*.md) + AI-генерация через generateObject | 🟡 Интересно |
| **Plugin system** | ❌ Нет | ✅ JS/TS модули + hooks + custom tools + system prompt transformation | 🟡 Интересно |
| **MCP support** | ❌ Нет (уровень runner'ов) | ✅ Full MCP client (tools + resources + OAuth) | — (разный уровень) |
| **LSP support** | ❌ Нет | ✅ Built-in LSP client (diagnostics, definitions, references) | — (разный уровень) |
| **Snapshot tracking** | ❌ Нет | ✅ File diff tracking per LLM step + revert capability | 🟡 Интересно |
| **Error classification** | ⚠️ Basic (retry on failure) | ✅ ContextOverflow/API 5xx/FreeUsageLimitError/GoUsageLimitError/rate limit — retry/no-retry classification | 🟡 Интересно |
| **Client-server architecture** | ❌ Нет (CLI только) | ✅ HTTP API (Hono) + WebSocket sync + mDNS discovery + remote control | — (разный уровень) |
| **ACP (Agent Client Protocol)** | ❌ Нет | ✅ JSON-RPC over stdio (Zed, внешние IDE) | — (разный уровень) |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❌ Effect-TS service layer (Context.Tag + Layer) | ✅ У нас лучше |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Прямой вызов Effect-пайплайна | ✅ У нас лучше |
| **JSONL audit trail** | ✅ JsonlAuditLogger | ⚠️ Sync events (event-sourced), не audit trail в нашем смысле | ✅ У нас лучше |

---

## 3. Что полезно взять и почему

### 3.1 🟡 Doom Loop Detection (session/processor.ts)

**Что у них:** Обнаружение зацикливания через подсчёт повторяющихся tool calls:

```typescript
// В processor.ts, обработка события "tool-call"
const recentParts = parts.slice(-DOOM_LOOP_THRESHOLD)  // DOOM_LOOP_THRESHOLD = 3

if (
  recentParts.length !== DOOM_LOOP_THRESHOLD ||
  !recentParts.every(
    (part) =>
      part.type === "tool" &&
      part.tool === value.toolName &&
      part.state.status !== "pending" &&
      JSON.stringify(part.state.input) === JSON.stringify(value.input),
  )
) {
  return  // не doom loop — продолжаем
}

// 3 идентичных tool call подряд → ask permission
const agent = yield* agents.get(ctx.assistantMessage.agent)
yield* permission.ask({
  permission: "doom_loop",
  patterns: [value.toolName],
  sessionID: ctx.sessionID,
  metadata: { tool: value.toolName, input: value.input },
  always: [value.toolName],
  ruleset: agent.permission,
})
```

**Почему нам интересно:** Защита от зацикливания в fix_iterations. Подтверждено 3+ проектами (Crush — window-based, OpenHands SDK — 4+1, Paperclip AI — evidence-based). OpenCode предлагает простейшую реализацию: 3 идентичных вызова = doom loop. Для task-orchestrator: detect repeating runner calls с идентичными параметрами → early termination или warning.

### 3.2 🟡 Structured Context Compaction (session/compaction.ts)

**Что у них:** 7-секционный structured summary template для auto-compaction:

```markdown
## Goal
- [single-sentence task summary]

## Constraints & Preferences
- [user constraints, preferences, specs, or "(none)"]

## Progress
### Done
- [completed work or "(none)"]
### In Progress
- [current work or "(none)"]
### Blocked
- [blockers or "(none)"]

## Key Decisions
- [decision and why, or "(none)"]

## Next Steps
- [ordered next actions or "(none)"]

## Critical Context
- [important technical facts, errors, open questions, or "(none)"]

## Relevant Files
- [file or directory path: why it matters, or "(none)"]
```

Дополнительно:
- **Pruning** — обратный проход по tool outputs, обрезка старых (PRUNE_PROTECT = 40K tokens)
- **Preserve recent turns** — бюджет для сохранения последних N turns (2 по умолчанию, 2K–8K tokens)
- **Replay** — при overflow: компактификация + повторный запуск последнего промпта (без медиа)
- **Tail splitting** — если turn не помещается в бюджет, разрезание на head/tail

**Почему нам интересно:** Наиболее структурированный подход к compaction из исследованных. Kilo Code имеет похожий 7-секционный шаблон, но OpenCode добавляет pruning, preserve recent и replay — три дополнительных механизма. Для длинных цепочек и dynamic loops контекст-менеджмент станет необходим.

### 3.3 🟡 Permission System с Glob Patterns (permission/)

**Что у них:** Трёхуровневая система allow/ask/deny для каждого tool:

```typescript
// Конфигурация (opencode.jsonc)
{
  "permission": {
    "*": "allow",                    // все tools → allow
    "doom_loop": "ask",              // doom loop detection → ask
    "edit": { "*.env": "ask" },      // edit .env → ask
    "external_directory": {          // доступ к внешним директориям
      "*": "ask",
      "/tmp/*": "allow"
    },
    "read": { "*.env": "ask" },      // read .env → ask
    "question": "deny"               // question tool → deny
  }
}
```

Ключевые особенности:
- **Glob patterns** — `*.env`, `/tmp/*`, `~/.ssh/*`
- **Per-agent permissions** — build agent имеет одни правила, plan — другие
- **Inheritance** — subagents наследуют deny-правила от parent + external_directory
- **Session-level overrides** — runtime-изменение через `session.setPermission()`
- **Approval persistence** — "always" approvals сохраняются в SQLite
- **Doom loop** как отдельное permission (`doom_loop`)

**Почему нам интересно:** Для CI/CD sandboxing: не требует Docker — работает на уровне chain executor. Аналог exec policy из Codex, но с glob patterns. Для task-orchestrator: allow/deny per runner + glob patterns для shell-команд.

### 3.4 🟡 Subagent Task Tool (tool/task.ts)

**Что у них:** Делегирование подзадачи в изолированную сессию:

```typescript
// Параметры task tool
{
  description: "short 3-5 words",
  prompt: "task for the agent",
  subagent_type: "general",       // имя агента
  task_id: "optional_for_resume", // для продолжения предыдущей сессии
  command: "triggering command"    // команда, вызвавшая задачу
}
```

Ключевые особенности:
- **Isolated session** — создаёт child session (parentID linkage)
- **Permission inheritance** — deny rules + external_directory от parent
- **No recursive delegation** — task и todowrite запрещены для subagents (unless explicitly allowed)
- **Resume** — task_id для продолжения предыдущей subagent сессии
- **AbortSignal** — parent abort → child cancel
- **Model inheritance** — subagent использует модель parent, если не указана явно

**Почему нам интересно:** «Chain внутри chain» с изолированным контекстом. Kilo Code имеет аналогичный task tool, но OpenCode добавляет resume по task_id — уникальная возможность. Для dynamic chains: изолированные шаги с собственным контекстом + возможность возобновления.

### 3.5 🟡 Error Classification + Retry Policy (session/retry.ts)

**Что у них:** Классификация ошибок для умного retry:

```typescript
// Не retryable:
if (MessageV2.ContextOverflowError.isInstance(error)) return undefined

// Retryable:
if (status >= 500) return { message: ... }                    // 5xx → retry
if (responseBody?.includes("FreeUsageLimitError")) return ...  // upsell
if (responseBody?.includes("GoUsageLimitError")) return ...    // upsell
if (msg.includes("rate limit")) return { message: msg }        // rate limit → retry
if (json.error?.type === "too_many_requests") return ...       // 429 → retry

// Retry-After header parsing:
const retryAfterMs = headers["retry-after-ms"]    // миллисекунды
const retryAfter = headers["retry-after"]          // секунды или HTTP date
// Fallback: exponential backoff 2s → 4s → 8s → ... → 30s max
```

**Почему нам интересно:** Конкретная модель для RetryingAgentRunner: context overflow → не retry (→ compact), 5xx → retry с backoff, rate limit → retry с Retry-After. Дополнение к circuit breaker: CB защищает от cascade, error classification — от бессмысленных retry.

### 3.6 🟡 Custom Agents через Markdown (config/agent.ts)

**Что у них:** Определение агентов через markdown-файлы:

```markdown
<!-- .opencode/agent/researcher.md -->
---
name: researcher
description: "Research agent for deep investigation"
model: anthropic:claude-sonnet-4-20250514
mode: subagent
temperature: 0.3
steps: 10
permission:
  edit: deny
  bash: ask
---

You are a research agent. Focus on reading code, searching for patterns,
and providing detailed analysis. Do not modify any files.
```

Также AI-генерация агентов через `Agent.generate()`:

```typescript
const result = yield* agents.generate({
  description: "An agent that reviews PRs",
  model: { providerID, modelID },
})
// → { identifier, whenToUse, systemPrompt }
```

**Почему нам интересно:** Модель для переиспользуемых chain templates: markdown-определение шага/агента с frontmatter-конфигурацией. AI-генерация — для будущего DSL: user описывает что нужно → AI генерирует chain definition.

### 3.7 🟡 Git Worktree Management (worktree/)

**Что у них:** Полный lifecycle git worktree:

```typescript
// Create: generate slug → create branch → add worktree → reset hard → bootstrap
const info = yield* makeWorktreeInfo(name)
// → { name: "abc123", branch: "opencode/abc123", directory: "/path/to/worktree" }

// Remove: stop fsmonitor → git worktree remove → clean directory → delete branch
yield* remove({ directory })

// Reset: fetch default branch → hard reset → clean → submodule update → run start scripts
yield* reset({ directory })
```

Особенности:
- **Auto-branch** — `opencode/<slug>` naming
- **Start command** — выполнение скриптов после bootstrap
- **Snapshot** — file diff tracking per step
- **Reset to default branch** — full clean + submodule update
- **Scoped to git projects only** — graceful error для non-git

**Почему нам интересно:** Для параллельных chain runs: изоляция через git worktrees. Sandcastle и Archon предлагают похожие модели. OpenCode добавляет reset и submodule update — наиболее полная реализация cleanup.

### 3.8 🟡 Cost Tracking (session/session.ts)

**Что у них:** Детальный cost tracking per message:

```typescript
export function getUsage(input: { model, usage, metadata }) {
  const tokens = {
    total,
    input: adjustedInputTokens,           // input - cache read - cache write
    output: outputTokens - reasoningTokens,
    reasoning: reasoningTokens,
    cache: {
      write: cacheWriteInputTokens,
      read: cacheReadInputTokens,
    },
  }

  const cost = inputTokens * price.input/1M
    + outputTokens * price.output/1M
    + cacheReadTokens * price.cache.read/1M
    + cacheWriteTokens * price.cache.write/1M
    + reasoningTokens * price.output/1M

  return { cost, tokens }
}
```

Поддержка provider-specific pricing:
- Anthropic: `metadata.anthropic.cacheCreationInputTokens`
- Vertex: `metadata.vertex.cacheCreationInputTokens`
- Bedrock: `metadata.bedrock.usage.cacheWriteInputTokens`
- Experimental over 200K pricing

**Почему нам интересно:** Детализация cost tracking для budget control: cache tokens отдельно, reasoning отдельно, provider-specific pricing. Для BudgetVo: более точный расчёт стоимости.

### 3.9 🟡 Snapshot Tracking + Revert (snapshot/, session/revert.ts)

**Что у них:** File diff tracking per LLM step с возможностью отката:

- **Snapshot** — pre-step file system state, post-step diff (snapshot → patch → file changes)
- **Step-level patches** — каждый LLM step получает `step-start` (snapshot) и `step-finish` (diff)
- **Revert** — откат к конкретному message/part через сохранённый snapshot
- **Summary** — additions/deletions/files/diffs per session

**Почему нам интересно:** Audit trail на уровне файловых изменений: каждый шаг цепочки оставляет diff. Для task-orchestrator: вместо JSONL-only audit — snapshot + diff per step. Revert — механизм отката failed chains.

---

## 4. Что НЕ берём и почему

### 4.1 🟢 LLM API / AI SDK Integration

OpenCode работает напрямую с LLM API через Vercel AI SDK (23+ провайдеров). Task-orchestrator делегирует runner'ам. Разный уровень: OpenCode — LLM client, мы — chain orchestrator поверх LLM clients.

### 4.2 🟢 Effect-TS как runtime dependency

Effect-TS — мощный функциональный фреймворк (Context.Tag + Layer = DI, Schema = validation, Stream = reactive, Schedule = retry). Но чуждый PHP-экосистеме. Наши слои Domain/Application/Infrastructure + Symfony DI решают те же задачи проще для нашей аудитории.

### 4.3 🟢 TUI / Desktop App / IDE Extensions

Presentation layer OpenCode (Solid.js TUI, Tauri desktop, VS Code/JetBrains/Zed extensions) — не переносим в PHP. Клиент-серверная архитектура (Hono HTTP + WebSocket + mDNS) — интересна концептуально, но не приоритет.

### 4.4 🟢 Provider-Specific Prompts (23+ variants)

OpenCode содержит отдельные промпты для каждого провайдера: `anthropic.txt`, `gpt.txt`, `gemini.txt`, `copilot-gpt-5.txt`, `codex.txt`, `kimi.txt`, `trinity.txt`, `beast.txt`. Это оптимизация под конкретные модели — не наша ответственность (runner level).

### 4.5 🟢 ACP (Agent Client Protocol)

JSON-RPC over stdio для интеграции с Zed и другими IDE — стандарт взаимодействия с внешними клиентами. Не применим к chain orchestrator.

### 4.6 🟢 LSP Integration

Встроенный Language Server Protocol client (diagnostics, definitions, references) — функциональность IDE, не оркестратора.

### 4.7 🟢 AI-Generated Agents

`Agent.generate()` — AI-генерация agent config через `generateObject()` — интересная концепция, но premature для текущего этапа task-orchestrator.

---

## 5. Сводка рекомендаций

| Фича | Приоритет | Обоснование |
|---|---|---|
| Chain orchestration (YAML chains) | ✅ Уже есть | Core-функциональность task-orchestrator |
| Retry + Circuit Breaker + Quality Gates + Budget | ✅ Уже есть | Ключевые отличия task-orchestrator |
| Doom loop detection | 🟡 P2 | Защита от зацикливания в fix_iterations. Подтверждено OpenCode (3 идентичных вызова) + Crush + OpenHands + Paperclip AI |
| Error classification для retry | 🟡 P2 | ContextOverflow → compact, 5xx → retry, rate limit → retry с Retry-After. Конкретная модель для RetryingAgentRunner |
| Permission system (allow/ask/deny + glob) | 🟡 P2 | Для CI/CD sandboxing без Docker. Per-runner restrictions + shell command filtering |
| Context compaction (structured template) | 🟡 P3 | 7-секционный summary + pruning + preserve recent. Для длинных цепочек и dynamic loops |
| Subagent task tool (isolated session) | 🟡 P3 | «Chain внутри chain» с изолированным контекстом + resume. Для future dynamic chains |
| Custom agents через Markdown | 🟡 P3 | Модель для переиспользуемых chain templates: MD frontmatter = конфиг |
| Git worktree management | 🟡 P3 | Для параллельных chain runs: create/remove/reset + submodule update |
| Cost tracking (per-step tokens + cache) | 🟡 P2 | Детализация для BudgetVo: cache tokens, reasoning, provider-specific pricing |
| Snapshot tracking + revert | 🟡 P3 | File diff per step + откат failed chains. Audit trail на уровне файловых изменений |
| LLM API / AI SDK | 🟢 — | Разный уровень абстракции (LLM client vs. chain orchestrator) |
| Effect-TS | 🟢 — | Чуждый PHP-экосистеме |
| TUI / Desktop / IDE | 🟢 — | Presentation layer, не переносим |
| ACP / LSP | 🟢 — | Стандарты взаимодействия, не наша ответственность |

---

## 6. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории OpenCode:

- [`README.md`](https://github.com/anomalyco/opencode/blob/dev/README.md) — документация: installation, agents (build/plan/general), FAQ (differences from Claude Code)
- [`packages/opencode/src/agent/agent.ts`](https://github.com/anomalyco/opencode/blob/dev/packages/opencode/src/agent/agent.ts) — Agent Service: 7 built-in агентов, permission defaults, custom agents, AI-генерация (~413 LOC)
- [`packages/opencode/src/session/session.ts`](https://github.com/anomalyco/opencode/blob/dev/packages/opencode/src/session/session.ts) — Session CRUD: create/fork/remove, cost tracking (getUsage), event-sourced sync (~500 LOC)
- [`packages/opencode/src/session/processor.ts`](https://github.com/anomalyco/opencode/blob/dev/packages/opencode/src/session/processor.ts) — Core agent loop: stream processing, doom loop detection, retry policy, abort handling (~770 LOC)
- [`packages/opencode/src/session/retry.ts`](https://github.com/anomalyco/opencode/blob/dev/packages/opencode/src/session/retry.ts) — Error classification: ContextOverflow/API 5xx/rate limit/FreeUsageLimitError, Retry-After parsing, backoff policy (~130 LOC)
- [`packages/opencode/src/session/compaction.ts`](https://github.com/anomalyco/opencode/blob/dev/packages/opencode/src/session/compaction.ts) — Auto-compaction: 7-секционный summary template, pruning, preserve recent turns, replay (~350 LOC)
- [`packages/opencode/src/session/overflow.ts`](https://github.com/anomalyco/opencode/blob/dev/packages/opencode/src/session/overflow.ts) — Context overflow detection: usable token calculation, reserved buffer (~20 LOC)
- [`packages/opencode/src/permission/index.ts`](https://github.com/anomalyco/opencode/blob/dev/packages/opencode/src/permission/index.ts) — Permission system: allow/ask/deny, glob patterns, approval persistence, session-level overrides (~250 LOC)
- [`packages/opencode/src/tool/task.ts`](https://github.com/anomalyco/opencode/blob/dev/packages/opencode/src/tool/task.ts) — Subagent task tool: isolated session, permission inheritance, resume, abort propagation (~150 LOC)
- [`packages/opencode/src/tool/registry.ts`](https://github.com/anomalyco/opencode/blob/dev/packages/opencode/src/tool/registry.ts) — Tool Registry: 15+ built-in tools + custom tools + plugin tools + MCP tools (~200 LOC)
- [`packages/opencode/src/worktree/index.ts`](https://github.com/anomalyco/opencode/blob/dev/packages/opencode/src/worktree/index.ts) — Git worktree management: create/remove/reset, auto-branch, start scripts, submodule update (~350 LOC)
- [`packages/opencode/src/skill/index.ts`](https://github.com/anomalyco/opencode/blob/dev/packages/opencode/src/skill/index.ts) — Skills system: SKILL.md discovery, remote URLs, permission filtering (~200 LOC)
- [`packages/opencode/src/config/agent.ts`](https://github.com/anomalyco/opencode/blob/dev/packages/opencode/src/config/agent.ts) — Agent configuration schema: model/variant/temperature/steps/permission/custom options (~130 LOC)
- [`packages/opencode/src/session/prompt.ts`](https://github.com/anomalyco/opencode/blob/dev/packages/opencode/src/session/prompt.ts) — Main prompt/loop entry point: session creation, agentic loop, structured output (~1900 LOC)
- [`packages/opencode/src/acp/README.md`](https://github.com/anomalyco/opencode/blob/dev/packages/opencode/src/acp/README.md) — ACP (Agent Client Protocol) implementation docs
- [`AGENTS.md`](https://github.com/anomalyco/opencode/blob/dev/AGENTS.md) — Стиль кода, правила тестирования, type checking

📚 **Источники:**
1. [github.com/anomalyco/opencode](https://github.com/anomalyco/opencode) — репозиторий проекта (156K+ звёзд)
2. [opencode.ai](https://opencode.ai) — официальный сайт, документация
3. [opencode.ai/docs](https://opencode.ai/docs) — документация: agents, configuration, skills
4. [Vercel AI SDK](https://sdk.vercel.ai) — основной SDK для LLM interaction (23+ провайдеров)
5. [Agent Client Protocol](https://agentclientprotocol.com/) — ACP specification (Zed integration)
