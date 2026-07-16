---
type: chore
created: 2026-07-16
value: V4
complexity: C1
priority: P0
depends_on: TASK-fix-phar-release-version-resolution
epic:
author: system_analyst_sherlock
assignee: team_lead_alex
branch: task/release-v0-2-1
pr: https://github.com/prikotov/task-orchestrator/pull/312
status: review
---

# TASK-release-v0-2-1-preparation: Подготовить и выпустить patch release v0.2.1

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

Опубликованный PHAR `v0.2.0` выводит `Task Orchestrator 1.0.0.0` вместо `0.2.0`.
Исправление принято в `release/0.2` через hotfix PR #310, merge commit (коммит слияния)
`ac9b969`, но пользователи получат исправленный артефакт только после отдельного patch release
(патч-релиза) `v0.2.1`.

### Варианты или путь решения (Solution Sketch)

Ограничить подготовку patch entry (записью патч-релиза) в `CHANGELOG.md`, отдельным release
plan (планом релиза) и финальными проверками. Затем открыть PR из `task/release-v0-2-1` в
`release/0.2`. Тег `v0.2.1` публиковать отдельной операцией только после явного разрешения
пользователя.

### Ожидаемый результат (Expected Result)

В `release/0.2` приняты проверенные release metadata (релизные метаданные) `v0.2.1`, а после
отдельного разрешения тег запускает выпуск PHAR, который выводит ровно
`Task Orchestrator 0.2.1`.

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)

> Когда hotfix версии PHAR принят в релизную линию, я хочу выпустить минимальный patch release,
> чтобы пользователи получали артефакт с достоверным выводом `--version`.

### Goal (Цель по SMART)

В текущем релизном цикле добавить patch entry `0.2.1` в `CHANGELOG.md`, подготовить
`docs/releases/v0.2.1/release-plan.md`, выполнить финальные проверки и создать PR
`task/release-v0-2-1` → `release/0.2`. После принятия PR и отдельного явного разрешения
пользователя опубликовать тег `v0.2.1` с проверенной вершины `release/0.2`.

## 2. Context and Scope (Контекст и Границы)

* **Где делаем:** `CHANGELOG.md`, `docs/releases/v0.2.1/release-plan.md`, этот task file
  (файл задачи); GitHub PR и тег как отдельные операции.
* **Основание:** hotfix PR #310 слит в `release/0.2` коммитом `ac9b969`; отдельный merge-back
  PR #311 переносит исправление в `main` и не входит в этот релизный PR.
* **Границы (Out of Scope):**
  * не менять production-код, тесты, зависимости, CI/CD и release tooling
    (инструментарий выпуска);
  * не добавлять features (функции), сопутствующие исправления или рефакторинг;
  * не включать и не выполнять backlog-задачи;
  * не менять Packagist, README и общую документацию;
  * не создавать и не отправлять тег до отдельного явного разрешения пользователя.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [x] `CHANGELOG.md` содержит минимальную запись `0.2.1` о корректном exact SemVer
  (точном SemVer) в Composer/PHAR и усиленной проверке версии.
- [x] `docs/releases/v0.2.1/release-plan.md` фиксирует состав patch release, hotfix PR #310,
  риски, проверки и порядок публикации.
- [x] Финальные проверки кода, platform requirements (платформенных требований) и
  Composer-host smoke проходят на вершине release candidate (кандидата в релиз); Composer-host
  выводит ровно `Task Orchestrator 0.2.1`.
- [ ] Финальный PHAR release candidate подтверждает точный вывод `Task Orchestrator 0.2.1`.
- [x] Создан PR `task/release-v0-2-1` → `release/0.2`; поле `pr` заполнено, задача переведена
  в `review`.
- [ ] После approval (одобрения), до merge (слияния), задача переведена в `done` и перенесена
  в `todo/done/`.
- [ ] PR слит только после явной команды пользователя на merge.
- [ ] Тег `v0.2.1` создан и отправлен только после отдельного явного разрешения пользователя
  на публикацию.
- [ ] После публикации проверены успешный workflow (процесс GitHub Actions) `Release Phar`,
  точный вывод версии и наличие PHAR-артефакта.

