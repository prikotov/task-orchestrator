---
type: refactor
created: 2026-09-09 15:54:39 (1788969279)
due: 
started: 2026-09-09 15:57:40 (1788969460)
completed: 2026-09-09 17:15:14 (1788974114)
cancelled: 
value: V3
complexity: C3
priority: P1
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Аналитик Шерлок (codex)
assignee: Бэкендер Левша (codex)
branch: task/refactor-task-orchestrator-locale
pr: https://github.com/prikotov/task-orchestrator/pull/380
status: done
---

# TASK-refactor-task-orchestrator-locale: Отделить локаль AI-ролей task-orchestrator от локали host-project

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Сейчас task-orchestrator использует `APP_LOCALE` одновременно как язык собственных AI-ролей и каталога навыков и как `framework.default_locale` (стандартную локаль Symfony). В Composer-host (проекте, подключившем пакет через Composer) это создаёт ложную связь с локалью host-project и вводит пользователя в заблуждение. Кроме того, выбранная локаль попадает в скомпилированный контейнер: после первого запуска смена языка может не изменить role file (файл роли) без ручной очистки кеша.

### Варианты или путь решения (Solution Sketch)
Ввести отдельный публичный параметр окружения `TASK_ORCHESTRATOR_LOCALE` для role files (файлов ролей) и каталога skills (навыков), сделать его единым источником для модулей `AgentRole` и `ChainExecution` и устранить зависимость от `APP_LOCALE`. Обеспечить корректную смену локали при повторном запуске с тем же корнем кеша. `framework.default_locale` собственного Symfony Kernel (ядра Symfony) оставить независимой стандартной настройкой Symfony.

### Ожидаемый результат (Expected Result)
Пользователь независимо управляет языком AI-ролей task-orchestrator через `TASK_ORCHESTRATOR_LOCALE`; локаль приложения host-project и Symfony-переводчика не меняет выбор role file. Переход `en` → `ru` применяется на следующем запуске без ручного удаления кеша.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **Job Story:** Когда task-orchestrator установлен в host-project через Composer, я хочу задавать язык его AI-ролей отдельной переменной `TASK_ORCHESTRATOR_LOCALE`, чтобы локаль ролей не конфликтовала с локалью приложения и предсказуемо менялась без ручной очистки кеша.

### Цель по SMART (Goal)
В рамках этой задачи заменить публичный контракт локали ролей с `APP_LOCALE` на `TASK_ORCHESTRATOR_LOCALE` во всех точках `AgentRole` и `ChainExecution`, сохранить default (значение по умолчанию) `en`, развязать его с `framework.default_locale`, устранить stale-cache (устаревший кеш) при смене локали и зафиксировать поведение модульными/интеграционными тестами, включая Composer-host regression test (регрессионный тест) `en` → `ru` при неизменном корне кеша.

## 2. Контекст и Границы (Context and Scope)

* **Где делаем:**
  * [`src/Kernel.php`](../../src/Kernel.php) — получение и публикация `task_orchestrator.locale`, а также cache-sensitive (зависящее от кеша) поведение Kernel;
  * [`src/Module/AgentRole/`](../../src/Module/AgentRole) — выбор локализованного role file и язык заголовков каталога skills;
  * [`src/Module/ChainExecution/`](../../src/Module/ChainExecution) — выбор role file при построении prompt (запроса для AI);
  * [`config/packages/translation.yaml`](../../config/packages/translation.yaml) — независимая стандартная настройка `framework.default_locale` task-orchestrator;
  * [`tests/`](../../tests) — модульные, интеграционные и Composer-host regression tests;
  * публичная документация и примеры окружения: [`AGENTS.md`](../../AGENTS.md), `README*.md`, [`docs/`](../../docs), [`.env.example`](../../.env.example), существующий `.env.dist` при его наличии, [`CHANGELOG.md`](../../CHANGELOG.md).
