# Verify-ревью research-артефактов Orca ADE

**Роль:** Архитектор Локи  
**Дата:** 2026-07-10  
**Объект:** `docs/research/framework-comparisons/orca-ade-comparison.md`, `docs/research/agent-frameworks-summary.md`  
**Задача:** `todo/TASK-research-onorca-ade.todo.md`; проверка устранения 5 CR из `docs/agents/reports/system-architect/2026-07-10_20-20_orca-ade-research-review.md`

---

## Вердикт

Approval.

## Проверка CR

| CR | Статус | Подтверждение |
|---:|---|---|
| 1 | Fixed | `docs/research/framework-comparisons/orca-ade-comparison.md:104-105`, `:210`, `:222`, `:280`, `:324` — абсолютное «нет retry/CB» заменено на ограничение уровня runner/chain и явно указан experimental dispatch-level retry/circuit-break после 3 failures. Источник сверки: `skills/orchestration/SKILL.md:120`, `src/main/runtime/orchestration/db.ts:592-595`, `:712-725`. |
| 2 | Fixed | `docs/research/agent-frameworks-summary.md:50` — строка #30 синхронизирована; `:73`, `:399-406`, `:679` — нюанс повторён в summary/recommendations/change history. |
| 3 | Fixed | `docs/research/agent-frameworks-summary.md:533-554` — счётчик `18 из 30` воспроизводим, есть список из 18 проектов; Orca явно включён в числитель (`:533`, `:554`). |
| 4 | Fixed | `docs/research/agent-frameworks-summary.md:67-73` — Executive Summary содержит ровно пункты 1–3; Orca встроен в пункт 3, дублирующего пункта 2 нет. |
| 5 | Fixed | `docs/research/framework-comparisons/orca-ade-comparison.md:14-27` и `docs/research/agent-frameworks-summary.md:9-11` — добавлена терминологическая пометка/глоссарий для спорных англоязычных терминов. |

## Остаточные замечания

Новых критичных замечаний не выявлено.

## Источники сверки

- https://github.com/stablyai/orca/blob/main/skills/orchestration/SKILL.md
- https://github.com/stablyai/orca/blob/main/src/main/runtime/orchestration/db.ts
