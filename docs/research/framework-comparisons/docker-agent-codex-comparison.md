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
2. **Codex Web** (`chatgpt.com/codex`) — проприетарный cloud-агент в sandboxed compute environment;
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
codex (binary) CLI entry point (Rust / Ratatui TUI)
 codex-rs/core/ Business logic
 agent/ Multi-agent system
 control.rs AgentControl — spawn/terminate sub-agents
 mailbox.rs Mailbox — async message passing (Sender/Receiver)
 registry.rs Registry — depth tracking, spawn limits
 role.rs Roles — agent type system (default, custom)
 session/ Session management
 session.rs Core session loop (LLM → tool → observation → ...)
 turn.rs Turn management (one LLM call = one turn)
 turn_context.rs Per-turn context (config, environment, services)
 rollout_reconstruction.rs Session replay from persisted rollout files
 handlers.rs Tool call handlers dispatch
 codex_delegate.rs Sub-agent delegation (spawn, IO channels, approval routing)
 codex_thread.rs Thread management for interactive sub-agents
 guardian/ LLM-based safety reviewer
 policy.md Risk taxonomy (data exfiltration, credential probing, etc.)
 review.rs Review logic (risk assessment → allow/deny/escalate)
 review_session.rs Review session management
 exec_policy.rs Rules-based command execution policy
 sandboxing/ OS-level sandboxing adapter
 config/ Configuration system
 permissions.rs Filesystem/network permission compilation
 schema.rs Config schema (config.toml → typed config)
 compact.rs Auto-compaction (context overflow → LLM summarization)
 client.rs OpenAI API client (Responses API)
 tools/ Built-in tools
 handlers/
 apply_patch.rs File editing (structured patch)
 multi_agents_v2/ Multi-agent tools (spawn/send_message/wait/close_agent/list_agents)
 mcp.rs MCP tool calls
 plan.rs Plan mode (analyze without executing)
 list_dir.rs Directory listing
 js_repl.rs JavaScript REPL
 agent_jobs.rs Background agent jobs
 context/ Context assembly (prompt instructions, permission prompts, environment)
 agents_md.rs AGENTS.md hierarchical discovery
 context_manager/ Conversation history management
 history.rs History entry storage/lookup
 updates.rs History update logic
 codex-rs/exec/ Headless CLI (codex exec)
 codex-rs/tui/ Full-screen TUI (Ratatui)
 codex-rs/cli/ CLI multitool (subcommands: exec, review, sandbox, mcp, plugin, mcp-server, app, apply, resume, fork, …)
 codex-rs/hooks/ Hook engine (pre/post tool use, session start/stop)
 codex-rs/memories/ Long-term memory (citations, storage, compaction)
 codex-rs/plugins/ Plugin system (marketplace, dynamic tool loading)
 codex-rs/network-proxy/ Network proxy for outbound traffic control
 codex-rs/process-hardening/ Process-level security hardening
 codex-cli/Dockerfile Docker container for sandboxed execution
 codex-cli/scripts/
 run_in_container.sh Docker run with iptables firewall
 init_firewall.sh iptables/ipset-based network whitelist
