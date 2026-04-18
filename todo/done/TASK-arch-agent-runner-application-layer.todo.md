---
type: refactor
created: 2026-04-17
value: V3
complexity: C4
priority: P2
depends_on: []
epic: ""
author: Архитектор (Гэндальф)
assignee: Бэкендер
branch: task/arch-agent-runner-application-layer
pr: '#14'
status: done
---

# TASK-arch-agent-runner-application-layer: Application-слой AgentRunner и Integration-слой Orchestrator для соблюдения конвенции межмодульного взаимодействия

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда я разрабатываю модульную архитектуру бандла, я хочу чтобы межмодульное взаимодействие Orchestrator → AgentRunner шло строго по конвенциям: Orchestrator Integration → AgentRunner Application, — чтобы Infrastructure-слой оставался изолированным от других модулей, а каждый модуль предоставлял публичный контракт через Application.

### Goal (Цель по SMART)
Устранить два нарушения конвенций:
1. **AgentRunner** не имеет Application-слоя — внешний потребитель (Orchestrator) вынужден ходить напрямую в Domain.
2. **Orchestrator** Adapter'ы лежат в `Infrastructure/Adapter/` и ходят в `AgentRunner\Domain` — нарушено правило «Infrastructure изолирован от других модулей».

После задачи:
- `AgentRunner/Application/` содержит use cases + DTO для внешних потребителей.
- `Orchestrator/Integration/` содержит Adapter'ы (ACL), вызывающие `AgentRunner\Application`.
- `Orchestrator/Infrastructure/Adapter/` — удалён, адаптеры перенесены в Integration.
- `grep -r "AgentRunner" src/Module/Orchestrator/Infrastructure/ --include="*.php"` → 0 результатов.

## 2. Context and Scope (Контекст и Границы)

### Текущее состояние
```
Orchestrator/Infrastructure/Adapter/
├── AgentRunnerAdapter.php            # use AgentRunner\Domain\*
├── AgentRunnerRegistryAdapter.php    # use AgentRunner\Domain\*
└── AgentVoMapper.php                 # use AgentRunner\Domain\*
```

Все 3 файла напрямую импортируют из `AgentRunner\Domain\Service\*` и `AgentRunner\Domain\ValueObject\*`.

### Нарушения конвенций

1. **`docs/conventions/layers/infrastructure.md`:** «Изолирован от других модулей» — Orchestrator Infrastructure зависит от AgentRunner Domain.
2. **`docs/conventions/layers/layers.md` матрица:** Integration → Application — межмодульное взаимодействие идёт через Application. Infrastructure → чужой Domain — не разрешено.

### Как должно быть (после задачи)

```
Orchestrator                              AgentRunner
──────────                                ──────────
Domain (Port)                             Domain (контракты, VO)
  ↓                                           ↓
Application (использует Port)             Application (use cases, DTO)
  ↓                                           ↑
Integration (Adapter/ACL) ──────────→  Application
```

### Границы (Out of Scope)
- Изменение бизнес-логики AgentRunner (retry, circuit breaker — остаются как есть)
- Изменение Orchestrator Domain (Port-интерфейсы не меняются)
- Изменение Orchestrator Application (use cases не меняются)
- Presentation-слой

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [ ] **AgentRunner/Application/ — use cases и DTO:**

| Компонент | Назначение |
|-----------|------------|
| `RunAgentCommand` | DTO: role, task, systemPrompt, previousContext, model, tools, workingDir, timeout, command |
| `RunAgentCommandHandler` | Orchestrator Domain → AgentRunner Domain VO mapping, вызывает `AgentRunnerInterface`, возвращает `RunAgentResultDto` |
| `RunAgentResultDto` | outputText, inputTokens, outputTokens, cacheReadTokens, cacheWriteTokens, cost, error |
| `GetRunnersQuery` | DTO (пустой или с фильтром) |
| `GetRunnersQueryHandler` | Вызывает `AgentRunnerRegistryServiceInterface`, возвращает `GetRunnersResultDto` |
| `GetRunnersResultDto` | список runners (name, isAvailable) |
| `CreateRetryableRunnerCommand` | DTO: runner, retryPolicy |
| `CreateRetryableRunnerCommandHandler` | Вызывает `RetryableRunnerFactoryInterface`, возвращает `AgentRunnerInterface` |

- [ ] **Orchestrator/Integration/ — Adapter'ы (ACL):**
  - Перенести `Orchestrator/Infrastructure/Adapter/` → `Orchestrator/Integration/Adapter/`
  - `AgentRunnerAdapter` — вызывает `AgentRunner\Application\RunAgentCommandHandler` вместо `AgentRunner\Domain\Service\AgentRunnerInterface`
  - `AgentRunnerRegistryAdapter` — вызывает `AgentRunner\Application\GetRunnersQueryHandler` вместо `AgentRunner\Domain\Service\AgentRunnerRegistryServiceInterface`
  - `AgentVoMapper` — маппинг: Orchestrator VO → AgentRunner Application DTO (вместо Orchestrator VO → AgentRunner Domain VO)

- [ ] **Удалить `Orchestrator/Infrastructure/Adapter/`** после переноса в Integration.

- [ ] **DI-конфигурация:** обновить `config/services.yaml` — автоконфигурация Integration, привязка адаптеров.

- [ ] **Verifiable constraint:**
  ```bash
  grep -r "AgentRunner" src/Module/Orchestrator/Infrastructure/ --include="*.php" | wc -l  # → 0
  grep -r "AgentRunner" src/Module/Orchestrator/Domain/ --include="*.php" | wc -l  # → 0 (только Port-имена)
  ```

