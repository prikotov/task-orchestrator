---
type: chore
created: 2026-11-27 14:26:32 (1795789592)
due: 
started: 2026-11-27 14:26:54 (1795789614)
completed: 
cancelled: 
value: V2
complexity: C2
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Алекс (pi)
assignee: Бэкендер Тони (pi)
consultants: []
reviewer: Ревьювер Бэка Пуаро (pi)
approver: 
informed: []
branch: task/deps-update-all
pr: 
status: in_progress
---

# TASK-chore-deps-update-all: Обновить все зависимости проекта до последних версий в рамках констрейнтов

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- `composer.lock` отстал от доступных релизов: 12 прямых зависимостей имеют обновления в рамках заданных констрейнтов (symfony/* 8.1.x → 8.1.12, phpunit 13.3.3 → 13.4.8, phpstan 2.2.13 → 2.3.7, psalm/plugin-symfony 5.3.0 → 5.16.3, deptrac 4.7.1 → 4.7.2, symfony/monolog-bundle 4.0.2 → 4.1.0, phpmd 3.x-dev bump).
- Накопление отставания lock-файла увеличивает риск несовместимости при будущих обновлениях и лишает проект актуальных исправлений инструментов качества и Symfony.

### Варианты или путь решения (Solution Sketch)
- Выполнить `composer update` (полное обновление lock-файла в рамках констрейнтов `composer.json`).
- Прогнать полный набор проверок (`make check`); если обновления что-то ломают — точечные правки кода/конфигурации.
- Основной кандидат на риск — `psalm/plugin-symfony` (скачок 5.3.0 → 5.16.3): возможны новые/усиленные проверки Psalm.

### Ожидаемый результат (Expected Result)
- `composer.lock` содержит актуальные версии всех зависимостей в рамках констрейнтов.
- Полный набор проверок зелёный; кодовые правки — только если требуются для совместимости.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как сопровождающий проекта, я хочу регулярно обновлять зависимости, чтобы не накапливать несовместимость и получать актуальные исправления инструментов и Symfony.

### Цель по SMART (Goal)
- Обновить все зависимости до последних версий, разрешённых констрейнтами, и подтвердить совместимость полным `make check` не позднее завершения текущей рабочей сессии.

## 2. Контекст и Границы (Context and Scope)
- **Где делаем:** `composer.lock`; `composer.json` — только если разрешение зависимостей потребует; код/конфигурация — только точечные правки совместимости.
- **Вне границ:** смена констрейнтов на новые мажорные линии (Psalm 7, dev-master вне констрейнтов), рефакторинг кода, изменение бизнес-логики.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] `composer update` выполнен; lock обновлён в рамках констрейнтов.
- [x] `composer validate --strict` и `composer audit` проходят.
- [x] `make check` зелёный (coding-standard-verify, coding-standard-di-check, phpstan, deptrac, psalm, phpmd, phpcs, md-links, validate-todo, validate-roles, validate-language, tests).
- [x] Точечные правки совместимости внесены: `ProcessLivenessWatcher` (точечный `@phpstan-ignore`), `RunAgentProcessLifecycleServiceTest` (переименование приватного helper).

### ⚫ Won't Have (Не будем делать)
- Переход на `dev-master`/новые мажорные версии вне текущих констрейнтов (vimeo/psalm остаётся на линии 6.x).
- Массовый рефакторинг кода под новые предупреждения инструментов (фиксировать, если появятся; устранение — отдельными задачами).

## 4. План реализации (Implementation Plan)
1. [x] Выполнить `composer update` и зафиксировать сводку изменений версий.
2. [x] Прогнать `composer validate --strict` и `composer audit`.
3. [x] Прогнать `make check`; при падениях — точечные правки совместимости.
4. [x] Обновить задачу: критерии, фактические версии.

## 5. Критерии приёмки (Definition of Done)
- [x] `composer validate --strict` и `composer audit` проходят.
- [x] `make check` зелёный.
- [ ] Diff содержит `composer.lock` (+ `composer.json` при необходимости) и только оправданные правки совместимости. Инвентаризация: `composer.lock` (обновление зависимостей, `composer.json` не менялся), `src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php` (подавление PHPStan, комментарий), `tests/Unit/Infrastructure/Service/AgentRunner/Lifecycle/RunAgentProcessLifecycleServiceTest.php` (rename helper), `.make-check.json` (маркер локального прогона `make check`, обязателен для CI `local-check-proof`), `todo/TASK-chore-deps-update-all.todo.md`, новая `todo/backlog/TASK-techdebt-phpstan-suppressions-audit.todo.md` (CR-2). — фиксация диффа и создание PR: за автором (коммит выполняется вручную по регламенту).

## 6. Самопроверка (Verification)
```bash
composer validate --strict
composer audit
make check
php vendor/bin/todo-md validate
```

## 7. Риски и зависимости (Risks and Dependencies)
- `psalm/plugin-symfony` 5.3.0 → 5.16.3 может добавить новые проверки Psalm — возможны замечания статического анализа.
- Symfony patch-релизы стабильны, риск низкий.
- Отсутствуют блокирующие зависимости от других задач.

## 8. Источники (Sources)
- Вывод `composer outdated --direct` (2026-11-27).
- Прецедент: `todo/done/TASK-update-all-dependencies.todo.md` (PR #381), `todo/done/TASK-chore-deps-prikotov-bump.todo.md` (PR #514).

## 9. Комментарии (Comments)
- `vimeo/psalm` остаётся на линии 6.x: `dev-master` (линия 7) вне констрейнта и вне устойчивого набора обновления (см. прецедент PR #381). Факт: обновлён `6.x-dev` → стабильный `6.21.4` (`prefer-stable` выбрал тег линии 6.x).

### Фактические версии (2026-11-27, Бэкендер Тони; скорректировано по результатам ревью CR-1)
- `composer update` (итог двух проходов 2026-11-27): обновлено 54 пакета, удалён 1 (`theseer/tokenizer` — `phpunit/php-code-coverage` 14.5.2 больше не требует его: токенизация через `nikic/php-parser ^5.9`), добавленных нет; `composer.json` не менялся (разрешение внутри констрейнтов, `content-hash` неизменен).
- Прямые зависимости: `symfony/*` 8.1.x → 8.1.13 (dependency-injection, filesystem, finder, http-client, http-kernel, yaml) / 8.1.12 (console, lock, process, translation) / 8.1.9 (twig-bundle); `symfony/monolog-bundle` 4.0.2 → 4.1.0; `phpunit/phpunit` 13.3.3 → 13.4.8; `phpstan/phpstan` 2.2.13 → 2.3.7; `deptrac/deptrac` 4.7.1 → 4.7.2; `phpmd/phpmd` `3.x-dev` → `3.0.0`; `pdepend/pdepend` `3.x-dev` → `3.1.0` (замена dev-снапшотов стабильными тегами — канон `prefer-stable`); `vimeo/psalm` `6.x-dev` → `6.21.4`.
- Транзитивные, из значимых: `symfony/monolog-bridge` 8.1.6 → 8.1.10 (через `symfony/monolog-bundle`), `symfony/twig-bridge` 8.1.5 → 8.1.12, `symfony/string` 8.1.2 → 8.1.13, `symfony/http-foundation` 8.1.12 → 8.1.13, `symfony/cache` 8.1.12 → 8.1.13, `symfony/expression-language` 8.1.12 → 8.1.13, `monolog/monolog` 3.12.0 → 3.13.0.
- `psalm/plugin-symfony`: **остаётся 5.3.0** — это последняя версия, совместимая с Psalm 6; начиная с 5.4.0 плагин требует `vimeo/psalm ^7`, что вне констрейнта `^6.16` и вне границ задачи. «Скачок 5.3.0 → 5.16.3» из постановки — артефакт вывода `composer outdated --direct` (не учитывает transitive-конфликт); риск из раздела 7 не материализовался.
- После повторного обновления (по результатам ревью CR-1 — первый проход отстал на patch: 6 прямых `symfony/*` имели доступный `v8.1.13`) `composer outdated --direct` показывает только `psalm/plugin-symfony` (5.16.3, вне констрейнтов — требует Psalm 7) и `vimeo/psalm` (7.22.1, вне констрейнтов — Won't Have).

### Точечные правки совместимости
1. `src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php` — PHPStan 2.3.7 стал считать повторный опрос `$process->isRunning()` в одной итерации всегда-ложным (`booleanNot.alwaysFalse`): его модель не учитывает внешнее асинхронное состояние OS-процесса. Опрос контрактный (тест `waitForNaturalExitImmediatelyBeforeIdleStopReturnsNaturally`), поэтому — точечный `@phpstan-ignore booleanNot.alwaysFalse` с обоснованием (прецеденты: `property.unusedType`, `nullCoalesce.offset` — `HttpsProxyBridge.php:41`, `:176`). По конвенции подавления оформлен техдолг-задачей: `TASK-techdebt-phpstan-suppressions-audit` (аудит всех подавлений PHPStan, включая 4 известных «мёртвых»). Глобальный `treatPhpDocTypesAsCertain: false` отклонён (ослабление всего анализа ради одного места).
2. `tests/Unit/Infrastructure/Service/AgentRunner/Lifecycle/RunAgentProcessLifecycleServiceTest.php` — PHPUnit 13.4.8 сделал `TestCase::runLifecycle()` final; приватный helper теста `runLifecycle()` переименован в `runLifecycleScenario()` (11 вхождений, только внутри файла).

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-27 14:26:32 (1795789592) | Тимлид Алекс (pi) | Создание задачи |
| 2026-11-27 21:43:14 (1795790594) | Бэкендер Тони (pi) | Обновление выполнено: 45 пакетов, `make check` зелёный; 2 точечные правки совместимости; критерии/план закрыты |
| 2026-11-27 15:15:48 (1795792548) | Бэкендер Тони (pi) | Доработка по ревью (CR-1…CR-7): повторный composer update (+10 пакетов до v8.1.13, итого 54 обновления), техдолг-задача TASK-techdebt-phpstan-suppressions-audit (CR-2), сокращение комментария подавления (CR-3/CR-4), уточнения §9 и метаданных (CR-5/CR-7) |
