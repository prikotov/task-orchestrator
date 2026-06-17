---
# Metadata (Метаданные)
type: research
created: 2026-06-17
value: V3
complexity: C2
priority: P2
depends_on:
epic: EPIC-research-coding-agents-comparison
author: Аналитик (Шерлок)
assignee: Аналитик (Шерлок)
branch: task/research-zcode-coding-agent
pr: "https://github.com/prikotov/task-orchestrator/pull/269"
status: done
---

# TASK-research-zcode-coding-agent: ZCode (Z.AI / Zhipu)

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мне нужно оценить AI-агента кодинга как кандидата в сабагенты для ролей команды, я хочу знать его возможности по кастомизации (системный промпт, роль, скиллы, AGENTS.md, headless/JSON-режим), чтобы определить, подходит ли он для работы с нашей системой ролей и скиллов. Дополнительно — закрыть вопрос, требует ли Z.AI/ZCode отдельного исследования в `EPIC-research-agent-frameworks-comparison` (субагенты ZCode как возможная система оркестрации).

### Goal (Цель по SMART)
Исследовать ZCode (Z.AI / Zhipu AI, desktop-агент, GLM-5.2) по 10 критериям единой методологии `EPIC-research-coding-agents-comparison`. Создать отчёт в `docs/research/coding-agents/zcode-coding-agent-comparison.md` со сводкой по каждому критерию и вердиктом: подходит / частично подходит / не подходит. Зафиксировать находку по `subagents` (встроенный read-only Explore + отсутствие кастомных) внутри отчёта. Обосновать, что во второй эпик (`agent-frameworks`) исследование не требуется.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/research/coding-agents/zcode-coding-agent-comparison.md`, обновление `docs/research/coding-agents-summary.md`, эпика `EPIC-research-coding-agents-comparison`.
*   **Предпосылка (Reverse Briefing):** По итогам анализа установлено, что ZCode — это **кодовый агент** (desktop GUI), а не система оркестрации. Раздел `/subagents` описывает feature всё того же ZCode (встроенный Explore + roadmap кастомных), а не отдельный продукт. Прецедент в репо подтверждает отнесение: `oh-my-openagent` был перенесён *из* coding-agents *в* frameworks как «система оркестрации» — ZCode строго обратный случай.
*   **Границы (Out of Scope):** написание кода интеграции, глубокий code review исходников (ZCode проприетарный, исходников нет), бенчмарки производительности, повторное исследование как оркестратора (не требуется).

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] **Критерий 1. Системный промпт:** Полная замена / дополнение / передача файла роли. Механизмы (CLI-аргумент, конфигурационный файл, env). Сравнение с pi (`--system-prompt`, `--append-system-prompt`) и codex (`model_instructions_file`).
- [x] **Критерий 2. Промпт агента / Роль:** Инъекция контекста роли (файл роли) через CLI-аргумент, env, файл конфигурации. Изоляция ролей.
- [x] **Критерий 3. Скиллы:** Встроенная поддержка Agent Skills standard. Подключение файлов/каталогов скиллов. Импорт из внешних агентов. Per-role назначение.
- [x] **Критерий 4. AGENTS.md:** Автообнаружение `AGENTS.md` / `CLAUDE.md`. Приоритеты. Отключение. Альтернативные форматы инструкций проекта.
- [x] **Критерий 5. Стандартная папка `.agents/skills/`:** Поддержка `.agents/skills/`. Автосканирование из коробки или явное подключение. Стандарт `.agents/` для MCP.
- [x] **Критерий 6. Запуск как сабагент:** Headless CLI / JSON-режим / programmatic API / pipe-управление. Remote Control, Bot Channel. Контроль таймаутов. Ephemeral / non-interactive режим. Встроенный Explore subagent и поддержка кастомных сабагентов.
- [x] **Критерий 7. Токены и стоимость:** Отслеживание потребления токенов (input/output). Breakdown по моделям. Расчёт стоимости. Способ доступа (GUI/JSON/CLI).
- [x] **Критерий 8. Free tier:** Наличие бесплатного тарифа (free trial quota). Coding Plans (Lite/Pro/Max). Лимиты. BYOK.
- [x] **Критерий 9. Провайдеры и модели:** Список поддерживаемых провайдеров (Z.ai, BigModel, Anthropic, OpenAI, OpenRouter, ...). BYOK. Custom Anthropic/OpenAI-compatible endpoints. Локальные модели. Переключение.
- [x] **Критерий 10. Лицензия:** Open source / проприетарный. Тип лицензии. Ограничения использования (reverse engineering, конкурирующие продукты, юрисдикция).
- [x] Вердикт: подходит / частично подходит / не подходит — с обоснованием
- [x] Пояснение: почему отдельного исследования в `EPIC-research-agent-frameworks-comparison` не требуется

### 🟡 Should Have (Желательно)
- [x] Сравнение с pi и codex по ключевым критериям
- [x] Выделение паттернов, интересных для нашего Orchestrator (Goal Mode, Execution Modes), даже при вердикте «не подходит»

### 🟢 Could Have (Опционально)
- [x] Mermaid-диаграмма рассуждения «почему не оркестратор»

### ⚫ Won't Have (Не будем делать)
- [ ] Код интеграции
- [ ] Бенчмарки производительности
- [ ] Исследование в `EPIC-research-agent-frameworks-comparison` (обоснованно исключено)

## 4. Implementation Plan (План реализации)
1. [x] Изучить официальную документацию Z.AI (https://zcode.z.ai/en/docs/*): agents, subagents, install, skill, mcp-services, usage-stats, remote-control, configuration (API Key Setup), goal, commands, Terms of Use.
2. [x] Оценить каждый из 10 критериев с примерами и ссылками на первоисточник.
3. [x] Зафиксировать находку по `subagents` (Explore + отсутствие кастомных) и обосновать принадлежность только к coding-agents эпику.
4. [x] Создать отчёт в `docs/research/coding-agents/zcode-coding-agent-comparison.md`.
5. [x] Добавить строку агента в `docs/research/coding-agents-summary.md` (рейтинг + детальные таблицы + список отчётов).
6. [x] Обновить план эпика `EPIC-research-coding-agents-comparison` (Stage 1h) и историю изменений.

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт создан в `docs/research/coding-agents/zcode-coding-agent-comparison.md`
- [x] Каждый из 10 критериев оценён с примерами / ссылками на документацию
- [x] Вердикт: подходит / частично подходит / не подходит — с обоснованием
- [x] Строка агента добавлена в `docs/research/coding-agents-summary.md`
- [x] Эпик обновлён: добавлен Stage 1h, задача отмечена, история изменений пополнена
- [x] Пояснение по субагентам и принадлежности к одному эпику включено в отчёт

## 6. Verification (Самопроверка)
```bash
ls docs/research/coding-agents/zcode-coding-agent-comparison.md
grep -i "ZCode" docs/research/coding-agents-summary.md
grep -i "zcode-coding-agent" todo/done/EPIC-research-coding-agents-comparison.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- ZCode — проприетарный продукт: анализ только по официальной документации (исходный код недоступен, reverse engineering запрещён Terms of Use).
- ZCode активно развивается (v3.1.1 на дату исследования) — информация может устареть.
- ZCode — desktop GUI-приложение, а не headless CLI: это сразу дисквалифицирует его как кандидата в сабагенты (К6), но делает объектом изучения паттернов.
- Юрисдикция Terms of Use — КНР (Haidian District, Beijing): потенциальный комплаенс-риск для коммерческого использования вне Китая.
- Страницы pricing — SPA (JS-рендеринг), конкретные цифры тарифов не извлечены статически; опираемся на качественное описание из configuration/docs.