* **Текущее поведение:** `Kernel::resolveLocale()` читает `APP_LOCALE` с default `en` и сохраняет результат в `task_orchestrator.locale`; этот параметр внедряется в `AgentRole` и `ChainExecution`. Одновременно `config/packages/translation.yaml` назначает `APP_LOCALE` в `framework.default_locale`. При повторном запуске Composer-host с тем же cache root (корнем кеша) скомпилированный контейнер может сохранить прежний `task_orchestrator.locale` и выбрать прежний role file.
* **Целевой контракт:**
  * `TASK_ORCHESTRATOR_LOCALE` — единственный источник локали AI-ролей для `AgentRole` и `ChainExecution`;
  * незаданное или пустое значение даёт `en`, сохраняя текущую нормализацию регистра;
  * `APP_LOCALE` не является публичным контрактом task-orchestrator и не влияет на AI-роли;
  * `framework.default_locale` task-orchestrator настраивается независимо штатным механизмом Symfony и не подменяется локалью AI-ролей.
* **Границы (Out of Scope):**
  * не менять правило разрешения относительных ссылок, указанных внутри role file; это отдельная проблема и должна оформляться отдельной задачей;
  * не менять fallback-chain (цепочку резервного выбора) role files, кроме замены источника локали и необходимого исправления кеширования;
  * не связывать `TASK_ORCHESTRATOR_LOCALE` с `framework.default_locale` host-project или собственного Kernel;
  * не добавлять новые локализации, не переводить содержимое role files и skills;
  * не выполнять побочный рефакторинг модулей и не добавлять зависимости.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [x] Ввести `TASK_ORCHESTRATOR_LOCALE` как документированный публичный параметр окружения с default `en` для языка role files и каталога skills.
- [x] `task_orchestrator.locale`, `AgentRole` (включая выбор role file и форматирование каталога skills) и `ChainExecution` получают локаль из одного источника — `TASK_ORCHESTRATOR_LOCALE`.
- [x] Удалить использование `APP_LOCALE` как контракта task-orchestrator: значение `APP_LOCALE` не должно влиять на `task_orchestrator.locale`, выбор role file или язык каталога skills.
- [x] Не добавлять скрытый fallback (резервный переход) `TASK_ORCHESTRATOR_LOCALE` → `APP_LOCALE`. Отсутствующий или пустой `TASK_ORCHESTRATOR_LOCALE` приводит к `en`, даже если задан `APP_LOCALE`.
- [x] Оставить `framework.default_locale` собственного task-orchestrator независимой стандартной Symfony-настройкой; не направлять в неё `TASK_ORCHESTRATOR_LOCALE` и не описывать её как локаль AI-ролей.
- [x] Исправить подтверждённый stale-cache баг: после запуска с `TASK_ORCHESTRATOR_LOCALE=en` следующий запуск с `TASK_ORCHESTRATOR_LOCALE=ru` выбирает `.ru.md` role file без ручной очистки кеша.
- [x] Добавить Composer-host regression test, который использует один host-project и стабильный cache root, последовательно запускает task-orchestrator с локалями `en`, затем `ru`, и подтверждает смену реально выбранного role file/его содержимого на русскую версию.
- [x] Тестом подтвердить согласованность `AgentRole` и `ChainExecution`: при одной `TASK_ORCHESTRATOR_LOCALE` обе точки выбирают одну локализованную версию роли.
- [x] Тестами подтвердить default `en`, нормализацию поддерживаемого значения локали и отсутствие влияния `APP_LOCALE` на локаль ролей.
- [x] Явно оформить обратную совместимость как breaking configuration change (несовместимое изменение конфигурации): обновить migration note (указание по переходу) и запись в `CHANGELOG.md`, предписав заменить `APP_LOCALE` на `TASK_ORCHESTRATOR_LOCALE`; иной подход допустим только после отдельного согласования и с зафиксированным обоснованием в задаче.
- [x] Выполнить фактический поиск по репозиторию и обновить активные упоминания контракта во всём коде, PHPDoc, комментариях, тестах, [`AGENTS.md`](../../AGENTS.md), `README*.md`, [`docs/`](../../docs), [`.env.example`](../../.env.example) и существующем `.env.dist`. Исторические упоминания допускаются только там, где они нужны для описания прежнего контракта или миграции и не выглядят как действующая инструкция.
- [x] Обновить все затронутые примеры конфигурации: русское поведение задаётся `TASK_ORCHESTRATOR_LOCALE=ru`, а отсутствие переменной сохраняет английский default.
- [x] Все изменения соответствуют [Конвенциям](../../docs/conventions/index.md); модульные границы и существующие публичные контракты, не относящиеся к локали, не меняются.

