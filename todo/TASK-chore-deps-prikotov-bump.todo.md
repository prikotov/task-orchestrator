---
type: chore
created: 2026-11-26 14:26:08 (1795703168)
due: 
started: 2026-11-27 02:44:17 (1795747457)
completed: 
cancelled: 
value: V2
complexity: C3
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Алекс (pi)
assignee: Бэкендер Тони (pi)
consultants: []
reviewer: 
approver: 
informed: []
branch: task/deps-prikotov-bump
pr: 
status: in_progress
---

# TASK-chore-deps-prikotov-bump: Подъём всех зависимостей prikotov/* до последних релизов

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- `composer.lock` отстаёт от релизов внутренних пакетов `prikotov/*`: `prikotov/coding-standard` — в lock `0.34.2` при выпущенном `0.35.0`. Из-за этого `make check` красный **на всём `main`** (цель `coding-standard-verify` требует актуальный релиз) — CI-job маркера локального прогона (`local-check`, PR #510) падает для любого кодового PR, включая текущие. Констрейнты в `composer.json` (`^0.34.0` и др.) блокируют `composer update`.

### Варианты или путь решения (Solution Sketch)
- Поднять констрейнты всех трёх внутренних пакетов в `composer.json` до последних релизов и выполнить `composer update` по ним: `prikotov/coding-standard` (0.34.2 → 0.35+), `prikotov/todo-md` (^0.7.0 → последний), `prikotov/git-workflow` (^0.4.0 → последний).
- Новые версии могут принести новые правила стиля/валидации — прогнать полный набор проверок (`make check`, phpunit, psalm, deptrac) и устранить замечания в коде, если появятся.

### Ожидаемый результат (Expected Result)
- Все зависимости `prikotov/*` — последних релизов; `make check` зелёный на `main`; CI-job маркера `local-check` снова пригоден для кодовых PR.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **Job Story:** Когда я открываю кодовый PR, я хочу, чтобы барьер `make check` и его CI-верификация работали на актуальных инструментах, чтобы красный CI означал проблему моего PR, а не устаревший lock всего репозитория.

### Цель по SMART (Goal)
- Все `prikotov/*`-зависимости в `composer.json`/`composer.lock` — последние релизы; `make check` зелёный; полный phpunit и psalm зелёные. Срок — ближайший спринт (блокер для кодовых PR).

## 2. Контекст и Границы (Context and Scope)

- **Где делаем:** `composer.json`, `composer.lock`; при новых правилах стиля — правки кода/доков, которые они требуют.
- **Текущее состояние (2026-11-26):** `prikotov/coding-standard` `0.34.2` в lock, релиз `0.35.0`, констрейнт `^0.34.0`; `prikotov/todo-md` `^0.7.0`; `prikotov/git-workflow` `^0.4.0`. Последние релизы уточнить при старте (`composer outdated` / packagist).
- **Out of scope:** подъём не-`prikotov` зависимостей (symfony, phpunit, psalm и пр.); смена PHP-платформы.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] Констрейнты `prikotov/coding-standard`, `prikotov/todo-md`, `prikotov/git-workflow` в `composer.json` указывают на последние релизы; lock обновлён.
- [ ] `make check` зелёный (включая `coding-standard-verify` и doc-проверки).
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` зелёные; замечания новых правил стиля устранены или обоснованно отключены (с ссылкой на обсуждение).
### ⚫ Won't Have (Не будем делать)
- Подъём сторонних (не `prikotov/*`) зависимостей; изменение бизнес-логики; отключение проверок ради зелёного прогона без решения владельца.

## 4. План реализации (Implementation Plan)
1. [ ] Зафиксировать актуальные последние релизы трёх пакетов (`composer outdated` / packagist) и их changelog (breaking changes, новые правила).
2. [ ] Поднять констрейнты в `composer.json`, выполнить `composer update prikotov/coding-standard prikotov/todo-md prikotov/git-workflow --with-all-dependencies`.
3. [ ] Прогнать полный набор проверок; устранить замечания новых правил (или эскалировать владельцу, если объём правок велик — разбить на отдельные задачи).
4. [ ] Полный `vendor/bin/phpunit`, `vendor/bin/psalm`, `make check` — зелёные.

## 5. Критерии приёмки (Definition of Done)
- [ ] Lock содержит последние релизы всех `prikotov/*`; констрейнты им соответствуют.
- [ ] `make check` зелёный; `vendor/bin/phpunit` и `vendor/bin/psalm` зелёные.
- [ ] CI на PR зелёный (включая job маркера `local-check`).

## 6. Самопроверка (Verification)
```bash
composer outdated prikotov/coding-standard prikotov/todo-md prikotov/git-workflow
vendor/bin/phpunit
vendor/bin/psalm
make check
```

## 7. Риски и зависимости (Risks and Dependencies)
- Релиз `0.35.0` coding-standard может содержать новые правила — объём правок по коду неизвестен до прогона; при большом объёме — разбить на отдельные задачи по согласованию с владельцем.
- Блокирует (и блокируется) текущий открытый PR #512: его CI-job маркера упадёт до merge этой задачи; после merge `main` — PR #512 актуализировать или мержить с учётом известного блокера (решение владельца).

## 8. Источники (Sources)
- `composer.json` (require-dev: строки 32, 34–35), `composer.lock`.
- Вывод `vendor/bin/coding-standard-verify` (2026-11-26): FAIL «composer.lock has 0.34.2, latest release is 0.35.0».
- `vendor/prikotov/coding-standard/README.md` — раздел «Обновление».
- Контекст: PR #512 (TASK-test-watchsubagent-stall-flaky), CI-job `local-check` (TASK-build-doc-checks-ci, PR #510).

## 9. Комментарии (Comments)
- Решение владельца 2026-11-26 (в контрольной точке PR #512): завести задачу на подъём **всех** `prikotov/*` до последних релизов (расширение исходного предложения только про coding-standard).
- Итог выполнения 2026-11-27 (Бэкендер Тони): констрейнты подняты (`coding-standard ^0.35.0`, `git-workflow ^0.4.3`, `todo-md ^0.7.0` — уже последняя), `coding-standard-verify` зелёный; доки конвенций и workflow-шаблоны синхронизированы через init-скрипты.
- Новое правило 0.35.0 (свойства VO только приватные) вскрыло 172 ошибки PHPCS в 26 VO-файлах (~90 файлов-потребителей) — по плану п. 4.3 эскалировано отдельной задачей TASK-techdebt-vo-private-properties; до её merge `make check` остаётся красным только на PHPCS (остальные проверки зелёные).
- До merge этой задачи `make check` красный на `main` для всех кодовых PR — известное принятое состояние.
- Решение владельца 2026-11-27: (а) обновление advisory-зависимостей перенесено в этот PR из Won't Have — в ветке обновляются сторонние пакеты, по которым есть advisory в `composer audit`, до последних патч-версий строго в рамках констрейнтов `composer.json`; (б) задача переводится в `done` при открытии PR — блокер `coding-standard-verify` снят, полная зелень `make check` достигается после TASK-techdebt-vo-private-properties.
- Итог выполнения 2026-11-27, security-часть (Бэкендер Тони): `composer update symfony/config symfony/expression-language symfony/filesystem symfony/framework-bundle symfony/http-client symfony/http-foundation symfony/http-kernel symfony/routing twig/twig --with-all-dependencies` — 9 advisory-пакетов подняты до последних патч-версий в рамках констрейнтов `^8.0` / twig `3.x`: 8× `symfony/*` → `v8.1.12` (было `v8.1.5`/`v8.1.6`), `twig/twig` `v3.28.0` → `v3.31.1`; транзитивные зависимости обновлены автоматически (`symfony/cache`, `symfony/dependency-injection`, `symfony/var-dumper`, `symfony/error-handler`, `symfony/var-exporter`, contracts, polyfills). Полный перечень обновлений (для аудита посторонних): прямые зависимости проекта, подтянутые `--with-all-dependencies` — `symfony/finder` (`v8.1.5` → `v8.1.12`), `symfony/yaml` (`v8.1.11` → `v8.1.12`); dev-транзитивные, обновлённые вместе с `prikotov/coding-standard` — `slevomat/coding-standard` (`8.31.3` → `8.32.0`), `phpstan/phpdoc-parser` (`2.4.1` → `2.4.2`; легитимность подтверждена `composer why`). `composer audit` после обновления — чист: 34 advisory на 9 пакетов → 0; остатков нет. `vendor/bin/phpunit` — OK (1779 тестов, 5335 assertions, 2 skipped — предсуществующие пропуски), `vendor/bin/psalm` — ошибок нет. Полный `make check` ожидаемо красный только на новом правиле PHPCS «свойства VO приватные» (172 ошибки, отдельная задача TASK-techdebt-vo-private-properties; VO не трогались).

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-26 14:26:08 (1795703168) | Тимлид Алекс (pi) | Создание задачи |
| 2026-11-27 10:06:08 (1795748768) | Бэкендер Тони (pi) | Решение владельца 2026-11-27: advisory-зависимости (8× symfony/* → v8.1.12, twig/twig → v3.31.1) обновлены в этом PR из Won't Have; composer audit чист; задача — в done при открытии PR |
