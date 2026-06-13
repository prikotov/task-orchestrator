---
type: fix
created: 2026-06-13
value: V3
complexity: C1
priority: P0
depends_on:
epic:
author: codex-cli
assignee: codex-cli
branch: task/fix-codex-subagent-runner
pr: https://github.com/prikotov/task-orchestrator/pull/257
status: review
---

# TASK-fix-codex-subagent-runner: Исправить запуск Codex CLI через run-subagent

## 0. Простое описание (Human Brief)
Починить запуск `codex` (Codex CLI) через `watch-subagent.sh`, чтобы делегирование могло работать через локальный Codex CLI без ложной ошибки завершения.

### Проблема простыми словами (Problem)
`codex exec --json` успешно завершает ход событием `turn.completed`, но `watch-subagent.sh` ждал только `agent_end` из `pi` runner (раннер Pi) и поэтому считал успешный запуск ошибкой `missing_agent_end`.

### Варианты или путь решения (Solution Sketch)
Разделить success/failure events (события успеха/ошибки) по runner: для `pi` оставить `agent_end`, для `codex` использовать `turn.completed`/`turn.failed`, а текст ответа извлекать из `item.completed` и `turn.completed.turn.items`.

### Ожидаемый результат (Expected Result)
`watch-subagent.sh --runner codex -o text` возвращает текст ответа и exit code 0, а существующее поведение `pi` runner остаётся совместимым.

## 1. Concept and Goal (Концепция и Цель)
### Story
Как разработчик, я хочу запускать `codex` (Codex CLI) через `watch-subagent.sh`, чтобы делегирование работало не только через `pi`, но и через локальный Codex CLI.

### Goal
Адаптировать `docs/agents/skills/run-subagent/scripts/watch-subagent.sh` к актуальному JSONL-формату `codex exec --json`: считать `turn.completed` успешным завершением и извлекать текст ответа из `item.completed` / `agent_message`.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/agents/skills/run-subagent/scripts/watch-subagent.sh`.
*   **Текущее поведение:** `codex exec` возвращает `turn.completed`, но wrapper (обёртка) ждёт `agent_end` и завершает запуск ошибкой `missing_agent_end`.
*   **Границы (Out of Scope):** не меняем авторизацию `pi openai-codex`, не меняем системные proxy-настройки, не переписываем протокол `pi`.

## 3. Requirements (MoSCoW)
### 🔴 Must Have
- [x] `watch-subagent.sh --runner codex -o text` успешно завершается по событию `turn.completed`.
- [x] `watch-subagent.sh --runner codex -o text` выводит текст `agent_message`.
- [x] `watch-subagent.sh --runner codex` корректно распознаёт `turn.failed` как ошибку.
- [x] Поведение `pi` runner (раннер) не ломается.

### ⚫ Won't Have (Не будем делать)
- Не лечим expired token (истёкший токен) у `pi --provider openai-codex`.
- Не меняем глобальные значения `ALL_PROXY`, `HTTP_PROXY`, `HTTPS_PROXY`.

## 4. Implementation Plan (План реализации)
1. [x] Обновить фильтры `codex` JSONL: `item.completed` + `agent_message`.
2. [x] Разделить success/failure события для `pi` и `codex`.
3. [x] Добавить fallback-проверку success/failure events после закрытия pipe (канал событий).
4. [x] Проверить `codex` runner локальным запуском.
5. [x] Проверить `pi` runner на regression (регрессия).

## 5. Definition of Done (Критерии приёмки)
- [x] `watch-subagent.sh --runner codex -o text` возвращает `OK` и exit code 0.
- [x] `watch-subagent.sh --provider zai --model glm-5 -o text` возвращает `OK` и exit code 0.
- [x] Диагностика proxy/auth зафиксирована в отчёте пользователю.

## 6. Verification (Самопроверка)
```bash
docs/agents/skills/run-subagent/scripts/watch-subagent.sh -s 60 -t 60 -o text --runner codex -r docs/agents/roles/team/system_analyst_sherlock.ru.md
docs/agents/skills/run-subagent/scripts/watch-subagent.sh -s 60 -t 60 -o text --provider zai --model glm-5 -r docs/agents/roles/team/system_analyst_sherlock.ru.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- `codex exec` может писать предупреждения о WebSocket proxy (прокси для WebSocket) в stderr, но при успешном fallback всё равно завершаться `turn.completed`.
- У `pi --provider openai-codex` отдельно истёк authentication token (токен авторизации); это не исправляется изменением wrapper-скрипта.

## 8. Sources (Источники)
- `docs/agents/skills/run-subagent/scripts/watch-subagent.sh`
- Локальный вывод `codex exec --json`.
- Локальный вывод `pi --provider openai-codex`.

## 9. Comments (Комментарии)
Проверено локально:
- `bash -n docs/agents/skills/run-subagent/scripts/watch-subagent.sh` — OK.
- `vendor/bin/phpunit tests/Integration/Docs/Agents/Skills/RunSubagent/WatchSubagentScriptTest.php` — OK (13 tests, 62 assertions).
- `vendor/bin/phpunit` — OK (963 tests, 2733 assertions).
- `vendor/bin/psalm` — OK, No errors found.
- Live `watch-subagent.sh --runner codex -o text` — OK, stdout `OK`, exit code 0.
- Live `watch-subagent.sh --provider zai --model glm-5 -o text` — OK, stdout `OK`, exit code 0.

Codex self-review через исправленный `watch-subagent.sh --runner codex` нашёл замечания по backward compatibility (обратная совместимость), `turn.completed`/`item.completed` и сохранению `pi` error reasons; замечания учтены.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-13 | codex-cli | Создание задачи |
