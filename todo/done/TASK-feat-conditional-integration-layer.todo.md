---
type: feat
created: 2026-05-01
value: V3
complexity: C2
priority: P1
depends_on: TASK-feat-conditional-execution-strategy
epic: EPIC-sprint-8-conditional-branching
author: system_analyst_sherlock (Шерлок)
assignee: Бэкендер Левша
branch: task/feat-conditional-integration-layer
pr:
status: done
---

# TASK-feat-conditional-integration-layer: Integration-слой для Conditional Branching

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда `ConditionalExecutionStrategy` реализована как третья стратегия, я хочу создать Integration-слой по тому же паттерну, что StaticExecution (ACL, DTO mapping, cross-module wiring), чтобы валидировать G6: Integration-паттерн масштабируется на ≥2 стратегии без God-interface.

### Goal (Цель по SMART)
Создать Integration-слой для Conditional Branching: Integration [`Service`](../../docs/conventions/core_patterns/service.md) по конвенциям проекта (не Port/Adapter). Integration-паттерн воспроизводится на 3-й стратегии без God-interface (≤15 методов, < 200 LOC). Integration-тесты с реальными YAML-файлами, содержащими `when:` conditions. Срок: Sprint 8 (финальная задача).

## 2. Context and Scope (Контекст и Границы)
### Где делаем
- `src/Module/Orchestrator/Integration/` — Integration-слой Orchestrator (если Conditional = часть Orchestrator)
- Или `src/Module/ConditionalExecution/Integration/` — если выделен в отдельный модуль (решение на основе результатов G6 validation)
- `src/Module/StaticExecution/Integration/` — референс Integration-паттерна (2 Integration Service)

### Текущее поведение
- StaticExecution Integration: 2 Integration Service (`RunAgentService`, `FormatPromptService`) в `src/Module/StaticExecution/Integration/`
- StaticExecutionStrategy выполняет маппинг `StaticChainResultVo` → `OrchestrateChainResultDto` внутри себя (в `toResultDto()`)
- Integration-паттерн: Domain interface → Integration implementation → cross-module wiring
- Deptrac: 3 модуля (AgentRunner, Orchestrator, StaticExecution)

### Границы (Out of Scope)
- Не выделяем ConditionalExecution в отдельный модуль (решение — после G6 validation)
- Не меняем StaticExecution Integration (референс)
- Не создаём новый модуль без обоснования

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] Integration [`Service`](../../docs/conventions/core_patterns/service.md) для ConditionalExecution (по конвенциям — Integration Layer, не Port/Adapter):
  - ConditionalExecutionStrategy в Orchestrator: `ExecuteConditionalStepServiceInterface` (Domain/Service/) → `ExecuteConditionalStepService` (Infrastructure/)
  - Wiring через `ConditionalExecutionStrategy` в Application
- [x] G6 Validation: Integration-паттерн воспроизводится на 3-й стратегии:
  - Integration Service `ExecuteConditionalStepService`: 138 LOC < 200
  - `ConditionalExecutionStrategy`: 4 public метода ≤ 15
  - Тот же паттерн, что StaticExecution Integration
- [x] Integration-тесты с реальными YAML-файлами:
  - Цепочка с `when:` conditions → execution → correct branching ✅
  - Цепочка с `when: ... == true` → step executed ✅
  - Цепочка с `when: ... == false` → step skipped ✅
  - Обратная совместимость: static chain без `when:` → StaticExecutionStrategy ✅
- [x] Deptrac green

### 🟡 Should Have (Желательно)
- [ ] Документация Integration-паттерна для 3 стратегий (валидация G6 как завершённая)
- [x] Integration-тест на end-to-end: YAML → ChainLoader → CommandHandler → ConditionalExecutionStrategy → result
- [ ] Обновить `docs/guide/architecture.md` — Conditional Branching Integration

### 🟢 Could Have (Опционально)
- [ ] Deptrac rules для Integration-слоя (если separate module)

### ⚫ Won't Have (Не будем делать)
- [ ] Выделение ConditionalExecution в отдельный модуль (решение — после G6)
- [ ] Dynamic split
- [ ] Parallel execution

## 4. Implementation Plan (План реализации)

