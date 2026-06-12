# Исследование: Odysseus (PewDiePie archdaemon) — Self-hosted AI workspace

> **Проект:** [github.com/pewdiepie-archdaemon/odysseus](https://github.com/pewdiepie-archdaemon/odysseus)
> **Дата анализа:** 2026-06-12
> **Версия:** 1.0
> **Язык:** Python (>=3.11, FastAPI)
> **Лицензия:** AGPL-3.0
> **Аналитик:** Аналитик (Шерлок)

---

## 1. Обзор проекта

Odysseus — self-hosted AI workspace, предназначенный как локальная альтернатива ChatGPT/Claude с privacy-first подходом. Built on OpenCode, использует Python + FastAPI backend и React frontend. Предлагает Chat, Agent, Deep Research, Cookbook, Documents, Memory/Skills, Email, Calendar, Notes & Tasks.

Odysseus **не является** оркестратором внешних CLI-ассистентов (как task-orchestrator). Это **полнофункциональное веб-приложение** с встроенным agent loop, tools, memory, skills и интеграциями. Odysseus управляет потоком выполнения внутри себя (LLM → tool call → observation → LLM), но не оркестрирует внешние цепочки команд или процессы.

### Архитектура

```
odysseus/
├── src/
│ ├── agent_loop.py          # Agent loop (LLM → tool → observation → LLM)
│ ├── agent_tools.py         # Tool parsing, execution, formatting
│ ├── tool_*.py              # Tool implementations (bash, python, web_search, etc.)
│ ├── llm_core.py            # LLM API integration, retry, host health, stream
│ ├── memory.py              # Memory manager (ChromaDB, vector + keyword retrieval)
│ ├── model_context.py       # Context length estimation
│ ├── endpoint_resolver.py   # Model endpoint resolution
│ ├── auth_helpers.py        # Multi-tenant auth (owner-scoped endpoints)
│ ├── tool_security.py       # Tool security (blocked tools, plan mode)
│ ├── prompt_security.py     # Prompt security (untrusted context filtering)
│ └── app.py                 # FastAPI application
├── routes/
│ ├── research_routes.py     # Deep Research workflow
│ ├── skills_routes.py       # Skills REST API (SKILL.md format)
│ ├── memory_routes.py       # Memory REST API
│ └── ...
├── services/
│ ├── memory/
│ │ ├── memory.py            # Memory manager
│ │ ├── memory_vector.py     # Vector retrieval (ChromaDB)
│ │ ├── memory_extractor.py  # LLM-based memory extraction
│ │ └── skills.py            # Skills manager
│ └── ...
├── mcp_servers/             # MCP server implementations
│ └── memory_server.py       # Memory MCP server
└── integrations/
    ├── claude/skills/       # Claude integration (SKILL.md)
    └── codex/skills/        # Codex integration (SKILL.md)
```

### Ключевые характеристики

| Характеристика | Значение |
| --- | --- |
| **Тип** | Self-hosted AI workspace (полнофункциональное веб-приложение) |
| **Модель выполнения (Agents)** | Agent loop (LLM → tool call → observation → LLM → ...) |
| **Модель выполнения (Deep Research)** | Multi-step runs (adapted from Alibaba DeepResearch) |
| **State management** | Session-based (SQLite) + ChromaDB (memory/skills) |
| **Провайдеры** | vLLM · llama.cpp · Ollama · OpenRouter · OpenAI · GitHub Copilot · ChatGPT Subscription |
| **Расширяемость** | Tools (bash, python, web_search, etc.), MCP servers, Skills (SKILL.md), custom endpoints |
| **Memory** | ChromaDB + fastembed (ONNX) + vector + keyword retrieval, persistence |
| **Skills** | SKILL.md format (frontmatter + structured body), learned skills, teacher escalation |
| **HITL** | Нет (автономный агент) |
| **Fallback** | Host health (cooldown after 2 consecutive failures), retry max 3 |
| **Guardrails** | Tool security (blocked tools per owner), prompt security (untrusted context filtering) |
| **Deep Research** | Multi-step research jobs (sidebar panel, streaming progress, final report) |
| **Web UI** | Responsive, PWA, mobile-first |
| **Лицензия** | AGPL-3.0 (copyleft, требует sharing модификаций) |

### Основные компоненты

| Компонент | Назначение |
| --- | --- |
| [`src/agent_loop.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/src/agent_loop.py) | Agent loop, domain rules, tool parsing, LLM orchestration |
| [`src/agent_tools.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/src/agent_tools.py) | Tool parsing, execution, formatting (MAX_AGENT_ROUNDS, 60s timeout, 10K char limit) |
| [`src/llm_core.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/src/llm_core.py) | LLM API integration, retry (max 3), host health (cooldown 20s), stream, harmony routing |
| [`src/memory.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/src/memory.py) | Memory manager (JSON file, ChromaDB, vector + keyword retrieval) |
| [`services/memory/memory_vector.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/services/memory/memory_vector.py) | Vector retrieval (ChromaDB, fastembed ONNX) |
| [`services/memory/skills.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/services/memory/skills.py) | Skills manager (SKILL.md format, learned skills, teacher escalation) |
| [`routes/research_routes.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/routes/research_routes.py) | Deep Research workflow (multi-step research jobs, sidebar panel) |
| [`src/tool_security.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/src/tool_security.py) | Tool security (blocked tools per owner, plan mode disabled tools) |
| [`src/prompt_security.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/src/prompt_security.py) | Prompt security (untrusted context filtering) |
| [`mcp_servers/memory_server.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/mcp_servers/memory_server.py) | MCP server for memory access |
| [`routes/skills_routes.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/routes/skills_routes.py) | Skills REST API (SKILL.md format, add/edit/delete/list/publish) |

---

## 2. Возможности оркестрации — обзор

| Функция | Odysseus |
| --- | --- |
| **Цепочки шагов (chains)** | ❌ Нет (агент loop с tools, но нет явного chain definition) |
| **Conditional branching** | ❌ Нет (агент решает через LLM, но нет явной conditional routing) |
| **Parallel execution** | ❌ Нет |
| **Циклы (loops)** | ⚠️ Agent loop с MAX_AGENT_ROUNDS (без явного loop DSL) |
| **Retry с backoff** | ⚠️ Retry max 3, delay 0.5s (без exponential backoff) |
| **Quality Gates** | ❌ Нет (только tool timeout и output limit) |
| **Fallback routing** | ⚠️ Host health (cooldown 20s after 2 consecutive failures) |
| **Audit Trail (JSONL)** | ❌ Нет (session persistence via SQLite, но нет audit trail) |
| **Ролевые промпты** | ⚠️ Domain rules per tool set (web, documents, email, etc.) |
| **Multiple runners** | ✅ vLLM · llama.cpp · Ollama · OpenRouter · OpenAI · GitHub Copilot · ChatGPT Subscription |
| **DDD-архитектура** | ❌ Монолит FastAPI приложение (no DDD) |
| **Decorator pattern** | ❌ Прямой вызов через tool blocks |
| **YAML-конфигурация** | ❌ Нет (через UI и API) |
| **Human-in-the-loop** | ❌ Нет (автономный агент) |
| **Session persistence** | ✅ SQLite sessions |
| **Memory system** | ✅ ChromaDB + fastembed (ONNX) + vector + keyword retrieval, persistence |
| **RAG / Knowledge** | ✅ Vector search (ChromaDB) |
| **Evaluation framework** | ❌ Нет |
| **Compression** | ❌ Нет |
| **CEL expressions** | ❌ Нет |
| **Approval system** | ❌ Нет |
| **Guardrails** | ✅ Tool security, prompt security |
| **Multi-agent Teams** | ❌ Нет (один агент) |
| **Deep Research** | ✅ Multi-step research jobs (adapted from Alibaba DeepResearch) |
| **MCP** | ✅ MCP client/server |
| **Skills** | ✅ SKILL.md format, learned skills, teacher escalation |
| **Runtime (FastAPI)** | ✅ Полнофункциональный web UI (responsive, PWA, mobile-first) |

---

## 3. Оркестрационные возможности

### 3.1 🔴 Agent Loop — LLM → tool call → observation → LLM (`src/agent_loop.py`)

**Что у них:** Классический ReAct pattern — LLM решает, какой tool вызвать, форматит fenced code block с tool name, оркестратор его выполняет, подставляет результат в контекст, и повторяет.

```python
# Tool block format (фенсированный код блок с названием tool):
```bash
<shell command>
```

```python
<python code>
```

```web_search
<search query>
```
```

**MAX_AGENT_ROUNDS:** Ограничение на количество раундов (определено в `src/agent_tools.py`). Если агент делает tool calls в каждом раунде, он может превысить лимит.

**Tool timeouts:** 60s timeout per tool, 10K char output limit. Если tool не успевает — timeout и ошибка.

**Domain rules:** Детальные правила для каждого домена (web, documents, email, cookbook, notes_calendar_tasks, ui, sessions, files, settings). Эти правила инжектируются в system prompt и управляют поведением агента (например, для email: UID — это не row number, bulk actions — один вызов `bulk_email`, и т.д.).

> ⚠️ **Архитектурные ограничения:**
> - Нет явного chain definition — агент сам решает, какой tool вызвать в каждом раунде, но нет визуальной структуры chain.
> - Нет conditional branching — если агенту нужно выбрать путь A или B, он решает через LLM, но нет явной conditional routing.
> - Нет parallel execution — все tool calls последовательны (хотя `MAX_AGENT_ROUNDS` можно превысить, если много tool calls).
> - Нет circuit breaker — только host health (cooldown после 2 sequential failures).
> - Retry без exponential backoff — фиксированный max 3 attempts, delay 0.5s.

---

### 3.2 🔴 Host Health — Cooldown после 2 consecutive failures (`src/llm_core.py`)

**Что у них:** Dead-host cooldown mechanism — maps host → unix timestamp when cooldown expires. Когда connect к хосту fail, он помечается как dead на 20s, но только после 2 consecutive failures (threshold). Любой success сразу сбрасывает counter.

```python
DEAD_HOST_COOLDOWN = 20.0
_HOST_FAIL_THRESHOLD = 2
_dead_hosts: Dict[str, float] = {}
_host_fails: Dict[str, int] = {}
```

**Оркестрационная значимость:** Host health — это примитивный circuit breaker на уровне host. Однако это **не circuit breaker** в классическом понимании (состояния closed/open/half-open). Это просто cooldown после 2 sequential failures. Нет отдельного состояния per-host (только dead/alive), нет recovery attempt после cooldown, нет error classification.

> ⚠️ **Архитектурные ограничения:**
> - Нет circuit breaker — только cooldown. Host может быть dead из-за transient blip (например, local model briefly busy).
> - No error classification — все connect failures обрабатываются одинаково.
> - No fallback routing — если host dead, запрос просто fail fast, нет альтернативного endpoint.
> - No retry with exponential backoff — фиксированный retry max 3, delay 0.5s.

---

### 3.3 🟡 Deep Research — Multi-step research jobs (`routes/research_routes.py`)

**Что у них:** Deep Research workflow — multi-step runs, adapted from Alibaba DeepResearch. Запускается через `trigger_research` tool или UI sidebar. Стримит прогресс, собирает источники, читает, синтезирует в отчёт.

**Owner-scoped research:** Результаты исследованиий не owner-scoped в on-disk JSON, поэтому все endpoints требуют authenticated user. Multi-tenant deploys должны дополнительно проверять, что session принадлежит этому user.

**Model detection:** Автоматически пропускает non-chat models (embedding, tts, whisper, dall-e, moderation, rerank, etc.) и выбирает первый chat model.

> ⚠️ **Архитектурные ограничения:**
> - Deep Research — отдельный workflow, не интегрирован в agent loop.
> - Нет визуализации progress в main chat — только sidebar panel.
> - Нет явного управления iterations/max_steps (возможно, внутренне).

---

### 3.4 🟡 Skills — SKILL.md format with learned skills (`services/memory/skills.py`)

**Что у них:** Skills system — SKILL.md format (frontmatter + structured body). Skills хранятся в `data/skills/<category>/<name>/`. Атрибуты: `name`, `description`, `category`, `tags`, `platforms`, `requires_toolsets`, `fallback_for_toolsets`, `when_to_use`, `procedure`, `pitfalls`, `verification`, `status`, `version`, `confidence`, `source` ("user" or "learned"), `teacher_model`, `session_id`.

**Learned skills:** Агент может создавать навыки через teacher escalation (ask teacher model → teacher writes skill → saved as draft → publish). Skills могут быть evicted when cap reached (user skills exempt).

**Skill testing:** `_skill_test_task` — генерит self-contained test task. Агент сначала создает realistic scenario, затем applies skill.

**Оркестрационная значимость:** Skills — это хранилище процедурных знаний. Агент может lookup skill по query, apply procedure, avoid pitfalls, verify result. Это аналог RAG для процедурных знаний (не только фактических).

> ⚠️ **Архитектурные компромиссы:**
> - Skills — часть memory system, не интегрированы в chain definition. Агент должен явно lookup skill через tool.
> - Нет навыка компиляции или проверки процедурной логики — LLM пишет procedure, но нет валидации.
> - Cap eviction — когда cap reached, skills могут быть evicted. User skills exempt, но learned skills may be lost.

---

### 3.5 🟡 Memory — ChromaDB + vector + keyword retrieval (`services/memory/memory_vector.py`)

**Что у них:** Memory system — ChromaDB + fastembed (ONNX) для vector retrieval, keyword retrieval для точных совпадений. Persistence через JSON file (`data/memory.json`).

**Memory extraction:** `memory_extractor.py` — LLM-based extraction of memories from chat history. Fallback: regex pattern matching для bullet points/numbered lists.

**Memory management:** `memory.py` — CRUD operations, owner-scoped (multi-tenant), claim ownerless memories.

**Оркестрационная значимость:** Memory — персистентное хранилище фактов/предпочтений пользователя. Агент может `manage_memory` для добавления фактов ("my name is X", "I live in Y"), и retrieval через vector search для релевантного контекста.

> ⚠️ **Архитектурные компромиссы:**
> - Memory — часть general state management, не интегрирована в chain definition. Агент должен явно manage memory.
> - LLM-based extraction — может быть неточной. Fallback regex — limited.
> - No memory compression/summarization — context может перерасти.

---

### 3.6 🟡 Tool Security — Blocked tools per owner (`src/tool_security.py`)

**Что у них:** Tool security — `blocked_tools_for_owner()` возвращает blocked tool set per owner. `plan_mode_disabled_tools()` — tools disabled in plan mode (например, shell, python).

**Оркестрационная значимость:** Tool security — простая ACL на уровне tools. Owner-scoped — multi-tenant isolation. No sandboxing (кроме контейнерной изоляции через Docker для Cookbook).

> ⚠️ **Архитектурные ограничения:**
> - No sandboxing для tool execution — shell/python запускаются напрямую на host machine.
> - No permission system — только blocked tools, нет fine-grained permissions (glob patterns, allow/deny per path).
> - No exec policy — нет декларативных правил для command filtering.

---

### 3.7 🟡 Prompt Security — Untrusted context filtering (`src/prompt_security.py`)

**Что у них:** Prompt security — `untrusted_context_message()` фильтрует untrusted context перед отправкой в LLM.

**Оркестрационная значимость:** Prompt security — защита от prompt injection через untrusted контент (например, из web search results).

> ⚠️ **Архитектурные ограничения:**
> - Паттерн-матчинг — limited, не полный prompt injection detection.
> - No LLM-based guardrail — только regex/pattern matching.

---

## 4. Прочие возможности (вне оркестрации)

### 4.1 🟢 Cookbook — Модельный менеджер с GPU awareness

Cookbook — LLM-serving subsystem. Scans hardware, recommends models, click to download and serve. Supports vLLM · llama.cpp · Ollama · remote servers. GPU-aware для NVIDIA/AMD. Not relevant для chain orchestrator.

### 4.2 🟢 Documents — Редактор с AI

Multi-tab editor, markdown/HTML/CSV, syntax highlighting, AI edits, suggestions. Not relevant для chain orchestrator.

### 4.3 🟢 Email — IMAP/SMTP inbox с AI triage

IMAP/SMTP inbox с AI triage: urgency reminders, auto-tag, auto-summary, auto-reply drafts, auto-spam. Not relevant для chain orchestrator.

### 4.4 🟢 Calendar — Local-first calendar с CalDAV sync

Local-first calendar с CalDAV sync (Radicale/Nextcloud/Apple/Fastmail). Not relevant для chain orchestrator.

### 4.5 🟢 Notes & Tasks — Quick notes с reminders

Quick notes, checklist, cron-style tasks, ntfy/browser/email channels. Not relevant для chain orchestrator.

### 4.6 🟢 Works on mobile — Responsive, PWA

Responsive design, installable (PWA), touch gestures. Not relevant для chain orchestrator.

---

## 5. Сводка по оркестрации

| Возможность | Статус в продукте | Описание |
| --- | --- | --- |
| Agent loop (ReAct) | 🔴 P1 | Базовый механизм — LLM → tool call → observation → LLM |
| Host health (cooldown) | 🟡 P2 | Примитивный circuit breaker (cooldown after 2 consecutive failures) |
| Deep Research | 🟡 P2 | Multi-step research jobs (adapted from Alibaba DeepResearch) |
| Skills (SKILL.md) | 🟡 P2 | Procedural knowledge storage with learned skills, teacher escalation |
| Memory (ChromaDB) | 🟡 P2 | Vector + keyword retrieval, persistence, LLM-based extraction |
| Tool security | 🟡 P2 | Blocked tools per owner, plan mode disabled tools |
| Prompt security | 🟡 P2 | Untrusted context filtering |
| MCP | 🟡 P2 | MCP client/server |
| Multi-provider support | 🟢 — | vLLM · llama.cpp · Ollama · OpenRouter · OpenAI · GitHub Copilot · ChatGPT Subscription |
| Chains | 🔴 — | Нет (агент loop с tools, но нет явного chain definition) |
| Conditional branching | 🔴 — | Нет |
| Parallel execution | 🔴 — | Нет |
| Loop с end_condition | 🔴 — | Нет (только MAX_AGENT_ROUNDS) |
| Retry с exponential backoff | 🔴 — | Нет (фиксированный retry max 3, delay 0.5s) |
| Circuit breaker | 🔴 — | Нет (только host health cooldown) |
| Quality Gates | 🔴 — | Нет (только tool timeout и output limit) |
| Fallback routing | 🔴 — | Нет |
| HITL | 🔴 — | Нет (автономный агент) |
| Audit Trail (JSONL) | 🔴 — | Нет (session persistence через SQLite, но нет audit trail) |
| Evaluation framework | 🔴 — | Нет |

---

## 6. Сравнение с task-orchestrator

| Характеристика | task-orchestrator | Odysseus |
| --- | --- | --- |
| **Тип** | CLI chain orchestrator (PHP/Symfony, DDD) | Self-hosted AI workspace (Python/FastAPI, monolith) |
| **Модель выполнения** | YAML chains (static/dynamic) + runner orchestration | Agent loop (ReAct) + tools + Deep Research |
| **State management** | Chain state per step, JSONL audit trail | Session persistence (SQLite), ChromaDB (memory/skills) |
| **Error handling** | Retry с backoff, circuit breaker, budget enforcement, quality gates | Retry max 3, host health (cooldown), tool timeout |
| **Fallback** | Chain-level fallback (переключение на alternative chain) | Host health (cooldown) — нет fallback routing |
| **Extensibility** | Decorator pattern, custom runners, YAML DSL | Tools, MCP servers, Skills (SKILL.md), custom endpoints |
| **Memory** | Нет (out of scope) | ChromaDB + fastembed + vector + keyword retrieval |
| **Skills** | Нет (out of scope) | SKILL.md format, learned skills, teacher escalation |
| **HITL** | Нет (CLI) | Нет (автономный агент) |
| **Multi-agent** | Нет (planned) | Нет |
| **License** | MIT | AGPL-3.0 (copyleft) |
| **Runtime** | CLI (не нужен UI) | Web UI (responsive, PWA, mobile-first) |

---

## 7. Риски и ограничения

### 7.1 🔴 AGPL-3.0 лицензия

AGPL-3.0 — copyleft лицензия, требует sharing модификаций кода при предоставлении сервиса пользователям по сети. Для проприетарных продуктов — неприемлемо. Даже код под AGPL может использоваться только если весь проект open-source под AGPL или совместимой лицензией.

### 7.2 🔴 Монолит FastAPI приложение

Odysseus — монолит FastAPI приложение. Нет DDD, нет разделения на слои (Domain/Application/Infrastructure). Код смешивает логику UI, API, agent loop, memory, skills. Для task-orchestrator — не подходит архитектурно (мы следует DDD и Clean Architecture).

### 7.3 🟡 Зрелость

Odysseus — относительно новый проект (май 2026, version 1.0). Активно развивается, но зрелость ниже, чем у других исследованных фреймворков (Archon, Paperclip AI, Zeroclaw). GitHub stars: ~69k на дату анализа — популярность выше среднего, но зрелость кодовой базы неизвестна.

### 7.4 🟡 Зависимость от OpenCode

Odysseus built on OpenCode. Это означает, что key patterns (agent loop, tools, memory, skills) взяты из OpenCode. Если мы хотим использовать patterns из Odysseus, нужно понимать, что они — часть экосистемы OpenCode.

### 7.5 🟡 Нет explicit chain definition

Odysseus не имеет explicit chain definition. Агент сам решает, какой tool вызвать в каждом раунде. Для deterministic chains (как в task-orchestrator) — не подходит. Это trade-off: гибкость против детерминизма.

### 7.6 🟡 Нет circuit breaker и fallback routing

Odysseus имеет только host health cooldown. Нет circuit breaker (состояния closed/open/half-open), нет fallback routing (переключение на alternative endpoint при типе ошибки). Для production-систем — не подходит.

---

## 8. Implementation Candidates for task-orchestrator

> **Важно:** AGPL-3.0 лицензия запрещает копирование кода и архитектурных фрагментов. Этот раздел использует Odysseus как **product signal** — источник идей для независимого проектирования в нашей DDD/Clean Architecture. Не копировать implementation details, только концепции.

### 🔴 Не использовать как dependency

Odysseus — **не подходит как dependency** для task-orchestrator по трём причинам:

1. **AGPL-3.0 лицензия** — copyleft, неприемлемо для MIT-проекта. Даже minor code copying обязует open-source всех модификаций.
2. **Архитектурное несоответствие** — монолит FastAPI против DDD/PHP/Symfony. Нет Domain/Application/Infrastructure разделения.
3. **Нет explicit chain definition** — Odysseus — agent workspace, не chain orchestrator. Наша модель (YAML chains → runner call → payload) принципиально другая.

### 🟡 Feature candidates for independent implementation

| Кандидат функции | Описание | Приоритет | Затраты | Риск |
| --- | --- | ---: | ---: | ---: |
| **Deep Research Chain** | Multi-step research workflow: iterate → collect sources → read → synthesize → report. Адаптировано из Alibaba DeepResearch. | P2 | C3 | R2 |
| **Agent Skills Registry** | SKILL.md format для процедурных знаний: when_to_use, procedure, pitfalls, verification. Discovery, validation, role binding. | P2 | C3 | R2 |
| **Tool Permission Policy** | allow/deny/ask для инструментов по ролям/chains. Glob patterns, inheritance для sub-agents. | P3 | C2 | R2 |
| **Agent Memory / Context Store** | Vector + keyword retrieval для результатов research/ретро/решений. Persistence, compression, pruning. | P3 | C4 | R3 |
| **Provider Health / Failover** | Host health (cooldown после N consecutive failures), fallback routing на альтернативный provider. | P2 | C2 | R1 |

---

## 9. Вердикт

**🔴 Не подходит как dependency, 🟡 feature candidates for independent implementation.**

Odysseus — self-hosted AI workspace с богатым функционалом (Chat, Agent, Deep Research, Cookbook, Documents, Memory/Skills, Email, Calendar, Notes & Tasks), но это **полнофункциональное веб-приложение**, не chain orchestrator. Ключевые причины:

1. **AGPL-3.0 лицензия** — неприемлемо для MIT-проекта. Нельзя использовать как dependency или source для заимствования кода.
2. **Монолит FastAPI** — архитектурно не совпадает с DDD/PHP/Symfony.
3. **Нет explicit chain definition** — Odysseus управляет agent loop, не цепочками команд.
4. **Нет circuit breaker и fallback routing** — только host health cooldown.
5. **Зависимость от OpenCode** — patterns взяты из OpenCode, анализ потребует глубокого погружения в OpenCode.

**Рекомендация:** Не использовать Odysseus как dependency. AGPL-3.0 запрещает копирование кода. Однако отдельные product capabilities (Deep Research, Skills, Tool Permission, Memory, Provider Health) полезны как **feature candidates для самостоятельной реализации** в task-orchestrator с нуля, в нашей DDD/Clean Architecture. См. секцию "Implementation Candidates for task-orchestrator".

---

## 10. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории Odysseus:

- [`src/agent_loop.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/src/agent_loop.py) — Agent loop, domain rules, tool parsing
- [`src/agent_tools.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/src/agent_tools.py) — Tool parsing, execution, formatting (MAX_AGENT_ROUNDS, 60s timeout, 10K char limit)
- [`src/llm_core.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/src/llm_core.py) — LLM API integration, retry (max 3), host health (cooldown 20s), stream, harmony routing
- [`src/memory.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/src/memory.py) — Memory manager (JSON file, ChromaDB, vector + keyword retrieval)
- [`services/memory/memory_vector.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/services/memory/memory_vector.py) — Vector retrieval (ChromaDB, fastembed ONNX)
- [`services/memory/skills.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/services/memory/skills.py) — Skills manager (SKILL.md format, learned skills, teacher escalation)
- [`routes/research_routes.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/routes/research_routes.py) — Deep Research workflow (multi-step research jobs, sidebar panel)
- [`src/tool_security.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/src/tool_security.py) — Tool security (blocked tools per owner, plan mode disabled tools)
- [`src/prompt_security.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/src/prompt_security.py) — Prompt security (untrusted context filtering)
- [`mcp_servers/memory_server.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/mcp_servers/memory_server.py) — MCP server for memory access
- [`routes/skills_routes.py`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/routes/skills_routes.py) — Skills REST API (SKILL.md format, add/edit/delete/list/publish)
- [`README.md`](https://github.com/pewdiepie-archdaemon/odysseus/blob/main/README.md) — Overview, features, quick start
- [Landing page](https://pewdiepie-archdaemon.github.io/odysseus/) — Demo, screenshots, full tour

---

📚 **Источники:**
1. [github.com/pewdiepie-archdaemon/odysseus](https://github.com/pewdiepie-archdaemon/odysseus) — репозиторий проекта (~69k★ на дату анализа)
2. [pewdiepie-archdaemon.github.io/odysseus/](https://pewdiepie-archdaemon.github.io/odysseus/) — landing page с демо
3. [OpenCode](https://github.com/anomalyco/opencode) — базовый фреймворк для Odysseus
4. [Alibaba DeepResearch](https://github.com/Alibaba-NLP/DeepResearch) — источник для Deep Research workflow