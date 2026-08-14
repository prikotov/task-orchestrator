---
type: docs
created: 2026-08-03
value: V3
complexity: C2
priority: P2
depends_on:
epic: EPIC-research-coding-agents-comparison
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch: task/research-deepagents
pr: "https://github.com/prikotov/task-orchestrator/pull/335"
status: review
---

# TASK-research-deepagents: Deep Agents (langchain-ai/deepagents) — agent-харнес + Deep Agents Code CLI

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В проекте уже подключены CLI-агенты кодинга как сабагенты к ролям команды: Pi (`@earendil-works/pi-coding-agent`) и omp (`@oh-my-pi/pi-coding-agent`, текущий кандидат #1) — запускаются в JSON-режиме/programmatic, с системным промптом роли, скиллами (`SKILL.md`), AGENTS.md, BYO-ключами.
- `deepagents` (`langchain-ai/deepagents`, Python, MIT, ≈27.3k★) — opinionated agent-харнес на LangGraph + его CLI-продукт **Deep Agents Code** («a pre-built coding agent in your terminal, similar to Claude Code or Cursor», «Inspired by Claude Code», install `curl -LsSf https://langch.in/dcode | bash`). Бандлит: sub-agents (изолированный контекст), filesystem (pluggable backends), context management (summarize + offload на диск), persistent memory, human-in-the-loop (approve/edit/reject tool calls), skills-on-demand, tools/MCP; model-agnostic (любой LLM с tool calling — OpenAI/Anthropic/Google, open-weight, локальные via Ollama/vLLM/llama.cpp), BYO LLM.
- Нужно понять по единой методологии из 10 критериев (системный промпт, роль, скиллы, AGENTS.md, `.agents/skills/`, JSON-режим/programmatic API, токены, free tier, провайдеры, лицензия), подходит ли Deep Agents Code как CLI-агент кодинга / кандидат в сабагенты к нашим ролям — и как он соотносится с Claude Code, omp, Codex CLI, OpenCode.

### Варианты или путь решения (Solution Sketch)
- Изучить первичные источники `langchain-ai/deepagents`: README, docs.langchain.com overview, API reference (`create_deep_agent`), страницу Deep Agents Code, версию на PyPI.
- Оценить каждый из 10 критериев coding-agents-эпика (акцент на CLI-продукт Deep Agents Code; Python-харнес/programmatic API — как контекст и для критерия 6 «запуск как сабагент»).
- Сопоставить с ближайшими аналогами в сводке: Claude Code (по декларации «Inspired by Claude Code»), omp (#1, BYO LLM, MIT), Codex CLI, OpenCode, Pi.
- Оформить comparison-отчёт в `docs/research/coding-agents/deepagents-comparison.md`, строку #21 в `docs/research/coding-agents-summary.md`, reopen эпика стадией `1k`.

### Ожидаемый результат (Expected Result)
- Есть отдельный отчёт `docs/research/coding-agents/deepagents-comparison.md` (10 критериев), строка `deepagents` (#21) в `docs/research/coding-agents-summary.md`, эпик reopened стадией `1k`.
- Вердикт зафиксирован: ✅ подходит / ⚠️ частично / ❌ не подходит (X/10).

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мне нужно определить, подходит ли `langchain-ai/deepagents` (и его CLI-продукт Deep Agents Code) как CLI-агента кодинга / кандидата в сабагенты к нашим ролям команды, я хочу оценить его по единой методологии из 10 критериев (системный промпт, роль, скиллы, AGENTS.md, `.agents/skills/`, JSON-режим/programmatic API, токены, free tier, провайдеры, лицензия), чтобы дать вердикт: подходит / частично подходит / не подходит — и сравнить с Claude Code, omp, Codex CLI, OpenCode.

### Goal (Цель по SMART)
Исследовать `langchain-ai/deepagents` (Python, MIT, ≈27.3k★) — opinionated agent-харнес на LangGraph + его CLI-продукт **Deep Agents Code** («a pre-built coding agent in your terminal, similar to Claude Code or Cursor», «Inspired by Claude Code») — по 10 критериям. Создать отчёт в `docs/research/coding-agents/deepagents-comparison.md` со сводкой по каждому критерию. Вердикт: ✅ подходит / ⚠️ частично / ❌ не подходит (X/10). Добавить строку #21 в сводную таблицу `docs/research/coding-agents-summary.md`.

## 2. Context and Scope (Контекст и Границы)
*   **Объект:** `langchain-ai/deepagents` — Python-библиотека, batteries-included agent harness поверх LangGraph/LangChain `create_agent` (sub-agents, filesystem, context management, persistent memory, human-in-the-loop, skills-on-demand, tools/MCP). CLI-продукт **Deep Agents Code** — готовый coding-агент для терминала (install `curl -LsSf https://langch.in/dcode | bash`), BYO LLM. Лицензия MIT, ≈27.3k★/≈3.8k forks. JS/TS-порт: `langchain-ai/deepagentsjs`.
*   **Где делаем:** `docs/research/coding-agents/deepagents-comparison.md`, `docs/research/coding-agents-summary.md`, `todo/done/EPIC-research-coding-agents-comparison.md`, agent-report в `docs/agents/reports/system-analyst/`.
*   **Текущее поведение:** В эпике исследовано 20 CLI-агентов кодинга. Ближайшие аналоги: Claude Code (аналог по декларации «Inspired by Claude Code»), omp (BYO LLM, MIT), Codex CLI, OpenCode. Pi/omp — текущие сабагенты; deepagents — кандидат.
*   **Границы (Out of Scope):** написание кода интеграции, глубокий code review Python-исходников, бенчмарки. JS/TS-порт `deepagentsjs` упоминается, но не исследуется отдельно.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] **Критерии 1–10** — по единой методологии coding-agents-эпика:
  1. Системный промпт — замена/дополнение/передача файла роли (`system_prompt` в `create_deep_agent`, CLI Deep Agents Code).
  2. Промпт агента / роль — инъекция контекста роли через CLI/env/файл.
  3. Скиллы (skills) — встроенная поддержка skills-on-demand, подключение файлов/каталогов.
  4. AGENTS.md — автообнаружение/отключение/альтернативные форматы.
  5. Стандартная папка `.agents/skills/` — автосканирование/явное подключение.
  6. Запуск как сабагент — JSON-режим / programmatic API (`create_deep_agent`, sub-agents с изолированным контекстом), контроль таймаутов.
  7. Токены и стоимость — отслеживание потребления (LangSmith), расчёт стоимости.
  8. Free tier — наличие/лимиты (BYO LLM → сам инструмент бесплатный/Open Source).
  9. Провайдеры и модели — model-agnostic (любой LLM с tool calling: OpenAI/Anthropic/Google, open-weight Baseten/Fireworks, локальные Ollama/vLLM/llama.cpp), BYOK.
  10. Лицензия — MIT (open source).
- [x] Вердикт: ✅/⚠️/❌ (X/10) с обоснованием.
- [x] Оценить отличия: deepagents (Python-харнес/programmatic) vs Deep Agents Code (CLI coding-агент) — по каким критериям оцениваем CLI-продукт.

### 🟡 Should Have (Желательно)
- [x] Практические примеры запуска Deep Agents Code как CLI и `create_deep_agent(...)` как programmatic API.
- [x] Оценка уникальных возможностей: sub-agents (isolated context), context management (summarize + offload на диск), persistent memory, HITL (approve/edit/reject tool calls), MCP, filesystem (pluggable backends).

### ⚫ Won't Have (Не будем делать)
- [ ] Код интеграции, бенчмарки.

## 4. Implementation Plan (План реализации)
*План предзаполнен автором (Тимлид Алекс); исполнитель подтверждает понимание (Reverse Briefing).*
1. [x] Создать/переключиться на ветку `task/research-deepagents`, без переключения на `main`.
2. [x] Прочитать reference: `done/TASK-research-omp-coding-agent.todo.md`, `done/TASK-research-claude-code-agent.todo.md`, comparison-документы Claude Code/omp.
3. [x] Получить GitHub metadata и commit snapshot `langchain-ai/deepagents`; зафиксировать версию PyPI.
4. [x] Изучить README, docs.langchain.com overview, API reference (`create_deep_agent`), страницу Deep Agents Code.
5. [x] Оценить каждый из 10 критериев (акцент на Deep Agents Code как CLI-поверхности; programmatic API — как контекст).
6. [x] Создать отчёт `docs/research/coding-agents/deepagents-comparison.md`.
7. [x] Добавить строку #21 в `docs/research/coding-agents-summary.md` (→ 21/21), обновить ранжирование и рекомендации.
8. [x] Reopen'уть эпик: `reopened: <дата>`, стадия `1k`, change history.
9. [x] Сохранить agent-report; запустить `make md-links` и `make validate-todo`.

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт создан, все 10 критериев оценены.
- [x] Вердикт ✅/⚠️/❌ (X/10) с обоснованием.
- [x] Строка #21 добавлена в сводную таблицу (21/21).
- [x] Ранжирование и рекомендации обновлены с учётом deepagents.

## 6. Verification (Самопроверка)
```bash
ls docs/research/coding-agents/deepagents-comparison.md
grep "deepagents" docs/research/coding-agents-summary.md
grep -n "1k" todo/done/EPIC-research-coding-agents-comparison.md
make md-links
make validate-todo
```

## 7. Risks and Dependencies (Риски и зависимости)
- deepagents — прежде всего Python-библиотека/харнес; CLI-поверхность для оценки coding-агента — **Deep Agents Code**. Часть критериев (CLI system prompt, JSON-режим) могут отличаться между библиотекой и CLI-продуктом — оценивать CLI-продукт, библиотеку как контекст.
- Продукт активно развивается (≈27k★) — фиксировать commit snapshot + версию PyPI, указывать дату.
- «Inspired by Claude Code» — высокий риск «двойника» Claude Code; вердикт строго по 10 критериям, не по маркетингу.
- LangSmith-зависимость для tracing/eval/deploy — учитывать как вендор-локап (критерий 7).

## 8. Sources (Источники)
- [x] [langchain-ai/deepagents — GitHub](https://github.com/langchain-ai/deepagents)
- [x] [Deep Agents — Overview (docs.langchain.com)](https://docs.langchain.com/oss/python/deepagents/overview)
- [x] [Deep Agents — API reference (`create_deep_agent`)](https://reference.langchain.com/python/deepagents/)
- [x] [Deep Agents Code — документация CLI-продукта](https://docs.langchain.com/deepagents-code)
- [x] [deepagentsjs — GitHub (JS/TS-порт)](https://github.com/langchain-ai/deepagentsjs)

## 9. Comments (Комментарии)
deepagents — batteries-included agent harness на LangGraph (`create_agent` → `deepagents`: добавлены filesystem, sub-agents, context management, skills, HITL). CLI-продукт **Deep Agents Code** — готовый coding-агент для терминала, BYO LLM, «similar to Claude Code or Cursor», install через curl. По прямой аналогии с Claude Code (уже в этом эпике) классифицирован как CLI-агент кодинга. Перенесён из `EPIC-research-agent-frameworks-comparison` (зеркальный прецедент строки 125 этого эпика: OmO ушли отсюда в frameworks как «система оркестрации»; deepagents возвращён как «кодинг-агент»). Предварительный verdict: ✅ Подходит (MIT, BYO LLM/model-agnostic, skills/MCP, HITL, sub-agents, programmatic API) — подтвердить по 10 критериям.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-03 | Тимлид (Алекс) | Создание задачи. Источник: пользователь указал репозиторий `langchain-ai/deepagents`. Классифицирован как CLI-агент кодинга (по аналогии с Claude Code, «Inspired by Claude Code») → `EPIC-research-coding-agents-comparison`, стадия `1k` (строка #21). Перенесён из `EPIC-research-agent-frameworks-comparison` по решению пользователя. Предварительный verdict: ✅ Подходит (подтвердить по 10 критериям). |
| 2026-08-14 | Аналитик (Шерлок) | Исследование выполнено и подготовлено к review: создан отчёт `docs/research/coding-agents/deepagents-comparison.md` по 10 критериям, сводная таблица обновлена до 21 исследования, строка #21 добавлена в детальные отчёты, эпик `EPIC-research-coding-agents-comparison` обновлён стадией `1k`. Self-review уточнил snapshot-ссылки на commit и счётчик Agent Skills standard (17/21 → 81%). |
