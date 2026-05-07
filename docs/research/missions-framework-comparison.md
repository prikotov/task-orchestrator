# Исследование: Missions Framework (Factory AI) — multi-day autonomous software development

> **Проект:** [factory.ai](https://factory.ai) (проприетарный, закрытый исходный код)
> **Дата анализа:** 2026-05-07
> **Язык:** Закрытый исходный код (TypeScript backend, по наблюдаемому поведению)
> **Лицензия:** Проприетарный (Factory AI, Inc.)
> **Аналитик:** Аналитик (Шерлок)

---

## 1. Обзор проекта

Factory AI — компания (Series C, $150M, оценка $1.5B, Khosla Ventures), создавшая платформу **Droids** (AI-кодинг-агенты) и **Missions** (систему оркестрации этих агентов для multi-day автономной разработки ПО). Ключевая идея: вместо растягивания одного context window на много дней, Missions использует **orchestrator-worker-модель** — планировщик декомпозирует цель на milestones и features, затем запускает отдельные worker-сессии с чистым контекстом для каждой задачи.

> «The bottleneck in modern software engineering is human attention, not intelligence.» — Luke Alvoeiro

**Доклад:** [Missions: Multi-Agent Systems That Ship for Days — Luke Alvoeiro, Factory](https://www.youtube.com/watch?v=ow1we5PzK-o) (AI Engineer, 2026)

**Архитектура Missions в корне отличается от task-orchestrator:** Missions — это коммерческий SaaS-продукт для autonomous software engineering, работающий поверх собственных AI-ассистентов (Factory Droids). Task-orchestrator — CLI-фреймворк для chain-оркестрации внешних runner'ов. Разные уровни абстракции, но пересекающиеся паттерны.

### Архитектура

```
┌──────────────────────────────────────────────────────────────┐
│                     User (Developer)                         │
│   • Describes mission goals via Factory CLI / Web UI         │
│   • Reviews proposals, provides clarifications               │
└───────────────────────────┬──────────────────────────────────┘
                            │
                            ▼
┌──────────────────────────────────────────────────────────────┐
│               Orchestrator (claude-opus-4-6)                  │
│            51KB mission-specific system prompt                │
│                                                              │
│  Responsibilities:                                           │
│   • Plan & decompose → milestones, features                  │
│   • Create shared state (mission.md, AGENTS.md, .factory/)   │
│   • Delegate features → workers via start_mission_run        │
│   • Handle worker handoffs & mid-mission user requests       │
│   • Track requirements & quality enforcement                 │
│                                                              │
│  Tools: ProposeMission, StartMissionRun,                     │
│         DismissHandoffItems, AskUser, Task (subagents)       │
└──────────┬───────────────────────────────┬───────────────────┘
           │                               │
    ┌──────┴──────┐              ┌─────────┴─────────┐
    ▼             ▼              ▼                   ▼
┌─────────┐  ┌─────────┐  ┌──────────┐  ┌───────────────────┐
│ Worker  │  │ Worker  │  │ Validator│  │ Validator         │
│ (opus)  │  │ (opus)  │  │ Scrutiny │  │ User-Testing      │
│         │  │         │  │ (inject) │  │ (inject)          │
│ Code    │  │ Code    │  │          │  │                   │
│ only    │  │ only    │  │ test +   │  │ behavioral        │
│         │  │         │  │ lint +   │  │ assertions via    │
│ No      │  │ No      │  │ typecheck│  │ flow validators   │
│ AskUser │  │ AskUser │  │ + review │  │ + synthesis       │
│ No Task │  │ No Task │  │ subagents│  │ + evidence capture│
└────┬────┘  └────┬────┘  └──────────┘  └───────────────────┘
     │            │
     └────────────┴──────────────────────────────────┐
                                                     ▼
┌──────────────────────────────────────────────────────────────┐
│                  Shared State (on disk)                       │
│                                                              │
│  missionDir:                                                 │
│   • mission.md (proposal, requirements)                      │
│   • validation-contract.md (behavioral assertions, TDD)      │
│   • validation-state.json (assertion status tracker)         │
│   • features.json (ordered feature list)                     │
│   • AGENTS.md (operational guidance for workers)             │
│                                                              │
│  repo root:                                                  │
│   • .factory/skills/{worker-type}/SKILL.md                   │
│   • .factory/services.yaml (commands + services manifest)    │
│   • .factory/init.sh (idempotent env setup)                  │
│   • .factory/library/ (knowledge base, flat structure)       │
│   • .factory/validation/<milestone>/ (reports)               │
└──────────────────────────────────────────────────────────────┘
```

### Три роли агентов

| Роль | Модель | Инструменты | Контекст | Ответственность |
|---|---|---|---|---|
| **Orchestrator** | claude-opus-4-6 | ProposeMission, StartMissionRun, DismissHandoffItems, AskUser, Task (subagents) | 51KB mission prompt + full mission history | Планирование, декомпозиция, делегирование, контроль качества |
| **Worker** | claude-opus-4-6 | Кодирование, файлы, shell | 4KB prompt + AGENTS.md + mission.md + skill + feature description | Реализация конкретной фичи в одной сессии |
| **Validator** | claude-opus-4-6 | Кодирование, тесты, shell | missionDir + services.yaml + library | Автоматическая валидация (scrutiny + user-testing) |

### Ключевые характеристики

| Характеристика | Значение |
|---|---|
| **Тип** | Проприетарный SaaS-продукт (multi-day autonomous software development) |
| **Модель выполнения** | Orchestrator → Worker delegation (serial feature execution) |
| **AI-провайдер** | claude-opus-4-6 (model-agnostic дизайн) |
| **State management** | File-based (mission.md, features.json, validation-state.json, AGENTS.md, .factory/) |
| **Mission prompt** | 50,960 символов (orchestrator), 3,872 символов (worker) |
| **Расширяемость** | SKILL.md per worker type, .factory/library/, services.yaml |
| **Валидация** | Двухуровневая: scrutiny (test/lint/typecheck + subagent review) + user-testing (behavioral assertions) |
| **Пять паттернов коммуникации** | Delegation, Creator-Verifier, Broadcast (shared state), Negotiation (user), Direct (subagents) |
| **Production-результаты** | Missions до 16 дней, 50% кода = тесты, 90% test coverage |
| **Архитектурный принцип** | Prompt-driven (не hard-coded — улучшается с моделями) |

### Рабочий процесс Mission (4 фазы)

```
Phase 1: Mission Planning         Phase 2: Worker Design
┌──────────────────────┐          ┌──────────────────────┐
│ User describes goal   │          │ Determine worker types│
│ Orchestrator asks     │          │ Create SKILL.md per   │
│   clarifying questions│          │   worker type         │
│ Investigate codebase  │          │ Design handoff format │
│   via subagents       │          │ Define acceptance     │
│ Confirm milestones    │          │   criteria            │
│ Capture ALL           │          │                      │
│   requirements        │          │                      │
└──────────┬───────────┘          └──────────┬───────────┘
           │                                  │
           └──────────┬───────────────────────┘
                      ▼
           Phase 3: Creating Mission Artifacts
           ┌──────────────────────────────────┐
           │ validation-contract.md (TDD!)     │
           │ validation-state.json (pending)   │
           │ features.json (ordered list)      │
           │ AGENTS.md (boundaries + guidance) │
           │ .factory/skills/                  │
           │ .factory/services.yaml            │
           │ .factory/init.sh                  │
           │ .factory/library/                 │
           └──────────────┬───────────────────┘
                          ▼
           Phase 4: Managing Execution
           ┌──────────────────────────────────┐
           │ start_mission_run (blocking)     │
           │   → Worker 1: feature A          │
           │   → Worker 2: feature B          │
           │   → ... (serial execution)       │
           │ Milestone complete?               │
           │   → scrutiny-validator (auto)    │
           │   → user-testing-validator (auto)│
           │ Handle handoffs:                  │
           │   → fix features? → re-run       │
           │   → user input? → pause          │
           │ All milestones sealed?            │
           │   → ALL assertions passed? → DONE│
           └──────────────────────────────────┘
```

---

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | Task Orchestrator | Factory Missions | Статус |
|---|---|---|---|
| **Цепочки шагов (chains)** | ✅ YAML chains, статические и динамические | ✅ features.json (ordered array, serial execution) | ✅ Паритет (разный подход) |
| **Оркестратор (центральный агент)** | ✅ Chain Executor (PHP-код) | ✅ LLM-оркестратор (51KB prompt, opus) | 🟡 Разная парадигма |
| **Делегирование (delegation)** | ✅ Chain step → runner | ✅ Orchestrator → Worker (start_mission_run) | ✅ Паритет (разный подход) |
| **Validation Contract (mission-level TDD)** | ❌ Нет (quality gates = shell-команды) | ✅ validation-contract.md + validation-state.json — поведенческие assertion'ы пишутся ДО кода | 🟡 Очень интересно |
| **Structured Handoffs** | ❌ Нет (runner вывод → текст) | ✅ Worker returns: successState, discoveredIssues, whatWasLeftUndone, handoffFile | 🟡 Очень интересно |
| **Milestones + Sealing** | ❌ Нет | ✅ Vertical slices + sealed milestones (never add after validation) | 🟡 Интересно |
| **Serial feature execution** | ✅ Serial chain execution | ✅ Serial feature execution (avoid agent conflicts) | ✅ Паритет |
| **Shared state on disk** | ❌ In-memory + JSONL audit | ✅ mission.md, AGENTS.md, .factory/ (all file-based) | 🟡 Интересно |
| **Retry с backoff** | ✅ RetryingAgentRunner | ❌ Нет (failed feature → orchestrator handles) | ✅ У нас есть |
| **Circuit Breaker** | ✅ CircuitBreakerAgentRunner | ❌ Нет | ✅ У нас есть |
| **Quality Gates** | ✅ Shell-команды как проверки | ✅ Two-level: scrutiny (test/lint/typecheck + review subagents) + user-testing (behavioral assertions) | 🟡 Более развитые |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based) | ❌ Нет явного budget control | ✅ У нас есть |
| **Fix iterations** | ✅ Группа шагов с max_iterations | ✅ Failed feature → fix features → re-run validator (incremental) | ✅ Паритет (разный подход) |
| **Fallback routing** | ✅ Per-step fallback runner | ❌ Нет (model-agnostic design, но один провайдер) | ✅ У нас есть |
| **Subagents (Task tool)** | ❌ Нет | ✅ Orchestrator delegates to subagents for investigation, review, research | 🟡 Интересно |
| **Mid-mission scope changes** | ❌ Нет (chain = static definition) | ✅ Formal procedure: pause → investigate → update all shared state → update contract → resume | 🟡 Очень интересно |
| **Requirement tracking** | ❌ Нет | ✅ Every requirement tracked, echo-back to user, propagate to all files | 🟡 Интересно |
| **Mission Boundaries** | ❌ Нет | ✅ Explicit constraints (port ranges, off-limits resources, never violate) | 🟡 Интересно |
| **Worker skills (SKILL.md)** | ✅ Role .md файлы (18+ ролей) | ✅ .factory/skills/{worker-type}/SKILL.md per worker type | ✅ Паритет |
| **Services manifest** | ❌ Нет (runner'ы сами управляют) | ✅ .factory/services.yaml (commands + services + ports + healthchecks) | 🟡 Интересно |
| **Knowledge library** | ❌ Нет | ✅ .factory/library/ (flat structure, topic files) | 🟡 Интересно |
| **AGENTS.md as operational guidance** | ✅ AGENTS.md (project-level) | ✅ AGENTS.md per mission (boundaries + conventions + testing guidance) | ✅ Паритет |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❌ Monolithic prompt-driven (51KB system prompt) | ✅ У нас лучше |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Direct tool invocation | ✅ У нас лучше |
| **Proprietary / Open-source** | ✅ Open-source (MIT) | 🔴 Проприетарный SaaS | 🔴 Не dependency |
| **Язык реализации** | PHP 8.4 / Symfony 8.0 | Prompt-driven (TypeScript backend, закрытый) | — |

---

## 3. Что полезно взять и почему

### 3.1 🟡 Validation Contract — mission-level TDD

**Что у них:** Перед любым кодом пишется `validation-contract.md` — конечный чеклист тестируемых поведенческих assertion'ов, определяющих «done». Каждый assertion имеет стабильный ID (VAL-AUTH-001), заголовок, описание поведения и требования к evidence. Assertion'ы организованы по областям + cross-area flows. Запускается coverage-check: каждый assertion должен быть покрыт ровно одним feature.

```markdown
## Area: Authentication

### VAL-AUTH-001: Successful login
A user with valid credentials submits the login form and is redirected to the dashboard.
Evidence: screenshot, console-errors, network(POST /api/auth/login -> 200)
```

**Почему нам интересно:** Наши quality gates — это shell-команды (пост-фактум проверки). Validation contract — это **декларативная спецификация корректности**, независимая от имплементации. Концепция применима на уровне chain: определить assertion'ы для всей цепочки перед выполнением, затем проверить каждый после завершения.

**Как адаптировать:** Не обязательно behavioural assertions — можно начать с JSON Schema для output каждого шага + `until_bash` для детерминированных проверок + shell-based gates как сейчас. Validation contract как паттерн мышления (TDD на уровне chain), а не как формат файла.

### 3.2 🟡 Structured Handoffs между шагами

**Что у них:** Workers возвращают структурированные handoffs:
- `successState`: `"success"` | `"failure"` | `"partial"`
- `discoveredIssues`: массив найденных проблем (обязательно отслеживать)
- `whatWasLeftUndone`: незавершённая работа
- `handoffFile`: путь к файлу с полной детализацией

Orchestrator решает: fix within mission → user input required → escalate.

**Почему нам интересно:** У нас runner возвращает текстовый вывод. Structured handoff позволил бы chain executor'у принимать решения: если `successState = "partial"` → retry с доп. контекстом, если `discoveredIssues` → добавить fix-шаг, если `whatWasLeftUndone` → insert additional step.

**Как адаптировать:** Определить схему handoff для runner output (JSON поверх текста). Chain executor парсит handoff и принимает решения. Начать с простого: `success: bool`, `issues: string[]`, `next_action: "continue" | "retry" | "stop"`.

### 3.3 🟡 Milestones + Sealing (границы завершённых этапов)

**Что у них:** Mission декомпозируется на milestones (вертикальные срезы). Когда все features в milestone завершены:
1. Автоматически инжектируется scrutiny-validator (test + lint + typecheck + review subagents)
2. Автоматически инжектируется user-testing-validator (behavioral assertions + flow validation)
3. Если валидация проходит → milestone **seals** (замораживается)
4. После sealed: **никогда** не добавлять features в этот milestone

Если нужна доработка sealed milestone → `misc-*` milestone или follow-up milestone.

**Почему нам интересно:** У нас нет концепции «завершённого этапа» — chain выполняется последовательно, и любой шаг может быть повторён. Sealing обеспечивает прогресс: «эта часть готова, не трогаем». Для длинных multi-step chains это предотвратило бы regression.

**Как адаптировать:** Для простых chains — не нужно (chain = один атомарный запуск). Для dynamic chains / fix_iterations: после завершения fix loop → seal (запретить модификацию уже проверенных шагов). Реализуемо через metadata в chain execution.

### 3.4 🟡 Fresh Context per Worker Session

**Что у них:** Каждый worker стартует с чистым контекстом. Worker читает state с диска (mission.md, AGENTS.md, SKILL.md, feature description). Нет «накопления» контекста предыдущих worker'ов.

**Почему нам интересно:** Напрямую связано с нашей DynamicLoop — если agent loop повторяется много раз, контекст разрастается. Archon предлагает `fresh_context: true` для loop nodes. Missions доводит идею до логического завершения: каждый worker = чистая сессия.

**Как адаптировать:** Для fix_iterations: опциональный режим, где каждый iteration начинается с чистого контекста (agent читает state payload с диска, а не из накопившегося контекста). Требует персистентного payload storage.

### 3.5 🟡 Five Multi-Agent Communication Patterns

**Что у них:** Missions использует пять паттернов коммуникации:
1. **Delegation**: orchestrator → worker (start_mission_run)
2. **Creator-Verifier**: implementation → validation (auto-injected validators)
3. **Broadcast**: orchestrator → all workers (через shared state: mission.md, AGENTS.md)
4. **Negotiation**: orchestrator ↔ user (AskUser tool, clarifying questions)
5. **Direct**: subagents (Task tool для investigation, review, research)

**Почему нам интересно:** Это таксономия паттернов для multi-agent систем. У нас есть (1) Delegation (chain step → runner) и зачатки (3) Broadcast (payload между шагами). Паттерн Creator-Verifier — это наши quality gates, но Missions интегрирует его формально (auto-injected validators). Negotiation — для interactive mode (частично).

**Как адаптировать:** Использовать таксономию для проектирования расширений: когда добавляем validation → паттерн Creator-Verifier, когда добавляем HITL → паттерн Negotiation, когда добавляем sub-agents → паттерн Direct.

### 3.6 🟡 Mission Boundaries (явные ограничения)

**Что у них:** AGENTS.md содержит секцию Mission Boundaries — жёсткие ограничения:
- Port ranges (e.g., 3100-3199)
- External services (USE existing postgres, DO NOT touch redis)
- Off-limits resources (/data directory, port 3000)
- Workers MUST NEVER violate boundaries → при невозможности → return to orchestrator

```markdown
## Mission Boundaries (NEVER VIOLATE)
**Port Range:** 3100-3199. Never start services outside this range.
**Off-Limits:** /data directory - do not read or modify
Workers: If you cannot complete your work within these boundaries, return to orchestrator.
```

**Почему нам интересно:** Для автономного выполнения chains в CI/CD нужны аналогичные ограничения. Наши quality gates — это post-execution проверки. Mission boundaries — pre-execution ограничения, которые runner видит ДО начала работы.

**Как адаптировать:** Добавить секцию `boundaries` в chain definition YAML. Runner'ы получают boundaries как часть payload. При нарушении → fail-fast. Простой quick win: `allowed_paths`, `banned_commands`, `max_timeout`.

### 3.7 🟡 Services Manifest (.factory/services.yaml)

**Что у них:** Единый файл для всех команд и сервисов, которые используют workers:

```yaml
commands:
  install: pnpm install
  test: npm run test
  build: turbo build

services:
  postgres:
    start: docker compose up -d postgres
    stop: docker compose stop postgres
    healthcheck: pg_isready -h localhost -p 5432
    port: 5432
    depends_on: []
```

Workers читают services.yaml — не угадывают команды. Port hardcoded в каждой команде.

**Почему нам интересно:** У нас runner'ы получают payload через chain, но нет единого манифеста «как запустить окружение». Для chains, требующих запуска тестов, серверов, БД — это ценная абстракция.

**Как адаптировать:** Опциональный `services.yaml` в chain definition. Runner'ы могут ссылаться на `services.test` вместо хардкода. Не обязательно для P1, но полезно для chains с комплексным окружением.

### 3.8 🟡 Requirement Tracking (отслеживание требований)

**Что у них:** Orchestrator обязан:
- Поддерживать mental inventory ВСЕХ требований (даже вскользь упомянутых)
- Echo-back каждого требования перед proposal
- Распространять изменения во ВСЕ файлы, содержащие «старую правду»
- Mid-mission scope changes: формальная процедура с 9 шагами

**Ключевой принцип:** «Every file that states the old truth must be updated to state the new truth before workers resume.»

**Почему нам интересно:** Для сложных chains с множеством шагов актуально: если mid-execution меняются требования (пользовательский ввод, ошибка), нужно propagate изменения в downstream шаги. У нас это решается через dynamic chains, но формализации нет.

**Как адаптировать:** Для task-orchestrator — это patтерн мышления, а не реализация. При dynamic chain resolution: если обнаружено изменение requirements → пересоздать downstream steps. Формализовать через metadata payload.

---

## 4. Что НЕ берём и почему

### 4.1 🟢 LLM-as-Orchestrator (51KB system prompt)

Missions использует LLM (claude-opus-4-6) как оркестратор — 51KB промпт определяет поведение, инструменты и процедуры. Это «prompt-driven architecture». Task-orchestrator использует PHP-код (Chain Executor) как оркестратор — детерминированный, предсказуемый, тестируемый.

LLM-as-orchestrator даёт гибкость (адаптируется к неожиданным ситуациям), но платит ненадёжностью (галлюцинации, непредсказуемые решения, деградация контекста). Для нашего случая (CI/CD, автоматизация) — детерминированный оркестратор правильный выбор.

### 4.2 🟢 Проприетарная платформа (Factory Droids)

Factory — SaaS-продукт с закрытым исходным кодом. Droids — проприетарные AI-ассистенты, доступные через Factory CLI / Web UI. Нельзя использовать как dependency, нельзя форкнуть, нельзя модифицировать. Анализ ограничен наблюдением за паттернами.

### 4.3 🟢 User-Testing Validator (behavioral assertions через LLM)

Missions инжектирует user-testing validator, который через LLM-субагентов тестирует behavioral assertions (GUI interactions, flows). Это мощно, но требует: (а) GUI-доступ к приложению, (б) LLM-модели для тестирования, (в) significant compute.

Для task-orchestrator (CLI) — это overengineering. Наши shell-based quality gates покрывают практические случаи (test suite, lint, typecheck).

### 4.4 🟢 Mid-Mission User Interaction (AskUser)

Missions позволяет orchestrator'у задавать пользователю вопросы mid-execution (1-4 multiple-choice questions через AskUser tool). Для multi-day миссий это критично (требования меняются). Для наших chains (запустил → ушёл → получил результат) — не подходит. Исключение: interactive mode, но это P3+.

### 4.5 🟢 Online Research Subagents

Missions позволяет orchestrator'у делегировать online research субагентам (WebSearch, FetchUrl). Для task-orchestrator — не актуально, наши chains работают с кодом, а не с веб-поиском.

### 4.6 🟢 Prompt-Driven Architecture

Factory сознательно выбрала «prompt-driven, not hard-coded» — система улучшается по мере улучшения моделей. task-orchestrator сознательно выбрал «hard-coded, deterministic» — PHP-код даёт предсказуемость, тестируемость, воспроизводимость. Это философское различие, не проблема.

### 4.7 🟢 Subagent Pattern (Task tool)

Orchestrator делегирует investigation/review subagents через Task tool. У нас нет subagents (каждый chain step = один runner call). Добавление subagents = significant architectural change (P3+). Связано с паттерном sub-agent из Archon, Codex, Claude Code.

---

## 5. Фокус-анализ: delegation, verification, structured communication, multi-day workflow coherence

### Delegation (делегирование)

**Missions:** Orchestrator делегирует через `start_mission_run` — blocking call, workers выполняются serial. Каждый worker получает: mission.md + AGENTS.md + SKILL.md + feature description (с preconditions, expectedBehavior, verificationSteps). Worker НЕ имеет доступа к mission tools (ProposeMission, AskUser, Task). Явное разделение: orchestrator планирует, worker реализует.

**Task-orchestrator:** Chain executor делегирует через runner interface. Каждый step получает payload + runner config. Runner не имеет доступа к chain definition или другим шагам.

**Вердикт:** Паттерн делегирования похож. Ключевое отличие: Missions передаёт structured context (mission.md, preconditions, expectedBehavior, verificationSteps), а мы передаём payload (free-form). Structured context делает делегирование более надёжным.

**Заимствовать:** Structured feature description (preconditions + expectedBehavior + verificationSteps) как опциональное расширение chain step definition.

### Verification (верификация)

**Missions:** Трёхуровневая верификация:
1. **Worker self-verification**: verificationSteps в feature description
2. **Scrutiny validator** (auto-injected): test + lint + typecheck + review subagents
3. **User-testing validator** (auto-injected): behavioral assertions + flow validation + evidence capture

`validation-contract.md` (assertions) пишется ДО кода. `validation-state.json` отслеживает статус каждого assertion. Coverage gate: каждый assertion покрыт ровно одной feature.

**Task-orchestrator:** Одноуровневая верификация:
1. **Quality gates** (shell-команды): post-step проверки
2. **fix_iterations**: closed-loop для исправления

**Вердикт:** Missions значительно превосходит в формализации верификации. Validation contract (TDD at mission level) — самый интересный паттерн. Но для наших use cases (chain steps, не multi-day missions) трёхуровневая верификация избыточна.

**Заимствовать:** Идею validation contract как паттерн мышления. Усиление quality gates через structured assertions (JSON Schema output validation). `until_bash` как детерминированная проверка завершения.

### Structured Communication (структурированная коммуникация)

**Missions:** Пять паттернов коммуникации (delegation, creator-verifier, broadcast, negotiation, direct). Shared state через файловую систему (mission.md, AGENTS.md, features.json, validation-state.json). Structured handoffs (successState, discoveredIssues, whatWasLeftUndone). Библиотека знаний (.factory/library/).

**Task-orchestrator:** Коммуникация через payload (in-memory) + JSONL audit trail. Runner output = текст.

**Вердикт:** Missions формализовала коммуникацию на уровне, которого у нас нет. Но это обусловлено multi-day, multi-agent контекстом. Для наших коротких chains файловый shared state избыточен.

**Заимствовать:** Structured handoffs (JSON-схема для runner output). Knowledge library как паттерн (может быть реализован через AGENTS.md с секциями).

### Multi-Day Workflow Coherence (согласованность много-дневных workflows)

**Missions решает эту проблему через:**
1. **Fresh context per worker** — нет деградации от накопления контекста
2. **Shared state on disk** — state переживает переcreation контекста
3. **Validation contract** — определение «done» не зависит от конкретной имплементации
4. **Sealed milestones** — завершённые этапы не модифицируются
5. **Requirement tracking** — все требования отслеживаются и распространяются
6. **Mission Boundaries** — жёсткие ограничения, не зависящие от контекста

**Task-orchestrator:** DynamicLoop — цикл с max_iterations, но нет persistent state между запусками. Coherence обеспечивается только в рамках одной chain execution.

**Вердикт:** Missions решает проблему, которой у нас пока нет (multi-day workflows). Но паттерны (fresh context, sealed milestones, validation contract) применимы к нашим dynamic chains и fix_iterations.

**Заимствовать:** Fresh context per iteration (опционально для DynamicLoop). Sealed milestones для длинных chains (не пересоздавать уже успешные шаги).

---

## 6. Сводка рекомендаций

| Фича | Приоритет | Обоснование |
|---|---|---|
| Chain orchestration (YAML chains) | ✅ Уже есть | Core-функциональность |
| Retry + Circuit Breaker + Budget | ✅ Уже есть | Устойчивость при сбоях |
| Quality Gates (shell-based) | ✅ Уже есть | Автоматическая проверка |
| Fix iterations (DynamicLoop) | ✅ Уже есть | Closed-loop разработка |
| Structured handoffs (JSON-схема runner output) | 🟡 P2 | Явные решения chain executor'а на основе runner output |
| Mission Boundaries в chain definition | 🟡 P2 | Pre-execution ограничения для автономного выполнения |
| Validation contract (TDD at chain level) | 🟡 P2 | Structured assertions → усиление quality gates |
| Fresh context per iteration | 🟡 P2 | Опционально для DynamicLoop (аналог Archon `fresh_context`) |
| Services manifest (services.yaml) | 🟡 P3 | Для chains с комплексным окружением |
| Milestone sealing | 🟡 P3 | Для длинных multi-step dynamic chains |
| Structured feature description | 🟡 P3 | preconditions + expectedBehavior + verificationSteps per step |
| Knowledge library (.factory/library/) | 🟡 P3 | Персистентная база знаний для сложных chains |
| Five communication patterns taxonomy | 🟢 — | Паттерн мышления, не реализация |
| LLM-as-orchestrator | 🟢 — | Разная философия: детерминированный vs. гибкий |
| User-testing validators (LLM-based) | 🟢 — | Overengineering для CLI |
| Mid-mission user interaction | 🟢 — | Только interactive mode (P3+) |
| Subagent pattern | 🟢 — | Significant architectural change (P3+) |

### Вердикт

**🟡 Заимствовать паттерны**

Factory Missions — **проприетарный SaaS-продукт**, не может быть dependency. Но паттерны, выявленные через реверс-инжиниринг system prompts, **исключительно ценны**:

1. **Validation Contract (mission-level TDD)** — самый интересный паттерн. Определение «done» до начала работы. Непосредственно применимо к усилению наших quality gates.

2. **Structured Handoffs** — runner output как JSON с явным success/partial/failure + issues. Позволяет chain executor'у принимать intelligent decisions.

3. **Mission Boundaries** — pre-execution ограничения (port ranges, off-limits resources). Quick win для автономного выполнения.

4. **Fresh Context per Worker Session** — каждый worker/iteration с чистым контекстом, state с диска. Решает проблему деградации в long-running loops.

5. **Milestone Sealing** — завершённые этапы замораживаются. Для длинных chains — защита от regression.

**Почему не dependency:** Проприетарный продукт, закрытый исходный код, SaaS-only. Работает на уровне полноценного AI-ассистента (Droids), а не chain-оркестрации.

**Почему не «не подходит»:** Паттерны delegation, verification, structured communication и workflow coherence — напрямую пересекаются с нашими задачами. Factory подтвердила production-viability (missions до 16 дней, 90% test coverage). Архитектурные решения (fresh context, validation-first, sealed milestones) применимы к task-orchestrator.

---

## 7. Указатель источников

- [YouTube: Missions: Multi-Agent Systems That Ship for Days — Luke Alvoeiro, Factory](https://www.youtube.com/watch?v=ow1we5PzK-o) — доклад на AI Engineer (2026)
- [GitHub Gist: Factory Droid /missions — Complete Orchestrator & Worker prompts reverse engineered](https://gist.github.com/V1ki/356b121038722ebf32b5aac85482c113) — реверс-инжиниринг промптов (51KB orchestrator prompt, 3 mission tools, worker delegation model), извлечено 2026-03-25
- [Factory.ai/news/missions](https://factory.ai/news/missions) — официальный анонс Missions
- [Hacker News: Factory introduces "Missions"](https://news.ycombinator.com/item?id=47182879) — обсуждение: orchestrator-worker model, fresh context per feature, multi-day autonomous execution
- [daily.dev: Multi-Agent Systems That Ship for Days](https://app.daily.dev/posts/multi-agent-systems-that-ship-for-days-luke-alvoeiro-factory-bf6o18zfb) — краткое изложение доклада: три роли (orchestrator/worker/validator), 5 паттернов коммуникации, production-результаты (16 дней, 50% тестов, 90% coverage), prompt-driven архитектура

📚 **Источники:**
1. [YouTube: Missions — Luke Alvoeiro, Factory](https://www.youtube.com/watch?v=ow1we5PzK-o)
2. [GitHub Gist: Reverse-engineered prompts](https://gist.github.com/V1ki/356b121038722ebf32b5aac85482c113)
3. [factory.ai/news/missions](https://factory.ai/news/missions)
4. [Hacker News discussion](https://news.ycombinator.com/item?id=47182879)
5. [daily.dev summary](https://app.daily.dev/posts/multi-agent-systems-that-ship-for-days-luke-alvoeiro-factory-bf6o18zfb)
