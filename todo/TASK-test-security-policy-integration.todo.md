---
type: test
created: 2026-05-02
value: V3
complexity: C2
priority: P1
depends_on: TASK-feat-security-policy-infrastructure, TASK-feat-security-policy-yaml-dsl
epic: EPIC-sprint-9-security-policy
author: system_analyst (Шерлок)
assignee:
branch:
pr:
status: todo
---

# TASK-test-security-policy-integration: Integration тесты Security Policy end-to-end

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда SecurityPolicy модуль реализован (Domain, Infrastructure, Decorators, YAML), я хочу создать integration-тесты, которые проверяют полный цикл: YAML loading → exec rule parsing → decorator checks → violation exceptions — с реальными конфигурационными файлами, чтобы убедиться, что все слои работают корректно в связке.

### Goal (Цель по SMART)
Создать integration-тесты в `tests/Integration/Module/SecurityPolicy/` для проверки: (1) exec policy violation при banned command, (2) permission deny при неразрешённом runner, (3) chain execution block при chain-level deny, (4) successful execution когда все checks pass, (5) YAML `permissions:` block влияет на security checks. Срок: 0.5 дня.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `tests/Integration/Module/SecurityPolicy/`
*   **Текущее поведение:** Unit-тесты покрывают Domain и Infrastructure классы изолированно, но сквозное взаимодействие не проверено
*   **Границы (Out of Scope):**
    *   НЕ создавать новые production-классы
    *   НЕ менять Domain/Infrastructure код
    *   НЕ тестировать реальные AI-agent runners (использовать stubs)
    *   НЕ тестировать Symfony DI container wiring (отдельная задача при необходимости)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Integration test: exec policy violation — banned command (`bash -c ...`) → `ExecPolicyViolationException`
- [ ] Integration test: runner not allowed — chain с `allowed_runners: [pi]`, runner = `codex` → `ExecPolicyViolationException`
- [ ] Integration test: chain execution blocked — chain-level deny → `SecurityPolicyViolationException`
- [ ] Integration test: successful execution — все checks pass → no exception
- [ ] Integration test: fallback to default rules — exec policy file missing → InMemory defaults applied
- [ ] Тесты используют реальные YAML-файлы (fixtures в `tests/Fixtures/`)
- [ ] `vendor/bin/phpunit` — зелёный

### 🟡 Should Have (Желательно)
- [ ] Integration test: YAML `permissions:` block — chain-specific permissions override global policy
- [ ] Integration test: decorator ordering — SecurityPolicy → Retry → CircuitBreaker (verify SecurityPolicy is outermost)
- [ ] Integration test: `RuleSeverity::warn` → logged but not blocked

### 🟢 Could Have (Опционально)
- [ ] Performance test: security check overhead < 5ms per call

### ⚫ Won't Have (Не будем делать)
- [ ] E2E тесты с реальными agent runners
- [ ] Load testing
- [ ] Security penetration testing
- [ ] Symfony kernel testing

## 4. Implementation Plan (План реализации)
1. [ ] Создать каталог `tests/Integration/Module/SecurityPolicy/`
2. [ ] Создать YAML fixtures: `tests/Fixtures/security_policy.yaml` (test rules)
3. [ ] Создать YAML chain fixtures с `permissions:` block
4. [ ] Написать integration test: exec policy violation (banned command)
5. [ ] Написать integration test: runner not allowed
6. [ ] Написать integration test: chain-level deny
7. [ ] Написать integration test: successful execution
8. [ ] Написать integration test: fallback to defaults
9. [ ] (Should Have) Написать integration test: chain-specific permissions
10. [ ] Проверить: `vendor/bin/phpunit tests/Integration/`

## 5. Definition of Done (Критерии приёмки)
- [ ] Все Must Have integration tests проходят
- [ ] Тесты используют реальные YAML fixtures (не mocks для YAML parsing)
- [ ] Каждый тест проверяет конкретный violation type с конкретным exception message
- [ ] Tests изолированы — не влияют друг на друга
- [ ] `vendor/bin/phpunit` — зелёный

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Integration/Module/SecurityPolicy/
vendor/bin/phpunit
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Зависимость:** Task 4 (Infrastructure) — decorators и DI wiring
- **Зависимость:** Task 5 (YAML DSL) — exec policy file, `YamlExecRuleRepository`
- **Риск:** Integration tests могут вскрыть проблемы в DI wiring или Deptrac rules. Это ожидаемо — цель integration tests именно в этом.

## 8. Sources (Источники)
- [ ] [ADR-010: Security Policy Architecture](../../docs/adr/010-security-policy-architecture.md)
- [ ] [Security Policy Cross-Cutting Analysis](../../docs/releases/security-policy-cross-cutting-analysis.md)
- [ ] [Конвенция: Testing](../../docs/conventions/testing/index.md)
- [ ] [Существующие integration tests](../../tests/Integration/)

## 9. Comments (Комментарии)
- Integration tests — **финальная** задача эпика. Если все tests green → Security Policy foundation готов.
- Test fixtures хранятся в `tests/Fixtures/` — это стандарт проекта.
- При обнаружении проблем в integration tests — bug fix может потребовать возвращения к Task 4 или Task 5.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-02 | system_analyst (Шерлок) | Создание задачи |
