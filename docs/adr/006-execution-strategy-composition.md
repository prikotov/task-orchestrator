# ADR-006: Композиция стратегий выполнения (ExecutionStrategy Composition)

| Поле        | Значение                                             |
|-------------|------------------------------------------------------|
| Статус      | Принято (реализация отложена до conditional branching) |
| Дата        | 2026-04-29                                           |
| Автор       | Архитектор (Гэндальф)                                |
| Участники   | Гэндальф, Локи, Левша, Шерлок                       |
| Источник    | Brainstorm-сессия: декомпозиция модуля Orchestrator   |

## Контекст

`OrchestrateChainCommandHandler` (328 строк) содержит два поведенческих пути: `static-chain` execution (C1) и `dynamic-loop` execution (C4). Выбор пути реализован через проверки `isDynamic()` в 5 точках кода. Dynamic path занимает ~170 строк и превращает CommandHandler в божественный объект (God object), объединяющий диспетчеризацию, оркестрацию и обработку ошибок.

Текущие проблемы:

1. **Switch-точки:** 5 мест с `isDynamic()` растут линейно с каждой новой стратегией выполнения (conditional branching, parallel execution, sub-agents).
  2. **Божественный объект CommandHandler (God object):** 328 строк, 2 совершенно разных поведенческих пути в одном классе. При добавлении conditional branching количество путей вырастет до 3, и handler станет нечитаемым.
3. **Roadmap-тренды:** анализ 16 AI-agent фреймворков (Archon, Agno, Mastra AI) показывает паттерн движка рабочих процессов (workflow engine) с типизированными стратегиями выполнения.

## Решение

Применить паттерн **«Стратегия + Композиция» (Strategy + Composition)**: выделить `ExecutionStrategyInterface` в Application-слое.

### Контракт ExecutionStrategyInterface

```php
interface ExecutionStrategyInterface
{
    public function execute(ChainDefinitionVo $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto;
    public function resume(ChainDefinitionVo $chain, OrchestrateChainCommand $command): OrchestrateChainResultDto;
    public function supports(ChainDefinitionVo $chain): bool;
}
```

### Реализации

- **`StaticExecutionStrategy`** (C1): обёртка над `static-chain` execution (~2 часа реализации).
- **`DynamicExecutionStrategy`** (C4): обёртка над `dynamic-loop` path — `execute()` + `resume()` + `finalize` + маппингом DTO, 4 зависимости в конструкторе (~1–1.5 дня).

### CommandHandler → чистый диспетчер

`OrchestrateChainCommandHandler` переписывается как диспетчер (~30 строк):

```php
protected function handle(OrchestrateChainCommand $command): OrchestrateChainResultDto
{
    $chain = $this->chainLoader->load($command->chainName);
    $strategy = $this->resolveStrategy($chain);

    return $command->isResume
        ? $strategy->resume($chain, $command)
        : $strategy->execute($chain, $command);
}
```

### Симметричность resume()

`resume()` принимает `ChainDefinitionVo` — симметрично с `execute()`. Это устраняет `ChainLoaderInterface` из DI `DynamicExecutionStrategy` (4 зависимости вместо 5), поскольку `ChainDefinitionVo` передаётся через параметр, а не загружается внутри стратегии.

### Критерий реализации

Реализация откладывается до появления задачи на **conditional branching**. ADR фиксирует направление и контракт, чтобы предотвратить повторное обсуждение (re-litigation) и «ловушку отложенного рефакторинга» (стоимость введения стратегии при 3+ типах цепочек выше, чем при 2).

## Обоснование

| Критерий                      | Текущее состояние                        | После ExecutionStrategy                |
|-------------------------------|------------------------------------------|----------------------------------------|
| Switch-точки                  | 5 проверок `isDynamic()`                 | 0 (стратегия определяется через `supports()`) |
| CommandHandler размер         | 328 строк, божественный объект (God object)        | ~30 строк, чистый диспетчер           |
| Добавление новой стратегии    | Редактирование handler + 5 switch-точек  | Новый класс стратегии, 0 изменений handler |
| Зависимости `DynamicStrategy`   | 5 (включая ChainLoaderInterface)         | 4 (ChainDefinitionVo через параметр)  |
| Тестируемость                 | Интеграционный тест 1095 строк           | Unit-тесты на каждую стратегию отдельно |

## Последствия

### Положительные

- **Принцип открытости/закрытости (OCP):** новые стратегии (conditional branching, parallel execution) добавляются без изменения существующего кода.
- CommandHandler становится читаемым (~30 строк), ответственность — только диспетчеризация.
- Каждая стратегия тестируется изолированно, без mock-наворотов 1095-строчного интеграционного теста.
- Симметричность `resume()` и `execute()` снижает количество DI-зависимостей в DynamicExecutionStrategy.

### Отрицательные

- Отложенная реализация означает, что switch-точки и божественный объект (God object) сохраняются до триггера (conditional branching).
- Существующий интеграционный тест CommandHandler (1095 строк) потребует разделения/адаптации при реализации.

### Риски

- Если conditional branching появится раньше, чем ожидается, стоимость реализации растёт за счёт параллельного рефакторинга. Митигируется фиксацией контракта в данном ADR.

## Альтернативы

1. **Наследование (типовая иерархия):** `ChainDefinition (abstract) → StaticChainDefinition / DynamicChainDefinition`. Отвергнуто — хрупкий базовый класс (fragile base class) при каждом новом подклассе. Гэндальф отозвал предложение после анализа roadmap-фич (раунд 5).

2. **Теговое/дискриминированное объединение (tagged/discriminated union):** тип-сумма для ChainDefinition. Отвергнуто — PHP не поддерживает `tagged union` нативно. Эмуляция через enum + VO добавляет сложность без пользы (benefit).

3. **«Ждать 3-ю стратегию»:** не рефакторить до появления conditional branching. Отвергнуто — «ловушка отложенного рефакторинга» (Шерлок, раунд 18): при 3 стратегиях стоимость выше из-за большего радиуса последствий (blast radius). ADR фиксирует направление сейчас, реализация — по триггеру.

4. **Полностью плагинная архитектура (full plugin architecture):** реестр стратегий с конфигурацией, хуками событий и промежуточными слоями (event hooks, middleware). Отвергнуто — избыточное усложнение (overengineering) для текущих потребностей, нет потребителя.
