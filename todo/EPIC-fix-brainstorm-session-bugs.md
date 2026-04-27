---
# Metadata (Метаданные)
type: epic
created: 2026-04-27
value: V3
complexity: C2
priority: P1
author: Тимлид (Алекс) (pi)
assignee: Тимлид (Алекс) (pi)
status: done
branch: fix/epic-brainstorm-session-bugs
pr: https://github.com/prikotov/task-orchestrator/pull/88
---

# EPIC-fix-brainstorm-session-bugs: Исправление багов brainstorm-сессии

## 1. Concept and Goal (Концепция и цель)
### Story (Job Story)
> Когда brainstorm-сессия завершается, я хочу получать в `result.md` осмысленный протокол с текстом synthesis, а не слово «Array», чтобы результат сессии был пригоден для чтения без ручного восстановления. Когда участник получает промпт в первом раунде (без предыдущих выступлений), я хочу чтобы в промпте не было пустых секций «Выступления предыдущих участников» и рекомендаций по чтению файлов, чтобы агент не путался. Когда фасилитатор и участники видят список файлов предыдущих выступлений, я хочу чтобы каждый файл сопровождался именем роли, чтобы было понятно кто что сказал.

### Goal (Цель по SMART)
Устранить 4 бага, выявленные по итогам brainstorm-сессии 2026-04-27 (45 раундов, декомпозиция Orchestrator): (1) `result.md` содержит «Array» вместо текста synthesis, (2) в первом раунде участнику показывается пустая секция «Выступления предыдущих участников» + рекомендация «Рекомендуется начать с последних файлов», (3) список файлов выступлений не содержит имён ролей, (4) формат журнала выступлений не унифицирован (фасилитатор: «Выступления участников (файлы — прочитай их)», участник: «Выступления предыдущих участников (файлы):»). Результат: следующая brainstorm-сессия выдаёт читаемый result.md, промпты не содержат пустых секций, файлы сопровождаются ролями, формат журнала унифицирован.

## 2. Context and Scope (Контекст и границы)
*   **In Scope (Что делаем):**
    *   `FacilitatorResponseParserService::parse()` — обработка `synthesis` как массива
    *   `PromptFormatterService::buildParticipantUserPrompt()` — удаление пустого блока + рекомендательной строки
    *   `ChainSessionLogger::getResponseFilePaths()` — возврат роли вместе с путём
    *   `ExecuteDynamicTurnService` — форматирование списка файлов с ролями
    *   Промпты `participant_user.txt` и `facilitator_continue.txt` — унификация заголовка секции файлов
    *   Unit-тесты для всех изменений
*   **Out of Scope (Чего НЕ делаем):**
    *   Изменение архитектуры dynamic-цикла
    *   Изменение формата `session.json`
    *   Изменение `SKILL.md` или других доков
    *   Правки в `FormatDynamicJournalService`

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Блокирующие требования)
- [x] `result.md` содержит текст synthesis, даже если LLM вернула synthesis как массив строк (склейка через `\n`)
- [x] В первом раунде промпт участника НЕ содержит секцию «Выступления предыдущих участников» и рекомендательную строку «Рекомендуется начать...»
- [x] Список файлов выступлений содержит имя роли рядом с каждым файлом (формат: `- <роль>: <путь>`)
- [x] Unit-тесты покрывают все 4 исправления

### 🟡 Should Have (Важные требования)
- [x] Формат заголовка секции файлов унифицирован: одинаковый заголовок в `participant_user.txt` и `facilitator_continue.txt`

### 🟢 Could Have (Желательно)
- [ ] Без пунктов

### ⚫ Won't Have (Не в этот раз)
- [ ] Изменение формата `session.json`
- [ ] Обновление SKILL.md

## 4. Solution Design (Техническое решение)

```mermaid
flowchart TD
    A[Баг 1: synthesis = Array] -->|FacilitatorResponseParserService::parse()| B["is_array() → implode(\n, arr)"]
    C[Баг 2: пустой блок в раунде 1] -->|PromptFormatterService::buildParticipantUserPrompt()| D["Расширенный regex: удаляет заголовок + рекомендательную строку"]
    E[Баг 3: нет ролей в списке файлов] -->|ChainSessionLogger::getResponseFilePaths()| F["Возврат структуры {role, path} + форматирование в ExecuteDynamicTurnService"]
    G[Баг 4: неунифицированный формат] -->|participant_user.txt + facilitator_continue.txt| H["Общий заголовок: 'Выступления предыдущих участников (файлы):'"]
```

## 5. Implementation Plan (План реализации)

- [x] [TASK-fix-brainstorm-synthesis-array](done/TASK-fix-brainstorm-synthesis-array.todo.md) — Исправление бага synthesis=Array в FacilitatorResponseParserService → **Бэкендер Левша**, P0
- [x] [TASK-fix-brainstorm-empty-participant-section](done/TASK-fix-brainstorm-empty-participant-section.todo.md) — Удаление пустой секции + рекомендательной строки в PromptFormatterService → **Бэкендер Левша**, P0
- [x] [TASK-fix-brainstorm-file-list-with-roles](done/TASK-fix-brainstorm-file-list-with-roles.todo.md) — Добавление ролей в список файлов выступлений → **Бэкендер Левша**, P1
- [x] [TASK-fix-brainstorm-unified-journal-format](done/TASK-fix-brainstorm-unified-journal-format.todo.md) — Унификация формата заголовка секции файлов в промптах → **Бэкендер Тони**, P2

## 6. Definition of Done (Критерии приёмки эпика)
- [x] Все задачи Must Have выполнены и протестированы
- [x] PHPUnit проходит без регрессий
- [x] Psalm проходит без новых ошибок
- [ ] Ретроспектива записана в `docs/agents/team-retro/`

## 7. Release Notes and Deployment (Инструкция по релизу)
- [x] Изменения backward compatible, никаких миграций

## 8. Risks and Dependencies (Риски и зависимости)
- Задача 3 (роли в списке файлов) меняет форматирование списка, что может повлиять на парсинг промпта агентом — но промпт использует список как контекст для чтения файлов, парсинг не зависит от формата
- Задача 4 (унификация промптов) меняет текст промпта — агенты должны адаптироваться автоматически

## 9. Sources (Источники)
- [ ] [Сессия brainstorm](../var/sessions/brainstorm/2026-04-27_12-29-03/)
- [ ] [Ретроспектива brainstorm-improvements](../docs/agents/team-retro/2026-04-27_17-58-brainstorm-improvements.md)
- [ ] [FacilitatorResponseParserService](../src/Module/Orchestrator/Infrastructure/Service/Chain/FacilitatorResponseParserService.php)
- [ ] [PromptFormatterService](../src/Module/Orchestrator/Infrastructure/Service/Chain/PromptFormatterService.php)
- [ ] [ChainSessionLogger](../src/Module/Orchestrator/Infrastructure/Service/Chain/ChainSessionLogger.php)

## 10. Comments (Комментарии)
Баги выявлены при ретроспективном анализе brainstorm-сессии на 45 раундов (декомпозиция Orchestrator). Сессия завершилась успешно по результатам обсуждения, но `result.md` оказался непригоден (synthesis = «Array»). Формат промптов не оптимальный — в первом раунде участник получает пустой блок. Файлы выступлений не содержат информации о ролях, что затрудняет контекст.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-27 | Тимлид (Алекс) (pi) | Создание эпика |