### 🟡 Желательно (Should Have)
- [x] Названия тестов и поясняющие комментарии явно различают Symfony locale (локаль Symfony) и agent-role locale (локаль AI-ролей), чтобы связь не появилась повторно.
- [x] Проверка остаточных упоминаний `APP_LOCALE` включена в отчёт о реализации с классификацией каждого сохранённого совпадения.

### 🟢 Опционально (Could Have)
- [ ] Добавить отдельный тест, подтверждающий, что изменение стандартного `framework.default_locale` не меняет выбранный role file.

### ⚫ Won't Have (Не будем делать)
- [ ] Не добавлять обратную совместимость через неявное чтение `APP_LOCALE`.
- [ ] Не связывать язык AI-ролей с `framework.default_locale` task-orchestrator или host-project.
- [ ] Не включать изменение правил resolve (разрешения) ссылок внутри role file; оформить его отдельной задачей при необходимости.
- [ ] Не менять алгоритм fallback между `<role>.<locale>.md`, `<role>.md` и другими переводами сверх необходимого для нового источника локали.
- [ ] Не изменять production-код вне описанного локального рефакторинга локали и кеша.

## 4. План реализации (Implementation Plan)

1. [x] Выполнить `rg` по `APP_LOCALE`, `task_orchestrator.locale`, `framework.default_locale` и связанным описаниям локали; составить полный список затронутых точек до правок.
2. [x] Обновить получение locale (локали) в собственном `Kernel`: `TASK_ORCHESTRATOR_LOCALE` с default `en`, без чтения `APP_LOCALE`; сохранить единый DI-параметр `task_orchestrator.locale` для `AgentRole` и `ChainExecution`.
3. [x] Развязать `config/packages/translation.yaml` и `framework.default_locale` с локалью AI-ролей, оставив штатную независимую Symfony-конфигурацию.
4. [x] Устранить зависимость выбранной локали от stale compiled container (устаревшего скомпилированного контейнера) так, чтобы смена env при стабильном cache root применялась без ручной очистки; не ослаблять существующую изоляцию кеша Composer-host по версии пакета и окружению.
5. [x] Обновить PHPDoc/комментарии и тесты `AgentRole`, `ChainExecution`, Kernel wiring (связывания Kernel), defaults (значений по умолчанию) и независимости от `APP_LOCALE`.
6. [x] Добавить Composer-host regression test с двумя локализованными role files и последовательностью `TASK_ORCHESTRATOR_LOCALE=en` → `ru` при одном cache root; проверять наблюдаемый выбранный файл или уникальное содержимое, а не только значение параметра контейнера.
7. [x] Обновить `AGENTS.md`, `README*.md`, `docs/`, `.env.example`/существующий `.env.dist` и `CHANGELOG.md`; явно описать несовместимое изменение и переход на новое имя.
8. [x] Повторить фактический поиск по старому имени, классифицировать допустимые исторические/миграционные совпадения и выполнить все проверки задачи.

## 5. Критерии приёмки (Definition of Done)

- [x] При незаданном `TASK_ORCHESTRATOR_LOCALE` обе подсистемы выбирают английскую локаль `en`.
- [x] При `TASK_ORCHESTRATOR_LOCALE=ru` `AgentRole` и `ChainExecution` выбирают соответствующий `<role>.ru.md`, а каталог skills использует русские локализованные заголовки.
- [x] При заданном только `APP_LOCALE=ru` локаль AI-ролей остаётся `en`; скрытого fallback нет.
- [x] `TASK_ORCHESTRATOR_LOCALE` не изменяет `kernel.default_locale`, а стандартная Symfony-локаль не изменяет `task_orchestrator.locale` и выбор role file.
- [x] Composer-host regression test воспроизводит два последовательных запуска `en` → `ru` с одинаковыми host root и cache root и проходит без удаления кеша между запусками.
- [x] Тест проверяет наблюдаемый результат: после смены env выбран русский role file/русское уникальное содержимое, а не только новое значение параметра.
- [x] `APP_LOCALE` отсутствует в действующих инструкциях и реализации task-orchestrator; каждое оставшееся совпадение относится только к документированию прежнего контракта, миграции или отрицательному regression test.
- [x] `CHANGELOG.md` и пользовательская документация явно называют изменение несовместимым и содержат инструкцию `APP_LOCALE` → `TASK_ORCHESTRATOR_LOCALE`.
- [x] Все затронутые модульные и интеграционные тесты, полный PHPUnit, Psalm и архитектурная проверка проходят.
- [x] Production-код, не относящийся к локали/кешу, не изменён; правило ссылок внутри role file не затронуто.