## 8. Sources (Источники)
- [ZCode Agent — docs](https://zcode.z.ai/en/docs/agents)
- [Subagents — docs](https://zcode.z.ai/en/docs/subagents)
- [Skill — docs](https://zcode.z.ai/en/docs/skill)
- [MCP Servers — docs](https://zcode.z.ai/en/docs/mcp-services)
- [Usage Stats — docs](https://zcode.z.ai/en/docs/usage-stats)
- [Remote Control — docs](https://zcode.z.ai/en/docs/remote-control)
- [API Key Setup (configuration) — docs](https://zcode.z.ai/en/docs/configuration)
- [Goal Mode — docs](https://zcode.z.ai/en/docs/goal)
- [Commands — docs](https://zcode.z.ai/en/docs/commands)
- [Install — docs](https://zcode.z.ai/en/docs/install)
- [ZCode Terms of Use](https://zcode.z.ai/en/terms)

## 9. Comments (Комментарии)
Задача изначально возникла из вопроса пользователя: «у нас есть кодовый агент Z.AI с субагентами — стоит ли делать research в обоих эпиках (frameworks + coding-agents) или достаточно одного». По итогам анализа установлено: ZCode — кодовый агент, его `subagents` — это feature продукта (read-only Explore + roadmap), а не отдельная система оркестрации. Прецедент в репо (`oh-my-openagent`) и аналогия с Claude Code/Codex (тоже имеют сабагентов, но исследуются как один продукт в одном эпике) подтверждают: достаточно одного исследования в `EPIC-research-coding-agents-comparison`. Во второй эпик (`agent-frameworks`) ZCode не добавляется.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-17 | Аналитик (Шерлок) | Создание задачи и выполнение исследования (отчёт, обновление summary и эпика). |
