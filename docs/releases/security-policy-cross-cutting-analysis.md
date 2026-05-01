# Анализ Security Policy как Cross-Cutting Concern

**Автор:** Архитектор Гэндальф  
**Дата:** 2026-05-01  
**Задача:** [TASK-docs-security-policy-analysis](../../todo/TASK-docs-security-policy-analysis.todo.md)  
**Roadmap:** AI#14, Sprint 2 → входные данные для Sprint 9 (Security Policy implementation)  
**Источники:**
- [Roadmap 2026 Q2–Q3](ROADMAP-2026-Q2-Q3.md) — OQ-3, триггер G4, Sprint 9 plan
- [Протокол brainstorm #2](../../var/sessions/brainstorm/2026-04-30_16-02-26/result.md) — консенсус: SecurityPolicy = отдельный модуль
- [Исследование фреймворков](../research/agent-frameworks-summary.md) — Кластер 2: Безопасность
- [ADR-006: ExecutionStrategy Composition](../adr/006-execution-strategy-composition.md)
- [ADR-008: Shared Kernel Contract](../adr/008-shared-kernel-contract.md)

---

## 1. Постановка проблемы

**OQ-3 (Roadmap):** Security Policy — единственный roadmap-сценарий, где разделение Static/Dynamic создаёт архитектурную проблему. Cross-cutting concern зависит от обоих subdomain'ов. Если они в разных модулях → Shared Kernel разрастается.

**Триггер G4:** Если SecurityPolicy потребует раздельных моделей разрешений для Static и Dynamic — это сигнал к физическому разделению, а не к общему интерфейсу.

**Ключевой вопрос:** как Security Policy модуль взаимодействует со Static и Dynamic стратегиями, не нарушая слоистую архитектуру и не раздувая Shared Kernel?

---

## 2. Текущая архитектура: точки входа для Security Policy

На основе анализа текущей кодовой базы `src/Module/Orchestrator/` выявлены **6 точек**, где Security Policy должен вмешиваться в процесс выполнения:

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

### 2.3. Точка 3: Step execution (Static path)

```
RunStaticChainService::executeStep()
    → runAgentService->run(ChainRunRequestVo)
    → qualityGateRunner->run(QualityGateVo)
```

**Что можно проверить:**
- **Agent step:** разрешён ли runner (`$step->getRunner()`), tools, model
- **Quality gate step:** разрешена ли shell-команда (`$step->getCommand()`) — exec policy

**Только Static:** да, только static-цепочки имеют шаги с предопределёнными runner'ами и quality gates.

### 2.4. Точка 4: Agent run (оба path)

```
RunAgentServiceInterface::run(ChainRunRequestVo)
```

**Что можно проверить:** exec policy для runner'а, banned commands, per-path restrictions.

**Одинаково для Static и Dynamic:** оба пути используют `RunAgentServiceInterface` для вызова runner'а. Runner name, tools, model — в `ChainRunRequestVo`.

### 2.5. Точка 5: Dynamic turn (Dynamic path)

```
RunDynamicLoopService::execute()
    → agentRunner->run() для фасилитатора/участника
```

**Что можно проверить:** разрешён ли вызов конкретного runner'а для конкретной роли в runtime.

**Только Dynamic:** да, routing происходит runtime — фасилитатор решает, кто говорит. Security Policy может ограничивать допустимых участников.

### 2.6. Точка 6: Quality gate shell execution

```
QualityGateRunnerInterface::run(QualityGateVo)
```

**Что можно проверить:** exec policy — banned prefixes, safe command detection, per-path restrictions.

**Только Static:** quality gates выполняют shell-команды. Dynamic path не имеет quality gates (использует фасилитатора для оценки).

---

## 3. Оценка: разные ли permission models для Static и Dynamic?

### 3.1. Сравнительная таблица

| Аспект | Static path | Dynamic path | Общий? |
|--------|-------------|--------------|--------|
| **Authorization** (может ли цепочка запускаться) | ✅ Имя цепочки + окружение | ✅ Имя цепочки + окружение | **Да** |
| **Exec policy (shell)** | ✅ Quality gates + agent commands | ✅ Agent commands | **Частично** (quality gates — только Static) |
| **Runner restrictions** | ✅ Per-step runner в `ChainStepVo` | ✅ Per-role runner в `RoleConfigVo` | **Да** |
| **Tool restrictions** | ✅ Per-step tools в `ChainStepVo` | ✅ Per-role tools в `RoleConfigVo` | **Да** |
| **Model restrictions** | ✅ Per-step model в `ChainStepVo` | ✅ Per-role model в `RoleConfigVo` | **Да** |
| **Step-level permissions** | ✅ Предопределённые шаги | ❌ Routing runtime | **Нет** |
| **Role-level permissions** | ❌ Шаги не привязаны к participants | ✅ Участники + фасилитатор | **Нет** |
| **Iteration limits** | ✅ Fix iterations → повтор шагов | ❌ Max rounds вместо итераций | **Нет** |

### 3.2. Вывод по G4

**Permission model — одна и та же по сути, но разная granularity:**

- **Общее ядро (Core Permission Model):** exec policy (rules filter), runner/tool/model restrictions. Реализуется через одни и те же interfaces.
- **Различие в точках применения:** Static проверяет per-step, Dynamic — per-role/per-turn. Это не два разных permission models, а **два контекста применения одной модели**.

**Вердикт по G4:** Триггер **НЕ срабатывает**. Static и Dynamic имеют **одну и ту же** permission model с разной granularity применения. Shared Kernel не разрастается — достаточно одного интерфейса `SecurityPolicyCheckInterface`, применяемого в разных точках.

---

## 4. Рекомендация: Decorator + Interface Injection (а не ACL)

### 4.1. Почему не ACL

ACL (Anti-Corruption Layer) между SecurityPolicy и Orchestrator означает:
- SecurityPolicy определяет свои interfaces
- Orchestrator создаёт adapters/DTO mapping

Это оправдано при интеграции двух **независимых bounded context'ов** с разными Ubiquitous Languages. Но Security Policy — это **cross-cutting concern**, а не bounded context с собственной бизнес-логикой. Он не производит данные — он фильтрует и проверяет. ACL здесь — overengineering.

### 4.2. Почему Decorator

task-orchestrator уже использует **Decorator pattern** для cross-cutting concerns:
- `RetryingAgentRunner` decorator для retry
- `CircuitBreakerAgentRunner` decorator для circuit breaker
- Budget checking через service injection

Security Policy естественно ложится в тот же паттерн:

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
              (проверяет chain-level permissions)
```

```
                       RunAgentServiceInterface
                                  │
              SecurityPolicyRunAgentDecorator
              (проверяет exec policy, runner/tool/model)
                    │
              RetryingAgentRunner (существующий)
                    │
              CircuitBreakerAgentRunner (существующий)
                    │
              Конкретный Runner
```

### 4.3. Эскиз interfaces

Интерфейсы определяются в **Orchestrator Domain** (как port), реализуются в **SecurityPolicy module Infrastructure**:

```php
// src/Module/Orchestrator/Domain/Service/Security/

/**
 * Проверка security policy перед выполнением цепочки.
 * Порт в Orchestrator Domain, реализация — в SecurityPolicy Infrastructure.
 */
interface ChainSecurityPolicyInterface
{
    /**
     * Проверяет, авторизован ли запуск цепочки.
     * @throws SecurityPolicyViolationException если цепочка запрещена
     */
    public function checkChainExecution(string $chainName, ChainTypeEnum $type): void;
}

/**
 * Проверка exec policy для команды runner'а.
 * Порт в Orchestrator Domain, реализация — в SecurityPolicy Infrastructure.
 */
interface ExecPolicyInterface
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
├── SecurityPolicy/                     ← Sprint 9: отдельный модуль
│   └── Domain/
│       ├── Policy/                     ← Rules, Permission models
│       │   ├── ExecRule.php
│       │   └── Permission.php
│       └── Service/
│           └── SecurityPolicyService.php
│   └── Infrastructure/
│       └── Orchestrator/               ← Реализация ports Orchestrator'а
│           ├── ChainSecurityPolicy.php   (implements ChainSecurityPolicyInterface)
│           └── ExecPolicyCheck.php       (implements ExecPolicyInterface)
```

**Зависимости:**
- `SecurityPolicy Infrastructure` → `Orchestrator Domain` (interfaces only) ✅
- `Orchestrator Domain` не зависит от `SecurityPolicy` ✅
- Инверсия зависимостей через ports в Orchestrator Domain ✅

---

## 5. Ответ на OQ-3 Roadmap

> **OQ-3:** Security Policy module — единственный roadmap-сценарий, где разделение Static/Dynamic создаёт проблему.

**Ответ:** Разделение Static/Dynamic **НЕ создаёт проблемы** для Security Policy по трём причинам:

1. **Permission model — единая.** Exec policy (rules filter) и permission checks работают через одни и те же интерфейсы (`ChainSecurityPolicyInterface`, `ExecPolicyInterface`), независимо от типа цепочки. Различие — только в точках применения (per-step для Static, per-role/per-turn для Dynamic), но это реализационная деталь decorator'а.

2. **Shared Kernel не разрастается.** Interfaces размещаются в `Orchestrator/Domain/Service/Security/` — это Domain-слой Orchestrator'а, а не Shared Kernel. Shared Kernel (по ADR-008: name, budget, roles) остаётся неизменным — SecurityPolicy не добавляет в него методы.

3. **Cross-cutting реализован через Decorator, а не через shared data.** SecurityPolicy не нуждается в доступе к strategy-specific данным (шагам, промптам). Ему достаточно chain identity (имя, тип) + runner info (runner name, tools, command) — всё доступно через существующие VO без расширения контрактов.

---

## 6. Влияние на Integration-слой при split Static (Sprint 7)

Если Static физически выделяется в отдельный модуль (`src/Module/StaticExecution/`) в Sprint 7:

| Аспект | Влияние | Митигация |
|--------|---------|-----------|
| `ChainSecurityPolicyInterface` | Остается в Orchestrator Domain. StaticExecution module зависит от Orchestrator interfaces — это уже так (ExecutionStrategyInterface) | 0 изменений |
| `ExecPolicyInterface` | Может потребоваться в StaticExecution module для quality gates | Определить interface в Orchestrator Domain (port). StaticExecution Infrastructure его реализует. ИЛИ: вынести в отдельный shared Security interfaces |
| Decorator для RunAgentService | Интеграционный сервис уже проходит через Orchestrator Domain port | 0 изменений |

**Вывод:** Split Static не влияет на архитектуру Security Policy, если interfaces (ports) остаются в Orchestrator Domain. Это согласуется с Integration-паттерном из ADR-007 (VO ACL boundary).

---

## 7. Паттерны из исследования фреймворков

| Паттерн | Источник | Применимость в task-orchestrator |
|---------|----------|----------------------------------|
| **Exec policy (rules)** | Codex (.rules файлы), Claude Code (allow/deny lists) | ✅ Основной паттерн. Декларативные правила: banned prefixes, safe command detection. Реализуется через `ExecPolicyInterface` |
| **Permission system** | Claude Code (auto-accept/ask/deny), Crush (allow-list), Codex (split FS permissions) | ✅ Runner/tool/model restrictions per step/role. Реализуется через `ChainSecurityPolicyInterface` |
| **Guardian (LLM safety reviewer)** | Codex | ⚠️ R&D (Sprint 10+). Pre-execution LLM risk assessment. Дополняет rule-based exec policy. Не для Sprint 9 |
| **Docker sandbox** | Codex (iptables + Docker), Copilot Cloud Agent (container isolation) | ⚠️ R&D (Q4). Network isolation через container. Infrastructure-level, не Domain concern |
| **Policy engine** | Copilot Cloud Agent (org-level policies) | ⚠️ Долгосрочная перспектива. Организационные политики. Не для Sprint 9 |

**Рекомендация для Sprint 9:** Начать с rule-based exec policy + permission system. Это quick win, подтверждённый Codex и Claude Code.

---

## 8. Резюме для Sprint 9

### Архитектурные решения

1. **SecurityPolicy — отдельный модуль** (подтверждено brainstorm #2, все 4 участника).
2. **Interfaces (ports) в Orchestrator Domain**, реализация в SecurityPolicy Infrastructure — Dependency Inversion.
3. **Decorator pattern** для применения security checks — консистентно с retry/circuit breaker.
4. **Shared Kernel не расширяется** — SecurityPolicy не добавляет методов в `SharedChainDefinitionVo`.

### Что реализовать в Sprint 9

| Приоритет | Компонент | Описание |
|-----------|-----------|----------|
| P0 | `ExecPolicyInterface` + реализация | Rule-based command filtering: banned prefixes, safe commands |
| P0 | `ChainSecurityPolicyInterface` + реализация | Chain-level authorization: может ли цепочка запускаться |
| P1 | SecurityPolicyRunAgentDecorator | Decorator для `RunAgentServiceInterface` — проверка exec policy перед agent run |
| P1 | SecurityPolicyExecutionStrategyDecorator | Decorator для `ExecutionStrategyInterface` — chain-level checks |
| P2 | YAML DSL: `permissions:` block | Декларативные per-chain permissions в YAML-конфигурации |
| P2 | Exec policy файл | Внешний файл с rules (аналог Codex .rules) |

### Что НЕ реализовать в Sprint 9

- Docker sandboxing (Q4 R&D)
- Guardian / LLM safety reviewer (Sprint 10+)
- Org-level policy engine (долгосрочная перспектива)
- Per-path filesystem permissions (требует container isolation)

---

## Изменения

| Дата | Автор | Изменение |
|:-----|:------|:----------|
| 2026-05-01 | Архитектор (Гэндальф) | Создание документа |
