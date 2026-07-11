# Отчёт анализа Orca ADE

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-07-10
**Объект:** `stablyai/orca` (Orca ADE), сайт `onorca.dev`, сравнение с `task-orchestrator`
**Задача:** `todo/TASK-research-onorca-ade.todo.md`

---


## Терминологическая пометка

В отчёте англоязычные термины используются в следующих значениях: **fan-out** — веерная раздача задачи нескольким агентам; **BYO (bring your own)** — «принеси свой» агент/подписку; **control surface** — управляющая поверхность; **human-in-the-loop** — человек в контуре принятия решения; **workflow** — рабочий процесс; **workbench** — рабочее место/панель управления; **live monitoring** — наблюдение за живым запуском; **runner-/chain-level retry/backoff/CB** — повтор, задержка и circuit breaker (предохранитель отказов) на уровне запуска агента или цепочки; **dispatch-level retry/circuit-break** — повтор и размыкание на уровне dispatch-задачи Orca с порогом 3 failures.

## 1. Snapshot источников

- Репозиторий: [github.com/stablyai/orca](https://github.com/stablyai/orca)
- Сайт: [onorca.dev](https://www.onorca.dev/), docs: [onorca.dev/docs](https://www.onorca.dev/docs)
- Дата анализа: 2026-07-10
- Branch: `main`
- Snapshot commit: `0f0e32952e7c33adbc8d03325c8ea9d241df1bb8`
- GitHub metadata на момент анализа: MIT, TypeScript, 15,693 stars, 1,227 forks, 1,332 open issues, created 2026-03-17, pushed 2026-07-10T10:56:55Z.

## 2. Краткий вывод

Orca — не coding agent (кодинг-агент) и не LLM SDK (SDK для работы с LLM). Это **Agent Development Environment (ADE, среда разработки для агентов)**: desktop/mobile workbench для параллельного запуска внешних CLI-агентов в изолированных `git worktree`.

Основной workflow (рабочий процесс): **один prompt → несколько CLI-агентов в отдельных worktrees → compare results (сравнение результатов) → merge winner (слияние победителя)**.

Итоговый verdict (вердикт):

- 🟡 **заимствовать отдельные паттерны**;
- 🔴 **не использовать как dependency (зависимость)**.

Причина: Orca даёт сильный UX (пользовательский опыт) для ручной параллельной оркестрации, но не имеет нашего universal runner-/chain-level automated resilience stack (автоматического контура устойчивости на уровне runner/chain): retry with backoff, circuit breaker, quality gates, budget control, `fix_iterations`, JSONL audit trail. При этом в experimental `orca orchestration` есть более узкий dispatch-level retry/circuit-break после 3 failures.

## 3. Ключевые находки

### 3.1 Parallel worktree fan-out

Orca worktree-native: каждая task/workspace получает собственный on-disk `git worktree`, branch, files, agent terminals, editor/browser tabs and source-control state. Это делает безопасным параллельный запуск Codex, Claude Code, OpenCode, Pi and other CLI agents.

### 3.2 BYO agent/subscription

Orca запускает любые CLI-агенты и использует existing user subscriptions (существующие подписки пользователя). README перечисляет Claude Code, Codex, Grok, Cursor, GitHub Copilot, OpenCode, Pi, Hermes Agent, Droid, Kilo, Qwen Code, Rovo Dev и `+ any CLI agent`.

### 3.3 `orca.yaml`

В snapshot корневой `orca.yaml` минимален и содержит setup script:

```yaml
scripts:
  setup: |
    node config/scripts/run-internal-dev-setup.mjs
    pnpm install
```

Но Orca skills describe broader workspace recipe contract (`environmentRecipes`, lifecycle scripts, connection mode `orca serve` vs SSH, `orca vm recipe doctor`). Для `task-orchestrator` полезна идея project-owned setup/preflight recipe рядом с chain config.

### 3.4 Terminal/mobile model

Desktop: Electron + xterm.js + WebGL + split panes + Ghostty import + bounded/main-owned terminal state recovery.

Mobile: React Native/Expo companion pairs with desktop over WebSocket RPC; desktop remains source of truth. Mobile supports worktree status, terminal scrollback, quick replies, source-control review/commit, account switcher and notifications.

### 3.5 Tasks/Automations

Orca integrates GitHub/Linear/Jira work items. Scheduled automations use CLI (`orca automations create`) with presets/cron/RRULE, repo/workspace targets, reuse/fresh session, manual run and rerun failed launch.

### 3.6 Meta-agent layer

Orca ships `AGENTS.md`, `CLAUDE.md` (`@AGENTS.md`) and installable `skills/*/SKILL.md`: `orca-cli`, `orchestration`, `computer-use`, `orca-linear`, emulator skills and per-workspace env. Docs also mention MCP endpoints under Settings → Integrations → MCP.

## 4. Сравнение с task-orchestrator

| Критерий | Orca ADE | task-orchestrator | Вывод |
| --- | --- | --- | --- |
| Orchestration model | Manual parallel worktree fan-out + experimental tasks/dispatches/gates | YAML chains, chain execution, `DynamicLoop`, `run-subagent`/`task-via-subagents` | Orca сильнее в visible fan-out, мы сильнее в deterministic automation |
| State management | `git worktree`, branch/files/terminals/tasks/dispatches, mobile pairing | Chain context, JSONL audit trail, task files, orchestrator-owned Git workflow | Worktree isolation useful; desktop/mobile state not portable |
| Error handling | Manual recovery/rerun, prechecks, validation; нет universal runner-/chain-level retry/backoff/CB; есть experimental dispatch-level retry/circuit-break после 3 failures | retry/backoff, circuit breaker, fallback routing, quality gates, budget, `fix_iterations` | Наш resilience stack сильнее |
| Extensibility | Any CLI agent, BYO subscription, Skills, MCP, SSH/mobile/emulator/computer-use | Runner profiles, role files, skills, Symfony DI, DDD modules | Заимствовать operational skills and runner ergonomics |
| Applicability | Desktop/mobile ADE | PHP/Symfony CLI/library orchestrator | Комплементарно, не dependency |

## 5. Сопоставление с аналогами

- **SwarmForge (#29):** близок role/worktree coordination (координацией ролей в worktrees), но SwarmForge — tmux/daemon/file handoff; Orca — зрелый desktop/mobile product.
- **AgentCraft (#16):** близок GUI wrapper (графической оболочкой) над внешними агентами; Orca отличается open-source MIT and strong worktree/mobile surface.
- **Sandcastle (#20):** пересекается по git worktrees and parallel templates, но Sandcastle — TypeScript library for sandbox lifecycle, Orca — manual ADE.

## 6. Паттерны для возможного заимствования

1. **Parallel fan-out candidate comparison (P2):** один prompt → N runner profiles/worktrees → compare → merge decision.
2. **Structured `worker_done` + dispatch-id report (P2):** task id, dispatch/run id, status, files changed, checks, blockers, report path.
3. **BYO runner profile documentation (P2):** runner as external CLI + user's subscription/account and rate-limit assumptions.
4. **Worktree isolation for parallel subagents (P3):** especially for independent research/review/candidate implementations.
5. **Workspace setup/preflight recipe (P3):** project-owned setup/precheck metadata adjacent to `config/chains.yaml`.
6. **Live run observability/notifications (P3):** chain status, current step, waiting-on-input, completion/failure notifications.
7. **Installable `task-orchestrator` skill (P3):** reusable `SKILL.md` for external agents to operate task-orchestrator safely.

## 7. Артефакты

Созданы/обновлены:

- `docs/research/framework-comparisons/orca-ade-comparison.md`
- `docs/research/agent-frameworks-summary.md`
- `docs/agents/reports/system-analyst/2026-07-10_19-50_orca-ade-research.md`
- `todo/TASK-research-onorca-ade.todo.md` (result/change history)

## 8. Проверки

Плановые проверки по задаче:

- `make md-links` — успешно, все internal links (внутренние ссылки) валидны.
- `make validate-todo` — завершился с ошибкой на ранее существующей несвязанной задаче `todo/TASK-fix-pi-static-chain-system-prompt.todo.md` (`type: bug`, отсутствуют обязательные секции). Текущая задача `todo/TASK-research-onorca-ade.todo.md` валидируется отдельно успешно: `0 error(s), 0 warning(s)`.
- PHPUnit/Psalm не запускались: task is docs-only research (исследование только документации).


## 9. Доработка после review Архитектора Локи (2026-07-10 20:20)

- Перепроверены `skills/orchestration/SKILL.md` и `src/main/runtime/orchestration/db.ts` репозитория `stablyai/orca`: подтверждено, что Orca имеет experimental dispatch-level retry/circuit-break (`failure_count`, `circuit_broken`, порог 3 failures), но не имеет universal runner-/chain-level retry/backoff/CB, сопоставимого с `CircuitBreakerAgentRunnerService`.
- Уточнены research-артефакты: `docs/research/framework-comparisons/orca-ade-comparison.md` и `docs/research/agent-frameworks-summary.md`.
- Нормализованы Executive Summary и счётчик Sub-agents / Multi-agent: Orca явно входит в числитель, список учтённых 18 проектов перечислен воспроизводимо.
- Добавлены терминологические пометки для англоязычных терминов.
- Проверки после доработки: `make md-links` — успешно; `make validate-todo` — целевая задача `todo/TASK-research-onorca-ade.todo.md` валидируется с `0 error(s), 0 warning(s)`, в общем выводе остаётся прежняя несвязанная ошибка `todo/TASK-fix-pi-static-chain-system-prompt.todo.md`.
