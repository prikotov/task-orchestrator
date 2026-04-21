---
type: research
created: 2026-04-20
value: V3
complexity: C4
priority: P2
depends_on: []
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee: Технический писатель (Гермиона)
branch: task/research-crewai-langgraph-autogen
pr: https://github.com/prikotov/task-orchestrator/pull/42
status: done
---

# TASK-research-crewai-langgraph-autogen: Исследовать CrewAI, LangGraph и AutoGen для сравнения с task-orchestrator

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы оцениваем multi-agent orchestration фреймворки, я хочу изучить CrewAI, LangGraph и AutoGen, чтобы понять их модели оркестрации нескольких агентов, координации, распределения ролей — и сравнить с нашими подходами.

### Goal (Цель по SMART)
Провести техническое исследование трёх leading multi-agent фреймворков: архитектура, модель оркестрации, обработка ошибок, state management. Составить единый сравнительный отчёт с выводами: заимствовать паттерны, использовать как dependency, или не подходит.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** docs/research/
*   **Текущее поведение:** В docs/research/ уже есть сравнительные анализы (agent-bernstein-comparison.md, agent-orchestrator-comparison.md, superpowers-brainstorming-comparison.md)
*   **Границы (Out of Scope):** Не пишем код интеграции — только исследование

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Изучить CrewAI: архитектуру, модель crew/task/agent, role-based orchestration
- [ ] Изучить LangGraph: графовую модель оркестрации, state management, conditional edges
- [ ] Изучить AutoGen: multi-agent conversations, code execution, human-in-the-loop
- [ ] Сравнить все три с нашей моделью (static/dynamic chains, retry, circuit breaker, budget, quality gates)
- [ ] Оформить отчёт в docs/research/crewai-langgraph-autogen-comparison.md по формату существующих comparison-документов
- [ ] Заполнить строки для CrewAI, LangGraph, AutoGen в сводной таблице docs/research/agent-frameworks-summary.md
### 🟡 Should Have (Желательно)
- [ ] Составить сравнительную таблицу: модель оркестрации, state management, error handling, extensibility
- [ ] Определить конкретные паттерны, которые стоит заимствовать из каждого
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- [ ] Написание кода интеграции

## 4. Implementation Plan (План реализации)
1. [ ] Изучить репозитории и документацию CrewAI, LangGraph, AutoGen
2. [ ] Сравнить модели оркестрации между собой и с нашей
3. [ ] Составить сравнительную таблицу
4. [ ] Написать docs/research/crewai-langgraph-autogen-comparison.md

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт docs/research/crewai-langgraph-autogen-comparison.md создан по формату существующих comparison-документов
- [ ] Содержит сравнительную таблицу трёх фреймворков
- [ ] Содержит чёткий вывод: заимствовать / использовать / не подходит
- [ ] Строки CrewAI, LangGraph, AutoGen в сводной таблице docs/research/agent-frameworks-summary.md заполнены

## 6. Verification (Самопроверка)
```bash
ls docs/research/crewai-langgraph-autogen-comparison.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- Три продукта в одной задаче — объём исследования большой (C4)
- Все три активно развиваются — информация может устареть

## 8. Sources (Источники)
- https://github.com/crewAIInc/crewAI
- https://github.com/langchain-ai/langgraph
- https://github.com/microsoft/autogen

## 9. Comments (Комментарии)
Тройка самых популярных multi-agent orchestration фреймворков на Python. Прямые конкуренты по функциональности. Важно понять чем наша chain-модель лучше/хуже и какие паттерны стоит перенять.

## Инструкции для сабагента

**Твоя роль:** docs/agents/roles/team/technical_writer.ru.md
**Ветка:** task/research-crewai-langgraph-autogen (уже создана и активна)
**PR:** будет создан после коммита

### Порядок действий
1. Переключись в ветку `task/research-crewai-langgraph-autogen`: `git checkout task/research-crewai-langgraph-autogen`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. Проверок PHPUnit/Psalm не требуется — задача docs-only.
6. Сделай `git push`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-20 | Тимлид (Алекс) | Создание задачи |
