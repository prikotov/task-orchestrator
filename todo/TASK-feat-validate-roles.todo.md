---
type: feat
created: 2026-05-22
value: V3
complexity: C2
priority: P2
depends_on:
epic:
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: todo
---

# TASK-feat-validate-roles: Валидатор файлов ролей AI-агентов

## 0. Простое описание (Human Brief)
Создать валидатор `validate-roles` для проверки файлов описания ролей AI-агентов в `docs/agents/roles/`.

### Проблема простыми словами (Problem)
Нет автоматической проверки корректности файлов ролей — ошибки в front matter, отсутствующие обязательные поля и секции не выявляются до ручного просмотра.

### Варианты или путь решения (Solution Sketch)
Написать PHP-скрипт `bin/validate-roles`, аналогичный по стилю `vendor/prikotov/coding-standard/bin/validate-docs.php`, который проверяет front matter поля и обязательные секции тела файла.

### Ожидаемый результат (Expected Result)
Команда `make validate-roles` проверяет все файлы в `docs/agents/roles/team/` (и других подкаталогах), возвращает 0 при успехе, 1 при ошибках.

## 1. Concept and Goal (Концепция и Цель)
### Story
Как тимлид, я хочу автоматически валидировать файлы ролей, чтобы гарантировать соответствие формату ROLE-CREATION.md.

### Goal
PHP CLI-скрипт `bin/validate-roles` с интеграцией в `make validate-roles` и `make check`.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `bin/validate-roles` (новый файл), `Makefile` (добавить target)
*   **Исходные данные:** [docs/agents/roles/ROLE-CREATION.md](../docs/agents/roles/ROLE-CREATION.md) — спецификация формата
*   **Границы:** не валидируем `docs/agents/roles/references/` (справочные материалы, не роли)

## 3. Requirements (MoSCoW)

### 🔴 Must Have
- [ ] Скрипт `bin/validate-roles` проверяет все `*.md` в `docs/agents/roles/team/` (и других подкаталогах, кроме `references/`)
- [ ] **Front matter — обязательные поля:** `agent`, `role`, `name`, `title`, `description`
- [ ] **Front matter — `agent` совпадает с именем файла** без `.{locale}.md`
- [ ] **Front matter — `role`** в формате `snake_case`, латиница, без имени персонажа
- [ ] **Front matter — `personality`**: если есть, проверить что содержит валидные подсекции (`disc`, `big_five`, `adizes`, `belbin`, `jung`)
- [ ] **Front matter — `skills`**: если есть, проверить что это массив строк
- [ ] **Имя файла**: соответствует паттерну `{agent}.{locale}.md` (locale = 2-3 буквы)
- [ ] **Тело файла**: начинается с H1 заголовка
- [ ] **Makefile**: target `validate-roles` + добавить в `check`
- [ ] Все существующие роли проходят валидацию

### 🟡 Should Have
- [ ] Поддержка `--path` для указания произвольного каталога
- [ ] Цветной вывод ошибок (красный) как в `validate-docs.php`

### ⚫ Won't Have (Не будем делать)
- Валидация содержимого справочников (`references/`)
- Проверка ссылок в теле файла (это делает `validate-md-links`)
- Проверка `personality` на корректность значений (только наличие подсекций)

## 4. Implementation Plan
*Заполняется исполнителем.*

## 5. Definition of Done
- [ ] `php bin/validate-roles` проходит на текущих 10 ролях
- [ ] `make validate-roles` работает
- [ ] `make check` включает `validate-roles`
- [ ] `make check` зелёный
- [ ] PHPUnit + Psalm без ошибок

## 6. Verification
```bash
php bin/validate-roles
make validate-roles
make check
```

## 7. Risks and Dependencies
- Некоторые роли могут иметь не все поля personality — нужно проверить перед реализацией
- `expertise` поле есть не во всех ролях — не делаем обязательным

## 8. Sources
- [docs/agents/roles/ROLE-CREATION.md](../docs/agents/roles/ROLE-CREATION.md) — спецификация формата
- [vendor/prikotov/coding-standard/bin/validate-docs.php](../vendor/prikotov/coding-standard/bin/validate-docs.php) — референс-стиль
- [docs/agents/roles/team/](../docs/agents/roles/team/) — существующие роли (10 файлов)

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-22 | Тимлид (Алекс) | Создание задачи |
