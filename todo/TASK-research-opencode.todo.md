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

# TASK-research-opencode: Исследовать OpenCode (opencode-ai/opencode) для сравнения с task-orchestrator

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда мы оцениваем AI-agent фреймворки и паттерны оркестрации, я хочу изучить OpenCode (https://github.com/opencode-ai/opencode), чтобы понять его модель оркестрации агентов, подход к multi-agent координации, обработку ошибок и state management — и сравнить с нашими подходами.

### Goal (Цель по SMART)
Провести техническое исследование OpenCode: архитектура, модель агентов, оркестрация, обработка ошибок, расширяемость. Составить отчёт с выводами: заимствовать паттерны, использовать как dependency, или не подходит. Добавить строку #22 в сводную таблицу `docs/research/agent-frameworks-summary.md`.

## 2. Context and Scope (Контекст и Границы)

*   **Где делаем:** `docs/research/framework-comparisons/opencode-comparison.md`
*   **Текущее поведение:** В `docs/research/framework-comparisons/` уже есть 21 сравнительный анализ и сводная таблица `agent-frameworks-summary.md` (21 строка)
*   **Важно:** OpenCode — **не** Kilo Code. Это отдельный Go-проект (`opencode-ai/opencode`, 12K+ звёзд). Терминальный AI-agent, не TypeScript-платформа
*   **Границы (Out of Scope):** Не пишем код интеграции — только исследование

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] Исследован OpenCode: README, документация, исходный код (Go)
- [ ] Отчёт `docs/research/framework-comparisons/opencode-comparison.md` по формату существующих comparison-документов
- [ ] Строка #22 добавлена в сводную таблицу `docs/research/agent-frameworks-summary.md`
- [ ] Вердикт: заимствовать паттерны / dependency / не подходит

### 🟡 Should Have (Желательно)
- [ ] Сравнение с нашими паттернами (ExecutionStrategy, DynamicLoop, ChainDefinition)
- [ ] Конкретные рекомендации: что заимствовать, приоритет

### ⚫ Won't Have (Не в этот раз)
- Код интеграции
- Performance-бенчмарки

## 4. Implementation Plan (План реализации)

1. [ ] Изучить репозиторий https://github.com/opencode-ai/opencode: README, исходный код на Go, примеры
2. [ ] Проанализировать по единой методологии: модель оркестрации, state management, error handling, extensibility
3. [ ] Составить отчёт `docs/research/framework-comparisons/opencode-comparison.md`
4. [ ] Добавить строку #22 в `docs/research/agent-frameworks-summary.md`

## 5. Definition of Done (Критерии приёмки)

- [ ] Файл `docs/research/framework-comparisons/opencode-comparison.md` создан
- [ ] Строка OpenCode добавлена в сводную таблицу
- [ ] Вердикт сформулирован

## 6. Risks and Dependencies (Риски и зависимости)

- Проект на Go — требуется понимание архитектуры на уровне структуры пактов, а не деталей реализации

## 7. Sources (Источники)

- https://github.com/opencode-ai/opencode
- Существующие comparison-документы в `docs/research/framework-comparisons/`

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-08 | Тимлид (Алекс) | Создание задачи |
