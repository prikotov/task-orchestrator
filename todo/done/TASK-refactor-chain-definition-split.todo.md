---
type: refactor
created: 2026-05-02
value: V2
complexity: C3
priority: P1
depends_on:
epic: EPIC-sprint-10-hooks-debt-cleanup
author: system_analyst_sherlock (Шерлок)
assignee: backend_developer_levsha
branch:
pr:
status: done
---

# TASK-refactor-chain-definition-split: ChainDefinitionVo split завершение

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда [`ChainDefinitionVo`](../../src/Module/Orchestrator/Domain/ValueObject/ChainDefinitionVo.php) содержит 546 строк, 17 параметров конструктора и 3 фабричных метода (`createFromSteps`, `createFromDynamic`, `createFromConditionalSteps`) — каждая стратегия видит поля, которые ей не нужны. ConditionalExecutionStrategy вызывает `getFacilitator()`, который актуален только для dynamic. StaticExecutionStrategy видит `getMaxIterations()`, который только для fix_iterations. Я хочу расщепить God-VO на Static/Dynamic/Conditional sub-VO с общим `ChainDefinitionInterface`, чтобы каждая стратегия зависела только от нужных ей данных — ISP compliance.

### Goal (Цель по SMART)
Завершить расщепление [`ChainDefinitionVo`](../../src/Module/Orchestrator/Domain/ValueObject/ChainDefinitionVo.php) (546 LOC, 17 параметров): создать `StaticChainDefinitionVo`, `DynamicChainDefinitionVo`, `ConditionalChainDefinitionVo` с общим `ChainDefinitionInterface` ([`SharedChainDefinitionVo`](../../src/Module/Orchestrator/Domain/ValueObject/SharedChainDefinitionVo.php) уже существует). Обновить все потребители (~15 файлов). Заложено в [ADR-008](../../docs/adr/008-shared-kernel-contract.md), не реализовано. Срок: 1.5 дня.

## 2. Context and Scope (Контекст и Границы)
### Где делаем
- [`src/Module/Orchestrator/Domain/ValueObject/ChainDefinitionVo.php`](../../src/Module/Orchestrator/Domain/ValueObject/ChainDefinitionVo.php) — God-VO 546 LOC — основной объект рефакторинга
- [`src/Module/Orchestrator/Domain/ValueObject/SharedChainDefinitionVo.php`](../../src/Module/Orchestrator/Domain/ValueObject/SharedChainDefinitionVo.php) — уже существует (shared kernel data)
- [`src/Module/Orchestrator/Application/Service/Chain/ExecutionStrategyInterface.php`](../../src/Module/Orchestrator/Application/Service/Chain/ExecutionStrategyInterface.php) — `supports()` проверяет тип ChainDefinition
- Все 3 стратегии: StaticExecutionStrategy, DynamicExecutionStrategy, ConditionalExecutionStrategy
- YamlChainLoader — фабрика ChainDefinitionVo
- Все сервисы, зависящие от ChainDefinitionVo

### Текущее поведение
- `ChainDefinitionVo` — God-VO: 546 LOC, 17 параметров конструктора, 3 фабричных метода, геттеры для static/dynamic/conditional полей одновременно
- `SharedChainDefinitionVo` создан в Sprint 4, но **нигде не используется как тип параметра**
- `ExecutionStrategyInterface::supports(ChainDefinitionVo $definition)` — каждая стратегия проверяет `$definition->getType()`
- ISP violation: ConditionalExecutionStrategy видит `getFacilitator()`, StaticExecutionStrategy видит `getMaxIterations()`

### Границы (Out of Scope)
- Физический split модулей (StaticExecution — отдельный модуль, Sprint 7 ✅)
- Изменение YAML DSL (формат не меняется, только внутреннее представление)
- Удаление ChainDefinitionVo (оставить как deprecated alias на переходный период или удалить)
- Resume implementation (только ADR в TASK-docs-resume-adr)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `ChainDefinitionInterface` в Domain — общий интерфейс с методами `getName()`, `getType()`, `getSharedDefinition(): SharedChainDefinitionVo`
- [ ] `StaticChainDefinitionVo implements ChainDefinitionInterface` — только static-specific данные (steps, maxIterations, qualityGate)
- [ ] `DynamicChainDefinitionVo implements ChainDefinitionInterface` — только dynamic-specific данные (facilitator, maxTurns, dynamicConfig)
- [ ] `ConditionalChainDefinitionVo implements ChainDefinitionInterface` — только conditional-specific данные (whenBranches)
- [ ] `ExecutionStrategyInterface::supports()` типизирован через `ChainDefinitionInterface`, не `ChainDefinitionVo`
- [ ] Все 3 стратегии принимают специализированный VO, не God-VO
- [ ] `YamlChainLoader` возвращает специализированный VO в зависимости от chain type
- [ ] Unit-тесты: каждый sub-VO покрыт, стратегии типизированы корректно
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные
- [ ] Deptrac green

### 🟡 Should Have (Желательно)
- [ ] `ChainDefinitionVo` помечен `@deprecated` с указанием на sub-VO (если не удалён)
- [ ] Migration guide в PHPDoc

### 🟢 Could Have (Опционально)
- [ ] Sub-интерфейсы: `StaticChainDefinitionInterface`, `DynamicChainDefinitionInterface`, `ConditionalChainDefinitionInterface` (для strategy-specific dependencies)

### ⚫ Won't Have (Не будем делать)
- [ ] Физический split модулей (уже сделан в Sprint 7 для Static)
- [ ] Изменение YAML DSL
- [ ] Resume implementation
- [ ] Sub-agent pattern

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем (агентом) перед стартом.*

