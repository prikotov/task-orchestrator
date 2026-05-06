---
type: refactor
created: 2026-05-06
value: V2
complexity: C3
priority: P1
depends_on: TASK-refactor-crossmodule-deptrac-rule
epic: EPIC-refactor-responsibility-decomposition
author: Тимлид Алекс
assignee: Бэкендер Левша
branch: task/refactor-integration-layer-violations
pr:
status: in_progress
---

# TASK-refactor-integration-layer-violations: Перенос классов в Integration-слой для устранения 10 Deptrac-violations

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда Deptrac обнаруживает 10 violations, вызванных классами в неправильных слоях (Application вместо Integration, Presentation вместо общего слоя), я хочу перенести эти классы в корректные слои и упразднить лишние интерфейсы, чтобы Deptrac → 0 violations.

### Goal (Цель по SMART)
Устранить 10 из 15 Deptrac-violations через рефакторинг: перенос 5 классов в Integration-слой, упразднение 1 интерфейса, обновление DI-конфигурации. После выполнения + TASK-refactor-crossmodule-deptrac-rule → 0 violations.

## 2. Context and Scope (Контекст и Границы)

### Где делаем

**Переносятся в Integration:**
- `ChainExecution\Application\UseCase\Query\GetRunners\GetRunnersQueryHandler` → `ChainExecution\Integration\Service\AgentRunner\`
- `DynamicLoop\Application\Service\DispatchRoundEventService` → `DynamicLoop\Integration\Service\ChainExecution\`
- `DynamicLoop\Application\Service\DispatchSessionCompletedEventService` → `DynamicLoop\Integration\Service\ChainExecution\`
- `DynamicLoop\Application\Service\DynamicExecutionStrategy` → `DynamicLoop\Integration\Service\ChainExecution\`
- `DynamicLoop\Infrastructure\Service\RunDynamicLoopAgentService` → `DynamicLoop\Integration\Service\ChainExecution\`

**Упраздняется:**
- `ChainExecution\Application\Service\ResolveExitCodeServiceInterface` + реализация — логика встроена в `OrchestrateChainResultDto` / `OrchestrateCommand`

**Обновляется:**
- `config/services.yaml` — автосвязывание после переноса классов

### Текущее поведение
Deptrac → 15 violations. Из них 10 — классы в неправильных слоях:
- Application-классы с cross-module зависимостями (должны быть в Integration)
- Infrastructure-класс с cross-module DI-зависимостями (должен быть в Integration)
- Presentation с прямой зависимостью на модульный сервис-контракт

### Границы (Out of Scope)
- ❌ Не меняем Deptrac-правила (отдельная задача TASK-refactor-crossmodule-deptrac-rule)
- ❌ Не меняем Domain-слой
- ❌ Не трогаем violations #4, #5, #12 (решаются через правила)
- ❌ Не создаём новых интерфейсов (кроме возможных Integration-портов)

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] `GetRunnersQueryHandler` перенесён из `ChainExecution\Application` в `ChainExecution\Integration`
- [ ] `DispatchRoundEventService` перенесён из `DynamicLoop\Application` в `DynamicLoop\Integration`
- [ ] `DispatchSessionCompletedEventService` перенесён из `DynamicLoop\Application` в `DynamicLoop\Integration`
- [ ] `DynamicExecutionStrategy` перенесён из `DynamicLoop\Application` в `DynamicLoop\Integration`
- [ ] `RunDynamicLoopAgentService` перенесён из `DynamicLoop\Infrastructure` в `DynamicLoop\Integration`
- [ ] `ResolveExitCodeServiceInterface` упразднён, логика встроена в результат/Presentation
- [ ] `config/services.yaml` обновлён после всех переносов
- [ ] Все существующие тесты проходят
- [ ] Deptrac: violations #1, #2, #3, #6, #7, #8, #9, #10, #11, #13, #14 устранены

### 🟡 Should Have (Желательно)
- [ ] Тесты обновлены для новых namespace

### ⚫ Won't Have (Не будем делать)
- Не переписываем бизнес-логику переносимых классов
- Не создаём Integration-порты для портированных Dispatch-сервисов (они уже используют Domain-порты)

## 4. Implementation Plan (План реализации)

1. [ ] Создать ветку `task/refactor-integration-layer-violations` от `main`
2. [ ] Упразднить `ResolveExitCodeServiceInterface` + реализацию (#1)
   - Встроить exit code resolution в `OrchestrateChainResultDto` или `OrchestrateCommand`
   - Обновить `OrchestrateCommand` — убрать инжект `ResolveExitCodeServiceInterface`
3. [ ] Перенести `GetRunnersQueryHandler` (#2, #3)
   - `ChainExecution\Application\UseCase\Query\GetRunners\` → `ChainExecution\Integration\Service\AgentRunner\`
   - Обновить namespace, imports, services.yaml
4. [ ] Перенести `DynamicExecutionStrategy` (#8–#11)
   - `DynamicLoop\Application\Service\` → `DynamicLoop\Integration\Service\ChainExecution\`
   - Обновить namespace, imports, services.yaml
5. [ ] Перенести `Dispatch*EventService` (#6, #7)
   - `DynamicLoop\Application\Service\` → `DynamicLoop\Integration\Service\ChainExecution\`
   - Обновить namespace, imports, services.yaml
6. [ ] Перенести `RunDynamicLoopAgentService` (#13, #14)
   - `DynamicLoop\Infrastructure\Service\` → `DynamicLoop\Integration\Service\ChainExecution\`
   - Обновить namespace, imports, services.yaml
7. [ ] Обновить все тесты (namespace, imports)
8. [ ] Запустить проверки: `vendor/bin/phpunit`, `vendor/bin/psalm`, `vendor/bin/deptrac`

## 5. Definition of Done (Критерии приёмки)
- [ ] Deptrac: 10 violations устранены (остаются только #4, #5, #12 — для правила)
- [ ] `vendor/bin/phpunit` → OK
- [ ] `vendor/bin/psalm` → OK
- [ ] `config/services.yaml` корректен

## 6. Verification (Самопроверка)
```bash
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Зависимость:** TASK-refactor-crossmodule-deptrac-rule (PR в coding-standard). До его мержа violations #4, #5, #12 останутся
- **Риск:** Перенос классов может сломать автосвязывание Symfony — тщательно обновить services.yaml
- **Риск:** Тесты могут ссылаться на старые namespace — обновить все импорты

## 8. Sources (Источники)
- [Отчёт Архитектора Гэндальфа](../docs/agents/reports/system-architect/2026-05-06_10-00_deptrac-violations-analysis.md)
- [Критический анализ Архитектора Локи](../docs/agents/reports/system-architect/2026-05-06_12-00_critical-review-deptrac-violations.md)
- [`docs/conventions/layers/layers.md`](../docs/conventions/layers/layers.md) — Integration-слой: разрешённые зависимости

## 9. Comments (Комментарии)
### Классификация violations

| # | Violation | Решение |
|---|-----------|---------|
| #1 | `OrchestrateCommand` → `ResolveExitCodeServiceInterface` | Упразднить интерфейс, встроить логику |
| #2, #3 | `GetRunnersQueryHandler` → AgentRunner.Application | Перенести в Integration |
| #6, #7 | `Dispatch*EventService` → ChainExecution.Application events | Перенести в Integration |
| #8–#11 | `DynamicExecutionStrategy` → ChainExecution.Application | Перенести в Integration |
| #13, #14 | `RunDynamicLoopAgentService` → ChainExecution.Domain.Contract (DI) | Перенести в Integration |

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-06 | Тимлид Алекс | Создание задачи |
