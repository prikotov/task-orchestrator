---
type: research
created: 2026-04-16
value: V2
complexity: C3
priority: P2
depends_on:
epic: EPIC-research-agent-frameworks-comparison
author: Технический писатель (Гермиона)
assignee: Технический писатель (Гермиона)
branch: task/research-charmbracelet-crush
pr:
status: in_progress
---

# TASK-research-charmbracelet-crush: Исследовать и оценить charmbracelet/crush для интеграции

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы выбираем инструменты оркестрации AI-агентов, я хочу оценить архитектуру и подход charmbracelet/crush, чтобы понять стоит ли интегрировать его идеи или код в task-orchestrator.

### Goal (Цель по SMART)
Провести техническое исследование crush: архитектура, модель оркестрации, интерфейс runner'ов, протоколы взаимодействия. Составить отчёт с выводами: заимствовать паттерны, использовать как dependency, или не подходит.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** docs/research/
*   **Текущее поведение:** В docs/research/ уже есть сравнительные анализы (agent-bernstein-comparison.md, agent-orchestrator-comparison.md, superpowers-brainstorming-comparison.md)
*   **Границы (Out of Scope):** Не пишем код интеграции — только исследование

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Изучить репозиторий https://github.com/charmbracelet/crush: архитектуру, стек, модель цепочек
- [ ] Сравнить с нашей моделью (static/dynamic chains, retry, circuit breaker, budget, quality gates)
- [ ] Оформить отчёт в docs/research/crush-comparison.md по формату существующих comparison-документов
- [ ] Заполнить строку(и) для Crush в сводной таблице docs/research/agent-frameworks-summary.md
### 🟡 Should Have (Желательно)
- [ ] Определить конкретные паттерны, которые стоит заимствовать
- [ ] Оценить возможность использования crush как runner'а (аналогично pi)
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- [ ] Написание кода интеграции

## 4. Implementation Plan (План реализации)
1. [ ] Изучить README, архитектуру, исходный код crush
2. [ ] Сравнить с нашей моделью оркестрации
3. [ ] Написать docs/research/crush-comparison.md

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт docs/research/crush-comparison.md создан по формату существующих comparison-документов
- [ ] Содержит чёткий вывод: заимствовать / использовать / не подходит
- [ ] Строка Crush в сводной таблице docs/research/agent-frameworks-summary.md заполнена

## 6. Verification (Самопроверка)
```bash
ls docs/research/crush-comparison.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- Репозиторий может быть в ранней стадии — мало документации

## 8. Sources (Источники)
- https://github.com/charmbracelet/crush

## 9. Comments (Комментарии)
Charmbracelet — авторы Bubbletea, Lip Gloss, VHS. Качество их проектов высокое, подход может быть полезен.

## Инструкции для сабагента

**Твоя роль:** docs/agents/roles/team/technical_writer.ru.md
**Ветка:** task/research-charmbracelet-crush (уже создана и активна)
**PR:** уже создан (draft) из task/research-charmbracelet-crush в task/research-agent-frameworks-comparison — [PR #40](https://github.com/prikotov/task-orchestrator/pull/40)

### Порядок действий
1. Переключись в ветку `task/research-charmbracelet-crush`: `git checkout task/research-charmbracelet-crush`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. Проверок PHPUnit/Psalm не требуется — задача docs-only.
6. Сделай `git push`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-16 | Технический писатель (Гермиона) | Создание задачи |
