---
type: docs
created: 2026-09-23 02:37:48 (1790131068)
due: 
started: 2026-09-23 02:40:36 (1790131236)
completed: 2026-09-23 03:30:50 (1790134250)
cancelled: 
value: V3
complexity: C3
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид Алекс (pi)
assignee: Аналитик Шерлок (pi)
branch: task/research-strands-harness-sdk
pr: https://github.com/prikotov/task-orchestrator/pull/407
status: done
---

# TASK-research-strands-harness-sdk: Research: Strands Agents harness-sdk (AWS) — SDK для построения agent-харнесов

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Наш движок оркестрации AI-агентов развивается без систематического сравнения с AWS Strands Agents — крупным open-source SDK (Apache-2.0, ≈7.6k★, Amazon.com) для построения и запуска AI-агентов в Python и TypeScript.
- Strands решает ту же задачу, что и наш AgentRunner/ChainExecution, но с другой стороны: «runs in your process with no hosted control plane» — SDK-уровень, а не декларативные YAML-цепочки. Ключевые механики не исследованы с позиций нашей архитектуры: lifecycle-контроли agent loop (лимиты ходов, токен-бюджеты, отмена, stop reasons), hooks/interventions (перехват любой итерации), guardrails, sessions/memory/context management, multi-agent паттерны (graph/swarm/a2a), evals SDK (детекторы, оценщики, red-teaming, симуляторы), двухуровневая модель «harness с дефолтами + SDK с полным контролем».
- Неизвестно, что из этого применимо к нашим AgentRunner, ChainExecution, DynamicLoop и quality gates.

### Варианты или путь решения (Solution Sketch)
- Research-задача по методологии эпика: comparison-отчёт `docs/research/framework-comparisons/strands-harness-sdk-comparison.md` с маппингом каждой механики на компоненты task-orchestrator и вердиктом заимствовать / dependency / не подходит.
- Объект исследования — открытый репозиторий и документация (280+ страниц docs-сайта); предварительный ресерч уже выполнен тимлидом (клон в `/tmp/research/strands-harness-sdk`, снапшот main `fec0427` от 2026-09-22) — исполнитель проверяет выводы по первоисточникам, а не копирует их.

### Ожидаемый результат (Expected Result)
- Comparison-документ `docs/research/framework-comparisons/strands-harness-sdk-comparison.md`.
- Строка #39 в сводной таблице `docs/research/agent-frameworks-summary.md`, актуализация счётчиков (39/39) и затронутых сравнительных выводов.
- Чёткий вердикт: какие паттерны заимствовать (кандидаты: lifecycle-контроли, hooks/interventions, guardrails, harness-defaults, evals), что изучить дополнительно, что неприменимо.
- Обновлённый эпик: стадия 1q, история изменений.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story / Job Story)
> **Job Story:** Когда мы развиваем систему оркестрации AI-агентов (AgentRunner, ChainExecution, DynamicLoop, GitIdentity), я хочу исследовать Strands Agents harness-sdk — SDK-подход AWS к построению agent-харнесов с lifecycle-контролями, hooks и evals, — чтобы выявить применимые паттерны управления agent loop, наблюдаемости и качества, и не изобретать колёса.

