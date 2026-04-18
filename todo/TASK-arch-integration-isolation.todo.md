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
pr:
status: in_progress
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

* **Где делаем:** `src/Common/Module/Orchestrator/Domain/Service/Integration/`, `src/Common/Module/Orchestrator/Integration/Service/AgentRunner/`
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

## Инструкции для сабагента

**Роль:** docs/agents/roles/team/backend_developer.ru.md
**Ветка:** task/arch-integration-isolation (уже создана и активна)
**PR:** draft #16 из task/arch-integration-isolation в task/arch-orchestrator-module-decomposition

### Порядок действий
1. Переключись в ветку: `git checkout task/arch-integration-isolation`
2. Следуй AGENTS.md и Конвенциям.
3. Делай коммиты по Conventional Commits.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти `vendor/bin/phpunit` и `vendor/bin/psalm`.
6. Запуш: `git push`.
7. Переведи PR из draft в ready: `gh pr ready 16`.

**НЕ создавай новый PR** — он уже существует.
**НЕ меняй base Branch**.

### Ключевая идея
`RunAgentService` должен быть stateless (final readonly, без per-instance данных). Имя runner'а передаётся через `ChainRunRequestVo.runnerName`, а не через конструктор. Фабрика (`ResolveAgentRunnerService`) удаляется.

### ЭТАП 1: ChainRunRequestVo — добавить поле runnerName

В `src/Common/Module/Orchestrator/Domain/ValueObject/ChainRunRequestVo.php`:
- Добавить параметр конструктора `private ?string $runnerName = null` (после `$runnerArgs`)
- Добавить геттер `getRunnerName(): ?string`
- Обновить `withTruncatedContext()`: передавать `runnerName` в `new self(...)`

### ЭТАП 2: RunAgentServiceInterface — упростить до stateless

В `src/Common/Module/Orchestrator/Domain/Service/Integration/RunAgentServiceInterface.php`:
- Удалить методы `getName(): string` и `isAvailable(): bool`
- Оставить только `run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo`

### ЭТАП 3: RunAgentService — stateless реализация

В `src/Common/Module/Orchestrator/Integration/Service/AgentRunner/RunAgentService.php`:
- Убрать из конструктора `string $runnerName` и `bool $runnerAvailable`
- Конструктор: только `RunAgentCommandHandler $runAgentHandler` и `AgentDtoMapper $mapper`
- Удалить методы `getName()` и `isAvailable()`
- В `run()`: получать `runnerName` из `$request->getRunnerName() ?? ''`
- Обновить вызов mapper: `$this->mapper->mapToRunAgentCommand($request)` (runnerName берётся из VO)

### ЭТАП 4: AgentDtoMapper — runnerName из VO

В `src/Common/Module/Orchestrator/Integration/Service/AgentRunner/AgentDtoMapper.php`:
- Изменить `mapToRunAgentCommand()`: убрать параметр `string $runnerName = ''`
- Вместо него: `$runnerName = $vo->getRunnerName() ?? ''`

### ЭТАП 5: Удалить Resolve-сервисы

Удалить файлы:
- `src/Common/Module/Orchestrator/Domain/Service/Integration/ResolveAgentRunnerServiceInterface.php`
- `src/Common/Module/Orchestrator/Integration/Service/AgentRunner/ResolveAgentRunnerService.php`

### ЭТАП 6: Обновить потребителей — inject вместо registry

**OrchestrateChainCommandHandler** (`Application/UseCase/Command/OrchestrateChain/`):
- Заменить `ResolveAgentRunnerServiceInterface $runnerRegistry` → `RunAgentServiceInterface $agentRunner` в конструкторе
- Удалить `$runner = $this->runnerRegistry->get($runnerName)` из `executeStatic()` и `runDynamicLoop()`
- В `executeStatic()`: не передавать `$runner` в `$this->staticChainExecutor->execute()`, передать только `$runnerName`
- В `runDynamicLoop()`: не передавать `$runner` в `$this->dynamicLoopRunner->execute()`

**ExecuteStaticChainServiceInterface** (`Application/Service/Chain/`):
- Убрать `RunAgentServiceInterface $runner` из `execute()`
- Сигнатура: `execute(ChainDefinitionVo $chain, string $runnerName, string $task, ...)`

**ExecuteStaticChainService** (`Application/Service/Chain/`):
- Добавить `RunAgentServiceInterface $agentRunner` в конструктор
- Убрать `RunAgentServiceInterface $runner` из `execute()`
- Пробросить `$this->agentRunner` вместо `$runner` в `RunStaticChainService::execute()`

**RunStaticChainService** (`Domain/Service/Chain/Static/`):
- Добавить `RunAgentServiceInterface $agentRunner` в конструктор
- Убрать `RunAgentServiceInterface $runner` из `execute()` и всех приватных методов
- Везде где используется `$runner->run(...)` → `$this->agentRunner->run(...)`
- `string $runnerName` остаётся параметром (нужен для audit и StepResultVo)

**ExecuteStaticStepService** (`Domain/Service/Chain/Static/`):
- Добавить `RunAgentServiceInterface $agentRunner` в конструктор
- Убрать `RunAgentServiceInterface $runner` из `runAgentStep()`
- В `runAgentStep()`: `$this->agentRunner->run(...)` вместо `$runner->run(...)`
- Важно: `$request = new ChainRunRequestVo(...)` → добавить `runnerName: $runnerName` в конструктор VO