## 6. Самопроверка (Verification)

```bash
rg -n --hidden --glob '!vendor/**' --glob '!var/**' 'APP_LOCALE|TASK_ORCHESTRATOR_LOCALE|framework\.default_locale|task_orchestrator\.locale' .
vendor/bin/phpunit tests/Unit/Module/AgentRole/ tests/Unit/Module/ChainExecution/ tests/Integration/DependencyInjection/KernelIntegrationTest.php
vendor/bin/phpunit
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
php vendor/bin/todo-md validate todo/TASK-refactor-task-orchestrator-locale.todo.md
```

## 7. Риски и зависимости (Risks and Dependencies)

- **Breaking change:** host-project, который задавал `APP_LOCALE` ради русских AI-ролей, после обновления получит default `en`, пока не перейдёт на `TASK_ORCHESTRATOR_LOCALE=ru`. Это намеренное подтверждённое изменение; скрытая совместимость запрещена.
- **Stale compiled container:** простая замена имени env в `Kernel::resolveLocale()` не гарантирует исправления, если значение по-прежнему запекается в кеш контейнера. Приёмка требует проверки второго запуска на том же кеше.
- **Ложноположительный тест:** проверка только `task_orchestrator.locale` может пройти, не доказав смену role file. Нужна проверка пути или различимого содержимого выбранного файла через реальную точку Composer-host.
- **Два потребителя локали:** `AgentRole` и `ChainExecution` имеют отдельную логику выбора файлов; изменение только одной подсистемы создаст расхождение между `become-role` и orchestration (оркестрацией).
- **Разделение локалей:** изменение `config/packages/translation.yaml` может случайно поменять стандартное поведение Symfony-переводчика. Его независимость должна быть явно покрыта тестом.
- **Документационный охват:** исторические release notes (примечания к выпуску) могут законно содержать `APP_LOCALE`; их нельзя механически переписать так, чтобы исказить историю, но действующий контракт и migration note должны быть однозначны.
- Внешних зависимостей и миграций данных нет.

## 8. Источники (Sources)

- [Kernel и текущий источник локали](../../src/Kernel.php)
- [Symfony translation configuration](../../config/packages/translation.yaml)
- [AgentRole service wiring](../../src/Module/AgentRole/Resource/config/services.yaml)
- [ChainExecution service wiring](../../src/Module/ChainExecution/Resource/config/services.yaml)
- [Текущие Kernel integration tests](../../tests/Integration/DependencyInjection/KernelIntegrationTest.php)
- [Публичные инструкции проекта](../../AGENTS.md)
- [Конвенции проекта](../../docs/conventions/index.md)

## 9. Комментарии (Comments)

Подтверждённое решение пользователя: локаль AI-ролей принадлежит task-orchestrator и не является локалью host-project. У task-orchestrator собственный `Kernel` и собственная Symfony-конфигурация, поэтому `TASK_ORCHESTRATOR_LOCALE` должен быть самостоятельным контрактом. Решение об отсутствии fallback на `APP_LOCALE` принято осознанно.

Если при реализации обнаружится отдельный дефект разрешения относительных ссылок из role file, его нужно зафиксировать новой задачей без расширения scope этой задачи.

