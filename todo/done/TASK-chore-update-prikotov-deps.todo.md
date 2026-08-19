---
# Metadata (Метаданные)
type: chore
created: 2026-08-16
due:
started: 2026-08-16
completed: 2026-08-16 13:59:57 (1786888797)
cancelled:
value: V2
complexity: C1
priority: P2
cost_plan:
cost_fact:
depends_on:
epic:
author: Бэкендер Левша (pi)
assignee: Бэкендер Левша (pi)
branch: task/update-prikotov-deps
pr: https://github.com/prikotov/task-orchestrator/pull/351
status: done
---

# TASK-chore-update-prikotov-deps: Обновление зависимостей prikotov/* до последних версий

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Проект использует устаревшие версии собственных библиотек `prikotov/coding-standard` (0.29.1) и `prikotov/todo-md` (0.0.7). В свежих версиях исправлены ошибки стандарта кодирования и изменён формат задач (эпики `EPIC-*.todo.md`, единый бинарник `todo-md`, валидация Human Brief) — локальная доска задач и проверки не соответствуют актуальному контракту пакетов.

### Варианты или путь решения (Solution Sketch)
1. `composer update prikotov/*` с транзитивными зависимостями.
2. Адаптировать вызовы инструментов в `Makefile` к новому CLI (`todo-md validate` вместо `bin/todo-md-validate`).
3. Запустить иниты обновившихся библиотек (`todo-md init`, `coding-standard-init`), мигрировать активные эпики на новый формат и завести конфиг `.todo-md.php`.

### Ожидаемый результат (Expected Result)
Все зависимости `prikotov/*` на последних версиях; `make check` проходит полностью (включая `validate-todo` на новом формате); активные эпики валидны; ссылки на эпики в документации не битые.

## 1. Концепция и Цель (Concept and Goal)
### История (User Story или Job Story)
> **User Story:** Как бэкендер, я хочу, чтобы библиотеки `prikotov/*` стояли на последних версиях, чтобы проверки стиля, валидация задач и документация соответствовали актуальному контракту этих пакетов.

### Цель по SMART (Goal)
Обновить `prikotov/coding-standard` до 0.29.3 и `prikotov/todo-md` до 0.0.10, адаптировать `Makefile` и формат активных эпиков к новому CLI и шаблону, чтобы `make check` завершался успешно. Срок: 2026-08-16.

## 2. Контекст и Границы (Context and Scope)
* **In Scope (Что делаем):**
    * `composer update prikotov/* --with-all-dependencies` (обновление `composer.lock`)
    * Правка цели `validate-todo` в `Makefile`: `vendor/bin/todo-md validate` вместо удалённого `bin/todo-md-validate`
    * Запуск `todo-md init` (регенерация `docs/todo-md/`) и `coding-standard-init --no-exceptions` (добавление новых файлов конвенций)
    * Конфиг `.todo-md.php` с ролями и агентами проекта
    * Миграция двух активных эпиков на формат 0.0.10 (переименование в `EPIC-*.todo.md`, Human Brief, поле `pr`, статус `todo`, формат author/assignee «Роль (агент)»)
    * Обновление ссылок на переименованные эпики в `docs/research/*` и отчётах агентов
    * Чистая регенерация `docs/conventions/` из пакета 0.29.3 (`rm -rf` + init: устраняет расслоение копий эпох 0.0.1/до-0.17.0/0.29.3 и устаревший пример с `resource:`/`exclude:`)
    * Починка ссылок на конвенции в трекаемых документах: замена underscore-путей на дефис-эквиваленты, исправление относительных префиксов в архивах (72 → 0 битых)
    * Регенерация `phpmd.baseline.xml` (3 ранее существовавших нарушения длины методов/классов)
