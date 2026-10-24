# Orchestration Articles — сводная таблица исследований

**Дата создания:** 2026-07-29  
**Дата обновления:** 2026-10-24 (2 исследования)  
**Эпик:** [EPIC-research-orchestration-articles](../../todo/EPIC-research-orchestration-articles.todo.md)  
**Автор:** Аналитик (Шерлок)

---

## Легенда оценок

| Символ | Значение | Балл |
|---|---|---:|
| ✅ | Сильная применимость / прямое совпадение с task-orchestrator | 3 |
| ⚠️ | Частичная применимость, нужен `study` (дополнительное изучение) или адаптация | 2 |
| ❌ | Низкая применимость или отсутствует в источнике | 1 |

## Часть 1. Ранжирование статей

Место определяется суммой баллов по 6 критериям методологии эпика (максимум 18) и качественным вердиктом для architecture/process (архитектуры и процесса) task-orchestrator.

| # | Статья | Категория | К1 Тезис | К2 Концепция | К3 Domain | К4 Failure handling | К5 Маппинг | К6 Вердикт | ∑ | Итог |
|---:|---|---|---|---|---|---|---|---|---:|---|
| 1 | [The Orchestrator's Tax](orchestration-articles/orchestrator-tax-research.md) | AI-agent orchestration / context engineering (управление контекстом) | ✅ | ✅ | ✅ | ⚠️ | ✅ | ✅ | **17** | **apply** — закрепить правила защиты working memory (рабочей памяти), `study` для метрик |
| 2 | [Pi Durable](orchestration-articles/pi-durable-research.md) | AI-agent orchestration / durable execution (долговечное исполнение) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | **18** | **study** — проектировать durable-журнал с resume, saga-компенсацию, компакцию; **apply** — replay-классификация шагов, `failFast`-контракт |

## Часть 2. Детальная сводная таблица

| # | Статья | Главный тезис | Pattern type (тип паттерна) | Domain (область) | Failure handling (обработка сбоев) | Главный gap у нас | Рекомендация | Effort |
|---:|---|---|---|---|---|---|---|---|
| 1 | [The Orchestrator's Tax](orchestration-articles/orchestrator-tax-research.md) | Налог платится не за subagents (сабагентов), а за то, что orchestrator (оркестратор) несёт дальше в своём контексте. | Context isolation (изоляция контекста), cognitive locality (когнитивная локальность), minimal standing rules (минимальные постоянные правила). | AI-agent workflows (процессы AI-агентов), long-running sessions (долгие сессии). | Process failures: raw transcript polling (опрос полной стенограммы), duplicated orientation (дублирование ориентации), unsafe git operations (опасные git-операции), missing skill propagation (непереданные skills). | Нет явного summary-first контракта для статусов subagent, нет правила cognitive locality, нет метрик context pollution (загрязнения контекста). | `apply`: summary-first status, cognitive locality heuristic, запрет repository-wide git operations в concurrent agents; `study`: метрики working memory quality. | Medium |
| 2 | [Pi Durable](orchestration-articles/pi-durable-research.md) | «Every step of a run is a task that stores a checkpoint before it moves on»: долговечность — свойство шагов и хранилища, а не только вызовов. | Durable tasks с чекпойнтами, replay-семантика (`safe` / no-replay), saga-компенсация bottom-up (refund при abort), ожидание задач `failFast`, документы-состояние в атомарных коммитах со стенограммой, компакция контекста, подагент-беседа с владением. | AI-agent harness (каркас агентов) — библиотека `@earendil-works/pi-durable` (experimental). | Crash recovery: resume с чекпойнта; повтор только read-only вызовов; мутирующие — «never repeated», отчёт модели; частичный ответ помечается aborted; таймеры переживают рестарт. | Нет durable-журнала шагов и команды resume (смерть оркестратора = потеря прогресса), нет компенсирующих шагов, нет replay-классификации шагов, контекст обрезается без сводки. | `apply`: replay-классификация шагов в ChainDefinition, `failFast`-контракт для parallel/DAG, «store names, never code», «nothing is deleted»; `study`: durable-журнал с resume, компенсация, документы-состояние, summary-компакция; `skip`: malleable, буквальный API. | High (главные направления) |

## Часть 3. Проверка гипотезы orchestrator vs choreography

Пока исследовано 2 / N статьи. Дополнительный вывод по [Pi Durable](orchestration-articles/pi-durable-research.md): durability (долговечное исполнение) не меняет модель координации — Pi Durable остаётся централизованным orchestrator'ом и добавляет вертикаль «состояние» (чекпойнты, resume, компенсация). Наша ставка на orchestrator валидируется; следующий уровень зрелости — устойчивость самого процесса оркестрации к сбою, а не только вызовов раннеров.

| Вопрос | Вывод |
|---|---|
| task-orchestrator — orchestrator или choreography? | **Orchestrator.** Центральный `OrchestrateChainCommandHandler` выбирает strategy (стратегию), `ChainExecution` и `DynamicLoop` управляют порядком, контекстом, budget (бюджетом), retry/fallback (повторами и резервным исполнением). |
| Есть ли choreography? | Нет как модель управления. Events (события) используются как уведомления, а не как распределённый протокол выбора следующего шага. |
| Какой tax (налог) платим? | Context tax, coordination tax, configuration tax, duplicated-orientation tax, workspace hazard tax. |
| Нужно ли переходить на choreography? | Нет. Для воспроизводимых цепочек, audit trail (следа аудита), quality gates (ворот качества) и fail-fast (быстрого отказа) центральная оркестрация уместна. Можно использовать events только для побочных эффектов. |

## Часть 4. Backlog рекомендаций по итогам исследований

| # | Рекомендация | Источник | Статус | Effort |
|---:|---|---|---|---|
| 1 | В subagent-status (статусе сабагента) возвращать summary (сводку), а полный transcript (стенограмму) читать только по явному запросу. | [The Orchestrator's Tax](orchestration-articles/orchestrator-tax-research.md#9-рекомендации) | Рекомендация, задача не создана | Low/Medium |
| 2 | Добавить cognitive locality heuristic: задачи с общей ментальной моделью, файлами или conventions (конвенциями) объединять, а не распараллеливать механически. | [The Orchestrator's Tax](orchestration-articles/orchestrator-tax-research.md#9-рекомендации) | Рекомендация, задача не создана | Low |
| 3 | Запретить `git stash`, `git reset --hard` и другие repository-wide mutations (изменения всего репозитория) внутри concurrent subagents без явного разрешения orchestrator. | [The Orchestrator's Tax](orchestration-articles/orchestrator-tax-research.md#9-рекомендации) | Рекомендация, задача не создана | Medium |
| 4 | Добавить метрики context pollution: bytes/tokens imported from raw transcript, context truncation count, summary/read ratio. | [The Orchestrator's Tax](orchestration-articles/orchestrator-tax-research.md#9-рекомендации) | `study`, задача не создана | Medium/High |
| 5 | При spawn subagent явно передавать relevant skill file paths (пути к нужным skill-файлам), не рассчитывать на наследование parent skills. | [The Orchestrator's Tax](orchestration-articles/orchestrator-tax-research.md#9-рекомендации) | Рекомендация, задача не создана | Low/Medium |
| 6 | Replay-классификация шагов в `ChainDefinition` (`read-only` / `mutating`): read-only ретраятся смело, mutating — только с явной идемпотентностью. | [Pi Durable](orchestration-articles/pi-durable-research.md#9-рекомендации) | Рекомендация, задача не создана | Medium |
| 7 | В дизайн parallel/DAG-задач заложить `failFast`: первый сбой прерывает остальные шаги + каскадная компенсация выполненных. | [Pi Durable](orchestration-articles/pi-durable-research.md#9-рекомендации) | Рекомендация, задача не создана | Low (дизайн) |
| 8 | Durable-журнал шагов цепочки + команда `resume` прерванной цепочки с последнего зафиксированного шага. | [Pi Durable](orchestration-articles/pi-durable-research.md#9-рекомендации) | `study`, задача не создана | High |
| 9 | Компенсирующие шаги (`compensate:` у шага, каскад bottom-up) — применение saga из методологии эпика. | [Pi Durable](orchestration-articles/pi-durable-research.md#9-рекомендации) | `study`, задача не создана | Medium/High |
| 10 | Summary-компакция контекста вместо грубого усечения по `maxContextLength`; полный журнал остаётся доступным. | [Pi Durable](orchestration-articles/pi-durable-research.md#9-рекомендации) | `study`, задача не создана | Medium/High |
| 11 | В audit-записях хранить имена/версии цепочки и раннеров, а не снапшоты конфигурации («store names, never code»). | [Pi Durable](orchestration-articles/pi-durable-research.md#9-рекомендации) | Рекомендация, задача не создана | Low |
| 12 | Для mutating-шагов требовать идемпотентные ключи (предсказуемые имена веток/заголовки PR). | [Pi Durable](orchestration-articles/pi-durable-research.md#9-рекомендации) | Рекомендация, задача не создана | Low |

## Часть 5. Тренды и предварительные выводы

1. **Orchestrator tax в AI-agent системах — это прежде всего tax контекста.** Стоимость token (токена) вторична по сравнению с шумом, который остаётся в working memory.
2. **Централизованный orchestrator нам подходит.** Его надо делать тонким, наблюдаемым и защищённым от context pollution, а не заменять choreography без сильной причины.
3. **Subagents полезны не количеством, а изоляцией disposable reasoning.** Если результат subagent не нужен будущим решениям orchestrator, он не должен попадать в главный контекст.
4. **Долговечность — свойство шагов и хранилища, а не только вызовов.** Устойчивость уровня вызова (retry, circuit breaker, budget) не спасает при смерти самого оркестратора; следующий уровень — чекпойнты шагов и resume ([Pi Durable](orchestration-articles/pi-durable-research.md)).
5. **Повтор после сбоя — решение с последствиями.** Read-only вызовы повторяются свободно, мутирующие — только с идемпотентным ключом; при сомнении — не повторять, а отчитаться и откатить (saga-компенсация).
