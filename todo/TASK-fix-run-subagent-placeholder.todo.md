---
type: fix
created: 2026-09-17 13:07:16 (1789650436)
due: 
started: 2026-09-17 13:07:40 (1789650460)
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
branch: task/fix-run-subagent-placeholder
pr: 
status: in_progress
---

# TASK-fix-run-subagent-placeholder: Плейсхолдер-форма команд в run-subagent/SKILL.md

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- run-subagent/SKILL.md учил агентов запускать `scripts/watch-subagent.sh` относительной формой — та же ловушка слепого копирования (exit 127), что закрыта для become-role в v0.8.1.

### Варианты или путь решения (Solution Sketch)
- Перевести все 10 блоков команд на плейсхолдер `<skill-dir>/scripts/watch-subagent.sh` + строка-пояснение перед первым блоком (конвенция SKILL-CREATION п.1–3).

### Ожидаемый результат (Expected Result)
- В SKILL.md нет копируемых относительных команд запуска; упоминания в тексте остаются относительными.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> (заполнить)

### Цель по SMART (Goal)
- (заполнить)

## 2. Контекст и Границы (Context and Scope)
- docs/agents/skills/run-subagent/SKILL.md (10 блоков), docs/agents/team-retro/RETRO-ROADMAP.md (закрытие кандидата).
- Аудит сессий pi 2026-08-27…17: watch-subagent.sh напрямую не скипался — правка профилактическая по конвенции.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] 10 блоков на плейсхолдер-форме; пояснение `<skill-dir>` перед первым блоком.
- [x] Реестр: кандидат закрыт ссылкой на задачу.
### ⚫ Won't Have (Не будем делать)
- Изменения скрипта watch-subagent.sh (он самонастраивающийся, cwd-независимый).

## 4. План реализации (Implementation Plan)
1. [x] Замена форм + пояснение + реестр.

## 5. Критерии приёмки (Definition of Done)
- [x] `grep '^scripts/watch-subagent.sh' SKILL.md` пуст; docs-проверки зелёные.

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate
```

## 7. Риски и зависимости (Risks and Dependencies)
- (заполнить)

## 8. Источники (Sources)

## 9. Комментарии (Comments)

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-17 13:07:16 (1789650436) | Тимлид Алекс (pi) | Создание задачи |
