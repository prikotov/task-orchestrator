---
# Metadata (Метаданные)
type: epic
created: 2026-04-20
value: V2
complexity: C2
priority: P1
author: Бэкендер (Левша)
assignee: Технический писатель (Гермиона)
status: todo
pr:
---

# EPIC-docs-agents-md-contradictions: Устранение противоречий в AGENTS.md и связанных файлах

## 1. Concept and Goal (Концепция и цель)
### Story (User Story)
> Как AI-агент, я хочу, чтобы AGENTS.md точно описывал реальную структуру проекта, чтобы не получать ложные инструкции и не нарушать архитектуру.

### Goal (Цель по SMART)
Устранить все 7 выявленных противоречий между AGENTS.md, `docs/guide/architecture.md` и реальной кодовой базой. Все ссылки в AGENTS.md должны быть кликабельными. Структура проекта и описание слоёв должны совпадать с `architecture.md`.

## 2. Context and Scope (Контекст и границы)
*   **In Scope (Что делаем):**
    *   Актуализация структуры проекта в AGENTS.md
    *   Исправление описания слоёв (добавить Integration, двухмодульность)
    *   Исправление namespace автозагрузки
    *   Оформление ссылки на architecture.md как кликабельной
    *   Проверка/добавление phpcs.xml.dist в корень
    *   Уточнение про `docs/releases/` vs `docs/git-workflow/releases/`
*   **Out of Scope (Чего НЕ делаем):**
    *   Изменение кодовой базы
    *   Изменение `docs/guide/architecture.md` (он корректен)
    *   Рефакторинг ролей или skills

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Блокирующие требования)
- [ ] AGENTS.md: структура проекта `src/` отражает двухмодульную архитектуру (AgentRunner + Orchestrator)
- [ ] AGENTS.md: описание слоёв включает Integration-слой для Orchestrator
- [ ] AGENTS.md: namespace автозагрузки указан как `TaskOrchestrator\Common\` (не `TaskOrchestrator\`)
- [ ] AGENTS.md: ссылка на architecture.md оформлена как кликабельная markdown-ссылка

### 🟡 Should Have (Важные требования)
- [ ] AGENTS.md: таблица инструментов — уточнить про phpcs.xml.dist (есть только в examples)
- [ ] AGENTS.md: комментарий про `docs/releases/` (release-plan) vs `docs/git-workflow/releases/` (документация)

### 🟢 Could Have (Желательно)
- [ ] Ревью всех ролей на предмет актуальности ссылок после правок AGENTS.md

### ⚫ Won't Have (Не в этот раз)
- [ ] Переписывание architecture.md
- [ ] Добавление новых разделов в AGENTS.md

## 4. Solution Design (Техническое решение)
Правки вносятся только в `AGENTS.md`. Источник истины — `docs/guide/architecture.md` и реальная структура `src/`.

## 5. Implementation Plan (План реализации)

- [ ] [TASK-docs-agents-md-structure](TASK-docs-agents-md-structure.todo.md) — актуализировать структуру проекта, слои и namespace в AGENTS.md
- [ ] [TASK-docs-agents-md-minor-fixes](TASK-docs-agents-md-minor-fixes.todo.md) — кликабельная ссылка на architecture.md, таблица инструментов, комментарий про docs/releases/

## 6. Definition of Done (Критерии приёмки эпика)
- [ ] AGENTS.md точно описывает реальную структуру `src/`
- [ ] Описание слоёв совпадает с `docs/guide/architecture.md`
- [ ] Все ссылки кликабельны и ведут на существующие файлы
- [ ] PHPUnit и Psalm проходят без изменений (docs-only)

## 7. Release Notes and Deployment (Инструкция по релизу)
- docs-only, отдельный PR

## 8. Risks and Dependencies (Риски и зависимости)
- AGENTS.md — главный конфигурационный файл агента; правки влияют на поведение AI во всех задачах

## 9. Sources (Источники)
- [ ] [architecture.md](../docs/guide/architecture.md) — источник истины по структуре
- [ ] [composer.json](../composer.json) — источник истины по namespace

## 10. Comments (Комментарии)
Аудит противоречий проведён 2026-04-20. Выявлено 7 несоответствий, сгруппировано в 2 задачи.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-20 | Бэкендер (Левша) | Создание эпика |
