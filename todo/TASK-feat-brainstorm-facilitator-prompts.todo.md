---
# Metadata (Метаданные)
type: feat
created: 2026-04-27
value: V2
complexity: C1
priority: P1
depends_on:
epic: EPIC-feat-brainstorm-improvements
author: Тимлид (Алекс) (pi)
assignee: Бэкендер Тони (pi)
branch: task/brainstorm-facilitator-prompts
pr:
status: in_progress
---

# TASK-feat-brainstorm-facilitator-prompts: Consensus call и декомпозиция темы для фасилитатора

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как фасилитатор brainstorm, я хочу уметь фиксировать консенсус по подвопросу и переходить к следующему, а также декомпозировать тему на подвопросы в начале сессии, чтобы обсуждение было структурированным и не тратило раунды на вторичные споры.

### Goal (Цель по SMART)
Обновить два промпта фасилитатора: (1) `facilitator_append.txt` — добавить правило consensus call, (2) `facilitator_start.txt` — добавить инструкцию декомпозировать тему на 2-4 подвопроса.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `config/prompts/brainstorm/facilitator_append.txt` — основные правила фасилитатора
    *   `config/prompts/brainstorm/facilitator_start.txt` — первый шаг сессии
*   **Текущее поведение:**
    *   `facilitator_append.txt` содержит 9 правил маршрутизации + формат JSON-ответа. Нет механизма консенсуса — фасилитатор продолжает спор до исчерпания раундов.
    *   `facilitator_start.txt` содержит инструкцию задать провокационный тон и выбрать первого участника. Нет декомпозиции темы.
*   **Границы (Out of Scope):**
    *   Не меняем `facilitator_continue.txt` (шаблон продолжения — уже работает)
    *   Не меняем `facilitator_finalize.txt` (шаблон синтеза — уже работает)
    *   Не добавляем программный контроль консенсуса (fasilitator сам определяет)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] В `facilitator_append.txt` добавлено правило consensus call:
  > «Если ты видишь, что по текущему подвопросу достигнут консенсус (все ключевые участники согласны или приняли компромисс) — зафиксируй его кратко и переходи к следующему подвопросу или к синтезу. НЕ продолжай спор ради спора.»
- [ ] В `facilitator_start.txt` добавлена инструкция декомпозиции:
  > «Перед выбором первого участника — декомпозируй тему на 2-4 подвопроса и двигайся по ним последовательно. Сначала реши главный вопрос, потом детали.»
### 🟡 Should Have (Желательно)
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- [ ] Программная проверка консенсуса
- [ ] Отдельный шаг на декомпозицию (декомпозиция — часть первого шага фасилитатора)

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем перед стартом.*
1. [ ] Прочитать текущие `facilitator_append.txt` и `facilitator_start.txt`
2. [ ] Добавить правило consensus call в `facilitator_append.txt` после существующих правил
3. [ ] Добавить инструкцию декомпозиции в `facilitator_start.txt` перед инструкцией о provocation
4. [ ] Проверить, что JSON-формат ответа не нарушен

## 5. Definition of Done (Критерии приёмки)
- [ ] `facilitator_append.txt` содержит правило consensus call
- [ ] `facilitator_start.txt` содержит инструкцию декомпозиции
- [ ] Существующие тесты проходят

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- Фасилитатор может «переоценить» консенсус и завершить спор раньше времени — но это лучше, чем 5 раундов на вторичный вопрос
- Декомпозиция может отнять часть первого шага — но это инвестиция в структуру всей сессии

## 8. Sources (Источники)
- [ ] [facilitator_append.txt](../../config/prompts/brainstorm/facilitator_append.txt)
- [ ] [facilitator_start.txt](../../config/prompts/brainstorm/facilitator_start.txt)
- [ ] [Ретроспектива — P4: нет consensus call, P5: нет декомпозиции темы](../../var/sessions/brainstorm/2026-04-27_06-46-57/discussion_history.md)

## 9. Comments (Комментарии)
Проблема: в brainstorm 2026-04-27 раунды 9-13 (5 раундов из 13) ушли на спор о формате документа (plan в ADR vs Playbook) — вторичный вопрос, где консенсус был достигнут на раунде 8.

## Инструкции для сабагента

**Ветка:** `task/brainstorm-facilitator-prompts` (уже создана и активна)
**PR:** уже создан (draft) из `task/brainstorm-facilitator-prompts` в `feat/brainstorm-improvements`

### Порядок действий
1. Переключись в ветку `task/brainstorm-facilitator-prompts`: `git checkout task/brainstorm-facilitator-prompts`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](../../docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `vendor/bin/phpunit` и `vendor/bin/psalm`.
6. Сделай `git push`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-27 | Тимлид (Алекс) (pi) | Создание задачи |
