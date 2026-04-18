# ADR-001: Декомпозиция на модули AgentRunner и Orchestrator

| Поле        | Значение                    |
|-------------|-----------------------------|
| Статус      | Принято                      |
| Дата        | 2026-04-17                  |
| Автор       | Архитектор (Гэндальф)       |

## Контекст

Бандл `TaskOrchestrator` реализует оркестрацию AI-агентов: запуск агентов через CLI-инструменты и управление цепочками (static/dynamic). Изначально код был организован в единую DDD-структуру с тремя слоями (Domain/Application/Infrastructure) без модульного разделения.

Проблемы монолитной структуры:

1. **Скрытая coupling:** Domain-слой содержал интерфейсы движка (`AgentRunnerInterface`) и интерфейсы оркестрации (`ChainLoaderInterface`) в одном пространстве имён, хотя они относятся к разным областям ответственности.
2. **Зависимость от VO:** `RunStaticChainService` и `RunDynamicLoopService` использовали `AgentRunRequestVo` / `AgentResultVo` напрямую, хотя эти типы относятся к движку агента, а не к бизнес-логике оркестрации.
3. **Трудность расширения:** Добавление нового движка (например, Codex CLI) требовало понимания всей Domain-области, а не только контракта движка.

## Решение

Разделить бандл на два модуля со своими DDD-слоями:

### Модуль AgentRunner

**Ответственность:** запуск AI-агента через конкретный CLI-инструмент.

- Domain: `AgentRunnerInterface`, `AgentRunnerRegistryServiceInterface`, VO (`AgentResultVo`, `AgentRunRequestVo`, …)
- Infrastructure: `PiAgentRunner`, `RetryingAgentRunner`, `CircuitBreakerAgentRunner`, `RetryableRunnerFactory`

### Модуль Orchestrator

**Ответственность:** оркестрация цепочек агентов (static/dynamic), бюджет, аудит.

- Domain: бизнес-логика цепочек, Port-интерфейсы (`AgentRunnerPortInterface`), собственные VO (`ChainRunRequestVo`, `ChainRunResultVo`, …)
- Application: use cases (`OrchestrateChainCommandHandler`, `RunAgentCommandHandler`)
- Infrastructure: Adapter'ы к AgentRunner (`AgentRunnerAdapter`, `AgentVoMapper`), YAML-загрузка, JSONL-лог

### Связь через Port/Adapter

Orchestrator Domain определяет Port-интерфейсы (`AgentRunnerPortInterface`, `AgentRunnerRegistryPortInterface`). Infrastructure-слой Orchestrator реализует Adapter'ы, которые:

1. Маппят Orchestrator VO → AgentRunner VO через `AgentVoMapper`
2. Делегируют вызовы в `AgentRunnerInterface`
3. Инкапсулируют retry через `RetryableRunnerFactory`

```
Orchestrator Domain (Port)
    ↓ implements
Orchestrator Infrastructure (Adapter)
    ↓ delegates to
AgentRunner Domain (Interface)
```

## Обоснование

| Критерий                      | До декомпозиции          | После декомпозиции                  |
|-------------------------------|--------------------------|-------------------------------------|
| Связность модулей             | Высокая coupling         | Независимые модули                  |
| Расширяемость движков         | Требует понимания Domain| Только `AgentRunnerInterface`       |
| Тестируемость                 | Моки на всю Domain       | Моки только на Port-интерфейсы      |
| Переиспользование AgentRunner | Невозможно               | Отдельный модуль, независимая сборка|
| VO Ownership                  | Общие VO                 | Каждый модуль владеет своими VO     |

## Последствия

### Положительные

- AgentRunner можно переиспользовать в других бандлах без оркестрации.
- Orchestrator Domain не зависит от конкретного движка — замена Pi на Codex не затрагивает Domain.
- Тестирование Orchestrator Domain упрощается — мокируются Port-интерфейсы, не конкретные runner'ы.
- VO дублирование намеренно: каждый модуль владеет своей моделью данных.

### Отрицательные

- Дублирование VO на границе модулей (`ChainRunRequestVo` ↔ `AgentRunRequestVo`) — требует поддержки `AgentVoMapper`.
- Дополнительный уровень косвенности (Port → Adapter → Runner) — незначительный overhead в рантайме.

### Риски

- Рассинхронизация VO при изменении полей — митигируется тестами на `AgentVoMapper` и `AgentRunnerAdapter`.

## Альтернативы

1. **Общий модуль с namespace-разделением:** без Port/Adapter, но с разделением на подпространства имён. Отвергнуто — не решает проблему coupling на уровне VO.
2. **Shared Kernel:** выделить общие VO в отдельный пакет. Отвергнуто — создаёт нежелательную зависимость обоих модулей от третьего.
3. **Events-based связка:** модули общаются через события. Отвергнуто — избыточно для синхронного вызова движка.
