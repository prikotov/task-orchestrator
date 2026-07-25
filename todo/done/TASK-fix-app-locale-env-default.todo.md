---
type: fix
created: 2026-07-25
value: V1
complexity: C1
priority: P2
depends_on:
epic:
author: Архитектор Гэндальф
assignee: Архитектор Гэндальф
branch: task/fix-app-locale-env-default
pr: https://github.com/prikotov/task-orchestrator/pull/318
status: done
---

# TASK-fix-app-locale-env-default: Корректный env-default для APP_LOCALE

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
`config/packages/translation.yaml` использовал `%env(default:en:APP_LOCALE)%`.
Symfony-процессор `default:fallback:VAR` трактует `fallback` как имя
container-параметра, а не литерал → бросал `Invalid env fallback ... parameter
"en" not found` при чтении локали. Баг латентный (env разрешается лениво) и
проявлялся в host-проектах (task-orchestrator как зависимость), ломая
`become-role` и любые пути, читающие `framework.default_locale`.

### Варианты или путь решения (Solution Sketch)
Перейти на идиоматичный env-default: `parameters.env(APP_LOCALE): en` +
`%env(APP_LOCALE)%`. Покрыть kernel-boot-регрессией.

### Ожидаемый результат (Expected Result)
Ядро пакета загружается в host-контексте без исключения; `APP_LOCALE` не задан
→ `en`, задан → значение. `make check` зелёный.

## 1. Concept and Goal (Концепция и Цель)

### Goal (Цель по SMART)
Устранить латентный env-баг в `translation.yaml`, чтобы task-orchestrator как
зависимость корректно загружал ядро при любом (или отсутствии) `APP_LOCALE`.
Проверки `make check` зелёные.

## 2. Context and Scope (Контекст и Границы)

- **Где делаем:** `config/packages/translation.yaml`, `tests/Integration/DependencyInjection/KernelIntegrationTest.php`.
- **Текущее поведение:** `default:en:APP_LOCALE` бросал "parameter 'en' not found".
- **Границы (Out of Scope):** локаль-зависимое поведение become-role — отдельная задача `TASK-feat-agent-role-i18n-locale` (промотирована в `todo/`).

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have
- [x] `APP_LOCALE` разрешается без исключения (idiomatic env-default).
- [x] Default `en` при незаданном `APP_LOCALE`.
- [x] Регрессионные тесты kernel-boot (fallback + override).

## 4. Implementation Plan (План реализации)
1. [x] Заменить `default:en:APP_LOCALE` на `parameters.env(APP_LOCALE): en` + `%env(APP_LOCALE)%`.
2. [x] Добавить тесты `frameworkDefaultLocaleFallsBackToEnWhenAppLocaleUnset` + `frameworkDefaultLocaleFollowsAppLocaleEnv` + helper изоляции env.

## 5. Definition of Done (Критерии приёмки)
- [x] Воспроизведение до фикса подтверждено; после фикса — не воспроизводится.
- [x] unset→`en`, `ru`→`ru`, `zh`→`zh`; translator конструируется без исключения.
- [x] `make check` зелёный (phpstan+deptrac+psalm+phpmd+phpcs+md-links+validate-todo+validate-roles+1464 теста).

## 6. Verification (Самопроверка)
```bash
make check
```

## 7. Risks and Dependencies (Риски и зависимости)
- Фикс в исходнике; до host-проектов доходит следующим релизом + `composer update prikotov/task-orchestrator`.
- Разблокирует `TASK-feat-agent-role-i18n-locale` (env-default предусловие выполнено).

## 8. Sources (Источники)
- `vendor/symfony/dependency-injection/EnvVarProcessor.php` (процессор `default`).
- `config/packages/framework.yaml` (корректное использование `default:<param>:VAR`).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-25 | Архитектор Гэндальф | Создание задачи и выполнение: диагностирован латентный env-баг `APP_LOCALE`, фикс + kernel-boot регрессия. |
