---
type: chore
created: 2026-11-07 15:21:03 (1794064863)
due: 
started: 2026-11-07 15:21:50 (1794064910)
completed: 2026-11-07 15:41:36 (1794066096)
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
branch: release/0.13.0
pr: https://github.com/prikotov/task-orchestrator/pull/467
status: done
---

# TASK-release-v0-13-0-preparation: Подготовить релиз v0.13.0

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Изменения #466 (пер-скилл приоритет каталогов skills: host-проект дополняет и переопределяет пакет) находятся в `main`, но ещё не выпущены как версия пакета.

### Варианты или путь решения (Solution Sketch)
- Подготовить `CHANGELOG.md` и план релиза, открыть release PR, проверить и опубликовать тег `v0.13.0`.

### Ожидаемый результат (Expected Result)
- Тег `v0.13.0` опубликован на проверенном merge commit, GitHub Release содержит PHAR-asset; хост-проекты могут подключать свои skills без потери пакетных.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story или Job Story)

> **Job Story:** Когда хост-проект добавляет свои skills в `docs/agents/skills/`, я хочу, чтобы пакетные skills продолжали резолвиться, чтобы обновления пакета и локальные навыки сосуществовали.

### Цель по SMART (Goal)
- Опубликовать `v0.13.0` с пер-скилл приоритетом каталогов skills (#466) в день запроса.

## 2. Контекст и Границы (Context and Scope)

*   **Где делаем:** `CHANGELOG.md`, `docs/releases/v0.13.0/release-plan.md`, релизная задача.
*   **Текущее поведение:** #466 смержен в `main`, версия не выпущена.
*   **Прецеденты для переиспользования:** релиз v0.12.0 (документы и порядок публикации).
*   **Критичные конвенции:** `docs/git-workflow/pull-request.md` (идентичность PR от бота, метка).
*   **Границы (Out of Scope):** кодовые изменения #466; правки документации skills вне релизных файлов.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [ ] `CHANGELOG.md` содержит секцию 0.13.0 (#466).
- [ ] `docs/releases/v0.13.0/release-plan.md` описывает порядок публикации.
- [ ] Предрелизные проверки на merge commit: composer validate --strict, phpunit, psalm, deptrac, make phar-e2e — без ошибок.
- [ ] Тег `v0.13.0` опубликован на проверенном merge commit; workflow `Release Phar` успешен.

### 🟡 Желательно (Should Have)
- [ ] GitHub Release содержит PHAR-asset.

### 🟢 Опционально (Could Have)
- [ ] Нет.

### ⚫ Не будем делать (Won't Have)
- [ ] Правки кода #466 в подготовительном PR.

## 4. План реализации (Implementation Plan)

1. [ ] CHANGELOG.md и план релиза.
2. [x] Release PR от бота, апрув владельца, merge.
3. [x] Проверки и phar-e2e на merge commit (70db8a22): OK (7 tests, 44 assertions).
4. [x] Тег `v0.13.0` на 70db8a22, workflow `Release Phar` успешен, GitHub Release с asset `task-orchestrator.phar`.

## 5. Критерии приёмки (Definition of Done)

- [x] `gh release view v0.13.0 --json tagName,assets` — релиз с PHAR-asset.
- [x] `git rev-list -n 1 v0.13.0` — проверенный merge commit (70db8a22).

## 6. Самопроверка (Verification)

```bash
composer validate --strict && vendor/bin/phpunit && vendor/bin/psalm && make phar-e2e
```

## 7. Риски и зависимости (Risks and Dependencies)

- Internal BC конструктора `YamlLoadSkillFrontmatterService` (external-потребителей нет).

## 8. Источники (Sources)

- [ ] #466; план релиза `docs/releases/v0.13.0/release-plan.md`; прецедент v0.12.0.

## 9. Комментарии (Comments)

- Релиз выполняется по явной команде владельца («смержи, релизни, обнови и затести»).

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-07 15:21:03 (1794064863) | Тимлид Алекс (pi) | Создание задачи |
