---
# Metadata (Метаданные)
type: epic
created: 2026-05-09
value: V2
complexity: C4
priority: P1
author: Тимлид Алекс (pi)
assignee: Тимлид Алекс (pi)
branch: task/epic-feat-subagent-delegation-and-task-chains
status: todo
pr: https://github.com/prikotov/task-orchestrator/pull/194
---

# EPIC-feat-subagent-delegation-and-task-chains: Делегирование сабагентов и оцифровка task-workflow

## 1. Concept and Goal (Концепция и цель)
### Story (User Story)
> Как тимлид, я хочу запускать сабагентов через любой CLI-раннер (pi, codex) — и через оркестратор, и через независимый fallback-скрипт, чтобы делегировать задачи команде даже когда оркестратор нестабилен.

> Как тимлид, я хочу чтобы рутинные детерминированные шаги task-workflow (проверки, коммиты) выполнялись как шаги цепочки, а не только как инструкции в markdown-скиллах, чтобы уменьшить количество пропущенных шагов.

### Goal (Цель по SMART)
1. Параметризовать `watch-subagent.sh` для поддержки pi и codex с опциональными env-переменными.
2. Добавить тип шага `tool` в движок цепочек для детерминированных shell-операций с результатом в context.
3. Создать шаблонные цепочки `task-implement` и `task-hotfix` в `chains.yaml`.

## 2. Context and Scope (Контекст и границы)
*   **In Scope (Что делаем):**
    *   Параметризация `watch-subagent.sh` — флаг `--runner`, env `RUNNER`, `MODEL`
    *   Новый тип шага `tool` в `ChainStepTypeEnum` + handler + загрузка из YAML
    *   Шаблонные цепочки `task-implement`, `task-hotfix` в `chains.yaml`
    *   Передача stdout `tool`-шага в context следующего шага
*   **Out of Scope (Чего НЕ делаем):**
    *   DSL для пайплайнов внутри `tool`-шага
    *   White-list разрешённых команд (сделаем позже если нужно)
    *   Visualizer для цепочек
    *   Resume цепочки с произвольного шага
    *   Новый CLI-арифлет `app:agent:delegate` — `app:agent:run` достаточно (добавим `--context`, `--timeout`)

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Блокирующие требования)
- [ ] `watch-subagent.sh` поддерживает `--runner pi|codex` (по умолчанию `pi`)
- [ ] `watch-subagent.sh` поддерживает опциональные `--model` и env `RUNNER`, `MODEL`
- [ ] Для codex: формирует команду `codex exec --json --dangerously-bypass-approvals-and-sandbox --skip-git-repo-check --ephemeral`
- [ ] Новый enum case `tool` в `ChainStepTypeEnum`
- [ ] `ToolStepHandler` — выполняет shell-команду, пишет результат в `ChainContext`
- [ ] Загрузка `tool`-шагов из `chains.yaml` (ключ `command`, опционально `output_key`)
- [ ] Цепочка `task-implement` в `chains.yaml`: implement → self-review → review → quality_gate(make check)
- [ ] `fix_iterations` для цикла implement → review (max 3)

### 🟡 Should Have (Важные требования)
- [ ] Цепочка `task-hotfix` (ветка от tag, без architect-шага)
- [ ] Добавить `--timeout` и `--context` в `app:agent:run`
- [ ] Контрактная версия в шапке `watch-subagent.sh` (`# CONTRACT: v1`)

### 🟢 Could Have (Желательно)
- [ ] Интеграционный тест для `watch-subagent.sh` (pi + codex)
- [ ] `--reasoning` в `watch-subagent.sh` для codex (`-c model_reasoning_effort=...`)

### ⚫ Won't Have (Не в этот раз)
- [ ] White-list разрешённых команд для `tool`-шагов
- [ ] Resume цепочки с произвольного шага
- [ ] Визуализатор цепочек
- [ ] Парсинг stdout tool-шага в структуру

## 4. Solution Design (Техническое решение)

### Позиция команды (брейншторм)

**Гэндальф (Архитектор):**
- `app:agent:run` достаточен для делегирования, новую команду не вводить
- Добавить `--context`, `--format`, `--timeout` в `app:agent:run`
- codex — через Infrastructure-runner, скрипт не трогать
- Нужен тип шага `tool` — как `quality_gate`, но с результатом в context

**Локи (Архитектор-критик):**
- `tool`-шаг — троянский конь сложности. Нет стейта между шагами, нет idempotency
- Альтернатива: цепочки только для AI→AI, детерминированные шаги — через Symfony Console
- watch-subagent.sh = fallback с **опциональным** контрактом (env + defaults + версия)
- Риск: YAML-driven development без визуализации

