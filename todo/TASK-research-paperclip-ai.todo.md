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
branch: task/research-paperclip-ai
pr:
status: done
---

# TASK-research-paperclip-ai: Исследовать Paperclip AI для сравнения с task-orchestrator

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы оцениваем AI-agent фреймворки, я хочу изучить Paperclip AI, чтобы понять его модель оркестрации, подход к агентам и workflow — и сравнить с нашими подходами.

### Goal (Цель по SMART)
Провести техническое исследование Paperclip AI: архитектура, модель агентов, оркестрация, state management, error handling, расширяемость. Составить отчёт с выводами: заимствовать паттерны, использовать как dependency, или не подходит. Добавить строку в сводную таблицу.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** docs/research/
*   **Текущее поведение:** В docs/research/ уже есть сравнительные анализы 14 фреймворков и сводная таблица agent-frameworks-summary.md
*   **Границы (Out of Scope):** Не пишем код интеграции — только исследование

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] Изучить Paperclip AI: архитектуру, модель агентов, workflow-паттерны, tools
- [x] Сравнить с нашей моделью (static/dynamic chains, retry, circuit breaker, budget, quality gates)
- [x] Оформить отчёт в docs/research/paperclip-ai-comparison.md по формату существующих comparison-документов
- [x] Заполнить строку для Paperclip AI в сводной таблице docs/research/agent-frameworks-summary.md
### 🟡 Should Have (Желательно)
- [x] Определить конкретные паттерны, которые стоит заимствовать
- [x] Оценить подход к state management и error handling
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- [ ] Написание кода интеграции

## 4. Implementation Plan (План реализации)
1. Изучить репозиторий https://github.com/paperclipai/paperclip — архитектуру, модель агентов, workflow, tools
2. При недостатке документации — проанализировать исходный код
3. Составить отчёт по формату существующих comparison-документов (см. docs/research/crush-comparison.md как пример)
4. Заполнить строку Paperclip AI (#15) в сводной таблице docs/research/agent-frameworks-summary.md

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт docs/research/paperclip-ai-comparison.md создан по формату существующих comparison-документов
- [x] Содержит чёткий вывод: заимствовать / использовать / не подходит
- [x] Строка Paperclip AI в сводной таблице docs/research/agent-frameworks-summary.md заполнена

## 6. Verification (Самопроверка)
```bash
ls docs/research/paperclip-ai-comparison.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- Проект может быть на ранней стадии — мало документации
- Необходим анализ исходного кода при недостатке документации

## 8. Sources (Источники)
- https://github.com/paperclipai/paperclip

## Инструкции для сабагента

**Ветка:** task/research-paperclip-ai (уже создана и активна)
**PR:** уже создан (draft) из task/research-paperclip-ai в task/research-agent-frameworks-comparison — [PR #95](https://github.com/prikotov/task-orchestrator/pull/95)

### Порядок действий
1. Переключись в ветку `task/research-paperclip-ai`: `git checkout task/research-paperclip-ai`
2. Изучи проект Paperclip AI (https://github.com/paperclipai/paperclip) — архитектуру, модель агентов, workflow-паттерны, tools, state management, error handling, расширяемость.
3. При недостатке документации — анализируй исходный код.
4. Создай отчёт docs/research/paperclip-ai-comparison.md по формату существующих comparison-документов (как docs/research/crush-comparison.md).
5. Заполни строку Paperclip AI (#15) в сводной таблице docs/research/agent-frameworks-summary.md.
6. Следуй [Конвенциям](docs/conventions/index.md) проекта.
7. После реализации запусти проверки: `vendor/bin/phpunit` и `vendor/bin/psalm` (хотя для docs-only они могут быть пропущены — укажи это в отчёте).
8. Сделай `git push`.

## 9. Comments (Комментарии)

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-28 | Тимлид (Алекс) | Создание задачи |
| 2026-04-28 | Технический писатель (Гермиона) | Задача выполнена: создан отчёт paperclip-ai-comparison.md, заполнена строка #15 в сводной таблице, обновлены тренды и рекомендации |