1. [x] Изучить контекст: ConditionalExecutionStrategy, EvaluateConditionService, ExecuteConditionalStepService, StaticExecution Integration
2. [x] Добавить conditional chain fixtures в `tests/Integration/_fixtures/test_chains.yaml`
3. [x] Создать `StubConditionalAgentService` для Orchestrator `RunAgentServiceInterface`
4. [x] Создать `ConditionalChainIntegrationTest` с 6 тестами:
   - Quality gate passed → step executed
   - Quality gate failed → step skipped
   - Mixed unconditional + conditional steps
   - Explicit `type: conditional` in YAML
   - Backwards compatibility: static chain with both strategies
   - Resume throws LogicException
5. [x] G6 Validation: Integration Service < 200 LOC, ≤15 public methods
6. [x] Запустить PHPUnit (764 tests OK) и Psalm (no errors)
7. [x] Закоммитить и запушить

## 5. Definition of Done (Критерии приёмки)
- [x] Integration [`Service`](../../docs/conventions/core_patterns/service.md) создан по конвенциям Integration Layer
- [x] G6 Validation: Integration-паттерн масштабируется на 3-ю стратегию:
  - Integration Service < 200 LOC, ≤15 public методов
  - Тот же паттерн, что StaticExecution (ACL + DTO mapping)
- [x] Integration-тесты с реальными YAML-файлами проходят (6 тестов, 51 assertions)
- [x] End-to-end: YAML с `when:` → CommandHandler → ConditionalExecutionStrategy → корректное ветвление
- [x] Обратная совместимость: static и dynamic chains работают без изменений
- [x] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные
- [x] Deptrac green (no new violations, pre-existing are unrelated)

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
```

## 7. Risks and Dependencies (Риски и зависимости)
- **R-6 (критический):** Integration-паттерн может не масштабироваться на Conditional Branching. Если паттерн треснет:
  - План A: ConditionalExecutionStrategy живёт внутри Orchestrator без отдельного Integration-слоя
  - План B: refactor Integration-паттерна (более общий ACL framework)
  - План C: merge Static обратно в Orchestrator (drastic)
- **Зависимость:** TASK-feat-conditional-execution-strategy (ConditionalExecutionStrategy реализована)

## 8. Sources (Источники)
- [ ] [Конвенция: Integration Layer](../../docs/conventions/layers/integration.md)
- [ ] [Конвенция: Service](../../docs/conventions/core_patterns/service.md)
- [ ] [ADR-008: Shared Kernel Contract](../../docs/adr/008-shared-kernel-contract.md)
- [ ] [StaticExecution Integration](../../../src/Module/StaticExecution/Integration/) — референс паттерна
- [ ] [Roadmap: G6 trigger](../../docs/releases/ROADMAP-2026-Q2-Q3.md) — Integration-паттерн работает для ≥2 стратегий

## 9. Comments (Комментарии)
- Это задача — **точка валидации G6**: Integration-паттерн должен масштабироваться на 3-ю стратегию без God-interface. Если валидация провалена — это стратегический сигнал к пересмотру Integration-паттерна (brainstorm #2, решение #7).
- Критерий успеха G6 (из протокола brainstorm #2): «Integration-слой для второй стратегии создан по тому же паттерну без God-interface на 15 методов». Для 3-й стратегии — тот же критерий.
- Integration [`Service`](../../docs/conventions/core_patterns/service.md) — по конвенциям Integration Layer (координирует работу между модулями, реагирует на доменные события, не содержит бизнес-логики). НЕ Port/Adapter.

## Инструкции для сабагента

**Ветка:** task/feat-conditional-integration-layer (уже создана и активна)
**PR:** уже создан (draft) из task/feat-conditional-integration-layer в task/epic-sprint-8-conditional-branching — [PR #123](https://github.com/prikotov/task-orchestrator/pull/123)

### Порядок действий
1. Переключись в ветку `task/feat-conditional-integration-layer`: `git checkout task/feat-conditional-integration-layer`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](../../docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `vendor/bin/phpunit`, `vendor/bin/psalm`.
6. Сделай `git push`.
7. Переведи PR из draft в ready: `gh pr ready <PR_NUMBER>`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-01 | system_analyst_sherlock (Шерлок) | Создание задачи |
