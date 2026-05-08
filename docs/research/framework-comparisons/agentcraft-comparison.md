# Исследование: AgentCraft — RTS-интерфейс оркестрации AI-агентов (проприетарный)

> **Проект:** [getagentcraft.com](https://www.getagentcraft.com/)
> **Дата анализа:** 2026-04-28
> **Язык:** TypeScript (Node.js, Next.js docs)
> **Лицензия:** Проприетарный (закрытый исходный код)
> **Аналитик:** Технический писатель (Гермиона)

---

## 1. Обзор проекта

AgentCraft — проприетарный визуальный оркестратор AI-агентов с интерфейсом в стиле RTS-игр (Real-Time Strategy). Ключевая идея: управление AI-агентами как «героями» на «поле боя» (вашем проекте) с геймификацией: миссии, достижения, туман войны (fog of war), расовые скины, музыкальное сопровождение.

AgentCraft **не является** фреймворком оркестрации цепочек. Это **визуальный GUI-оркестратор**, который оборачивает существующие AI-coding агенты (Claude Code, OpenCode, Cursor, OpenClaw) и предоставляет им интерактивный TUI-интерфейс. В отличие от task-orchestrator, AgentCraft не поддерживает YAML-цепочки шагов, retry-механизмы, circuit breaker, бюджетный контроль или quality gates.

### Архитектура

Точный внутренний код недоступен (проприетарный). Ниже — восстановленная структура на основе анализа документации, RSC-метаданных сайта и публичных материалов.

```
agentcraft/                             (закрытый репозиторий, npx idosal/agentcraft)
  integrations/
    claude-code/                        Primary: интеграция с Anthropic's Claude Code CLI
    opencode/                           Интеграция с OpenCode AI coding agent
    cursor/                             Интеграция с Cursor agent mode via CLI
    openclaw/                           Экспериментальная: passive интеграция с OpenClaw
  features/
    side-panel/                         Основной интерфейс взаимодействия с агентами
    agent-teams/                        Мультиагентные командные workflows
    containers/                         Docker / Apple Containers с network isolation
    scheduled-tasks/                    Cron-like задачи для агентов
    skill-scrolls/                      Коллекционные «свитки» навыков агентов
    fog-of-war/                         Визуализация активности агентов на карте проекта
    missions/                           Трекинг завершённых задач (mission history)
    worktrees/                          Git worktrees: герои в разных ветках
    git-management/                     Встроенный stash и branch management
    terminal/                           Интегрированный терминал
    file-explorer/                      Обзор файлов проекта
    voice-input/                        Speech-to-text в composer
    remote-access/                      Secure tunnels + mobile PWA
    achievements/                       Система достижений с trophy cards
    music/                              Амбиентный саундтрек
    race-skins/                         Тематические скины фракций
    keyboard-shortcuts/                 Горячие клавиши
  channels/                             (Upcoming) Telegram/Discard каналы для агентов
  alliance-hall/                        (Upcoming) Мультиплеер: shared battlefield
```

### Ключевые характеристики

| Характеристика | Значение |
|---|---|
| **Тип** | GUI-оркестратор / TUI-клиент |
| **Модель выполнения** | Обёртка над внешними AI-агентами (Claude Code, OpenCode, Cursor, OpenClaw) |
| **State management** | Локальный (git worktrees, mission history) |
| **Интеграции** | Claude Code (primary), OpenCode, Cursor, OpenClaw |
| **Расширяемость** | Skill Scrolls, Race Skins, Channels (upcoming) |
| **Интерфейс** | TUI в стиле RTS-игры |
| **Платформы** | macOS (Docker + Apple Containers), Linux (Docker) |
| **Установка** | `npx idosal/agentcraft` |
| **Автор** | Ido Sal ([@idosal1](https://x.com/idosal1), автор [git-mcp](https://github.com/idosal/git-mcp), 8K★) |

### Основные компоненты

| Компонент | Назначение |
|---|---|
| Side Panel | Основной интерфейс взаимодействия с отдельными агентами-«героями» |
| Agent Teams | Коллаборативные мультиагентные командные workflows |
| Isolated Containers | Docker/Apple Containers с полной network-изоляцией |
| Scheduled Tasks | Повторяющиеся задачи с cron-like интервалами |
| Skill Scrolls | Коллекционные «свитки», устанавливающие навыки агентов |
| Fog of War | Визуализация активности агентов на «карте» проекта |
| Missions | Трекинг завершённых задач с persistent mission history |
| Git Worktrees | Параллельные герои в разных git worktrees |
| Remote Access & Mobile | Secure tunnels + мобильный PWA-клиент |
| Voice Input | Speech-to-text в composer |
| Alliance Hall | (Upcoming) Коллаборативный мультиплеер — shared battlefield |
| Channels | (Upcoming) Telegram/Discord каналы для отправки промптов и approve планов |

---

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | Task Orchestrator | AgentCraft | Статус |
|---|---|---|---|
| **Цепочки шагов (chains)** | ✅ YAML chains, статические и динамические | ❌ Нет. GUI wrapper над внешними агентами | ✅ У нас есть |
| **Retry с backoff** | ✅ RetryingAgentRunner | ❌ Нет (делегируется агентам) | ✅ У нас есть |
| **Circuit Breaker** | ✅ CircuitBreakerAgentRunner | ❌ Нет | ✅ У нас есть |
| **Quality Gates** | ✅ Shell-команды как проверки | ❌ Нет | ✅ У нас есть |
| **Бюджетный контроль** | ✅ BudgetVO (cost-based) | ❌ Нет | ✅ У нас есть |
| **Итерационные циклы (fix_iterations)** | ✅ Группа шагов с max_iterations | ❌ Нет | ✅ У нас есть |
| **Fallback routing** | ✅ Per-step fallback runner | ❌ Нет | ✅ У нас есть |
| **Audit Trail (JSONL)** | ✅ JsonlAuditLogger | ❌ Нет (mission history — только GUI) | ✅ У нас есть |
| **Ролевые промпты** | ✅ .md файлы (18+ ролей) | ⚠️ Skill Scrolls (коллекционные навыки, проприетарный формат) | ✅ Паритет по концепции |
| **Multiple runners** | ✅ Pi + Codex (через interface) | ✅ Claude Code + OpenCode + Cursor + OpenClaw | ✅ Паритет |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❓ Неизвестно (закрытый код) | ✅ У нас сильнее (вероятно) |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❓ Неизвестно | ✅ У нас есть |
| **YAML-конфигурация** | ✅ Chains + roles в YAML | ❓ Неизвестно (вероятно JSON/config GUI) | ✅ Паритет по подходу |
| **Session persistence** | ❌ Нет (in-memory) | ⚠️ Mission history (persistent) | 🟡 Позже |
| **Multi-agent teams** | ❌ Нет (chain = single context) | ✅ Agent Teams: коллаборативные мультиагентные workflows | 🟡 Интересно |
| **Isolated containers** | ❌ Нет | ✅ Docker + Apple Containers с network isolation | 🟡 Позже |
| **Git worktrees** | ❌ Нет | ✅ Герои в разных git worktrees | 🟡 Интересно |
| **Scheduled tasks** | ❌ Нет | ✅ Cron-like интервалы | 🟡 Позже |
| **Fog of War** | ❌ Нет | ✅ Визуализация активности агентов | 🟢 Не берём (GUI-фича) |
| **Remote Access / Mobile** | ❌ Нет | ✅ Secure tunnels + mobile PWA | 🟢 Не берём |
| **Voice Input** | ❌ Нет | ✅ Speech-to-text | 🟢 Не берём |
| **Achievements / Gamification** | ❌ Нет | ✅ Tiered achievement system | 🟢 Не берём |
| **Race Skins / Music** | ❌ Нет | ✅ Фракционные темы, амбиентный саундтрек | 🟢 Не берём |
| **Channels (Telegram/Discord)** | ❌ Нет | ⚠️ Upcoming: отправка промптов, approve планов | 🟡 Интересно |
| **Alliance Hall (multiplayer)** | ❌ Нет | ⚠️ Upcoming: shared battlefield | 🟢 Не берём |
| **Open-source** | ✅ Да | ❌ Нет (проприетарный) | ✅ У нас есть |

---

## 3. Что полезно взять и почему

### 3.1 🟡 Git Worktrees для параллельного выполнения (`features/worktrees`)

**Что у них:** AgentCraft позволяет «спавнить» героев (агентов) в разных git worktrees — каждый агент работает в изолированной рабочей копии. Это позволяет параллельно выполнять задачи без конфликтов в одном репозитории.

**Почему нам интересно:** Для параллельного выполнения цепочек (если добавим parallel execution) или одновременного запуска нескольких chain-файлов в одном проекте — git worktrees предоставляют изоляцию на уровне файловой системы без Docker overhead.

**Отличие от нашей реализации:**
- У нас: единая рабочая копия, chain выполняется последовательно
- У них: каждый агент = свой worktree, параллельная работа без конфликтов

**Аналог в исследованных проектах:** Archon (`IIsolationProvider`) предлагает аналогичный паттерн.

---

### 3.2 🟡 Multi-Agent Teams — коллаборативные workflows (`features/agent-teams`)

**Что у них:** AgentCraft поддерживает командные мультиагентные workflows: несколько «героев» работают вместе над задачей. Детали протокола взаимодействия недоступны (закрытый код), но концептуально это координация нескольких AI-агентов с разделением ролей.

**Почему нам интересно:** Паттерн «команда агентов» — это эволюция наших dynamic chains. Вместо линейной цепочки шагов — несколько агентов с разными ролями, координируемые оркестратором. Концептуально пересекается с sub-agent pattern (Claude Code Task tool, Codex spawn).

**Отличие от нашей реализации:**
- У нас: chain = линейная последовательность шагов
- У них: team = несколько агентов с координацией (детали неизвестны)

---

### 3.3 🟡 Isolated Agent Containers (`features/containers`)

**Что у них:** Агенты запускаются в Docker или Apple Containers с полной network-изоляцией. Это sandboxing для безопасного выполнения команд AI-агентов.

**Почему нам интересно:** Для автономного выполнения chain-файлов в CI/CD — sandboxing критичен. AgentCraft реализует на уровне GUI-оркестратора, мы могли бы реализовать на уровне chain executor.

**Аналог в исследованных проектах:** Codex (Docker + iptables + auto-cleanup), Copilot Cloud Agent (container isolation). AgentCraft добавляет Apple Containers — нативную sandbox-технологию macOS.

---

### 3.4 🟡 Scheduled Tasks — cron-like выполнение (`features/scheduled-tasks`)

**Что у них:** Создание повторяющихся задач для агентов с cron-like интервалами. Агент запускается автоматически по расписанию.

**Почему нам интересно:** Для CI/CD pipeline: автоматический запуск chain-файлов по расписанию (ежедневный код-ревью, еженедельный рефакторинг, мониторинг и т.д.). Это не относится к оркестрации внутри chain, но к оркестрации запуска chain-файлов.

**Отличие от нашей реализации:**
- У нас: ручной запуск chain через CLI
- У них: автоматический запуск агента по расписанию

---

### 3.5 🟡 Skill Scrolls — коллекционные навыки агентов (`features/skill-scrolls`)

**Что у них:** «Коллекционные свитки» (Skill Scrolls) — устанавливаемые навыки для агентов. Концептуально это расширение capabilities агента через декларативные описания.

**Почему нам интересно:** Концептуально пересекается с SKILL.md / Agent Skills стандартом (Crush, OpenHands SDK, Archon). Наша система ролей (.md файлы) уже реализует аналогичную функциональность. AgentCraft добавляет «коллекционность» — геймификацию discovery навыков.

**Отличие от нашей реализации:**
- У нас: role .md = промпт, загружается по имени из YAML
- У них: Skill Scrolls = коллекционные навыки с UI-дискавери

---

### 3.6 🟡 Channels — мессенджеры как интерфейс (`channels`, upcoming)

**Что у них:** (Запланированная фича) Отправка промптов, approve планов, grant permissions через Telegram/Discord. Агенты становятся «доступными» через привычные мессенджеры.

**Почему нам интересно:** Для CI/CD: webhook-уведомления о статусе chain execution + возможность approve/reject через мессенджер. Это форма Human-in-the-loop, которую мы пока не поддерживаем.

---

## 4. Что НЕ берём и почему

### 4.1 🟢 RTS-геймификация (Fog of War, Achievements, Race Skins, Music)

AgentCraft использует метафору RTS-игры: Fog of War (визуализация активности), Achievements (система достижений), Race Skins (тематические скины), Music (амбиентный саундтрек). Это круто для пользовательского вовлечения, но не относится к технической оркестрации. Task-orchestrator — CLI-утилита для автоматического выполнения цепочек, не интерактивная игра.

### 4.2 🟢 TUI/GUI-интерфейс

AgentCraft — визуальный интерфейс (Side Panel, File Explorer, Integrated Terminal). Task-orchestrator — CLI-утилита. Разные парадигмы: интерактивный GUI vs. автоматизированный pipeline.

### 4.3 🟢 Remote Access / Mobile PWA

Secure tunnels + мобильный PWA-клиент для удалённого управления агентами. Интересно для GUI-продукта, не актуально для CLI pipeline.

### 4.4 🟢 Voice Input

Speech-to-text в composer. Не относится к оркестрации цепочек.

### 4.5 🟢 Alliance Hall (мультиплеер)

Коллаборативный «shared battlefield» для командной работы. Это multiplayer-фича GUI-продукта, не применимая к CLI pipeline.

### 4.6 🟢 Проприетарная модель

Закрытый исходный код — нельзя использовать как dependency или заимствовать реализацию. Анализ основан исключительно на публичной документации и метаданных.

---

## 5. Сводка рекомендаций

| Фича | Приоритет | Обоснование |
|---|---|---|
| Chain orchestration | ✅ Уже есть | Core-функциональность task-orchestrator |
| Retry + Circuit Breaker | ✅ Уже есть | Устойчивость при сбоях |
| Quality Gates | ✅ Уже есть | Автоматическая проверка кода |
| Budget control | ✅ Уже есть | Предотвращение runaway spending |
| Fix iterations | ✅ Уже есть | Closed-loop цикл разработки |
| Git worktrees для изоляции | 🟡 P3 | Для параллельного выполнения цепочек. Аналог: Archon IIsolationProvider |
| Multi-agent teams | 🟡 P3 | Эволюция dynamic chains — координация нескольких агентов |
| Isolated containers | 🟡 P2 | Для CI/CD sandboxing. Подтверждено Codex и Copilot Cloud Agent |
| Scheduled tasks | 🟡 P3 | Автоматический запуск chain по расписанию (CI/CD pipeline) |
| Skill Scrolls / Skills | 🟡 P3 | Концептуально = наш AGENTS.md + role .md. Формализация discovery — см. SKILL.md стандарт |
| Channels (HITL) | 🟡 P3 | Human-in-the-loop через мессенджеры для CI/CD approve |
| RTS геймификация | 🟢 — | Не относится к CLI pipeline |
| TUI/GUI интерфейс | 🟢 — | Разная парадигма |
| Remote Access / Mobile | 🟢 — | Не актуально для CLI |
| Voice Input | 🟢 — | Не относится к оркестрации |
| Alliance Hall | 🟢 — | Мультиплеер для GUI |

---

## 6. Указатель источников для деталей

Все ссылки ведут к публичной документации AgentCraft (доступной через сайт):

- [getagentcraft.com](https://www.getagentcraft.com/) — Homepage с демо-видео
- [getagentcraft.com/docs](https://www.getagentcraft.com/docs) — Документация (sidebar-навигация)
- [getagentcraft.com/docs/getting-started](https://www.getagentcraft.com/docs/getting-started) — Installation & Setup
- [getagentcraft.com/docs/getting-started/first-hero](https://www.getagentcraft.com/docs/getting-started/first-hero) — Your First Hero
- [getagentcraft.com/docs/features/agent-teams](https://www.getagentcraft.com/docs/features/agent-teams) — Agent Teams
- [getagentcraft.com/docs/features/containers](https://www.getagentcraft.com/docs/features/containers) — Isolated Agent Containers
- [getagentcraft.com/docs/features/scheduled-tasks](https://www.getagentcraft.com/docs/features/scheduled-tasks) — Scheduled Tasks
- [getagentcraft.com/docs/features/skill-scrolls](https://www.getagentcraft.com/docs/features/skill-scrolls) — Skill Scrolls
- [getagentcraft.com/docs/features/worktrees](https://www.getagentcraft.com/docs/features/worktrees) — Git Worktrees
- [getagentcraft.com/docs/features/fog-of-war](https://www.getagentcraft.com/docs/features/fog-of-war) — Fog of War
- [getagentcraft.com/docs/integrations/claude-code](https://www.getagentcraft.com/docs/integrations/claude-code) — Claude Code integration
- [Discord](https://discord.com/invite/nEaZAH7C7F) — AgentCraft Discord
- [Ido Sal (@idosal1)](https://x.com/idosal1) — Автор проекта
- [idosal/git-mcp](https://github.com/idosal/git-mcp) — Другой проект автора (8K★, MCP-сервер для GitHub)

---

📚 **Источники:**
1. [getagentcraft.com](https://www.getagentcraft.com/) — официальный сайт
2. [getagentcraft.com/docs](https://www.getagentcraft.com/docs) — документация (RSC sidebar metadata)
3. [github.com/idosal](https://github.com/idosal) — GitHub-профиль автора
4. [Discord AgentCraft](https://discord.com/invite/nEaZAH7C7F) — сообщество
5. [npm agentcraft](https://www.npmjs.com/package/agentcraft) — ⚠️ НЕ тот пакет: npm `agentcraft` — другая библиотека (звуковые паки для AI-агентов от rohenaz). AgentCraft Ido Sal устанавливается через `npx idosal/agentcraft` из закрытого GitHub-репозитория.