**RunDynamicLoopServiceInterface** (`Domain/Service/Chain/Dynamic/`):
- Убрать `RunAgentServiceInterface $runner` из `execute()`

**RunDynamicLoopService** (`Domain/Service/Chain/Dynamic/`):
- Добавить `RunAgentServiceInterface $agentRunner` в конструктор
- Убрать `RunAgentServiceInterface $runner` из `execute()` и всех приватных методов (`initExecution`, `runRound`, `executeFacilitator`, `executeParticipant`, `executeFinalize`, `checkBudget`)
- Везде `$runner` → `$this->agentRunner`

**ExecuteDynamicTurnService** (`Domain/Service/Chain/Dynamic/`):
- Добавить `RunAgentServiceInterface $agentRunner` в конструктор
- Убрать `RunAgentServiceInterface $runner` из всех публичных методов (`runFacilitatorStep`, `runParticipantStep`, `runFacilitatorFinalizeStep`)
- Везде `$runner` → `$this->agentRunner`

**RunDynamicLoopAgentServiceInterface** (`Domain/Service/Chain/Dynamic/`):
- Убрать `RunAgentServiceInterface $runner` из `runFacilitator()`, `runParticipant()`, `runFacilitatorFinalize()`

**RunDynamicLoopAgentService** (`Infrastructure/Service/Chain/`):
- Убрать `RunAgentServiceInterface $runner` из `runFacilitator()`, `runParticipant()`, `runFacilitatorFinalize()`
- Эти методы уже не принимают `$runner` — вместо этого сервис получает его через конструктор родительского класса или注入. НО этот класс не имеет `RunAgentServiceInterface` в конструкторе!
- Решение: добавить `RunAgentServiceInterface $agentRunner` в конструктор `RunDynamicLoopAgentService`

**ResolveChainRunnerService** (`Infrastructure/Service/Chain/`):
- Заменить `ResolveAgentRunnerServiceInterface $runnerRegistry` на `RunAgentServiceInterface $agentRunner`
- Вместо `$this->runnerRegistry->get($fallbackRunnerName)` → создать `$fallbackRequest` с `runnerName: $fallbackRunnerName` и вызвать `$this->agentRunner->run($fallbackRequest, $retryPolicy)`

**RunAgentCommandHandler** (Orchestrator `Application/UseCase/Command/RunAgent/`):
- Заменить `ResolveAgentRunnerServiceInterface $runnerRegistry` → `RunAgentServiceInterface $agentRunner`
- Заменить `$this->runnerRegistry->get($runnerName)->run($request)` → добавить `runnerName` в ChainRunRequestVo и `$this->agentRunner->run($request)`

**GetRunnersQueryHandler** (Orchestrator `Application/UseCase/Query/GetRunners/`):
- Заменить `ResolveAgentRunnerServiceInterface $runnerRegistry` → `AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQueryHandler` (inject AgentRunner Application QueryHandler directly)
- В `__invoke()`: вызвать `($this->getRunnersHandler)(new AgentRunner\Application\UseCase\Query\GetRunners\GetRunnersQuery())` → map `RunnerDto[]` from AgentRunner to `RunnerDto[]` in Orchestrator
- Оба DTO имеют одинаковые поля (`name`, `isAvailable`), поэтому маппинг простой

### ЭТАП 7: DI — config/services.yaml

- Удалить alias для `ResolveAgentRunnerServiceInterface`
- Добавить alias: `RunAgentServiceInterface` → `RunAgentService` (Integration)

### ЭТАП 8: Тесты

**Удалить тесты:**
- `tests/Unit/Integration/Service/AgentRunner/RunAgentServiceTest.php` — тесты `getName()` и `isAvailable()` удалены, а `run()` нужно переписать (runnerName через VO)

**Обновить тесты:**
- `tests/Unit/Integration/Service/AgentRunner/RunAgentServiceTest.php` — переписать: `new RunAgentService($handler, $mapper)` без runnerName/runnerAvailable. `ChainRunRequestVo` с `runnerName: 'pi'`
- `tests/Unit/Integration/Service/AgentRunner/AgentDtoMapperTest.php` — `mapToRunAgentCommand()` без второго параметра `$runnerName`
- `tests/Unit/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommandHandlerTest.php` — заменить mock `ResolveAgentRunnerServiceInterface` на mock `RunAgentServiceInterface`
- `tests/Unit/Application/UseCase/Command/RunAgent/RunAgentCommandHandlerTest.php` — заменить mock `ResolveAgentRunnerServiceInterface` на mock `RunAgentServiceInterface`
- `tests/Unit/Application/UseCase/Query/GetRunners/GetRunnersQueryHandlerTest.php` — заменить mock `ResolveAgentRunnerServiceInterface` на mock AgentRunner `GetRunnersQueryHandler`

**Поиск по тестам:** `grep -rn "ResolveAgentRunnerServiceInterface\|->get(\|->list(\|getName()\|isAvailable()" tests/` — убедиться что 0 совпадений по старым именам.

### Проверка
- `vendor/bin/phpunit` — 0 failures
- `vendor/bin/psalm` — 0 errors
- `grep -rn "ResolveAgentRunnerService" src/ tests/` — 0 совпадений
- `grep -rn "runnerRegistry" src/ tests/` — 0 совпадений

## Change History (История изменений)

| Дата | Автор | Изменение |
|------|-------|-----------|
| 2026-04-18 | Тимлид (Алекс) | Создание задачи — выявлены нарушения изоляции при архитектурном ревью |
