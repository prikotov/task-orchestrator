## Постановка

Исследовать **Orca ADE** (`stablyai/orca`, https://www.onorca.dev/) как **систему ручной оркестрации** и включить в эпик research.

- Задача: `todo/done/TASK-research-onorca-ade.todo.md`
- Эпик: `todo/done/EPIC-research-agent-frameworks-comparison.md` (reopen, стадия `1i`)

## Контекст

Orca (onorca.dev) — ADE (Agent Development Environment, среда разработки для агентов): параллельная **ручная** оркестрация coding-агентов (Codex, Claude Code, OpenCode, Pi и 30+ других) в изолированных `git worktree` (fan-out одного промпта → N агентов → compare → merge winner), BYO subscription (свои подписки на модели), Ghostty-class terminal IDE, mobile companion, Orca Tasks/Automations, `orca.yaml` workspace config. Классифицирован как **система оркестрации** (не coding-агент) → эпик `agent-frameworks` по прецеденту `oh-my-openagent` (#23). Ближайшие аналоги: SwarmForge (#29), AgentCraft (#16), Sandcastle (#20).

## Изменения

- **`docs/research/framework-comparisons/orca-ade-comparison.md`** (новый, 332 строки) — comparison-отчёт: обзор проекта, 7 ключевых механизмов (parallel worktree fan-out, BYO agent/subscription, `orca.yaml`, terminal/mobile model, Orca Tasks/Automations, meta-agent layer `AGENTS.md`/`skills/`, experimental structured orchestration), comparison table, сравнение с task-orchestrator (+ Mermaid-диаграмма), аналоги, 7 concrete patterns (P2/P3), вердикт, указатель источников.
- **`docs/research/agent-frameworks-summary.md`** — строка `Orca ADE` (#30), счётчик `30 / 30`, glossary англоязычных терминов, нормализованный Executive Summary, воспроизводимый счётчик `18 / 30` (явный список учтённых проектов), 6 рекомендаций P2/P3, запись в Change History.
- **`todo/done/EPIC-research-agent-frameworks-comparison.md`** — reopen (статус `in_progress`, стадия `1i`, change history).
- **`todo/done/TASK-research-onorca-ade.todo.md`** — постановка + заполненный Result + Change History (статус `done`).
- Отчёты: `docs/agents/reports/system-analyst/2026-07-10_19-50_orca-ade-research.md` (Аналитик Шерлок), `docs/agents/reports/system-architect/2026-07-10_20-20_orca-ade-research-review.md` + `2026-07-10_20-36_orca-ade-research-verify-review.md` (Архитектор Локи).

## Вердикт исследования

🟡 **заимствовать отдельные паттерны**, 🔴 **не использовать как dependency (зависимость)**.

- **Паттерны для заимствования (P2):** parallel fan-out comparison кандидатов, structured `worker_done` / dispatch-id отчёты, BYO runner ergonomics.
- **Паттерны (P3):** worktree isolation для параллельных сабагентов, workspace setup/preflight recipe, live run monitoring/notifications, installable task-orchestrator skill.
- **Не dependency:** Electron/mobile ADE непереносим в PHP/Symfony CLI; автоматические retry с backoff, circuit breaker, quality gates, бюджетный контроль, `fix_iterations` — наши сильные стороны. У Orca лишь experimental dispatch-level retry/circuit-break после 3 failures (проверено по `skills/orchestration/SKILL.md` и `src/main/runtime/orchestration/db.ts`).

## Code review

Архитектор Локи (сабагент): **Changes Requested** (5 CR — фактология retry/CB, синхронизация summary, воспроизводимость счётчика, нормализация Executive Summary, англицизмы) → доработка исполнителем → повторное ревью: **Approval**. Все CR устранены и сверены с источниками.

## Проверки

- `make md-links` — ✅ All internal links valid
- `make validate-todo` — ✅ задача `TASK-research-onorca-ade` валидна (0 errors). ⚠️ `make validate-todo` падает на **чужой** ранее сломанной задаче `todo/TASK-fix-pi-static-chain-system-prompt.todo.md` (существующее состояние `main`, вне scope этого PR).
- `phpunit` / `psalm` / `make check` — пропущены по исключению: изменения строго ограничены документацией (docs-only research).

## Sources

- https://www.onorca.dev/ , https://www.onorca.dev/docs
- https://github.com/stablyai/orca (MIT, TypeScript/Electron, ≈15.3k★, YC-backed; snapshot `main`)
- https://github.com/stablyai/orca/blob/main/skills/orchestration/SKILL.md
- https://github.com/stablyai/orca/blob/main/src/main/runtime/orchestration/db.ts
