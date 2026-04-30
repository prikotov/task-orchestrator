---
type: refactor
created: 2026-04-29
value: V3
complexity: C1
priority: P1
depends_on:
epic: EPIC-refactor-orchestrator-decomposition
author: Тимлид (Алекс)
assignee: Бэкендер (Левша)
branch: task/inline-execute-dynamic-turn
pr: https://github.com/prikotov/task-orchestrator/pull/101
status: done
---

# TASK-refactor-inline-execute-dynamic-turn: Инлайнинг ExecuteDynamicTurnService в RunDynamicLoopService

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда RunDynamicLoopService вызывает ExecuteDynamicTurnService как промежуточный слой (service sandwich), я хочу инлайнить его методы как private-методы, чтобы уменьшить вложенность call chain с 7 до 5 уровней и устранить класс без собственной ответственности.

### Goal (Цель по SMART)
Удалить `ExecuteDynamicTurnService` (308 строк). Три его метода переедут как private-методы в `RunDynamicLoopService`. Blast radius: 1 файл удалён, 1 файл расширен (+~90 строк), 0 интерфейсных изменений.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/Orchestrator/Domain/Service/Chain/Dynamic/`
*   **Текущее поведение:** `ExecuteDynamicTurnService` — Domain-сервис между двумя другими сервисами, добавляющий один `implode()`. Вызывается только из `RunDynamicLoopService`.
*   **Границы (Out of Scope):**
    *   Не трогаем RunDynamicLoopService декомпозицию (отдельная P3 задача)
    *   Не трогаем DynamicTurnResultVo (P3 задача)
    *   Не меняем публичный API и CLI

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Файл `ExecuteDynamicTurnService` удалён
- [ ] Методы `ExecuteDynamicTurnService` перенесены как private-методы в `RunDynamicLoopService`
- [ ] Все unit-тесты `ExecuteDynamicTurnService` перенесены/адаптированы для `RunDynamicLoopService`
- [ ] `vendor/bin/phpunit` — все тесты проходят
- [ ] `vendor/bin/psalm` — без ошибок

### 🟡 Should Have (Желательно)
- [ ] Вложенность call chain задокументирована (было 7 → стало 5)

### ⚫ Won't Have (Не будем делать)
- [ ] Рефакторинг RunDynamicLoopService
- [ ] Изменение DynamicTurnResultVo

## 4. Implementation Plan (План реализации)
1. [ ] Изучить `ExecuteDynamicTurnService`: все методы, зависимости, вызовы
2. [ ] Перенести методы как private-методы в `RunDynamicLoopService`
3. [ ] Обновить DI-конфигурацию (убрать `ExecuteDynamicTurnService`)
4. [ ] Перенести/адаптировать unit-тесты
5. [ ] Удалить файл `ExecuteDynamicTurnService`
6. [ ] Запустить проверки: phpunit, psalm, phpcs

## 5. Definition of Done (Критерии приёмки)
- [ ] `ExecuteDynamicTurnService` удалён из кодовой базы
- [ ] Все методы перенесены в `RunDynamicLoopService` как private
- [ ] Все тесты проходят (`vendor/bin/phpunit`, `vendor/bin/psalm`)
- [ ] Вложенность call chain уменьшена

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
php vendor/prikotov/coding-standard/bin/run-sniff-tests.php
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск 1:** Методы `ExecuteDynamicTurnService` могут иметь зависимости, которые не инжектированы в `RunDynamicLoopService` — добавить в конструктор
- **Зависимость:** ADR-006 (ExecutionStrategy) — задача независима, но результат может упростить будущую DynamicExecutionStrategy

## 8. Sources (Источники)
- [ ] [Протокол brainstorm (решение 3)](../var/sessions/brainstorm/2026-04-29_08-06-49/result.md)
- [ ] [ADR-006: ExecutionStrategy](../docs/adr/006-execution-strategy-composition.md)

## 9. Comments (Комментарии)
Brainstorm-раунд 11: Левша обнаружил «service sandwich» — Domain-сервис между двумя другими, добавляющий один `implode()`. Консенсус всех 4 участников.

Action item #1 из brainstorm-протокола. P1 — выполняется первым.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-29 | Тимлид (Алекс) | Создание задачи |
