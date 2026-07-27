# ADR-010: Resume для Static и Conditional цепочек

| Поле        | Значение                                                        |
|-------------|-----------------------------------------------------------------|
| Статус      | Принято (реализация отложена до Q4 2026)                       |
| Дата        | 2026-05-02                                                      |
| Автор       | Архитектор (Гэндальф)                                          |
| Участники   | Гэндальф, Шерлок                                                |
| Источник    | [TASK-docs-resume-adr](../../todo/done/TASK-docs-resume-adr.todo.md) |

## Контекст

### Проблема

`StaticExecutionStrategy` и `ConditionalExecutionStrategy` не поддерживают возобновление — при вызове `resume()` бросают `LogicException`:

- `StaticExecutionStrategy::resume()` → `LogicException('Static chain does not support resume.')`
- `ConditionalExecutionStrategy::resume()` → `LogicException('Conditional chain does not support resume.')`

Только `DynamicExecutionStrategy` реализует возобновление через [`ChainSessionLoggerInterface`](../../src/Module/ChainDefinition/Domain/Service/Chain/Session/ChainSessionLoggerInterface.php) — механизм чекпоинтов/сессий на основе JSONL (JSONL-based checkpoint/session mechanism).

**Финансовая боль:** цепочка из 10 шагов, падение на 8-м → все результаты теряются. При стоимости LLM-вызова $0.50–$2.00 за шаг это потеря $3.50–$14.00 на каждый неудачный запуск (failed run).

### Существующая инфраструктура

| Компонент | Назначение | Используется в |
|-----------|------------|----------------|
| [`ExecutionStrategyInterface::resume()`](../../src/Module/ChainDefinition/Application/Service/Chain/ExecutionStrategyInterface.php) | Контракт возобновления | Dynamic ✅, Static ❌, Conditional ❌ |
| [`OrchestrateChainCommand::$resumeDir`](../../src/Module/ChainDefinition/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommand.php) | Путь к директории с checkpoint | Dynamic ✅ |
| [`OrchestrateChainCommandHandler`](../../src/Module/ChainDefinition/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommandHandler.php) | Диспетчер: `resumeDir !== null` → `resume()` | Все стратегии |
| [`ChainSessionLoggerInterface`](../../src/Module/ChainDefinition/Domain/Service/Chain/Session/ChainSessionLoggerInterface.php) | Жизненный цикл сессии JSONL (start/logRound/complete/resume) | Dynamic |
| [`ChainSessionStateVo`](../../src/Module/ChainDefinition/Domain/ValueObject/ChainSessionStateVo.php) | VO восстановленного состояния | Dynamic |
| [`AuditLoggerInterface`](../../src/Module/ChainDefinition/Domain/Service/Chain/Audit/AuditLoggerInterface.php) | JSONL audit: `logStepStart`, `logStepResult` | Dynamic |
| [`StaticChainExecution`](../../src/Module/StaticExecution/Domain/Entity/StaticChainExecution.php) | Изменяемое состояние в памяти (in-memory mutable state) | Static (no persistence) |
| [`StaticAuditServiceInterface`](../../src/Module/StaticExecution/Domain/Service/StaticAuditServiceInterface.php) | Audit для static цепочек | Static (optional) |

### Почему сейчас не реализовано

1. **`StaticChainExecution` — in-memory:** нет persistence-механизма. State живёт только в рамках одного вызова `runStaticChain()`.
2. **`ChainSessionStateVo` — dynamic-specific:** содержит `facilitator`, `participants`, `discussionHistory`, `facilitatorJournal` — поля, не применимые к static/conditional.
3. **`ConditionalExecutionStrategy` собирает `$context` на лету:** ассоциативный массив `stepName → {passed, exitCode, status}` накапливается итеративно — его нужно восстанавливать при возобновлении.

### Аналог: поток возобновления для Dynamic (resume flow)

```
resumeDir → ChainSessionLogger::resumeSession()
          → ChainSessionReader::getResumedState() → ChainSessionStateVo
          → BuildDynamicContextService::buildContext(state)
          → RunDynamicLoopService::execute(chain, context, startRound, history, journal)
          → finalizeSession()
```

## Решение