```

### Ключевые характеристики

| Характеристика | Значение |
| --- | --- |
| **Тип** | CLI-агент + cloud-агент, одноагентный (с hierarchical sub-agent'ами) |
| **Модель выполнения** | Agent loop (LLM → tool call → observation → LLM → ...) |
| **State management** | SQLite + rollout files (persistent), auto-compact при переполнении, memories (long-term) |
| **Провайдер** | OpenAI (GPT-4.1, o-series), Ollama (local), custom providers, Amazon Bedrock |
| **Расширяемость** | MCP client/server, SKILL.md, AGENTS.md, exec policy rules, custom agent roles, plugins, apps (connectors), hooks |
| **Интерфейс** | Interactive TUI + headless (`codex exec`) + MCP server + desktop app (`codex app`) + IDE + Web |
| **Платформы** | macOS, Linux, Windows (npm / brew / binary) |
| **Sandboxing** | Seatbelt (macOS), Landlock/Bubblewrap (Linux), restricted token (Windows), Docker + iptables firewall, external-sandbox mode |
| **Лицензия** | Apache-2.0 (CLI), проприетарный (Web) |

### Основные компоненты

| Компонент | Назначение |
| --- | --- |
| Agent loop | Ядро: итеративный вызов LLM с инструментами, до естественного завершения или лимита итераций |
| Sandbox (OS-level) | Seatbelt (macOS), Landlock/Bubblewrap (Linux), restricted token (Windows) — filesystem + network isolation |
| Sandbox (Docker) | Docker container + iptables/ipset firewall — whitelist доменов, auto-cleanup |
| Sandbox (external) | `external-sandbox` — когда процесс уже в контейнере/VM, full disk access, network по настройке |
| Guardian | LLM-based safety reviewer — risk taxonomy (exfiltration, credential probing, destructive actions, security weakening) → allow/deny/escalate |
| Exec policy | Starlark-based rules DSL (`.rules`/`.codexpolicy` файлы) — `prefix_rule`, `define_program`, banned prefixes, safe command detection |
| Multi-agent (v2) | Hierarchical sub-agents — spawn/send_message/wait/close_agent/list_agents/message_tool/followup_task + depth limit |
| Compaction | Auto-compact при context overflow — inline LLM summarization или remote compaction task |
| MCP client/server | MCP для расширения инструментов; Codex может быть MCP tool для других агентов |
| Skills (SKILL.md) | Bundled + custom skills — формализованные каталоги с инструкциями и скриптами |
| AGENTS.md | Hierarchical context discovery — глубокий файл перекрывает верхний |
| Session persistence | SQLite state DB + rollout files (JSONL) — resumable sessions (`codex resume`/`codex fork`) |
| Plan mode | TUI collaboration mode (`ModeKind::Plan`) — модель использует `update_plan` для структурированного планирования |
| Hooks | Pre/post tool use, session start/stop — внешние hook-скрипты для CI/CD интеграции |
| Memories | Long-term memory с citations, storage, компактификация — персистентный контекст между сессиями |
| Plugins | Динамическая загрузка инструментов из marketplace — расширяет capabilities агента |

---

## 2. Возможности оркестрации — обзор

| Функция | Codex CLI + Docker Agent |
| --- | --- |
| **Бюджетный контроль** | ⚠️ ChatGPT plan limits, но без программных лимитов |
| **Audit Trail (JSONL)** | ✅ Rollout files (JSONL) + SQLite state |
| **Ролевые промпты** | ✅ AGENTS.md + agent roles (TOML) |
| **Multiple runners** | ✅ OpenAI + Ollama + Amazon Bedrock + custom providers + model_provider в config.toml |
| **YAML-конфигурация** | ✅ config.toml (TOML) |
| **Sandboxing (OS-level)** | ✅ Seatbelt/Landlock/Bubblewrap/restricted token — per-platform |
| **Sandboxing (Docker)** | ✅ Docker + iptables firewall + domain whitelist + auto-cleanup |
| **Guardian (safety reviewer)** | ✅ LLM-based risk assessment — data exfiltration, credential probing, destructive actions |
| **Exec policy (rules)** | ✅ .rules файлы — banned prefixes, safe command detection, per-path restrictions |
| **Multi-agent (hierarchical)** | ✅ spawn/send_message/wait/close_agent/list_agents + depth limit + mailbox |
| **Network isolation** | ✅ iptables/ipset firewall — whitelist доменов, DNS-only, DROP default |
| **Split filesystem permissions** | ✅ Per-path read/write/none — granular filesystem access control |
| **Compaction (auto-compact)** | ✅ LLM summarization при context overflow (inline + remote) |
| **MCP server mode** | ✅ `codex mcp-server` — Codex как MCP tool для других агентов |
| **MCP client** | ✅ MCP servers в config.toml, parallel tool calls support |
| **SKILL.md** | ✅ Bundled + custom skills |
| **Session persistence** | ✅ SQLite + rollout files (JSONL) — `codex resume`/`codex fork` |
| **Plan mode** | ✅ TUI collaboration mode (`ModeKind::Plan`) — структурированное планирование через `update_plan` tool |
| **Headless mode** | ✅ `codex exec` (stdin/stdout) |
| **Non-interactive CI mode** | ✅ `codex exec --full-auto` — для CI/CD |
| **Hooks (lifecycle)** | ✅ Pre/post tool use, session start/stop — внешние скрипты |
| **Memories (long-term)** | ✅ Citations, storage, компактификация — персистентный контекст между сессиями |
| **Plugins (marketplace)** | ✅ Динамическая загрузка инструментов через `codex plugin` |
| **IDE integration** | ✅ VS Code / Cursor / Windsurf extensions |
| **Realtime audio** | ✅ Experimental realtime audio mode |
| **Image generation** | ✅ Built-in image generation context |

---

## 3. Оркестрационные возможности

### 3.1 🟡 Multi-layer Sandboxing — OS-level + Docker + Network isolation

**Что у них:** Codex CLI реализует трёхуровневую систему sandboxing:

**Уровень 1: OS-level sandboxing**
```
macOS → Seatbelt (/usr/bin/sandbox-exec)
Linux → Bubblewrap (bwrap) + Landlock
Windows → Restricted token + elevated backend
```

Конфигурация через `--sandbox` flag и `SandboxPolicy` enum в `config.toml`:
- `read-only` — только чтение filesystem (`read_only_access`), нет network
- `workspace-write` — запись в workspace (`writable_roots`), network через proxy
- `danger-full-access` — без ограничений (для trusted environments)
- `external-sandbox` — процесс уже в контейнере/VM, full disk access, network по настройке

Каждый вариант управляет параметрами: `read_only_access`, `writable_roots`, `network_access`, `readable_roots`, `read_only_subpaths`.

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

**Оркестрационная значимость:** Для autonomous CI/CD pipeline — критически важная безопасность. Multi-layer sandboxing позволяет:
- Изолировать выполнение shell-команд от host-системы
- Ограничить network access (только к разрешённым API endpoints)
- Контролировать filesystem access (запись только в workspace)
- Запускать pipeline в Docker container с auto-cleanup

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
 • Destructive Actions (high/critical): удаление данных, повреждение production-окружения
 • Low-Risk Actions (low/medium): обычные операции
 → Decision: allow / deny / escalate to user
```

