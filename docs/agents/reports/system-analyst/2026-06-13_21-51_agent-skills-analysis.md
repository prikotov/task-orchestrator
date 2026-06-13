# Аналитический отчёт: addyosmani/agent-skills

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-06-13
**Объект:** `addyosmani/agent-skills`, `docs/research/framework-comparisons/agent-skills-comparison.md`, `docs/research/agent-frameworks-summary.md`
**Задача:** `todo/TASK-research-agent-skills.todo.md`

---

## Краткий вывод

Agent Skills — это не runtime orchestrator (рантайм-оркестратор), а skill pack (пакет скиллов) для AI coding agents (AI-агентов разработки). Его ценность для `task-orchestrator` — не в dependency (зависимости), а в authoring patterns (паттернах написания инструкций): единая anatomy (анатомия) `SKILL.md`, anti-rationalization (анти-рационализация), red flags (красные флаги), evidence-based verification (проверка доказательствами), progressive disclosure (прогрессивная загрузка контекста), validator (валидатор) скиллов и guardrails (защитные правила) для persona composition (композиции персон).

## Что изучено

- Репозиторий `https://github.com/addyosmani/agent-skills` через GitHub archive и GitHub API.
- `README.md`: lifecycle commands, supported agents/IDEs, список 24 skills.
- `docs/skill-anatomy.md`: формат `SKILL.md`, frontmatter, recommended sections, progressive disclosure.
- `AGENTS.md`: OpenCode integration, mandatory skill invocation, intent→skill mapping.
- `agents/README.md` и `references/orchestration-patterns.md`: personas, fan-out review, запрет persona-calls-persona.
- `scripts/validate-skills.js`: валидатор frontmatter/sections/cross-skill references.
- Representative skills: `using-agent-skills`, `spec-driven-development`, `planning-and-task-breakdown`, `code-review-and-quality`, `git-workflow-and-versioning`.

## Основные находки

| Ось | Вывод |
| --- | --- |
| Orchestration model | Host-driven skill activation + lifecycle commands; `/ship` описывает parallel fan-out with merge. |
| State management | N/A / host-dependent: пакет не хранит состояние, всё делегировано host agent. |
| Error handling | N/A / delegated to host agent: нет retry/CB/fallback, есть prompt-level verification и guardrails. |
| Extensibility | `SKILL.md`, personas, commands, plugin manifests, hooks, references, validator. |
| Applicability | Заимствовать паттерны authoring/governance; не использовать как dependency. |

## Рекомендованные паттерны для task-orchestrator

1. Skill anatomy convention для `docs/agents/skills/*`.
2. Skill anatomy validator для frontmatter, required sections, `depends_on` и ссылок.
3. Anti-rationalization tables в critical skills (`task-via-subagents`, `epic-via-subagents`, `run-subagent`).
4. Red flags для self-review и orchestrator review.
5. Lifecycle map ролей/скиллов: analysis → architecture → implementation → QA → review → docs → ship.
6. Reference checklists с progressive disclosure.
7. Persona composition guardrail: роли не вызывают роли; orchestration only через Тимлид/skill/chain.

## Созданные/обновлённые артефакты

- `docs/research/framework-comparisons/agent-skills-comparison.md` — полный comparison report.
- `docs/research/agent-frameworks-summary.md` — добавлена строка Agent Skills и счётчик `28 / 28`.
- `todo/TASK-research-agent-skills.todo.md` — отмечены выполненные requirements/plan/DoD без перевода в `done` и без `pr`.
- `todo/done/EPIC-research-agent-frameworks-comparison.md` — Stage 1g отмечен как ожидающий review/orchestrator finalization, без закрытия задачи.

## Sources

1. `https://github.com/addyosmani/agent-skills`
2. `https://github.com/addyosmani/agent-skills/blob/main/README.md`
3. `https://github.com/addyosmani/agent-skills/blob/main/docs/skill-anatomy.md`
4. `https://github.com/addyosmani/agent-skills/blob/main/references/orchestration-patterns.md`
5. `https://api.github.com/repos/addyosmani/agent-skills`
