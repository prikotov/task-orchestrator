---
type: research
created: 2026-05-02
value: V2
complexity: C2
priority: P2
depends_on: []
epic: done/EPIC-research-agent-frameworks-comparison.md
author:
assignee:
branch:
pr:
status: todo
---

# TASK-research-oz-cloud-agents: Oz — платформа оркестрации облачных AI-агентов

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы исследуем паттерны AI-агентной оркестрации, я хочу проанализировать Oz (https://www.warp.dev/oz) — платформу оркестрации облачных AI-агентов, чтобы понять её подход к управлению агентными workflow, обработке контекста и масштабированию.

### Goal (Цель по SMART)
Создать comparison-документ `docs/research/oz-cloud-agents-comparison.md` по единой методологии (модель оркестрации, state management, error handling, extensibility). Заполнить строку в сводной таблице `docs/research/agent-frameworks-summary.md`. Вердикт: заимствовать / dependency / не подходит.

## 2. Context and Scope (Контекст и Границы)
* **Что это:** Oz — orchestration platform for cloud agents от команды Warp. Позволяет запускать и координировать множество AI-агентов в облаке.
* **Почему интересно:** платформа оркестрации облачных агентов — другой уровень масштабирования по сравнению с нашим CLI-подходом.
* **Границы (Out of Scope):**
  - Установка или использование Oz
  - Code review исходников (проприетарный)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Comparison-документ в `docs/research/oz-cloud-agents-comparison.md`
- [ ] Строка в сводной таблице `docs/research/agent-frameworks-summary.md`
- [ ] Вердикт: заимствовать / dependency / не подходит

### 🟡 Should Have (Желательно)
- [ ] Сравнение с Archon и Mastra AI по cloud-orchestration подходу

## 4. Implementation Plan (План реализации)
1. Изучить https://www.warp.dev/oz — документация, blog, демо
2. Проанализировать по единой методологии
3. Написать comparison-документ
4. Обновить сводную таблицу

## 5. Definition of Done (Критерии приёмки)
- [ ] `docs/research/oz-cloud-agents-comparison.md` создан
- [ ] Строка в сводной таблице добавлена
- [ ] Вердикт обоснован

## 6. Risks and Dependencies (Риски и зависимости)
- Oz — проприетарный продукт, анализ только по документации
- Продукт новый, информация может быть неполной

## 7. Sources (Источники)
- https://www.warp.dev/oz
- Существующие comparison-документы в `docs/research/`

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-02 | Тимлид | Создание задачи |
