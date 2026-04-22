# Архитектурное ревью: Исследование Agno (agno-comparison.md)

**Роль:** Архитектор Локи (System Architect)
**Дата:** 2026-04-22
**Объект:** `docs/research/agno-comparison.md` — исследование Python-фреймворка Agno vs task-orchestrator
**Задача:** Найти слепые зоны, слабые аргументы и необоснованные выводы

---

## Рефлексия

- 🧩 **Сложность запроса:** 7/10 — кросс-языковое архитектурное сравнение с рекомендациями по заимствованию паттернов
- 🗂️ **Уровень контекста:** 6/10 — есть кодовая база проекта и исследуемый документ, но нет доступа к исходникам Agno для верификации утверждений аналитика
- 🛡️ **Риск ошибки:** 5/10 — анализ основан на исследовании, которое я не могу полностью верифицировать; возможны неточности в интерпретации Agno

---

## Замечания

### BLIND_SPOT-01: Error handling при nested workflows

**Пункт исследования:** §3.1 Workflow Engine, §3.4 HITL, сводка §5 (Nested workflows → P3)

**Проблема:** Документ описывает вложенные workflows (до 10 уровней), но не анализирует, **что происходит при ошибке на уровне 5 вложенности**. Agno workflow как Step — это стек вызовов. Если nested workflow падает:

- Как происходит unwind? Механизм `on_error: "fail" | "skip" | "pause"` описан только для HITL-режима, а для обычных шагов?
- Есть ли partial state recovery? Агент упоминает session persistence, но не анализирует, восстанавливается ли состояние mid-workflow после сбоя.
- Как распространяется ошибка наверх? Exception propagation между уровнями вложенности не описана.

**Почему важно:** Если мы заимствуем nested workflows (рекомендация P3), мы обязаны сначала определить модель обработки ошибок. Наш `ChainDefinitionVo` сейчас flat — один уровень шагов. Введение вложенности создаёт совершенно иную поверхность ошибок.

---

### BLIND_SPOT-02: Race conditions и shared state в Parallel

**Пункт исследования:** §3.1 (Parallel), сводка §5 (Parallel execution → P3)

**Проблема:** Документ рекомендует Parallel execution, но полностью игнорирует фундаментальное различие сред:

- **Agno (Python):** async/await + asyncio. Parallel — это cooperative multitasking, не true parallelism. GIL гарантирует отсутствие data races на уровне Python-объектов.
- **task-orchestrator (PHP):** синхронная модель. CLI-процесс однопоточный. «Параллельность» требует `proc_open`, `pcntl_fork`, ReactPHP или Symfony Process — совершенно другую модель, нежели Agno.

Документ не анализирует:
- Как Agno решает shared state между parallel шагами (race conditions при записи в session)
- Что происходит, если один из parallel-шагов падает (cancel others? wait all? partial results?)
- Семантику слияния результатов parallel-шагов

**Почему важно:** Простое «возьмём Parallel» — это скрытый architectural spike. Реализация в PHP потребует принципиально иных механизмов, нежели в Agno, со своей моделью ошибок и состояний.

---

### BLIND_SPOT-03: Production readiness Agno

**Пункт исследования:** §1 Обзор проекта (версия 2.5.17)

**Проблема:** Документ анализирует код Agno, но не оценивает production readiness:

- Проект переименован из Phi → Agno, что может указывать на instability или major pivot
- Версия 2.5.x при возрасте проекта — высокий темп изменений (potential breaking changes)
- Нет данных о: production deployments, known issues, backward compatibility policy, community stability
- `agent.py` — 1729 строк в одном файле. Это god class. Для фреймворка с 40+ провайдерами и 12+ DB-адаптерами это architectural smell.

**Почему важно:** Мы строим рекомендации по заимствованию паттернов из проекта, чья архитектурная стабильность не верифицирована. Паттерн из v2.5 может исчезнуть в v3.0.

---

### BLIND_SPOT-04: Session persistence как prerequisite для HITL и Parallel

**Пункт исследования:** §2 (сравнительная таблица — Session persistence → «Позже»)

**Проблема:** HITL (P3) и Parallel (P3) оба требуют session persistence для работы. Но:

- HITL: человек подтверждает → процесс должен уметь «заснуть» и «проснуться». В CLI это означает: завершить процесс, сохранить состояние, восстановить при следующем запуске.
- Parallel: нужно собирать результаты из нескольких subprocess-ов, что требует intermediate state storage.
- Nested workflows: состояние вложенных шагов требует persistence для recovery.

