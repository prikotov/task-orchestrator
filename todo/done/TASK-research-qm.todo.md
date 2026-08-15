---
type: docs
created: 2026-08-03
value: V3
complexity: C2
priority: P2
depends_on:
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch: task/research-qm
pr: "https://github.com/prikotov/task-orchestrator/pull/347"
status: done
---

# TASK-research-qm: Исследовать yc-software/qm (multiplayer/multi-tenant agent-платформа-оркестратор поверх внешних харнесов)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В проекте уже есть собственный `task-orchestrator`: YAML-цепочки (`config/chains.yaml`), `DynamicLoop` (динамические циклы), `run-subagent`/`task-via-subagents`/`epic-via-subagents`, retry с backoff (повтор с задержкой), circuit breaker (автоматический выключатель), quality gates (ворота качества), бюджетный контроль, `fix_iterations` (итерации доработки), JSONL audit trail (аудит-лог), и своя система skills (`docs/agents/skills/` + `SKILL.md` + meta-skill `become-role`).
- `qm` (`yc-software/qm`, TypeScript/Node, MIT, ≈8.8k★, npm `@yc-software/qm`) — это **multiplayer/multi-tenant agent-платформа-оркестратор**: не отдельный агент, а слой-оркестратор над внешними coding-агентами (Pi, OpenCode, Codex, Claude Code — все за одним интерфейсом). Каждый сотрудник получает изолированный workspace (область); есть personal/shared scopes (личные и общие области) со своей памятью, файлами, keychain (хранилищем ключей), правами, cron'ами, веб-приложениями и durable sandbox (постоянной песочницей). Поверхности — Slack и web; админка задаёт org-config, security posture (режим безопасности) и доступные harnesses/модели. Архитектура: Postgres (sessions/memory/queue) ↔ headless core (API/identity/policy/**scheduler** + agent loop) ↔ per-scope sandbox. Контракт deployment directory (каталога развёртывания) + `qm` CLI: generic core + org-specific слой, все субстраты (harness/session store/sandbox/memory) за интерфейсами. Security postures: Strict (HITL на каждый tool call) / Auto (классификатор-скрининг, дефолт) / Dangerous + predeclared command policy (жёсткие запреты на деструктив). Skills: scope-owned, shareable by grant (доступ по разрешению), admin-gated promotion to org, skill packs из git-репозиториев; использует `SKILL.md` + `.claude/skills` + `.codex/skills` + `AGENTS.md` — те же конвенции, что у нас.
- Нужно понять, какие паттерны `qm` стоит заимствовать в `task-orchestrator` (и наши agent-skills), а какие неприменимы: `qm` — полноценная multi-tenant SaaS-платформа/продукт, не PHP/Symfony CLI dependency (зависимость); у нас single-tenant chain-оркестрация, а не Slack/web-платформа для команды.

### Варианты или путь решения (Solution Sketch)
- Изучить первичные источники `yc-software/qm`: README, `deployment.md`/`deploy-directory.md`, `SECURITY.md` (threat model), `cli/README.md` (`qm` CLI + deployment directory contract), `CONTRIBUTING.md`, структуру репозитория (`cli/src/` — backends/commands/sandbox-layer/deployment-layer/providers/plugins, `plugins/`).
- Особый фокус (наибольшая ценность заимствования): **multi-harness abstraction** (Pi/OpenCode/Codex/Claude Code за одним интерфейсом — релевантно нашему runner/subagent и кодинг-агентам-эпику), **shared skills governance** (scope-owned + grant-sharing + admin-gated org-promotion + skill packs из git — богатая параллель нашим skills), **security postures + predeclared command policy** (Strict/Auto/Dangerous + hard denials — релевантно sandboxing/approval gates), **deployment directory contract** (generic core + org-слой, interface-backed субстраты — референс разделения config/deploy), **per-scope durable sandbox** (релевантно `TASK-feat-docker-sandboxing`).
- Сравнить модель `qm` (platform-over-external-agents) с нашим chain-based подходом: `config/chains.yaml`, `ChainExecution`, `DynamicLoop`, retry/CB/quality gates/budget/`fix_iterations`, JSONL audit, `run-subagent`/`task-via-subagents`, наши skills.
- Сопоставить с ближайшими аналогами в сводке: Orca ADE #30 (platform/control-surface над внешними CLI-агентами — ближайший аналог), OmO #23 (coordination layer над OpenCode), bx-dev #31, LangGraph (#4). Уточнить дельту: `qm` добавляет **multiplayer/multi-tenant** (per-employee workspaces, Slack/web, org-admin) + **durable per-scope sandboxes** + **shared skills governance** + **security postures**.
- Оформить отдельный comparison report (сравнительный отчёт), строку #32 в summary (сводке), reopen эпика стадией `1k`.

