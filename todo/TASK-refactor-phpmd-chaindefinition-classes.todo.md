---
type: refactor
created: 2026-05-21
value: V2
complexity: C3
priority: P2
depends_on:
epic: EPIC-refactor-phpmd-baseline-elimination
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: todo
---

# TASK-refactor-phpmd-chaindefinition-classes: Устранить LongClass и LongMethod в ChainDefinition

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
Экстракция приватных методов, вынос VO или логики в helper-классы.

### Ожидаемый результат (Expected Result)
PHPMD baseline пуст, `make phpmd-full` = 0 violations.

## 1. Concept and Goal (Концепция и Цель)
### Story
Как разработчик, я хочу чтобы `ChainDefinitionVo` (528 строк) и `YamlChainLoaderService` (553 строки + parseSteps 92 строки) соответствовали порогам PHPMD, чтобы код был поддерживаемым.

### Goal
Уменьшить `ChainDefinitionVo` до ≤500 LOC, `YamlChainLoaderService` до ≤500 LOC, `parseSteps()` до ≤79 LOC.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/ChainDefinition/Domain/ValueObject/ChainDefinitionVo.php`, `src/Module/ChainDefinition/Infrastructure/Service/Chain/YamlChainLoaderService.php`
*   **Текущее поведение:** 3 violation PHPMD (LongClass × 2, LongMethod × 1)
*   **Границы (Out of Scope):** не меняем формат DSL-конфигурации цепей, не меняем структуру VO

## 3. Requirements (MoSCoW)
### 🔴 Must Have
- [ ] `ChainDefinitionVo` ≤500 LOC
- [ ] `YamlChainLoaderService` ≤500 LOC
- [ ] `YamlChainLoaderService::parseSteps()` ≤79 LOC
- [ ] Все тесты проходят
- [ ] Удалить 3 записи из `phpmd.baseline.xml`

### ⚫ Won't Have (Не будем делать)
- Изменение DSL-формата или контракта ChainLoaderInterface
- Изменение порогов в phpmd.xml

## 4. Implementation Plan
*Заполняется исполнителем.*

## 5. Definition of Done
- [ ] `phpmd` не ругается на оба файла
- [ ] `make check` зелёный
- [ ] 3 записи убраны из baseline

## 6. Verification
```bash
make phpmd
make check
```

## 7. Risks and Dependencies
- `ChainDefinitionVo` —大型 VO, экстракция требует осторожности (геттеры, factories)
- `YamlChainLoaderService::parseSteps()` — парсинг YAML DSL, сложная логика ветвлений

## 8. Sources
- `src/Module/ChainDefinition/Domain/ValueObject/ChainDefinitionVo.php`
- `src/Module/ChainDefinition/Infrastructure/Service/Chain/YamlChainLoaderService.php`
- `tests/Unit/Infrastructure/Service/Chain/YamlChainLoaderTest.php`

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-21 | Тимлид (Алекс) | Создание задачи |
