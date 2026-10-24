# Исследование: Книга Ω «Инженерия надёжных фреймворков на AI-агентах»

**Источник:** Ω, «Инженерия надёжных фреймворков на AI-агентах» (синтез корпуса переписки, 12 глав; внешний URL отсутствует — локальная копия в репозитории: [sources/omega-reliable-ai-agent-frameworks.html](sources/omega-reliable-ai-agent-frameworks.html))  
**Дата первоисточника:** сборка и авторская рецензия 05.08.2026 (корпус переписки — июль–август 2026)  
**Дата анализа:** 2026-10-24  
**Эпик:** [EPIC-research-orchestration-articles](../../../todo/EPIC-research-orchestration-articles.todo.md)  
**Задача:** [TASK-research-omega-reliable-frameworks](../../../todo/TASK-research-omega-reliable-frameworks.todo.md)  
**Аналитик:** Аналитик (Шерлок)

---

## 0. Важное уточнение по первоисточнику

Это не статья и не документация API, а **книга-синтез**: редактор собрал систему практик из полугода переписки практика под ником Ω, который гоняет на VPS конвейер AI-агентов (проекты по 250–500K строк). Рукопись построчно сверена с автором переписки: его исправления помечены в тексте «Правка автора» — редкий случай, когда описание сверено с работающим продакшеном.

Три системные оговорки для вердиктов:

1. **Книга — не полный чертёж.** По признанию автора, за кадром осталось больше половины конструкции (воркфлоу как цепочки скиллов, пайплайн как множество воркфлоу, конкретная модель хуков). Глава 11 (минимальная архитектура ядра) — проект книги, а не реконструкция конвейера Ω; сам автор называет её «сложнее и по-другому» устроенной.
2. **Числа — параметры конвейера Ω, а не нормы.** Пороги диспатчей (14/21 → 21/33 → 21/34 за полгода), деградация контекста после 200–300K токенов, ~73% шагов с первого захода, три реворка, mutation score 90/98% — всё это калибровано под его домен, масштаб и цену ошибки. Переносить принципы, не числа.
3. **Масштаб другой.** Конвейер Ω — ночные автономные прогоны десятков задач с волнами параллельных агентов. task-orchestrator — оркестрация цепочки по одной задаче с человеком на merge (слиянии). Часть паттернов (ночная автономность, waves, merge-координатор) адресует потребности, которых у нас пока нет — они оцениваются как `study` на будущее (подтверждают backlog parallel/DAG), а не как немедленные gaps.

