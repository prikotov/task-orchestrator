---
type: fix
created: 2026-07-16
value: V4
complexity: C2
priority: P0
depends_on:
epic:
author: system_analyst_sherlock
assignee: backend_developer_levsha
branch: hotfix/0.2.1-phar-release-version
pr: https://github.com/prikotov/task-orchestrator/pull/310
status: review
---

# TASK-fix-phar-release-version-resolution: Исправить версию приложения в PHAR

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

Опубликованный PHAR релиза `v0.2.0` с SHA-256, начинающимся на `00d4826e`, выводит
`Task Orchestrator 1.0.0.0` вместо `Task Orchestrator 0.2.0`. Пользователь не может по
артефакту достоверно определить установленную версию, а текущий PHAR smoke (дымовой тест)
проверяет только успешность запуска `--version` и пропускает неверное значение.

### Варианты или путь решения (Solution Sketch)

Для пользовательской версии использовать exact pretty version (точную человекочитаемую
версию) Composer либо версию release tag (релизного тега), сохраняя полный SemVer и удаляя
только допустимый префикс `v`. Нормализованное значение Composer (`1.0.0.0`) не использовать
как публичную версию. Для source checkout (исходной рабочей копии) без точной SemVer не
выдумывать номер релиза, а возвращать явный non-release marker (маркер нерелизной сборки)
`dev`. Усилить PHAR smoke точным сравнением ожидаемой и фактической версии.

### Ожидаемый результат (Expected Result)

PHAR, опубликованный из тега `v0.2.1`, выводит ровно `Task Orchestrator 0.2.1`; Composer
distribution (Composer-дистрибутив), установленный по точной версии, выводит ту же SemVer.
Source checkout без точной версии выводит `Task Orchestrator dev`, а не произвольный номер.
Несовпадение версии делает PHAR smoke красным до публикации артефакта.

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)

> Когда я запускаю `task-orchestrator --version` из опубликованного PHAR или Composer package
> (Composer-пакета), я хочу видеть точную версию соответствующего релиза, чтобы однозначно
> идентифицировать артефакт при диагностике и обновлении.

### Goal (Цель по SMART)

В hotfix (срочном исправлении) `v0.2.1` устранить подмену версии `0.2.0` нормализованным
Composer-значением `1.0.0.0`: разрешать публичную версию из exact SemVer pretty version/тега,
обеспечить безопасное поведение `dev` для source checkout и добавить автоматические
регрессионные проверки Composer/PHAR. Задача готова, когда полный `make check` проходит, а
PHAR smoke завершается ошибкой при любом отличии от явно ожидаемой версии.

## 2. Context and Scope (Контекст и Границы)

### 2.1. Где делаем

- `src/Kernel.php`, метод `Kernel::resolveVersion()` — источник параметра `app.version`.
- Unit tests (модульные тесты) и Integration tests (интеграционные тесты) по
  [testing convention (конвенции тестирования)](../docs/conventions/testing/index.md).
- `bin/phar-smoke` и только необходимая передача ожидаемой версии существующим release/CI
  workflow (процессам релиза/CI), без добавления нового инструментария выпуска.

### 2.2. Подтверждённый дефект

- Production tag (продукционный тег): `v0.2.0`.
- SHA-256 опубликованного PHAR начинается с `00d4826e`.
- Фактический вывод: `Task Orchestrator 1.0.0.0`.
- Ожидаемый вывод: `Task Orchestrator 0.2.0`.
- Текущий `Kernel::resolveVersion()` сначала вызывает
  `Composer\InstalledVersions::getVersion('prikotov/task-orchestrator')`, а при `null` берёт
  нормализованный `version` root package (корневого пакета). Это значение предназначено для
  сравнения зависимостей, а не для точного пользовательского отображения тега.
- Текущий `bin/phar-smoke` запускает `php task-orchestrator.phar --version`, но не сравнивает
  вывод с ожидаемой версией.

### 2.3. Границы (Out of Scope)

- Подготовка и публикация patch release (патч-релиза) `v0.2.1` — отдельный релизный PR/шаг
  после принятия hotfix.
- Изменения Packagist, `README.md` и локализаций README.
- Новый release tooling (инструментарий выпуска), переработка workflows или формата PHAR.
- Изменение публичных CLI-команд, кроме исправления значения существующего `--version`.
- Рефакторинг `Kernel` и смежной загрузки контейнера вне необходимого исправления.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [x] Публичная версия разрешается из pretty version Composer или release tag, только если
  значение является точной SemVer (`v?MAJOR.MINOR.PATCH` с допустимыми prerelease/build
  частями); начальный `v` удаляется без иных преобразований.
- [x] `Composer\InstalledVersions::getVersion()` и нормализованный root `version` больше не
  используются как пользовательское значение версии.
- [x] Для Composer distribution точная pretty version пакета имеет приоритет над root package;
  для root/PHAR используется точная pretty version корневого пакета либо явно переданная
  версия release tag.
- [x] При отсутствии точной SemVer (`dev-main`, `1.0.0+no-version-set`, `null`, некорректное
  значение) source checkout возвращает `dev` и не маскируется под опубликованный релиз.
- [x] PHAR smoke требует явно ожидаемую SemVer, запускает `--version` и сравнивает полный вывод
  с `Task Orchestrator <expected>`; отсутствие ожидания, неверный формат или несовпадение
  завершают проверку ошибкой.
