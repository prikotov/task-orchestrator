---
type: chore
created: 2026-09-23 14:44:13 (1790174653)
due: 
started: 2026-09-23 14:44:13 (1790174653)
completed: 2026-09-23 14:49:12 (1790174952)
cancelled: 
value: V2
complexity: C1
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Бэкендер Тони (pi)
assignee: Бэкендер Тони (pi)
branch: task/codex-gpt6-profiles
pr: https://github.com/prikotov/task-orchestrator/pull/408
status: done
---

# TASK-chore-codex-gpt6-profiles: Переход пяти активных профилей Codex CLI на GPT-6

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Пять активных профилей Codex CLI в `config/chains.yaml` работают на моделях GPT‑5.6 с неспециализированными уровнями рассуждения: координация и быстрая разработка получают избыточно глубокое рассуждение (медленно и дорого), основной архитектор — наоборот, недостаточно глубокое.

### Варианты или путь решения (Solution Sketch)
- Точечно заменить модель и `model_reasoning_effort` в пяти активных (незакомментированных) codex-командах: GPT‑6-варианты по назначению роли + углублённое рассуждение основному архитектору, среднее — координации, быстрому архитектурному разбору и разработке, высокое — технической редактуре.
- Закомментированные альтернативные конфигурации и профили на других раннерах не трогать.

### Ожидаемый результат (Expected Result)
- Все пять ролей на Codex CLI запускаются на целевых моделях GPT‑6 с уровнями рассуждения, подобранными по назначению роли; конфигурация проходит проверки проекта.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **User Story:** Как владелец оркестратора цепочек, я хочу, чтобы роли на Codex CLI использовали модели GPT‑6 с уровнем рассуждения по назначению роли, чтобы балансировать качество, скорость и стоимость запусков цепочек.

### Цель по SMART (Goal)
- В `config/chains.yaml` заменить в пяти активных codex-командах пару модель/рассуждение:
  - `team_lead_alex` → `gpt-6-sol`, `medium`;
  - `system_architect_gandalf` → `gpt-6-astra`, `high`;
  - `system_architect_loki` → `gpt-6-sol`, `medium`;
  - `backend_developer_tony` → `gpt-6-sol`, `medium`;
  - `technical_writer_ostap` → `gpt-6-luna`, `high`.
- PHPUnit и Psalm — зелёные.

## 2. Контекст и Границы (Context and Scope)
* **Где делаем:** `config/chains.yaml` (только активные codex-команды пяти ролей).
* **Границы (Out of Scope):** закомментированные альтернативы (`pi + zai`, `pi + openai-codex`); профиль `system_analyst_sherlock` и прочие роли на `pi`; документация (упоминаний старых моделей нет).

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] `team_lead_alex`: `gpt-5.6-terra`/`high` → `gpt-6-sol`/`medium`.
- [x] `system_architect_gandalf`: `gpt-5.6-sol`/`medium` → `gpt-6-astra`/`high`.
- [x] `system_architect_loki`: `gpt-5.6-terra`/`xhigh` → `gpt-6-sol`/`medium`.
- [x] `backend_developer_tony`: `gpt-5.6-terra`/`high` → `gpt-6-sol`/`medium`.
- [x] `technical_writer_ostap`: `gpt-5.6-terra`/`high` → `gpt-6-luna`/`high`.
- [x] Закомментированные блоки и прочие профили не изменены.
- [x] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные.
### ⚫ Won't Have (Не будем делать)
- Изменение профилей на других раннерах (`pi`, `zai`, `openai-codex`), цепочек (`chains:`), прокси-настроек и документации.

## 4. План реализации (Implementation Plan)
1. [x] Ветка `task/codex-gpt6-profiles` от актуального `main`.
2. [x] Точечная замена модели/рассуждения в пяти активных codex-командах `config/chains.yaml`.
3. [x] Проверка: имена моделей и уровни рассуждения соответствуют цели; закомментированные блоки не тронуты; в тестах и документации ссылок на старые модели нет.
4. [x] `vendor/bin/phpunit`, `vendor/bin/psalm`.
5. [x] PR по регламенту.

## 5. Критерии приёмки (Definition of Done)
- [x] Пять активных профилей Codex CLI — на целевых моделях GPT‑6 с заданными уровнями рассуждения (9 заменённых строк в `config/chains.yaml`).
- [x] PHPUnit: OK (1572 теста, 4448 утверждений; 1 предсуществующее устаревание вне зоны правки, 2 skipped).
- [x] Psalm: ошибок нет.
- [x] Задача в `done`, PR создан.

## 6. Самопроверка (Verification)
```bash
grep -n "gpt-\|model_reasoning_effort" config/chains.yaml
php vendor/bin/todo-md validate todo/TASK-chore-codex-gpt6-profiles.todo.md
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Риски и зависимости (Risks and Dependencies)
- Выбор моделей — конфигурационное решение, не утверждение о доказанном превосходстве конкретных моделей; при необходимости откат — точечная правка YAML.
- Имена моделей GPT‑6 (`gpt-6-sol`, `gpt-6-astra`, `gpt-6-luna`) должны быть доступны в аккаунте Codex; в противном случае запуск шага роли завершится ошибкой раннера (локальная проверка запусков — вне рамок задачи).

## 8. Источники (Sources)

## 9. Комментарии (Comments)
- Уровни рассуждения по назначению ролей: углублённый (`high`) — основному архитектору (`gpt-6-astra`) и технической редактуре (`gpt-6-luna`); средний (`medium`) — координации (`team_lead_alex`), быстрому архитектурному разбору (`system_architect_loki`) и разработке (`backend_developer_tony`).

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-23 14:44:13 (1790174653) | Бэкендер Тони (pi) | Создание задачи |
| 2026-09-23 14:45:00 (1790174700) | Бэкендер Тони (pi) | Старт задачи, заполнение постановки |
| 2026-09-23 21:50:00 (1790175000) | Бэкендер Тони (pi) | Замена пяти профилей на GPT‑6, phpunit+psalm зелёные |
| 2026-09-23 22:05:00 (1790175900) | Бэкендер Тони (pi) | PR #408 создан, CI зелёный, задача закрыта |
