---
# Metadata (Метаданные)
type: docs
created: 2026-04-20
value: V2
complexity: C0
priority: P1
depends_on: TASK-docs-agents-md-structure
epic: EPIC-docs-agents-md-contradictions
author: Бэкендер (Левша)
assignee: Технический писатель (Гермиона)
branch:
pr:
status: todo
---

# TASK-docs-agents-md-minor-fixes: Мелкие исправления в AGENTS.md (ссылки, таблица инструментов)

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как AI-агент, я хочу чтобы все ссылки в AGENTS.md были кликабельными, а таблица инструментов точно отражала конфигурацию проекта.

### Goal (Цель по SMART)
Устранить 3 мелких несоответствия: некликабельная ссылка, неточный путь к phpcs.xml.dist, неочевидное разделение docs/releases/ и docs/git-workflow/releases/.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `AGENTS.md` (корень)
*   **Текущее поведение:**
    1. Ссылка на architecture.md — plain text, не markdown-ссылка: `Для детальных архитектурных правил используй docs/guide/architecture.md`
    2. Таблица инструментов: PHP_CodeSniffer — Configuration File: `—`, но есть `docs/conventions/examples/phpcs.xml.dist`; в корне проекта `phpcs.xml.dist` отсутствует
    3. После git-workflow-init созданы две папки: `docs/releases/` (для release-plan) и `docs/git-workflow/releases/` (документация) — без пояснения
*   **Границы (Out of Scope):** не создаём phpcs.xml.dist в корне, не трогаем git-workflow

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Ссылка на architecture.md оформлена как `[docs/guide/architecture.md](docs/guide/architecture.md)`
- [ ] Таблица инструментов: PHP_CodeSniffer — указать `docs/conventions/examples/phpcs.xml.dist` или добавить комментарий
### 🟡 Should Have (Желательно)
- [ ] Комментарий в секции структуры про назначение `docs/releases/` vs `docs/git-workflow/releases/`
### 🟢 Could Have (Опционально)
- [ ] ...
### ⚫ Won't Have (Не будем делать)
- [ ] Создание phpcs.xml.dist в корне проекта

## 4. Implementation Plan (План реализации)
1. [ ] Заменить plain text ссылку на architecture.md на markdown-ссылку
2. [ ] Обновить ячейку PHP_CodeSniffer в таблице: указать путь `docs/conventions/examples/phpcs.xml.dist`
3. [ ] Добавить краткое пояснение про docs/releases/ (опционально)

## 5. Definition of Done (Критерии приёмки)
- [ ] Ссылка на architecture.md кликабельна
- [ ] Таблица инструментов не содержит прочерков без пояснений
- [ ] PHPUnit и Psalm не затронуты (docs-only)

## 6. Verification (Самопроверка)
```bash
# docs-only — проверки пропускаются
```

## 7. Risks and Dependencies (Риски и зависимости)
- Зависит от TASK-docs-agents-md-structure (структура может измениться)

## 8. Sources (Источники)
- [ ] [AGENTS.md](../AGENTS.md)

## 9. Comments (Комментарии)
Выявлено при аудите 2026-04-20.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-20 | Бэкендер (Левша) | Создание задачи |
