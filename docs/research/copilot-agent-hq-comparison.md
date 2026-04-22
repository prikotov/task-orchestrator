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

GitHub Copilot Agent — проприетарный продукт. Архитектура восстановлена по официальной документации GitHub Docs (docs.github.com/en/copilot), GitHub Blog, GitHub Universe 2024–2025 announcements и наблюдаемому поведению. Детали реализации sandbox и внутренних механизмов — предположительные ( GitHub не раскрывает их публично).

```
github.com                                   Cloud platform (GitHub infrastructure)
  cloud-agent/                              Copilot Cloud Agent (официальный термин)
    agent-loop                               Core: LLM → tool call → observation → LLM → ...
    tools/                                   Встроенные инструменты агента
      file-operations                        Чтение/запись/редактирование файлов в репозитории
      terminal-commands                      Выполнение shell-команд в изолированной среде
      search                                 Поиск по коду (GitHub Search API)
      browser                                Веб-браузер для поиска документации
      edit                                   Точечное редактирование файлов
    sandbox/                                 Изолированная среда выполнения (подробности не раскрыты)
      container-based-isolation              Изоляция выполнения (предположительно container-based)
      firewall                               Настраиваемый firewall (domain/URL allowlist на уровне org/repo)
    context/                                 Управление контекстом
      repository-context                     Автоматический анализ структуры репозитория
      issue-context                          Контекст из Issue / PR description
      copilot-memory                         Агент сохраняет знания о кодовой базе для будущих сессий
      custom-instructions                    Многоуровневые инструкции (personal / repo / path-specific / org)
      spaces                                 Copilot Spaces — коллаборативный контекст
    session/                                 Управление сессиями
      agent-session                          Cloud-based agent session (не локальный)
      resume                                 Возможность возобновления сессии
    hooks/                                   Pre/post execution hooks
      pre-tool-use                           Хук перед выполнением инструмента
      post-tool-use                          Хук после выполнения инструмента
      user-prompt-submitted                  Хук при отправке промпта
    integration/                             Интеграция с GitHub
      issue-triggered                        Агент запускается по Issue / @copilot mention
      pr-review                              Автоматический review PR агентом
      actions-integration                    Запуск агента из GitHub Actions
      checks                                 Agent results как GitHub Check
  copilot-cli/                               Copilot CLI — терминальный агент
    fleet                                    /fleet — параллельное выполнение задач
    custom-agents                            Пользовательские агенты для CLI
    plugins                                  CLI-плагины (marketplace)
    autonomous-tasks                         Автономное выполнение задач
  spark/                                     Copilot Spark — генерация и деплой приложений
    prompt-to-app                            Промпт → готовое приложение
    deploy                                   Деплой из CLI
  copilot-sdk/                               Copilot SDK — программный доступ
    hooks                                    Pre/post hooks, session lifecycle
    mcp-servers                              MCP-серверы через SDK
    session-persistence                     Сохранение сессий
    streaming                                Streaming events (OpenTelemetry)
    byok                                     Bring Your Own Key (пользовательские модели)
    custom-skills                            Пользовательские навыки
  management/                                Управление (enterprise/org)
    agent-management                         Управление агентами (cloud agent)
    access-management                        Управление доступом к агентам
    policies                                 Org-level политики (permissions, scopes)
    audit-logs                               Audit trail действий агентов
    mcp-servers                              Model Context Protocol серверы
    custom-agents                            Пользовательские агенты для cloud agent
    firewall-config                          Настройка firewall (org/repo level)
    monitor-agentic-activity                 Мониторинг активности агентов
```

### Ключевые характеристики

| Характеристика | Значение |
|---|---|
| **Тип** | Cloud SaaS: AI-агент, встроенный в GitHub platform |
| **Модель выполнения** | Agent loop (LLM → tool call → observation → LLM → ...) в cloud sandbox |
| **State management** | Cloud-managed (GitHub infrastructure), session-based |
| **Провайдер** | Мультимодельный: OpenAI, Anthropic, Google Gemini, и другие (выбор модели через Copilot; конкретные модели меняются со временем) |
| **Расширяемость** | MCP-серверы, custom instructions (4 уровня), hooks, custom agents, Copilot SDK, GitHub Models |
| **Интерфейс** | IDE-интеграция (VS Code, Visual Studio, JetBrains) + Web (github.com) + API |
| **Платформы** | Cloud (GitHub infrastructure), sandboxed containers |

### Основные компоненты

