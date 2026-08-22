---
type: chore
created: 2026-08-22 15:00:57 (1787410857)
due: 
started: 2026-08-22 15:00:57 (1787410857)
completed: 
cancelled: 
value: V3
complexity: C1
priority: P1
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Алекс (pi)
assignee: Тимлид Алекс (pi)
branch: task/release-v0-4-1
pr: 
status: in_progress
---

# TASK-release-v0-4-1-preparation: Подготовить и выпустить релиз v0.4.1 из main

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- PR #367 (docs-синхронизация порядка «done до запроса апрува» + обновление зависимостей `prikotov/*`) есть только в `main`: последний опубликованный tag `v0.4.0` его не содержит, потребители не получают обновлённые регламенты и свежие пакеты (git-workflow 0.3.2, todo-md 0.0.12).

### Варианты или путь решения (Solution Sketch)
- Подготовить patch-релиз `v0.4.1` в ветке от `main` по политике выпуска из `main`: changelog, release plan, задача; после merge опубликовать tag и GitHub Release с PHAR.

### Ожидаемый результат (Expected Result)
- Потребители получают `v0.4.1`: согласованный порядок «done до запроса апрува» в регламентах, обновлённые зависимости `prikotov/*`.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **Job Story:** Когда синхронизация порядка «done до запроса апрува» слита и проверена, я хочу выпустить её patch-релизом, чтобы host-проекты получили согласованные регламенты и свежие зависимости.

### Цель по SMART (Goal)
Подготовить, проверить и слить PR выпуска `v0.4.1` в `main`; после approval (одобрения) опубликовать tag и GitHub Release с PHAR.

## 2. Контекст и Границы (Context and Scope)
* **Где делаем:** `CHANGELOG.md`, `docs/releases/v0.4.1/release-plan.md`, этот файл задачи.
* **Текущее поведение:** последний tag `v0.4.0`; в `main` после него PR #367 (docs: синхронизация порядка done + deps).
* **Границы (Out of Scope):** не добавляем новый код и не меняем зависимости в PR подготовки; правки нормативов сверх #367 не входят.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] CHANGELOG.md: раздел 0.4.1 с подразделами Changed/Updated, ссылка на #367.
- [ ] docs/releases/v0.4.1/release-plan.md по шаблону v0.4.0.
- [ ] Задача релиза оформлена, статус и ветка заполнены.
- [ ] `make check` зелёный; `todo-md validate` задачи релиза без ошибок.
- [ ] Коммит `chore(release): prepare v0.4.1 from main`.

### ⚫ Won't Have (Не будем делать)
- Изменения кода и зависимостей сверх уже слитого #367.

## 4. План реализации (Implementation Plan)
1. [x] CHANGELOG.md: раздел 0.4.1 (Changed: docs-синхронизация #367; Updated: git-workflow 0.3.2, todo-md 0.0.12).
2. [x] docs/releases/v0.4.1/release-plan.md по образцу v0.4.0.
3. [ ] Заполнены секции задачи релиза.
4. [ ] Проверки: make check, todo-md validate.
5. [ ] Коммит prepare + PR, merge, tag, GitHub Release.

## 5. Критерии приёмки (Definition of Done)
- [ ] Раздел CHANGELOG 0.4.1 корректен и соответствует составу #367.
- [ ] Release-plan v0.4.1 заполнен (метаданные, состав, риски, порядок deploy, проверки).
- [ ] `make check` зелёный.
- [ ] PR подготовки релиза слит в `main`; tag `v0.4.1` опубликован; GitHub Release с `task-orchestrator.phar`.

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate todo/TASK-release-v0-4-1-preparation.todo.md
make check
```

## 7. Риски и зависимости (Risks and Dependencies)
- (заполнить)

## 8. Источники (Sources)

## 9. Комментарии (Comments)

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-22 15:00:57 (1787410857) | Тимлид Алекс (pi) | Создание задачи |
