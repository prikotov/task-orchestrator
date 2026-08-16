---
type: refactor
created: 2026-05-06
value: V2
complexity: C2
priority: P1
depends_on:
epic: EPIC-refactor-responsibility-decomposition
author: Тимлид Алекс
assignee:
branch:
pr:
status: todo
---

# TASK-refactor-crossmodule-deptrac-rule: Точечные исключения в CrossModuleDomainRule

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда Integration-мапперы ACL обращаются к чужим `Domain\Contract\`-интерфейсам, а Infrastructure-адаптеры реализуют чужие `Domain\Contract\`-порты, я хочу добавить 2 точечных исключения в `CrossModuleDomainRule`, чтобы Deptrac не flagged легальные архитектурные паттерны как violations.

### Goal (Цель по SMART)
Добавить 2 исключения с guards в `CrossModuleDomainRule` (пакет `prikotov/coding-standard`), которые закроют 5 из 15 текущих Deptrac-violations (#4, #5, #12). Исключения — минимальные, с защитой от злоупотреблений (`interface_exists()` guard, `DependencyType::INHERIT` guard).

## 2. Context and Scope (Контекст и Границы)

### Где делаем
**Изменяется:** `vendor/prikotov/coding-standard/src/Deptrac/CrossModuleDomainRule.php` (через PR в пакет `prikotov/coding-standard`)

### Текущее поведение
`CrossModuleDomainRule` разрешает только `Integration → foreign Application`. Любое обращение к чужому Domain (даже к `Domain\Contract\`-интерфейсам) flagged как violation.

15 violations → 5 из них являются **легальными архитектурными паттернами**:

| # | Violation | Паттерн |
|---|-----------|---------|
| #4 | `ChainExecution\Integration → ChainDefinition\Domain\Contract\Chain\ChainLoaderInterface` | ACL-маппер загружает данные через чужой контракт |
| #5 | `DynamicLoop\Integration → ChainDefinition\Domain\Contract\Chain\ChainLoaderInterface` | ACL-маппер загружает данные через чужой контракт |
| #12 | `DynamicLoop\Infrastructure → ChainExecution\Domain\Contract\Chain\Audit\AuditLoggerInterface` | Port/Adapter: Infrastructure реализует чужой Domain-порт |

### Границы (Out of Scope)
- ❌ Не меняем `ServiceContractDependencyRule`
- ❌ Не меняем базовые слои в `depfile.yaml`
- ❌ Не трогаем violations #13, #14 (решаются рефакторингом в отдельной задаче)

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] В `CrossModuleDomainRule` добавлено исключение: `Integration → foreign Domain\Contract\` (только интерфейсы)
  - Guard: `interface_exists()` — нельзя обойти, положив класс в `Contract\`
- [ ] В `CrossModuleDomainRule` добавлено исключение: `Infrastructure → foreign Domain\Contract\` (только implements)
  - Guard: `DependencyType::INHERIT` — разрешено только `implements`, не constructor injection
  - Guard: `interface_exists()` — только интерфейсы
- [ ] Violations #4, #5, #12 устранены (Deptrac → 0 для этих случаев)
- [ ] Исключение `Domain\Service\Integration\` **НЕ добавлено** (нет violations, создаёт лазейку)

### 🟡 Should Have (Желательно)
- [ ] Unit-тесты на новые исключения в `prikotov/coding-standard`

### ⚫ Won't Have (Не будем делать)
- Не добавляем широкие исключения для `Domain\` целиком
- Не добавляем исключение `Domain\Service\Integration\` (phantom-fix)
- Не трогаем существующее `@todo`-исключение для Command → Repository/Entity (отдельная задача)

## 4. Implementation Plan (План реализации)

1. [ ] Создать ветку в `prikotov/coding-standard` для PR
2. [ ] Добавить 2 исключения в `CrossModuleDomainRule::handleEvent()`:
   ```php
   // Исключение 1: Integration → foreign Domain\Contract (interface only)
   if (
       $depender['layer'] === 'Integration' && $dependent['layer'] === 'Domain'
       && str_starts_with($dependent['path'], 'Contract\\')
       && interface_exists($event->dependentReference->getToken()->toString())
   ) {
       return;
   }

   // Исключение 2: Infrastructure → foreign Domain\Contract (implements only)
   if (
       $depender['layer'] === 'Infrastructure' && $dependent['layer'] === 'Domain'
       && str_starts_with($dependent['path'], 'Contract\\')
       && $event->dependency->getContext()->dependencyType === DependencyType::INHERIT
       && interface_exists($event->dependentReference->getToken()->toString())
   ) {
       return;
   }
   ```
3. [ ] Добавить unit-тесты на оба исключения (положительные и отрицательные кейсы)
4. [ ] Запустить Deptrac в task-orchestrator: violations #4, #5, #12 должны исчезнуть
5. [ ] Создать PR в `prikotov/coding-standard`

## 5. Definition of Done (Критерии приёмки)
- [ ] PR в `prikotov/coding-standard` создан и одобрен
- [ ] `vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress` → violations #4, #5, #12 устранены
- [ ] Исключения защищены guards (`interface_exists()`, `DependencyType::INHERIT`)

## 6. Verification (Самопроверка)
```bash
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск:** PR в coding-standard может требовать review владельцем пакета
- **Митигация:** до мержа coding-standard — временно добавить violations в `skip_violations` в проектном `depfile.yaml`

## 8. Sources (Источники)
- [Отчёт Архитектора Гэндальфа](../docs/agents/reports/system-architect/2026-05-06_10-00_deptrac-violations-analysis.md)
- [Критический анализ Архитектора Локи](../docs/agents/reports/system-architect/2026-05-06_12-00_critical-review-deptrac-violations.md)
- [`CrossModuleDomainRule.php`](../vendor/prikotov/coding-standard/src/Deptrac/CrossModuleDomainRule.php)
- [`docs/conventions/layers/layers.md`](../../docs/conventions/layers/layers.md) — конвенции Integration-слоя

## 9. Comments (Комментарии)
Решение принято по результатам консультации двух архитекторов (Гэндальф + Локи). Локи обоснованно раскритиковал первоначальное предложение Гэндальфа добавить `Domain\Service\Integration\` — ни одно из 15 violations не попадает под это исключение. Финальное решение — консенсус обоих архитекторов.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-06 | Тимлид Алекс | Создание задачи |
