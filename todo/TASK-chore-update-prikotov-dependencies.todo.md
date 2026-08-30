---
type: chore
created: 2026-08-30 03:13:26 (1788059606)
due: 
started: 2026-08-30 03:14:07 (1788059647)
completed: 
cancelled: 
value: V2
complexity: C1
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Бэкендер Тони (codex)
assignee: Бэкендер Тони (codex)
branch: task/update-prikotov-dependencies
pr: 
status: in_progress
---

# TASK-chore-update-prikotov-dependencies: Обновление прямых зависимостей prikotov

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В проекте используется устаревшая версия набора правил качества кода; разработчики и автоматические проверки не получают исправления и улучшения последнего стабильного выпуска.

### Варианты или путь решения (Solution Sketch)
- Обновить ограничение версии `prikotov/coding-standard`, пересобрать lock-файл (зафиксированный набор зависимостей) и устранить только несовместимости, обнаруженные проверками.

### Ожидаемый результат (Expected Result)
- Все прямые зависимости `prikotov/*` используют последние стабильные версии, а проект проходит полный набор проверок качества.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **User Story:** Как разработчик проекта, я хочу использовать последние стабильные версии прямых пакетов `prikotov/*`, чтобы применять актуальные правила качества без нарушения работоспособности проекта.

### Цель по SMART (Goal)
- В текущей итерации обновить `prikotov/coding-standard` с `v0.30.0` до `v0.31.0`, сохранить последние стабильные версии остальных прямых пакетов `prikotov/*` и подтвердить совместимость командами `make check`, PHPUnit и Psalm.

## 2. Контекст и Границы (Context and Scope)
* **Где делаем:** `composer.json`, `composer.lock`, а также только файлы, которые требуют корректировки из-за несовместимости обновлённого `prikotov/coding-standard`.
* **Текущее поведение:** прямые зависимости зафиксированы как `prikotov/coding-standard` `v0.30.0`, `prikotov/git-workflow` `v0.3.2` и `prikotov/todo-md` `v0.0.12`.
* **Границы (Out of Scope):** не обновлять зависимости других вендоров, не выполнять посторонний рефакторинг, не изменять публичные контракты или архитектуру.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] Ограничение `prikotov/coding-standard` в `composer.json` изменено на `^0.31.0`.
- [x] `composer.lock` обновлён согласованно с `composer.json`.
- [x] `symfony/yaml` сохранён на исходной версии `v8.0.12`; посторонние зависимости в lock-файле не обновлены.
- [x] Проверено, что `prikotov/git-workflow` остаётся на `v0.3.2`, а `prikotov/todo-md` — на `v0.0.12`, если это их последние стабильные версии.
- [x] Устранены только несовместимости, непосредственно вызванные обновлением зависимости.
### ⚫ Won't Have (Не будем делать)
- Не обновлять пакеты, не принадлежащие `prikotov/*`.
- Не делать рефакторинг или изменение поведения, не вызванные обновлением зависимостей.
- Не создавать commit (коммит), push (отправку ветки) или PR (запрос на слияние).

## 4. План реализации (Implementation Plan)
1. [x] Проверить фактические версии и доступные стабильные обновления прямых пакетов `prikotov/*`.
2. [x] Обновить ограничение `prikotov/coding-standard` и lock-файл через Composer.
3. [x] Устранить несовместимости, выявленные зависимостями и проверками, без расширения объёма задачи.
4. [x] Выполнить валидацию задачи, `make check`, PHPUnit и Psalm.

## 5. Критерии приёмки (Definition of Done)
- [x] В `composer.json` указано `prikotov/coding-standard: ^0.31.0`.
- [x] В `composer.lock` зафиксирован `prikotov/coding-standard` `v0.31.0`; lock-файл валиден.
- [x] В `composer.lock` сохранён `symfony/yaml` `v8.0.12`.
- [x] Версии прямых `prikotov/git-workflow` и `prikotov/todo-md` сверены с последними стабильными релизами и не изменены без необходимости.
- [x] `make check` завершается успешно.
- [x] `vendor/bin/phpunit` и `vendor/bin/psalm` завершаются успешно.

## 6. Самопроверка (Verification)
```bash
composer validate
git diff --check
php vendor/bin/todo-md validate todo/TASK-chore-update-prikotov-dependencies.todo.md
make check
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Риски и зависимости (Risks and Dependencies)
- Новая версия `prikotov/coding-standard` может усилить правила статического анализа или форматирования и потребовать точечных исправлений существующего кода.
- Доступность последних стабильных версий определяется репозиторием Packagist и разрешением зависимостей Composer.

## 8. Источники (Sources)
- `composer.json` и `composer.lock` текущей ветки.
- [Packagist: prikotov/coding-standard](https://packagist.org/packages/prikotov/coding-standard)
- [Packagist: prikotov/git-workflow](https://packagist.org/packages/prikotov/git-workflow)
- [Packagist: prikotov/todo-md](https://packagist.org/packages/prikotov/todo-md)

## 9. Комментарии (Comments)

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-30 03:13:26 (1788059606) | Бэкендер Тони (codex) | Создание задачи |
| 2026-08-30 03:13:26 (1788059606) | Бэкендер Тони (codex) | Заполнены постановка, границы, критерии приёмки и план реализации |
| 2026-08-30 10:23:37 (1788060217) | Бэкендер Тони (codex) | Обновлена зависимость и подтверждено прохождение проверок |
| 2026-08-30 10:43:31 (1788061411) | Бэкендер Тони (codex) | По самопроверке `symfony/yaml` возвращён на `v8.0.12`; подтверждено прохождение `composer validate`, `git diff --check` и `make check` |
