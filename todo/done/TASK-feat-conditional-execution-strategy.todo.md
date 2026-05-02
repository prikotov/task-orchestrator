---
type: feat
created: 2026-05-01
value: V3
complexity: C3
priority: P1
depends_on: TASK-refactor-static-audit-isolation, TASK-feat-conditional-yaml-dsl
epic: EPIC-sprint-8-conditional-branching
author: system_analyst_sherlock (Шерлок)
assignee: Бэкендер Левша
branch: task/feat-conditional-execution-strategy
pr:
status: in_progress
---

# TASK-feat-conditional-execution-strategy: ConditionalExecutionStrategy

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда YAML DSL поддерживает `when:` expressions, а [`ExecutionStrategyInterface`](../../src/Module/Orchestrator/Application/Service/Chain/ExecutionStrategyInterface.php) подтверждён ADR-006 как точка расширения для conditional branching, я хочу реализовать третью стратегию `ConditionalExecutionStrategy`, чтобы цепочки с `when:` conditions выполнялись с ветвлением по результатам предыдущих шагов.

### Goal (Цель по SMART)
Реализовать `ConditionalExecutionStrategy` — третью реализацию [`ExecutionStrategyInterface`](../../src/Module/Orchestrator/Application/Service/Chain/ExecutionStrategyInterface.php). Стратегия поддерживает `when:` branching logic: evaluate condition → execute/skip step. Автоматически подхватывается tagged iterator в [`OrchestrateChainCommandHandler`](../../src/Module/Orchestrator/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommandHandler.php). Unit-тесты ≥80%. Срок: Sprint 8 (третья задача).

## 2. Context and Scope (Контекст и Границы)
### Где делаем
- `src/Module/Orchestrator/Application/Service/Chain/` — новый файл `ConditionalExecutionStrategy.php`
- `src/Module/Orchestrator/Domain/` — возможный новый [`Service`](../../docs/conventions/core_patterns/service.md) для condition evaluation
- `src/Module/Orchestrator/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommandHandler.php` — диспетчер (не меняется, tagged iterator уже есть)

### Текущее поведение
- [`ExecutionStrategyInterface`](../../src/Module/Orchestrator/Application/Service/Chain/ExecutionStrategyInterface.php) — 3 метода: `execute()`, `resume()`, `supports()`
- [`StaticExecutionStrategy`](../../src/Module/Orchestrator/Application/Service/Chain/StaticExecutionStrategy.php) — delegates to `ExecuteStaticChainServiceInterface`, `supports()` → `ChainTypeEnum::staticType`
- [`DynamicExecutionStrategy`](../../src/Module/Orchestrator/Application/Service/Chain/DynamicExecutionStrategy.php) — delegates to Dynamic path, `supports()` → `ChainTypeEnum::dynamicType`
- [`OrchestrateChainCommandHandler`](../../src/Module/Orchestrator/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommandHandler.php) — диспетчер (58 LOC), итерирует стратегии через `resolveStrategy()`

### Границы (Out of Scope)
- Не меняем `ExecutionStrategyInterface` (контракт стабилен)
- Не меняем `OrchestrateChainCommandHandler` (tagged iterator уже работает)
- Не создаём отдельный модуль ConditionalExecution (решение — после G6 validation)
- Integration-слой — следующая задача

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `ConditionalExecutionStrategy` реализует [`ExecutionStrategyInterface`](../../src/Module/Orchestrator/Application/Service/Chain/ExecutionStrategyInterface.php):
  - `supports(ChainDefinitionVo $chain): bool` — `true` для `ChainTypeEnum::conditionalType`
  - `execute(ChainDefinitionVo $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto` — выполнение с ветвлением
  - `resume()` — `LogicException` (MVP: conditional chains не поддерживают resume)
- [ ] Condition evaluator [`Service`](../../docs/conventions/core_patterns/service.md): принимает `ConditionExpressionVo` + context (results of previous steps) → `bool`
  - Evaluator в Domain-слое (чистая логика, без I/O)
  - Context = map of step results: `{stepName: {passed: bool, exitCode: int, status: string}}`
