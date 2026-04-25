---
# Metadata (Метаданные)
type: chore
created: 2026-04-23
value: V2
complexity: C1
priority: P0
depends_on:
epic: EPIC-feat-standalone-cli
author: Тимлид (Алекс)
assignee: Бэкендер (Тони)
branch: task/chore-composer-library-type
pr:
status: in_progress
---

# TASK-chore-composer-library-type: Изменить type на library и добавить bin в composer.json

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как PHP-разработчик, я хочу установить task-orchestrator через `composer require`, чтобы использовать его как CLI-утилиту в своём проекте.

### Goal (Цель по SMART)
Изменить `composer.json`: `type: "project"` → `"library"`, добавить `"bin": ["bin/task-orchestrator"]`. После этого пакет можно зарегистрировать на Packagist и устанавливать через `composer require`.

## 2. Context and Scope (Контекст и Границы)
* **Где делаем:** `composer.json`, `bin/`
* **Текущее поведение:** `type: "project"` — Packagist не индексирует, `bin` не объявлен
* **Границы:**
  - Не регистрируем на Packagist (отдельная задача)
  - Не создаём Phar (отдельная задача)

## 3. Requirements (Требования)
### 🔴 Must Have
- [ ] `type` изменён на `"library"` в composer.json
- [ ] Добавлен `"bin": ["bin/task-orchestrator"]` в composer.json
- [ ] Файл `bin/task-orchestrator` создан (точка входа: PHP shebang + require autoload + Console Application)
- [ ] `vendor/bin/task-orchestrator --version` работает после `composer install`

### 🟡 Should Have
- [ ] Проверка: `composer validate` проходит без ошибок

## 4. Implementation Plan
1. Создать `bin/task-orchestrator` — точку входа (shebang + autoload + Application)
2. Обновить `composer.json`: `type` + `bin`
3. Проверить локально: `composer install` → `vendor/bin/task-orchestrator --version`

## 5. Definition of Done
- [ ] `composer validate` без ошибок
- [ ] `vendor/bin/task-orchestrator --version` выводит версию
- [ ] PHPUnit и Psalm зелёные

## 6. Verification
```bash
composer validate
vendor/bin/task-orchestrator --version
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks
- Текущая структура `apps/console/` может не совпадать с ожиданиями от `bin/` — нужно проверить совместимость

## 8. Sources
- [RFC: cli-distribution-rfc.md](../docs/research/cli-distribution-rfc.md) — Решение 2
- [Composer vendor binaries](https://getcomposer.org/doc/articles/vendor-binaries.md)

## Инструкции для сабагента

**Твоя роль:** docs/agents/roles/team/backend_developer_tony.ru.md
**Ветка:** task/chore-composer-library-type (уже создана и активна)
**PR:** уже создан (draft) из task/chore-composer-library-type в epic/feat-standalone-cli — [PR #62](https://github.com/prikotov/task-orchestrator/pull/62)

### Порядок действий
1. Переключись в ветку `task/chore-composer-library-type`: `git checkout task/chore-composer-library-type`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](../docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `vendor/bin/phpunit` и `vendor/bin/psalm`.
6. Сделай `git push`.
7. Переведи PR из draft в ready: `gh pr ready <PR_NUMBER>`.

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-23 | Тимлид (Алекс) | Создание задачи из RFC brainstorm |
