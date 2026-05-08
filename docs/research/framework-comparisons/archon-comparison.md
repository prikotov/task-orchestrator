# Исследование: Archon — workflow-движок для AI-кодинг-агентов (TypeScript/Bun)

> **Проект:** [github.com/coleam00/Archon](https://github.com/coleam00/Archon)
> **Дата анализа:** 2026-04-21
> **Язык:** TypeScript (Bun runtime)
> **Лицензия:** MIT
> **Версия:** 0.3.6
> **Аналитик:** Технический писатель (Гермиона)

---

## 1. Обзор проекта

Archon — платформенно-независимый **workflow-движок для AI-кодинг-агентов**. Ключевая идея: определять процессы разработки как YAML-воркфлоу (DAG) — планирование, имплементация, валидация, код-ревью, создание PR — и выполнять их надёжно и воспроизводимо.

> ⚠️ **Примечание от ревьюера (Локи, 2026-04-22):** Фактическая достоверность проверена по исходному коду репозитория. Исправлены: количество таблиц БД (7 → 8), описание retry-механизма (уточнён default/max), пометка container/VM как planned в схеме изоляции. Добавлено упоминание о 21 hook event (вместо «17+»).

> «Like what Dockerfiles did for infrastructure and GitHub Actions did for CI/CD — Archon does for AI coding workflows. Think n8n, but for software development.»

Архитектура Archon в корне отличается от task-orchestrator: Archon не работает напрямую с LLM API — он **оркестрирует внешние AI-ассистенты** (Claude Code, Codex CLI, Pi Coding Agent) через их subprocess SDK. Каждый «узел» (node) воркфлоу — это полноценная сессия AI-ассистента с инструментами, MCP-серверами, хуками и skill'ами.

### Предыдущая версия (v1)

Оригинальный Archon (Python) представлял собой систему управления задачами + RAG. Полностью сохранён на ветке `archive/v1-task-management-rag`. Нынешний Archon (v2) — полная переработка на TypeScript с нуля.

### Архитектура

```
┌─────────────────────────────────────────────────────────────┐
│ Platform Adapters (Web UI, CLI, Telegram, Slack, │
│ Discord, GitHub) │
│ • IPlatformAdapter interface │
└──────────────────────────┬──────────────────────────────────┘
 │
 ▼
┌─────────────────────────────────────────────────────────────┐
│ Orchestrator │
│ (Message Routing & Context Management) │
│ • Route slash commands → Command Handler │
│ • Route AI queries → AI Router → Workflow/Command │
│ • Session lifecycle, streaming, workflow events │
└──────────────┬──────────────────────────┬───────────────────┘
 │ │
 ┌───────┴────────┐ ┌───────┴────────┐
 ▼ ▼ ▼ ▼
┌───────────┐ ┌────────────┐ ┌─────────────────────────────┐
│ Command │ │ Workflow │ │ AI Agent Providers │
│ Handler │ │ Executor │ │ (Claude / Codex / Pi) │
│ (Slash) │ │ (DAG) │ │ IAgentProvider interface │
└───────────┘ └────────────┘ └─────────────────────────────┘
 │ │ │
 └──────────────┴──────────────────────┘
 │
 ▼
┌─────────────────────────────────────────────────────────────┐
│ Isolation (IIsolationProvider) │
│ Git Worktrees (Container / VM — planned) │
└──────────────────────────┬──────────────────────────────────┘
 │
 ▼
┌─────────────────────────────────────────────────────────────┐
│ SQLite / PostgreSQL (8 Tables) │
│ Codebases • Conversations • Sessions • Workflow Runs │
│ Isolation Environments • Messages • Workflow Events │
│ Codebase Env Vars │
└─────────────────────────────────────────────────────────────┘
```

### Ключевые характеристики

| Характеристика | Значение |
| --- | --- |
| **Тип** | Workflow-движок (DAG) для AI-кодинг-агентов |
| **Модель выполнения** | DAG (nodes + depends_on) + topological layers + parallel execution |
| **AI-провайдеры** | Claude Code SDK, Codex CLI, Pi Coding Agent (через subprocess) |
| **State management** | SQLite (default) / PostgreSQL (8 таблиц) |
| **Workflow-формат** | YAML-файлы (`.archon/workflows/`) |
| **Расширяемость** | IPlatformAdapter, IAgentProvider, IIsolationProvider, MCP, Hooks, Skills, Commands |
| **Изоляция** | Git worktrees (по умолчанию) — каждый run в своём worktree |
| **Интерфейсы** | Web UI, CLI, Telegram, Slack, Discord, GitHub Webhooks |
| **Платформы** | macOS, Linux, Windows (WSL), Docker |

### Основные пакеты (monorepo)

| Пакет | Назначение |
| --- | --- |
| [`@archon/core`](https://github.com/coleam00/Archon/tree/main/packages/core) | Orchestrator, Command Handler, DB operations, Session management, Workflow operations |
| [`@archon/workflows`](https://github.com/coleam00/Archon/tree/main/packages/workflows) | DAG Executor, Loader, Router, Validator, Event Emitter, Condition Evaluator |
| [`@archon/providers`](https://github.com/coleam00/Archon/tree/main/packages/providers) | IAgentProvider: Claude, Codex, Pi (community) — subprocess-based streaming |
| [`@archon/isolation`](https://github.com/coleam00/Archon/tree/main/packages/isolation) | IIsolationProvider: WorktreeProvider (git worktree isolation) |
| [`@archon/adapters`](https://github.com/coleam00/Archon/tree/main/packages/adapters) | IPlatformAdapter: Telegram, Slack, GitHub, Discord, Web |
| [`@archon/server`](https://github.com/coleam00/Archon/tree/main/packages/server) | Hono HTTP server: REST API, SSE streaming, Web UI |
| [`@archon/web`](https://github.com/coleam00/Archon/tree/main/packages/web) | Web Dashboard: Chat, Workflow Builder (drag-and-drop), Monitoring |
| [`@archon/git`](https://github.com/coleam00/Archon/tree/main/packages/git) | Git operations: clone, worktree, branch management |
| [`@archon/paths`](https://github.com/coleam00/Archon/tree/main/packages/paths) | Path resolution: workspaces, artifacts, logs, config |

---

## 2. Возможности оркестрации — обзор

| Функция | Archon |
| --- | --- |
| **Цепочки шагов (chains)** | ✅ YAML DAG workflows (nodes + depends_on) |
| **DAG (направленный ациклический граф)** | ✅ Полноценный DAG с topological layers, parallel execution |
| **Условное ветвление** | ✅ `when:` expressions + `trigger_rule` на каждом node |
| **Retry с backoff** | ✅ Node-level retry (default: 2, max: 5), exponential backoff, error classification (FATAL/TRANSIENT) |
| **Quality Gates** | ✅ Bash-узлы + `until_bash` в loops + approval nodes |
| **Бюджетный контроль** | ✅ `maxBudgetUsd` per node (Claude only) + cost tracking per session |
| **Итерационные циклы (fix_iterations)** | ✅ Loop nodes: `loop.prompt` + `loop.until` + `loop.max_iterations` |
| **Fallback routing** | ✅ `fallbackModel` per node / per workflow (Claude only) |
| **Human-in-the-loop** | ✅ Approval nodes (approve/reject) + Interactive loops + `$LOOP_USER_INPUT` |
| **Isolation (git worktrees)** | ✅ Каждый workflow run = git worktree (IIsolationProvider) |
| **Session persistence** | ✅ SQLite/PostgreSQL: сессии, сообщения, workflow events |
| **DAG Resume on Failure** | ✅ Автоматический resume с пропуском завершённых узлов |
| **Ролевые промпты** | ✅ Markdown Commands (`.archon/commands/`) + variable substitution |
| **Multiple runners** | ✅ Claude + Codex + Pi (через IAgentProvider registry) |
| **DDD-архитектура** | ❌ Monorepo packages (core/workflows/providers/adapters) |
| **Decorator pattern** | ❌ Прямой вызов через provider registry |
| **Structured output** | ✅ `output_format` (JSON Schema) — SDK-enforced на Claude/Codex, best-effort на Pi |
| **Tool control (hooks)** | ✅ Per-node SDK hooks (21 событие: PreToolUse, PostToolUse, Stop, SessionStart и др.) |
| **Tool restrictions** | ✅ `allowed_tools` / `denied_tools` per node |
| **MCP-протокол** | ✅ Per-node MCP server configs (JSON) |
| **Skills system** | ✅ SKILL.md standard + marketplace (skills.sh) |
| **Inline sub-agents** | ✅ `agents:` в YAML — inline определения sub-agent'ов для map-reduce |
| **Artifact chain** | ✅ `$ARTIFACTS_DIR` + `$nodeId.output` substitution |
| **Per-node provider/model** | ✅ `provider:` и `model:` на каждом node |
| **Streaming modes** | ✅ Stream / Batch (per-platform) + SSE + structured events |
| **AI Router** | ✅ AI Router: natural language → workflow/command matching |
| **Platform adapters** | ✅ Web UI, CLI, Telegram, Slack, Discord, GitHub webhooks |
| **Web UI / Dashboard** | ✅ Chat, Workflow Builder (drag-and-drop), Monitoring |
| **Audit Trail (JSONL)** | ✅ JSONL logs (`~/.archon/workspaces/.../logs/`) + DB events |
| **Cancel/Abandon workflow** | ✅ Cancel nodes + `/workflow cancel` |
| **Sandbox mode** | ✅ OS-level filesystem/network restrictions per node (Claude only) |
| **Context management** | ✅ `context: fresh |
| **Error classification** | ✅ FATAL / TRANSIENT / UNKNOWN с разными стратегиями retry |
| **Env vars injection** | ✅ Codebase-scoped env vars + per-provider env config |
| **Script nodes (deterministic)** | ✅ Bash nodes + Script nodes (Bun/Python) — без AI, бесплатно |

---

## 3. Оркестрационные возможности

### 3.1 🟡 DAG-based Workflow Execution (`packages/workflows/src/dag-executor.ts`)

**Что у них:** Workflows определяются как направленный ациклический граф (DAG) с узлами и зависимостями. Независимые узлы выполняются параллельно через `Promise.allSettled`. Топологические слои строятся алгоритмом Кана (Kahn's algorithm).

```yaml
nodes:
 - id: classify
 command: classify-issue
 output_format:
 type: object
 properties:
 type: { type: string, enum: [BUG, FEATURE] }

 - id: fix-bug
 command: fix-bug
 depends_on: [classify]
 when: "$classify.output.type == 'BUG'"

 - id: build-feature
 command: build-feature
 depends_on: [classify]
 when: "$classify.output.type == 'FEATURE'"

 - id: pr
 command: create-pr
 depends_on: [fix-bug, build-feature]
 trigger_rule: none_failed_min_one_success
```

**Архитектурный анализ:** DAG-модель принципиально отличается от линейных цепочек: независимые узлы выполняются параллельно через `Promise.allSettled`, а `trigger_rule` определяет, при каких условиях узел запускается после завершения зависимостей. Доступные значения `trigger_rule`: `all_success` (default), `none_failed`, `none_failed_min_one_success`, `any_success`, `all_done`. Условное ветвление через `when:`-выражения позволяет создавать ветки без отдельного condition-узла — логика встроена в декларацию каждого узла. Узлы с невыполненным `when:`-условием помечаются как `skipped` и не считаются `failed` для `trigger_rule` downstream-узлов — именно поэтому `none_failed_min_one_success` в примере корректно обрабатывает mutually exclusive ветки.

**Ограничения:** DAG не поддерживает циклы по определению — итеративная обработка вынесена в отдельный тип узла (Loop Node, см. §3.2). Отладка параллельных веток сложнее линейного выполнения: при падении одного из параллельных узлов `trigger_rule` определяет судьбу downstream-узлов, и не всегда тривиально установить, какой путь привёл к результату.

### 3.2 🟡 Loop Nodes — итеративное выполнение с сигналом завершения (`packages/workflows/src/dag-executor.ts`)

**Что у них:** Loop-узлы повторяют промпт до выполнения одного из условий:
1. LLM выдаёт `<promise>SIGNAL</promise>` (completion signal)
2. `until_bash` — shell-скрипт выходит с кодом 0
3. `max_iterations` достигнут (узел падает с ошибкой)

```yaml
- id: implement
 loop:
 prompt: |
 Read the PRD and implement the next unfinished story.
 Validate your changes before committing.
 When all stories are done: <promise>COMPLETE</promise>
 until: COMPLETE
 max_iterations: 15
 fresh_context: true # Каждый iteration = новая сессия
 until_bash: "bun run test" # Deterministic exit check
```

**Архитектурный анализ:** Паттерн итеративного выполнения с несколькими стратегиями завершения:
- `fresh_context: true` — каждая итерация начинается с чистой сессии AI-ассистента (агент восстанавливает state через `$ARTIFACTS_DIR`), что предотвращает контекстное загрязнение между итерациями
- `until_bash` — детерминированная проверка завершения через shell-скрипт (код возврата 0 = условие выполнено); позволяет прекратить цикл по объективному критерию, а не только по решению LLM
- `interactive: true` — пауза между итерациями для ввода пользователя (значение доступно через `$LOOP_USER_INPUT`)
- Двойная стратегия выхода: LLM-сигнал `<promise>SIGNAL</promise>` и bash-проверка оцениваются независимо — цикл завершается при первом истинном условии

**Ограничения:** При `fresh_context: true` агент теряет контекст предыдущих итераций и должен восстанавливать его из артефактов на диске — корректность восстановления целиком на совести промпта. `max_iterations` — жёсткий предел; при его достижении узел переходит в состояние `failed`, что блокирует downstream-узлы.

### 3.3 🟡 Human-in-the-Loop: Approval Nodes + Interactive Loops

**Что у них:** Два механизма для участия человека в workflow:

**Approval Node** — пауза для ревью с approve/reject:
```yaml
- id: review-gate
 approval:
 message: "Review the plan before proceeding."
 capture_response: true
 on_reject:
 prompt: "Revise based on feedback: $REJECTION_REASON"
 max_attempts: 3
 depends_on: [plan]
```

**Interactive Loop** — итеративный цикл с обратной связью:
```yaml
- id: refine
 loop:
 prompt: "User feedback: $LOOP_USER_INPUT. Apply and improve."
 until: APPROVED
 interactive: true
 gate_message: "Review and provide feedback."
```

**Архитектурный анализ:** Паттерн approval gate — узел приостанавливает выполнение DAG и ожидает явного решения человека. `capture_response: true` фиксирует комментарий ревьюера. `on_reject` с `max_attempts` создаёт цикл доработки: reject → доработка → повторный approval. Interactive loops предоставляют более гибкий механизм — цикл с `$LOOP_USER_INPUT` для итеративной обратной связи.

**Ограничения:** Approval-узлы требуют живого участия человека, что делает невозможным полностью автономное выполнение. `on_reject.max_attempts` при исчерпании переводит узел в `failed` — downstream-узлы не выполнятся. Без объявления `on_reject` reject немедленно завершает узел как `failed` без попытки доработки — промежуточного состояния между «полной доработкой» и «немедленным провалом» нет.

### 3.4 🟡 Isolation через Git Worktrees (`packages/isolation/`)

**Что у них:** Каждый workflow run автоматически создаёт git worktree — изолированную копию репозитория:
- Параллельные runs не конфликтуют
- Рабочая ветка остаётся чистой
- Проваленные runs не оставляют «мусор»

```typescript
// Упрощённо — полный интерфейс также включает readonly providerType
// и опциональный adopt? для workspace-переиспользования
interface IIsolationProvider {
 create(request: IsolationRequest): Promise<IsolatedEnvironment>;
 destroy(envId: string, options?: DestroyOptions): Promise<DestroyResult>;
 get(envId: string): Promise<IsolatedEnvironment | null>;
 list(codebaseId: string): Promise<IsolatedEnvironment[]>;
 healthCheck(envId: string): Promise<boolean>;
}
```

**Архитектурный анализ:** Паттерн isolation-through-branching: каждый workflow run получает собственную файловую систему через git worktree. Это гарантирует:
- параллельные runs не конфликтуют при записи файлов
- проваленные runs не оставляют изменений в рабочей ветке
- каждый run начинается от чистого HEAD целевой ветки

Интерфейс `IIsolationProvider` абстрагирует механизм изоляции — текущая реализация через git worktrees, но интерфейс допускает container/VM-based изоляцию (статус: planned).

**Ограничения:** Git worktree изолирует только файловую систему — два параллельных runs могут одновременно обращаться к одному API или shared resource (БД, кэш). Создание worktree стоит O(working tree size) по диску — git worktree разделяет объекты (`.git`) с основным репозиторием, дублируются только рабочие файлы. Для репозиториев с большой историей это существенно дешевле полной копии.

### 3.5 🟡 Error Classification (FATAL / TRANSIENT / UNKNOWN)

**Что у них:** Функция `classifyError()` в `executor-shared.ts` разбирает сообщение об ошибке и классифицирует его в один из трёх классов. Класс определяет стратегию retry:

| Class | Примеры | Retry-поведение |
|---|---|---|
| `FATAL` | Auth failure (401/403), permission denied, invalid API key | Retry **не выполняется** — узел немедленно переходит в `failed` |
| `TRANSIENT` | Process crash, rate limit (429), timeout, network error | Retry с exponential backoff в пределах `max_retries` узла |
| `UNKNOWN` | Нераспознанные сообщения об ошибках | Retry в пределах `max_retries` узла, но без гарантии исправимости |

Классификация основана на pattern matching по сообщению ошибки (ключевые слова, HTTP-коды). Это эвристика — нераспознанные ошибки попадают в `UNKNOWN`.

**Архитектурный анализ:** Разделение ошибок на исправимые и неисправимые — паттерн circuit breaker на уровне узла. Без классификации retry-механизм тратит попытки и время на заведомо неисправимые ошибки (401, 403), увеличивая общее время выполнения workflow.

**Ограничения:** Классификация основана на тексте ошибки, а не на формальном контракте — провайдер может вернуть нестандартное сообщение, и ошибка попадёт в `UNKNOWN`. Классы захардкожены в `classifyError()` — нет расширяемой системы правил.

### 3.6 🟡 DAG Resume on Failure — автоматическое восстановление

**Что у них:** Если workflow-выполнение падает, следующий запуск автоматически:
1. Ищет предыдущий failed run на том же рабочем пути
2. Загружает `node_completed` events
3. Пропускает уже завершённые узлы
4. Выполняет только failed и невыполненные

**Архитектурный анализ:** Паттерн checkpoint-and-resume: при перезапуске failed workflow executor загружает `node_completed` events из предыдущего run'а и помечает завершённые узлы как `skipped`. Каждый узел выполняется не более одного раза. Эффективен для длинных DAG (10+ узлов), где стоимость повторного выполнения высока.

**Ограничения:** Resume корректен только если output завершённых узлов доступен для `$nodeId.output`-подстановки в downstream-узлах. Если артефакты предыдущего run'а удалены (worktree уничтожен), resume невозможен — требуется полный перезапуск. Resume не реализует паттерн compensating transaction (saga): внешние побочные эффекты от уже выполненных узлов (созданные PR, отправленные уведомления, изменённые external state) не откатываются — повторное выполнение downstream-узлов может создать дубликаты. Корректность resume при наличии side effects целиком на ответственности автора workflow.

### 3.7 🟡 `$nodeId.output` — межузловая коммуникация через output substitution

**Что у них:** Выход каждого узла доступен downstream через `$nodeId.output`:

```yaml
- id: classify
 command: classify-issue
 output_format:
 type: object
 properties:
 type: { type: string, enum: [BUG, FEATURE] }

- id: implement
 command: implement
 depends_on: [classify]
 # В команде доступно: $classify.output.type
```

Поддерживается dot-notation для JSON-полей: `$classify.output.type`, `$classify.output.severity`.

**Архитектурный анализ:** Паттерн data binding между узлами: `$nodeId.output` подставляет JSON-output завершённого узла в промпт/конфигурацию downstream-узла. Dot-notation (`$classify.output.type`) обеспечивает доступ к вложенным полям. Это превращает DAG из чистой flow-control конструкции в data-flow граф — каждый узел может использовать результаты любого upstream-узла.

**Ограничения:** Подстановка работает только с JSON-output. Если узел не объявил `output_format`, `$nodeId.output` содержит сырой текст ответа — dot-notation может не сработать. Нет механизма агрегации output'ов массива узлов (например, `collect($nodes.*.output)`) — для map-reduce используется отдельный паттерн `agents:`. Циклические ссылки предотвращаются на этапе загрузки (cycle detection в `loader.ts`).

### 3.8 🟡 `output_format` — Structured JSON Output

**Что у них:** Узлы могут объявить JSON Schema, и AI-ассистент вернёт структурированный JSON:

```yaml
- id: classify
 command: classify-issue
 output_format:
 type: object
 properties:
 type: { type: string, enum: [BUG, FEATURE] }
 severity: { type: string, enum: [low, medium, high] }
 required: [type]
```

SDK-enforced на Claude/Codex; best-effort на Pi (schema добавляется в промпт, JSON извлекается из ответа).

**Архитектурный анализ:** Паттерн contract enforcement: JSON Schema на выходе узла гарантирует, что downstream-потребители (`when:`-выражения, `$nodeId.output`-подстановка) получают структурированные данные. Уровень гарантии зависит от провайдера: SDK Claude/Codex принудительно применяет схему (schema enforcement); Pi — best-effort (schema добавляется в промпт, JSON извлекается из текстового ответа regex'ом).

**Ограничения:** Best-effort enforcement означает, что на Pi-провайдере возможен невалидный JSON или несоответствие схеме — downstream-узлы получают `null` или падают с ошибкой парсинга. Schema validation — single-node scope: нет механизма проверки совместимости output-схемы upstream-узла с ожидаемым input downstream-узла на этапе загрузки воркфлоу.

### 3.9 🟡 Per-Node Provider/Model Override

**Что у них:** Каждый узел может использовать своего AI-провайдера и модель:

```yaml
nodes:
 - id: classify
 prompt: "Classify this issue"
 model: haiku # Быстрая дешёвая модель

 - id: deep-review
 command: thorough-review
 provider: claude
 model: opus # Мощная дорогая модель
```

**Архитектурный анализ:** Паттерн heterogeneous routing: каждый узел декларирует `provider:` и `model:`, что позволяет оптимизировать cost/quality ratio — быстрые дешёвые модели для классификации, мощные для кодогенерации. Provider registry (`IAgentProvider`) разрешает провайдера по ID в runtime.

**Ограничения:** Переключение провайдера между узлами одного workflow означает потерю контекстной сессии — каждый узел начинается с новой сессией соответствующего AI-ассистента. `maxBudgetUsd` поддерживается только Claude-провайдером; на Codex и Pi бюджетный контроль отсутствует. Нет fallback между провайдерами при недоступности — только `fallbackModel` внутри Claude.

---

## 4. Прочие возможности (вне оркестрации)

### 4.1 🟢 Subprocess-based AI Execution

Archon запускает AI-ассистенты (Claude Code, Codex CLI) как subprocess через SDK. Это принципиально другая модель: Archon не работает с LLM API напрямую — он делегирует полноценному AI-ассистенту с файловым доступом, инструментами, MCP.

Task-orchestrator работает через runner'ы (pi, codex), которые сами управляют LLM-взаимодействием. Нам не нужна subprocess-оркестрация — мы на уровень выше.

### 4.2 🟢 Platform Adapters (Telegram, Slack, Discord, GitHub)

Archon — мультиплатформенный чат-бот с webhooks, polling, SSE. Task-orchestrator — CLI-утилита для автоматического выполнения цепочек. Разные парадигмы.

### 4.3 🟢 Web UI / Dashboard / Drag-and-Drop Workflow Builder

Визуальный редактор воркфлоу и dashboard для мониторинга — отличный UX, но не входит в scope task-orchestrator. Наш целевой сценарий: YAML → CLI → результат.

### 4.4 🟢 SQLite/PostgreSQL Persistence

Archon хранит всё в 8 таблицах БД: сессии, сообщения, workflow runs, events, env vars. Для интерактивного мультиплатформенного чата это оправдано. In-memory + JSONL audit trail достаточно.

### 4.5 🟢 AI Router (Natural Language → Workflow Matching)

Archon использует AI-роутер: пользователь пишет «fix issue #42», роутер подбирает подходящий workflow/command. Мы используем явный выбор цепочки через CLI.

### 4.6 🟢 Session Transitions / Immutable Sessions

Архитектура сессий Archon (immutable sessions, parent_session_id, transition triggers: `first-message`, `plan-to-execute`) — сложная система для интерактивных диалогов. Для нашего одноразового pipeline — overengineering.

### 4.7 🟢 Per-Node Hooks (21 SDK event)

Хуки Claude SDK (PreToolUse, PostToolUse, Notification, Stop, SessionStart, SessionEnd, SubagentStart, SubagentStop, PreCompact, PermissionRequest, Elicitation, WorktreeCreate/Remove и др. — 21 событие) — мощный механизм контроля AI-ассистента на уровне tool call. Но это специфично для subprocess-based модели. Наши runner'ы управляют tool call сами.

### 4.8 🟢 Per-Node MCP Servers

MCP-конфигурация на уровне узла — полезно для интерактивного агента, но overhead для нашего pipeline. Если понадобится, добавим через runner.

### 4.9 🟢 Sandbox Mode

OS-level filesystem/network restrictions (Claude only) — полезно для безопасности при выполнении непроверенного кода. Для нашего controlled pipeline с проверенными runner'ами — пока не актуально.

---

## 5. Сводка по оркестрации

| Возможность | Статус в продукте | Описание |
| --- | --- | --- |
| DAG-based workflow (parallel execution) | 🟡 P3 | Значительное архитектурное изменение, нужна отдельная задача |
| Loop nodes с `until_bash` | 🟡 P2 | Усиление fix_iterations: детерминированная проверка завершения |
| Loop nodes с `fresh_context` | 🟡 P2 | Каждая итерация с чистым контекстом (агент читает state с диска) |
| Human-in-the-loop (approval gates) | 🟡 P3 | Для сценариев частично автономного выполнения |
| Error classification (FATAL/TRANSIENT/UNKNOWN) | 🟡 P2 | Умный retry: не тратить попытки на неисправимые ошибки |
| DAG Resume on Failure | 🟡 P3 | Resume на уровне цепочки при сбое |
| `$nodeId.output` substitution | 🟡 P2 | Явная передача данных между шагами |
| `output_format` (structured JSON) | 🟡 P3 | Предсказуемый формат вывода для quality gates |
| Per-node provider/model override | 🟡 P3 | Оптимизация: дешёвая модель для простых шагов |
| Isolation (git worktrees) | 🟡 P3 | Для параллельного выполнения цепочек |
| Subprocess AI execution | 🟢 — | Разный уровень абстракции |
| Platform adapters (Telegram, Slack, etc.) | 🟢 — | Разная парадигма (chat vs. pipeline) |
| Web UI / Dashboard | 🟢 — | Не scope task-orchestrator |
| SQLite/PostgreSQL persistence | 🟢 — | In-memory + JSONL достаточно |
| AI Router | 🟢 — | Явный выбор цепочки через CLI |
| Per-node hooks (21 SDK event) | 🟢 — | Специфично для subprocess модели |
| Per-node MCP servers | 🟢 — | Если нужно — через runner |
| Session transitions | 🟢 — | Overengineering для pipeline |

---

## 6. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории Archon:

- [`packages/workflows/src/dag-executor.ts`](https://github.com/coleam00/Archon/blob/main/packages/workflows/src/dag-executor.ts) — DAG Executor: topological layers, parallel execution, node retry, cancel/abort, loop node dispatch, idle timeout
- [`packages/workflows/src/schemas/dag-node.ts`](https://github.com/coleam00/Archon/blob/main/packages/workflows/src/schemas/dag-node.ts) — Zod-схемы: DagNode discriminated union, BashNode, LoopNode, ApprovalNode, CancelNode, ScriptNode
- [`packages/workflows/src/condition-evaluator.ts`](https://github.com/coleam00/Archon/blob/main/packages/workflows/src/condition-evaluator.ts) — `when:` condition evaluator: string/numeric operators, compound expressions (`&&`, `||`)
- [`packages/providers/src/types.ts`](https://github.com/coleam00/Archon/blob/main/packages/providers/src/types.ts) — IAgentProvider, MessageChunk, ProviderCapabilities, NodeConfig, SendQueryOptions
- [`packages/providers/src/registry.ts`](https://github.com/coleam00/Archon/blob/main/packages/providers/src/registry.ts) — Provider registry: registerBuiltinProviders, registerCommunityProviders
- [`packages/isolation/src/providers/worktree.ts`](https://github.com/coleam00/Archon/blob/main/packages/isolation/src/providers/worktree.ts) — WorktreeProvider: create/destroy/adopt git worktrees
- [`packages/core/src/orchestrator/orchestrator.ts`](https://github.com/coleam00/Archon/blob/main/packages/core/src/orchestrator/orchestrator.ts) — Orchestrator: message routing, session lifecycle, streaming, context injection
- [`packages/core/src/state/session-transitions.ts`](https://github.com/coleam00/Archon/blob/main/packages/core/src/state/session-transitions.ts) — Session state machine: TransitionTrigger types, plan-to-execute detection
- [`packages/workflows/src/executor-shared.ts`](https://github.com/coleam00/Archon/blob/main/packages/workflows/src/executor-shared.ts) — classifyError (FATAL/TRANSIENT/UNKNOWN), detectCompletionSignal, loadCommandPrompt, variable substitution
- [`packages/workflows/src/loader.ts`](https://github.com/coleam00/Archon/blob/main/packages/workflows/src/loader.ts) — Workflow loader: YAML parsing, cycle detection, validation
- [`packages/workflows/src/router.ts`](https://github.com/coleam00/Archon/blob/main/packages/workflows/src/router.ts) — AI Router: natural language → workflow/command matching
- [`README.md`](https://github.com/coleam00/Archon/blob/main/README.md) — Обзор проекта, архитектура, quick start, примеры workflows

📚 **Источники:**
1. [github.com/coleam00/Archon](https://github.com/coleam00/Archon) — репозиторий проекта
2. [archon.diy](https://archon.diy) — документация (The Book of Archon, Guides, API Reference)
3. [archon.diy/docs/reference/architecture](https://archon.diy/docs/reference/architecture/) — полная архитектура, интерфейсы, data flow
4. [archon.diy/docs/guides/authoring-workflows](https://archon.diy/docs/guides/authoring-workflows/) — справочник по YAML workflow DSL
5. [archon.diy/docs/guides/loop-nodes](https://archon.diy/docs/guides/loop-nodes/) — loop nodes: итеративное выполнение с completion signals
