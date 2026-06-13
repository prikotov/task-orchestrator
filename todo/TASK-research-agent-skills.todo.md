---
type: docs
created: 2026-06-13
value: V3
complexity: C2
priority: P2
depends_on:
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch: task/research-agent-skills
pr: https://github.com/prikotov/task-orchestrator/pull/258
status: review
---

# TASK-research-agent-skills: Исследовать addyosmani/agent-skills для сравнения с task-orchestrator

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В проекте развивается собственная модель `skills`, ролей и workflow orchestration (оркестрации рабочих процессов).
- Нужно понять, какие практики из `addyosmani/agent-skills` полезны для наших агентных инструкций и какие не подходят.

### Варианты или путь решения (Solution Sketch)
- Изучить первичные источники репозитория `addyosmani/agent-skills`.
- Сравнить skill pack (пакет скиллов) с нашими `docs/agents/skills/*`, ролями и orchestration skills.
- Зафиксировать выводы в comparison report и сводной таблице исследований.

### Ожидаемый результат (Expected Result)
- Есть отдельный отчёт по Agent Skills, строка в сводной таблице и понятный verdict (вердикт): не dependency, но источник паттернов.
- Оркестратор может отправить задачу на review без ручного восстановления контекста.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы развиваем собственную модель `skills` (скиллов), ролей и workflow orchestration (оркестрации рабочих процессов), я хочу изучить `addyosmani/agent-skills`, чтобы понять, какие паттерны lifecycle skills (скиллов жизненного цикла), slash commands (команд), personas (персон) и quality gates (контрольных проверок качества) можно безопасно заимствовать в `task-orchestrator`.

### Goal (Цель по SMART)
Провести техническое исследование `addyosmani/agent-skills`: архитектура skill pack (пакета скиллов), формат `SKILL.md`, slash commands, agent personas, hooks, support для Claude Code / Gemini CLI / OpenCode / Cursor / Copilot / Antigravity, orchestration patterns (паттерны оркестрации), verification gates (проверки) и применимость к нашему PHP/Symfony `task-orchestrator`. Оформить отчёт и обновить сводную таблицу research-эпика.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/research/framework-comparisons/`, `docs/research/agent-frameworks-summary.md`, `todo/done/EPIC-research-agent-frameworks-comparison.md`.
*   **Текущее поведение:** В research-эпике уже исследованы 27 AI-agent frameworks/orchestrators (фреймворков/оркестраторов), включая проекты со `SKILL.md`, personas (персонами), sub-agents (сабагентами) и workflow engines (движками workflow).
*   **Границы (Out of Scope):** Не интегрируем пакет как dependency (зависимость), не копируем скиллы напрямую в проект, не переписываем наши роли/скиллы в рамках этой задачи.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] Изучить репозиторий `https://github.com/addyosmani/agent-skills`: структура, README, лицензия, поддерживаемые агенты/IDE.
- [x] Изучить anatomy (анатомию) `SKILL.md`: frontmatter, workflow sections, anti-rationalizations (анти-рационализации), red flags (красные флаги), verification (проверки), progressive disclosure (прогрессивное раскрытие контекста).
- [x] Изучить orchestration model (модель оркестрации): lifecycle commands `/spec` → `/plan` → `/build` → `/test` → `/review` → `/ship`, personas, fan-out review, запрет persona-calls-persona.
- [x] Сравнить с нашей моделью `docs/agents/skills/*`, `docs/agents/roles/team/*`, `task-via-subagents`, `epic-via-subagents`, `brainstorm`.
- [x] Оформить отчёт `docs/research/framework-comparisons/agent-skills-comparison.md` по формату существующих comparison-документов.
- [x] Добавить строку `Agent Skills` в `docs/research/agent-frameworks-summary.md` и обновить счётчик заполнения.
- [x] Дать чёткий verdict (вердикт): dependency / заимствовать паттерны / не подходит.
### 🟡 Should Have (Желательно)
- [x] Выделить конкретные паттерны для улучшения наших скиллов: anti-rationalization tables, skill anatomy validator, lifecycle command mapping, reference checklists.
- [x] Оценить совместимость MIT license (лицензии MIT) и риски прямого копирования текстов.
- [x] Сравнить plugin manifests (манифесты плагинов) Claude/Antigravity с нашими plugin/skill conventions (конвенциями).
### 🟢 Could Have (Опционально)
- [x] Предложить backlog tasks (задачи бэклога) на улучшение нашего skill authoring guide (гайда написания скиллов).
- [x] Составить маленькую Mermaid-диаграмму сопоставления lifecycle команд с нашими ролями.
### ⚫ Won't Have (Не будем делать)
- [x] Интеграция `agent-skills` как runtime dependency.
- [x] Массовое копирование чужих `SKILL.md` в проект.
- [x] Изменение существующих production workflows (рабочих процессов) без отдельной задачи.

