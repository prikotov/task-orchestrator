# Orchestration Articles — сводная таблица исследований

**Дата создания:** 2026-07-29  
**Дата обновления:** 2026-09-11 (2 исследования)  
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
| 2 | [T3 Code](orchestration-articles/t3-code-research.md) | Meta-orchestration (мета-оркестрация) агент-харнесов / control surface (контрольная поверхность) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | **18** | Выборочный **apply** для контракта и BYO subscription (своей подписки), `study` для долговечных потоков, worktree и разрешений |

## Часть 2. Детальная сводная таблица

| # | Статья | Главный тезис | Pattern type (тип паттерна) | Domain (область) | Failure handling (обработка сбоев) | Главный gap у нас | Рекомендация | Effort |
|---:|---|---|---|---|---|---|---|---|
| 1 | [The Orchestrator's Tax](orchestration-articles/orchestrator-tax-research.md) | Налог платится не за subagents (сабагентов), а за то, что orchestrator (оркестратор) несёт дальше в своём контексте. | Context isolation (изоляция контекста), cognitive locality (когнитивная локальность), minimal standing rules (минимальные постоянные правила). | AI-agent workflows (процессы AI-агентов), long-running sessions (долгие сессии). | Process failures: raw transcript polling (опрос полной стенограммы), duplicated orientation (дублирование ориентации), unsafe git operations (опасные git-операции), missing skill propagation (непереданные skills). | Нет явного summary-first контракта для статусов subagent, нет правила cognitive locality, нет метрик context pollution (загрязнения контекста). | `apply`: summary-first status, cognitive locality heuristic, запрет repository-wide git operations в concurrent agents; `study`: метрики working memory quality. | Medium |
| 2 | [T3 Code](orchestration-articles/t3-code-research.md) | Единая контрольная поверхность управляет сторонними харнесами, сохраняя исполнение, Git и credentials (учётные данные) в окружении рабочей области. | Нормализованный provider contract (контракт провайдера), durable thread (долговечный поток), branch/worktree isolation (изоляция рабочей области), BYO subscription. | AI-agent orchestration, локальные CLI-харнесы, Git-процессы, удалённые клиенты. | Журнал событий, идемпотентные command receipts (квитанции команд), reactors (обработчики последствий), capability checks (проверки возможностей), безопасные checkpoints (контрольные точки). | Нет долговечного контракта сессии и возможностей runner, worktree-владения, исполняемой политики разрешений и удалённого API. | `apply`: нормализованная граница и BYO subscription; `study`: thread/session, worktree, переключение, remote и permission modes; `skip`: one-button PR в текущем движке. | Medium–High |

## Часть 3. Проверка гипотезы orchestrator vs choreography

Пока исследовано 2 / N источника.

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

## Часть 5. Тренды и предварительные выводы

1. **Orchestrator tax в AI-agent системах — это прежде всего tax контекста.** Стоимость token (токена) вторична по сравнению с шумом, который остаётся в working memory.
2. **Централизованный orchestrator нам подходит.** Его надо делать тонким, наблюдаемым и защищённым от context pollution, а не заменять choreography без сильной причины.
3. **Subagents полезны не количеством, а изоляцией disposable reasoning.** Если результат subagent не нужен будущим решениям orchestrator, он не должен попадать в главный контекст.

**Предварительные тренды после 2 / N исследований:**

4. **Нормализация должна включать capabilities (возможности), а не только общий результат.** Иначе оркестратор начнёт предполагать, что каждый runner умеет продолжать сессию, менять модель, запрашивать разрешения и откатывать разговор.
5. **Долговечный execution (запуск) и процесс runner — разные сущности.** Состояние задачи должно переживать завершение процесса исполнителя; масштаб решения зависит от требований к resume и remote control.
6. **Изоляция конкурентных писателей требует worktree, а не только branch.** Ветка задаёт историю, но не разделяет файловую систему одновременных процессов.
7. **Централизация переносит сложность в границы совместимости.** Налог уменьшают provider adapters (границы провайдеров), идемпотентные команды и явное согласование возможностей, но он не исчезает.
8. **Выбираемая автономия не должна ослаблять правила проекта.** Permission mode (режим разрешений) может быть строже, но не отменяет обязательный человеческий барьер на merge и правила идентичности Git.
