# Модульная структура

Собрание правил, описывающих устройство модулей и их конфигурацию. Используйте эти материалы как эталон при создании новых модулей или ревью существующих.

- [Конфигурирование модулей](configuration.md)

## Пример: двухмодульный бандл с Port/Adapter

Бандл `TaskOrchestrator` демонстрирует паттерн **Port/Adapter** (Hexagonal Architecture) для связи двух модулей:

```
src/Common/Module/
├── AgentRunner/              # Модуль 1: движок AI-агента
│   ├── Domain/               # AgentRunnerInterface, AgentResultVo, Registry
│   └── Infrastructure/       # PiAgentRunner, Retry, Circuit Breaker
└── Orchestrator/             # Модуль 2: оркестрация цепочек
    ├── Domain/               # Port-интерфейсы, бизнес-логика
    ├── Application/          # Use cases, DTO
    └── Infrastructure/       # Adapter'ы к AgentRunner, YAML, JSONL
```

**Принципы:**

1. **Каждый модуль владеет своими VO.** Orchestrator использует `ChainRunRequestVo`, AgentRunner — `AgentRunRequestVo`. Маппинг выполняется в Infrastructure-слое Orchestrator (`AgentVoMapper`).

2. **Domain не зависит от другого модуля.** Orchestrator Domain определяет Port-интерфейсы (`AgentRunnerPortInterface`), а Infrastructure реализует Adapter'ы, делегирующие в AgentRunner.

3. **Зависимость однонаправленная:** Orchestrator Infrastructure → AgentRunner Domain. AgentRunner не знает об Orchestrator.

Обоснование декомпозиции — в [ADR-001](../../adr/001-module-decomposition.md). Архитектурная документация — в [Архитектура](../../guide/architecture.md).
