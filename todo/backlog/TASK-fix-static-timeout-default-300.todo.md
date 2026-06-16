---
type: fix
created: 2026-06-16
value: V2
complexity: C2
priority: P3
depends_on: [TASK-fix-cli-default-timeout-overrides-chain]
epic:
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: backlog
---

# TASK-fix-static-timeout-default-300: Вернуть intended default 300s для static-цепочек (осознанное behavior change)

## 0. Простое описание (Human Brief)

### Контекст
`TASK-fix-cli-default-timeout-overrides-chain` закрывает longstanding-баг: CLI default `--timeout=600` молча затирал `chain.timeout`. При этом выяснилось, что `StaticExecutionStrategyService::DEFAULT_STATIC_TIMEOUT = 300` (создан как intended default для static-цепочек) **фактически никогда не применялся** — из-за того же бага CLI static-цепочки всегда получали 600.

Решение Тимлида по ревью Пуаро (CR-1): в fix-задаче `DEFAULT_STATIC_TIMEOUT` **поднят до 600** (back-compat-нейтрально — static-цепочки без `chain.timeout` продолжают работать с 600 как раньше). Эта задача — вернуть intended 300 как **осознанное product-решение** с явным behavior change в CHANGELOG и migration-hint.

### Problem
300 — архитектурно intended default для static (быстрее dynamic 600, т.к. static-шаги детерминированные). Но молчаливое изменение 600→300 в fix-задаче = тихий regression для публичного пакета. Решение принято: оставить 600 в fix-задаче, вернуть 300 здесь — отдельно, с явным объявлением breaking change.

### Ожидаемый результат (Expected Result)
- `DEFAULT_STATIC_TIMEOUT` возвращён к 300.
- `## [Unreleased] → ### Changed` в `CHANGELOG.md`: **Behavior change / potentially breaking для static-цепочек без `chain.timeout` (600 → 300 с)** + migration-hint («добавьте `timeout: 600` в chains.yaml, если зависит от 10-минутного шага»).
- Предупреждение блоком в `docs/guide/chains.md` рядом с описанием hard defaults.

## 1. Context and Scope
* **Где делаем:** `src/Module/ChainExecution/Application/Service/Chain/StaticExecutionStrategyService.php` (`DEFAULT_STATIC_TIMEOUT`), `CHANGELOG.md`, `docs/guide/chains.md`.
* **Out of scope:** логика precedence (уже корректна в fix-задаче); dynamic timeout; CLI mapping.

## 3. Requirements (MoSCoW)
### 🔴 Must Have
- [ ] `DEFAULT_STATIC_TIMEOUT = 300`.
- [ ] Запись в `CHANGELOG.md` (`## [Unreleased] → ### Changed`) с явной пометкой Behavior change / potentially breaking + migration-hint.
- [ ] Предупреждение в `docs/guide/chains.md`.
- [ ] Удалена `@techdebt`-ссылка на эту задачу (поставлена в fix-задаче).

## 6. Sources
- Ревью Пуаро CR-1: `todo/done/TASK-fix-cli-default-timeout-overrides-chain.todo.md` (после merge).
- Git-история `DEFAULT_STATIC_TIMEOUT` (commit `31b1caa`, 2026-05-22) — изначально 300.

## 7. Comments
Задача создана как часть решения CR-1 вариант (a) по итогам code review Пуаро. Не блокирует fix-задачу — выполняется после её merge.

## Change History
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-06-16 | Тимлид (Алекс) | Создание follow-up к CR-1 (вариант a: 600 в fix-задаче, 300 здесь) |
