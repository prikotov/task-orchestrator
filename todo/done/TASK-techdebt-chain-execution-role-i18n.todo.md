---
type: chore
created: 2026-07-25
value: V1
complexity: C2
priority: P3
depends_on:
epic:
author: Тимлид Алекс
assignee: Бэкендер Левша
branch: task/chain-execution-role-i18n
pr: '#320'
status: done
---

# TASK-techdebt-chain-execution-role-i18n: Локаль-зависимый выбор role-file в ChainExecution

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
В проекте два независимых резолвера role-файла: `FilesystemLocateRoleFileService` (модуль AgentRole, путь `become-role`) и `RolePromptBuilderService` (модуль ChainExecution, путь запуска цепочки с `--system-prompt @role`). Первый уже переведён на env `APP_LOCALE` (задача `TASK-feat-agent-role-i18n-locale`), а второй держит локаль захардкоженной: `private const DEFAULT_LOCALE = 'ru'` и `buildDefaultLocaleFilePath()` строит исключительно `<role>.ru.md`. Из-за этого локаль-механика расходится между модулями: при `APP_LOCALE=en` и ролях только в `.en.md` команда `become-role` отработает, а запуск цепочки упадёт с `RoleNotFoundException`.

### Варианты или путь решения (Solution Sketch)
Завести в `RolePromptBuilderService` локаль через DI-параметр `task_orchestrator.locale` и унифицировать семантику выбора файла с `FilesystemLocateRoleFileService`: приоритет `<role>.<locale>.md` → `<role>.md` → любой доступный перевод. Покрыть тестами на локали (ru/en/zh) и fallback.

### Ожидаемый результат (Expected Result)
Один env `APP_LOCALE` управляет выбором role-файла во ВСЕХ точках (AgentRole + ChainExecution). Запуск цепочки с ролью и `become-role` резолвят файл роли по одной логике. `phpunit`/`psalm`/`deptrac` зелёные, без регрессий.

## 1. Concept and Goal (Концепция и Цель)

### Goal (Цель по SMART)
Унифицировать локаль-зависимый выбор role-файла между модулями AgentRole и ChainExecution: перевести `RolePromptBuilderService` на `task_orchestrator.locale` (env `APP_LOCALE`) с fallback-цепочкой, идентичной `FilesystemLocateRoleFileService`. Локаль — единый источник истины для обоих use case'ов.

> ⚠️ **Это не баг, а осознанный техдолг.** В `TASK-feat-agent-role-i18n-locale` scope был намеренно ограничен модулем AgentRole (`become-role`). ChainExecution — отдельный use case (запуск цепочек), его правка вынесена сюда, чтобы не раздувать scope исходного PR и не менять поведение запуска цепочек без отдельного тестирования.

## 2. Context and Scope (Контекст и Границы)

* **Где делаем:** `src/Module/ChainExecution/Infrastructure/Service/Prompt/RolePromptBuilderService.php` (конструктор, `loadCache()`, `buildDefaultLocaleFilePath()`, `getPromptFilePath()`), DI-конфигурация модуля ChainExecution, тесты модуля.
* **Текущее поведение:** `RolePromptBuilderService` использует `private const DEFAULT_LOCALE = 'ru'`; кэш ролей грузит через glob `*.ru.md`; fallback на `<role>.ru.md` безneutral `<role>.md` и без других локалей.
* **Зависимость:** выполнена после merge PR задачи `TASK-feat-agent-role-i18n-locale` (введён параметр `task_orchestrator.locale`).
* **Границы (Out of Scope):**
  - Не меняем архитектуру модуля ChainExecution.
  - Не трогаем остальные PromptProvider'ы (только role-file).
  - Перевод самих ролей (role-files) — вне механики.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [x] `RolePromptBuilderService` принимает локаль через DI (`task_orchestrator.locale`), без захардкоженного `DEFAULT_LOCALE`.
