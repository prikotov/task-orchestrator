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
branch:
pr:
status: todo
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
- [ ] `--timeout <seconds>` в `RunCommand` — ограничение общего времени выполнения
- [ ] Timeout пробрасывается в `AgentRunRequestVo`
- [ ] `--context <json>` в `RunCommand` — произвольный JSON как previousContext
- [ ] Context парсится и валидируется (должен быть валидный JSON)
- [ ] Context передаётся в prompt агента как `previousContext`
- [ ] Unit-тесты: RunCommand с timeout, RunCommand с context

### 🟡 Should Have (Желательно)
- [ ] `--timeout 0` = без лимита (default)
- [ ] Ошибка при невалидном JSON в `--context`

### 🟢 Could Have (Опционально)
- [ ] `--report-file <path>` — вывод результата в файл

### ⚫ Won't Have (Не будем делать)
- [ ] Новая команда `app:agent:delegate`
- [ ] `--reasoning-effort` (отдельная задача)
- [ ] `--provider` (отдельная задача)

## 4. Implementation Plan (План реализации)
1. [ ] Добавить `--timeout` и `--context` в `RunCommand::configure()`
2. [ ] Добавить поля в DTO: `AgentRunRequestVo` (timeout, context)
3. [ ] В `RunCommand::execute()`: валидация JSON, проброс в request
4. [ ] В runners (PiAgentRunner, CodexAgentRunner): timeout → process timeout
5. [ ] В prompt builder: context → previousContext секция
6. [ ] Unit-тесты: RunCommand options, AgentRunRequestVo, prompt builder
7. [ ] Обновить документацию команды

## 5. Definition of Done (Критерии приёмки)
- [ ] `app:agent:run --role dev --task "..." --timeout 300` завершается за ≤300 сек или Killed
- [ ] `app:agent:run --role dev --task "..." --context '{"key":"value"}'` — агент видит context
- [ ] Невалидный JSON в `--context` даёт ошибку
- [ ] Psalm, PHPUnit зелёные
- [ ] Deptrac не нарушен

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
- [ ] [RunCommand.php](apps/console/src/Command/RunCommand.php)
- [ ] [AgentRunRequestVo](src/Module/AgentRunner/Application/)
- [ ] [PiAgentRunner](src/Module/AgentRunner/Infrastructure/Service/)
- [ ] [Отчёт Гэндальфа — Вопрос 1](docs/agents/reports/system-architect/2026-05-10_10-48_three-architecture-questions.md)

## 9. Comments (Комментарии)
Гэндальф рекомендовал расширить `app:agent:run` вместо создания новой команды `delegate`. Это первая итерация — только timeout + context. Остальные опции (--reasoning-effort, --provider, --report-file) — отдельные задачи.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-10 | Тимлид Алекс (pi) | Создание задачи |
