# Исследование: GitHub Copilot Agent Mode / Cloud Agent (проприетарный, cloud)

> **Проект:** [github.com/features/copilot](https://github.com/features/copilot)
> **Дата анализа:** 2026-04-22
> **Язык:** Закрытый исходный код (cloud SaaS, runs on GitHub infrastructure)
> **Лицензия:** Проприетарный (GitHub / Microsoft)
> **Аналитик:** Технический писатель (Гермиона)

---

## 1. Обзор проекта

GitHub Copilot Agent Mode — следующая эволюция GitHub Copilot, превращающая AI-ассистента из автодополняющего инструмента в автономного агента, способного самостоятельно выполнять многошаговые задачи разработки. Agent Mode работает как в IDE (VS Code, Visual Studio, JetBrains), так и в cloud-среде GitHub — через Copilot Cloud Agent (Issue → Agent → PR → Review → Merge), Copilot CLI (с командой `/fleet` для параллельного выполнения), Copilot Spark (генерация и деплой приложений), а также Copilot SDK для программного доступа.

GitHub Copilot Cloud Agent (официальный термин GitHub, ранее иногда упоминался как «Agent Mode в cloud») — облачный агент, запускаемый по событиям (Issue → Agent → PR → Review → Merge). Cloud Agent предоставляет agent management, access management, policy management, audit logging, hooks, MCP-интеграцию и настраиваемый firewall.

> **Примечание:** Архитектура восстановлена по официальной документации GitHub Docs (актуальная на 2026-04-22). Некоторые детали реализации (устройство sandbox, внутренняя архитектура) — проприетарные и могут отличаться от описанных.

Copilot Agent Mode / Cloud Agent **не является** фреймворком оркестрации в классическом смысле. Это **проприетарный cloud-сервис**, встроенный в экосистему GitHub. В отличие от task-orchestrator, Copilot не поддерживает декларативные цепочки шагов (chains), retry-механизмы с backoff, circuit breaker, бюджетный контроль или quality gates. Однако его модель интеграции AI-агентов в development workflow (Issue → Plan → Code → Test → PR), подход к управлению агентами и sandboxed-выполнение представляют значительный интерес.

### Архитектура

GitHub Copilot Agent — проприетарный продукт. Архитектура восстановлена по официальной документации GitHub Docs (docs.github.com/en/copilot), GitHub Blog, GitHub Universe 2024–2025 announcements и наблюдаемому поведению. Детали реализации sandbox и внутренних механизмов — предположительные (GitHub не раскрывает их публично).

```
github.com Cloud platform (GitHub infrastructure)
 cloud-agent/ Copilot Cloud Agent (официальный термин)
 agent-loop Core: LLM → tool call → observation → LLM → ...
 tools/ Встроенные инструменты агента
 file-operations Чтение/запись/редактирование файлов в репозитории
 terminal-commands Выполнение shell-команд в изолированной среде
 search Поиск по коду (GitHub Search API)
 browser Веб-браузер для поиска документации
 edit Точечное редактирование файлов
 sandbox/ Изолированная среда выполнения (подробности не раскрыты)
 container-based-isolation Изоляция выполнения (предположительно container-based)
 firewall Настраиваемый firewall (domain/URL allowlist на уровне org/repo)
 context/ Управление контекстом
 repository-context Автоматический анализ структуры репозитория
 issue-context Контекст из Issue / PR description
 copilot-memory Агент сохраняет знания о кодовой базе для будущих сессий
 custom-instructions Многоуровневые инструкции (personal / repo / path-specific / org)
 spaces Copilot Spaces — коллаборативный контекст
 session/ Управление сессиями
 agent-session Cloud-based agent session (не локальный)
 resume Возможность возобновления сессии
 hooks/ Pre/post execution hooks
 pre-tool-use Хук перед выполнением инструмента
 post-tool-use Хук после выполнения инструмента
 user-prompt-submitted Хук при отправке промпта
 integration/ Интеграция с GitHub
 issue-triggered Агент запускается по Issue / @copilot mention
 pr-review Автоматический review PR агентом
 actions-integration Запуск агента из GitHub Actions
 checks Agent results как GitHub Check
 copilot-cli/ Copilot CLI — терминальный агент
 fleet /fleet — параллельное выполнение задач
 custom-agents Пользовательские агенты для CLI
 plugins CLI-плагины (marketplace)
 autonomous-tasks Автономное выполнение задач
 spark/ Copilot Spark — генерация и деплой приложений
 prompt-to-app Промпт → готовое приложение
 deploy Деплой из CLI
 copilot-sdk/ Copilot SDK — программный доступ
 hooks Pre/post hooks, session lifecycle
 mcp-servers MCP-серверы через SDK
 session-persistence Сохранение сессий
 streaming Streaming events (OpenTelemetry)
 byok Bring Your Own Key (пользовательские модели)
 custom-skills Пользовательские навыки
 management/ Управление (enterprise/org)
 agent-management Управление агентами (cloud agent)
 access-management Управление доступом к агентам
 policies Org-level политики (permissions, scopes)
 audit-logs Audit trail действий агентов
 mcp-servers Model Context Protocol серверы
 custom-agents Пользовательские агенты для cloud agent
 firewall-config Настройка firewall (org/repo level)
 monitor-agentic-activity Мониторинг активности агентов
```

### Ключевые характеристики

| Характеристика | Значение |
| --- | --- |
| **Тип** | Cloud SaaS: AI-агент, встроенный в GitHub platform |
| **Модель выполнения** | Agent loop (LLM → tool call → observation → LLM → ...) в cloud sandbox |
| **State management** | Cloud-managed (GitHub infrastructure), session-based |
| **Провайдер** | Мультимодельный: OpenAI, Anthropic, Google Gemini, и другие (выбор модели через Copilot; конкретные модели меняются со временем) |
| **Расширяемость** | MCP-серверы, custom instructions (4 уровня), hooks, custom agents, Copilot SDK, GitHub Models |
| **Интерфейс** | IDE-интеграция (VS Code, Visual Studio, JetBrains) + Web (github.com) + API |
| **Платформы** | Cloud (GitHub infrastructure), sandboxed containers |

### Основные компоненты

| Компонент | Назначение |
| --- | --- |
| Cloud Agent | Автономный многошаговый агент: получает задачу → планирует → выполняет (edit files, run commands, search) → завершает. Официальный термин: «Copilot cloud agent» |
| Copilot CLI | Терминальный агент с командой `/fleet` для параллельного выполнения задач, custom agents, plugins |
| Copilot Spark | Генерация и деплой приложений из промпта (prompt → app → deploy) |
| Copilot SDK | Программный доступ к Copilot: hooks, MCP, session persistence, BYOK, OpenTelemetry |
| Sandbox | Изолированная среда для выполнения агентом shell-команд и изменения файлов (подробности реализации не раскрыты) |
| Custom Instructions | Многоуровневые инструкции: personal, `.github/copilot-instructions.md` (repo), `*.instructions.md` (path-specific), org-level |
| Copilot Memory | Агент запоминает факты о кодовой базе и использует их в будущих сессиях |
| Copilot Spaces | Коллаборативный контекст: общие пространства для совместной работы с Copilot |
| MCP Integration | Model Context Protocol серверы для расширения возможностей агента (cloud agent + CLI + SDK) |
| Hooks | Pre/post execution hooks: pre-tool-use, post-tool-use, user-prompt-submitted, session lifecycle |
| Custom Agents | Пользовательские агенты для cloud agent и CLI |
| Agent Firewall | Настраиваемый firewall: domain/URL allowlist на уровне org и repo |
| GitHub Actions Integration | Запуск агента из CI/CD pipeline, результаты как Checks |
| Agent Management | Управление агентами: access management, monitor agentic activity, enable/block cloud agent |

---

## 2. Возможности оркестрации — обзор

| Функция | GitHub Copilot Agent HQ |
| --- | --- |
| **Бюджетный контроль** | ⚠️ Только org-level rate limits (Copilot usage limits), без step-level контроля |
| **Fallback routing** | ⚠️ Multi-model (GPT-4, Claude, Gemini), но routing не конфигурируется пользователем |
| **Audit Trail (JSONL)** | ✅ Agent HQ audit log (все действия агентов логируются) |
| **Ролевые промпты** | ⚠️ Custom instructions (.github/copilot-instructions.md) — единый файл, не ролевой |
| **Multiple runners** | ✅ Multi-model (GPT-4, Claude, Gemini через GitHub Models) |
| **Sandboxed execution** | ✅ Docker-container sandbox (изолированная среда) |
| **Issue → Agent → PR workflow** | ✅ Полная интеграция: Issue → Copilot → Plan → Code → PR → Review |
| **MCP-протокол** | ✅ Полная поддержка MCP (custom tools, knowledge bases) |
| **Multi-model routing** | ✅ Multi-model через GitHub Models marketplace |
| **Policy engine** | ✅ Agent HQ: org-level policies, permissions, scopes |
| **Knowledge base integration** | ✅ Подключение внешних документаций для контекста агента |
| **GitHub Actions integration** | ✅ copilot-setup-steps, agent как CI/CD step |
| **Custom instructions** | ✅ .github/copilot-instructions.md (аналог) |
| **Web search / browser** | ✅ Агент может искать информацию в интернете |
| **IDE-интеграция** | ✅ VS Code, Visual Studio, JetBrains, Web |
| **Cloud execution** | ✅ Полностью cloud-based (GitHub infrastructure) |

---

## 3. Оркестрационные возможности

### 3.1 🟡 Issue → Agent → PR Workflow — интеграция с development lifecycle

**Что у них:** GitHub Copilot Agent интегрирован в полный development workflow:

```
Issue created / @copilot mentioned
 → Copilot Workspace: generates plan
 → User reviews/approves plan
 → Agent Mode: executes plan step by step
 → Creates branch, edits files, runs tests
 → Opens Pull Request
 → Automatic code review by Copilot
 → Human review & merge
```

**Механика:**
- **Issue-triggered:** Агент может быть запущен напрямую из GitHub Issue (через `@copilot` mention или назначение Copilot assignee)
- **Plan → Execute:** Workspace генерирует план, пользователь подтверждает, агент выполняет
- **PR creation:** Агент автоматически создаёт branch, вносит изменения, открывает PR
- **Code review:** Copilot автоматически ревьюит PR (review comments, suggestions)
- **Checks integration:** Результаты выполнения агента отображаются как GitHub Checks

**Оркестрационная значимость:** Паттерн Issue → Plan → Execute → PR — пример event-driven запуска автономных агентов с контрольными точками (plan approval, PR review). Ключевое ограничение: workflow линейный, без условного ветвления или параллельных шагов.

### 3.2 🟡 Sandboxed Execution — изолированная среда для агентских действий

**Что у них:** Copilot Agent выполняет shell-команды и изменяет файлы в Docker-container sandbox:

```
Host (developer machine / GitHub cloud)
 └─ Docker container (sandbox)
 ├─ File system: clone of repository (read-write)
 ├─ Network: restricted (allowlist-based)
 ├─ Tools: terminal, file edit, search, browser
 └─ Lifecycle: created per session, destroyed after
```

**Механика:**
- Agent запускается в изолированном Docker-контейнере
- Repository клонируется внутрь контейнера
- Shell-команды выполняются только внутри sandbox
- Сетевой доступ ограничен (whitelist URLs)
- После завершения сессии sandbox уничтожается
- Изменения коммитятся в branch только после approval

**Оркестрационная значимость:** Для автономного выполнения цепочек (особенно в CI/CD) — критически важная безопасность.

### 3.3 🟡 Policy Engine — организационные политики для агентов

**Что у них:** Agent HQ позволяет определять org-level политики для Copilot-агентов:

```
Organization Settings
 ├─ Allowed repositories (scope)
 ├─ Allowed models (model selection)
 ├─ Permission levels
 │ ├─ Read-only (анализ без изменений)
 │ ├─ Edit with approval (предлагает изменения, требует approval)
 │ └─ Full access (автономное выполнение)
 ├─ Network policies (allowed URLs, blocked domains)
 ├─ Tool restrictions (запрет определённых shell-команд)
 └─ Audit requirements (логирование уровня compliance)
```

**Механика:**
- Политики определяются на уровне организации (GitHub Org)
- Применяются ко всем Copilot-агентам в организации
- Интеграция с GitHub governance (branch protection, required reviews)
- Audit log всех действий агента для compliance

**Оркестрационная значимость:** Org-level политики задают периметр допустимых действий агента. Это аналог runner-scoping и command-allowlisting. Ограничение: политики глобальные для организации, нет per-chain или per-step конфигурации — все агенты подчиняются одним правилам.

### 3.4 🟡 Knowledge Base Integration — обогащение контекста агента

**Что у них:** Copilot Agent может подключать внешние документации для обогащения контекста:

```
Agent context sources:
 ├─ Repository code (auto-indexed)
 ├─ .github/copilot-instructions.md (custom instructions)
 ├─ Connected knowledge bases (docs sites, wikis)
 ├─ GitHub Issues / PRs (project context)
 └─ MCP server data (external tools and data)
```

**Механика:**
- Knowledge bases подключаются через Agent HQ dashboard
- Поддерживаются docs sites (Mintlify, Docusaurus, ReadTheDocs)
- Индексация документации через GitHub search infrastructure
- Агент автоматически подтягивает релевантную документацию при выполнении задачи
- MCP-серверы предоставляют доступ к external data (APIs, databases)

**Оркестрационная значимость:** Для длинных цепочек с разными этапами (анализ → кодирование → тестирование) — доступ к актуальной документации библиотек может значительно повысить качество генерируемых артефактов на каждом шаге.

### 3.5 🟡 Copilot Workspace: Plan → Review → Execute — паттерн человеко-машинного взаимодействия

**Что у них:** Copilot Workspace реализует трёхфазный паттерн:

```
Phase 1: PLAN
 Issue description → LLM generates step-by-step plan
 Plan includes: files to change, commands to run, tests to verify

Phase 2: REVIEW
 User reviews plan in web UI
 Can modify steps, add constraints, reorder
 Explicit approval before execution

Phase 3: EXECUTE
 Agent executes approved plan step by step
 Each step: edit files → run commands → verify
 User can intervene at any step
```

**Механика:**
- Plan — структурированный список шагов с зависимостями
- Review — визуальный diff-based UI для проверки плана
- Execute — пошаговое выполнение с визуализацией прогресса
- Intervention — пользователь может остановить, изменить, перезапустить

**Оркестрационная значимость:** Паттерн «Plan → Review → Execute» — пример dynamic chain с human-in-the-loop контрольной точкой между планированием и выполнением.

### 3.6 🟡 Multi-model marketplace — выбор модели под задачу

**Что у них:** GitHub Copilot поддерживает несколько LLM-провайдеров через GitHub Models:

```
GitHub Models marketplace:
 ├─ OpenAI: GPT-4o, GPT-4.1, o1, o3-mini
 ├─ Anthropic: Claude Sonnet 4, Claude Opus 4
 ├─ Google: Gemini 2.0 Flash, Gemini 2.5 Pro
 ├─ Meta: Llama 3.3
 ├─ Mistral: Mistral Large
 └─ DeepSeek: DeepSeek-V3
```

**Механика:**
- Пользователь выбирает модель в настройках Copilot или per-request
- GitHub Models API — единый endpoint для всех провайдеров
- Модель можно менять mid-conversation
- Enterprise: org-level model policies (разрешить/запретить определённые модели)

**Оркестрационная значимость:** GitHub Models — пример unified API поверх разных провайдеров. Для оркестрации это означает возможность выбора оптимальной модели под тип задачи (анализ, кодирование, ревью) без смены интеграции.

---

## 4. Прочие возможности (вне оркестрации)

### 4.1 🟢 Cloud-only execution (GitHub lock-in)

Copilot Agent работает **только** на инфраструктуре GitHub (cloud). Task-orchestrator — локальный CLI pipeline. Полная зависимость от cloud-провайдера противоречит нашей архитектуре (локальный контроль, offline capability).

### 4.2 🟢 IDE-интеграция (VS Code, JetBrains)

Copilot Agent встроен в IDE. Task-orchestrator работает как Symfony Console CLI. Разные точки входа и парадигмы взаимодействия. IDE-интеграция — задача отдельных инструментов (extensions), а не оркестратора.

### 4.3 🟢 Web Search / Browser Tool

Copilot Agent может искать информацию в интернете через встроенный браузер.

### 4.4 🟢 Copilot Workspace UI (Web-based plan editor)

Workspace — визуальный web-инструмент для планирования. Task-orchestrator — YAML-based configuration. Разные подходы к определению задач: GUI vs. code-as-config.

### 4.5 🟢 .github/copilot-instructions.md (проприетарный формат)

Custom instructions — проприетарный формат GitHub. Мы используем AGENTS.md (универсальный стандарт, поддерживаемый несколькими инструментами). Нет смысла добавлять зависимость от проприетарного формата.

### 4.6 🟢 GitHub Actions integration (tightly coupled)

Запуск Copilot Agent из GitHub Actions — проприетарная интеграция с конкретной CI/CD платформой. Task-orchestrator — platform-agnostic CLI. CI/CD интеграция — через shell, а не через platform-specific API.

### 4.7 🟢 Enterprise dashboard / fleet management

Agent HQ dashboard для управления fleet-ом агентов — enterprise-фича SaaS-продукта. Для PHP-bundle это overengineering: управление через YAML config + CLI достаточно.

---

## 5. Сводка по оркестрации

| Возможность | Статус в продукте | Описание |
| --- | --- | --- |
| Issue → Agent → PR workflow pattern | 🟡 P2 | Паттерн интеграции chain в development lifecycle: webhook-triggered chains, PR review chains |
| Sandboxed execution | 🟡 P2 | Docker-container изоляция для безопасного выполнения shell-команд в CI/CD |
| Policy engine (permissions, scopes) | 🟡 P2 | Ограничение runner'ов, команд и scope файлов для автономного выполнения |
| Plan → Review → Execute (human-in-the-loop) | 🟡 P3 | LLM-generated dynamic chains с человеко-машинным подтверждением |
| Knowledge base integration | 🟡 P3 | Обогащение контекста шагов релевантной документацией |
| MCP support | 🟡 P3 | Протокол расширения возможностей через внешние серверы |
| Cloud execution | 🟢 — | Полная зависимость от GitHub infrastructure |
| IDE integration | 🟢 — | Задача extensions, а не оркестратора |
| Web search / browser | 🟢 — | Задача shell-команд и runner'ов |
| Workspace UI | 🟢 — | GUI vs. code-as-config (YAML) |
| Fleet management dashboard | 🟢 — | Overengineering для PHP-bundle |
| GitHub Actions integration | 🟢 — | Platform-specific, используем CLI |

---

## 6. Указатель источников для деталей

- [GitHub Docs: Copilot Agent Mode](https://docs.github.com/en/copilot/concepts/collapse-or-expand-agent-mode) — официальная документация: Agent Mode, инструменты, sandbox
- [GitHub Blog: Introducing GitHub Copilot Agent Mode](https://github.blog/ai-and-ml/github-copilot/introducing-github-copilot-agent-mode/) — анонс Agent Mode, возможности, сравнение с edit mode
- [GitHub Blog: GitHub Copilot Workspace](https://github.blog/news-insights/product-news/github-copilot-workspace/) — Workspace: Issue → Plan → Code workflow
- [GitHub Docs: Custom Instructions for Copilot](https://docs.github.com/en/copilot/customizing-copilot/adding-repository-custom-instructions-for-github-copilot) — custom instructions, .github/copilot-instructions.md
- [GitHub Docs: GitHub Models](https://docs.github.com/en/github-models) — multi-model marketplace, unified API, model selection

---

📚 **Источники:**
1. [docs.github.com/en/copilot](https://docs.github.com/en/copilot) — официальная документация GitHub Copilot
2. [github.blog/ai-and-ml/github-copilot](https://github.blog/ai-and-ml/github-copilot/) — GitHub Blog: Copilot announcements, Agent Mode, Workspace
3. [docs.github.com/en/github-models](https://docs.github.com/en/github-models) — GitHub Models marketplace, multi-model support
4. [github.com/features/copilot](https://github.com/features/copilot) — landing page: features overview, pricing
5. [docs.github.com/en/copilot/customizing-copilot](https://docs.github.com/en/copilot/customizing-copilot/) — custom instructions, MCP, extensions
