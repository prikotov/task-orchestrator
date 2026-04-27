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

# TASK-feat-brainstorm-human-readable-protocol: Человекочитаемый формат discussion_history для фасилитатора

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как пользователь, открывший `discussion_history.md` после brainstorm, я хочу видеть человекочитаемый протокол обсуждения, а не JSON-блоки вида `{"next_role": "...", "challenge": "..."}`, чтобы понимать ход дискуссии без декодирования JSON.

### Goal (Цель по SMART)
Изменить `FormatDynamicJournalService::formatDiscussionEntry()` так, чтобы для фасилитатора в discussion_history записывался человекочитаемый текст (например: «Дал слово Локи. Вызов: если Гэндальф видит 5 групп...»), а не сырой JSON-ответ. Для участников формат остаётся без изменений.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `src/Module/Orchestrator/Domain/Service/Chain/Dynamic/FormatDynamicJournalService.php` — метод `formatDiscussionEntry()`
    *   `src/Module/Orchestrator/Domain/Service/Chain/Dynamic/FormatDynamicJournalServiceInterface.php` — возможно, расширение сигнатуры
    *   `src/Module/Orchestrator/Domain/Service/Chain/Dynamic/RunDynamicLoopService.php` — вызов `formatDiscussionEntry()` для фасилитатора (строки ~168-172)
*   **Текущее поведение:** `formatDiscussionEntry($role, $outputText)` вставляет `getOutputText()` как есть: для фасилитатора это JSON вида `{"next_role":"...","challenge":"..."}`
*   **Границы (Out of Scope):**
    *   Не меняем формат ответа фасилитатора (он остаётся JSON — это контракт с `FacilitatorResponse`)
    *   Не меняем формат discussion_history для участников (они пишут текст — и это правильно)
    *   Не меняем `FacilitatorResponseParser` — он парсит JSON из ответа, не из discussion_history

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Для фасилитатора `formatDiscussionEntry()` формирует человекочитаемую запись: роль + краткое описание действия (дал слово / завершил обсуждение)
- [ ] Для участников формат остаётся без изменений (текущий behaviour)
- [ ] Существующие тесты не ломаются
### 🟡 Should Have (Желательно)
- [ ] Метод различает фасилитатора и участника по параметру (is_facilitator или separate method)
### 🟢 Could Have (Опционально)
- [ ] Человекочитаемая запись включает challenge (вызов) в виде цитаты
### ⚫ Won't Have (Не будем делать)
- [ ] Полная транскрипция JSON в prose-формат

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем перед стартом.*
1. [ ] Расширить `formatDiscussionEntry()` или добавить `formatFacilitatorDiscussionEntry()` в интерфейс и реализацию
2. [ ] В `RunDynamicLoopService::executeFacilitatorTurn()` вызывать новый метод для фасилитатора
3. [ ] Написать unit-тесты на новый формат
4. [ ] Проверить, что существующие тесты проходят

## 5. Definition of Done (Критерии приёмки)
- [ ] `discussion_history.md` после brainstorm содержит человекочитаемые записи фасилитатора
- [ ] Записи участников не изменились
- [ ] PHPUnit проходит
- [ ] Psalm проходит

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- `formatDiscussionEntry()` используется в двух местах (фасилитатор и участник) — нужно аккуратно разделить логику, не ломая participant-вызовы
- `FacilitatorResponse` парсит JSON из ответа агента, не из discussion_history — изменение discussion_history не влияет на парсинг

## 8. Sources (Источники)
- [ ] [FormatDynamicJournalService](../../src/Module/Orchestrator/Domain/Service/Chain/Dynamic/FormatDynamicJournalService.php)
- [ ] [RunDynamicLoopService — appendDiscussionHistory для фасилитатора](../../src/Module/Orchestrator/Domain/Service/Chain/Dynamic/RunDynamicLoopService.php)

## 9. Comments (Комментарии)
Проблема обнаружена при ретроспективе brainstorm 2026-04-27: discussion_history на 200K содержит JSON-блоки фасилитатора, которые нечитаемы для человека. Участники пишут prose — с ними всё ок.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-27 | Тимлид (Алекс) (pi) | Создание задачи |
