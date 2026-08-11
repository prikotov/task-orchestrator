---
# Metadata (Метаданные)
type: epic
created: 2026-04-21
value: V3
complexity: C4
priority: P2
author: Тимлид (Алекс)
assignee:
branch: task/research-agent-frameworks-comparison
status: in_progress
reopened: 2026-08-11
pr: "#51 (исследование), #52 (ревью и исправления), #97 (Paperclip AI + AgentCraft, финализация)"
---

# EPIC-research-agent-frameworks-comparison: Исследование AI-agent фреймворков и оркестраторов

## 1. Concept and Goal (Концепция и цель)
### Story (Job Story)
Когда мы развиваем архитектуру task-orchestrator, я хочу провести систематическое исследование AI-agent фреймворков и оркестраторов, чтобы понять лучшие паттерны оркестрации, обработки ошибок, state management — и определить, что стоит заимствовать, а от чего отказаться.

### Goal (Цель по SMART)
Исследовать 10+ AI-agent фреймворков и инструментов, составить единый сравнительный отчёт со сводной таблицей (модель оркестрации, state management, error handling, extensibility, применимость). По каждому — вердикт: заимствовать паттерны / использовать как dependency / не подходит. Отчёт в `docs/research/` до конца Q2 2026.

## 2. Context and Scope (Контекст и границы)
*   **In Scope (Что делаем):**
    *   Исследование каждого фреймворка/инструмента по единой методологии
    *   Индивидуальные comparison-отчёты в `docs/research/`
    *   Сводная таблица с классификацией и рекомендациями
*   **Out of Scope (Чего НЕ делаем):**
    *   Написание кода интеграции — только исследование
    *   Глубокий code review исходников — анализ на уровне архитектуры и паттернов

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Блокирующие требования)
- [x] Каждый фреймворк исследован по единой методологии (модель оркестрации, state, error handling, extensibility)
- [x] По каждому фреймворку создан отчёт в `docs/research/` по формату существующих comparison-документов
- [x] Сводная таблица в `docs/research/agent-frameworks-summary.md` со всеми фреймворками
- [x] Чёткий вердикт по каждому: заимствовать / dependency / не подходит

### 🟡 Should Have (Важные требования)
- [x] Сравнительная таблица с группировкой по категориям (multi-agent, single-agent, cloud, meta-orchestration)
- [x] Рекомендации по приоритетам заимствования паттернов

### 🟢 Could Have (Желательно)
- [ ] Визуализация (Mermaid-диаграммы) ключевых архитектурных различий

### ⚫ Won't Have (Не в этот раз)
- [ ] Код интеграции любого из фреймворков
- [ ] Performance-бенчмарки

## 4. Solution Design (Техническое решение)

Исследование проводится в два этапа:

**Этап 1 — Индивидуальные research-задачи:** каждая задача изучает один фреймворк (или группу), пишет отдельный comparison-документ **и заполняет свою строку** в сводной таблице `docs/research/agent-frameworks-summary.md`. Задачи независимы, могут выполняться параллельно.

**Этап 2 — Финализация:** после завершения всех индивидуальных исследований финальная задача проверяет полноту таблицы, выявляет тренды и составляет итоговые рекомендации.

Все отчёты размещаются в `docs/research/` рядом с уже существующими:
- `agent-bernstein-comparison.md`
- `agent-orchestrator-comparison.md`
- `superpowers-brainstorming-comparison.md`

**Сводная таблица** `docs/research/agent-frameworks-summary.md` создаётся заранее (пустой шаблон) и заполняется инкрементально — каждая задача Этапа 1 добавляет свою строку при выполнении.

## 5. Implementation Plan (План реализации)

### Этап 1: Индивидуальные исследования (параллельные)

