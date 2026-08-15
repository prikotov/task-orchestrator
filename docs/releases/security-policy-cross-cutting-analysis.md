# Анализ политики безопасности (`SecurityPolicy`) как сквозной функциональности

**Автор:** Архитектор Гэндальф  
**Дата:** 2026-05-01  
**Задача:** [TASK-docs-security-policy-analysis](../../todo/done/TASK-docs-security-policy-analysis.todo.md)  
**Roadmap:** AI#14, Sprint 2 → входные данные для Sprint 9 (реализация политики безопасности `SecurityPolicy`)
**Источники:**
- [Roadmap 2026 Q2–Q3](ROADMAP-2026-Q2-Q3.md) — `OQ-3`, триггер G4, план Sprint 9
- [Протокол brainstorm #2](../../var/sessions/brainstorm/2026-04-30_16-02-26/result.md) — консенсус: `SecurityPolicy` = отдельный модуль
- [Исследование фреймворков](../research/agent-frameworks-summary.md) — Кластер 2: Безопасность
- [ADR-006: ExecutionStrategy Composition](../adr/006-execution-strategy-composition.md)
- [ADR-008: контракт общего ядра (`Shared Kernel Contract`)](../adr/008-shared-kernel-contract.md)

---

## 1. Постановка проблемы

**`OQ-3` (Roadmap):** Политика безопасности (`SecurityPolicy`) — единственный roadmap-сценарий, где разделение Static/Dynamic создаёт архитектурную проблему. Сквозная функциональность (`cross-cutting`) зависит от обоих subdomain'ов. Если они в разных модулях → общее ядро разрастается.

**Триггер G4:** Если `SecurityPolicy` потребует раздельных моделей разрешений для Static и Dynamic — это сигнал к физическому разделению, а не к общему интерфейсу.

**Ключевой вопрос:** как модуль политики безопасности (`SecurityPolicy`) взаимодействует со Static и Dynamic стратегиями, не нарушая слоистую архитектуру и не раздувая общее ядро?

---

## 2. Текущая архитектура: точки входа для политики безопасности

На основе анализа текущей кодовой базы `src/Module/ChainDefinition/` выявлены **6 точек**, где политика безопасности (`SecurityPolicy`) должна вмешиваться в процесс выполнения:

### 2.1. Точка 1: CommandHandler — вход в оркестрацию

```
OrchestrateChainCommandHandler::__invoke()
    → chainLoader->load($chainName)
    → resolveStrategy($chain)
    → strategy->execute($chain, $command)
```

**Что можно проверить:** авторизован ли запуск конкретной цепочки для данного окружения/пользователя.

**Одинаково для Static и Dynamic:** да — на уровне `ChainDefinitionVo` (имя цепочки, тип, бюджет).

### 2.2. Точка 2: ExecutionStrategy::execute() — начало выполнения

```
StaticExecutionStrategy::execute()  → ExecuteStaticChainService
DynamicExecutionStrategy::execute() → RunDynamicLoopService
```

**Что можно проверить:** разрешения на уровне стратегии (разрешён ли данный тип выполнения в данном окружении).

**Различие:** Static и Dynamic имеют разные профили рисков. Dynamic (фасилитатор routing runtime) — выше риск непредсказуемых действий. Static (предопределённые шаги) — ниже риск, но quality gates выполняют shell-команды.

### 2.3. Точка 3: Выполнение шага (статический путь)

```
RunStaticChainService::executeStep()
    → runAgentService->run(ChainRunRequestVo)
    → qualityGateRunner->run(QualityGateVo)
```

**Что можно проверить:**
- **Шаг агента:** разрешён ли runner (`$step->getRunner()`), tools, model
- **Шаг quality gate:** разрешена ли shell-команда (`$step->getCommand()`) — `exec policy`

**Только Static:** да, только static-цепочки имеют шаги с предопределёнными runner'ами и quality gates.

### 2.4. Точка 4: Запуск агента (Agent run; оба пути)

```
RunAgentServiceInterface::run(ChainRunRequestVo)
```

**Что можно проверить:** `exec policy` для runner'а, запрещённые команды, ограничения для пути (`per-path`).

**Одинаково для Static и Dynamic:** оба пути используют `RunAgentServiceInterface` для вызова runner'а. Runner name, tools, model — в `ChainRunRequestVo`.

### 2.5. Точка 5: Dynamic turn (динамический путь)

```
RunDynamicLoopService::execute()
    → agentRunner->run() для фасилитатора/участника
```

**Что можно проверить:** разрешён ли вызов конкретного runner'а для конкретной роли в runtime.

**Только Dynamic:** да, маршрутизация происходит во время выполнения — фасилитатор решает, кто говорит. Security Policy может ограничивать допустимых участников.

### 2.6. Точка 6: Выполнение shell-команд quality gate

```
QualityGateRunnerInterface::run(QualityGateVo)
```

**Что можно проверить:** `exec policy` — запрещённые префиксы, обнаружение безопасных команд, ограничения для пути (`per-path`).

**Только Static:** quality gates выполняют shell-команды. Динамический путь не имеет quality gates (использует фасилитатора для оценки).

---

## 3. Оценка: разные ли модели разрешений для Static и Dynamic?

### 3.1. Сравнительная таблица

| Аспект | Статический путь | Динамический путь | Общий? |
|--------|-------------|--------------|--------|
| **Авторизация (Authorization)** (может ли цепочка запускаться) | ✅ Имя цепочки + окружение | ✅ Имя цепочки + окружение | **Да** |
| **`exec policy` (shell)** | ✅ Quality gates + команды агента (agent commands) | ✅ Команды агента | **Частично** (quality gates — только Static) |
| **Ограничения runner** | ✅ `per-step` runner в `ChainStepVo` | ✅ `per-role` runner в `RoleConfigVo` | **Да** |
| **Ограничения tools** | ✅ `per-step` tools в `ChainStepVo` | ✅ `per-role` tools в `RoleConfigVo` | **Да** |
| **Ограничения model** | ✅ `per-step` model в `ChainStepVo` | ✅ `per-role` model в `RoleConfigVo` | **Да** |
| **разрешения уровня шага (`step-level`) (permissions)** | ✅ Предопределённые шаги | ❌ Маршрутизация (routing) в runtime | **Нет** |
| **`role-level` разрешения (permissions)** | ❌ Шаги не привязаны к участникам (participants) | ✅ Участники + фасилитатор | **Нет** |
| **Лимиты итераций (iteration limits)** | ✅ Fix iterations → повтор шагов | ❌ Максимум раундов (max rounds) вместо итераций | **Нет** |

### 3.2. Вывод по G4

**Permission model — одна и та же по сути, но разная гранулярность (granularity):**

- **Общее ядро (Core Permission Model):** `exec policy` (фильтр правил), ограничения runner/инструмента/модели. Реализуется через одни и те же интерфейсы.
- **Различие в точках применения:** Static проверяет `per-step`, Dynamic — `per-role`/`per-turn`. Это не две разные модели разрешений, а **два контекста применения одной модели**.

**Вердикт по G4:** Триггер **НЕ срабатывает**. Static и Dynamic имеют **одну и ту же** permission model с разной гранулярностью (granularity) применения. Общее ядро не разрастается — достаточно двух интерфейсов (`ChainSecurityPolicyInterface` + `ExecPolicyInterface`), применяемых в разных точках (см. секцию 4.3).

---

## 4. Рекомендация: Decorator + Interface Injection (а не ACL)

### 4.1. Почему не ACL

ACL (Anti-Corruption Layer) между `SecurityPolicy` и Orchestrator означает:
- `SecurityPolicy` определяет свои `interfaces`
- Orchestrator создаёт adapters/DTO mapping

Это оправдано при интеграции двух **независимых ограниченных контекстов (bounded context)** с разными едиными языками (Ubiquitous Language). Но Security Policy — это **сквозная функциональность (`cross-cutting`)**, а не bounded context с собственной бизнес-логикой. Он не производит данные — он фильтрует и проверяет. ACL здесь — избыточное усложнение (overengineering).

### 4.2. Почему Decorator

task-orchestrator уже использует **паттерн Decorator** для сквозной функциональности (`cross-cutting`):
- `RetryingAgentRunner` decorator для retry
- `CircuitBreakerAgentRunner` decorator для circuit breaker
- Budget checking через service injection

Политика безопасности (`SecurityPolicy`) естественно ложится в тот же паттерн:

```
                         ExecutionStrategyInterface
                                  │
                    ┌─────────────┼─────────────┐
                    │             │             │
          StaticExecution    DynamicExec    Conditional...
                    │             │
                    └──────┬──────┘
                           │
              SecurityPolicyExecutionStrategyDecorator
              (проверяет `chain-level` permissions)
```

```
                       RunAgentServiceInterface
                                  │
              SecurityPolicyRunAgentDecorator
              (проверяет `exec policy`, runner/tool/model)
                    │
              RetryingAgentRunner (существующий)
                    │
              CircuitBreakerAgentRunner (существующий)
                    │
              Конкретный Runner
```

### 4.3. Эскиз `interfaces`

Интерфейсы определяются в **Orchestrator Domain** (как port), реализуются в **`SecurityPolicy` module Infrastructure**:

```php
// src/Module/ChainDefinition/Domain/Service/Security/

/**
 * Проверка политики безопасности перед выполнением цепочки.
 * Порт в Orchestrator Domain, реализация — в `SecurityPolicy` Infrastructure.
 */
`interface` ChainSecurityPolicyInterface
{
    /**
     * Проверяет, авторизован ли запуск цепочки.
     * @throws SecurityPolicyViolationException если цепочка запрещена
     */
    public function checkChainExecution(string $chainName, ChainTypeEnum $type): void;
}

/**
 * Проверка `exec policy` для команды runner'а.
 * Порт в Orchestrator Domain, реализация — в `SecurityPolicy` Infrastructure.
 */
`interface` ExecPolicyInterface
{
    /**
     * Проверяет, разрешена ли команда runner'а.
     * @throws ExecPolicyViolationException если команда запрещена
     */
    public function checkRunnerCommand(string $runnerName, string $task, ?string $tools = null): void;

    /**
     * Проверяет, разрешена ли shell-команда (quality gates).
     * @throws ExecPolicyViolationException если команда запрещена
     */
    public function checkShellCommand(string $command): void;
}
```

### 4.4. Слой размещения

```
src/Module/
├── Orchestrator/
│   └── Domain/Service/Security/        ← Interfaces (ports)
│       ├── ChainSecurityPolicyInterface.php
│       └── ExecPolicyInterface.php
│   └── Infrastructure/Security/        ← Decorators (если нужно)
│       └── SecurityPolicyRunAgentDecorator.php
│
├── `SecurityPolicy`/                     ← Sprint 9: отдельный модуль
│   └── Domain/
│       ├── Policy/                     ← Rules, Permission models
│       │   ├── ExecRule.php
│       │   └── Permission.php
│       └── Service/
│           └── SecurityPolicyService.php
│   └── Infrastructure/
│       └── Orchestrator/               ← Реализация ports Orchestrator'а
│           ├── Chain`SecurityPolicy`.php   (implements ChainSecurityPolicyInterface)
│           └── ExecPolicyCheck.php       (implements ExecPolicyInterface)
```

**Зависимости:**
- ``SecurityPolicy` Infrastructure` → `Orchestrator Domain` (`interfaces` only) ✅
- `Orchestrator Domain` не зависит от ``SecurityPolicy`` ✅
- Инверсия зависимостей через ports в Orchestrator Domain ✅

---

## 5. Ответ на `OQ-3` Roadmap

> **`OQ-3`:** Модуль политики безопасности (`SecurityPolicy`) — единственный roadmap-сценарий, где разделение Static/Dynamic создаёт проблему.

**Ответ:** Разделение Static/Dynamic **НЕ создаёт проблемы** для политики безопасности по трём причинам:

1. **Permission model — единая.** `exec policy` (фильтр правил) и проверки разрешений работают через одни и те же интерфейсы (`ChainSecurityPolicyInterface`, `ExecPolicyInterface`), независимо от типа цепочки. Различие — только в точках применения (`per-step` для Static, `per-role`/`per-turn` для Dynamic), но это реализационная деталь decorator'а.

2. **Общее ядро не разрастается.** Interfaces размещаются в `Orchestrator/Domain/Service/Security/` — это Domain-слой Orchestrator'а, а не общее ядро. Общее ядро (по ADR-008: name, budget, roles) остаётся неизменным — `SecurityPolicy` не добавляет в него методы.

3. **Сквозная функциональность реализована через Decorator, а не через общие данные.** `SecurityPolicy` не нуждается в доступе к данным, специфичным для стратегии (шагам, промптам). Ему достаточно идентичности цепочки (имя, тип) и информации о runner'е (имя runner'а, инструменты, команда) — всё доступно через существующие VO без расширения контрактов.

