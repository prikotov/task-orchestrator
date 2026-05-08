# Исследование: AI-Agents-Orchestrator (Python)

> **Проект:** [github.com/hoangsonww/AI-Agents-Orchestrator](https://github.com/hoangsonww/AI-Agents-Orchestrator)
> **Дата анализа:** 2026-04-05
> **Язык:** Python (LangChain, LangGraph)
> **Лицензия:** MIT
> **Аналитик:** Аналитик (kilocode)

---

## 1. Обзор проекта

AI-Agents-Orchestrator — Python-фреймворк для оркестрации нескольких AI-агентов с поддержкой разных LLM-моделей (OpenAI, Anthropic, Google, Ollama). Ключевая идея: **декларативная конфигурация агентов в YAML + последовательный запуск с retry, fallback routing и метриками**.

Фреймворк работает на уровне прямых LLM API-вызовов через LangChain/LangGraph. Каждый агент — это Python-объект с привязанной моделью, промптом и параметрами. Агенты запускаются последовательно через `OrchestrationWorkflow`, результаты передаются между шагами через LangGraph state.

### Архитектура

```
AI-Agents-Orchestrator/
 agents.yaml Декларативная конфигурация: агенты, модели, fallback
 utils/
 workflow.py OrchestrationWorkflow: последовательный запуск агентов
 engine.py Ядро: call_ai_model() с retry (tenacity), обработка ошибок
 fallback.py ModelFallbackRouter: cloud→local при недоступности
 retry.py RetryConfig: dataclass (exponential backoff)
 metrics.py MetricsCollector: Prometheus counters/gauges/histograms
 report_generator.py ReportGenerator: HTML + JSON отчёты
 offline.py OfflineCache: SQLite-кэширование результатов
```

### Ключевые характеристики

| Характеристика | Значение |
| --- | --- |
| **Тип** | Multi-agent orchestration framework |
| **Модель выполнения** | Последовательная (workflow) — агенты запускаются по порядку |
| **State management** | LangGraph state (in-memory, передаётся между шагами) |
| **LLM-провайдеры** | OpenAI, Anthropic, Google, Ollama (через LangChain) |
| **Расширяемость** | Python-классы (agents), YAML-конфигурация |
| **Мониторинг** | Prometheus-метрики (success/failure rate, latency, tokens) |

### Основные модули

| Модуль | Назначение |
| --- | --- |
| [`utils/engine.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/engine.py) | Ядро: вызов LLM, retry (tenacity), обработка ошибок |
| [`utils/workflow.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/workflow.py) | Orchestration workflow: последовательный запуск агентов, передача контекста |
| [`utils/fallback.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/fallback.py) | Fallback routing: cloud→local модель при недоступности |
| [`utils/retry.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/retry.py) | Retry config: dataclass с параметрами exponential backoff |
| [`utils/metrics.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/metrics.py) | Prometheus-метрики: success/failure rate, latency, tokens |
| [`utils/report_generator.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/report_generator.py) | Генерация отчётов: HTML- и JSON-форматы |
| [`utils/offline.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/offline.py) | Офлайн-режим: кэширование результатов в SQLite |
| [`agents.yaml`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/agents.yaml) | Конфигурация агентов: модели, параметры, fallback |

---

## 2. Возможности оркестрации — обзор

| Функция | AI-Agents-Orchestrator | Примечание |
| --- | --- | --- |
| **Workflow execution** | ✅ Последовательная | `OrchestrationWorkflow` запускает агентов по порядку |
| **Retry с backoff** | ✅ Tenacity | `@retry` декоратор, exponential backoff, configurable |
| **Fallback routing** | ✅ Model→Model | `ModelFallbackRouter`: cloud→local маппинг моделей |
| **State passing** | ✅ LangGraph state | Контекст передаётся между шагами через state |
| **YAML-конфигурация** | ✅ agents.yaml | Декларативное описание агентов, моделей, fallback |
| **Multi-model** | ✅ 4 провайдера | OpenAI, Anthropic, Google, Ollama через LangChain |
| **Prometheus metrics** | ✅ MetricsCollector | success/failure rate, latency, tokens |
| **Report generation** | ✅ HTML + JSON | `ReportGenerator` с двумя форматами |
| **Offline cache** | ✅ SQLite | Кэширование результатов по промпту |
| **DAG / Conditional routing** | ❌ Нет | Только последовательное выполнение |
| **Circuit Breaker** | ❌ Нет | Только retry + fallback |
| **Quality Gates** | ❌ Нет | Нет проверок результатов |
| **Budget control** | ❌ Нет | Только token counting в метриках |
| **Parallel execution** | ❌ Нет | Строго последовательно |
| **Error classification** | ❌ Нет | Retry на все `Exception` без разбора |

---

## 3. Оркестрационные возможности

### 3.1 Последовательный Workflow (`workflow.py`)

**Как работает:** `OrchestrationWorkflow` реализует последовательный запуск агентов:

1. Загрузка конфигурации из `agents.yaml`
2. Для каждого агента — вызов `call_ai_model()` с промптом
3. Результат каждого агента добавляется в LangGraph state
4. Следующий агент получает state с результатами предыдущих

**Поведение при ошибках:** Если вызов `call_ai_model()` для агента завершается ошибкой после всех retry-попыток — весь workflow прерывается. Результаты уже выполненных агентов теряются (state не персистентится). Возобновления с точки падения нет — при повторном запуске workflow начинается с первого агента.

**Поток данных:** Результат каждого агента — сырой текстовый ответ LLM. State не имеет схемы (schemaless dict) — нет валидации структуры перед передачей следующему агенту. Агент не может повлиять на то, какой агент будет следующим (нет dynamic routing). Промпт каждого агента статичен — задаётся в `agents.yaml` и не меняется на основе результатов предыдущих шагов.

**Ограничения:**
- Только линейная последовательность — нет условных переходов, параллельного выполнения, циклов или DAG.
- Нет error boundaries — нет возможности изолировать сбой одного агента и продолжить workflow.
- Нет dynamic prompt injection — результаты предыдущих агентов доступны в state, но промпт не перестраивается автоматически.

---

### 3.2 Retry с Exponential Backoff (`engine.py`, `retry.py`)

**Механизм:** Функция `call_ai_model()` обёрнута в `@retry` из библиотеки `tenacity`:

```python
@retry(
 wait=wait_exponential_multi(multiplier=1, min=4, max=10),
 stop=stop_after_attempt(max_retries),
 retry=retry_if_exception_type(Exception)
)
def call_ai_model(model, prompt, **kwargs):
 ...
```

**Конфигурация retry** через `RetryConfig` dataclass:
- `max_retries` — максимальное число попыток
- `base_delay` — начальная задержка
- `max_delay` — максимальная задержка

**Оркестрационная значимость:** Retry обеспечивает устойчивость при временных сбоях LLM API (rate limits, timeouts, 5xx). Однако retry применяется ко ВСЕМ `Exception` без классификации — нет различия между retryable (5xx, timeout) и non-retryable (auth, billing) ошибками.

**Последствия blanket retry:**
- **Auth-ошибки (401/403):** retry бессмысленен — повторный запрос с теми же credentials даст тот же результат, но расходует время и API-квоту.
- **Billing-ошибки (402/429 с quota exceeded):** retry может усугубить ситуацию, создавая дополнительную нагрузку на уже исчерпанный лимит.
- **Timeout с частичным результатом:** если LLM-вызов успешно обработан сервером, но ответ не дошёл — retry приведёт к дублированию (нет idempotency key).
- **Нет общего таймаута:** `max_delay` ограничивает задержку одной попытки, но суммарное время всех retry может быть значительным (`max_retries × max_delay`), что блокирует весь workflow.

---

### 3.3 Fallback Routing: Model→Model (`fallback.py`)

**Механизм:** Класс `ModelFallbackRouter` с fallback-маппингом:

```python
class ModelFallbackRouter:
 fallback_map: dict[str, str] = {
 "gpt-4o": "gpt-4o-mini", # cloud → cheaper cloud
 "claude-3.5": "gpt-4o", # cross-provider fallback
 }
```

**Оркестрационная значимость:** При недоступности основной модели (API error, timeout) — автоматическое переключение на fallback-модель. Fallback-маппинг — глобальный (настраивается в `agents.yaml`), не per-step. Cross-provider fallback позволяет переключаться между провайдерами (Anthropic → OpenAI).

**Ограничения:** Fallback работает на уровне модели, а не агента или шага. Нельзя задать разные fallback-цепочки для разных шагов workflow.

**Семантическая совместимость при cross-provider fallback:** При переключении между провайдерами (например, Anthropic → OpenAI) возникает риск потери качества:
- Промпты, оптимизированные под одну модель (system prompt, few-shot examples), могут неэффективно работать с другой.
- Контекстное окно fallback-модели может быть уже, чем у основной — обрезка state.
- Форматирование ответа (JSON, XML) может отличаться между провайдерами — нет нормализации выхода.
- Нет механизма prompt adaptation при fallback — промпт передаётся как есть.

---

### 3.4 LangGraph State Management

**Механизм:** Результаты передаются между шагами через LangGraph state:

- Каждый агент получает state с результатами всех предыдущих агентов
- State — in-memory Python dict (no persistence)
- Нет checkpointing — при падении workflow начинается с начала

**Оркестрационная значимость:** State passing — базовая форма контекстного менеджмента. Каждый следующий агент видит, что сделали предыдущие. Однако нет более продвинутых механизмов: checkpointing, branching, conditional routing.

**Характеристики state и их следствия:**
- **In-memory, без persistence:** при падении процесса (OOM, segfault, kill) все промежуточные результаты теряются. Для длинных цепочек (10+ агентов) это означает полный перезапуск.
- **Schemaless dict:** нет валидации структуры данных между шагами. Агент может записать в state данные произвольного формата, следующий агент получит их без гарантий.
- **Мутабельность:** любой агент может перезаписать или удалить результаты предыдущих агентов. Нет иммутабельности или append-only гарантий.
- **Рост памяти:** state хранит полные текстовые ответы всех предыдущих агентов. Для длинных цепочек с объёмными ответами потребление памяти растёт линейно, без TTL или eviction.

---

### 3.5 Observability: Prometheus Metrics (`metrics.py`)

> **Классификация:** Вспомогательная возможность (observability), не является механизмом оркестрации. Влияет на видимость процесса, но не на flow управления.

**Механизм:** `MetricsCollector` с Prometheus counters/gauges/histograms:

- **success_count** — число успешных вызовов агентов
- **failure_count** — число неудачных вызовов
- **latency_histogram** — гистограмма времени выполнения
- **token_count** — суммарное потребление токенов

**Значимость для оркестрации:** Observability обеспечивает видимость выполнения workflow — позволяет отслеживать, какие агенты медленные, какие часто падают, сколько стоит выполнение. Метрики собираются по каждому агенту, что позволяет идентифицировать bottlenecks. Однако метрики не влияют на принятие решений внутри workflow — нет механизмов auto-scaling, throttling или circuit breaking на основе метрик.

---

### 3.6 Report Generation (`report_generator.py`)

> **Классификация:** Вспомогательная возможность (output/reporting), не является механизмом оркестрации. Формирует артефакты после завершения workflow, но не участвует в управлении flow.

**Механизм:** `ReportGenerator` создаёт отчёты в двух форматах:

- **HTML** — визуальный отчёт с результатами каждого агента
- **JSON** — машиночитаемый отчёт для программной обработки

**Значимость:** Post-execution reporting — агрегация результатов всех агентов workflow в единый отчёт. Полезно для review и audit. Не влияет на ход выполнения workflow и не может прервать или перенаправить выполнение.

---

### 3.7 Offline Cache (`offline.py`)

> **Классификация:** Оптимизация производительности, **не** механизм оркестрации. Не влияет на flow управления, не участвует в принятии решений о маршрутизации или обработке ошибок. Подробное описание — в §4.2.

**Механизм:** SQLite-кэш результатов LLM-вызовов по ключу промпта:

- При повторном запросе с идентичным промптом — возврат кэшированного результата без LLM-вызова
- Кэш хранится в локальном SQLite-файле
- Ключ кэша — текст промпта (точное совпадение)

**Ограничения:**
- Кэширование по точному совпадению промпта — незначительное изменение текста ведёт к cache miss
- Нет TTL или инвалидации — закэшированные ошибочные результаты не обновляются
- Только для development/testing — в production с уникальными промптами cache hit rate близок к нулю

---

### 3.8 YAML Configuration (`agents.yaml`)

**Механизм:** Декларативная конфигурация агентов в YAML:

```yaml
agents:
 - name: analyst
 model: gpt-4o
 temperature: 0.3
 fallback: gpt-4o-mini
 - name: coder
 model: claude-3.5-sonnet
 temperature: 0.0
 fallback: gpt-4o
```

**Оркестрационная значимость:** Configuration-as-code для orchestration — описание workflow (агенты, модели, fallback) в декларативном формате. Каждый агент конфигурируется отдельно: модель, параметры, fallback-модель.

**Что можно сконфигурировать:**
- Список агентов и порядок их выполнения
- Модель, temperature, fallback-модель для каждого агента
- Параметры retry (max_retries, delays)

**Что НЕЛЬЗЯ сконфигурировать:**
- Условные переходы (если результат агента A содержит X — перейти к агенту B, иначе к C)
- Параллельное выполнение (агенты A и B одновременно, затем C)
- Циклы (повторять агента пока результат не пройдёт проверку)
- Quality gates (проверка результата перед передачей следующему агенту)
- Динамическое добавление/удаление агентов в runtime

Язык конфигурации описывает только линейную топологию — все продвинутые паттерны требуют изменений в Python-коде.

---

### 3.9 Отсутствующие оркестрационные паттерны

Ниже перечислены паттерны оркестрации, которые **отсутствуют** в AI-Agents-Orchestrator. Для каждого паттерна указано, какую роль он играет в multi-agent системах и почему его отсутствие ограничивает применимость фреймворка.

| Паттерн | Назначение | Последствия отсутствия |
| --- | --- | --- |
| **DAG / Conditional routing** | Направление выполнения на основе результатов промежуточных шагов | Workflow всегда линейный — нельзя реализовать разветвлённую логику (например, «если агент-аналитик нашёл ошибку — запустить агента-фиксера, иначе — пропустить») |
| **Circuit Breaker** | Предотвращение каскадных сбоев: временная блокировка нестабильного провайдера | При устойчивом сбое одного LLM-провайдера retry будет продолжать попытки до `max_retries`, блокируя workflow на суммарное время retry. Нет «размыкания цепи» | 
| **Quality Gates** | Проверка выхода агента перед передачей следующему | LLM может вернуть некорректный, неполный или галлюцинированный результат. Без quality gate этот результат передаётся следующему агенту без проверки, распространяя ошибку по цепочке |
| **Budget / Token control** | Ограничение расхода токенов или стоимости в рамках workflow | Token counting есть только в метриках (post-factum). Нет преднастроенных лимитов — один «разговорчивый» агент может исчерпать бюджет всего workflow |
| **Parallel execution** | Одновременный запуск независимых агентов | Агенты, не зависящие друг от друга, всё равно выполняются последовательно. Время выполнения = сумма времён всех агентов |
| **Error classification** | Разная стратегия обработки для разных типов ошибок | Retry применяется ко всем `Exception` одинаково. Auth-ошибки, billing-ошибки и timeout обрабатываются одной стратегией |
| **Checkpointing / Resume** | Сохранение промежуточного состояния для восстановления | При падении workflow начинается с начала. Для длинных цепочек (10+ агентов) это значительные потери времени и стоимости |
| **Human-in-the-loop** | Приостановка workflow для человеческого review | Нет механизма паузы/возобновления. Workflow выполняется автономно от начала до конца |
| **Dynamic loop** | Повторное выполнение агента до выполнения условия | Нельзя реализовать паттерн «генерация → проверка → доработка» — итеративную работу между агентами |

---

## 4. Прочие возможности (вне оркестрации)

### 4.1 Multi-Provider через LangChain

AI-Agents-Orchestrator поддерживает 4 LLM-провайдеров через LangChain abstraction: OpenAI, Anthropic, Google, Ollama. Это уровень LLM-провайдера, а не оркестрации.

### 4.2 Offline Cache (SQLite)

Кэширование результатов LLM-вызовов — optimization, не orchestration mechanism. Актуально для development/testing, не для production workflow с реальными данными. Детальный анализ механизма и ограничений — в §3.7.

---

## 5. Сводка по оркестрации

| Механизм оркестрации | Реализация | Зрелость | Классификация |
| --- | --- | --- | --- |
| **Workflow execution** | Последовательная (linear) | ⬛ Базовая | Core |
| **Retry** | `tenacity` decorator, exponential backoff | 🟨 Средняя (нет error classification, нет idempotency) | Core |
| **Fallback routing** | `ModelFallbackRouter`, global mapping | 🟨 Средняя (нет per-step fallback, нет prompt adaptation) | Core |
| **State passing** | LangGraph state (in-memory) | ⬛ Базовая (нет persistence, нет schema, мутабельная) | Core |
| **Observability** | Prometheus metrics | 🟩 Хорошая (нет обратной связи в flow) | Supporting |
| **Reporting** | HTML + JSON | 🟩 Хорошая (post-execution only) | Supporting |
| **Offline cache** | SQLite | 🟩 Хорошая (только dev/test) | Supporting |
| **Configuration** | YAML (agents.yaml) | 🟩 Хорошая (только линейная топология) | Supporting |
| **DAG / Conditional** | — | ⬛ Отсутствует | — |
| **Circuit Breaker** | — | ⬛ Отсутствует | — |
| **Quality Gates** | — | ⬛ Отсутствует | — |
| **Budget / Token control** | — | ⬛ Отсутствует | — |
| **Parallel execution** | — | ⬛ Отсутствует | — |
| **Error classification** | — | ⬛ Отсутствует | — |
| **Checkpointing / Resume** | — | ⬛ Отсутствует | — |
| **Human-in-the-loop** | — | ⬛ Отсутствует | — |
| **Dynamic loops** | — | ⬛ Отсутствует | — |

**Общая оценка:** AI-Agents-Orchestrator — **учебный/прототипный** фреймворк. Реализует базовую sequential orchestration с retry и fallback, но не имеет продвинутых механизмов: DAG, conditional routing, circuit breaker, quality gates, budget control, parallel execution, error classification. Подходит для простых linear workflow (agent1 → agent2 → agent3 → report), не для сложных orchestration scenarios.

---

## 6. Указатель источников для деталей

- [`utils/engine.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/engine.py) — retry обёртка над LLM-вызовами
- [`utils/retry.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/retry.py) — RetryConfig dataclass
- [`utils/fallback.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/fallback.py) — ModelFallbackRouter
- [`utils/workflow.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/workflow.py) — orchestration workflow
- [`utils/metrics.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/metrics.py) — Prometheus metrics
- [`utils/report_generator.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/report_generator.py) — report generation
- [`utils/offline.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/offline.py) — SQLite cache
- [`agents.yaml`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/agents.yaml) — конфигурация агентов и моделей

📚 **Источники:**
1. [github.com/hoangsonww/AI-Agents-Orchestrator](https://github.com/hoangsonww/AI-Agents-Orchestrator) — репозиторий проекта
