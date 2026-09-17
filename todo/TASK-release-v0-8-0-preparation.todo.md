---
type: chore
created: 2026-09-17 01:33:30 (1789608810)
due: 
started: 2026-09-17 01:34:05 (1789608845)
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
branch: task/release-v0-8-0
pr: 
status: in_progress
---

# TASK-release-v0-8-0-preparation: Подготовка релиза v0.8.0

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- После мержа #393/#394 нужен выпуск v0.8.0: CHANGELOG, release-plan, тег, GitHub Release с PHAR.

### Варианты или путь решения (Solution Sketch)
- По docs/releases/RELEASE-POLICY.md: ветка task/release-v0-8-0, CHANGELOG + docs/releases/v0.8.0/release-plan.md, PR в main, тег на вершине main, workflow Release Phar публикует PHAR.

### Ожидаемый результат (Expected Result)
- Опубликован тег v0.8.0 и GitHub Release с task-orchestrator.phar; CHANGELOG и release-plan в main.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> (заполнить)

### Цель по SMART (Goal)
- (заполнить)

## 2. Контекст и Границы (Context and Scope)
- Версия minor: расширение CLI agent:role-skills (<role|file>, --logical-pwd).
- Состав: #393 (фикс+рефакторинг become-role), #394 (ретро, служебное).

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] CHANGELOG.md с блоком [0.8.0].
- [x] docs/releases/v0.8.0/release-plan.md по шаблону v0.7.0.
- [ ] PR слит, тег v0.8.0 отправлен, GitHub Release с PHAR опубликован.
### ⚫ Won't Have (Не будем делать)
- Изменения кода (релизная подготовка docs-only).

## 4. План реализации (Implementation Plan)
1. [x] CHANGELOG + release-plan.
2. [ ] PR → merge → тег → workflow Release Phar → проверка публикации.

## 5. Критерии приёмки (Definition of Done)
- [ ] Проверки после публикации из release-plan выполнены.

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate
```

## 7. Риски и зависимости (Risks and Dependencies)
- PHAR-канал secondary/best-effort: сбой Release Phar не блокирует Composer-канал.

## 8. Источники (Sources)

## 9. Комментарии (Comments)

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-17 01:33:30 (1789608810) | Тимлид Алекс (pi) | Создание задачи |
