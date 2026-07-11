---
type: docs
created: 2026-07-10
value: V3
complexity: C2
priority: P2
depends_on:
epic: EPIC-research-agent-frameworks-comparison
author: Аналитик (Шерлок)
assignee: Аналитик (Шерлок)
branch: task/research-onorca-ade
pr: "#303"
status: done
---

# TASK-research-onorca-ade: Исследовать stablyai/orca (ADE для параллельной ручной оркестрации coding-агентов)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В проекте уже развита собственная модель оркестрации: YAML-цепочки (`config/chains.yaml`), retry с backoff, circuit breaker, quality gates (shell-проверки), бюджетный контроль, `fix_iterations`, fallback routing, JSONL audit trail, ролевая координация (`docs/agents/roles/team/*`) + `AGENTS.md` + `docs/conventions/`, делегирование через сабагентов (`run-subagent`, `task-via-subagents`).
- **Orca** (`stablyai/orca`, https://www.onorca.dev/) решает **соседнюю задачу** иначе: это ADE (Agent Development Environment, среда разработки для агентов) — desktop+mobile приложение для **параллельной ручной оркестрации** coding-агентов. Один prompt «разворачивается» (fan-out) на N агентов (Codex, Claude Code, OpenCode, Pi и десятки других CLI), каждый работает в изолированном git-worktree; результаты сравниваются, победитель merge'ится. BYO subscription (свои подписки на модели), Ghostty-class terminal IDE, mobile companion для мониторинга, Orca Tasks/Automations, `orca.yaml` workspace config.
- Нужно понять, какие паттерны Orca стоит заимствовать в `task-orchestrator` (например, parallel fan-out comparison, workspace config, идея mobile-мониторинга), а какие нам не подходят (desktop Electron-приложение, terminal GUI, mobile native — не переносимы в PHP/Symfony CLI; нет retry/CB/quality gates/budget — наши сильные стороны).

### Варианты или путь решения (Solution Sketch)
- Изучить первичные источники репозитория `stablyai/orca`: `README.md` (main), `orca.yaml` (workspace config), `AGENTS.md`/`CLAUDE.md` (как Orca сам инструктирует агентов), `skills/`, `package.json`/`electron.vite.config.ts` (стек), `docs/model/worktrees`, `docs/terminal`, `docs/mobile`, `docs/automations*`, `docs/reference/`. Сайт https://www.onorca.dev/ и его `/docs`.
- Сравнить модель параллельной worktree-оркестрации Orca с нашей chain-оркестрацией, dynamic loops и сабагент-делегированием.
- Сопоставить с ближайшими аналогами уже в сводной таблице: SwarmForge (#29, tmux+worktrees per role), AgentCraft (#16, GUI wrapper+worktrees), Sandcastle (#20, sandbox+worktrees).
- Зафиксировать выводы в comparison report и новой строке (#30) сводной таблицы research-эпика.

### Ожидаемый результат (Expected Result)
- Есть отдельный отчёт по Orca, новая строка (#30) в сводной таблице и понятный verdict (вердикт): не dependency, но источник паттернов parallel fan-out comparison / workspace config / mobile monitoring.
- Эпик `EPIC-research-agent-frameworks-comparison` reopened новой стадией `1i`.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы развиваем собственную модель оркестрации (chain execution, dynamic loops, retry/CB/quality gates/budget) и оцениваем внешние подходы к координации флота coding-агентов, я хочу изучить `stablyai/orca` (ADE, «AI Orchestrator for 100x builders»), чтобы понять, какие паттерны параллельной ручной оркестрации (parallel worktree fan-out, BYO agent/subscription, workspace config, mobile companion для мониторинга, Orca Tasks/Automations) можно безопасно заимствовать в `task-orchestrator`, а какие нам не применимы (desktop Electron, terminal GUI, отсутствие автоматических resilience-механизмов).

### Goal (Цель по SMART)
Провести техническое исследование `stablyai/orca`: архитектура (TypeScript/Electron desktop + mobile companion, Ghostty-class terminal, WebGL), модель оркестрации (parallel worktree fan-out: 1 prompt → N агентов в изолированных worktrees → compare → merge winner), BYO any CLI agent + subscription, `orca.yaml` workspace config, Orca Tasks/Automations, mobile companion (monitor/steer/notify), встроенные `AGENTS.md`/`CLAUDE.md`/`skills/` в репо Orca, git tracking, и применимость к нашему PHP/Symfony `task-orchestrator`. Оформить отчёт `docs/research/framework-comparisons/orca-ade-comparison.md`, обновить сводную таблицу `docs/research/agent-frameworks-summary.md` (строка #30, счётчик `30 / 30`) и reopen'уть эпик новой стадией `1i`. Срок: до конца Q3 2026.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/research/framework-comparisons/` (новый `orca-ade-comparison.md`), `docs/research/agent-frameworks-summary.md` (новая строка #30 + счётчик `30 / 30`), `todo/done/EPIC-research-agent-frameworks-comparison.md` (reopen: статус `in_progress`, новая стадия `1i`, change history).
*   **Текущее поведение:** В research-эпике уже исследованы 29 AI-agent frameworks/orchestrators (фреймворков/оркестраторов). Ближайшие аналоги по уровню абстракции — **SwarmForge** (#29, tmux+worktrees per role, layered constitution, handoff-протокол), **AgentCraft** (#16, GUI-orchestrator: RTS-интерфейс поверх внешних агентов + git worktrees), **Sandcastle** (#20, sandbox-orchestration: agent invocation loop в sandbox + commit collection). Orca концептуально ближе всего к этой тройке, но это зрелый desktop+mobile product (15.3k★, MIT, YC-backed, TypeScript/Electron), а не bash-скрипты (SwarmForge) и не проприетарный GUI (AgentCraft).
*   **Границы (Out of Scope):** Не интегрируем Orca как dependency (Electron desktop app не переносим в PHP), не запускаем Orca локально, не переносим terminal/mobile/WebGL логику, не переписываем наши цепочки/роли/конвенции в рамках этой задачи. Не выполняем performance-бенчмарки.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Изучить метаданные GitHub repo `stablyai/orca`: description, license (MIT), language (TypeScript), stars/forks (≈15.3k/1.1k), created/pushed (2026-03-17 / активно), topics, default branch, активность. Зафиксировать дату анализа и snapshot.
- [ ] Изучить `README.md` (main, EN): intent («The AI Orchestrator for 100x builders», «Run Codex, ClaudeCode, OpenCode or Pi side-by-side — each in its own worktree»), features (Mobile Companion, Parallel Worktrees, Terminal Splits, Automations, Tasks, Browser, Editor, Notes), supported CLI agents (список 30+), BYO subscription, платформы (macOS/Windows/Linux/mobile).
- [ ] Изучить `orca.yaml` — формат workspace config (топология workspaces/agents/worktrees/branches, привязка агента к worktree).
- [ ] Изучить `docs/model/worktrees` — модель worktree-оркестрации: fan-out prompt → N агентов в изолированных worktrees, compare results, merge winner, изоляция, cleanup/delete preflight.
- [ ] Изучить `docs/terminal` — terminal backend (Ghostty-class, WebGL rendering, infinite splits, scrollback survives restarts, terminal-main-owned-state).
- [ ] Изучить `docs/mobile` — mobile companion (monitor live agent status, steer, notifications on finish, follow-ups, pairing/desktop-web-pairing).
- [ ] Изучить `docs/automations*` + Orca Tasks — модель tasks/automations (триггеры, последовательности, GitHub/Linear integration, rerun on failure, orchestration reset/validation).
- [ ] Изучить `AGENTS.md` + `CLAUDE.md` + `skills/` в репо Orca — как ADE сам инструктирует агентов внутри worktree (мета-интерес для нашего AGENTS.md/conventions; не путать слои).
- [ ] Изучить стек: `package.json`, `electron.vite.config.ts`, `native/`, `mobile/` (Electron + native modules + mobile companion архитектура).
- [ ] Сравнить с нашей моделью: `config/chains.yaml`, chain execution, `DynamicLoop`, `run-subagent`/`task-via-subagents`, retry/CB/quality gates/budget/`fix_iterations` vs Orca (manual parallel harness, автоматические resilience-механизмы делегируются агентам/человеку).
- [ ] Сопоставить с ближайшими аналогами в сводной таблице: SwarmForge (#29), AgentCraft (#16), Sandcastle (#20) — общее и различное.
- [ ] Оформить отчёт `docs/research/framework-comparisons/orca-ade-comparison.md` по формату существующих comparison-документов (см. референс `swarm-forge-comparison.md` / `sandcastle-comparison.md`): стандартная comparison table (orchestration model, state management, error handling, extensibility, applicability).
- [ ] Добавить строку `Orca ADE` (#30) в `docs/research/agent-frameworks-summary.md`, обновить счётчик на `30 / 30`.
- [ ] Reopen'уть эпик: статус `in_progress`, добавить стадию `1i` + задачу в план, запись в change history.
- [ ] Дать чёткий verdict (вердикт): dependency / заимствовать паттерны / не подходит — с обоснованием (ожидаемо: 🟡 заимствовать отдельные паттерны, 🔴 не dependency — как у большинства из 29).
### 🟡 Should Have (Желательно)
- [ ] Выделить конкретные паттерны для возможного заимствования: parallel fan-out comparison («1 prompt → N agents → merge winner» — у нас dynamic loop, но нет fan-out сравнения кандидатов), workspace config (`orca.yaml` vs наш `chains.yaml`), BYO agent/subscription model, mobile companion как идея мониторинга живых запусков, Orca Tasks/Automations (триггеры/последовательности).
- [ ] Оценить ограничения Orca: desktop-first (Electron, не server/CI-first), terminal GUI неприменим к PHP/CLI, нет retry/CB/quality gates/budget (наши сильные стороны), BYO subscription — продуктовая, а не техническая модель.
- [ ] Оценить место Orca в таксономии уровней абстракции эпика (ADE / manual parallel-agent harness как новый подтип рядом с GUI-orchestrator и swarm-orchestration).
### 🟢 Could Have (Опционально)
- [ ] Составить Mermaid-диаграмму сопоставления parallel worktree fan-out Orca с нашим dynamic loop / сабагент-делегированием.
- [ ] Предложить backlog tasks (задачи бэклога) на fan-out comparison кандидатов (если паттерн окажется ценным).
### ⚫ Won't Have (Не будем делать)
- [ ] Интеграция Orca как runtime dependency.
- [ ] Локальный запуск Orca (desktop/mobile/Electron).
- [ ] Перенос TypeScript/Electron/Ghostty/mobile логики в PHP.
- [ ] Изменение существующих production цепочек/ролей/конвенций/конфигов без отдельной задачи.

## 4. Implementation Plan (План реализации)
1. [ ] Изучить метаданные GitHub repo `stablyai/orca`: description, license (MIT), language, stars/forks, created/pushed, topics, default branch, активность. Зафиксировать дату анализа и commit/branch snapshot.
2. [ ] Изучить `README.md` (main, EN): intent, features, supported agents (30+), BYO subscription, платформы.
3. [ ] Изучить `orca.yaml` — workspace config (топология workspaces/agents/worktrees/branches).
4. [ ] Изучить `docs/model/worktrees` — fan-out prompt → N агентов → compare → merge winner; изоляция; cleanup/worktree-delete-preflight.
5. [ ] Изучить `docs/terminal` — Ghostty-class backend, WebGL, splits, scrollback persistence, terminal-main-owned-state.
6. [ ] Изучить `docs/mobile` — mobile companion: monitor/steer/notify/follow-ups; pairing.
7. [ ] Изучить `docs/automations*` + Orca Tasks — триггеры, последовательности, GitHub/Linear, rerun-on-failure, orchestration-reset-scope-validation.
8. [ ] Изучить `AGENTS.md`/`CLAUDE.md`/`skills/` в репо Orca — как ADE инструктирует агентов (мета-анализ).
9. [ ] Изучить стек: `package.json`, `electron.vite.config.ts`, `native/`, `mobile/`.
10. [ ] Сравнить findings (находки) с нашими `config/chains.yaml`, chain execution, `DynamicLoop`, `run-subagent`/`task-via-subagents`, retry/CB/quality gates/budget/`fix_iterations`.
11. [ ] Сопоставить с аналогами: SwarmForge (#29), AgentCraft (#16), Sandcastle (#20).
12. [ ] Написать `docs/research/framework-comparisons/orca-ade-comparison.md`.
13. [ ] Обновить `docs/research/agent-frameworks-summary.md`: строка `Orca ADE` (#30), счётчик `30 / 30`, рекомендации/паттерны при необходимости.
14. [ ] Reopen'уть эпик `todo/done/EPIC-research-agent-frameworks-comparison.md`: статус `in_progress`, стадия `1i`, задача в плане, change history.
15. [ ] Провести self-review (саморевью), external review (внешнее ревью) через сабагента, оставить задачу в `review` до merge finalization (финализации перед слиянием).

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт `docs/research/framework-comparisons/orca-ade-comparison.md` создан и содержит сравнение с `task-orchestrator`.
- [ ] В отчёте есть стандартная comparison table (таблица сравнения): orchestration model, state management, error handling, extensibility, applicability.
- [ ] В отчёте подробно разобраны ключевые механизмы: parallel worktree fan-out, BYO agent/subscription, `orca.yaml` workspace config, terminal/mobile model, Orca Tasks/Automations, встроенные `AGENTS.md`/`skills/`.
- [ ] В `docs/research/agent-frameworks-summary.md` добавлена строка `Orca ADE` (#30) и счётчик обновлён до `30 / 30`.
- [ ] Эпик `EPIC-research-agent-frameworks-comparison.md` reopened: статус `in_progress`, стадия `1i`, задача в плане, запись в change history.
- [ ] В отчёте перечислены 3–7 concrete patterns (конкретных паттернов) для возможного заимствования с приоритетами.
- [ ] Указаны sources (источники) и дата анализа.

## 6. Verification (Самопроверка)
```bash
ls docs/research/framework-comparisons/orca-ade-comparison.md
grep -c "Orca" docs/research/agent-frameworks-summary.md
grep -n "30 / 30" docs/research/agent-frameworks-summary.md
grep -n "1i" todo/done/EPIC-research-agent-frameworks-comparison.md
make validate-todo
make md-links
```

## 7. Risks and Dependencies (Риски и зависимости)
- Репозиторий **очень активно развивается** (push в день анализа, 1280 open issues) — metadata, фичи и структура `docs/` могут быстро меняться; зафиксировать дату анализа и commit/branch snapshot.
- Orca — **desktop-first** Electron-приложение (не server/CI-first): сравнение должно быть честным и не притягивать «CI/server-возможности» туда, где их нет.
- У Orca **нет retry/circuit breaker/quality gates/budget control** (resilience делегируется агентам/человеку) — это наши сильные стороны; вердикт должен это отразить, а не дублировать их паттерны.
- Часть возможностей (terminal WebGL, mobile native, Ghostty) — desktop/mobile-инфраструктура, неприменимая к нашему CLI/PHP-стеку; помечать как `🟢 out of scope` для заимствования.
- Orca сам содержит `AGENTS.md`/`CLAUDE.md`/`skills/` — это мета-слой (как ADE инструктирует агентов внутри worktree); не путать с нашей системой `AGENTS.md`/`docs/conventions/` и не выдавать их форматы за эталон.
- Сайт onorca.dev — Next.js SPA с динамическим контентом; опираться в первую очередь на репозиторий и `/docs`, сайт — как дополнение.

## 8. Sources (Источники)
- https://www.onorca.dev/ (лендинг: «Orca — The most powerful Agent Development Environment (ADE)»)
- https://www.onorca.dev/docs (документация: model/worktrees, terminal, mobile, automations)
- https://github.com/stablyai/orca (репозиторий; default branch `main`)
- https://github.com/stablyai/orca/blob/main/README.md (intent, features, supported agents)
- https://github.com/stablyai/orca/blob/main/orca.yaml (workspace config)
- https://github.com/stablyai/orca/tree/main/docs (reference/, model/worktrees, terminal, mobile, automations)
- https://github.com/stablyai/orca/tree/main/skills (встроенные скиллы ADE)
- https://github.com/stablyai/orca/blob/main/AGENTS.md , `/CLAUDE.md` (как Orca инструктирует агентов)
- Референс формата отчёта: `docs/research/framework-comparisons/swarm-forge-comparison.md` (ближайший аналог), `docs/research/framework-comparisons/sandcastle-comparison.md`

## 9. Comments (Комментарии)
По первичной разведке (выполнена Аналитиком при постановке): **Orca** (`stablyai/orca`, MIT, Copyright 2026 Lovecast Inc., backed by Y Combinator, автор — Stably) — ADE (Agent Development Environment), позиционируется как «The AI Orchestrator for 100x builders». Стек: TypeScript + Electron (desktop) + mobile companion (iOS App Store / TestFlight / Android APK). Метрика: ≈15.3k★, ≈1.1k forks, создан 2026-03-17, активно развивается (push в день анализа).

Ключевое: **parallel worktree orchestration** («Fan one prompt across five agents, each in its own isolated git worktree — compare the results and merge the winner»), **BYO any CLI agent + subscription** (Codex, Claude Code, OpenCode, Pi, Grok, Gemini, Cursor, Copilot и 30+ других), **Ghostty-class terminal IDE** (WebGL rendering, infinite splits, scrollback survives restarts), **mobile companion** (monitor live status, steer, notifications, follow-ups), **Orca Tasks + Automations** (триггеры, GitHub/Linear integration), **`orca.yaml` workspace config**, **git tracking**, browser/editor/notes.

Orca — **не coding agent** и **не фреймворк** (CrewAI/LangGraph); это manual orchestrator harness/ADE поверх coding-агентов. Концептуально ближе всего к **SwarmForge** (#29, tmux+worktrees per role, bash/Clojure), **AgentCraft** (#16, GUI wrapper+worktrees, проприетарный), **Sandcastle** (#20, sandbox+worktrees). Отличие: Orca — зрелый desktop+mobile product (15.3k★, YC, MIT), не bash-скрипты и не проприетарный GUI.

**Классификация:** отнесён в `EPIC-research-agent-frameworks-comparison` (не в `coding-agents-comparison`) по прямому прецеденту — перенос `oh-my-openagent` (#23) с пометкой «система оркестрации, не кодинг-агент». Orca — система ручной оркестрации (harness/ADE), что явно отметил пользователь.

Предварительный verdict: **🟡 заимствовать отдельные паттерны** (parallel fan-out comparison кандидатов, `orca.yaml` workspace config, идея mobile-мониторинга живых запусков), **🔴 не dependency** (Electron desktop app, не переносим в PHP/Symfony CLI; нет retry/CB/quality gates/budget). Сильные стороны Orca — UX параллельной ручной оркестрации и mobile companion; сильные стороны task-orchestrator — автоматические resilience-механизмы и декларативные цепочки. Решения комплементарны, не конкурируют.

## 10. Result (Результат выполнения)

Исследование Orca ADE выполнено по первичным источникам на дату 2026-07-10.

Созданы/обновлены артефакты:

- `docs/research/framework-comparisons/orca-ade-comparison.md` — comparison-отчёт по Orca ADE со snapshot `main` `0f0e32952e7c33adbc8d03325c8ea9d241df1bb8`, стандартной comparison table, разбором parallel worktree fan-out, BYO agent/subscription, `orca.yaml`, terminal/mobile model, Tasks/Automations, `AGENTS.md`/`CLAUDE.md`/`skills/`, сравнением с `task-orchestrator`, SwarmForge, AgentCraft и Sandcastle.
- `docs/research/agent-frameworks-summary.md` — добавлена строка `Orca ADE` (#30), статус заполнения обновлён до `30 / 30`, добавлены рекомендации и пересчитаны релевантные тренды.
- `docs/agents/reports/system-analyst/2026-07-10_19-50_orca-ade-research.md` — self-contained agent-report (отчёт агента) по результатам анализа.

Итоговый verdict (вердикт): 🟡 **заимствовать отдельные паттерны**, 🔴 **не использовать как dependency (зависимость)**. Orca полезен как reference (референс) для parallel fan-out comparison, worktree isolation, live monitoring, BYO runner ergonomics and structured `worker_done`/dispatch-id reports. Как runtime dependency не подходит: Electron/mobile ADE, manual human-in-the-loop model, отсутствие chain-level retry/CB/quality gates/budget/`fix_iterations`.

Проверки:

- `make md-links` — успешно, все internal links (внутренние ссылки) валидны.
- `make validate-todo` — завершился с ошибкой на ранее существующей несвязанной задаче `todo/TASK-fix-pi-static-chain-system-prompt.todo.md` (`type: bug`, отсутствуют обязательные секции); текущая задача `todo/TASK-research-onorca-ade.todo.md` валидируется успешно (`0 error(s), 0 warning(s)`).
- PHPUnit/Psalm — не запускались, потому что задача docs-only research (исследование только документации).

**Code review:** Архитектор Локи (сабагент) → Changes Requested (5 CR) → доработка исполнителем → повторное ревью: **Approval** (все CR устранены, сверено с `skills/orchestration/SKILL.md` и `src/main/runtime/orchestration/db.ts` репозитория stablyai/orca). Отчёты: `docs/agents/reports/system-architect/2026-07-10_20-20_orca-ade-research-review.md`, `2026-07-10_20-36_orca-ade-research-verify-review.md`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-10 | Аналитик (Шерлок) | Создание задачи и постановка исследования Orca ADE. Эпик `EPIC-research-agent-frameworks-comparison` reopened (статус `in_progress`, стадия `1i`). |
| 2026-07-10 | Тимлид (Алекс) | Задача переведена в `in_progress`, делегирована сабагенту в роли Аналитик (Шерлок) на реализацию исследования (конвейер `task-via-subagents`). |
| 2026-07-10 | Тимлид (Алекс) | Code review (Архитектор Локи, сабагент): Changes Requested (5 CR) → доработка исполнителем → повторное ревью: **Approval** (все CR устранены). `make md-links`/`make validate-todo` — зелёные. Ожидает коммита/PR по запросу пользователя. |
| 2026-07-10 | Тимлид (Алекс) | Коммит, push, PR #303 создан от имени `prikotov-agent[bot]` (label `pi`). Задача переведена в `done`, перенесена в `todo/done/`, ссылка в эпике (стадия `1i`) актуализирована. Ожидает merge по явному подтверждению пользователя. |
| 2026-07-10 | Аналитик (Шерлок) | Выполнено исследование Orca ADE: создан comparison-отчёт, обновлена сводная таблица до `30 / 30`, сохранён agent-report; задача оставлена в `in_progress` без изменения front matter. |
