---
# Metadata (Метаданные)
type: fix
created: 2026-04-27
value: V3
complexity: C0
priority: P0
depends_on:
epic: EPIC-fix-brainstorm-session-bugs
author: Тимлид (Алекс) (pi)
assignee: Бэкендер (Левша) (pi)
branch:
pr:
status: todo
---

# TASK-fix-brainstorm-empty-participant-section: Удаление пустой секции «Выступления предыдущих участников» в первом раунде

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как участник brainstorm-сессии, я хочу чтобы в первом раунде мой промпт не содержал пустую секцию «Выступления предыдущих участников (файлы):» и рекомендательную строку «Рекомендуется начать с последних файлов...», чтобы не путаться при ответе.

### Goal (Цель по SMART)
Исправить regex в `PromptFormatterService::buildParticipantUserPrompt()`: когда `hasPreviousResponses === false`, удалять весь блок — заголовок, пустой список файлов и рекомендательную строку из шаблона `participant_user.txt`.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/Orchestrator/Infrastructure/Service/Chain/PromptFormatterService.php` (строка 73, метод `buildParticipantUserPrompt`)
*   **Шаблон:** `config/prompts/brainstorm/participant_user.txt`:
  ```
  # Тема:
  %s

  # Выступления предыдущих участников (файлы):
  %s

  Рекомендуется начать с последних файлов — в них самая актуальная позиция и свежие аргументы. Более ранние выступления читай по необходимости.
  ```
*   **Текущее поведение:** regex `/\n*# Выступления предыдущих участников.*?:\s*$/s` удаляет только строку заголовка, но **не** рекомендательную строку после него. Итог: участник видит бессмысленный текст «Рекомендуется начать с последних файлов...» без файлов.
*   **Границы (Out of Scope):** Не меняем шаблон промпта (это задача TASK-fix-brainstorm-unified-journal-format)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Когда `hasPreviousResponses === false`, промпт НЕ содержит строку «Рекомендуется начать с последних файлов...»
- [ ] Когда `hasPreviousResponses === false`, промпт НЕ содержит строку «Выступления предыдущих участников»
- [ ] Когда `hasPreviousResponses === true`, промпт содержит оба элемента (заголовок + рекомендация + список файлов)
- [ ] Существующие тесты не ломаются

### 🟡 Should Have (Желательно)
- [ ] Unit-тест проверяет отсутствие рекомендательной строки при `hasPreviousResponses === false`

### ⚫ Won't Have (Не будем делать)
- [ ] Изменение шаблона `participant_user.txt`

## 4. Implementation Plan (План реализации)
1. [ ] Обновить regex в `buildParticipantUserPrompt()` — расширить для удаления всего блока: заголовок + рекомендательная строка
2. [ ] Обновить существующий тест `buildParticipantUserPromptRemovesSectionWhenNoPreviousResponses` — проверить отсутствие рекомендательной строки
3. [ ] Добавить тест: при `hasPreviousResponses === true` рекомендательная строка присутствует

## 5. Definition of Done (Критерии приёмки)
- [ ] При `hasPreviousResponses === false` в промпте нет «Рекомендуется начать» и «Выступления предыдущих»
- [ ] При `hasPreviousResponses === true` в промпте есть обе строки
- [ ] PHPUnit проходит

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Infrastructure/Service/Chain/PromptFormatterServiceTest.php
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- Regex может быть слишком жадным — нужно убедиться, что он не удаляет лишнее

## 8. Sources (Источники)
- [ ] [PromptFormatterService](../src/Module/Orchestrator/Infrastructure/Service/Chain/PromptFormatterService.php)
- [ ] [participant_user.txt](../config/prompts/brainstorm/participant_user.txt)
- [ ] [Существующие тесты](../tests/Unit/Infrastructure/Service/Chain/PromptFormatterServiceTest.php)
- [ ] [step_002_round_001 — пример проблемного промпта](../var/sessions/brainstorm/2026-04-27_12-29-03/step_002_round_001_system_architect_gandalf_3_user.md)

## 9. Comments (Комментарии)
Пример проблемного вывода (раунд 1, step_002):
```
# Выступления предыдущих участников (файлы):


Рекомендуется начать с последних файлов — в них самая актуальная позиция и свежие аргументы. Более ранние выступления читай по необходимости.
```

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-27 | Тимлид (Алекс) (pi) | Создание задачи |
