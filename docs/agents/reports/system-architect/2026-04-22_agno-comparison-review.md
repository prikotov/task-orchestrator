# Архитектурное ревью: docs/research/agno-comparison.md

**Роль:** Архитектор Локи
**Дата:** 2026-04-22
**Объект:** `docs/research/agno-comparison.md` — сравнительный анализ Agno v2.5.17
**Задача:** Найти слепые зоны, слабые аргументы, риски заимствования и антипаттерны Agno

---

## BLIND_SPOT: Агенты Agno — 1729 строк в одном файле

Документ описывает архитектуру Agno как «6 строительных блоков workflow», но замалчивает, что `agent/agent.py` — это 1729-строчный dataclass-God Object. Agent одновременно держит LLM-сессию, tools, memory, hooks, guardrails, evals, reasoning, compression. Это не «архитектурный выбор», а **architectural smell**: если мы берём идеи из workflow engine, но не замечаем, что вся coordination завязана на мутабельный монолит — мы рискуем воспроизвести ту же проблему при реализации TeamMode.

**Следствие:** Документ не оценивает качество реализации Agno, только API-поверхность. Для research-документа это слепая зона — «что брать» без оценки «насколько хорошо это сделано у них».

---

## BLIND_SPOT: FallbackConfig Vo — не error-specific

В разделе 3.3 документ утверждает: «Наш `FallbackConfigVo` не переключает на альтернативный runner». Это правда, но неполная. Реальный `FallbackConfigVo` в коде (`src/Module/Orchestrator/Domain/ValueObject/FallbackConfigVo.php`) — это просто список CLI-аргументов (`command: list<string>`). Там **нет даже типа ошибки** — fallback вызывается при любом сбое. Agno разделяет `on_error` / `on_rate_limit` / `on_context_overflow` — это качественно другая модель.

Документ описывает разрыв, но не формулирует **domain model** для error-specific fallback: какие типы ошибок мы вообще можем классифицировать (rate limit ≠ timeout ≠ malformed output ≠ context overflow)? Без этой модели реализация «P2 error-specific fallback» начнётся с неправильной абстракции.

---

## BLIND_SPOT: Session persistence — не «🟡 Позже», а «прямо сейчас»

Документ ставит Session persistence в «🟡 Позже», но `StaticChainExecution` — **in-memory сущность** без персистенции. Если процесс падает на шаге 4 из 6 — вся цепочка теряется. Для CLI-утилиты это терпимо, но как только появятся `Loop с end_condition` (рекомендация P2) или `Parallel` (P3), длина выполнения растёт, и потеря промежуточного состояния станет критичной.

**Рекомендация:** Session persistence должен быть **предусловием** для Loop/Parallel, а не отдельной фичей «когда-нибудь». Иначе мы строим workflow engine поверх эфемерного состояния.

---

## WEAK_ARGUMENT: «У нас лучше» для Retry + Circuit Breaker

Таблица в разделе 2: «Retry с backoff — ✅ У нас лучше», «Circuit Breaker — ✅ У нас есть».

Сравнение некорректно:
- **Retry:** У нас exponential backoff, у Agno — `max_retries` без backoff. Но Agno компенсирует это `FallbackConfig` (при ошибке — переключиться на другой провайдер). Это **разные стратегии**: retry-same vs. fallback-to-alternative. Нельзя сравнивать как «лучше/хуже» без контекста: retry-same полезен при transient errors, fallback-to-alternative — при rate limits.
- **Circuit Breaker:** У нас есть, у Agno — нет. Но Agno — in-process SDK, где circuit breaker менее критичен (нет сети между оркестратором и агентом). У нас — CLI → subprocess, где network-like failure естественен. Это не «мы лучше», это «разная проблемная область».

---

## WEAK_ARGUMENT: «DDD-архитектура — ✅ У нас лучше»

Формально верно: Agno — монорепозиторий без слоёв, у нас — Domain/Application/Infrastructure. Но comparison без контекста вводит в заблуждение:
- Agno — SDK-библиотека с flat package structure, где пользователь импортирует `Agent`, `Team`, `Workflow` напрямую. Для SDK это норма.
- task-orchestrator — приложение (CLI tool) с бизнес-правилами (бюджет, retry, circuit breaker). Для приложения DDD оправдан.

Сравнивать архитектурные стили библиотеки и приложения — как сравнивать архитектуру микросервиса и shared library.

---

## WEAK_ARGUMENT: «CEL — избыточная зависимость, callable достаточно»

Раздел 4.6 отбрасывает CEL. Но в разделе 3.5 тут же показан пример CEL-выражения: `'all_success && current_iteration >= 2'`. Callable — это PHP-код, а **YAML-конфигурация** (наш core) не может содержать callable. Это противоречие:
- YAML chain может задать `max_iterations: 5`, но не может задать `end_condition: callable`.
- Либо нужен DSL для условий в YAML (свой мини-CEL), либо callable придётся регистрировать через service container — и тогда «декларативность» YAML теряется.

