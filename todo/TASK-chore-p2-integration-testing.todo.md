---
type: chore
created: 2026-04-30
value: V2
complexity: C1
priority: P2
depends_on: TASK-refactor-execution-strategy
epic: EPIC-refactor-orchestrator-p3
author: pi
assignee: Тестировщик Бэка Хаус
branch: task/chore-p2-integration-testing
pr:
status: in_progress
---

# TASK-chore-p2-integration-testing: Интеграционное тестирование Strategy pattern end-to-end

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда ExecutionStrategy pattern реализован (P2), а следующие спринты зависят от его стабильности, я хочу провести end-to-end интеграционное тестирование, чтобы убедиться, что Static и Dynamic стратегии работают через CommandHandler корректно во всех сценариях.

### Goal (Цель по SMART)
Создать интеграционные тесты, проверяющие полный цикл: YAML-конфигурация → CommandHandler → Strategy → Domain-сервисы → результат. Покрыть: static chain, dynamic chain, resume, error scenarios.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `tests/Integration/`
*   **Текущее поведение:** Unit-тесты есть, интеграционное покрытие Strategy pattern — нет
*   **Границы (Out of Scope):**
    *   Не меняем production-код
    *   Conditional branching (Sprint 8)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Integration-тест: static chain end-to-end (YAML → result)
- [ ] Integration-тест: dynamic chain end-to-end (YAML → result)
- [ ] Integration-тест: resume dynamic chain

### 🟡 Should Have (Желательно)
- [ ] Integration-тест: error scenario (agent fail, retry)

### ⚫ Won't Have (Не будем делать)
- [ ] Conditional branching тесты

## 5. Definition of Done (Критерии приёмки)
- [ ] ≥3 integration-теста проходят
- [ ] `vendor/bin/phpunit` — зелёный
- [ ] Обновить Roadmap: статус «Интеграционное тестирование P2» `📋` → `✅ Done`, добавить ссылку на задачу и PR

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Integration/
```

## 7. Risks and Dependencies (Риски и зависимости)
- Зависимость: ExecutionStrategyInterface (Sprint 3) ✅

## 8. Sources (Источники)
- [ ] [Roadmap Sprint 5](../../docs/releases/ROADMAP-2026-Q2-Q3.md)

## 9. Comments (Комментарии)
Roadmap Sprint 5 (буферный). Буфер для завершения P2 + валидация перед P3.

## Инструкции для сабагента

**Ветка:** task/chore-p2-integration-testing (уже создана и активна)
**PR:** уже создан (draft) из task/chore-p2-integration-testing в task/epic-refactor-orchestrator-p3 — [PR #113](https://github.com/prikotov/task-orchestrator/pull/113)

### Порядок действий
1. Переключись в ветку `task/chore-p2-integration-testing`: `git checkout task/chore-p2-integration-testing`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `make check`.
6. Сделай `git push`.
7. Переведи PR из draft в ready: `gh pr ready <PR_NUMBER>`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-30 | pi | Создание задачи |

