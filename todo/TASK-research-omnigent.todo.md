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
branch:
pr:
status: todo
---

# TASK-research-omnigent: Исследовать omnigent-ai/omnigent (open-source meta-harness над внешними coding-агентами)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В проекте уже есть собственный `task-orchestrator`: YAML-цепочки (`config/chains.yaml`), `DynamicLoop` (динамические циклы), `run-subagent`/`task-via-subagents`/`epic-via-subagents`, retry с backoff (повтор с задержкой), circuit breaker (автоматический выключатель), quality gates (ворота качества), бюджетный контроль, `fix_iterations` (итерации доработки), JSONL audit trail (аудит-лог), и своя система skills (`docs/agents/skills/` + `SKILL.md` + meta-skill `become-role`).
- `omnigent` (`omnigent-ai/omnigent`, Python, Apache-2.0, ≈8k★, PyPI `omnigent`, **status: alpha**) — это **open-source meta-harness**: общий оркестрационный слой над внешними coding-агентами (Claude Code, Codex, Cursor, OpenCode, Hermes, Pi) и custom-агентами (YAML-определения). Меняй/комбинируй харнесы без переписывания. Поверхности: терминал, браузер, телефон, native desktop (macOS) — сессии следуют за пользователем跨-устройств. Архитектурные блоки: multi-agent supervision (один агент ревьюит другого, разбиение задачи), cloud sandboxes (Modal/Daytona/E2B/CoreWeave/K8s/OpenShell/Boxlite/Databricks — disposable, per-session managed hosts), OS-sandboxing (bwrap на Linux / seatbelt на macOS / Job Objects на Windows + L7 egress proxy), policies/governance (pause-on-approval для рискованных действий, cap spend, лимит tools; scope: server/agent/chat), collaboration (shared sessions, co-drive, fork), any-model (API key / Claude/ChatGPT subscription / gateway). Нативные terminal-обёртки (tmux/PTY) для claude/codex/cursor/hermes/kiro/pi + SDK-harnesses (antigravity/copilot/cursor/agents-sdk). Потребляет `.claude/skills/` + `AGENTS.md` — те же конвенции, что у нас.
- Нужно понять, какие паттерны `omnigent` стоит заимствовать в `task-orchestrator` (и наши agent-skills), а какие неприменимы: `omnigent` — meta-harness/платформа (Python, alpha), не PHP/Symfony CLI dependency (зависимость); у нас single-tenant chain-оркестрация, а не multi-device collaboration-платформа.

### Варианты или путь решения (Solution Sketch)
- Изучить первичные источники `omnigent-ai/omnigent`: README, docs (policies, sandboxes, harnesses, custom YAML agents), `scripts/install_oss.sh`, структуру репозитория (`src/` — harness backends/sandbox providers/policy engine/server+web+desktop), `.github/agents/*.yaml` (custom agent definitions).
- Особый фокус (наибольшая ценность заимствования): **meta-harness abstraction** (единый интерфейс над Claude Code/Codex/Cursor/OpenCode/Hermes/Pi + custom YAML-агенты — релевантно нашему runner/subagent и coding-agents-эпику), **custom agents в YAML** (релевантно нашим `config/chains.yaml`/роли), **policies/governance** (approval-gates/spend-caps/tool-limits с scope server/agent/chat — релевантно quality gates/budget), **cloud + OS sandboxing** (10 cloud providers + bwrap/seatbelt/Job Objects/L7 egress — релевантно `TASK-feat-docker-sandboxing`), **multi-agent supervision** (review-between-agents, task split — релевантно нашему review/сабагентам).
- Сравнить модель `omnigent` (meta-harness over external agents) с нашим chain-based подходом: `config/chains.yaml`, `ChainExecution`, `DynamicLoop`, retry/CB/quality gates/budget/`fix_iterations`, JSONL audit, `run-subagent`/`task-via-subagents`, наши skills.
- Сопоставить с ближайшими аналогами в сводке: **qm #32 (ближайший аналог — тоже meta-harness над внешними агентами)**, Orca ADE #30 (control-surface над CLI-агентами), OmO #23 (coordination layer), bx-dev #31. Уточнить дельту: `omnigent` vs `qm` — шире покрытие харнесов (Cursor/Hermes/Kiro + custom YAML), богаче sandboxing (OS-level + 10 cloud providers), policies со spend-caps, native multi-device apps; `qm` сильнее в multi-tenant/team/org-governance и deployment-контракте.
- Оформить отдельный comparison report (сравнительный отчёт), строку #33 в summary (сводке), reopen эпика стадией `1l`.