---

## 6. Влияние на Integration-слой при split Static (Sprint 7)

Если Static физически выделяется в отдельный модуль (`src/Module/StaticExecution/`) в Sprint 7:

| Аспект | Влияние | Митигация |
|--------|---------|-----------|
| `ChainSecurityPolicyInterface` | Остается в Orchestrator Domain. StaticExecution module зависит от Orchestrator `interfaces` — это уже так (ExecutionStrategyInterface) | 0 изменений |
| `ExecPolicyInterface` | Может потребоваться в модуле StaticExecution для quality gate | Определить интерфейс в Orchestrator Domain (port). StaticExecution Infrastructure его реализует. ИЛИ: вынести в отдельные общие интерфейсы Security |
| Decorator для RunAgentService | Интеграционный сервис уже проходит через Orchestrator Domain port | 0 изменений |

**Вывод:** Разделение Static не влияет на архитектуру политики безопасности, если `interfaces` (ports) остаются в Orchestrator Domain. Это согласуется с Integration-паттерном из ADR-007 (VO ACL boundary).

---

## 7. Паттерны из исследования фреймворков

| Паттерн | Источник | Применимость в task-orchestrator |
|---------|----------|----------------------------------|
| **`exec policy` (rules)** | Codex (.rules-файлы), Claude Code (списки разрешений/запретов) | ✅ Основной паттерн. Декларативные правила: запрещённые префиксы, обнаружение безопасных команд. Реализуется через `ExecPolicyInterface` |
| **Система разрешений (Permission system)** | Claude Code (auto-accept/ask/deny), Crush (allow-list), Codex (разделённые разрешения файловой системы) | ✅ Ограничения runner/tool/model на уровне шага/роли. Реализуется через `ChainSecurityPolicyInterface` |
| **Guardian (LLM-ревьюер безопасности, safety reviewer)** | Codex | ⚠️ R&D (Sprint 10+). `pre-execution` оценка рисков LLM (risk assessment). Дополняет `rule-based` `exec policy`. Не для Sprint 9 |
| **Docker-песочница (sandbox)** | Codex (iptables + Docker), Copilot Cloud Agent (изоляция контейнера, container isolation) | ⚠️ R&D (Q4). Сетевая изоляция (network isolation) через контейнер (container). `infrastructure-level`, не Domain-задача (аспект) |
| **Движок политик (Policy engine)** | Copilot Cloud Agent (`org-level` policies) | ⚠️ Долгосрочная перспектива. Организационные политики. Не для Sprint 9 |

