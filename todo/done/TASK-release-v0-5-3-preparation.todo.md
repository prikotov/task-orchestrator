---
type: chore
created: 2026-09-03 05:25:00 (1788413100)
due:
started: 2026-09-03 05:51:29 (1788414689)
completed: 2026-09-03 05:51:29 (1788414689)
cancelled:
value: V2
complexity: C1
priority: P1
cost_plan:
cost_fact:
depends_on:
epic:
author: Бэкендер Тони (pi)
assignee: Бэкендер Тони (pi)
branch: task/release-v0-5-3
pr: https://github.com/prikotov/task-orchestrator/pull/379
status: done
---

# TASK-release-v0-5-3-preparation: Подготовить release PR для v0.5.3

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

Обновление dev-зависимости `prikotov/coding-standard` до `^0.32.0` (#378) слито в `main`, но последний опубликованный тег `v0.5.2` его не содержит. Инструментарий проверок (phpcs, deptrac) в опубликованных версиях отстаёт от `main`.

### Варианты или путь решения (Solution Sketch)

Подготовить patch-релиз `v0.5.3` из `main`: кратко описать изменение в CHANGELOG, оформить план релиза и создать release PR с документационными артефактами.

### Ожидаемый результат (Expected Result)

Release PR фиксирует состав `v0.5.3`, риски и шаги публикации; после слияния можно безопасно создать тег `v0.5.3` из `main`.

## 1. Концепция и Цель (Concept and Goal)

### История (Job Story)

> **Job Story:** Когда обновление зависимостей слито в `main`, я хочу подготовить release PR для patch-релиза, чтобы потребители получили обновлённый инструментарий в следующем опубликованном теге.

### Цель по SMART (Goal)

Подготовить документационные артефакты релиза (CHANGELOG, план релиза), создать PR в `main`, после зелёной CI перевести задачу в `done`. Публикация тега — отдельный операционный этап после слияния. Дата: 2026-09-03.

## 2. Контекст и Границы (Context and Scope)

* **Где делаем:** `CHANGELOG.md`, `docs/releases/v0.5.3/release-plan.md`, этот файл задачи.
* **Текущее поведение:** последний опубликованный тег — `v0.5.2`; в `main` после него слит [#378](https://github.com/prikotov/task-orchestrator/pull/378) — обновление `prikotov/coding-standard` до `^0.32.0` (только `require-dev`, код не менялся).
* **Границы (Out of Scope):** не изменять код, конфигурацию, скрипты, публичные контракты, PHAR-установку; не обновлять сторонние зависимости.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [x] `CHANGELOG.md` содержит краткую запись `v0.5.3` со ссылкой на PR #378.
- [x] `docs/releases/v0.5.3/release-plan.md` фиксирует состав, риски, отсутствие миграций, порядок публикации и проверки после публикации.
- [x] Задача подготовки создана со статусом `in_progress` и веткой `task/release-v0-5-3`.
- [x] Release PR создан от имени GitHub App с меткой AI-агента.
### 🟡 Желательно (Should Have)
- [ ] —
### 🟢 Опционально (Could Have)
- [ ] —
### ⚫ Не будем делать (Won't Have)
- [ ] Изменения кода, конфигурации или контрактов.
- [ ] Публикация тега до слияния release PR.

## 4. План реализации (Implementation Plan)
1. [x] Зафиксировать состав `v0.5.3` (`v0.5.2..main`).
2. [x] Заполнить `CHANGELOG.md` (категория Changed).
3. [x] Создать `docs/releases/v0.5.3/release-plan.md` по образцу `v0.5.2`.
4. [x] Создать release PR, заполнить `pr` этой задачи, перевести задачу в `done`.

## 5. Критерии приёмки (Definition of Done)
- [ ] Release PR слит в `main`, CI зелёная.
- [ ] В `main` присутствуют запись `CHANGELOG.md`, план `v0.5.3` и эта задача в `todo/done/`.
- [ ] `make check` зелёный на вершине `main`.

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate todo/done/TASK-release-v0-5-3-preparation.todo.md
git log v0.5.2..main --oneline
```

## 7. Риски и зависимости (Risks and Dependencies)
- Зависимость только в `require-dev` — runtime и потребители не затронуты.
- `coding-standard` 0.32.0 несёт ломающее правило `ReservedLayerSegment` — в этом проекте нарушений нет (проверено в #378).

## 8. Источники (Sources)
- План релиза: `docs/releases/v0.5.3/release-plan.md`
- Запрос #378 — обновление `prikotov/coding-standard`

## 9. Комментарий (Comments)
Прямая команда пользователя: «смержи, релизни». По политике релизов (docs/releases/RELEASE-POLICY.md) релиз готовится только из `main` через release PR; тег — после слияния.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-03 05:25:00 (1788413100) | Бэкендер Тони (pi) | Создание задачи |
