---
type: research
created: 2026-05-08
value: V3
complexity: C3
priority: P2
depends_on: []
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee: Аналитик Шерлок
branch: task/research-opencode
pr:
status: in_progress
---

# TASK-research-opencode: Исследовать OpenCode (anomalyco/opencode) для сравнения с task-orchestrator

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда мы оцениваем AI-agent фреймворки и паттерны оркестрации, я хочу изучить OpenCode (https://github.com/anomalyco/opencode), чтобы понять его модель оркестрации агентов, подход к multi-agent координации, обработку ошибок и state management — и сравнить с нашими подходами.

### Goal (Цель по SMART)
Провести техническое исследование OpenCode: архитектура, модель агентов, оркестрация, обработка ошибок, расширяемость. Составить отчёт с выводами: заимствовать паттерны, использовать как dependency, или не подходит. Добавить строку #22 в сводную таблицу `docs/research/agent-frameworks-summary.md`.

## 2. Context and Scope (Контекст и Границы)

*   **Где делаем:** `docs/research/framework-comparisons/opencode-comparison.md`
*   **Текущее поведение:** В `docs/research/framework-comparisons/` уже есть 21 сравнительный анализ и сводная таблица `agent-frameworks-summary.md` (21 строка)
*   **Важно:** OpenCode — `anomalyco/opencode`, TypeScript, 156K+ звёзд, активный проект. НЕ путать с `opencode-ai/opencode` (Go, архивирован, продолжен как Crush)
*   **Границы (Out of Scope):** Не пишем код интеграции — только исследование

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [x] Исследован OpenCode (anomalyco/opencode): README, документация, исходный код (TypeScript)
- [x] Отчёт `docs/research/framework-comparisons/opencode-comparison.md` по формату существующих comparison-документов
- [x] Строка #22 добавлена в сводную таблицу `docs/research/agent-frameworks-summary.md`
- [x] Вердикт: заимствовать паттерны / dependency / не подходит

### 🟡 Should Have (Желательно)
- [x] Сравнение с нашими паттернами (ExecutionStrategy, DynamicLoop, ChainDefinition)
- [x] Конкретные рекомендации: что заимствовать, приоритет

### ⚫ Won't Have (Не в этот раз)
- Код интеграции
- Performance-бенчмарки

## 4. Implementation Plan (План реализации)

1. [ ] Изучить репозиторий https://github.com/anomalyco/opencode: README, исходный код (TypeScript), примеры
2. [ ] Проанализировать по единой методологии: модель оркестрации, state management, error handling, extensibility
3. [ ] Составить отчёт `docs/research/framework-comparisons/opencode-comparison.md`
4. [ ] Добавить строку #22 в `docs/research/agent-frameworks-summary.md`

## 5. Definition of Done (Критерии приёмки)

- [x] Файл `docs/research/framework-comparisons/opencode-comparison.md` создан
- [x] Строка OpenCode добавлена в сводную таблицу
- [x] Вердикт сформулирован

## 6. Risks and Dependencies (Риски и зависимости)

- Проект на TypeScript — крупная кодовая база (156K звёзд), анализ на уровне архитектуры и ключевых модулей

## 7. Sources (Источники)

- https://github.com/anomalyco/opencode
- Существующие comparison-документы в `docs/research/framework-comparisons/`

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-08 | Тимлид (Алекс) | Создание задачи |
