---
type: docs
created: 2026-11-09 04:04:04 (1794197044)
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
epic: EPIC-research-orchestration-articles
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

# TASK-research-anthropic-agent-memory: Статья Anthropic «The components of agent memory» (память агента, dreaming)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Наш проект фактически уже управляет памятью AI-агентов на трёх горизонтах (гипотеза владельца, 2026-11-09): долгосрочная — инструкции и внешние документы (`AGENTS.md`, конвенции, `todo/`-регламент, git-workflow), среднесрочная — задачи `todo/*.todo.md`, краткосрочная — сессия/чат с агентом. Но эта модель нигде не систематизирована и не сверена с внешним опытом.
- Anthropic опубликовал статью «The components of agent memory» (https://www.anthropic.com/research/components-of-agent-memory, Engineering, 2026-11-06) о компонентах памяти агента: memory notes, dreaming (офлайн-рефлексия над логами сессий), суждение о значимости (salience), wiki-представления, иерархия памяти с инжектируемым верхним слоем (`MEMORY.md` — первые 200 строк / 25 КБ как индекс).
- Наш механизм ретроспектив структурно совпадает с dreaming: офлайн-разбор журналов сессий, трассировка находок к первоисточникам, проверенные правила в реестре `docs/agents/team-retro/RETRO-ROADMAP.md`. Неизвестно: какие компоненты памяти Anthropic подтверждает или опровергает в нашей модели, и что из этого выражается в цепочках движка task-orchestrator.

### Варианты или путь решения (Solution Sketch)
- Добыть первоисточник через HTTPS-прокси: `CODEX_HTTP_PROXY` из `.env.local` (значение секрета в документах не указывается). Проверено Тимлидом 2026-11-09: статья отвечает HTTP 200. Локальная копия в `docs/research/orchestration-articles/sources/` — прецедент эпика (книга Ω, учебник, курс, статья о ревью).
- Исследовать статью по единой методологии эпика из 6 критериев (тезис, паттерн, domain, failure handling, маппинг, применяемость). Скилл knowledge-extraction временно недоступен (HTTP 401 от API, зафиксировано 2026-11-09) — при недоступности на момент выполнения читать локальную копию напрямую, метод зафиксировать в отчёте.
- Маппинг на две плоскости: (а) наш процесс управления памятью ролей — слои `AGENTS.md`/конвенций как инжектируемый верхний слой, задачи `todo/` как средний слой заметок, сессии как транскрипты, ретроспективы + `RETRO-ROADMAP.md` как dreaming-контур; (б) движок — что из memory-механик выражается цепочками (dreaming как шаг ретро-цикла, контроль качества заметок, префетч контекста, динамические циклы).
- Вердикт apply / study / skip по каждому компоненту статьи.

### Ожидаемый результат (Expected Result)
- Research-документ `docs/research/orchestration-articles/anthropic-agent-memory-research.md` по 6 критериям с маппингом на наш процесс и движок.
- Строка в сводной таблице `docs/research/orchestration-articles-summary.md` + рекомендации в backlog.
- Проверка гипотезы владельца о трёх горизонтах памяти проекта: подтверждена / скорректирована / опровергнута — с формулировками из статьи.
- Чёткий вердикт: что переносить в процесс (apply), что проектировать отдельными задачами (study), что неприменимо (skip).

## 1. Концепция и Цель (Concept and Goal)

### Story (Job Story)
> **Job Story:** Когда мы управляем знанием команды AI-агентов между сессиями (инструкции и конвенции — долгосрочная память, задачи `todo/` — среднесрочная, сессии — краткосрочная, ретроспективы — офлайн-консолидация), я хочу исследовать опыт Anthropic «The components of agent memory» — как устроены memory notes, dreaming, salience, wiki-представления и иерархия памяти с инжектируемым слоем, — чтобы сверить нашу модель с проверенной практикой Anthropic и понять, какие механизмы переносить в процесс и в дизайн цепочек task-orchestrator.

### Цель по SMART (Goal)
Исследовать статью Anthropic «The components of agent memory» (https://www.anthropic.com/research/components-of-agent-memory/) по методологии из 6 критериев. Создать отчёт в `docs/research/orchestration-articles/anthropic-agent-memory-research.md` с маппингом на процесс управления памятью проекта (AGENTS.md/конвенции/todo/ретро) и на модули движка, вердиктами apply/study/skip. Добавить строку в сводную таблицу `docs/research/orchestration-articles-summary.md`.

## 2. Контекст и Границы (Context and Scope)

- **Объект:** статья Anthropic «The components of agent memory» (Engineering, 2026-11-06, автор Lance Martin). Тема: компоненты памяти агента — memory notes, dreaming (офлайн-рефлексия над логами), salience (суждение о значимости), wiki-представления, иерархия памяти с инжектируемым верхним слоем, фокусировка памяти на цели.
- **Где делаем:** `docs/research/orchestration-articles/anthropic-agent-memory-research.md`, `docs/research/orchestration-articles-summary.md`, `docs/research/orchestration-articles/sources/` (локальная копия первоисточника).
- **Текущее поведение:** память проекта распределена по слоям — корневой `AGENTS.md` и конвенции (`docs/conventions/`) грузятся в контекст каждой роли; задачи `todo/*.todo.md` — среднесрочное состояние; ретроспективы (`docs/agents/skills/retrospective/`, реестр `docs/agents/team-retro/RETRO-ROADMAP.md`) — офлайн-разбор сессий с трассировкой к первоисточникам.
- **Границы (Out of Scope):** написание кода, изменение процесса/архитектуры без решения владельца, бенчмарки, внедрение memory-хранилищ в движок. Изменения — отдельными задачами.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [ ] **Критерии 1–6** — по единой методологии эпика (тезис/проблема, паттерн/концепция, domain, failure handling, маппинг на нашу архитектуру, применяемость)
- [ ] Проверка гипотезы владельца о трёх горизонтах памяти (долгосрочная = инструкции/внешние документы, среднесрочная = задачи, краткосрочная = сессия) — с дословными формулировками из статьи
- [ ] Сопоставление механизма ретроспектив проекта с dreaming: трассировка к первоисточникам, верификация правил, реестр `RETRO-ROADMAP.md` — что совпадает, чего не хватает
- [ ] Маппинг компонентов статьи на движок task-orchestrator: dreaming как шаг ретро-цикла, контроль качества заметок, префетч/инжекция контекста, динамические циклы — со ссылками на модули (AgentRunner, ChainExecution, ChainDefinition, DynamicLoop)
- [ ] Вердикт apply / study / skip по каждому компоненту — с обоснованием и оценкой усилий
- [ ] Первоисточник добыт и зафиксирован локальной копией в `docs/research/orchestration-articles/sources/`

### 🟡 Желательно (Should Have)
- [ ] Разбор бенчмарков статьи (CL-Bench, Pokémon, Stainless-wiki, Harvey LAB) — какие выводы масштабируются на нашу предметную область, какие нет
- [ ] Рекомендации для скилла ретроспектив и для будущих задач движка в backlog сводной таблицы

### ⚫ Won't Have (Не будем делать)
- [ ] Код/конфиги, memory-хранилища в движке, изменение процесса или архитектуры без отдельного решения владельца

## 4. План реализации (Implementation Plan)
1. [ ] Скачать первоисточник через HTTPS-прокси (`CODEX_HTTP_PROXY` из `.env.local`; проверено 2026-11-09: HTTP 200), сохранить локальную копию в `docs/research/orchestration-articles/sources/`
2. [ ] Изучить статью: если скилл knowledge-extraction восстановлен (2026-11-09 API отдавал HTTP 401) — RAG-допрос по протоколу полного покрытия; иначе — прямое чтение локальной копии с цитатной выборкой (метод зафиксировать в отчёте)
3. [ ] Актуализировать знание нашего процесса: корневой `AGENTS.md`, `docs/conventions/index.md`, регламент `todo/AGENTS.md`, `docs/agents/skills/retrospective/SKILL.md`, `docs/agents/team-retro/RETRO-ROADMAP.md`
4. [ ] Оценить каждый из 6 критериев; проверить гипотезу трёх горизонтов памяти; маппинг на процесс и на движок (AgentRunner, ChainExecution, ChainDefinition, DynamicLoop)
5. [ ] Создать отчёт `docs/research/orchestration-articles/anthropic-agent-memory-research.md`
6. [ ] Обновить сводную таблицу `docs/research/orchestration-articles-summary.md` (строка, backlog-рекомендации, тренды)
7. [ ] Создать аналитический отчёт в `docs/agents/reports/system-analyst/` по формату проекта

## 5. Критерии приёмки (Definition of Done)
- [ ] Первоисточник скачан через прокси; локальная копия в `sources/` зафиксирована
- [ ] Отчёт создан, все 6 критериев оценены
- [ ] Гипотеза трёх горизонтов памяти проверена по формулировкам статьи
- [ ] Сопоставление ретроспектив с dreaming выполнено; маппинг на модули движка — со ссылками на конкретные файлы
- [ ] Вердикт apply/study/skip по каждому компоненту с обоснованием
- [ ] Строка добавлена в сводную таблицу `docs/research/orchestration-articles-summary.md`
- [ ] Аналитический отчёт в `docs/agents/reports/system-analyst/` создан
- [ ] `make md-links validate-todo validate-language` пройдены (PHPUnit/Psalm не требуются — изменения только в docs/todo)

## 6. Самопроверка (Verification)
```bash
ls docs/research/orchestration-articles/anthropic-agent-memory-research.md
ls docs/research/orchestration-articles/sources/
php vendor/bin/todo-md validate TASK-research-anthropic-agent-memory
```

## 7. Риски и зависимости (Risks and Dependencies)
- Доступ к первоисточнику — только через HTTPS-прокси (`CODEX_HTTP_PROXY` из `.env.local`; проверено 2026-11-09: HTTP 200). Прямой доступ из окружения заблокирован по региону. Значение прокси — секрет: в файлах, отчётах и логах не указывается. Содержание не выдумывать; при недоступности — `blocked` с причиной.
- Скилл knowledge-extraction временно недоступен (HTTP 401 от TasK API, зафиксировано 2026-11-09): если не восстановится к моменту выполнения, исследование — прямым чтением локальной копии; RAG-верификация цитат в этом случае заменяется повторным чтением первоисточника (метод и ограничение фиксируются в отчёте, по прецеденту отчёта aipdlc §0/§13).
- Зависимостей от других задач нет; эпик ведётся в evergreen-режиме. Задача TASK-research-anthropic-code-review (тот же эпик) независима — можно выполнять параллельно.

## 8. Источники (Sources)

- [Anthropic, «The components of agent memory»](https://www.anthropic.com/research/components-of-agent-memory) — первоисточник (Engineering, 2026-11-06)

**Внутренние источники:**

- Корневой `AGENTS.md`, `docs/conventions/index.md`, `todo/AGENTS.md` — слои долгосрочной/среднесрочной памяти проекта
- `docs/agents/skills/retrospective/SKILL.md`, `docs/agents/team-retro/RETRO-ROADMAP.md` — наш dreaming-контур
- `docs/research/orchestration-articles-summary.md` — сводная таблица трека
- Прецедент постановки: `todo/TASK-research-anthropic-code-review.todo.md`

## 9. Комментарии (Comments)

**Контекст постановки (для исполнителя):** задача добавлена по предложению владельца (2026-11-09) в evergreen-трек статей по оркестрации. Статья — первый в треке источник про память агента как отдельную дисциплину; гипотеза владельца: наша система (инструкции + `todo/` + ретро) уже реализует трёхслойную модель памяти из статьи — задача должна либо подтвердить это формулировками первоисточника, либо показать расхождения. Ожидаемое пересечение с находками отчёта pi-durable (summary-компакция) и белой книги PDLC (#52 Session Handoff, #57 градуированная компакция) — помечать кросс-ссылками на строки сводной таблицы.

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-09 04:04:04 (1794197044) | Тимлид Алекс (pi) | Создание задачи |
