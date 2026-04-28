---
type: research
created: 2026-04-28
value: V3
complexity: C3
priority: P2
depends_on: []
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee: Технический писатель (Гермиона)
branch: task/research-agentcraft
pr:
status: in_progress
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
1. Изучить проект AgentCraft (https://www.getagentcraft.com/) — архитектуру, модель агентов, workflow, tools
2. При недостатке публичной документации — проанализировать доступные материалы (сайт, демо, API, GitHub)
3. Составить отчёт по формату существующих comparison-документов (см. docs/research/crush-comparison.md как пример)
4. Заполнить строку AgentCraft (#16) в сводной таблице docs/research/agent-frameworks-summary.md

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

## Инструкции для сабагента

**Ветка:** task/research-agentcraft (уже создана и активна)
**PR:** будет создан после подготовки

### Порядок действий
1. Переключись в ветку `task/research-agentcraft`: `git checkout task/research-agentcraft`
2. Изучи проект AgentCraft (https://www.getagentcraft.com/) — архитектуру, модель агентов, workflow-паттерны, tools, state management, error handling, расширяемость.
3. При недостатке документации — анализируй сайт, демо, API, GitHub-профиль, публичные материалы.
4. Создай отчёт docs/research/agentcraft-comparison.md по формату существующих comparison-документов (как docs/research/crush-comparison.md).
5. Заполни строку AgentCraft (#16) в сводной таблице docs/research/agent-frameworks-summary.md.
6. Следуй [Конвенциям](docs/conventions/index.md) проекта.
7. После реализации запусти проверки: `vendor/bin/phpunit` и `vendor/bin/psalm` (хотя для docs-only они могут быть пропущены — укажи это в отчёте).
8. Сделай `git push`.

## 9. Comments (Комментарии)

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-28 | Тимлид (Алекс) | Создание задачи |
