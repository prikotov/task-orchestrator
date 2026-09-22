---
type: chore
created: 2026-09-22 01:30:54 (1790040654)
due: 
started: 2026-09-22 01:30:54 (1790040654)
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
branch: task/update-git-workflow-0-4-1
pr: https://github.com/prikotov/task-orchestrator/pull/405
status: review
---

# TASK-chore-update-git-workflow-0-4-1: Подтяжка prikotov/git-workflow v0.4.1 и честная проверка локальных доков

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Вышел `prikotov/git-workflow` v0.4.1: словарь терминов переведён на чистый Markdown (один термин — один файл), ложные `broken-anchor` валидатора ссылок устранены в источнике.
- В `.md-links.php` (PR #404) остались исключения `docs/git-workflow/` и `docs/todo-md/` — обходной костыль, больше не нужный.

### Варианты или путь решения (Solution Sketch)
- Обновить зависимость до v0.4.1, пересинхронизировать локальные копии доков, откатить исключения в `.md-links.php` к исходному виду.

### Ожидаемый результат (Expected Result)
- `prikotov/git-workflow` v0.4.1 в lock; локальные копии доков пакетов снова честно проверяются валидатором; `make check` зелёный.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **User Story:** Как разработчик проекта, я хочу зависимость на свежем patch-релизе git-workflow и честную (без исключений) проверку локальных копий доков пакетов, чтобы ловить битые ссылки рано и без обходных путей.

### Цель по SMART (Goal)
- `composer update prikotov/git-workflow` → v0.4.1 (единственное изменение в lock); `docs/git-workflow/` пересинхронизирован (`glossary/` 27 файлов, устаревший `glossary.md` удалён); `.md-links.php` без исключений `docs/git-workflow/` и `docs/todo-md/`; `make check` зелёный.

## 2. Контекст и Границы (Context and Scope)
* **Где делаем:** `composer.lock`, `.md-links.php`; локально (не в git) — `docs/git-workflow/`.
* **Границы (Out of Scope):** не обновлять прочие зависимости; не менять код и конфигурации приложения.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] `prikotov/git-workflow` v0.4.1 в `composer.lock` (посторонние пакеты не тронуты).
- [x] Локальные `docs/git-workflow/` синхронны вендору v0.4.1; старый `glossary.md` и осиротевший `releases/templates/` удалены.
- [x] `.md-links.php`: исключения `docs/git-workflow/` и `docs/todo-md/` откатлены к исходному набору PR-мержа #404-предшественника.
- [x] `make check` — зелёный.
### ⚫ Won't Have (Не будем делать)
- Обновление других `prikotov/*` или сторонних пакетов.

## 4. План реализации (Implementation Plan)
1. [x] `composer update prikotov/git-workflow`; проверка точечности lock.
2. [x] `git-workflow-init --force`; удаление устаревших локальных артефактов.
3. [x] Валидация локальных копий доков без исключений; откат `.md-links.php`.
4. [x] `make check`.
5. [ ] PR по регламенту.

## 5. Критерии приёмки (Definition of Done)
- [x] Lock: единственное изменение — `prikotov/git-workflow` v0.4.0 → v0.4.1.
- [x] `validate-md-links docs/git-workflow/` и `docs/todo-md/` — зелёные без исключений.
- [x] `make check` зелёный (1572 теста, 2 skipped).
- [ ] Задача в `done`, PR создан.

## 6. Самопроверка (Verification)
```bash
composer validate
php vendor/bin/todo-md validate todo/TASK-chore-update-git-workflow-0-4-1.todo.md
make check
```

## 7. Риски и зависимости (Risks и Dependencies)
- Зависит от релиза `prikotov/git-workflow` v0.4.1 (опубликован: тег + GitHub Release).

## 8. Источники (Sources)
- [Релиз v0.4.1](https://github.com/prikotov/git-workflow/releases/tag/v0.4.1) и [план релиза](https://github.com/prikotov/git-workflow/blob/v0.4.1/docs/releases/v0.4.1/release-plan.md).

## 9. Комментарии (Comments)
- Исключения в `.md-links.php` были временным обходом ложных срабатываний на HTML-якорях глоссария (см. PR #404); v0.4.1 закрывает причину — чистый Markdown.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-22 01:30:54 (1790040654) | Бэкендер Тони (pi) | Создание задачи |
| 2026-09-22 01:35:00 (1790040900) | Бэкендер Тони (pi) | Старт задачи, заполнение постановки |
| 2026-09-22 01:45:00 (1790041500) | Бэкендер Тони (pi) | Обновление до v0.4.1, ре-синк доков, откат исключений, make check зелёный |