| Компонент | Назначение |
|---|---|
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

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | Task Orchestrator | GitHub Copilot Agent HQ | Статус |
|---|---|---|---|
| **Цепочки шагов (chains)** | ✅ YAML chains, статические и динамические | ⚠️ Agent loop — один непрерывный поток, Workspace — linear plan steps | ✅ У нас есть |
| **Retry с backoff** | ✅ RetryingAgentRunner | ⚠️ Встроенный retry на уровне API (transparent для пользователя) | ✅ У нас есть |
| **Circuit Breaker** | ✅ CircuitBreakerAgentRunner | ❌ Нет (cloud-сервис управляет ошибками внутренне) | ✅ У нас есть |
| **Quality Gates** | ✅ Shell-команды как проверки | ⚠️ Workspace имеет шаг verify, но не конфигурируемый извне | ✅ У нас есть |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based лимиты) | ⚠️ Только org-level rate limits (Copilot用量 limits), без step-level контроля | ✅ У нас лучше |
| **Итерационные циклы (fix_iterations)** | ✅ Группа шагов с max_iterations | ⚠️ Agent Mode повторяет попытки при ошибках (implicit loop), но не конфигурируемый | ✅ У нас есть |
| **Fallback routing** | ✅ Per-step fallback runner | ⚠️ Multi-model (GPT-4, Claude, Gemini), но routing не конфигурируется пользователем | ✅ У нас лучше |
| **Audit Trail (JSONL)** | ✅ JsonlAuditLogger | ✅ Agent HQ audit log (все действия агентов логируются) | ✅ Паритет |
| **Ролевые промпты** | ✅ .md файлы (18+ ролей) | ⚠️ Custom instructions (.github/copilot-instructions.md) — единый файл, не ролевой | ✅ У нас лучше |
| **Multiple runners** | ✅ Pi + Codex (через interface) | ✅ Multi-model (GPT-4, Claude, Gemini через GitHub Models) | ✅ Паритет |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❌ Закрытый cloud-сервис | ✅ У нас есть |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Закрытая архитектура | ✅ У нас есть |
| **YAML-конфигурация** | ✅ Chains + roles в YAML | ❌ Конфигурация через UI/GitHub Settings, не декларативная | ✅ У нас есть |
| **Sandboxed execution** | ❌ Нет (shell-команды на хосте) | ✅ Docker-container sandbox (изолированная среда) | 🟡 Интересно |
| **Issue → Agent → PR workflow** | ❌ Нет (CLI pipeline) | ✅ Полная интеграция: Issue → Copilot → Plan → Code → PR → Review | 🟡 Интересно |
| **MCP-протокол** | ❌ Нет | ✅ Полная поддержка MCP (custom tools, knowledge bases) | 🟡 Позже |
| **Multi-model routing** | ✅ Через runner interface | ✅ Multi-model через GitHub Models marketplace | ✅ Паритет |
| **Policy engine** | ❌ Нет | ✅ Agent HQ: org-level policies, permissions, scopes | 🟡 Интересно |
| **Knowledge base integration** | ❌ Нет | ✅ Подключение внешних документаций для контекста агента | 🟡 Интересно |
| **GitHub Actions integration** | ❌ Нет | ✅ copilot-setup-steps, agent как CI/CD step | 🟡 Интересно |
| **Custom instructions** | ✅ AGENTS.md + role .md | ✅ .github/copilot-instructions.md (аналог) | ✅ Паритет |
| **Web search / browser** | ❌ Нет | ✅ Агент может искать информацию в интернете | 🟢 Не берём |
| **IDE-интеграция** | ❌ Нет (CLI only) | ✅ VS Code, Visual Studio, JetBrains, Web | 🟢 Не берём |
| **Cloud execution** | ❌ Нет (local CLI) | ✅ Полностью cloud-based (GitHub infrastructure) | 🟢 Не берём |

---

## 3. Что полезно взять и почему

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

**Почему нам интересно:** Для task-orchestrator — паттерн интеграции AI-цепочки в development workflow. Сейчас task-orchestrator работает как standalone CLI pipeline. Концепция «событие → plan → execute → report» может быть применена для:
- Webhook-triggered chains (GitHub webhook → chain execution)
- Automatic PR review chains (PR opened → quality gate → review comments)
- Issue-driven development (Issue → analyze → implement → PR)

**Отличие от нашей реализации:**
- У нас: CLI pipeline, пользователь запускает вручную
- У них: full lifecycle integration, event-driven, GUI-based

---

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

**Почему нам интересно:** Для автономного выполнения цепочек (особенно в CI/CD) — критически важная безопасность. Сейчас task-orchestrator выполняет shell-команды (quality gates) на хост-системе без изоляции. Sandbox pattern позволяет:
- Безопасно выполнять произвольные команды агента
- Ограничить доступ к файловой системе
- Предотвратить unintended side effects
- Обеспечить воспроизводимость окружения

**Отличие:** У нас quality gates — shell-команды на хост-системе. Docker sandbox обеспечивает полную изоляцию.

---

### 3.3 🟡 Policy Engine — организационные политики для агентов

**Что у них:** Agent HQ позволяет определять org-level политики для Copilot-агентов:

```
Organization Settings
  ├─ Allowed repositories (scope)
  ├─ Allowed models (model selection)
  ├─ Permission levels
  │    ├─ Read-only (анализ без изменений)
  │    ├─ Edit with approval (предлагает изменения, требует approval)
  │    └─ Full access (автономное выполнение)
  ├─ Network policies (allowed URLs, blocked domains)
  ├─ Tool restrictions (запрет определённых shell-команд)
  └─ Audit requirements (логирование уровня compliance)
```