**Рекомендация для Sprint 9:** Начать с `rule-based` `exec policy` и системы разрешений. Это быстрая полезная доработка, подтверждённый Codex и Claude Code.

---

## 8. Резюме для Sprint 9

### Архитектурные решения

1. **`SecurityPolicy` — отдельный модуль** (подтверждено brainstorm #2, все 4 участника).
2. **Interfaces (ports) в Orchestrator Domain**, реализация в `SecurityPolicy` Infrastructure — Dependency Inversion.
3. **Decorator pattern** для применения проверок безопасности — консистентно с retry/circuit breaker.
4. **Общее ядро не расширяется** — `SecurityPolicy` не добавляет методов в `SharedChainDefinitionVo`.

### Что реализовать в Sprint 9

| Приоритет | Компонент | Описание |
|-----------|-----------|----------|
| P0 | `ExecPolicyInterface` + реализация | Фильтрация команд на основе правил (`rule-based`): запрещённые префиксы, безопасные команды |
| P0 | `ChainSecurityPolicyInterface` + реализация | `chain-level` authorization: может ли цепочка запускаться |
| P1 | SecurityPolicyRunAgentDecorator | Decorator для `RunAgentServiceInterface` — проверка `exec policy` перед agent run |
| P1 | SecurityPolicyExecutionStrategyDecorator | Decorator для `ExecutionStrategyInterface` — проверки уровня цепочки (`chain-level`) |
| P2 | YAML DSL: `permissions:` block | Декларативные `per-chain` permissions в YAML-конфигурации |
| P2 | `exec policy` файл | Внешний файл с rules (аналог Codex .rules) |

### Что НЕ реализовать в Sprint 9

- Docker sandboxing (Q4 R&D)
- Guardian / LLM safety reviewer (Sprint 10+)
- `org-level` policy engine (долгосрочная перспектива)
- `per-path` filesystem permissions (требует container isolation)

---

## Изменения

| Дата | Автор | Изменение |
|:-----|:------|:----------|
| 2026-05-01 | Архитектор (Гэндальф) | Создание документа |
