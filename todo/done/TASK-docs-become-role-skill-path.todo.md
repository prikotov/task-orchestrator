---
type: docs
created: 2026-07-17
value: V2
complexity: C0
priority: P1
depends_on:
epic:
author: pi
assignee: pi
branch: task/fix-become-role-skill-path
pr: PR #313
status: done
---

# TASK-docs-become-role-skill-path: Относительные пути к скриптам skills по стандарту Agent Skills

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда пакет `task-orchestrator` подключён в host-проекте (Composer), а `SKILL.md` навыка `become-role` предписывает путь к скрипту от корня проекта (`docs/agents/skills/...`), я хочу, чтобы путь указывался относительно каталога skill по стандарту Agent Skills, чтобы агент находил скрипт в любом контексте (standalone / vendor / pi / codex).

### Goal (Цель по SMART)
Привести путь к скрипту в `become-role/SKILL.md` к относительному (`scripts/become-role.sh`) согласно стандарту Agent Skills и документации pi; сослаться на стандарт как источник истины в `SKILL-CREATION.md`.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/agents/skills/become-role/SKILL.md`, `docs/agents/skills/SKILL-CREATION.md`.
*   **Текущее поведение:** в `become-role/SKILL.md` путь к скрипту — `docs/agents/skills/become-role/scripts/become-role.sh` (project-relative, существует только в standalone). В host-проекте `stocks2` это привело к ошибке «Нет такого файла или каталога». `SKILL-CREATION.md` не ссылается на внешний стандарт.
*   **Границы (Out of Scope):** код скрипта `become-role.sh` и CLI-команды не трогаем; `README.md` навыка и прочая документация (`cli.md`, `troubleshooting.md`) вне правок.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] Путь к скрипту в `become-role/SKILL.md` — относительный (`scripts/become-role.sh`) с пояснением резолва через `<location>`.
- [x] В `SKILL-CREATION.md` добавлен блок «Стандарты и источники» со ссылками на agentskills.io.
### 🟡 Should Have (Желательно)
- [x] Альтернативный вызов через `agent:role-skills` (source / Composer-host).
### 🟢 Could Have (Опционально)
- [ ] —
### ⚫ Won't Have (Не будем делать)
- [ ] Изменение поведения `become-role.sh` или CLI.

## 4. Implementation Plan (План реализации)
1. [x] Изучить стандарт Agent Skills и исходники pi (резолв относительных путей skill).
2. [x] Заменить путь в `become-role/SKILL.md` на относительный.
3. [x] Добавить блок источников в `SKILL-CREATION.md`.
4. [x] Проверить живость ссылок и соответствие `SKILL-CREATION.md`.

## 5. Definition of Done (Критерии приёмки)
- [x] Путь к скрипту относительный; работает в standalone и host (через `<location>`).
- [x] `SKILL-CREATION.md` ссылается на стандарт.
- [x] docs-only → `make check` пропущен по исключению.

## 6. Verification (Самопроверка)
```bash
# docs-only — make check пропущен по исключению регламента PR
grep -n "scripts/become-role.sh" docs/agents/skills/become-role/SKILL.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- Распространение в host-проекты — через `composer update prikotov/task-orchestrator` после релиза новой версии.

## 8. Sources (Источники)
- [Agent Skills Specification](https://agentskills.io/specification)
- [Using scripts in skills](https://agentskills.io/skill-creation/using-scripts)
- pi `docs/skills.md`, `dist/core/skills.js` (`formatSkillsForPrompt`)

## 9. Comments (Комментарии)
* Обнаружено при разборе ошибки в host-проекте `stocks2`: агент вызывал `docs/agents/skills/become-role/scripts/become-role.sh`, которого нет вне standalone-репозитория.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-17 | pi | Создание задачи |
| 2026-07-17 | pi | Переведена в done (merge PR #313) |
