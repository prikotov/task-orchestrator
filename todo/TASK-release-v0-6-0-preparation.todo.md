---
type: chore
created: 2026-09-10 15:21:04 (1789053664)
due: 
started: 2026-09-10 15:21:04 (1789053664)
completed: 
cancelled: 
value: V2
complexity: C1
priority: P1
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Технический писатель Гермиона (pi)
assignee: Технический писатель Гермиона (pi)
branch: task/release-v0-6-0
pr: 
status: in_progress
---

# TASK-release-v0-6-0-preparation: Подготовить release PR для v0.6.0

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В `main` после тега `v0.5.3` слиты три запроса: [#380](https://github.com/prikotov/task-orchestrator/pull/380) (несовместимое изменение конфигурации: `TASK_ORCHESTRATOR_LOCALE` вместо `APP_LOCALE`), [#381](https://github.com/prikotov/task-orchestrator/pull/381) (полное обновление зависимостей до устойчивых линий) и [#382](https://github.com/prikotov/task-orchestrator/pull/382) (модернизация тестовых двойников PHPUnit). Опубликованный тег `v0.5.3` их не содержит.
- Несовместимое изменение конфигурации (breaking change) в линейке `0.x` по принятой проектной SemVer-конвенции требует повышения `minor`: следующий релиз — `v0.6.0`.

### Варианты или путь решения (Solution Sketch)
- Подготовить релизные артефакты в ветке `task/release-v0-6-0`: запись `v0.6.0` в `CHANGELOG.md` (дата 2026-09-10, ссылки на PR) и план релиза `docs/releases/v0.6.0/release-plan.md` по актуальному шаблону вендора и образцу `v0.5.3`.

### Ожидаемый результат (Expected Result)
- Артефакты фиксируют состав (`#380`, `#381`, `#382`), тип версии `minor` с обоснованием, миграцию окружения на `TASK_ORCHESTRATOR_LOCALE` (добавление переменной; `APP_LOCALE` сохраняется при наличии потребителей в host-приложении), отсутствие миграций данных, риски полного обновления dev-зависимостей и восстановление через hotfix `v0.6.1`. Тег и GitHub Release не создаются до слияния release PR и явного подтверждения merge.

## 1. Концепция и Цель (Concept and Goal)

### История (Job Story)
> **Job Story:** Когда состав релиза слит в `main` и содержит несовместимое изменение конфигурации, я хочу подготовить release PR для minor-релиза `v0.6.0`, чтобы потребители получили миграцию окружения, риски и порядок публикации в одном документированном комплекте.

### Цель по SMART (Goal)
К 2026-09-10 подготовить в ветке `task/release-v0-6-0`: запись `CHANGELOG.md` `[0.6.0] - 2026-09-10` с составом и ссылками на PR, план релиза с типом `minor` и обоснованием, задачу в `in_progress` с заполненной веткой. Коммит, push и создание release PR — штатная часть подтверждённой пользователем работы над релизом (отдельный запрос не требуется); тег `v0.6.0` и GitHub Release — только после слияния release PR и явного подтверждения merge пользователем.

## 2. Контекст и Границы (Context and Scope)

* **Где делаем:** `CHANGELOG.md`, `docs/releases/v0.6.0/release-plan.md`, `.env.example` (стабилизационная документационная правка по итогам ревью), этот файл задачи.
* **Текущее состояние:** `task/release-v0-6-0` стоит на вершине `main` (`e929e2f`, merge #382); `git log v0.5.3..main` = #380, #381, #382.
* **Шаблон плана релиза:** `vendor/prikotov/git-workflow/docs/git-workflow/templates/release-plan.template.md` (обязательные поля: тип повышения версии и обоснование).
* **Проектный образец:** `docs/releases/v0.5.3/release-plan.md` (релиз из `main`, «Порядок публикации» вместо deploy Web/Workers).
* **Границы (Out of Scope):** не изменять код, тесты, скрипты и публичные контракты; не создавать тег `v0.6.0` и GitHub Release до слияния release PR и явного подтверждения merge пользователем.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [x] `CHANGELOG.md`: Unreleased-состав перенесён в `[0.6.0] - 2026-09-10` со ссылками на #380; добавлены краткие записи #381 (зависимости: coding-standard 0.33, PHPUnit 13, Deptrac 4) и #382 (test doubles, ноль notices/deprecations, защитные fail-флаги).
- [x] `docs/releases/v0.6.0/release-plan.md` создан по шаблону вендора и образцу `v0.5.3`.
- [x] План явно указывает тип `minor` и обоснование: breaking change в `0.x` повышает minor по принятой проектной SemVer-конвенции.
- [x] План фиксирует: состав (#380, #381, #382), миграцию окружения подробно (добавление `TASK_ORCHESTRATOR_LOCALE`; `APP_LOCALE` сохраняется при наличии потребителей в host-приложении, удаляется только при их отсутствии), отсутствие миграций данных, риски полного обновления dev-зависимостей, восстановление через hotfix `v0.6.1`.
- [x] План отражает выпуск релиза из `main` по `docs/releases/RELEASE-POLICY.md`, а не из `release/x.y`.
- [x] Задача создана, назначена на исполнителя, `status=in_progress`, `branch=task/release-v0-6-0`.
- [x] Доработка по замечаниям ревью Остапа — миграция для Composer host в `CHANGELOG.md` и release-plan говорит «добавьте `TASK_ORCHESTRATOR_LOCALE=ru`»; `APP_LOCALE` не удаляется безусловно.
- [x] Доработка по замечаниям ревью Остапа — три относительные ссылки на `todo/done/` из release-plan исправлены на `../../../todo/done/...`.
- [x] Доработка по замечаниям ревью Остапа — блок `v0.6.0` в `CHANGELOG.md` сокращён до краткого release-summary (breaking change + безопасная миграция; по одной компактной строке для #381 и #382); детальный fallback и кеш — в release plan.
- [x] Доработка по замечаниям ревью Остапа — `.env.example` не заявляет безусловный дефолт `en` для role file: точно разделены порядок поиска role file при пустой переменной (`<role>.md` → `<role>.en.md` → `<role>.ru.md` → `<role>.zh.md` → первый локализованный) и дефолт `en` только для заголовков каталога skills.
- [x] Полная релизная проверка пройдена успешно: `composer validate --strict`; `composer audit` — уязвимостей нет; `make check` (1527 тестов, 4283 assertions, 2 skipped; PHPStan/Deptrac/Psalm/PHPMD/PHPCS/docs — зелёные); `make composer-host-smoke`; `PHAR_EXPECTED_VERSION=dev make phar-smoke`.
### 🟡 Желательно (Should Have)
- [ ] —
### 🟢 Опционально (Could Have)
- [ ] —
### ⚫ Не будем делать (Won't Have)
- [ ] Создание тега `v0.6.0` и GitHub Release до слияния release PR и явного подтверждения merge пользователем.
- [ ] Изменения кода, тестов или конфигурации (кроме стабилизационной документационной правки `.env.example`).

## 4. План реализации (Implementation Plan)
1. [x] Зафиксировать состав `v0.6.0` (`git log v0.5.3..main`): #380, #381, #382.
2. [x] Перенести Unreleased-состав в `[0.6.0] - 2026-09-10`, добавить записи #381 и #382 в `CHANGELOG.md`.
3. [x] Создать `docs/releases/v0.6.0/release-plan.md` по шаблону вендора и образцу `v0.5.3`.
4. [x] Проверить артефакты (`todo-md validate`, `validate-language`, `md-links`, `validate-docs`) и доложить пользователю; публикация ветки и создание release PR — штатные шаги подготовки релиза после закрытия ревью; тег и GitHub Release — после слияния release PR и явного подтверждения merge.
5. [x] Доработать по замечаниям ревью Остапа: миграция «добавьте `TASK_ORCHESTRATOR_LOCALE=ru`» с сохранением `APP_LOCALE` при потребителях host-приложения, исправление ссылок `../../../todo/done/`, сокращение блока `v0.6.0` в `CHANGELOG.md` до краткого release-summary, точное разделение дефолтов в `.env.example`; повторить проверки документации.
6. [x] Полная релизная проверка перед созданием release PR: `composer validate --strict`; `composer audit` без уязвимостей; `make check` (1527 тестов, 4283 assertions, 2 skipped; PHPStan/Deptrac/Psalm/PHPMD/PHPCS/docs зелёные); `make composer-host-smoke`; `PHAR_EXPECTED_VERSION=dev make phar-smoke`.

## 5. Критерии приёмки (Definition of Done)
- [x] В ветке `task/release-v0-6-0` есть запись `CHANGELOG.md` (краткий release-summary) и план `v0.6.0`; артефакты соответствуют шаблону и образцу.
- [x] Замечания ревью Остапа закрыты: миграция «добавьте» с сохранением `APP_LOCALE`, ссылки `../../../todo/done/`, краткий блок CHANGELOG, точное разделение дефолтов в `.env.example` (сверено с контрактом `TaskOrchestratorLocaleEnvVarProcessor` и `FilesystemLocateRoleFileService`).
- [x] `php vendor/bin/todo-md validate todo/TASK-release-v0-6-0-preparation.todo.md` и `make validate-language` зелёные (дополнительно: `make md-links`, `composer validate-docs`).
- [x] Полная релизная проверка зелёная: `composer validate --strict`; `composer audit` без уязвимостей; `make check` — 1527 тестов, 4283 assertions, 2 skipped, PHPStan/Deptrac/Psalm/PHPMD/PHPCS/docs зелёные; `make composer-host-smoke`; `PHAR_EXPECTED_VERSION=dev make phar-smoke`.
- [x] Тег `v0.6.0` и GitHub Release не созданы; рабочее дерево содержит только артефакты релиза (`CHANGELOG.md`, план, `.env.example`) и задачу.

## 6. Самопроверка (Verification)

Проверки документации (зелёные, выполнены 2026-09-10):
```bash
git log v0.5.3..main --oneline
vendor/bin/todo-md validate todo/TASK-release-v0-6-0-preparation.todo.md
make validate-language
make md-links
composer validate-docs
```

Полная релизная проверка (успешна, выполнена 2026-09-10; self-review пройден, повторное ревью Остапа после доработки одобрено):
```bash
composer validate --strict
composer audit                # уязвимостей нет
make check                    # 1527 тестов, 4283 assertions, 2 skipped; PHPStan/Deptrac/Psalm/PHPMD/PHPCS/docs
make composer-host-smoke
PHAR_EXPECTED_VERSION=dev make phar-smoke
```

## 7. Риски и зависимости (Risks and Dependencies)
- Breaking change конфигурации (#380): потребители, не заменившие `APP_LOCALE` на `TASK_ORCHESTRATOR_LOCALE`, после обновления получат язык каталога по умолчанию `en` и англоязычные заголовки skills — миграция обязательна и подробно описана в плане.
- Полное обновление dev-зависимостей (#381) затрагивает `composer.lock` целиком (PHPUnit 10 → 13, Deptrac 2 → 4, coding-standard 0.32 → 0.33, PHPStan 2.1 → 2.2; runtime `symfony/*` в lock выровнен на линию 8.1.x при неизменном ограничении `^8.0` в `require`): риск регрессий инструментария смягчён CI и #382; при сбое — восстановление через hotfix `v0.6.1`.
- Документационная задача: PHPUnit/Psalm не требуются (изменения только в `docs/` и `todo/`), проверки ограничены валидаторами документации и задач.

## 8. Источники (Sources)
- Политика релизов: `docs/releases/RELEASE-POLICY.md`
- Шаблон плана: `vendor/prikotov/git-workflow/docs/git-workflow/templates/release-plan.template.md`
- Образец: `docs/releases/v0.5.3/release-plan.md`
- Запросы: [#380](https://github.com/prikotov/task-orchestrator/pull/380), [#381](https://github.com/prikotov/task-orchestrator/pull/381), [#382](https://github.com/prikotov/task-orchestrator/pull/382)

## 9. Комментарии (Comments)
Пользователь подтвердил работу над релизом `v0.6.0`: коммит, push и создание release PR — штатные шаги подготовки, отдельный запрос не требуется. До слияния release PR и явного подтверждения merge пользователем запрещены тег `v0.6.0` и GitHub Release (сценарий `Release Phar` запускается тегом автоматически). Доработка по замечаниям ревью Остапа выполнена; повторное ревью Остапа после доработки одобрено. Self-review (самопроверка) пройден. Полная релизная проверка успешна: `composer validate --strict`; `composer audit` — уязвимостей нет; `make check` — 1527 тестов, 4283 assertions, 2 skipped (PHPStan/Deptrac/Psalm/PHPMD/PHPCS/docs зелёные); `make composer-host-smoke`; `PHAR_EXPECTED_VERSION=dev make phar-smoke`. По прямому указанию пользователя коммит и push в выполненных заходах не проводились; release PR ещё не создан — `pr` пуст, `status=in_progress`.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-10 15:21:04 (1789053664) | Технический писатель Гермиона (pi) | Создание задачи |
| 2026-09-10 15:21:04 (1789053664) | Технический писатель Гермиона (pi) | Старт: `in_progress`, ветка `task/release-v0-6-0`, назначение на себя |
| 2026-09-10 22:30:49 (1789054249) | Технический писатель Гермиона (pi) | Самопроверка: факт-чек по `git log v0.5.3..main`, `composer.json`/`composer.lock` и PR #380/#381/#382; правки точности (смесь 8.0.x/8.1.0 → линия 8.1.x, «JUnit» → «PHPUnit», неизменность `require`), добавлены «Включённые задачи» и уточнение про PHPDoc `LocateRoleFileServiceInterface`; проверки `todo-md`/`validate-language`/`md-links`/`validate-docs` зелёные |
| 2026-09-10 22:40:17 (1789054817) | Технический писатель Гермиона (pi) | Доработка по замечаниям ревью Остапа: миграция Composer host «добавьте `TASK_ORCHESTRATOR_LOCALE=ru`» с сохранением `APP_LOCALE` при потребителях host-приложения, исправление трёх ссылок на `../../../todo/done/`, сокращение блока `v0.6.0` в `CHANGELOG.md` до краткого release-summary, точное разделение дефолтов в `.env.example`; снято ложное ограничение отдельного запроса на коммит/push (запрет остаётся только для тега/GitHub Release до слияния release PR и подтверждения merge); по указанию пользователя коммит и push в этом заходе не выполнялись |
| 2026-09-10 22:48:43 (1789055323) | Технический писатель Гермиона (pi) | Зафиксированы контрольные точки: self-review пройден; повторное ревью Остапа после доработки одобрено; полная релизная проверка успешна (`composer validate --strict`; `composer audit` без уязвимостей; `make check` — 1527 тестов, 4283 assertions, 2 skipped, PHPStan/Deptrac/Psalm/PHPMD/PHPCS/docs зелёные; `make composer-host-smoke`; `PHAR_EXPECTED_VERSION=dev make phar-smoke`); PR не создан — `pr` пуст, статус `in_progress`; коммит и push по указанию пользователя не выполнялись |
