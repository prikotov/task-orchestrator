---
type: epic
created: 2026-04-29
value: V3
complexity: C4
priority: P1
author: Тимлид (Алекс)
assignee: Тимлид (Алекс)
status: done
pr: https://github.com/prikotov/task-orchestrator/pull/98
---

# EPIC-refactor-orchestrator-decomposition: Декомпозиция модуля Orchestrator

## 1. Concept and Goal (Концепция и цель)
### Story (Job Story)
> Когда модуль Orchestrator разрастается (120 файлов, 9890 строк, 25 VO, 5 DTO, 4 VO-дубликата с AgentRunner), я хочу декомпозировать его на модули с чёткими границами, чтобы разработка не тормозилась из-за связанности, вложенности вызовов и неясного ownership'а классов.

### Goal (Цель по SMART)
Провести глубокий анализ кода Orchestrator → определить границы модулей → реализовать декомпозицию. Результат: каждый модуль ≤ 3000 строк, ≤ 1 причина для изменения, явные Integration-слои между модулями.

## 2. Context and Scope (Контекст и границы)
*   **In Scope (Что делаем):**
    *   Глубокий анализ структуры Orchestrator (brainstorm 3 часа, 40 раундов)
    *   Построение схем: текущая структура, поток вызовов, карта зависимостей
    *   План декомпозиции с вариантами
    *   Реализация выбранного варианта декомпозиции
    *   Обновление документации архитектуры
*   **Out of Scope (Чего НЕ делаем):**
    *   Декомпозиция AgentRunner (29 файлов, 2427 строк — адекватный размер)
    *   Реализация новых фич из research-summary (error classification, sub-agents и т.д.)
    *   Изменение публичного API (commands, queries)

### Предпосылки (почему сейчас)

**Признаки оверсложности Orchestrator:**

1. **Связанность** — `Shared/` содержит 6 интерфейсов, из которых 4 принадлежат одному subdomain (Static или Dynamic), а не являются общими. Brainstorm выявил это как симптом ложной границы.
2. **VO-дублирование** — 4 VO в Orchestrator/Domain являются семантическими копиями AgentRunner VO с префиксом `Chain`: `ChainRunRequestVo` ≈ `AgentRunRequestVo`, `ChainRunResultVo` ≈ `AgentResultVo`, `ChainTurnResultVo` ≈ `AgentTurnResultVo`, `ChainRetryPolicyVo` ≈ `RetryPolicyVo`. Вместо изоляции — разрастание типов.
3. **Размер** — 120 файлов, 9890 строк в одном модуле. Domain/Service/Chain/ — 26 файлов, 2820 строк с 5 подпапками (Static, Dynamic, Session, Audit, Shared).
4. **Вложенность вызовов** — цепочка OrchestrateChainCommandHandler → ExecuteStaticChainService → RunStaticChainService → ExecuteStaticStepService → RunAgentServiceInterface → AgentDtoMapper → RunAgentCommandHandler. 7 уровней для одного шага цепочки.
5. **Ghost proxy** — Orchestrator/Application содержит `RunAgentCommandHandler` и `GetRunnersQueryHandler`, которые проксируют в AgentRunner. Brainstorm оставил RunAgent (там есть prompt resolution), но GetRunners — чистый pass-through.
6. **Roadmap давления** — `docs/research/agent-frameworks-summary.md` планирует: conditional branching, parallel execution, sub-agents, security policy, context management. Большинство этих фич не ложатся в текущую двухмодульную структуру.

### Учёт исследований

Анализ должен учитывать паттерны из 16 исследованных фреймворков ([agent-frameworks-summary.md](../../docs/research/agent-frameworks-summary.md)):
- **Archon, Agno, Mastra AI** — модели workflow engine с типизированными шагами, conditional branching, parallel execution
- **LangGraph** — graph/DAG с durable execution, checkpoint
- **Paperclip AI** — мета-оркестратор с plugin system, scoped budgets, execution policies
- **Codex** — security: exec policy, Guardian, sandboxing
- Общие тренды: typed I/O per step, processor pipeline, hooks system, sub-agent pattern

Границы модулей должны допускать расширение этими фичами без повторной реструктуризации.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Блокирующие требования)
- [ ] Проведён brainstorm-анализ (3 часа, 40 раундов) с участием архитекторов и бэкендера
- [ ] Построена схема текущей структуры Orchestrator (классы, зависимости, вызовы)
- [ ] Выявлены конкретные точки оверсложности (связанность, размер, вложенность, дублирование)
- [ ] Предложен план декомпозиции с ≥ 1 вариантом
- [ ] План согласован с владельцем проекта
- [ ] Выбранный вариант реализован: модули с явными границами и Integration-слоями
- [ ] Все тесты проходят без изменений бизнес-логики

