---
type: research
created: 2026-04-20
value: V3
complexity: C3
priority: P2
depends_on: []
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee: Технический писатель (Гермиона)
branch: task/research-archon-ai-planner
pr: https://github.com/prikotov/task-orchestrator/pull/44
status: done
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
- [x] Изучить репозиторий https://github.com/coleam00/Archon: архитектуру, модель мета-оркестрации, планирование
- [x] Сравнить с нашей моделью (static/dynamic chains, retry, circuit breaker, budget, quality gates)
- [x] Оформить отчёт в docs/research/archon-comparison.md по формату существующих comparison-документов
- [x] Заполнить строку для Archon в сводной таблице docs/research/agent-frameworks-summary.md
### 🟡 Should Have (Желательно)
- [x] Определить конкретные паттерны мета-оркестрации, которые стоит заимствовать
- [x] Оценить подход к генерации agent-конфигураций и self-improvement
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- [x] Написание кода интеграции

## 4. Implementation Plan (План реализации)
1. [x] Изучить репозиторий https://github.com/coleam00/Archon: README, архитектуру, исходный код
2. [x] Сравнить с нашей моделью оркестрации
3. [x] Написать docs/research/archon-comparison.md

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт docs/research/archon-comparison.md создан по формату существующих comparison-документов
- [x] Содержит чёткий вывод: заимствовать / использовать / не подходит
- [x] Строка Archon в сводной таблице docs/research/agent-frameworks-summary.md заполнена

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

## Инструкции для сабагента

**Твоя роль:** docs/agents/roles/team/technical_writer_hermione.ru.md
**Ветка:** task/research-archon-ai-planner (уже создана и активна)
**PR:** будет создан после коммита

### Порядок действий
1. Переключись в ветку `task/research-archon-ai-planner`: `git checkout task/research-archon-ai-planner`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. Проверок PHPUnit/Psalm не требуется — задача docs-only.
6. Сделай `git push`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-20 | Тимлид (Алекс) | Создание задачи |
