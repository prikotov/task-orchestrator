---
type: refactor
created: 2026-04-23
value: V2
complexity: C3
priority: P2
depends_on:
epic:
author: Архитектор (Локи)
assignee: Бэкендер Левша
branch: task/refactor-remove-runner-model-options
pr: #59
status: review
---

# TASK-refactor-remove-runner-model-options: Удалить опции --runner и --model из CLI и цепочек

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда роль (role) определена в config/chains.yaml с конкретным runner и model, я хочу, чтобы эти параметры не дублировались на уровне CLI-опций, чтобы исключить противоречие: модель и движок — атрибут роли, а не параметр запуска цепочки.

### Goal (Цель по SMART)
Удалить опции `--runner` (`-r`) и `--model` (`-m`) из `OrchestrateCommand`, `OrchestrateChainCommand` и всех downstream-потребителей. Runner и model должны определяться исключительно в конфигурации роли (`config/chains.yaml`, секция `roles`).

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `apps/console/src/Module/Orchestrator/Command/OrchestrateCommand.php` — удалить опции `--runner`, `--model`
    *   `src/Module/Orchestrator/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommand.php` — удалить свойства `$runner`, `$model`
    *   `src/Module/Orchestrator/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommandHandler.php` — убрать использование `$command->runner`, `$command->model`; брать runner/model из конфигурации роли через `ChainDefinitionVo`
    *   `src/Module/Orchestrator/Domain/ValueObject/DynamicChainContextVo.php` — убрать `$runnerName`, `$model` (брать из роли)
    *   `src/Module/Orchestrator/Domain/Service/Chain/Dynamic/ExecuteDynamicTurnService.php` — использовать runner/model из роли, не из контекста
    *   Все тесты, ссылающиеся на `runner`/`model` в command/context
*   **Текущее поведение:** CLI-опции `--runner` и `--model` пробрасываются через всю цепочку (Command → Handler → Context → ExecuteTurn) и могут переопределять то, что задано в роли. Это создаёт противоречие с архитектурным принципом «runner и model — атрибут роли».
*   **Границы (Out of Scope):**
    *   Не меняем формат `config/chains.yaml`
    *   Не трогаем `RunAgentCommand` (отдельный use case для прямого запуска агента)
    *   Не меняем static-цепочки, если runner/model берутся из конфигурации шага

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] Удалить `--runner` и `--model` из `OrchestrateCommand::configure()`
- [x] Удалить `$runner` и `$model` из `OrchestrateChainCommand`
- [x] `OrchestrateChainCommandHandler` берёт runner/model из конфигурации роли (`ChainDefinitionVo`) через `ChainStepVo::getRunner()` / `ChainStepVo::getModel()` или из дефолтов
- [x] `DynamicChainContextVo` не содержит полей `runnerName`/`model`; `ExecuteDynamicTurnService` получает их из конфигурации роли
- [x] Все тесты обновлены (unit + integration)
- [x] PHPUnit и Psalm проходят без ошибок

### 🟡 Should Have (Желательно)
- [x] Обновить PHPDoc и комментарии, где упоминаются runner/model как параметры команды

### 🟢 Could Have (Опционально)
- [ ] Добавить в chains.yaml пример роли без явного model (использует дефолт runner'а)

### ⚫ Won't Have (Не будем делать)
- [ ] Не меняем `RunAgentCommand` / `RunAgentCommandHandler`
- [ ] Не добавляем fallback runner/model на уровне роли (уже есть в chains.yaml)

## 4. Implementation Plan (План реализации)

1. **Domain: DynamicChainContextVo** — убрать поля `runnerName` и `model`.
2. **Domain: BuildDynamicContextServiceInterface + BuildDynamicContextService** — убрать параметры `runnerName` и `model` из `buildContext()` и `buildInvocation()`.
3. **Domain: ExecuteDynamicTurnService** — заменить `$context->runnerName` на `'pi'` (для логирования), `$context->model` на `null` (model определяется через roleConfig::command).
4. **Domain: ExecuteStaticStepService** — убрать параметры `$runnerName` и `$model` из `runAgentStep()`, брать `$step->getRunner()` и `$step->getModel()`.
5. **Domain: RunStaticChainService** — убрать параметры `$runnerName` и `$model` из `execute()`, `processStep()`, `executeStep()`. Runner/model определяются на уровне шага.
6. **Application: ExecuteStaticChainServiceInterface + ExecuteStaticChainService** — обновить сигнатуру `execute()`.
7. **Application: OrchestrateChainCommand** — убрать свойства `$runner` и `$model`.
8. **Application: OrchestrateChainCommandHandler** — убрать использование `$command->runner` и `$command->model`.
9. **CLI: OrchestrateCommand** — убрать опции `--runner` и `--model`.
10. **Tests** — обновить все тесты `OrchestrateChainCommandHandlerTest`.

## 5. Definition of Done (Критерии приёмки)
- [x] `php bin/console app:agent:orchestrate` не принимает `--runner` и `--model`
- [x] Runner и model определяются только из конфигурации роли в `config/chains.yaml`
- [x] Все существующие тесты проходят
- [x] Psalm без ошибок
- [x] PHPCS — sniff-test скрипт не установлен в окружении, пропущено

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
php vendor/prikotov/coding-standard/bin/run-sniff-tests.php
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск:** Static-цепочки используют `$command->runner` напрямую (`ExecuteStaticChainService`). Нужно убедиться, что runner для static-шагов также берётся из конфигурации роли.
- **Риск:** `BuildDynamicContextServiceInterface::buildContext()` принимает `runnerName` и `model` — нужно изменить сигнатуру интерфейса, что может затронуть несколько реализаций.
- **Зависимость:** Изменения в Domain VO и интерфейсах требуют согласованного рефакторинга.

## 8. Sources (Источники)
- [OrchestrateCommand.php](../../apps/console/src/Module/Orchestrator/Command/OrchestrateCommand.php)
- [OrchestrateChainCommand.php](../../src/Module/Orchestrator/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommand.php)
- [OrchestrateChainCommandHandler.php](../../src/Module/Orchestrator/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommandHandler.php)
- [config/chains.yaml](../../config/chains.yaml) — секция `roles`, поле `command`

## 9. Comments (Комментарии)
Выявлено при ревью скилла brainstorm (Архитектор Локи): `--runner` и `--model` — это атрибуты роли, а не параметры запуска. Их наличие на уровне CLI создаёт архитектурное противоречие и путает пользователя (какой runner/model используется — из CLI или из роли?).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-23 | Архитектор (Локи) | Создание задачи |