### 🟡 Should Have (Важные требования)
- [ ] Cada модуль ≤ 3000 строк (ориентир)
- [ ] Карта зависимостей между модулями в формате Mermaid
- [ ] Deptrac-конфигурация для контроля границ модулей
- [ ] Устранено VO-дублирование (или обосновано, почему оставлено)

### 🟢 Could Have (Желательно)
- [ ] ADR (Architecture Decision Record) с обоснованием выбора варианта декомпозиции
- [ ] Метрики «до/после»: количество файлов, строк, VO, связей на модуль

### ⚫ Won't Have (Не в этот раз)
- [ ] Декомпозиция AgentRunner
- [ ] Реализация новых фич из research-summary
- [ ] Переход на graph/DAG — это отдельный R&D-эпик
- [ ] Изменение публичного CLI API

## 4. Solution Design (Техническое решение)
*Определяется по результатам brainstorm-анализа (Фаза 1).*

Предварительные кандидаты на выделение из Orchestrator:
- **Chain Execution** (Static + Dynamic) — ядро оркестрации
- **Chain Config** (YAML-загрузка, валидация, определение цепочки)
- **Reporting** (генерация отчётов, форматтеры)
- **Session/Audit** (JSONL-лог, сессионный state)
- **Prompt** (сборка промптов для ролей)

Финальное решение — по результатам анализа.

## 5. Implementation Plan (План реализации)

### Фаза 1: Анализ (P1)
- [x] [TASK-refactor-orchestrator-brainstorm-analysis](TASK-refactor-orchestrator-brainstorm-analysis.todo.md) — Глубокий brainstorm-анализ Orchestrator: 40 раундов, 81 шаг, 2ч 42мин, 10 решений, 16 action items. [Протокол](../../var/sessions/brainstorm/2026-04-29_08-06-49/result.md).

### Фаза 2: Планирование (P1)
- [x] [TASK-refactor-orchestrator-decomposition-plan](TASK-refactor-orchestrator-decomposition-plan.todo.md) — Оформление плана: 3 ADR (ExecutionStrategy, VO ACL, Shared Kernel), черновой roadmap, задачи на P1-action items.

### Фаза 3: Реализация P1 (P1)
- [x] TASK-refactor-inline-execute-dynamic-turn — Инлайнинг ExecuteDynamicTurnService в RunDynamicLoopService. Вложенность 7→5. — PR #101
- [x] TASK-refactor-prompt-configuration-vo — PromptConfiguration VO: 7 промпт-полей → отдельный VO. — PR #102
- [x] TASK-refactor-session-writer-consumers — Переключение 3 потребителей на ChainSessionWriterInterface. — PR #103

### Фаза 4: Реализация P2 (P2, задачи по готовности)
- [x] TASK-refactor-execution-strategy — ExecutionStrategyInterface + Static/Dynamic + CommandHandler rewrite (ADR-006) — PR #104
- [x] TASK-refactor-chain-definition-split — Расщепление ChainDefinitionVo (ADR-008) — PR #105

### Фаза 5: Валидация (P2)
- [x] Deptrac-конфигурация для контроля границ
- [x] Обновление docs/guide/architecture.md

## 6. Definition of Done (Критерии приёмки эпика)
- [x] Brainstorm-анализ проведён, протокол сохранён
- [x] План декомпозиции согласован
- [x] Модули разделены с явными Integration-слоями
- [x] `vendor/bin/phpunit` — все тесты проходят
- [x] `vendor/bin/psalm` — без ошибок
- [x] Deptrac контролирует границы модулей
- [x] Архитектурная документация обновлена

## 7. Release Notes and Deployment (Инструкция по релизу)
- [ ] Заполняется по завершении

## 8. Risks and Dependencies (Риски и зависимости)
- **Риск 1:** Декомпозиция может затронуть `config/services.yaml` и DI-конфигурацию — возможны временные перебои
- **Риск 2:** VO-дублирование между Orchestrator и AgentRunner может оказаться не случайным, а необходимым — тщательный анализ перед удалением
- **Риск 3:** Brainstorm 3 часа / 40 раундов — тяжёлая сессия, может потребоваться resume при сбоях
- **Зависимость:** Результаты `docs/research/agent-frameworks-summary.md` должны быть доступны участникам brainstorm

## 9. Sources (Источники)
- [ ] [Архитектура проекта](../../docs/guide/architecture.md)
- [ ] [Сводная таблица исследований](../../docs/research/agent-frameworks-summary.md)
- [ ] [Протокол brainstorm от 2026-04-29](../../var/sessions/brainstorm/2026-04-29_03-42-46/result.md)
- [ ] [Конвенции](../../docs/conventions/index.md)

## 10. Comments (Комментарии)
Контекст: на предыдущем brainstorm (2026-04-29, 13 раундов) было принято решение «не декомпозировать сейчас». Владелец проекта не согласился — признаки оверсложности налицо (связанность, размер, вложенность, VO-дублирование). Запускаем глубокий анализ для принятия взвешенного решения.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-29 | Тимлид (Алекс) | Создание эпика |
