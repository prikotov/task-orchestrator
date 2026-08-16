# Why Software Factories Fail — сравнение подхода с процессом task-orchestrator

**Роль:** Аналитик (Шерлок)  
**Дата:** 2026-07-27  
**Объект:** Dex Horthy / HumanLayer — `Why Software Factories Fail` и тезис `Harness Engineering is not Enough`  
**Задача:** [TASK-research-why-software-factories-fail](../../../todo/done/TASK-research-why-software-factories-fail.todo.md)
**Эпик:** [EPIC-research-approaches-comparison](../../../todo/EPIC-research-approaches-comparison.md)

---

## Сводка

Dex Horthy описывает провал `dark factory` (тёмная фабрика — автономная разработка без чтения кода человеком): когда `agent` (AI-исполнитель) генерирует большую долю кода, а `review` (проверка) заменяется тестами, мониторингом и `canary` (постепенный выпуск), система быстро получает рост `slop` (низкокачественного AI-кода), потери владения кодом и ухудшение поддерживаемости.

Главный тезис: `harness engineering is not enough` (одного оркестрационного слоя недостаточно). `Harness` (обвязка: инструменты, sandbox, agents, retry, gates) может ускорить генерацию и проверку локальных условий, но не даёт модели устойчивого сигнала о `maintainability` (поддерживаемости): последствия плохой архитектуры проявляются через месяцы и не возвращаются в `RL reward` (награду обучения с подкреплением) как простой бинарный сигнал «тест прошёл / не прошёл».

Рецепт Horthy — вернуть свет в фабрику: `front-loaded alignment` (предварительное выравнивание) перед кодом и обязательное человеческое владение результатом:

1. `product review` (продуктовая проверка): проблема и ожидаемое поведение;
2. `system architecture` (системная архитектура): контракты, модели данных, ограничения;
3. `program design` (программный дизайн): типы, сигнатуры методов, стеки/графы вызовов;
4. `vertical slicing` (вертикальная нарезка): порядок реализации, межрепозиторная координация, точки проверки;
5. построчное человеческое `code review` (ревью кода) и владение изменением.

**Итоговый вердикт для task-orchestrator:** `adapt` (адаптировать). Процесс проекта уже является `lit factory` (освещённая фабрика): роли, `todo`-артефакты, `self-review` (самопроверка), внешний `review`, запрет `merge` (слияния) без явного человеческого подтверждения, `quality gates` (контроль качества). Главный выявленный `gap` (пробел) — у нас нет формализованного шага `program design` как обязательного planning-артефакта для задач с изменением кода.

### Легенда оценок

| Символ | Значение | Балл |
|--------|----------|------|
| ✅ | Сильное совпадение с нашим процессом / высокая применимость | 3 |
| ⚠️ | Частичное совпадение, нужен `adapt` (адаптация) | 2 |
| ❌ | Низкая применимость или прямое несоответствие | 1 |

### Итог по 8 критериям

| Критерий | Оценка | Ключевой вывод |
|----------|--------|----------------|
| 1. Тезис / проблема | ✅ 3 | Проблема напрямую совпадает с рисками AI-разработки: скорость без владения кодом разрушает поддерживаемость. |
| 2. Модель процесса | ✅ 3 | `front-loaded alignment` хорошо ложится на наш role-based workflow (процесс по ролям). |
| 3. Уровень автономии | ✅ 3 | Подход требует `lit factory`; наш процесс уже запрещает «безлюдный» `merge`. |
| 4. Качество / поддерживаемость | ✅ 3 | Подтверждает ценность `review gates`, `conventions` (конвенций) и human ownership (владения человеком). |
| 5. Context engineering | ✅ 3 | Совпадает с нашими `roles`, `skills`, `AGENTS.md`, отчётами и research/plan artifacts. |
| 6. Артефакты процесса | ⚠️ 2 | Сильные `todo`-артефакты есть, но `program design` не выделен явно. |
| 7. Маппинг на наш процесс | ✅ 3 | Большинство тезисов уже покрыто ролями, скиллами и правилами проекта. |
| 8. Применяемость | ✅ 3 | Принимаем позиционирование `lit factory`; адаптируем только программный дизайн. |
| **Итого** | **23 / 24** | Подход валидирует текущую стратегию и даёт один важный процессный `gap`. |

