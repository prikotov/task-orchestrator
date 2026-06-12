---
type: research
created: 2026-06-13
value: V3
complexity: C2
priority: P2
depends_on: []
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch:
pr:
status: todo
---

# TASK-research-agent-skills: Исследовать addyosmani/agent-skills для сравнения с task-orchestrator

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
- [ ] Изучить репозиторий `https://github.com/addyosmani/agent-skills`: структура, README, лицензия, поддерживаемые агенты/IDE.
- [ ] Изучить anatomy (анатомию) `SKILL.md`: frontmatter, workflow sections, rationalizations (анти-рационализации), red flags (красные флаги), verification (проверки), progressive disclosure (прогрессивное раскрытие контекста).
- [ ] Изучить orchestration model (модель оркестрации): lifecycle commands `/spec` → `/plan` → `/build` → `/test` → `/review` → `/ship`, personas, fan-out review, запрет persona-calls-persona.
- [ ] Сравнить с нашей моделью `docs/agents/skills/*`, `docs/agents/roles/team/*`, `task-via-subagents`, `epic-via-subagents`, `brainstorm`.
- [ ] Оформить отчёт `docs/research/framework-comparisons/agent-skills-comparison.md` по формату существующих comparison-документов.
- [ ] Добавить строку `Agent Skills` в `docs/research/agent-frameworks-summary.md` и обновить счётчик заполнения.
- [ ] Дать чёткий verdict (вердикт): dependency / заимствовать паттерны / не подходит.
### 🟡 Should Have (Желательно)
- [ ] Выделить конкретные паттерны для улучшения наших скиллов: anti-rationalization tables, skill anatomy validator, lifecycle command mapping, reference checklists.
- [ ] Оценить совместимость MIT license (лицензии MIT) и риски прямого копирования текстов.
- [ ] Сравнить plugin manifests (манифесты плагинов) Claude/Antigravity с нашими plugin/skill conventions (конвенциями).
### 🟢 Could Have (Опционально)
- [ ] Предложить backlog tasks (задачи бэклога) на улучшение нашего skill authoring guide (гайда написания скиллов).
- [ ] Составить маленькую Mermaid-диаграмму сопоставления lifecycle команд с нашими ролями.
### ⚫ Won't Have (Не будем делать)
- [ ] Интеграция `agent-skills` как runtime dependency.
- [ ] Массовое копирование чужих `SKILL.md` в проект.
- [ ] Изменение существующих production workflows (рабочих процессов) без отдельной задачи.

## 4. Implementation Plan (План реализации)
1. [ ] Изучить metadata (метаданные) GitHub repo: description, license, stars/forks, default branch, topics.
2. [ ] Изучить `README.md`, `docs/skill-anatomy.md`, `AGENTS.md`, `agents/README.md`, `references/orchestration-patterns.md`.
3. [ ] Просмотреть 3–5 representative skills (репрезентативных скиллов): `using-agent-skills`, `spec-driven-development`, `planning-and-task-breakdown`, `code-review-and-quality`, `git-workflow-and-versioning`.
4. [ ] Сравнить findings (находки) с нашими `docs/agents/skills/` и `docs/agents/roles/team/`.
5. [ ] Написать `docs/research/framework-comparisons/agent-skills-comparison.md`.
6. [ ] Обновить `docs/research/agent-frameworks-summary.md`: строка `Agent Skills`, счётчик `28 / 28`, рекомендации/паттерны при необходимости.
7. [ ] Перевести задачу в `done`, перенести в `todo/done/`, обновить ссылку в epic (эпике).

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт `docs/research/framework-comparisons/agent-skills-comparison.md` создан и содержит сравнение с `task-orchestrator`.
- [ ] В отчёте есть таблица: orchestration model, state management, error handling, extensibility, applicability.
- [ ] В `docs/research/agent-frameworks-summary.md` добавлена строка `Agent Skills` и обновлён счётчик.
- [ ] В отчёте перечислены 3–7 concrete patterns (конкретных паттернов) для возможного заимствования.
- [ ] Указаны sources (источники) и дата анализа.

## 6. Verification (Самопроверка)
```bash
ls docs/research/framework-comparisons/agent-skills-comparison.md
grep -c "Agent Skills" docs/research/agent-frameworks-summary.md
grep -n "28 / 28" docs/research/agent-frameworks-summary.md
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

## 9. Comments (Комментарии)
По первичной разведке проект позиционируется как “Production-grade engineering skills for AI coding agents”, содержит 24 skills (скилла), 7 lifecycle slash commands (команд жизненного цикла), agent personas (персоны), reference checklists (чеклисты) и plugin manifests (манифесты плагинов). Особенно интересны: единая anatomy (анатомия) скилла, anti-rationalization (анти-рационализация), progressive disclosure (прогрессивное раскрытие), fan-out review (параллельное ревью) и явный запрет nested persona orchestration (вложенной оркестрации персон).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-13 | Тимлид (Алекс) | Создание задачи и постановка исследования |