### ⚫ Won't Have (Не будем делать)

- [ ] Новый tooling (инструментарий), features (функции) или исправления вне PR #310.
- [ ] Backlog-задачи, README/Packagist или общий docs overhaul (переработка документации).
- [ ] Merge-back в `main`: он выполняется отдельным PR #311.

## 4. Implementation Plan (План реализации)

1. [x] Добавить минимальный patch entry `0.2.1` в `CHANGELOG.md`.
2. [x] Подготовить `docs/releases/v0.2.1/release-plan.md`.
3. [x] Выполнить `make check`, проверить platform requirements и Composer-host exact-version
   smoke для `0.2.1`.
4. [ ] Подтвердить exact-version финального PHAR release candidate для `0.2.1`.
5. [x] Создать PR `task/release-v0-2-1` → `release/0.2`, заполнить `pr` и перевести задачу
   в `review`.
6. [ ] После approval перевести задачу в `done`, перенести в `todo/done/` и только по явной
   команде пользователя слить PR.
7. [ ] После отдельного явного разрешения создать и отправить тег `v0.2.1`, затем проверить
   GitHub Release и PHAR-артефакт.

## 5. Definition of Done (Критерии приёмки)

- [x] Diff (набор изменений) PR ограничен `CHANGELOG.md`, release plan и файлом задачи.
- [x] `make check` проходит успешно.
- [x] Composer-host smoke подтверждает точную версию `0.2.1`.
- [x] Platform requirements подтверждены: PHP `>=8.4.1`, `ext-openssl`, `ext-zlib`.
- [ ] Финальный PHAR smoke подтверждает точную версию `0.2.1`.
- [x] `todo-md-validate` и `git diff --check` проходят успешно.
- [ ] PR принят в `release/0.2` по правилам проекта.
- [ ] Публикация тега выполнена только после отдельного явного разрешения пользователя.
- [ ] GitHub Release `v0.2.1` опубликован, workflow `Release Phar` успешен, PHAR выводит
  `Task Orchestrator 0.2.1`.

## 6. Verification (Самопроверка)

```bash
make check
PHAR_EXPECTED_VERSION=0.2.1 make phar-smoke
php vendor/prikotov/todo-md/bin/todo-md-validate todo/TASK-release-v0-2-1-preparation.todo.md
git diff --check
```

Результаты подготовки:

- `make check`: успешно, PHPUnit — 1462 tests (теста), 3902 assertions (утверждения).
- Composer-host smoke: точный вывод версии `0.2.1` подтверждён.
- Platform requirements: PHP `>=8.4.1`, `ext-openssl`, `ext-zlib` подтверждены.
- Финальная проверка exact-version PHAR выполняется отдельно и пока не отмечена.

После отдельного разрешения на публикацию:

```bash
gh run list --workflow="Release Phar" --limit=1
gh release view v0.2.1
```

## 7. Risks and Dependencies (Риски и зависимости)

- Зависимость выпуска — принятый hotfix `TASK-fix-phar-release-version-resolution`, PR #310,
  merge commit `ac9b969`.
- Тег должен указывать на проверенную вершину `release/0.2`; иначе опубликованный артефакт
  может не содержать исправление версии.
- Merge-back PR #311 обязателен для синхронизации `main`, но выполняется отдельно и не расширяет
  scope текущего релизного PR.
- Публикация тега запускает внешний release workflow и требует отдельного явного разрешения.

## 8. Sources (Источники)

- [Исправление версии PHAR](done/TASK-fix-phar-release-version-resolution.todo.md)
- [Правила проекта](../AGENTS.md)
- [Правила работы с задачами](AGENTS.md)

## 9. Comments (Комментарии)

Подготовка релиза одобрена пользователем. Merge PR и публикация тега — разные операции;
каждая выполняется только после соответствующей явной команды.

## Change History (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-16 | Аналитик Шерлок | Создание задачи и начало подготовки patch release `v0.2.1`. |
| 2026-07-16 | Тимлид Алекс | CHANGELOG и release plan подготовлены, проверки кода/платформы/Composer-host пройдены; создан PR #312 в `release/0.2`, задача переведена в `review`. |
