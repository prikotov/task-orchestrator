---
type: refactor
created: 2026-04-18
value: V3
complexity: C4
priority: P1
depends_on:
  - TASK-arch-integration-naming-convention
epic: EPIC-arch-orchestrator-module-decomposition
author: Тимлид (Алекс)
assignee: Бэкендер
branch: task/arch-integration-isolation
pr: '#16'
status: done
---

# TASK-arch-integration-isolation: Устранить нарушение изоляции между Integration-сервисами Orchestrator

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда я разрабатываю Integration-слой Orchestrator, я хочу чтобы каждый интеграционный сервис был независимым и не создавал другие интеграционные сервисы через `new`, — чтобы соблюдалась конвенция `service.md`: Integration Service внедряется через DI и возвращает DTO/VO/примитивы.

### Goal (Цель по SMART)
Устранить 3 нарушения изоляции в Integration-слое Orchestrator:

1. `ResolveAgentRunnerService` создаёт `new RunAgentService(...)` — прямой `new` другого Integration-сервиса вместо DI
2. `ResolveAgentRunnerServiceInterface::get()` возвращает `RunAgentServiceInterface` — два Integration-сервиса связаны через Domain-контракт
3. `ResolveAgentRunnerService` не `readonly` — mutable кэш внутри Integration-сервиса

## 2. Context and Scope (Контекст и Границы)

* **Где делаем:** `src/Module/Orchestrator/Domain/Service/Integration/`, `src/Module/Orchestrator/Integration/Service/AgentRunner/`
* **Текущее поведение:**
  - `RunAgentService` (Integration) принимает `string $runnerName` через **конструктор** — каждый экземпляр привязан к одному runner'у
  - `ResolveAgentRunnerService` (Integration) — фабрика, создающая `RunAgentService` через `new`, с mutable-кэшем
  - `ResolveAgentRunnerServiceInterface` (Domain) возвращает `RunAgentServiceInterface` — Domain знает о двух связанных контрактах
* **Конвенция** (`service.md`, Integration Service):
  - Возвращает и принимает: DTO, VO, Enum, примитивы, UuidInterface
  - Не зависит от реализаций домена. Допускается только внедрение интерфейсов
  - Stateless (без состояния)

### Scope (делаем)
- Переработать `RunAgentServiceInterface`: `runnerName` → параметр метода `run()`, а не конструктора
- Удалить `ResolveAgentRunnerService` и `ResolveAgentRunnerServiceInterface` — функция реестра переходит в Application AgentRunner (уже есть `GetRunnersQueryHandler`, `GetRunnerByNameQueryHandler`)
- Обновить всех потребителей: Infrastructure-сервисы Orchestrator получают `RunAgentServiceInterface` напрямую через DI
- Обновить DI-конфигурацию

### Out of Scope (не делаем)
- Не меняем Application AgentRunner (QueryHandler'ы уже реализуют реестр)
- Не меняем Domain AgentRunner

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (обязательно)

- [ ] `RunAgentServiceInterface` — `runnerName` параметр метода, не конструктора:
  ```php
  interface RunAgentServiceInterface {
      public function run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo;
  }
  ```
  `RunAgentService` получает `RunAgentCommandHandler` + `AgentDtoMapper` через DI (stateless, readonly).
- [ ] Удалить `ResolveAgentRunnerServiceInterface` и `ResolveAgentRunnerService`
- [ ] Удалить `GetRunnersQueryHandler` / `GetRunnerByNameQueryHandler` из Orchestrator Integration — Orchestrator больше не является consumer'ом этих Query; если они нужны только внутри AgentRunner — оставить в AgentRunner Application
- [ ] Infrastructure-сервисы Orchestrator (`ResolveChainRunnerService`, `RunDynamicLoopAgentService` и др.) получают `RunAgentServiceInterface` через DI и передают `runnerName` через параметры методов
- [ ] Обновить DI-конфигурацию: `RunAgentServiceInterface` → `RunAgentService`
- [ ] Обновить все unit-тесты
- [ ] `vendor/bin/phpunit` — зелёные
- [ ] `vendor/bin/psalm` — 0 ошибок

### 🟡 Should Have (желательно)

- [ ] Обновить `docs/conventions/modules/index.md`
- [ ] Обновить `docs/guide/architecture.md`

### 🟢 Could Have (опционально)

### ⚫ Won't Have (не будем делать)

- [ ] Изменение Application/Domain AgentRunner
- [ ] Изменение бизнес-логики

## 4. Risks and Dependencies (Риски и зависимости)

- **Риск:** Infrastructure-сервисы Orchestrator (`ResolveChainRunnerService`, `RunDynamicLoopAgentService`) активно используют `AgentRunnerRegistryPortInterface::get()` для получения runner'а по имени. При удалении registry нужно продумать, как Infrastructure будет резолвить runner'а по имени.
- **Риск:** `RunDynamicLoopAgentService.runFacilitator()` и `runParticipant()` принимают `AgentRunnerPortInterface $runner` как параметр — текущий паттерн уже изолирован, но вызывающий код должен как-то получать runner'а.
- **Решение:** Infrastructure-сервисы, которым нужно резолвить runner'а по имени, могут использовать `RunAgentServiceInterface::run()` с `runnerName` в `ChainRunRequestVo` (добавить поле) или получить `RunAgentServiceInterface` через DI и передавать имя runner'а через параметр.

## 5. Acceptance Criteria (Критерии приёмки)

1. `ResolveAgentRunnerService` и `ResolveAgentRunnerServiceInterface` не существуют
2. `RunAgentService` — `final readonly class`, создаётся через DI, не имеет mutable-состояния
3. Integration-слой Orchestrator не содержит `new` для других Integration-сервисов
4. Integration-слой Orchestrator не возвращает интерфейсы других Integration-сервисов
5. `vendor/bin/phpunit` — зелёные
6. `vendor/bin/psalm` — 0 ошибок

## Change History (История изменений)

| Дата | Автор | Изменение |
|------|-------|-----------|
| 2026-04-18 | Тимлид (Алекс) | Создание задачи — выявлены нарушения изоляции при архитектурном ревью |
