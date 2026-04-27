---
# Metadata (Метаданные)
type: feat
created: 2026-04-27
value: V3
complexity: C2
priority: P0
depends_on:
epic: EPIC-feat-brainstorm-improvements
author: Тимлид (Алекс) (pi)
assignee:
branch:
pr:
status: todo
---

# TASK-feat-brainstorm-finalize-time-guarantee: Гарантия вызова finalize до истечения max_time

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как пользователь, запустивший brainstorm с `max_time=3600`, я хочу получить финальный синтез (result.md) даже если дискуссия заняла всё отведённое время, чтобы не терять результаты 60-минутной сессии.

### Goal (Цель по SMART)
Изменить `RunDynamicLoopService` так, чтобы перед запуском очередного раунда дискуссии проверялось: хватит ли оставшегося времени на один раунд + finalize (~10-15% от max_time). Если нет — прервать дискуссию и запустить finalize с имеющимися результатами.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `src/Module/Orchestrator/Domain/Service/Chain/Dynamic/RunDynamicLoopService.php` — главный цикл dynamic-сессии
    *   Возможно, `src/Module/Orchestrator/Domain/Service/Chain/Dynamic/DynamicLoopExecution.php` или аналогичный класс, отслеживающий время
*   **Текущее поведение:** Цикл продолжается пока `completed_rounds < max_rounds` и не достигнут `max_time`. Когда `max_time` достигнут — сессия прерывается с `status=interrupted`, finalize не вызывается, `result.md = "Interrupted: timeout"`.
*   **Границы (Out of Scope):**
    *   Не меняем структуру finalize-вызова
    *   Не меняем max_time как параметр CLI
    *   Не добавляем отдельный параметр `finalize_time` (используем фиксированный процент)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Перед каждым новым раундом дискуссии проверяется: `remaining_time > finalize_reserve`. Если нет — дискуссия останавливается, вызывается finalize
- [ ] `finalize_reserve` = 10% от `max_time` (но не менее 60 секунд)
- [ ] Если `max_time` не задан — резервирование не применяется (backward compatible)
- [ ] Unit-тест: при `remaining_time < finalize_reserve` finalize вызывается корректно
### 🟡 Should Have (Желательно)
- [ ] В журнале фасилитатора фиксируется причина остановки: «Дискуссия остановлена: резервирование времени на синтез»
### 🟢 Could Have (Опционально)
- [ ] Параметр `finalize_reserve_percent` конфигурируемый в chains.yaml (default: 10)
### ⚫ Won't Have (Не будем делать)
- [ ] Отдельный таймаут на сам finalize-шаг (уже есть timeout на шаг)

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем перед стартом.*
1. [ ] Изучить `RunDynamicLoopService` — как отслеживается время и где вызывается finalize
2. [ ] Добавить расчёт `finalize_reserve` в начале сессии
3. [ ] Добавить проверку перед запуском нового раунда
4. [ ] Если время исчерпано — установить флаг для вызова finalize вместо interrupt
5. [ ] Написать unit-тесты
6. [ ] Проверить integration-сценарий

## 5. Definition of Done (Критерии приёмки)
- [ ] При `max_time=3600` и заполнении всего времени дискуссией — finalize вызывается, `result.md` содержит протокол
- [ ] При `max_time` не задан — поведение не меняется
- [ ] PHPUnit проходит
- [ ] Psalm проходит

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- Если finalize-шаг сам занимает больше reserve-времени (модель медленная) — он всё равно может упасть по timeout на шаг. Но это лучше, чем interrupt без finalize вообще.
- Нужна аккуратная работа с таймингами: elapsed time vs wall clock — проверить, как сейчас считается

## 8. Sources (Источники)
- [ ] [RunDynamicLoopService](../../src/Module/Orchestrator/Domain/Service/Chain/Dynamic/RunDynamicLoopService.php)
- [ ] [Ретроспектива — проблема P0: таймаут убил синтез](../../var/sessions/brainstorm/2026-04-27_06-46-57/session.json)

## 9. Comments (Комментарии)
Проблема обнаружена при ретроспективе: brainstorm 2026-04-27 длился 64 минуты, потратил все раунды на дискуссию, finalize не успел выполниться. result.md = "Interrupted: timeout". Пользователь (я) вручную вычитывал 200K discussion_history для восстановления результата.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-27 | Тимлид (Алекс) (pi) | Создание задачи |
