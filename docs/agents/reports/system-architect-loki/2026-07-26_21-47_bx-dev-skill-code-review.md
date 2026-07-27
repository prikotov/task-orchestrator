# Code review исследования bx-dev-skill

**Роль:** Архитектор Локи
**Дата:** 2026-07-26
**Объект:** ветка `task/research-bx-dev-skill`; файлы `todo/TASK-research-bx-dev-skill.todo.md`, `docs/research/framework-comparisons/bx-dev-skill-comparison.md`, `docs/research/agent-frameworks-summary.md`, `todo/done/EPIC-research-agent-frameworks-comparison.md`
**Задача:** `todo/TASK-research-bx-dev-skill.todo.md` — docs-only research исследования Codex-skill `bish-x/bx-dev-skill` (stage `1j`, #31 для эпика `EPIC-research-agent-frameworks-comparison`)

---

## Контекст проверки

Проведено review (ревью) без исправления артефактов. Внешняя сверка выполнена по первоисточникам репозитория `bish-x/bx-dev-skill` на snapshot `main` / `dd7fa7a2f65e487e49847394bff6cd5986b5877e`.

Прочитаны первичные источники:

- `README.md`, `README.ru.md`
- `skills/bx-dev/SKILL.md`
- `skills/bx-dev/docs/CODEX-ORCHESTRATION.md`
- `skills/bx-dev/docs/MERGE-PROTOCOL.md`
- `skills/bx-dev/templates/agents/bx-dev-merger.md`
- `skills/bx-dev/skill-library/INDEX.md`, `MANIFEST.md`
- GitHub API metadata, commit API, recursive tree API

## Что подтверждено

- Классификация `bx-dev` как `Codex-skill / manual workflow harness` корректна: первоисточники описывают installed `$bx-dev` skill, который координирует Codex subagents, но не поставляет LLM runtime и не реализует собственный `agent-loop`.
- Lifecycle в отчёте в целом соответствует источникам: session branch от `origin/dev`, `.bx-dev/<session-id>/state.json`, state через `jq`, single-shot subagents, `wait_agent`, `close_agent`, `push`/PR/merge, `exit`/cleanup.
- Флаги `--solo`, `--careful`, `--no-review`, `--plan-approve`, `--no-sop` и deprecated no-op `--sop` отражены корректно, включая strict parsing / fail-fast unknown flags.
- Bounded review/QA rounds подтверждены: smoke/test fix max 2, review max 2, QA max 2.
- `MERGE-PROTOCOL.md` корректно представлен как отдельный artifact (артефакт) с conflict taxonomy, semantic/smoke/verify/rollback flow и JSON status contract через `bx-dev-merger.md`.
- Skill-library metadata подтверждена: 105 support skills / 9 categories; `INDEX.md` — category router, `MANIFEST.md` — flat inventory.
- Snapshot metadata подтверждена: `language: Python`, `license: null`, отсутствие LICENSE-like файла в tree, 17★, 5 forks, 1 open issue, topics and default branch `main`.
- Пересчёты summary выглядят консистентно: `31 / 31`, `agent-loop 19/31` без включения bx-dev, `SKILL.md 22/31` с включением bx-dev, `MCP 18/31` без включения bx-dev, `sub-agents 19/31` с включением bx-dev, `compression 12/31` без включения bx-dev, `security 8 projects` без включения bx-dev.
- Формат research-документа соответствует reference (референсам) `orca-ade-comparison.md` / `swarm-forge-comparison.md`: snapshot, classification, architecture, capability overview, comparison with `task-orchestrator`, analogues, borrow summary, verdict, sources.

## Change Requests

### CR-1 — Уточнить порядок `commit` и QA в task lifecycle

**Файл:** `docs/research/framework-comparisons/bx-dev-skill-comparison.md`

**Строки:** 33, 247–249

**Проблема:** отчёт и Mermaid diagram показывают порядок `review → optional QA → conventional commit`. В `skills/bx-dev/SKILL.md` фактический порядок другой: `Step 9: Commit` идёт после review, затем `Step 10: QA (--careful only)`, а при QA failure делается fix и `git commit --amend --no-edit`. То есть QA выполняется после первичного task commit и может amend'ить этот commit, а не предшествует commit boundary.

**Почему важно:** это искажает один из проверяемых механизмов — `conventional-commit-per-task`. Для переноса pattern (паттерна) в `task-orchestrator` порядок gate/commit/QA принципиален: либо QA должна быть pre-commit gate, либо post-commit amend gate, но это разные governance semantics.

**Предложение:** заменить line 33 на что-то вроде:

```text
Каждая задача проходит scout → implement → smoke tests → review → conventional commit; при `--careful` после commit запускается QA, а найденные QA-исправления amend'ят task commit.
```

И поправить Mermaid-фрагмент: `Reviewers -> Conventional commit -> {--careful?} -> QA -> Amend commit/Task complete`, либо явно подписать QA как post-commit gate.

## Проверки

- `make md-links` — успешно: `All internal links valid`.
- `make validate-todo` — успешно: `todo/TASK-research-bx-dev-skill.todo.md`, `0 error(s), 0 warning(s)`.
- PHPUnit/Psalm не запускались: изменения docs-only, код/конфигурация/скрипты не затронуты.

## Итог

Approval пока не даю из-за одного фактического искажения lifecycle в comparison-документе. После исправления CR-1 ожидается Approval без повторной глубокой сверки всего отчёта.

## Источники

- https://github.com/bish-x/bx-dev-skill/tree/dd7fa7a2f65e487e49847394bff6cd5986b5877e
- https://raw.githubusercontent.com/bish-x/bx-dev-skill/main/README.md
- https://raw.githubusercontent.com/bish-x/bx-dev-skill/main/skills/bx-dev/SKILL.md
- https://raw.githubusercontent.com/bish-x/bx-dev-skill/main/skills/bx-dev/docs/CODEX-ORCHESTRATION.md
- https://raw.githubusercontent.com/bish-x/bx-dev-skill/main/skills/bx-dev/docs/MERGE-PROTOCOL.md
