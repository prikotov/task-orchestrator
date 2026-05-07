# Исследование: Oz — платформа оркестрации облачных AI-агентов (Warp)

> **Проект:** [warp.dev/oz](https://www.warp.dev/oz)
> **Дата анализа:** 2026-05-07
> **Язык:** — (проприетарная облачная платформа; SDK: Python, TypeScript)
> **Лицензия:** Проприетарный (SaaS); клиент Warp — AGPL v3 (open-source)
> **Аналитик:** Аналитик (Шерлок)

---

## 1. Обзор проекта

Oz — **платформа оркестрации облачных AI-агентов** от компании Warp. Разработана поверх терминала Warp (56K+ GitHub звёзд, 500K+ пользователей) и предоставляет инфраструктуру для запуска, координации и мониторинга автономных кодинг-агентов в облаке.

Ключевая идея: **любой разработчик может запустить неограниченное количество параллельных AI-агентов в облачных Docker-окружениях** с программным управлением через API/SDK, CLI, расписания (cron), вебхуки и интеграции (Slack, Linear, GitHub Actions).

> «Oz is an orchestration platform for cloud agents. Spin up unlimited parallel coding agents that are programmable, auditable, and fully steerable.»

### Контекст: Warp

Warp — «агентный терминал» (agentic terminal), переосмысление терминального эмулятора для эры AI. Warp-клиент open-source (AGPL v3, [github.com/warpdotdev/warp](https://github.com/warpdotdev/warp)), серверная часть и Oz — проприетарный SaaS. Warp получил финансирование Series B ($75M) от Dylan Field (Figma), GV (Google Ventures) и других.

### Архитектура

```
┌─────────────────────────────────────────────────────────────┐
│                   Точки входа / Триггеры                     │
│   Warp Terminal │ Oz CLI │ REST API │ SDK (Python/TS)       │
│   Slack │ Linear │ GitHub Actions │ Cron Schedules          │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                   Oz Orchestration Layer                     │
│   • Agent run lifecycle (QUEUED → INPROGRESS → SUCCEEDED/   │
│     FAILED)                                                  │
│   • Schedule management (create/pause/resume/delete)        │
│   • Integration routing (webhook → agent run)               │
│   • Run tracking & audit (run_id, state, timestamps)        │
└──────────────────────────┬──────────────────────────────────┘
                           │
              ┌────────────┴────────────┐
              ▼                         ▼
┌──────────────────────┐  ┌──────────────────────────────────┐
│  Встроенный Oz Agent │  │  Сторонние CLI-агенты            │
│  (own LLM orchest.)  │  │  Claude Code / Codex / Gemini    │
│  • Codebase Context  │  │  (запуск в Docker-окружении)     │
│  • Planning          │  │                                  │
│  • Task Lists        │  │                                  │
│  • MCP               │  │                                  │
└──────────┬───────────┘  └──────────────┬───────────────────┘
           │                             │
           └──────────────┬──────────────┘
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                Cloud Environments (Docker)                   │
│   • Docker image (base / language / full)                   │
│   • GitHub repos clone                                      │
│   • Setup commands                                          │
│   • Secrets (API keys)                                      │
│   • MCP server configs                                      │
│   • Skills (SKILL.md)                                       │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│              Observability & Management                      │
│   • Oz Web App (oz.warp.dev) — dashboard                    │
│   • Run listing / filtering (state, config, creator)        │
│   • Session links (full transcript)                         │
│   • Notification system (in-app + desktop)                  │
└─────────────────────────────────────────────────────────────┘
```

### Ключевые характеристики

| Характеристика | Значение |
|---|---|
| **Тип** | Платформа оркестрации облачных AI-агентов (SaaS) |
| **Модель выполнения** | Cloud-managed agent runs (QUEUED → INPROGRESS → SUCCEEDED / FAILED) |
| **Триггеры запуска** | CLI, REST API, SDK, Cron-расписания, Slack, Linear, GitHub Actions, Webhook |
| **State management** | Cloud-managed (server-side, run_id + state transitions + session links) |
| **Окружения** | Docker-контейнеры (10+ предустановленных образов: Go, Rust, Java, Node, Python и др.) |
| **LLM-модели** | Мульти-модельный (Claude Sonnet/Opus, GPT-4, Gemini и др. — пользователь выбирает) |
| **SDK** | Python (`oz-agent-sdk`, PyPI), TypeScript (`oz-agent-sdk`, NPM) |
| **CLI** | `oz` CLI (агенты, расписания, окружения, секреты, MCP) |
| **Интеграции** | Slack, Linear, GitHub Actions (webhook-триггеры → agent runs) |
| **Контекст** | Codebase Context (semantic indexing Git-tracked файлов), Skills (SKILL.md), Rules |
| **MCP** | Да (per-run MCP-конфигурация: Warp shared, stdio, remote SSE) |
| **Расширяемость** | SDK/REST API + Skills (SKILL.md) + MCP + Rules + Agent Profiles & Permissions |
| **Безопасность** | Agent Profiles & Permissions, command denylist, secrets management, Docker isolation |
| **Веб-интерфейс** | oz.warp.dev — dashboard для управления runs, schedules, интеграциями |
| **Open-source компоненты** | Warp-клиент (AGPL v3), oz-skills, oz-dev-environments (Docker images) |

### SDK (Python — `oz-agent-sdk`)

```python
from oz_agent_sdk import OzAPI

client = OzAPI(api_key=os.environ.get("WARP_API_KEY"))

# Запуск облачного агента
response = client.agent.run(
    prompt="Fix the bug in auth.go",
    config={
        "environment_id": "env_abc123",
        "model_id": "claude-sonnet-4",
        "base_prompt": "You are a helpful coding assistant.",
        "mcp_servers": {
            "github": {"warp_id": "shared-mcp-id"},
        },
    },
)
print(response.run_id)

# Проверка статуса
run = client.agent.runs.retrieve(run_id)
print(run.state)  # QUEUED | INPROGRESS | SUCCEEDED | FAILED

# Список runs
for run in client.agent.runs.list():
    print(run.run_id, run.state)
```

### SDK — ключевые модели

| Модель | Назначение |
|---|---|
| `RunAgentRequest` | Запрос на запуск (prompt + config) |
| `RunAgentResponse` | Ответ (run_id, state) |
| `RunItem` | Полная информация о run (state, timestamps, session_link, config) |
| `RunState` | QUEUED / INPROGRESS / SUCCEEDED / FAILED |
| `AmbientAgentConfig` | Конфигурация (environment_id, model_id, base_prompt, mcp_servers, name, skill_spec) |
| `McpServerConfig` | MCP-конфигурация (warp_id / command+args / url+headers) |
| `ScheduledAgentItem` | Cron-расписание (create / pause / resume / delete) |

---

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | Task Orchestrator | Oz | Статус |
|---|---|---|---|
| **Цепочки шагов (chains)** | ✅ YAML chains, статические и динамические | ❌ Нет цепочек — каждый run = один агент с одним prompt | ✅ У нас есть (ключевое отличие) |
| **Retry с backoff** | ✅ RetryingAgentRunner | ⚠️ SDK имеет built-in retries (HTTP-level), платформа управляет run lifecycle | ✅ У нас есть (на уровне шагов) |
| **Circuit Breaker** | ✅ CircuitBreakerAgentRunner | ❌ Нет (пользователь не управляет retry-логикой) | ✅ У нас есть |
| **Quality Gates** | ✅ Shell-команды как проверки | ❌ Нет явных quality gates — агент сам решает, когда завершить | ✅ У нас есть |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based) | ❌ Нет явного бюджетного контроля (биллинг через Warp-подписку) | ✅ У нас есть |
| **Итерационные циклы (fix_iterations)** | ✅ Группа шагов с max_iterations | ❌ Нет итерационных циклов — один prompt = один run | ✅ У нас есть |
| **Fallback routing** | ✅ Per-step fallback runner | ⚠️ Пользователь выбирает model_id, fallback — на уровне платформы | ✅ У нас есть (явный) |
| **Облачные Docker-окружения** | ❌ Локальное выполнение | ✅ Cloud Environments: 10+ Docker-образов, clone любых GitHub repos, setup commands | 🟡 Интересно |
| **Cron-расписания** | ❌ Нет | ✅ Schedule management (create/pause/resume/delete, cron expressions) | 🟡 Интересно |
| **Webhook / Integration triggers** | ❌ Только CLI | ✅ Slack, Linear, GitHub Actions — webhook → agent run | 🟡 Интересно |
| **REST API / SDK** | ❌ Нет (только CLI) | ✅ REST API + Python SDK + TypeScript SDK | 🟡 Позже |
| **SKILL.md** | ✅ Ролевые .md файлы | ✅ SKILL.md (агенты запускаются из skill definitions: `owner/repo:skill-name`) | ✅ Паритет |
| **MCP-протокол** | ❌ Нет | ✅ Per-run MCP-конфигурация (warp shared / stdio / remote SSE) | 🟡 Позже |
| **Planning (LLM)** | ❌ Нет | ✅ Agent Planning — LLM генерирует план, пользователь редактирует, agent выполняет | 🟡 Интересно |
| **Codebase Context (semantic indexing)** | ❌ Нет | ✅ Semantic indexing Git-tracked файлов для контекста агента | 🟡 Интересно |
| **Agent Profiles & Permissions** | ❌ Нет | ✅ Профили с уровнями автономии + command denylist | 🟡 Позже |
| **Параллельные agents** | ❌ Последовательное выполнение цепочек | ✅ Unlimited parallel cloud agents (cloud-native) | 🟡 Интересно |
| **Observability / Audit** | ✅ JSONL audit trail | ✅ Run tracking (run_id, state, timestamps, session_link) + Web dashboard | ✅ Паритет (разный подход) |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❌ Проприетарный SaaS (архитектура неизвестна) | ✅ У нас лучше |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Cloud API (нет доступа к внутренней архитектуре) | ✅ У нас лучше |
| **Notification system** | ❌ Нет | ✅ In-app (toast, mailbox, tab indicators) + desktop alerts | 🟢 Не берём |
| **Web Dashboard** | ❌ Нет | ✅ oz.warp.dev — visual management | 🟢 Не берём |
| **Компьютерное использование (Computer Use)** | ❌ Нет | ✅ Desktop GUI interaction (screenshots, clicks, typing) | 🟢 Не берём |
| **Computer Use (Full Terminal Use)** | ❌ Нет | ✅ Agent управляет интерактивными терминальными приложениями | 🟢 Не берём |
| **Web Search** | ❌ Нет | ✅ Agent может искать в интернете | 🟢 Не берём |

---

## 3. Что полезно взять и почему

### 3.1 🟡 Cron-расписания (Scheduled Agents)

**Что у них:** Полноценный cron-based планировщик запуска агентов через CLI, API и Web:

```bash
oz schedule create \
  --name "Weekly dependency updates" \
  --cron "0 10 * * 1" \
  --environment env_abc123 \
  --prompt "Check for dependency updates and open a PR"
```

SDK: `client.agent.schedules.create()`, `.pause()`, `.resume()`, `.delete()`.

**Почему нам интересно:** Для CI/CD-сценариев: автоматический запуск цепочки по расписанию (ежедневный lint, еженедельный dependency update). Реализуемо через внешний cron + CLI, но встроенный scheduler — более удобный UX.

**Отличие:** У Oz расписание = повторяющийся запуск одного и того же промпта. У нас потенциально расписание = повторяющийся запуск цепочки с разными входными данными.

### 3.2 🟡 Webhook-триггеры (Slack, Linear, GitHub Actions)

**Что у них:** Интеграции преобразуют внешние события в agent runs:
- Slack: сообщение в канале → agent run
- Linear: новый issue → agent run
- GitHub Actions: workflow event → agent run

**Почему нам интересно:** Для CI/CD: push в ветку → запуск цепочки (lint → test → review). Проблема: наш оркестратор — CLI-инструмент, не web-сервис. Реализуемо через GitHub Actions + CLI, но требует обёртку.

### 3.3 🟡 Cloud Environments (Docker-based isolation)

**Что у них:** Каждый agent run выполняется в изолированном Docker-контейнере:
- Предустановленные образы (10+ языков: Go, Rust, Java, Node, Python, .NET, Ruby, Web)
- Клонирование GitHub-репозиториев
- Setup-команды
- Secrets management (API keys)
- `-agents` варианты образов с предустановленными Claude Code, Codex, Gemini CLI

**Почему нам интересно:** Полная изоляция + воспроизводимость. Для production CI/CD — критически важно. Мы уже заимствовали паттерн из Docker Agent + Codex (iptables + Docker). Oz предлагает более «упакованное» решение (managed).

### 3.4 🟡 REST API / SDK

**Что у них:** Полноценный REST API (`https://app.warp.dev/api/v1`) с типизированными SDK:
- `POST /agent/run` — запуск агента
- `GET /agent/runs/{runId}` — статус
- `GET /agent/runs` — список с фильтрами
- `POST /agent/runs/{runId}/cancel` — отмена

**Почему нам интересно:** Для программного управления цепочками из других систем (CI/CD, Slack bot, IDE extension). Но для CLI-инструмента это P3: сперва нужно убедиться, что оркестратор работает автономно.

### 3.5 🟡 Planning (LLM-генерация плана)

**Что у них:** Агент может превратить запрос в редактируемый пошаговый план:
- LLM генерирует plan
- Пользователь редактирует
- Агент выполняет план step-by-step

**Почему нам интересно:** Похож на наш DynamicChainResolver, но с интерактивным участием человека. Для автономного режима — не подходит (требует интерактивности). Для интерактивного — interesting R&D.

### 3.6 🟡 Codebase Context (Semantic Indexing)

**Что у них:** Автоматическая семантическая индексация Git-отслеживаемых файлов для обогащения контекста агента. Агент «понимает» структуру кодовой базы без явного указания файлов.

**Почему нам интересно:** Для длинных цепочек с множеством шагов — обогащение контекста агента знанием о проекте. Но это уровень runner'а (как Codex с AGENTS.md), не оркестратора.

---

## 4. Что НЕ берём и почему

### 4.1 🟢 Cloud SaaS-модель

Oz — полностью облачный SaaS-продукт. Agent runs выполняются на серверах Warp, управление через Web Dashboard. Task-orchestrator — CLI-first утилита, запускаемая локально или в CI. Разные парадигмы: managed cloud vs. self-hosted CLI.

### 4.2 🟢 Unlimited Parallel Cloud Agents

Oz позволяет запускать неограниченное количество параллельных агентов в облаке. Это cloud-native feature, недоступная в CLI-контексте. Наш оркестратор выполняет одну цепочку за раз (последовательно).

### 4.3 🟢 Web Dashboard (oz.warp.dev)

Визуальный интерфейс для управления runs, schedules, интеграциями — отличный UX, но не входит в scope task-orchestrator. Наш целевой сценарий: YAML → CLI → результат.

### 4.4 🟢 Computer Use / Full Terminal Use

Oz позволяет агентам управлять GUI (скриншоты, клики) и интерактивными терминальными приложениями. Это расширяет возможности агентов за пределы text-based interaction, но не относится к оркестрации цепочек.

### 4.5 🟢 Notification System (In-App + Desktop)

Система уведомлений (toast, mailbox, tab indicators, desktop alerts) — great для интерактивного использования, но irrelevant для нашего CLI pipeline. В CI/CD — достаточно exit code и JSONL audit trail.

### 4.6 🟢 Slack / Linear / GitHub Actions Integrations

Готовые интеграции с внешними системами — удобные для конечного пользователя, но добавляют зависимость от конкретных сервисов. Для нашего CLI-orchestrator достаточно CLI-вызова из любого CI/CD pipeline.

### 4.7 🟢 Multi-Model Selection

Oz позволяет выбирать LLM-модель (Claude Sonnet/Opus, GPT-4, Gemini) для каждого run. У нас это реализовано через runner concept: каждый runner = конкретный AI-ассистент (pi, codex). Разный уровень: Oz выбирает model, мы выбираем runner.

---

## 5. Сводка рекомендаций

| Фича | Приоритет | Обоснование |
|---|---|---|
| Chain orchestration (YAML chains) | ✅ Уже есть | Core-функциональность task-orchestrator, отсутствует у Oz |
| Retry + Circuit Breaker | ✅ Уже есть | Устойчивость при сбоях, отсутствует у Oz |
| Quality Gates | ✅ Уже есть | Автоматическая проверка кода, отсутствует у Oz |
| Budget control | ✅ Уже есть | Предотвращение runaway spending, отсутствует у Oz |
| Fix iterations | ✅ Уже есть | Closed-loop цикл разработки, отсутствует у Oz |
| Cron-расписания | 🟡 P3 | Для автоматического запуска цепочек по расписанию. Реализуемо через внешний cron + CLI |
| Webhook-триггеры | 🟡 P3 | Для CI/CD интеграции. Реализуемо через GitHub Actions + CLI |
| Cloud Environments (Docker) | 🟡 P3 | Изоляция + воспроизводимость. Уже исследовано через Docker Agent + Codex |
| REST API / SDK | 🟡 P3 | Для программного управления. Сперва — стабилизация CLI |
| Planning (LLM) | 🟡 P3 | R&D: LLM-генерация динамических цепочек. Требует интерактивности |
| Codebase Context | 🟡 P3 | Уровень runner'а, не оркестратора |
| Cloud SaaS | 🟢 — | Разная парадигма (cloud vs. CLI) |
| Unlimited parallel agents | 🟢 — | Cloud-native, не CLI |
| Web Dashboard | 🟢 — | Не scope task-orchestrator |
| Computer Use / Full Terminal Use | 🟢 — | Не относится к оркестрации |
| Notifications | 🟢 — | CLI exit code + JSONL достаточно |
| Integrations (Slack/Linear/GitHub Actions) | 🟢 — | Достаточно CLI-вызова из CI/CD |
| Multi-model selection | 🟢 — | Реализовано через runner concept |

---

## 6. Указатель источников для деталей

- [warp.dev/oz](https://www.warp.dev/oz) — страница продукта Oz
- [docs.warp.dev/agent-platform](https://docs.warp.dev/agent-platform) — документация Agent Platform
- [docs.warp.dev/agent-platform/cloud-agents/overview](https://docs.warp.dev/agent-platform/cloud-agents/overview) — Cloud Agents Overview
- [docs.warp.dev/agent-platform/cloud-agents/platform](https://docs.warp.dev/agent-platform/cloud-agents/platform) — Oz Platform Overview (CLI, API/SDK, orchestration, environments)
- [docs.warp.dev/agent-platform/cloud-agents/environments](https://docs.warp.dev/agent-platform/cloud-agents/environments) — Cloud Environments (Docker-окружения)
- [docs.warp.dev/reference/api-and-sdk](https://docs.warp.dev/reference/api-and-sdk) — REST API & SDK Reference
- [github.com/warpdotdev/oz-sdk-python](https://github.com/warpdotdev/oz-sdk-python) — Python SDK (15 звёзд, 5 форков)
- [github.com/warpdotdev/oz-sdk-typescript](https://github.com/warpdotdev/oz-sdk-typescript) — TypeScript SDK
- [github.com/warpdotdev/oz-skills](https://github.com/warpdotdev/oz-skills) — Коллекция SKILL.md для Oz
- [github.com/warpdotdev/oz-dev-environments](https://github.com/warpdotdev/oz-dev-environments) — Docker-образы для Oz environments
- [github.com/warpdotdev/warp](https://github.com/warpdotdev/warp) — Warp terminal (56K+ звёзд, AGPL v3)
- [oz.warp.dev](https://oz.warp.dev) — Oz Web Dashboard
- [agentskills.io](https://agentskills.io) — Спецификация SKILL.md

📚 **Источники:**
1. [warp.dev/oz](https://www.warp.dev/oz) — продукт Oz
2. [docs.warp.dev](https://docs.warp.dev/agent-platform) — документация Warp Agent Platform
3. [github.com/warpdotdev/oz-sdk-python](https://github.com/warpdotdev/oz-sdk-python) — Python SDK
4. [github.com/warpdotdev/oz-dev-environments](https://github.com/warpdotdev/oz-dev-environments) — Docker-образы
5. [github.com/warpdotdev/warp](https://github.com/warpdotdev/warp) — Warp terminal (open-source)
