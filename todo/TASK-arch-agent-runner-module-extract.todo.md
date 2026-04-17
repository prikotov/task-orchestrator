---
type: refactor
created: 2026-04-17
value: V2
complexity: C3
priority: P2
depends_on:
  - TASK-arch-chain-service-restructure
epic: EPIC-arch-orchestrator-module-decomposition
author: Архитектор (Гэндальф)
assignee:
branch:
pr:
status: todo
---

# TASK-arch-agent-runner-module-extract: Выделение модуля AgentRunner

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда я добавляю новый движок AI-агента (например, Codex CLI), я хочу работать в отдельном модуле `AgentRunner` с 20 файлами, а не искать нужные файлы среди 70 файлов Orchestrator.

### Goal (Цель по SMART)
Создать модуль `src/Common/Module/AgentRunner/` с Domain- и Infrastructure-слоями. Перенести контракты запуска агентов, retry-, circuit-breaker-обёртки, PI-runner. Orchestrator пока **напрямую** зависит от нового модуля (адаптеры — следующая задача).

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Common/Module/AgentRunner/` — новый модуль
*   **Что переносим (Domain):**

| Файл | Текущий путь | Новый путь |
|---|---|---|
| `AgentRunnerInterface` | `Orchestrator/Domain/Service/AgentRunner/` | `AgentRunner/Domain/Service/` |
| `AgentRunnerRegistryServiceInterface` | `Orchestrator/Domain/Service/AgentRunner/` | `AgentRunner/Domain/Service/` |
| `AgentRunnerRegistryService` | `Orchestrator/Domain/Service/AgentRunner/` | `AgentRunner/Domain/Service/` |
| `RetryableRunnerFactoryInterface` | `Orchestrator/Domain/Service/AgentRunner/` | `AgentRunner/Domain/Service/` |
| `AgentResultVo` | `Orchestrator/Domain/ValueObject/` | `AgentRunner/Domain/ValueObject/` |
| `AgentRunRequestVo` | `Orchestrator/Domain/ValueObject/` | `AgentRunner/Domain/ValueObject/` |
| `AgentTurnResultVo` | `Orchestrator/Domain/ValueObject/` | `AgentRunner/Domain/ValueObject/` |
| `RetryPolicyVo` | `Orchestrator/Domain/ValueObject/` | `AgentRunner/Domain/ValueObject/` |
| `CircuitBreakerStateVo` | `Orchestrator/Domain/ValueObject/` | `AgentRunner/Domain/ValueObject/` |
| `CircuitStateEnum` | `Orchestrator/Domain/Enum/` | `AgentRunner/Domain/Enum/` |
| `RunnerNotFoundException` | `Orchestrator/Domain/Exception/` | `AgentRunner/Domain/Exception/` |
| `AgentException` | `Orchestrator/Domain/Exception/` | `AgentRunner/Domain/Exception/` |

*   **Что переносим (Infrastructure):**

| Файл | Текущий путь | Новый путь |
|---|---|---|
| `PiAgentRunner` | `Orchestrator/Infrastructure/Service/AgentRunner/Pi/` | `AgentRunner/Infrastructure/Service/Pi/` |
| `PiJsonlParser` | `Orchestrator/Infrastructure/Service/AgentRunner/Pi/` | `AgentRunner/Infrastructure/Service/Pi/` |
| `RetryingAgentRunner` | `Orchestrator/Infrastructure/Service/AgentRunner/` | `AgentRunner/Infrastructure/Service/` |
| `CircuitBreakerAgentRunner` | `Orchestrator/Infrastructure/Service/AgentRunner/` | `AgentRunner/Infrastructure/Service/` |
| `RetryableRunnerFactory` | `Orchestrator/Infrastructure/Service/AgentRunner/` | `AgentRunner/Infrastructure/Service/` |

*   **Границы (Out of Scope):**
    *   Orchestrator → AgentRunner адаптеры (задача TASK-arch-orchestrator-ports-and-adapters)
    *   DI-конфигурация — только базовое подключение нового модуля
    *   Report, Budget, Prompt — не трогаем

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Модуль `src/Common/Module/AgentRunner/` создан с Domain/ и Infrastructure/ слоями
- [ ] Все перечисленные файлы перенесены, namespace обновлён
- [ ] Все `use`-statements в `Orchestrator/` обновлены на новые namespace
- [ ] Все `use`-statements в `tests/` обновлены
- [ ] `config/services.yaml` обновлён: добавлен resource для AgentRunner
- [ ] PHPUnit green (все 31 тест)
- [ ] Psalm green

### 🟡 Should Have (Желательно)
- [ ] `RolePromptBuilder` (`Infrastructure/Service/Prompt/`) остаётся в Orchestrator (использует `PromptProviderInterface` — Orchestrator-концепт)

### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- Port/Adapter между модулями (следующая задача)
- Разделение DI-конфигурации на отдельные files per module

## 4. Implementation Plan (План реализации)
1. [ ] Создать структуру каталогов `src/Common/Module/AgentRunner/{Domain,Infrastructure}/`
2. [ ] Перенести Domain-файлы (интерфейсы, VO, Enum, Exception), обновить namespace → `TaskOrchestrator\Common\Module\AgentRunner\*`
3. [ ] Перенести Infrastructure-файлы, обновить namespace → `TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\*`
4. [ ] Обновить все `use`-statements в `src/Common/Module/Orchestrator/` (Domain, Application, Infrastructure)
5. [ ] Обновить все `use`-statements в `tests/`
6. [ ] Обновить `config/services.yaml` — подключить AgentRunner module resource
7. [ ] `composer dump-autoload`
8. [ ] PHPUnit + Psalm
9. [ ] Обновить `docs/guide/architecture.md`

## 5. Definition of Done (Критерии приёмки)
- [ ] `src/Common/Module/AgentRunner/` содержит Domain/ и Infrastructure/
- [ ] `src/Common/Module/Orchestrator/Domain/Service/AgentRunner/` — пуст или удалён
- [ ] VO `AgentResultVo`, `AgentRunRequestVo` и др. определены в `AgentRunner/Domain/ValueObject/`
- [ ] PHPUnit green
- [ ] Psalm green

## 6. Verification (Самопроверка)
```bash
find src/Common/Module/AgentRunner -type f -name "*.php" | wc -l   # ~20
find src/Common/Module/Orchestrator/Domain/Service/AgentRunner -type f | wc -l  # 0
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- Зависит от TASK-arch-chain-service-restructure (Фаза 1)
- Массовый namespace-миграция ~80 файлов — высокая трудоёмкость, но низкий технический риск
- `ResolveChainRunnerService` (Orchestrator) импортирует `AgentRunnerInterface` + `RetryPolicyVo` — после этой задачи зависит от нового namespace, но ещё без портов

## 8. Sources (Источники)
- [docs/guide/architecture.md](docs/guide/architecture.md)
- [docs/conventions/modules/index.md](docs/conventions/modules/index.md)

## 9. Comments (Комментарии)
На этом этапе Orchestrator **напрямую** depends on AgentRunner (через `use`). Это допустимо как промежуточное состояние. Изоляция через порты — следующая задача.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-17 | Архитектор (Гэндальф) | Создание задачи |
