---
type: chore
created: 2026-09-21 22:50:00 (1790005800)
due: 
started: 2026-09-21 22:50:30 (1790005830)
completed: 
cancelled: 
value: V2
complexity: C1
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Бэкендер Тони (pi)
assignee: Бэкендер Тони (pi)
branch: task/update-prikotov-packages
pr: https://github.com/prikotov/task-orchestrator/pull/404
status: review
---

# TASK-chore-update-prikotov-packages: Обновление пакетов prikotov/* до последних версий

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Прямые зависимости `prikotov/*` отстают от последних стабильных релизов: `prikotov/git-workflow` 0.3.3 при доступном v0.4.0 (ограничение `^0.3.0` не пускает minor-обновление), `prikotov/todo-md` 0.0.12 при доступном v0.0.14. `prikotov/coding-standard` 0.33.0 уже последний.
- v0.4.0 `git-workflow` чинит замеченную в PR #403 проблему: вместо жёсткого `master` базовая ветка определяется как основная через `origin/HEAD`, добавлена секция «Обязательные проверки».

### Варианты или путь решения (Solution Sketch)
- Поднять ограничение `prikotov/git-workflow` до `^0.4.0`, обновить lock-файл целевым `composer update` только по пакетам `prikotov/*`, пересинхронизировать поставляемые пакетами доки (`docs/git-workflow/` через `git-workflow-init --force`, `docs/todo-md/` вручную по vendor) и устранить только несовместимости, выявленные проверками.

### Ожидаемый результат (Expected Result)
- Все прямые зависимости `prikotov/*` на последних стабильных версиях; поставляемые доки синхронны вендору; полный набор проверок зелёный.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **User Story:** Как разработчик проекта, я хочу использовать последние стабильные версии прямых пакетов `prikotov/*`, чтобы применять актуальные правила workflow и валидации задач без нарушения работоспособности проекта.

### Цель по SMART (Goal)
- Обновить `prikotov/git-workflow` 0.3.3 → v0.4.0 (с бампом ограничения до `^0.4.0`) и `prikotov/todo-md` 0.0.12 → v0.0.14; убедиться, что `prikotov/coding-standard` 0.33.0 — последняя стабильная; пересинхронизировать `docs/git-workflow/` и `docs/todo-md/`; подтвердить совместимость полным `make check`.

## 2. Контекст и Границы (Context and Scope)
* **Где делаем:** `composer.json`, `composer.lock`, `docs/git-workflow/`, `docs/todo-md/`, а также только файлы, требующие корректировки из-за несовместимости обновлений.
* **Текущее состояние:** `prikotov/coding-standard` 0.33.0 (последняя), `prikotov/git-workflow` 0.3.3, `prikotov/todo-md` 0.0.12; `docs/git-workflow/` и `docs/todo-md/` — копии доков пакетов (у `git-workflow` — через `git-workflow-init`, у `todo-md` — вручную).
* **Границы (Out of Scope):** не обновлять зависимости других вендоров; не выполнять посторонний рефакторинг; не менять публичные контракты или архитектуру.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] Ограничение `prikotov/git-workflow` в `composer.json` изменено на `^0.4.0`.
- [x] `composer.lock` обновлён целевым `composer update` по `prikotov/git-workflow` и `prikotov/todo-md`; посторонние зависимости не обновлены.
- [x] `docs/git-workflow/` пересинхронизирован с v0.4.0 (`git-workflow-init --force`), `.github/workflows/secret-scan-pr-content.yml` сверен с шаблоном пакета.
- [x] `docs/todo-md/` пересинхронизирован с v0.0.14.
- [x] Устранены только несовместимости, непосредственно вызванные обновлением.
### ⚫ Won't Have (Не будем делать)
- Не обновлять пакеты, не принадлежащие `prikotov/*`.
- Не делать рефакторинг или изменение поведения, не вызванные обновлением.

## 4. План реализации (Implementation Plan)
1. [x] Проверить фактические и доступные версии прямых пакетов `prikotov/*`.
2. [x] Бампнуть ограничение `git-workflow` до `^0.4.0`, выполнить `composer update prikotov/git-workflow prikotov/todo-md`.
3. [x] Пересинхронизировать `docs/git-workflow/` (`git-workflow-init --force`) и `docs/todo-md/` (по vendor).
4. [x] Прогнать проверки, устранить несовместимости.
5. [ ] Оформить PR по регламенту.

## 5. Критерии приёмки (Definition of Done)
- [x] `composer.json`: `prikotov/git-workflow: ^0.4.0`; lock содержит `v0.4.0` и `v0.0.14`.
- [x] `composer validate` проходит; `symfony/yaml` и прочие посторонние зависимости не обновлены.
- [x] `docs/git-workflow/` и `docs/todo-md/` соответствуют вендору новых версий.
- [x] `make check` завершается успешно.
- [ ] Задача переведена в `done`, PR создан по регламенту.

## 6. Самопроверка (Verification)
```bash
composer validate
php vendor/bin/todo-md validate todo/TASK-chore-update-prikotov-packages.todo.md
make check
```

## 7. Риски и зависимости (Risks и Dependencies)
- `git-workflow` v0.4.0 существенно перерабатывает доки веток/релизов — возможно изменение формулировок, на которые ссылаются локальные документы (проверит md-links).
- `todo-md` v0.0.14 меняет инструкции задач — валидатор может стать строже к существующим файлам (унаследованные предупреждения не чиним в рамках этой задачи).
- Обновление затрагивает `composer.lock` — CI обязан пройти полный набор проверок.

## 8. Источники (Sources)
- `composer.json` и `composer.lock` текущей ветки.
- [github.com/prikotov/git-workflow](https://github.com/prikotov/git-workflow) — теги v0.4.0, changelog.
- [github.com/prikotov/todo-md](https://github.com/prikotov/todo-md) — теги v0.0.13/v0.0.14, changelog.

## 9. Комментарии (Comments)

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-21 22:50:00 (1790005800) | Бэкендер Тони (pi) | Создание задачи |
| 2026-09-21 22:51:00 (1790005860) | Бэкендер Тони (pi) | Старт задачи, заполнение постановки |
| 2026-09-21 23:00:00 (1790006400) | Бэкендер Тони (pi) | Пакеты обновлены (git-workflow v0.4.0, todo-md v0.0.14), доки пересинхронизированы, локальный pre-commit хук обновлён; `make check` зелёный |
