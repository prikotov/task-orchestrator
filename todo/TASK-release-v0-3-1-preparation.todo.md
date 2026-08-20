---
type: chore
created: 2026-08-20
value: V3
complexity: C1
priority: P0
depends_on:
epic:
author: Тимлид Алекс (pi)
assignee: Тимлид Алекс (pi)
branch: task/release-v0-3-1
pr:
status: in_progress
---

# TASK-release-v0-3-1-preparation: Подготовить и выпустить релиз v0.3.1 из main

## 0. Простое описание (Human Brief)
### Проблема простыми словами (Problem)
Исправление `become-role/SKILL.md` (якорь каталога запуска скрипта, PR #362) слито в `main`, но недоступно Composer- и PHAR-потребителям: последний опубликованный tag — `v0.3.0` без этого исправления.

### Варианты или путь решения (Solution Sketch)
Подготовить docs-only patch `v0.3.1` в ветке от `main`: зафиксировать changelog и release plan, проверить, слить PR, затем опубликовать tag и GitHub Release с PHAR.

### Ожидаемый результат (Expected Result)
Потребители получают `v0.3.1`: агент в host-проекте видит комментарий-якорь и резолвит путь скрипта от каталога skill даже при буквальном копировании команды.

## 1. Концепция и Цель (Concept and Goal)
### История (Job Story)
> **Job Story:** Когда docs-исправление слито в `main`, я хочу выпустить его patch-релизом, чтобы host-проекты получили его через `composer update` без ожидания функциональных изменений.

### Цель по SMART (Goal)
Подготовить, проверить и слить PR выпуска `v0.3.1` в `main`; после approval (одобрения) опубликовать tag и GitHub Release с PHAR.

## 2. Контекст и Границы (Context and Scope)
* **Где делаем:** `CHANGELOG.md`, `docs/releases/v0.3.1/release-plan.md`, этот файл задачи.
* **Текущее поведение:** последний tag `v0.3.0`; в `main` после него только PR #362 (docs).
* **Границы (Out of Scope):** не добавляем новый production-код, не меняем зависимости и не трогаем код `become-role.sh` в PR подготовки.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] SemVer-версия определена как `v0.3.1` (docs-only patch после `v0.3.0`).
- [x] `CHANGELOG.md` и release plan описывают состав релиза.
- [x] `make check` успешно завершён.
- [x] Создан PR в `main` с меткой `pi`.
- [ ] После merge опубликованы tag `v0.3.1`, GitHub Release и PHAR-артефакт.

### ⚫ Не будем делать (Won't Have)
- [ ] Не включаем изменения, появившиеся в `main` после release preparation PR.
- [ ] Не используем отдельную `release/x.y` ветку (политика выпуска из `main`).

## 4. План реализации (Implementation Plan)
1. [x] Убедиться, что с `v0.3.0` в `main` нет изменений кроме PR #362.
2. [x] Сформировать changelog и release plan `v0.3.1`.
3. [x] Запустить `make check`.
4. [x] Создать PR подготовки в `main` и передать на ревью.
5. [ ] После approval: задача в `done` до merge, слить PR, опубликовать tag с GitHub Release.

## 5. Критерии приёмки (Definition of Done)
- [ ] `main` содержит релизные метаданные `v0.3.1`.
- [ ] Tag `v0.3.1` и GitHub Release с PHAR опубликованы.
- [ ] Host-проект (TasK/codexcli) обновлён до `^0.3.1` и шаг 2 `SKILL.md` содержит якорь.

## 6. Самопроверка (Verification)
```bash
make check
git diff --check
```

После публикации:
```bash
gh release view v0.3.1
gh run list --workflow='Release Phar' --limit=1
```

## 7. Риски и зависимости (Risks and Dependencies)
- Tag запускает внешний workflow сборки PHAR; GitHub Release нельзя считать завершённым до его успешного окончания.
- Обновление host-проектов зависит от доступности tag `v0.3.1` в Composer VCS repository.

## 8. Источники (Sources)
- `CHANGELOG.md`
- `docs/releases/RELEASE-POLICY.md`
- `docs/releases/v0.3.1/release-plan.md`
- PR #362 и задача TASK-docs-become-role-run-from-skill-root

## 9. Комментарии (Comments)
- Docs-only патч: единственное изменение — якорь каталога запуска в `become-role/SKILL.md`.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-20 | Тимлид Алекс (pi) | Создание задачи и подготовка релиза `v0.3.1` из `main`. |