Сейчас у нас **in-memory execution** (см. `CircuitBreakerAgentRunner` → `private array $states = []`). Session persistence отмечен как «Позже», но логически он — **prerequisite** для половины P2/P3-рекомендаций.

**Почему важно:** Приоритизация неверна. Session persistence должен идти *до* HITL, Parallel и Nested workflows, а не параллельно с ними.

---

### BLIND_SPOT-05: Агностичность Runner'ов к оркестрации

**Пункт исследования:** §3.3 FallbackConfig, §3.6 CompressionManager

**Проблема:** Agno — **SDK-фреймворк**: он вызывает LLM API напрямую, в процессе. Наши runner'ы (`PiAgentRunner`, будущий Codex) — **CLI-обёртки**: они запускают внешний процесс и парсят JSONL.

Фундаментальное различие:
- Agno: `model.generate()` → context window контролируем, tool results доступны в памяти, compression тривиальна
- task-orchestrator: `proc_open('pi ...')` → context window неизвестен, tool results = JSONL-файл, compression = перезапуск процесса

Документ рекомендует `CompressionManager` (P3), но не обсуждает, что **LLM-based compression наших tool results — это дополнительный вызов runner'а**, который стоит денег и времени. В Agno это «дешёвый» вызов к уже подключённому API. У нас — полноценный запуск агента.

**Почему важно:** «Интересные» паттерны Agno могут иметь радикально иную стоимость в нашей архитектуре.

---

### WEAK_ARGUMENT-01: Loop с end_condition (P2) —callable в YAML

**Пункт исследования:** §3.5, §5 (Loop с end_condition → P2)

**Проблема:** Agno использует **Python callables** и CEL-выражения для `end_condition`. Наш `FixIterationGroupVo` не имеет `end_condition` — только `maxIterations`. Автор рекомендует «усиление», но:

- Callable-условия (`lambda outputs: outputs[-1].success`) **невозможно выразить в YAML** без внедрения DSL.
- CEL отклонён в §4.6 как «избыточная зависимость» — справедливо. Но тогда остаётся только… что?
- Варианты: (а) предопределённые conditions (`last_success`, `all_success`, `quality_gate_passed`), (б) shell-команда как condition (наш existing pattern), (в) Symfony Expression Language.
- Ни один вариант не проанализирован. Рекомендация P2 без конкретного «как».

**Стоимость:** Не просто «усиление» — это новый VO `EndConditionVo`, новый enum `EndConditionTypeEnum`, extension `ChainDefinitionVo`, изменение `ExecuteStaticChainService`, новый YAML-парсинг, тесты. Это **medium feature**, не «усиление».

---

### WEAK_ARGUMENT-02: Error-specific fallback (P2) — у нас уже есть fallback

**Пункт исследования:** §3.3, §5 (Error-specific fallback → P2)

**Проблема:** Документ рекомендует error-specific fallback как «дополнение к circuit breaker», но не анализирует нашу существующую реализацию:

- `FallbackConfigVo` уже поддерживает fallback-команду для роли (cm. код: `getRunnerName()`, `getCommand()`)
- `ChainStepVo` имеет `retryPolicy`
- `CircuitBreakerAgentRunner` защищает от cascade failures
- В YAML-конфигурации `roles.xxx.fallback` уже настраивается

Автор не проводит gap analysis с нашей существующей реализацией. Непонятно, что **конкретно** мы не можем сделать сейчас. Рекомендация выглядит как «Agno делает иначе → возьмём», а не как «у нас проблема X → Agno решает X».

---

### WEAK_ARGUMENT-03: Conditional branching (P2) — скрытая стоимость DAG

**Пункт исследования:** §3.1, §5 (Conditional branching → P2)

**Проблема:** Conditional branching превращает линейную цепочку в **directed graph**. Это не «добавим Condition в YAML» — это смена фундаментальной модели выполнения:

- `ExecuteStaticChainService` сейчас проходит `steps[]` последовательно. С branching нужен graph executor.
- `ChainStepVo` — линейный. Нужен `NextStepResolver` с support condition → step mapping.
- `FixIterationGroupVo` — тоже линейный (first → last → first). С branching в группе — это цикл в графе.
- Audit trail (`JsonlAuditLogger`) рассчитывает линейный порядок. С branching нужен tree/graph audit.

