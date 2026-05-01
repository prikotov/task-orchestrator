---
type: refactor
created: 2026-04-30
value: V3
complexity: C3
priority: P2
depends_on: TASK-refactor-execution-strategy, TASK-refactor-chain-definition-split
epic: EPIC-refactor-orchestrator-p3
author: pi
assignee:
branch:
pr:
status: todo
---

# TASK-refactor-dynamic-loop-decomposition: Декомпозиция RunDynamicLoopService

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда RunDynamicLoopService содержит 786 LOC, 21 импорт и 11 методов, а roadmap планирует conditional branching (Sprint 8) как третью стратегию, я хочу декомпозировать God-объект на 4 сфокусированных сервиса, чтобы добавление новых стратегий не увеличивало когнитивную нагрузку нелинейно.

### Goal (Цель по SMART)
Расщепить RunDynamicLoopService на: (1) LoopOrchestrator (координация), (2) TurnExecutor (один шаг), (3) BudgetChecker (проверка бюджета), (4) Finalizer (завершение и DTO mapping). Покрытие unit-тестами ≥80% ДО декомпозиции.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/Orchestrator/Domain/Service/Chain/Dynamic/`
*   **Текущее поведение:** RunDynamicLoopService = 786 LOC, God-объект. Уже частично декомпозирован (4 извлечённых сервиса: BuildDynamicContextService 161 LOC, FormatDynamicJournalService 185 LOC, RecordDynamicRoundService 128 LOC, RunDynamicLoopAgentServiceInterface 78 LOC), но основной файл не сократился.
*   **Границы (Out of Scope):**
    *   Не меняем интерфейс RunDynamicLoopService для потребителей
    *   Не добавляем conditional branching
    *   Не меняем Infrastructure-слой

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Unit-тесты на основную логику цикла ≥80% покрытия (ДО декомпозиции)
- [ ] LoopOrchestrator — координация цикла (итерации, условия выхода)
- [ ] TurnExecutor — выполнение одного шага (agent call, prompt, response)
- [ ] BudgetChecker — проверка и контроль бюджета
- [ ] Finalizer — завершение сессии, форматирование результата, DTO mapping
- [ ] DynamicTurnResultVo → TurnContinueVo + TurnBreakVo (discriminated union split)
- [ ] Все существующие тесты проходят без изменений поведения

### 🟡 Should Have (Желательно)
- [ ] Каждый сервис ≤200 LOC
- [ ] Unit-тесты на каждый новый сервис изолированно

### ⚫ Won't Have (Не будем делать)
- [ ] Изменение публичного API (OrchestrateChainCommandHandler не трогаем)
- [ ] Conditional branching

## 5. Definition of Done (Критерии приёмки)
- [ ] RunDynamicLoopService ≤ 200 LOC (координатор)
- [ ] 4 новых сервиса вместо inline-логики
- [ ] DynamicTurnResultVo → TurnContinueVo + TurnBreakVo
- [ ] Unit-тесты ≥80% на цикл
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные
- [ ] Обновить Roadmap: статус AI#11 `📋` → `✅ Done`, добавить ссылку на задачу и PR

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск:** Декомпозиция может дать чистый прирост LOC (+15-20%) за счёт интерфейсов и DTO — это нормально, цель в снижении когнитивной нагрузки, а не LOC
- **Зависимость:** ExecutionStrategyInterface (Sprint 3) ✅, ChainDefinitionVo split (Sprint 4) ✅

## 8. Sources (Источники)
- [ ] [Roadmap AI#11](../../docs/releases/ROADMAP-2026-Q2-Q3.md)
- [ ] [Протокол brainstorm #2](../var/sessions/brainstorm/2026-04-30_16-02-26/result.md) — аргументы Левши и Локи о декомпозиции
- [ ] [ADR-006: ExecutionStrategy](../../docs/adr/006-execution-strategy-composition.md)

## 9. Comments (Комментарии)
Roadmap Sprint 6. Предусловие для Sprint 7 (split Static). Brainstorm #2 выявил: декомпозиция God-объектов даёт +LOC, но снижает когнитивную нагрузку.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-30 | pi | Создание задачи |
