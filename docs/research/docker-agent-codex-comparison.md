# Исследование: OpenAI Codex CLI + Docker Agent — контейнеризованный sandboxing (проприетарный + open-source)

> **Проект:** [github.com/openai/codex](https://github.com/openai/codex) (CLI), [chatgpt.com/codex](https://chatgpt.com/codex) (Web)
> **Дата анализа:** 2026-04-22
> **Язык:** Rust (codex-rs), TypeScript (codex-cli legacy)
> **Лицензия:** Apache-2.0 (CLI), проприетарный (Codex Web)
> **Аналитик:** Технический писатель (Гермиона)

---

## 1. Обзор проекта

**OpenAI Codex** — линейка AI-coding продуктов от OpenAI, включающая три форм-фактора:
1. **Codex CLI** (`@openai/codex`) — open-source CLI-агент (Rust), работает локально с OS-level sandboxing;
2. **Codex Web** (`chatg.com/codex`) — проприетарный cloud-агент в sandboxed compute environment;
3. **Codex IDE** — интеграция в VS Code / Cursor / Windsurf.

Codex CLI — наиболее технически продвинутый CLI-агент из исследованных: Rust-ядро, многоуровневая система sandboxing (Seatbelt / Landlock / Bubblewrap / Docker containers + iptables firewall), иерархические multi-agent'ы с depth limit, Guardian (LLM-based safety reviewer), exec policy (rules-based command filtering), MCP client/server, auto-compact, SKILL.md.

**Docker Agent** (контейнеризованная модель выполнения) — подход к изоляции AI-агента через Docker-контейнер с:
- iptables/ipset firewall (whitelist доменов);
- split filesystem permissions (read/write/none per path);
- non-root user execution;
- auto-cleanup контейнера при выходе.

Codex CLI **не является** фреймворком оркестрации цепочек агентов. Это **одноагентный CLI-инструмент** с возможностью порождения sub-agent'ов. В отличие от task-orchestrator, Codex не поддерживает декларативные цепочки шагов (chains), retry-механизмы с exponential backoff, circuit breaker, budget control с лимитами или quality gates. Однако его sandboxing-архитектура, Guardian system, exec policy и multi-agent model представляют значительный интерес.

### Архитектура

Codex CLI — open-source (Apache-2.0) продукт с Rust-ядром. Архитектура восстановлена по исходному коду (`codex-rs/`), официальной документации и конфигурационным файлам.

```
codex (binary)                     CLI entry point (Rust / Ratatui TUI)
  codex-rs/core/                   Business logic
    agent/                          Multi-agent system
      control.rs                   AgentControl — spawn/terminate sub-agents
      mailbox.rs                   Mailbox — async message passing (Sender/Receiver)
      registry.rs                  Registry — depth tracking, spawn limits
      role.rs                      Roles — agent type system (default, custom)
    session/                        Session management
      session.rs                   Core session loop (LLM → tool → observation → ...)
      turn.rs                      Turn management (one LLM call = one turn)
      turn_context.rs              Per-turn context (config, environment, services)
      rollout_reconstruction.rs    Session replay from persisted rollout files
      handlers.rs                  Tool call handlers dispatch
    codex_delegate.rs               Sub-agent delegation (spawn, IO channels, approval routing)
    codex_thread.rs                 Thread management for interactive sub-agents
    guardian/                       LLM-based safety reviewer
      policy.md                    Risk taxonomy (data exfiltration, credential probing, etc.)
      review.rs                    Review logic (risk assessment → allow/deny/escalate)
      review_session.rs            Review session management
    exec_policy.rs                  Rules-based command execution policy
    sandboxing/                     OS-level sandboxing adapter
    config/                         Configuration system
      permissions.rs               Filesystem/network permission compilation
      schema.rs                    Config schema (config.toml → typed config)
    compact.rs                      Auto-compaction (context overflow → LLM summarization)
    client.rs                       OpenAI API client (Responses API)
    tools/                          Built-in tools
      handlers/
        apply_patch.rs             File editing (structured patch)
        multi_agents_v2/           Multi-agent tools (spawn/send_message/wait/close_agent/list_agents)
        mcp.rs                     MCP tool calls
        plan.rs                    Plan mode (analyze without executing)
        list_dir.rs                Directory listing
        js_repl.rs                 JavaScript REPL
        agent_jobs.rs              Background agent jobs
    context/                        Context management
      agents_md.rs                 AGENTS.md hierarchical discovery
      context_manager/             Conversation history management
  codex-rs/exec/                    Headless CLI (codex exec)
  codex-rs/tui/                     Full-screen TUI (Ratatui)
  codex-rs/cli/                     CLI multitool (subcommands: exec, sandbox, mcp-server)
  codex-cli/Dockerfile              Docker container for sandboxed execution
  codex-cli/scripts/
    run_in_container.sh            Docker run with iptables firewall
    init_firewall.sh               iptables/ipset-based network whitelist
```

### Ключевые характеристики

| Характеристика | Значение |
|---|---|
| **Тип** | CLI-агент + cloud-агент, одноагентный (с hierarchical sub-agent'ами) |
| **Модель выполнения** | Agent loop (LLM → tool call → observation → LLM → ...) |
| **State management** | SQLite + rollout files (persistent), auto-compact при переполнении |
| **Провайдер** | OpenAI (GPT-4.1, o-series), Ollama (local), custom providers |
| **Расширяемость** | MCP client/server, SKILL.md, AGENTS.md, exec policy rules, custom agent roles, apps (connectors) |
| **Интерфейс** | Interactive TUI + headless (`codex exec`) + MCP server + IDE + Web |
| **Платформы** | macOS, Linux, Windows (npm / brew / binary) |
| **Sandboxing** | Seatbelt (macOS), Landlock/Bubblewrap (Linux), restricted token (Windows), Docker + iptables firewall |
| **Лицензия** | Apache-2.0 (CLI), проприетарный (Web) |

### Основные компоненты

| Компонент | Назначение |
|---|---|
| Agent loop | Ядро: итеративный вызов LLM с инструментами, до естественного завершения или лимита итераций |
| Sandbox (OS-level) | Seatbelt (macOS), Landlock/Bubblewrap (Linux), restricted token (Windows) — filesystem + network isolation |
| Sandbox (Docker) | Docker container + iptables/ipset firewall — whitelist доменов, auto-cleanup |
| Guardian | LLM-based safety reviewer — risk taxonomy (exfiltration, credential probing, destructive actions, security weakening) → allow/deny/escalate |
| Exec policy | Rules-based command filtering — `.rules` файлы, banned prefixes, safe command detection |
| Multi-agent (v2) | Hierarchical sub-agents — spawn/send_message/wait/close_agent/list_agents с depth limit |
| Compaction | Auto-compact при context overflow — inline LLM summarization или remote compaction task |
| MCP client/server | MCP для расширения инструментов; Codex может быть MCP tool для других агентов |
| Skills (SKILL.md) | Bundled + custom skills — формализованные каталоги с инструкциями и скриптами |
| AGENTS.md | Hierarchical context discovery — глубокий файл перекрывает верхний |
| Session persistence | SQLite state DB + rollout files (JSONL) — resumable sessions, conversation history |
| Plan mode | Анализ без выполнения (read-only tool calls) — для exploration/planning |

---

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | Task Orchestrator | Codex CLI + Docker Agent | Статус |
|---|---|---|---|
| **Цепочки шагов (chains)** | ✅ YAML chains, статические и динамические | ❌ Нет. Agent loop — один непрерывный поток | ✅ У нас есть |
| **Retry с backoff** | ✅ RetryingAgentRunner | ⚠️ Базовый retry на уровне API (429/500) | ✅ У нас есть |
| **Circuit Breaker** | ✅ CircuitBreakerAgentRunner | ❌ Нет | ✅ У нас есть |
| **Quality Gates** | ✅ Shell-команды как проверки | ❌ Нет (LLM сам оценивает результат) | ✅ У нас есть |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based лимиты) | ⚠️ ChatGPT plan limits, но без программных лимитов | ✅ У нас лучше |
| **Итерационные циклы (fix_iterations)** | ✅ Группа шагов с max_iterations | ❌ Нет явных итерационных циклов | ✅ У нас есть |
| **Fallback routing** | ✅ Per-step fallback runner | ❌ Нет (единственный провайдер — OpenAI, но поддержка Ollama/custom) | ✅ У нас есть |
| **Audit Trail (JSONL)** | ✅ JsonlAuditLogger | ✅ Rollout files (JSONL) + SQLite state | ✅ Паритет |
| **Ролевые промпты** | ✅ .md файлы (18+ ролей) | ✅ AGENTS.md + agent roles (TOML) | ✅ Паритет |
| **Multiple runners** | ✅ Pi + Codex (через interface) | ⚠️ OpenAI + Ollama + custom providers | ✅ У нас лучше |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❌ Monorepo crate structure | ✅ У нас есть |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Прямой вызов | ✅ У нас есть |
| **YAML-конфигурация** | ✅ Chains + roles в YAML | ✅ config.toml (TOML) | ✅ Паритет |
| **Sandboxing (OS-level)** | ❌ Нет | ✅ Seatbelt/Landlock/Bubblewrap/restricted token — per-platform | 🟡 Интересно |
| **Sandboxing (Docker)** | ❌ Нет | ✅ Docker + iptables firewall + domain whitelist + auto-cleanup | 🟡 Интересно |
| **Guardian (safety reviewer)** | ❌ Нет | ✅ LLM-based risk assessment — data exfiltration, credential probing, destructive actions | 🟡 Интересно |
| **Exec policy (rules)** | ❌ Нет | ✅ .rules файлы — banned prefixes, safe command detection, per-path restrictions | 🟡 Интересно |
| **Multi-agent (hierarchical)** | ❌ Нет | ✅ spawn/send_message/wait/close_agent/list_agents + depth limit + mailbox | 🟡 Интересно |
| **Network isolation** | ❌ Нет | ✅ iptables/ipset firewall — whitelist доменов, DNS-only, DROP default | 🟡 Интересно |
| **Split filesystem permissions** | ❌ Нет | ✅ Per-path read/write/none — granular filesystem access control | 🟡 Интересно |
| **Compaction (auto-compact)** | ❌ Нет | ✅ LLM summarization при context overflow (inline + remote) | 🟡 Позже |
| **MCP server mode** | ❌ Нет | ✅ `codex mcp-server` — Codex как MCP tool для других агентов | 🟡 Интересно |
| **MCP client** | ❌ Нет | ✅ MCP servers в config.toml, parallel tool calls support | 🟡 Позже |
| **SKILL.md** | ✅ Есть в agent skills | ✅ Bundled + custom skills | ✅ Паритет |
| **Session persistence** | ❌ Нет (in-memory) | ✅ SQLite + rollout files (JSONL) — resumable | 🟡 Позже |
| **Plan mode** | ❌ Нет | ✅ Read-only exploration без выполнения | 🟡 Интересно |
| **Headless mode** | ✅ CLI Symfony Console | ✅ `codex exec` (stdin/stdout) | ✅ Паритет |
| **Non-interactive CI mode** | ✅ CLI pipeline | ✅ `codex exec --full-auto` — для CI/CD | ✅ Паритет |
| **Apps (connectors)** | ❌ Нет | ✅ ChatGPT connectors через `$` в composer | 🟢 Не берём |
| **IDE integration** | ❌ Нет | ✅ VS Code / Cursor / Windsurf extensions | 🟢 Не берём |
| **Realtime audio** | ❌ Нет | ✅ Experimental realtime audio mode | 🟢 Не берём |
| **Image generation** | ❌ Нет | ✅ Built-in image generation context | 🟢 Не берём |

---

## 3. Что полезно взять и почему

### 3.1 🟡 Multi-layer Sandboxing — OS-level + Docker + Network isolation

**Что у них:** Codex CLI реализует трёхуровневую систему sandboxing:

**Уровень 1: OS-level sandboxing**
```
macOS  → Seatbelt (/usr/bin/sandbox-exec)
Linux  → Bubblewrap (bwrap) + Landlock
Windows → Restricted token + elevated backend
```

Конфигурация через `--sandbox` flag:
- `read-only` — только чтение filesystem, нет network
- `workspace-write` — запись в workspace, network через proxy
- `danger-full-access` — без ограничений (для trusted environments)

**Уровень 2: Split filesystem permissions**
```toml
[permissions.default.filesystem]
entries = [
  { path = "/workspace", access = "write" },
  { path = "/workspace/.env", access = "none" },
  { path = "/workspace/secrets", access = "read" },
]
```

**Уровень 3: Docker + iptables firewall**
```
docker run \
  --cap-add=NET_ADMIN \
  -v "$WORK_DIR:/app$WORK_DIR" \
  codex sleep infinity
→ iptables whitelist (api.openai.com, github.com, ...)
→ DROP default policy
→ auto-cleanup при выходе
```

**Почему нам интересно:** Для autonomous CI/CD pipeline — критически важная безопасность. Task-orchestrator запускает shell-команды без ограничений. Multi-layer sandboxing позволяет:
- Изолировать выполнение shell-команд от host-системы
- Ограничить network access (только к разрешённым API endpoints)
- Контролировать filesystem access (запись только в workspace)
- Запускать pipeline в Docker container с auto-cleanup

**Отличие:** У нас нет sandboxing вообще. Shell-команды выполняются напрямую на host.

---

### 3.2 🟡 Guardian System — LLM-based safety reviewer

**Что у них:** Codex CLI включает Guardian — LLM-based систему оценки рисков для каждого tool call:

```
Agent → tool call (shell command / file write)
  → Guardian review (risk assessment)
    → Risk taxonomy:
      • Data Exfiltration (high/critical): отправка данных наружу
      • Credential Probing (high): извлечение credentials
      • Persistent Security Weakening (high/critical): ослабление безопасности
      • Destructive Actions (high/critical): удаление данных,破坏 production
      • Low-Risk Actions (low/medium): обычные операции
    → Decision: allow / deny / escalate to user
```

Ключевые правила Guardian:
- **Outcome rule:** deny actions that disclose secrets/credentials to untrusted destinations
- **Context awareness:** различает local (user machine) vs. production environments
- **Granularity:** отличает `rm -rf` конкретного файла (low/medium) от broad destructive action (high/critical)
- **Override:** user authorization может повысить разрешённый risk level

**Почему нам интересно:** Для autonomous execution в CI/CD — LLM-based «quality gate» на уровне каждого shell-вызова. Это дополняет наши shell-based quality gates: если step выполняет потенциально опасную команду, Guardian может оценить risk и заблокировать. Пример:
- Quality gate: проверяет результат команды (тесты прошли/упали)
- Guardian: проверяет безопасность команды до выполнения (не удалит ли production)

**Отличие:** У нас quality gates — post-execution проверки. Guardian — pre-execution safety review.

---

### 3.3 🟡 Exec Policy — rules-based command filtering

**Что у них:** Codex CLI использует `.rules` файлы для декларативного управления разрешёнными командами:

```rules
# default.rules
allow git status
allow git diff
allow git log
allow cargo test
allow cargo build
deny rm -rf /
deny curl *
deny wget *
```

Дополнительные механизмы:
- **Banned prefixes:** `bash -c`, `python -c`, `sh -c` — блокируются по умолчанию
- **Safe command detection:** `is_known_safe_command()` — whitelist безопасных команд
- **Dangerous command detection:** `command_might_be_dangerous()` — эвристика для рискованных команд
- **Network rules:** per-command network access control
- **Per-path restrictions:** команды ограничены определёнными директориями

**Почему нам интересно:** Декларативные rules для ограничения shell-команд в цепочках. Сейчас task-orchestrator выполняет любую shell-команду без ограничений. Exec policy позволяет:
- Определить разрешённые команды для каждого типа шага
- Заблокировать опасные команды (`rm -rf /`, `curl` к внешним endpoints)
- Дифференцировать политики по environment (dev vs. CI vs. production)

**Отличие:** У нас quality gates — post-execution проверки. Exec policy — pre-execution filtering.

---

### 3.4 🟡 Multi-Agent v2 — hierarchical sub-agents с depth limit

**Что у них:** Codex CLI поддерживает порождение sub-agent'ов через tool calls:

```
Codex (main agent)
  ├─ spawn("Investigate auth module") → sub-agent с изолированным контекстом
  │    ├─ send_message("Found issue in auth.rs")
  │    └─ close_agent()
  ├─ spawn("Write unit tests") → sub-agent
  │    └─ send_message("3 tests written")
  └─ spawn("Review code") → sub-agent
       └─ send_message("LGTM")
```

**Механика:**
- **Tools:** `spawn`, `send_message`, `wait`, `close_agent`, `list_agents`, `message_tool`
- **Depth limit:** `agent_max_depth` — ограничение вложенности (agent → sub-agent → sub-sub-agent)
- **Mailbox pattern:** async message passing через `Sender/Receiver` channels
- **Isolated context:** sub-agent получает собственный context window + config
- **Fork modes:** `FullHistory` (наследует историю) или `Clean` (чистый старт)
- **Role system:** `agent_type` — назначение роли sub-agent'у (default, custom)
- **Approval routing:** sub-agent approval requests направляются parent session

**Почему нам интересно:** Для dynamic chains — возможность делегировать подзадачу отдельному агенту с чистым контекстом. Sub-agent pattern позволяет:
- Изолировать контекст подзадачи (меньше token usage)
- Выполнять подзадачи параллельно
- Ограничивать вложенность (depth limit) — защита от runaway spawning
- Назначать разные роли/модели разным sub-agent'ам

**Отличие:** У нас каждый шаг — вызов runner'а с payload. Sub-agent — «chain внутри chain» с собственным контекстом, mailbox communication и depth limit.

---

### 3.5 🟡 Network Isolation — iptables/ipset firewall для AI-агентов

**Что у них:** Codex CLI при запуске в Docker container инициализирует iptables firewall:

```bash
# 1. Flush existing rules
iptables -F && iptables -X

# 2. Allow DNS and localhost
iptables -A OUTPUT -p udp --dport 53 -j ACCEPT
iptables -A INPUT -i lo -j ACCEPT

# 3. Create ipset with allowed domains
ipset create allowed-domains hash:net
for domain in "${ALLOWED_DOMAINS[@]}"; do
  ips=$(dig +short A "$domain")
  ipset add allowed-domains "$ips"
done

# 4. Default DROP
iptables -P INPUT DROP
iptables -P OUTPUT DROP
iptables -P FORWARD DROP

# 5. Allow only whitelisted domains
iptables -A OUTPUT -m set --match-set allowed-domains dst -j ACCEPT

# 6. Verify: example.com blocked, api.openai.com allowed
```

**Почему нам интересно:** Для autonomous pipeline — критически важно ограничить network access. Если AI-агент может выполнять shell-команды, он потенциально может:
- Скачать и выполнить вредоносный код
- Отправить данные на внешний сервер
- Получить инструкции от третьих лиц

Network isolation через iptables — простой и надёжный механизм: по умолчанию блокировать всё, разрешать только whitelist доменов (API endpoints runner'ов, git servers).

**Отличие:** У нас нет network isolation. Shell-команды в pipeline выполняются с полным network access.

---

### 3.6 🟡 Auto-Compaction — LLM summarization при context overflow

**Что у них:** Codex CLI автоматически сжимает контекст при приближении к context window:

```rust
// Compaction strategy:
// 1. Detect context overflow (token count approaching limit)
// 2. Summarize conversation history via LLM call
// 3. Replace history with summary + last user message
// 4. Continue agent loop with compacted context
```

**Механика:**
- **Trigger:** automatic при приближении к context window limit
- **Inline compaction:** LLM summarization в текущем agent loop
- **Remote compaction:** отдельный API call для summarization (если provider поддерживает)
- **Preservation:** сохраняет initial context + summary + последний user message
- **Analytics:** отслеживание compaction events (trigger, strategy, status)

**Почему нам интересно:** Для длинных цепочек с большим количеством шагов — context overflow реальная проблема. Auto-compaction позволяет:
- Продолжать выполнение даже при длинной истории
- Сохранять ключевой контекст через LLM summarization
- Не терять последние instructions при сжатии

**Отличие:** У нас нет context management. Payload передаётся между шагами без сжатия.

---

### 3.7 🟡 Plan Mode — read-only exploration перед выполнением

**Что у них:** Codex CLI поддерживает Plan mode — agent анализирует задачу без выполнения destructive actions:

```
codex --plan "Refactor authentication module"
→ Agent читает файлы, анализирует код, предлагает план
→ Не выполняет shell-команды, не пишет файлы
→ Пользователь подтверждает → Agent выполняет
```

**Почему нам интересно:** Для dynamic chains — возможность preview перед execution. В task-orchestrator динамическая цепочка генерируется и выполняется сразу. Plan mode позволяет:
- Сгенерировать цепочку → показать пользователю → подтвердить → выполнить
- Использовать дешёвую модель для planning, дорогую для execution
- Разделять exploration и execution phases

**Отличие:** У нас нет preview/plan phase для dynamic chains.

---

## 4. Что НЕ берём и почему

### 4.1 🟢 Seatbelt / Landlock / Bubblewrap — OS-specific sandboxing

Codex CLI использует platform-specific OS-level sandboxing (macOS Seatbelt, Linux Landlock/Bubblewrap, Windows restricted token). Эти механизмы:
- Требуют platform-specific implementation
- Не применимы к PHP/Symfony
- Docker container — более универсальный подход для изоляции

### 4.2 🟢 Codex Web (cloud-based agent)

Codex Web — проприетарный cloud SaaS от OpenAI (через ChatGPT). Полностью managed environment: пользователь не контролирует execution, sandboxing, или модель. task-orchestrator — self-hosted pipeline. Разные парадигмы.

### 4.3 🟢 Apps (ChatGPT connectors)

Codex CLI интегрируется с ChatGPT connectors через `$` в composer. Это проприетарная OpenAI-экосистема. Не применимо к task-orchestrator.

### 4.4 🟢 IDE integration (VS Code / Cursor / Windsurf)

Codex IDE extension — интеграция в code editors. task-orchestrator — CLI/Symfony pipeline. Разные форм-факторы.

### 4.5 🟢 Realtime audio mode

Codex поддерживает experimental realtime audio (voice interaction). Не относится к pipeline orchestration.

### 4.6 🟢 Image generation context

Codex имеет встроенный контекст для генерации изображений. Не относится к code orchestration.

### 4.7 🟢 MCP server mode (`codex mcp-server`)

Codex может выступать как MCP server — предоставлять свои возможности другим MCP clients. Интересно для интеграции, но task-orchestrator не является MCP client. Потенциально возможно в будущем, но не приоритет.

---

## 5. Сводка рекомендаций

| Фича | Приоритет | Обоснование |
|---|---|---|
| Chain orchestration | ✅ Уже есть | Core-функциональность task-orchestrator |
| Retry + Circuit Breaker | ✅ Уже есть | Устойчивость при сбоях |
| Quality Gates | ✅ Уже есть | Автоматическая проверка кода |
| Budget control | ✅ Уже есть | Предотвращение runaway spending |
| Fix iterations | ✅ Уже есть | Closed-loop цикл разработки |
| SKILL.md | ✅ Уже есть | Формализация agent skills |
| Docker-based sandboxing | 🟡 P2 | Контейнеризация pipeline для CI/CD: изоляция filesystem + network |
| Network isolation (iptables) | 🟡 P2 | Whitelist доменов для autonomous pipeline — блокировка data exfiltration |
| Guardian (LLM safety reviewer) | 🟡 P2 | Pre-execution safety review для shell-команд — дополнение к post-execution quality gates |
| Exec policy (rules) | 🟡 P2 | Декларативные rules для разрешённых/запрещённых команд |
| Split filesystem permissions | 🟡 P2 | Per-path read/write/none — granular control для autonomous execution |
| Multi-agent (hierarchical) | 🟡 P2 | Для dynamic chains: sub-agents с изолированным контекстом и depth limit |
| Auto-compaction | 🟡 P3 | LLM summarization при context overflow — для длинных цепочек |
| Plan mode | 🟡 P3 | Preview dynamic chains перед выполнением — для human-in-the-loop |
| Depth limit (agent spawning) | 🟡 P2 | Защита от runaway sub-agent spawning — `agent_max_depth` |
| Mailbox pattern (async messages) | 🟡 P3 | Async communication между agent/sub-agents — для параллельного выполнения |
| MCP client | 🟡 P3 | Расширение runner capabilities через external tool servers |
| Session persistence (SQLite) | 🟡 P3 | Resumable sessions — для долгих pipelines с возможностью прерывания |
| Codex Web / IDE | 🟢 — | Проприетарный cloud, другой форм-фактор |
| OS-specific sandboxing | 🟢 — | Platform-specific, не применимо к PHP |
| Apps / connectors | 🟢 — | Проприетарная OpenAI-экосистема |
| Realtime audio | 🟢 — | Не относится к pipeline |
| Image generation | 🟢 — | Не относится к pipeline |

---

## 6. Указатель источников для деталей

- [GitHub: openai/codex](https://github.com/openai/codex) — исходный код (Apache-2.0), README, CHANGELOG
- [OpenAI Developers: Codex Documentation](https://developers.openai.com/codex) — официальная документация: config, auth, features
- [OpenAI Developers: Codex Config Reference](https://developers.openai.com/codex/config-reference) — полная конфигурация config.toml
- [GitHub: codex-rs/core/README.md](https://github.com/openai/codex/blob/main/codex-rs/core/README.md) — sandboxing architecture: Seatbelt, Landlock, Bubblewrap, Windows
- [GitHub: codex-rs/core/src/guardian/policy.md](https://github.com/openai/codex/blob/main/codex-rs/core/src/guardian/policy.md) — Guardian risk taxonomy: data exfiltration, credential probing, destructive actions

---

📚 **Источники:**
1. [github.com/openai/codex](https://github.com/openai/codex) — исходный код Codex CLI (Rust), Docker container, sandboxing
2. [developers.openai.com/codex](https://developers.openai.com/codex) — официальная документация Codex
3. [developers.openai.com/codex/config-reference](https://developers.openai.com/codex/config-reference) — полная конфигурация config.toml
4. [github.com/openai/codex/blob/main/codex-rs/core/README.md](https://github.com/openai/codex/blob/main/codex-rs/core/README.md) — sandboxing architecture per-platform
5. [github.com/openai/codex/blob/main/codex-rs/core/src/guardian/policy.md](https://github.com/openai/codex/blob/main/codex-rs/core/src/guardian/policy.md) — Guardian risk taxonomy