- [ ] Step execution: iterate steps → evaluate `when:` → execute or skip → collect results
  - Skipped steps: записываются в `OrchestrateChainResultDto` с маркером `skipped` (новое поле в [`StepResultDto`](../../src/Module/Orchestrator/Application/UseCase/Command/OrchestrateChain/StepResultDto.php))
- [ ] Зарегистрировать стратегию в DI (tagged iterator `ExecutionStrategyInterface`)

### 🟡 Should Have (Желательно)
- [ ] Unit-тесты ≥80% на ConditionalExecutionStrategy
- [ ] Unit-тесты на condition evaluator (покрытие всех операторов)
- [ ] Логирование: какой step skipped по какому condition

### 🟢 Could Have (Опционально)
- [ ] `else:` branch: шаг без `when:` в conditional chain выполняется, если все предыдущие `when:` в группе — false

### ⚫ Won't Have (Не будем делать)
- [ ] Resume для conditional chains
- [ ] Nested conditions
- [ ] Parallel execution в ветках

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем (агентом) перед стартом.*
1. [ ] ...

## 5. Definition of Done (Критерии приёмки)
- [ ] `ConditionalExecutionStrategy` реализует все 3 метода [`ExecutionStrategyInterface`](../../src/Module/Orchestrator/Application/Service/Chain/ExecutionStrategyInterface.php)
- [ ] Condition evaluator корректно вычисляет `steps.<name>.passed`, `steps.<name>.exitCode`, `result.status`
- [ ] Skipped steps отражены в результате (`StepResultDto` с маркером)
- [ ] Стратегия подхватывается `resolveStrategy()` в [`OrchestrateChainCommandHandler`](../../src/Module/Orchestrator/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommandHandler.php) для `ChainTypeEnum::conditionalType`
- [ ] Unit-тесты ≥80% покрытия
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск:** Condition evaluator может потребовать expression language (symfony/expression-language) для сложных условий. Митигация: MVP = простой парсер для `==`, `!=`, без внешних зависимостей.
- **Зависимость:** TASK-refactor-static-audit-isolation ✅ (audit isolation), TASK-feat-conditional-yaml-dsl (ConditionExpressionVo, ChainTypeEnum::conditionalType)

## 8. Sources (Источники)
- [ ] [ADR-006: ExecutionStrategy composition](../../docs/adr/006-execution-strategy-composition.md)
- [ ] [ExecutionStrategyInterface](../../src/Module/Orchestrator/Application/Service/Chain/ExecutionStrategyInterface.php)
- [ ] [StaticExecutionStrategy](../../src/Module/Orchestrator/Application/Service/Chain/StaticExecutionStrategy.php) — референс для третьей стратегии
- [ ] [OrchestrateChainCommandHandler](../../src/Module/Orchestrator/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommandHandler.php) — диспетчер

## 9. Comments (Комментарии)
- `ConditionalExecutionStrategy` логически ближе к `StaticExecutionStrategy` (линейное выполнение + ветвление), чем к `DynamicExecutionStrategy` (фасилитатор + участники). Можно было бы расширить StaticExecution, но ADR-006 фиксирует: каждая стратегия — отдельный класс.
- Architecture decision: ConditionalExecutionStrategy live в `Orchestrator\Application\Service\Chain\` (как Static и Dynamic) или в отдельном модуле? Для MVP — в Orchestrator. Выделение в отдельный модуль — после G6 validation.
- Resume не поддерживается в MVP. Conditional chain = один проход. Это ограничение зафиксировать в ADR-009.

## Инструкции для сабагента

**Ветка:** task/feat-conditional-execution-strategy (уже создана и активна)
**PR:** уже создан (draft) из task/feat-conditional-execution-strategy в task/epic-sprint-8-conditional-branching — [PR #122](https://github.com/prikotov/task-orchestrator/pull/122)

### Порядок действий
1. Переключись в ветку `task/feat-conditional-execution-strategy`: `git checkout task/feat-conditional-execution-strategy`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `vendor/bin/phpunit`, `vendor/bin/psalm`.
6. Сделай `git push`.
7. Переведи PR из draft в ready: `gh pr ready <PR_NUMBER>`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-01 | system_analyst_sherlock (Шерлок) | Создание задачи |
