---
type: docs
created: 2026-04-30
value: V2
complexity: C1
priority: P1
depends_on:
epic: EPIC-refactor-orchestrator-decomposition
author: pi
assignee:
branch:
pr:
status: todo
---

# TASK-docs-domain-inventory: Инвентаризация Domain-слоя Orchestrator

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда модуль Orchestrator содержит 126 файлов и 5964 LOC Domain, а roadmap планирует декомпозицию и 6 новых фич, я хочу иметь полную каталогизацию Domain-слоя, чтобы принимать архитектурные решения на основе данных, а не оценок.

### Goal (Цель по SMART)
Создать каталог всех Domain-файлов Orchestrator: категория (VO/Service/Entity/Interface/Enum/Exception), LOC, зависимости между subdomain'ами (Static/Dynamic/Shared), cross-references. Результат — документ в `docs/releases/`.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/Orchestrator/Domain/` (~64 файла)
*   **Текущее поведение:** Нет инвентаризации. Brainstorm дал оценки, но не полный каталог.
*   **Границы (Out of Scope):**
    *   Не инвентаризируем Application/Infrastructure слои
    *   Не меняем код

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Полный каталог Domain-файлов: путь, категория, LOC
- [ ] Карта зависимостей между Static/Dynamic/Shared namespace'ами
- [ ] Выявление cross-references (какие файлы Static/ зависят от Dynamic/ и наоборот)
- [ ] Группировка по кластерам для оценки границ split

### 🟡 Should Have (Желательно)
- [ ] Таблица «VO → consumer count» для оценки blast radius изменений
- [ ] Метрики: общие LOC, распределение по категориям, средний размер файла

### ⚫ Won't Have (Не будем делать)
- [ ] Рекомендации по декомпозиции (это — отдельный анализ)

## 5. Definition of Done (Критерии приёмки)
- [ ] Документ создан в `docs/releases/`
- [ ] 100% покрытие Domain-файлов Orchestrator
- [ ] Cross-reference таблица Static ↔ Dynamic заполнена
- [ ] Обновить Roadmap: статус AI#13 `📋` → `✅ Done`, добавить ссылку на документ и PR (если есть)

## 6. Verification (Самопроверка)
Аналитическая задача — `make check` не требуется.

## 7. Risks and Dependencies (Риски и зависимости)
- Нет технических рисков (анализ, не код)

## 8. Sources (Источники)
- [ ] [Roadmap AI#13](../../docs/releases/ROADMAP-2026-Q2-Q3.md)
- [ ] [Протокол brainstorm #2](../var/sessions/brainstorm/2026-04-30_16-02-26/result.md) — данные Шерлока по инвентаризации

## 9. Comments (Комментарии)
Roadmap Sprint 2, AI#13. Предшественник — brainstorm-протокол, где Шерлок уже провёл частичную инвентаризацию (cross-references = 0 между Static/ и Dynamic/, 3 кластера по LOC).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-30 | pi | Создание задачи |
