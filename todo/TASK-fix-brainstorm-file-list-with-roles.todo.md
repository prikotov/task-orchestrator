---
# Metadata (Метаданные)
type: fix
created: 2026-04-27
value: V2
complexity: C1
priority: P1
depends_on:
epic: EPIC-fix-brainstorm-session-bugs
author: Тимлид (Алекс) (pi)
assignee: Бэкендер (Левша) (pi)
branch: task/fix-brainstorm-file-list-with-roles
pr: https://github.com/prikotov/task-orchestrator/pull/86
status: in_progress
---

# TASK-fix-brainstorm-file-list-with-roles: Добавление ролей в список файлов выступлений

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как фасилитатор и участник brainstorm-сессии, я хочу видеть в списке файлов предыдущих выступлений имя роли рядом с каждым файлом, чтобы понимать кто сказал что до чтения файлов.

### Goal (Цель по SMART)
Изменить форматирование списка файлов выступлений: вместо `- var/sessions/.../step_042_round_021_system_architect_loki_4_response.md` выводить `- system_architect_loki: var/sessions/.../step_042_round_021_system_architect_loki_4_response.md`. Роль берётся из данных, уже хранящихся в `$roundData['role']`.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `src/Module/Orchestrator/Infrastructure/Service/Chain/ChainSessionLogger.php` — метод `getResponseFilePaths()` (строка 291)
    *   `src/Module/Orchestrator/Domain/Service/Chain/Dynamic/ExecuteDynamicTurnService.php` — форматирование `$facResponsePaths` и `$prevResponsePaths` (строки ~79 и ~118)
*   **Текущее поведение:**
    *   `getResponseFilePaths()` возвращает `array<string>` — только пути файлов
    *   `$roundData` уже содержит поле `role` (заполняется в `logRound()`), но оно не используется в `getResponseFilePaths()`
    *   `ExecuteDynamicTurnService` форматирует: `"- {$path}"` — без роли
*   **Границы (Out of Scope):**
    *   Не меняем формат `session.json`
    *   Не меняем `RunDynamicLoopAgentService` (получает уже отформатированную строку)
    *   Не меняем промпты (отдельная задача TASK-fix-brainstorm-unified-journal-format)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `getResponseFilePaths()` возвращает структуры `array{role: string, path: string}` вместо `array<string>`
- [ ] Форматирование в `ExecuteDynamicTurnService` выводит `- <роль>: <путь>` вместо `- <путь>`
- [ ] Unit-тесты обновлены
- [ ] Существующие тесты не ломаются

### 🟡 Should Have (Желательно)
- [ ] Если роль неизвестна (backward compat) — выводить только путь

### ⚫ Won't Have (Не будем делать)
- [ ] Изменение интерфейса `ChainSessionLoggerInterface` — метод `getResponseFilePaths()` уже описан в интерфейсе, нужно проверить и при необходимости обновить сигнатуру

## 4. Implementation Plan (План реализации)
1. [ ] Обновить `ChainSessionLoggerInterface::getResponseFilePaths()` — вернуть `array<array{role: string, path: string}>`
2. [ ] Обновить реализацию `ChainSessionLogger::getResponseFilePaths()` — включить `role` из `$roundData`
3. [ ] Обновить форматирование в `ExecuteDynamicTurnService::runFacilitatorStep()` — `"- {$data['role']}: {$data['path']}"`
4. [ ] Обновить форматирование в `ExecuteDynamicTurnService::runParticipantStep()` — аналогично
5. [ ] Обновить форматирование в `ExecuteDynamicTurnService::runFinalizeStep()` — аналогично
6. [ ] Обновить unit-тесты

## 5. Definition of Done (Критерии приёмки)
- [ ] Список файлов в промпте фасилитатора содержит роли
- [ ] Список файлов в промпте участника содержит роли
- [ ] PHPUnit проходит
- [ ] Psalm проходит

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- Изменение сигнатуры `getResponseFilePaths()` — нужно обновить все вызовы и тесты
- Задача зависит от корректности данных `role` в `$roundData` — проверено: поле заполняется в `logRound()` (строка 155 ChainSessionLogger.php)

## 8. Sources (Источники)
- [ ] [ChainSessionLogger](../src/Module/Orchestrator/Infrastructure/Service/Chain/ChainSessionLogger.php)
- [ ] [ExecuteDynamicTurnService](../src/Module/Orchestrator/Domain/Service/Chain/Dynamic/ExecuteDynamicTurnService.php)
- [ ] [step_043 — текущий формат списка файлов](../var/sessions/brainstorm/2026-04-27_12-29-03/step_043_round_022_team_lead_alex_3_user.md)

## 9. Comments (Комментарии)
`$roundData` уже содержит `role` (строка 155 в ChainSessionLogger.php) — нужно только использовать его при формировании списка файлов.

## Инструкции для сабагента

**Ветка:** task/fix-brainstorm-file-list-with-roles (уже создана и активна)
**PR:** уже создан (draft) из task/fix-brainstorm-file-list-with-roles в fix/epic-brainstorm-session-bugs — [PR #86](https://github.com/prikotov/task-orchestrator/pull/86)

### Порядок действий
1. Переключись в ветку `task/fix-brainstorm-file-list-with-roles`: `git checkout task/fix-brainstorm-file-list-with-roles`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](../docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `vendor/bin/phpunit` и `vendor/bin/psalm`.
6. Сделай `git push`.
7. Переведи PR из draft в ready: `gh pr ready <PR_NUMBER>`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-27 | Тимлид (Алекс) (pi) | Создание задачи |
