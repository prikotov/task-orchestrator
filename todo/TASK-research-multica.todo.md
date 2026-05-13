---
# Metadata (Метаданные)
type: research
created: 2026-05-13
value: V3
complexity: C2
priority: P2
depends_on:
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch:
pr:
status: todo
---

# TASK-research-multica: Multica (Project Management для Human + Agent Teams)

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мы развиваем архитектуру task-orchestrator, я хочу исследовать Multica как систему оркестрации AI-агентов в контексте проектного управления, чтобы понять паттерны управления задачами (issues, projects, autopilot) и определить, что стоит заимствовать.

### Goal (Цель по SMART)
Исследовать Multica (https://multica.ai/, GitHub: multica-ai/multica, ~28k stars, TypeScript) по 4 осям: модель оркестрации, state management, error handling, extensibility. Создать отчёт в `docs/research/framework-comparisons/multica-comparison.md`. Вердикт: заимствовать / dependency / не подходит.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/research/framework-comparisons/multica-comparison.md`
*   **Границы (Out of Scope):** код интеграции, бенчмарки

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Обзор проекта: что такое Multica, позиционирование, архитектура
- [ ] **Модель оркестрации** — как оркестируются агенты (daemon-based runtimes), autopilot (scheduled recurring tasks), issue → agent assignment, chat interface
- [ ] **State management** — workspace, issues, projects, skills, shared team context
- [ ] **Error handling** — agent failure, task failure, offline detection (heartbeat)
- [ ] **Extensibility** — skills (SKILL.md, import from URL/ClawHub/Skills.sh/GitHub), runtimes (local/remote/cloud), agents с кастомными skill-ами
- [ ] Сравнение с task-orchestrator
- [ ] Вердикт: заимствовать / dependency / не подходит

### 🟡 Should Have (Желательно)
- [ ] Оценка модели Issues/Projects как task management для AI-агентов
- [ ] Оценка модели Skills (workspace-level shared skills, per-agent assignment)
- [ ] Оценка модели Runtimes (daemon-based, remote machines, health monitoring)
- [ ] Паттерны для заимствования

### ⚫ Won't Have (Не будем делать)
- [ ] Код интеграции, бенчмарки

## 4. Implementation Plan (План реализации)
1. [ ] Изучить репозиторий: https://github.com/multica-ai/multica
2. [ ] Изучить сайт: https://multica.ai/
3. [ ] Оценить архитектуру по 4 осям
4. [ ] Создать отчёт в `docs/research/framework-comparisons/multica-comparison.md`
5. [ ] Добавить строку в `docs/research/agent-frameworks-summary.md`

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт создан
- [ ] Все 4 оси оценены
- [ ] Вердикт с обоснованием
- [ ] Строка добавлена в сводную таблицу

## 6. Verification (Самопроверка)
```bash
ls docs/research/framework-comparisons/multica-comparison.md
grep "Multica" docs/research/agent-frameworks-summary.md
```

## 7. Sources (Источники)
- [Multica — GitHub](https://github.com/multica-ai/multica)
- [Multica — сайт](https://multica.ai/)

## 8. Comments (Комментарии)
Multica — open-source платформа для управления проектами с AI-агентами как «teammates» (~28k stars, TypeScript). Ключевые фичи: Issues/Projects (как Linear/Jira но для AI-агентов), Skills (SKILL.md, workspace-level sharing), Runtimes (daemon-based, local/remote/cloud), Autopilot (scheduled recurring tasks для агентов), Chat (контекстное общение с агентом). Лицензия — NOASSERTION (проверить).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-13 | Тимлид (Алекс) | Создание задачи |
