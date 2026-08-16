---
type: docs
created: 2026-08-16
value: V3
complexity: C2
priority: P2
depends_on:
epic: EPIC-research-coding-agents-comparison
author: Аналитик (Шерлок)
assignee: Аналитик (Шерлок)
branch: task/research-deepseek-harness
pr: "https://github.com/prikotov/task-orchestrator/pull/349"
status: done
---

# TASK-research-deepseek-harness: DeepSeek Harness (`deepseek-ai/deepseek-harness`) — агентный харнес с CLI/SDK-поверхностями

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В проекте уже подключены CLI-агенты кодинга как сабагенты к ролям команды: Pi (`@earendil-works/pi-coding-agent`) и omp (`@oh-my-pi/pi-coding-agent`, текущий кандидат #1) — запускаются в JSON-режиме/programmatic, с системным промптом роли, скиллами (`SKILL.md`), AGENTS.md, BYO-ключами.
- `deepseek-harness` (`deepseek-ai/deepseek-harness`, TypeScript, MIT, ≈116.8k★ за 3 дня, npm `@deepseek-ai/dsh` 0.1.0-rc.6) — open-source агентный харнес DeepSeek «Everything is a Plugin» на Cordis с продуктовыми поверхностями: Web UI (`dsh web`), headless CLI (`dsh --profile headless "task"`), JSON-RPC SDK (TS + Python `deepseek-harness-sdk`), ACP-сервер. Подсистемы: system-prompt (реестр секций с `complete: true`), skills (`SKILL.md`, ranks `.agents/skills`), AGENTS.md/CLAUDE.md discovery, субагенты (провайдеры codex/claude-code/acp/dsh-sdk), token-meter, sandbox/permissions.
- По решению пользователя это **агент** (продукт с CLI/SDK-поверхностями, по прецеденту deepagents), а не фреймворк → эпик coding-agents, стадия `1l`.
- Нужно понять по единой методологии из 10 критериев, подходит ли dsh как кандидат в сабагенты к нашим ролям и как соотносится с omp, Pi, Deep Agents, OpenCode.

### Варианты или путь решения (Solution Sketch)
- Изучить первичные источники: README, `apps/cli/reference`, `docs/subsystems/*` (skills, system-prompt, subagent, token-meter), `docs/user/guide/*` (providers, python-sdk), `packages/context/agent-instructions`, npm/PyPI версии; зафиксировать snapshot-коммит.
- Оценить 10 критериев (акцент на продуктовые поверхности: headless CLI + SDK; программный API — как контекст и критерий 6).
- Сопоставить с аналогами: omp (#1), Pi (#2, общий LLM-каталог pi-ai), Deep Agents (#4, зеркальный вердикт CLI/SDK), OpenCode (#5).
- Оформить comparison-отчёт `docs/research/coding-agents/deepseek-harness-comparison.md`, строку #22 в `docs/research/coding-agents-summary.md`, стадию `1l` эпика.

### Ожидаемый результат (Expected Result)
- Отчёт по 10 критериям + вердикт ✅/⚠️/❌ (X/10) с обоснованием и SDK-оценкой по прецеденту deepagents.
- Строка #22 в сводной таблице (22/22), обновлённое ранжирование и приложение, стадия `1l` в эпике.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мне нужно определить, подходит ли `deepseek-ai/deepseek-harness` как агент кодинга / кандидат в сабагенты к нашим ролям команды, я хочу оценить его по единой методологии из 10 критериев (системный промпт, роль, скиллы, AGENTS.md, `.agents/skills/`, JSON-режим/programmatic API, токены, free tier, провайдеры, лицензия), чтобы дать вердикт: подходит / частично подходит / не подходит — и сравнить с omp, Pi, Deep Agents, OpenCode.

### Goal (Цель по SMART)
Исследовать `deepseek-ai/deepseek-harness` (TypeScript, MIT, developer preview, npm 0.1.0-rc.6, snapshot-коммит 47f9438 от 2026-08-13) по 10 критериям. Создать отчёт в `docs/research/coding-agents/deepseek-harness-comparison.md`, добавить строку #22 в сводную таблицу `docs/research/coding-agents-summary.md`, reopen-стадию `1l` эпика. Вердикт: ✅ подходит / ⚠️ частично / ❌ не подходит (X/10).

## 2. Context and Scope (Контекст и Границы)
*   **Объект:** `deepseek-ai/deepseek-harness` (`dsh`) — агентный харнес «everything is a plugin» на vendored Cordis; поверхности: Web UI, headless CLI, JSON-RPC SDK (TS/Python), ACP. Субагент-провайдеры codex/claude-code/acp/dsh-sdk; LLM-адаптеры `dsh-llm-deepseek` (native) и `dsh-llm-pi-ai` (`@earendil-works/pi-ai` — каталог провайдеров как у Pi).
*   **Где делаем:** `docs/research/coding-agents/deepseek-harness-comparison.md`, `docs/research/coding-agents-summary.md`, `todo/done/EPIC-research-coding-agents-comparison.md`, agent-report в `docs/agents/reports/system-analyst/`.
*   **Текущее поведение:** в эпике 21 исследование; ближайшие аналоги — omp/Pi (pi-ai), Deep Agents (зеркальный CLI/SDK-вердикт), OpenCode.
*   **Границы (Out of Scope):** код интеграции, бенчмарки, глубокий code review TS-исходников; сам фреймворк Cordis отдельно не исследуется.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] **Критерии 1–10** по единой методологии эпика (CLI/SDK-поверхности продукта; программный API — контекст для К6).
- [x] Вердикт: ✅/⚠️/❌ (X/10) с обоснованием; SDK-оценка отдельной строкой по прецеденту deepagents (не входит в CLI-сумму).
- [x] Отчёт `docs/research/coding-agents/deepseek-harness-comparison.md`.
- [x] Строка #22 в `docs/research/coding-agents-summary.md` (22/22): рейтинг, критериальные таблицы, детальные отчёты, приложение.
- [x] Эпик: стадия `1l`, change history.

### 🟡 Should Have (Желательно)
- [x] Практические примеры запуска (Web UI, headless, Python SDK с `DSH_SYSTEM_PROMPT`).
- [x] Оценка уникальных возможностей: `complete: true`-сборка промпта, ранжированное discovery скиллов, continuable-субагенты, бриджи codex/claude-code.

### ⚫ Won't Have (Не будем делать)
- [ ] Код интеграции, бенчмарки.

## 4. Implementation Plan (План реализации)
*План заполнен исполнителем (Reverse Briefing).*
1. [x] Ветка `task/research-deepseek-harness` от актуального `main`.
2. [x] GitHub API: метаданные, snapshot SHA, npm/PyPI версии; tarball master для локального анализа.
3. [x] Изучить README, `apps/cli` (+reference), `docs/subsystems/{skills,system-prompt,subagent,token-meter}.md`, `docs/user/guide/{providers,python-sdk}.md`, `packages/context/agent-instructions`, `packages/subagent/subagent-{codex,claude-code}`, `packages/sdk`, `packages/acp`.
4. [x] Оценить 10 критериев; сверить прецеденты (deepagents для К6/SDK-оценки, Pi для pi-ai-каталога).
5. [x] Отчёт `deepseek-harness-comparison.md`.
6. [x] Строка #22 в сводной таблице: Часть 1 (рейтинг, ∑ 26 → #6), критериальные таблицы 1–10, детальные отчёты, приложение (18/22).
7. [x] Эпик: стадия `1l`, change history.
8. [x] Agent-report; `make md-links validate-todo validate-language validate-roles`.

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт создан, все 10 критериев оценены, вердикт с обоснованием.
- [x] Строка #22 в сводной таблице (22/22), ранжирование обновлено.
- [x] Эпик обновлён стадией `1l`.
- [x] Проверки документации пройдены (`make md-links`, `make validate-todo`; PHPUnit/Psalm не запускаются — изменения строго в docs/todo).

## 6. Verification (Самопроверка)
```bash
ls docs/research/coding-agents/deepseek-harness-comparison.md
grep -c "DeepSeek Harness" docs/research/coding-agents-summary.md
grep -n "1l" todo/done/EPIC-research-coding-agents-comparison.md
make md-links
make validate-todo
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Developer preview**: репозиторий создан 2026-08-13, релизов нет, задекларированы ломающие изменения — фиксировать snapshot-коммит и версии пакетов.
- Классификация «агент vs фреймворк»: харнес-природа очевидна, но продуктовые поверхности (Web UI/headless/SDK) — по прецеденту deepagents относим к coding-агентам (решение пользователя).
- Взрывной рост звёзд (116k за 3 дня) — отделять хайп от инженерной сути; вердикт строго по 10 критериям.
- Двухдневная давность данных — при review перепроверить статус релизов.

## 8. Sources (Источники)
- [x] [deepseek-ai/deepseek-harness — GitHub](https://github.com/deepseek-ai/deepseek-harness)
- [x] [apps/cli + CLI behavior reference](https://github.com/deepseek-ai/deepseek-harness/blob/master/apps/cli/README.md)
- [x] [docs/subsystems — skills, system-prompt, subagent, token-meter](https://github.com/deepseek-ai/deepseek-harness/tree/master/docs/subsystems)
- [x] [docs/user/guide — providers, python-sdk](https://github.com/deepseek-ai/deepseek-harness/tree/master/docs/user/guide)
- [x] [@deepseek-ai/dsh — npm](https://www.npmjs.com/package/@deepseek-ai/dsh), [deepseek-harness-sdk — PyPI](https://pypi.org/project/deepseek-harness-sdk/)

## 9. Comments (Комментарии)
Пользователь явно классифицировал объект как агента («это ресерч агента, не фреймворка») → `EPIC-research-coding-agents-comparison`, стадия `1l`, строка #22. Прецедент зеркального переноса — deepagents (строка 125 эпика frameworks, стадия 1k). Предварительный вердикт по документации: ⚠️ Частично (CLI; preview-статус и отсутствие JSONL stdout) / SDK ✅ — подтвердить по 10 критериям.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-16 | Аналитик (Шерлок) | Создание задачи по запросу пользователя (классификация: агент). Стадия `1l`, строка #22. |
| 2026-08-16 | Аналитик (Шерлок) | Исследование выполнено, PR #349 создан; задача переведена в `review`. |
| 2026-08-16 | Тимлид (Алекс) | PR #349 апрувнут владельцем; задача переведена в `done` перед слиянием. |