### 🟡 Should Have (Желательно)
- [ ] Unit-тесты на AgentRunner Application use cases
- [ ] Unit-тесты на Integration Adapter'ы (обновлённые)
- [ ] Обновить `docs/guide/architecture.md` — отразить Integration-слой и Application AgentRunner
- [ ] Обновить `docs/adr/001-module-decomposition.md` — обоснование добавления Application

### 🟢 Could Have (Опционально)

### ⚫ Won't Have (Не будем делать)
- Изменение контрактов Port-интерфейсов Orchestrator Domain
- Изменение retry/circuit breaker логики
- Добавление Presentation- или Integration-слоёв в AgentRunner

## 4. Implementation Plan (План реализации)

1. [ ] Создать `AgentRunner/Application/DTO/`: `RunAgentCommand`, `RunAgentResultDto`, `GetRunnersQuery`, `GetRunnersResultDto`, `RunnerDto`, `CreateRetryableRunnerCommand`
2. [ ] Создать `AgentRunner/Application/UseCase/Command/RunAgent/RunAgentCommandHandler.php`
3. [ ] Создать `AgentRunner/Application/UseCase/Query/GetRunners/GetRunnersQueryHandler.php`
4. [ ] Создать `AgentRunner/Application/UseCase/Command/CreateRetryableRunner/CreateRetryableRunnerCommandHandler.php`
5. [ ] Перенести `Orchestrator/Infrastructure/Adapter/` → `Orchestrator/Integration/Adapter/`
6. [ ] Обновить `AgentRunnerAdapter`: вызывать `RunAgentCommandHandler` вместо `AgentRunnerInterface`
7. [ ] Обновить `AgentRunnerRegistryAdapter`: вызывать `GetRunnersQueryHandler` вместо `AgentRunnerRegistryServiceInterface`
8. [ ] Обновить `AgentVoMapper`: маппить Orchestrator VO → AgentRunner Application DTO
9. [ ] Обновить `ResolveChainRunnerService` (Orchestrator Infrastructure) — если он ещё зависит от AgentRunner Domain напрямую
10. [ ] Обновить `RunDynamicLoopAgentService` (Orchestrator Infrastructure) — аналогично
11. [ ] Удалить `Orchestrator/Infrastructure/Adapter/`
12. [ ] Обновить `config/services.yaml` — зарегистрировать Integration-слой, обновить привязки
13. [ ] Обновить/добавить тесты
14. [ ] PHPUnit + Psalm

## 5. Definition of Done (Критерии приёмки)

- [ ] `grep -r "AgentRunner" src/Module/Orchestrator/Infrastructure/ --include="*.php"` → 0 результатов
- [ ] `grep -r "AgentRunner" src/Module/Orchestrator/Domain/ --include="*.php"` → только Port-имена (`AgentRunnerPortInterface`, `AgentRunnerRegistryPortInterface`)
- [ ] `find src/Module/Orchestrator/Infrastructure/Adapter -type f` → directory not found
- [ ] `find src/Module/Orchestrator/Integration/Adapter -type f` → 3 файла (Adapter, RegistryAdapter, VoMapper)
- [ ] `find src/Module/AgentRunner/Application -type f -name "*.php" | wc -l` → ≥ 4
- [ ] PHPUnit green
- [ ] Psalm green

## 6. Verification (Самопроверка)

```bash
# Infrastructure не зависит от AgentRunner
grep -r "AgentRunner" src/Module/Orchestrator/Infrastructure/ --include="*.php" | wc -l  # → 0
# Domain — только Port-имена
grep -r "AgentRunner" src/Module/Orchestrator/Domain/ --include="*.php"
# Integration — содержит адаптеры
find src/Module/Orchestrator/Integration/ -type f -name "*.php"
# AgentRunner Application — создан
find src/Module/AgentRunner/Application/ -type f -name "*.php"
# Проверки
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)

- Нет зависимостей от других задач (может выполняться независимо)
- Риск: `ResolveChainRunnerService` и `RunDynamicLoopAgentService` в Orchestrator Infrastructure могут напрямую зависеть от `RetryableRunnerFactoryInterface` из AgentRunner Domain — нужно проверить и перевести на Integration-слой
- Риск: DI-конфигурация требует аккуратной привязки нового Integration-каталога — автоконфигурация Symfony должна его подхватить
- Масштаб: ~10 новых файлов в AgentRunner Application, ~3 файла переносятся/переписываются, ~5 файлов обновляются, ~200-300 LOC

## 8. Sources (Источники)

- [docs/conventions/layers/layers.md](../../docs/conventions/layers/layers.md) — матрица зависимостей, Integration → Application
- [docs/conventions/layers/infrastructure.md](../../docs/conventions/layers/infrastructure.md) — «Изолирован от других модулей»
- [docs/conventions/layers/integration.md](../../docs/conventions/layers/integration.md) — Integration-слой
- [docs/conventions/modules/index.md](../../docs/conventions/modules/index.md) — пример двухмодульного бандла
- [docs/guide/architecture.md](../../docs/guide/architecture.md) — текущая архитектура

## 9. Comments (Комментарии)

Ключевой принцип: **Infrastructure не знает о других модулях**. Межмодульное взаимодействие — через Integration → Application. AgentRunner получает Application-слой с публичным контрактом (use cases + DTO), а Orchestrator Integration играет роль ACL (Anti-Corruption Layer), маппя Orchestrator VO в AgentRunner DTO.

Вопрос для реализации: `CreateRetryableRunnerCommandHandler` — нужен ли отдельный use case, или retry инкапсулируется внутри `RunAgentCommandHandler`? Зависит от того, должен ли Orchestrator управлять retry-policy самостоятельно (через Port) или это внутренняя деталь AgentRunner.

## Change History (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-17 | Архитектор (Гэндальф) | Создание задачи по итогам рефлексии эпика EPIC-arch-orchestrator-module-decomposition |
