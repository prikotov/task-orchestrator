---
# Metadata (Метаданные)
type: feat
created: 2026-04-24
value: V2
complexity: C2
priority: P2
depends_on:
epic: EPIC-feat-standalone-cli
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: todo
---

# TASK-feat-timeout-exit-code: Propagation таймаута в CLI exit code

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story или Job Story)
> **Job Story:** Когда шаг цепочки превышает таймаут, я хочу получать exit code 6 (timeout), чтобы CI-пайплайн отличал таймаут от других ошибок и мог принять решение (retry / increase timeout / fail).

### Goal (Цель по SMART)
Реализовать propagation информации о таймауте от AgentRunner до OrchestrateCommand, чтобы `ProcessTimedOutException` в `PiAgentRunner` приводил к exit code 6 вместо generic error (1).

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `src/Module/AgentRunner/Infrastructure/Service/Pi/PiAgentRunner.php` — источник `ProcessTimedOutException`
    *   `src/Module/Orchestrator/Application/UseCase/Command/OrchestrateChain/OrchestrateChainResultDto.php` — флаг timeout
    *   `src/Module/Orchestrator/Application/Service/ResolveExitCodeService.php` — маппинг timeout → exit code 6
    *   `apps/console/src/Module/Orchestrator/Command/OrchestrateCommand.php` — уже описан exit code 6 в PHPDoc
    *   `tests/` — unit + integration
*   **Текущее поведение:** `OrchestrateExitCodeEnum::timeout = 6` определён, но никогда не возвращается. `ProcessTimedOutException` обрабатывается внутри `PiAgentRunner` и не доходит до Command — Command получает generic error (exit code 1).
*   **Границы (Out of Scope):** Не меняем механику самого таймаута (Symfony Process timeout), не добавляем retry при таймауте.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] OrchestrateChainResultDto содержит информацию о том, что шаг завершился по таймауту
- [ ] ResolveExitCodeService маппит timeout-результат в exit code 6
- [ ] OrchestrateCommand корректно отображает timeout в CLI-выводе
- [ ] Unit-тесты на timeout-сценарий

### 🟡 Should Have (Желательно)
- [ ] Integration-тест с реальным таймаутом (короткий timeout + долгий процесс)

### 🟢 Could Have (Опционально)
- [ ] CLI-вывод: различать timeout шага от timeout цепочки

### ⚫ Won't Have (Не будем делать)
- [ ] Автоматический retry при таймауте
- [ ] Изменение механики Symfony Process timeout
- [ ] Graceful shutdown с partial result при таймауте

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем перед стартом.*

## 5. Definition of Done (Критерии приёмки)
- [ ] Таймаут шага → exit code 6 (не 1)
- [ ] Обычная ошибка шага → exit code 1 (без изменений)
- [ ] PHPUnit и Psalm зелёные
- [ ] `@todo` в `OrchestrateExitCodeEnum.php` удалён (заменён ссылкой на задачу → затем на PR)

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- `ProcessTimedOutException` может перехватываться на нескольких уровнях (RetryingAgentRunner, CircuitBreakerAgentRunner) — нужно убедиться, что информация о таймауте не теряется
- Необходимость добавить поле в DTO — возможное влияние на существующие тесты

## 8. Sources (Источники)
- [ ] [OrchestrateExitCodeEnum.php](../../src/Module/Orchestrator/Application/Enum/OrchestrateExitCodeEnum.php) — @todo на строке 33
- [ ] [ResolveExitCodeService](../../src/Module/Orchestrator/Application/Service/ResolveExitCodeService.php)
- [ ] [PiAgentRunner](../../src/Module/AgentRunner/Infrastructure/Service/Pi/PiAgentRunner.php)

## 9. Comments (Комментарии)
Варианты реализации propagation:
1. Новое поле `bool $timedOut` в `OrchestrateChainResultDto` и `StepResultDto`
2. Domain-исключение `ChainTimeoutException`, перехватываемое в Command
3. Флаг в существующем `StepResultDto::$isError` + отдельный `?string $errorType`

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-24 | Тимлид (Алекс) | Создание задачи |
