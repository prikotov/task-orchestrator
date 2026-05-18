# ADR-009: Dynamic остаётся в Orchestrator

**Роль:** Архитектор (Гэндальф)
**Дата:** 2026-05-02
**Объект:** Архитектурное решение о fate Dynamic-стратегии — физический split или оставание в Orchestrator
**Задача:** [TASK-docs-dynamic-split-adr.todo.md](../../../../todo/done/TASK-docs-dynamic-split-adr.todo.md)

---

## Решение

**Dynamic остаётся в Orchestrator. Физический split не планируется.**

ADR: [`docs/adr/009-dynamic-split-decision.md`](../../../docs/adr/009-dynamic-split-decision.md)

## Ключевые аргументы

1. **Dynamic — ядро домена Orchestrator.** Agent loops, budget, fix_iterations, quality gates, facilitator-парсинг — это суть оркестратора. Без него Orchestrator = routing layer.

2. **Integration-слой для 11+ bridge-интерфейсов (~500+ LOC) нарушает критерий успеха split.** Static split: 3 интерфейса, 167 LOC. Dynamic: 11+ интерфейсов, оценка 500+ LOC. Критерий «Integration-слой < 200 LOC, без God-interface» не выполняется.

3. **G6 пройден, но не обязателен к применению.** Integration-паттерн работает для стратегий с 3–5 bridge-интерфейсами. Dynamic (11+) — за пределами sweet spot.

4. **0 cross-imports в Domain** — архитектура уже чистая. Namespace `Service/Chain/Dynamic/` = де-факто bounded context.

## Фактические данные

| Метрика | Static (split) | Dynamic (остаётся) |
|---------|----------------|-------------------|
| Domain-интерфейсов | 7 | **11** |
| Domain LOC | ~860 | **~2385** |
| Integration bridge LOC | 167 | оценка: **500+** |
| Cross-imports | 0 | 0 |

## Сделанное

- ✅ ADR-009 создан: `docs/adr/009-dynamic-split-decision.md`
- ✅ OQ-6 в roadmap обновлён: статус → Resolved (ADR-009)
- ✅ Git commit: `631605f` на ветке `task/docs-dynamic-split-adr`

## Критерии пересмотра

C1: Domain LOC ≥ 7000 | C2: 4-я стратегия (parallel) | C3: Integration toolkit | C4: SubOrchestration split | C5: Командный consensus
