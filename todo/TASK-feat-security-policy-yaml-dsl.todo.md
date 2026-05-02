---
type: feat
created: 2026-05-02
value: V2
complexity: C2
priority: P2
depends_on: TASK-feat-security-policy-infrastructure
epic: EPIC-sprint-9-security-policy
author: system_analyst (Шерлок)
assignee:
branch:
pr:
status: todo
---

# TASK-feat-security-policy-yaml-dsl: YAML DSL `permissions:` block + Exec policy файл

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда security checks работают через hardcoded rules, а разработчик цепочки хочет явно указать какие runners/tools/commands доступны для конкретной цепочки, я хочу добавить `permissions:` block в YAML-chain DSL и внешний exec policy файл, чтобы политики безопасности конфигурировались декларативно, а не в коде.

### Goal (Цель по SMART)
Расширить YAML-chain DSL блоком `permissions:` (per-chain allow/deny для runners, tools, models) и создать внешний exec policy файл (`config/security_policy.yaml`) с declarative rules. `YamlChainLoader` парсит `permissions:`, `YamlExecRuleRepository` загружает rules из файла. Срок: 1 день.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `src/Module/Orchestrator/Infrastructure/Service/Chain/YamlChainLoader.php` — расширение парсинга
    *   `src/Module/Orchestrator/Domain/ValueObject/` — новый VO для permissions config
    *   `src/Module/SecurityPolicy/Infrastructure/Persistence/` — `YamlExecRuleRepository`
    *   `config/security_policy.yaml` — default exec policy file
    *   `apps/console/config/agent_chains.yaml` — пример `permissions:` block
*   **Текущее поведение:** YAML-chain содержит только chain definition (steps, budget, roles). Security policy — hardcoded default rules.
*   **Границы (Out of Scope):**
    *   НЕ менять Domain-слой SecurityPolicy (Task 2)
    *   НЕ менять ports (Task 3)
    *   НЕ менять decorator logic (Task 4)
    *   НЕ добавлять JSON Schema валидацию YAML (Sprint 10)
    *   НЕ добавлять Symfony TreeBuilder для security_policy config

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Exec policy файл `config/security_policy.yaml` с default rules (banned prefixes, allowed runners)
- [ ] `YamlExecRuleRepository` в SecurityPolicy Infrastructure — загружает ExecRule из YAML файла
- [ ] `YamlExecRuleRepository` заменяет `InMemoryExecRuleRepository` в DI (или fallback: если файл не найден → InMemory defaults)
- [ ] Unit-тесты на `YamlExecRuleRepository` — парсинг YAML в ExecRule entities
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные

### 🟡 Should Have (Желательно)
- [ ] `permissions:` block в YAML-chain DSL:
    ```yaml
    chains:
      my-chain:
        type: static
        permissions:
          allowed_runners: [pi, codex]
          denied_commands: ['rm -rf', 'sudo']
          allowed_tools: ['file_read', 'file_write']
    ```
- [ ] [`ChainDefinitionVo`](../../src/Module/Orchestrator/Domain/ValueObject/ChainDefinitionVo.php) расширен для хранения permissions config
- [ ] `ChainSecurityPolicy` использует chain-specific permissions из `ChainDefinitionVo`
- [ ] Обратная совместимость: цепочки без `permissions:` block работают с default policy

### 🟢 Could Have (Опционально)
- [ ] Per-step `permissions:` override в YAML
- [ ] Environment-specific policies (dev vs prod)

### ⚫ Won't Have (Не будем делать)
- [ ] JSON Schema валидация YAML — Sprint 10
- [ ] Symfony TreeBuilder/Configuration для security_policy — separate task
- [ ] Runtime hot-reload exec policy file
- [ ] Per-path filesystem permissions
- [ ] UI для управления политиками

## 4. Implementation Plan (План реализации)
1. [ ] Создать `config/security_policy.yaml` — default exec rules
2. [ ] Создать `YamlExecRuleRepository` — YAML parsing → ExecRule[]
3. [ ] Обновить DI wiring: `YamlExecRuleRepository` вместо `InMemoryExecRuleRepository`
4. [ ] Написать unit-тесты на `YamlExecRuleRepository`
5. [ ] (Should Have) Добавить `permissions:` в chain YAML schema
6. [ ] (Should Have) Расширить `ChainDefinitionVo` для permissions
7. [ ] (Should Have) Обновить `ChainSecurityPolicy` для chain-specific permissions
8. [ ] Проверить: `vendor/bin/phpunit`, `vendor/bin/psalm`

## 5. Definition of Done (Критерии приёмки)
- [ ] `config/security_policy.yaml` содержит default rules (banned prefixes, allowed runners)
- [ ] `YamlExecRuleRepository` загружает rules из YAML и возвращает `ExecRule[]`
- [ ] Exec policy file hot-reloadable при следующем chain execution (не runtime, но при reload)
- [ ] Unit-тесты на YAML parsing: valid YAML, missing file (fallback), invalid rules (skip with warning)
- [ ] (Should Have) `permissions:` block в chain YAML поддерживает `allowed_runners`, `denied_commands`, `allowed_tools`
- [ ] (Should Have) Цепочки без `permissions:` работают с default policy
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Module/SecurityPolicy/Infrastructure/Persistence/
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Зависимость:** Task 4 (Infrastructure) — ExecPolicyCheckService, ExecPolicyCheck, InMemoryExecRuleRepository
- **Риск (YAML parsing):** Некорректный YAML в security_policy.yaml не должен крашить приложение. Митигация: fallback на InMemory defaults + warning log.
- **Риск (backward compat):** Добавление `permissions:` в chain YAML не должно ломать цепочки без этого блока. Митигация: nullable field, default = no restrictions (apply global policy).

## 8. Sources (Источники)
- [ ] [ADR-010: Security Policy Architecture](../../docs/adr/010-security-policy-architecture.md) (создаётся в Task 1)
- [ ] [Codex .rules file format](https://github.com/openai/codex) (inspiration)
- [ ] [Конвенция: External Service](../../docs/conventions/core_patterns/external-service.md)
- [ ] [Существующий YamlChainLoader](../../src/Module/Orchestrator/Infrastructure/Service/Chain/YamlChainLoader.php)

## 9. Comments (Комментарии)
- Exec policy file — аналог Codex `.rules`. Формат YAML выбран для консистентности с chain configuration.
- `permissions:` block в chain YAML — Should Have, т.к. можно жить с global policy. Но его добавление даёт per-chain granularity, что подтверждено Claude Code и Crush.
- `YamlExecRuleRepository` может быть позже заменён на `DbExecRuleRepository` для org-level policies (Q4+).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-02 | system_analyst (Шерлок) | Создание задачи |
