---
type: research
created: 2026-04-21
value: V3
complexity: C3
priority: P2
depends_on: []
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee: Технический писатель (Гермиона)
branch: task/research-mastra-ai
pr:
status: done
---

# TASK-research-mastra-ai: Исследовать Mastra AI для сравнения с task-orchestrator

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы оцениваем AI-agent фреймворки, я хочу изучить Mastra AI, чтобы понять его модель оркестрации агентов, workflow-подход, интеграцию с LLM-провайдерами — и сравнить с нашими подходами.

### Goal (Цель по SMART)
Провести техническое исследование Mastra AI: архитектура, модель агентов и workflows, работа с памятью, инструментарий (tools), RAG, расширяемость. Составить отчёт с выводами: заимствовать паттерны, использовать как dependency, или не подходит.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** docs/research/
*   **Текущее поведение:** В docs/research/ уже есть сравнительные анализы (agent-bernstein-comparison.md, agent-orchestrator-comparison.md, superpowers-brainstorming-comparison.md)
*   **Границы (Out of Scope):** Не пишем код интеграции — только исследование

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Изучить Mastra AI: архитектуру, TypeScript-first подход, модель агентов, workflows, инструменты (tools)
- [ ] Изучить систему workflows: step-based, branching, conditional, parallel execution
- [ ] Сравнить с нашей моделью (static/dynamic chains, retry, circuit breaker, budget, quality gates)
- [ ] Оформить отчёт в docs/research/mastra-ai-comparison.md по формату существующих comparison-документов
- [ ] Заполнить строку для Mastra AI в сводной таблице docs/research/agent-frameworks-summary.md
### 🟡 Should Have (Желательно)
- [ ] Определить конкретные паттерны, которые стоит заимствовать
- [ ] Оценить подход Mastra к memory, RAG, eval и интеграции с LLM-провайдерами
- [ ] Сравнить TypeScript-first подход с нашим PHP/Symfony подходом
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- [ ] Написание кода интеграции

## 4. Implementation Plan (План реализации)
1. [ ] Изучить репозиторий https://github.com/mastra-inc/mastra и документацию https://mastra.ai
2. [ ] Сравнить модель workflows и оркестрации с нашей
3. [ ] Написать docs/research/mastra-ai-comparison.md

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт docs/research/mastra-ai-comparison.md создан по формату существующих comparison-документов
- [ ] Содержит чёткий вывод: заимствовать / использовать / не подходит
- [ ] Строка Mastra AI в сводной таблице docs/research/agent-frameworks-summary.md заполнена

## 6. Verification (Самопроверка)
```bash
ls docs/research/mastra-ai-comparison.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- Mastra активно развивается — архитектура и API могут меняться
- TypeScript/JavaScript-экосистема — оценка применимости паттернов в PHP требует аккуратности

## 8. Sources (Источники)
- https://mastra.ai
- https://github.com/mastra-inc/mastra
- https://mastra.ai/docs

## 9. Comments (Комментарии)
Mastra AI — TypeScript-фреймворк для построения AI-агентов и workflows. Интересен step-based workflow-моделью, встроенной памятью, RAG, eval-фреймворком и сильной интеграцией с LLM-провайдерами. TypeScript-first подход отличает его от Python-конкурентов (CrewAI, LangGraph, AutoGen), но паттерны оркестрации универсальны и могут быть заимствованы.

## Инструкции для сабагента

**Твоя роль:** docs/agents/roles/team/technical_writer.ru.md
**Ветка:** task/research-mastra-ai (уже создана и активна)
**PR:** будет создан после коммита

### Порядок действий
1. Переключись в ветку `task/research-mastra-ai`: `git checkout task/research-mastra-ai`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. Проверок PHPUnit/Psalm не требуется — задача docs-only.
6. Сделай `git push`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-21 | Тимлид (Алекс) | Создание задачи |
