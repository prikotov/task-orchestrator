---
type: chore
created: 2026-09-17 06:50:53 (1789627853)
due: 
started: 2026-09-17 06:51:24 (1789627884)
completed: 2026-09-17 06:52:20 (1789627940)
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
branch: task/release-v0-8-1
pr: https://github.com/prikotov/task-orchestrator/pull/397
status: done
---

# TASK-release-v0-8-1-preparation: Подготовка релиза v0.8.1

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- После мержа #396 нужен patch-релиз v0.8.1, чтобы host-проекты получили плейсхолдер-форму SKILL.md.

### Варианты или путь решения (Solution Sketch)
- По RELEASE-POLICY: CHANGELOG + docs/releases/v0.8.1/release-plan.md + PR + тег + Release Phar.

### Ожидаемый результат (Expected Result)
- Тег v0.8.1 и GitHub Release с PHAR; CHANGELOG/release-plan в main.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> (заполнить)

### Цель по SMART (Goal)
- (заполнить)

## 2. Контекст и Границы (Context and Scope)
- Состав: #396 (docs-only). Версия patch.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] CHANGELOG [0.8.1], release-plan v0.8.1.
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

## 7. Риски и зависимости (Risks и Dependencies)
- Нет: docs-only; PHAR-канал secondary/best-effort.

## 8. Источники (Sources)

## 9. Комментарии (Comments)

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-17 06:50:53 (1789627853) | Тимлид Алекс (pi) | Создание задачи |