Сравнение с другими источниками трека: [The Orchestrator's Tax](orchestrator-tax-research.md) дал проблему (context tax, загрязнение контекста оркестратора), [Pi Durable](pi-durable-research.md) — механизмы устойчивости (чекпойнты, resume, компенсация). Книга Ω — самый широкий источник трека: она систематизирует **всю вертикаль** — от спецификации и ролей до хуков, верификации, экономики и эксплуатации — и отвечает на вопрос «как удерживать вероятностные компоненты в рамках детерминированной процедуры».

## 1. Краткий вывод

**Вердикт по книге:** `apply` для принципов и практик, совпадающих с нашей архитектурой (валидация: roles/quality gates/fix-итерации/бюджеты уже соответствуют); `study` для механизмов, которых у нас нет (исполняемая permission matrix, hooks на рёбрах, словарь классов отказов, dispatch-бюджеты, parking, baseline provenance, adversarial verifier, production eval); `skip` для блоков вне нашего масштаба (ночная автономность, waves, merge-координатор, интерфейс для нетехнического фаундера, installer).

**Главная идея для task-orchestrator:** «Надёжность агентной разработки создаёт не самая умная модель, а архитектура ограничений, независимых проверок, изолированных контекстов и доказуемых переходов состояния». И уточняющий принцип, прямо относящийся к нам: **«Промпт — это policy (политика), enforcement (принуждение) — это код, sandbox (изолированная среда) и хуки»**. У нас сейчас почти весь контроль — процессный: AGENTS.md (правила агента) + человек на merge + quality gates. Книга показывает следующий уровень зрелости — контроль, который невозможно нарушить, а не контроль, который нельзя нарушать.

**Ключевая валидация:** наша модульная декомпозиция буквально совпадает с «минимальной архитектурой ядра» из главы 11: `ChainDefinition` = Product/Spec layer, `ChainExecution` + `DynamicLoop` = Controller/Scheduler с lifecycle, `AgentRunner` = role adapters (адаптеры ролей), JSONL audit = evidence store (хранилище доказательств), `GitIdentity` = delivery adapter в зачатке. По шкале зрелости книги (L0–L4) мы — **L2 «Verified pipeline»** с элементами L3: роли и независимая верификация есть, evidence привязан к шагам, но durable lifecycle (переживание сбоя процесса) есть только у `DynamicLoop` (ADR-010 отложен до Q4 2026), а хуков и capability broker (посредник полномочий) нет.

## 2. Методология эпика: 6 критериев

| Критерий | Оценка | Вывод для task-orchestrator |
|---|---:|---|
| 1. Тезис / проблема | ✅ | Надёжная система строится из ненадёжных (вероятностных) компонентов на архитектуре ограничений; контроль власти агента — задача кода, а не промпта. |
| 2. Паттерн / концепция | ✅ | Богатейший набор трека: функциональные роли, spec как источник истины, workflow как конечный автомат, hooks/permission matrix, лестница доказательств, типы памяти, портфель моделей, waves, классы отказов, уровни зрелости L0–L4. |
| 3. Domain (область) | ✅ | AI-agent frameworks и autonomous development systems — наш домен в точности; книга адресована создателям agent harness (каркасов агентов). |
| 4. Failure handling (обработка сбоев) | ✅ | Ядро книги: словарь классов отказов с маршрутами, parking ≠ failure, bounded rework, baseline provenance, safe replay, recovery-first scheduler, «инцидент должен изменить систему». |
| 5. Маппинг на архитектуру | ✅ | Прямой: компоненты ядра гл. 11 совпадают с нашими модулями; паттерны ложатся на `ChainDefinition`, `ChainExecution`, `DynamicLoop`, `AgentRunner`, `GitIdentity`; часть — на процесс (AGENTS.md, todo/). |
| 6. Применяемость | ✅ | `apply` — принципы и подтверждения текущей практики; `study` — механизмы следующего уровня; `skip` — вне масштаба. Числа конвейера Ω не копировать. |

## 3. Содержание первоисточника (по главам)

Главные тезисы книги (формулировки источника):

1. **Гл. 1 — система ненадёжных компонентов.** «Пайплайн агентов — это не организация из людей, а распределённая вычислная система»: декомпозировать по функциям, а не должностям; шесть приёмов теории надёжности (декомпозиция, изоляция, разнообразие, обратная связь, безопасный отказ, восстановление); оркестратор не пишет код; агентов запускать «с чистого листа», а не доливать контекст к старым выводам; решения о деньгах/legal/production/архитектуре — только человеку.
2. **Гл. 2 — спецификация как исполняемый источник истины.** Модель закрывает пробелы спеки статистически правдоподобным — gap (пробел) должен быть состоянием, а не приглашением к творчеству: протокол `CONTEXT_INSUFFICIENT` → проверка брифера → `SPEC_GAP` → решение владельца → запись в спеку. Цепочка трассировки intent → spec → architecture → task → brief → code → evidence; Zero Information Loss Policy при правках спеки; brief — «скомпилированный» контекст 5–10 КБ со ссылками на governing-документы (управляющие документы).
3. **Гл. 3 — функциональные роли и изоляция.** Минимальная цепочка: Orchestrator → Brief Agent → Implementation/Test Worker → Verifier (+ Explorer, Adversarial Verifier по необходимости); «тесты и код пишут всегда разные агенты»; проверяющий не исправляет проверяемое; permission matrix (матрица полномочий), обеспечиваемая controller (контроллером) и sandbox, а не промптом; запрет рекурсивной власти (дочерние агенты не создают исполнителей; исключение — read-only Explorer с капом).
4. **Гл. 4 — workflow как state machine (конечный автомат).** Три уровня планирования (Stage/Wave/Task); lifecycle задачи `planned → ready → in_progress → done | blocked | failed`; терминальные переходы принадлежат детерминированному controller, а не рассказу модели (живой инцидент: оркестратор самовольно закоммитил ветку — «модель была неглупая — она была убедительная»); dispatch budget и retry budget (норма/хардкап диспатчей; три полных реворка — дальше эскалация); parking ≠ failure (парковка с handoff-записью); recovery-first scheduler («сначала доказательство безопасности повтора — потом повтор»); baseline и frozen zones (замороженные зоны): красные тесты в замороженном модуле остаются красными, эскалация владельцу.
5. **Гл. 5 — hooks, permissions, границы.** «Промпт не является enforcement»; хуки живут на рёбрах (на каждом переходе: схема, роль, write boundary (граница записи), бюджеты, evidence, candidate SHA) и возвращают стабильные коды (`WRITE_BOUNDARY_VIOLATION`); capability design (полномочия правильной ширины вместо универсального shell); trust boundary (недоверенные данные: issue, PR, веб); хуки как метрика качества промпта и routing; чего хуки не дают (нужна изоляция уровня ОС).
6. **Гл. 6 — независимое тестирование.** «Зелёный тест ≠ правильный продукт»; развязка code/tests кратно дороже по токенам, но покупает независимость слепых зон; лестница доказательств (structural gates → focused behavioral → integration → independent semantic → adversarial); verdict привязан к candidate SHA (изменился коммит — approval устарел); review без вкусовщины (контракт blocking-находки); mutation testing (мутационное тестирование) по risk tier (уровню риска); rework loop с ограниченным бюджетом.
7. **Гл. 7 — контекст, память, восстановление.** Контекст — рабочая память, а не архив; деградация после 200–300K токенов — резать задачи так, чтобы вход ~80K; четыре типа памяти (durable product truth / durable execution state / ephemeral compiled context / observation journal); compaction (сжатие) — контролируемое событие: всё прошедшее compaction — переделывать из durable-состояния; handoff — схема с фактами, а не рассказ; authority order (порядок авторитетности: Git/CI-факты выше snapshot планировщика выше контекста чата); safe replay (безопасный повтор): класс side effect известен до запуска.
8. **Гл. 8 — портфель моделей и экономика.** «Не существует одной лучшей модели»: frontier (передовая модель) наверху для планирования, середняки на рельсы внизу; «детальный план прощает тупость исполнителей в определённом пределе»; разводить слепые зоны разными семействами моделей; production eval (производственная оценка) важнее публичных benchmark (эталонных тестов); наблюдение ≠ вывод (выборка 32 шага — «не сказал бы, что достаточно для доверительного интервала»); budgets как часть routing; метрики здоровья фреймворка.
9. **Гл. 9 — параллелизм, Git, delivery.** Параллелизм начинается с зависимостей: dependency graph, непересекающиеся write boundaries, worktree на задачу, conflict zones, baseline provenance (сравнивать дельту с зафиксированным baseline, а не «зелёный/красный»), same-SHA rule (все evidence и approvals привязаны к SHA), единственный merge owner (владелец слияния); «начните с одной дорожки; параллелизм — контролируемая оптимизация после доказанной изоляции».
10. **Гл. 10 — отказы, gaps, escalation.** Словарь классов ошибок (`SPEC_GAP`, `CONTEXT_INSUFFICIENT`, `IMPLEMENTATION_FAILURE`, `TEST_FAILURE`, `BASELINE_FAILURE`, `BOUNDARY_VIOLATION`, `ENVIRONMENT_FAILURE`, `SECURITY_HALT`, `BUDGET_EXCEEDED`, `OWNER_DECISION_REQUIRED`; боевой набор Ω — первые пять + бюджеты); матрица реакций; bounded rework («каждая новая попытка обязана иметь изменённое основание»); автономность при недоступном владельце (максимум безопасного прогресса + точные вопросы); наблюдения tech debt не чинятся в текущей задаче; «инцидент должен изменить систему» (исправление — в хуке/permission/фикстуре, не в промпте); hook admission filter (фильтр допуска хуков); «успешный failure» — containment (локализация) важнее безостановочности.
11. **Гл. 11 — минимальная архитектура ядра.** Универсальное ядро (контракты, lifecycle, evidence, границы) + специализируемый доменный слой; компоненты: Product/Spec layer, Task compiler, Controller/Scheduler, Role adapters, Sandbox + Capability broker, Hook engine, Evidence store, Delivery adapter; минимальные durable records (Task / Run checkpoint / Verdict); порядок реализации P0 (один безопасный вертикальный срез) → P1 (context/recovery) → P2 (независимость/риск) → P3 (масштабирование); критерии готовности v1 («после kill/restart задача восстанавливается без чата»).
12. **Гл. 12 — операционная инструкция.** Чек-листы запуска проекта, admission (допуска) задачи, перед запуском роли, наблюдения за прогоном, после failure, перед/после merge, post-incident review; анти-паттерны фреймворка (14 шт.); уровни зрелости L0 One-shot → L1 Bounded worker → L2 Verified pipeline → L3 Recoverable controller → L4 Risk-aware autonomous queue; «короткая конституция» из 10 правил.

## 4. Маппинг механизмов на task-orchestrator

| # | Механизм книги (глава) | Компоненты task-orchestrator | Текущее состояние | Вердикт | Effort |
|---:|---|---|---|---|---|
| 1 | Функциональные роли с одним выходом; оркестратор не пишет код (гл. 1, 3) | `config/chains.yaml` (roles), `src/Module/AgentRole/`, шаги `ChainStepVo` | Роли разведены по функциям (аналитик → архитектор → разработчики → ревьюер → тестировщик); каждый шаг — отдельный запуск агента с чистым контекстом; сам оркестратор (PHP-код) не генерирует контент — детерминированный по построению | `apply` — валидация: архитектура уже соответствует принципу | — |
| 2 | Spec как источник истины; протокол gap-эскалации `CONTEXT_INSUFFICIENT` → `SPEC_GAP` (гл. 2, 10) | Постановка задачи `todo/*.todo.md`, промпты ролей, `AGENTS.md` | Задача в todo — де-факто спецификация; AGENTS.md требует уточнять вопросы, но нет структурированного протокола «шаг вернул gap → маршрутизация к постановщику/владельцу» | `study` — протокол gap-эскалации на уровне шага цепочки | Medium |
| 3 | Развязка code/tests: тесты пишет агент, не видевший код (гл. 3, 6) | `config/chains.yaml` (роль `qa_backend_house` отдельно от `backend_developer_*`) | Роли разделены процессно; изоляции контекста (тестировщик не видит diff разработчика) как контракта нет | `study` — как контракт шага/цепочки; процессно уже практикуется | Low/Medium |
| 4 | Независимый Verifier + Adversarial Verifier для high-risk зон (гл. 3, 6) | Роль `code_reviewer_backend_puaro`, quality gates | Независимый ревьюер есть (другая роль/модель); adversarial-режим («какая жопа нехорошая может произойти» для денег/секьюрности) отсутствует | `study` — adversarial-шаг как опция цепочки по risk tier | Medium |
| 5 | Workflow как state machine; lifecycle задачи; терминальные переходы у controller (гл. 4) | `StaticChainExecution` (в памяти), `DynamicLoopExecution`, `OrchestrateExitCodeEnum` | Lifecycle есть у dynamic-сессий (journal, статусы раундов); static — линейный проход в памяти процесса; ADR-010 (checkpoint + resume для Static/Conditional) принят, реализация отложена до Q4 2026 | `study` — книга валидирует ADR-010 и добавляет: переходы — по артефактам, не по рассказу модели | High (ADR-010) |
| 6 | Dispatch budget — лимит вызовов агента на шаг/попытку (гл. 4) | `BudgetVo` (`max_cost_total`, `max_cost_per_step`, per role), `CheckStaticBudgetService` | Бюджеты только стоимостные; количество вызовов агента внутри шага не лимитируется (агент CLI сам решает, сколько внутренних диспатчей сделать) | `study` — dispatch/cycle-бюджеты как дополнение к cost-бюджетам (числа Ω не копировать) | Medium |
| 7 | Retry budget на rework: ограниченные итерации, дальше эскалация (гл. 4, 6) | `FixIterationGroupVo` (`max_iterations: 3`), `RunStaticChainService::findRetryGroup` | Точное совпадение: fix-итерации implement ↔ review с лимитом 3 — это и есть bounded rework книги | `apply` — валидация практики | — |
| 8 | Parking ≠ failure: парковка с handoff-записью и продолжением независимых ветвей (гл. 4, 10) | `RunStaticChainService` (сбой шага → стоп цепочки), backlog parallel/DAG | Парковки нет: сбой шага останавливает цепочку; нет ветвления по независимым путям (исполнение линейное) | `study` — парковка шага как состояние вместо «сбой = стоп»; полноценный вариант требует DAG | Medium/High |
| 9 | Recovery-first scheduler; safe replay: «сначала доказательство безопасности повтора — потом повтор» (гл. 4, 7) | ADR-010 (resume), `RetryingAgentRunnerService`, сессии `DynamicLoop` | Resume есть у dynamic (сессии JSONL); replay-классификация шагов (read-only / mutating — только чтение / с побочными эффектами) отсутствует — рекомендация #6 из [Pi Durable](pi-durable-research.md) | `study`/`apply` — книга независимо подтверждает replay-классификацию как предусловие resume | Medium |
| 10 | Hooks на рёбрах: детерминированные проверки переходов со стабильными кодами; write boundary (гл. 5) | `ShellHookExecutorService` (postStep — уведомления), quality gates, AGENTS.md | postStep-хуки есть, но это уведомления, а не enforcement; write boundary (разрешённые/запретные пути) и проверка diff против границы отсутствуют; сэндбокс — в backlog (`TASK-feat-docker-sandboxing`) | `study` — главный системный gap книги: enforcement как код | High |
| 11 | Permission matrix как исполняемая конфигурация роли (гл. 3, 5) | `RoleConfigVo` (prompt_file, command), `AgentRole` | Полномочия ролей заданы процессом (AGENTS.md, правила PR), не конфигурацией; per-role allowlist инструментов есть частично (`tools` у шага в `ChainStepVo`) | `study` — read/write/allowlist в контракте роли | Medium/High |
| 12 | Лестница доказательств: structural gates → focused → integration → semantic → adversarial (гл. 6) | Quality gates `make check` (phpunit, psalm, phpcs, deptrac), роли ревьюеров, fix-итерации | Structural gates и fix-loop реализованы; semantic verification (вердикт по acceptance criteria против постановки) — роль ревьюера без формального контракта; mutation testing нет | `apply` (лестница как чек-схема цепочек) / `study` (mutation — отдельная тема) | Low (схема) / High (mutation) |
| 13 | Verdict/evidence привязан к candidate SHA (гл. 6, 9) | JSONL audit (`JsonlAuditLoggerService`), `GenerateReportQueryHandler` | Audit фиксирует результаты шагов, но не привязывает их к SHA коммита кандидата (у нас коммиты делает человек/агент вне контракта цепочки) | `study` — recording SHA в audit при наличии | Low/Medium |
| 14 | Baseline provenance: известные падения до старта; сравнение дельты (гл. 4, 9) | `RunRoleStartupCheckCommandHandler` (connectivity), CI | Connectivity-проверка есть; фиксации baseline тестов (известные красные) и сравнения дельты нет | `study` — для долгих цепочек и будущего parallel | Medium |
| 15 | Типы памяти: durable product truth / execution state / ephemeral context / observation journal (гл. 7) | `todo/` (задачи + `done/`), сессии/journal dynamic, JSONL audit, `docs/` | Фактически разделено: постановка задачи (durable) ≠ контекст шага (ephemeral) ≠ журнал (execution state) ≠ ретро-доки (observation); контрактного разделения нет, mixing контролирует процесс | `apply` — принцип уже соблюдается; зафиксировать в документации | Low |
| 16 | Compaction — контролируемое событие; вход задачи ~минимально достаточный контекст (гл. 7) | `maxContextLength` (усечение), `BuildDynamicContextService`, `noContextFiles` | Контекст управляется (noContextFiles, ролевые промпты, компиляция dynamic-контекста по раундам); summary-компакции нет — рекомендация #10 из [Pi Durable](pi-durable-research.md) | `study` — подтверждает рекомендацию Pi Durable; у Ω радикальнее: «всё, что прошло через compaction, — в мусорку» | Medium/High |
| 17 | Портфель моделей по ролям; frontier наверху, середняки на рельсы (гл. 8) | `config/chains.yaml`: на роль — своя команда/модель (codex `gpt-6-sol`, `gpt-6-astra` xhigh, pi+`gpt-5.6-terra`, glm...) | Уже практикуем: разные провайдеры/модели на разных ролях; fallback-раннер — backlog (`TASK-feat-runner-provider-fallback`) | `apply` — валидация практики; `study` — production eval (метрики first-pass/реворков по ролям) | Low / Medium |
| 18 | Бюджеты как часть routing; политика при исчерпании (гл. 8) | `BudgetVo`, `CheckStaticBudgetService` (total/per-step/per-role), `DynamicLoopBudgetVo` | Реализовано: стоимости на цепочку/шаг/роль; поведение при исчерпании — стоп цепочки с кодом | `apply` — валидация практики | — |
| 19 | Параллелизм после доказанной изоляции: waves, worktree на задачу, conflict zones, same-SHA rule, merge owner (гл. 9) | Backlog: `TASK-feat-parallel-execution`, `TASK-feat-dag-orchestration`, `TASK-feat-docker-sandboxing`; процессные правила PR в `AGENTS.md`/`docs/git-workflow/` | Исполнение последовательное; same-SHA и merge-owner частично покрыты процессом (PR от актуальной ветки, merge только человеком); worktree/waves нет | `study` — книга задаёт предусловия для parallel-задач backlog: сначала write boundaries и изоляция | High |
| 20 | Словарь классов отказов + матрица реакций (гл. 10) | `ErrorClassificationEnum` (fatal/transient/unknown — на уровне runner), `OrchestrateExitCodeEnum`, interrupt с причиной в dynamic | Классификация есть только у ошибок раннера; у цепочки нет различения spec gap / insufficient context / baseline failure / boundary violation с разными маршрутами | `study` — расширение словаря на уровень шага (начать с 5 классов, как у Ω) | Medium |
| 21 | «Инцидент должен изменить систему»: post-incident → хук/permission/фикстура (гл. 10) | Ретро-процесс проекта (`docs/agents/skills/`), `todo/` техдолг, `@techdebt`-правила AGENTS.md | Ретро и регистрация техдолга есть; формального правила «каждый инцидент → детерминированное изменение» нет | `apply` — как принцип ретро-процесса | Low |
| 22 | Hook admission filter: хук оправдан реальным отказом (гл. 10, 12) | Процесс принятия задач `todo/` | Прямого аналога нет (нет хуков) | `apply` — вместе с #10: вносить в конвенцию, чтобы не обрастать запретами | Low |
| 23 | Уровни зрелости L0–L4; порядок реализации P0→P3 (гл. 11, 12) | Дорожная карта проекта, backlog | Не формализованы; текущее состояние — L2 с элементами L3 (см. §7) | `apply` — как система координат для дорожной карты | Low |
| 24 | Founder interface: start/status/run/review; outcome-first статус (гл. 11) | CLI-отчёты `GenerateReportQueryHandler` (text/json) | Мы — библиотека для технического пользователя; «нетехнический фаундер» вне целевой аудитории; outcome-first формулировки статуса полезны для отчётов | `skip` (интерфейс) / `study` (карточка статуса для отчётов) | Low |
| 25 | Ночная автономность при недоступном владельце (гл. 4, 10) | `AGENTS.md` (merge только по явному подтверждению) | Принципиально не наш режим: человек в контуре — by design, правила запрещают автоматический merge | `skip` — осознанно: наша цена ошибки и модель владения другие | — |
| 26 | Installer/обновления framework-owned vs project-owned (гл. 11) | Composer-пакет, `config/modules.php` | Потребности нет: мы — библиотека, устанавливаемая composer | `skip` | — |

## 5. Маппинг по главам на модули проекта

Обязательный маппинг каждой из 12 глав ( Must Have задачи):

| Глава книги | Модули task-orchestrator | Соответствие / gap |
|---|---|---|
| 1. Система ненадёжных компонентов | `AgentRunner` (retry, circuit breaker, liveness), `ChainDefinition` (декомпозиция на шаги), процесс `AGENTS.md` (человек на материальных решениях) | Соответствие сильное: один шаг — одна функция; retry/circuit breaker = обратная связь и безопасный отказ; «человек на merge» = разделение «что оставлять человеку». Gap: разнообразие моделей на независимых ролях — практика есть, контракта нет. |
| 2. Спецификация как источник истины | `todo/*.todo.md` (спецификация задачи), `AgentRole` (роли/скиллы как вход), `ChainStepVo` (`noContextFiles` — контроль контекста шага) | Частично: постановка задачи — реальный источник истины, цепочки стартуют от неё. Gap: нет протокола `SPEC_GAP`, нет трассировки acceptance → evidence. |
| 3. Роли и изоляция контекста | `config/chains.yaml` (roles: аналитик, архитекторы, разработчики, ревьюер, QA, писатели), `AgentRole` (`ResolveRoleSkillsService`), `DynamicLoop` (участники с изолированными сессиями) | Соответствие по духу: роли разведены, QA отдельно от разработчиков, dynamic-участники изолированы. Gap: нет Explorer-роли (read-only поиск), нет Adversarial Verifier, permission matrix не исполняемая. |
| 4. Workflow как state machine | `ChainExecution` (`RunStaticChainService`, `StaticChainExecution`, fix-итерации, бюджеты, `OrchestrateExitCodeEnum`), `DynamicLoop` (lifecycle сессии/раундов, interrupt/resume), ADR-010 | Смешанное: у dynamic — полный lifecycle с journal/resume (реализация L3-уровня), у static — линейный проход в памяти. Книга валидирует ADR-010 и добавляет parking и dispatch-бюджеты. |
| 5. Hooks, permissions, границы | `ChainExecution/Infrastructure/Service/Chain/Hook/ShellHookExecutorService` (postStep), quality gates, `ChainStepVo.tools`, backlog `TASK-feat-docker-sandboxing` | Главный gap: хуки-уведомления ≠ enforcement; write boundary отсутствует; сэндбокс в backlog. Прямое продолжение темы «оркестратор не пишет код» — у нас это обеспечено детерминированностью PHP-оркестратора. |
| 6. Многоуровневая верификация | Quality gates (`make check`: PHPUnit, Psalm, PHP_CodeSniffer, Deptrac), `FixIterationGroupVo` (rework ≤ 3), роли `code_reviewer_backend_puaro` / `qa_backend_house` | Соответствие сильное: лестница gates → fix-loop → независимый ревьюер — рабочий процесс проекта. Gap: verdict не привязан к SHA, mutation testing нет, adversarial-уровня нет. |
| 7. Контекст, память, восстановление | `AgentRunner` (`RunAgentCommandHandler`, `maxContextLength`), `DynamicLoop` (`BuildDynamicContextService`, session/journal), JSONL audit, `todo/done/` | Соответствие: минимальный контекст шага (ролевой промпт + контекстные файлы), journal по раундам, «история ≠ источник истины» — постановка задачи первична. Gap: compaction-стратегии нет, replay-классификации нет (см. Pi Durable). |
| 8. Портфель моделей, экономика | `config/chains.yaml` (per-role command/model), `AgentRunner` (`AgentRunnerRegistryService`, fallback `FallbackConfigVo`, `CircuitBreakerAgentRunnerService`), `BudgetVo`, `DynamicLoopMetrics` | Соответствие сильное: портфель моделей по ролям уже в конфигурации, бюджеты total/per-step/per-role, метрики dynamic. Gap: production eval по ролям (first-pass, реворки) не собирается систематически. |
| 9. Параллелизм, Git, delivery | `GitIdentity` (идентичность бота для GitHub-операций), `AGENTS.md` + `docs/git-workflow/` (PR-процесс, стеки запрещены, merge человеком), backlog parallel/DAG | Соответствие процессное: same-SHA («PR-ветка от актуальной main, стеки запрещены») и merge owner («merge только по подтверждению») — правила процесса. Gap: технической изоляции (worktree, boundaries) нет — предусловия для parallel-задач. |
| 10. Отказы, gaps, escalation | `AgentRunner` (`ErrorClassificationEnum` fatal/transient/unknown), `ChainExecution` (`OrchestrateExitCodeEnum`, budget stop), `DynamicLoop` (interrupt с причиной), ретро-скиллы, `todo/` | Частично: классификация на уровне runner есть, природа сбоев шага (spec gap vs insufficient context) не различается; «инцидент → система» практикуется через ретро. Gap: словарь классов цепочки и матрица реакций. |
| 11. Минимальная архитектура ядра | Вся структура: `ChainDefinition` = Spec layer, `ChainExecution`/`DynamicLoop` = Controller, `AgentRunner` = Role adapters, JSONL audit = Evidence store, `GitIdentity` = Delivery adapter | Валидация: компоненты ядра книги один-в-один ложатся на наши модули. Gap относительно книги: Sandbox/Capability broker и Hook engine отсутствуют; run checkpoint только у dynamic. |
| 12. Операционная инструкция | `AGENTS.md` (мини-чеклист самопроверки, запреты, конституция проекта), `todo/AGENTS.md` (регламент задач), `docs/git-workflow/pull-request.md` (чек-листы PR) | Соответствие сильное: наш AGENTS.md — это «короткая конституция» книги в миниатюре (коммит по запросу, merge по подтверждению, задачи в done перед merge). Gap: admission-чеклист для допуска задачи к цепочке не формализован. |

## 6. Failure handling: что уже закрыто и что нет

| Риск / механизм | У нас закрыто? | Комментарий |
|---|---:|---|
| Временный сбой раннера | ✅ | `RetryingAgentRunnerService` + `ErrorClassificationEnum`. |
| Недоступность раннера/провайдера | ✅ | Circuit breaker (`closed → open → half_open`), fallback-конфигурация. |
| Зависание процесса раннера | ✅ | `ProcessLivenessWatcher` — контроль живости процесса. |
| Превышение стоимости | ✅ | `CheckStaticBudgetService`: total / per-step / per-role, стоп с кодом. |
| Качество результата шага | ✅ | Quality gates + fix-итерации (bounded rework ≤ 3) + независимый ревьюер. |
| Ошибка агента в dynamic loop | ✅ | interrupt с причиной, journal, метрики. |
| Возобновление прерванной сессии | ⚠️ | Только dynamic (JSONL-сессии); static/conditional — ADR-010 отложен до Q4 2026. Книга: recovery-first + «сначала доказательство безопасности повтора». |
| Повтор мутирующего шага (replay) | ❌ | Replay-классификации шагов нет (рекомендация [Pi Durable](pi-durable-research.md) #6; книга гл. 4.7, 7.8 подтверждает). |
| Выход агента за границы записи | ❌ | Write boundary и enforcement отсутствуют; защита — процесс (AGENTS.md) и ревью человека. Главный gap книги для нас. |
| Парковка вместо сбоя | ❌ | Сбой шага = стоп цепочки; parking-состояния нет. |
| Baseline-отличение (красный до нас или из-за нас) | ⚠️ | Процессуально (человек знает известные падения); фиксации baseline нет. |
| Классификация отказа по природе | ⚠️ | fatal/transient/unknown на runner; spec gap / insufficient context / baseline failure не различаются. |
| Инцидент → системное изменение | ⚠️ | Ретро-процесс есть; формального правила «исправление в хуке/фикстуре, не в промпте» нет (хуков нет). |

## 7. Проверка гипотезы: уровень зрелости по шкале книги

Шкала L0–L4 из гл. 12.10 приложена к task-orchestrator:

| Уровень (по книге) | Критерий книги | task-orchestrator |
|---|---|---|
| L0 One-shot | один агент, ручная проверка | Пройден исторически |
| L1 Bounded worker | контракт задачи, write boundary, тесты | Частично: контракт шага и бюджеты есть; write boundary нет |
| L2 Verified pipeline | брифер/воркер/верификатор, evidence, ограниченные повторы, PR/CI | **Да**: роли, quality gates, fix-итерации ≤ 3, JSONL audit, PR + CI + ревью человека |
| L3 Recoverable controller | durable lifecycle, checkpoint, парковка, capability broker, хуки | **Частично**: durable-сессии с resume только у `DynamicLoop`; парковки, capability broker и hook engine нет; ADR-010 (checkpoint для static) отложен |
| L4 Risk-aware autonomous queue | волны, routing, adversarial-роли, человек только на материальном | Нет (и частично осознанно: автономный merge запрещён процессом) |

**Вывод:** мы — устойчивый L2 с элементами L3. Книга не требует спешить вверх по шкале: «переход на следующий уровень оправдывается реальными failure cases и evals — не тем, что на конференции показали красивый сварм». Наши реальные failure cases (потеря прогресса длинной static-цепочки при сбое, риск двойного побочного эффекта при повторе мутирующего шага) указывают на тот же L3-набор, что и [Pi Durable](pi-durable-research.md): durable-журнал с resume + replay-классификация. Порядок реализации P0→P3 книги совпадает с направлением нашего backlog (сначала один безопасный вертикальный срез и resume, потом изоляция и только затем параллелизм).

Дополнительно книга валидирует ключевые положения [The Orchestrator's Tax](orchestrator-tax-research.md): «оркестратор — не самый умный разработчик команды» (= тонкий orchestrator), «агентов запускать с чистого листа» (= cognitive locality, когнитивная локальность), минимальный контекст (= context tax). И закрывает тему, которой не было ни в одном источнике трека: **экономику верификации** — «строгость пропорциональна цене ошибки», глубина проверки следует risk tier.

## 8. Что делаем правильно / gaps / не применимо

### Уже делаем правильно (валидация книги)

- **Один шаг — одна функция, роли разведены** (гл. 1, 3): цепочка analyst → architect → devs → reviewer → QA; каждый шаг — новый агент с чистым контекстом.
- **Детерминированный оркестратор** (гл. 3, 4): PHP-код оркестрации не пишет контент — терминальные решения принадлежат коду и человеку, «оркестратор физически не может писать код» у нас выполнено по построению.
- **Bounded rework** (гл. 4, 6): fix-итерации с `max_iterations: 3` — буквально retry budget книги.
- **Бюджеты** (гл. 8): total/per-step/per-role до старта, стоп при исчерпании.
- **Портфель моделей по ролям** (гл. 8): разные модели/провайдеры на ролях уже в `chains.yaml`.
- **Разделение типов памяти де-факто** (гл. 7): постановка задачи ≠ контекст шага ≠ журнал ≠ ретро-наблюдения.
- **Человек на материальных решениях** (гл. 1, 12): merge только по явному подтверждению, автоматические коммиты запрещены — «короткая конституция» AGENTS.md.
- **Independent verification** (гл. 6): ревьюер и QA — отдельные роли, отличные от исполнителей.

### Gaps (кандидаты в доработки)

1. **Enforcement как код** (гл. 5): хуки-уведомления вместо проверок границ; write boundary отсутствует. Самое системное расхождение с книгой.
2. **Словарь классов отказов цепочки** (гл. 10): нет различения spec gap / insufficient context / boundary violation с разными маршрутами реакции.
3. **Replay-классификация шагов** (гл. 4.7, 7.8): подтверждает gap, ранее выявленный [Pi Durable](pi-durable-research.md).
4. **Dispatch-бюджеты** (гл. 4): лимит вызовов агента в попытке — дёшево считаемая альтернатива/дополнение стоимостным бюджетам.
5. **Parking ≠ failure** (гл. 4.6): нет состояния «отложено с handoff» вместо «сбой = стоп».
6. **Baseline provenance** (гл. 9.5): нет фиксации известных красных тестов до старта.
7. **Production eval по ролям** (гл. 8): метрики first-pass/реворков/стоимости по ролям не агрегируются (заготовки в `DynamicLoopMetrics` и отчётах есть).
8. **Adversarial verifier** (гл. 3.8): нет специализированной «злой» проверки для high-risk изменений.

### Не применимо из этой книги

- **Ночная автономность** (гл. 4.8, 10.5): наш процесс требует человека на merge by design.
- **Waves/parallel worktrees/merge-координатор** (гл. 9): нет параллельного исполнения; вернёмся при реализации parallel/DAG-задач.
- **Founder interface** (гл. 11): мы — библиотека для технических пользователей.
- **Installer и framework/project-owned разделение** (гл. 11.7): composer-пакет, потребности нет.
- **Числа конвейера Ω** (диспатчи 21/34, 200–300K контекста, 73% first-pass, mutation 90/98%): параметры чужого домена; переносить принципы.

## 9. Рекомендации

| # | Рекомендация | Тип | Effort | Приоритет |
|---:|---|---|---|---|
| 1 | Словарь классов отказов шага/цепочки (начать с 5: `spec_gap`, `context_insufficient`, `implementation_failure`, `test_failure`, `budget_exceeded`) с маршрутами реакций — расширение `ErrorClassificationEnum`/`OrchestrateExitCodeEnum` на уровень шага. | `study` | Medium | P2 |
| 2 | Replay-классификация шагов (`read-only` / `mutating`) как предусловие resume — независимо подтверждено книгой (гл. 4.7, 7.8) и [Pi Durable](pi-durable-research.md#9-рекомендации). | `apply` (дизайн) | Medium | P1 |
| 3 | Dispatch-бюджет шага: лимит вызовов/циклов верификации на попытку (дополняет стоимостные бюджеты; свои пороги, не числа Ω). | `study` | Medium | P2 |
| 4 | Состояние «parking» для шага (сброс с handoff-записью вместо «сбой = стоп цепочки») — как первый шаг к DAG. | `study` | Medium/High | P3 |
| 5 | Enforcement-хуки на рёбрах шагов со стабильными кодами (первый кандидат — проверка write boundary: разрешённые/запретные пути в `ChainStepVo` + сверка фактического diff); связать с `TASK-feat-docker-sandboxing`. | `study` | High | P2 |
| 6 | Baseline provenance: фиксация известных падений до запуска цепочки, сравнение дельты после. | `study` | Medium | P3 |
| 7 | Adversarial-шаг (роль) для high-risk изменений по risk tier — расширение `config/chains.yaml`. | `study` | Medium | P3 |
| 8 | Production eval по ролям: агрегировать first-pass rate, число fix-итераций и стоимость из audit/сессий — данные для routing моделей. | `study` | Medium | P2 |
| 9 | Зафиксировать в документации принцип «промпт — policy, enforcement — код» и «инцидент → детерминированное изменение (хук/правило/фикстура), не абзац в промпте» — правило ретро-процесса. | `apply` | Low | P1 |
| 10 | Карточка статуса прогона в outcome-first формате (outcome, progress, budget, next, needs-you) для CLI-отчётов. | `study` | Low | P3 |
| 11 | Сверка дорожной карты со шкалой L0–L4 и порядком P0→P3 книги: наши backlog-приоритеты (resume → изоляция → parallel) совпадают с книгой — использовать как обоснование. | `apply` | Low | P2 |

## 10. Итоговый вердикт

**Итог:** `apply` для принципов и подтверждений текущей практики (валидация архитектуры: компоненты ядра книги = наши модули; fix-итерации = bounded rework; бюджеты; портфель моделей; человек на материальных решениях), `study` для механизмов следующего уровня (enforcement-хуки и write boundary — главный системный gap; словарь классов отказов; dispatch-бюджеты; parking; baseline provenance; adversarial-верификация; production eval), `skip` для блоков вне нашего масштаба и модели владения (ночная автономность, waves, founder-интерфейс, installer, числа конвейера Ω).

Книга — первый источник трека, дающий **целостную систему координат**: она связывает все предыдущие находки трека в одну вертикаль (spec → роли → state machine → hooks → верификация → контекст → экономика → параллелизм → отказы) и добавляет недостающие измерения — экономику верификации («строгость пропорциональна цене ошибки»), уровни зрелости L0–L4 и доктрину «prompt — это policy, enforcement — это код».

**Самостоятельная ценность для task-orchestrator:** трезвая самооценка (L2 с элементами L3) и подтверждение направления backlog: сначала durable-исполнение с безопасным повтором (то же, что показал [Pi Durable](pi-durable-research.md)), затем изоляция и enforcement, и только потом параллелизм. Главный практический принцип: **надёжность создаётся не самой умной моделью, а архитектурой ограничений и доказуемых переходов — и чем точнее границы, тем меньше требуется от модели.**
