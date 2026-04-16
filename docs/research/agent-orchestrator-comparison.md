# Исследование: AI-Agents-Orchestrator (Python)

> **Проект:** [github.com/hoangsonww/AI-Agents-Orchestrator](https://github.com/hoangsonww/AI-Agents-Orchestrator)  
> **Дата анализа:** 2026-04-05  
> **Язык:** Python (LangChain, LangGraph)  
> **Аналитик:** Аналитик (kilocode)

---

## 1. Обзор проекта

AI-Agents-Orchestrator — это Python-фреймворк для оркестрации нескольких AI-агентов с поддержкой разных LLM-моделей (OpenAI, Anthropic, Google, Ollama). Основные возможности: multi-agent orchestration, fallback routing, retry с exponential backoff, Prometheus-метрики, генерация отчётов (HTML/JSON).

### Ключевые файлы

| Файл | Назначение |
|---|---|
| [`utils/engine.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/engine.py) | Ядро: вызов LLM, retry (tenacity), обработка ошибок |
| [`utils/workflow.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/workflow.py) | Orchestration workflow: последовательный запуск агентов |
| [`utils/fallback.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/fallback.py) | Fallback routing: cloud→local модель при недоступности |
| [`utils/retry.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/retry.py) | Retry config: dataclass с параметрами exponential backoff |
| [`utils/metrics.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/metrics.py) | Prometheus-метрики: success/failure rate, latency, tokens |
| [`utils/report_generator.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/report_generator.py) | Генерация отчётов: HTML и JSON форматы |
| [`utils/offline.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/offline.py) | Офлайн-режим: кэширование результатов в SQLite |
| [`agents.yaml`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/agents.yaml) | Конфигурация агентов: модели, параметры, fallback |

---

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | TasK Orchestrator | AI-Agents-Orchestrator | Статус |
|---|---|---|---|
| **Multi-agent orchestration** | ✅ Цепочки (YAML) | ✅ Workflow (Python) | ✅ У нас есть |
| **Retry с backoff** | ✅ RetryingAgentRunner | ✅ Tenacity (`engine.py`) | ✅ Реализовано |
| **Fallback routing** | ✅ ResolveChainRunnerService | ✅ `ModelFallbackRouter` (`fallback.py`) | ✅ Реализовано |
| **Prometheus-метрики** | ❌ Нет | ✅ `MetricsCollector` (`metrics.py`) | 🟡 Позже |
| **Генерация отчётов** | ✅ Text/JSON mappers | ✅ HTML/JSON (`report_generator.py`) | ✅ Реализовано |
| **Офлайн-режим / кэш** | ❌ Нет | ✅ SQLite cache (`offline.py`) | 🟢 Не берём |
| **YAML-конфигурация** | ✅ YAML chains + roles | ✅ `agents.yaml` | ✅ У нас есть |
| **Ролевые промпты** | ✅ `.md` файлы (18 ролей) | ✅ Встроенные в Python | ✅ У нас лучше |
| **Контекст между шагами** | ✅ `buildContext()` | ✅ Через LangGraph state | ✅ У нас есть |
| **Multiple runners** | ✅ Pi + Codex (через interface) | ✅ Multi-model (OpenAI/Anthropic/...) | ✅ У нас есть |
| **Budget/cost tracking** | ✅ BudgetVo | ❌ Нет явного budget | ✅ У нас лучше |
| **CLI-интерфейс** | ✅ Symfony Console | ❌ Python script | ✅ У нас лучше |
| **Lock (single execution)** | ✅ Symfony Lock | ❌ Нет | ✅ У нас лучше |

---

## 3. Что полезно взять и почему

### 3.1 ✅ Retry с exponential backoff (`engine.py`, `retry.py`)

**Что у них:** Функция `call_ai_model()` обёрнута в `@retry` из библиотеки `tenacity`:
```python
@retry(
    wait=wait_exponential_multi(multiplier=1, min=4, max=10),
    stop=stop_after_attempt(max_retries),
    retry=retry_if_exception_type(Exception)
)
def call_ai_model(model, prompt, **kwargs):
    ...
```

**Чем наша реализация отличается:**
- PHP Decorator pattern (`RetryingAgentRunner` implements `AgentRunnerInterface`)
- Immutable `RetryPolicyVo` (readonly class)
- Retry configurable через YAML на уровне цепочки и шага

**Задача:** TASK-agent-retry-mechanism

---

### 3.2 ✅ Fallback routing (`fallback.py`)

**Что у них:** Класс `ModelFallbackRouter` с fallback-маппингом:
```python
class ModelFallbackRouter:
    fallback_map: dict[str, str] = {"gpt-4o": "gpt-4o-mini", "claude-3.5": "gpt-4o"}
```

**Чем наша реализация отличается:**
- Per-step fallback в YAML (не глобальный маппинг)
- Fallback вызывается через `AgentRunnerInterface` (через registry)
- У них: model → model; у нас: runner → runner (более абстрактно)

**Задача:** TASK-agent-fallback-runner

---

### 3.3 ✅ Генерация отчётов (`report_generator.py`)

**Что у них:** Класс `ReportGenerator` с двумя форматами (text + HTML + JSON).

**Чем наша реализация отличается:**
- Text + JSON (без HTML) — CLI-focused
- `--report-file` для записи в файл
- `role + runner` вместо просто `agent`

**Задача:** TASK-agent-report-generator

---

### 3.4 🟡 Prometheus-метрики (`metrics.py`)

**Что у них:** Класс `MetricsCollector` с Prometheus counters/gauges/histograms.

**Почему нам это НЕ нужно сейчас:** Orchestrator — CLI-утилита, запускается по требованию. Prometheus нужен для long-running сервисов. Если в будущем появится web-API — метрики станут актуальны.

---

### 3.5 🟢 Офлайн-режим / кэш (`offline.py`)

**Почему нам это НЕ нужно:**
1. Orchestrator работает с реальным кодом (файлы меняются) — кэш по промпту ненадёжен
2. Контекст между шагами уникален для каждого запуска
3. SQLite-зависимость усложняет инфраструктуру

---

## 4. Сводка рекомендаций

| Фича | Приоритет | Обоснование |
|---|---|---|
| Retry с exponential backoff | ✅ Реализовано | Критично для устойчивости при временных сбоях |
| Fallback routing | ✅ Реализовано | Обеспечивает работу при недоступности основного runner'а |
| Генерация отчётов | ✅ Реализовано | Ускоряет анализ результатов |
| Prometheus-метрики | 🟡 P3 | Нужно для API, не нужно для CLI |
| Офлайн-кэш | 🟢 — | Не применим к нашему use case |

---

## 5. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории AI-Agents-Orchestrator:

- [`utils/engine.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/engine.py) — retry обёртка над LLM-вызовами
- [`utils/retry.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/retry.py) — RetryConfig dataclass
- [`utils/fallback.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/fallback.py) — ModelFallbackRouter
- [`utils/workflow.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/workflow.py) — orchestration workflow
- [`utils/metrics.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/metrics.py) — Prometheus metrics
- [`utils/report_generator.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/report_generator.py) — report generation
- [`utils/offline.py`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/utils/offline.py) — SQLite cache
- [`agents.yaml`](https://github.com/hoangsonww/AI-Agents-Orchestrator/blob/main/agents.yaml) — конфигурация агентов и моделей
