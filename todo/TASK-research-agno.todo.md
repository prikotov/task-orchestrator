---
type: research
created: 2026-04-22
value: V3
complexity: C3
priority: P2
depends_on: []
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee:
branch: task/research-agno
pr:
status: review
---

# TASK-research-agno: Исследовать Agno (бывший Phi) для сравнения с task-orchestrator

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы оцениваем AI-agent фреймворки, я хочу изучить Agno, чтобы понять его модель оркестрации агентов, подход к multi-agent системам, работу с памятью и инструментами — и сравнить с нашими подходами.

### Goal (Цель по SMART)
Провести техническое исследование Agno: архитектура, модель агентов, multi-agent orchestration, memory, tools, расширяемость. Составить отчёт с выводами: заимствовать паттерны, использовать как dependency, или не подходит. Добавить строку в сводную таблицу.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** docs/research/
*   **Текущее поведение:** В docs/research/ уже есть сравнительные анализы 10+ фреймворков и сводная таблица agent-frameworks-summary.md
*   **Границы (Out of Scope):** Не пишем код интеграции — только исследование

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Изучить Agno: архитектуру, Python-first подход, модель агентов, multi-agent teams, tools
- [ ] Изучить систему оркестрации: Agent Teams, workflow-паттерны, routing
- [ ] Сравнить с нашей моделью (static/dynamic chains, retry, circuit breaker, budget, quality gates)
- [ ] Оформить отчёт в docs/research/agno-comparison.md по формату существующих comparison-документов
- [ ] Заполнить строку для Agno в сводной таблице docs/research/agent-frameworks-summary.md
### 🟡 Should Have (Желательно)
- [ ] Определить конкретные паттерны, которые стоит заимствовать
- [ ] Оценить подход Agno к memory (short-term, long-term), RAG, multimodal
- [ ] Сравнить Python-first подход с нашим PHP/Symfony подходом
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- [ ] Написание кода интеграции

## 4. Implementation Plan (План реализации)
1. [ ] Изучить репозиторий https://github.com/agno-agi/agno и документацию https://docs.agno.com
2. [ ] Сравнить модель agents/teams и оркестрации с нашей
3. [ ] Написать docs/research/agno-comparison.md
4. [ ] Добавить строку Agno в docs/research/agent-frameworks-summary.md

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт docs/research/agno-comparison.md создан по формату существующих comparison-документов
- [ ] Содержит чёткий вывод: заимствовать / использовать / не подходит
- [ ] Строка Agno в сводной таблице docs/research/agent-frameworks-summary.md заполнена

## 6. Verification (Самопроверка)
```bash
ls docs/research/agno-comparison.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- Agno активно развивается — архитектура и API могут меняться
- Python-экосистема — оценка применимости паттернов в PHP требует аккуратности

## 8. Sources (Источники)
- https://www.agno.com
- https://docs.agno.com
- https://github.com/agno-agi/agno

## 9. Comments (Комментарии)
Agno (бывший Phi) — Python-фреймворк для построения AI-агентов с акцентом на high performance и minimal abstraction. Key features: Agent Teams (multi-agent orchestration), built-in memory (short-term, long-term, user), multimodal поддержка, 20+ модель-провайдеров, structured output. Заявляет о fast agent instantiation (~3ms). Интересен моделью Agent Teams (mode: route, coordinate) — прямая аналогия с нашими static/dynamic chains.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-22 | Тимлид (Алекс) | Создание задачи |
