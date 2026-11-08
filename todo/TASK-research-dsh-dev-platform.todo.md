---
type: docs
created: 2026-11-08 14:06:49 (1794146809)
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
epic: 
author: Тимлид Алекс (pi)
assignee: 
consultants: []
reviewer: 
approver: 
informed: []
branch: 
pr: 
status: todo
---

# TASK-research-dsh-dev-platform: Feasibility платформы разработки на базе dsh (brainstorm + плюсы/минусы)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Владелец рассматривает продуктовую идею: **платформа разработки на базе dsh** (`deepseek-ai/deepseek-harness`) — доска задач (task board) как интерфейс к dsh, метрики проекта, агенты и цепочки с удобными интерфейсами; по сути — экосистема task-orchestrator, встроенная в dsh и сделанная удобной.
- Референс: **dsh-trading** (`zhu1090093659/dsh-trading`, TypeScript, ≈315★, PolyForm NC 1.0.0) — агентно-нативный трейдинг-терминал на DSH 0.2.2-rc.1 + Cordis-микроядро: ролевая multi-agent команда (Master / Researcher / Trader / Risk Reviewer ≈ наши роли), плейбуки и чек-листы (≈ наши SKILL.md), dual-track approval gate с dry-run (≈ наша приёмка владельцем), append-only журнал (≈ наш audit.jsonl), 19+ коннекторов. Паттерн «прикладная платформа поверх dsh» доказан — вопрос в том, работает ли он для разработки.
- Неизвестно: **стоит ли строить** (и в каком виде), какие плюсы и минусы у каждой альтернативы, что говорит команда. Решение должно быть принято через **мозговой штурм команды** (скилл `brainstorm`), а не единолично.

### Варианты или путь решения (Solution Sketch)
- Изучить референс dsh-trading и поверхности dsh (Web UI `dsh web`, JSON-RPC SDK TS+Python, plugin-архитектура «Everything is a Plugin» на Cordis, ACP) — по данным ресерча #22 (`docs/research/coding-agents/deepseek-harness-comparison.md`) и свежего состояния репозиториев.
- **Провести brainstorm-сессию команды** через скилл [`brainstorm`](../docs/agents/skills/brainstorm/SKILL.md): `php vendor/bin/task-orchestrator agent:orchestrate "<тема>" --chain=brainstorm` — фасилитатор направляет спор, участники отстаивают позиции, итог — протокол с решениями.
- Свести плюсы/минусы альтернатив и вердикт в feasibility-отчёт; решение о продуктовом эпике (или отказе) — за владельцем.

### Ожидаемый результат (Expected Result)
- Протокол brainstorm-сессии с позициями участников, спорами и решениями.
- Feasibility-отчёт: сравнение альтернатив по плюсам/минусам, риски, вердикт «стоит / не стоит» (и в каком виде: платформа на dsh / UI-слой над нашим движком / не строить).
- Основание для решения владельца: создавать продуктовый эпик или закрыть направление.

## 1. Концепция и Цель (Concept and Goal)

### Story (Job Story)
> **Job Story:** Когда у нас появляется идея встроить экосистему task-orchestrator (доска задач, метрики, агенты, цепочки) в dsh с удобными интерфейсами, я хочу, чтобы команда коллективно обсудила «стоит / не стоит» через мозговой штурм с конструктивным конфликтом, и получил протокол спора, плюсы/минусы каждой альтернативы и обоснованный вердикт — до вложений в разработку.

### Цель по SMART (Goal)
К закрытию задачи: проведена brainstorm-сессия команды (скилл `brainstorm`, ≥3 участников с разными позициями, фасилитатор — Тимлид Алекс), её протокол сохранён в `docs/research/dsh-dev-platform/brainstorm-protocol.md`; создан feasibility-отчёт `docs/research/dsh-dev-platform/feasibility.md` с плюсами/минусами ≥3 альтернатив и вердиктом; владельцу передано основание для решения о продуктовом эпике.

### Тема brainstorm (закреплена)
> **Стоит ли строить платформу разработки на базе dsh** (доска задач как интерфейс к dsh, метрики проекта, агенты, цепочки с удобными интерфейсами — экосистема task-orchestrator внутри dsh)? Референс — dsh-trading (агентно-нативный терминал на DSH+Cordis). Альтернативы для спора: (а) платформа на базе dsh, (б) UI-слой над нашим PHP-оркестратором без dsh, (в) не строить / отложить. Плюсы и минусы каждого варианта, риски (dsh — developer preview, второй рантайм TS+PHP, контракт control plane), стоимость.