- [x] Release/CI вызовы существующего `bin/phar-smoke` передают ожидаемую версию из проверенного
  тега/контекста без нового механизма публикации.
- [x] Unit tests покрывают приоритет pretty version, удаление `v`, prerelease/build SemVer,
  запрет normalized version и fallback `dev`.
- [x] Integration regression test (интеграционный регрессионный тест) подтверждает итоговый
  вывод CLI для release и source сценариев.
- [x] PHAR regression test (регрессионный тест PHAR) падает на `1.0.0.0` при ожидании `0.2.1`
  и проходит только при точном совпадении `0.2.1`.

### 🟡 Should Have (Желательно)

- [x] Диагностика PHAR smoke печатает ожидаемую и фактическую версии без неоднозначности.

### ⚫ Won't Have (Не будем делать)

- [ ] Регистрация или обновление пакета в Packagist.
- [ ] Изменения README или общая переработка release pipeline (конвейера выпуска).
- [ ] Автоматическое создание тега либо GitHub Release.
- [ ] Изменения, не связанные с разрешением и проверкой версии приложения.

## 4. Implementation Plan (План реализации)

1. [x] Зафиксировать регрессию тестами разрешения версии для package/root, release/source и
   некорректных normalized values (нормализованных значений).
2. [x] Исправить `Kernel::resolveVersion()` либо минимально выделенный тестируемый механизм:
   брать точную pretty version/версию тега, валидировать SemVer, иначе возвращать `dev`.
3. [x] Добавить Integration test (интеграционный тест) итогового `app.version`/CLI `--version`.
4. [x] Усилить `bin/phar-smoke` обязательным ожидаемым значением и точным сравнением вывода;
   адаптировать только существующие места его вызова.
5. [x] Выполнить целевые тесты, `make phar-smoke` с ожидаемой `0.2.1` и полный `make check`.
6. [x] Создать hotfix PR (PR срочного исправления) в `release/0.2`.
7. [ ] После merge отдельным PR перенести тот же commit (коммит) в `main`, не смешивая
   merge-back с выпуском `v0.2.1`.

## 5. Definition of Done (Критерии приёмки)

- [x] PHAR-кандидат `v0.2.1` выводит ровно `Task Orchestrator 0.2.1`.
- [x] Composer install (установка Composer) точной версии выводит соответствующую exact SemVer.
- [x] Source checkout без exact SemVer выводит `Task Orchestrator dev`.
- [x] Значение `1.0.0.0` не может пройти version resolver (разрешение версии) или PHAR smoke.
- [x] Добавлены Unit/Integration/PHAR regression tests.
- [x] `vendor/bin/phpunit`, `vendor/bin/psalm` и `make check` проходят успешно.
- [x] Hotfix PR направлен в активную `release/0.2`.
- [ ] После принятия hotfix создан отдельный merge-back PR в `main`.
- [ ] Подготовка/публикация `v0.2.1` выполняется отдельным релизным шагом с явным разрешением
  пользователя.

## 6. Verification (Самопроверка)

```bash
vendor/bin/phpunit
vendor/bin/psalm
PHAR_EXPECTED_VERSION=0.2.1 make phar-smoke
make check
php vendor/prikotov/todo-md/bin/todo-md-validate todo/TASK-fix-phar-release-version-resolution.todo.md
git diff --check
```

Результаты:

- Self-review (самопроверка реализации): **Approval**.
- Code review (проверка кода): **Approval**.
- `make check`: успешно, PHPUnit — 1462 tests (теста), 3902 assertions (утверждения).
- Composer-host smoke (проверка Composer-дистрибутива): точная версия `0.2.1` подтверждена.

## 7. Risks and Dependencies (Риски и зависимости)

- `release/0.2` — активная release branch (релизная ветка) и base (база) hotfix PR.
- После принятия hotfix обязателен отдельный merge-back PR в `main`; иначе исправление останется
  только в линии `0.2.x` и может потеряться в следующей версии.
- Composer может представлять source checkout как `dev-main` или
  `1.0.0+no-version-set`; такие значения нельзя принимать за номер релиза.
- PHAR не должен зависеть от наличия `.git`; release tag должен быть передан в доступные
  упакованному приложению метаданные существующим процессом сборки.
- Подготовка patch release `v0.2.1` зависит от принятия hotfix, но не входит в эту задачу.

## 8. Sources (Источники)

- [`Kernel::resolveVersion()`](../src/Kernel.php)
- [`bin/phar-smoke`](../bin/phar-smoke)
- [SemVer 2.0.0](https://semver.org/spec/v2.0.0.html)
- [Правила проекта](../AGENTS.md)
- [Правила работы с задачами](AGENTS.md)

## 9. Comments (Комментарии)

Hotfix branch создана от production tag `v0.2.0`, а не от `main`. Исправление предназначено
для `release/0.2`; синхронизация в `main` выполняется отдельным PR после принятия hotfix.

## Change History (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-16 | Аналитик Шерлок | Создание P0 hotfix-задачи по подтверждённому дефекту версии PHAR. |
| 2026-07-16 | Бэкендер Левша | Реализация и проверки завершены; self-review и code review — Approval; создан PR #310 в `release/0.2`, задача переведена в `review`. |
