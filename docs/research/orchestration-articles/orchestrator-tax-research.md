# Исследование: The Orchestrator's Tax (налог оркестратора)

**Источник:** [Rahul Garg, The Orchestrator's Tax, martinfowler.com](https://martinfowler.com/articles/orchestrator-tax.html)  
**Дата первоисточника:** 2026-07-28  
**Дата анализа:** 2026-07-29  
**Эпик:** [EPIC-research-orchestration-articles](../../../todo/EPIC-research-orchestration-articles.md)  
**Задача:** [TASK-research-orchestrator-tax](../../../todo/done/TASK-research-orchestrator-tax.todo.md)  
**Аналитик:** Аналитик (Шерлок)

---

## 0. Важное уточнение по первоисточнику

В постановке задачи статья описана как материал Martin Fowler 2012 года про микросервисную оркестрацию, choreography (хореография через события) и saga (сага с компенсациями). Фактический первоисточник по указанной ссылке — статья Rahul Garg от 2026-07-28 на сайте Martin Fowler.

Статья не про микросервисные saga/choreography. Она про multi-agent workflow (многоагентный процесс) и главный дефицит долгой сессии: качество рабочей памяти orchestrator (центрального координатора). Поэтому выводы ниже опираются на фактический текст первоисточника, а темы saga/choreography отмечены как отсутствующие в статье.

## 1. Краткий вывод

**Вердикт по статье:** `apply` (применить) с частичным `study` (изучить) для метрик.

Статья напрямую релевантна task-orchestrator: наш продукт — централизованный orchestrator (оркестратор) AI-агентов, а главный «налог» у нас — не только время запуска runner (исполнителя агента), а риск загрязнения контекста, дублирования ориентации агентов и опасных операций при параллельной работе.

**Гипотеза “orchestrator или choreography?”:** task-orchestrator сейчас — **orchestrator**, не choreography. Центральный `OrchestrateChainCommandHandler` выбирает стратегию, static/conditional/dynamic-цепочки централизованно управляют шагами, retry/fallback/budget/audit задаются оркестратором. Event (событие) `OrchestrateSessionCompletedEvent` — уведомление о завершении, а не хореографическая модель управления процессом.

## 2. Методология эпика: 6 критериев

| Критерий | Оценка | Вывод для task-orchestrator |
|---|---:|---|
| 1. Тезис / проблема | ✅ | Налог orchestrator — загрязнение его рабочей памяти и рост когнитивной нагрузки, а не только стоимость subagents (сабагентов). |
| 2. Паттерн / концепция | ✅ | Subagents нужны как изоляция рассуждений; делить задачи надо по cognitive locality (когнитивной локальности). |
| 3. Domain (область) | ✅ | Статья про AI-agent orchestration (оркестрацию AI-агентов), то есть ближе к нам, чем классические микросервисы. |
| 4. Failure handling (обработка сбоев) | ⚠️ | Статья разбирает process failures (сбои процесса): polling (опрос) transcript (стенограммы), unsafe git (опасные git-операции), missing skill propagation (непереданные skills). Технические retry/circuit breaker у нас уже есть, но не закрывают эти риски. |
| 5. Маппинг на архитектуру | ✅ | Хорошо ложится на `ChainExecution`, `DynamicLoop`, `AgentRunner`, `ChainDefinition`; слабее — на `GitIdentity`. |
| 6. Применяемость | ✅ | Применить как правила делегирования, границы контекста и метрики контекстного шума; не переносить как универсальные числовые пороги. |

## 3. Содержание первоисточника

Главные тезисы статьи:

1. **Скорость — видимый побочный эффект, не главный смысл subagents.** Параллельный запуск может экономить wall-clock time (настенное время), но основная ценность — не тащить промежуточные рассуждения в главный контекст.
2. **Full transcript polling (полный опрос стенограмм) загрязняет orchestrator context (контекст оркестратора).** Одно чтение десятков тысяч token (токенов) остаётся в контексте и влияет на все следующие решения.
3. **Tokens are spent once; context keeps charging rent.** Токены тратятся один раз, а шум в контексте продолжает конкурировать за внимание модели.
4. **Cognitive locality (когнитивная локальность).** Задачи, которым нужна одна и та же ментальная модель, лучше держать вместе; иначе несколько agents заново читают одни файлы и восстанавливают одну архитектуру.
5. **Repository-wide git operations (операции на весь репозиторий) опасны в конкурентной работе.** `git stash`/`git stash pop` в одном subagent могут повредить работу sibling agents (соседних агентов).
6. **Standing rules (постоянные правила) должны быть минимальными.** Добавлять нужно недостающий факт, а не бюрократическую процедуру.
7. **Skills не наследуются автоматически.** Parent orchestrator (родительский оркестратор) должен явно указать subagent, какие skill-файлы загрузить.
8. **Метрики пока открытый вопрос.** Автор подчёркивает, что self-critique (самооценка) orchestrator не заменяет измерения.

## 4. Маппинг паттернов и anti-patterns на task-orchestrator

| # | Паттерн / риск из статьи | Компоненты task-orchestrator | Текущее состояние | Вердикт | Effort (усилие) |
|---:|---|---|---|---|---|
| 1 | Subagents как изоляция рабочей памяти orchestrator | `src/Module/ChainExecution/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommandHandler.php`, `src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopService.php` | Оркестратор делегирует шаги в стратегии и runner'ы; dynamic-цикл ведёт journal/session отдельно. | `apply` — закрепить как дизайн-принцип. | Low |
| 2 | Full transcript polling загрязняет контекст | `src/Module/AgentRunner/Infrastructure/*Jsonl*`, `src/Module/DynamicLoop/Infrastructure/Service/JsonlAuditLogger.php`, session files | Audit хранится в JSONL/файлах; риск появляется, если status/read tools возвращают полный raw log в orchestrator context. | `apply` — статус должен отдавать summary, не raw transcript. | Medium |
| 3 | Duplicated orientation (дублированная ориентация) при слишком мелком делении | `src/Module/ChainDefinition/Domain/ValueObject/ChainStepVo.php`, `config/chains.yaml`, role-based chains | Цепочки описывают шаги декларативно, но нет формального признака shared cognitive locality. | `study` — добавить методологическое правило группировки задач. | Low/Medium |
| 4 | Cognitive locality как критерий разбиения | `todo/AGENTS.md`, `docs/agents/skills/*`, `ChainDefinition` | Процесс требует декомпозиции, но не фиксирует «одна ментальная модель — один agent/batch». | `apply` — добавить в future task-шаблоны/skills. | Low |
| 5 | Unsafe repository-wide git operations при конкуренции | Git workflow в `AGENTS.md`, `docs/git-workflow/*`, потенциально runner prompts | Правила запрещают автокоммиты/пуши, но нет отдельного runtime guard (защитного ограничения) для `git stash` в concurrent agents. | `apply` — правило/guard для конкурентных subagents. | Medium |
| 6 | Минимальные standing rules вместо governance ritual | `AGENTS.md`, role files, skill `become-role` | Проект уже предпочитает явные правила, но есть риск разрастания инструкций. | `apply` — новые правила добавлять как факты/эвристики, не как лишние approval gates. | Low |
| 7 | Skill propagation не автоматическая | `docs/agents/skills/become-role/SKILL.md`, role frontmatter `skills` | `become-role` явно объявляет skills; при запуске subagent важно передавать путь к `SKILL.md`, а не вставлять всё inline. | `apply` — валидировать в subagent skills. | Low/Medium |
| 8 | Измерение качества working memory | Метрики `AgentRunner`, audit/session logs `DynamicLoop`, будущие observability docs | Есть метрики runner'ов, tokens/cost/duration; нет метрики контекстного шума и объёма импортированных transcript. | `study` — нужна отдельная research/feat-задача. | Medium/High |
| 9 | Batch-size thresholds: 2–4 agents, 5+ как сигнал консолидации | Chain definitions, future subagent orchestration | Числа в статье эмпирические и model-specific (зависят от модели). | `study` — не копировать как константы, изучить на наших задачах. | Medium |
| 10 | Saga/choreography из постановки задачи | Нет в фактическом первоисточнике | Статья не обсуждает saga/choreography. | `skip` для этой статьи; исследовать отдельными источниками. | — |

## 5. Маппинг на модули проекта

### AgentRunner

`AgentRunner` запускает конкретный CLI runner (исполнитель командной строки). Модуль уже содержит техническую устойчивость:

- `src/Module/AgentRunner/Infrastructure/Service/RetryingAgentRunnerService.php` — retry (повтор) с exponential backoff (экспоненциальной задержкой) и классификацией ошибок.
- `src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunnerService.php` — circuit breaker (автоматический размыкатель) с fallback runner (резервным исполнителем).
- `src/Module/AgentRunner/Application/UseCase/Command/RunAgent/RunAgentCommandHandler.php` — усечение `previousContext` по `maxContextLength`.

**Gap:** retry/circuit breaker защищают вызовы, но не защищают orchestrator working memory от случайной загрузки полного transcript.

### ChainExecution

`ChainExecution` — ядро централизованной оркестрации static/conditional chains (статических и условных цепочек):

- `OrchestrateChainCommandHandler` выбирает `ExecutionStrategyInterface`.
- `StaticExecutionStrategyService` делегирует линейное выполнение в `ExecuteStaticChainService`.
- `RunStaticChainService` ведёт step context (контекст шага), budget (бюджет), fix iterations (итерации исправления), hooks (хуки).
- `ConditionalExecutionStrategyService` хранит контекст результатов и управляет `when`-условиями.

**Вывод:** это classic orchestrator (классический оркестратор): центральный компонент знает порядок шагов, контекст предыдущих результатов, budget и правила остановки.

### DynamicLoop

`DynamicLoop` ближе всего к статье:

- `DynamicExecutionStrategy` запускает session (сессию), audit (аудит), context build (сбор контекста), loop run (запуск цикла), finalize (финализацию).
- `RunDynamicLoopService` централизованно управляет facilitator/participant turns (ходами фасилитатора и участника), max rounds (лимитом раундов), max time (лимитом времени) и synthesis (синтезом).
- `RunDynamicLoopAgentService` пишет prompt files (файлы промптов), передаёт append prompt (добавочный промпт), сохраняет invocation (вызов).

**Вывод:** это не choreography. Даже если участники генерируют ответы, facilitator и DynamicLoop остаются центром управления.

### ChainDefinition

`ChainDefinition` задаёт декларативный contract (контракт) цепочки:

- `ChainStepVo` знает тип шага (`agent`, `quality_gate`, `tool`), роль, runner, retry policy, `noContextFiles`, `when`, `postStep`.
- `PromptConfigurationVo` задаёт промпты dynamic-цепочки.

**Gap:** в конфигурации нет явной метки cognitive locality / file ownership (владения файлами), по которой orchestrator мог бы понять, какие задачи лучше объединить.

### GitIdentity

`GitIdentity` обеспечивает installation token (токен установки) и bot identity (идентичность бота) для GitHub operations (операций GitHub). Он не управляет рабочей памятью orchestrator.

**Связь со статьёй:** косвенная. Статья предупреждает про repository-wide git operations в concurrent agents; GitIdentity помогает корректной идентичности PR/commit, но не предотвращает опасные операции в рабочем дереве.

## 6. Failure handling: что уже закрыто и что нет

| Риск | У нас закрыто? | Комментарий |
|---|---:|---|
| Transient runner failure (временный сбой runner) | ✅ | Retry с классификацией в `RetryingAgentRunnerService`. |
| Provider/runner outage (недоступность провайдера/runner) | ✅ | Circuit breaker + fallback runner. |
| Budget overrun (превышение бюджета) | ✅ | Static и dynamic budget checks. |
| Agent error / timeout в dynamic loop | ✅ | DynamicLoop прерывает session с причиной `agent_error` или `timeout`. |
| Context pollution от raw transcript | ⚠️ | Нет явного контракта: status/read tools должны возвращать summary, не полный JSONL. |
| Duplicate orientation из-за плохой нарезки | ⚠️ | Есть декомпозиция задач, но нет правила cognitive locality. |
| Concurrent git workspace hazard | ⚠️ | Есть workflow-правила, но нет отдельного guard для concurrent writers. |
| Skill propagation failure | ⚠️ | `become-role` помогает, но subagent orchestration должна явно передавать skill path. |

## 7. Проверка гипотезы: orchestrator или choreography?

### Критерии

| Критерий | Orchestrator | Choreography | task-orchestrator |
|---|---|---|---|
| Кто знает порядок процесса? | Центральный orchestrator | Каждый участник реагирует на events | Центральный handler/strategy знает порядок |
| Как выбирается следующий шаг? | Strategy/handler/config | Event subscriptions (подписки на события) | `ExecutionStrategyInterface`, `ChainStepVo`, facilitator |
| Где retry/fallback/budget? | В orchestrator/runtime | В локальных сервисах-участниках | В AgentRunner/ChainExecution/DynamicLoop |
| Как передаётся контекст? | Явно через orchestrator | Через event payloads (полезную нагрузку событий) | `previousContext`, prompt files, session journal |
| Есть ли central audit/session? | Да | Обычно распределённо | Да: JSONL audit, session dirs |

**Вердикт:** task-orchestrator — orchestrator with delegated agents (оркестратор с делегированными агентами). Choreography сейчас отсутствует как модель управления.

### Какой «налог» мы платим

1. **Context tax (налог контекста):** результаты шагов, session journal и audit могут стать шумом, если возвращать их в главный поток без сжатия.
2. **Coordination tax (налог координации):** `ChainExecution` и `DynamicLoop` должны знать порядок, budget, retry, fallback, prompt wiring (связку промптов), session lifecycle (жизненный цикл сессии).
3. **Configuration tax (налог конфигурации):** YAML-chain (цепочка YAML) и role config должны корректно связать roles, runners, prompt files, retry policies.
4. **Cognitive duplication tax (налог дублирования понимания):** несколько agents могут заново читать одну архитектуру, если шаги разбиты по задачам, а не по cognitive locality.
5. **Workspace hazard tax (налог риска рабочей директории):** параллельные agents могут конфликтовать в одном Git working tree (рабочем дереве Git).

### Может ли choreography заменить orchestrator?

Для текущего продукта — **нет как основная модель**:

- Нам нужна воспроизводимость цепочек, audit trail (след аудита), quality gates (ворота качества) и fail-fast (быстрый отказ).
- DynamicLoop требует facilitator, max rounds, max time и synthesis; это центральная координация.
- Choreography через events усложнит отладку и трассировку: процесс размажется по event handlers (обработчикам событий).

Что можно взять от choreography локально:

- event notifications (уведомления событиями) для post-run reports (отчётов после запуска), metrics (метрик), cleanup (очистки);
- локальные handlers для побочных эффектов, не влияющих на порядок цепочки;
- запрет переносить управление критическим порядком шагов в распределённые события без отдельного ADR.

## 8. Что делаем правильно / gaps / не применимо

### Уже делаем правильно

- Центральный handler не содержит всю поведенческую логику: Strategy pattern (паттерн «стратегия») снижает coupling (связность).
- Retry/circuit breaker/fallback есть на уровне runner.
- Context truncation (усечение контекста) уже реализовано в `RunAgentCommandHandler` и dynamic integration.
- Audit/session logs отделены от основного результата, что поддерживает идею «шум остаётся вне главного потока».
- Role/skill model (модель ролей и навыков) явно документирована через `become-role`.

### Gaps

1. Нет правила: status (статус) subagent должен быть summary-first (сначала сводка), raw transcript — только по явному запросу.
2. Нет формального критерия cognitive locality при декомпозиции задач и выборе batch (пакета) subagents.
3. Нет guard против repository-wide git operations в concurrent agent prompts.
4. Нет метрик «контекстного загрязнения»: сколько token/строк transcript импортировано в главный контекст, сколько raw tool output попало в orchestrator.
5. Нет явного поля file ownership / affected files для subagent wave (волны сабагентов) в задачах и skills.

### Не применимо из этой статьи

- Универсальный порог «2–4 agents, 5+ — плохо» — это эмпирика автора для конкретной модели и workflow.
- Saga/choreography как микросервисные паттерны — отсутствуют в первоисточнике по указанной ссылке.
- Полный переход на choreography — противоречит целям воспроизводимой оркестрации цепочек.

## 9. Рекомендации

| # | Рекомендация | Тип | Effort | Приоритет |
|---:|---|---|---|---|
| 1 | В `subagent`-правилах закрепить: status returns summary, full transcript only on explicit request (статус отдаёт сводку; полная стенограмма только по явному запросу). | `apply` | Low/Medium | P1 |
| 2 | Добавить heuristic (эвристику) cognitive locality: задачи с общей ментальной моделью, файлами или conventions (конвенциями) объединять в одного agent или один batch. | `apply` | Low | P2 |
| 3 | Запретить `git stash`, `git reset --hard`, repository-wide mutations (изменения всего репозитория) внутри concurrent subagents без явного разрешения orchestrator. | `apply` | Medium | P1 |
| 4 | Метрики контекста: raw transcript bytes/tokens imported, context truncation count, summary/read ratio. | `study` | Medium/High | P2 |
| 5 | При spawn (запуске) subagent передавать relevant skill file paths (пути к нужным skill-файлам), а не рассчитывать на наследование parent skills. | `apply` | Low/Medium | P1 |
| 6 | Не создавать approval gate (ворота подтверждения) на каждый spawn; добавлять подтверждение только при high-risk signals: 5+ agents, overlapping files, repository-wide operations. | `apply` | Low | P2 |

## 10. Итоговый вердикт

**Итог:** `apply` для process rules (правил процесса), `study` для измерения working memory quality (качества рабочей памяти), `skip` для saga/choreography в рамках именно этой статьи.

Статья валидирует архитектурную ставку task-orchestrator на централизованный, наблюдаемый orchestrator, но добавляет важный критерий качества: orchestrator должен быть защищён не только от технических сбоев runner'ов, но и от мусора в собственной рабочей памяти.

Главный практический принцип: **subagent должен возвращать orchestrator только тот результат, который заслуживает места в будущем контексте; весь disposable reasoning (одноразовое рассуждение) должен остаться в изолированном контексте subagent.**
