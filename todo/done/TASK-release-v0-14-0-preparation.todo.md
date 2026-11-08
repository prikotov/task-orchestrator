---
type: chore
created: 2026-11-08 03:03:02 (1794106982)
due: 
started: 2026-11-08 03:03:02 (1794106982)
completed: 2026-11-08 03:06:12 (1794107172)
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
consultants: []
reviewer: 
approver: 
informed: []
branch: release/0.14.0
pr: https://github.com/prikotov/task-orchestrator/pull/470
status: done
---

# TASK-release-v0-14-0-preparation: Подготовить релиз v0.14.0

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- #469 (скилл `pi-native-rendering` в каталоге пакета) в `main`, но не выпущен; PHAR-сборка не включала новый каталог skills — smoke `[layout]` красный на `main`.

### Варианты или путь решения (Solution Sketch)
- Подготовить `CHANGELOG.md` и план, включить фикс упаковки PHAR в релизный PR, проверить и опубликовать тег `v0.14.0`.

### Ожидаемый результат (Expected Result)
- Тег `v0.14.0` на проверенном merge commit; GitHub Release с PHAR-asset; PHAR содержит полный каталог skills.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story или Job Story)

> **Job Story:** Когда downstream-проект оркестратора хочет дать ролям скилл встроенного рендеринга pi, я хочу получить его из пакета, чтобы не копировать скилл в каждый проект.

### Цель по SMART (Goal)
- Опубликовать `v0.14.0` в день запроса владельца.

## 2. Контекст и Границы (Context and Scope)

*   **Где делаем:** `CHANGELOG.md`, `docs/releases/v0.14.0/release-plan.md`, `box.json.dist` (фикс упаковки), релизная задача.
*   **Текущее поведение:** main после #466/#469; PHAR smoke `[layout]` падает (skill вне PHAR).
*   **Прецеденты для переиспользования:** релизы v0.12.0/v0.13.0.
*   **Критичные конвенции:** `docs/git-workflow/pull-request.md`.
*   **Границы (Out of Scope):** кодовые изменения резолвинга; правки контента скилла.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [ ] `box.json.dist`: пакуется весь каталог `docs/agents/skills`; `make phar-smoke PHAR_EXPECTED_VERSION=dev` зелёный локально.
- [ ] `CHANGELOG.md` и план релиза v0.14.0.
- [ ] Предрелизные проверки на merge commit: composer validate --strict, phpunit, psalm, deptrac, phar-e2e — без ошибок.
- [ ] Тег `v0.14.0` опубликован на проверенном merge commit; workflow `Release Phar` успешен.

### 🟡 Желательно (Should Have)
- [ ] GitHub Release содержит PHAR-asset.

### 🟢 Опционально (Could Have)
- [ ] Нет.

### ⚫ Не будем делать (Won't Have)
- [ ] Правки контента скилла и резолвинга.

## 4. План реализации (Implementation Plan)

1. [x] Фикс `box.json.dist` + локальный `phar-smoke`.
2. [x] CHANGELOG.md и план релиза.
3. [ ] Release PR от бота, апрув владельца, merge.
4. [ ] phar-e2e на merge commit, тег `v0.14.0`, публикация, проверка Release Phar.

## 5. Критерии приёмки (Definition of Done)

- [ ] `gh release view v0.14.0 --json tagName,assets` — релиз с PHAR-asset.
- [ ] `git rev-list -n 1 v0.14.0` — проверенный merge commit.

## 6. Самопроверка (Verification)

```bash
composer validate --strict && vendor/bin/phpunit && vendor/bin/psalm && make phar-smoke PHAR_EXPECTED_VERSION=dev && make phar-e2e
```

## 7. Риски и зависимости (Risks and Dependencies)

- PHAR растёт ~350 КБ (каталог skills целиком).

## 8. Источники (Sources)

- [ ] #469; планы релизов v0.12.0/v0.13.0.

## 9. Комментарии (Comments)

- Релиз выполняется по явной команде владельца («469 заапрувил, смержи и сделай релиз»).

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-08 03:03:02 (1794106982) | Тимлид Алекс (pi) | Создание задачи |
