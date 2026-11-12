---
type: chore
created: 2026-11-12 02:37:50 (1794451070)
due: 
started: 2026-11-12 02:38:00 (1794451080)
completed: 2026-11-12 09:51:30 (1794451890)
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
branch: release/0.15.1
pr: https://github.com/prikotov/task-orchestrator/pull/483
status: done
---

# TASK-release-v0-15-1-preparation: Подготовить релиз v0.15.1

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- #481 (обратная связь постановщику в скилле `retrospective`) и #482 (todo-md v0.3.0 → v0.4.0) слиты в `main`, но не выпущены: потребители не получили обновление.

### Варианты или путь решения (Solution Sketch)
- Ветка `release/0.15.1` от актуального `main`; финализировать `CHANGELOG.md` (`[0.15.1]`), создать `docs/releases/v0.15.1/release-plan.md`, прогнать предрелизные проверки (включая `phar-smoke`/`phar-e2e`), выпустить PR в `main`, после слияния — тег `v0.15.1` и GitHub Release.

### Ожидаемый результат (Expected Result)
- Тег `v0.15.1` на проверенном merge commit; GitHub Release с PHAR-asset; workflow `Release Phar` зелёный.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **Job Story:** Когда downstream-проект обновляет пакет, я хочу получить релиз `v0.15.1` с обратной связью постановщику в ретроспективе и актуальным todo-md, чтобы обновление было предсказуемым и задокументированным.

### Цель по SMART (Goal)
- Выпустить `v0.15.1` (patch, без несовместимых изменений) с #481 и #482: CHANGELOG, план релиза, проверки, PR в `main`, тег и GitHub Release — в течение дня.

## 2. Контекст и Границы (Context and Scope)

- Состав: #481 (`docs(retrospective)` — обратная связь постановщику), #482 (`chore(deps)` — todo-md v0.4.0) + подготовительный PR (CHANGELOG, план релиза, эта задача).
- Несовместимость отсутствует: изменения в документации скилла и dev-зависимости.
- Out of scope: рефакторинг phpmd-замечаний, задачи из `todo/backlog`.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] Ветка `release/0.15.1` от актуального `main`.
- [x] `CHANGELOG.md`: секция `[0.15.1]`, compare-ссылка.
- [x] `docs/releases/v0.15.1/release-plan.md`.
- [x] Предрелизные проверки (включая `phar-smoke`, `phar-e2e`).
- [x] PR в `main`, метка `pi`, CI зелёный, задача в `done` до апрува.
- [ ] Слияние по подтверждению владельца, проверка merge commit, тег `v0.15.1`, GitHub Release.
### ⚫ Won't Have (Не будем делать)
- Повышение minor/major (нет feat/fix/breaking), рефакторинг phpmd-замечаний.

## 4. План реализации (Implementation Plan)
1. [x] Ветка `release/0.15.1` от актуального `main`.
2. [x] `CHANGELOG.md`: секция `[0.15.1]` с compare-ссылкой.
3. [x] `docs/releases/v0.15.1/release-plan.md`.
4. [x] Предрелизные проверки (включая `phar-smoke`, `phar-e2e`).
5. [x] PR в `main`, метка `pi`, CI зелёный, задача в `done` до апрува.
6. [ ] Слияние по подтверждению владельца, проверка merge commit, тег `v0.15.1`, GitHub Release.

## 5. Критерии приёмки (Definition of Done)
- [x] Предрелизные проверки зелёные: `composer validate --strict`, `vendor/bin/phpunit`, `vendor/bin/psalm`, deptrac, `make phar-smoke PHAR_EXPECTED_VERSION=dev`, `make phar-e2e`.
- [ ] PR `release/0.15.1` → `main` одобрен владельцем и слит по подтверждению.
- [ ] Тег `v0.15.1` на проверенном merge commit; GitHub Release с PHAR-asset; workflow `Release Phar` успешен.

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate
```

## 7. Риски и зависимости (Risks and Dependencies)
- Локальный `make phpmd` красный на ранее существовавших замечаниях `ExcessiveClassLength` (`src/Kernel.php`, `YamlChainLoaderService.php`) — вне состава релиза.

## 8. Источники (Sources)
- #481, #482; `docs/git-workflow/release.md`.

## 9. Комментарии (Comments)

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-12 02:37:50 (1794451070) | Тимлид Алекс (pi) | Создание задачи |
| 2026-11-12 02:38:00 (1794451080) | Тимлид Алекс (pi) | Назначение исполнителя, запуск (ветка `release/0.15.1`) |
| 2026-11-12 09:51:30 (1794451890) | Тимлид Алекс (pi) | CI зелёный, PR #483, задача переведена в `done` до апрува |