**Стоимость:** Это не P2, а **major architectural change**. Оценка снизу: 3–5 новых VO, новый graph executor, переработка YAML schema, переработка audit, новый набор integration-тестов. Честная оценка — **P1 отдельного epic'а**, не пункт в списке.

---

### WEAK_ARGUMENT-04: Tool hooks (P2) — «альтернатива» работающему паттерну

**Пункт исследования:** §3.7, §5 (Tool hooks → P2)

**Проблема:** Документ описывает tool hooks как «альтернативу decorator pattern», но наш decorator pattern (`RetryingAgentRunner`, `CircuitBreakerAgentRunner`) — **DDD-compliant**, работает и покрыт тестами. Зачем «альтернатива»?

- Agno tool hooks — это middleware вокруг LLM tool calls (function calls внутри агента). У нас **нет tool calls** — runner'ы — это чёрные ящики (CLI-процессы).
- «Per-step middleware» уже решается через `QualityGateRunner` и `JsonlAuditLogger` в Infrastructure.
- Внедрение «hooks» как альтернативы decorator — это **два параллельных механизма** для одной задачи.

**Что не хватает:** Конкретного use case, который не покрывается текущим decorator pattern. Без него рекомендация — solution looking for a problem.

---

### WEAK_ARGUMENT-05: CompressionManager (P3) — скрытая стоимость LLM-вызова

**Пункт исследования:** §3.6, §5 (Compression → P3)

**Проблема:** В Agno compression = один API call к LLM (модель уже подключена, токены в контексте). В task-orchestrator compression = **запуск нового agent-процесса**:

- Стоимость: отдельный runner call, который **тратит бюджет** (который мы контролируем через `BudgetVo`)
- Время: latency полного CLI-запуска
- Контекст: runner не имеет доступа к предыдущему контексту (мы stateless) — нужно передать весь output для сжатия

**Круговая зависимость:** Compression активируется при context overflow → compression вызывает runner → runner тратит бюджет → budget exceeded → compression сама превысила бюджет.

Это не «LLM-based сжатие» — это «запуск нового агента для сжатия output предыдущего агента». Совершенно иная стоимость, нежели в Agno.

---

### ALTERNATIVE_VIEW-01: Агностичность «у нас лучше»

**Пункт исследования:** §2 Сравнительная таблица

**Проблема:** В 7 из 16 строк таблицы автор пишет «✅ У нас лучше». Это оптимистичная интерпретация:

| Критерий | Утверждение | Контраргумент |
|---|---|---|
| Retry | «У нас лучше» (exponential backoff) | Agno `max_retries` + FallbackConfig — разные механизмы. Retry ≠ Fallback. У Agno retry на уровне step, у нас на уровне runner. Не apples-to-apples. |
| DDD-архитектура | «У нас лучше» | Банально. Agno — SDK-фреймворк, не enterprise app. Сравнивать DDD-слоистость CLI-утилиты с Python SDK — некорректный критерий. |
| Decorator pattern | «У нас лучше» | Agno использует hooks (pre/post) — другой паттерн с другими trade-offs. Hooks гибче для composition, decorators — для single-responsibility. Нельзя declare winner без контекста. |
| Quality Gates | «Разный фокус» | Но это единственная честная строка. Почему остальные не «разный фокус»? |

**Вывод:** Сравнение страдает confirmation bias — мы ищем, где мы «лучше», а не где Agno решает проблемы, которых у нас нет.

---

### ALTERNATIVE_VIEW-02: HITL в CLI-утилите — архитектурное противоречие

**Пункт исследования:** §3.4, §5 (HITL → P3)

**Проблема:** HITL в Agno работает потому, что Agno — long-running service (FastAPI runtime). Процесс живёт, WebSocket открыт, пользователь может подтвердить в UI.

В task-orchestrator (CLI):
- Процесс запускается из терминала
- Если нужно «подтверждение» — это `readline('Confirm? [y/n]')` — тривиально
- Но если нужно «output review» — кто ревьюит? Пользователь сидит и ждёт? Это блокирующий ввод.
- А если цепочка из 20 шагов и на каждом HITL? Пользователь сидит 40 минут?

HITL делает sense для **interactive** режима, но ломает **autonomous** режим. Документ не разделяет эти сценарии.

