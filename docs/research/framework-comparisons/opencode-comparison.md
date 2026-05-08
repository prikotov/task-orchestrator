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
│ Presentation Layer │
│ • TUI (terminal user interface, Solid.js через @opentui) │
│ • Desktop App (Tauri/Electron) │
│ • VS Code / JetBrains / Zed Extensions │
│ • HTTP API (Hono) + WebSocket sync │
│ • ACP (Agent Client Protocol) — stdio JSON-RPC │
└──────────────────────────┬──────────────────────────────────┘
 │
 ▼
┌─────────────────────────────────────────────────────────────┐
│ Session Layer (session/) │
│ • Session CRUD (create/fork/list/remove) │
│ • MessageV2 (user/assistant/tool/compaction parts) │
│ • SessionPrompt.prompt() — основной entry point │
│ • SessionPrompt.loop() — agentic loop (LLM → tools → LLM) │
│ • RunState — mutual exclusion per session (Runner) │
│ • Status: idle / busy / retry │
│ • Cost tracking (getUsage: tokens + cost per call) │
└──────────────────────────┬──────────────────────────────────┘
 │
 ▼
┌─────────────────────────────────────────────────────────────┐
│ Agent Loop (session/processor.ts + session/llm.ts) │
│ • Stream processing (start → text/tool/reasoning → finish) │
│ • Tool lifecycle: pending → running → completed/error │
│ • Doom loop detection (3 repeated identical tool calls) │
│ • Retry policy (exponential backoff + Retry-After headers) │
│ • Context overflow → auto-compaction │
│ • Permission checks per tool call │
│ • AbortSignal support │
└──────────────────────────┬──────────────────────────────────┘
 │
 ┌───────────┴───────────┐
 ▼ ▼