**Документ не разрешает это противоречие.**

---

## ADOPTION_RISK: Workflow Engine — риск overengineering

Раздел 3.1 рекомендует 6 строительных блоков workflow (Step, Steps, Loop, Parallel, Router, Condition). У нас сейчас 131 PHP-файл, один тип цепочки (static + dynamic brainstorm). Внедрение полноценного workflow engine — это:
- минимум 3× рост кодовой базы (workflow executor, YAML-парсер для каждого блока, визуализация/отладка);
- необходимость DAG или топологической сортировки для Parallel + Condition + Loop вместе;
- резкое усложнение YAML-конфигурации, которую сейчас можно прочитать за 30 секунд.

**Риск:** Построить workflow engine «на будущее» без реальной задачи, которая его требует. Сейчас нет ни одной user story, требующей Parallel или Router.

---

## ADOPTION_RISK: HITL — архитектурная несовместимость с CLI

Раздел 3.4 рекомендует 3 режима HITL (confirmation, user input, output review). Но task-orchestrator — **CLI-утилита**, которая запускает subprocess-агенты. HITL в CLI означает:
- пауза выполнения процесса → блокировка терминала;
- interactive prompt в середине цепочки → невозможность запуска в CI/CD;
- timeout-механизм → нужен daemon или background process.

Agno решает это через FastAPI runtime (WebSocket, REST API). У нас этого runtime нет, и он заявлен как «🟢 Не берём». HITL без runtime — half-baked feature.

---

## ADOPTION_RISK: TeamMode — 4 режима, но нет multi-agent runners

Раздел 3.2 рекомендует TeamMode (coordinate/route/broadcast/tasks). Но сейчас есть ровно 2 runner'а: Pi и Codex. Broadcast (запуск всех runners одновременно) при 2 runners — бессмысленно. Route (выбор специалиста) при 2 runners — тривиально. Tasks mode (autonomous decomposition) — не реализуем без LLM-in-the-loop для декомпозиции, а LLM-вызовы делает runner, не оркестратор.

---

## ANTIPATTERN: Agno Agent — God Object с mixed responsibilities

`agent/agent.py` (1729 строк) нарушает SRP: один класс отвечает за LLM-взаимодействие, tool execution, memory management, compression, guardrails, hooks, reasoning, evals. При заимствовании паттернов из этого файла есть риск:
- скопировать coupling (шаг знает о memory, compression, guardrails одновременно);
- воспроизвести «всё в одном» в `StaticChainExecution` или `ExecuteStaticChainService`.

**Антипаттерн для нас:** не делать шаг цепочки aware of compression/guardrails/evals. Эти cross-cutting concerns должны быть middleware/decorator (как наш `RetryingAgentRunner`), не встроены в ядро шага.

---

## ANTIPATTERN: Mutable state в workflow loops

Agno `Loop` передаёт `forward_iteration_output` как флаг, а данные текут через мутабельный workflow state. Наш `StaticChainExecution` уже имеет `$previousContext` — тот же паттерн. При добавлении Parallel этот подход ломается: parallel steps не могут писать в единственный `previousContext` без conflict.

**Антипаттерн:** Implicit state threading. Нужно явно моделировать data flow между шагами (input/output per step), а не полагаться на «предыдущий контекст».

---

## ANTIPATTERN: Fallback как конфигурация, а не стратегия

Agno `FallbackConfig` — это dataclass с массивом моделей. Наш `FallbackConfigVo` — массив CLI-аргументов. Оба — **configuration, не behaviour**. Логика fallback (когда, как, с какими параметрами) размазана по caller'у. Паттерн Strategy (или хотя бы FallbackStrategy interface) был бы чище.

---

## Сводка

| Категория | Кол-во | Критичность |
|---|---|---|
| BLIND_SPOT | 3 | 🟡 Средняя — пропущены качество реализации, модель ошибок, предусловия |
| WEAK_ARGUMENT | 3 | 🟡 Средняя — некорректные сравнения, неразрешённые противоречия |
| ADOPTION_RISK | 3 | 🔴 Высокая — overengineering, архитектурная несовместимость, premature abstraction |
| ANTIPATTERN | 3 | 🟡 Средняя — God Object, mutable state, configuration-as-strategy |

**Главный вывод:** Документ хорошо описывает API-поверхность Agno, но не оценивает качество реализации и не проверяет совместимость рекомендаций с текущей архитектурой. Ключевые рекомендации (Workflow Engine, HITL, TeamMode) требуют предварительного анализа осуществимости в контексте CLI-утилиты с 2 runners.
