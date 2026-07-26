---
type: docs
created: 2026-07-26
value: V3
complexity: C2
priority: P2
depends_on:
epic: EPIC-research-agent-frameworks-comparison
author: Аналитик (Шерлок)
assignee: Аналитик (Шерлок)
branch: task/research-bx-dev-skill
pr:
status: done
---

# TASK-research-bx-dev-skill: Исследовать bish-x/bx-dev-skill (Codex-skill для workflow-оркестрации dev-сессий)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В проекте уже есть собственный `task-orchestrator`: YAML-цепочки (`config/chains.yaml`), `DynamicLoop`, `run-subagent`/`task-via-subagents`, retry с backoff, circuit breaker, quality gates (проверки качества), бюджетный контроль, `fix_iterations`, JSONL audit trail (аудит в JSONL) и строгий Git/PR workflow.
- `bx-dev` (`bish-x/bx-dev-skill`) решает соседнюю задачу в мире Codex: это самодостаточный Codex-skill `$bx-dev`, который ведёт временную dev-сессию на session branch (сессионной ветке), хранит state (состояние) в `.bx-dev/<session-id>/`, делегирует работу single-shot Codex subagents (одноразовым субагентам Codex) и проводит `impl → review → optional QA → PR → merge → cleanup`.
- Нужно понять, какие паттерны `bx-dev` можно заимствовать в `task-orchestrator` и наши agent-skills, а какие неприменимы: `bx-dev` Codex-specific, не PHP/Symfony CLI dependency (зависимость), не имеет собственного LLM runtime (рантайма), agent-loop (цикла агента), retry/CB/budget/fix_iterations на уровне оркестратора.

### Варианты или путь решения (Solution Sketch)
- Изучить первичные источники репозитория `bish-x/bx-dev-skill`: README EN/RU, `skills/bx-dev/SKILL.md`, `docs/CODEX-ORCHESTRATION.md`, `docs/MERGE-PROTOCOL.md`, merger template, `skill-library/INDEX.md`, `MANIFEST.md`, `.gitattributes`, `.gitignore`.
- Сравнить модель `$bx-dev` с нашим `task-via-subagents`/`epic-via-subagents`, `config/chains.yaml`, `ChainExecution`, `DynamicLoop`, retry/CB/gates/budget.
- Сопоставить с ближайшими аналогами в сводке: SwarmForge #29, Orca ADE #30, Oh My OpenAgent #23, AgentCraft #16.
- Оформить отдельный comparison report (сравнительный отчёт), строку #31 в summary (сводке), reopen эпика стадией `1j`.

