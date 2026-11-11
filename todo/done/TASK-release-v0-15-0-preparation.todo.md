---
type: chore
created: 2026-11-11 05:11:57 (1794373917)
due: 
started: 2026-11-11 05:12:02 (1794373922)
completed: 2026-11-11 05:16:16 (1794374176)
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
branch: release/0.15.0
pr: https://github.com/prikotov/task-orchestrator/pull/480
status: done
---

# TASK-release-v0-15-0-preparation: Подготовить релиз v0.15.0

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- #479 слит в `main`, но не выпущен: потребители ещё не получили требование обязательного уровня сложности в `watch-subagent.sh` и строку сложности в run-log.

### Варианты или путь решения (Solution Sketch)
- Ветка `release/0.15.0` от актуального `main`; финализировать `CHANGELOG.md` (`[0.15.0]`), создать `docs/releases/v0.15.0/release-plan.md`, прогнать предрелизные проверки (включая `phar-smoke`/`phar-e2e`), выпустить PR в `main`, после слияния — тег `v0.15.0` и GitHub Release.

### Ожидаемый результат (Expected Result)
- Тег `v0.15.0` на проверенном merge commit; GitHub Release с PHAR-asset; workflow `Release Phar` зелёный.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story или Job Story)

> **Job Story:** Когда downstream-проект обновляет пакет, я хочу получить релиз `v0.15.0` с зафиксированным контрактом сложности `watch-subagent.sh`, чтобы обновление было предсказуемым и задокументированным.

### Цель по SMART (Goal)
- Выпустить `v0.15.0` (minor, BREAKING CHANGE) с #479: CHANGELOG, план релиза, проверки, PR в `main`, тег и GitHub Release — в течение дня.

## 2. Контекст и Границы (Context and Scope)

- Состав: #479 + подготовительный PR (CHANGELOG, план релиза, эта задача).
- Несовместимость: запуск `watch-subagent.sh` без `--task-id`/`--complexity` завершается ошибкой; миграция описана в `docs/releases/v0.15.0/release-plan.md`.
- Out of scope: `TASK-fix-orchestrate-complexity-required` (backlog), рефакторинг phpmd-замечаний.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] (заполнить)
### ⚫ Won't Have (Не будем делать)
- (заполнить)

## 4. План реализации (Implementation Plan)
1. [x] Ветка `release/0.15.0` от актуального `main`.
2. [x] `CHANGELOG.md`: `[Unreleased]` → `[0.15.0]`, compare-ссылка, раздел «Несовместимость».
3. [x] `docs/releases/v0.15.0/release-plan.md`.
4. [ ] Предрелизные проверки (включая `phar-smoke`, `phar-e2e`).
5. [ ] PR в `main`, метка `pi`, CI зелёный, задача в `done` до апрува.
6. [ ] Слияние по подтверждению владельца, проверка merge commit, тег `v0.15.0`, GitHub Release.

## 5. Критерии приёмки (Definition of Done)
- [ ] Предрелизные проверки зелёные: `composer validate --strict`, `vendor/bin/phpunit`, `vendor/bin/psalm`, deptrac, `make phar-smoke PHAR_EXPECTED_VERSION=dev`, `make phar-e2e`.
- [ ] PR `release/0.15.0` → `main` одобрен владельцем и слит по подтверждению.
- [ ] Тег `v0.15.0` на проверенном merge commit; GitHub Release с PHAR-asset; workflow `Release Phar` успешен.

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate
```

## 7. Риски и зависимости (Risks and Dependencies)
- Несовместимое изменение контракта `watch-subagent.sh` для потребителей (см. план релиза).
- Локальный `make phpmd` красный на ранее существовавших замечаниях `ExcessiveClassLength` (`src/Kernel.php`, `YamlChainLoaderService.php`) — вне состава релиза.

## 8. Источники (Sources)

## 9. Комментарии (Comments)

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-11 05:11:57 (1794373917) | Тимлид Алекс (pi) | Создание задачи |
