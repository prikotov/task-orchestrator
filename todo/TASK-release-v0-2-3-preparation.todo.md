---
type: chore
created: 2026-07-17
value: V3
complexity: C1
priority: P0
depends_on:
epic:
author: pi
assignee: pi
branch: task/release-v0-2-3
pr:
status: review
---

# TASK-release-v0-2-3-preparation: Подготовить и выпустить patch release v0.2.3

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

Агенты в host-проектах путались на относительных путях внутри skill (`scripts/…`), не связывая их с `<location>`, и искали скрипты в корне проекта. Улучшение (PR #315) принято в `main`, но до релиза `v0.2.3` оно не доходит до пользователей `release/0.2`.

### Варианты или путь решения (Solution Sketch)

Cherry-pick исправления из `main` в `release/0.2`, добавить patch entry в `CHANGELOG.md` и release plan, провести финальные проверки, открыть PR `task/release-v0-2-3` → `release/0.2`. Тег `v0.2.3` публиковать после merge.

### Ожидаемый результат (Expected Result)

В `release/0.2` приняты проверенные release metadata `v0.2.3`, а тег запускает выпуск PHAR; агенты в host-проектах получают ясную инструкцию резолва путей skill через `<location>`.

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)

> Когда улучшение подсказки резолва путей skill принято в `main`, я хочу выпустить минимальный patch release `v0.2.3`, чтобы пользователи host-проектов получали ясную инструкцию `become-role`.

### Goal (Цель по SMART)

Добавить patch entry `0.2.3` в `CHANGELOG.md`, подготовить `docs/releases/v0.2.3/release-plan.md`, выполнить финальные проверки и создать PR `task/release-v0-2-3` → `release/0.2`. После merge опубликовать тег `v0.2.3`.

## 2. Context and Scope (Контекст и Границы)

* **Где делаем:** `CHANGELOG.md`, `docs/releases/v0.2.3/release-plan.md`, этот task file; cherry-pick исправления из `main`; GitHub PR и тег.
* **Основание:** PR #315 слит в `main` merge commit `d05d460`; cherry-pick переносит исправление в `release/0.2`. Merge-back не требуется — исправление уже в `main`.
* **Границы (Out of Scope):**
  * не менять production-код (кроме cherry-pick), тесты, зависимости, CI/CD, release tooling;
  * не добавлять features или рефакторинг вне PR #315.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [x] `CHANGELOG.md` содержит patch entry `0.2.3` о подсказке резолва путей skill через `<location>`.
- [x] `docs/releases/v0.2.3/release-plan.md` фиксирует состав, риски, проверки и порядок публикации.
- [x] Исправление PR #315 cherry-pickнуто в `release/0.2`.
- [x] `make check` проходит на вершине release candidate.
- [x] Создан PR `task/release-v0-2-3` → `release/0.2`; поле `pr` заполнено, задача в `review`.
- [x] После approval задача переведена в `done` и перенесена в `todo/done/`.
- [ ] Тег `v0.2.3` создан и отправлен после merge PR.
- [ ] После публикации проверены workflow `Release Phar` и наличие PHAR-артефакта.

### ⚫ Won't Have (Не будем делать)

- [ ] Новый tooling, features или исправления вне PR #315.
- [ ] Merge-back в `main` — исправление уже в `main`.

## 4. Implementation Plan (План реализации)

1. [x] Cherry-pick merge commit `d05d460` в ветку `task/release-v0-2-3` от `release/0.2`.
2. [x] Добавить patch entry `0.2.3` в `CHANGELOG.md`.
3. [x] Подготовить `docs/releases/v0.2.3/release-plan.md`.
4. [x] Выполнить `make check`.
5. [x] Создать PR `task/release-v0-2-3` → `release/0.2`, заполнить `pr`, перевести задачу в `review`.
6. [ ] После approval перевести задачу в `done` и перенести в `todo/done/`.
7. [ ] Слить PR в `release/0.2`.
8. [ ] Создать и отправить тег `v0.2.3`, проверить GitHub Release и PHAR-артефакт.

## 5. Definition of Done (Критерии приёмки)

- [x] Diff PR ограничен `CHANGELOG.md`, release plan, файлом задачи и cherry-pick фикса.
- [x] `make check` проходит успешно.
- [ ] PR одобрен, CI зелёный; задача переведена в `done` и перенесена в `todo/done/`.
- [ ] PR слит в `release/0.2`.
- [ ] Тег `v0.2.3` опубликован; GitHub Release содержит PHAR-артефакт.

## 6. Verification (Самопроверка)

```bash
make check
git diff --check
```

После публикации:

```bash
gh run list --workflow="Release Phar" --limit=1
gh release view v0.2.3
```

## 7. Risks and Dependencies (Риски и зависимости)

- Тег должен указывать на проверенную вершину `release/0.2` после merge PR.
- Публикация тега запускает внешний release workflow (`Release Phar`).

## 8. Sources (Источники)

- [Правила релизов](../docs/git-workflow/release.md)
- [Правила работы с задачами](../AGENTS.md)
- PR #315 (исходное улучшение)

## 9. Comments (Комментарии)

Релиз одобрен пользователем. Merge release-PR и публикация тега выполнены по единому разрешению «смержи, релизни».

## Change History (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-17 | pi | Создание задачи и подготовка patch release `v0.2.3`. |