### Ожидаемый результат (Expected Result)
- Есть отдельный отчёт `docs/research/framework-comparisons/bx-dev-skill-comparison.md`, строка `bx-dev` (#31) в `docs/research/agent-frameworks-summary.md`, эпик reopened стадией `1j`.
- Verdict (вердикт) зафиксирован: 🟡 заимствовать отдельные паттерны; 🔴 не использовать как dependency.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы развиваем собственный workflow `task-via-subagents`/`epic-via-subagents` и chain-based orchestration (цепочечную оркестрацию), я хочу изучить `bish-x/bx-dev-skill`, чтобы понять, какие паттерны Codex-native (нативного для Codex) dev-session harness (контура dev-сессии) можно безопасно адаптировать: `.bx-dev/<session-id>/` state, строгие флаги режимов, scout-plan approval gate (ворота подтверждения плана), optional QA, merge protocol как отдельный артефакт, conventional-commit-per-task и governance (управление) библиотеки skills.

### Goal (Цель по SMART)
Провести техническое исследование `bish-x/bx-dev-skill` по snapshot (снимку) `main`, зафиксировать модель оркестрации, state management, error handling, extensibility и применимость к `task-orchestrator`. Оформить `docs/research/framework-comparisons/bx-dev-skill-comparison.md`, обновить `docs/research/agent-frameworks-summary.md` до `31 / 31`, добавить стадию `1j` в `todo/done/EPIC-research-agent-frameworks-comparison.md`. Срок: 2026-07-26.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/research/framework-comparisons/bx-dev-skill-comparison.md`, `docs/research/agent-frameworks-summary.md`, `todo/done/EPIC-research-agent-frameworks-comparison.md`, agent-report в `docs/agents/reports/system-analyst/`.
*   **Текущее поведение:** В эпике уже исследовано 30 проектов. Ближайшие аналоги: SwarmForge #29 (tmux/worktree swarm + handoff), Orca ADE #30 (desktop/mobile worktree fan-out), Oh My OpenAgent #23 (team mode поверх OpenCode), AgentCraft #16 (GUI wrapper поверх внешних агентов).
*   **Границы (Out of Scope):** Не интегрируем `bx-dev` как runtime dependency, не запускаем `$bx-dev` локально, не меняем код `task-orchestrator`, не переносим Codex-specific tools (`wait_agent`, `close_agent`, `send_input`) в PHP. Не читаем все 105 support skills построчно — используем `INDEX.md`/`MANIFEST.md` и выборочные ключевые документы.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] Изучить GitHub metadata repo `bish-x/bx-dev-skill`: description, default branch, language, license, stars/forks/issues, topics, created/pushed, commit snapshot.
- [x] Изучить `README.md` и `README.ru.md`: назначение, session branch, команды `push`/`exit`, requirements (требования), install (установка), skill-library.
- [x] Изучить `skills/bx-dev/SKILL.md`: lifecycle (жизненный цикл), state files, flags, task loop, review/QA, push/exit/recovery, commit convention.
- [x] Изучить `docs/CODEX-ORCHESTRATION.md`: single-shot subagents, `codex_agents`, close lifecycle, report contract.
- [x] Изучить `docs/MERGE-PROTOCOL.md` и `templates/agents/bx-dev-merger.md`: merge stages, rollback, status contract, squash vs merge.
- [x] Изучить `skill-library/INDEX.md` и `MANIFEST.md`: 105 support skills / 9 категорий, router (роутер), exclusions (исключения).
- [x] Сравнить с `task-orchestrator`: `config/chains.yaml`, `ChainExecution`, `DynamicLoop`, retry/CB/quality gates/budget/`fix_iterations`, JSONL audit, `run-subagent`/`task-via-subagents`.
- [x] Сопоставить с аналогами: SwarmForge #29, Orca ADE #30, Oh My OpenAgent #23, AgentCraft #16.
- [x] Оформить отчёт `docs/research/framework-comparisons/bx-dev-skill-comparison.md` со стандартной comparison table (таблицей сравнения).
- [x] Добавить строку `bx-dev` (#31) в `docs/research/agent-frameworks-summary.md`, обновить счётчик до `31 / 31`.
- [x] Reopen'уть эпик: `reopened: 2026-07-26`, стадия `1j`, change history.
- [x] Дать чёткий verdict: 🟡 patterns, 🔴 not dependency.

### 🟡 Should Have (Желательно)
- [x] Выделить concrete patterns (конкретные паттерны) для заимствования: `.bx-dev/<session-id>/` state, strict flags, `--plan-approve`, MERGE-PROTOCOL, conventional commits per task, optional QA, support skill-library governance.
- [x] Отдельно отметить ограничения: Codex-specific skill, нет собственного runtime/LLM, нет universal retry/CB/budget/fix_iterations, resilience делегируется Codex и человеку.
- [x] Уточнить место в таксономии эпика: Codex-skill / manual workflow harness, не coding agent.

### 🟢 Could Have (Опционально)
- [x] Добавить Mermaid-диаграмму сопоставления `bx-dev` с `task-orchestrator`.
- [ ] Создать backlog tasks на отдельные паттерны — только по решению тимлида после review.

### ⚫ Won't Have (Не будем делать)
- [x] Интеграция `bx-dev` как dependency.
- [x] Локальный запуск `$bx-dev`.
- [x] Перенос Codex subagent API в PHP.
- [x] Изменение production цепочек/ролей/конвенций.

## 4. Implementation Plan (План реализации)
1. [x] Проверить текущую ветку `task/research-bx-dev-skill` без переключения веток.
2. [x] Прочитать reference (референс) задачи Orca ADE и comparison-документы Orca/SwarmForge.
3. [x] Получить GitHub metadata и commit snapshot `bish-x/bx-dev-skill`.
4. [x] Прочитать README EN/RU.
5. [x] Прочитать `skills/bx-dev/SKILL.md` и выделить lifecycle, flags, state, task loop, push/exit/recovery.
6. [x] Прочитать `CODEX-ORCHESTRATION.md` и `MERGE-PROTOCOL.md`.
7. [x] Прочитать merger template и skill-library inventory.
8. [x] Сравнить с `task-orchestrator` и ближайшими аналогами.
9. [x] Создать task file `todo/TASK-research-bx-dev-skill.todo.md`.
10. [x] Создать comparison report.
11. [x] Обновить summary: строка #31, счётчики, тренды, рекомендации.
12. [x] Обновить epic stage `1j` и change history.
13. [x] Сохранить self-contained agent-report.
14. [x] Запустить `make md-links` и `make validate-todo`.

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт `docs/research/framework-comparisons/bx-dev-skill-comparison.md` создан и содержит сравнение с `task-orchestrator`.
- [x] В отчёте есть стандартная comparison table: orchestration model, state management, error handling, extensibility, applicability.
- [x] В отчёте разобраны ключевые механизмы: `.bx-dev/` session-state, impl/review/QA фазы, flags, MERGE-PROTOCOL, scout-plan gate, conventional commits, skill-library, BYO Codex runtime.
- [x] В `docs/research/agent-frameworks-summary.md` добавлена строка `bx-dev` (#31) и счётчик `31 / 31`.
- [x] Эпик reopened стадией `1j`, есть change history.
- [x] Указаны sources и дата анализа.

## 6. Verification (Самопроверка)
```bash
ls docs/research/framework-comparisons/bx-dev-skill-comparison.md
grep -c "bx-dev" docs/research/agent-frameworks-summary.md
grep -n "31 / 31" docs/research/agent-frameworks-summary.md
grep -n "1j" todo/done/EPIC-research-agent-frameworks-comparison.md
make md-links
make validate-todo
```

## 7. Risks and Dependencies (Риски и зависимости)
- Репозиторий `bx-dev-skill` маленький и публичный, но часть runtime-семантики зависит от Codex subagent tools; мы анализируем документы, не выполняем runtime.
- `skills/bx-dev/SKILL.md` исторически содержит legacy Claude Agent Teams vocabulary (терминологию), но `CODEX-ORCHESTRATION.md` явно задаёт Codex mapping. В отчёте нужно считать этот документ более авторитетным.
- Skill-library содержит 105 support skills; полный построчный аудит каждого support skill не входит в scope. Выводы о governance основаны на `INDEX.md`, `MANIFEST.md` и структуре repository tree.
- В `bx-dev` есть merge protocol и bounded review/QA rounds, но нет полноценного chain-level retry/CB/budget/fix_iterations — нельзя завышать resilience.
- `push`/`exit` в `bx-dev` сами выполняют `gh pr merge`; в нашем проекте такие действия запрещены без явного подтверждения пользователя. При заимствовании pattern нужен перенос с нашими Git rules.

## 8. Sources (Источники)
- https://github.com/bish-x/bx-dev-skill
- https://api.github.com/repos/bish-x/bx-dev-skill
- https://raw.githubusercontent.com/bish-x/bx-dev-skill/main/README.md
- https://raw.githubusercontent.com/bish-x/bx-dev-skill/main/README.ru.md
- https://raw.githubusercontent.com/bish-x/bx-dev-skill/main/skills/bx-dev/SKILL.md
- https://raw.githubusercontent.com/bish-x/bx-dev-skill/main/skills/bx-dev/docs/CODEX-ORCHESTRATION.md
- https://raw.githubusercontent.com/bish-x/bx-dev-skill/main/skills/bx-dev/docs/MERGE-PROTOCOL.md
- https://raw.githubusercontent.com/bish-x/bx-dev-skill/main/skills/bx-dev/templates/agents/bx-dev-merger.md
- https://raw.githubusercontent.com/bish-x/bx-dev-skill/main/skills/bx-dev/skill-library/INDEX.md
- https://raw.githubusercontent.com/bish-x/bx-dev-skill/main/skills/bx-dev/skill-library/MANIFEST.md

## 9. Comments (Комментарии)
Первичный вывод подтверждён: `bx-dev` — не coding agent и не framework dependency. Это Codex-skill/manual workflow harness (ручной workflow-контур), полезный как зеркало для нашего `task-via-subagents`: он формализует одну dev-сессию через state, role lifecycle, review/QA gates, PR/merge protocol и cleanup.

## 10. Result (Результат выполнения)

Исследование выполнено на дату 2026-07-26 по snapshot `main` `dd7fa7a2f65e487e49847394bff6cd5986b5877e`.

Созданы/обновлены артефакты:
- `docs/research/framework-comparisons/bx-dev-skill-comparison.md`
- `docs/research/agent-frameworks-summary.md`
- `todo/done/EPIC-research-agent-frameworks-comparison.md`
- `docs/agents/reports/system-analyst/2026-07-26_21-17_bx-dev-skill-research.md`

Итоговый verdict: 🟡 **заимствовать отдельные паттерны**, 🔴 **не использовать как dependency**.

Проверки:
- `make md-links` — успешно (`All internal links valid`).
- `make validate-todo` — успешно (`todo/TASK-research-bx-dev-skill.todo.md`, `0 error(s), 0 warning(s)`).
- PHPUnit/Psalm не запускались: docs-only research.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-26 | Аналитик (Шерлок) | Создание задачи и выполнение исследования `bish-x/bx-dev-skill` для stage `1j` research-эпика. |
