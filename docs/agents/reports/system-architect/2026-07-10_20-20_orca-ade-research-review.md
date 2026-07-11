# Ревью research-артефактов Orca ADE

**Роль:** Архитектор Локи
**Дата:** 2026-07-10
**Объект:** `docs/research/framework-comparisons/orca-ade-comparison.md`, `docs/research/agent-frameworks-summary.md`, `docs/agents/reports/system-analyst/2026-07-10_19-50_orca-ade-research.md`
**Задача:** `todo/TASK-research-onorca-ade.todo.md`

---

## Вердикт

Changes Requested.

## Change Requests

1. `docs/research/framework-comparisons/orca-ade-comparison.md`: уточнить описание experimental orchestration в Orca. В официальном `skills/orchestration/SKILL.md` и `src/main/runtime/orchestration/db.ts` есть dispatch retry до 3 failures и статус `circuit_broken`; в отчёте это местами названо как отсутствие circuit breaker/retry. Нужно заменить абсолютное «нет retry/CB» на «нет универсального runner-/chain-level retry/backoff и circuit breaker, сопоставимого с task-orchestrator; есть experimental dispatch-level retry/circuit-break в `orca orchestration`».
2. `docs/research/agent-frameworks-summary.md`: строка #30 и Change History повторяют ту же проблему: `нет universal retry/CB/gates/budget` выглядит как абсолютный claim. Нужно синхронизировать с уточнением из comparison-отчёта.
3. `docs/research/agent-frameworks-summary.md`: trend `Sub-agents / Multi-agent` указывает 18/30, но перечисление counted проектов не делает число воспроизводимым: Orca добавлен в пояснение, не в основной список. Нужно явно указать, входит ли Orca в numerator, и перечислить все 18 counted проектов.
4. `docs/research/agent-frameworks-summary.md`: Executive Summary формально говорит про «три главных вывода», но содержит дублирующий пункт 2 и отдельный абзац про SwarmForge/Orca без нумерации. Нужно нормализовать список, чтобы Orca-блок не висел внутри пункта 3.
5. Все три артефакта: стиль не полностью соответствует AGENTS.md по пояснению англоязычных терминов. Нужно либо добавить краткий glossary (глоссарий), либо на первом употреблении пояснить `manual parallel-agent harness`, `fan-out`, `BYO`, `control surface`, `human-in-the-loop`, `workflow`, `workbench`, `live monitoring`.

## Проверенные источники

- https://api.github.com/repos/stablyai/orca
- https://github.com/stablyai/orca/blob/main/README.md
- https://github.com/stablyai/orca/blob/main/skills/orchestration/SKILL.md
- https://github.com/stablyai/orca/blob/main/src/main/runtime/orchestration/db.ts
- https://www.onorca.dev/docs/mobile
