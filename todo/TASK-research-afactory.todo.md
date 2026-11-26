---
type: docs
created: 2026-11-26 12:43:53 (1795697033)
due: 
started: 
completed: 
cancelled: 
value: V3
complexity: C3
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид Алекс (pi)
assignee: Аналитик Шерлок (pi)
consultants: []
reviewer: 
approver: 
informed: []
branch: 
pr: 
status: todo
---

# TASK-research-afactory: Research: afactory (PakhomovAlexander) — мульти-агентная coding factory: review/task-конвейеры над Claude/Codex

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Наш движок оркестрации AI-агентов развивается без систематического сравнения с afactory (`PakhomovAlexander/afactory`) — открытым (Apache-2.0, Rust, 73★, public alpha, релиз `v0.15.2`) мульти-агентным «coding factory» (фабрика кодинга): CLI `af` запускает coding-агентов конвейерами (pipelines) поверх Git-репозитория.
- afactory решает ту же задачу, что и наш ChainExecution, но с другой стороны: конвейер — это коммиченные в репозиторий файлы `.af/` («shared like code»), каждый Worker (агент Claude/Codex или обычная shell-команда) работает на собственной копии репозитория в своей песочнице и на собственном токен-бюджете. Ключевые механики не исследованы с позиций нашей архитектуры: plan-before-spend (токен-бесплатный предпросмотр плана `af review plan` до расхода токенов), Gate (собственная check-команда проекта, например `make check`, обязательная перед любым ревьюером; «проверка, которая не смогла запуститься, — не pass»), бюджет на каждый Worker с резервированием до старта (tightest limit refuses by name; лимит останавливает новые работы, а не идущий вызов), pinning воркеров и релизов по digest, state machine задачи («состояние Task называет следующую команду»), ledger/report с постатейным учётом расходов, человеко-решения (`af review decide` — zero-token задачи с инспекцией и подтверждением точного плана), provider-реестр (логины claude/codex; креды остаются у native CLI или «AF's Pi SDK host»).
- Неизвестно, что из этого применимо к нашим AgentRunner, ChainExecution, DynamicLoop, quality gates и бюджету цепочки.

### Варианты или путь решения (Solution Sketch)
- Research-задача по методологии эпика: comparison-отчёт `docs/research/framework-comparisons/afactory-comparison.md` с маппингом каждой механики на компоненты task-orchestrator и вердиктом заимствовать / dependency / не подходит.
- Объект исследования — открытый репозиторий и документация; предварительный ресерч уже выполнен тимлидом (клон в `/tmp/research/afactory`, снапшот main `7760fd1cf85674f538b08ae8d2b7ee9d3adca37f` от 2026-11-26, релиз `v0.15.2`) — исполнитель проверяет выводы по первоисточникам, а не копирует их.

### Ожидаемый результат (Expected Result)
- Comparison-документ `docs/research/framework-comparisons/afactory-comparison.md`.
- Строка #40 в сводной таблице `docs/research/agent-frameworks-summary.md`, актуализация счётчиков (40/40) и затронутых сравнительных выводов.
- Чёткий вердикт: какие паттерны заимствовать (кандидаты: plan-before-spend, gate fail-closed, per-worker бюджеты с резервированием, digest pinning, task state machine, spend ledger, human decision tasks), что изучить дополнительно, что неприменимо.
- Обновлённый эпик: стадия 1r, история изменений.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story / Job Story)
> **Job Story:** Когда мы развиваем систему оркестрации AI-агентов (AgentRunner, ChainExecution, DynamicLoop, quality gates), я хочу исследовать afactory — мульти-агентную coding factory поверх Git с plan-before-spend, обязательными гейтами, per-worker бюджетами и state machine задач, — чтобы выявить применимые паттерны управления расходами, приёмки и доказательств, и не изобретать колёса.

