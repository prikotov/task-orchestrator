---
# Metadata (Метаданные)
type: feat
created: 2026-05-10
value: V2
complexity: C1
priority: P1
depends_on:
epic: EPIC-feat-subagent-delegation-and-task-chains
author: Тимлид Алекс (pi)
assignee: Бэкендер Левша (pi)
branch:
pr:
status: todo
---

# TASK-feat-watch-subagent-runner-param: Параметризация watch-subagent.sh для pi и codex

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как тимлид, я хочу запускать сабагентов через `watch-subagent.sh` с выбором раннера (pi или codex), чтобы делегировать задачи через fallback-скрипт независимо от PHP-оркестратора.

### Goal (Цель по SMART)
Добавить в `watch-subagent.sh` флаг `--runner pi|codex` (default: `pi`), опциональные `--model` и env-переменные `RUNNER`, `MODEL`. Для codex формировать команду `codex exec --json --dangerously-bypass-approvals-and-sandbox --skip-git-repo-check --ephemeral`.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/agents/skills/run-pi-subagent/scripts/watch-subagent.sh`
*   **Текущее поведение:** скрипт хардкодит `pi --mode json`
*   **Границы (Out of Scope):** retry/circuit-breaker в скрипте, переименование скилла, поддержка runners кроме pi и codex

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Флаг `--runner pi|codex` (default: `pi`)
- [ ] Опциональный флаг `--model <string>`
- [ ] Env-фолбэки: `RUNNER`, `MODEL` (флаги приоритетнее env)
- [ ] Для codex: `codex exec --dangerously-bypass-approvals-and-sandbox --json --skip-git-repo-check --ephemeral`
- [ ] Per-runner функция построения команды (`build_runner_command`)
- [ ] Per-runner функции фильтрации вывода (`filter_text_pi`, `filter_text_codex`)
- [ ] Контрактная версия в шапке скрипта (`# CONTRACT: v1`)
- [ ] Обновление SKILL.md — документация `--runner`

### 🟡 Should Have (Желательно)
- [ ] `--reasoning` для codex → `-c 'model_reasoning_effort=...'`

### 🟢 Could Have (Опционально)
- [ ] Интеграционный тест скрипта (pi + codex)

### ⚫ Won't Have (Не будем делать)
- [ ] Поддержка runners кроме pi и codex
- [ ] Retry/circuit-breaker/logic в скрипте
- [ ] Переименование скилла `run-pi-subagent` → `run-subagent`

## 4. Implementation Plan (План реализации)
1. [ ] Добавить парсинг `--runner` и `--model` в блок `while/case`
2. [ ] Добавить чтение env `RUNNER`, `MODEL` как defaults
3. [ ] Создать функцию `build_runner_command()` с `case` по runner
4. [ ] Создать `filter_text_codex()`, `filter_tools_codex()`, `filter_files_codex()`
5. [ ] Обновить `PI_CMD=()` на вызов `build_runner_command`
6. [ ] Добавить `# CONTRACT: v1` в шапку
7. [ ] Обновить `SKILL.md` — добавить `--runner`, примеры codex
8. [ ] Протестировать вручную: `watch-subagent.sh --runner pi ...` и `--runner codex ...`

## 5. Definition of Done (Критерии приёмки)
- [ ] `watch-subagent.sh --runner codex -s 60 -r <role> <<< "prompt"` запускает codex
- [ ] `watch-subagent.sh --runner pi -s 60 -r <role> <<< "prompt"` запускает pi (без регрессий)
- [ ] `RUNNER=codex watch-subagent.sh -s 60 ...` работает через env
- [ ] `bash -n watch-subagent.sh` — нет синтаксических ошибок
- [ ] SKILL.md обновлён

## 6. Verification (Самопроверка)
```bash
bash -n docs/agents/skills/run-pi-subagent/scripts/watch-subagent.sh
bash docs/agents/skills/run-pi-subagent/scripts/watch-subagent.sh -h
```

## 7. Risks and Dependencies (Риски и зависимости)
- Формат JSONL-вывода codex может отличаться от pi — нужны реальные тесты
- codex может не поддерживать `--system-prompt` — нужно использовать `-c model_instructions_file=...`

## 8. Sources (Источники)
- [ ] [watch-subagent.sh](docs/agents/skills/run-pi-subagent/scripts/watch-subagent.sh)
- [ ] [run-pi-subagent SKILL.md](docs/agents/skills/run-pi-subagent/SKILL.md)
- [ ] [Отчёт Гэндальфа](docs/agents/reports/system-architect/2026-05-10_10-48_three-architecture-questions.md)
- [ ] [Отчёт Локи](docs/agents/reports/system-architect/2026-05-10_blind-spots-task-workflow-chains.md)

## 9. Comments (Комментарии)
Скрипт — fallback при неработающем оркестраторе. Не превращать в мини-оркестратор (Локи). Контракт с defaults (env + версия) — компромисс между параметризацией и стабильностью.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-10 | Тимлид Алекс (pi) | Создание задачи |
