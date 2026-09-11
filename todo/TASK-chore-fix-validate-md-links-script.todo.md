---
type: chore
created: 2026-09-11 12:05:00 (1789128300)
due: 
started: 2026-09-11 12:06:00 (1789128360)
completed: 
cancelled: 
value: V2
complexity: C1
priority: P2
cost_plan: 
cost_fact: 10000
depends_on: 
epic: 
author: Тимлид (Алекс)
assignee: Бэкендер (Тони)
branch: task/research-t3-code
pr: 
status: in_progress
---

# TASK-chore-fix-validate-md-links-script: Починить битый composer-скрипт validate-md-links (CR-4 из ревью TASK-research-t3-code)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Composer-скрипт `validate-md-links` в `composer.json` (строка 68) указывает на несуществующий файл `vendor/prikotov/coding-standard/bin/validate-md-links.php`.
- Фактический исполняемый файл в пакете — `vendor/prikotov/coding-standard/bin/validate-md-links` (без расширения `.php`), соседний `validate-docs.php` — с расширением.
- Из-за этого падают `composer validate-md-links` и составной composer-скрипт, включающий `@validate-md-links` (выявлено при ревью TASK-research-t3-code, замечание CR-4 Ревьювера Бэка Пуаро; воспроизведено тимлидом: `Could not open input file: ...validate-md-links.php`).

### Варианты или путь решения (Solution Sketch)
- Исправить путь в `composer.json` на рабочий исполняемый файл пакета `prikotov/coding-standard` (минимальная правка одной строки).
- Выбор формы вызова (`php <file>` vs прямой вызов) — по содержимому самого файла (shebang/расширение); критерий — `composer validate-md-links` завершается с кодом 0.

### Ожидаемый результат (Expected Result)
- `composer validate-md-links` выполняется успешно (exit 0) и валидирует Markdown-ссылки репозитория.
- Составные composer-скрипты, использующие `@validate-md-links`, не падают на этом шаге.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **Job Story:** Когда я (агент или разработчик) запускаю проверки качества документации через composer, я хочу, чтобы скрипт `validate-md-links` работал из коробки, чтобы проверки ссылок не падали из-за неверного пути в `composer.json`.

### Цель по SMART (Goal)
Исправить путь скрипта `validate-md-links` в `composer.json` так, чтобы команда `composer validate-md-links` завершалась с кодом 0 на актуальном дереве репозитория. Изменение — не более одной строки конфигурации.

## 2. Контекст и Границы (Context and Scope)

- **Где делаем:** `composer.json` (секция `scripts`, строка `validate-md-links`).
- **Текущее поведение:** `"validate-md-links": "php vendor/prikotov/coding-standard/bin/validate-md-links.php"` — файл не существует, команда падает с `Could not open input file`.
- **Факты (проверены):** `vendor/prikotov/coding-standard/bin/` содержит `validate-md-links` (без `.php`), `validate-docs.php` (с `.php`), `validate-language` (без `.php`). Makefile-цель `md-links` вызывает `php vendor/prikotov/coding-standard/bin/validate-md-links` напрямую и работает — может служить образцом формы вызова.
- **Границы (Out of Scope):** рефакторинг Makefile (его цель `md-links` уже корректна), обновление зависимостей, изменения других composer-скриптов, правки самого пакета `prikotov/coding-standard`.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [x] `composer.json`: путь скрипта `validate-md-links` указывает на существующий исполняемый файл пакета
- [x] `composer validate-md-links` завершается с кодом 0 на актуальном дереве репозитория
- [x] Остальные composer-скрипты не затронуты (диф — одна строка)

### 🟡 Желательно (Should Have)
- [x] Форма вызова консистентна с соседним `validate-docs.php` либо обоснована отличий (например, shebang-исполняемость)

### ⚫ Не будем делать (Won't Have)
- Изменения Makefile, других скриптов composer.json, зависимостей, кода пакета

## 4. План реализации (Implementation Plan)
1. [x] Изучить содержимое `vendor/prikotov/coding-standard/bin/validate-md-links` (первая строка, синтаксис) и выбрать форму вызова
2. [x] Исправить одну строку в `composer.json`
3. [x] Проверить: `composer validate-md-links` — exit 0; `composer validate-docs` — не сломан; `make md-links` — работает
4. [x] Отметить чекбоксы, добавить запись в «Историю изменений»

## 5. Критерии приёмки (Definition of Done)
- [x] `composer validate-md-links` — успешно (exit 0)
- [x] Диф ограничен одной строкой `composer.json` (плюс файл самой задачи)
- [x] `php vendor/bin/todo-md validate todo/TASK-chore-fix-validate-md-links-script.todo.md` — 0 ошибок

## 6. Самопроверка (Verification)
```bash
composer validate-md-links && echo OK
php vendor/bin/todo-md validate todo/TASK-chore-fix-validate-md-links-script.todo.md
git diff --stat composer.json
```

## 7. Риски и зависимости (Risks and Dependencies)
- Путь в vendor зависит от версии пакета `prikotov/coding-standard` — при обновлении пакета форма вызова может снова измениться (принято; фиксируем под текущую установленную версию).
- Задача выполняется в ветке `task/research-t3-code` совместно с TASK-research-t3-code по явному решению пользователя (одна ветка, отдельные коммиты, один PR с навигацией).

## 8. Источники (Sources)

🔗 **Внутренние источники:**

- `todo/done/TASK-research-t3-code.todo.md` — первоисточник замечания (CR-4 ревью Пуаро)
- `composer.json` — секция `scripts`, строки 67–72
- `Makefile` — цель `md-links` (рабочий образец вызова)

## 9. Комментарии (Комментарии)

- Дефект обнаружен Ревьювером Бэка Пуаро при экспертизе TASK-research-t3-code и воспроизведён тимлидом до постановки.
- Отчёт аналитика уже ссылается на обходной путь (прямой вызов `vendor/bin/validate-md-links`) — после фикса обходное описание в отчёте остаётся исторически точным, править отчёт не нужно.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-11 12:05:00 (1789128300) | Тимлид (Алекс) | Создание задачи по итогам ревью TASK-research-t3-code (CR-4) |
| 2026-09-11 | Бэкендер (Тони) | Исправлен путь `validate-md-links`; `composer validate-md-links`, `composer validate-docs` и `make md-links` завершены успешно. |
