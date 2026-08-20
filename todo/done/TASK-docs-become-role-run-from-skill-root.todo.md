---
# Metadata (Метаданные)
type: docs
created: 2026-08-20
due:
started:
completed: 2026-08-20 16:49:43 (1787244583)
cancelled:
value: V2
complexity: C0
priority: P1
cost_plan:
cost_fact:
depends_on:
epic:
author: Тимлид Алекс (pi)
assignee: Тимлид Алекс (pi)
branch: task/become-role-run-from-skill-root
pr: https://github.com/prikotov/task-orchestrator/pull/362
status: done
---

# TASK-docs-become-role-run-from-skill-root: Якорь каталога запуска скрипта в SKILL.md become-role

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
В host-проекте (`TasK/Sandbox/codexcli`) агент выполнил команду из `become-role/SKILL.md` буквально — `scripts/become-role.sh` от корня проекта — и получил «No such file or directory». Стандарт Agent Skills предписывает агенту самому резолвить относительные пути от каталога skill, но модель это правило проигнорировала. Без подстраховки в тексте команды сбой повторяется.

### Варианты или путь решения (Solution Sketch)
Оставить канонический относительный путь (`scripts/become-role.sh`) по стандарту Agent Skills и добавить одну строку-комментарий в code block — по образцу встроенных skills codex (`plugin-creator`: «Run from the skill root»).

### Ожидаемый результат (Expected Result)
Агент, копирующий команду из SKILL.md дословно, видит комментарий с указанием каталога запуска и резолвит путь от каталога skill; ошибки «No such file or directory» при запуске в host-проектах не повторяются.

## 1. Концепция и Цель (Concept and Goal)
### История (User Story или Job Story)
> **Job Story:** Когда агент выполняет команду из `become-role/SKILL.md` в host-проекте, я хочу, чтобы code block содержал якорь каталога запуска, чтобы путь резолвился от каталога skill даже при буквальном копировании команды.

### Цель по SMART (Goal)
Добавить в code block шага 2 `docs/agents/skills/become-role/SKILL.md` комментарий «Запускай из каталога скилла (каталог с этим SKILL.md)», не меняя саму команду и код скрипта.

## 2. Контекст и Границы (Context and Scope)
*   **Где делаем:** `docs/agents/skills/become-role/SKILL.md` (только code block шага 2).
*   **Текущее поведение:** команда `scripts/become-role.sh <role|file>` без якоря каталога; при буквальном выполнении из cwd проекта — ошибка «No such file or directory».
*   **Границы (Out of Scope):** код скрипта `become-role.sh` и CLI не трогаем; команда в SKILL.md остаётся относительной по стандарту Agent Skills (абсолютные пути не вводим); README навыка, `cli.md`, `troubleshooting.md`, README.\*.md вне правок.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] В code block шага 2 `SKILL.md` добавлен комментарий-якорь каталога запуска.
- [x] Команда остаётся относительной (`scripts/become-role.sh`), без project-relative и абсолютных путей.
### 🟡 Желательно (Should Have)
- [ ] —
### 🟢 Опционально (Could Have)
- [ ] —
### ⚫ Не будем делать (Won't Have)
- [ ] Изменение поведения `become-role.sh` или CLI-команд.
- [ ] Переписывание инструкции на `<skill-dir>/scripts/...` (расходится со стандартом).

## 4. План реализации (Implementation Plan)
1. [x] Изучить исходники codex 0.148.0 (`catalog_prompt.rs`, образец `plugin-creator`) и стандарт agentskills.io.
2. [x] Добавить строку-комментарий в code block шага 2 SKILL.md.
3. [x] Проверить поиском согласованность остальных упоминаний `become-role.sh` в документации.

## 5. Критерии приёмки (Definition of Done)
- [x] Комментарий-якорь в code block шага 2 SKILL.md присутствует.
- [x] Форма команды соответствует стандарту (голый относительный путь).
- [x] Docs-only → `make check` пропущен по исключению; точечно прогнан integration-тест скрипта.

## 6. Самопроверка (Verification)
```bash
# docs-only — полный make check пропущен по исключению регламента PR
vendor/bin/phpunit tests/Integration/Docs/Agents/Skills/BecomeRole/BecomeRoleScriptTest.php
php vendor/bin/todo-md validate todo/TASK-docs-become-role-run-from-skill-root.todo.md
grep -n "scripts/become-role.sh" docs/agents/skills/become-role/SKILL.md
```

## 7. Риски и зависимости (Risks and Dependencies)
- Комментарий — прагматичная подстраховка, не гарантия: контракт резолва путей остаётся на стороне агента.
- Распространение в host-проекты — через `composer update prikotov/task-orchestrator` после релиза.

## 8. Источники (Sources)
- [ ] [Agent Skills Specification — File references](https://agentskills.io/specification)
- [ ] [Using scripts in skills — Referencing scripts from SKILL.md](https://agentskills.io/skill-creation/using-scripts)
- [ ] [openai/codex, rust-v0.148.0 — catalog_prompt.rs, plugin-creator sample](https://github.com/openai/codex/tree/rust-v0.148.0)

## 9. Комментарии (Comments)
- Инцидент воспроизведён в `~/MyProjects/TasK/Sandbox/codexcli` (codex-cli 0.148.0, skill установлен симлинком `.agents/skills/become-role` → vendor).

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-20 | Тимлид Алекс (pi) | Создание задачи |
