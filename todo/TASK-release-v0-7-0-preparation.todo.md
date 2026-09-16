---
type: chore
created: 2026-09-16 10:21:10 (1789528870)
due: 
started: 2026-09-16 10:21:10 (1789528870)
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
branch: task/release-v0-7-0
pr: 
status: in_progress
---

# TASK-release-v0-7-0-preparation: Подготовить release PR для v0.7.0

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
После `v0.6.0` в `main` добавлена новая возможность установки `become-role` из PHAR и исправлен ложный код завершения `run-subagent`, но опубликованный релиз этих изменений не содержит.

### Варианты или путь решения (Solution Sketch)
Подготовить `CHANGELOG.md`, план релиза и release PR из ветки `task/release-v0-7-0` по проектной политике.

### Ожидаемый результат (Expected Result)
Состав `v0.7.0`, ограничения PHAR и проверки публикации документированы; после слияния PR можно создать тег и дождаться автоматической публикации PHAR.

## 1. Концепция и Цель (Concept and Goal)

### История (Job Story)
> Когда новая PHAR-функциональность проверена и слита в `main`, я хочу выпустить minor-релиз, чтобы потребители могли устанавливать `become-role` из опубликованного PHAR.

### Цель по SMART (Goal)
К 2026-09-16 подготовить и проверить релизные артефакты `v0.7.0`, создать PR в `main` и после отдельного явного разрешения на merge опубликовать тег.

## 2. Контекст и Границы (Context and Scope)

- Где делаем: `CHANGELOG.md`, `docs/releases/v0.7.0/release-plan.md`, этот файл задачи.
- База: `main` (`4fa1afb`), последний релиз `v0.6.0`.
- Вне scope: изменения runtime-кода, исправление backlog-дефекта PHAR-кеша, публикация тега до слияния release PR.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [x] Выбрать `v0.7.0` как minor-релиз новой функциональности в серии `0.x`.
- [x] Перенести Unreleased-состав в секцию `0.7.0` и добавить пропущенные исправления.
- [x] Создать план релиза с составом, рисками, известным ограничением PHAR-кеша и проверками.
- [ ] Выполнить проектные проверки и дождаться CI PR.
- [ ] Перевести задачу в `done`, указать PR и перенести в `todo/done/` до запроса одобрения.

### 🟡 Желательно (Should Have)
- [x] Отделить поведенческие изменения от исследовательских и служебных коммитов.

### 🟢 Опционально (Could Have)
- [ ] —

### ⚫ Не будем делать (Won't Have)
- [ ] Не исправлять `TASK-fix-phar-runtime-layout-cache` в рамках выпуска.
- [ ] Не создавать тег и GitHub Release до слияния подготовительного PR.

## 4. План реализации (Implementation Plan)

1. [x] Проверить историю после `v0.6.0` и состояние GitHub.
2. [x] Обновить `CHANGELOG.md`.
3. [x] Создать план `docs/releases/v0.7.0/release-plan.md`.
4. [ ] Выполнить проверки, self-review и документационное review.
5. [ ] Закоммитить, опубликовать ветку и создать PR.
6. [ ] После явного подтверждения merge слить PR и опубликовать тег.

## 5. Критерии приёмки (Definition of Done)

- [ ] Release PR содержит только релизные артефакты и завершённую задачу.
- [ ] Все обязательные проверки и CI успешны.
- [ ] План точно описывает поддержку `agent:init` из PHAR и остаточное ограничение кеша.
- [ ] После публикации GitHub Release содержит рабочий PHAR версии `0.7.0`.

## 6. Самопроверка (Verification)

```bash
composer validate --strict
composer audit
make check
make composer-host-smoke
PHAR_EXPECTED_VERSION=dev make phar-smoke
php vendor/bin/todo-md validate todo/TASK-release-v0-7-0-preparation.todo.md
git diff --check
```

## 7. Риски и зависимости (Risks and Dependencies)

- Публикация PHAR запускается тегом и необратима без нового patch-релиза.
- PHAR сохраняет документированную привязку установленного skill к физическому пути архива.
- Backlog-дефект runtime layout/cache не блокирует основной Composer-канал, но должен быть указан в release notes.

## 8. Источники (Sources)

- `docs/releases/RELEASE-POLICY.md`
- `CHANGELOG.md`
- PR [#387](https://github.com/prikotov/task-orchestrator/pull/387)
- PR [#390](https://github.com/prikotov/task-orchestrator/pull/390)
- `todo/backlog/TASK-fix-phar-runtime-layout-cache.todo.md`

## 9. Комментарии (Comments)

Пользователь поручил создать релизы. Слияние PR выполняется только после отдельной явной команды «merge» или «вливай» согласно правилам проекта.

## История изменений (Change History)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-16 10:21:10 (1789528870) | Технический писатель Гермиона (pi) | Создана и начата задача подготовки `v0.7.0`. |
