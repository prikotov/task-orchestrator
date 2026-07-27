# Исследование bx-dev-skill для EPIC-research-agent-frameworks-comparison

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-07-26
**Объект:** `bish-x/bx-dev-skill` (`main` `dd7fa7a2f65e487e49847394bff6cd5986b5877e`), артефакты `docs/research/framework-comparisons/bx-dev-skill-comparison.md`, `docs/research/agent-frameworks-summary.md`, `todo/TASK-research-bx-dev-skill.todo.md`, `todo/done/EPIC-research-agent-frameworks-comparison.md`
**Задача:** `todo/TASK-research-bx-dev-skill.todo.md` — stage `1j`, research #31 для `EPIC-research-agent-frameworks-comparison`

---

## Краткий вывод

`bx-dev` — самодостаточный Codex-skill `$bx-dev` для ad-hoc development sessions (разовых сессий разработки) на session branch (сессионной ветке). Это **не coding agent** и **не LLM runtime**: skill управляет внешним Codex runtime и single-shot Codex subagents (одноразовыми субагентами) в workflow `Dev → review → optional QA → Merger → PR/merge → cleanup`.

**Итоговый verdict:** 🟡 заимствовать отдельные паттерны, 🔴 не использовать как dependency.

## Проверенный snapshot

- Repository: https://github.com/bish-x/bx-dev-skill
- Default branch: `main`
- Commit: `dd7fa7a2f65e487e49847394bff6cd5986b5877e`
- Commit date: 2026-06-05T13:06:40Z
- Message: `docs: add Russian README`
- GitHub metadata на дату анализа: 17★, 5 forks, 1 open issue, language `Python`, license `null`, topics `ai-agents`, `automation`, `codex`, `codex-skill`, `developer-tools`.

## Прочитанные первичные источники

- `README.md`, `README.ru.md`
- `skills/bx-dev/SKILL.md`
- `skills/bx-dev/docs/CODEX-ORCHESTRATION.md`
- `skills/bx-dev/docs/MERGE-PROTOCOL.md`
- `skills/bx-dev/templates/agents/bx-dev-merger.md`
- `skills/bx-dev/skill-library/INDEX.md`
- `skills/bx-dev/skill-library/MANIFEST.md`
- `.gitattributes`, `.gitignore`
- GitHub repo metadata and recursive tree API

## Ключевые findings (находки)

1. **Session-state**: `.bx-dev/<session-id>/state.json`, `brief.md`, `context.md` фиксируют `branch`, `mode`, `flags`, `codex_agents`, `waiting_for`, `task_count`, `push_count`, `completed_tasks`. Это полезно как live workflow recovery layer рядом с нашим JSONL audit.
2. **Single-shot lifecycle**: Codex subagent всегда проходит spawn → persist `agent_id` → wait → persist report → `close_agent` → mark closed. Это хороший report/cleanup pattern для `run-subagent`.
3. **Strict flags**: `--solo`, `--careful`, `--no-review`, `--plan-approve`, `--no-sop`, deprecated no-op `--sop`; unknown flags fail-fast, resolved flags echoed to user.
4. **Scout-plan gate**: при `--plan-approve` Dev сначала возвращает scout-only plan; реализация начинается только после user approval.
5. **Review/QA bounded loops**: review and QA fix cycles capped at 2 rounds; non-convergence escalates to user.
6. **MERGE-PROTOCOL**: отдельный артефакт с conflict taxonomy, semantic checks, smoke tests, rollback and Merger JSON status taxonomy. Ценно как governance pattern, но auto-merge нельзя переносить буквально из-за наших PR rules.
7. **Skill-library governance**: 105 support skills / 9 categories, `INDEX.md` category router and `MANIFEST.md` flat inventory.

## Сравнение с task-orchestrator

`bx-dev` похож на наши `task-via-subagents`/`epic-via-subagents`, но слабее как runtime: нет chain-level retry/backoff, circuit breaker, budget control, fallback routing, deterministic quality gates as reusable primitive or JSONL audit parity. Сильная сторона `bx-dev` — UX and governance of a single dev session.

## Созданные/обновлённые артефакты

- `todo/TASK-research-bx-dev-skill.todo.md` — постановка задачи по формату Orca reference.
- `docs/research/framework-comparisons/bx-dev-skill-comparison.md` — comparison report with snapshot, standard comparison table, mechanisms, task-orchestrator comparison, analogues and verdict.
- `docs/research/agent-frameworks-summary.md` — добавлена строка #31, статус `31 / 31`, пересчитаны тренды: agent-loop `19 / 31`, SKILL.md `22 / 31`, MCP `18 / 31`, sub-agents/multi-agent `19 / 31`, compression `12 / 31`.
- `todo/done/EPIC-research-agent-frameworks-comparison.md` — reopened `2026-07-26`, добавлена стадия `1j` and Change History entry.

## Ограничения анализа

- `$bx-dev` не запускался локально; анализ docs-only по первичным источникам.
- 105 support skills не читались построчно; использованы `INDEX.md`, `MANIFEST.md` and repository tree. Поэтому выводы о skill-library — про taxonomy/governance, не про качество каждого skill.
- Runtime semantics Codex subagent tools считаются documented contract, не проверенным execution trace.

## Итоговые рекомендации

- P2: strict workflow flags for subagent workflows.
- P2: scout-plan approval gate for risky implementation tasks.
- P2: session resume metadata beside JSONL audit.
- P2: structured subagent report + explicit close/cleanup contract.
- P2: optional QA as flag (`--careful` analogue).
- P3: standalone merge/conflict protocol adapted to project rule: no merge without explicit user confirmation.
- P3: skill-library category router + manifest for publishable skills.