### Ожидаемый результат (Expected Result)
- Есть отдельный отчёт `docs/research/framework-comparisons/omnigent-comparison.md`, строка `omnigent` (#33) в `docs/research/agent-frameworks-summary.md`, эпик reopened стадией `1l`.
- Verdict (вердикт) зафиксирован: 🟡 заимствовать отдельные паттерны (meta-harness abstraction, custom YAML agents, policies/spend-caps, cloud+OS sandboxing, multi-agent supervision); 🔴 не использовать как dependency (Python, alpha, multi-device платформа, не PHP/Symfony single-tenant chain-оркестрация).

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы развиваем собственные agent-skills (`docs/agents/skills/`, `become-role`), runner/subagent-модель (`run-subagent`/`task-via-subagents`) и контуры sandboxing/policies/budget, я хочу изучить `omnigent-ai/omnigent` — open-source meta-harness, оркестрирующий внешние coding-агенты (Claude Code/Codex/Cursor/OpenCode/Hermes/Pi + custom YAML) поверх единого интерфейса, — чтобы понять, какие его паттерны (meta-harness abstraction, custom YAML-агенты, policies с approval/spend/tool-limits, cloud+OS sandboxing, multi-agent supervision) можно безопасно адаптировать под нашу single-tenant chain-модель, а где multi-device collaboration-платформа (alpha) принципиально избыточна.

### Goal (Цель по SMART)
Провести техническое исследование `omnigent-ai/omnigent` по snapshot (снимку) `main` на дату анализа, зафиксировать модель оркестрации, state management, error handling, extensibility и применимость к `task-orchestrator`. Оформить `docs/research/framework-comparisons/omnigent-comparison.md`, обновить `docs/research/agent-frameworks-summary.md` до `33 / 33`, добавить стадию `1l` в `todo/done/EPIC-research-agent-frameworks-comparison.md`. Срок: согласовать с тимлидом (ориентир — ближайший спринт).

## 2. Context and Scope (Контекст и Границы)
*   **Объект:** `omnigent-ai/omnigent` — Python meta-harness. PyPI `omnigent`, Apache-2.0, ≈8k★/≈1.2k forks, **status: alpha**. Стек: Python 3.12+, `uv`; server + web UI (pnpm) + native desktop (macOS); native terminal-обёртки (tmux/PTY). Оркестрирует Claude Code/Codex/Cursor/OpenCode/Hermes/Pi + SDK-harnesses (antigravity/copilot/cursor/agents-sdk) + custom YAML-агенты.
*   **Где делаем:** `docs/research/framework-comparisons/omnigent-comparison.md`, `docs/research/agent-frameworks-summary.md`, `todo/done/EPIC-research-agent-frameworks-comparison.md`, agent-report в `docs/agents/reports/system-analyst/`.
*   **Текущее поведение:** В эпике qm (#32, стадия `1k`) — ближайший аналог (тоже meta-harness над внешними агентами). `omnigent` — параллельный кандидат в той же нише: шире покрытие харнесов и sandboxing, но alpha-стадус и другая фокусировка (multi-device collaboration vs qm's multi-tenant team).
*   **Границы (Out of Scope):** Не интегрируем `omnigent` как runtime dependency, не запускаем `install_oss.sh`/`omnigent server` локально, не меняем код `task-orchestrator`, не портируем Python/web/desktop-стек в PHP. Глубокий code review — на уровне архитектуры и контрактов (harness backends, sandbox providers, policy engine, custom agent YAML), не построчно. Multi-device collaboration (phone/desktop/co-drive) описывается концептуально.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Зафиксировать GitHub metadata `omnigent-ai/omnigent`: description, default branch, language, license, stars/forks/issues, topics, created/pushed, commit snapshot, версию PyPI `omnigent`, подтвердить status: alpha.
- [ ] Изучить README: что такое Omnigent, capabilities (multi-device, multi-agent supervision, any-model, collaboration, cloud sandboxes, policies/governance), meta-harness модель (Claude Code/Codex/Cursor/OpenCode/Hermes/Pi + custom YAML), install (extras), supported harnesses/sandboxes/models.
- [ ] Изучить **meta-harness abstraction**: единый интерфейс над харнесами, swap/combine без переписывания; native terminal-обёртки (tmux/PTY) vs SDK-harnesses; точки расширения (свой harness).
- [ ] Изучить **custom agents в YAML** (`.github/agents/*.yaml`, `omnigent run <agent.yaml>`): схема определения агента, mapping на наши роли/`config/chains.yaml`.
- [ ] Изучить **policies/governance**: approval-gates (pause перед рискованными действиями), spend caps, tool limits; scope server/agent/chat; mapping на наши quality gates/budget/approval.
- [ ] Изучить **sandboxing**: cloud providers (Modal/Daytona/E2B/CoreWeave/K8s/OpenShell/Boxlite/Databricks, disposable per-session managed hosts) + OS-level (bwrap/seatbelt/Job Objects + L7 egress proxy); mapping на `TASK-feat-docker-sandboxing`.
- [ ] Изучить **multi-agent supervision** (review-between-agents, task split across agents) и collaboration (shared sessions, co-drive, fork); `.claude/skills/` + `AGENTS.md`.
- [ ] Сравнить с `task-orchestrator`: `config/chains.yaml`, `ChainExecution`, `DynamicLoop`, retry/CB/quality gates/budget/`fix_iterations`, JSONL audit, `run-subagent`/`task-via-subagents`, наши skills.
- [ ] Сопоставить с аналогами: qm #32 (ближайший — meta-harness над внешними агентами), Orca ADE #30, OmO #23, bx-dev #31; уточнить дельту `omnigent` vs `qm` (harness-покрытие, sandboxing, policies, multi-device vs multi-tenant).
- [ ] Оформить отчёт `docs/research/framework-comparisons/omnigent-comparison.md` со стандартной comparison table (orchestration model, state management, error handling, extensibility, applicability).
- [ ] Добавить строку `omnigent` (#33) в `docs/research/agent-frameworks-summary.md`, обновить счётчик до `33 / 33` и пересчитать затронутые тренды (agent loop, SKILL.md, MCP, sub-agents/multi-agent, context compression, sandboxing, policy/HITL).
- [ ] Reopen'уть эпик: `reopened: <дата>`, стадия `1l`, change history.
- [ ] Дать чёткий verdict: 🟡 patterns / 🔴 not dependency (предварительно).

### 🟡 Should Have (Желательно)
- [ ] Выделить concrete patterns (конкретные паттерны) для заимствования: meta-harness abstraction (единый интерфейс над 6+ харнесами + custom YAML), custom agents в YAML, policies (approval/spend/tool-limits с гранулярным scope), cloud + OS sandboxing, multi-agent supervision (review-between-agents).
- [ ] Отдельно отметить ограничения: Python, **alpha** (незрелый, API/поведение могут меняться), multi-device collaboration-платформа (phone/desktop/co-drive), не single-tenant chain-оркестрация; resilience (retry/CB/budget/fix_iterations) — на уровне platform/sandbox/policy, не нашего chain-уровня.
- [ ] Уточнить место в таксономии эпика: open-source meta-harness / orchestration platform over external agents, ближайший аналог qm (#32), по прецеденту Orca ADE (#30)/OmO (#23).
- [ ] Оценить релевантность meta-harness abstraction для нашего coding-agents-эпика (omnigent оркестрирует тех же агентов: Claude Code/Codex/Cursor/Pi/OpenCode).

### 🟢 Could Have (Опционально)
- [ ] Добавить Mermaid-диаграмму слоёв `omnigent` (meta-harness ↔ harness backends ↔ sandboxes ↔ surfaces) и сопоставления с chain-моделью `task-orchestrator`.
- [ ] Сравнительная таблица `omnigent` vs `qm` (оба meta-harness): по осям harness-покрытие, sandboxing, policies, multi-tenancy, multi-device, зрелость.
- [ ] Создать backlog tasks на отдельные паттерны — только по решению тимлида после review.

### ⚫ Won't Have (Не будем делать)
- [ ] Интеграция `omnigent` как dependency.
- [ ] Локальный запуск `install_oss.sh` / `omnigent server` / desktop/phone.
- [ ] Порт Python/web/desktop-стека или multi-device-модели в PHP.
- [ ] Изменение production цепочек/ролей/конвенций.

## 4. Implementation Plan (План реализации)
*План предзаполнен автором (Тимлид Алекс); исполнитель (Аналитик Шерлок) подтверждает понимание перед стартом (Reverse Briefing) и уточняет при необходимости.*
1. [ ] Проверить рабочую ветку (создать/переключиться на `task/research-omnigent`), без переключения на `main`.
2. [ ] Прочитать reference-задачи: `TASK-research-qm.todo.md` (#32, ближайший аналог — meta-harness), `done/TASK-research-onorca-ade.todo.md` (#30), `done/TASK-research-oh-my-openagent.todo.md` (#23), comparison-документы qm/Orca/OmO.
3. [ ] Получить GitHub metadata и commit snapshot `omnigent-ai/omnigent`; зафиксировать версию PyPI `omnigent`, подтвердить status: alpha.
4. [ ] Прочитать README целиком; выписать capabilities, meta-harness модель, harnesses/sandboxes/models, install (extras).
5. [ ] Изучить meta-harness abstraction (harness backends, native vs SDK, точки расширения).
6. [ ] Изучить custom agents в YAML (`.github/agents/*.yaml`, схема) и policies/governance (approval/spend/tools, scope).
7. [ ] Изучить sandboxing (10 cloud providers + OS-level bwrap/seatbelt/Job Objects/L7 egress) и multi-agent supervision/collaboration.
8. [ ] Сравнить с `task-orchestrator` (chains/DynamicLoop/retry/CB/gates/budget/fix_iterations/JSONL/наши skills) и с аналогами (qm #32, Orca #30, OmO #23, bx-dev #31).
9. [ ] Создать comparison report `docs/research/framework-comparisons/omnigent-comparison.md`.
10. [ ] Обновить summary: строка #33, счётчики `33 / 33`, пересчёт затронутых трендов.
11. [ ] Обновить epic: `reopened`, стадия `1l`, change history.
12. [ ] Сохранить self-contained agent-report в `docs/agents/reports/system-analyst/`.
13. [ ] Запустить `make md-links` и `make validate-todo`.

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт `docs/research/framework-comparisons/omnigent-comparison.md` создан и содержит сравнение с `task-orchestrator`.
- [ ] В отчёте есть стандартная comparison table: orchestration model, state management, error handling, extensibility, applicability.
- [ ] В отчёте разобраны ключевые механизмы: meta-harness abstraction, custom YAML-агенты, multi-agent supervision, policies/governance, cloud + OS sandboxing, multi-device surfaces, collaboration.
- [ ] В `docs/research/agent-frameworks-summary.md` добавлена строка `omnigent` (#33), счётчик `33 / 33`, пересчитаны затронутые тренды.
- [ ] Эпик reopened стадией `1l`, есть change history.
- [ ] Указаны sources, версия PyPI, status: alpha и дата анализа.

## 6. Verification (Самопроверка)
```bash
ls docs/research/framework-comparisons/omnigent-comparison.md
grep -c "omnigent" docs/research/agent-frameworks-summary.md
grep -n "33 / 33" docs/research/agent-frameworks-summary.md
grep -n "1l" todo/done/EPIC-research-agent-frameworks-comparison.md
make md-links
make validate-todo
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Status: alpha** — продукт незрелый, API/поведение/архитектура могут меняться; фиксировать commit snapshot + версию PyPI, явно отмечать alpha-статус, оценивать паттерны с поправкой на незрелость.
- Репозиторий крупный (≈3.9k+ файлов) — анализ на уровне архитектуры и контрактов, не построчно.
- `omnigent` — Python multi-device collaboration-платформа; перенос паттернов в PHP/Symfony single-tenant chain-оркестрацию требует аккуратности (многое избыточно: phone/desktop/co-drive/multi-tenant-collaboration).
- qm (#32) — ближайший аналог; нужно чётко показать дельту (harness-покрытие Cursor/Hermes/Kiro/custom-YAML, OS+cloud sandboxing, spend-cap policies, native multi-device vs qm's multi-tenant/team/org-governance/deployment-контракт), а не повторять meta-harness-анализ.
- Meta-harness abstraction перекликается с coding-agents-эпиком — оценивать как паттерн runner-абстракции над теми же агентами (Claude Code/Codex/Cursor/Pi/OpenCode), не как дублирование.
- `omnigent` потребляет skill-конвенции (`.claude/skills/`, `AGENTS.md`), но сам определяет custom-агентов в YAML — и consumer skills, и producer agent-definitions; учитывать при сравнении с нашими skills/ролями.

## 8. Sources (Источники)
- [ ] [omnigent-ai/omnigent — GitHub](https://github.com/omnigent-ai/omnigent)
- [ ] [omnigent — README (capabilities, harnesses, sandboxes, policies)](https://github.com/omnigent-ai/omnigent#readme)
- [ ] [omnigent — PyPI](https://pypi.org/project/omnigent/)
- [ ] [omnigent.ai — сайт + download desktop app](https://omnigent.ai)
- [ ] [.github/agents/*.yaml — custom agent definitions (примеры)](https://github.com/omnigent-ai/omnigent/tree/main/.github/agents)

## 9. Comments (Комментарии)
Первичный вывод тимлида: `omnigent` — не coding-агент и не framework dependency для нас (Python, alpha, multi-device collaboration-платформа). Ценность — как зеркало и источник паттернов, причём **ближайший аналог qm (#32)**: оба — open-source meta-harness над внешними coding-агентами. Наибольший потенциал заимствования: **meta-harness abstraction** (единый интерфейс над Claude Code/Codex/Cursor/OpenCode/Hermes/Pi + custom YAML-агенты — прямой мост к нашему coding-agents-эпику и runner-модели), **custom agents в YAML** (богатая параллель нашим ролям/`config/chains.yaml`), **policies/governance** (approval/spend/tool-limits с гранулярным scope — релевантно quality gates/budget), **cloud + OS sandboxing** (10 providers + bwrap/seatbelt/L7 egress — релевантно `TASK-feat-docker-sandboxing`), **multi-agent supervision** (review-between-agents — релевантно нашему review/сабагентам). Дельта vs qm: omnigent шире по harness-покрытию и sandboxing, но слабее в multi-tenant/team/org-governance (где силён qm). Классификация в эпике: open-source meta-harness / orchestration platform over external agents, по прецеденту qm (#32, ближайший аналог) + Orca ADE (#30) + OmO (#23).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-03 | Тимлид (Алекс) | Создание задачи. Источник: пользователь указал репозиторий `omnigent-ai/omnigent`. Классифицирован как open-source meta-harness / orchestration platform над внешними coding-агентами (ближайший аналог qm #32) → `EPIC-research-agent-frameworks-comparison`, стадия `1l` (строка #33). Status: alpha — отмечен как риск. Предварительный verdict: 🟡 patterns / 🔴 not dependency. |
