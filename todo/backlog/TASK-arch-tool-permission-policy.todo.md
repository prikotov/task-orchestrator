---
type: research
created: 2026-06-12
value: V2
complexity: C2
priority: P3
depends_on: []
epic:
author: Аналитик (Шерлок)
assignee:
branch:
pr:
status: backlog
---

# TASK-arch-tool-permission-policy: Политика allow/deny инструментов по ролям/chains

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда агент выполняет цепочку с shell-командами, я хочу декларативную политику allow/deny/ask по ролям/chains, чтобы предотвратить несанкционированные действия и обеспечить granular control.

### Goal (Цель по SMART)
Спроектировать и реализовать Tool Permission Policy: allow/deny/ask для инструментов по ролям/chains с glob patterns и inheritance для sub-agents. Определить entity/VO, policy engine, и интегрировать с нашей DDD/Clean Architecture.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/AgentRunner/` или `src/Module/Orchestrator/` (Application + Domain layers). Возможные новые сущности: `ToolPermission`, `ToolPermissionPolicy`, `ToolPermissionRule`, `RolePermission`, `ChainPermission`.
*   **Текущее поведение:** task-orchestrator не имеет tool permission system. Shell-команды выполняются без ограничений (кроме circuit breaker).
*   **Границы (Out of Scope):** Не реализовывать sandboxing (Docker/containers). Не интегрировать с OS-level permissions (chmod/chown).

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Определить entity/VO для Tool Permission: `ToolPermissionId`, `ToolName`, `PermissionAction` (allow/deny/ask), `GlobPattern` (for command paths/arguments)
- [ ] Определить entity/VO для Policy: `ToolPermissionPolicyId`, `PolicyScope` (role/chain), `PolicyRules` (collection of `ToolPermissionRule`)
- [ ] Определить policy engine interface: `ToolPermissionPolicyInterface` (Domain layer) с method `evaluate(ToolName, CommandContext): PermissionDecision`
- [ ] Реализовать policy engine (Infrastructure layer): glob pattern matching, rule evaluation (last-matching-wins), fallback to default
- [ ] Определить use case для permission checking: `CheckToolPermissionQuery/Handler` (Application layer)
- [ ] Интегрировать permission checking в agent runner execution flow (Application layer)
- [ ] Указать ссылки на конвенции: [`Entity`](../../docs/conventions/layers/domain/entity.md), [`VO`](../../docs/conventions/core_patterns/value-object.md), [`Repository`](../../docs/conventions/layers/domain/repository.md), [`Use Case`](../../docs/conventions/layers/application/use_case.md), [`Service`](../../docs/conventions/core_patterns/service.md)

### 🟡 Should Have (Желательно)
- [ ] Реализовать role-based permissions: `RolePermission` entity (role → policy mapping)
- [ ] Реализовать chain-based permissions: `ChainPermission` entity (chain → policy mapping)
- [ ] Реализовать inheritance для sub-agents: sub-agent inherits parent's permissions with optional override
- [ ] Реализовать permission context: includes path, arguments, environment variables

### 🟢 Could Have (Опционально)
- [ ] Рассмотреть permission caching (Infrastructure layer)
- [ ] Рассмотреть permission audit trail (log each permission decision)
- [ ] Рассмотреть permission versioning (policy updates over time)

### ⚫ Won't Have (Не будем делать)
- [ ] Не реализовывать sandboxing (Docker/containers)
- [ ] Не интегрировать с OS-level permissions (chmod/chown)
- [ ] Не реализовывать LLM-based permission evaluation (only declarative rules)

## 4. Implementation Plan (План реализации)
*План заполняется исполнителем перед стартом.*
1. [ ] Определить entity/VO для Tool Permission, Policy, RolePermission, ChainPermission
2. [ ] Определить policy engine interface
3. [ ] Реализовать policy engine (glob matching, rule evaluation, fallback)
4. [ ] Определить use case handler (CheckToolPermissionQuery)
5. [ ] Интегрировать permission checking в agent runner execution flow
6. [ ] Создать unit-тесты для policy engine
7. [ ] Обновить документацию (архитектура, permission policy examples)

## 5. Definition of Done (Критерии приёмки)
- [ ] Определены entity/VO для Tool Permission, Policy, RolePermission, ChainPermission (Domain layer)
- [ ] Определен policy engine interface (Domain layer) и implementation (Infrastructure layer)
- [ ] Определен use case handler (CheckToolPermissionQuery) (Application layer)
- [ ] Интегрировано permission checking в agent runner execution flow
- [ ] Созданы unit-тесты для policy engine
- [ ] Обновлена документация: архитектура permission policy, examples

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Module/AgentRunner/Infrastructure/Policy/ToolPermissionPolicyEngineTest.php
vendor/bin/phpunit tests/Integration/Module/AgentRunner/Application/Handler/CheckToolPermissionQueryHandlerTest.php
ls config/permissions/  # Проверка примеров конфигурации (если есть)
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск:** Glob pattern matching может быть slow for large rule sets — нужно оптимизировать или кэшировать.
- **Риск:** Permission checking overhead may affect performance — need to measure and optimize.
- **Зависимость:** Задача может быть блокирована до определения final role/chain concept (если ещё не определён).

## 8. Sources (Источники)
- [odysseus-comparison.md](../../docs/research/framework-comparisons/odysseus-comparison.md) — секция "Implementation Candidates for task-orchestrator"
- [Kilo Code comparison](../../docs/research/framework-comparisons/opencode-orchestrator-comparison.md) — permission system с glob patterns
- [Claude Code comparison](../../docs/research/framework-comparisons/claude-code-comparison.md) — allow/deny permission system

## 9. Comments (Комментарии)
Цель этой задачи — declarative permission system без sandboxing. Sandbox (Docker/containers) — отдельная задача/эпик.

**AGPL disclaimer:** Концепция permission system с glob patterns взята из Odysseus/Kilo Code/Claude Code, но мы не копируем код. Implementation будет с нуля в нашей DDD/Clean Architecture.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-12 | Аналитик (Шерлок) | Создание задачи |