### Цель по SMART (Goal)
Исследовать репозиторий `strands-agents/harness-sdk` (снапшот main `fec042766488cfb2627f1998897b1cc0fc238d3f` от 2026-09-22) по методологии эпика: монорепозиторий `harness-py`/`harness-ts` (собранный харнес), `strands-py`/`strands-ts` (SDK), `strands-cli`, docs-сайт (`site/src/content/docs/`). Создать comparison-отчёт в `docs/research/framework-comparisons/strands-harness-sdk-comparison.md` с маппингом ключевых механик на компоненты task-orchestrator, вердиктом по каждой и сравнением с аналогами трека (OpenHands SDK #6, Mastra #10, Agno #14, omnigent #33). Добавить строку в сводную таблицу `docs/research/agent-frameworks-summary.md`. Срок — в рамках evergreen-режима эпика.

## 2. Контекст и Границы (Context and Scope)

- **Объект:** `strands-agents/harness-sdk` (Apache-2.0, Copyright Amazon.com; Python 3.10+ и TypeScript; ≈7.6k★, ≈1.2k fork'ов; создан 2025-05-14; снапшот main `fec0427` от 2026-09-22; теги: `python/v1.57.0`, `typescript/v1.19.0`, `harness-python/v0.1.2`, `harness-typescript/v0.1.1`). Монорепозиторий: `harness-py`/`harness-ts` — «fully assembled agent» через `create_harness()`/`createHarness()` с benchmarked defaults (модель, инструменты, память, сессии, context management — всё переопределяемо); `strands-py`/`strands-ts` — SDK agent loop с ручным контролем (tools, model providers, memory, sessions, hooks); `strands-cli` — `strands` CLI для прототипирования/чата (npm `@strands-agents/cli`); `strands-mcp` — MCP-сервер; `site/` — docs (280 страниц); `team/` — governance (tenets, decisions, PR/compatibility guidelines). Заявленные возможности: lifecycle controls (turn limits, token budgets, cancellation, stop reasons), tools + structured output, MCP, multi-agent patterns, memory + sessions, model portability (Bedrock/Anthropic/OpenAI/Gemini и др.), streaming, guardrails, tracing, evals. Python SDK (`strands-py/src/strands/`): каталоги `agent`, `event_loop`, `handlers`, `hooks`, `multiagent` (graph/swarm/a2a), `interventions`, `memory`, `session`, `storage`, `tools`, `sandbox`, `telemetry`, `experimental`, `injection`, `background_tasks`, `plugins`.
- **Классификация (предварительная, проверить):** `SDK / agent-framework` (SDK для построения agent-харнесов) — не CLI-агент кодинга (CLI — тонкая поверхность прототипирования), не мета-оркестратор над внешними агентами. Ближайшие аналоги трека: OpenHands SDK (#6, «SDK-подход»), Mastra AI (#10, TS SDK), Agno (#14, Python SDK).
- **Где делаем:** `docs/research/framework-comparisons/strands-harness-sdk-comparison.md`, `docs/research/agent-frameworks-summary.md`, эпик.
- **Текущее поведение:** наш AgentRunner абстрагирует однократный запуск раннеров (pi, codex) с JSONL-парсингом и итогом `AgentResultVo`; ChainExecution исполняет декларативные YAML-цепочки с retry/backoff, circuit breaker, quality gates, бюджетом, `fix_iterations`; DynamicLoop — итеративные раунды с resume; hooks/guardrails/evals у нас отсутствуют.
- **Границы (Out of Scope):** написание кода/конфигов, изменение архитектуры, бенчмарки, установка и запуск strands (описываем по коду и документации), аудит безопасности исходников, перенос Python/TS-кода в PHP.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [x] Зафиксировать дату исследования и воспроизводимый идентификатор версии: снапшот main `fec042766488cfb2627f1998897b1cc0fc238d3f` от 2026-09-22 (клон в `/tmp/research/strands-harness-sdk`) с указанием релизных тегов (`python/v1.57.0`, `harness-python/v0.1.2` и др.).
- [x] Проверить по первичным источникам классификацию как SDK/agent-framework: двухуровневая модель «harness с дефолтами (create_harness) + SDK с полным контролем цикла»; отделить SDK от тонкой CLI-поверхности.
- [x] Описать lifecycle-контроли agent loop (turn limits, token budgets, cancellation, stop reasons) по коду `strands-py/src/strands/agent/`, `event_loop/`; сравнить с нашим retry/budget/fix_iterations.
- [x] Описать hooks/interventions (перехват итерации: log/validate/redirect, steering) и guardrails (валидация tool-вызовов до исполнения); сопоставить с нашими quality gates и отсутствием валидации команд.
- [x] Описать state management: sessions, memory, context management (compaction); сопоставить с нашим DynamicLoop resume и payload-контекстом.
- [x] Описать multi-agent паттерны (`multiagent/graph.py`, `swarm.py`, `a2a/`); сопоставить с нашими цепочками и сабагентами.
- [x] Кратко охарактеризовать evals SDK (detectors/evaluators/red-teaming/simulators из docs `user-guide/evals-sdk/`) как отдельный класс контроля качества; модель portability (Bedrock/Anthropic/OpenAI/Gemini, custom providers); observability (tracing/streaming).
- [x] Сравнить Strands с `task-orchestrator` по единой матрице трека (модель оркестрации, state mgmt, error handling, extensibility) и с аналогами: OpenHands SDK (#6), Mastra (#10), Agno (#14), omnigent (#33).
- [x] Проверить гипотезы тимлида (секция «Комментарии») — подтверждены/опровергнуты, явно.
- [x] Создать `docs/research/framework-comparisons/strands-harness-sdk-comparison.md` по формату существующих comparison-документов, со ссылками на первичные источники, ограничениями и чётким вердиктом: заимствовать паттерны / использовать как зависимость / не подходит.
- [x] Обновить `docs/research/agent-frameworks-summary.md`: строка #39, счётчики (39/39), затронутые сравнительные выводы и тренды.
- [x] Обновить эпик: стадия 1q в плане, запись в истории изменений.

### 🟡 Желательно (Should Have)
- [x] Выделить 3–7 потенциально переносимых паттернов с приоритетом, ожидаемой пользой, ограничениями и необходимой последующей проверкой.
- [x] Архитектурную диаграмму (harness → SDK-слои; agent loop с hooks/guardrails) в Mermaid, если улучшает проверяемость.
- [x] Оценить формат «harness configuration reference» (каждый дефолт переопределяем и документирован) как паттерн для наших YAML-профилей и конфигурации цепочек.
- [x] Зафиксировать пробелы документации как неизвестные данные, не подменяя их предположениями.

### ⚫ Не будем делать (Won't Have)
- [x] Не считать маркетинговые заявления («production-ready», benchmarks) подтверждёнными фактами — только как заявления авторов.
- [x] Не проводить полный аудит безопасности исходного кода или бенчмарки производительности.
- [x] Не интегрировать strands и не добавлять его как зависимость; не менять код, конфигурацию и архитектуру `task-orchestrator`.
- [x] Не исследовать заново весь docs-сайт (280 страниц) — только разделы, релевантные методологии трека.
- [x] Не создавать задачи реализации без отдельного решения пользователя.

## 4. План реализации (Implementation Plan)
1. [x] Изучить снапшот `/tmp/research/strands-harness-sdk` (main `fec0427` от 2026-09-22): `README.md`, `harness-py/src/strands_harness/`, `strands-py/src/strands/` (agent, event_loop, hooks, interventions, multiagent, session, memory), `strands-cli/`, docs `site/src/content/docs/user-guide/` (harness/, sdk/, evals-sdk/), `team/` (обзорно).
2. [x] Актуализировать знание нашей архитектуры: `src/Module/AgentRunner/`, `src/Module/ChainExecution/`, `src/Module/DynamicLoop/`, `docs/guide/observability.md`.
3. [x] Сопоставить по единой матрице трека; сравнить с OpenHands SDK (#6), Mastra (#10), Agno (#14), omnigent (#33).
4. [x] Проверить гипотезы тимлида (секция «Комментарии»).
5. [x] Создать `docs/research/framework-comparisons/strands-harness-sdk-comparison.md`.
6. [x] Обновить `docs/research/agent-frameworks-summary.md` (строка #39, счётчики, тренды).
7. [x] Обновить эпик (стадия 1q, история изменений); выполнить `php vendor/bin/todo-md validate`.

## 5. Критерии приёмки (Definition of Done)
- [x] Comparison-документ создан по формату трека; все ключевые механики описаны с маппингом на компоненты task-orchestrator и ссылками на первичные источники.
- [x] Вердикт по каждой механике: заимствовать / dependency / не подходит — с обоснованием.
- [x] Гипотезы тимлида проверены явно (подтверждены/опровергнуты).
- [x] Сравнение с аналогами трека (OpenHands SDK, Mastra, Agno, omnigent) выполнено.
- [x] Строка #39 добавлена в `docs/research/agent-frameworks-summary.md`, счётчики и затронутые тренды актуализированы.
- [x] Эпик обновлён (стадия 1q, история изменений).
- [x] `php vendor/bin/todo-md validate` для затронутых файлов — без ошибок.

## 6. Самопроверка (Verification)
```bash
ls docs/research/framework-comparisons/strands-harness-sdk-comparison.md
grep -n "strands" docs/research/agent-frameworks-summary.md
php vendor/bin/todo-md validate
```

## 7. Риски и зависимости (Risks and Dependencies)
- Продукт активно развивается (коммиты ежедневно; harness-пакеты v0.1.x — ранняя стадия при зрелом SDK v1.57) — зафиксирован снапшот `fec0427` от 2026-09-22; выводы привязывать к снапшоту.
- Двуязычность (Python + TypeScript) удваивает объём — приоритизировать Python-ветку как первичную, TypeScript упоминать по расхождениям.
- Документация обширна (280 страниц) — риск утонуть; ограничиться разделами, релевантными матрице трека.
- Монорепозиторий объединяет несколько продуктов (harness, SDK, CLI, MCP, docs) — в отчёте чётко разделять артефакты, чтобы не смешивать выводы.

## 8. Источники (Sources)

📚 **Внешние источники** (до 5, кратким списком):

- [github.com/strands-agents/harness-sdk](https://github.com/strands-agents/harness-sdk) — монорепозиторий (README, harness-py/, strands-py/, strands-cli/)
- [strandsagents.com/docs](https://strandsagents.com/docs/user-guide/concepts/agents/agent-loop/) — документация (agent loop, lifecycle, multi-agent, harness)
- [Harness quickstart](https://strandsagents.com/docs/user-guide/harness/quickstart/) — create_harness и переопределяемые дефолты
- [PyPI strands-agents](https://pypi.org/project/strands-agents/) / [npm @strands-agents/sdk](https://www.npmjs.com/package/@strands-agents/sdk) — версии и релизный цикл
- [team/ governance](https://github.com/strands-agents/harness-sdk/tree/main/team) — tenets, compatibility, decisions

🔗 **Внутренние источники:**

- `docs/guide/architecture.md` — архитектура проекта
- `src/Module/AgentRunner/` — модуль запуска AI-агентов (JSONL-парсинг)
- `src/Module/ChainExecution/` — модуль исполнения цепочек (повторы, прерывание)
- `src/Module/DynamicLoop/` — динамические циклы (итеративные раунды, resume)
- `docs/research/agent-frameworks-summary.md` — сводная таблица трека (аналоги: OpenHands SDK #6, Mastra #10, Agno #14, omnigent #33)

## 9. Комментарии (Комментарии)

**Резюме объекта исследования (для контекста исполнителя, НЕ заменяет самостоятельного изучения источников):**

Strands Agents — SDK-фреймворк от AWS (Copyright Amazon.com): «Choose Strands when you would otherwise write your own agent loop». Работает в процессе пользователя, без hosted control plane (управляющего облачного слоя). Два уровня: (1) harness — собранный агент с benchmarked defaults через один вызов `create_harness()`; (2) SDK — полный контроль agent loop, tools, providers, memory. Это зеркальный нашему подход: у нас декларативные YAML-цепочки поверх CLI-раннеров (pi/codex), у них — программный SDK-цикл, который разработчик встраивает в своё приложение. Наш движок сам является оркестратором, поэтому интерес представляет не интеграция, а паттерны цикла, контроля и качества.

**Гипотезы тимлида к проверке:**

1. **Lifecycle-контроли agent loop:** turn limits, token budgets, cancellation, stop reasons — формализованный контракт завершения цикла. У нас есть max_iterations и бюджет, но нет единой номенклатуры stop reasons. Вопрос: стоит ли ввести явный перечень причин завершения шага (success/limit/budget/cancelled/error) в `AgentResultVo`?
2. **Hooks/interventions:** перехват любой итерации цикла (log/validate/redirect) + steering handlers («агенты корректируют себя вместо тихого падения»). Сравнить с нашими quality gates: у нас проверка ПОСЛЕ шага (shell), у них — ВО ВРЕМЯ итерации. Применимо ли к ChainExecution как пре-шаговая валидация?
3. **Guardrails:** валидация tool-вызовов до исполнения. У нас shell-команды не валидируются вообще. Кандидат на read-only/whitelist-политику для аналитических ролей.
4. **Harness-defaults паттерн:** «single create_harness() call with benchmarked defaults, every default documented and overridable». Сравнить с нашими YAML-профилями: нет ли у нас недокументированных неявных дефолтов?
5. **Multi-agent (graph/swarm/a2a):** graph.py/swarm.py/a2a (Agent2Agent-протокол). A2a — стандарт межагентного взаимодействия; оценить, нужен ли нам когда-либо (сейчас цепочки внутри одного движка).
6. **Evals SDK:** detectors/evaluators/red-teaming/simulators — систематический контроль качества агентов. У нас quality gates — только shell-проверки. Отдельный класс; оценить как направление R&D.
7. **Error handling:** как SDK классифицирует ошибки (retryable/fatal?), есть ли retry/backoff на уровне model-вызовов, circuit breaker. Сравнить с нашим RetryingAgentRunner.

**Связь с треком:** ближайшие аналоги — OpenHands SDK (#6, тоже «SDK-подход», Python), Mastra AI (#10, TS SDK), Agno (#14, Python SDK с FallbackConfig). Отличие Strands: происхождение AWS (Bedrock first-class), зрелость релизов (SDK v1.57 при harness v0.1.x), двуязычность. Проверить, как зрелость сказывается на стабильности контрактов (`team/COMPATIBILITY.md`).

**Предварительный вердикт тимлида (уточнить по итогам):** 🟡 заимствовать паттерны (stop reasons, hooks/interventions, guardrails, harness-defaults, evals-подход) / 🔴 не dependency (Python/TS SDK не встраивается в PHP-движок; наш движок сам — оркестратор, интеграция означала бы смену архитектуры).

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-23 02:37:48 (1790131068) | Тимлид (Алекс) | Создание задачи |
| 2026-09-23 | Тимлид (Алекс) | Заполнена постановка по итогам предварительного ресерча (снапшот main `fec0427` от 2026-09-22): методология эпика, 7 гипотез, предварительный вердикт. |
| 2026-09-23 | Аналитик (Шерлок) | Подготовлены comparison-отчёт, строка #39 и обновление сводки до 39/39; семь гипотез проверены по первичным источникам, эпик дополнен стадией `1q`. Статус сохранён `in_progress` до процессной приёмки. |
