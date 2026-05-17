---
# Metadata (Метаданные)
type: feat
created: 2026-05-10
value: V2
complexity: C2
priority: P2
depends_on: TASK-feat-tool-step-type
epic: EPIC-feat-subagent-delegation-and-task-chains
author: Тимлид Алекс (pi)
assignee: Бэкендер Левша (pi)
branch: task/feat-task-implement-chain
pr: https://github.com/prikotov/task-orchestrator/pull/198
status: done
---

# TASK-feat-task-implement-chain: Шаблонная цепочка task-implement

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как тимлид, я хочу запускать типовой workflow реализации задачи одной цепочкой (`app:agent:orchestrate task-implement`), чтобы не пропускать шаги self-review и quality gate.

### Goal (Цель по SMART)
Создать цепочку `task-implement` в `chains.yaml`: implement → self-review → review → quality_gate(make check). Цикл implement → review управляется `fix_iterations` (max 3).

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `config/chains.yaml`
*   **Текущее поведение:** тимлид выполняет шаги вручную по инструкциям из skill-файлов
*   **Границы (Out of Scope):**
    *   Цепочка `task-hotfix` (сделать после `task-implement` как PoC)
    *   git-операции в цепочке (branch, commit, push, PR) — остаются тимлиду
    *   Template variables (`{{task.slug}}`)
    *   Resume цепочки

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Цепочка `task-implement` типа `static` в `chains.yaml`
- [ ] Шаг 1: `agent` — backend_developer (реализация задачи)
- [ ] Шаг 2: `agent` — backend_developer (self-review: проверь свой код)
- [ ] Шаг 3: `agent` — code_reviewer_backend (ревью кода)
- [ ] Шаг 4: `quality_gate` — `make check` (phpunit + psalm + deptrac)
- [ ] `fix_iterations` на цикле implement → review (max 3)
- [ ] Unit/Integration-тест: цепочка загружается и выполняется корректно

### 🟡 Should Have (Желательно)
- [ ] Роль и task для каждого agent-шага параметризуются через chain context (не захардкожены)
- [ ] Цепочка `task-hotfix` (ветка от tag, без architect-шага)

### 🟢 Could Have (Опционально)
- [ ] `tool`-шаг для `git diff --stat` перед review (если TASK-feat-tool-step-type готов)

### ⚫ Won't Have (Не будем делать)
- [ ] git commit/push/PR внутри цепочки
- [ ] Branching/conditional logic
- [ ] Resume с произвольного шага
- [ ] Template variables

## 4. Implementation Plan (План реализации)
1. [ ] Определить роли для каждого agent-шага из существующих ролей `docs/agents/roles/team/`
2. [ ] Написать `task-implement` цепочку в `chains.yaml`
3. [ ] Настроить `fix_iterations` на цикле implement → review
4. [ ] Добавить integration-тест: загрузка цепочки из YAML + валидация шагов
5. [ ] Протестировать `app:agent:orchestrate task-implement` на реальной задаче

## 5. Definition of Done (Критерии приёмки)
- [ ] `app:agent:orchestrate task-implement` запускает цепочку
- [ ] Цепочка проходит цикл: implement → self-review → review → quality_gate
- [ ] `fix_iterations` корректно циклит implement → review (max 3)
- [ ] Если `make check` падает — цикл повторяется (до fix_iterations)
- [ ] Psalm, PHPUnit зелёные

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
php bin/console app:agent:orchestrate task-implement
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Зависит от:** TASK-feat-tool-step-type (если нужны tool-шаги внутри)
- **Хрупкость шаблонов (Локи):** цепочка может не покрыть все edge cases (конфликты, WIP) — начать с PoC, итерировать
- **Роли:** нужно подобрать существующие роли или создать tasks для agent-шагов

## 8. Sources (Источники)
- [ ] [chains.yaml](../../config/chains.yaml)
- [ ] [ChainTypeEnum](../src/Module/Orchestrator/Domain/)
- [ ] [roles/team/](../../docs/agents/roles/team/)
- [ ] [Отчёт Локи](../../docs/agents/reports/system-architect/2026-05-10_blind-spots-task-workflow-chains.md)

## 9. Comments (Комментарии)
PoC — начинаем с одной цепочки `task-implement`. Если опыт успешный — добавить `task-hotfix`. Git-операции пока оставляем тимлиду (Локи прав — нет idempotency).

## Инструкции для сабагента

**Ветка:** task/feat-task-implement-chain (уже создана и активна)
**PR:** будет создан draft PR из task/feat-task-implement-chain в task/epic-feat-subagent-delegation-and-task-chains

### Порядок действий
1. Переключись в ветку `task/feat-task-implement-chain`: `git checkout task/feat-task-implement-chain`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](../../docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `vendor/bin/phpunit`, `vendor/bin/psalm`.
6. Сделай `git push`.

### Важно
- Зависимость TASK-feat-tool-step-type уже выполнена — тип шага `tool` доступен
- Цепочка `task-implement` — типа `static` в `chains.yaml`
- Шаги: agent (implement) → agent (self-review) → agent (review) → quality_gate (make check)
- fix_iterations на цикле implement → review (max 3)

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-10 | Тимлид Алекс (pi) | Создание задачи |
