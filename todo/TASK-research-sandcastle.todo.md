---
type: research
created: 2026-05-04
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

# TASK-research-sandcastle: Исследовать Sandcastle (Matt Pocock) для сравнения с task-orchestrator

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда мы оцениваем AI-agent фреймворки и паттерны оркестрации, я хочу изучить Sandcastle (https://github.com/mattpocock/sandcastle), чтобы понять его модель оркестрации агентов, подход к multi-agent координации, обработку ошибок и state management — и сравнить с нашими подходами.

### Goal (Цель по SMART)
Провести техническое исследование Sandcastle: архитектура, модель агентов, оркестрация, обработка ошибок, расширяемость. Составить отчёт с выводами: заимствовать паттерны, использовать как dependency, или не подходит. Добавить строку в сводную таблицу `docs/research/agent-frameworks-summary.md`.

## 2. Context and Scope (Контекст и Границы)

*   **Где делаем:** `docs/research/sandcastle-comparison.md`
*   **Текущее поведение:** В `docs/research/` уже есть сравнительные анализы 13+ фреймворков и сводная таблица `agent-frameworks-summary.md`
*   **Границы (Out of Scope):** Не пишем код интеграции — только исследование

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] Исследован репозиторий https://github.com/mattpocock/sandcastle: README, исходный код, примеры
- [ ] Отчёт `docs/research/sandcastle-comparison.md` по формату существующих comparison-документов
- [ ] Строка добавлена в сводную таблицу `docs/research/agent-frameworks-summary.md`
- [ ] Вердикт: заимствовать паттерны / dependency / не подходит

### 🟡 Should Have (Желательно)
- [ ] Сравнение с нашими паттернами (ExecutionStrategy, DynamicLoop, ChainDefinition)
- [ ] Конкретные рекомендации: что заимствовать, приоритет

### ⚫ Won't Have (Не в этот раз)
- Код интеграции
- Performance-бенчмарки

## 4. Implementation Plan (План реализации)

1. [ ] Изучить README, документацию и исходный код репозитория
2. [ ] Проанализировать по единой методологии: модель оркестрации, state management, error handling, extensibility
3. [ ] Составить отчёт `docs/research/sandcastle-comparison.md`
4. [ ] Добавить строку в `docs/research/agent-frameworks-summary.md`

## 5. Definition of Done (Критерии приёмки)

- [ ] Файл `docs/research/sandcastle-comparison.md` создан
- [ ] Строка Sandcastle добавлена в сводную таблицу
- [ ] Вердикт сформулирован

## 6. Risks and Dependencies (Риски и зависимости)

- Репозиторий может быть ранней стадии — мало документации → анализ по исходному коду

## 7. Sources (Источники)

- https://github.com/mattpocock/sandcastle
- Существующие comparison-документы в `docs/research/`

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-04 | Тимлид (Алекс) | Создание задачи |
