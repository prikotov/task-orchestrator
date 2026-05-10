---
# Metadata (Метаданные)
type: feat
created: 2026-05-10
value: V2
complexity: C3
priority: P1
depends_on:
epic: EPIC-feat-subagent-delegation-and-task-chains
author: Тимлид Алекс (pi)
assignee: Бэкендер Левша (pi)
branch: task/feat-tool-step-type
pr:
status: in_progress
---

# TASK-feat-tool-step-type: Тип шага tool в цепочках

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как тимлид, я хочу чтобы цепочки могли выполнять детерминированные shell-команды (git, gh, mv) и передавать stdout в context следующих шагов, чтобы оцифровывать рутинные операции task-workflow.

### Goal (Цель по SMART)
Добавить тип шага `tool` в `ChainStepTypeEnum` с handler'ом, загрузкой из YAML и передачей stdout в `ChainContext`. Урезанная версия: только `command` + `output_key`, без DSL/conditions/retry внутри.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `src/Module/Orchestrator/Domain/` — enum, VO, interface
    *   `src/Module/Orchestrator/Application/` — DTO
    *   `src/Module/Orchestrator/Infrastructure/` — handler (Symfony Process)
    *   `config/chains.yaml` — пример `tool`-шага
*   **Текущее поведение:** `ChainStepTypeEnum` имеет `agent` и `quality_gate`. `quality_gate` игнорирует stdout, возвращает только pass/fail.
*   **Границы (Out of Scope):**
    *   Template variables (`{{task.slug}}`)
    *   Conditions/branching внутри tool-шага
    *   Retry/rollback
    *   White-list разрешённых команд
    *   Resume цепочки с произвольного шага

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `tool` case в `ChainStepTypeEnum`
- [ ] `ToolStepVo` (command, label, output_key?, timeout_seconds)
- [ ] `ToolStepRunnerInterface` в Domain
- [ ] `ToolStepRunner` (Infrastructure) — Symfony Process, stdout/exit_code → результат
- [ ] Загрузка `tool`-шагов из `chains.yaml` (type: tool, command, output_key)
- [ ] stdout tool-шага записывается в `ChainContext` по ключу `output_key`
- [ ] Последующий `agent`-шаг видит результат tool-шага в `previousContext`
- [ ] Unit-тесты: ToolStepVo, ToolStepRunner (mock Process), загрузка YAML
- [ ] Пример `tool`-шага в `config/chains.yaml.example`

### 🟡 Should Have (Желательно)
- [ ] Timeout для tool-шага (default: 120 сек)
- [ ] Error policy: `fail` (default) — tool-шаг с exit code ≠ 0 останавливает цепочку

### 🟢 Could Have (Опционально)
- [ ] `capture_output: false` (default: true) — не писать stdout в context

### ⚫ Won't Have (Не будем делать)
- [ ] DSL/pipeline внутри tool-шага
- [ ] Conditions/retry/rollback
- [ ] White-list команд
- [ ] Resume с произвольного шага
- [ ] Template variables (отдельная задача)

## 4. Implementation Plan (План реализации)
1. [ ] Добавить `case tool = 'tool'` в `ChainStepTypeEnum`
2. [ ] Создать `ToolStepVo` в Domain (command, label, outputKey, timeoutSeconds)
3. [ ] Создать `ToolStepRunnerInterface` в Domain\Service
4. [ ] Создать `ToolStepRunner` в Infrastructure (Symfony Process)
5. [ ] Создать `ToolStepResultVo` (exitCode, stdout, success)
6. [ ] Добавить парсинг `tool`-шагов в YAML-загрузчик цепочек
7. [ ] Интегрировать в `RunStaticChainService` / `ExecuteStaticStepService`
8. [ ] Передача stdout в ChainContext по output_key
9. [ ] Unit-тесты (≥80% покрытия новых классов)
10. [ ] Пример в `chains.yaml.example`

## 5. Definition of Done (Критерии приёмки)
- [ ] Цепочка с `tool`-шагом выполняет shell-команду
- [ ] stdout tool-шага доступен в context следующего agent-шага
- [ ] tool-шаг с exit code ≠ 0 останавливает цепочку (error policy: fail)
- [ ] Psalm и PHPUnit зелёные
- [ ] Deptrac не нарушен (Domain ← Infrastructure)

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
```

## 7. Risks and Dependencies (Риски и зависимости)
- **«Троянский конь» (Локи):** tool-шаг может обрастать DSL/conditions/retry → ограничить scope, не добавлять ничего сверх command + output_key
- **Стейт между шагами:** пока только stdout → string в context. Структурированный парсинг stdout — won't have
- **Idempotency:** git-операции не idempotent → пока не решаем, пользователь отвечает за корректность команд

## 8. Sources (Источники)
- [ ] [ChainStepTypeEnum](src/Module/Orchestrator/Domain/)
- [ ] [chains.yaml](config/chains.yaml)
- [ ] [Отчёт Гэндальфа — Вопрос 3](docs/agents/reports/system-architect/2026-05-10_10-48_three-architecture-questions.md)
- [ ] [Отчёт Локи — Предложение 1](docs/agents/reports/system-architect/2026-05-10_blind-spots-task-workflow-chains.md)
- [ ] [Гайд architecture.md](docs/guide/architecture.md)

## 9. Comments (Комментарии)
Команда сошлась на урезанной версии (Тони, Гэндальф). Локи предупредил о риске разрастания — принято, scope ограничен. Если tool-шаг понадобится расширить — отдельная задача с обоснованием.

## Инструкции для сабагента

**Ветка:** task/feat-tool-step-type (уже создана и активна)
**PR:** уже создан (draft) из task/feat-tool-step-type в task/epic-feat-subagent-delegation-and-task-chains — [PR #196](https://github.com/prikotov/task-orchestrator/pull/196)

### Порядок действий
1. Переключись в ветку `task/feat-tool-step-type`: `git checkout task/feat-tool-step-type`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](../../docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `vendor/bin/phpunit`, `vendor/bin/psalm`, `vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress`.
6. Сделай `git push`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-10 | Тимлид Алекс (pi) | Создание задачи |
