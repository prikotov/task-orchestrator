---
type: fix
created: 2026-09-17 04:03:12 (1789617792)
due: 
started: 2026-09-17 04:04:39 (1789617879)
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
branch: task/fix-skill-command-placeholder
pr: https://github.com/prikotov/task-orchestrator/pull/396
status: review
---

# TASK-fix-skill-command-placeholder: Плейсхолдер-форма команды скилла: защита от слепого копирования scripts/…

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Агенты копируют команду из code block SKILL.md дословно и запускают `bash scripts/…` от cwd проекта → exit 127. Якорь-комментарий не защищает: самоотчёт исполнителя подтвердил, что команда-шаблон доминирует над текстом.

### Варианты или путь решения (Solution Sketch)
- Плейсхолдер-форма команды `<skill-dir>/scripts/foo.sh` (каталог SKILL.md из `<location>`): дословное копирование невозможно, переносимость сохранена, хардкод абсолютных путей запрещён.

### Ожидаемый результат (Expected Result)
- Конвенция SKILL-CREATION (п.1–3, чеклист) и become-role/SKILL.md переведены на плейсхолдер-форму; реестр — escalated со ссылкой на задачу.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> (заполнить)

### Цель по SMART (Goal)
- (заполнить)

## 2. Контекст и Границы (Context and Scope)
- docs/agents/skills/SKILL-CREATION.md (раздел «Скрипты и пути», чеклист), docs/agents/skills/become-role/{SKILL.md,README.md}, docs/agents/team-retro/RETRO-ROADMAP.md.
- Фактура: ретро 2026-08-20 и 2026-09-16, сессии двух host-проектов, самоотчёт исполнителя 2026-09-17.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] Конвенция: плейсхолдер-форма для команд запуска; якорь-комментарий упразднён (недостаточен).
- [x] become-role/SKILL.md — плейсхолдер-форма; README — синхронизирован.
- [x] Реестр ретро — escalated со ссылкой на задачу.
### ⚫ Won't Have (Не будем делать)
- Хардкод абсолютных путей в SKILL.md (против стандарта agentskills.io и переносимости).
- Перевод остальных скиллов (run-subagent и др.) на новую форму — отдельная задача (кандидат в реестре).

## 4. План реализации (Implementation Plan)
1. [x] Конвенция + SKILL.md/README + реестр.
2. [x] Проверки (md-links, validate-language, validate-todo).

## 5. Критерии приёмки (Definition of Done)
- [x] В SKILL.md нет копируемой относительной команды запуска; форма — `<skill-dir>/scripts/become-role.sh`.

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
| 2026-09-17 04:03:12 (1789617792) | Тимлид Алекс (pi) | Создание задачи |
