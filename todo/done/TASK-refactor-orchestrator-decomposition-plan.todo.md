---
type: docs
created: 2026-04-29
value: V3
complexity: C3
priority: P1
depends_on: TASK-refactor-orchestrator-brainstorm-analysis
epic: EPIC-refactor-orchestrator-decomposition
author: Тимлид (Алекс)
assignee: Тимлид (Алекс)
branch: task/orchestrator-decomposition-plan
pr:
status: done
---

# TASK-refactor-orchestrator-decomposition-plan: Оформление плана декомпозиции Orchestrator

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда brainstorm-анализ завершён (10 решений, 16 action items), я хочу оформить результаты в конкретный план декомпозиции с ADR, roadmap и задачами на реализацию, чтобы команда могла начать работу по приоритетам P1 → P2 → P3 без потери контекста.

### Goal (Цель по SMART)
Создать 3 ADR, черновой roadmap в `docs/releases/` и ≥ 5 задач на P1-action items из brainstorm-протокола. Всё — в одном PR, ≤ 600 строк diff.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/adr/`, `docs/releases/`, `todo/`
*   **Контекст:** Brainstorm-протокол `var/sessions/brainstorm/2026-04-29_08-06-49/result.md` — 10 решений, 16 action items
*   **Границы (Out of Scope):**
    *   Не реализуем декомпозицию — только планирование и документация
    *   Не пишем код, кроме ADR и roadmap
    *   Не создаём задачи на P2/P3 — только P1 (будут созданы по мере готовности)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] ADR-006: ExecutionStrategy composition — зафиксировать решение, альтернативы (наследование, tagged union), критерий реализации (conditional branching)
- [x] ADR-007: VO ACL между Orchestrator и AgentRunner — зафиксировать ACL как осознанное решение, порог пересмотра (>3 общих поля или typed I/O)
- [x] ADR-008: Shared Kernel Contract — Shared Kernel = chain identity (name, budget, roles). Strategy-specific data НЕ входит
- [x] Задача TASK-refactor-inline-execute-dynamic-turn — инлайнинг ExecuteDynamicTurnService (P1 action item #1)
- [x] Задача TASK-refactor-prompt-configuration-vo — PromptConfiguration VO (P1 action item #2)
- [x] Задача TASK-refactor-session-writer-consumers — переключение 3 потребителей на ChainSessionWriterInterface (P1 action item #3)

### 🟡 Should Have (Желательно)
- [x] Черновой roadmap в `docs/releases/` на 2 квартала с привязкой conditional branching / parallel execution к спринтам
- [ ] Задачи на P2-action items (ExecutionStrategyInterface, CommandHandler rewrite, Domain-инвентаризация)

### 🟢 Could Have (Опционально)
- [ ] Mermaid-схема будущей структуры (после ExecutionStrategy)

### ⚫ Won't Have (Не будем делать)
- [ ] Реализация декомпозиции
- [ ] Написание кода (кроме документации)
- [ ] Задачи на P3 (RunDynamicLoopService декомпозиция, ChainSessionLogger расщепление, Shared/ переразложение)

## 4. Implementation Plan (План реализации)
1. [x] Делегировать Архитектору Гэндальфу: создание ADR-006, ADR-007, ADR-008
2. [x] Делегировать Аналитику Шерлоку: черновой roadmap в `docs/releases/`
3. [x] Тимлид: создать задачи на P1-action items (#1–3) в `todo/`
4. [x] Обновить эпик: добавить Фазы 3-5, ссылки на новые задачи
5. [x] Самопроверка: все ADR соответствуют формату, задачи соответствуют шаблону

## 5. Definition of Done (Критерии приёмки)
- [x] 3 ADR созданы в `docs/adr/` (ADR-006, ADR-007, ADR-008)
- [x] ≥ 3 задачи на P1-action items созданы в `todo/`
- [x] Эпик обновлён (Фазы 3-5 добавлены, ссылки актуальны)
- [x] Все файлы задач содержат обязательные метаданные и соответствуют шаблону

## 6. Verification (Самопроверка)
*Задача docs-only — PHPUnit/Psalm не требуются.*
- [ ] Все ADR содержат: Context, Decision, Alternatives, Consequences
- [ ] Все задачи содержат: Story, Goal, Context, Requirements (MoSCoW), DoD, Risks

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск 1:** ADR может потребовать итерации с Архитектором Локи (критик) — заложить 1 раунд ревью
- **Зависимость:** Протокол brainstorm `var/sessions/brainstorm/2026-04-29_08-06-49/result.md`

## 8. Sources (Источники)
- [x] [Протокол brainstorm (40 раундов)](../var/sessions/brainstorm/2026-04-29_08-06-49/result.md)
- [x] [Эпик декомпозиции](EPIC-refactor-orchestrator-decomposition.md)
- [ ] [Существующие ADR](../docs/adr/) — для формата
- [ ] [Шаблон задачи](../docs/todo-md/templates/task.md) — для создания задач

## 9. Comments (Комментарии)
Это задача Фазы 2 эпика EPIC-refactor-orchestrator-decomposition. Результат — вход для Фазы 3 (реализация P1-action items).

ADR создаются через сабагента (Архитектор Гэндальф), задачи создаёт Тимлид лично (это координация, не реализация).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-29 | Тимлид (Алекс) | Создание задачи |
