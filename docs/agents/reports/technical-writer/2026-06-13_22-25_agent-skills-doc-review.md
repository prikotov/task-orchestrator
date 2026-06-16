# External review: Agent Skills documentation changes

**Роль:** Технический писатель (Гермиона)  
**Дата:** 2026-06-13  
**Ветка:** `task/research-agent-skills`  
**Задача:** `todo/TASK-research-agent-skills.todo.md`  
**Тип ревью:** documentation external review (внешнее ревью документации)  

---

## Вердикт

**Change Requests. Approval не даю до исправления CR-1.**

Основной research report (исследовательский отчёт) структурно готов: задача и DoD в целом покрыты, позиционирование Agent Skills как **skill pack, not runtime** в индивидуальном отчёте корректное, источники достаточные и первичные. Найден один существенный риск misleading aggregate count (вводящего в заблуждение агрегированного счётчика) в сводке и один minor broken link (битая ссылка) в epic artifact.

---

## Проверенные артефакты

- `docs/research/framework-comparisons/agent-skills-comparison.md`
- `docs/research/agent-frameworks-summary.md`
- `todo/TASK-research-agent-skills.todo.md`
- `todo/done/EPIC-research-agent-frameworks-comparison.md`
- `docs/agents/reports/system-analyst/2026-06-13_21-51_agent-skills-analysis.md`
- `docs/agents/reports/system-analyst/2026-06-13_22-17_agent-skills-implementation-self-review.md`

---

## Что проверено и подтверждено

1. **Соответствие задаче и DoD:** основные Must Have и DoD закрыты: создан comparison report, добавлена строка Agent Skills, есть verdict, дата анализа и sources.
2. **Структура research report:** отчёт содержит overview, metadata, anatomy `SKILL.md`, lifecycle mapping, comparison table, patterns, risks, verdict, sources.
3. **Skill pack positioning:** в `agent-skills-comparison.md:14-16`, `197-199`, `229`, `257-259`, `281-291` корректно зафиксировано, что Agent Skills — не runtime orchestrator (рантайм-оркестратор), state/error handling делегированы host agent (агенту-хосту).
4. **Sources:** источники первичные: GitHub repo/API, README, `docs/skill-anatomy.md`, `AGENTS.md`, `agents/README.md`, `references/orchestration-patterns.md`, validator и manifests.
5. **Process state:** `todo/TASK-research-agent-skills.todo.md:12-13` — `pr:` пустой, `status: in_progress`; `gh pr list --head task/research-agent-skills ...` вернул `[]`; файл задачи не перенесён в `todo/done/`.
6. **Прямое копирование:** признаков лишнего копирования больших фрагментов чужого текста не найдено; формулировки выглядят как пересказ/сравнение.

---

## Change Requests

### CR-1 — Misleading sub-agents / multi-agent count в сводке

**Severity:** Major  
**Файл/строки:** `docs/research/agent-frameworks-summary.md:499-504`

**Проблема:** строка `17 из 28 проектов поддерживают sub-agents или multi-agent` выглядит недоказанной/двусмысленной. Список на `docs/research/agent-frameworks-summary.md:500` содержит 16 проектов. Далее `Agent Skills` описан отдельно как проект без собственного runtime (`docs/research/agent-frameworks-summary.md:504`), но, судя по изменению счётчика с `16/26` на `17/28`, именно он мог быть добавлен в numerator (числитель). Это конфликтует с важным позиционированием **skill pack, not runtime** и может ввести читателя в заблуждение, будто Agent Skills runtime-level поддерживает sub-agents.

**Что изменить:** выбрать один из вариантов:
- либо вернуть агрегат к `16 из 28` для runtime/platform-level sub-agents и оставить Agent Skills отдельным host-dependent prompt-level pattern;
- либо явно написать: `16 runtime/platform-level проектов + Agent Skills как prompt-level/host-dependent fan-out pattern`, не называя это полноценной runtime support (поддержкой на уровне исполнения).

### CR-2 — Broken internal link в epic artifact

**Severity:** Minor  
**Файл/строка:** `todo/done/EPIC-research-agent-frameworks-comparison.md:102`

**Проблема:** ссылка `[TASK-research-zeroclaw](done/TASK-research-zeroclaw.todo.md)` из файла в `todo/done/` резолвится как `todo/done/done/TASK-research-zeroclaw.todo.md`, такого файла нет. Правильный относительный путь для текущего расположения epic artifact, вероятно, `TASK-research-zeroclaw.todo.md`.

**Примечание:** ссылка, похоже, была сломана до текущей ветки, но файл входит в reviewed artifacts (проверяемые артефакты), а пользователь явно попросил проверить broken links.

---

## Проверки

- `make validate-todo` — **passed**: 6 todo-файлов провалидированы, `0 error(s), 0 warning(s)`.
- `gh pr list --head task/research-agent-skills --json number,url,state,title --limit 10` — **PR не найден** (`[]`).
- Локальная проверка markdown-ссылок по review artifacts — найден 1 broken internal link: `todo/done/EPIC-research-agent-frameworks-comparison.md:102`.
- GitHub API/raw spot-check: подтверждены `main`, MIT, 24 `skills`, 8 `commands`, 4 persona-файла + `agents/README.md`, Claude plugin manifest и минимальный Antigravity `plugin.json`.

---

## Итог по пунктам запроса

1. **Соответствие задаче и DoD:** в основном ок; блокирует только CR-1 по сводному счётчику.
2. **Полнота/структура отчёта и сводки:** индивидуальный отчёт ок; summary требует корректировки sub-agent count.
3. **Misleading/stale/broken/copying:** misleading count — CR-1; broken link — CR-2; stale `27/27` не найден; лишнего копирования не найдено.
4. **Sources и skill pack positioning:** источники достаточны; позиционирование в индивидуальном отчёте корректное, в summary нужно не размыть его через sub-agent count.
5. **Process state:** корректен: `in_progress`, PR не создан, done finalization (финализация в done) позже.
6. **`make validate-todo`:** passed.

---

## Sources used for spot-check

- https://api.github.com/repos/addyosmani/agent-skills
- https://api.github.com/repos/addyosmani/agent-skills/contents/skills
- https://api.github.com/repos/addyosmani/agent-skills/contents/commands
- https://raw.githubusercontent.com/addyosmani/agent-skills/main/.claude-plugin/plugin.json
- https://raw.githubusercontent.com/addyosmani/agent-skills/main/plugin.json

## Re-review

**Дата:** 2026-06-13  
**Проверенная ветка:** `task/research-agent-skills`  
**Вердикт:** **Approval**.

- **CR-1:** закрыт. В summary (сводке) указано `16 из 28 runtime/platform-level проектов`, а `Agent Skills` вынесен отдельно как `host-dependent prompt-level fan-out pattern`, не как runtime support (поддержка на уровне исполнения).
- **CR-2:** закрыт. Ссылка `TASK-research-zeroclaw` в epic artifact ведёт на `TASK-research-zeroclaw.todo.md`; целевой файл существует в `todo/done/`.
- **`make validate-todo`:** passed — 6 todo-файлов, `0 error(s), 0 warning(s)`.