┌────────────────────┐ ┌────────────────────────────────────┐
│ Agent Service │ │ Tool Registry │
│ (agent/agent.ts) │ │ (tool/registry.ts) │
│ • build (primary) │ │ • shell, read, write, edit, │
│ • plan (primary) │ │ apply_patch, glob, grep │
│ • general (sub) │ │ • task (subagent delegation) │
│ • explore (sub) │ │ • todo, question, skill, plan │
│ • compaction │ │ • webfetch, websearch, lsp │
│ • title, summary │ │ • Custom: .opencode/tool/*.ts │
│ • Custom agents │ │ • Plugin tools │
│ (.opencode/ │ │ • MCP tools │
│ agent/*.md) │ │ │
│ • AI-generated │ │ │
│ agents │ │ │
└────────┬───────────┘ └────────────────────────────────────┘
 │
 ▼
┌─────────────────────────────────────────────────────────────┐
│ Provider Layer (provider/) │
│ • 23+ LLM провайдеров через Vercel AI SDK │
│ • Anthropic, OpenAI, Google, Azure, Bedrock, Groq, ... │
│ • Model-specific prompts (anthropic.txt, gpt.txt, ...) │
│ • Cost calculation per provider (input/output/cache/reason) │
│ • Variant system (cheapest/flash/default/progressive) │
│ • GitHub Copilot provider (OAuth + OpenAI-compatible) │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ Cross-cutting Services │
│ • Permission (allow/ask/deny per tool + glob patterns) │
│ • Compaction (auto-summarization при context overflow) │
│ • Skills (SKILL.md discovery из .opencode/skills/) │
│ • Plugins (JS/TS модули, hooks, custom tools) │
│ • MCP (Model Context Protocol) │
│ • LSP (Language Server Protocol, встроенный) │
│ • Snapshot (file diff tracking per step) │
│ • Worktree (git worktree create/remove/reset) │
│ • Sync (event-sourced state через SQLite + sync events) │
│ • Bus (publish/subscribe event bus) │
│ • Storage (SQLite через Drizzle ORM) │
│ • Effect-TS (Context.Tag + Layer — dependency injection) │
└─────────────────────────────────────────────────────────────┘
```

### Ключевые характеристики

| Характеристика | Значение |
| --- | --- |
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

## 2. Возможности оркестрации — обзор

| Функция | OpenCode |
| --- | --- |
| **Итерационные циклы** | ✅ Agent steps (maxSteps) — forced text-only response при достижении лимита |
| **Doom loop detection** | ✅ 3 повторяющихся идентичных tool call → permission ask |
| **Permission system** | ✅ allow/ask/deny per tool + glob patterns + inherited + session-level overrides |
| **Agent modes** | ✅ 7 built-in агентов + custom + AI-generated; primary/subagent/hidden режимы |
| **Subagents (task tool)** | ✅ Isolated session + permission inheritance + resume по task_id + no recursive delegation |
| **Context compaction** | ✅ Auto-compaction с 7-секционным structured template + pruning + preserve recent turns |
| **Git worktrees** | ✅ Create/remove/reset + auto-branch + start scripts + snapshot tracking |
| **Cost tracking** | ✅ Per-message cost + tokens (input/output/reasoning/cache read/cache write) + provider-specific pricing |
| **Skills (SKILL.md)** | ✅ Discovery из .opencode/skills/, .claude/skills/, .agents/skills/ + remote URLs + permission filtering |
| **Custom agents** | ✅ Markdown-файлы (.opencode/agent/*.md) + AI-генерация через generateObject |
| **Plugin system** | ✅ JS/TS модули + hooks + custom tools + system prompt transformation |
| **MCP support** | ❌ Нет (уровень runner'ов) |
| **LSP support** | ❌ Нет |
| **Snapshot tracking** | ✅ File diff tracking per LLM step + revert capability |
| **Error classification** | ✅ ContextOverflow/API 5xx/FreeUsageLimitError/GoUsageLimitError/rate limit — retry/no-retry classification |
| **Client-server architecture** | ❌ Нет (CLI только) |
| **ACP (Agent Client Protocol)** | ❌ Нет |
| **DDD-архитектура** | ❌ Effect-TS service layer (Context.Tag + Layer) |
| **Decorator pattern** | ❌ Прямой вызов Effect-пайплайна |
| **JSONL audit trail** | ⚠️ Sync events (event-sourced), не audit trail в нашем смысле |

---

## 3. Оркестрационные возможности

### 3.1 🟡 Doom Loop Detection (session/processor.ts)

**Что у них:** Обнаружение зацикливания через подсчёт повторяющихся tool calls:

```typescript
// В processor.ts, обработка события "tool-call"
const recentParts = parts.slice(-DOOM_LOOP_THRESHOLD) // DOOM_LOOP_THRESHOLD = 3

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
 return // не doom loop — продолжаем
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

**Оркестрационная значимость:** Защита от зацикливания в fix_iterations. Подтверждено 3+ проектами (Crush — window-based, OpenHands SDK — 4+1, Paperclip AI — evidence-based). OpenCode предлагает простейшую реализацию: 3 идентичных вызова = doom loop.

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

**Оркестрационная значимость:** Наиболее структурированный подход к compaction из исследованных. Kilo Code имеет похожий 7-секционный шаблон, но OpenCode добавляет pruning, preserve recent и replay — три дополнительных механизма. Для длинных цепочек и dynamic loops контекст-менеджмент станет необходим.

### 3.3 🟡 Permission System с Glob Patterns (permission/)

**Что у них:** Трёхуровневая система allow/ask/deny для каждого tool:

```typescript
// Конфигурация (opencode.jsonc)
{
 "permission": {
 "*": "allow", // все tools → allow
 "doom_loop": "ask", // doom loop detection → ask
 "edit": { "*.env": "ask" }, // edit .env → ask
 "external_directory": { // доступ к внешним директориям
 "*": "ask",
 "/tmp/*": "allow"
 },
 "read": { "*.env": "ask" }, // read .env → ask
 "question": "deny" // question tool → deny
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

**Оркестрационная значимость:** Трёхуровневая модель allow/ask/deny с glob-фильтрацией — механизм разграничения доступа на уровне агентного loop. Уровень `ask` подразумевает интерактивное подтверждение пользователем, что ограничивает применимость в полностью автоматическом (batch) режиме. Glob patterns позволяют детализировать доступ до уровня файлов и директорий.

### 3.4 🟡 Subagent Task Tool (tool/task.ts + session/prompt.ts + agent/agent.ts)

**Что у них:** Делегирование подзадачи в изолированную сессию через `TaskTool` (tool/task.ts). Вызов происходит двумя путями: (1) LLM-primary agent вызывает task tool через tool call; (2) slash-команда или subtask-часть сообщения (SubtaskPart) инициирует subagent через handleSubtask() в prompt.ts. Оба пути приводят к одному и тому же механизму — созданию child session и запуску agent loop.

```typescript
// Параметры task tool (Schema-валидация)
{
 description: "short 3-5 words", // краткое описание задачи
 prompt: "task for the agent", // полный промпт для субагента
 subagent_type: "general", // имя агента (general | explore | custom)
 task_id: "optional_for_resume", // для продолжения предыдущей сессии
 command: "triggering command" // команда, вызвавшая задачу
}
```

Ключевые особенности:
- **Isolated session** — создаёт child session (parentID linkage), сохраняется в SQLite
- **Permission inheritance** — deny rules + external_directory от parent; task/todowrite запрещены по умолчанию
- **No recursive delegation** — task и todowrite запрещены для subagents, если агент явно не разрешает их через свой permission ruleset
- **Primary tools isolation** — экспериментальный `primary_tools` конфиг: инструменты из списка запрещены для subagents
- **Resume** — task_id для продолжения предыдущей subagent сессии (полная история сообщений сохранена в child session)
- **AbortSignal propagation** — parent abort → child cancel через acquireUseRelease + removeEventListener cleanup
- **Model inheritance** — subagent использует модель parent (из assistantMessage), если у агента нет явного model override
- **Tool filtering** — subagent получает ограниченный набор инструментов: task=false, todowrite=false, primary_tools=false

**Оркестрационная значимость:** «Chain внутри chain» с изолированным контекстом. Kilo Code имеет аналогичный task tool, но OpenCode добавляет resume по task_id и primary_tools isolation — уникальные возможности. Для dynamic chains: изолированные шаги с собственным контекстом + возможность возобновления.

---

#### 3.4.1 Архитектура субагентов — детальный разбор

##### 1. Жизненный цикл субагента

Жизненный цикл субагента — это state machine с пятью состояниями:

```
┌──────────┐ create ┌───────────┐ prompt() ┌───────────┐
│ none │─────────────►│ created │─────────────►│ running │
└──────────┘ └───────────┘ └─┬─────┬───┘
 │ │
 completed ◄──┘ └──► error / aborted
```

**Последовательность операций в `TaskTool.execute()` (tool/task.ts:67–161):**

1. **Permission check** — `ctx.ask()` с permission `"task"` и patterns `[subagent_type]`. Если не bypass — LLM решает, разрешить ли.
2. **Agent resolution** — `agent.get(subagent_type)`. Если агент не найден — `Effect.fail`.
3. **Capability check** — проверка `canTask` и `canTodo` по permission ruleset агента. Определяет, какие инструменты будут доступны субагенту.
4. **Session resolution:**
 - Если передан `task_id` — попытка загрузить существующую сессию (`sessions.get(taskID)` с catchCause → undefined при отсутствии). Это механизм **resume**.
 - Если `task_id` не передан или сессия не найдена — `sessions.create()` с параметрами наследования.
5. **Model resolution** — берётся модель из agent config, либо наследуется от текущего assistant message.
6. **Metadata update** — `ctx.metadata()` записывает sessionId и model в tool call state (для UI).
7. **Запуск agent loop** — `ops.prompt()` вызывает `SessionPrompt.prompt()` → `createUserMessage()` → `loop()` → `runLoop()` для child session.
8. **Abort registration** — `ctx.abort.addEventListener("abort", onAbort)`. При parent abort → `ops.cancel(nextSession.id)`.
9. **Cleanup** — `acquireUseRelease` гарантирует: при interrupt → cancel + removeEventListener.

**Результат** возвращается родителю в формате:
```
task_id: <sessionID> (for resuming to continue this task if needed)

<task_result>
<последний text-part ответа субагента>
</task_result>
```

Родитель (LLM primary agent) получает этот результат как tool observation и может:
- Проанализировать результат и продолжить работу
- Использовать `task_id` для повторного вызова с тем же субагентом (resume)

##### 2. Типы агентов и роли субагентов

Встроенные агенты определены в `agent/agent.ts` (state initializer). Каждый агент имеет `mode`:

| Агент | mode | Может быть субагентом | Назначение |
| --- | --- | --- | --- |
| **build** | `primary` | Нет (primary-only) | Основной агент. Полный доступ к tools, question, plan_enter. |
| **plan** | `primary` | Нет (primary-only) | Режим планирования. edit запрещён, кроме plan files. |
| **general** | `subagent` | **Да (основной субагент)** | Универсальный. todowrite запрещён по умолчанию. |
| **explore** | `subagent` | **Да** | Быстрый поиск: только grep/glob/list/bash/webfetch/websearch/read. |
| **compaction** | `primary` (hidden) | Нет | Служебный: context compaction. Все tools запрещены. |
| **title** | `primary` (hidden) | Нет | Служебный: генерация заголовка сессии. |
| **summary** | `primary` (hidden) | Нет | Служебный: summarization. |

Custom agents (`.opencode/agent/*.md`) могут иметь mode `"all"` (и primary, и subagent), `"primary"`, или `"subagent"`.

**Два пути запуска субагента:**

1. **LLM tool call** — primary agent (build/plan) вызывает `TaskTool` через tool call. Субагент определяется через `subagent_type`.
2. **SubtaskPart** — пользователь вводит `@agent_name` в сообщении, или slash-команда с `subtask: true`. `handleSubtask()` в prompt.ts создаёт assistant message + tool part и вызывает TaskTool.execute() напрямую (bypassAgentCheck=true).

##### 3. Изоляция контекста

**Что от родителя:**
- **Permission deny rules** — все deny-правила parent session копируются в child: `parent.permission.filter(rule => rule.permission === "external_directory" || rule.action === "deny")`.
- **Model** — если у агента нет явного model config, наследуется от текущего assistant message.
- **Worktree/directory** — child session привязан к тому же project/directory через InstanceState context.
- **AbortSignal** — parent abort → child cancel. Реализовано через `addEventListener("abort", onAbort)` + `acquireUseRelease`.

**Что изолировано:**
- **История сообщений** — child session начинает с чистой историей (или resume history, если task_id передан). Parent history не видна субагенту.
- **Инструменты** — субагент получает ограниченный набор: `task: false`, `todowrite: false`, `primary_tools: false`. Это определяет параметр `tools` в PromptInput.
- **Permission rules** — child session получает собственный permission array, расширяющий deny rules parent дополнительными ограничениями.
- **System prompt** — субагент получает свой system prompt (из agent.prompt или агента по умолчанию), не parent prompt.
- **Session status** — child session имеет собственный busy/idle status.

**Модель данных изоляции (task.ts:87–115):**
```typescript
const nextSession = yield* sessions.create({
 parentID: ctx.sessionID, // связь parent → child
 title: params.description + ` (@${next.name} subagent)`,
 permission: [
 // 1. Наследуем deny rules и external_directory от parent
 ...(parent.permission ?? []).filter(
 (rule) => rule.permission === "external_directory" || rule.action === "deny",
 ),
 // 2. Запрещаем todowrite, если агент не разрешает
 ...(canTodo ? [] : [{ permission: "todowrite", pattern: "*", action: "deny" }]),
 // 3. Запрещаем task (рекурсивная делегация), если агент не разрешает
 ...(canTask ? [] : [{ permission: "task", pattern: "*", action: "deny" }]),
 // 4. Запрещаем primary_tools (экспериментальная фича)
 ...(cfg.experimental?.primary_tools?.map(item => ({ pattern: "*", action: "allow", permission: item })) ?? []),
 ],
})
```

##### 4. Наследование разрешений (Permission Inheritance)

Система разрешений (permission/) — трёхуровневая: agent permission + session permission + approved (runtime).

**Алгоритм оценки (`permission/evaluate.ts`):**
```typescript
// findLast — последнее правило побеждает (приоритет по порядку)
function evaluate(permission, pattern, ...rulesets): Rule {
 const rules = rulesets.flat()
 const match = rules.findLast(
 (rule) => Wildcard.match(permission, rule.permission) && Wildcard.match(pattern, rule.pattern)
 )
 return match ?? { action: "ask", permission, pattern: "*" } // default = ask
}
```

**Что наследует субагент:**
1. **deny rules parent** — все deny-правила parent session копируются.
2. **external_directory rules** — правила доступа к файлам за пределами worktree.
3. **approved (runtime approvals)** — НЕ наследуются. Каждый subagent session имеет свой набор approved rules.

**Что запрещено субагенту по умолчанию:**
- `task` — рекурсивная делегация субагентов. Запрещена, если агент не имеет явного `task: allow` в своём permission ruleset.
- `todowrite` — управление TODO-листом. Запрещено для subagents.
- `primary_tools` — экспериментальный список инструментов, доступных только primary агентам.

**Когда рекурсивная делегация разрешена:** Если custom agent явно определяет `permission: { task: "allow" }` в конфигурации — он может создавать субагентов. Но стандартные general и explore — не могут.

**Настройка:** Через opencode.jsonc:
```jsonc
{
 "permission": {
 "*": "allow",
 "task": { "general": "allow" }, // general может делегировать
 "edit": { "*.env": "deny" } // запретить edit .env для всех
 }
}
```

##### 5. Параллельность

LLM-primary agent может отправить **несколько tool calls в одном сообщении** (Vercel AI SDK поддерживает parallel tool calls). task.txt инструктирует:
> «Launch multiple agents concurrently whenever possible, to maximize performance; to do that, use a single message with multiple tool uses»

Каждый tool call → отдельный TaskTool.execute() → отдельная child session → отдельный agent loop. Это **LLM-инициированный параллелизм без оркестраторного управления**: нет планировщика, нет очереди, нет приоритизации.

**Механизм координации:**
- Каждый субагент работает в **изолированной child session** — нет shared state между параллельными субагентами.
- LLM-primary agent получает **все результаты одновременно** как tool observations в следующем assistant message.
- Координация результатов — **полностью на усмотрение LLM** (primary agent анализирует все результаты и синтезирует ответ). Нет встроенного merge/reduce, нет промежуточных барьеров синхронизации.

**Ограничения:**
- `SessionRunState` обеспечивает **mutual exclusion per session** — один agent loop на сессию. Параллельные tool calls запускают разные child sessions, но одна child session не может выполнять два agent loops одновременно.
- В plan mode: «Launch up to 3 explore agents IN PARALLEL» — guidance для LLM, не техническое ограничение.

**Runner state machine (effect/runner.ts):**
```
Idle → Running (ensureRunning)
Idle → Shell (startShell)
Running → Idle (work complete)
Shell → Idle (shell complete)
Shell → ShellThenRun (shell complete + pending run)
ShellThenRun → Running (shell done, run starts)
```

##### 6. Cost Tracking и изоляция стоимости

**Cost tracking реализован на уровне MessageV2 (сообщение), а не Session (сессия).** Каждый assistant message содержит:
```typescript
{
 cost: number, // стоимость в USD
 tokens: {
 input: number, // non-cached input tokens
 output: number, // output tokens (без reasoning)
 reasoning: number, // reasoning tokens
 cache: { read: number, write: number } // cache tokens
 }
}
```

**Изоляция стоимости между сессиями:**
- Субагент работает в своей child session. Каждый LLM call в child session создаёт assistant message с собственным cost/tokens.
- **Cost субагента НЕ прибавляется к cost родительского сообщения.** Родитель и child — раздельные sessions с раздельными message histories.
- Cost субагента учитывается **только в его собственной child session**.
- Родитель видит только результат task tool (text output), а не токены/стоимость субагента.

Следствие: при нескольких субагентах **агрегированная стоимость цепочки вызовов невидима для родителя**. Нельзя отследить суммарные затраты primary agent + все его child sessions.

**getUsage() (session/session.ts)** — provider-specific pricing:
- Anthropic: `metadata.anthropic.cacheCreationInputTokens`
- Vertex: `metadata.vertex.cacheCreationInputTokens`
- Bedrock: `metadata.bedrock.usage.cacheWriteInputTokens`
- Experimental over 200K pricing

**Budget limit:** В OpenCode **нет явного budget limit**. Cost tracking — исключительно информационный. Нет механизма остановки выполнения по достижении лимита стоимости.

##### 7. Resume по task_id

**Механизм:** Если в вызове TaskTool передан `task_id`, происходит resume:

1. `sessions.get(SessionID.make(task_id))` — загрузка существующей child session.
2. Если сессия найдена — она используется как `nextSession` (без создания новой).
3. Если не найдена (`catchCause → undefined`) — создаётся новая сессия.
4. В child session вызывается `ops.prompt()` с новым messageID и prompt.
5. `runLoop()` в child session читает существующую историю сообщений (из SQLite) и продолжает агентный loop.

**Что сохраняется между вызовами:**
- Вся история сообщений child session (user/assistant/tool parts).
- Permission rules child session.
- Session metadata (title, model, agent, parentID).
- Файловые изменения (snapshot tracking per step).

**Что НЕ сохраняется:**
- Runtime approved permissions (только в памяти).
- LLM context window (перечитывается из SQLite при каждом prompt()).

**Пример сценария:**
1. Primary agent запускает general subagent → получает task_id.
2. General subagent выполняет часть работы → возвращает промежуточный результат.
3. Primary agent анализирует результат и решает продолжить → вызывает TaskTool с тем же task_id + новым prompt.
4. General subagent возобновляется в той же session с предыдущим контекстом + новый prompt.

##### 8. Error Handling

**При ошибке субагента:**

1. **TaskTool.execute() обёрнут в `Effect.orDie`** (task.ts:161) — ошибка пробрасывается как defect (unrecoverable).
2. **Однако** в handleSubtask (prompt.ts) TaskTool.execute() обёрнут в `Effect.catchCause()` — ошибка перехватывается, логируется и **не пробрасывается в parent session**.
3. Результат: tool part получает `status: "error"` с сообщением об ошибке.
4. **Родитель НЕ падает** — agent loop родителя продолжает работу. Error представляется как tool observation.
5. LLM-primary agent видит ошибку и может решить: повторить, использовать другой подход, или сообщить пользователю.

**Классы ошибок:**
- `PermissionDeniedError` — субагент нарушил deny rule. Agent loop получает ошибку как tool result.
- `PermissionRejectedError` — пользователь отклонил permission request.
- `ContextOverflowError` — в child session. Триггерит compaction.
- `APIError` (5xx, rate limit) — retry с exponential backoff внутри child session.
- `AbortError` — parent cancel. TaskTool cleanup через `acquireUseRelease` + `onInterrupt`.

**Doom loop detection** работает и в child session: 3 идентичных tool call подряд → permission ask (если не deny).



### 3.5 🟡 Error Classification + Retry Policy (session/retry.ts)

**Что у них:** Классификация ошибок для умного retry:

```typescript
// Не retryable:
if (MessageV2.ContextOverflowError.isInstance(error)) return undefined

// Retryable:
if (status >= 500) return { message: ... } // 5xx → retry
if (responseBody?.includes("FreeUsageLimitError")) return ... // upsell
if (responseBody?.includes("GoUsageLimitError")) return ... // upsell
if (msg.includes("rate limit")) return { message: msg } // rate limit → retry
if (json.error?.type === "too_many_requests") return ... // 429 → retry

// Retry-After header parsing:
const retryAfterMs = headers["retry-after-ms"] // миллисекунды
const retryAfter = headers["retry-after"] // секунды или HTTP date
// Fallback: exponential backoff 2s → 4s → 8s → ... → 30s max
```

**Оркестрационная значимость:** Конкретная модель для RetryingAgentRunner: context overflow → не retry (→ compact), 5xx → retry с backoff, rate limit → retry с Retry-After. Дополнение к circuit breaker: CB защищает от cascade, error classification — от бессмысленных retry.

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

**Оркестрационная значимость:** Модель для переиспользуемых chain templates: markdown-определение шага/агента с frontmatter-конфигурацией. AI-генерация — для будущего DSL: user описывает, что нужно → AI генерирует chain definition.

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

**Оркестрационная значимость:** Для параллельных chain runs: изоляция через git worktrees. Sandcastle и Archon предлагают похожие модели. OpenCode добавляет reset и submodule update — наиболее полная реализация cleanup.

### 3.8 🟡 Cost Tracking (session/session.ts)

**Что у них:** Детальный cost tracking per message:

```typescript
export function getUsage(input: { model, usage, metadata }) {
 const tokens = {
 total,
 input: adjustedInputTokens, // input - cache read - cache write
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

**Оркестрационная значимость:** Детализация cost tracking для budget control: cache tokens отдельно, reasoning отдельно, provider-specific pricing. Для BudgetVo: более точный расчёт стоимости.

### 3.9 🟡 Snapshot Tracking + Revert (snapshot/, session/revert.ts)

**Что у них:** File diff tracking per LLM step с возможностью отката:

- **Snapshot** — pre-step file system state, post-step diff (snapshot → patch → file changes)
- **Step-level patches** — каждый LLM step получает `step-start` (snapshot) и `step-finish` (diff)
- **Revert** — откат к конкретному message/part через сохранённый snapshot
- **Summary** — additions/deletions/files/diffs per session

**Оркестрационная значимость:** Audit trail на уровне файловых изменений: каждый шаг цепочки оставляет diff. Revert — механизм отката failed chains.

---

## 4. Прочие возможности (вне оркестрации)

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

## 5. Сводка по оркестрации

| Возможность | Статус в продукте | Описание |
| --- | --- | --- |
| Doom loop detection | 🟡 P2 | Защита от зацикливания в fix_iterations. Подтверждено OpenCode (3 идентичных вызова) + Crush + OpenHands + Paperclip AI |
| Error classification для retry | 🟡 P2 | ContextOverflow → compact, 5xx → retry, rate limit → retry с Retry-After. Конкретная модель для RetryingAgentRunner |
| Permission system (allow/ask/deny + glob) | 🟡 P2 | Интерактивное разграничение доступа с glob-фильтрацией. ask-уровень требует подтверждения — ограничение для batch-режима |
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