## 4. Implementation Plan (План реализации)
1. [x] Изучить metadata (метаданные) GitHub repo: description, license, stars/forks, default branch, topics.
2. [x] Изучить `README.md`, `docs/skill-anatomy.md`, `AGENTS.md`, `agents/README.md`, `references/orchestration-patterns.md`.
3. [x] Просмотреть 3–5 representative skills (репрезентативных скиллов): `using-agent-skills`, `spec-driven-development`, `planning-and-task-breakdown`, `code-review-and-quality`, `git-workflow-and-versioning`.
4. [x] Сравнить findings (находки) с нашими `docs/agents/skills/` и `docs/agents/roles/team/`.
5. [x] Написать `docs/research/framework-comparisons/agent-skills-comparison.md`.
6. [x] Обновить `docs/research/agent-frameworks-summary.md`: строка `Agent Skills`, счётчик `28 / 28`, рекомендации/паттерны при необходимости.
7. [x] Провести self-review (саморевью), external review (внешнее ревью), создать PR и оставить задачу в `review` до merge finalization (финализации перед слиянием).

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт `docs/research/framework-comparisons/agent-skills-comparison.md` создан и содержит сравнение с `task-orchestrator`.
- [x] В отчёте есть стандартная comparison table (таблица сравнения): orchestration model, state management, error handling, extensibility, applicability. Колонки `state management` и `error handling` должны явно комментировать applicability для skill pack (пакета скиллов): `N/A`, `host-dependent` или delegated to host agent (делегировано агенту-хосту).
- [x] В `docs/research/agent-frameworks-summary.md` добавлена строка `Agent Skills` и обновлён счётчик.
- [x] В отчёте перечислены 3–7 concrete patterns (конкретных паттернов) для возможного заимствования.
- [x] Указаны sources (источники) и дата анализа.

## 6. Verification (Самопроверка)
```bash
ls docs/research/framework-comparisons/agent-skills-comparison.md
grep -c "Agent Skills" docs/research/agent-frameworks-summary.md
grep -n "28 / 28" docs/research/agent-frameworks-summary.md
make validate-todo
```

## 7. Risks and Dependencies (Риски и зависимости)
- Репозиторий активно развивается — metadata и список skills (скиллов) могут быстро устареть.
- Это skill pack (пакет скиллов), а не runtime orchestrator (рантайм-оркестратор): сравнение должно быть честным и не притягивать state/error handling туда, где их нет.
- MIT license (лицензия MIT) допускает переиспользование, но прямое копирование больших текстов создаёт продуктовый и юридический шум; предпочтительно заимствовать паттерны, а не контент.
- Нужно учитывать, что часть возможностей зависит от конкретных agents/IDE (Claude Code, Gemini CLI, OpenCode, Cursor, Copilot, Antigravity).

## 8. Sources (Источники)
- https://github.com/addyosmani/agent-skills
- https://github.com/addyosmani/agent-skills/blob/main/README.md
- https://github.com/addyosmani/agent-skills/blob/main/docs/skill-anatomy.md
- https://github.com/addyosmani/agent-skills/blob/main/references/orchestration-patterns.md
- https://github.com/addyosmani/agent-skills/blob/main/AGENTS.md
- https://github.com/addyosmani/agent-skills/blob/main/agents/README.md
- https://github.com/addyosmani/agent-skills/tree/main/commands

## 9. Comments (Комментарии)
По первичной разведке проект позиционируется как “Production-grade engineering skills for AI coding agents”, содержит 24 skills (скилла), 6 lifecycle slash commands + 2 utility commands = 8 total commands (6 команд жизненного цикла + 2 служебные команды), agent personas (персоны), reference checklists (чеклисты) и plugin manifests (манифесты плагинов). Особенно интересны: единая anatomy (анатомия) скилла, anti-rationalization (анти-рационализация), progressive disclosure (прогрессивное раскрытие), fan-out review (параллельное ревью) и явный запрет nested persona orchestration (вложенной оркестрации персон).

## 10. Result (Результат выполнения)
- Создан comparison report: `docs/research/framework-comparisons/agent-skills-comparison.md`.
- Обновлена сводная таблица: строка `Agent Skills` (#28), счётчик `28 / 28`, рекомендации по authoring/governance patterns.
- Сохранён self-contained отчёт аналитика: `docs/agents/reports/system-analyst/2026-06-13_21-51_agent-skills-analysis.md`.
- Пройдены self-review и external review с Approval после доработок:
  - `docs/agents/reports/system-analyst/2026-06-13_22-17_agent-skills-implementation-self-review.md`;
  - `docs/agents/reports/technical-writer/2026-06-13_22-25_agent-skills-doc-review.md`.
- Создан PR: https://github.com/prikotov/task-orchestrator/pull/258.
- Задача оставлена в `status: review`, файл не перенесён в `todo/done/` до merge finalization.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-13 | Тимлид (Алекс) | Создание задачи и постановка исследования |
| 2026-06-13 | Аналитик (Шерлок) | Выполнено исследование Agent Skills, создан comparison report, обновлена сводная таблица и подготовлен отчёт agent-report; задача оставлена на review без переноса в done. |
| 2026-06-13 | Тимлид (Алекс) | Создан PR #258, задача переведена в `review`; финализация `done` и перенос в `todo/done/` будут выполнены перед merge. |
