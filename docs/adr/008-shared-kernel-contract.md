# ADR-008: Shared Kernel Contract

| Поле        | Значение                                             |
|-------------|------------------------------------------------------|
| Статус      | Принято                                              |
| Дата        | 2026-04-29                                           |
| Автор       | Архитектор (Гэндальф)                                |
| Участники   | Гэндальф, Локи, Левша, Шерлок                       |
| Источник    | Brainstorm-сессия: декомпозиция модуля Orchestrator   |

## Контекст

Модуль Orchestrator содержит два де-факто bounded context'а (Static и Dynamic цепочки) с 0 cross-imports в Domain-слое. `ChainDefinitionVo` (20 параметров, private-конструктор, named constructors) — единая точка входа для обоих context'ов, но при этом:
- Static-специфичные данные (шаги, конфигурация промптов) и Dynamic-специфичные данные (agent, retry-политика, бюджет) смешаны в одном VO.
- ExecutionStrategy (ADR-006) требует доступа к общим данным (name, budget, roles), но не к strategy-specific данным.
- Roadmap-сценарии (conditional branching, parallel execution) добавляют новые типы chain-specific данных.

Текущий `ChainDefinitionVo` с named constructors (`createFromSteps()`, `createFromDynamic()`) обеспечивает типобезопасность, но не формализует контракт между bounded context'ами.

## Решение

**Shared Kernel = chain identity (name, budget, roles). Strategy-specific данные НЕ входят.**

Формальный контракт определяется через `ChainDefinitionInterface` при реализации P4 (расщепление `ChainDefinitionVo`):

```php
interface ChainDefinitionInterface
{
    public function getName(): string;
    public function getBudget(): BudgetVo;
    public function getRoleConfig(): ?RoleConfigVo;
}
```

### Расширение через sub-интерфейсы

Новые стратегии добавляют данные через sub-интерфейсы, не модифицируя Shared Kernel:

```php
interface StaticChainDefinitionInterface extends ChainDefinitionInterface
{
    public function getSteps(): list<ChainStepVo>;
    public function getPromptConfiguration(): PromptConfigurationVo;
}

interface DynamicChainDefinitionInterface extends ChainDefinitionInterface
{
    public function getAgentName(): string;
    public function getRetryPolicy(): ChainRetryPolicyVo;
    public function getMaxTurns(): int;
}

// Будущие стратегии:
// interface ConditionalChainDefinitionInterface extends ChainDefinitionInterface { ... }
// interface ParallelChainDefinitionInterface extends ChainDefinitionInterface { ... }
```

### Принцип OCP для расширения

- **Добавление** новой стратегии = новый sub-интерфейс + новая `ExecutionStrategy`-реализация. Нулевые изменения в существующих интерфейсах.
- **Модификация** Shared Kernel (добавление метода в `ChainDefinitionInterface`) — только при появлении поля, общего для ВСЕХ стратегий. Это событие требует нового ADR.

### Триггер для P4

P4 (расщепление `ChainDefinitionVo` и формализация Shared Kernel) реализуется **до** ExecutionStrategy (задачи #7–9 из action plan brainstorm-сессии). Это обеспечивает:
1. Типизированный контракт для `ExecutionStrategyInterface::supports(ChainDefinitionVo $chain)`.
2. Явные границы bounded context'ов Static и Dynamic.
3. Возможность физического разделения модулей при появлении бизнес-драйвера (решение 9 brainstorm-сессии).

## Обоснование

| Критерий                            | Текущее состояние                 | После Shared Kernel Contract         |
|-------------------------------------|-----------------------------------|--------------------------------------|
| Формализация границ context'ов      | Неявная (named constructors)      | Явная (interfaces + sub-interfaces) |
| Расширение новой стратегией         | Редактирование ChainDefinitionVo  | Новый sub-интерфейс, 0 изменений    |
| Coupling между Static/Dynamic       | Один VO на 20 параметров          | Только 3 общих метода               |
| Типобезопасность в ExecutionStrategy| `instanceof` + `isDynamic()`      | `supports(ChainDefinitionInterface)` |
| Физическое разделение модулей       | Невозможно без VO-рефакторинга    | Возможно: Shared Kernel = contract   |

## Последствия

### Положительные

- **Формализованный контракт** между Static и Dynamic bounded context'ами — 3 метода вместо 20 параметров.
- **OCP-совместимое расширение:** conditional branching, parallel execution, sub-agents добавляются через sub-интерфейсы без модификации `ChainDefinitionInterface`.
- **Пререквизит для физического разделения** модулей: после P4 Static и Dynamic можно переносить в отдельные namespace/модули при появлении бизнес-драйвера.
- **Чистая типизация в ExecutionStrategy:** `supports()` работает с `ChainDefinitionInterface`, а не с 20-параметровым VO.

### Отрицательные

- P4 — нетривиальная задача: расщепление `ChainDefinitionVo` затрагивает всех потребителей (est. 20+ файлов).
- До реализации P4 контракт остаётся неявным (named constructors), что не формализует архитектурную дисциплину в коде.

### Риски

- **Over-splitting:** при проектировании sub-интерфейсов можно ошибочно отнести общее поле к strategy-specific. Митигируется: в sub-интерфейс попадают только поля, которые не нужны другим стратегиям.
- **Security Policy как cross-cutting concern:** будущий модуль Security Policy может потребовать доступа к данным обеих стратегий. Shared Kernel при этом разрастается. Это открытый вопрос (владелец: Локи, задача #14 из action plan).

## Альтернативы

1. **Shared Kernel = все данные ChainDefinitionVo:** формальный контракт включает все 20 параметров. Отвергнуто — defeats the purpose: strategy-specific данные (шаги, retry-политика) не являются общими между context'ами и будут загрязнять контракт.

2. **ISP-рефакторинг (Interface Segregation) без Shared Kernel:** расщепить ChainDefinitionVo на интерфейсы по ISP, но не выделять общий root. Отвергнуто — при добавлении новой стратегии нет гарантии, что «общие» методы действительно общие; формальный root-интерфейс обеспечивает дисциплину.

3. **Не формализовать контракт до необходимости:** оставить `ChainDefinitionVo` как есть, не создавать интерфейсы. Отвергнуто — ExecutionStrategy (ADR-006) требует типизированного входа, а Roadmap-сценарии добавляют как минимум 2 новых типа цепочек. Без формального контракта `supports()` превращается в type-checking hack.
