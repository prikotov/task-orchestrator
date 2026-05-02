---
type: feat
created: 2026-05-02
value: V3
complexity: C3
priority: P1
depends_on: TASK-feat-security-policy-domain, TASK-feat-security-policy-ports
epic: EPIC-sprint-9-security-policy
author: system_analyst (Шерлок)
assignee:
branch: task/feat-security-policy-infrastructure
pr: 128
status: done
---

# TASK-feat-security-policy-infrastructure: Infrastructure реализация ports + Decorators

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда SecurityPolicy Domain логика готова и ports определены в Orchestrator, я хочу создать Infrastructure-реализацию ports и decorator-обёртки, чтобы security checks автоматически применялись при выполнении цепочек и agent runs — через DI, без изменения Domain-кода.

### Goal (Цель по SMART)
Создать: (1) `ChainSecurityPolicy` и `ExecPolicyCheck` в SecurityPolicy Infrastructure (implements ports из Orchestrator Domain), (2) `SecurityPolicyRunAgentDecorator` и `SecurityPolicyExecutionStrategyDecorator` в Orchestrator Infrastructure, (3) In-memory ExecRule repository, (4) Symfony DI wiring. Unit-тесты ≥80%. Срок: 1 день.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `src/Module/SecurityPolicy/Infrastructure/Orchestrator/` — реализация ports
    *   `src/Module/SecurityPolicy/Infrastructure/Persistence/` — In-memory repository
    *   `src/Module/SecurityPolicy/Application/Service/` — Application service
    *   `src/Module/Orchestrator/Infrastructure/Service/Security/` — decorators (или `src/Module/Orchestrator/Integration/Service/Security/`)
    *   `config/services.yaml` — DI wiring
*   **Текущее поведение:** Security checks отсутствуют, agents run без ограничений
*   **Границы (Out of Scope):**
    *   НЕ менять Domain-слой SecurityPolicy (Task 2)
    *   НЕ менять ports в Orchestrator Domain (Task 3)
    *   НЕ добавлять YAML DSL `permissions:` (Task 5)
    *   НЕ добавлять file-based exec policy loading (Task 5)
    *   НЕ добавлять audit trail / logging denied operations

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `ChainSecurityPolicy` в `SecurityPolicy/Infrastructure/Orchestrator/` implements `ChainSecurityPolicyInterface`
- [ ] `ExecPolicyCheck` в `SecurityPolicy/Infrastructure/Orchestrator/` implements `ExecPolicyInterface`
- [ ] `InMemoryExecRuleRepository` — хранит набор ExecRule в памяти (для Sprint 9 — hardcoded rules)
- [ ] Default rules: banned prefixes (`bash -c`, `rm -rf /`, `sudo`), allowed runners (all by default), no tool restrictions by default
- [ ] `SecurityPolicyRunAgentDecorator` — decorator для [`RunAgentServiceInterface`](../../src/Module/Orchestrator/Domain/Service/Integration/RunAgentServiceInterface.php): перед `run()` вызывает `ExecPolicyInterface::checkRunnerCommand()`
- [ ] `SecurityPolicyExecutionStrategyDecorator` — decorator для [`ExecutionStrategyInterface`](../../src/Module/Orchestrator/Application/Service/Chain/ExecutionStrategyInterface.php): перед `execute()` вызывает `ChainSecurityPolicyInterface::checkChainExecution()`
- [ ] Symfony DI wiring: decorators оборачивают реальные services через decoration pattern
- [ ] Unit-тесты на все Infrastructure-классы ≥80% покрытия
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные
- [ ] Deptrac green

### 🟡 Should Have (Желательно)
- [ ] `SecurityPolicyApplicationService` — Application-level orchestration между Domain Service и Infrastructure
- [ ] Configurable default rules через Symfony parameters (пока hardcoded)

### 🟢 Could Have (Опционально)
- [ ] Feature flag для включения/выключения security checks (по умолчанию enabled)

### ⚫ Won't Have (Не будем делать)
- [ ] YAML-based rule loading — Task 5
- [ ] File-based exec policy — Task 5
- [ ] Database persistence
- [ ] Audit trail / logging
- [ ] Symfony Bundle configuration exposure (TreeBuilder для security_policy)

