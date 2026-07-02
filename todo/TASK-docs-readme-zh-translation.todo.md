---
type: docs
created: 2026-07-01
value: V1
complexity: C1
priority: P3
depends_on:
epic:
author: Бэкендер (Левша)
assignee:
branch:
pr:
status: todo
---

# TASK-docs-readme-zh-translation: Синхронизировать README.zh.md с правками become-role

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
В `README.zh.md` (китайский, традиционный) отсутствуют правки из PR #289: нет `become-role` в таблице skills и нет шага `agent:init` в Quick Start. Русская (`README.md`) и английская (`README.en.md`) версии уже обновлены.

### Варианты или путь решения (Solution Sketch)
Добавить в `README.zh.md` те же две правки, что в `README.md`/`README.en.md`, на традиционном китайском. Перевод должен выполнить носитель языка — машинный/не-носитель даёт неточности.

### Ожидаемый результат (Expected Result)
`README.zh.md` содержит `become-role` в таблице skills и шаг `agent:init` после `composer require`, на традиционном китайском.

## 1. Concept and Goal (Концепция и Цель)

### Goal (Цель по SMART)
Синхронизировать `README.zh.md` с `README.md`/`README.en.md` по become-role и agent:init, перевод носителем традиционного китайского.

## 2. Context and Scope (Контекст и границы)

### In Scope (Что делаем)
- `become-role` в таблице skills (`## 技能（Skills）`).
- Шаг `agent:init` в Quick Start (`composer require` → `agent:init`).

### Out of Scope (Чего НЕ делаем)
- Перевод остального README (только две новые правки).
- Перевод SKILL.md/README.md become-role (они в пакете, отдельная задача).

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have
- [ ] `become-role` строка в таблице skills `README.zh.md`.
- [ ] Шаг `agent:init` в Quick Start `README.zh.md`.

### ⟫ Won't Have (Не будем делать)
- Полный перевод README — только две правки.

## 4. Implementation Plan (План реализации)
1. Носитель традиционного китайского переводит описание `become-role` и шаг `agent:init` (см. `README.md`/`README.en.md`).
2. Внести правки в `README.zh.md`.

## 5. Definition of Done (Критерии приёмки)
- `README.zh.md` содержит обе правки, перевод корректен (носитель).
- `make validate-roles`/md-links — зелёные (если применимо).

## 6. Verification (Самопроверка)
- [ ] Содержание `become-role`/`agent:init` есть в `README.zh.md`.
- [ ] Перевод вычитан носителем.

## 7. Risks and Dependencies (Риски и зависимости)
- Требуется носитель традиционного китайского — машинный перевод неприемлем (риск неточностей в технической терминологии).

## 8. Sources (Источники)
- `README.md`, `README.en.md` — актуальные версии (эталон перевода).
