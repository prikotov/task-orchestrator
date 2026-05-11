---
epic: EPIC-refactor-step-runner-strategy
title: Рефакторинг ExecuteStaticStepService: стратегия + фабрика
assignee: Бэкендер Левша (pi)
branch: task/refactor-step-runner-strategy
pr:
status: in_progress
created: 2026-05-10
---

# Рефакторинг ExecuteStaticStepService: стратегия + фабрика

## Контекст

`ExecuteStaticStepService` перегружен — содержит логику выполнения трёх типов шагов:
1. **Agent step** — контекст, fallback, retry, context truncation (~50 строк)
2. **Quality gate step** — делегирует `QualityGateRunnerInterface` (~25 строк)
3. **Tool step** — делегирует `ToolStepRunnerInterface` (~25 строк)

Плюс `RunStaticChainService::executeStep()` вручную диспетчирует по типу: `isQualityGate()` → `isTool()` → иначе agent. Это и есть место для фабрики.

## Цель

Декомпозировать `ExecuteStaticStepService` на:
- `StepRunnerInterface` — интерфейс стратегии выполнения одного шага
- `AgentStepRunner` — выполнение agent-шагов (runAgentStep, applyFallback, truncateRequestContext)
- `QualityGateStepRunner` — выполнение quality_gate-шагов (runQualityGate)
- `ToolStepRunner` — выполнение tool-шагов (runToolStep)
- `StepRunnerResolver` — фабрика, которая по `ChainStepTypeEnum` отдаёт нужный `StepRunnerInterface`

## Что менять

### 1. Новый интерфейс `StepRunnerInterface`

```
src/Module/ChainExecution/Domain/Service/Static/Step/StepRunnerInterface.php
```

```php
interface StepRunnerInterface
{
    public function supports(ChainStepTypeEnum $type): bool;
    public function run(ExecutionStepVo $step, StepContextVo $context): StaticStepResultVo;
}
```

Где `StepContextVo` — новый VO, содержащий всё необходимое для выполнения шага:
- task, workingDir, timeout, previousContext, iterationNumber, roleConfig, noContextFiles

### 2. Три реализации StepRunnerInterface

**`AgentStepRunner`** — забирает из `ExecuteStaticStepService`:
- `runAgentStep()`
- `applyFallback()`
- `truncateRequestContext()`
- `createAgentResultFromStep()` (если используется)

Зависимости: `RunAgentServiceInterface`, `ResolveChainRunnerServiceInterface`, `FormatPromptServiceInterface`

**`QualityGateStepRunner`** — забирает:
- `runQualityGate()`

Зависимости: `QualityGateRunnerInterface`

**`ToolStepRunner`** — забирает:
- `runToolStep()`

Зависимости: `ToolStepRunnerInterface`

⚠️ Имя `ToolStepRunnerInterface` (существующий интерфейс) и `ToolStepRunner` (новая стратегия) — конфликт имён. Новую стратегию назвать `ToolStepRunnerStrategy` или положить в подпространство `Step\ToolStepRunner`.

### 3. `StepRunnerResolver` — фабрика

```
src/Module/ChainExecution/Domain/Service/Static/Step/StepRunnerResolver.php
```

Принимает `iterable<StepRunnerInterface>` (tagged), по `ChainStepTypeEnum` находит подходящий runner.

### 4. Упразднить `ExecuteStaticStepService`

Все методы разнесены по стратегиям. Класс удалить.

`RunStaticChainService` вместо `ExecuteStaticStepService` использует `StepRunnerResolver`.

### 5. Обновить `RunStaticChainService::executeStep()`

Убрать if-elseif диспетчеризацию. Заменить на:
```php
$runner = $this->stepRunnerResolver->resolve($step->getType());
$stepResult = $runner->run($step, $context);
```

## Чего НЕ трогать

- `RunStaticChainService` — общую логику (processStep, handlePostStep, fixIterations, budget) не менять
- `ExecuteStaticStepService::createAgentResultFromStep()` — если используется вне — перенести в `AgentStepRunner` или в VO-фабрику
- Интерфейсы `QualityGateRunnerInterface`, `ToolStepRunnerInterface`, `RunAgentServiceInterface` — не менять
- `config/services.yaml` — обновить алиасы при необходимости

## Тесты

1. Переписать `ExecuteStaticStepServiceToolTest` → `ToolStepRunnerStrategyTest` (unit)
2. Добавить `AgentStepRunnerTest` (unit) — agent + fallback
3. Добавить `QualityGateStepRunnerTest` (unit)
4. Добавить `StepRunnerResolverTest` (unit) — резолв по типу
5. Обновить integration-тесты (`StaticChainIntegrationTest`, `TaskImplementChainIntegrationTest`, `ConditionalChainIntegrationTest`) — использовать новые классы
6. Все существующие тесты должны проходить

## Критерии приёмки

- [ ] `ExecuteStaticStepService` удалён
- [ ] `StepRunnerInterface` + 3 реализации + `StepRunnerResolver` созданы
- [ ] `RunStaticChainService` использует `StepRunnerResolver`
- [ ] `StepContextVo` создан (или метод `run()` принимает нужные параметры напрямую)
- [ ] PHPUnit: все тесты зелёные
- [ ] Psalm: 0 errors
- [ ] Deptrac: 0 violations

## Зависимости

Нет зависимостей от других задач.

## Change History (История изменений)

| Дата       | Автор         | Изменение                         |
|------------|---------------|-----------------------------------|
| 2026-05-10 | Тимлид Алекс  | Создание задачи                   |