---

## Критерий 1. Тезис / проблема

### Что утверждает подход

| Тезис | Смысл | Тип тезиса |
|-------|------|------------|
| `Dark factory` проваливается | Если никто не читает код, команда теряет понимание продукта и кодовой базы. | Процесс |
| `Harness engineering is not enough` | Обвязка и автоматические циклы не исправляют отсутствие сигнала о поддерживаемости. | Процесс + модель |
| `Maintainability reward` отсутствует | `RL` (обучение с подкреплением) вознаграждает прохождение тестов, а не долгосрочную архитектурную чистоту. | Модель / информационный |
| Рост скорости создаёт новый `bottleneck` (узкое место) | Реализация ускоряется, а review/ownership не масштабируются автоматически. | Процесс |

### Маппинг на task-orchestrator

| Тезис | Наши артефакты | Покрытие |
|-------|----------------|----------|
| Нельзя убирать человеческий `review` | [AGENTS.md / Pull Requests](../../../AGENTS.md#pull-request-запросы-на-слияние), [task-via-subagents / Self-review и Code Review](../../agents/skills/task-via-subagents/SKILL.md#шаг-4-самопроверка), [epic-via-subagents / ревью обязательно](../../agents/skills/epic-via-subagents/SKILL.md#шаг-6-ревью) | ✅ Уже покрыто |
| Поддерживаемость важнее локально зелёных тестов | [Конвенции первичны](../../../AGENTS.md#терминология), [Layer Interaction](../../conventions/layers/layers.md), [Architecture](../../guide/architecture.md) | ✅ Уже покрыто |
| Риск `bad PR` дороже риска `many PRs` | [Один PR содержит одну задачу](../../../AGENTS.md#мини-чеклист-для-самопроверки), [todo/AGENTS.md / декомпозиция](../../../todo/AGENTS.md#декомпозиция-задач) | ✅ Уже покрыто |
| Модель/RL не гарантирует качество | В проекте нет зависимости от конкретного обучения модели; качество обеспечивается процессом, ролями и проверками. | ⚠️ Информационный тезис |

**Оценка:** ✅ 3/3. Проблема релевантна и подтверждает базовую архитектуру процесса task-orchestrator.

---

## Критерий 2. Модель процесса (SDLC/PDLC)

### Модель Dex Horthy

```mermaid
flowchart LR
    A[Product Review] --> B[System Architecture]
    B --> C[Program Design]
    C --> D[Vertical Slicing]
    D --> E[Agent Implementation]
    E --> F[Human Line-by-Line Review]
    F --> G[Approval / Merge]
```

### Наш процесс

```mermaid
flowchart LR
    A[Todo Task] --> B[System Analyst]
    B --> C[System Architect]
    C --> D[Backend Developer]
    D --> E[Self-review]
    E --> F[External Review]
    F --> G[Human Approval]
    G --> H[Merge]
```

| Шаг Horthy | Ближайший артефакт task-orchestrator | Gap |
|------------|--------------------------------------|-----|
| `product review` | [task template / Concept and Goal + Requirements](../../todo-md/templates/task.md), [Аналитик Шерлок](../../agents/roles/team/system_analyst_sherlock.ru.md) | Нет критичного gap |
| `system architecture` | [Архитектор Гэндальф](../../agents/roles/team/system_architect_gandalf.ru.md), [Архитектор Локи](../../agents/roles/team/system_architect_loki.ru.md), [Architecture](../../guide/architecture.md), [Conventions](../../conventions/index.md) | Нет критичного gap |
| `program design` | Частично: типизация и контракты в [DTO](../../conventions/core_patterns/dto.md), [Use Case](../../conventions/layers/application/use_case.md), [Layer Interaction](../../conventions/layers/layers.md) | ⚠️ Нет обязательного planning-подэтапа |
| `vertical slicing` | [todo/AGENTS.md / 1 задача = 1 логическая подзадача](../../../todo/AGENTS.md#декомпозиция-задач), [epic-via-subagents](../../agents/skills/epic-via-subagents/SKILL.md) | Частично: можно усилить чекпоинты между фазами |
| Human review | [code_reviewer_backend_puaro](../../agents/roles/team/code_reviewer_backend_puaro.ru.md), [task-via-subagents](../../agents/skills/task-via-subagents/SKILL.md), [AGENTS.md / merge only by confirmation](../../../AGENTS.md#pull-request-запросы-на-слияние) | Нет gap |

**Оценка:** ✅ 3/3. Процессная модель совместима. Единственная существенная доработка — формализация `program design`.

---

## Критерий 3. Уровень автономии (dark vs lit factory)

| Уровень | Подход Horthy | task-orchestrator |
|---------|---------------|-------------------|
| `Classic factory` | Человек реализует и ревьюит; медленно, но владение сохраняется. | Не целевой режим. |
| `Agentic factory` | Agent реализует, человек ревьюит; review становится узким местом. | Наш базовый режим. |
| `Dark factory` | Agent реализует, тесты/мониторинг заменяют review. | ❌ Прямо отвергается правилами проекта. |
| `Lit factory` | Agent ускоряет, человек заранее выравнивает и построчно владеет кодом. | ✅ Целевой режим позиционирования. |

**Ключевые правила проекта:**

- `self-review` и внешний `review` обязательны в [task-via-subagents](../../agents/skills/task-via-subagents/SKILL.md#шаг-4-самопроверка) и [epic-via-subagents](../../agents/skills/epic-via-subagents/SKILL.md#шаг-5-self-review);
- `merge` (слияние) запрещён без явного подтверждения пользователя: [AGENTS.md / Pull Requests](../../../AGENTS.md#pull-request-запросы-на-слияние);
- прямые изменения `main` (основной ветки) запрещены: [AGENTS.md / Работа с кодом](../../../AGENTS.md#работа-с-кодом).

**Оценка:** ✅ 3/3. Наш процесс уже `lit factory`, не `dark factory`.

---

## Критерий 4. Механизм качества / поддерживаемости

### Качество у Horthy

| Механизм | Роль |
|----------|------|
| Tests (тесты) | Нужны, но проверяют локальную корректность. |
| Monitoring / canary | Нужны, но ловят проблему после попадания в систему. |
| Review | Возвращает владение и выравнивание команды. |
| Up-front design | Уменьшает объём плохого кода до review. |
| Human ownership | Единственный текущий механизм, способный оценить долгосрочную поддерживаемость. |

### Качество у нас

| Наш механизм | Файл | Соответствие тезису |
|--------------|------|--------------------|
| Конвенции как source of truth (источник истины) | [docs/conventions/index.md](../../conventions/index.md), [AGENTS.md / Конвенции первичны](../../../AGENTS.md#терминология) | ✅ Не даём модели копировать плохой соседний код. |
| Слоистая архитектура | [Layer Interaction](../../conventions/layers/layers.md), [Architecture](../../guide/architecture.md) | ✅ Поддерживаемость задаётся архитектурными границами. |
| Reviewer role | [Ревьювер Пуаро](../../agents/roles/team/code_reviewer_backend_puaro.ru.md) | ✅ Проверяет DDD, безопасность, типизацию, тесты. |
| QA role | [Тестировщик Хаус](../../agents/roles/team/qa_backend_house.ru.md) | ✅ Усиливает edge cases (пограничные сценарии). |
| Mandatory checks | [AGENTS.md / Tests and Validation](../../../AGENTS.md#тесты-и-проверки) | ✅ Tests/Psalm дополняют, но не заменяют review. |
| Product harness | Модули `AgentRunner`, `ChainExecution`, `DynamicLoop` в [Architecture](../../guide/architecture.md) | ⚠️ Harness полезен как качество исполнения, но не как замена ownership. |

**Оценка:** ✅ 3/3. Подход подтверждает, что `quality gates` должны быть не только автоматическими, но и человеческими.

---

## Критерий 5. Context engineering

### Тезисы HumanLayer `Advanced Context Engineering`

| Тезис | Смысл | Применимость |
|-------|------|--------------|
| LLM — stateless function (без состояния) | Качество выхода определяется качеством входного контекста. | ✅ Процессный тезис |
| Контекст должен быть correct / complete / small / trajectory-aware | Вредны неверные данные, пропуски и шум. | ✅ Процессный тезис |
| `Subagents` — не театр ролей, а контроль контекста | Свежие окна контекста используются для поиска, анализа и сжатия. | ✅ Процессный тезис |
| `Research / Plan / Implement` | Человек ревьюит research/plan, потому что ошибка там масштабируется в сотни/тысячи строк кода. | ✅ Процессный тезис |
| High-leverage review | 200 строк хорошего плана дешевле проверить, чем 2000 строк кода. | ✅ Процессный тезис |

### Маппинг

| HumanLayer | task-orchestrator |
|------------|-------------------|
| `AGENTS.md` / `CLAUDE.md` как high-leverage config | [AGENTS.md](../../../AGENTS.md) — обязательный проектный контекст |
| Role-specific context | [Роли команды](../../agents/roles/team/) и `become-role` |
| Subagents for context isolation | [task-via-subagents](../../agents/skills/task-via-subagents/SKILL.md), [epic-via-subagents](../../agents/skills/epic-via-subagents/SKILL.md) |
| Research artifacts | `docs/research/*`, `docs/agents/reports/*` |
| Plan artifacts | `todo/*.todo.md`, [task template](../../todo-md/templates/task.md), [epic template](../../todo-md/templates/epic.md) |
| Compaction back into artifacts | [agent-report](../../agents/skills/agent-report/SKILL.md) и отчёты ролей |

**Оценка:** ✅ 3/3. Мы уже строим процесс вокруг управляемого контекста; стоит только усилить `program design` как отдельный компактный артефакт.

---

## Критерий 6. Артефакты процесса

| Артефакт Horthy/HumanLayer | Есть у нас? | Файл / место | Оценка |
|----------------------------|-------------|--------------|--------|
| Problem statement (описание проблемы) | ✅ | [task template / Concept and Goal](../../todo-md/templates/task.md) | Сильно |
| Requirements (требования) | ✅ | [task template / Requirements](../../todo-md/templates/task.md) | Сильно |
| System architecture | ✅ | [epic template / Solution Design](../../todo-md/templates/epic.md), [Architecture](../../guide/architecture.md), [Conventions](../../conventions/index.md) | Сильно |
| Program design | ⚠️ | Частично в конвенциях типов, но не в шаблоне задачи | Gap |
| Vertical slicing | ✅/⚠️ | [todo/AGENTS.md / decomposition](../../../todo/AGENTS.md#декомпозиция-задач), [epic-via-subagents](../../agents/skills/epic-via-subagents/SKILL.md) | Хорошо, можно уточнить checkpoints |
| Review record | ✅ | `docs/agents/reports/*`, PR review, `self-review` | Сильно |
| Source of truth | ✅ | `AGENTS.md`, `docs/conventions/*`, `todo/*.todo.md` | Сильно |

### Проверка гипотезы тимлида про `program design`

**Вердикт:** гипотеза подтверждена.

Факты:

1. [task template](../../todo-md/templates/task.md) содержит `Implementation Plan` (план реализации), но не содержит `Solution Design` и не требует указывать типы, сигнатуры методов или графы вызовов.
2. [epic template](../../todo-md/templates/epic.md) содержит `Solution Design`, но описание ограничено «архитектурным подходом, затронутыми модулями, ссылками на схемы или RFC»; `program design` как подэтап не выделен.
3. [Conventions](../../conventions/index.md) хорошо формализуют типизацию и контракты на уровне кода: [DTO](../../conventions/core_patterns/dto.md), [Use Case](../../conventions/layers/application/use_case.md), [Layer Interaction](../../conventions/layers/layers.md). Но это правила реализации и review, а не обязательный planning-артефакт до кодинга.
4. [system_analyst_sherlock](../../agents/roles/team/system_analyst_sherlock.ru.md) описывает системные контракты, API, edge cases; [system_architect_gandalf](../../agents/roles/team/system_architect_gandalf.ru.md) — DDD-границы и архитектуру. Ни одна роль не требует явно приложить таблицу типов/сигнатур/граф вызовов к задаче перед implementation (реализацией).

**Рекомендация:** отдельной задачей добавить в task template (шаблон задачи) секцию `Program Design` для C3+ code tasks (задачи с изменением кода):

- affected classes/interfaces (затронутые классы/интерфейсы);
- public method signatures (публичные сигнатуры методов);
- DTO/VO/Enum contracts (контракты данных);
- call graph (граф вызовов) в Mermaid для нетривиальных сценариев;
- phase checkpoints (проверки между вертикальными срезами).

**Оценка:** ⚠️ 2/3 из-за отсутствия явного `program design`.

---

## Критерий 7. Маппинг на наш процесс

| Ключевой тезис | Наш артефакт | Вердикт | Effort (усилия) |
|----------------|--------------|---------|-----------------|
| `Dark factory` не работает | [AGENTS.md / Pull Requests](../../../AGENTS.md#pull-request-запросы-на-слияние), [task-via-subagents](../../agents/skills/task-via-subagents/SKILL.md), [epic-via-subagents](../../agents/skills/epic-via-subagents/SKILL.md) | **adopt** — уже принято | Low |
| `Harness engineering is not enough` | [Architecture / AgentRunner + ChainExecution + DynamicLoop](../../guide/architecture.md), [AGENTS.md / review gates](../../../AGENTS.md#тесты-и-проверки) | **adapt** — позиционировать как `lit-factory harness`, не как автопилот | Medium |
| RL-награда не учит поддерживаемости | [Conventions](../../conventions/index.md), [Пуаро](../../agents/roles/team/code_reviewer_backend_puaro.ru.md) | **adopt as rationale** — использовать как обоснование review/conventions | Low |
| Model + harness co-training даёт vendor advantage | Нет прямого артефакта: мы не обучаем модели | **reject direct** — не строить roadmap на конкуренции с model vendors (поставщиками моделей) в training | None |
| Faros: throughput up, quality down | [Tests and Validation](../../../AGENTS.md#тесты-и-проверки), [QA Хаус](../../agents/roles/team/qa_backend_house.ru.md) | **adopt** — использовать как risk framing (рамка риска) | Low |
| `Product review` | [task template](../../todo-md/templates/task.md), [Шерлок](../../agents/roles/team/system_analyst_sherlock.ru.md) | **adopt** — уже есть | Low |
| `System architecture` | [Гэндальф](../../agents/roles/team/system_architect_gandalf.ru.md), [Локи](../../agents/roles/team/system_architect_loki.ru.md), [Architecture](../../guide/architecture.md) | **adopt** — уже есть | Low |
| `Program design` | Частично: [DTO](../../conventions/core_patterns/dto.md), [Use Case](../../conventions/layers/application/use_case.md), [Layer Interaction](../../conventions/layers/layers.md) | **adapt** — добавить planning-секцию | Medium |
| `Vertical slicing` | [todo/AGENTS.md / decomposition](../../../todo/AGENTS.md#декомпозиция-задач), [epic-via-subagents](../../agents/skills/epic-via-subagents/SKILL.md) | **adapt** — усилить phase checkpoints для больших задач | Low/Medium |
| Построчное human review | [Пуаро](../../agents/roles/team/code_reviewer_backend_puaro.ru.md), [AGENTS.md / merge confirmation](../../../AGENTS.md#pull-request-запросы-на-слияние) | **adopt** — уже принято | Low |
| Research/plan review важнее code-only review | [agent-report](../../agents/skills/agent-report/SKILL.md), [todo/AGENTS.md / Reverse Briefing](../../../todo/AGENTS.md#процесс-работы-с-задачами) | **adapt** — ревьюить research/plan явно для C3+ | Medium |
| Малые задачи не всегда требуют полного 4-step flow | [todo/AGENTS.md / декомпозиция](../../../todo/AGENTS.md#декомпозиция-задач) | **adapt** — сохранить lightweight mode (облегчённый режим), не отменяя gates | Medium |

**Оценка:** ✅ 3/3. Маппинг плотный; новая работа — только процессная формализация.

---

## Критерий 8. Применяемость и итоговый вердикт

### Что мы уже делаем

- `Lit factory`: agents пишут, люди/роли контролируют, человек подтверждает `merge`.
- `Front-loaded alignment`: `todo`-задачи, требования, роль Аналитика, роль Архитектора, конвенции.
- `Quality gates`: `self-review`, внешний `review`, PHPUnit/Psalm, Deptrac, конвенции.
- `Context engineering`: роли, `skills`, `AGENTS.md`, отчёты, research-артефакты.
- `Vertical slicing`: атомарные задачи, один PR — одна задача, эпик декомпозируется на tasks.

### Что стоит усилить

| Gap | Рекомендация | Effort |
|-----|--------------|--------|
| Нет явного `program design` в task template | Добавить секцию для C3+ code tasks: типы, сигнатуры, call graph, checkpoints | Medium |
| План реализации не всегда проверяется как high-leverage artifact | Для сложных задач вводить explicit plan review (явное ревью плана) до implementation | Medium |
| Позиционирование продукта может звучать как «ещё один harness» | В docs/marketing формулировать как `lit-factory orchestration`: harness + roles + human gates | Low/Medium |
| Vertical slicing не всегда выражает checkpoints | В задачах C3+ просить фазы с verification (проверкой) после каждого среза | Low |

### Что отвергнуть

| Идея | Причина |
|------|---------|
| `Dark factory` / no-review merge | Противоречит AGENTS.md и главному выводу исследования. |
| Ставка на «больше review agents вместо человека» | Может поднять нижнюю планку, но не заменяет ownership (владение) и долгосрочную оценку поддерживаемости. |
| Конкуренция с model vendors через RL внутри нашего harness | Неприменимо напрямую: мы оркестрируем процесс, а не обучаем foundation models (базовые модели). |

### Влияние на позиционирование task-orchestrator

Тезис `harness engineering is not enough` не обесценивает task-orchestrator; он уточняет позиционирование.

Неверное позиционирование:

> task-orchestrator — harness, который позволяет полностью заменить инженеров AI-агентами.

Корректное позиционирование:

> task-orchestrator — `lit-factory orchestration` (оркестрация освещённой AI-фабрики): harness для запуска агентов, quality gates, roles, artifacts и human approval, чтобы ускорять SDLC/PDLC без потери владения кодом и поддерживаемости.

**Итоговый вердикт:** **adapt**. Подход Horthy валидирует наш процесс и требует одной целевой доработки методологии: формального `program design` до implementation для нетривиальных code tasks.

---

## Разделение тезисов: модели/RL vs процесс

| Тезис | Категория | Как использовать у нас |
|-------|-----------|------------------------|
| RL-награда бинарно проверяет тесты | Модель/RL | Информационно: объясняет, почему нельзя полагаться только на модель и тесты. |
| Плохая архитектура проявляется через месяцы | Модель/RL + процесс | Использовать как аргумент за `review`, conventions и program design. |
| Model+harness co-training даёт преимущество Claude Code | Модель/RL | Не брать как прямую задачу продукта; учитывать при выборе runners (исполнителей). |
| Review agents имеют потолок | Модель/RL + процесс | Не заменять human review автоматическим review-loop. |
| `Product review → architecture → program design → vertical slicing` | Процесс | Адаптировать в task template и роли. |
| Human line-by-line ownership | Процесс | Сохранить как non-negotiable gate (необсуждаемая контрольная точка). |
| Research/plan/implement и compaction | Процесс | Продолжать развивать отчёты, задачи и скиллы как compacted artifacts. |

---

## Источники

1. Tony Bai — [Harness Engineering is not Enough: Why Software Factories Fail](https://tonybai.com/2026/07/27/why-software-factories-fail-harness-engineering-not-enough/) — основной доступный текстовый разбор доклада.
2. Dex Horthy / HumanLayer — [Advanced Context Engineering for Coding Agents](https://www.humanlayer.dev/blog/advanced-context-engineering) — канонический блог автора про `research / plan / implement` и leverage (рычаг влияния).
3. YouTube — [Harness Engineering is not Enough: Why Software Factories Fail — Dex Horthy](https://www.youtube.com/watch?v=Ib5GBkD555M) — видео доклада; использованы title/description и доступные метаданные.
4. Faros AI — [AI Acceleration Whiplash](https://www.faros.ai/research/ai-acceleration-whiplash) — отраслевые данные: +51% PR size, +28% bugs per PR, 5X review time, 3X incidents per PR, 10X code churn.
5. Локальные источники task-orchestrator: [AGENTS.md](../../../AGENTS.md), [todo/AGENTS.md](../../../todo/AGENTS.md), [Conventions](../../conventions/index.md), [team roles](../../agents/roles/team/), [skills](../../agents/skills/).