---

### ALTERNATIVE_VIEW-03: Team routing → dynamic chain facilitator

**Пункт исследования:** §3.2, §5 (Team routing → P3)

**Проблема:** Документ описывает `route` mode Team как routing к специалисту. Но наш `dynamic` chain с facilitator уже делает это:

- Facilitator решает, какой participant вызывает (`determine_input_for_members` в Agno ≈ `facilitatorContinuePrompt` у нас)
- `route` mode = facilitator, который выбирает **одного** участника вместо **всех**
- Это не новая фича — это **режим существующего dynamic chain**: вместо `broadcastAll` → `pickOne`

Документ не видит этого соответствия и рекомендует «Team routing» как отдельную фичу, хотя это может быть **параметром** `dynamic` chain (`mode: broadcast | route`).

---

### ADOPTION_RISK-01: YAML → Turing-complete DSL

**Пункт исследования:** §3.1 (Workflow blocks), §3.5 (CEL), §5 (P2: Conditional branching, Loop с end_condition)

**Проблема:** Если мы добавим Conditional branching + Loop с end_condition + Router — наш YAML-chain станет **Turing-complete DSL**. Это:

- **Теряем декларативность.** YAML-chain сейчас — простая декларация. С conditions и routers — это программа.
- **Теряем auditability.** Простую цепочку можно прочитать за 30 секунд. Граф с условиями — за 30 минут.
- **Теряем тестируемость.** YAML-chain сейчас тестируется через integration tests. С DSL нужны unit-тесты для каждого expression.
- **Нарушаем принцип.** Наши chains — конфигурация, не код. Agno workflows — код (Python). Мы пытаемся втиснуть код в конфигурацию.

**Риск:** Проект уйдёт в сторону «Visual workflow builder» (n8n, Temporal) вместо оркестратора AI-агентов.

---

### ADOPTION_RISK-02: PHP constraints для Agno-паттернов

**Пункт исследования:** Все рекомендации

**Проблема:** Несколько рекомендаций игнорируют PHP-specific ограничения:

| Паттерн | Agno (Python) | PHP reality |
|---|---|---|
| Parallel | `asyncio.gather()` | `pcntl_fork()` / Symfony Process / ReactPHP — принципиально иная модель |
| Callable conditions | `lambda outputs: ...` | Closure в YAML невозможна — нужен expression language или предопределённые conditions |
| Tool hooks | Декораторы / middleware на функциях | У нас нет «tool calls» — runner'ы — CLI-процессы |
| Session persistence | Pickle / DB | Symfony services stateless по умолчанию — нужна explicit state management |
| Compression | LLM call in-process | Запуск нового CLI-процесса с полной стоимостью |

**Риск:** «Простое заимствование» паттернов без адаптации к PHP/Symfony/CLI породит Frankenstein-архитектуру.

---

### ADOPTION_RISK-03: Нарушение DDD при внедрении guardrails/hooks

**Пункт исследования:** §3.7 (Tool hooks), §3.8 (Guardrails)

**Проблема:** Guardrails (PII detection, prompt injection) — cross-cutting concerns. В нашей DDD-архитектуре:

- **Domain** не должен знать о PII и prompt injection — это infrastructure concern
- **Infrastructure** уже перегружена (ChainSessionLogger, JsonlAuditLogger, QualityGateRunner)
- Добавление guardrails как «pre-flight checks» перед шагом — это **новый слой middleware** между Domain и Infrastructure

Agno решает это через hooks (cross-cutting, вне DDD). Но у нас DDD — **core value** (прямо указано в AGENTS.md: «Логика строго в нужном слое», «Не нарушай слоистую архитектуру»).

Guardrails/hooks нужно аккуратно разместить: интерфейс в Domain (как `StepPreCheckInterface`?), реализация в Infrastructure. Это не «просто добавим» — это архитектурное решение.

---

### ANTIPATTERN-01: Agent.py — god class (1729 строк)

**Пункт исследования:** §1 (agent.py — 1729 строк)

**Проблема:** Agno `agent.py` — антипаттерн, который мы **ни в коем случае** не должны повторить:

- 1729 строк: LLM-сессия + tools + memory + hooks + guardrails + evals + reasoning + compression
- Нарушение SRP: Agent — и execution engine, и state manager, и tool coordinator
- Тестирование: невозможно unit-testировать memory logic без execution engine
- Расширяемость: добавить новый hook type требует изменения god class