### Состав участников (рекомендация, исполнитель может скорректировать)
- **Тимлид Алекс** — фасилитатор (дефолт цепочки).
- **Архитектор Гэндальф** — стратег, системность (вариант «платформа на dsh»).
- **Архитектор Локи** — скептик, слепые зоны и альтернативы (контрпозиция).
- **Аналитик Шерлок** — требования, сценарии использования, edge cases.
- **Бэкендер Тони** — прагматизм, скорость и стоимость (версия «минимум усилий»).
- **Бэкендер Левша** — реализуемость на нашем стеке, аккуратность (версия «надёжно»).

Запуск (при реализации):
```bash
php vendor/bin/task-orchestrator agent:orchestrate \
  "Стоит ли строить платформу разработки на базе dsh (доска задач, метрики, агенты, цепочки; референс dsh-trading)? Альтернативы: платформа на dsh / UI-слой над task-orchestrator / не строить" \
  --chain=brainstorm \
  --participants=system_architect_gandalf,system_architect_loki,system_analyst_sherlock,backend_developer_tony,backend_developer_levsha \
  --report-format=text \
  --report-file=docs/research/dsh-dev-platform/brainstorm-protocol.md
```

## 2. Контекст и Границы (Context and Scope)

- **Объекты:** идея владельца «экосистема task-orchestrator внутри dsh»; референс dsh-trading (README, docs/current-state.md, структура репозитория — по данным GitHub API/README, без запуска); поверхности dsh из ресерча #22 (`docs/research/coding-agents/deepseek-harness-comparison.md`, `docs/research/coding-agents-summary.md` строка #22: вердикт ⚠️ Частично 7/10, SDK ✅ 9/10, developer preview).
- **Где делаем:** `docs/research/dsh-dev-platform/brainstorm-protocol.md`, `docs/research/dsh-dev-platform/feasibility.md`, agent-report в `docs/agents/reports/`.
- **Текущее состояние:** движок task-orchestrator (PHP, Symfony, DDD) — файловая экосистема: `todo/*.todo.md` с метаданными, `config/chains.yaml`, роли со `SKILL.md`, audit.jsonl, ретроспективы; UI как такового нет. dsh — долгоживущий TS-харнес с Web UI и SDK, статус developer preview без релизов.
- **Границы (Out of Scope):** реализация платформы или прототипа, код/конфиги, выбор окончательной архитектуры (это — отдельный эпик, если вердикт «стоит»), полный повторный ресерч dsh (только дельты по данным #22 и актуального состояния), интеграция с внешними сервисами.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [ ] **Brainstorm-сессия проведена** через скилл `brainstorm` (`agent:orchestrate --chain=brainstorm`): тема — из раздела «Тема brainstorm», ≥3 участника с разными позициями (обязательны архитектор-стратег и скептик), фасилитатор — Тимлид Алекс
- [ ] Протокол сессии сохранён в `docs/research/dsh-dev-platform/brainstorm-protocol.md` (позиции, споры, решения, план действий — формат скилла)
- [ ] Референс dsh-trading разобран: какие элементы паттерна «платформа на dsh» переносимы на разработку (доска, роли, approval gate, журналы, метрики), какие — нет и почему
- [ ] Feasibility-отчёт `docs/research/dsh-dev-platform/feasibility.md`: плюсы/минусы каждой альтернативы (платформа на dsh / UI-слой над нашим движком / не строить), риски (developer preview dsh, второй рантайм, контракт control plane), оценка стоимости, вердикт
- [ ] Явная рекомендация владельцу: создавать продуктовый эпик (и с каким scope) или закрыть направление

### 🟡 Желательно (Should Have)
- [ ] Проверка актуального состояния dsh на дату штурма (релизы/версии после снапшота #22 — влияние на аргументы «за/против»)
- [ ] Минимальный набросок scope продуктового эпика при вердикте «стоит» (этапы, зависимости) — без постановки задач

### ⚫ Won't Have (Не будем делать)
- [ ] Код, прототипы, постановки задач реализации, изменения архитектуры task-orchestrator
- [ ] Полный повторный ресерч dsh по 10 критериям
- [ ] Решение «строить» единолично, без протокола штурма

## 4. План реализации (Implementation Plan)
1. [ ] Собрать вход: ресерч #22 (comparison + строка #22 сводной таблицы), актуальное состояние dsh и dsh-trading (GitHub API, README), наша файловая экосистема (todo/, chains.yaml, роли, audit)
2. [ ] Провести brainstorm-сессию командой (`agent:orchestrate --chain=brainstorm`, состав — из постановки; при сбое — resume сессии)
3. [ ] Сохранить протокол в `docs/research/dsh-dev-platform/brainstorm-protocol.md`
4. [ ] Написать feasibility-отчёт `docs/research/dsh-dev-platform/feasibility.md` (плюсы/минусы, риски, стоимость, вердикт, рекомендация владельцу)
5. [ ] Agent-report в `docs/agents/reports/`; `make md-links validate-todo validate-language`

## 5. Критерии приёмки (Definition of Done)
- [ ] Протокол brainstorm существует и содержит позиции ≥3 участников, спор и решения
- [ ] Feasibility-отчёт содержит плюсы/минусы ≥3 альтернатив, риски, вердикт «стоит / не стоит» с обоснованием
- [ ] Референс dsh-trading разобран с выводами о переносимости паттерна
- [ ] Рекомендация владельцу сформулирована явно (эпик + scope / закрыть)
- [ ] Проверки документации пройдены (PHPUnit/Psalm не требуются — изменения только в docs/todo)
- [ ] `php vendor/bin/todo-md validate TASK-research-dsh-dev-platform` — зелёный

## 6. Самопроверка (Verification)
```bash
ls docs/research/dsh-dev-platform/brainstorm-protocol.md
ls docs/research/dsh-dev-platform/feasibility.md
php vendor/bin/todo-md validate TASK-research-dsh-dev-platform
```

## 7. Риски и зависимости (Risks and Dependencies)
- **Стоимость сессии:** brainstorm — длинная цепочка (до 20 раундов, ≤6 агентов-участников). Запуск сессии — по готовности владельца (позиция владельца: «будут лишние токены — проведём»); задача может ждать в `todo` без ущерба.
- **dsh — developer preview** (ресерч #22: ломающие изменения задекларированы): аргументы штурма должны учитывать нестабильность базы; при смене статуса dsh перед сессией — обновить входные данные (Шаг 1 плана).
- **Зависимости:** нет блокирующих; вход — ресерчи #22 (done) и #471-постановки (anthropic-code-review, dsh-x-post) — опциональный контекст, не блокер.
- Прокси для доступа к внешним источникам: `CODEX_HTTP_PROXY` из `.env.local` (значение секрета в документах не указывается).

## 8. Источники (Sources)

- [GitHub: zhu1090093659/dsh-trading](https://github.com/zhu1090093659/dsh-trading) — референс платформы на базе dsh
- [GitHub: deepseek-ai/deepseek-harness](https://github.com/deepseek-ai/deepseek-harness) — базовая платформа dsh

**Внутренние источники:**

- `docs/research/coding-agents/deepseek-harness-comparison.md`, `docs/research/coding-agents-summary.md` (строка #22) — ресерч dsh
- `docs/agents/skills/brainstorm/SKILL.md` — скилл проведения мозгового штурма
- `AGENTS.md`, `docs/guide/architecture.md` — наша архитектура и экосистема
- `todo/TASK-research-dsh-x-post.todo.md` — смежная дельта-постановка по посту dsh (кросс-ссылка)

## 9. Комментарии (Comments)

**Контекст постановки (для исполнителя):** задача создана по решению владельца (2026-11-08) как **одиночное feasibility-исследование** без эпика — «пока задача просто на исследование того стоит / не стоит, плюсы — минусы». Создание продуктового эпика — возможный исход по вердикту штурма (тогда постановки реализации оформляются отдельным решением владельца). Ключевое требование владельца: решение принимается **мозговым штурмом команды** (скилл `brainstorm` с конструктивным конфликтом), а не единоличным мнением одного агента. При вердикте «стоит» в отчёте — краткий sketch scope эпика; при «не стоит» — зафиксированные причины и условия пересмотра (например, выход dsh из preview).

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-08 14:06:49 (1794146809) | Тимлид Алекс (pi) | Создание задачи |
