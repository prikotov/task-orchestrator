---
type: chore
created: 2026-09-03 05:13:05 (1788412385)
due: 
started: 2026-09-03 05:15:37 (1788412537)
completed: 2026-09-03 05:16:17 (1788412577)
cancelled: 
value: V2
complexity: C2
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Бэкендер Тони (pi)
assignee: Бэкендер Тони (pi)
branch: task/update-prikotov-coding-standard
pr: https://github.com/prikotov/task-orchestrator/pull/378
status: done
---

# TASK-chore-update-prikotov-coding-standard: Обновление prikotov/coding-standard до ^0.32.0

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Из пакетов `prikotov/*` устарел `prikotov/coding-standard` (0.31.0 → доступна 0.32.0). Новая версия добавляет правило `ReservedLayerSegment` (phpcs-снифф + deptrac-правило) и обновляет эталонные deptrac-коллекторы. Constraint `^0.31.0` не пускает 0.32.0 — нужен подъём в `composer.json` и проверка совместимости кода проекта.

### Варианты или путь решения (Solution Sketch)
1. Проверить код проекта на нарушения нового правила (grep по вложенным сегментам слоёв).
2. Поднять constraint `prikotov/coding-standard` до `^0.32.0`, выполнить `composer update prikotov/coding-standard --with-dependencies`.
3. Прогнать полный `make check` (phpstan, deptrac, psalm, phpmd, phpcs, md-links, validate-todo, validate-roles, validate-language, tests).

### Ожидаемый результат (Expected Result)
`prikotov/coding-standard` на 0.32.0, остальные `prikotov/*` без изменений (уже последние); `make check` зелёный, код проекта не тронут.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **User Story:** Как бэкендер, я хочу, чтобы `prikotov/coding-standard` стоял на последней версии, чтобы проверки стиля и deptrac-правила соответствовали актуальному контракту пакета.

### Цель по SMART (Goal)
Обновить `prikotov/coding-standard` до 0.32.0 (constraint `^0.32.0`) и убедиться, что полный `make check` проходит без правок кода проекта. Срок: 2026-09-03.

## 2. Контекст и Границы (Context and Scope)

* **In Scope (Что делаем):**
    * `composer.json`: constraint `prikotov/coding-standard` `^0.31.0` → `^0.32.0`
    * `composer update prikotov/coding-standard --with-dependencies` (транзитивно: `phpstan/phpdoc-parser` 2.3.3 → 2.3.5)
    * Полный прогон `make check`
* **Out of Scope (Чего НЕ делаем):**
    * Обновление сторонних зависимостей (symfony/*, phpunit 13, deptrac 4 и пр.) — отдельное решение
    * Изменения кода проекта (предварительная проверка показала, что нарушений нового правила нет)

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] Constraint `prikotov/coding-standard` поднят до `^0.32.0`, `composer.lock` зафиксирован
- [x] Новое правило `ReservedLayerSegment` (phpcs + deptrac) не находит нарушений в коде проекта
- [x] `make check` завершается успешно
### ⚫ Won't Have (Не будем делать)
- Обновление symfony/* и прочих сторонних зависимостей

## 4. План реализации (Implementation Plan)
1. [x] Создать ветку `task/update-prikotov-coding-standard` от актуального `main`
2. [x] Проверить код на нарушения `ReservedLayerSegment` (grep — чисто)
3. [x] Поднять constraint, `composer update prikotov/coding-standard --with-dependencies`
4. [x] Полный прогон `make check`
5. [x] Коммит `chore(deps)` по явному подтверждению пользователя
6. [x] Push ветки токеном бота (безопасный HTTPS-рецепт), PR #378 от `prikotov-agent[bot]`, метка `pi`

## 5. Критерии приёмки (Definition of Done)
- [x] `composer outdated --direct` не показывает обновлений для пакетов `prikotov/*`
- [x] `make check` зелёный: 1517 тестов, 4052 assertions, 2 skipped (окружение-зависимые)
- [x] `composer validate` — OK, security advisories — нет

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate
composer outdated --direct
make check
```

## 7. Риски и зависимости (Risks and Dependencies)
- Версия 0.32.0 добавляет ломающее правило `ReservedLayerSegment` — для проектов-потребителей с вложенными именами слоёв обновление потребует правок кода; в этом проекте нарушений нет (проверено grep'ом)
- Локальный `depfile.yaml` импортирует эталонный из вендора — обновлённые коллекторы подхватились автоматически

## 8. Источники (Sources)
- Коммит `1ce89eb` prikotov/coding-standard: feat(rules) forbid reserved layer names as nested namespace segments

## 9. Комментарий (Comments)
Прямая команда пользователя: «Обнови все зависимости, prikotov/* до самых последних. И сделай необходимые действия после обновления». Коммит и PR выполнены по явному подтверждению.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-03 05:13:05 (1788412385) | Бэкендер Тони (pi) | Создание задачи |