Применить паттерн **Checkpoint + Resume** к static и conditional стратегиям, переиспользуя существующую инфраструктуру сессий JSONL (JSONL-session).

### Чекпоинт: что сохранять

#### Чекпоинт static-цепочки (Static Chain Checkpoint)

```json
{
  "chain_name": "code-review",
  "chain_type": "static",
  "task": "Review PR #42",
  "completed_steps": 7,
  "total_steps": 10,
  "results": [
    {
      "step_index": 0,
      "role": "system_architect",
      "runner": "pi",
      "output_text": "...",
      "input_tokens": 4500,
      "output_tokens": 2200,
      "cost": 1.34,
      "duration": 12.5,
      "passed": true
    }
  ],
  "accumulated_context": "...",
  "budget_state": {
    "total_cost": 8.45,
    "role_costs": {"system_architect": 3.20, "backend_dev": 5.25}
  },
  "iteration_state": {
    "group_iterations": {"fix_loop": 2},
    "total_iterations": 1
  },
  "created_at": "2026-05-02T14:30:00+00:00"
}
```

#### Чекпоинт conditional-цепочки (Conditional Chain Checkpoint)

```json
{
  "chain_name": "adaptive-review",
  "chain_type": "conditional",
  "task": "Review PR #42",
  "completed_steps": 5,
  "total_steps": 8,
  "results": ["...аналогично static..."],
  "condition_context": {
    "lint": {"passed": true, "exitCode": 0, "status": "passed"},
    "tests": {"passed": false, "exitCode": 1, "status": "failed"}
  },
  "budget_state": {"total_cost": 3.50},
  "created_at": "2026-05-02T14:30:00+00:00"
}
```

### Точка сохранения

Checkpoint записывается **после каждого успешно выполненного шага** (выполнение post-step hook, но до budget check):

```mermaid
sequenceDiagram
    participant Handler as CommandHandler
    participant Strategy as StaticExecutionStrategy
    participant Service as RunStaticChainService
    participant Step as ExecuteStaticStepService
    participant Checkpoint as CheckpointWriter

    Handler->>Strategy: execute(chain, command)
    loop Для каждого step[N]
        Strategy->>Service: processStep(step[N])
        Service->>Step: runAgentStep() / runQualityGate()
        Step-->>Service: StepResultVo
        Service->>Checkpoint: saveCheckpoint(N, results, state)
        Note over Checkpoint: JSONL append: step completed
    end
    Strategy-->>Handler: OrchestrateChainResultDto
```

### Поток возобновления (Resume Flow)

```mermaid
sequenceDiagram
    participant Handler as CommandHandler
    participant Strategy as StaticExecutionStrategy
    participant Checkpoint as CheckpointReader
    participant Service as RunStaticChainService
    participant Step as ExecuteStaticStepService

    Handler->>Strategy: resume(chain, command)
    Note over Handler: command.resumeDir !== null
    Strategy->>Checkpoint: loadCheckpoint(resumeDir)
    Checkpoint-->>Strategy: StaticCheckpointStateVo
    Strategy->>Service: resumeFromStep(chain, startIndex, previousResults, state)
    loop Для каждого step[N], N = startIndex..total
        Service->>Step: runAgentStep() / runQualityGate()
        Step-->>Service: StepResultVo
    end
    Strategy-->>Handler: OrchestrateChainResultDto
```

### Архитектурные изменения

#### 1. Новый Domain VO: `StaticCheckpointStateVo`

В `src/Module/ChainDefinition/Domain/ValueObject/`:

```
StaticCheckpointStateVo {
    int $completedSteps,
    string $accumulatedContext,
    float $totalCost,
    int $totalInputTokens,
    int $totalOutputTokens,
    array $roleCosts,
    array $groupIterations,
    int $totalIterations,
    list<StepResultDto> $completedResults,
}
```

Отдельный от `ChainSessionStateVo` (dynamic-specific). Оба имплементируют общий интерфейс `CheckpointStateInterface`, если в Q4 это окажется целесообразным — решение на этапе реализации.

#### 2. Новый Domain интерфейс: `CheckpointWriterInterface` / `CheckpointReaderInterface`

В `src/Module/ChainDefinition/Domain/Service/Chain/Checkpoint/`:

