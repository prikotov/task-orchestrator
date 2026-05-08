# Исследование: Kilo Code — AI-агентная платформа с оркестрацией через subagents

> **Проект:** [github.com/Kilo-Org/kilocode](https://github.com/Kilo-Org/kilocode)
> **Дата анализа:** 2026-05-08
> **Язык:** TypeScript (Bun, Effect-TS)
> **Лицензия:** MIT
> **Версия:** v7.2.44
> **Аналитик:** Аналитик (Шерлок)

---

## 1. Обзор проекта

Kilo Code — **полнофункциональная AI-агентная платформа для разработки ПО** от Kilo AI. Включает VS Code Extension, CLI-клиент, JetBrains plugin. Реализует модель `LLM → tool call → observation → LLM → ...` со встроенным механизмом subagents через `task` tool. Ключевая особенность: **оркестратор теперь встроен в каждый primary-агент** — специализированный Orchestrator Mode объявлен deprecated.

> ⚠️ **Важно:** Orchestrator Mode официально **deprecated** и будет удалён в будущей версии. Документация прямо заявляет: «Orchestrator mode is no longer needed — agents with full tool access (Code, Plan, Debug) can now delegate to subagents natively». Тем не менее, архитектурные паттерны, которые он представляет, заслуживают детального анализа, т.к. они сохранены в текущей архитектуре subagents.

Архитектура Kilo Code радикально отличается от task-orchestrator: Kilo Code — **интерактивный AI-ассистент** с direct LLM API calls, agent modes и permission system. Task-orchestrator — **batch chain orchestrator**, который управляет выполнением внешних runner'ов через YAML-цепочки. Разные уровни абстракции: Kilo Code работает на уровне agent loop + tool calls, task-orchestrator — на уровне chain steps + retry + circuit breaker + quality gates.

### Архитектура

```
┌─────────────────────────────────────────────────────────────┐
│ Пользователь (CLI / VS Code / JetBrains) │
│ • Выбор агента: Code, Plan, Debug, Ask, Custom │
│ • Ввод промпта │
│ • Интерактивное подтверждение (ask/deny/allow) │
└──────────────────────────┬──────────────────────────────────┘
 │
 ▼
┌─────────────────────────────────────────────────────────────┐
│ Agent Service (agent.ts) │
│ • Agent.Info: name, mode (primary/subagent/all), │
│ permission, model, prompt, steps, temperature, topP │
│ • 7 built-in агентов: code, plan, debug, ask, │
│ orchestrator (deprecated), general, explore │
│ • Custom agents: JSON config / Markdown files / CLI create │
│ • Permission system: allow/ask/deny per tool │
│ • Mode: primary (user-facing) / subagent (delegated) / all │
└──────────────────────────┬──────────────────────────────────┘
 │
 ▼
┌─────────────────────────────────────────────────────────────┐
│ Session (session.ts) │
│ • Session prompt → LLM streaming → tool calls │
│ • MessageV2: role, modelID, providerID, cost, parts │
│ • Context compaction при overflow (LLM summarization) │
│ • Retry policy: exponential backoff + Retry-After headers │
│ • Max steps control (agent.steps) │
└──────────────────────────┬──────────────────────────────────┘
 │
 ┌───────────┴───────────┐
 ▼ ▼
┌────────────────────┐ ┌────────────────────────────────────┐
│ Tools (tool/) │ │ Task Tool (task.ts) │
│ • read/write/edit │ │ • Subagent invocation mechanism │
│ • bash │ │ • Creates isolated child session │
│ • grep/glob │ │ • Permission inheritance │
│ • plan │ │ • Cost propagation (child→parent) │
│ • question │ │ • task_id для resume │
│ • skill │ │ • Subagent type: general/explore │
│ • mcp │ │ • Parallel invocation support │
│ • webfetch/search │ │ • Subagent cannot call task │
│ • todo │ │ (deny by default) │
│ • diagnostics │ │ • AbortSignal propagation │
│ • apply_patch │ │ │
│ • task │ │ │
└────────────────────┘ └────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ Permission System (permission/) │
│ • Rules: { permission, pattern, action } │
│ • Action: allow / ask / deny │
│ • Pattern matching: glob (*, git diff*) │
│ • Last matching rule wins │
│ • Bash allowlist/denylist с glob patterns │
│ • Per-agent overrides через config │
│ • Inheritance: caller restrictions → child session │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ Orchestrator Agent (deprecated) │
│ • Prompt: wave-based execution pattern │
│ • Bash: denied (только task delegation) │
│ • Edit: denied │
│ • Task: allowed (делегирование subagents) │
│ • Flow: explore → plan → wave-by-wave → synthesize │
│ • Wave = параллельные tool calls в одном сообщении │
│ • Зависимости: independent → parallel, dependent → │
│ sequential │
└─────────────────────────────────────────────────────────────┘
```

### Ключевые характеристики

| Характеристика | Значение |
| --- | --- |
| **Тип** | AI-агентная платформа для разработки (VS Code + CLI + JetBrains) |
| **Модель выполнения** | Agent loop (LLM → tool call → observation → LLM → ...) + subagent delegation |
| **Агенты** | 7 built-in (code, plan, debug, ask, orchestrator, general, explore) + custom |
| **Subagent модель** | Task tool: isolated session, permission inheritance, cost propagation, resume |
| **State management** | In-memory + context compaction (LLM summarization) + session persistence |
| **Permission system** | allow/ask/deny per tool с glob patterns, inheritance, bash allowlist |
| **Retry** | Exponential backoff + Retry-After header parsing + error classification |
| **Error classification** | ContextOverflow (no retry), API 5xx (retry), rate limit (retry), Kilo errors (no retry) |
| **Расширяемость** | Custom agents (JSON/markdown/CLI), MCP, Skills (SKILL.md), Plugins, Workflows |
| **Язык реализации** | TypeScript, Effect-TS, Bun, Zod, Vercel AI SDK |

---

## 2. Возможности оркестрации — обзор

| Функция | Kilo Code |
| --- | --- |
| **DAG / Workflow engine** | ❌ Только линейные/динамические цепочки |
| **Retry с backoff** | ✅ Exponential backoff + Retry-After header parsing |
| **Subagent / Sub-task** | ✅ Task tool: isolated session, parallel, resume, cost propagation |
| **Permission system** | ✅ allow/ask/deny per tool, glob patterns, inheritance, bash allowlist |
| **Context compaction** | ✅ LLM summarization при overflow с structured template |
| **Agent modes** | ✅ Agent.Info с mode (primary/subagent/all), per-agent model/temperature/prompt |
| **Parallel execution** | ✅ Несколько task tool calls в одном сообщении (parallel subagents) |
| **Error classification** | ✅ ContextOverflow, API 5xx, rate limit, FreeUsageLimitError, Kilo errors |
| **Cost propagation** | ✅ Child→parent cost propagation с concurrent lock |
| **Wave-based orchestration** | ✅ Orchestrator prompt: parallel waves, dependency classification |
| **Workflows (slash commands)** | ✅ Markdown files как пошаговые инструкции |
| **MCP support** | ✅ MCP client (встроенный) |
| **Skills (SKILL.md)** | ✅ Skill discovery из config/skill директорий |
| **Custom agents** | ✅ JSON config / Markdown files / CLI create / Agent.generate (LLM-based) |
| **DDD-архитектура** | ❌ Effect-TS service layer (Context.Tag + Layer) |
| **Decorator pattern** | ❌ Прямой вызов Effect-пайплайна |
| **Session resume** | ✅ task_id для resume subagent session |
| **Git worktree** | ✅ worktree/ модуль (ограниченная поддержка) |
| **Autonomous CI/CD mode** | ✅ `kilo run --auto` (no user interaction) |

---

## 3. Оркестрационные возможности

### 3.1 🟡 Wave-Based Orchestration Pattern (из Orchestrator prompt)

**Что у них:** Orchestrator-агент использует **wave-based execution pattern** — пошаговую координацию параллельных subagents:

1. **Understand** — исследовать кодовую базу через explore-агентов
2. **Plan** — разбить задачу на подзадачи с указанием файлов
3. **Classify dependencies** — независимые подзадачи → parallel (одна wave), зависимые → sequential (разные waves)
4. **Execute wave by wave** — параллельные tool calls в одном LLM-сообщении
5. **Synthesize** — объединить результаты

Ключевое правило: **«All agents share the same working directory. If two subtasks are likely to edit the same files, they MUST be in different waves to avoid conflicts.»**

**Ограничения и риски:** Wave-based подход — упрощённая альтернатива DAG, но с существенными компромиссами:

1. **LLM-классификация зависимостей ненадёжна.** Агент определяет, какие подзадачи независимы, а какие — нет. False positive (зависимые задачи определены как независимые) → параллельное выполнение → конфликт редактирования одних и тех же файлов. False negative (независимые → зависимые) → ненужная последовательность → потеря скорости.
2. **Shared working directory — фундаментальное ограничение.** Все subagents работают в одной файловой системе. Единственная защита — инструкция в промпте: «If two subtasks are likely to edit the same files, they MUST be in different waves». Это soft guarantee, зависящая от качества LLM-рассуждений.
3. **Deprecated статус Orchestrator Mode.** Авторы Kilo Code отказались от специализированного оркестратора в пользу embedded subagents (task tool доступен в каждом primary agent). Это указывает на то, что выделенный оркестрирующий агент не показал преимуществ перед прямым делегированием.
4. **Нет механизма rollback.** Если wave завершена с ошибкой — нет встроенного способа откатить изменения предыдущих wave. Agent полагается на git как внешний механизм восстановления.

### 3.2 🟡 Subagent Isolation с Permission Inheritance (KiloTask.inherited)

**Что у них:** При запуске subagent через task tool:
- Создаётся **isolated child session** — отдельная история, отдельный контекст
- **Permission inheritance**: edit/bash/MCP restrictions от caller → child
- **Subagent не может создавать sub-subagents** (task permission → deny by default)
- **Cost propagation**: child cost → parent message (с concurrent lock от race conditions)
- **Model override**: subagent может использовать другую модель (cheaper для простых задач)

```typescript
// Ключевая логика из task.ts
const rules = KiloTask.inherited({ caller, session, mcp })
const nextSession = yield* sessions.create({
 parentID: ctx.sessionID,
 permission: [
 ...(parent.permission ?? []).filter(...),
 // Запретить task и todowrite для subagents
 ...(canTask ? [] : [{ permission: "task", pattern: "*", action: "deny" }]),
 ...KiloTask.permissions(rules),
 ],
})
```

**Оркестрационная значимость:** Модель для вложенных chain runs (chain внутри chain). Permission inheritance — способ передать ограничения родительской цепочки в дочернюю. Cost propagation — механизм агрегации затрат при вложенных вызовах.

### 3.3 🟡 Permission System с Glob Patterns

**Что у них:** Трёхуровневая система разрешений:

| Action | Поведение |
| --- | --- |
| `allow` | Разрешить без запроса |
| `ask` | Запросить подтверждение пользователя |
| `deny` | Заблокировать полностью |

Поддерживается glob-паттерны для bash-команд:
```json
{
 "bash": {
 "*": "ask",
 "git diff *": "allow",
 "rm *": "deny"
 }
}
```

**Last matching rule wins** — порядок важен. Встроенный bash allowlist из 40+ безопасных команд (cat, grep, ls, jq, git log, git diff, ...). Read-only bash отдельный набор (без git stash, git push, sort -o, пайпов, редиректов).

**Оркестрационная значимость:** Для автономного выполнения в CI/CD — необходимый механизм. Реализуемо на уровне chain executor: каждый шаг chain может иметь permission profile (allow/deny per tool type). Glob patterns для shell-команд — практичная альтернатива Docker sandboxing: не нужен контейнер, достаточно whitelist команд. Подтверждено Codex (exec policy), Claude Code (allow/deny lists).

### 3.4 🟡 Cost Propagation с Concurrent Lock

**Что у них:** При завершении subagent — его cost delta добавляется к parent assistant message:

```typescript
// acquireUseRelease pattern — serialize concurrent propagations
yield* Effect.acquireUseRelease(
 // acquire: snapshot child cost
 Effect.gen(function* () {
 return yield* KiloCostPropagation.childCost(sessions, nextSession.id)
 }),
 // use: run subagent
 () => Effect.gen(function* () { /* ... */ }),
 // release: propagate cost delta
 (costBefore) => Effect.gen(function* () {
 const costAfter = yield* KiloCostPropagation.childCost(sessions, nextSession.id)
 yield* KiloCostPropagation.propagate(sessions, ctx.sessionID, ctx.messageID, costAfter - costBefore)
 }),
)
```

Concurrent lock (`acquire(key)`) предотвращает lost updates при параллельном завершении нескольких subagents.

**Оркестрационная значимость:** Для вложенных chain runs — агрегация cost на каждом уровне иерархии. Parent chain знает стоимость всех child chains. Concurrent lock — важная деталь для корректности при параллельном выполнении шагов. В PHP аналогом может быть pessimistic locking через DB transaction.

### 3.5 🟡 Error Classification для Retry Policy

**Что у них:** Retry policy классифицирует ошибки перед retry:

| Тип ошибки | Retry? | Обоснование |
| --- | --- | --- |
| ContextOverflowError | ❌ Нет | Контекст переполнен — retry бессмысленно |
| API 5xx | ✅ Да | Transient server failure |
| Rate limit (429) | ✅ Да | С backoff + Retry-After header |
| isKiloError | ❌ Нет | Требует действия пользователя (login/signup) |
| FreeUsageLimitError | ❌ Нет | Retry capped model бесполезен |
| «Overloaded» | ✅ Да | С backoff |
| JSON error codes (exhausted/unavailable) | ✅ Да | Provider overloaded |

Retry delay: exponential backoff (2s → 4s → 8s → ...) + respect Retry-After headers (ms, seconds, HTTP date). Max delay: 30s (no headers) / 2^31 ms (with headers).

**Оркестрационная значимость:** Конкретная модель для нашего `RetryingAgentRunner`. Сейчас retry на любую ошибку — нужно добавить классификацию: context overflow → не retry, auth errors → не retry, rate limit → retry с backoff + Retry-After. Подтверждено Archon, OpenClaw, Hermes Agent.

### 3.6 🟡 Context Compaction с Structured Template

**Что у них:** При context overflow — LLM-суммаризация с structured template:

```
## Goal
## Constraints & Preferences
## Progress (Done / In Progress / Blocked)
## Key Decisions
## Next Steps
## Critical Context
## Relevant Files
```

Параметры: PRUNE_MINIMUM = 20K tokens, PRUNE_PROTECT = 40K tokens, tool output truncated до 2000 chars, protected tools (skill), DEFAULT_TAIL_TURNS = 2.

**Ограничения и риски:** Context compaction — lossy-операция с несколькими критическими компромиссами:

1. **Потеря intermediate values.** LLM-суммаризация может потерять конкретные данные: file paths, error messages, variable values, intermediate вычисления. Structured template частично решает проблему, но не гарантирует сохранение всех значимых деталей.
2. **Зависимость от качества summarization-model.** Если модель, выполняющая compaction, плохо структурирует информацию — subsequent steps получают некачественный контекст, что ведёт к ошибкам. Качество compaction = качество модели × качество template.
3. **Hardcoded пороги.** PRUNE_MINIMUM = 20K tokens, PRUNE_PROTECT = 40K tokens — фиксированные значения, не адаптируются к context window конкретной модели (у GPT-4 и Claude разные limits). Несоответствие порога и window → преждевременное или запоздалое сжатие.
4. **Tool output truncation до 2000 chars** — агрессивный лимит, вырезающий полный вывод команд (bash output, file contents). Для debugging-сценариев потеря полного tool output критична.
5. **Нет оценки влияния на качество.** В коде нет метрик или A/B тестирования, показывающих, как compaction влияет на success rate последующих шагов. Эмпирические данные отсутствуют.

### 3.7 🟡 Custom Agent Configuration (JSON + Markdown)

**Что у них:** Три способа создания custom agents:

1. **JSON config** (kilo.jsonc): описание, mode, model, prompt, permissions, temperature, steps
2. **Markdown files** (~/.config/kilo/agents/*.md или .kilo/agents/*.md): YAML frontmatter + markdown body как prompt
3. **CLI interactive**: `kilo agent create` — AI-генерация конфигурации из описания

Configuration precedence: built-in defaults → global config → project config → global markdown → project markdown. Properties merge (override, not replace).

**Оркестрационная значимость:** Markdown files с YAML frontmatter — удобный формат для определения chain templates (аналог наших YAML-цепочек, но с richer metadata). AI-генерация конфигурации (`Agent.generate`) — интересный подход для onboarding.

### 3.8 🟡 Steps Limit (Max Agentic Iterations)

**Что у них:** Каждый агент имеет параметр `steps` — максимальное количество agentic итераций (LLM call + tool calls). После достижения лимита — **forced text-only response**: агент обязан ответить текстом без вызова tool calls, даже если задача не завершена.

```json
{ "agent": { "test-gen": { "steps": 15 } } }
```

**Механизм работы:**
1. Каждая итерация agent loop (LLM call → tool calls → observation) увеличивает счётчик.
2. При `steps_exceeded` — LLM получает системное сообщение о необходимости завершить ответ текстом.
3. Forced text-only response не гарантирует полезный результат — агент может вернуть неполный или бессодержательный ответ.

**Ограничения:**
1. **Steps ≠ time.** Лимит итераций не контролирует wall-clock time: одна итерация с длительным tool call (bash, MCP) может занять минуты, при этом steps counter = 1.
2. **Нет recovery-стратегии.** При forced text-only response — нет встроенного механизма resume или retry. Вызывающая сторона получает whatever text agent managed to produce.
3. **Default значение не документировано явно.** Built-in агенты используют разные defaults (code=80, ask=1), custom agents — без явного указания fallback на глобальный default.
4. **Не распространяется на subagents.** Каждый subagent через task tool имеет собственный steps limit, independent от parent. Parent не может ограничить суммарное количество шагов parent + children.

---

## 4. Прочие возможности (вне оркестрации)

### 4.1 🟢 Agent Loop (прямое LLM API взаимодействие)

Kilo Code делает прямые LLM API calls через Vercel AI SDK (streaming, tool calls, structured output). Task-orchestrator делегирует runner'ам (pi, codex). Разные уровни абстракции: Kilo Code = agent runtime, task-orchestrator = chain orchestrator поверх agent runtimes.

### 4.2 🟢 Effect-TS как runtime dependency

Effect-TS — мощный функциональный фреймворк (Context.Tag, Layer, Schedule, Effect.gen). Наши слои Domain/Application/Infrastructure + Symfony DI решают те же задачи проще для PHP-экосистемы.

### 4.3 🟢 Interactive Permission System (ask → user confirmation)

`ask` action запрашивает подтверждение пользователя перед выполнением. Task-orchestrator — batch pipeline для CI/CD, не interactive tool. Permission system для нас = declarative deny/allow без runtime user confirmation.

### 4.4 🟢 VS Code / JetBrains Extension

GUI-интеграция в IDE — не наш уровень. Task-orchestrator — CLI/library.

### 4.5 🟢 Deprecated Orchestrator Mode

Сам Orchestrator Mode объявлен deprecated — берём паттерны (wave-based execution, subagent delegation), а не конкретную реализацию. Подход Kilo Code (subagents встроены в каждый primary agent) подтверждает тренд: оркестрация — не отдельный режим, а capability каждого агента.

### 4.6 🟢 LLM-based Agent Generation (Agent.generate)

Генерация custom agent конфигурации через LLM (Vercel AI SDK `generateObject`) — удобная UX-фича, но не архитектурный паттерн для заимствования.

### 4.7 🟢 Session Persistence (SQLite / file-backed)

Kilo Code использует SQLite через Drizzle ORM для session persistence. Task-orchestrator не хранит conversation history — только chain execution state (in-memory + JSONL audit trail).

---

## 5. Сводка по оркестрации

| Возможность | Статус в продукте | Описание |
| --- | --- | --- |
| Wave-based parallel execution | 🟡 P3 | Модель для будущих dynamic chains: parallel waves внутри chain steps. Практичная альтернатива DAG |
| Subagent isolation + permission inheritance | 🟡 P2 | «Chain внутри chain» с изолированным контекстом и ограничениями. Cost propagation для вложенных вызовов |
| Permission system (allow/deny per tool, glob) | 🟡 P2 | Для CI/CD: declarative restrictions без Docker sandboxing. Bash allowlist из Kilo Code — конкретный reference |
| Cost propagation (child→parent) | 🟡 P3 | Для вложенных chain runs: агрегация cost на каждом уровне иерархии |
| Error classification для retry | 🟡 P2 | Context overflow → не retry, auth → не retry, rate limit → retry с backoff + Retry-After. Подтверждено 4+ проектами |
| Context compaction (structured template) | 🟡 P3 | Для длинных цепочек и fix_iterations — LLM summarization при overflow |
| Custom agent config (JSON/Markdown) | 🟡 P3 | Переиспользуемые chain templates с rich metadata |
| Steps limit per chain step | 🟡 P2 | `max_steps` per step — ограничение agent loop внутри одного шага |
| Agent Loop / LLM API | 🟢 — | Разный уровень абстракции |
| Effect-TS | 🟢 — | Чуждый PHP-экосистеме |
| Interactive permission (ask) | 🟢 — | Task-orchestrator — batch pipeline, не interactive tool |
| IDE extension | 🟢 — | Разная парадигма (CLI/library vs. IDE plugin) |
| Deprecated Orchestrator Mode | 🟢 — | Берём паттерны, не реализацию |

---

## 6. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории Kilo-Org/kilocode:

- [`README.md`](https://github.com/Kilo-Org/kilocode/blob/main/README.md) — обзор: VS Code extension, CLI, features, autonomous mode
- [`packages/opencode/src/agent/agent.ts`](https://github.com/Kilo-Org/kilocode/blob/main/packages/opencode/src/agent/agent.ts) — Agent Service: Agent.Info schema, built-in agents, permission defaults, custom agent config loop (~400 LOC)
- [`packages/opencode/src/agent/prompt/orchestrator.txt`](https://github.com/Kilo-Org/kilocode/blob/main/packages/opencode/src/agent/prompt/orchestrator.txt) — Orchestrator prompt: wave-based execution pattern, dependency classification, delegation guidelines
- [`packages/opencode/src/tool/task.ts`](https://github.com/Kilo-Org/kilocode/blob/main/packages/opencode/src/tool/task.ts) — Task tool: subagent invocation, session creation, permission inheritance, cost propagation (~130 LOC)
- [`packages/opencode/src/tool/task.txt`](https://github.com/Kilo-Org/kilocode/blob/main/packages/opencode/src/tool/task.txt) — Task tool description: when to use, parallel execution guidelines, context isolation
- [`packages/opencode/src/kilocode/agent/index.ts`](https://github.com/Kilo-Org/kilocode/blob/main/packages/opencode/src/kilocode/agent/index.ts) — Kilo-specific agent patches: code/plan/debug/ask/orchestrator agents, bash allowlist, permission guards (~280 LOC)
- [`packages/opencode/src/kilocode/tool/task.ts`](https://github.com/Kilo-Org/kilocode/blob/main/packages/opencode/src/kilocode/tool/task.ts) — KiloTask: validate (reject primary as subagent), inherited permissions, model resolution (~100 LOC)
- [`packages/opencode/src/session/retry.ts`](https://github.com/Kilo-Org/kilocode/blob/main/packages/opencode/src/session/retry.ts) — Retry policy: error classification, exponential backoff, Retry-After header parsing (~130 LOC)
- [`packages/opencode/src/session/compaction.ts`](https://github.com/Kilo-Org/kilocode/blob/main/packages/opencode/src/session/compaction.ts) — Context compaction: structured template, pruning thresholds, tool output truncation
- [`packages/opencode/src/kilocode/session/cost-propagation.ts`](https://github.com/Kilo-Org/kilocode/blob/main/packages/opencode/src/kilocode/session/cost-propagation.ts) — Cost propagation: acquireUseRelease, concurrent lock, child→parent delta (~80 LOC)
- [`packages/kilo-docs/pages/customize/custom-subagents.md`](https://github.com/Kilo-Org/kilocode/blob/main/packages/kilo-docs/pages/customize/custom-subagents.md) — Документация: custom subagents, JSON/Markdown config, permissions, examples
- [`packages/kilo-docs/pages/code-with-ai/agents/orchestrator-mode.md`](https://github.com/Kilo-Org/kilocode/blob/main/packages/kilo-docs/pages/code-with-ai/agents/orchestrator-mode.md) — Документация: Orchestrator Mode deprecated, migration guide
- [`packages/kilo-docs/pages/code-with-ai/agents/using-agents.md`](https://github.com/Kilo-Org/kilocode/blob/main/packages/kilo-docs/pages/code-with-ai/agents/using-agents.md) — Документация: built-in agents, switching, capabilities
- [`packages/kilo-docs/pages/customize/workflows.md`](https://github.com/Kilo-Org/kilocode/blob/main/packages/kilo-docs/pages/customize/workflows.md) — Документация: workflows / slash commands

📚 **Источники:**
1. [github.com/Kilo-Org/kilocode](https://github.com/Kilo-Org/kilocode) — репозиторий проекта (19K stars, MIT)
2. [kilo.ai/docs](https://kilo.ai/docs/code-with-ai/agents/orchestrator-mode) — документация Orchestrator Mode (deprecated)
3. [packages/opencode/src/](https://github.com/Kilo-Org/kilocode/tree/main/packages/opencode/src) — исходный код Kilo Code CLI
4. [packages/kilo-docs/](https://github.com/Kilo-Org/kilocode/tree/main/packages/kilo-docs) — документация в репозитории
5. [packages/opencode/src/agent/prompt/orchestrator.txt](https://github.com/Kilo-Org/kilocode/blob/main/packages/opencode/src/agent/prompt/orchestrator.txt) — Orchestrator prompt (wave-based pattern)
