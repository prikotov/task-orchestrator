---
type: research
created: 2026-04-20
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

# TASK-research-metagpt-openclaw: Исследовать MetaGPT и OpenClaw для сравнения с task-orchestrator

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы оцениваем подходы к multi-agent оркестрации с ролевыми моделями и SOP (Standard Operating Procedures), я хочу изучить MetaGPT и OpenClaw, чтобы понять их модель распределения ролей, координации, стандартизации процессов — и сравнить с нашими подходами.

### Goal (Цель по SMART)
Провести техническое исследование MetaGPT и OpenClaw: архитектура, ролевая модель, SOP-подход, multi-agent координация. Составить отчёт с выводами: заимствовать паттерны, использовать как dependency, или не подходит.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** docs/research/
*   **Текущее поведение:** В docs/research/ уже есть сравнительные анализы (agent-bernstein-comparison.md, agent-orchestrator-comparison.md, superpowers-brainstorming-comparison.md)
*   **Границы (Out of Scope):** Не пишем код интеграции — только исследование

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Изучить MetaGPT (FoundationAgents): архитектуру, ролевую модель (ProductManager, Architect, Engineer), SOP, message passing
- [ ] Изучить OpenClaw (openclaw): архитектуру, подход к multi-agent координации, модель оркестрации
- [ ] Сравнить оба продукта с нашей моделью (static/dynamic chains, retry, circuit breaker, budget, quality gates)
- [ ] Оформить отчёт в docs/research/metagpt-openclaw-comparison.md по формату существующих comparison-документов
- [ ] Заполнить строки для MetaGPT и OpenClaw в сводной таблице docs/research/agent-frameworks-summary.md
### 🟡 Should Have (Желательно)
- [ ] Определить конкретные паттерны SOP и ролевой координации, которые стоит заимствовать
- [ ] Оценить подход к стандартизации output между агентами (shared context, message passing)
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- [ ] Написание кода интеграции

## 4. Implementation Plan (План реализации)
1. [ ] Изучить репозитории: https://github.com/FoundationAgents/MetaGPT, https://github.com/openclaw/openclaw
2. [ ] Сравнить модели оркестрации между собой и с нашей
3. [ ] Написать docs/research/metagpt-openclaw-comparison.md

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт docs/research/metagpt-openclaw-comparison.md создан по формату существующих comparison-документов
- [ ] Содержит чёткий вывод: заимствовать / использовать / не подходит
- [ ] Строки MetaGPT и OpenClaw в сводной таблице docs/research/agent-frameworks-summary.md заполнены

## 6. Verification (Самопроверка)
```bash
ls docs/research/metagpt-openclaw-comparison.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- MetaGPT — крупный проект, может потребоваться значительное время на анализ
- OpenClaw может быть в ранней стадии — мало документации

## 8. Sources (Источники)
- https://github.com/FoundationAgents/MetaGPT
- https://github.com/openclaw/openclaw

## 9. Comments (Комментарии)
MetaGPT интересен SOP-подходом — стандартные операционные процедуры для AI-агентов с ролевой моделью. Это близко к нашим chain-определениям с facilitator/participants. OpenClaw — менее известный проект, стоит проверить его уникальность.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-20 | Тимлид (Алекс) | Создание задачи |
