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

# TASK-research-duet: Duet (AI-агент для командной работы)

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мы развиваем архитектуру task-orchestrator, я хочу исследовать систему оркестрации Duet, чтобы понять паттерны оркестрации AI-агентов в командном контексте и определить, что стоит заимствовать.

### Goal (Цель по SMART)
Исследовать Duet (https://duet.so/) как систему оркестрации AI-агентов по 4 осям: модель оркестрации, state management, error handling, extensibility. Создать отчёт в `docs/research/framework-comparisons/duet-comparison.md`. Вердикт: заимствовать / dependency / не подходит.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/research/framework-comparisons/duet-comparison.md`
*   **Границы (Out of Scope):** код интеграции, бенчмарки

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Обзор проекта: что такое Duet, позиционирование, архитектура
- [ ] **Модель оркестрации** — как оркестируются агенты, workflow,autopilot (cron/scheduled tasks)
- [ ] **State management** — workspace, shared context, issues, projects
- [ ] **Error handling** — retry, recovery, agent failure handling
- [ ] **Extensibility** — skills, integrations, runtime model (Desktop / CLI / Cloud)
- [ ] Сравнение с task-orchestrator
- [ ] Вердикт: заимствовать / dependency / не подходит

### 🟡 Should Have (Желательно)
- [ ] Оценка модели Skills (shared workspace skills, import from URL/runtime/ClawHub)
- [ ] Оценка модели Agents + Runtimes (daemon-based, remote machines)
- [ ] Паттерны для заимствования

### ⚫ Won't Have (Не будем делать)
- [ ] Код интеграции, бенчмарки

## 4. Implementation Plan (План реализации)
1. [ ] Изучить сайт: https://duet.so/ и документацию
2. [ ] Найти GitHub-репозиторий (aomni-com)
3. [ ] Оценить архитектуру по 4 осям
4. [ ] Создать отчёт в `docs/research/framework-comparisons/duet-comparison.md`
5. [ ] Добавить строку в `docs/research/agent-frameworks-summary.md`

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт создан
- [ ] Все 4 оси оценены
- [ ] Вердикт с обоснованием
- [ ] Строка добавлена в сводную таблицу

## 6. Verification (Самопроверка)
```bash
ls docs/research/framework-comparisons/duet-comparison.md
grep "Duet" docs/research/agent-frameworks-summary.md
```

## 7. Sources (Источники)
- [Duet — сайт](https://duet.so/)
- [Aomni — GitHub](https://github.com/aomni-com)

## 8. Comments (Комментарии)
Duet (by Aomni, $4.4M funding) — "always-on AI agent built for teams". Позиционируется как автономный бизнес-агент: автоматизация GTM, product, ops workflows. Ключевые фичи: shared workspace, skills, autopilot (scheduled tasks), runtimes (Desktop/CLI/Cloud). Проприетарный, cloud/SaaS.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-13 | Тимлид (Алекс) | Создание задачи |