* **Out of Scope (Чего НЕ делаем):**
    * Миграция архивных задач `todo/done/`, `todo/backlog/`, `todo/cancelled/` на новый формат
    * Обновление остальных зависимостей (symfony/* и пр.)
    * Рефакторинг длинных методов `run()` — отдельная задача техдолга (выполнена: [TASK-techdebt-phpmd-lengths](TASK-techdebt-phpmd-lengths.todo.md))

## 3. Требования, MoSCoW (Requirements)

### 🔴 Блокирующие требования (Must Have)
- [x] Зависимости `prikotov/*` обновлены до последних версий, `composer.lock` зафиксирован
- [x] Цель `validate-todo` работает с новым CLI `todo-md validate`
- [x] Активные эпики проходят `vendor/bin/todo-md validate` без ошибок и предупреждений
- [x] `make check` завершается успешно

### 🟡 Важные требования (Should Have)
- [x] Иниты обновившихся библиотек выполнены (`docs/todo-md/` регенерирован, новые файлы конвенций добавлены)
- [x] Конфиг `.todo-md.php` описывает роли и агенты проекта
- [x] Ссылки на переименованные эпики обновлены во всех документах

### 🟢 Желательно (Could Have)
- [ ] Массовая миграция архивных задач на формат 0.0.10 (отложено)

### ⚫ Не в этот раз (Won't Have)
- [ ] Обновление symfony/* и прочих сторонних зависимостей
- [ ] Изменение правил phpmd.xml (только baseline)

## 4. План реализации (Implementation Plan)
- [x] Создать ветку `task/update-prikotov-deps` от актуального `main`
- [x] `composer update prikotov/* --with-all-dependencies`
- [x] Адаптировать `Makefile` (validate-todo) под новый CLI
- [x] `todo-md init` + конфиг `.todo-md.php`
- [x] `coding-standard-init --no-exceptions`
- [x] Миграция эпиков `EPIC-research-orchestration-articles` и `EPIC-research-approaches-comparison`
- [x] Обновление ссылок в `docs/research/*`
- [x] Чистая регенерация `docs/conventions/` из пакета 0.29.3
- [x] Починка всех битых ссылок на конвенции в трекаемых документах (72 → 0)
- [x] Регенерация `phpmd.baseline.xml`
- [x] Полный прогон `make check`

## 5. Критерии приёмки (Definition of Done)
- [x] `composer outdated prikotov/*` не показывает доступных обновлений
- [x] `make check` зелёный
- [x] Документация согласована (нет упоминаний `todo-md-validate` вне архивных записей)

## 6. Самопроверка (Verification)
- Запуск: `vendor/bin/phpunit` — все тесты зелёные.
- Запуск: `vendor/bin/psalm` — без ошибок.
- Запуск: `make check` — все цели (phpstan, deptrac, psalm, phpmd, phpcs, md-links, validate-todo, validate-roles, validate-language, tests) успешны.
- Запуск: `vendor/bin/todo-md validate todo/EPIC-research-orchestration-articles.todo.md todo/EPIC-research-approaches-comparison.todo.md` — 0 ошибок, 0 предупреждений.
- Проверка скриптом: относительные ссылки на `docs/conventions/*` из `docs/`, `todo/`, `AGENTS.md`, `README.md` — 0 битых (до работы — 72).

## 7. Риски и зависимости (Risks and Dependencies)
- Архивные задачи (`done/`, `backlog/`, `cancelled/`) остаются в старом формате и не валидируются целью `validate-todo`
- После регенерации `docs/conventions/modules/configuration.md` и `configuration/configuration.md` стали пакетными (универсальными): проектная специфика модульной конфигурации полностью покрыта трекаемым ADR-012 (включая PHAR-безопасность); локальные правки удалены как устаревшие и дублирующие

## 8. Источники (Sources)
- CHANGELOG `prikotov/todo-md` — версии 0.0.8–0.0.10
- [CONFIG.md](../../docs/todo-md/reference/CONFIG.md) — конфигурация `.todo-md.php`

## 9. Комментарии (Comments)
Пользователь явно запросил: обновить все `prikotov/*`, переименовать эпики, настроить валидацию текстов задач, выполнить иниты обновившихся библиотек.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-16 | Бэкендер Левша (pi) | Создание задачи. Обновление зависимостей, адаптация Makefile, иниты, миграция эпиков. |
| 2026-08-16 | Бэкендер Левша (pi) | Дополнено по решению пользователя: чистая регенерация `docs/conventions/` из пакета 0.29.3 (удалены слои копий 0.0.1/до-0.17.0 и устаревший пример с `resource:`), починены все битые ссылки на конвенции (72 → 0). |
| 2026-08-16 | Бэкендер Левша (pi) | Работа завершена, PR #351 создан; статус → review. |
