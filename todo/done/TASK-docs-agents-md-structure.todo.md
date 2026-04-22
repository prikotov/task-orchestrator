---
# Metadata (Метаданные)
type: docs
created: 2026-04-20
value: V2
complexity: C1
priority: P1
depends_on:
epic: EPIC-docs-agents-md-contradictions
author: Бэкендер (Левша)
assignee: Технический писатель (Гермиона)
branch: task/docs-agents-md-structure
pr: https://github.com/prikotov/task-orchestrator/pull/53
status: done
---

# TASK-docs-agents-md-structure: Актуализировать структуру проекта, слои и namespace в AGENTS.md

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как AI-агент, я хочу видеть в AGENTS.md реальную структуру проекта, чтобы правильно размещать код по слоям и модулям.

### Goal (Цель по SMART)
Привести секции «Структура проекта», «Слои» и «Архитектура проекта» в AGENTS.md в полное соответствие с `docs/guide/architecture.md` и реальной кодовой базой.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `AGENTS.md` (корень)
*   **Текущее поведение:**
    1. Структура `src/` показана плоской (Domain/Application/Infrastructure/DependencyInjection) — реально двухмодульная (`Module/AgentRunner/` + `Module/Orchestrator/`)
    2. Слои описаны как 3 (Domain/Application/Infrastructure) — у Orchestrator 4 слоя (+ Integration)
    3. Namespace указан `TaskOrchestrator\` — реально `TaskOrchestrator\Common\`
*   **Границы (Out of Scope):** не трогаем `docs/guide/architecture.md`, роли, skills, composer.json

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Секция «Структура проекта» отражает двухмодульную архитектуру с `src/Module/AgentRunner/` и `src/Module/Orchestrator/`
- [ ] Секция «Слои» описывает Integration-слой для Orchestrator (ACL к AgentRunner)
- [ ] Namespace автозагрузки: `TaskOrchestrator\Common\ → src/`
- [ ] `DependencyInjection/` показан как часть Bundle infrastructure, не как «5-й корневой каталог»
### 🟡 Should Have (Желательно)
- [ ] Добавить упоминание `apps/console/` в структуру проекта
### 🟢 Could Have (Опционально)
- [ ] ...
### ⚫ Won't Have (Не будем делать)
- [ ] Переписывание architecture.md

## 4. Implementation Plan (План реализации)
1. [ ] Прочитать `docs/guide/architecture.md` как источник истины
2. [ ] Переписать секцию «Структура проекта» с двухмодульной схемой
3. [ ] Переписать секцию «Слои» с учётом Integration-слоя
4. [ ] Исправить namespace на `TaskOrchestrator\Common\`

## 5. Definition of Done (Критерии приёмки)
- [ ] Описание структуры совпадает с `docs/guide/architecture.md`
- [ ] Описание слоёв совпадает с `docs/guide/architecture.md`
- [ ] Namespace совпадает с `composer.json`

## 6. Verification (Самопроверка)
```bash
# docs-only — проверки пропускаются
```

## 7. Risks and Dependencies (Риски и зависимости)
- Правки в AGENTS.md влияют на поведение AI-агента во всех последующих задачах

## 8. Sources (Источники)
- [ ] [architecture.md](../docs/guide/architecture.md)
- [ ] [composer.json](../composer.json)

## 9. Comments (Комментарии)
Выявлено при аудите 2026-04-20: плоская структура в AGENTS.md vs двухмодульная в architecture.md.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-20 | Бэкендер (Левша) | Создание задачи |
