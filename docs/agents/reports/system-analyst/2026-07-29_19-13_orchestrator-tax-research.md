# Аналитический отчёт: The Orchestrator's Tax

**Роль:** Аналитик (Шерлок)  
**Дата:** 2026-07-29  
**Объект:** `todo/TASK-research-orchestrator-tax.todo.md`, первоисточник `https://martinfowler.com/articles/orchestrator-tax.html`, модули `AgentRunner`, `ChainExecution`, `DynamicLoop`, `ChainDefinition`, `GitIdentity`  
**Задача:** [TASK-research-orchestrator-tax](../../../../todo/TASK-research-orchestrator-tax.todo.md)

---

## Результат

Созданы документы:

- `docs/research/orchestration-articles/orchestrator-tax-research.md`
- `docs/research/orchestration-articles-summary.md`
- `docs/agents/reports/system-analyst/2026-07-29_19-13_orchestrator-tax-research.md`

## Ключевая находка

Постановка задачи ожидала статью Martin Fowler 2012 года про микросервисы, choreography (хореографию через события) и saga (сагу с компенсациями). По фактической ссылке находится статья Rahul Garg от 2026-07-28 на сайте Martin Fowler. Она посвящена multi-agent workflow (многоагентному процессу), subagents (сабагентам) и загрязнению working memory (рабочей памяти) orchestrator (оркестратора).

Исследование выполнено по фактическому первоисточнику. Saga/choreography отмечены как темы, отсутствующие в статье.

## Оценка по 6 критериям

| Критерий | Оценка | Вывод |
|---|---:|---|
| Тезис / проблема | ✅ | Налог orchestrator — не только token/cost, а шум в контексте, который влияет на все последующие решения. |
| Паттерн / концепция | ✅ | Subagents нужны как изоляция disposable reasoning (одноразовых рассуждений); деление задач должно учитывать cognitive locality. |
| Domain | ✅ | Прямая область: AI-agent orchestration. |
| Failure handling | ⚠️ | Статья про process failures: raw transcript polling, duplicated orientation, unsafe git operations, missing skill propagation. |
| Маппинг | ✅ | Хорошо ложится на `ChainExecution`, `DynamicLoop`, `AgentRunner`, `ChainDefinition`; `GitIdentity` связан косвенно. |
| Применяемость | ✅ | Вердикт `apply` для правил процесса, `study` для метрик working memory quality. |

Итоговый балл: **17/18**.

## Гипотеза: orchestrator или choreography?

**Вердикт:** task-orchestrator — **orchestrator**, не choreography.

Основания:

- `OrchestrateChainCommandHandler` централизованно выбирает `ExecutionStrategyInterface`.
- `StaticExecutionStrategyService` и `ConditionalExecutionStrategyService` управляют порядком шагов.
- `DynamicExecutionStrategy` и `RunDynamicLoopService` управляют session lifecycle (жизненным циклом сессии), facilitator/participant turns (ходами фасилитатора и участников), max rounds, max time и finalize.
- retry/fallback/budget/audit задаются в runtime (среде выполнения) orchestrator, а не распределены по независимым event subscribers (подписчикам событий).

Choreography можно использовать только локально: события для уведомлений, метрик и cleanup (очистки). Передавать критический порядок цепочек в распределённые события сейчас нецелесообразно.

## Основные gaps

1. Нет summary-first contract (контракта «сначала сводка») для статуса subagent; raw transcript может загрязнить контекст.
2. Нет формального правила cognitive locality при декомпозиции задач и запуске batch (пакета) subagents.
3. Нет отдельного guard (защитного ограничения) против repository-wide git operations в concurrent agents.
4. Нет метрик context pollution: объём raw transcript, попавший в главный контекст; число усечений контекста; соотношение summary/read.
5. Skill propagation (передача skills) должна быть явной: subagent получает путь к `SKILL.md`, а не рассчитывает на наследование parent session.

## Рекомендации

| # | Рекомендация | Вердикт | Effort |
|---:|---|---|---|
| 1 | Status subagent должен возвращать summary; full transcript — только по явному запросу. | `apply` | Low/Medium |
| 2 | Добавить cognitive locality heuristic в правила запуска subagents. | `apply` | Low |
| 3 | Запретить `git stash`, `git reset --hard` и repository-wide mutations внутри concurrent agents без разрешения orchestrator. | `apply` | Medium |
| 4 | Исследовать и добавить метрики context pollution / working memory quality. | `study` | Medium/High |
| 5 | Явно передавать relevant skill file paths при spawn subagent. | `apply` | Low/Medium |

## Источники

- [Rahul Garg, The Orchestrator's Tax](https://martinfowler.com/articles/orchestrator-tax.html) — первоисточник.
- `docs/guide/architecture.md` — архитектура task-orchestrator.
- `src/Module/AgentRunner/`
- `src/Module/ChainExecution/`
- `src/Module/DynamicLoop/`
- `src/Module/ChainDefinition/`
- `src/Module/GitIdentity/`

## Проверки

Изменения только в Markdown-документации. PHPUnit и Psalm не запускались по docs-only исключению из `AGENTS.md`.
