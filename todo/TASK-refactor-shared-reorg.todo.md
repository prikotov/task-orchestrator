---
type: refactor
created: 2026-04-30
value: V2
complexity: C1
priority: P2
depends_on:
epic: EPIC-refactor-orchestrator-decomposition
author: pi
assignee:
branch:
pr:
status: todo
---

# TASK-refactor-shared-reorg: Переразложение Shared/ каталога

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда каталог Shared/ содержит интерфейсы, часть из которых используется только Static, а часть только Dynamic, я хочу переразложить их по правильным namespace'ам, чтобы границы между Static/Dynamic/Shared были явными до физического split.

### Goal (Цель по SMART)
Переместить 6 интерфейсов из `Shared/`: Static-only → `Static/`, Dynamic-only → `Dynamic/`, ChainLoader → `Application/`. Общие интерфейсы остаются в `Shared/`.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/Orchestrator/Domain/Service/Chain/Shared/`
*   **Текущее поведение:** 7 интерфейсов в Shared/, часть — strategy-specific
*   **Границы (Out of Scope):**
    *   Не создаём Integration-слой
    *   Не меняем Application/Infrastructure слои

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Static-only интерфейсы перемещены в `Static/` namespace
- [ ] Dynamic-only интерфейсы перемещены в `Dynamic/` namespace
- [ ] ChainLoaderInterface → `Application/` namespace
- [ ] Общие интерфейсы остаются в `Shared/`
- [ ] Deptrac green

### 🟡 Should Have (Желательно)
- [ ] Инвентаризация: какой интерфейс куда и почему

### ⚫ Won't Have (Не будем делать)
- [ ] Физический split модуля (AI#17)

## 5. Definition of Done (Критерии приёмки)
- [ ] 6 интерфейсов перемещены в правильные namespace'ы
- [ ] Shared/ содержит только общие интерфейсы
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные
- [ ] Deptrac green
- [ ] Обновить Roadmap: статус AI#16 `📋` → `✅ Done`, добавить ссылку на задачу и PR

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
```

## 7. Risks and Dependencies (Риски и зависимости)
- Низкий риск: механическое перемещение файлов + namespace update

## 8. Sources (Источники)
- [ ] [Roadmap AI#16](../../docs/releases/ROADMAP-2026-Q2-Q3.md)

## 9. Comments (Комментарии)
Roadmap Sprint 7. Выполняется перед AI#17 (физический split Static).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-30 | pi | Создание задачи |