**Механика:**
- Политики определяются на уровне организации (GitHub Org)
- Применяются ко всем Copilot-агентам в организации
- Интеграция с GitHub governance (branch protection, required reviews)
- Audit log всех действий агента для compliance

**Почему нам интересно:** Для task-orchestrator, запускаемого в CI/CD или автономно — необходимость ограничивать доступные runner'ы и shell-команды. Сейчас у нас нет ограничений: любой шаг цепочки может выполнить любую shell-команду. Policy engine позволяет:
- Ограничить доступные runner'ы для определённых цепочек
- Запретить опасные команды (rm -rf, sudo, ...)
- Определить scope допустимых файлов для редактирования
- Обеспечить compliance через audit

**Отличие:** У нас нет ограничений на runner'ы и команды. Quality gates проверяют результат, но не ограничивают действия до выполнения.

---

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

**Почему нам интересно:** Для длинных цепочек с разными этапами (анализ → кодирование → тестирование) — доступ к актуальной документации библиотек может значительно повысить качество. Сейчас task-orchestrator передаёт контекст через AGENTS.md и role .md файлы. Knowledge base pattern позволяет:
- Автоматически подтягивать документацию зависимостей
- Обогащать промпт шага релевантной информацией
- Снижать hallucinations через grounding в реальной документации

**Отличие:** У нас контекст — статические файлы (AGENTS.md, role .md). У них — динамический retrieval из подключённых источников.

---

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

**Почему нам интересно:** Паттерн «Plan → Review → Execute» — это по сути `dynamic chain` с human-in-the-loop. Для task-orchestrator:
- Generation phase: LLM генерирует YAML chain из описания задачи
- Review phase: пользователь подтверждает/корректирует chain
- Execution phase: task-orchestrator выполняет утверждённую chain
- Это расширяет наши static chains → LLM-generated dynamic chains

**Отличие:** У нас chains — YAML-файлы, написанные вручную. Workspace генерирует plan через LLM и позволяет интерактивное редактирование.

---

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

**Почему нам интересно:** Подтверждает наш подход к multi-runner архитектуре (AgentRunnerInterface). GitHub Models — пример unified API поверх разных провайдеров. Для task-orchestrator:
- Per-step model selection: дешёвая модель для анализа, мощная для кодогенерации
- Model failover: если одна модель недоступна → переключение на другую
- Cost optimization: разные модели для разных типов шагов

**Отличие:** У нас multi-runner через interface. У них — cloud marketplace с unified API. Архитектурно похожие подходы.

---

## 4. Что НЕ берём и почему

### 4.1 🟢 Cloud-only execution (GitHub lock-in)

Copilot Agent работает **только** на инфраструктуре GitHub (cloud). Task-orchestrator — локальный CLI pipeline. Полная зависимость от cloud-провайдера противоречит нашей архитектуре (локальный контроль, offline capability).

### 4.2 🟢 IDE-интеграция (VS Code, JetBrains)

Copilot Agent встроен в IDE. Task-orchestrator работает как Symfony Console CLI. Разные точки входа и парадигмы взаимодействия. IDE-интеграция — задача отдельных инструментов (extensions), а не оркестратора.

### 4.3 🟢 Web Search / Browser Tool

Copilot Agent может искать информацию в интернете через встроенный браузер. В task-orchestrator web-запросы выполняются через shell-команды (curl) или runner'ы — не нужны как отдельный тип шага в ядре.

### 4.4 🟢 Copilot Workspace UI (Web-based plan editor)

Workspace — визуальный web-инструмент для планирования. Task-orchestrator — YAML-based configuration. Разные подходы к определению задач: GUI vs. code-as-config.

### 4.5 🟢 .github/copilot-instructions.md (проприетарный формат)

Custom instructions — проприетарный формат GitHub. Мы используем AGENTS.md (универсальный стандарт, поддерживаемый несколькими инструментами). Нет смысла добавлять зависимость от проприетарного формата.

### 4.6 🟢 GitHub Actions integration (tightly coupled)

Запуск Copilot Agent из GitHub Actions — проприетарная интеграция с конкретной CI/CD платформой. Task-orchestrator — platform-agnostic CLI. CI/CD интеграция — через shell, а не через platform-specific API.

### 4.7 🟢 Enterprise dashboard / fleet management

Agent HQ dashboard для управления fleet-ом агентов — enterprise-фича SaaS-продукта. Для PHP-bundle это overengineering: управление через YAML config + CLI достаточно.

---

## 5. Сводка рекомендаций

| Фича | Приоритет | Обоснование |
|---|---|---|
| Chain orchestration | ✅ Уже есть | Core-функциональность task-orchestrator |
| Retry + Circuit Breaker | ✅ Уже есть | Устойчивость при сбоях |
| Quality Gates | ✅ Уже есть | Автоматическая проверка кода |
| Budget control | ✅ Уже есть | Предотвращение runaway spending |
| Fix iterations | ✅ Уже есть | Closed-loop цикл разработки |
| Multi-runner (AgentRunnerInterface) | ✅ Уже есть | Выбор провайдера/модели |
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
