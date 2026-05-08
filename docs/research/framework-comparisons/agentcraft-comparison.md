# Исследование: AgentCraft — RTS-интерфейс оркестрации AI-агентов (проприетарный)

> **Проект:** [getagentcraft.com](https://www.getagentcraft.com/)
> **Дата анализа:** 2026-04-28
> **Язык:** TypeScript (Node.js, Next.js docs)
> **Лицензия:** Проприетарный (закрытый исходный код)
> **Аналитик:** Технический писатель (Гермиона)

---

## 1. Обзор проекта

AgentCraft — проприетарный визуальный оркестратор AI-агентов с интерфейсом в стиле RTS-игр (Real-Time Strategy). Ключевая идея: управление AI-агентами как «героями» на «поле боя» (вашем проекте) с геймификацией: миссии, достижения, туман войны (fog of war), расовые скины, музыкальное сопровождение.

AgentCraft **не является** фреймворком оркестрации цепочек. Это **визуальный GUI-оркестратор**, который оборачивает существующие AI-coding агенты (Claude Code, OpenCode, Cursor, OpenClaw) и предоставляет им интерактивный TUI-интерфейс. AgentCraft не предоставляет собственных механизмов YAML-цепочек шагов, retry, circuit breaker, бюджетного контроля или quality gates — эти аспекты делегируются обёрнутым AI-агентам.

### Архитектура

Точный внутренний код недоступен (проприетарный). Ниже — восстановленная структура на основе анализа документации, RSC-метаданных сайта и публичных материалов.

```
agentcraft/ (закрытый репозиторий, npx idosal/agentcraft)
 integrations/
 claude-code/ Primary: интеграция с Anthropic's Claude Code CLI
 opencode/ Интеграция с OpenCode AI coding agent
 cursor/ Интеграция с Cursor agent mode via CLI
 openclaw/ Экспериментальная: passive интеграция с OpenClaw
 features/
 side-panel/ Основной интерфейс взаимодействия с агентами
 agent-teams/ Мультиагентные командные workflows
 containers/ Docker / Apple Containers с network isolation
 scheduled-tasks/ Cron-like задачи для агентов
 skill-scrolls/ Коллекционные «свитки» навыков агентов
 fog-of-war/ Визуализация активности агентов на карте проекта
 missions/ Трекинг завершённых задач (mission history)
 worktrees/ Git worktrees: герои в разных ветках
 git-management/ Встроенный stash и branch management
 terminal/ Интегрированный терминал
 file-explorer/ Обзор файлов проекта
 voice-input/ Speech-to-text в composer
 remote-access/ Secure tunnels + mobile PWA
 achievements/ Система достижений с trophy cards
 music/ Амбиентный саундтрек
 race-skins/ Тематические скины фракций
 keyboard-shortcuts/ Горячие клавиши
 channels/ (Upcoming) Telegram/Discord каналы для агентов
 alliance-hall/ (Upcoming) Мультиплеер: shared battlefield
```

### Ключевые характеристики

| Характеристика | Значение |
| --- | --- |
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
| --- | --- |
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

## 2. Возможности оркестрации — обзор

| Функция | AgentCraft |
| --- | --- |
| **Ролевые промпты** | ⚠️ Skill Scrolls (коллекционные навыки, проприетарный формат) |
| **Multiple runners** | ✅ Claude Code + OpenCode + Cursor + OpenClaw |
| **DDD-архитектура** | ❓ Неизвестно (закрытый код) |
| **YAML-конфигурация** | ❓ Неизвестно (вероятно JSON/config GUI) |
| **Session persistence** | ⚠️ Mission history (persistent) |
| **Multi-agent teams** | ✅ Agent Teams: коллаборативные мультиагентные workflows |
| **Isolated containers** | ✅ Docker + Apple Containers с network isolation |
| **Git worktrees** | ✅ Герои в разных git worktrees |
| **Scheduled tasks** | ✅ Cron-like интервалы |
| **Fog of War** | ✅ Визуализация активности агентов |
| **Remote Access / Mobile** | ✅ Secure tunnels + mobile PWA |
| **Voice Input** | ✅ Speech-to-text |
| **Achievements / Gamification** | ✅ Tiered achievement system |
| **Race Skins / Music** | ✅ Фракционные темы, амбиентный саундтрек |
| **Channels (Telegram/Discord)** | ⚠️ Upcoming: отправка промптов, approve планов |
| **Alliance Hall (multiplayer)** | ⚠️ Upcoming: shared battlefield |

---

## 3. Оркестрационные возможности

### 3.0 Классификация оркестрационных паттернов

AgentCraft реализует несколько оркестрационных паттернов, которые можно классифицировать по уровням:

| Уровень | Паттерн | Реализация |
| --- | --- | --- |
| **Прокси** | Agent Gateway (Facade) | Единый интерфейс (Side Panel / TUI) над несколькими AI-backends (Claude Code, OpenCode, Cursor, OpenClaw). Нормализует запуск, но не ход выполнения |
| **Изоляция** | Execution Sandbox | Два ортогональных механизма: git worktrees (параллельные FS-ветвления) + Docker/Apple Containers (sandboxing с network-изоляцией) |
| **Координация** | Agent Teams | Группировка «героев» в команды с разделением задач внутри одной «миссии». Топология координации неизвестна (закрытый код) |
| **Планирование** | Cron Scheduling | Триггерное выполнение агентов по расписанию (open-loop: fire-and-forget без обратной связи) |
| **Расширение** | Prompt Augmentation | Skill Scrolls — вероятнее всего injection дополнительных инструкций (system prompt), а не расширение runtime-возможностей агента |
| **Наблюдаемость** | Activity Monitoring | Fog of War (runtime-визуализация) + Mission History (post-mortem audit). Два разных архитектурных назначения |
| **Человек в цикле** | HITL | Side Panel (текущий) + Channels (запланировано: transport extension через Telegram/Discord) |
| **Обработка ошибок** | Delegation | Обработка ошибок, retry, circuit breaker полностью делегируются обёрнутому AI-агенту. AgentCraft не реализует собственных fault tolerance-механизмов |
| **Совместное состояние** | — | Не обнаружено. Обмен контекстом между агентами внутри команды не подтверждён документацией. См. анализ в §3.2 |

AgentCraft не реализует собственную AI-логику — он выступает как **Agent Gateway** (Facade): proxy-слой, нормализующий доступ к разным agent backends. Фундаментальное следствие: оркестрируется не ход выполнения внутри агента, а **запуск, изоляция и мониторинг** агентов как целостных unit-ов. Это определяет границы всех остальных паттернов — например, отсутствие совместного состояния (shared state) между агентами и делегирование обработки ошибок на уровень обёрнутого агента.

Ниже — детальный анализ каждого паттерна.

---

### 3.1 🟡 Git Worktrees для параллельного выполнения (`features/worktrees`)

**Что у них:** AgentCraft позволяет «спавнить» героев (агентов) в разных git worktrees — каждый агент работает в изолированной рабочей копии. Это позволяет параллельно выполнять задачи без конфликтов в одном репозитории.

**Оркестрационная значимость:** Git worktrees обеспечивают **параллельные ветвления файловой системы** (concurrent branching) — каждый агент работает в своей рабочей копии, изменения не конфликтуют. Это решение проблемы concurrency, а не sandboxing: worktrees не изолируют процессы или сеть, они лишь предоставляют независимые FS-views одного репозитория.

**Важно:** Worktrees и containers — **ортогональные, а не альтернативные** механизмы. Worktrees решают «несколько агентов параллельно в одном репо», containers решают «агент не может выйти за пределы sandbox». AgentCraft использует оба одновременно: герой спавнится в контейнере (изоляция) и worktree (параллелизм).

**Технические ограничения git worktrees:**
- **Один worktree на ветку:** Нельзя создать два worktree для одной ветки — каждый «герой» обязан работать в отдельной ветке. Это ограничивает сценарии, где несколько агентов должны анализировать одну и ту же ветку.
- **Disk usage:** Каждый worktree — полная рабочая копия. При множестве параллельных агентов disk usage растёт линейно.
- **Cleanup:** Удаление worktree — ручная или скриптовая операция. Документация не описывает автоматический cleanup при termination героя (см. §3.7).
- **Нет runtime-изоляции:** Worktrees изолируют файловую систему, но не процессы, сеть или память. Агенты в разных worktrees видят процессы друг друга и могут конфликтовать за порты, lock-файлы и другие ресурсы.

**Аналог в исследованных проектах:** Archon (`IsolationProvider`) решает задачу изоляции execution environment (ближе к containers). Прямой аналог worktrees для параллельного выполнения среди исследованных проектов не обнаружен.

---

### 3.2 🟡 Multi-Agent Teams — коллаборативные workflows (`features/agent-teams`)

**Что у них:** AgentCraft поддерживает командные мультиагентные workflows: несколько «героев» работают вместе над задачей. Детали протокола взаимодействия недоступны (закрытый код), но концептуально это координация нескольких AI-агентов с разделением ролей.

**Оркестрационная значимость:** Паттерн «команда агентов» — координация нескольких AI-агентов с разделением ролей. Каждый «герой» получает свою задачу в рамках общей «миссии».

**Архитектурный вопрос — топология координации:** Известные топологии координации мультиагентных систем:

| Топология | Описание | Характеристики |
| --- | --- | --- |
| **Иерархия (sub-agent)** | Parent→child: родитель делегирует подзадачу, получает результат, принимает решение о следующем шаге | Единый decision-maker, простое разрешение зависимостей. Примеры: Claude Code Task tool, Codex spawn, Hermes delegate_task |
| **Hub / Supervisor** | Центральный координатор распределяет задачи и агрегирует результаты, но не является AI-агентом | Чёткое разделение orchestration и execution. Fault isolation зависит от hub |
| **Peer-to-peer** | Агенты обмениваются сообщениями напрямую, без центрального координатора | Максимальная fault isolation, но сложная координация зависимостей |
| **Blackboard** | Агенты пишут/читают из общего пространства состояний (shared workspace) | Loose coupling через общие данные. Требует conflict resolution |
| **Pipeline** | Агенты выстроены в цепочку: выход одного — вход следующего | Простая модель для линейных workflows, неприменима для параллельных задач |

**Предположение о топологии AgentCraft:** Каждый герой — самостоятельный агент со своим backend-ом и worktree. Side Panel визуально выступает координатором. Это **вероятнее hub-топология** (Side Panel как supervisor), а не чистый peer-to-peer (агенты не обмениваются сообщениями напрямую — нет подтверждённого механизма inter-agent communication). Однако без доступа к коду точная классификация невозможна — документация не описывает протокол координации.

**Архитектурное следствие hub-модели:** Fault isolation выше (падение одного героя не ломает остальных), но разрешение зависимостей между подзадачами ложится на пользователя через GUI, а не автоматический decision-maker. Это принципиально отличает AgentCraft от sub-agent иерархий, где зависимостями управляет AI-агент-родитель.

**Наблюдаемые характеристики (из документации):**
- Команда формируется из нескольких «героев», каждый из которых может использовать разные AI-backends (Claude Code, OpenCode, Cursor).
- Задачи внутри миссии распределяются между героями — структура распределения настраивается через GUI.
- Каждый герой может работать в собственном git worktree (см. §3.1), что обеспечивает параллельность без конфликтов.
- Результаты миссии агрегируются в Mission History с трекингом статуса.

**Ограничения анализа:** Протокол взаимодействия между агентами внутри команды недоступен (закрытый код). Неизвестно: поддерживается ли обмен контекстом между агентами, есть ли механизм разрешения конфликтов при работе над одними файлами, как координируются зависимости между подзадачами.

**Аналог в исследованных проектах:** Концептуально пересекается с sub-agent pattern, но с иной топологией. Sub-agent (Claude Code Task, Codex spawn, Hermes delegate_task) — **иерархическая** координация с единым decision-maker. AgentCraft Agent Teams — вероятнее **hub-координация** через Side Panel как supervisor, что даёт лучшую fault isolation, но ограничивает автоматическое разрешение зависимостей (разрешение зависимостей — задача пользователя, а не AI-агента).

---

### 3.3 🟡 Isolated Agent Containers (`features/containers`)

**Что у них:** Агенты запускаются в Docker или Apple Containers с полной network-изоляцией. Это sandboxing для безопасного выполнения команд AI-агентов.

**Оркестрационная значимость:** Sandboxed execution — критичный механизм для автономного выполнения агентов. AgentCraft реализует контейнерную изоляцию через две разные технологии:

| Технология | Платформа | Механизм изоляции | Гарантии |
| --- | --- | --- | --- |
| **Docker** | Linux, macOS | Namespace-based: PID, network, mount, UTS namespaces + cgroups для resource limits | Shared kernel — изоляция на уровне OS namespace, не виртуализация. Potential breakout при misconfiguration |
| **Apple Containers** | macOS only | OS-level виртуализация: каждый контейнер получает собственный lightweight OS instance | Сильнейшая изоляция среди доступных на macOS. Separate kernel, separate filesystem layer |

**Ключевое различие:** Apple Containers обеспечивают более сильную изоляцию, чем Docker (отдельный kernel vs shared kernel). Это влияет на security model: на macOS AgentCraft может предоставить stronger sandboxing, чем на Linux. Документация не уточняет, отличаются ли capabilities агентов в зависимости от платформы контейнера.

**Степень «полноты» изоляции** (ограничение CPU/memory, filesystem mount restrictions, seccomp profiles) не уточнена — закрытый код не позволяет верифицировать. Документация заявляет network isolation, но не описывает исходящий доступ (может ли агент обращаться к внешним API).

**Аналог в исследованных проектах:** Codex (Docker + iptables + auto-cleanup), Copilot Cloud Agent (container isolation). AgentCraft добавляет Apple Containers — нативную sandbox-технологию macOS с более сильными гарантиями изоляции, чем Docker.

---

### 3.4 🟡 Scheduled Tasks — cron-like выполнение (`features/scheduled-tasks`)

**Что у них:** Создание повторяющихся задач для агентов с cron-like интервалами. Агент запускается автоматически по расписанию.

**Оркестрационная значимость:** Trigger-based scheduling — оркестрация **запуска** агентов (когда и кого запускать), а не хода выполнения внутри агента.

**Архитектурное ограничение — open-loop vs closed-loop:** Документация не описывает механизмов обратной связи по результату выполнения (conditional scheduling, retry on failure, notification on outcome). Вероятная модель — **open-loop**: задача запускается по расписанию, результат фиксируется в Mission History, но не влияет на последующие триггеры. Это автоматизация (automation), а не адаптивная оркестрация (adaptive orchestration).

**Наблюдаемые характеристики (из документации):**
- Интервалы задаются в cron-подобном формате через GUI.
- Scheduled task привязывается к конкретному «герою» (agent backend) и project context.
- Результаты выполнения фиксируются в Mission History.

**Ограничения:** Неизвестно, поддерживается ли условное выполнение (conditional scheduling), retry при неудаче, или уведомления о результатах. AgentCraft делегирует обработку ошибок самому AI-агенту.

---

### 3.5 🟡 Skill Scrolls — коллекционные навыки агентов (`features/skill-scrolls`)

**Что у них:** «Коллекционные свитки» (Skill Scrolls) — устанавливаемые навыки для агентов. Концептуально это расширение capabilities агента через декларативные описания.

**Оркестрационная значимость:** Skill Scrolls — механизм prompt augmentation (обогащение инструкций агента), а не расширение runtime-возможностей. Поскольку AgentCraft — обёртка над CLI-агентами, фактические capabilities агента (tools, model, context window) определяются backend-ом (Claude Code, OpenCode и т.д.). Свитки вероятнее всего inject дополнительные инструкции в промпт, направляя поведение агента, но не добавляя новых инструментов.

**Наблюдаемые характеристики (из документации):**
- Свитки представляют собой декларативные описания навыков, которые «устанавливаются» на героя.
- Свитки коллекционируются — это часть геймификации (discovery mechanics).
- Формат и структура свитков не раскрыты — проприетарный.

**Архитектурное различие:** Prompt augmentation (Skill Scrolls) и capability injection (runtime tool registration) — разные уровни расширения. Prompt augmentation меняет поведение агента в рамках имеющихся инструментов; capability injection добавляет новые инструменты. В закрытом коде AgentCraft верифицировать уровень невозможно, но архитектурно (CLI-обёртка) prompt augmentation — наиболее вероятный механизм.

**Аналог в исследованных проектах:** Концептуально аналогичен SKILL.md стандарту (agentskills.io), но с проприетарным форматом и геймификацией discovery.

---

### 3.6 🟡 Channels — мессенджеры как интерфейс (`channels`, upcoming)

**Что у них:** (Запланированная фича) Отправка промптов, approve планов, grant permissions через Telegram/Discord. Агенты становятся «доступными» через привычные мессенджеры.

**Оркестрационная значимость:** Channels выполняют двойную роль:
1. **Transport extension:** Новая точка входа для взаимодействия с агентами — промпты, approve/reject планов, grant permissions через привычные мессенджеры (Telegram, Discord).
2. **HITL-расширение:** Side Panel уже реализует human-in-the-loop на десктопе; Channels расширяют HITL на мобильные и асинхронные сценарии. Архитектурно это не новый паттерн, а новый транспорт для существующего.

---

### 3.7 🟡 Agent Lifecycle Management

**Что у них:** AgentCraft реализует жизненный цикл «героя» (агента): spawn → assignment → monitoring → completion → termination. Управляется через Side Panel.

**Оркестрационная значимость:** Жизненный цикл агента — универсальный паттерн, присутствующий в большинстве agent-фреймворков. Специфика AgentCraft — в **точке принятия решений** при spawn: пользователь одновременно выбирает AI-backend (Claude Code, OpenCode, Cursor, OpenClaw) и стратегию изоляции (worktree / container). Это конфигурируемый spawn с двумя осями выбора, что отличает AgentCraft от фреймворков с фиксированным runtime.

**Отсутствующие этапы жизненного цикла:** Документация не описывает:
- **Health check / heartbeat:** активный мониторинг живости агента (detect hung/crashed agent);
- **Timeout handling:** максимальное время выполнения агента. Без timeout «зависший» агент может бесконечно потреблять ресурсы контейнера и worktree;
- **Graceful degradation:** поведение при нехватке ресурсов (все containers заняты);
- **Checkpoint/resume:** сохранение промежуточного состояния для возобновления после сбоя;
- **Cleanup / resource deallocation:** освобождение контейнера, удаление worktree, освобождение портов после termination. Без cleanup ресурсы утекают при повторных spawn/terminate циклах;
- **Transition rules:** допустимые переходы между состояниями (может ли «зависший» агент быть принудительно terminated? может ли terminated агент быть respawned?).

Без этих этапов lifecycle является «best-effort»: spawn с надеждой на completion, без recovery-механизмов при промежуточных сбоях и без гарантированного освобождения ресурсов.

---

### 3.8 🟡 Observability: Fog of War и Mission History

**Что у них:** Fog of War визуализирует активность агентов на «карте» проекта — показывает, какие файлы и директории затронуты каждым «героем». Mission History хранит persistent-записи завершённых задач с деталями выполнения.

**Оркестрационная значимость:** Observability — критичный компонент оркестрации мультиагентных систем. AgentCraft реализует два механизма с разными архитектурными назначениями:

| Механизм | Назначение | Характеристики |
| --- | --- | --- |
| **Fog of War** | Runtime-мониторинг: принятие решений в реальном времени | Real-time визуализация активности. Позволяет видеть пересечения файлов и потенциальные конфликты между агентами. Доступен только через GUI |
| **Mission History** | Post-mortem audit: ретроспективный анализ и прослеживаемость | Persistent лог завершённых миссий — что сделано, какие файлы изменены, статус. Обеспечивает историческую прослеживаемость |

**Различие важно:** Runtime-мониторинг требует низкой латентности и влияет на принимаемые решения (пользователь видит конфликт и вмешивается). Post-mortem audit требует полноты и поисковой способности, но не latency. AgentCraft чётко разделяет эти два механизма, но оба ограничены GUI — программный API для observability не описан.

**Архитектурный анализ observability-слоёв:** Observability мультиагентных систем традиционно разделяется на три столпа:

| Столп | Назначение | Реализация в AgentCraft |
| --- | --- | --- |
| **Logging** | Запись событий для post-mortem анализа | Mission History (persistent records). Без structured format — формат закрыт |
| **Tracing** | Прослеживание причинно-следственных связей между действиями разных агентов | Отсутствует. Нет correlation ID для связывания действий агентов в одной миссии. Критичный пробел для Agent Teams — без tracing невозможно reconstruct ход координации |
| **Metrics / Telemetry** | Количественные показатели: время выполнения, потребление ресурсов, частота ошибок | Не обнаружено. Нет данных о latency агентов, токен-usage, success rate, resource consumption |

**Следствие для Agent Teams:** Без tracing и metrics координация нескольких агентов в одной миссии не прослеживаема. При анализе неудачной миссии невозможно определить: какой агент задержал выполнение, какой первым затронул конфликтный файл, каков был вклад каждого агента.

**Ограничения:**
- Fog of War недоступен в программном API — невозможна автоматическая реакция на конфликты.
- Mission History не описывает структуру хранения (локальная БД, файлы, git notes) — формат закрыт.
- Technical mechanism Fog of War не описан: file system watching (inotify/FSEvents), agent output parsing, или git diff analysis — разные подходы с разной латентностью и полнотой.

---

## 4. Прочие возможности (вне оркестрации)

### 4.1 🟢 RTS-геймификация (Fog of War, Achievements, Race Skins, Music)

AgentCraft использует метафору RTS-игры: Fog of War (визуализация активности), Achievements (система достижений), Race Skins (тематические скины), Music (амбиентный саундтрек). Это повышает пользовательское вовлечение, но не относится к технической оркестрации.

### 4.2 🟢 TUI/GUI-интерфейс

AgentCraft — визуальный интерфейс (Side Panel, File Explorer, Integrated Terminal). Парадигма интерактивного GUI, где пользователь управляет агентами вручную.

### 4.3 🟢 Remote Access / Mobile PWA

Secure tunnels + мобильный PWA-клиент для удалённого управления агентами. Расширяет точку доступа к оркестрации за пределы десктопа.

### 4.4 🟢 Voice Input

Speech-to-text в composer. Не относится к оркестрации цепочек.

### 4.5 🟢 Alliance Hall (мультиплеер)

Коллаборативный «shared battlefield» для командной работы. Мультиплеерная фича, расширяющая Agent Teams на нескольких пользователей.

### 4.6 🟢 Проприетарная модель

Закрытый исходный код — нельзя использовать как dependency или заимствовать реализацию. Анализ основан исключительно на публичной документации и метаданных.

---

## 5. Сводка по оркестрации

| Механизм оркестрации | Реализация в AgentCraft | Зрелость |
| --- | --- | --- |
| **Agent Gateway (proxy)** | Единый интерфейс над 4 AI-backends (Claude Code, OpenCode, Cursor, OpenClaw) | 🟩 Хорошая (4 интеграции) |
| **Agent Lifecycle** | Spawn → Assignment → Monitoring → Completion → Termination через Side Panel | 🟩 Хорошая |
| **Multi-agent coordination** | Agent Teams — несколько «героев» координируются в одной «миссии» | 🟨 Средняя (детали закрыты) |
| **Isolation** | Git worktrees (FS-concurrency) + Docker/Apple Containers (sandboxing) — ортогональные механизмы, используются совместно | 🟩 Хорошая |
| **State management** | Совместное состояние между агентами не обнаружено | ⬛ Отсутствует |
| **Scheduling** | Cron-like scheduled tasks (open-loop, без conditional/retry/notification) | 🟨 Средняя |
| **Agent capabilities** | Skill Scrolls — вероятнее всего prompt augmentation, не runtime capability extension | 🟨 Средняя (проприетарный формат, механизм не верифицирован) |
| **Human-in-the-loop** | Channels (upcoming) — approve/reject через Telegram/Discord | ⬛ Запланировано |
| **Error handling / Retry** | Делегируется агентам | ⬛ Отсутствует |
| **Circuit Breaker** | — | ⬛ Отсутствует |
| **Quality Gates** | — | ⬛ Отсутствует |
| **Budget Control** | — | ⬛ Отсутствует |
| **Observability** | Fog of War (runtime) + Mission History (post-mortem). Оба — GUI-only, программный API не описан | 🟨 Средняя (нет structured logging / tracing) |

**Общая оценка:** AgentCraft — **визуальный оркестратор** (Agent Gateway / Facade) над внешними AI-агентами. Реализует паттерны: Agent Gateway (4 интеграции), Execution Isolation (worktrees для concurrency + containers для sandboxing — два ортогональных механизма), Agent Teams (hub-координация через Side Panel), Cron Scheduling (open-loop), Prompt Augmentation (Skill Scrolls), Observability (Fog of War для runtime + Mission History для post-mortem). Не предоставляет: совместное состояние между агентами, retry/circuit breaker/quality gates, budget control, structured logging/tracing. Закрытый код ограничивает анализ деталей координационного протокола и верификацию механизмов.

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
