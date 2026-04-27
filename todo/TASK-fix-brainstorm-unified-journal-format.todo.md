---
# Metadata (Метаданные)
type: fix
created: 2026-04-27
value: V1
complexity: C0
priority: P2
depends_on:
epic: EPIC-fix-brainstorm-session-bugs
author: Тимлид (Алекс) (pi)
assignee: Бэкендер (Тони) (pi)
branch: task/fix-brainstorm-unified-journal-format
pr:
status: in_progress
---

# TASK-fix-brainstorm-unified-journal-format: Унификация формата заголовка секции файлов в промптах

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как разработчик brainstorm-модуля, я хочу чтобы формат секции файлов в промптах фасилитатора и участника был унифицирован, чтобы не было двух разных заголовков для одной и той же сущности.

### Goal (Цель по SMART)
Унифицировать заголовок секции файлов выступлений в промптах: одинаковый заголовок в `participant_user.txt` и `facilitator_continue.txt`.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `config/prompts/brainstorm/participant_user.txt` и `config/prompts/brainstorm/facilitator_continue.txt`
*   **Текущее поведение:**
    *   `participant_user.txt`: `# Выступления предыдущих участников (файлы):`
    *   `facilitator_continue.txt`: `Выступления участников (файлы — прочитай их):`
    *   Два разных заголовка для одного и того же списка файлов
*   **Границы (Out of Scope):**
    *   Не меняем `facilitator_start.txt` (там нет секции файлов)
    *   Не меняем `facilitator_finalize.txt`
    *   Не меняем PHP-код

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Заголовок секции файлов одинаковый в обоих промптах
- [ ] Формат: `Выступления предыдущих участников (файлы — прочитай их):` (более информативный вариант)

### 🟡 Should Have (Желательно)
- [ ] Без дополнительных изменений

### ⚫ Won't Have (Не будем делать)
- [ ] Изменение структуры промптов

## 4. Implementation Plan (План реализации)
1. [ ] Обновить заголовок в `participant_user.txt`: заменить `# Выступления предыдущих участников (файлы):` на `Выступления предыдущих участников (файлы — прочитай их):`
2. [ ] Проверить, что `PromptFormatterService::buildParticipantUserPrompt()` regex по-прежнему корректно удаляет секцию при `hasPreviousResponses === false` (может потребоваться обновление regex — зависит от задачи TASK-fix-brainstorm-empty-participant-section)

## 5. Definition of Done (Критерии приёмки)
- [ ] Оба промпта содержат одинаковый заголовок секции файлов
- [ ] PHPUnit проходит (regex в PromptFormatterService не сломан)

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Infrastructure/Service/Chain/PromptFormatterServiceTest.php
```

## 7. Risks and Dependencies (Риски и зависимости)
- ⚠️ Зависимость от TASK-fix-brainstorm-empty-participant-section: если regex в PromptFormatterService обновляется в той задаче, нужно убедиться, что новый заголовок тоже удаляется корректно. Рекомендуется выполнять **после** TASK-fix-brainstorm-empty-participant-section

## 8. Sources (Источники)
- [ ] [participant_user.txt](../config/prompts/brainstorm/participant_user.txt)
- [ ] [facilitator_continue.txt](../config/prompts/brainstorm/facilitator_continue.txt)

## 9. Comments (Комментарии)
Формат в `facilitator_continue.txt` («файлы — прочитай их») более информативен — он даёт прямую инструкцию агенту. Убираем `#` из заголовка participant, чтобы он совпадал по стилю с facilitator (без Markdown-заголовка).

## Инструкции для сабагента

**Ветка:** task/fix-brainstorm-unified-journal-format (уже создана и активна)
**PR:** уже создан (draft) из task/fix-brainstorm-unified-journal-format в fix/epic-brainstorm-session-bugs — будет заполнен после создания

### Порядок действий
1. Переключись в ветку `task/fix-brainstorm-unified-journal-format`: `git checkout task/fix-brainstorm-unified-journal-format`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](../docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `vendor/bin/phpunit`.
6. Сделай `git push`.
7. Переведи PR из draft в ready: `gh pr ready <PR_NUMBER>`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-27 | Тимлид (Алекс) (pi) | Создание задачи |
