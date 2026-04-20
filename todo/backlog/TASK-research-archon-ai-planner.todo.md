---
type: research
created: 2026-04-20
value: V3
complexity: C3
priority: P2
depends_on: []
epic:
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: todo
---

# TASK-research-archon-ai-planner: Исследовать coleam00/Archon для сравнения с task-orchestrator

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы оцениваем подходы к мета-оркестрации AI-агентов (агент, который строит других агентов), я хочу изучить Archon, чтобы понять его модель планирования, генерации и оркестрации — и сравнить с нашими подходами.

### Goal (Цель по SMART)
Провести техническое исследование Archon: архитектура, модель мета-оркестрации, планирование задач, генерация agent-конфигураций. Составить отчёт с выводами: заимствовать паттерны, использовать как dependency, или не подходит.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** docs/research/
*   **Текущее поведение:** В docs/research/ уже есть сравнительные анализы (agent-bernstein-comparison.md, agent-orchestrator-comparison.md, superpowers-brainstorming-comparison.md)
*   **Границы (Out of Scope):** Не пишем код интеграции — только исследование

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Изучить репозиторий https://github.com/coleam00/Archon: архитектуру, модель мета-оркестрации, планирование
- [ ] Сравнить с нашей моделью (static/dynamic chains, retry, circuit breaker, budget, quality gates)
- [ ] Оформить отчёт в docs/research/archon-comparison.md по формату существующих comparison-документов
### 🟡 Should Have (Желательно)
- [ ] Определить конкретные паттерны мета-оркестрации, которые стоит заимствовать
- [ ] Оценить подход к генерации agent-конфигураций и self-improvement
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- [ ] Написание кода интеграции

## 4. Implementation Plan (План реализации)
1. [ ] Изучить репозиторий https://github.com/coleam00/Archon: README, архитектуру, исходный код
2. [ ] Сравнить с нашей моделью оркестрации
3. [ ] Написать docs/research/archon-comparison.md

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт docs/research/archon-comparison.md создан по формату существующих comparison-документов
- [ ] Содержит чёткий вывод: заимствовать / использовать / не подходит

## 6. Verification (Самопроверка)
```bash
ls docs/research/archon-comparison.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- Archon может быть в ранней стадии — мало документации

## 8. Sources (Источники)
- https://github.com/coleam00/Archon

## 9. Comments (Комментарии)
Archon — "AI agent that builds AI agents". Мета-оркестрация — интересный паттерн, может быть полезен для динамической генерации цепочек в task-orchestrator.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-20 | Тимлид (Алекс) | Создание задачи |