- [x] [TASK-research-charmbracelet-crush](TASK-research-charmbracelet-crush.todo.md) — Charmbracelet Crush (Go, CLI-agent)
- [x] [TASK-research-pi-agent-rust](TASK-research-pi-agent-rust.todo.md) — pi_agent_rust (Rust)
- [x] [TASK-research-crewai-langgraph-autogen](TASK-research-crewai-langgraph-autogen.todo.md) — CrewAI, LangGraph, AutoGen (Python multi-agent)
- [x] [TASK-research-openhands-sdk](TASK-research-openhands-sdk.todo.md) — OpenHands SDK (Python, SDK-подход)
- [x] [TASK-research-archon-ai-planner](TASK-research-archon-ai-planner.todo.md) — Archon (Python, мета-оркестрация)
- [x] [TASK-research-metagpt-openclaw](TASK-research-metagpt-openclaw.todo.md) — MetaGPT, OpenClaw (Python, SOP/роли)
- [x] [TASK-research-mastra-ai](TASK-research-mastra-ai.todo.md) — Mastra AI (TypeScript, workflows)
- [x] [TASK-research-claude-code](TASK-research-claude-code.todo.md) — Claude Code (проприетарный, agent loop)
- [x] [TASK-research-copilot-agent-hq](TASK-research-copilot-agent-hq.todo.md) — GitHub Copilot Agent HQ (проприетарный, cloud)
- [x] [TASK-research-docker-agent-codex](TASK-research-docker-agent-codex.todo.md) — Docker Agent, OpenAI Codex (проприетарный, sandboxing)
- [x] [TASK-research-agno](TASK-research-agno.todo.md) — Agno / бывший Phi (Python, multi-agent teams)

### Этап 1b: Дополнительные исследования (2026-04-28)

- [x] [TASK-research-paperclip-ai](TASK-research-paperclip-ai.todo.md) — Paperclip AI
- [x] [TASK-research-agentcraft](TASK-research-agentcraft.todo.md) — AgentCraft

### Этап 1c: Дополнительные исследования (2026-05-04)

- [ ] [TASK-research-sandcastle](TASK-research-sandcastle.todo.md) — Sandcastle (Matt Pocock)
- [ ] [TASK-research-hermes-agent](TASK-research-hermes-agent.todo.md) — Hermes Agent (Nous Research)
- [x] [TASK-research-oh-my-openagent](../done/TASK-research-oh-my-openagent.todo.md) — Oh My OpenAgent (форк OpenCode, TypeScript + паттерны оркестрации)

### Этап 1d: Дополнительные исследования (2026-05-13)

- [ ] [TASK-research-duet](TASK-research-duet.todo.md) — Duet (Aomni, cloud/SaaS, team AI-агент)
- [ ] [TASK-research-multica](TASK-research-multica.todo.md) — Multica (open-source, project management для human + agent teams)

### Этап 1e: Дополнительные исследования (2026-05-20)

- [x] [TASK-research-zeroclaw](TASK-research-zeroclaw.todo.md) — Zeroclaw (zeroclaw-labs, AI-agent orchestration)

### Этап 1f: Дополнительные исследования (2026-06-12)

- [x] [TASK-research-odysseus-ai-workspace](TASK-research-odysseus-ai-workspace.todo.md) — Odysseus (PewDiePie archdaemon, self-hosted AI workspace)

### Этап 1g: Дополнительные исследования (2026-06-13)

