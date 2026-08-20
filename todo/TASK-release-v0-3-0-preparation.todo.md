---
type: chore
created: 2026-08-20
value: V3
complexity: C2
priority: P0
depends_on:
epic:
author: Тимлид Алекс (codex-cli)
assignee: Тимлид Алекс (codex-cli)
branch: task/release-v0-3-0
pr:
status: in_progress
---

# TASK-release-v0-3-0-preparation: Подготовить и выпустить релиз v0.3.0 из main

## 0. Простое описание (Human Brief)
### Проблема простыми словами (Problem)
Исправление профиля роли в Composer-установках и накопленные изменения `main` ещё не доступны потребителям через опубликованный tag. История прошлых релизов только что синхронизирована с `main`, но новый выпуск не подготовлен.

### Варианты или путь решения (Solution Sketch)
Подготовить `v0.3.0` в отдельной ветке от `main`: зафиксировать changelog, release plan и обновлённую политику релиза, проверить их, слить PR в `main`, затем опубликовать tag и GitHub Release.

### Ожидаемый результат (Expected Result)
Потребители получают `v0.3.0` с исправленным `run-subagent`, а все последующие релизы формируются из `main`, содержащего историю прошлых выпусков.

## 1. Концепция и Цель (Concept and Goal)
### История (Job Story)
> **Job Story:** Когда исправление роли готово и проверено, я хочу выпустить его из полного `main`, чтобы Composer-потребители получили исправление вместе с прослеживаемой историей версий.

### Цель по SMART (Goal)
Подготовить, проверить и слить PR выпуска `v0.3.0` в `main`; после approval (одобрения) опубликовать tag и GitHub Release с PHAR.

## 2. Контекст и Границы (Context and Scope)
* **Где делаем:** `CHANGELOG.md`, `docs/releases/RELEASE-POLICY.md`, `docs/releases/v0.3.0/release-plan.md`, этот файл задачи.
* **Текущее поведение:** последний tag `v0.2.3`; исправление PR #359 есть в `main`.
* **Границы (Out of Scope):** не добавляем новый production-код и не меняем зависимости в PR подготовки.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] SemVer-версия определена как `v0.3.0` из-за новых `feat` после `v0.2.3`.
- [x] `CHANGELOG.md` и release plan описывают состав релиза.
- [x] Политика релизов в `docs/releases/RELEASE-POLICY.md` фиксирует выпуск только из `main`.
- [x] `make check` успешно завершён.
- [ ] Создан PR в `main` с меткой `codex-cli`.
- [ ] После approval опубликованы tag `v0.3.0`, GitHub Release и PHAR-артефакт.

### ⚫ Не будем делать (Won't Have)
- [ ] Не включаем изменения, появившиеся в `main` после release preparation PR.
- [ ] Не используем отдельную `release/x.y` ветку.

## 4. План реализации (Implementation Plan)
1. [x] Синхронизировать историю `release/0.2` с `main` через PR #360.
2. [x] Сформировать changelog, release plan и обновить процесс релизов.
3. [x] Запустить `make check`.
4. [ ] Создать PR подготовки в `main` и передать на ревью.
5. [ ] После approval перевести задачу в `done`, слить PR и опубликовать tag с GitHub Release.
6. [ ] Обновить TasK до `^0.3.0` и выполнить `make check`.

## 5. Критерии приёмки (Definition of Done)
- [ ] `main` содержит релизные метаданные `v0.3.0`.
- [ ] Tag `v0.3.0` и GitHub Release с PHAR опубликованы.
- [ ] TasK обновлён до `prikotov/task-orchestrator:^0.3.0` без локальной обёртки.
- [ ] `make check` успешно завершён в обоих проектах.

## 6. Самопроверка (Verification)
```bash
make check
git diff --check
```

После публикации:
```bash
gh release view v0.3.0
gh run list --workflow='Release Phar' --limit=1
```

## 7. Риски и зависимости (Risks and Dependencies)
- Tag запускает внешний workflow сборки PHAR; GitHub Release нельзя считать завершённым до его успешного окончания.
- Обновление TasK зависит от доступности tag `v0.3.0` в Composer VCS repository.

## 8. Источники (Sources)
- `CHANGELOG.md`
- `docs/releases/RELEASE-POLICY.md`
- `docs/releases/v0.3.0/release-plan.md`
- PR #359 и PR #360

## 9. Комментарии (Comments)
Политика пользователя: релизы всегда формируются из `main`, включающего историю предыдущих выпусков.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-20 | Тимлид Алекс (codex-cli) | Создание задачи и подготовка релиза `v0.3.0` из `main`. |
| 2026-08-20 | Тимлид Алекс (codex-cli) | `make check` завершён успешно. |
