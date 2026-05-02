---
type: feat
created: 2026-05-02
value: V3
complexity: C1
priority: P1
depends_on: TASK-docs-security-policy-adr
epic: EPIC-sprint-9-security-policy
author: system_analyst (Шерлок)
assignee:
branch:
pr:
status: todo
---

# TASK-feat-security-policy-ports: Ports (interfaces) в Orchestrator Domain для Security Policy

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда SecurityPolicy модуль должен проверять exec policy и permissions для цепочек, я хочу определить interfaces (ports) в Orchestrator Domain, чтобы Orchestrator зависел от абстракций, а SecurityPolicy Infrastructure их реализовывала (Dependency Inversion).

### Goal (Цель по SMART)
Создать два interface (port) в `src/Module/Orchestrator/Domain/Service/Security/`: `ChainSecurityPolicyInterface` (chain-level checks) и `ExecPolicyInterface` (exec-level checks). Interfaces используются decorators и strategy. Unit-тесты — mock-based проверка контракта. Срок: 3–4 часа.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/Orchestrator/Domain/Service/Security/` (новый каталог)
*   **Текущее поведение:** Security checks отсутствуют — нет точек вмешательства
*   **Границы (Out of Scope):**
    *   НЕ создавать реализацию interfaces (Task 4)
    *   НЕ создавать decorators (Task 4)
    *   НЕ менять существующие interfaces Orchestrator Domain
    *   НЕ добавлять SecurityPolicy в Shared Kernel (по ADR-008)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Каталог `src/Module/Orchestrator/Domain/Service/Security/` создан
- [ ] `ChainSecurityPolicyInterface` — метод `checkChainExecution(string $chainName, ChainTypeEnum $type): void`, бросает `SecurityPolicyViolationException` (из SecurityPolicy Domain, но referenced по FQN)
- [ ] `ExecPolicyInterface` — методы: `checkRunnerCommand(string $runnerName, string $task, ?string $tools = null): void`, `checkShellCommand(string $command): void`. Бросает `ExecPolicyViolationException`
- [ ] Interfaces следуют конвенции: [`Service`](../../docs/conventions/core_patterns/service.md) в Domain-слое
- [ ] PHPDoc на всех методах с описанием контракта и thrown exceptions
- [ ] Unit-тест: mock-based проверка, что interface контракт корректен (вызов методов, exception types)
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные

### 🟡 Should Have (Желательно)
- [ ] `ExecPolicyInterface::checkRunnerCommand()` поддерживает nullable `$tools` для backward compat

### 🟢 Could Have (Опционально)
- [ ] Marker interface `SecurityPolicyPortInterface` для Deptrac rules

### ⚫ Won't Have (Не будем делать)
- [ ] Реализация interfaces — Task 4
- [ ] Decorator classes — Task 4
- [ ] Изменение Shared Kernel — SecurityPolicy не расширяет SharedKernel
- [ ] DI wiring

## 4. Implementation Plan (План реализации)
1. [ ] Создать каталог `src/Module/Orchestrator/Domain/Service/Security/`
2. [ ] Создать `ChainSecurityPolicyInterface.php` с методом `checkChainExecution()`
3. [ ] Создать `ExecPolicyInterface.php` с методами `checkRunnerCommand()` и `checkShellCommand()`
4. [ ] Добавить PHPDoc: `@throws` annotations, параметр descriptions
5. [ ] Написать unit-тест с mock: проверка контракта interfaces
6. [ ] Проверить: `vendor/bin/phpunit`, `vendor/bin/psalm`

## 5. Definition of Done (Критерии приёмки)
- [ ] `ChainSecurityPolicyInterface` и `ExecPolicyInterface` в `Orchestrator/Domain/Service/Security/`
- [ ] Interfaces имеют PHPDoc с `@throws` annotations
- [ ] Orchestrator Domain НЕ зависит от SecurityPolicy (Deptrac: только interfaces, referenced по FQN в throws)
- [ ] Unit-тест с mock проходит
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Module/Orchestrator/Domain/Service/Security/
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Зависимость:** ADR-010 (Task 1) — фиксирует interface design
- **Риск (Deptrac):** Exception types из SecurityPolicy Domain, referenced в Orchestrator Domain interface `@throws`. Deptrac может увидеть это как dependency. **Митигация:** Exception interfaces можно определить в Orchestrator Domain (как port exceptions), либо Deptrac skip rules для `@throws`.
- **Риск (параллельность):** Task 2 (Domain) и Task 3 (Ports) можно выполнять параллельно, но exception types должны быть согласованы. Рекомендация: Task 2 первым — создаёт exception иерархию, Task 3 ссылается на них.

## 8. Sources (Источники)
- [ ] [ADR-010: Security Policy Architecture](../../docs/adr/010-security-policy-architecture.md) (создаётся в Task 1)
- [ ] [Security Policy Cross-Cutting Analysis, секция 4.3](../../docs/releases/security-policy-cross-cutting-analysis.md)
- [ ] [Архитектура: Integration-слой](../../docs/guide/architecture.md)
- [ ] [Конвенция: Service](../../docs/conventions/core_patterns/service.md)

## 9. Comments (Комментарии)
- Ports — ключевая точка Dependency Inversion. Orchestrator Domain определяет **что** нужно проверить, SecurityPolicy Infrastructure решает **как**.
- Exception design: `SecurityPolicyViolationException` и `ExecPolicyViolationException` — domain exceptions SecurityPolicy. Ports в Orchestrator ссылаются на них по FQN в `@throws`. Deptrac должен быть настроен на allowance cross-module exception references в Domain ports.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-02 | system_analyst (Шерлок) | Создание задачи |
