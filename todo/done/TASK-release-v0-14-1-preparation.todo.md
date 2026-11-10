---
type: chore
created: 2026-11-10
due: 
started: 2026-11-10 08:02:56 (1794297776)
completed: 2026-11-10 08:29:47 (1794299387)
cancelled: 
value: V2
complexity: C1
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Аналитик Шерлок (pi)
assignee: Аналитик Шерлок (pi)
consultants: []
reviewer: 
approver: 
informed: []
branch: release/0.14.1
pr: https://github.com/prikotov/task-orchestrator/pull/477
status: done
---

# TASK-release-v0-14-1-preparation: Подготовить релиз v0.14.1

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Изменения #471–#476 (актуализация скилла `pi-native-rendering` под pi 1.5.1, research-постановки, обновление выводов ZCode) находятся в `main`, но ещё не выпущены как версия пакета.

### Варианты или путь решения (Solution Sketch)
- Подготовить `CHANGELOG.md` и план релиза, открыть release PR, выполнить проверки (включая `make phar-e2e`) и после одобрения опубликовать тег `v0.14.1` с GitHub Release.

### Ожидаемый результат (Expected Result)
- Опубликован GitHub Release `v0.14.1` с PHAR-asset на проверенном merge commit.

## 1. Концепция и Цель (Concept and Goal)

### Цель по SMART (Goal)
- Подготовить и выпустить `v0.14.1` по release policy с успешными проверками до 2026-11-11.

## 2. Контекст и Границы (Context and Scope)

- Scope: релизные документы (`CHANGELOG.md`, `docs/releases/v0.14.1/release-plan.md`), проверки, тег и GitHub Release.
- Out of scope: новые функциональные изменения.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [ ] Версия `0.14.1` выбрана как patch (только `docs`/`chore` с `v0.14.0`); несовместимых изменений нет.
- [x] Состав и порядок публикации описаны в `docs/releases/v0.14.1/release-plan.md`.
- [x] Release PR слит, проверки и `make phar-e2e` успешны.
- [x] Тег и GitHub Release опубликованы на проверенном merge commit.

### ⚫ Won't Have (Не будем делать)
- Изменение опубликованных тегов и функциональные доработки.

## 4. План реализации (Implementation Plan)

1. [x] Обновить changelog и создать план.релиза.
2. [x] Открыть release PR, выполнить проверки и `make phar-e2e`.
3. [x] Слить PR после одобрения, проверить merge commit, опубликовать тег и проверить release workflow.

## 5. Критерии приёмки (Definition of Done)

- [x] `v0.14.1` доступен в GitHub Releases.
- [x] Тег неизменяем и указывает на проверенный merge commit.
- [x] PHAR-asset успешно опубликован.

## 6. Самопроверка (Verification)

```bash
composer validate --strict
vendor/bin/phpunit
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
make phar-smoke PHAR_EXPECTED_VERSION=dev
make phar-e2e
php vendor/bin/todo-md validate
```

## 7. Риски и зависимости (Risks and Dependencies)

- Риск минимальный: изменения только в документации; PHAR меняется содержимым скилла без кода.

## 8. Источники (Sources)

- [x] [RELEASE-POLICY.md](../../docs/releases/RELEASE-POLICY.md)
- [x] [release.md](../../docs/git-workflow/release.md)

## 9. Комментарии (Comments)

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-10 15:02:56 (1794297776) | Аналитик Шерлок (pi) | Создание задачи |
