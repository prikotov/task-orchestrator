---
type: chore
created: 2026-08-23 02:55:47 (1787453747)
due:
started: 2026-08-23 02:55:47 (1787453747)
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
assignee: Тимлид Алекс (pi)
branch: task/release-v0-5-0
pr: https://github.com/prikotov/task-orchestrator/pull/370
status: review
---

# TASK-release-v0-5-0-preparation: Подготовить и выпустить релиз v0.5.0

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Функциональность сохранения нативных сессий агентов уже слита в `main`, но последний опубликованный тег `v0.4.1` её не содержит. Потребители не получают новые журналы сессий и связанные изменения стандартных запусков Pi и Codex.

### Варианты или путь решения (Solution Sketch)
Подготовить minor-релиз `v0.5.0` из `main`: добавить запись в `CHANGELOG.md`, оформить release plan и задачу подготовки, затем пройти проверки, слить release PR, создать тег и GitHub Release с PHAR-артефактом.

### Ожидаемый результат (Expected Result)
Потребители получают `v0.5.0` с включённым сохранением нативных сессий Pi и Codex, а GitHub Release содержит опубликованный PHAR-артефакт.

## 1. Концепция и Цель (Concept and Goal)

### История (Job Story)
> **Job Story:** Когда изменение сохранения сессий агентов слито и проверено, я хочу выпустить его minor-релизом, чтобы потребители получили доступ к полным usage-метрикам после завершения запусков.

### Цель по SMART (Goal)
Подготовить, проверить и слить release PR для `v0.5.0` в `main`, затем опубликовать тег `v0.5.0` и GitHub Release с успешно собранным PHAR в день подготовки релиза.

## 2. Контекст и Границы (Context and Scope)
* **Где делаем:** `CHANGELOG.md`, `docs/releases/v0.5.0/release-plan.md`, этот файл задачи.
* **Текущее поведение:** последний опубликованный тег — `v0.4.1`; PR #369 с сохранением нативных сессий уже слит в `main`.
* **Границы (Out of Scope):** не добавляем новые изменения кода, не меняем зависимости и не включаем изменения после release preparation PR.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] Версия `v0.5.0` определена как minor-релиз из-за новой функциональности.
- [ ] `CHANGELOG.md` описывает состав релиза и ссылается на PR #369.
- [ ] `docs/releases/v0.5.0/release-plan.md` содержит состав, риски, порядок публикации и проверки.
- [x] `make check`, `PHAR_EXPECTED_VERSION=dev make phar-smoke` и `make composer-host-smoke` успешно завершены.
- [ ] Создан release PR в `main` с меткой `pi`.
- [ ] После одобрения release PR слит в `main`.
- [ ] На вершине `main` создан и опубликован тег `v0.5.0`.
- [ ] Создан GitHub Release с PHAR-артефактом `task-orchestrator.phar`.
### 🟡 Желательно (Should Have)
- [ ] Проверить запуск `php task-orchestrator.phar list` на опубликованном артефакте.
### 🟢 Опционально (Could Have)
- [ ] Нет.
### ⚫ Не будем делать (Won't Have)
- [ ] Не использовать отдельную `release/x.y` ветку: проект публикует релизы только из `main`.
- [ ] Не включать изменения, появившиеся в `main` после release preparation PR.

## 4. План реализации (Implementation Plan)
1. [x] Проверить последний тег и состав изменений после `v0.4.1`.
2. [x] Определить `v0.5.0` как minor-релиз и оформить `CHANGELOG.md`.
3. [x] Оформить `docs/releases/v0.5.0/release-plan.md` и задачу релиза.
4. [x] Запустить `make check`, `PHAR_EXPECTED_VERSION=dev make phar-smoke` и `make composer-host-smoke`.
5. [ ] Создать release PR, указать его в задаче и перевести задачу в `review`.
6. [ ] После одобрения перевести задачу в `done` и слить release PR.
7. [ ] Создать тег `v0.5.0`, дождаться workflow сборки PHAR и опубликовать GitHub Release.

## 5. Критерии приёмки (Definition of Done)
- [ ] `main` содержит запись `CHANGELOG.md` и release plan `v0.5.0`.
- [x] `make check`, `PHAR_EXPECTED_VERSION=dev make phar-smoke` и `make composer-host-smoke` зелёные.
- [ ] Release PR слит в `main` после одобрения.
- [ ] Тег `v0.5.0` опубликован на merge-коммите release PR.
- [ ] GitHub Release содержит `task-orchestrator.phar`.
- [ ] PHAR запускается командой `php task-orchestrator.phar list`.

## 6. Самопроверка (Verification)
```bash
make check
PHAR_EXPECTED_VERSION=dev make phar-smoke
make composer-host-smoke
git diff --check
php vendor/bin/todo-md validate todo/TASK-release-v0-5-0-preparation.todo.md
```

После публикации:
```bash
gh release view v0.5.0
gh run list --workflow='Release Phar' --limit=1
```

## 7. Риски и зависимости (Risks and Dependencies)
- Тег запускает внешний workflow сборки PHAR; релиз нельзя считать завершённым до успешной сборки и проверки артефакта.
- Нативные журналы сессий могут содержать локальные промпты и ответы, но изменение не отправляет их во внешние сервисы.
- Миграции данных и изменения формата конфигурации не требуются.

## 8. Источники (Sources)
- `CHANGELOG.md`
- `docs/releases/RELEASE-POLICY.md`
- `docs/releases/v0.4.1/release-plan.md`
- PR #369: https://github.com/prikotov/task-orchestrator/pull/369

## 9. Комментарии (Comments)
Релиз minor, потому что PR #369 добавляет функциональное поведение: стандартные запуски сохраняют нативные сессии Pi и Codex для последующего сбора usage.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-23 02:55:47 (1787453747) | Тимлид Алекс (pi) | Создание задачи и подготовка релиза `v0.5.0`. |