```php
interface CheckpointWriterInterface {
    public function startCheckpoint(string $chainName, string $chainType, string $task): string;
    public function saveStepResult(int $stepIndex, StepResultDto $result, array $state): void;
    public function completeCheckpoint(): void;
}

interface CheckpointReaderInterface {
    public function loadCheckpoint(string $sessionDir): ?StaticCheckpointStateVo;
}
```

Формат хранения — JSONL (append-only, аналогично `ChainSessionWriter`). Реализация — Infrastructure.

#### 3. Изменение `StaticExecutionStrategy`

```php
// execute() — добавить checkpoint после каждого шага
// resume() — загрузить checkpoint, восстановить state, продолжить с шага N+1
```

#### 4. Изменение `ConditionalExecutionStrategy`

```php
// execute() — сохранять condition_context в checkpoint
// resume() — восстановить condition_context, продолжить с шага N+1
```

#### 5. `RunStaticChainService` — поддержка смещения старта (startOffset)

Метод `execute()` получает опциональные параметры: `$startStepIndex`, `$previousResults`, `$restoredState`.

### Интеграция с существующим кодом

| Существующий компонент | Взаимодействие |
|------------------------|----------------|
| `ExecutionStrategyInterface::resume()` | Без изменений контракта. Static/Conditional убирают `LogicException`. |
| `OrchestrateChainCommand::$resumeDir` | Без изменений. Уже передаётся в `resume()`. |
| `OrchestrateChainCommandHandler` | Без изменений. Диспетчер уже роутит по `resumeDir`. |
| `AuditLoggerInterface` | CheckpointWriter может делегировать audit-записи в `AuditLoggerInterface` для единообразия. |
| `StaticAuditServiceInterface` | Продолжает работать параллельно. Audit ≠ checkpoint (audit = наблюдаемость, checkpoint = восстановление (recovery)). |
| `ChainSessionLoggerInterface` | Dynamic-специфичный. Не переиспользуется напрямую — слишком разные модели (rounds vs steps). |

### Критерий реализации (Q4 2026)

| Условие | Значение |
|---------|----------|
| Триггер | Повторяющиеся падения static цепочек ≥5 шагов на проде, либо финансовая потеря > $50/месяц на повторные запуски (re-runs) |
| Необходимые предпосылки | Стабильный hooks API (post_step hooks, MVP завершён в Sprint 10) |
| Объём работ | ~300–400 LOC (Domain VO + интерфейсы + Infrastructure JSONL), ~200 LOC (изменения стратегий), ~500 LOC тесты |
| Сроки | Q4 2026, Sprint 13–14 |
| Зависимости | Нет блокирующих. Может выполняться параллельно с другими задачами. |

## Последствия

### Положительные

- **Экономия средств:** resume с 8-го шага вместо полного повторного запуска (re-run) экономит $3.50–$14.00 за неудачный запуск (failed run).
- **Единый UX:** `--resume <dir>` работает для всех трёх стратегий (static, conditional, dynamic).
- **Без изменения контракта:** `ExecutionStrategyInterface::resume()` уже существует, меняем только реализацию.
- **Чекпоинт с дозаписью (append-only):** JSONL-формат устойчив к частичным записям (partial writes; краш в середине записи = потеря последней строки, не всего файла).

### Отрицательные

- **Накладные расходы I/O (overhead):** запись checkpoint после каждого шага добавляет дисковый I/O (disk I/O). Митигируется: дозапись JSONL (append) = O(1), данные минимальны (~1–5 КБ на шаг).
- **Состояние `StaticChainExecution` частично дублируется** в checkpoint. При реализации — продумать, не выделить ли checkpoint state в отдельный VO, который делегирует `StaticChainExecution`.
- **Checkpoint валидность:** конфигурация цепочки может измениться между run и resume (шаги добавлены/удалены). Необходима валидация чексаммы или версии определения цепочки.

### Риски

