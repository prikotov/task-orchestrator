# Roadmap Review: Sprint 9–10 — Архитектурный анализ

**Роль:** Архитектор Локи (system_architect_loki)
**Дата:** 2026-05-02
**Объект:** ROADMAP-2026-Q2-Q3.md, Sprint 9 (Error Handling + Typed I/O), Sprint 10 (Hooks + Sub-agents)
**Задача:** Оценка 6 задач двух следующих спринтов — реальная боль или overengineering

---

## Полный отчёт

Сохранён в: [`docs/research/analytical/loki-roadmap-review-2026-05.md`](../../../research/analytical/loki-roadmap-review-2026-05.md)

## Краткое резюме

### Вердикты по 6 задачам

| Задача | Вердикт | Pain |
|---|---|---|
| Error classification | 🟡 Делаем упрощённую (по exitCode, не по тексту) | 2/10 |
| Loop detection | 🔴 Overengineering — maxIterations уже ограничивает | 0/10 |
| Typed I/O (JSON Schema) | 🔴 Overengineering — строить не на чем, нет structured output | 1/10 |
| Hooks (pre/post) | 🟢 Делаем MVP: только post_step | 6/10 |
| Sub-agent ADR | 🟡 Отложить до Q4 — speculative | 1/10 |
| Model failover | 🟢 Делать приоритетно: CB open → trigger fallback | 7/10 |

### Упущенные задачи (добавить в план)

1. **MetricsCollectorInterface** — observability gap, 1 день
2. **ChainDefinitionVo split завершение** — God-VO 483 LOC, 1.5 дня
3. **ADR: Dynamic split решение** — закрыть OQ-6, 2 часа

### Рекомендованный план

**Sprint 9 (Resilience + Observability):** Model failover → Error classification (упрощённая) → MetricsCollector → Dynamic split ADR (~3 дня)

**Sprint 10 (Hooks + Debt):** Hooks post_step MVP → ChainDefinitionVo split → Resume ADR (~3.5 дня)

**Не делать:** Loop detection, Typed I/O, Sub-agent ADR (отложить до Q4)

---

*Покер — это не карты. Это умение вовремя сбросить. Loop detection и Typed I/O — блеф, который не стоит вскрывать за такую цену.*