## История изменений (Change History)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-09 15:54:39 (1788969279) | Аналитик Шерлок (codex) | Создание и полная формулировка задачи по подтверждённому решению пользователя |
| 2026-09-10 | Бэкендер Левша (codex) | Доработка по minor-замечаниям ревью: (1) `ComposerHostLocaleRegressionTest` — исправлены 14 новых PHPCS-ошибок в `isolateEnvVar` (вызовы глобальных функций `array_key_exists`/`getenv`/`putenv` без ведущего бэкслеша, автофикс PHPCBF), существующий долг вне diff не тронут; (2) `docs/agents/skills/become-role/README.md` — устаревший hardcoded-приоритет `<role>.ru.md` заменён на актуальный контракт: `<role>.<locale>.md` под локаль AI-ролей из env `TASK_ORCHESTRATOR_LOCALE` (default `en`) → локаль-нейтральный `<role>.md` → любой доступный перевод (соответствует `FilesystemLocateRoleFileService` и обновлённому `AGENTS.md`). Проверки: PHPCS по тесту чист; целевые тесты (`ComposerHostLocaleRegressionTest` + `KernelIntegrationTest` + `tests/Unit/Component/Locale/`) — OK (16 тестов, 64 assertions); полный `make check` зелёный (PHPUnit 1525 / 2 штатных skip, validate-language warning mode только в исторических release-планах). Постановка задачи не менялась. |
| 2026-09-10 | Бэкендер Левша (codex) | Реализация: `TaskOrchestratorLocaleEnvVarProcessor` (env `TASK_ORCHESTRATOR_LOCALE`, default `en`, trim + lower-case, без fallback на `APP_LOCALE`) как сквозной компонент `src/Component/Locale/`; `Kernel::getKernelParameters()` задаёт `task_orchestrator.locale` runtime-env-плейсхолдером `%env(task_orchestrator_locale:TASK_ORCHESTRATOR_LOCALE)%` (значение не запекается в скомпилированный контейнер); `Kernel::resolveLocale()` удалён; `config/packages/translation.yaml` — независимый `framework.default_locale: en` без env; PHPDoc/комментарии `AgentRole`/`ChainExecution` обновлены. Тесты: `TaskOrchestratorLocaleEnvVarProcessorTest` (unit, контракт нормализации), `KernelIntegrationTest` (default/follow/независимость от `APP_LOCALE`/смена без очистки кеша), `ComposerHostLocaleRegressionTest` (один host root + стабильный cache root, два последовательных запуска `en`→`ru`, наблюдаемый role file обеих подсистем + язык каталога skills + уникальные маркеры содержимого). Документация: `AGENTS.md`, `.env.example`, `docs/guide/extension.md` (шаг «Добавление новой роли»), `CHANGELOG.md` (два breaking change + stale-cache фикс + migration note). Опциональный отдельный тест «`framework.default_locale` не меняет role file» не потребовался: независимость в обоих направлениях уже доказана комбинацией `agentRoleLocaleFollowsTaskOrchestratorLocaleEnv` (при `kernel.default_locale=en` и `TASK_ORCHESTRATOR_LOCALE=ru` локаль ролей `ru`) и Composer-host regression (наблюдаемый файл). Проверки: `make check` зелёный (PHPStan, Deptrac, Psalm, PHPMD, PHPCS, md-links, validate-todo, validate-roles, validate-language — warning mode только в исторических release-планах, PHPUnit 1525 тестов / 2 штатных skip PharSmokeScriptTest при локальном Box); ручная верификация runtime: скомпилированный prod-контейнер содержит `'task_orchestrator.locale' => $container->getEnv('task_orchestrator_locale:TASK_ORCHESTRATOR_LOCALE')` (не литерал); изолированный host в env `prod`/debug=false — смена `en`→`ru` на том же корне кеша меняет параметр и выбранный файл обеих подсистем; физический Composer-host (`vendor/bin/task-orchestrator`, path repository без symlink) — то же через CLI `agent:role-skills`; `bin/composer-host-smoke` — OK. Остаточные `APP_LOCALE`: только миграционные/исторические (CHANGELOG [Unreleased] миграция, [0.3.0] история; `docs/releases/v0.3.0`; текст самой задачи; `todo/done/*` архив; отрицательные regression-упоминания в тестах/PHPDoc) — действующих инструкций нет. `.env.dist` в репозитории отсутствует. |
