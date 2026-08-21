---
type: chore
created: 2026-08-21
due:
started: 2026-08-21
completed:
cancelled:
value: V3
complexity: C2
priority: P0
cost_plan:
cost_fact:
depends_on:
epic:
author: Тимлид Алекс (pi)
assignee: Тимлид Алекс (pi)
branch: task/release-v0-4-0
pr: https://github.com/prikotov/task-orchestrator/pull/366
status: review
---

# TASK-release-v0-4-0-preparation: Подготовить и выпустить релиз v0.4.0 из main

## 0. Простое описание (Human Brief)
### Проблема простыми словами (Problem)
Шины Use Case и обновление зависимостей `prikotov/*` (PR #364) есть только в `main`: последний опубликованный tag `v0.3.1` их не содержит, Composer- и PHAR-потребители не получают ни новую диспетчеризацию, ни coding-standard 0.30.0.

### Варианты или путь решения (Solution Sketch)
Подготовить minor-релиз `v0.4.0` в ветке от `main` по политике выпуска из `main`: changelog, release plan, задача; после merge опубликовать tag и GitHub Release с PHAR.

### Ожидаемый результат (Expected Result)
Потребители получают `v0.4.0`: Use Case шины с автоподхватом хендлеров, обновлённые зависимости `prikotov/*`, подключённые PHPStan-правила.

## 1. Концепция и Цель (Concept and Goal)
### История (Job Story)
> **Job Story:** Когда функциональность шин слита и проверена, я хочу выпустить её minor-релизом, чтобы host-проекты получили каноническую диспетчеризацию и свежие зависимости без локальных замен.

### Цель по SMART (Goal)
Подготовить, проверить и слить PR выпуска `v0.4.0` в `main`; после approval (одобрения) опубликовать tag и GitHub Release с PHAR.

## 2. Контекст и Границы (Context and Scope)
* **Где делаем:** `CHANGELOG.md`, `docs/releases/v0.4.0/release-plan.md`, этот файл задачи.
* **Текущее поведение:** последний tag `v0.3.1`; в `main` после него PR #364 (feat: шины + deps) и #365 (docs: ретро).
* **Границы (Out of Scope):** не добавляем новый код и не меняем зависимости в PR подготовки; открытый вопрос ретро 2026-08-21 (P0 по done-коммиту) не решаем в этом релизе.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] SemVer-версия определена как `v0.4.0` (новая функциональность — шины, PR #364).
- [x] `CHANGELOG.md` и release plan описывают состав релиза.
- [x] `make check` успешно завершён.
- [x] Создан PR в `main` с меткой `pi`.
- [ ] После merge опубликованы tag `v0.4.0`, GitHub Release и PHAR-артефакт.

### ⚫ Не будем делать (Won't Have)
- [ ] Не включаем изменения, появившиеся в `main` после release preparation PR.
- [ ] Не используем отдельную `release/x.y` ветку (политика выпуска из `main`).
- [ ] Не решаем вопрос P0 из ретро 2026-08-21 (отложен владельцем).

## 4. План реализации (Implementation Plan)
1. [x] Проверить состав `v0.3.1..main` (только #364, #365).
2. [x] Сформировать changelog и release plan `v0.4.0`.
3. [x] Запустить `make check`.
4. [x] Создать PR подготовки в `main` и передать на ревью.
5. [ ] После approval: задача в `done` до merge, слить PR, опубликовать tag с GitHub Release.

## 5. Критерии приёмки (Definition of Done)
- [ ] `main` содержит релизные метаданные `v0.4.0`.
- [ ] Tag `v0.4.0` и GitHub Release с PHAR опубликованы.
- [ ] Host-проект (TasK/codexcli) обновлён до `^0.4.0`, CLI и `become-role` работают.

## 6. Самопроверка (Verification)
```bash
make check
git diff --check
```

После публикации:
```bash
gh release view v0.4.0
gh run list --workflow='Release Phar' --limit=1
```

## 7. Риски и зависимости (Risks and Dependencies)
- Tag запускает внешний workflow сборки PHAR; GitHub Release нельзя считать завершённым до его успешного окончания.
- Новая проводка контейнера (UseCaseBusCompilerPass) уже покрыта тестами и зелёными smokes PR #364.

## 8. Источники (Sources)
- `CHANGELOG.md`
- `docs/releases/RELEASE-POLICY.md`
- `docs/releases/v0.4.0/release-plan.md`
- PR #364 и PR #365

## 9. Комментарии (Comments)
- Minor-релиз: новая функциональность (шины) + обновление dev-зависимостей.
- Вопрос P0 из ретро 2026-08-21 отложен владельцем («потом решим») — в этот релиз не входит.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-21 | Тимлид Алекс (pi) | Создание задачи и подготовка релиза `v0.4.0` из `main`. |