- [x] Унифицированная fallback-цепочка выбора файла: `<role>.<locale>.md` → `<role>.md` → любой `<role>.*.md` (как в `FilesystemLocateRoleFileService`).
- [x] Тесты: ru/en/zh + fallback (locale-файл отсутствует → neutral → glob).

### ⟫ Won't Have (Не будем делать)

- Локализация prompt-контента ролей — это задача авторов контента.
- Смена интерфейса `RolePromptBuilderServiceInterface` (если не требуется для DI).

## 4. Implementation Plan (План реализации)

*Заполняется исполнителем перед стартом.*

1. [x] Убрать `DEFAULT_LOCALE`, добавить `string $locale` в конструктор `RolePromptBuilderService`, нормализация strtolower.
2. [x] Переработать `loadCache()` / `getPromptFilePath()` на fallback-цепочку по локали.
3. [x] DI: передать `%task_orchestrator.locale%` в определении сервиса.
4. [x] Тесты на локали и fallback.
5. [x] Проверки: `vendor/bin/phpunit`, `vendor/bin/psalm`, `make deptrac` — зелёные.

## 5. Definition of Done (Критерии приёмки)

- [x] `RolePromptBuilderService` резолвит role-файл по `APP_LOCALE` с fallback как `FilesystemLocateRoleFileService`.
- [x] При `APP_LOCALE=en` и ролях в `.en.md` запуск цепочки работает (не падает `RoleNotFoundException`).
- [x] Снять метку `@techdebt` с `RolePromptBuilderService` (см. Sources).
- [x] `vendor/bin/phpunit`, `vendor/bin/psalm`, `make deptrac` — зелёные, без регрессий.

## 6. Verification (Самопроверка)

```bash
vendor/bin/phpunit tests/Unit/Module/ChainExecution/ tests/Integration/Module/ChainExecution/
vendor/bin/phpunit
vendor/bin/psalm
make deptrac
```

## 7. Risks and Dependencies (Риски и зависимости)

- **Зависимость:** PR задачи `TASK-feat-agent-role-i18n-locale` слит (параметр `task_orchestrator.locale` в Kernel).
- **Риск регрессии:** `loadCache()` кэширует список ролей — изменение логики glob может повлиять на `getAvailableRoles()`. Mitigation: покрыть тестом.
- **Семантика fallback:** унификация с AgentRole может изменить видимый список доступных ролей (раньше только `.ru.md`). Зафиксировать в тестах.

## 8. Sources (Источники)

- Code review Ревьювера Бэка Пуаро по задаче `TASK-feat-agent-role-i18n-locale` (major CR #1: расхождение локаль-механики между модулями).
- `src/Module/AgentRole/Infrastructure/Service/FilesystemLocateRoleFileService.php` — эталонная fallback-цепочка.
- `src/Module/ChainExecution/Infrastructure/Service/Prompt/RolePromptBuilderService.php` (`@techdebt`-метка).

## 9. Comments (Комментарии)

Задача вынесена из PR `TASK-feat-agent-role-i18n-locale` по итогам code review Пуаро, чтобы:
1. Не раздувать scope исходного PR (In-Scope там был ограничен AgentRole/`become-role`).
2. Не менять поведение запуска цепочек без отдельного тестирования.
3. Соблюсти AGENTS.md: «Масштабный рефакторинг или смена архитектуры — только отдельным PR с обоснованием».

## Change History (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-25 | Тимлид Алекс | Создание задачи по итогам code review Пуаро (major CR #1 по `TASK-feat-agent-role-i18n-locale`). Расхождение локаль-механики между AgentRole и ChainExecution вынесено в отдельный techdebt. |
| 2026-07-25 | Тимлид Алекс | Реализация (Бэкендер Левша) → self-review → code review (Ревьювер Бэка Пуаро, conditional→resolved) завершены. PR #320. Задача переведена в `done`, файл перенесён в `todo/done/`. |
