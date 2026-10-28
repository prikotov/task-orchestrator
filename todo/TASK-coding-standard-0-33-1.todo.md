---
type: build
created: 2026-10-28
due:
started: 2026-10-28 14:15:43 (1793196943)
completed:
cancelled:
value: V1
complexity: C1
priority: P2
cost_plan:
cost_fact:
depends_on:
epic:
author: Тимлид Алекс (pi)
assignee: Тимлид Алекс (pi)
consultants: []
reviewer:
approver:
informed: []
branch: task/coding-standard-0-33-1
pr: https://github.com/prikotov/task-orchestrator/pull/438
status: review
---

# TASK-coding-standard-0-33-1: обновить prikotov/coding-standard до актуального релиза

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Проверка `coding-standard-verify` падает на `main`: `composer.lock` удерживает `prikotov/coding-standard` 0.33.0, хотя вышел более новый релиз. Из-за этого `make check` завершается ошибкой до основных этапов — это блокирует проверки всех задач.

### Варианты или путь решения (Solution Sketch)
Обновить пакет `prikotov/coding-standard` через Composer до актуального релиза и закрепить подключение проверок `coding-standard-verify`/`coding-standard-di-check` в `make check`.

### Ожидаемый результат (Expected Result)
`make check` проходит все этапы, включая проверку актуальности coding-standard.

## 1. Концепция и Цель (Concept and Goal)
### История (User Story или Job Story)
> **Job Story:** Когда `make check` падает на проверке актуальности coding-standard, я хочу обновить пакет до актуального релиза, чтобы проверки задач проходили без посторонних ошибок.

### Цель по SMART (Goal)
Обновить `prikotov/coding-standard` до 0.33.2 в `composer.lock`, добавить `coding-standard-verify` и `coding-standard-di-check` в цель `check` Makefile; `make check` зелёный.

## 2. Контекст и Границы (Context and Scope)
*   **Где делаем:** `composer.lock`, `Makefile`.
*   **Текущее поведение:** `coding-standard-verify` сообщает `composer.lock has 0.33.0, latest release is 0.33.1` и завершается ошибкой.
*   **Границы (Out of Scope):** не обновляем прочие зависимости (в т.ч. пакеты с advisories `symfony/*` — отдельная задача), не меняем PHP-код.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] `prikotov/coding-standard` обновлён до актуального релиза.
- [x] `coding-standard-verify` и `coding-standard-di-check` проходят.
### 🟡 Желательно (Should Have)
- [x] Проверки закреплены в цели `check` Makefile.
### 🟢 Опционально (Could Have)
- [ ] Завести задачу на обновление `symfony/*` с security advisories.
### ⚫ Не будем делать (Won't Have)
- [ ] Обновление прочих зависимостей и рефакторинг.

## 4. План реализации (Implementation Plan)
1. [x] `composer update prikotov/coding-standard --with-all-dependencies`.
2. [x] Проверить `coding-standard-verify`, `coding-standard-di-check`.
3. [ ] Прогнать `make check`, оформить PR.

## 5. Критерии приёмки (Definition of Done)
- [x] Нет регрессий (`make check` зелёный).

## 6. Самопроверка (Verification)
```bash
make check                                            # полный набор проверок проекта
php vendor/bin/todo-md validate todo/TASK-coding-standard-0-33-1.todo.md
```

## 7. Риски и зависимости (Risks and Dependencies)
- `composer audit` сообщает 2 advisories по `symfony/*` — существовали до правки и не устраняются здесь (отдельная задача).

## 8. Источники (Sources)
- [x] Вывод `vendor/bin/coding-standard-verify` на `main`.

## 9. Комментарии (Comments)
Обнаружено при проверках задачи TASK-chains-thinking-levels-tuning; вынесено в отдельный PR по правилу «не смешивать изменения из разных задач».
