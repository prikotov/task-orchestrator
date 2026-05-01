---
type: refactor
created: 2026-04-30
value: V3
complexity: C3
priority: P2
depends_on: TASK-refactor-shared-reorg, TASK-refactor-session-logger-split, TASK-refactor-dynamic-loop-decomposition
epic: EPIC-refactor-orchestrator-p3
author: pi
assignee: Бэкендер Левша
branch: task/refactor-static-execution-split
pr:
status: in_progress
---

# TASK-refactor-static-execution-split: Физический split StaticExecution в отдельный модуль

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда Static/ и Dynamic/ имеют нулевые cross-imports, а Integration-паттерн нужно валидировать до Conditional Branching (Sprint 8), я хочу физически вынести StaticExecution в отдельный модуль, чтобы проверить масштабируемость паттерна декомпозиции на реальном примере.

### Goal (Цель по SMART)
Перенести ~8 файлов StaticExecution (4 сервиса + 1 entity + 3 VO, ~1270 LOC) в `src/Module/StaticExecution/`. Создать Integration-слой (ACL, DTO mapping, cross-module wiring). Настроить Deptrac на 3 модуля (AgentRunner, Orchestrator, StaticExecution).

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/Orchestrator/Domain/Service/Chain/Static/` → `src/Module/StaticExecution/`
*   **Текущее поведение:** Static-стратегия — часть модуля Orchestrator, 0 cross-imports с Dynamic/
*   **Границы (Out of Scope):**
    *   Dynamic остаётся в Orchestrator
    *   Conditional Branching — Sprint 8

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `src/Module/StaticExecution/` создан с Domain/Application/Infrastructure слоями
- [ ] StaticExecution entity + StaticStepResultVo + StaticProcessResultVo + StaticChainResultVo перенесены
- [ ] ExecuteStaticStepService, ExecuteStaticChainServiceInterface и сопутствующие сервисы перенесены
- [ ] Integration-слой между Orchestrator и StaticExecution (ACL)
- [ ] Deptrac настроен на 3 модуля, green
- [ ] Все существующие тесты проходят

### 🟡 Should Have (Желательно)
- [ ] Integration-тест на cross-module wiring
- [ ] Документация Integration-паттерна для G6-валидации

### 🟢 Could Have (Опционально)
- [ ] Deptrac rules для Integration-слоя

### ⚫ Won't Have (Не будем делать)
- [ ] Split Dynamic
- [ ] Conditional Branching

## 5. Definition of Done (Критерии приёмки)
- [ ] StaticExecution — отдельный модуль с полной DDD-структурой
- [ ] Deptrac green на 3 модулях
- [ ] Integration-слой < 200 LOC
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные
- [ ] Критерий G6: Integration-паттерн задокументирован для проверки со второй стратегией
- [ ] Обновить Roadmap: статус AI#17 `📋` → `✅ Done`, добавить ссылку на задачу и PR

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск R-6:** Integration-паттерн может не масштабироваться на Conditional Branching — валидация в Sprint 8
- **Зависимости:** ExecutionStrategyInterface (Sprint 3) ✅, ChainDefinitionVo split (Sprint 4) ✅, Shared/ reorg (AI#16), RunDynamicLoopService decomposition (AI#11)

## 8. Sources (Источники)
- [ ] [Roadmap AI#17](../../docs/releases/ROADMAP-2026-Q2-Q3.md)
- [ ] [Протокол brainstorm #2](../var/sessions/brainstorm/2026-04-30_16-02-26/result.md) — компромисс Sprint 7
- [ ] [ADR-008: Shared Kernel Contract](../../docs/adr/008-shared-kernel-contract.md)

## 9. Comments (Комментарии)
Roadmap Sprint 7 (вторая половина). Новая задача из brainstorm #2. Цель — не «Deptrac green», а валидация Integration-паттерна для масштабирования на ≥2 стратегии (G6). Sprint 8 (Conditional Branching) — точка валидации.

## Инструкции для сабагента

**Ветка:** task/refactor-static-execution-split (уже создана и активна)
**PR:** уже создан (draft) из task/refactor-static-execution-split в task/epic-refactor-orchestrator-p3 — [PR #<PR_NUMBER>](<PR_LINK>)

### Порядок действий
1. Переключись в ветку `task/refactor-static-execution-split`: `git checkout task/refactor-static-execution-split`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `make check`.
6. Сделай `git push`.
7. Переведи PR из draft в ready: `gh pr ready <PR_NUMBER>`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-30 | pi | Создание задачи |