- [x] [TASK-research-agent-skills](TASK-research-agent-skills.todo.md) — Agent Skills (Addy Osmani, production-grade engineering skills for AI coding agents) *(PR #258)*

### Этап 1h: Дополнительные исследования (2026-06-18)

- [x] [TASK-research-swarm-forge](TASK-research-swarm-forge.todo.md) — SwarmForge (unclebob / Robert C. Martin, tmux-based swarm orchestration: git worktrees per role, layered constitution, handoff-протокол, config-driven topology) *(PR #272)*

### Этап 1i: Дополнительные исследования (2026-07-10)

- [x] [TASK-research-onorca-ade](TASK-research-onorca-ade.todo.md) — Orca ADE (stablyai/orca, MIT, TypeScript/Electron + mobile; «AI Orchestrator» — параллельная ручная оркестрация coding-агентов в изолированных git worktrees: fan-out prompt → N agents → merge winner; BYO subscription; Ghostty-class terminal IDE; mobile companion; Orca Tasks/Automations; `orca.yaml`) *(verdict 🟡 паттерны / 🔴 не dependency; code review Approval)*

### Этап 1j: Дополнительные исследования (2026-07-26)

- [x] [TASK-research-bx-dev-skill](TASK-research-bx-dev-skill.todo.md) — bx-dev (`bish-x/bx-dev-skill`, Codex-skill/manual workflow harness; session branch from `origin/dev`, state `.bx-dev/<session-id>/`, single-shot Codex subagents: Dev → review → conventional commit → optional post-commit QA → Merger; strict flags `--solo`/`--careful`/`--no-review`/`--plan-approve`/`--no-sop`; MERGE-PROTOCOL; 105 support skills / 9 categories) *(verdict 🟡 паттерны / 🔴 не dependency; code review Approval — 1 CR устранён)*

### Этап 1k: Дополнительные исследования (2026-08-03)

- [ ] [TASK-research-qm](../TASK-research-qm.todo.md) — qm (`yc-software/qm`, TypeScript/Node, MIT, ≈8.8k★; multiplayer/multi-tenant agent-платформа-оркестратор над внешними харнесами Pi/OpenCode/Codex/Claude Code: per-employee scopes, Slack+web, per-scope durable sandbox, shared skills с org-governance, security postures Strict/Auto/Dangerous + command policy, deployment-directory контракт + `qm` CLI, interface-backed субстраты; потребляет `SKILL.md`/`.claude/skills`/`.codex/skills`/`AGENTS.md`). Классификация: multiplayer/multi-tenant platform-оркестратор / harness-over-external-agents, ближайший аналог Orca ADE (#30), по прецеденту OmO (#23)/bx-dev (#31). Предварительный verdict: 🟡 паттерны / 🔴 не dependency.

### Этап 1l: Дополнительные исследования (2026-08-03)

- [ ] [TASK-research-omnigent](../TASK-research-omnigent.todo.md) — omnigent (`omnigent-ai/omnigent`, Python, Apache-2.0, ≈8k★, **alpha**; open-source meta-harness над внешними coding-агентами Claude Code/Codex/Cursor/OpenCode/Hermes/Pi + custom YAML-агенты: multi-device surfaces (terminal/browser/phone/desktop), cloud sandboxes (Modal/E2B/K8s/…), OS-sandboxing (bwrap/seatbelt/Job Objects + L7 egress), policies (approval/spend/tool-limits), multi-agent supervision, collaboration; потребляет `.claude/skills`/`AGENTS.md`). Классификация: open-source meta-harness / orchestration platform over external agents, ближайший аналог qm (#32), по прецеденту Orca ADE (#30)/OmO (#23). Предварительный verdict: 🟡 паттерны / 🔴 не dependency.

### Этап 1m: Дополнительное исследование (2026-08-11)

- [ ] [TASK-research-herdr](../TASK-research-herdr.todo.md) — Herdr (`herdrdev/herdr` `v0.8.0`, Rust, Apache-2.0; постоянный фоновый server (сервер) реальных PTY-терминалов для 19+ внешних coding agents (агентов программирования), состояния `idle/working/blocked/done/unknown`, 90-method CLI/socket API, события, Git worktrees, native resume, experimental live handoff, executable plugins и release-matched `SKILL.md`). Классификация: `terminal agent runtime / control surface` (терминальная среда выполнения и управляющая поверхность), не coding agent и не chain engine. Исследование выполнено; строка #34 добавлена как 32-й завершённый результат из 34 запланированных. Verdict: 🟡 паттерны / 🔴 не core dependency / 🟢 опциональная ручная среда.

### Этап 2: Сводный анализ (после завершения Этапа 1)

- [x] [TASK-research-agent-frameworks-summary](TASK-research-agent-frameworks-summary.todo.md) — Сводная таблица и итоговые рекомендации

## 6. Definition of Done (Критерии приёмки эпика)
- [x] Все индивидуальные research-задачи выполнены
- [x] Каждый comparison-документ создан в `docs/research/`
- [x] Сводная таблица в `docs/research/agent-frameworks-summary.md` создана
- [x] По каждому фреймворку есть вердикт: заимствовать / dependency / не подходит
- [x] Выделены конкретные паттерны для заимствования с приоритетами

## 7. Release Notes and Deployment (Инструкция по релизу)
Не требуется — эпик содержит только исследовательские задачи (docs).

## 8. Risks and Dependencies (Риски и зависимости)
- 10+ фреймворков — значительный объём исследования
- Многие продукты активно развиваются — информация может устареть
- Проприетарные продукты (Claude Code, Copilot, Codex) — анализ только по документации
- Разные языки/экосистемы (Python, TypeScript, Rust, Go) — нужна аккуратность при переносе паттернов в PHP

## 9. Sources (Источники)
- Существующие comparison-документы: `docs/research/framework-comparisons/agent-bernstein-comparison.md`, `docs/research/framework-comparisons/agent-orchestrator-comparison.md`, `docs/research/framework-comparisons/superpowers-brainstorming-comparison.md`
- Ссылки на репозитории и документацию — в индивидуальных задачах

## 10. Comments (Комментарии)
Эпик объединяет все накопившиеся research-задачи в единый трек с чётким финальным артефактом — сводной таблицей. Задачи Этапа 1 можно выполнять в любом порядке и параллельно. Задача Этапа 2 запускается только после завершения всех исследований.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-21 | Тимлид (Алекс) | Создание эпика |
| 2026-04-22 | Технический писатель (Гермиона) | Все 11 задач выполнены. Эпик завершён. |
| 2026-04-22 | Тимлид (Алекс) + Пуаро + Локи | Постфактум ревью всех 11 отчётов через сабагентов. 5 критических и 15+ значимых исправлений. PR #52. |
| 2026-06-13 | Тимлид (Алекс) | Добавлена research-задача по addyosmani/agent-skills. |
| 2026-06-13 | Аналитик (Шерлок) | Stage 1g: исследование Agent Skills подготовлено, задача ожидает review и финализацию оркестратором. |
| 2026-06-13 | Тимлид (Алекс) | Stage 1g: TASK-research-agent-skills принята, переведена в done и подготовлена к merge PR #258. |
| 2026-06-18 | Тимлид (Алекс) | Эпик reopened: добавлена стадия 1h — TASK-research-swarm-forge (unclebob/swarm-forge, tmux-based swarm orchestration от R.C. Martin). SwarmForge концептуально ближе всего к нашей системе ролей/AGENTS.md/conventions; предварительный verdict: заимствовать паттерны, не dependency. |
| 2026-06-18 | Тимлид (Алекс) | Stage 1h: TASK-research-swarm-forge принята (PR #272, verdict 🟡 паттерны / 🔴 не dependency, 29/29). Эпик возвращён в `done`. |
| 2026-07-10 | Аналитик (Шерлок) | Эпик reopened (статус `in_progress`, стадия `1i`): добавлена постановка TASK-research-onorca-ade — Orca ADE (stablyai/orca, MIT, TypeScript/Electron, ≈15.3k★, YC-backed). Orca — система ручной оркестрации (ADE/harness поверх coding-агентов в worktrees), не coding-агент → отнесён в этот эпик по прецеденту oh-my-openagent (#23). Ближайшие аналоги: SwarmForge (#29), AgentCraft (#16), Sandcastle (#20). Предварительный verdict: 🟡 паттерны / 🔴 не dependency. |
| 2026-07-10 | Тимлид (Алекс) | Stage 1i: TASK-research-onorca-ade выполнена — comparison-отчёт `orca-ade-comparison.md`, строка `Orca ADE` (#30) в summary (`30 / 30`). Verdict 🟡 заимствовать паттерны (parallel fan-out comparison, structured worker_done/dispatch-id, BYO runner ergonomics, worktree isolation, live monitoring) / 🔴 не dependency. Code review Архитектор Локи → Approval (5 CR устранены, сверено с `skills/orchestration/SKILL.md` и `src/main/runtime/orchestration/db.ts`). Задача перенесена в `done/`. |
| 2026-07-26 | Аналитик (Шерлок) | Эпик reopened (статус `in_progress`, стадия `1j`): добавлено исследование `TASK-research-bx-dev-skill` — `bish-x/bx-dev-skill` (`$bx-dev`, Codex-skill/manual workflow harness). Классификация: система оркестрации/harness, не coding-агент, по прецеденту oh-my-openagent #23 / Orca ADE #30. Предварительный verdict: 🟡 паттерны (session-state `.bx-dev/`, strict flags, scout-plan gate, MERGE-PROTOCOL, conventional commit per task, optional QA, skill-library governance) / 🔴 не dependency (Codex-specific, no retry/CB/quality gates/budget/fix_iterations parity). |
| 2026-07-26 | Тимлид (Алекс) | Stage 1j: TASK-research-bx-dev-skill выполнена — comparison-отчёт `bx-dev-skill-comparison.md`, строка `bx-dev` (#31) в summary (`31 / 31`). Verdict 🟡 заимствовать паттерны (strict workflow flags, scout-plan approval gate, session-state resume metadata, structured single-shot subagent report/close contract, optional post-commit QA flag, MERGE-PROTOCOL artifact, skill-library router/manifest) / 🔴 не dependency (Codex-specific, нет retry/CB/quality gates/budget/fix_iterations). Code review Архитектор Локи → 1 CR (порядок commit/QA в lifecycle) → доработка Шерлока → повторное ревью Approval (св. со `skills/bx-dev/SKILL.md` Step 9/10). `make md-links`/`validate-todo` — зелёные. Задача перенесена в `done/`. |
| 2026-08-03 | Тимлид (Алекс) | TASK-research-deepagents перенесён из этого эпика в `EPIC-research-coding-agents-comparison` (стадия `1k` там): deepagents — CLI-агент кодинга по аналогии с Claude Code («Inspired by Claude Code»), не фреймворк оркестрации. |
| 2026-08-03 | Тимлид (Алекс) | Эпик reopened (стадия `1k`): добавлена постановка TASK-research-qm — `yc-software/qm` (TypeScript/Node, MIT, ≈8.8k★; multiplayer/multi-tenant agent-платформа-оркестратор над внешними харнесами Pi/OpenCode/Codex/Claude Code: scopes, Slack+web, per-scope durable sandbox, shared skills governance, security postures + command policy, deployment-directory контракт + `qm` CLI, interface-backed субстраты). Классификация: multiplayer/multi-tenant platform-оркестратор / harness-over-external-agents, ближайший аналог Orca ADE (#30), по прецеденту OmO (#23)/bx-dev (#31). Предварительный verdict: 🟡 паттерны / 🔴 не dependency. |
| 2026-08-03 | Тимлид (Алекс) | Эпик reopened (стадия `1l`): добавлена постановка TASK-research-omnigent — `omnigent-ai/omnigent` (Python, Apache-2.0, ≈8k★, alpha; open-source meta-harness над Claude Code/Codex/Cursor/OpenCode/Hermes/Pi + custom YAML: multi-device, cloud+OS sandboxing, policies, multi-agent supervision). Классификация: meta-harness / orchestration platform over external agents, ближайший аналог qm (#32), по прецеденту Orca ADE (#30)/OmO (#23). Предварительный verdict: 🟡 паттерны / 🔴 не dependency. |
| 2026-08-11 | Аналитик (Шерлок) | Эпик reopened стадией `1m`: добавлена и выполнена постановка TASK-research-herdr по `herdrdev/herdr` `v0.8.0`. Herdr классифицирован как terminal agent runtime / control surface над внешними coding agents, а не chain engine. Создан `herdr-comparison.md`, заполнена строка #34; текущий прогресс — 32 завершённых из 34 запланированных исследований. Verdict: 🟡 заимствовать lifecycle/wait/ownership/worktree patterns; 🔴 не core dependency; 🟢 допустим как опциональная ручная среда. |
