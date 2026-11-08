---
type: docs
created: 2026-11-08 02:36:43 (1794105403)
due: 
started: 2026-11-08 02:37:01 (1794105421)
completed: 2026-11-08 02:37:45 (1794105465)
cancelled: 
value: V2
complexity: C2
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Технический писатель Гермиона (pi)
assignee: Технический писатель Гермиона (pi)
consultants: []
reviewer: 
approver: 
informed: []
branch: feat/add-pi-native-rendering-skill
pr: https://github.com/prikotov/task-orchestrator/pull/469
status: done
---

# TASK-add-pi-native-rendering-skill: Добавить скилл pi-native-rendering в каталог пакета

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Роли пакета при подготовке ответов не используют встроенный рендеринг pi (mermaid-ASCII, LaTeX, GFM-таблицы, kitty-картинки) — знаний об этом в каталоге skills нет.

### Варианты или путь решения (Solution Sketch)
- Добавить скилл `pi-native-rendering` в каталог пакета и подключить его через `skills:` metadata ролям архитектуры/анализа/документации/бэкенда.

### Ожидаемый результат (Expected Result)
- Роли при подготовке диаграмм/формул используют встроенный рендеринг pi; downstream-хосты могут переопределить скилл своим (пер-скилл приоритет v0.13.0).

## 1. Концепция и Цель (Concept and Goal)

### История (User Story или Job Story)

> **Job Story:** Когда я как роль готовлю архитектурную диаграмму или формулу, я хочу, чтобы pi отрисовал её в терминале, чтобы ответ был наглядным без внешних инструментов.

### Цель по SMART (Goal)
- Скилл добавлен, подключён 7 ролям, валидации и тесты зелёные, PR слит.

## 2. Контекст и Границы (Context and Scope)

*   **Где делаем:** `docs/agents/skills/pi-native-rendering/SKILL.md` (новый), `docs/agents/roles/team/*.ru.md` (metadata 7 ролей), релизная задача.
*   **Текущее поведение:** каталог пакета содержит только workflow-скиллы; знаний о рендеринге pi нет.
*   **Прецеденты для переиспользования:** идентичный скилл внедрён в host-проект TasK (`docs/agents/skills/pi-native-rendering/`); downstream-хосты переопределяют пакетный скилл per-skill (v0.13.0).
*   **Критичные конвенции:** `docs/git-workflow/pull-request.md` (идентичность PR от бота).
*   **Границы (Out of Scope):** правки кода резолвинга; локали en/zh (в пакете только ru).

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [x] `docs/agents/skills/pi-native-rendering/SKILL.md` добавлен (контент из прецедента TasK).
- [x] Скилл подключён ролям: Гэндальф, Локи, Шерлок, Гермиона, Остап, Левша, Тони.
- [x] `composer check` зелёный.

### 🟡 Желательно (Should Have)
- [ ] Нет.

### 🟢 Опционально (Could Have)
- [ ] Нет.

### ⚫ Не будем делать (Won't Have)
- [ ] Скрипты рендеринга и внешние сервисы внутри скилла.

## 4. План реализации (Implementation Plan)

1. [x] Добавить SKILL.md и подключить роли (7 ролей).
2. [x] Заполнить задачу, валидация (0 errors).
3. [x] `composer check` — все проверки зелёные.
4. [ ] Коммит, push по HTTPS-рецепту от бота, PR, апрув владельца, merge.

## 5. Критерии приёмки (Definition of Done)

- [ ] PR слит в `main`; `agent:role-skills` для подключённых ролей показывает скилл.

## 6. Самопроверка (Verification)

```bash
composer check
php vendor/bin/todo-md validate TASK-add-pi-native-rendering-skill
```

## 7. Риски и зависимости (Risks and Dependencies)

- Нет существенных: добавление каталога и metadata, downstream-хосты с v0.13.0 переопределяют per-skill.

## 8. Источники (Sources)

- [ ] Прецедент: TasK `docs/agents/skills/pi-native-rendering/SKILL.md`.

## 9. Комментарии (Comments)

- Запрос владельца: «добавь такую же штуку (скилл) в ~/MyProjects/task-orchestrator, ~/MyProjects/stocks2».

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-08 02:36:43 (1794105403) | Технический писатель Гермиона (pi) | Создание задачи |