## 4. Implementation Plan (План реализации)
1. [ ] Создать `SecurityPolicy/Infrastructure/Orchestrator/ChainSecurityPolicy.php` — implements port
2. [ ] Создать `SecurityPolicy/Infrastructure/Orchestrator/ExecPolicyCheck.php` — implements port
3. [ ] Создать `SecurityPolicy/Infrastructure/Persistence/InMemoryExecRuleRepository.php`
4. [ ] Создать `SecurityPolicy/Application/Service/SecurityPolicyApplicationService.php` (если Should Have)
5. [ ] Создать `Orchestrator/Infrastructure/Service/Security/SecurityPolicyRunAgentDecorator.php`
6. [ ] Создать `Orchestrator/Infrastructure/Service/Security/SecurityPolicyExecutionStrategyDecorator.php`
7. [ ] Настроить DI wiring в `config/services.yaml` — decoration pattern
8. [ ] Написать unit-тесты на Infrastructure-классы
9. [ ] Проверить: `vendor/bin/phpunit`, `vendor/bin/psalm`, Deptrac

## 5. Definition of Done (Критерии приёмки)
- [ ] `ChainSecurityPolicy` implements `ChainSecurityPolicyInterface` — делегирует в `SecurityPolicyService`
- [ ] `ExecPolicyCheck` implements `ExecPolicyInterface` — делегирует в `ExecPolicyCheckService`
- [ ] `SecurityPolicyRunAgentDecorator` проверяет exec policy перед `RunAgentServiceInterface::run()`
- [ ] `SecurityPolicyExecutionStrategyDecorator` проверяет chain policy перед `ExecutionStrategyInterface::execute()`
- [ ] DI wiring: decorators правильно оборачивают inner services
- [ ] Default rules блокируют `bash -c`, `rm -rf /`, `sudo`
- [ ] Unit-тесты ≥80% покрытия Infrastructure-классов
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные
- [ ] Deptrac green (SecurityPolicy Infrastructure → Orchestrator Domain interfaces only)

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Module/SecurityPolicy/Infrastructure/
vendor/bin/phpunit tests/Unit/Module/Orchestrator/Infrastructure/Service/Security/
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Зависимость:** Task 2 (Domain) — SecurityPolicyService, ExecPolicyCheckService, Exception types
- **Зависимость:** Task 3 (Ports) — ChainSecurityPolicyInterface, ExecPolicyInterface
- **Риск (Deptrac):** Decorator в Orchestrator Infrastructure зависит от SecurityPolicy Domain Exception types. Deptrac-правила должны разрешать cross-module exception references для decorators.
- **Риск (DI decoration):** Symfony decoration pattern требует правильного тегирования. Если inner service уже decorated (retry, circuit breaker) — порядок decoration важен. Рекомендация: SecurityPolicy decorator — outermost (проверка до retry).

## 8. Sources (Источники)
- [ ] [ADR-010: Security Policy Architecture](../../docs/adr/010-security-policy-architecture.md) (создаётся в Task 1)
- [ ] [Security Policy Cross-Cutting Analysis, секция 4.2–4.4](../../docs/releases/security-policy-cross-cutting-analysis.md)
- [ ] [Архитектура: Integration-слой](../../docs/guide/architecture.md)
- [ ] [Конвенция: External Service](../../docs/conventions/core_patterns/external-service.md)
- [ ] [Конвенция: Wrapper](../../docs/conventions/core_patterns/wrapper.md)

## 9. Comments (Комментарии)
- **Decorator ordering (важно):** SecurityPolicy → RetryingAgentRunner → CircuitBreakerAgentRunner → Concrete Runner. Security check — первый (outermost), чтобы не тратить retry на запрещённые команды.
- `InMemoryExecRuleRepository` — временное решение для Sprint 9. В Task 5 (YAML DSL) будет добавлен `YamlExecRuleRepository`.
- DI wiring использует Symfony decoration pattern: `decorates: RunAgentServiceInterface` + `decoration_on_invalid: ignore`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-02 | system_analyst (Шерлок) | Создание задачи |
