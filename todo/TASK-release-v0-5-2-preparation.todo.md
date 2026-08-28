---
type: chore
created: 2026-08-27 23:05:00 (1787846700)
due:
started: 2026-08-27 23:05:00 (1787846700)
completed:
cancelled:
value: V2
complexity: C1
priority: P1
cost_plan:
cost_fact:
depends_on:
epic:
author: Тимлид Алекс (pi)
assignee: Тимлид Алекс (pi)
branch: task/release-v0-5-2
pr:
status: in_progress
---

# TASK-release-v0-5-2-preparation: Подготовить release PR для v0.5.2

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

Правила скриптов и путей для навыков (#374) и исследование агента hax (#373) слиты в `main`, но последний опубликованный тег `v0.5.1` их не содержит. Потребители пакета не получают обновлённые правила создания навыков в `docs/`.

### Варианты или путь решения (Solution Sketch)

Подготовить patch-релиз `v0.5.2` из `main`: кратко описать изменения в CHANGELOG, оформить план релиза и создать release PR с документационными артефактами.

### Ожидаемый результат (Expected Result)

Release PR фиксирует состав `v0.5.2`, риски и шаги публикации; после слияния можно безопасно создать тег `v0.5.2` из `main`.

## 1. Концепция и Цель (Concept and Goal)

### История (Job Story)

> **Job Story:** Когда документационные изменения слиты в `main`, я хочу подготовить release PR для patch-релиза, чтобы потребители получили обновлённые правила в следующем опубликованном теге.

### Цель по SMART (Goal)

Подготовить документационные артефакты релиза (CHANGELOG, план релиза), создать PR в `main`, после зелёной CI перевести задачу в `done`. Публикация тега — отдельный операционный этап после слияния.

## 2. Контекст и Границы (Context and Scope)

* **Где делаем:** `CHANGELOG.md`, `docs/releases/v0.5.2/release-plan.md`, этот файл задачи.
* **Текущее поведение:** последний опубликованный тег — `v0.5.1`; в `main` после него слиты: [#373](https://github.com/prikotov/task-orchestrator/pull/373) (исследование CLI-агента hax, `docs/research/`) и [#374](https://github.com/prikotov/task-orchestrator/pull/374) (раздел «Скрипты и пути» в `SKILL-CREATION.md`, запись реестра ретро). Оба — docs-only.
* **Границы (Out of Scope):** не изменять код, конфигурацию, скрипты, публичный JSON/XML-контракт `agent:role-skills`, PHAR-установку `become-role`.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [x] `CHANGELOG.md` содержит краткую запись `v0.5.2` со ссылками на PR #373 и #374.
- [x] `docs/releases/v0.5.2/release-plan.md` фиксирует состав, риски, отсутствие миграций, порядок публикации и проверки после публикации.
- [x] Задача подготовки создана со статусом `in_progress` и веткой `task/release-v0-5-2`.
- [x] Release PR создан от имени GitHub App с меткой AI-агента.
### 🟡 Желательно (Should Have)
- [ ] —
### 🟢 Опционально (Could Have)
- [ ] —
### ⚫ Не будем делать (Won't Have)
- [ ] Изменения кода, конфигурации или контрактов.
- [ ] Публикация тега до слияния release PR.

## 4. План реализации (Implementation Plan)
1. [x] Зафиксировать состав `v0.5.2` (коммиты `v0.5.1..main`).
2. [x] Запись в `CHANGELOG.md`.
3. [x] План релиза `docs/releases/v0.5.2/release-plan.md`.
4. [ ] Коммит, push токеном бота, PR, зелёная CI.
5. [ ] Перевод задачи в `done`, запрос одобрения.

## 5. Критерии приёмки (Definition of Done)
- [x] Запись CHANGELOG краткая и соответствует фактическому составу.
- [x] План релиза заполнен по образцу v0.5.1.
- [ ] Release PR слит; тег `v0.5.2` опубликован из `main` через GitHub App.
- [ ] GitHub Release содержит `task-orchestrator.phar`; `php task-orchestrator.phar list` работает.

## 6. Самопроверка (Verification)
```bash
git log v0.5.1..main --oneline
php vendor/bin/todo-md validate todo/TASK-release-v0-5-2-preparation.todo.md
make md-links && make validate-language
make check
PHAR_EXPECTED_VERSION=dev make phar-smoke && make composer-host-smoke
```

## 7. Риски и зависимости (Risks and Dependencies)
- Docs-only состав: риск окна несовместимости отсутствует; публичные контракты не менялись.
- Публикация тега — только после слияния release PR и зелёных проверок.

## 8. Источники (Sources)
- [Политика релизов](../docs/releases/RELEASE-POLICY.md)
- [План релиза v0.5.1](../docs/releases/v0.5.1/release-plan.md) — образец.
- [Релизы и CHANGELOG](../docs/git-workflow/release.md)

## 9. Комментарии (Comments)
- Владелец подтвердил релиз 2026-08-27 («смержи и релизни») после апрува и merge PR #374.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-27 23:05:00 (1787846700) | Тимлид Алекс (pi) | Создание задачи и артефактов релиза |
