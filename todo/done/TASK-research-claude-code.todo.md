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
branch: task/research-claude-code
pr: https://github.com/prikotov/task-orchestrator/pull/47
status: in_progress
---

# TASK-research-claude-code: Исследовать и оценить Claude Code для сравнения с task-orchestrator

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы выбираем подходы к AI-agent orchestration, я хочу оценить архитектуру и модель Claude Code (Anthropic), чтобы понять какие идеи и паттерны стоит заимствовать в task-orchestrator, а от каких отказаться.

### Goal (Цель по SMART)
Провести техническое исследование Claude Code: архитектура агентного цикла, модель инструментов (tool use), контекст-менеджмент, обработка ошибок, retry-механизмы. Составить отчёт с выводами: заимствовать паттерны, использовать как dependency, или не подходит.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** docs/research/
*   **Текущее поведение:** В docs/research/ уже есть сравнительные анализы (agent-bernstein-comparison.md, agent-orchestrator-comparison.md, superpowers-brainstorming-comparison.md)
*   **Границы (Out of Scope):** Не пишем код интеграции — только исследование

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Изучить Claude Code: архитектуру агентного цикла, tool use protocol, контекст-менеджмент
- [ ] Сравнить с нашей моделью (static/dynamic chains, retry, circuit breaker, budget, quality gates)
- [ ] Оформить отчёт в docs/research/claude-code-comparison.md по формату существующих comparison-документов
- [ ] Заполнить строку для Claude Code в сводной таблице docs/research/agent-frameworks-summary.md
### 🟡 Should Have (Желательно)
- [ ] Определить конкретные паттерны, которые стоит заимствовать
- [ ] Оценить подходы к retry/fallback и обработке ошибок
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- [ ] Написание кода интеграции

## 4. Implementation Plan (План реализации)
1. [ ] Изучить документацию Anthropic: Claude Code agent loop, tool use, system prompts
2. [ ] Изучить открытые материалы: статьи, talks, API reference
3. [ ] Сравнить с нашей моделью оркестрации
4. [ ] Написать docs/research/claude-code-comparison.md

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт docs/research/claude-code-comparison.md создан по формату существующих comparison-документов
- [ ] Содержит чёткий вывод: заимствовать / использовать / не подходит
- [ ] Строка Claude Code в сводной таблице docs/research/agent-frameworks-summary.md заполнена

## 6. Verification (Самопроверка)
```bash
ls docs/research/claude-code-comparison.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- Claude Code — проприетарный продукт, исходный код закрыт — анализ по документации и наблюдаемому поведению

## 8. Sources (Источники)
- https://docs.anthropic.com/en/docs/claude-code
- https://www.anthropic.com/engineering/claude-code-best-practices

## 9. Comments (Комментарии)
Claude Code — один из ведущих coding-агентов. Их подход к agent loop (agentic coding) и tool use может дать полезные паттерны для chain-оркестрации.

## Инструкции для сабагента

**Твоя роль:** docs/agents/roles/team/technical_writer.ru.md
**Ветка:** task/research-claude-code (уже создана и активна)
**PR:** будет создан после коммита

### Порядок действий
1. Переключись в ветку `task/research-claude-code`: `git checkout task/research-claude-code`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты.
5. Проверок PHPUnit/Psalm не требуется — задача docs-only.
6. Сделай `git push`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-20 | Тимлид (Алекс) | Создание задачи |