Наш подход (отдельные `AgentRunnerInterface`, `CircuitBreakerAgentRunner`, `RetryingAgentRunner`, `RetryableRunnerFactory`) — **правильный**. Документ отмечает «У нас лучше» (декоратор), но не предупреждает: «Не повторяйте их подход при расширении».

---

### ANTIPATTERN-02: Implicit state через mutable session objects

**Пункт исследования:** §1 (session/), §3.4 (HITL)

**Проблема:** Agno хранит состояние в mutable session objects (`AgentSession`, `TeamSession`, `WorkflowSession`). State management неявный:

- Сессия мутируется в процессе выполнения шагов
- Нет явного state machine — state «размазан» по session attributes
- Rollback → manual cleanup (если поддерживается)

Наш подход (immutable `ChainSessionStateVo`, explicit `StaticChainExecution` entity) — более предсказуем. При заимствовании session persistence важно **не скатиться** к mutable session pattern.

---

### ANTIPATTERN-03: Безбрежная расширяемость → complexity explosion

**Пункт исследования:** §1 (40+ провайдеров, 12+ DB-адаптеров, Tools, MCP, Skills, Guardrails, Evals, Hooks)

**Проблема:** Agno страдает от feature creep:

- 40+ model providers — каждый со своей спецификой ошибок, rate limits, context windows
- 12+ DB adapters — каждый требует тестирования и поддержки
- Hooks + Guardrails + Evals — три пересекающихся механизма для pre/post checks
- Tools + MCP + Skills — три механизма для расширения возможностей агента

Мы должны **сознательно ограничить** расширяемость. Наш approach (2 runner'а, YAML config, JSONL audit) — это **feature, not limitation**. Каждый новый integration point — это поверхность для багов.

---

## Резюме приоритетов (скорректированное)

| Рекомендация документа | Моя оценка | Обоснование |
|---|---|---|
| Loop с end_condition (P2) | **P3 → переосмыслить** | Без DSL/expression language — просто предопределённые conditions. Стоимость выше, чем описано. |
| Error-specific fallback (P2) | **Отклонить как P2** | У нас уже есть fallback (`FallbackConfigVo`). Gap не показан. |
| Conditional branching (P2) | **P1 отдельного epic'а** | Это смена модели execution с linear на graph. Не «P2». |
| Tool hooks (P2) | **Отклонить** | Solution looking for a problem. Decorator pattern работает. |
| HITL (P3) | **P3 → только interactive mode** | Требует session persistence (prerequisite). Только для блокирующего CLI-ввода. |
| Parallel execution (P3) | **P3 → с оговоркой** | Фундаментально иная модель в PHP. Только через Symfony Process. |
| Compression (P3) | **Отклонить** | Стоимость LLM-вызова в нашей архитектуре неоправданна. |
| Session persistence (Позже) | **Повысить до P2** | Prerequisite для HITL, Parallel, Nested workflows. Должен идти первым. |

---

## Общая оценка исследования

**Сильные стороны:**
- Детальный анализ кодовой структуры Agno с конкретными ссылками на файлы
- Честное разделение «берём / не берём» с обоснованиями
- Хороший tabular format для сравнения

**Слабые стороны:**
- Отсутствие production-readiness оценки Agno
- Подтверждённый confirmation bias («у нас лучше» в 7 из 16 строк)
- Игнорирование Python ↔ PHP impedance mismatch
- Недооценка стоимости реализации рекомендаций
- Отсутствие gap analysis для существующих фич (fallback, decorator)

**Вердикт:** Исследование полезно как **карта паттернов** Agno, но непригодно как **дорожная карта заимствования** без доработки: каждого P2/P3 нужна отдельная техническая постановка с gap analysis, cost estimate и альтернативами.

---

📚 **Источники:**
1. `docs/research/agno-comparison.md` — объект ревью
2. `src/Module/Orchestrator/Domain/ValueObject/ChainDefinitionVo.php` — текущая модель chain
3. `src/Module/Orchestrator/Domain/ValueObject/FixIterationGroupVo.php` — текущая модель iterations
4. `src/Module/AgentRunner/Infrastructure/Service/CircuitBreakerAgentRunner.php` — текущий circuit breaker
5. `src/Module/Orchestrator/Domain/ValueObject/FallbackConfigVo.php` — текущий fallback
