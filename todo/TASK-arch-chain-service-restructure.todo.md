---
type: refactor
created: 2026-04-17
value: V2
complexity: C2
priority: P2
depends_on:
epic: EPIC-arch-orchestrator-module-decomposition
author: Архитектор (Гэндальф)
assignee:
branch:
pr:
status: todo
---

# TASK-arch-chain-service-restructure: Реорганизация Domain/Service/Chain/ на субдиректории

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда я открываю `Domain/Service/Chain/` и вижу 26 файлов, я хочу видеть логические группы (Static, Dynamic, Session, Audit, Shared), чтобы быстрее находить нужный файл и понимать зону ответственности.

### Goal (Цель по SMART)
Реорганизовать `src/Common/Module/Orchestrator/Domain/Service/Chain/` на 5 субдиректорий. Namespace меняется, поведение кода — нет. Все тесты проходят.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Common/Module/Orchestrator/Domain/Service/Chain/`
*   **Текущее состояние:** 26 файлов в одной директории, смешаны static/dynamic/session/audit/shared
*   **Границы (Out of Scope):**
    *   Infrastructure/Service/Chain/ — не трогаем (останется плоской)
    *   Application/Service/Chain/ — не трогаем
    *   Logic/behaviour — не меняется

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Файлы перемещены в субдиректории:
  - **Static/** — `RunStaticChainService`, `ExecuteStaticStepService`, `CheckStaticBudgetService`, `CheckStaticBudgetServiceInterface`
  - **Dynamic/** — `RunDynamicLoopService`, `RunDynamicLoopServiceInterface`, `ExecuteDynamicTurnService`, `BuildDynamicContextService`, `BuildDynamicContextServiceInterface`, `RecordDynamicRoundService`, `RecordDynamicRoundServiceInterface`, `FormatDynamicJournalService`, `FormatDynamicJournalServiceInterface`, `RunDynamicLoopAgentServiceInterface`
  - **Session/** — `ChainSessionLoggerInterface`, `ChainSessionReaderInterface`, `ChainSessionWriterInterface`
  - **Audit/** — `AuditLoggerInterface`, `AuditLoggerFactoryInterface`
  - **Shared/** — `ChainLoaderInterface`, `ResolveChainRunnerServiceInterface`, `PromptFormatterInterface`, `QualityGateRunnerInterface`, `FacilitatorResponseParserInterface`, `RoundCompletedNotifierInterface`
- [ ] Namespace обновлён во всех перемещённых файлах
- [ ] Все `use`-statements обновлены во всём проекте (src/, tests/)
- [ ] PHPUnit green
- [ ] Psalm green

### 🟡 Should Have (Желательно)
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- Выделение отдельных модулей (это Фаза 2)
- Переименование классов

## 4. Implementation Plan (План реализации)
1. [ ] Создать субдиректории: `Static/`, `Dynamic/`, `Session/`, `Audit/`, `Shared/`
2. [ ] Переместить файлы по группам, обновить `namespace` в каждом файле
3. [ ] Обновить `use`-statements в `src/Common/Module/Orchestrator/` (Domain, Application, Infrastructure)
4. [ ] Обновить `use`-statements в `tests/`
5. [ ] Обновить `docs/guide/architecture.md` — отразить новую структуру
6. [ ] Запустить PHPUnit + Psalm

## 5. Definition of Done (Критерии приёмки)
- [ ] В `Domain/Service/Chain/` нет PHP-файлов (только субдиректории)
- [ ] PHPUnit green
- [ ] Psalm green
- [ ] `docs/guide/architecture.md` отражает новую структуру

## 6. Verification (Самопроверка)
```bash
find src/Common/Module/Orchestrator/Domain/Service/Chain/ -maxdepth 1 -name "*.php" | wc -l  # → 0
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- Чисто механический рефакторинг, риск минимален
- IDE может кэшировать старые namespace — `composer dump-autoload` обязателен

## 8. Sources (Источники)
- [docs/guide/architecture.md](docs/guide/architecture.md)

## 9. Comments (Комментарии)
Фаза 1 эпика EPIC-arch-orchestrator-module-decomposition. Не добавляет новый функционал, только улучшает навигацию.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-17 | Архитектор (Гэндальф) | Создание задачи |
