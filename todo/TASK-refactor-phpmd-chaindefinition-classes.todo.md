---
type: refactor
created: 2026-05-21
value: V2
complexity: C3
priority: P2
depends_on:
epic: EPIC-refactor-phpmd-baseline-elimination
author: Тимлид (Алекс)
assignee: Бэкендер (Левша)
branch: refactor/phpmd-baseline-elimination
pr: (единый эпик-PR в конце)
status: in_progress
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
Как разработчик, я хочу чтобы `ChainDefinitionVo` (**545 строк**) и `YamlChainLoaderService` (**563 строки** + `parseSteps` **93 строки**) соответствовали порогам PHPMD, чтобы код был поддерживаемым.

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

**Реальные замеры (Тимлид, стабильный набор phpmd 3/3):** `ChainDefinitionVo` = 545 LOC, `YamlChainLoaderService` = 563 LOC, `parseSteps` = 93 LOC.

## 5. Definition of Done
- [ ] `phpmd` не ругается на оба файла
- [ ] `make check` зелёный
- [ ] 3 записи убраны из baseline

## 6. Verification
```bash
make phpmd
make check
```

**⚠️ Критично — флакучесть PHPMD:** единичный прогон phpmd на этом репозитории **недосчитывает** нарушения (подтверждено Тимлидом: единичные прогоны показывают 0 или 3 вместо реальных 6). Поэтому:
- Перед удалением baseline-записей — прогони `make phpmd` **минимум 3 раза** с паузами и убедись, что набор стабилен.
- Очистка кэша PDepend (`rm -rf ~/.cache/pdepend`) НЕ помогает и НЕ нужна — флакучесть не от кэша.
- Окончательный критерий: `make check` зелёный + `make phpmd` зелёный **3 раза подряд** после удаления baseline-записей.

## 7. Risks and Dependencies
- `ChainDefinitionVo` —大型 VO, экстракция требует осторожности (геттеры, factories)
- `YamlChainLoaderService::parseSteps()` — парсинг YAML DSL, сложная логика ветвлений

## 8. Sources
- `src/Module/ChainDefinition/Domain/ValueObject/ChainDefinitionVo.php`
- `src/Module/ChainDefinition/Infrastructure/Service/Chain/YamlChainLoaderService.php`
- `tests/Unit/Infrastructure/Service/Chain/YamlChainLoaderTest.php`

## Инструкции для сабагента

**Режим работы:** эпик-ветка `refactor/phpmd-baseline-elimination` напрямую (без подветки/PR) — единый эпик-PR в конце.

**Порядок:**
1. Активна ветка `refactor/phpmd-baseline-elimination`.
2. Рефакторинг (чистая экстракция, БЕЗ изменения DSL-формата и контракта `ChainLoaderInterface`):
   - `ChainDefinitionVo` (545 → ≤499 LOC): вынеси группы геттеров/factories/normalization в приватные методы или helper-классы (например, `ChainDefinitionNormalizer`). VO-семантика неизменна.
   - `YamlChainLoaderService` (563 → ≤499 LOC): вынеси парсинг-хелперы в отдельный класс (например, `ChainStepParser`) или приватные методы.
   - `parseSteps()` (93 → ≤79 LOC): экстракция приватных методов для ветвей DSL-парсинга.
3. Удали 3 записи из `phpmd.baseline.xml` (`ChainDefinitionVo` LongClass, `YamlChainLoaderService` LongClass, `parseSteps` LongMethod).
4. **Верификация (флакучесть!):** `make check` зелёный + `make phpmd` зелёный **3 раза подряд**.
5. Коммить (Conventional Commits, scope `ChainDefinition`). `git push`.

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-21 | Тимлид (Алекс) | Создание задачи |
| 2026-06-14 | Тимлид (Алекс) | Reverse Briefing: статус → in_progress, реальные замеры (545/563/93), предупреждение о флакучести phpmd, работа в эпик-ветке |
