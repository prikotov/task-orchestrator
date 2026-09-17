---
type: chore
created: 2026-09-17 13:16:24 (1789650984)
due: 
started: 2026-09-17 13:16:53 (1789651013)
completed: 
cancelled: 
value: V2
complexity: C2
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Алекс (pi)
assignee: Тимлид Алекс (pi)
branch: task/release-v0-8-2
pr: 
status: in_progress
---

# TASK-release-v0-8-2-preparation: Подготовка релиза v0.8.2

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- После мержа #398 нужен patch-релиз v0.8.2 для доставки плейсхолдер-формы run-subagent.

### Варианты или путь решения (Solution Sketch)
- По RELEASE-POLICY: CHANGELOG + release-plan + PR + тег + Release Phar.

### Ожидаемый результат (Expected Result)
- Тег v0.8.2 и GitHub Release с PHAR.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> (заполнить)

### Цель по SMART (Goal)
- (заполнить)

## 2. Контекст и Границы (Context and Scope)
- Состав: #398 (docs-only). Версия patch.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] CHANGELOG [0.8.2], release-plan v0.8.2.
- [ ] PR слит, тег отправлен, Release с PHAR опубликован.
### ⚫ Won't Have (Не будем делать)
- Изменения кода (docs-only релиз).

## 4. План реализации (Implementation Plan)
1. [x] CHANGELOG + release-plan.
2. [ ] PR → merge → тег → Release Phar → проверка.

## 5. Критерии приёмки (Definition of Done)
- [ ] Проверки после публикации выполнены.

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate
```

## 7. Риски и зависимости (Risks and Dependencies)
- (заполнить)

## 8. Источники (Sources)

## 9. Комментарии (Comments)

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-17 13:16:24 (1789650984) | Тимлид Алекс (pi) | Создание задачи |
