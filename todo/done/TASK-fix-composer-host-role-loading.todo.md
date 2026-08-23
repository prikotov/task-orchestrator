---
type: fix
created: 2026-08-23 14:08:08 (1787494088)
due: 
started: 2026-08-23 14:09:19 (1787494159)
completed: 2026-08-23 14:27:15 (1787495235)
cancelled: 
value: V3
complexity: C3
priority: P1
cost_plan: 20000
cost_fact: 
depends_on: 
epic: 
author: Бэкендер Левша (pi)
assignee: Бэкендер Левша (pi)
branch: task/fix-composer-host-role-loading
pr: https://github.com/prikotov/task-orchestrator/pull/371
status: done
---

# TASK-fix-composer-host-role-loading: Исправить загрузку ролей в Composer-host после обновления пакета

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- После обновления `task-orchestrator` роль host-проекта может не загрузиться: навык теряет корень проекта при переходе по симлинку, а CLI использует устаревший контейнер Symfony. Агент остаётся без требуемой личности и навыков и тратит время на ложную диагностику имени роли.

### Варианты или путь решения (Solution Sketch)
- Сохранить явный корень host-проекта при запуске установленного навыка и изолировать кеш CLI от приложения и предыдущих версий пакета.
- Защитить оба сценария воспроизводимыми Composer-host integration tests (интеграционными тестами).

### Ожидаемый результат (Expected Result)
- Установленный через Composer навык загружает host-only роль из рекомендованного каталога сразу после обновления пакета, без ручной очистки кеша.

## 1. Концепция и Цель (Concept and Goal)

### История (Job Story)
> Когда Composer-пакет обновлён и агент входит в роль host-проекта через `become-role`, я хочу получить роль и её skills (навыки) без ручной очистки кеша, чтобы рабочая сессия начиналась предсказуемо.

### Цель по SMART (Goal)
- В текущей итерации исправить dual-context resolution (разрешение путей в двух контекстах) и кеш контейнера CLI; подтвердить результат end-to-end тестом (сквозным тестом) физической Composer-установки с host-only ролью и имитацией устаревшего кеша.

## 2. Контекст и Границы (Context and Scope)
- **Где делаем:** `docs/agents/skills/become-role/scripts/become-role.sh`, `src/Kernel.php`, Composer-host integration tests и связанная пользовательская документация по фактически изменённому контракту.
- **Текущее поведение:** запуск из `.agents/skills/become-role` физически переносит `CWD` в `vendor/`, поэтому CLI ищет роли пакета; кеш `<host>/var/cache/prod` переживает обновление пакета и может содержать несовместимый DI-контейнер.
- **Границы (Out of Scope):** не менять формат role-файлов, каталог skills, публичные форматы `agent:role-skills`, PHAR-установку навыка и состав ролей host-проекта; выпуск релиза оформить отдельно.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] `become-role` сохраняет корень Composer-host при рекомендованном запуске из каталога навыка, включая симлинк в `vendor/`.
- [x] CLI использует собственный кеш, который не конфликтует с кешем host-приложения и не переиспользует несовместимый контейнер после обновления пакета.
- [x] Composer-host smoke test (дымовой тест) запускает навык из skill root (корня навыка) для роли, отсутствующей в пакете.
- [x] Regression test (регрессионный тест) доказывает безопасное поведение при устаревшем кеше предыдущей версии.
- [x] Документация синхронизирована с фактическим контрактом запуска и кеша.
### ⚫ Won't Have (Не будем делать)
- Не добавлять новые зависимости и резервные способы поиска ролей.
- Не очищать целиком `var/cache` host-приложения.
- Не менять публичный JSON/XML-контракт `agent:role-skills`.
- Не расширять поддержку PHAR в рамках этой задачи.

## 4. План реализации (Implementation Plan)
1. [x] Уточнить текущий distribution test harness (контур тестирования дистрибутива) и выбрать явный контракт передачи host-root.
2. [x] Сначала добавить падающие Composer-host тесты для запуска из skill root и устаревшего контейнера.
3. [x] Исправить запуск `become-role` и изоляцию/инвалидацию кеша минимальными изменениями.
4. [x] Обновить все упоминания изменённого контракта в документации.
5. [x] Выполнить целевые и полные проверки, зафиксировать результат в задаче.

## 5. Критерии приёмки (Definition of Done)
- [x] В физически установленном Composer-host команда из `SKILL.md` загружает host-only роль и её skills.
- [x] Повторный запуск после смены релизной версии пакета не использует несовместимый с кодом контейнер.
- [x] Кеш task-orchestrator не записывается в общий `<host>/var/cache/<env>`.
- [x] PHPUnit, Psalm и Deptrac проходят без ошибок.
- [x] Задача и документация проходят валидаторы.

## 6. Самопроверка (Verification)
```bash
vendor/bin/phpunit tests/Integration/Docs/Agents/Skills/BecomeRole/BecomeRoleScriptTest.php
make composer-host-smoke
PHAR_EXPECTED_VERSION=dev make phar-smoke
make check
php vendor/bin/todo-md validate todo/TASK-fix-composer-host-role-loading.todo.md
```

## 7. Риски и зависимости (Risks and Dependencies)
- Логический путь `.agents/skills/become-role` является каноническим источником host-root; физический путь симлинка используется только для поиска CLI пакета.
- Версионирование кеша должно работать в source, Composer и PHAR без записи в read-only package root.
- Изменение cache path (пути кеша) может оставить старые файлы, но не должно удалять данные host-приложения.

## 8. Источники (Sources)
- [`become-role` skill](../../docs/agents/skills/become-role/SKILL.md)
- [Архитектура dual-context](../../docs/guide/architecture.md#di-infrastructure)
- [Исходная задача механизма](TASK-feat-become-role-skills.todo.md)

## 9. Комментарии (Comments)
- Инцидент воспроизведён в `/home/dp/MyProjects/TasK/Sandbox/codexcli` на `prikotov/task-orchestrator v0.5.0`: запуск из skill root ищет роль в `vendor`, а прямой запуск из host-root падает на устаревшем DI-контейнере. С временным чистым `APP_CACHE_DIR` прямой запуск успешно загружает `team_lead_aragorn.en.md`.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-23 14:08:08 (1787494088) | Бэкендер Левша (pi) | Создание задачи |
| 2026-08-23 14:20:02 (1787494802) | Бэкендер Левша (pi) | Реализация завершена локально; Composer-host smoke, PHAR smoke и `make check` проходят |
