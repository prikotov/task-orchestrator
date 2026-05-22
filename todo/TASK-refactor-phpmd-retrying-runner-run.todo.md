---
type: refactor
created: 2026-05-21
value: V2
complexity: C2
priority: P2
depends_on:
epic: EPIC-refactor-phpmd-baseline-elimination
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: todo
---

# TASK-refactor-phpmd-retrying-runner-run: Уменьшить RetryingAgentRunnerService::run() с 112 до ≤79 строк

## 0. Простое описание (Human Brief)
Устранить PHPMD violation (technical debt), чтобы код соответствовал порогам проекта.

### Проблема простыми словами (Problem)
Метод или класс превышает порог PHPMD, suppression в baseline маскирует проблему.

### Варианты или путь решения (Solution Sketch)
Экстракция приватных методов или рефакторинг для уменьшения LOC.

### Ожидаемый результат (Expected Result)
PHPMD baseline пуст, `make phpmd-full` = 0 violations.

## 0. Простое описание (Human Brief)
Устранить PHPMD violation (technical debt), чтобы код соответствовал порогам проекта.

### Проблема простыми словами (Problem)
Метод или класс превышает порог PHPMD, suppression в baseline маскирует проблему.

### Варианты или путь решения (Solution Sketch)
Экстракция приватных методов для уменьшения LOC.

### Ожидаемый результат (Expected Result)
PHPMD baseline пуст, `make phpmd-full` = 0 violations.

## 1. Concept and Goal (Концепция и Цель)
### Story
Как разработчик, я хочу чтобы метод `run()` в `RetryingAgentRunnerService` был ≤79 строк, чтобы соответствовал порогу PHPMD ExcessiveMethodLength (80 LOC) и был проще для понимания.

### Goal
Декомпозировать `RetryingAgentRunnerService::run()` (112 LOC) до ≤79 LOC через экстракцию приватных методов, не изменяя поведение.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunnerService.php`
*   **Текущее поведение:** метод `run()` содержит 112 строк (retry loop с exponential backoff, JSONL streaming, error handling)
*   **Границы (Out of Scope):** не меняем retry-логику, не меняем контракт интерфейса

## 3. Requirements (MoSCoW)
### 🔴 Must Have
- [ ] Метод `run()` ≤79 LOC
- [ ] Все существующие тесты проходят
- [ ] Удалить запись из `phpmd.baseline.xml`

### ⚫ Won't Have (Не будем делать)
- Изменение retry-стратегии или контракта
- Изменение порогов в phpmd.xml

## 4. Implementation Plan
*Заполняется исполнителем.*

## 5. Definition of Done
- [ ] `phpmd` не ругается на `RetryingAgentRunnerService::run()`
- [ ] `make check` зелёный
- [ ] Запись убрана из baseline

## 6. Verification
```bash
make phpmd
make check
```

## 7. Risks and Dependencies
- Экстракция может потребовать передачи многих локальных переменных — рассмотреть VO для контекста retry

## 8. Sources
- `src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunnerService.php`
- `tests/Unit/Infrastructure/Service/AgentRunner/RetryingAgentRunnerTest.php`

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-21 | Тимлид (Алекс) | Создание задачи |
