---
type: feat
created: 2026-05-02
value: V2
complexity: C1
priority: P1
depends_on:
epic: EPIC-sprint-9-resilience-observability
author: system_analyst_sherlock (Шерлок)
assignee:
branch:
pr:
status: todo
---

# TASK-feat-error-classification: Error classification: упрощённая по exitCode/timeout

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда [`RetryingAgentRunner`](../../src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunner.php) получает ошибку от runner'а и retry-all без разбора — даже при FATAL ошибках (невалидный API-ключ, process crash) — я хочу добавить классификацию ошибок по [`AgentResultVo`](../../src/Module/AgentRunner/Domain/ValueObject/AgentResultVo.php)-полям (`exitCode`, `isTimedOut()`, `isError()`), чтобы FATAL ошибки не retryлись и цепочка падала быстрее.

### Goal (Цель по SMART)
Создать [`Value Object`](../../docs/conventions/core_patterns/value-object.md) `ErrorClassificationVo` в Domain AgentRunner с enum `ErrorClassificationEnum` (FATAL/TRANSIENT/UNKNOWN). Интегрировать в [`RetryingAgentRunner`](../../src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunner.php): FATAL → не retry, TRANSIENT → retry с backoff, UNKNOWN → retry (консервативно). Правила классификации: по `exitCode` + `isTimedOut()`. ~30 строк бизнес-логики. Срок: 0.5 дня.

## 2. Context and Scope (Контекст и Границы)
### Где делаем
- `src/Module/AgentRunner/Domain/ValueObject/ErrorClassificationVo.php` — новый [`Value Object`](../../docs/conventions/core_patterns/value-object.md)
- `src/Module/AgentRunner/Domain/Enum/ErrorClassificationEnum.php` — новый [`Enum`](../../docs/conventions/core_patterns/enum.md)
- [`src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunner.php`](../../src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunner.php) — интеграция classification
- `tests/Unit/Module/AgentRunner/Domain/ValueObject/ErrorClassificationVoTest.php` — новый тест
- `tests/Unit/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunnerTest.php` — обновить

### Текущее поведение
- [`RetryingAgentRunner`](../../src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunner.php) (строка ~63–85): retry при исключении И при `$result->isError()` без разбора. Если API-ключ невалиден (FATAL) — 3 попытки × exponential backoff = ~7 секунд бесполезного ожидания.
- [`AgentResultVo`](../../src/Module/AgentRunner/Domain/ValueObject/AgentResultVo.php) уже содержит `exitCode`, `isError()`, `isTimedOut()` — есть чем классифицировать, не нужно парсить текст.

### Границы (Out of Scope)
- Не парсим текст ошибки — только `exitCode`, `isTimedOut()`, `isError()`
- Не создаём отдельный ErrorClassifier service — достаточно статического метода в VO
- Не меняем [`RetryPolicyVo`](../../src/Module/AgentRunner/Domain/ValueObject/RetryPolicyVo.php) — текущий интерфейс достаточен
- Не добавляем per-error-type retry policies (один policy для всех TRANSIENT)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] [`Enum`](../../docs/conventions/core_patterns/enum.md) `ErrorClassificationEnum`: FATAL, TRANSIENT, UNKNOWN
- [ ] [`Value Object`](../../docs/conventions/core_patterns/value-object.md) `ErrorClassificationVo` с factory-методом `classify(AgentResultVo): self` и статическими правилами классификации
- [ ] Правила классификации:
  - `isTimedOut() == true` → TRANSIENT (network issue, retry имеет смысл)
  - `exitCode >= 100` → FATAL (process-level crash, retry бессмысленен)
  - `exitCode == 0 && isError() == true` → UNKNOWN (аномалия, retry консервативно)
  - `exitCode > 0 && exitCode < 100` → TRANSIENT (по умолчанию retry)
