---
type: refactor
created: 2026-04-17
value: V2
complexity: C5
priority: P2
depends_on:
author: Архитектор (Гэндальф)
assignee: Тимлид
branch: task/arch-orchestrator-module-decomposition
pr:
status: in_progress
---

# EPIC-arch-orchestrator-module-decomposition: Декомпозиция модуля Orchestrator

## User Story (Job Story)

Когда я разрабатываю новый функционал в task-orchestrator, я хочу чтобы каждый модуль содержал одну зону ответственности и был < 60 файлов, чтобы я мог держать в голове всю структуру модуля и не тратить время на навигацию по 110 файлам.

## Goal (SMART)

**К концу эпика:** модуль `Orchestrator` (110 файлов) декомпозирован на 2 модуля: `AgentRunner` (~20 файлов) и `Orchestrator` (~90 файлов → ~70 после реорганизации), с полной изоляцией Domain-слоёв через порты и маппинг VO на границах. Shared-VO запрещён. Без циклических зависимостей.

## Requirements (MoSCoW)

### 🔴 Must Have
- Внутренняя реорганизация `Domain/Service/Chain/` на субдиректории (Static/, Dynamic/, Session/, Audit/, Shared/)
- Выделение модуля `AgentRunner` с собственными VO, Enum, Exception
- В Orchestrator: Port-интерфейсы (`AgentRunnerPortInterface`, `AgentRunnerRegistryPortInterface`) + свои VO (`ChainRunRequestVo`, `ChainRunResultVo`, `ChainTurnResultVo`, `ChainRetryPolicyVo`)
- В Orchestrator: Infrastructure/Adapter с маппингом VO между модулями
- Все тесты (31 файл) обновлены и проходят
- Psalm: 0 errors
- Документация `docs/guide/architecture.md` актуализирована

### 🟡 Should Have
- Конвенции обновлены (модульная структура)
- ADR с обоснованием декомпозиции

### 🟢 Could Have
- Benchmark: замер времени маппинга VO (< 1ms на вызов)

### ⚫ Won't Have
- Выделение Budget, Prompt, Audit, Session в отдельные модули
- Выделение Report в отдельный модуль
- Shared-модуль с общими VO

## Plan (План реализации)

### Фаза 1: Внутренняя реорганизация (Вариант А)
- [x] [TASK-arch-chain-service-restructure](done/TASK-arch-chain-service-restructure.todo.md)

### Фаза 2: Декомпозиция на 2 модуля (Вариант C-light)
- [x] [TASK-arch-agent-runner-module-extract](done/TASK-arch-agent-runner-module-extract.todo.md)
- [ ] [TASK-arch-orchestrator-ports-and-adapters](TASK-arch-orchestrator-ports-and-adapters.todo.md)
- [ ] [TASK-arch-decomposition-tests-and-docs](TASK-arch-decomposition-tests-and-docs.todo.md)

## Definition of Done (DoD)

- [ ] `AgentRunner` — standalone модуль, 0 входящих зависимостей
- [ ] `Orchestrator` Domain не импортирует ничего из `AgentRunner`
- [ ] Связь только через `Orchestrator/Infrastructure/Adapter/`
- [ ] Каждый модуль < 80 файлов
- [ ] PHPUnit + Psalm green
- [ ] Архитектурная документация актуальна

## Risks and Dependencies

- Массовый namespace-миграция затрагивает ~80% файлов — нужен поэтапный подход
- `ResolveChainRunnerService` — мост между модулями, должен остаться в Orchestrator/Infrastructure
- DI-конфигурация (`services.yaml`) требует обновления для двух модулей
- Composer autoload не меняется (оба модуля внутри `src/`)

## Change History

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-17 | Архитектор (Гэндальф) | Создание эпика |