1. [ ] Проанализировать `ChainDefinitionVo` (546 LOC) — выделить static/dynamic/conditional-specific поля и методы
2. [ ] Создать `ChainDefinitionInterface` в Domain — общие методы (`getName()`, `getType()`, `getSharedDefinition()`)
3. [ ] Создать `StaticChainDefinitionVo implements ChainDefinitionInterface` — static-specific fields + constructor + getters
4. [ ] Создать `DynamicChainDefinitionVo implements ChainDefinitionInterface` — dynamic-specific fields + constructor + getters
5. [ ] Создать `ConditionalChainDefinitionVo implements ChainDefinitionInterface` — conditional-specific fields + constructor + getters
6. [ ] Обновить `ExecutionStrategyInterface::supports(ChainDefinitionInterface $definition)` — типизация через интерфейс
7. [ ] Обновить `StaticExecutionStrategy::supports()` + `execute()` — принять `StaticChainDefinitionVo`
8. [ ] Обновить `DynamicExecutionStrategy::supports()` + `execute()` — принять `DynamicChainDefinitionVo`
9. [ ] Обновить `ConditionalExecutionStrategy::supports()` + `execute()` — принять `ConditionalChainDefinitionVo`
10. [ ] Обновить `YamlChainLoader` — `load()` возвращает специализированный VO по chain type
11. [ ] Обновить все потребители (~15 файлов) — DI config, command handlers, services
12. [ ] Пометить `ChainDefinitionVo` как `@deprecated` или удалить (по результату анализа)
13. [ ] Unit-тесты: каждый sub-VO, YamlChainLoader, стратегии с новыми типами
14. [ ] Psalm + phpunit — зелёные

### Структура файлов
```
src/Module/Orchestrator/Domain/
  ChainDefinitionInterface.php          — новый (если не существует)
  ValueObject/
    ChainDefinitionVo.php              — deprecated / удалён
    SharedChainDefinitionVo.php        — без изменений
    StaticChainDefinitionVo.php        — новый
    DynamicChainDefinitionVo.php       — новый
    ConditionalChainDefinitionVo.php   — новый
src/Module/Orchestrator/Application/
  Service/Chain/
    ExecutionStrategyInterface.php     — изменён (тип параметра)
    StaticExecutionStrategy.php        — изменён
    DynamicExecutionStrategy.php       — изменён
    ConditionalExecutionStrategy.php   — изменён
src/Module/Orchestrator/Infrastructure/
  Service/Chain/
    YamlChainLoader.php                — изменён (фабрика)
config/services.yaml                   — обновлён (DI wiring)
tests/Unit/Module/Orchestrator/        — обновлён + новые тесты
```

## 5. Definition of Done (Критерии приёмки)
- [ ] `ChainDefinitionInterface` введён с общими методами
- [ ] `StaticChainDefinitionVo`, `DynamicChainDefinitionVo`, `ConditionalChainDefinitionVo` созданы и реализуют интерфейс
- [ ] `ChainDefinitionVo` (546 LOC) — deprecated или удалён
- [ ] Все 3 стратегии типизированы через специализированные VO
- [ ] `YamlChainLoader` возвращает специализированный VO
- [ ] Все потребители обновлены (~15 файлов)
- [ ] Unit-тесты покрывают все sub-VO и стратегии
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные
- [ ] Deptrac green

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Module/Orchestrator/
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Blast radius ~15 файлов.** ChainDefinitionVo используется в стратегиях, YamlChainLoader, DI config, command handlers, tests. Массовое изменение требует аккуратности.
- **Обратная совместимость.** Если ChainDefinitionVo удаляется — все ссылки должны быть обновлены. Если deprecated — дублирование на переходный период.
- **Тесты:** существующие тесты стратегий и YamlChainLoader используют ChainDefinitionVo. Нужно обновить все fixture-фабрики.
- **Deptrac:** новый ChainDefinitionInterface может создать нежелательные зависимости. Проверить depfile.yaml.
- **Зависимость от ADR-008:** [ADR-008](../../docs/adr/008-shared-kernel-contract.md) уже зафиксировал Shared Kernel = chain identity (name, budget, roles). Strategy-specific data НЕ входит. Это контракт для split.

## 8. Sources (Источники)
- [ ] [Roadmap: Sprint 10](../../docs/releases/ROADMAP-2026-Q2-Q3.md) — Sprint 10, Задача 2
- [ ] [Анализ Локи: ChainDefinitionVo — God-VO](../../docs/research/analytical/loki-roadmap-review-2026-05.md) — Упущенная боль #2
- [ ] [ADR-008: Shared Kernel Contract](../../docs/adr/008-shared-kernel-contract.md) — заложенный контракт
- [ ] [Конвенции: Value Object](../../docs/conventions/core_patterns/value-object.md)
- [ ] [Конвенции: External Service](../../docs/conventions/core_patterns/external-service.md)

## 9. Comments (Комментарии)
- Pain level: 4/10 — техдолг от Sprint 4. `SharedChainDefinitionVo` создан, но оригинальный `ChainDefinitionVo` (546 LOC) не стал легче. ISP violation: каждая стратегия видит ненужные поля.
- Зависимость для `TASK-feat-hooks-post-step`: ChainStepVo (часть ChainDefinitionVo) будет обновлён при split → hooks task должен использовать обновлённый ChainStepVo.
- ADR-008 зафиксировал Shared Kernel Contract: `getName()`, `getBudget()`, `getRoleConfig()`. Strategy-specific data (steps, facilitator, whenBranches) — не входит. Это прямое руководство для split.
- 3 фабричных метода в ChainDefinitionVo (`createFromSteps`, `createFromDynamic`, `createFromConditionalSteps`) — естественная точка разделения: каждый → отдельный sub-VO constructor.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-02 | system_analyst_sherlock (Шерлок) | Создание задачи |