**Тони (Бэкендер):**
- Параметризация watch-subagent.sh: 🟢 низкий риск, 3/10, 1-2 часа
- tool-шаг: 🟡 делать урезанным — только stdout/exit_code в context, без DSL
- Шаблонные цепочки: начать с одной `task-implement` как PoC, потом hotfix
- Приоритет: Эпик 1 → Эпик 2 → Эпик 3 (последовательная зависимость)

### Решение тимлида (синтез)

1. **watch-subagent.sh** — параметризуем (Тони прав, это дешёво и нужно). Контракт Локи (env + defaults + версия).
2. **tool-шаг** — делаем урезанным (Тони + Гэндальф). Без DSL, без white-list, без resume. Только command + output_key + exit_code/stdout в context.
3. **Шаблонные цепочки** — начинаем с `task-implement` как PoC. AI-шаги + quality_gate + fix_iterations. git-операции пока оставляем тимлиду (Локи прав — нет idempotency).
4. **app:agent:run** — расширяем `--timeout` и `--context`. Новую команду не вводим.

```mermaid
flowchart LR
    subgraph "watch-subagent.sh (fallback)"
        A[--runner pi|codex] --> B[pi --mode json]
        A --> C[codex exec --json]
    end
    subgraph "app:agent:run (orchestrator)"
        D[--runner --model --context --timeout] --> E[AgentRunnerRegistry]
        E --> F[PiAgentRunner]
        E --> G[CodexAgentRunner]
    end
    subgraph "chains.yaml (tool step)"
        H[tool step] --> I[Shell command]
        I --> J[stdout → context]
        K[agent step] --> L[AI reads context]
    end
```

## 5. Implementation Plan (План реализации)

- [ ] [TASK-feat-watch-subagent-runner-param](TASK-feat-watch-subagent-runner-param.todo.md) — Параметризация `watch-subagent.sh`: `--runner`, `--model`, env-фолбэки, поддержка codex
- [ ] [TASK-feat-tool-step-type](TASK-feat-tool-step-type.todo.md) — Тип шага `tool` в цепочках: enum, handler, загрузка YAML, context propagation
- [ ] [TASK-feat-task-implement-chain](TASK-feat-task-implement-chain.todo.md) — Шаблонная цепочка `task-implement` в `chains.yaml` с fix_iterations
- [ ] [TASK-feat-agent-run-extensions](TASK-feat-agent-run-extensions.todo.md) — Расширение `app:agent:run`: `--timeout`, `--context`

## 6. Definition of Done (Критерии приёмки эпика)
- [ ] `watch-subagent.sh --runner codex -s 600 -r <role>` запускает codex
- [ ] `tool`-шаг в цепочке выполняет shell-команду и передаёт stdout в context
- [ ] Цепочка `task-implement` проходит цикл: implement → self-review → review → quality_gate
- [ ] `fix_iterations` корректно циклит implement → review
- [ ] `app:agent:run --timeout 300 --context '{"key":"value"}'` работает
- [ ] Все новые классы покрыты unit-тестами (≥80%)
- [ ] Psalm и PHPUnit зелёные
- [ ] Deptrac не нарушен

## 7. Release Notes and Deployment (Инструкция по релизу)
- [ ] Обновить документацию `docs/agents/skills/run-pi-subagent/SKILL.md` — добавить `--runner`
- [ ] Обновить `docs/guide/chains.md` — добавить описание `tool`-шагов
- [ ] Обновить `config/chains.yaml.example` — добавить пример `tool`-шага

## 8. Risks and Dependencies (Риски и зависимости)
- **tool-шаг может разрастись** (Локи): ограничить scope, не добавлять DSL/conditions/retry
- **Нет idempotency для git-операций** (Локи): git-операции пока оставляем тимлиду, не оцифровываем в `tool`
- **watch-subagent.sh может дрейфовать** (Локи): контрактная версия + defaults
- **Шаблонные цепочки хрупки** (Локи): начать с PoC `task-implement`, итерировать по опыту
- **app:agent:run уже работает** (Гэндальф): не плодить новые команды

## 9. Sources (Источники)
- [ ] [Отчёт Гэндальфа](docs/agents/reports/system-architect/)
- [ ] [task-via-subagents SKILL.md](docs/agents/skills/task-via-subagents/SKILL.md)
- [ ] [epic-via-subagents SKILL.md](docs/agents/skills/epic-via-subagents/SKILL.md)
- [ ] [run-pi-subagent SKILL.md](docs/agents/skills/run-pi-subagent/SKILL.md)
- [ ] [chains.yaml](config/chains.yaml)

## 10. Comments (Комментарии)
Брейншторм проведён с тремя членами команды через сабагентов. Синтез — за тимлидом. Приоритет выполнения задач — последовательный (каждая следующая зависит от предыдущей).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-09 | Тимлид Алекс (pi) | Создание эпика |
| 2026-05-10 | Тимлид Алекс (pi) | Добавлены результаты брейншторма команды (Гэндальф, Локи, Тони) |
