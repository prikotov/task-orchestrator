# Исследование ZCode (Z.AI / Zhipu) как кодового агента и ответ на вопрос о втором эпике

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-06-17
**Объект:** ZCode v3.1.1 (Z.AI / Zhipu AI) — desktop GUI-агент; разделы документации `agents`, `subagents`, `skill`, `mcp-services`, `usage-stats`, `remote-control`, `configuration`, `goal`, `commands`, `install`, Terms of Use
**Задача:** [TASK-research-zcode-coding-agent](../../../../todo/done/TASK-research-zcode-coding-agent.todo.md), эпик [EPIC-research-coding-agents-comparison](../../../../todo/done/EPIC-research-coding-agents-comparison.md)

---

## Повод запуска

Пользователь спросил: у нас есть кодовый агент Z.AI с субагентами (https://zcode.z.ai/en/docs/agents, https://zcode.z.ai/en/docs/subagents) — стоит ли делать research в **обоих** эпиках (`EPIC-research-agent-frameworks-comparison` + `EPIC-research-coding-agents-comparison`) или достаточно одного. После анализа ответ подтверждён и research выполнен в coding-agents.

## Ответ на главный вопрос

**Достаточно одного исследования — в `EPIC-research-coding-agents-comparison`.** Во второй эпик (`agent-frameworks`) ZCode **не добавляется**.

Обоснование:
1. **Субагенты ZCode — часть продукта «кодовый агент», а не отдельная система оркестрации.** Раздел `/subagents` описывает единственный встроенный read-only помощник **Explore** (поиск/исследование кода) + roadmap кастомных. Модели оркестрации, state management, workflow-engine — отсутствуют. Это не предмет frameworks-эпика.
2. **Методология coding-agents автоматически покрывает субагенты** (критерий №6 «запуск как сабагент» + extensibility).
3. **Прямой прецедент в репо:** задача `oh-my-openagent` была перенесена *из* coding-agents *в* frameworks как «система оркестрации» — ZCode строго обратный случай (кодинг-агент). Claude Code / Codex (тоже имеют свои сабагенты) уже исследуются как один продукт в одном эпике.

## Итог исследования по 10 критериям

**Вердикт: ❌ Не подходит (Score 17/30; пригодность 4/10).**

| Критерий | Оценка | Балл | Краткое обоснование |
|----------|--------|------|---------------------|
| К1 Системный промпт | ❌ | 1 | Нет механизмов замены/дополнения (нет CLI вообще) |
| К2 Роль | ❌ | 1 | Нет ролей, нет изоляции |
| К3 Скиллы | ⚠️ | 2 | Agent Skills (SKILL.md) + импорт из внешних агентов, но GUI only, global |
| К4 AGENTS.md | ✅ | 3 | AGENTS.md (High) + CLAUDE.md (Low), поиск вверх по дереву |
| К5 `.agents/skills/` | ❌ | 1 | Не автосканируется (только `~/.zcode/skills/`); `.agents/` — только для MCP |
| К6 JSON-режим | ❌ | 1 | **Блокер**: нет headless CLI / JSON / API; Remote Control = телефон, Bot Channel = WeChat/Feishu |
| К7 Токены/стоимость | ⚠️ | 2 | Богатая телеметрия (per-model tokens, квоты), но только в GUI |
| К8 Free tier | ⚠️ | 2 | Free daily trial quota + платные Coding Plan Lite/Pro/Max; юрисдикция КНР |
| К9 Провайдеры | ✅ | 3 | Z.ai/BigModel + named BYOK + любой Anthropic/OpenAI-compatible + OpenRouter |
| К10 Лицензия | ❌ | 1 | Проприетарная; reverse eng. и конкурирующие продукты запрещены; юрисдикция КНР |

**Блокер — К6:** ZCode — интерактивное desktop GUI-приложение без headless/JSON/programmatic-интерфейса. Интеграция через `watch-subagent.sh` невозможна в принципе.

## Ключевые находки (даже при вердикте «не подходит»)

- **Goal Mode** (`/goal`) — встроенный **self-verifying iterative loop**: агент сам проверяет «достигнута ли цель» в конце каждой итерации и циклится, пока верификация не пройдёт. Прямой аналог нашего `dynamic-loop` + `quality-gate`. **Ценная идея для Orchestrator** — loop-логика, мигрирующая внутрь агента.
- **5 Execution Modes** (Default / Confirm Before Changes / Auto Edit / Plan / Full Access) — референс градации автономии/подтверждений.
- **AGENTS.md(High)+CLAUDE.md(Low)** — явный приоритет контекстных файлов.
- **Импорт skills/MCP из внешних агентов** (Claude Code, Codex CLI, OpenClaw, Augment, Windsurf) + стандарт `.agents/` для MCP (`~/.agents/mcp.json`) — паттерн совместимости/миграции.
- **Комплаенс-риск:** проприетарная лицензия (Terms of Use, юрисдикция Haidian District, Beijing) — даже pattern-inspiration только по публичной документации, без декомпиляции.

## Допущения

- Анализ ведётся **только по официальной документации** (исходный код закрыт, reverse engineering запрещён Terms of Use).
- Страницы pricing — SPA (JS-рендеринг); конкретные цифры Coding Plan Lite/Pro/Max не извлечены статически, опирались на качественное описание.
- Локальные модели (Ollama/LM Studio) явно не задокументированы, оценка по К9 (✅) основана на поддержке произвольного OpenAI-compatible endpoint.
- Информация актуальна на v3.1.1 (2026-06-17); продукт развивается.

## Артефакты (всё в ветке `task/research-zcode-coding-agent`, незакоммичено)

1. `todo/done/TASK-research-zcode-coding-agent.todo.md` — задача (status: done).
2. `docs/research/coding-agents/zcode-coding-agent-comparison.md` — основной отчёт (10 критериев + вердикт + Mermaid).
3. `docs/research/coding-agents-summary.md` — добавлены: строка рейтинга (#16), детальная строка в разделе 2.2 (проприетарные → 4), пункт тренда 5.3 (Goal Mode), строка #17 в «Детальные отчёты», строка в приложении «Проприетарные» (→ 4).
4. `todo/done/EPIC-research-coding-agents-comparison.md` — Stage 1h + Change History.

## Рекомендации / следующие шаги

- Для сабагентной интеграции ZCode **не кандидат** (Tier 0) — остаётся паттерн-референс.
- При развитии нашего `dynamic-loop`/`quality-gate` учесть идею Goal Mode (self-verification).
- Дождаться review → PR → merge от пользователя (merge только по явному подтверждению).
