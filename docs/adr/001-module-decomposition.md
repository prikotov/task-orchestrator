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
- Application: `RunAgentCommandHandler`, `GetRunnersQueryHandler`, `GetRunnerByNameQueryHandler`
- Infrastructure: `PiAgentRunner`, `RetryingAgentRunner`, `CircuitBreakerAgentRunner`, `RetryableRunnerFactory`

### Модуль Orchestrator

**Ответственность:** оркестрация цепочек агентов (static/dynamic), бюджет, аудит.

- Domain: бизнес-логика цепочек, интеграционный интерфейс (`RunAgentServiceInterface`), собственные VO (`ChainRunRequestVo`, `ChainRunResultVo`, …)
- Application: use cases (`OrchestrateChainCommandHandler`, `RunAgentCommandHandler`)
- Integration: ACL к AgentRunner (`RunAgentService`, `AgentDtoMapper`)
- Infrastructure: YAML-загрузка, JSONL-лог, Session

### Связь через Integration-слой (Clean Architecture)

Модули связаны через Integration-слой Orchestrator. Orchestrator Domain определяет интерфейс `RunAgentServiceInterface` в `Domain/Service/Integration/`. Integration-слой реализует `RunAgentService`, который:

1. Маппит Orchestrator VO → AgentRunner Application DTO через `AgentDtoMapper`
2. Делегирует вызовы в `RunAgentCommandHandler` (Application-слой AgentRunner)
3. Маппит результат обратно: AgentRunner Application DTO → Orchestrator VO

```
Orchestrator Domain (RunAgentServiceInterface)
    ↑ implements
Orchestrator Integration (RunAgentService)
    → delegates to
AgentRunner Application (RunAgentCommandHandler)
    → uses
AgentRunner Domain (AgentRunnerInterface + VO)
```

**DI:** Symfony связывает интерфейс с реализацией через alias в `services.yaml`.

## Обоснование

| Критерий                      | До декомпозиции          | После декомпозиции                  |
|-------------------------------|--------------------------|-------------------------------------|
| Связность модулей             | Высокая coupling         | Независимые модули                  |
| Расширяемость движков         | Требует понимания Domain| Только `AgentRunnerInterface`       |
| Тестируемость                 | Моки на всю Domain       | Моки на `RunAgentServiceInterface`  |
| Переиспользование AgentRunner | Невозможно               | Отдельный модуль, независимая сборка|
| VO Ownership                  | Общие VO                 | Каждый модуль владеет своими VO     |

## Последствия

### Положительные

- AgentRunner можно переиспользовать в других бандлах без оркестрации.
- Orchestrator Domain не зависит от конкретного движка — замена Pi на Codex не затрагивает Domain.
- Тестирование Orchestrator Domain упрощается — мокируется `RunAgentServiceInterface`, а не конкретные runner'ы.
- VO дублирование намеренно: каждый модуль владеет своей моделью данных.

### Отрицательные

- Дублирование VO на границе модулей (`ChainRunRequestVo` ↔ `AgentRunRequestVo`) — требует поддержки `AgentDtoMapper`.
- Дополнительный уровень косвенности (Integration → AgentRunner Application → AgentRunner Domain) — незначительный overhead в рантайме.

### Риски

- Рассинхронизация VO при изменении полей — митигируется тестами на `AgentDtoMapper` и `RunAgentService`.

## Альтернативы

1. **Общий модуль с namespace-разделением:** без Integration-слоя, но с разделением на подпространства имён. Отвергнуто — не решает проблему coupling на уровне VO.
2. **Shared Kernel:** выделить общие VO в отдельный пакет. Отвергнуто — создаёт нежелательную зависимость обоих модулей от третьего.
3. **Events-based связка:** модули общаются через события. Отвергнуто — избыточно для синхронного вызова движка.