Ключевые правила Guardian:
- **Outcome rule:** deny actions that disclose secrets/credentials to untrusted destinations
- **Context awareness:** различает local (user machine) vs. production environments
- **Granularity:** отличает `rm -rf` конкретного файла (low/medium) от broad destructive action (high/critical)
- **Override:** user authorization может повысить разрешённый risk level

**Оркестрационная значимость:** Для autonomous execution в CI/CD — LLM-based «quality gate» на уровне каждого shell-вызова. Guardian дополняет статические проверки (exec policy, sandboxing) семантической оценкой намерения команды:
- Exec policy: блокирует команды по формальным правилам (pattern matching)
- Guardian: оценивает семантику действия — даже если команда не попадает под запрещённый паттерн, LLM может распознать опасную последовательность

---

### 3.3 🟡 Exec Policy — rules-based command filtering

**Что у них:** Codex CLI использует `.rules` файлы для декларативного управления разрешёнными командами:

```python
# default.rules / .codexpolicy — Starlark DSL
prefix_rule(
 pattern = ["git", "reset", "--hard"],
 decision = "forbidden",
 justification = "destructive operation",
 match = [["git", "reset", "--hard"]],
 not_match = [["git", "reset", "--keep"]],
)

define_program(
 program="ls",
 system_path=["/bin/ls", "/usr/bin/ls"],
 options=[flag("-a"), flag("-l")],
 args=[ARG_RFILES_OR_CWD],
)
```

Дополнительные механизмы:
- **Banned prefixes:** `bash -c`, `python -c`, `sh -c` — блокируются по умолчанию
- **Safe command detection:** `is_known_safe_command()` — whitelist безопасных команд
- **Dangerous command detection:** `command_might_be_dangerous()` — эвристика для рискованных команд
- **Network rules:** per-command network access control
- **Per-path restrictions:** команды ограничены определёнными директориями

**Оркестрационная значимость:** Декларативные rules для ограничения shell-команд в цепочках. Exec policy позволяет:
- Определить разрешённые команды для каждого типа шага
- Заблокировать опасные команды (`rm -rf /`, `curl` к внешним endpoints)
- Дифференцировать политики по environment (dev vs. CI vs. production)

---

### 3.4 🟡 Multi-Agent v2 — hierarchical sub-agents с depth limit

**Что у них:** Codex CLI поддерживает порождение sub-agent'ов через tool calls:

```
Codex (main agent)
 ├─ spawn("Investigate auth module") → sub-agent с изолированным контекстом
 │ ├─ send_message("Found issue in auth.rs")
 │ └─ close_agent()
 ├─ spawn("Write unit tests") → sub-agent
 │ └─ send_message("3 tests written")
 └─ spawn("Review code") → sub-agent
 └─ send_message("LGTM")
```

**Механика:**
- **Tools:** `spawn`, `send_message`, `wait`, `close_agent`, `list_agents`, `message_tool`, `followup_task`
- **Depth limit:** `agent_max_depth` — ограничение вложенности (agent → sub-agent → sub-sub-agent)
- **Mailbox pattern:** async message passing через `Sender/Receiver` channels
- **Isolated context:** sub-agent получает собственный context window + config
- **Fork modes:** управляется через `fork_turns` параметр: `"all"` (FullHistory — наследует всю историю), `"none"` (чистый старт), или число (последние N turns). В v2 `fork_context` не поддерживается
- **Role system:** `agent_type` — назначение роли sub-agent'у (default, custom, built-in roles)
- **Approval routing:** sub-agent approval requests направляются parent session