### Цель по SMART (Goal)
Исследовать репозиторий `PakhomovAlexander/afactory` (релиз `v0.15.2` от 2026-11-25, снапшот main `7760fd1cf85674f538b08ae8d2b7ee9d3adca37f` от 2026-11-26) по методологии эпика: README, `docs/architecture.md`, `docs/contract-gate.md`, `docs/task-execution/`, `docs/providers.md`, выборка из 357 ADR, формат конфигурации `.af/` (task-catalog, task-packages), CLI-поверхность `af review` / `af task`. Создать comparison-отчёт в `docs/research/framework-comparisons/afactory-comparison.md` с маппингом ключевых механик на компоненты task-orchestrator, вердиктом по каждой и сравнением с аналогами трека (Agent Lifecycle Kit #35, pragmatic-orchestration #38, bx-dev #31, Orca ADE #30, qm #32). Добавить строку #40 в сводную таблицу `docs/research/agent-frameworks-summary.md`. Срок — в рамках evergreen-режима эпика.

## 2. Контекст и Границы (Context and Scope)

- **Объект:** afactory (`PakhomovAlexander/afactory`; Rust, Apache-2.0; 73★, 8 fork'ов; создан 2026-08-22; релиз `v0.15.2` от 2026-11-25; public alpha; topics: `agents`, `factory`, `harness`, `review`; снапшот main `7760fd1cf85674f538b08ae8d2b7ee9d3adca37f` от 2026-11-26). «Multi-agent coding factory made fast»: `af` запускает coding-агентов конвейерами поверх Git-репозитория; `af review` — ревью изменения (параллельные ревьюеры, например correctness (Claude) + bugs (Codex) → вердикт и findings), `af task` — реализация изменения с верификацией. Воркспейс из ~20 крейтов: `af` (CLI), `af-pipeline-config`, `af-graph`, `af-parallel`, `af-runner` + раннеры `af-runner-claude` / `af-runner-codex` / `af-runner-pi`, `af-attempt`, `af-check`, `af-sandbox`, `af-store`, `af-telemetry`, `af-source-git` / `af-source-issue`, `af-scenario`. Заявленные возможности: план до расхода (`af review plan` — token-free предпросмотр checks/reviewers/bindings/budgets), Gate — собственная check-команда проекта перед любым ревьюером, бюджет на каждый Worker с резервированием до старта, пайплайн как коммиченные файлы `.af/` с pinning воркеров по digest, JSON-выход (`--json`) и стабильные exit codes, state machine («состояние Task называет следующую команду»: plan → resume `--confirm-plan` → ledger → report), ledger/report/inspect/decision-context, `af review decide` — zero-token human decision tasks, provider-реестр (claude/codex логины; креды у native CLI или Pi SDK host), границы: commit/push/PR остаются за человеком. Документация: `docs/architecture.md`, `docs/contract-gate.md`, `docs/task-execution/` (review-task.md и др.), `docs/providers.md`, `docs/agents.md`, `docs/non-goals.md`, `docs/adr/` (357 ADR, например ADR-0145 «read only what this release writes»), `fixtures/` (executable-review, custom-tasks, pi-sdk, issue-triage).
- **Классификация (предварительная, проверить):** `multi-agent review/task pipeline orchestrator / harness over external coding agents` (конвейерный оркестратор поверх внешних CLI-агентов) — не coding-агент и не chain engine (движок цепочек). Ближайшие аналоги трека: Agent Lifecycle Kit (#35, plans/approvals/evidence), pragmatic-orchestration (#38, мульти-модельное ревью), bx-dev (#31), Orca ADE (#30), qm (#32).
- **Где делаем:** `docs/research/framework-comparisons/afactory-comparison.md`, `docs/research/agent-frameworks-summary.md`, эпик.
- **Текущее поведение:** наш AgentRunner абстрагирует однократный запуск раннеров (pi, codex) с JSONL-парсингом и итогом `AgentResultVo`; ChainExecution исполняет декларативные YAML-цепочки (`config/chains.yaml`) с retry/backoff, circuit breaker, quality gates, бюджетом, `fix_iterations`; DynamicLoop — итеративные раунды с resume; предварительный план с подтверждением до расхода токенов, per-step резервирование бюджета и ledger расходов у нас отсутствуют.
- **Границы (Out of Scope):** написание кода/конфигов, изменение архитектуры, бенчмарки, установка и запуск `af` (описываем по коду и документации), аудит безопасности исходников, перенос Rust-кода в PHP.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [ ] Зафиксировать дату исследования и воспроизводимый идентификатор версии: релиз `v0.15.2` от 2026-11-25, снапшот main `7760fd1cf85674f538b08ae8d2b7ee9d3adca37f` от 2026-11-26 (клон в `/tmp/research/afactory`).
- [ ] Проверить по первичным источникам классификацию как конвейерного harness-оркестратора над внешними coding-агентами (Claude/Codex/Pi + plain commands); отделить от coding-агентов и chain engine.
- [ ] Описать модель конвейера: `.af/` как коммиченная конфигурация («shared like code»), pinning воркеров/релизов по digest, черновик review-пайплайна из `af onboard` (ревьюеры → verdict); сравнить с нашими YAML-цепочками `config/chains.yaml`.
- [ ] Описать plan-before-spend: token-free `af review plan` (checks, reviewers, bindings, budgets) и resume по `--confirm-plan PLAN_ID`; сравнить с нашим исполнением цепочек без отдельного артефакта плана.
- [ ] Описать Gate: обязательная check-команда проекта перед ревьюерами, семантика «не запущенная проверка — не pass» (`docs/contract-gate.md`); сравнить с нашими quality gates.
- [ ] Описать бюджетную модель: резервирование токенов до старта каждого Worker, «tightest limit refuses by name», остановка новых работ без прерывания идущего вызова, отчёт о расходе каждого запуска; сравнить с нашим бюджетом цепочки.
- [ ] Описать state machine и учёт: состояния Task и «следующая команда», `ledger`/`report`/`inspect`/`decision-context`, человеко-решения `af review decide` (zero-token, инспекция и подтверждение точного плана); сравнить с DynamicLoop resume и отсутствием у нас ledger.
- [ ] Описать provider-модель: реестр провайдеров (claude/codex логины, `af provider detect/setup`), разделение «неконфиденциальная конфигурация в реестре / креды у native CLI или Pi SDK host»; раннер `af-runner-pi`; сравнить с нашей GitIdentity и модулями раннеров.
- [ ] Сравнить afactory с `task-orchestrator` по единой матрице трека (модель оркестрации, state mgmt, error handling, extensibility) и с аналогами: Agent Lifecycle Kit (#35), pragmatic-orchestration (#38), bx-dev (#31), Orca ADE (#30), qm (#32).
- [ ] Проверить гипотезы тимлида (секция «Комментарии») — подтверждены/опровергнуты, явно.
- [ ] Создать `docs/research/framework-comparisons/afactory-comparison.md` по формату существующих comparison-документов, со ссылками на первичные источники, ограничениями и чётким вердиктом: заимствовать паттерны / использовать как зависимость / не подходит.
- [ ] Обновить `docs/research/agent-frameworks-summary.md`: строка #40, счётчики (40/40), затронутые сравнительные выводы и тренды.
- [ ] Обновить эпик: стадия 1r в плане, запись в истории изменений.

### 🟡 Желательно (Should Have)
- [ ] Выделить 3–7 потенциально переносимых паттернов с приоритетом, ожидаемой пользой, ограничениями и необходимой последующей проверкой.
- [ ] Оценить паттерн «pipeline as committed code + digest pinning» для воспроизводимости наших цепочек (версионирование раннеров и конфигурации).
- [ ] Оценить паттерн «spend ledger per run» для `docs/guide/observability.md` (постатейный учёт расхода токенов).
- [ ] Зафиксировать пробелы документации как неизвестные данные, не подменяя их предположениями (public alpha: сверять README с `docs/adr/` и CHANGELOG).

### ⚫ Не будем делать (Won't Have)
- [ ] Не считать маркетинговые заявления («made fast», budgets, pinning) подтверждёнными фактами — только как заявления авторов, проверяемые по коду и ADR.
- [ ] Не проводить полный аудит безопасности исходного кода или бенчмарки производительности.
- [ ] Не интегрировать afactory и не добавлять его как зависимость; не менять код, конфигурацию и архитектуру `task-orchestrator`.
- [ ] Не исследовать заново все 357 ADR и весь docs-каталог — только разделы, релевантные методологии трека.
- [ ] Не создавать задачи реализации без отдельного решения пользователя.

## 4. План реализации (Implementation Plan)
1. [ ] Изучить снапшот `/tmp/research/afactory` (main `7760fd1c` от 2026-11-26): `README.md`, `docs/architecture.md`, `docs/contract-gate.md`, `docs/task-execution/`, `docs/providers.md`, `docs/non-goals.md`, `docs/adr/` (выборочно), крейты `af-pipeline-config`, `af-runner*`, `af-check`, `af-attempt`, `af-sandbox`, `fixtures/` (executable-review, custom-tasks, pi-sdk).
2. [ ] Актуализировать знание нашей архитектуры: `src/Module/AgentRunner/`, `src/Module/ChainExecution/`, `src/Module/DynamicLoop/`, `docs/guide/observability.md`.
3. [ ] Сопоставить по единой матрице трека; сравнить с Agent Lifecycle Kit (#35), pragmatic-orchestration (#38), bx-dev (#31), Orca ADE (#30), qm (#32).
4. [ ] Проверить гипотезы тимлида (секция «Комментарии»).
5. [ ] Создать `docs/research/framework-comparisons/afactory-comparison.md`.
6. [ ] Обновить `docs/research/agent-frameworks-summary.md` (строка #40, счётчики, тренды).
7. [ ] Обновить эпик (стадия 1r, история изменений); выполнить `php vendor/bin/todo-md validate`.

## 5. Критерии приёмки (Definition of Done)
- [ ] Comparison-документ создан по формату трека; все ключевые механики описаны с маппингом на компоненты task-orchestrator и ссылками на первичные источники.
- [ ] Вердикт по каждой механике: заимствовать / dependency / не подходит — с обоснованием.
- [ ] Гипотезы тимлида проверены явно (подтверждены/опровергнуты).
- [ ] Сравнение с аналогами трека (Agent Lifecycle Kit, pragmatic-orchestration, bx-dev, Orca ADE, qm) выполнено.
- [ ] Строка #40 добавлена в `docs/research/agent-frameworks-summary.md`, счётчики и затронутые тренды актуализированы.
- [ ] Эпик обновлён (стадия 1r, история изменений).
- [ ] `php vendor/bin/todo-md validate` для затронутых файлов — без ошибок.

## 6. Самопроверка (Verification)
```bash
ls docs/research/framework-comparisons/afactory-comparison.md
grep -n "afactory" docs/research/agent-frameworks-summary.md
php vendor/bin/todo-md validate
```

## 7. Риски и зависимости (Risks and Dependencies)
- Public alpha (`v0.15.x`): контракты и формат `.af/` меняются между релизами (ADR-0145 «read only what this release writes» требует завершать/сбрасывать Campaigns перед апгрейдом); выводы привязывать к снапшоту `7760fd1c` от 2026-11-26.
- README богат заявлениями («pinned by digest», «budget reserved before it starts») — проверять по коду и ADR, не по README.
- Проект молодой (создан 2026-08-22, 73★) — мало независимых источников; опираться на первоисточники.
- Rust-код и 357 ADR — риск утонуть; ограничиться разделами, релевантными матрице трека (модель оркестрации, state, error handling, extensibility).

## 8. Источники (Sources)

📚 **Внешние источники** (до 5, кратким списком):

- [github.com/PakhomovAlexander/afactory](https://github.com/PakhomovAlexander/afactory) — репозиторий (README, docs/, crates/, fixtures/)
- [README: Boundaries, Quickstart, For agents](https://github.com/PakhomovAlexander/afactory#boundaries) — план до расхода, гейт, бюджеты, границы ответственности
- [docs/contract-gate.md](https://github.com/PakhomovAlexander/afactory/blob/main/docs/contract-gate.md), [docs/architecture.md](https://github.com/PakhomovAlexander/afactory/blob/main/docs/architecture.md) — контракт гейта и архитектура воркспейса
- [docs/task-execution/review-task.md](https://github.com/PakhomovAlexander/afactory/blob/main/docs/task-execution/review-task.md), [docs/providers.md](https://github.com/PakhomovAlexander/afactory/blob/main/docs/providers.md) — review-задачи и provider-реестр
- [CHANGELOG.md](https://github.com/PakhomovAlexander/afactory/blob/main/CHANGELOG.md), [releases](https://github.com/PakhomovAlexander/afactory/releases) — `v0.15.2`, Authority compatibility по релизам

🔗 **Внутренние источники:**

- `docs/guide/architecture.md` — архитектура проекта
- `src/Module/AgentRunner/` — модуль запуска AI-агентов (JSONL-парсинг)
- `src/Module/ChainExecution/` — модуль исполнения цепочек (повторы, прерывание)
- `src/Module/DynamicLoop/` — динамические циклы (итеративные раунды, resume)
- `docs/research/agent-frameworks-summary.md` — сводная таблица трека (аналоги: ALK #35, pragmatic-orchestration #38, bx-dev #31, Orca ADE #30, qm #32)

## 9. Комментарии (Comments)

**Резюме объекта исследования (для контекста исполнителя, НЕ заменяет самостоятельного изучения источников):**

afactory — молодая (2026-08-22), но активно развиваемая (последний пуш 2026-11-26) open-source «coding factory» на Rust: конвейеры запуска внешних coding-агентов (Claude, Codex, а также Pi — крейт `af-runner-pi`) поверх Git-репозитория пользователя. Концептуально ближе всего к нашему движку во всём треке: те же ингредиенты (цепочки шагов, quality gates, бюджеты токенов, resume), но с механиками, которых у нас нет: токен-бесплатный план до расхода, резервирование бюджета на каждый воркер, коммиченная конфигурация с pinning по digest, state machine задачи с «следующей командой», постатейный ledger расходов, формализованные человеко-решения. Границы «commit/push/PR остаются за человеком» совпадают с нашей философией (запрет автоматических коммитов и merge без подтверждения). Affiliation с Pi-экосистемой (Pi SDK host) — дополнительный интерес: наш основной раннер — pi.

**Гипотезы тимлида к проверке:**

1. **Plan-before-spend:** токен-бесплатный план (`af review plan`) с последующим resume только по подтверждённому `PLAN_ID` — «замороженный план» как у ALK (#35). У нас исполнение цепочки стартует без отдельного артефакта плана. Вопрос: нужен ли нам plan artifact (шаги, ранкеры, бюджеты) + явное подтверждение перед расходом токенов?
2. **Gate fail-closed:** check-команда проекта выполняется до любых LLM-ревьюеров; «проверка, которая не смогла запуститься, — не pass». Сравнить с нашими quality gates: есть ли у нас явная fail-closed семантика, когда сам гейт не запустился (инфраструктурный сбой ≠ pass)?
3. **Per-worker budget reservation:** бюджет резервируется до старта каждого воркера, «tightest limit refuses by name», лимит останавливает новые работы, не прерывая идущий вызов. У нас бюджет на цепочку. Вопрос: нужен ли per-step/per-attempt резерв и семантика «мягкой» остановки?
4. **Pipeline as committed code:** `.af/` коммитится, воркеры пинятся по digest, изменение конвейера — диффуемое ревьюимое изменение. Аналогия с нашими YAML-цепочками: чем у нас обеспечивается воспроизводимость версии раннера и конфигурации между запусками?
5. **Task state machine:** состояние задачи называет следующую команду (plan → resume → ledger → report), JSON-выход и стабильные exit codes для агентного управления. Сравнить с нашим CLI-контрактом (`bin/console`) и resume DynamicLoop: можем ли мы формализовать «состояние → следующая команда»?
6. **Spend ledger:** постатейный учёт расхода каждого запуска (report показывает, что потратил каждый воркер). Сравнить с `docs/guide/observability.md`: есть ли у нас per-run расход токенов в отчётности?
7. **Human decisions as zero-token tasks:** `af review decide` планирует задачу решения без расхода токенов с инспекцией и явным подтверждением точного плана (closed batch contract). Сравнить с нашей ролевой моделью (человек апрувит PR); вопрос: нужны ли формализованные decision-артефакты для human-in-the-loop точек цепочки?

**Связь с треком:** ближайшие аналоги — Agent Lifecycle Kit (#35, frozen plan/proof), pragmatic-orchestration (#38, мульти-модельное ревью с судьёй), bx-dev (#31), Orca ADE (#30), qm (#32). Отличия afactory: конвейер = версионируемые файлы репозитория (а не скилл или внешний сервис); Rust; public alpha; раннеры только Claude/Codex/Pi; встроенный Pi SDK host. По стадии зрелости и философии границ («commit/push/PR — за человеком») аффиннее всего нашему движку из всех продуктов трека.

**Предварительный вердикт тимлида (уточнить по итогам):** 🟡 заимствовать паттерны (plan-before-spend, gate fail-closed, per-worker budget reservation, digest pinning, task state machine, spend ledger, zero-token decision tasks) / 🔴 не dependency (Rust CLI в alpha-стадии; наш движок сам — оркестратор, интеграция означала бы смену архитектуры).

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-26 12:43:53 (1795697033) | Тимлид Алекс (pi) | Создание задачи |
| 2026-11-26 | Тимлид (Алекс) | Заполнена постановка по итогам предварительного ресерча (релиз `v0.15.2` от 2026-11-25, снапшот main `7760fd1cf85674f538b08ae8d2b7ee9d3adca37f` от 2026-11-26): методология эпика, 7 гипотез, предварительный вердикт. Исполнитель — Аналитик Шерлок (pi). |
