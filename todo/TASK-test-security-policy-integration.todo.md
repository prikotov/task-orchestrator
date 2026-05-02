---
type: test
created: 2026-05-02
value: V3
complexity: C2
priority: P1
depends_on: TASK-feat-security-policy-infrastructure, TASK-feat-security-policy-yaml-dsl
epic: EPIC-sprint-9-security-policy
author: system_analyst (Шерлок)
assignee: backend_developer (Левша)
branch: task/test-security-policy-integration
pr: '130'
status: done
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
- [x] Integration test: exec policy violation — banned command (`bash -c ...`) → `ExecPolicyViolationException`
- [x] Integration test: runner not allowed — chain с `allowed_runners: [pi]`, runner = `codex` → `ExecPolicyViolationException`
- [x] Integration test: chain execution blocked — chain-level deny → `SecurityPolicyViolationException`
- [x] Integration test: successful execution — все checks pass → no exception
- [x] Integration test: fallback to default rules — exec policy file missing → InMemory defaults applied
- [x] Тесты используют реальные YAML-файлы (fixtures в `tests/Integration/_fixtures/`)
- [x] `vendor/bin/phpunit` — зелёный

### 🟡 Should Have (Желательно)
- [x] Integration test: YAML `permissions:` block — chain-specific permissions override global policy
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
1. [x] Создать каталог `tests/Integration/Module/SecurityPolicy/`
2. [x] Создать YAML fixtures: `tests/Integration/_fixtures/test_security_policy.yaml` (test rules)
3. [x] Создать YAML chain fixtures с `permissions:` block
4. [x] Написать integration test: exec policy violation (banned command)
5. [x] Написать integration test: runner not allowed
6. [x] Написать integration test: chain-level deny
7. [x] Написать integration test: successful execution
8. [x] Написать integration test: fallback to defaults
9. [x] (Should Have) Написать integration test: chain-specific permissions
10. [x] Проверить: `vendor/bin/phpunit tests/Integration/`

## 5. Definition of Done (Критерии приёмки)
- [x] Все Must Have integration tests проходят
- [x] Тесты используют реальные YAML fixtures (не mocks для YAML parsing)
- [x] Каждый тест проверяет конкретный violation type с конкретным exception message
- [x] Tests изолированы — не влияют друг на друга
- [x] `vendor/bin/phpunit` — зелёный

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
