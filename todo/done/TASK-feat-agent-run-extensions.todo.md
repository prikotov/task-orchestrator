---
# Metadata (Метаданные)
type: feat
created: 2026-05-10
value: V2
complexity: C2
priority: P1
depends_on:
epic: EPIC-feat-subagent-delegation-and-task-chains
author: Тимлид Алекс (pi)
assignee: Бэкендер Тони (pi)
branch: task/feat-agent-run-extensions
pr: https://github.com/prikotov/task-orchestrator/pull/197
status: done
---

# TASK-feat-agent-run-extensions: Расширение app:agent:run —timeout и --context

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как тимлид, я хочу задавать таймаут и дополнительный контекст при запуске одного агента (`app:agent:run`), чтобы контролировать время выполнения и передавать данные извне.

### Goal (Цель по SMART)
Добавить в `app:agent:run` опции `--timeout <seconds>` и `--context <json>`. Timeout ограничивает общее время выполнения агента. Context передаётся в `previousContext` агента как JSON-строка.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `apps/console/src/Command/RunCommand.php` — CLI-опции
    *   `src/Module/AgentRunner/Application/` — DTO (AgentRunRequestVo)
    *   `src/Module/AgentRunner/Infrastructure/Service/` — runners
*   **Текущее поведение:** `app:agent:run` имеет `--role`, `--task`, `--runner`, `--model`, `--tools`, `--working-dir`, `--no-context-files`. Нет timeout и context.
*   **Границы (Out of Scope):**
    *   Новая команда `app:agent:delegate` — `app:agent:run` достаточен (решение Гэндальфа)
    *   `--reasoning-effort` — отдельная задача (упомянута в эпике, но не блокирует)
    *   `--report-file` — отдельная задача

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] `--timeout <seconds>` в `RunCommand` — ограничение общего времени выполнения
- [x] Timeout пробрасывается в `AgentRunRequestVo`
- [x] `--context <json>` в `RunCommand` — произвольный JSON как previousContext
- [x] Context парсится и валидируется (должен быть валидный JSON)
- [x] Context передаётся в prompt агента как `previousContext`
- [x] Unit-тесты: RunCommand с timeout, RunCommand с context

### 🟡 Should Have (Желательно)
- [x] `--timeout 0` = без лимита (default)
- [x] Ошибка при невалидном JSON в `--context`

### 🟢 Could Have (Опционально)
- [ ] `--report-file <path>` — вывод результата в файл

### ⚫ Won't Have (Не будем делать)
- [ ] Новая команда `app:agent:delegate`
- [ ] `--reasoning-effort` (отдельная задача)
- [ ] `--provider` (отдельная задача)

## 4. Implementation Plan (План реализации)
1. [x] Добавить `--timeout` и `--context` в `RunCommand::configure()`
2. [x] Добавить поля в DTO: `AgentRunRequestVo` (timeout, context)
3. [x] В `RunCommand::execute()`: валидация JSON, проброс в request
4. [x] В runners (PiAgentRunner, CodexAgentRunner): timeout → process timeout
5. [x] В prompt builder: context → previousContext секция
6. [x] Unit-тесты: RunCommand options, AgentRunRequestVo, prompt builder
7. [x] Обновить документацию команды

## 5. Definition of Done (Критерии приёмки)
- [x] `app:agent:run --role dev --task "..." --timeout 300` завершается за ≤300 сек или Killed
- [x] `app:agent:run --role dev --task "..." --context '{"key":"value"}'` — агент видит context
- [x] Невалидный JSON в `--context` даёт ошибку
- [x] Psalm, PHPUnit зелёные
- [x] Deptrac не нарушен

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
php bin/console app:agent:run --help
```

## 7. Risks and Dependencies (Риски и зависимости)
- Timeout может не поддерживаться одинаково pi и codex — проверить API каждого runner
- Context размер может превышить token limit — валидация размера (won't have для первой версии)

## 8. Sources (Источники)
- [ ] [RunCommand.php](../apps/console/src/Command/RunCommand.php)
- [ ] [AgentRunRequestVo](../../src/Module/AgentRunner/Application/)
- [ ] [PiAgentRunner](../../src/Module/AgentRunner/Infrastructure/Service/)
- [ ] [Отчёт Гэндальфа — Вопрос 1](../../docs/agents/reports/system-architect/2026-05-10_10-48_three-architecture-questions.md)

## 9. Comments (Комментарии)
Гэндальф рекомендовал расширить `app:agent:run` вместо создания новой команды `delegate`. Это первая итерация — только timeout + context. Остальные опции (--reasoning-effort, --provider, --report-file) — отдельные задачи.

## Инструкции для сабагента

**Ветка:** task/feat-agent-run-extensions (уже создана и активна)
**PR:** будет создан draft PR из task/feat-agent-run-extensions в task/epic-feat-subagent-delegation-and-task-chains

### Порядок действий
1. Переключись в ветку `task/feat-agent-run-extensions`: `git checkout task/feat-agent-run-extensions`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](../../docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `vendor/bin/phpunit`, `vendor/bin/psalm`, `vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress`.
6. Сделай `git push`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-10 | Тимлид Алекс (pi) | Создание задачи |
