---
type: chore
created: 2026-11-06
due: 
started: 2026-11-06
completed: 
cancelled: 
value: V2
complexity: C1
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Алекс (pi)
assignee: Тимлид Алекс (pi)
branch: release/0.12.0
pr: 
status: review
---

# TASK-release-v0-12-0-preparation: Подготовить релиз v0.12.0

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Изменения #460 находятся в `main`, но ещё не выпущены как версия пакета.

### Варианты или путь решения (Solution Sketch)
- Подготовить `CHANGELOG.md` и план, открыть release PR, проверить и опубликовать тег `v0.12.0`.

### Ожидаемый результат (Expected Result)
- Опубликован GitHub Release `v0.12.0` с PHAR-asset на проверенном merge commit.

## 1. Концепция и Цель (Concept and Goal)

### Цель по SMART (Goal)
- Подготовить и выпустить `v0.12.0` по release policy с успешными проверками.

## 2. Контекст и Границы (Context and Scope)

- Scope: релизные документы, changelog, проверки, тег и GitHub Release.
- Out of scope: новые функциональные изменения.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [x] Версия `0.12.0` выбрана как minor; несовместимость resume отмечена.
- [x] Состав и порядок публикации описаны в `docs/releases/v0.12.0/release-plan.md`.
- [ ] Release PR слит, проверки и `make phar-e2e` успешны.
- [ ] Тег и GitHub Release опубликованы на проверенном merge commit.

### ⚫ Won't Have (Не будем делать)
- Изменение опубликованных тегов и функциональные доработки.

## 4. План реализации (Implementation Plan)

1. [x] Обновить changelog и создать план.
2. [ ] Открыть и слить release PR после одобрения.
3. [ ] Проверить merge commit, опубликовать тег и проверить release workflow.

## 5. Критерии приёмки (Definition of Done)

- [ ] `v0.12.0` доступен в GitHub Releases.
- [ ] Тег неизменяем и указывает на проверенный merge commit.
- [ ] PHAR-asset успешно опубликован.

## 6. Самопроверка (Verification)

```bash
composer validate --strict
vendor/bin/phpunit
vendor/bin/psalm
make phar-e2e
```

## 7. Риски и зависимости (Risks and Dependencies)

- Между подготовкой и публикацией `main` может измениться; тег привязывается только к проверенному составу.

## 8. Источники (Sources)

- `docs/git-workflow/release.md`.
- `docs/releases/v0.12.0/release-plan.md`.

## 9. Комментарии (Comments)

## История изменений (Change History)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-11-06 | Тимлид | Подготовка релиза v0.12.0 |