**Оркестрационная значимость:** Для dynamic chains — возможность делегировать подзадачу отдельному агенту с чистым контекстом. Sub-agent pattern позволяет:
- Изолировать контекст подзадачи (меньше token usage)
- Выполнять подзадачи параллельно
- Ограничивать вложенность (depth limit) — защита от runaway spawning
- Назначать разные роли/модели разным sub-agent'ам

---

### 3.5 🟡 Network Isolation — iptables/ipset firewall для AI-агентов

**Что у них:** Codex CLI при запуске в Docker container инициализирует iptables firewall:

```bash
# 1. Flush existing rules (all tables)
iptables -F && iptables -X
iptables -t nat -F && iptables -t nat -X
iptables -t mangle -F && iptables -t mangle -X
ipset destroy allowed-domains 2>/dev/null || true

# 2. Allow DNS and localhost
iptables -A OUTPUT -p udp --dport 53 -j ACCEPT
iptables -A INPUT -p udp --sport 53 -j ACCEPT
iptables -A INPUT -i lo -j ACCEPT
iptables -A OUTPUT -o lo -j ACCEPT

# 3. Create ipset with allowed domains
ipset create allowed-domains hash:net
for domain in "${ALLOWED_DOMAINS[@]}"; do
 ips=$(dig +short A "$domain")
 ipset add allowed-domains "$ips"
done

# 4. Allow host network (Docker host → container communication)
HOST_IP=$(ip route | grep default | cut -d" " -f3)
iptables -A INPUT -s "$HOST_NETWORK" -j ACCEPT
iptables -A OUTPUT -d "$HOST_NETWORK" -j ACCEPT

# 5. Default DROP
iptables -P INPUT DROP
iptables -P OUTPUT DROP
iptables -P FORWARD DROP

# 6. Allow established connections + whitelisted domains
iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
iptables -A OUTPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
iptables -A OUTPUT -m set --match-set allowed-domains dst -j ACCEPT

# 7. REJECT (not DROP) for immediate error feedback
iptables -A INPUT -p tcp -j REJECT --reject-with tcp-reset
iptables -A OUTPUT -p tcp -j REJECT --reject-with tcp-reset

# 8. Verify: example.com blocked, api.openai.com allowed
curl --connect-timeout 5 https://example.com # must fail
curl --connect-timeout 5 https://api.openai.com # must succeed
```

**Оркестрационная значимость:** Для autonomous pipeline — критически важно ограничить network access. Если AI-агент может выполнять shell-команды, он потенциально может:
- Скачать и выполнить вредоносный код
- Отправить данные на внешний сервер
- Получить инструкции от третьих лиц

Network isolation через iptables — простой и надёжный механизм: по умолчанию блокировать всё, разрешать только whitelist доменов (API endpoints runner'ов, git servers).

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

**Оркестрационная значимость:** Для длинных цепочек с большим количеством шагов — context overflow — реальная проблема. Auto-compaction позволяет:
- Продолжать выполнение даже при длинной истории
- Сохранять ключевой контекст через LLM summarization
- Не терять последние instructions при сжатии

---

### 3.7 🟡 Plan Mode — read-only exploration перед выполнением

**Что у них:** Codex CLI поддерживает Plan mode (`ModeKind::Plan`) — агент анализирует задачу, не выполняя деструктивные действия:

```
codex --plan "Refactor authentication module"
→ Agent использует tool update_plan для структурированного планирования
→ Читает файлы, анализирует код, формирует план
→ Не выполняет shell-команды, не пишет файлы
→ Пользователь подтверждает → Agent переключается в execution mode
```

**Техническая реализация:**
- Переключение через `--plan` flag в CLI или `ModeKind::Plan` в API
- Агент использует `update_plan` tool для структурированного планирования вместо прямого выполнения
- Plan mode ограничивает доступные инструменты: запрещены `shell` и `apply_patch`, разрешены `read`, `list_dir`, `update_plan`
- Результат планирования сохраняется в сессии и может быть использован при повторном запуске в execution mode

**Оркестрационная значимость:** Для dynamic chains — разделение exploration и execution phases:
- Сгенерировать план → показать пользователю → подтвердить → выполнить
- Снизить риск: планирование на основе актуального состояния кодовой базы перед модификацией
- Разделять exploration (анализ репозитория) и execution (внесение изменений)

---

## 4. Прочие возможности (вне оркестрации)

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

## 5. Сводка по оркестрации

| Возможность | Статус в продукте | Описание |
| --- | --- | --- |
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