- [ ] [`RetryingAgentRunner`](../../src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunner.php) проверяет classification → FATAL = не retry, TRANSIENT/UNKNOWN = retry
- [ ] Unit-тесты: ErrorClassificationVo покрывает все 4 правила + RetryingAgentRunner с FATAL/TRANSIENT
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные

### 🟡 Should Have (Желательно)
- [ ] Логирование: при FATAL classification — warning с указанием exitCode

### 🟢 Could Have (Опционально)
- [ ] Extension point: интерфейс `ErrorClassifierInterface` для будущих стратегий классификации

### ⚫ Won't Have (Не будем делать)
- [ ] Классификация по тексту ошибки (exitCode/timeout достаточно)
- [ ] Per-runner семантика exit codes
- [ ] Настраиваемые правила классификации через YAML

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем (Левша) перед стартом.*

1. [ ] Создать `ErrorClassificationEnum` (FATAL, TRANSIENT, UNKNOWN) в `src/Module/AgentRunner/Domain/Enum/`
2. [ ] Создать `ErrorClassificationVo` в `src/Module/AgentRunner/Domain/ValueObject/` с методом `classify(AgentResultVo): self`
3. [ ] Интегрировать в `RetryingAgentRunner::run()`: после получения error result → classify → FATAL = return error immediately
4. [ ] Unit-тесты: `ErrorClassificationVoTest` (4 правила + edge cases), обновить `RetryingAgentRunnerTest`
5. [ ] Проверить Psalm и Deptrac

### Структура файлов
```
src/Module/AgentRunner/Domain/Enum/ErrorClassificationEnum.php                    — новый
src/Module/AgentRunner/Domain/ValueObject/ErrorClassificationVo.php               — новый
src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunner.php             — изменить
tests/Unit/Module/AgentRunner/Domain/ValueObject/ErrorClassificationVoTest.php    — новый
tests/Unit/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunnerTest.php  — обновить
```

## 5. Definition of Done (Критерии приёмки)
- [ ] `ErrorClassificationEnum` + `ErrorClassificationVo` в Domain AgentRunner
- [ ] [`RetryingAgentRunner`](../../src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunner.php) не retry на FATAL
- [ ] Unit-тесты покрывают все 4 правила классификации + интеграцию в RetryingAgentRunner
- [ ] Обратная совместимость: существующие цепочки с TRANSIENT ошибками retryтся как раньше
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные
- [ ] Deptrac green

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Module/AgentRunner/
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Runner-specific exit codes:** Pi runner и Codex runner могут использовать exit code ≠ 0 для TRANSIENT ошибок. Митигация: консервативный подход — только exitCode ≥ 100 = FATAL, всё остальное → TRANSIENT (retry).
- **3 попытки за 7 секунд — не катастрофа:** Pain level 2/10. Задача дешёвая (0.5 дня, ~30 строк), поэтому ROI оправдан.
- **Нет зависимости от других задач Sprint 9** — можно выполнять параллельно.

## 8. Sources (Источники)
- [ ] [Анализ Локи: Error Classification](../../docs/research/analytical/loki-roadmap-review-2026-05.md) — упрощённая реализация
- [ ] [RetryingAgentRunner](../../src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunner.php)
- [ ] [AgentResultVo](../../src/Module/AgentRunner/Domain/ValueObject/AgentResultVo.php)
- [ ] [RetryPolicyVo](../../src/Module/AgentRunner/Domain/ValueObject/RetryPolicyVo.php)
- [ ] [Конвенция: Value Object](../../docs/conventions/core_patterns/value-object.md)

## 9. Comments (Комментарии)
- Pain level: 2/10, но cost = 0.5 дня (~30 строк). Value/Cost = высокий. Дешёвая победа.
- Мини-реализация из отчёта Локи:
  ```php
  if ($result->isTimedOut()) return TRANSIENT;
  if ($result->getExitCode() >= 100) return FATAL;
  if ($result->getExitCode() === 0 && $result->isError()) return UNKNOWN;
  return TRANSIENT;
  ```

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-02 | system_analyst_sherlock (Шерлок) | Создание задачи |
