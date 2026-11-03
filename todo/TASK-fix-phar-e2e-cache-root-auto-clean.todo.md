---
type: test
created: 2026-11-03 05:10:21 (1793682621)
due: 
started: 2026-11-03 05:10:21 (1793682621)
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
branch: task/fix-phar-e2e-cache-root-auto-clean
pr: https://github.com/prikotov/task-orchestrator/pull/452
status: in_progress
---

# TASK-fix-phar-e2e-cache-root-auto-clean: Обновить асерт E2E изоляции PHAR-кеша под контракт FR-4

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Обязательная предрелизная проверка `make phar-e2e` падает: `PharDistributionTest::testPharMoveToAnotherLocationReinstallsOnFreshDefaultCacheWithoutManualCleanup` требует «≥2 кеш-корня сосуществуют» после перемещения архива A → B. Фича FR-4 (#443) сознательно зачищает устаревший корень A при загрузке из B — остаётся ровно 1. Тест создан до FR-4 (#387/#400) и утверждает устаревшее поведение; `phar-e2e` — только локальная цель, в CI отсутствует, поэтому расхождение не было замечено.

### Варианты или путь решения (Solution Sketch)
- Продукт ведёт себя ровно как задокументировано (CHANGELOG 0.10.0, FR-4): чинится асерт теста, не код. Шаг 6 заменяется на контракт FR-4: ровно один оставшийся корень + маркер владения `.task-orchestrator-owner.json` (корень захвачен запуском из B).

### Ожидаемый результат (Expected Result)
- `make phar-e2e` зелёный на `main`; E2E-контракт синхронизирован с FR-4; состав релиза v0.10.0 в плане дополнен этим фиксом.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **Как** выпускающий участник, **я хочу** чтобы предрелизный E2E проверял актуальный контракт кеша PHAR, **чтобы** тег v0.10.0 ставился только на полностью проверенное состояние.

### Цель по SMART (Goal)
- Обновить шаг 6 теста `PharDistributionTest` под контракт FR-4; `make phar-e2e` (7 тестов) и `make check` зелёные на ветке фикса; план релиза v0.10.0 дополнен составом.

## 2. Контекст и Границы (Context and Scope)

- **Где делаем:** `apps/console/tests/E2E/Phar/PharDistributionTest.php`, `docs/releases/v0.10.0/release-plan.md`, задача в `todo/`
- **База:** `main` `00585c51` (merge #451)
- **Границы (Out of Scope):** изменение продакшн-кода `src/Component/PharCache`, подключение `phar-e2e` в CI (отдельная задача при согласии владельца)

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] Шаг 6 теста: ровно 1 кеш-корень после переустановки из B (автоочистка FR-4)
- [ ] Асерт маркера владения в оставшемся корне
- [ ] `make phar-e2e` и `make check` зелёные
- [ ] План релиза v0.10.0 дополнен составом фикса
### ⚫ Won't Have (Не будем делать)
- Правки продакшн-кода и CHANGELOG (тест-only изменение)

## 4. План реализации (Implementation Plan)
1. [x] Ветка `task/fix-phar-e2e-cache-root-auto-clean` от `main`
2. [x] Правка шага 6 теста под контракт FR-4
3. [x] `make phar-e2e` зелёный (7 тестов, 44 assertions)
4. [ ] `make check`, план релиза, коммит, push, PR

## 5. Критерии приёмки (Definition of Done)
- [ ] PR прошёл CI; `make phar-e2e` зелёный на принятом `main` перед тегом v0.10.0

## 6. Самопроверка (Verification)
```bash
make phar-e2e
make check
```
