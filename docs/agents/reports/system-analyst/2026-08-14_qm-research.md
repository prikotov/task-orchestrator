# Research `yc-software/qm` — финализация metadata

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-08-14
**Объект:** `yc-software/qm` (TypeScript/Node, MIT, multiplayer/multi-tenant agent-платформа-оркестратор над внешними харнесами)
**Задача:** [TASK-research-qm](../../../../todo/done/TASK-research-qm.todo.md) (stage `1k`, `EPIC-research-agent-frameworks-comparison`)

---

## Snapshot и версия

- **Объект:** [github.com/yc-software/qm](https://github.com/yc-software/qm), npm [`@yc-software/qm`](https://www.npmjs.com/package/@yc-software/qm).
- **Snapshot:** ветка `main`, commit `9ff90fc770d60658ae6c350b691204b5a5b3e394` (`pushed_at` 2026-08-14T03:28:43Z).
- **npm `@yc-software/qm`:** `0.1.4` (published 2026-07-31) — deployment-CLI (control-plane), не встраиваемый runtime; 0 runtime-зависимостей.
- **Дата анализа:** 2026-08-14.

## Verdict (вердикт)

- 🟡 **Заимствовать паттерны** — multi-harness abstraction (Pi/OpenCode/Codex/Claude Code за одним интерфейсом), shared skills governance (scope-owned + grant + admin-gated org-promotion + импорт из git), security postures Strict/Auto/Dangerous + predeclared command policy, deployment-directory контракт (generic core + org-слой, interface-backed субстраты, явный clause-status), per-scope durable sandbox.
- 🔴 **Не dependency** — multi-tenant TS/Node SaaS-платформа (Slack/web/admin), не PHP/Symfony, не single-tenant chain-оркестрация; npm-пакет — deployment-CLI, а не runtime.

> Полное обоснование и comparison table — в [comparison-отчёте](../../../../docs/research/framework-comparisons/qm-comparison.md).

## Изменённые артефакты (metadata-финализация)

В рамках этого шага изменены только metadata-артефакты (код не затронут):

- `todo/done/TASK-research-qm.todo.md` — `status: in_progress` → `status: review`; запись в Change History о готовности к review.
- `todo/done/EPIC-research-agent-frameworks-comparison.md` — строка TASK-research-qm (stage `1k`) помечена `Статус: review`; запись в Change History о подготовке stage 1k.
- `docs/agents/reports/system-analyst/2026-08-14_qm-research.md` — данный отчёт.

## Связанные research-артефакты

- `docs/research/framework-comparisons/qm-comparison.md` — comparison-отчёт (источник findings и verdict).
- `docs/research/agent-frameworks-summary.md` — целевая строка `qm` (#32) согласно плану задачи (DoD).

## Что НЕ сделано в этом шаге

- Commit/push/PR — не выполнялись (по запросу пользователя).
- Полный body comparison-отчёта и summary не перечитывались — шаг ограничен metadata-финализацией.
