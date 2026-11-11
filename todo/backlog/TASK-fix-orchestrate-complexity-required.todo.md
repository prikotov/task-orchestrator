---
type: fix
created: 2026-11-11 03:44:58 (1794368698)
due: 
started: 
completed: 
cancelled: 
value: V3
complexity: C2
priority: P3
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Алекс (pi)
assignee: 
consultants: []
reviewer: 
approver: 
informed: []
branch: 
pr: 
status: backlog
---

# TASK-fix-orchestrate-complexity-required: Убрать фолбэк C3 в agent:orchestrate — требовать явный уровень сложности

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- CLI-команда `agent:orchestrate` при отсутствии `--complexity` и поля `complexity` в файле задачи (`--task-id`) молча применяет фолбэк `C3` (`OrchestrateCommand::resolveComplexity()`).
- Это тот же паттерн «тихого дефолта», что удалён в `watch-subagent.sh` (TASK-fix-watch-subagent-complexity-required): тиры моделей не включаются, неверный выбор модели невидим.

### Варианты или путь решения (Solution Sketch)
- Сделать уровень обязательным: запуск без `--complexity` и без `--task-id` (или без поля `complexity` в файле задачи) — ошибка валидации на CLI-границе.
- Синхронизировать документацию (`docs/guide/roles.md`) и CHANGELOG.

### Ожидаемый результат (Expected Result)
- `agent:orchestrate` ведёт себя как `watch-subagent.sh`: явный уровень обязателен, молчаливого фолбэка нет.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как владелец оркестратора, я хочу единообразную дисциплину сложности у обоих исполнителей (`watch-subagent.sh` и `agent:orchestrate`), чтобы тиры моделей работали всегда.

### Цель по SMART (Goal)
- Убрать фолбэк `C3` из `agent:orchestrate`, сделав уровень сложности обязательным, с тестами и обновлённой документацией — один PR.

## 2. Контекст и Границы (Context and Scope)

- Точка изменения: CLI-команда `agent:orchestrate` (`OrchestrateCommand::resolveComplexity()`, см. комментарий в `OrchestrateChainCommand`).
- Зависимость: TASK-fix-watch-subagent-complexity-required (уже реализована в том же релизе).
- Out of scope: `watch-subagent.sh` (готов), сессии DynamicLoop (legacy-сессии без поля `complexity` требуют решения по миграции).

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] (заполнить)
### ⚫ Won't Have (Не будем делать)
- (заполнить)

## 4. План реализации (Implementation Plan)
1. [ ] (заполнить)

## 5. Критерии приёмки (Definition of Done)
- [ ] (заполнить)

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate
```

## 7. Риски и зависимости (Risks and Dependencies)
- (заполнить)

## 8. Источники (Sources)

## 9. Комментарии (Comments)

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-11 03:44:58 (1794368698) | Тимлид Алекс (pi) | Создание задачи |
