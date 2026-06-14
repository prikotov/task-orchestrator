---
type: refactor
created: 2026-06-13
value: V2
complexity: C1
priority: P2
depends_on:
epic: EPIC-refactor-phpmd-baseline-elimination
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: todo
---

# TASK-fix-phpmd-auditloggers: Убрать ErrorControlOperator (@mkdir) в двух JsonlAuditLogger

## 0. Простое описание (Human Brief)
Устранить PHPMD violation (technical debt), чтобы код соответствовал порогам проекта.

### Проблема простыми словами (Problem)
В двух audit-логгерах используется `@mkdir(...)` — подавление ошибок оператором `@`, что нарушает правило `CleanCode/ErrorControlOperator`. Suppression маскирует реальную проблему (игнорирование отказа mkdir).

### Варианты или путь решения (Solution Sketch)
Заменить `@mkdir($dir, 0777, true)` на явный guard: проверку `is_dir($dir)` (создаём каталог только если его нет). При невозможности создать каталог — fail-fast (бросать исключение), без скрытого подавления.

### Ожидаемый результат (Expected Result)
Две записи `ErrorControlOperator` убраны из `phpmd.baseline.xml`, `make phpmd-full` = 0 violations для этих файлов.

## 1. Concept and Goal (Концепция и Цель)
### Story
Как разработчик, я хочу, чтобы создание каталога для audit-логов не подавляло ошибки через `@`, а обрабатывало их явно, чтобы нарушения PHPMD `ErrorControlOperator` были устранены, а отказ создания каталога не маскировался.

### Goal
- Заменить `@mkdir` на явную проверку `is_dir()` + создание с fail-fast в обоих логгерах.
- Удалить 2 записи `ErrorControlOperator` из `phpmd.baseline.xml`.
- Поведение: каталог для логов создаётся рекурсивно; при отказе — явное исключение вместо скрытого игнорирования.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    - `src/Module/ChainExecution/Infrastructure/Service/Audit/JsonlAuditLoggerService.php` (метод `append`, `@mkdir` в строке ~118)
    - `src/Module/DynamicLoop/Infrastructure/Service/JsonlAuditLogger.php` (метод `append`, `@mkdir` в строке ~122)
*   **Текущее поведение:** `@mkdir($dir, 0777, true);` перед записью JSONL-строки.
*   **Границы (Out of Scope):** не меняем формат JSONL, имя файла, контракт `AuditLoggerInterface`. Не трогаем `bridge.php` (отдельное решение).

## 3. Requirements (MoSCoW)
### 🔴 Must Have
- [ ] `@mkdir` заменён на явную проверку `is_dir()` + `mkdir()` с fail-fast в обоих логгерах
- [ ] Все существующие тесты проходят (включая тесты audit-логгеров)
- [ ] Удалить 2 записи `ErrorControlOperator` из `phpmd.baseline.xml`

### ⚫ Won't Have (Не будем делать)
- Изменение контракта логгеров или формата JSONL
- Изменение порогов в phpmd.xml
- Правки `bridge.php`

## 4. Implementation Plan
*Заполняется исполнителем.*

## 5. Definition of Done
- [ ] `phpmd` не ругается на `ErrorControlOperator` в обоих логгерах
- [ ] `make check` зелёный
- [ ] 2 записи убраны из baseline

## 6. Verification
```bash
make phpmd
make check
```

## 7. Risks and Dependencies
- Поведение при отказе mkdir меняется: было «молча проигнорировать», станет «fail-fast». Проверить, что тесты покрывают нормальный путь (каталог создаётся) и нет скрытой зависимости на молчаливое игнорирование.

## 8. Sources
- `src/Module/ChainExecution/Infrastructure/Service/Audit/JsonlAuditLoggerService.php`
- `src/Module/DynamicLoop/Infrastructure/Service/JsonlAuditLogger.php`
- `phpmd.baseline.xml` (записи `ErrorControlOperator` для этих файлов)

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-13 | Тимлид (Алекс) | Создание задачи (разведка эпика выявила 5 непокрытых baseline-записей; эта задача покрывает 2 из них — `@mkdir` в audit-логгерах) |
