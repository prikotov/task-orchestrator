---
type: research
created: 2026-05-04
value: V3
complexity: C3
priority: P2
depends_on: []
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee: Аналитик Шерлок
branch: task/research-hermes-agent
pr:
status: done
---

# TASK-research-hermes-agent: Исследовать Hermes Agent (Nous Research) для сравнения с task-orchestrator

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда мы оцениваем AI-agent фреймворки и паттерны оркестрации, я хочу изучить Hermes Agent (https://github.com/nousresearch/hermes-agent), чтобы понять его модель оркестрации агентов, подход к multi-agent координации, обработку ошибок и state management — и сравнить с нашими подходами.

### Goal (Цель по SMART)
Провести техническое исследование Hermes Agent: архитектура, модель агентов, оркестрация, обработка ошибок, расширяемость. Составить отчёт с выводами: заимствовать паттерны, использовать как dependency, или не подходит. Добавить строку в сводную таблицу `docs/research/agent-frameworks-summary.md`.

## 2. Context and Scope (Контекст и Границы)

*   **Где делаем:** `docs/research/hermes-agent-comparison.md`
*   **Текущее поведение:** В `docs/research/` уже есть сравнительные анализы 13+ фреймворков и сводная таблица `agent-frameworks-summary.md`
*   **Границы (Out of Scope):** Не пишем код интеграции — только исследование

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [x] Исследован репозиторий https://github.com/nousresearch/hermes-agent: README, исходный код, примеры
- [x] Отчёт `docs/research/hermes-agent-comparison.md` по формату существующих comparison-документов
- [x] Строка добавлена в сводную таблицу `docs/research/agent-frameworks-summary.md`
- [x] Вердикт: заимствовать паттерны / dependency / не подходит

### 🟡 Should Have (Желательно)
- [x] Сравнение с нашими паттернами (ExecutionStrategy, DynamicLoop, ChainDefinition)
- [x] Конкретные рекомендации: что заимствовать, приоритет

### ⚫ Won't Have (Не в этот раз)
- Код интеграции
- Performance-бенчмарки

## 4. Implementation Plan (План реализации)

1. [ ] Изучить README, документацию и исходный код репозитория
2. [ ] Проанализировать по единой методологии: модель оркестрации, state management, error handling, extensibility
3. [ ] Составить отчёт `docs/research/hermes-agent-comparison.md`
4. [ ] Добавить строку в `docs/research/agent-frameworks-summary.md`

## 5. Definition of Done (Критерии приёмки)

- [x] Файл `docs/research/hermes-agent-comparison.md` создан
- [x] Строка Hermes Agent добавлена в сводную таблицу
- [x] Вердикт сформулирован

## 6. Risks and Dependencies (Риски и зависимости)

- Репозиторий может быть ранней стадии — мало документации → анализ по исходному коду

## 7. Sources (Источники)

- https://github.com/nousresearch/hermes-agent
- Существующие comparison-документы в `docs/research/`

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-04 | Тимлид (Алекс) | Создание задачи |
