---
type: feat
created: 2026-05-02
value: V3
complexity: C2
priority: P1
depends_on: TASK-docs-security-policy-adr
epic: EPIC-sprint-9-security-policy
author: system_analyst (Шерлок)
assignee:
branch: task/feat-security-policy-domain
pr: 126
status: done
---

# TASK-feat-security-policy-domain: Domain слой модуля SecurityPolicy

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда Security Policy модуль должен проверять exec rules и permissions, я хочу создать Domain слой с [`Entity`](../../docs/conventions/layers/domain/entity.md) (ExecRule), [`Value Object`](../../docs/conventions/core_patterns/value-object.md) (Permission, RulePattern, PermissionSet), [`Enum`](../../docs/conventions/core_patterns/enum.md) (RuleAction, RuleTarget, RuleSeverity) и [`Service`](../../docs/conventions/core_patterns/service.md) (ExecPolicyCheckService, SecurityPolicyService), чтобы бизнес-логика security checks была инкапсулирована в Domain и не зависела от инфраструктуры.

### Goal (Цель по SMART)
Создать Domain слой модуля `SecurityPolicy` (`src/Module/SecurityPolicy/Domain/`) с полной бизнес-логикой exec policy checking и permission evaluation. Unit-тесты ≥80% покрытия. Срок: 1 день.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/SecurityPolicy/Domain/` (новый модуль)
*   **Текущее поведение:** Security checks отсутствуют — агенты выполняют любые команды без ограничений
*   **Границы (Out of Scope):**
    *   НЕ создавать Infrastructure-слой (Task 4)
    *   НЕ создавать ports в Orchestrator Domain (Task 3)
    *   НЕ добавлять YAML DSL (Task 5)
    *   НЕ добавлять decorators (Task 4)
    *   НЕ добавлять Symfony DI конфигурацию

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] Модуль `src/Module/SecurityPolicy/` создан с DDD-структурой каталогов
- [x] [`Enum`](../../docs/conventions/core_patterns/enum.md): `RuleActionEnum` (allow | deny), `RuleTargetEnum` (command | runner | tool | model | chain), `RuleSeverityEnum` (block | warn)
- [x] [`Entity`](../../docs/conventions/layers/domain/entity.md): `ExecRule` — декларативное правило с полями: id, action, target, pattern, severity, description, priority
- [x] [`Value Object`](../../docs/conventions/core_patterns/value-object.md): `ExecRuleIdVo`, `RulePatternVo` (glob/regex/exact matching), `PermissionVo` (resource + action), `PermissionSetVo` (набор permissions с deny-first логикой)
- [x] [`Exception`](../../docs/conventions/core_patterns/exception.md): `SecurityPolicyException` (базовый), `SecurityPolicyViolationException` (chain-level), `ExecPolicyViolationException` (exec-level, содержит violated rule info)
- [x] [`Service`](../../docs/conventions/core_patterns/service.md): `ExecPolicyCheckService` — проверяет команду/runner/tool/модель против набора ExecRule, возвращает первую violation или ok
- [x] [`Service`](../../docs/conventions/core_patterns/service.md): `SecurityPolicyService` — агрегирует chain-level checks + exec-level checks
- [x] Exec rules logic: **deny-first** — если хотя бы одно deny-правило совпадает → denied, даже если есть allow для более широкого паттерна. Priority ordering при конфликтах.
- [x] Rule matching: `RulePattern` поддерживает exact match, glob (`*`), prefix (`bash-*`)
- [x] Unit-тесты на все Entity, VO, Enum, Service ≥80% покрытия
- [x] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные

### 🟡 Should Have (Желательно)
- [x] `RuleSeverityEnum::warn` — логирование без блокировки (separate violation type)
- [x] `ExecPolicyCheckService` возвращает detailed result (matched rules, reasons) для debug

### 🟢 Could Have (Опционально)
- [ ] Rule composition: AND/OR условия
- [ ] Rule expiration (временные правила)

### ⚫ Won't Have (Не будем делать)
- [ ] Infrastructure-реализация (repository, config loading) — Task 4
- [ ] Ports/interfaces в Orchestrator — Task 3
- [ ] YAML/файловая загрузка rules — Task 5
- [ ] Symfony DI wiring
- [ ] Audit trail / logging denied operations
- [ ] LLM-based rule evaluation (Guardian)

## 4. Implementation Plan (План реализации)
1. [x] Создать каталог `src/Module/SecurityPolicy/Domain/` с подкаталогами: Entity, Enum, Exception, Service, ValueObject
2. [x] Создать Enum'ы: `RuleActionEnum`, `RuleTargetEnum`, `RuleSeverityEnum`
3. [x] Создать Exception иерархию: `SecurityPolicyException` → `SecurityPolicyViolationException`, `ExecPolicyViolationException`
4. [x] Создать Value Object'ы: `ExecRuleIdVo`, `RulePatternVo`, `PermissionVo`, `PermissionSetVo`
5. [x] Создать Entity: `ExecRule`
6. [x] Создать Service: `ExecPolicyCheckService` — rule matching + deny-first logic
7. [x] Создать Service: `SecurityPolicyService` — агрегация checks
8. [x] Написать unit-тесты: `tests/Unit/Common/Module/SecurityPolicy/Domain/`
9. [x] Проверить: `vendor/bin/phpunit`, `vendor/bin/psalm`

## 5. Definition of Done (Критерии приёмки)
- [x] Модуль SecurityPolicy/Domain содержит Enum, Entity, VO, Exception, Service
- [x] `ExecPolicyCheckService` корректно фильтрует по deny-first logic
- [x] `RulePattern` поддерживает exact, glob, prefix matching
- [x] `PermissionSet` реализует deny-first с priority ordering
- [x] `ExecPolicyViolationException` содержит violated rule info (rule id, pattern, target)
- [x] Unit-тесты ≥80% покрытия нового кода
- [x] `vendor/bin/phpunit` — зелёный
- [x] `vendor/bin/psalm` — зелёный

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Module/SecurityPolicy/
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Зависимость:** ADR-010 (Task 1) должен быть завершён — фиксирует interfaces и model
- **Риск:** Deny-first logic может усложниться при добавлении priority + severity. Митигация: чёткие unit-тесты на каждый кейс.
- **Deptrac:** `SecurityPolicy/Domain` не должен зависеть ни от каких других модулей. Только PHP std + `Psr\Log\LoggerInterface`.

## 8. Sources (Источники)
- [ ] [ADR-010: Security Policy Architecture](../../docs/adr/010-security-policy-architecture.md) (создаётся в Task 1)
- [ ] [Security Policy Cross-Cutting Analysis](../../docs/releases/security-policy-cross-cutting-analysis.md)
- [ ] [Конвенция: Entity](../../docs/conventions/layers/domain/entity.md)
- [ ] [Конвенция: Value Object](../../docs/conventions/core_patterns/value-object.md)
- [ ] [Конвенция: Service](../../docs/conventions/core_patterns/service.md)
- [ ] [Конвенция: Enum](../../docs/conventions/core_patterns/enum.md)
- [ ] [Конвенция: Exception](../../docs/conventions/core_patterns/exception.md)

## 9. Comments (Комментарии)
- Domain слой SecurityPolicy — полностью независим от Orchestrator. Не зависит от ports (они в Task 3).
- ExecRule — in-memory entity (не aggregate root). Persistence — в Infrastructure (Task 4).
- Pattern matching вдохновлён Codex `.rules` и Claude Code allow/deny lists.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-02 | system_analyst (Шерлок) | Создание задачи |
