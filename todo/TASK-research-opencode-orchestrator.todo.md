---
type: research
created: 2026-05-07
value: V3
complexity: C3
priority: P2
depends_on: []
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee: Аналитик Шерлок
branch: task/research-opencode-orchestrator
pr:
status: todo
---

# TASK-research-opencode-orchestrator: Исследовать OpenCode Orchestrator (Kilo) для сравнения с task-orchestrator

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда мы оцениваем AI-agent фреймворки и паттерны оркестрации, я хочу изучить OpenCode Orchestrator Mode от Kilo AI (https://kilo.ai/docs/code-with-ai/agents/orchestrator-mode), чтобы понять его модель оркестрации агентов, подход к multi-agent координации, обработку ошибок и state management — и сравнить с нашими подходами.

### Goal (Цель по SMART)
Провести техническое исследование OpenCode Orchestrator: архитектура, модель агентов, оркестрация, обработка ошибок, расширяемость. Составить отчёт с выводами: заимствовать паттерны, использовать как dependency, или не подходит. Добавить строку #21 в сводную таблицу `docs/research/agent-frameworks-summary.md`.

## 2. Context and Scope (Контекст и Границы)

*   **Где делаем:** `docs/research/framework-comparisons/opencode-orchestrator-comparison.md`
*   **Текущее поведение:** В `docs/research/framework-comparisons/` уже есть 20 сравнительных анализов и сводная таблица `agent-frameworks-summary.md` (20 строк)
*   **Границы (Out of Scope):** Не пишем код интеграции — только исследование

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] Исследован OpenCode Orchestrator Mode: документация, исходный код, примеры
- [ ] Отчёт `docs/research/framework-comparisons/opencode-orchestrator-comparison.md` по формату существующих comparison-документов
- [ ] Строка #21 добавлена в сводную таблицу `docs/research/agent-frameworks-summary.md`
- [ ] Вердикт: заимствовать паттерны / dependency / не подходит

### 🟡 Should Have (Желательно)
- [ ] Сравнение с нашими паттернами (ExecutionStrategy, DynamicLoop, ChainDefinition)
- [ ] Конкретные рекомендации: что заимствовать, приоритет

### ⚫ Won't Have (Не в этот раз)
- Код интеграции
- Performance-бенчмарки

## 4. Implementation Plan (План реализации)

1. [ ] Изучить документацию https://kilo.ai/docs/code-with-ai/agents/orchestrator-mode и исходный код (если открытый)
2. [ ] Проанализировать по единой методологии: модель оркестрации, state management, error handling, extensibility
3. [ ] Составить отчёт `docs/research/framework-comparisons/opencode-orchestrator-comparison.md`
4. [ ] Добавить строку #21 в `docs/research/agent-frameworks-summary.md`

## 5. Definition of Done (Критерии приёмки)

- [ ] Файл `docs/research/framework-comparisons/opencode-orchestrator-comparison.md` создан
- [ ] Строка OpenCode Orchestrator добавлена в сводную таблицу
- [ ] Вердикт сформулирован

## 6. Risks and Dependencies (Риски и зависимости)

- Продукт может быть проприетарным — анализ по документации и наблюдаемому поведению

## 7. Sources (Источники)

- https://kilo.ai/docs/code-with-ai/agents/orchestrator-mode
- Существующие comparison-документы в `docs/research/framework-comparisons/`

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-07 | Тимлид (Алекс) | Создание задачи |
