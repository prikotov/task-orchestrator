---
type: research
created: 2026-04-28
value: V3
complexity: C3
priority: P2
depends_on: []
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: todo
---

# TASK-research-agentcraft: Исследовать AgentCraft для сравнения с task-orchestrator

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы оцениваем AI-agent фреймворки, я хочу изучить AgentCraft, чтобы понять его модель оркестрации, подход к агентам и workflow — и сравнить с нашими подходами.

### Goal (Цель по SMART)
Провести техническое исследование AgentCraft: архитектура, модель агентов, оркестрация, state management, error handling, расширяемость. Составить отчёт с выводами: заимствовать паттерны, использовать как dependency, или не подходит. Добавить строку в сводную таблицу.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** docs/research/
*   **Текущее поведение:** В docs/research/ уже есть сравнительные анализы 14 фреймворков и сводная таблица agent-frameworks-summary.md
*   **Границы (Out of Scope):** Не пишем код интеграции — только исследование

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Изучить AgentCraft: архитектуру, модель агентов, workflow-паттерны, tools
- [ ] Сравнить с нашей моделью (static/dynamic chains, retry, circuit breaker, budget, quality gates)
- [ ] Оформить отчёт в docs/research/agentcraft-comparison.md по формату существующих comparison-документов
- [ ] Заполнить строку для AgentCraft в сводной таблице docs/research/agent-frameworks-summary.md
### 🟡 Should Have (Желательно)
- [ ] Определить конкретные паттерны, которые стоит заимствовать
- [ ] Оценить подход к state management и error handling
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- [ ] Написание кода интеграции

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем перед стартом.*

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт docs/research/agentcraft-comparison.md создан по формату существующих comparison-документов
- [ ] Содержит чёткий вывод: заимствовать / использовать / не подходит
- [ ] Строка AgentCraft в сводной таблице docs/research/agent-frameworks-summary.md заполнена

## 6. Verification (Самопроверка)
```bash
ls docs/research/agentcraft-comparison.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- Продукт может быть коммерческим/проприетарным — анализ только по документации и публичным материалам
- Недостаточно публичной технической документации — может потребоваться анализ демо/API

## 8. Sources (Источники)
- https://www.getagentcraft.com/

## 9. Comments (Комментарии)

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-28 | Тимлид (Алекс) | Создание задачи |