### Ожидаемый результат (Expected Result)
- Есть отдельный отчёт `docs/research/framework-comparisons/qm-comparison.md`, строка `qm` (#32) в `docs/research/agent-frameworks-summary.md`, эпик reopened стадией `1k`.
- Verdict (вердикт) зафиксирован: 🟡 заимствовать отдельные паттерны (multi-harness abstraction, skills governance, security postures, deployment contract); 🔴 не использовать как dependency (multi-tenant SaaS-платформа, не PHP/Symfony, не single-tenant chain-оркестрация).

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы развиваем собственные agent-skills (`docs/agents/skills/`, `become-role`), runner/subagent-модель (`run-subagent`/`task-via-subagents`) и контуры sandboxing/approval gates, я хочу изучить `yc-software/qm` — multiplayer agent-платформу-оркестратор, абстрагирующую внешние харнесы (Pi/OpenCode/Codex/Claude Code), — чтобы понять, какие её паттерны (multi-harness abstraction за одним интерфейсом, shared skills с org-governance и import из git, security postures с hard denials, deployment-directory контракт с interface-backed субстратами, per-scope durable sandbox) можно безопасно адаптировать под нашу single-tenant chain-модель, а где multi-tenant SaaS-архитектура принципиально избыточна.

### Goal (Цель по SMART)
Провести техническое исследование `yc-software/qm` по snapshot (снимку) `main` на дату анализа, зафиксировать модель оркестрации, state management, error handling, extensibility и применимость к `task-orchestrator`. Оформить `docs/research/framework-comparisons/qm-comparison.md`, обновить `docs/research/agent-frameworks-summary.md` до `32 / 32`, добавить стадию `1k` в `todo/done/EPIC-research-agent-frameworks-comparison.md`. Срок: согласовать с тимлидом (ориентир — ближайший спринт).

## 2. Context and Scope (Контекст и Границы)
*   **Объект:** `yc-software/qm` — TypeScript/Node multiplayer agent-платформа-оркестратор. npm `@yc-software/qm`. Стек: Fastify (HTTP core), Postgres (persistence), Bolt (Slack plugin), Vite+Lit (web UI), `qm` CLI (TypeScript). Лицензия MIT, ≈8.8k★/≈927 forks. Параллельные артефакты: `.claude/skills/` и `.codex/skills/` (dev-instance, update-qm, upstream-pr, deploy-qm) — `qm` сам потребляет skill-конвенции.
*   **Где делаем:** `docs/research/framework-comparisons/qm-comparison.md`, `docs/research/agent-frameworks-summary.md`, `todo/done/EPIC-research-agent-frameworks-comparison.md`, agent-report в `docs/agents/reports/system-analyst/`.
*   **Текущее поведение:** В эпике исследовано 31 проект. Ближайший аналог — Orca ADE #30 (desktop/mobile control-surface над внешними CLI-агентами в worktrees). `qm` — следующий уровень: multi-tenant platform-оркестратор (Slack+web) над теми же внешними харнесами, с per-employee workspaces и durable sandboxes.
*   **Границы (Out of Scope):** Не интегрируем `qm` как runtime dependency, не разворачиваем `qm init` локально, не меняем код `task-orchestrator`, не портируем Postgres/Slack/web-стек в PHP. Глубокий code review TypeScript-исходников — на уровне архитектуры и контрактов (`qm` CLI, deployment directory, interface-backed субстраты), не построчно. Multi-tenant SaaS-функционал (Slack-бот, web-портал, админка) описывается концептуально, без воспроизведения.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] Зафиксировать GitHub metadata `yc-software/qm`: description, default branch, language, license, stars/forks/issues, topics, created/pushed, commit snapshot, версию npm `@yc-software/qm`.
- [x] Изучить README: что такое QM, фичи (scopes, Slack+web, admin control, web apps, shared skills, background work), multi-harness модель (Pi/OpenCode/Codex/Claude Code за одним интерфейсом), архитектуру (Postgres ↔ core ↔ sandbox), security postures, deployment (deployment repo vs private fork), `qm init`.
- [x] Изучить `cli/README.md` и deployment-directory contract: generic core + org-specific deployment dir, interface-backed субстраты (harness/session store/sandbox/memory), `qm` CLI (init/infra/setup/sandbox/conformance/check/outputs).
- [x] Изучить `SECURITY.md` (threat model): security postures (Strict/Auto/Dangerous), predeclared command policy (hard denials для деструктива), provenance-labelling + классификатор-скрининг external data.
- [x] Изучить архитектуру shared skills: scope-owned, shareable by grant, admin-gated org-promotion, skill packs из git; использование `SKILL.md` + `.claude/skills` + `.codex/skills` + `AGENTS.md`.
- [x] Сравнить с `task-orchestrator`: `config/chains.yaml`, `ChainExecution`, `DynamicLoop`, retry/CB/quality gates/budget/`fix_iterations`, JSONL audit, `run-subagent`/`task-via-subagents`, наши skills (`docs/agents/skills/` + `become-role`).
- [x] Сопоставить с аналогами: Orca ADE #30 (ближайший — control-surface над внешними агентами), OmO #23 (coordination layer), bx-dev #31, LangGraph (#4); уточнить дельту `qm` vs Orca (multiplayer/multi-tenant + Slack/web + durable sandboxes + skills governance).
- [x] Оформить отчёт `docs/research/framework-comparisons/qm-comparison.md` со стандартной comparison table (orchestration model, state management, error handling, extensibility, applicability).
- [x] Добавить строку `qm` (#32) в `docs/research/agent-frameworks-summary.md`, обновить счётчик до `32 / 32` и пересчитать затронутые тренды (agent loop, SKILL.md, MCP, sub-agents/multi-agent, context compression, security posture/HITL).
- [x] Reopen'уть эпик: `reopened: <дата>`, стадия `1k`, change history.
- [x] Дать чёткий verdict: 🟡 patterns / 🔴 not dependency (предварительно).

### 🟡 Should Have (Желательно)
- [x] Выделить concrete patterns (конкретные паттерны) для заимствования: multi-harness abstraction (единый интерфейс над Pi/OpenCode/Codex/Claude Code), shared skills governance (scope-owned + grant + admin-gated org-promotion + git import), security postures с hard denials, deployment-directory контракт (generic core + org-слой, interface-backed субстраты), per-scope durable sandbox.
- [x] Отдельно отметить ограничения: multi-tenant SaaS-платформа (Slack/web/admin), не single-tenant chain-оркестрация; Postgres/Bolt/Vite-стек; resilience (retry/CB/budget/fix_iterations) — на уровне platform/sandbox, не нашего chain-уровня.
- [x] Уточнить место в таксономии эпика: multiplayer/multi-tenant agent platform-оркестратор / harness-over-external-agents, по прецеденту Orca ADE (#30) + OmO (#23).
- [x] Оценить релевантность multi-harness abstraction для нашего coding-agents-эпика (qm абстрагирует ровно тех агентов, что мы исследовали: Pi/OpenCode/Codex/Claude Code).

### 🟢 Could Have (Опционально)
- [ ] Добавить Mermaid-диаграмму слоёв `qm` (Postgres ↔ core ↔ sandbox ↔ Slack/web) и сопоставления с chain-моделью `task-orchestrator`.
- [ ] Создать backlog tasks на отдельные паттерны — только по решению тимлида после review.

### ⚫ Won't Have (Не будем делать)
- [x] Интеграция `qm` как dependency.
- [x] Локальный запуск `qm init` / развёртывание Slack/web.
- [x] Порт Postgres/Slack/web-стека или multi-tenant-модели в PHP.
- [x] Изменение production цепочек/ролей/конвенций.

## 4. Implementation Plan (План реализации)
*План предзаполнен автором (Тимлид Алекс); исполнитель (Аналитик Шерлок) подтверждает понимание перед стартом (Reverse Briefing) и уточняет при необходимости.*
1. [x] Проверить рабочую ветку (создать/переключиться на `task/research-qm`), без переключения на `main`.
2. [x] Прочитать reference-задачи: `done/TASK-research-onorca-ade.todo.md` (#30, ближайший аналог), `done/TASK-research-oh-my-openagent.todo.md` (#23), `done/TASK-research-bx-dev-skill.todo.md` (#31), comparison-документы Orca/OmO.
3. [x] Получить GitHub metadata и commit snapshot `yc-software/qm`; зафиксировать версию npm `@yc-software/qm`.
4. [x] Прочитать README целиком; выписать фичи, multi-harness модель, архитектуру, security postures, deployment-режимы, `qm init`.
5. [x] Изучить `cli/README.md` и deployment-directory contract (`qm` CLI, interface-backed субстраты).
6. [x] Изучить `SECURITY.md` (threat model, postures, command policy, screening).
7. [x] Разобрать shared skills-модель и `SKILL.md`/`.claude/skills`/`.codex/skills`/`AGENTS.md` (сравнить с нашими skills).
8. [x] Сравнить с `task-orchestrator` (chains/DynamicLoop/retry/CB/gates/budget/fix_iterations/JSONL/наши skills) и с аналогами (Orca #30, OmO #23, bx-dev #31, LangGraph #4).
9. [x] Создать comparison report `docs/research/framework-comparisons/qm-comparison.md`.
10. [x] Обновить summary: строка #32, счётчики `32 / 32`, пересчёт затронутых трендов.
11. [x] Обновить epic: `reopened`, стадия `1k`, change history.
12. [x] Сохранить self-contained agent-report в `docs/agents/reports/system-analyst/`.
13. [x] Запустить `make md-links` и `make validate-todo`.

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт `docs/research/framework-comparisons/qm-comparison.md` создан и содержит сравнение с `task-orchestrator`.
- [x] В отчёте есть стандартная comparison table: orchestration model, state management, error handling, extensibility, applicability.
- [x] В отчёте разобраны ключевые механизмы: multi-harness abstraction, scopes (personal/shared), per-scope durable sandbox, shared skills governance, security postures + command policy, deployment-directory контракт, background work (crons/watches).
- [x] В `docs/research/agent-frameworks-summary.md` добавлена строка `qm` (#32), счётчик `32 / 32`, пересчитаны затронутые тренды.
- [x] Эпик reopened стадией `1k`, есть change history.
- [x] Указаны sources, версия npm и дата анализа.

## 6. Verification (Самопроверка)
```bash
ls docs/research/framework-comparisons/qm-comparison.md
grep -c "qm" docs/research/agent-frameworks-summary.md
grep -n "32 / 32" docs/research/agent-frameworks-summary.md
grep -n "1k" todo/done/EPIC-research-agent-frameworks-comparison.md
make md-links
make validate-todo
```

## 7. Risks and Dependencies (Риски и зависимости)
- Репозиторий крупный (≈1.3k+ файлов) и активно развивается (≈8.8k★) — фиксировать commit snapshot и версию npm на дату анализа; указывать дату в отчёте.
- `qm` — TypeScript/Node multi-tenant SaaS-платформа; перенос паттернов в PHP/Symfony single-tenant chain-оркестрацию требует аккуратности (многое избыточно: Slack/web/admin/multi-tenant).
- Orca ADE (#30) — ближайший аналог; нужно чётко показать дельту `qm` (multiplayer/multi-tenant + Slack/web + durable sandboxes + skills governance), а не повторять control-surface-анализ.
- Multi-harness abstraction (Pi/OpenCode/Codex/Claude Code) перекликается с coding-agents-эпиком — оценивать как паттерн runner-абстракции, не как дублирование coding-agent-исследований.
- `qm` потребляет skill-конвенции (`SKILL.md`, `.claude/skills`, `AGENTS.md`), сам не определяя агент-рантайм — это consumer skills, не producer; учитывать при сравнении с нашими skills.
- Версия/стабильность: `qm` — относительно молодой продукт; оценивать зрелость архитектуры по документации и контракту, а не по runtime-тестам.

## 8. Sources (Источники)
- [x] [yc-software/qm — GitHub](https://github.com/yc-software/qm)
- [x] [qm — README (архитектура, фичи, deployment)](https://github.com/yc-software/qm#readme)
- [x] [qm CLI + deployment directory contract — cli/README.md](https://github.com/yc-software/qm/blob/main/cli/README.md)
- [x] [SECURITY.md — threat model, security postures](https://github.com/yc-software/qm/blob/main/SECURITY.md)
- [x] [@yc-software/qm — npm](https://www.npmjs.com/package/@yc-software/qm)

## 9. Comments (Комментарии)
Первичный вывод тимлида: `qm` — не coding-агент и не framework dependency для нас (multi-tenant TS/Node SaaS-платформа). Ценность — как зеркало и источник паттернов. Наибольший потенциал заимствования: **multi-harness abstraction** (Pi/OpenCode/Codex/Claude Code за одним интерфейсом — прямой мост к нашему coding-agents-эпику и runner-модели), **shared skills governance** (scope-owned + grant + admin-gated org-promotion + git import — богатая параллель нашим `SKILL.md`/`become-role`), **security postures + predeclared command policy** (Strict/Auto/Dangerous + hard denials — релевантно sandboxing и approval gates), **deployment-directory контракт** (generic core + org-слой, interface-backed субстраты), **per-scope durable sandbox** (релевантно `TASK-feat-docker-sandboxing`). Классификация в эпике: multiplayer/multi-tenant agent platform-оркестратор / harness-over-external-agents, по прецеденту Orca ADE (#30, ближайший аналог) + OmO (#23) + bx-dev (#31).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-03 | Тимлид (Алекс) | Создание задачи. Источник: пользователь указал репозиторий `yc-software/qm`. Классифицирован как multiplayer/multi-tenant agent-платформа-оркестратор / harness над внешними агентами → `EPIC-research-agent-frameworks-comparison` (эпик ресерча систем/фреймворков оркестрации, не статей), стадия `1k` (строка #32). Предварительный verdict: 🟡 patterns / 🔴 not dependency. |
| 2026-08-14 | Аналитик (Шерлок) | `status: review`: исследование `yc-software/qm` завершено, comparison-отчёт и строка #32 подготовлены; артефакты готовы к review тимлидом. См. agent-report `docs/agents/reports/system-analyst/2026-08-14_qm-research.md`. |
| 2026-08-14 | Тимлид (Алекс) | Создан отдельный PR #347 для проверки пользователем; задача остаётся в `review`. |

| 2026-08-15 | Тимлид (Алекс) | PR #347 принят: задача переведена в `done` перед слиянием. |