1. **Группы итераций исправления (Fix Iteration Groups) + resume:** при resume в середине retry-группы (шаги 3→4→5 с retry) нужно корректно восстановить итерационное состояние. Сложность: средняя (medium).
2. **Пересборка контекста conditional (Conditional context rebuilding):** при resume conditional-цепочки выражения условий (condition expressions) переоцениваются. Если данные окружения изменились между run и resume — ветвление может пойти иначе. Это ожидаемое поведение (fresh evaluation), но должно быть задокументировано.
3. **Обратная совместимость (backward compatibility):** существующие цепочки без checkpoint не ломаются — resume продолжает бросать `LogicException` до миграции. Миграция = прозрачна (transparent; checkpoint создаётся автоматически при следующем run).

## Альтернативы

### A1: Общий `ChainSessionLoggerInterface` для всех стратегий

Переиспользовать `ChainSessionLoggerInterface` (JSONL session) для static/conditional.

**Плюсы:** меньше нового кода, единая session model.

**Минусы:** `ChainSessionLoggerInterface` заточен под dynamic модель (rounds, facilitator, participants, discussionHistory). Поля `logRound()` (step, round, isFacilitator) не маппятся на static/conditional семантику. Результат: грязный маппинг (mapping) или раздувание интерфейса.

**Вердикт:** ❌ Отвергнуто. Семантическое несовпадение (semantic mismatch) перевешивает экономию LOC. Лучше отдельный `CheckpointWriterInterface` с чистым контрактом.

### A2: Чекпоинт в памяти (in-memory checkpoint, без persistence)

Сохранять результаты в памяти (memory, array), доступные через `StaticChainExecution`.

**Плюсы:** ноль I/O, простота.

**Минусы:** при сбое процесса (crash; OOM, segfault, power loss) все результаты теряются — та же проблема, что сейчас. Resume требует живого процесса (living process).

**Вердикт:** ❌ Отвергнуто. Не решает исходную проблему (потеря результатов при crash).

### A3: Внешнее хранилище состояния (external state store; Redis / DB)

Писать checkpoint в Redis или database.

**Плюсы:** централизованное, запрашиваемое, разделяемое между экземплярами (centralised, queryable, shared between instances).

**Минусы:** добавляет infrastructure dependency. Для инструмента оркестрации, ориентированного на CLI (CLI-first orchestration tool; единственный consumer — локальный процесс), это избыточное усложнение (overengineering). JSONL-файлы уже доказали свою работоспособность в dynamic chains.

**Вердикт:** ❌ Отвергнуто на данном этапе. Может быть пересмотрено при появлении распределённой оркестрации (distributed orchestration; Q1 2027+).

### A4: Частичное повторное выполнение с кэшированием (partial re-execution с caching, без checkpoint)

Вместо checkpoint — кэшировать результаты шагов по (`chainName` + `stepIndex` + `inputHash`). При повторном запуске (re-run) проверять кэш.

**Плюсы:** не требует явного checkpoint — «автоматический» resume.

**Минусы:** хэш входных данных (input hash) сложен для LLM-вызовов (промпт может быть недетерминированным (nondeterministic)). Инвалидация кэша (cache invalidation) — классическая «проблема двух сложных вещей» (two hard things). Нет гарантии, что закэшированный результат актуален.

**Вердикт:** ❌ Отвергнуто. Ненадёжно для недетерминированных (nondeterministic) AI-вызовов. Явный чекпоинт (explicit checkpoint) = предсказуемость.

## Ссылки

- [ADR-006: ExecutionStrategy Composition](006-execution-strategy-composition.md) — архитектурный контракт стратегий
- [ADR-008: Shared Kernel Contract](008-shared-kernel-contract.md) — общий kernel для модулей
- [ADR-009: Dynamic остаётся в Orchestrator](009-dynamic-split-decision.md) — почему Dynamic не выделен в отдельный модуль
- [ExecutionStrategyInterface](../../src/Module/ChainExecution/Application/Contract/Chain/ExecutionStrategyInterface.php) — контракт `resume()`
- [ChainSessionLogger](../../src/Module/DynamicLoop/Infrastructure/Service/ChainSessionLogger.php) — эталонная реализация (reference implementation) сессии JSONL
- [StaticChainExecution](../../src/Module/ChainExecution/Domain/Entity/StaticChainExecution.php) — in-memory state static-цепочки
- [RunStaticChainService](../../src/Module/ChainExecution/Domain/Service/Static/RunStaticChainService.php) — цикл выполнения static-цепочки
