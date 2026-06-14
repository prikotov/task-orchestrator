---
type: refactor
created: 2026-05-21
value: V2
complexity: C2
priority: P2
depends_on:
epic: EPIC-refactor-phpmd-baseline-elimination
author: Тимлид (Алекс)
assignee: Бэкендер (Левша)
branch: task/refactor-phpmd-retrying-runner-run
pr: https://github.com/prikotov/task-orchestrator/pull/259
status: done
---

# TASK-refactor-phpmd-retrying-runner-run: Уменьшить RetryingAgentRunnerService::run() с 112 до ≤79 строк

## 0. Простое описание (Human Brief)
Устранить PHPMD violation (technical debt), чтобы код соответствовал порогам проекта.

### Проблема простыми словами (Problem)
Метод или класс превышает порог PHPMD, suppression в baseline маскирует проблему.

### Варианты или путь решения (Solution Sketch)
Экстракция приватных методов или рефакторинг для уменьшения LOC.

### Ожидаемый результат (Expected Result)
PHPMD baseline пуст, `make phpmd-full` = 0 violations.

## 0. Простое описание (Human Brief)
Устранить PHPMD violation (technical debt), чтобы код соответствовал порогам проекта.

### Проблема простыми словами (Problem)
Метод или класс превышает порог PHPMD, suppression в baseline маскирует проблему.

### Варианты или путь решения (Solution Sketch)
Экстракция приватных методов для уменьшения LOC.

### Ожидаемый результат (Expected Result)
PHPMD baseline пуст, `make phpmd-full` = 0 violations.

## 1. Concept and Goal (Концепция и Цель)
### Story
Как разработчик, я хочу чтобы метод `run()` в `RetryingAgentRunnerService` был ≤79 строк, чтобы соответствовал порогу PHPMD ExcessiveMethodLength (80 LOC) и был проще для понимания.

### Goal
Декомпозировать `RetryingAgentRunnerService::run()` (112 LOC) до ≤79 LOC через экстракцию приватных методов, не изменяя поведение.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunnerService.php`
*   **Текущее поведение:** метод `run()` содержит 112 строк (retry loop с exponential backoff, JSONL streaming, error handling)
*   **Границы (Out of Scope):** не меняем retry-логику, не меняем контракт интерфейса

## 3. Requirements (MoSCoW)
### 🔴 Must Have
- [x] Метод `run()` ≤79 LOC  *(фактически 45 физических строк, sig..brace)*
- [x] Все существующие тесты проходят  *(26 unit-тестов класса + полный набор 963 теста OK)*
- [x] Удалить запись из `phpmd.baseline.xml`

### ⚫ Won't Have (Не будем делать)
- Изменение retry-стратегии или контракта
- Изменение порогов в phpmd.xml

## 4. Implementation Plan
Исполнитель: Бэкендер (Левша).

Цель: декомпозировать `run()` (112 LOC) до ≤79 LOC экстракцией приватных методов, **не меняя поведение** (контракт интерфейса, retry-стратегия, метрики, логи — идентичны).

План:
1. Оставить цикл `while` оригинала (проверенная структура) с ранними `return` внутри `try` и фолл-через до ожидания, тело ~45 LOC. Первоначально пробовался вариант на `for`+`continue` — см. п. «Риски» (PHPMD-bug).
2. Вынести приватные методы (намерениеименование + DRY для повторяющихся веток):
   - `recordAttemptMetric(runnerName, attempt)` — счётчик `runner.attempt`;
   - `recordErrorAndLog(runnerName, attempt, throwable)` — счётчик `runner.error` + `logRetryAttempt` (используется в 2 ветках: исключение и transient-error-result);
   - `logFatalError(runnerName, result)` — warning «fatal error (exitCode=…), skipping retry»;
   - `waitBeforeNextAttempt(runnerName, attempt)` — guard `attempt >= maxRetries`, расчёт delay, debug-лог, `usleep`;
   - `finalizeSuccess(result, startTime, runnerName, attempt)` — метрика `success` + info-лог «succeeded on attempt»;
   - `finalizeExhausted(startTime, runnerName, lastResult, lastThrowable)` — метрика `exhausted` + warning + `AgentResultVo::createError` с пробросом `timedOut`.
   - Существующие `logRetryAttempt()` и `recordDuration()` оставить без изменений.
3. Эквивалентность тайминга ожидания: исходно delay = `getDelayForAttempt(k)` (k — 0-индекс текущей попытки), лог «before attempt (k+2)/(max+1)», ожидание только при `k < maxRetries`. В новом коде `waitBeforeNextAttempt` вызывается с pre-increment `k` до `$attempt++` → те же значения.
4. Удалить одну запись из `phpmd.baseline.xml` для `RetryingAgentRunnerService::run`.
5. Проверки: `vendor/bin/phpunit` (класс + полный набор), `vendor/bin/psalm`, `make phpmd`, `make check`.

Границы сохранены: retry-логика, контракт интерфейса, пороги phpmd.xml — без изменений.

## 5. Definition of Done
- [x] `phpmd` не ругается на `RetryingAgentRunnerService::run()`  *(make phpmd = No violations, проверено многократно)*
- [x] `make check` зелёный  *(✅ все стадии)*
- [x] Запись убрана из baseline

## 6. Verification
```bash
make phpmd
make check
```

## 7. Risks and Dependencies
- Экстракция может потребовать передачи многих локальных переменных — рассмотрено; обошлись передачей примитивов (runnerName/attempt/throwable), без отдельного VO-контекста.
- **PHPMD 2.15.0 directory-bug (найдено в работе).** В multi-file анализе (`analyze src`) PHPMD эпизодически ложно атрибутирует `run()` «112 LOC» независимо от реальной длины (исходные 112 строк и рефакторенные 45 строк воспроизводят одинаково). Single-file анализ и анализ в изоляции метод считают корректно. В простое (без конкурентной записи файлов) — 0 ложных срабативаний на 70+ прогонов; «112» воспроизводилось только при активной модификации файлов во время анализа (PHP-Depend читает недозаписанный файл). В CI (чистый чекаут, без конкурентных записей) не ожидается. Та же природа бага зафиксирована в `@todo 2026-05-21: PHPMD bug` (`ExecuteAgentStepService`, `RunStaticChainService`) и входит в scope эпика. Удаление baseline-записи безопасно для CI.

## 8. Sources
- `src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunnerService.php`
- `tests/Unit/Infrastructure/Service/AgentRunner/RetryingAgentRunnerTest.php`

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-21 | Тимлид (Алекс) | Создание задачи |
| 2026-06-13 | Бэкендер (Левша) | Reverse Briefing: заполнен Implementation Plan, статус → in_progress, назначен исполнитель, создана ветка |
| 2026-06-13 | Бэкендер (Левша) | Реализация: `run()` 112 → 45 LOC (while + экстракция 6 приватных методов), запись удалена из baseline. PHPUnit 963 OK, Psalm 0 ошибок, `make check` зелёный. Найден и задокументирован PHPMD directory-bug |
| 2026-06-13 | Ревьювер (Пуаро) | Code review: эквивалентность поведения подтверждена построчно (backoff-тайминг, метрики, ветки); архитектура/типизация/покрытие (26 кейсов) ок. Nit устранён: `finalizeExhausted` warning/createError унифицированы на `$runnerName`. APPROVE |
| 2026-06-13 | Бэкендер (Левша) | PR #259 создан (base=main, label=pi), статус → review |
| 2026-06-13 | Тимлид (Алекс) | Пользователь апрувил merge; задача → done, файл перенесён в done/, Epic обновлён |
