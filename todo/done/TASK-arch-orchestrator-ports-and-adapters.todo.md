---
type: refactor
created: 2026-04-17
value: V2
complexity: C4
priority: P2
depends_on:
  - TASK-arch-agent-runner-module-extract
epic: EPIC-arch-orchestrator-module-decomposition
author: Архитектор (Гэндальф)
assignee:
branch: task/arch-orchestrator-module-decomposition
pr: https://github.com/prikotov/task-orchestrator/pull/3
status: done
---

# TASK-arch-orchestrator-ports-and-adapters: Изоляция Orchestrator от AgentRunner через порты и маппинг VO

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда я разрабатываю оркестрацию цепочек, я хочу чтобы Domain-слой Orchestrator не знал о существовании модуля AgentRunner — только о собственных Port-интерфейсах, — чтобы можно было заменять движок агентов не трогая бизнес-логику.

### Goal (Цель по SMART)
Внедрить Port/Adapter между Orchestrator Domain и AgentRunner. Orchestrator Domain определяет свои VO (`ChainRunRequestVo`, `ChainRunResultVo`, `ChainTurnResultVo`, `ChainRetryPolicyVo`) и Port-интерфейсы. Маппинг реализован в `Orchestrator/Infrastructure/Adapter/`. После задачи: `grep -r "AgentRunner" Orchestrator/Domain/` → 0 совпадений.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Common/Module/Orchestrator/`
*   **Текущее состояние (после TASK-arch-agent-runner-module-extract):**
    *   Orchestrator Domain напрямую `use`-ает `AgentRunner\AgentRunnerInterface`, `AgentRunner\AgentRunnerRegistryServiceInterface`, `AgentRunner\RetryableRunnerFactoryInterface`
    *   Orchestrator Domain напрямую `use`-ает `AgentRunner\AgentResultVo`, `AgentRunRequestVo`, `AgentTurnResultVo`, `RetryPolicyVo`
    *   7 файлов в `Domain/Service/Chain/` зависят от AgentRunner
    *   4 файла в `Application/` зависят от AgentRunner

*   **Границы (Out of Scope):**
    *   Изменение AgentRunner модуля (он не должен знать об Orchestrator)
    *   Изменение логики выполнения цепочек
    *   Report, Budget, Prompt — не трогаем

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] **Новые VO в Orchestrator Domain** (дубликаты AgentRunner VO со своими namespace):

| Orchestrator VO | Агрегирует поля из |
|---|---|
| `ChainRunRequestVo` | `AgentRunRequestVo` (role, task, systemPrompt, previousContext, model, tools, workingDir, timeout, command) |
| `ChainRunResultVo` | `AgentResultVo` (outputText, inputTokens, outputTokens, cacheReadTokens, cacheWriteTokens, cost, error) |
| `ChainTurnResultVo` | `AgentTurnResultVo` (agentResult, duration, invocation, systemPrompt, userPrompt) |
| `ChainRetryPolicyVo` | `RetryPolicyVo` (maxRetries, initialDelayMs, multiplier, useJitter) |

- [ ] **Port-интерфейсы в Orchestrator Domain:**
  - `AgentRunnerPortInterface` — `run(ChainRunRequestVo): ChainRunResultVo`, `getName(): string`, `isAvailable(): bool`
  - `AgentRunnerRegistryPortInterface` — `get(string): AgentRunnerPortInterface`, `getDefault(): AgentRunnerPortInterface`, `list(): array`

- [ ] **Adapter в Orchestrator Infrastructure:**
  - `AgentRunnerAdapter implements AgentRunnerPortInterface` — делегирует `AgentRunnerInterface`, маппит VO
  - `AgentRunnerRegistryAdapter implements AgentRunnerRegistryPortInterface` — оборачивает `AgentRunnerRegistryServiceInterface`
  - `AgentVoMapper` — static-методы маппинга: `toAgentRequest()`, `toChainResult()`, `toChainTurnResult()`, `toAgentRetryPolicy()`

- [ ] **Обновление потребителей Orchestrator Domain:**
  - `Domain/Service/Chain/` все 7 файлов: `AgentRunnerInterface` → `AgentRunnerPortInterface`, VO AgentRunner → VO Orchestrator
  - `Domain/Service/Chain/Shared/ResolveChainRunnerServiceInterface`: `RetryableRunnerFactoryInterface` убран, retry инкапсулирован в Port

- [ ] **Обновление потребителей Orchestrator Application:**
  - `ExecuteStaticChainService`, `ExecuteStaticChainServiceInterface`: `AgentRunnerInterface` → `AgentRunnerPortInterface`
  - `OrchestrateChainCommandHandler`: `AgentRunnerRegistryServiceInterface` → `AgentRunnerRegistryPortInterface`
  - `RunAgentCommandHandler`: `AgentRunnerRegistryServiceInterface` → `AgentRunnerRegistryPortInterface`
  - `GetRunnersQueryHandler`: `AgentRunnerRegistryServiceInterface` → `AgentRunnerRegistryPortInterface`

- [ ] **DI-конфигурация:**
  - `config/services.yaml`: связывает адаптеры с AgentRunner-сервисами через autowiring

- [ ] **Verifiable constraint:** `grep -r "AgentRunner" src/Common/Module/Orchestrator/Domain/` → 0 результатов

### 🟡 Should Have (Желательно)
- [ ] Unit-тест на `AgentVoMapper` (покрытие маппинга всех 4 VO)
- [ ] Unit-тест на `AgentRunnerAdapter`

### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- Изменение контракта `AgentRunnerInterface` (он остаётся как есть)
- Маппинг `CircuitBreakerStateVo`, `CircuitStateEnum` — они не выходят за пределы AgentRunner

## 4. Implementation Plan (План реализации)
1. [ ] Создать 4 новых VO в `Orchestrator/Domain/ValueObject/`: `ChainRunRequestVo`, `ChainRunResultVo`, `ChainTurnResultVo`, `ChainRetryPolicyVo`
2. [ ] Создать `Orchestrator/Domain/Service/Port/AgentRunnerPortInterface.php`
3. [ ] Создать `Orchestrator/Domain/Service/Port/AgentRunnerRegistryPortInterface.php`
4. [ ] Создать `Orchestrator/Infrastructure/Adapter/AgentVoMapper.php` — 4 метода маппинга
5. [ ] Создать `Orchestrator/Infrastructure/Adapter/AgentRunnerAdapter.php`
6. [ ] Создать `Orchestrator/Infrastructure/Adapter/AgentRunnerRegistryAdapter.php`
7. [ ] Обновить `Domain/Service/Chain/Static/` — заменить прямые ссылки на AgentRunner VO/interface
8. [ ] Обновить `Domain/Service/Chain/Dynamic/` — аналогично
9. [ ] Обновить `Domain/Service/Chain/Shared/ResolveChainRunnerServiceInterface.php` — убрать `RetryableRunnerFactoryInterface`, использовать Port
10. [ ] Обновить `Application/Service/Chain/ExecuteStaticChainService.php` + Interface
11. [ ] Обновить `Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommandHandler.php`
12. [ ] Обновить `Application/UseCase/Command/RunAgent/RunAgentCommandHandler.php`
13. [ ] Обновить `Application/UseCase/Query/GetRunners/GetRunnersQueryHandler.php`
14. [ ] Обновить Infrastructure Chain-сервисы, которые используют AgentRunner VO
15. [ ] Обновить `config/services.yaml` — привязать адаптеры
16. [ ] Обновить все тесты (новые namespace, VO)
17. [ ] Добавить unit-тесты: `AgentVoMapperTest`, `AgentRunnerAdapterTest`
18. [ ] PHPUnit + Psalm

## 5. Definition of Done (Критерии приёмки)
- [ ] `grep -r "AgentRunner" src/Common/Module/Orchestrator/Domain/` → 0 результатов
- [ ] `grep -r "AgentRunner" src/Common/Module/Orchestrator/Application/` → 0 результатов (кроме Port/Adapter wiring через DI)
- [ ] PHPUnit green
- [ ] Psalm green
- [ ] Unit-тесты на AgentVoMapper и AgentRunnerAdapter проходят

## 6. Verification (Самопроверка)
```bash
grep -r "AgentRunner" src/Common/Module/Orchestrator/Domain/ --include="*.php" | wc -l  # → 0
grep -r "AgentRunner" src/Common/Module/Orchestrator/Application/ --include="*.php" | wc -l  # → 0
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- Зависит от TASK-arch-agent-runner-module-extract
- Самая сложная задача эпика: ~15 файлов обновляются, ~600 LOC новых VO + адаптеров
- Риск рассинхрона VO: при изменении контракта AgentRunner нужно обновить 2 набора VO — решается через тесты маппера
- `ResolveChainRunnerService` (Infrastructure) — единственный Infrastructure-сервис, который знает об AgentRunner внутренних деталях (RetryableRunnerFactory). Нужно решить: инкапсулировать retry в Port или оставить bridge

## 8. Sources (Источники)
- [docs/guide/architecture.md](docs/guide/architecture.md)
- [docs/conventions/layers/layers.md](docs/conventions/layers/layers.md)

## 9. Comments (Комментарии)
Ключевой момент: `ResolveChainRunnerService` сейчас создаёт retry-обёртку через `RetryableRunnerFactoryInterface`. После внедрения Port'а retry должен быть инкапсулирован внутри AgentRunner (Port не знает о retry). `ResolveChainRunnerService` вызывает `port->run()`, retry происходит прозрачно внутри AgentRunner.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-17 | Архитектор (Гэндальф) | Создание задачи |
