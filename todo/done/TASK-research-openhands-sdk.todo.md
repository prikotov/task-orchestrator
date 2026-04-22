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
branch: task/research-openhands-sdk
pr: https://github.com/prikotov/task-orchestrator/pull/43
status: done
---

# TASK-research-openhands-sdk: Исследовать OpenHands SDK для сравнения с task-orchestrator

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы оцениваем open-source AI-agent фреймворки, я хочу изучить OpenHands SDK, чтобы понять его модель оркестрации агентов, SDK-подход, расширяемость — и сравнить с нашими подходами.

### Goal (Цель по SMART)
Провести техническое исследование OpenHands SDK: архитектура, модель агентов, action/observation протокол, контейнеризация, расширяемость через SDK. Составить отчёт с выводами: заимствовать паттерны, использовать как dependency, или не подходит.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** docs/research/
*   **Текущее поведение:** В docs/research/ уже есть сравнительные анализы (agent-bernstein-comparison.md, agent-orchestrator-comparison.md, superpowers-brainstorming-comparison.md)
*   **Границы (Out of Scope):** Не пишем код интеграции — только исследование

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] Изучить OpenHands SDK: архитектуру, action/observation протокол, модель агентов, sandboxing
- [x] Сравнить с нашей моделью (static/dynamic chains, retry, circuit breaker, budget, quality gates)
- [x] Оформить отчёт в docs/research/openhands-sdk-comparison.md по формату существующих comparison-документов
- [x] Заполнить строку для OpenHands SDK в сводной таблице docs/research/agent-frameworks-summary.md
### 🟡 Should Have (Желательно)
- [x] Определить конкретные паттерны, которые стоит заимствовать
- [x] Оценить SDK-подход к расширяемости и плагинной архитектуре
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- [ ] Написание кода интеграции

## 4. Implementation Plan (План реализации)
1. [x] Изучить репозиторий https://github.com/All-Hands-AI/OpenHands: архитектуру, SDK, исходный код
2. [x] Сравнить с нашей моделью оркестрации
3. [x] Написать docs/research/openhands-sdk-comparison.md

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт docs/research/openhands-sdk-comparison.md создан по формату существующих comparison-документов
- [x] Содержит чёткий вывод: заимствовать / использовать / не подходит
- [x] Строка OpenHands SDK в сводной таблице docs/research/agent-frameworks-summary.md заполнена

## 6. Verification (Самопроверка)
```bash
ls docs/research/openhands-sdk-comparison.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- OpenHands активно развивается — архитектура может меняться

## 8. Sources (Источники)
- https://github.com/All-Hands-AI/OpenHands
- https://docs.all-hands.dev/

## 9. Comments (Комментарии)
OpenHands — один из самых активных open-source AI-agent проектов. Их action/observation протокол и SDK-подход к расширяемости могут быть полезны для нашей архитектуры runner'ов и chain-оркестрации.

## Инструкции для сабагента

**Твоя роль:** docs/agents/roles/team/technical_writer_hermione.ru.md
**Ветка:** task/research-openhands-sdk (уже создана и активна)
**PR:** будет создан после коммита

### Порядок действий
1. Переключись в ветку `task/research-openhands-sdk`: `git checkout task/research-openhands-sdk`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. Проверок PHPUnit/Psalm не требуется — задача docs-only.
6. Сделай `git push`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-20 | Тимлид (Алекс) | Создание задачи |
