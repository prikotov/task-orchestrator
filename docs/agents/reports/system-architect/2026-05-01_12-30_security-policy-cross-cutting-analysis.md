# Анализ Security Policy как Cross-Cutting Concern

**Роль:** Архитектор Гэндальф  
**Дата:** 2026-05-01  
**Объект:** `src/Module/Orchestrator/` — Static/Dynamic стратегии, ExecutionStrategy, RunAgentService, QualityGateRunner  
**Задача:** [TASK-docs-security-policy-analysis](../../../todo/TASK-docs-security-policy-analysis.todo.md)

---

## Результат

Создан документ: [docs/releases/security-policy-cross-cutting-analysis.md](../../../docs/releases/security-policy-cross-cutting-analysis.md)

## Ключевые выводы

### OQ-3 (Roadmap): Разделение Static/Dynamic НЕ создаёт проблемы для Security Policy

1. **Permission model — единая.** Exec policy + permission checks через одни интерфейсы. Различие — в точках применения (per-step для Static, per-role для Dynamic), но не в модели.

2. **Shared Kernel не разрастается.** Interfaces (ports) размещаются в `Orchestrator/Domain/Service/Security/`, а не в Shared Kernel.

3. **Decorator pattern** — естественное решение, консистентное с retry/circuit breaker.

### Триггер G4: НЕ срабатывает

Static и Dynamic имеют **одну и ту же** permission model с разной granularity. G4 (разные permission models) не подтверждён.

### Рекомендация для Sprint 9

- **Interfaces (ports):** `ChainSecurityPolicyInterface` + `ExecPolicyInterface` в Orchestrator Domain
- **Реализация:** в SecurityPolicy module Infrastructure
- **Decorators:** для `RunAgentServiceInterface` и `ExecutionStrategyInterface`
- **Начать с:** rule-based exec policy + permission system (quick win, подтверждён Codex и Claude Code)

## Выявленные точки входа (6 точек)

1. CommandHandler — chain-level authorization
2. ExecutionStrategy::execute() — strategy-level checks
3. Step execution (Static) — per-step runner/tool/model
4. Agent run (оба path) — exec policy для runner
5. Dynamic turn — per-role runtime routing
6. Quality gate (Static) — shell command exec policy

## Roadmap обновлён

AI#14 статус: 📋 → ✅ Done, добавлена ссылка на документ анализа.

## Источники

- [Roadmap 2026 Q2–Q3](../../../docs/releases/ROADMAP-2026-Q2-Q3.md)
- [Протокол brainstorm #2](../../../var/sessions/brainstorm/2026-04-30_16-02-26/result.md)
- [Исследование фреймворков](../../../docs/research/agent-frameworks-summary.md) — Кластер 2: Безопасность
- [ADR-006](../../../docs/adr/006-execution-strategy-composition.md), [ADR-008](../../../docs/adr/008-shared-kernel-contract.md)
