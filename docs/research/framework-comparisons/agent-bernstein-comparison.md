# Исследование: Bernstein — AI Agent Governance Framework (Python)

> **Проект:** [github.com/chernistry/bernstein](https://github.com/chernistry/bernstein)
> **Дата анализа:** 2026-05-08 (обновлено; первоначальный анализ 2026-04-05 основывался на прототипе, не отражающем текущий код)
> **Язык:** Python
> **Аналитик:** Аналитик (kilocode), актуализация: Архитектор Локи

---

## 1. Обзор проекта

Bernstein — это фреймворк для управления (governance) AI-агентами в программных проектах. Ключевая идея: AI-агенты выполняют работу в рамках строго определённых правил (governance policy), с автоматическими проверками качества (quality gates), бюджетным контролем, circuit breaker, кросс-модельной верификацией, голосованием и HMAC-chained audit trail. Проект ориентирован на CI/CD интеграцию и работу с git-репозиториями.

### Ключевые файлы

| Файл | Назначение |
| --- | --- |
| [`src/bernstein/core/observability/circuit_breaker.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/observability/circuit_breaker.py) | Agent lifecycle circuit breaker: kill-сигналы, quarantine, scope/budget/guardrail enforcement |
| [`src/bernstein/core/observability/provider_circuit_breaker.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/observability/provider_circuit_breaker.py) | Provider health circuit breaker: классический 3-state с thread-safety |
| [`src/bernstein/core/observability/cascading_failure_circuit_breaker.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/observability/cascading_failure_circuit_breaker.py) | Infrastructure service circuit breaker с latency-порогами |
| [`src/bernstein/evolution/circuit.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/evolution/circuit.py) | Evolution circuit breaker: risk levels (L0–L3), rate limiting, disk persistence |
| [`src/bernstein/core/quality/quality_gates.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/quality/quality_gates.py) | Quality gates: ~25+ типов проверок, intent verification, DLP, mutation testing |
| [`src/bernstein/core/quality/gate_plugins.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/quality/gate_plugins.py) | Plugin discovery: `.bernstein/gates/*.py` + entry_points |
| [`src/bernstein/core/quality/gate_pipeline.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/quality/gate_pipeline.py) | Pipeline structure: conditions, steps, status enum |
| [`src/bernstein/core/quality/cross_model_verifier.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/quality/cross_model_verifier.py) | Cross-model verification + VotingProtocol integration |
| [`src/bernstein/core/communication/voting.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/communication/voting.py) | Multi-model voting: MAJORITY, QUORUM, WEIGHTED, UNANIMOUS |
| [`src/bernstein/core/cost/budget_countdown.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/cost/budget_countdown.py) | Per-turn budget countdown, graceful-finish, task-budgets beta |
| [`src/bernstein/core/tasks/task_lifecycle.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/tasks/task_lifecycle.py) | Retry с model/effort escalation, backoff, DLQ, auto-decomposition |
| [`src/bernstein/core/security/audit.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/security/audit.py) | HMAC-chained audit log с daily rotation и tamper-evidence |
| [`src/bernstein/evolution/governance.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/evolution/governance.py) | Adaptive governance: weight adjustment, decision trail |
| [`bernstein.yaml`](https://github.com/chernistry/bernstein/blob/main/bernstein.yaml) | Конфигурация: agents, budgets, quality gates, role-model policy |

---

## 2. Возможности оркестрации — обзор

| Функция | Реализация в Bernstein | Архитектурная заметка |
| --- | --- | --- |
| **Task server** | ✅ HTTP task CRUD + DAG зависимостей | Централизованная координация через REST API |
| **Git worktree isolation** | ✅ Каждый агент в отдельном worktree | Файловая изоляция + scope violation detection |
| **Circuit breaker (provider)** | ✅ 3-state, thread-safe, per-provider | Классический Nygard с `threading.Lock` |
| **Circuit breaker (lifecycle)** | ✅ Scope/budget/guardrail → kill + quarantine | Не Nygard — enforcement mechanism |
| **Circuit breaker (evolution)** | ✅ Risk levels L0–L3, rate limits, persistent | Персистенция в `.sdd/evolution/circuit_state.json` |
| **Quality gates** | ✅ 25+ типов + plugin registry + conditions | Расширяемый через `GatePlugin` ABC |
| **Бюджетный контроль** | ✅ Per-agent identity card + modes + countdown | `graceful-finish-on-low` / `hard-stop-on-zero` |
| **Retry / итерации** | ✅ Model ladder + effort ladder + backoff + DLQ | haiku→sonnet→opus, low→max, exponential backoff |
| **Cross-model verification** | ✅ Provider-diverse reviewer + voting protocol | `claude→gemini`, `gemini→claude` auto-selection |
| **Audit trail** | ✅ HMAC-SHA256 chain + daily rotation + archive | Tamper-evident, key outside audit dir |
| **Adaptive governance** | ✅ Heuristic weight adjustment per cycle | 6 dimensions, context-driven re-weighting |
| **Self-evolution** | ✅ Analyze → propose → sandbox → apply | R&D-уровень, disabled по умолчанию |
| **Auto-decomposition** | ✅ LARGE → 3–5 subtasks via manager | `TaskSplitter` + `ManagerAgent` |
| **Fair scheduling** | ✅ Weighted deficit round-robin per tenant | Multi-tenant proportional service |
| **Dead Letter Queue** | ✅ `.sdd/runtime/dlq.jsonl` для permanently failed | Incident synthesizer integration |

---

## 3. Оркестрационные возможности

В этом разделе рассматриваются шесть оркестрационных механизмов Bernstein: circuit breaker, quality gates, бюджетный контроль, итерационные циклы, кросс-модельная верификация и audit trail. Выбор обусловлен тем, что именно эти механизмы определяют устойчивость (resilience) и управляемость (governance) AI-агента при работе с внешними LLM API. Cascade Failure Circuit Breaker (см. §2), защищающий инфраструктурные сервисы от каскадных сбоев, вынесен за рамки детального анализа — его предметная область (latency thresholds для infrastructure dependencies) ортогональна agent orchestration. Каждый подраздел содержит описание реализации, архитектурную оценку и перечень ограничений.

### 3.1 Circuit Breaker

**Реализация:** Bernstein содержит четыре независимых circuit breaker-механизма, каждый для своего домена:

**A. Provider Circuit Breaker** ([`provider_circuit_breaker.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/observability/provider_circuit_breaker.py)) — классический Nygard-паттерн с тремя состояниями (closed → open → half-open), по одному экземпляру на LLM-провайдер:

```python
class ProviderCircuitBreaker:
    def __init__(self, provider: str, config: CircuitBreakerConfig | None = None):
        self._lock = threading.Lock()
        self._state: CircuitState = CircuitState.CLOSED
        self._failure_count: int = 0
        self._half_open_in_flight: int = 0

    def should_allow(self) -> bool:
        with self._lock:
            self._maybe_transition_to_half_open()
            if self._state == CircuitState.CLOSED:
                return True
            if self._state == CircuitState.OPEN:
                return False
            # HALF_OPEN: allow limited probes
            if self._half_open_in_flight < self._config.half_open_max_probes:
                self._half_open_in_flight += 1
                return True
            return False

    def record_failure(self) -> None:
        with self._lock:
            if self._state == CircuitState.HALF_OPEN:
                self._half_open_in_flight = max(0, self._half_open_in_flight - 1)
                self._transition(CircuitState.OPEN)  # immediate return to OPEN
            elif self._state == CircuitState.CLOSED:
                self._failure_count += 1
                if self._failure_count >= self._config.failure_threshold:
                    self._transition(CircuitState.OPEN)
```

**B. Agent Lifecycle Circuit Breaker** ([`circuit_breaker.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/observability/circuit_breaker.py)) — не Nygard-паттерн, а enforcement mechanism: отслеживает scope violations (агент редактирует файлы вне `owned_files`), budget violations (превышение per-task token budget в 2x) и guardrail violations (секреты в diff). При обнаружении нарушения: writes kill signal → audit log → quarantine metadata.

**C. Evolution Circuit Breaker** ([`evolution/circuit.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/evolution/circuit.py)) — circuit breaker для эволюционной подсистемы (self-modification): 4 risk levels (L0_CONFIG: 5/day, L1_TEMPLATE: 3/day, L2_LOGIC: 1/day, L3_STRUCTURAL: never auto-apply), rate limiting, rollback tracking, sandbox failure tracking, metrics regression detection. Персистенция в `.sdd/evolution/circuit_state.json`.

**D. Cascading Failure Circuit Breaker** ([`cascading_failure_circuit_breaker.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/observability/cascading_failure_circuit_breaker.py)) — circuit breaker для инфраструктурных зависимостей (database, cache, message queue): отслеживает latency-пороги и error rate для каждого upstream-сервиса. При превышении порога — mark service as unhealthy, short-circuit dependent operations. Предназначен для предотвращения cascade failures при деградации инфраструктуры.

**Архитектурная оценка:**

Разделение circuit breaker по доменам — обоснованное архитектурное решение: транспортный уровень (provider health), инфраструктурный уровень (upstream service dependencies), агентный уровень (runtime violations) и мета-уровень (evolution safety) имеют разные паттерны сбоев и требуют разных стратегий восстановления. При этом четыре breaker-механизма не координируются между собой — каждый работает автономно, что упрощает реализацию, но не позволяет реализовать составные стратегии (например, cascade de-escalation: при деградации infra → снизить concurrency агентов).

Provider circuit breaker реализует корректный переход half-open → open: при `record_failure()` в `HALF_OPEN` состоянии breaker немедленно переходит в `OPEN` без проверки порога — это исправляет баг, характерный для наивных реализаций. Thread-safety обеспечивается `threading.Lock` на всех мутациях состояния.

**Ограничения реализации:**

1. **Внутрипроцессное состояние provider breaker.** `ProviderCircuitBreaker` хранит состояние в памяти. При перезапуске процесса (или при нескольких экземплярах оркестратора) breaker возвращается в closed. Evolution breaker эту проблему решает (disk persistence), но provider breaker — нет. Для multi-process деплоя (горизонтальное масштабирование) потребуется внешний coordination store (Redis, etcd).
2. **Нет классификации ошибок по recoverability в provider breaker.** Provider breaker учитывает все ошибки одинаково. Permanent-ошибки (401 — неверный API-ключ) и transient-ошибки (503 — временная перегрузка) инкрементируют один и тот же счётчик. Для permanent-сбоев размыкание breaker оправдано, но последующий half-open probe бессмыслен — ошибка повторится. Асимметрия: retry-механизм (§3.4) использует классификацию через `_TRANSIENT_MARKERS` / `_FATAL_MARKERS`, но provider breaker не получает от неё выгоды — классификация существует, но не интегрирована с breaker.
3. **Нет fallback при открытом circuit.** `should_allow()` возвращает `False`, но не предоставляет альтернативного действия (вызов другого провайдера, кэшированный результат, graceful degradation). Вызывающая сторона должна самостоятельно обрабатывать отказ.
4. **Семантические сбои невидимы для provider breaker — по дизайну.** Breaker реагирует на transport-level ошибки (timeout, HTTP 429/503), но не на семантические (галлюцинация, неверная логика при структурно корректном ответе). Это осознанное разделение ответственности: семантический контроль вынесен на другие уровни — cross-model verification (§3.5) и intent verification gate (§3.2). Provider breaker сфокусирован на transport-надёжности, и расширение его ответственности до semantic validation нарушило бы single concern.
5. **Нет координации с retry-механизмом (§3.4).** `retry_or_fail_task` не проверяет состояние provider breaker перед созданием retry-задачи. Если breaker для провайдера открыт, retry-задача будет поставлена в очередь, но агент при попытке вызова получит отказ. Это не расходует retry-бюджет задачи (retry считает попытки запуска агента, а не API-вызовы), но приводит к бесполезным spawn-циклам.

---

### 3.2 Quality Gates

**Реализация:** `QualityGatesConfig` — dataclass с ~40 настраиваемыми полями, покрывающий более 25 типов проверок:

```python
@dataclass
class QualityGatesConfig:
    enabled: bool = True
    lint: bool = True
    lint_command: str = "ruff check ."         # параметризуется
    type_check: bool = False
    type_check_command: str = "pyright"         # параметризуется
    tests: bool = False
    test_command: str = "uv run python scripts/run_tests.py -x"
    pii_scan: bool = True
    security_scan: bool = False
    mutation_testing: bool = False
    mutation_threshold: float = 0.50
    intent_verification: IntentVerificationConfig = field(default_factory=IntentVerificationConfig)
    benchmark: BenchmarkConfig = field(default_factory=BenchmarkConfig)
    dead_code_check: bool = False
    comment_quality_check: bool = False
    dep_audit: bool = False
    dlp_scan: bool = True
    # ... + ~20 других параметров
    pipeline: list[GatePipelineStep] | None = None  # explicit pipeline override
```

**Расширяемость через Plugin Registry** ([`gate_plugins.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/quality/gate_plugins.py)):

```python
class GatePlugin(ABC):
    @property
    @abstractmethod
    def name(self) -> str: ...

    @property
    def required(self) -> bool:
        return True

    @abstractmethod
    def run(self, changed_files, run_dir, task_title, task_description) -> GateResult: ...

class GatePluginRegistry:
    def discover(self) -> None:
        self._load_file_plugins(self._workdir / ".bernstein" / "gates")  # .py files
        self._load_entrypoint_plugins()  # Python entry_points
```

**Pipeline с условиями** ([`gate_pipeline.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/quality/gate_pipeline.py)):

```python
VALID_GATE_CONDITIONS = frozenset({
    "always", "python_changed", "tests_changed", "any_changed", "deps_changed"
})
```

**Intent Verification Gate** — отдельный gate, использующий LLM для проверки: «Запросили X → агент произвёл Y → удовлетворяет ли Y замыслу X?» Возвращает трёхзначный вердикт (`yes` / `partially` / `no`) с настраиваемой блокировкой (`block_on_no`, `block_on_partial`).

**Архитектурная оценка:**

Система quality gates реализована как расширяемый pipeline с тремя механизмами добавления проверок: (а) конфигурация встроенных gates через `QualityGatesConfig`; (б) явный pipeline через `list[GatePipelineStep]`; (в) plugin discovery через filesystem + entry_points. Условия выполнения (`python_changed`, `deps_changed`) позволяют запускать gates только при релевантных изменениях — оптимизация для больших репозиториев.

Команды инструментов параметризуются (`lint_command`, `type_check_command`, `test_command`), что позволяет адаптировать gates к конкретному стеку без модификации кода.

**Ограничения реализации:**

1. **Shell-выполнение команд.** Gates выполняются через `subprocess.run(..., shell=True)`. Код содержит комментарий: «shell=True required because quality gate commands are admin-configured shell strings (e.g. "ruff check src/") that may use pipes or globs; not user input». Это обоснованное решение (команды задаёт администратор, не пользователь), но создаёт surface area при скомпрометированном конфиге.
2. **Plugin discovery — вектор code execution.** `_load_file_plugins` загружает произвольные `.py` файлы из `.bernstein/gates/` через `importlib.util`. Если атакующий может записать файл в этот каталог, он получит code execution в контексте оркестратора.
3. **Intent verification: fallback на «yes» при нечитаемом ответе.** `_parse_intent_response` при невозможности распарсить JSON из ответа LLM возвращает `verdict="yes"`. Это defensive design (модель не должна блокировать pipeline при сбое верификатора), но маскирует систематические проблемы с верификатором — например, если модель-верификатор регулярно возвращает malformed output, все задачи проходят без проверки, и оператор не узнает об этом из результатов gate.
4. **Параллельное выполнение gates: частичный параллелизм.** `GateRunner` использует `asyncio.to_thread` для параллельного запуска gates. Shell-команды (lint, type-check, tests) через `subprocess.run` выполняются в отдельных процессах и получают полноценный OS-level параллелизм — GIL на них не действует. Однако Python-код gates (PII scan, DLP scan) выполняется в потоках `ThreadPoolExecutor` и ограничен GIL: при двух одновременно работающих CPU-bound Python gates реальное параллельное выполнение невозможно. Практическое влияние зависит от соотношения subprocess- и Python-gates в конкретной конфигурации.
5. **Связь gate failure с retry не документирована явно.** `block_on_issues` в cross-model verifier и `blocked=True` в gate result указывают на блокировку слияния, но точный механизм: что происходит после блокировки — retry, DLQ, или ручное вмешательство — определяется оркестратором, а не gate subsystem.

---

### 3.3 Бюджетный контроль

**Реализация:** Бюджетный контроль реализован через несколько взаимодействующих компонентов:

**Agent Identity Card** — каждый агент получает карточку с бюджетными параметрами:

```python
@dataclass
class AgentIdentityCard:
    max_tokens: int          # per-task token budget
    max_budget_usd: float    # per-task cost budget
    max_steps: int           # per-task step limit
    budget_mode: str         # "graceful-finish-on-low" | "hard-stop-on-zero"
```

**Per-turn countdown** ([`budget_countdown.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/cost/budget_countdown.py)) — детерминированный banner, инжектируемый в промпт агента каждый ход:

```python
def format_countdown(card: AgentIdentityCard, tracker: CostTracker, turn_state: TurnState) -> str:
    return (
        f"[budget] tokens left: {tokens_left:,} of {card.max_tokens:,} ({pct}%) | "
        f"${spent:.2f} of ${card.max_budget_usd:.2f} | "
        f"steps: {turn_state.step} of {card.max_steps} | "
        f"mode: {card.budget_mode}"
    )
```

**Graceful finish** — предикат, определяющий момент мягкой посадки:

```python
def should_finish_gracefully(card: AgentIdentityCard, turn_state: TurnState) -> bool:
    if card.budget_mode == "hard-stop-on-zero":
        return turn_state.remaining(card) <= 0
    # graceful-finish-on-low: trigger at 20% or when steps exhausted
    if card.max_steps > 0 and turn_state.step >= card.max_steps:
        return True
    return turn_state.percentage_left(card) <= 20
```

**Budget enforcement в lifecycle circuit breaker** — hard kill при превышении 2x от soft budget:

```python
def check_budget_violations(orch, result):
    kill_threshold = budget * 2  # hard-kill at 2x the soft budget
    if session.tokens_used > kill_threshold:
        enforce_kill_signal(workdir, session.id, KillReason.BUDGET_EXCEEDED, detail)
```

**Архитектурная оценка:**

Трёхуровневая модель: (а) soft budget через countdown banner в промпте (агент «видит» остаток и должен завершиться сам); (б) graceful-finish-on-low — оркестратор даёт агенту последний ход для фиксации WIP при достижении 20% лимита; (в) hard-kill при 2x budget — circuit breaker принудительно завершает агента-«беглеца». Это разумное разделение ответственности между self-regulation и enforcement.

`TurnState.remaining()` и `percentage_left()` дают per-turn гранулярность — бюджет проверяется каждый ход, а не только на уровне task/stage.

**Ограничения реализации:**

1. **Зависимость от «сотрудничества» модели.** Countdown banner инжектируется в промпт, но модель может его игнорировать. Hard-kill при 2x — единственная гарантия, но к этому моменту бюджет уже превышен в два раза. Это компромисс: ранний hard-kill прервал бы легитимные длительные задачи. Практический эффект зависит от модели — крупные модели (Claude Sonnet/Opus, GPT-4) следуют инструкциям в промпте значительно лучше, чем мелкие.
2. **Фиксированный порог graceful-finish (20%).** `DEFAULT_LOW_THRESHOLD_PCT = 20` не настраивается per-task или per-role. Задачи с предсказуемой стоимостью (lint-fix) и задачи с высокой вариативностью (architectural refactoring) имеют разные оптимальные пороги.
3. **Нет cross-agent бюджетной координации.** Каждый агент имеет независимый per-task бюджет. При параллельной работе 7 агентов (по `max_agents: 7` из конфига) суммарное потребление может значительно превысить ожидания оператора. Общий (project-level) бюджет контролируется через `CostTracker`, но не имеет enforcement mechanism — только observability.
4. **Budget multiplier на retry — экспоненциальный рост стоимости.** `maybe_retry_task` удваивает `budget_multiplier` при `terminal_reason == "error_max_budget_usd"`. Если retry также исчерпывает бюджет, следующий retry получит 4x, затем 8x от оригинального бюджета. Без ceiling это может привести к значительным расходам на задачу, которая принципиально не выполнима в рамках budget.
5. **`max_tokens` — единый лимит без разделения input/output.** Стоимость output-токенов у большинства провайдеров (OpenAI, Anthropic, Google) в 2–5 раз выше input-токенов, но лимит не различает их. Агент с длинным системным промптом и коротким output получит ту же квоту, что и агент с коротким промптом и длинным output, хотя реальная стоимость будет различаться.
6. **`budget_mode` — plain string вместо enum.** `AgentIdentityCard.budget_mode` имеет тип `str`, хотя допустимые значения — `"graceful-finish-on-low"` и `"hard-stop-on-zero"`. Опечатка в конфиге (например, `"graceful-finish"`) не вызовет ошибки при парсинге, но приведёт к silent fallback к дефолтному поведению в `should_finish_gracefully`, что затруднит диагностику.

---

### 3.4 Итерационные циклы

**Реализация:** Retry реализован в [`task_lifecycle.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/tasks/task_lifecycle.py) (~3090 строк) с несколькими стратегиями эскалации:

**Model escalation ladder:**
```python
_MODEL_LADDER = ["haiku", "sonnet", "opus"]

def _escalate_model(current_model: str) -> str:
    model_idx = 1  # default to sonnet position
    for i, name in enumerate(_MODEL_LADDER):
        if name in current_model.lower():
            model_idx = i
            break
    return _MODEL_LADDER[min(model_idx + 1, len(_MODEL_LADDER) - 1)]
```

**Effort escalation ladder:**
```python
_EFFORT_LADDER = ["low", "medium", "high", "max"]
```

**Exponential backoff:**
```python
base_delay = task.retry_delay_s if task.retry_delay_s > 0 else 30.0
backoff_delay = min(base_delay * (2 ** retry_count), 300.0)  # cap at 5 min
```

**Context-aware retry strategy** — `_choose_retry_escalation` выбирает модель и effort в зависимости от `terminal_reason`:

```python
match terminal_reason:
    case "error_max_turns":       return current_model, _bump_effort(current_effort)
    case "error_max_budget_usd":  return current_model, "max"
    case "model_error":           return current_model, current_effort
    case "blocking_limit":        return "opus", "max"
```

**Failure context injection** — retry-задача получает описание предыдущей ошибки:
```python
new_description = (
    f"{task.description}\n\n"
    "## Previous attempt failed\n"
    f"{failure_context}\n\n"
    "Avoid the same mistakes. If you hit the same error, try a different approach."
)
```

**Dynamic retry limits** — классификация ошибок по recoverability:
```python
_TRANSIENT_MARKERS = ("rate limit", "timeout", "503", "429", "connection error")
_FATAL_MARKERS = ("syntaxerror", "syntax error", "fatal")

def _dynamic_retry_limit(reason: str, default_max: int) -> int:
    if any(k in reason_lower for k in _TRANSIENT_MARKERS): return 3
    if any(k in reason_lower for k in _FATAL_MARKERS):     return 0
    return default_max
```

**Dead Letter Queue** — для задач, исчерпавших retry budget: `_enqueue_dlq_if_workdir` записывает в `.sdd/runtime/dlq.jsonl` с интеграцией в `IncidentSynthesizer`.

**Архитектурная оценка:**

Retry-механизм реализует стратегию эскалации по нескольким осям: модель (более мощная → выше шанс успеха), effort (больше попыток агента → больше возможностей), бюджет (удвоение multiplier → больше runway), timeout (прогрессивный `estimated_minutes * (retry_count + 2)`). Это значительно сложнее, чем простое «повторить тот же запрос 3 раза».

Exponential backoff с ceiling (300s) предотвращает перегрузку API. Dynamic retry limits различают transient-ошибки (стоит ретраить) и fatal-ошибки (бессмысленно).

Retry не является слепым повтором: failure context из agent log добавляется в описание retry-задачи, что даёт новому агенту информацию о предыдущей неудаче.

**Важный нюанс model ladder.** `_MODEL_LADDER = ["haiku", "sonnet", "opus"]` содержит только Anthropic-модели. Для не-Anthropic моделей (GPT, Gemini, Qwen, DeepSeek) `_escalate_model` не находит совпадения и использует `model_idx = 1` (позиция sonnet), затем берёт `_MODEL_LADDER[2]` = `"opus"`. Это означает, что при неудаче на GPT-4/Gemini retry всегда перепрыгивает на Claude Opus — кросс-провайдерный jump. Для некоторых сценариев это оправдано (другой провайдер = другой контекст ошибки), но для transient-ошибок (rate limit, timeout) смена провайдера добавляет задержку на cold start нового API и не гарантирует лучшего результата. Кроме того, cross-provider escalation делает модельное поведение менее предсказуемым для оператора.

**Ограничения реализации:**

1. **Классификация ошибок через keyword matching.** `_TRANSIENT_MARKERS` и `_FATAL_MARKERS` — списки строк, которые ищутся в `reason.lower()`. Это хрупкий эвристический механизм: формулировка reason зависит от вызывающего кода, и добавление новых источников ошибок может потребовать обновления списков. Структурная классификация (enum, typed error hierarchy) была бы надёжнее.
2. **Always-opus для architect/security/large.** `_choose_retry_escalation` всегда возвращает `("opus", "max")` для `scope == LARGE` или `role in ("architect", "security")`, даже если ошибка transient (timeout, rate limit). Это может привести к избыточным расходам: opus — самая дорогая модель.
3. **Нет интеграции с provider circuit breaker.** `maybe_retry_task` не проверяет состояние breaker для провайдера выбранной модели. Если провайдер в OPEN состоянии, retry-задача будет создана, но агент не сможет выполнить API-вызовы до перехода breaker в HALF_OPEN.
4. **Retry не откатывает побочные эффекты.** Retry создаёт новую задачу, но предыдущий агент мог уже создать git-коммиты, файлы, внешние API-вызовы. Git worktree изоляция частично решает это (старый worktree остаётся), но cleanup не автоматический — артефакты неудачной попытки сохраняются.
5. **Dead Letter Queue — best-effort.** `_enqueue_dlq_if_workdir` перехватывает все исключения и логирует их: «DLQ must never break the primary failure path». Это правильный приоритет (primary path важнее audit), но означает, что DLQ-запись может быть потеряна при I/O ошибке без уведомления оператора.
6. **Retry ≠ направленные циклы с обратной связью.** Retry — повтор при ошибке. В контексте AI-агентов нужен другой паттерн: developer → reviewer → developer (фикс замечаний) → reviewer — цикл с передачей контекста между шагами. Текущая архитектура не моделирует направленные циклы как первоклассную сущность. Cross-model verification (§3.5) при `block_on_issues=True` создаёт fix tasks, что формирует неявный developer → reviewer → fix cycle, но это побочный эффект gate-механизма, а не явный orchestration primitive: нет ограничения на количество итераций цикла, нет механизма передачи structured feedback, нет отдельного бюджета на цикл.
7. **`task_lifecycle.py` (~3090 строк) — god object.** Retry-логика, task decomposition, DLQ, scheduling, context building и escalation сосредоточены в одном модуле. Это затрудняет тестирование отдельных стратегий и модификацию behavior без regression risk.

---

### 3.5 Кросс-модельная верификация

**Реализация:** [`cross_model_verifier.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/quality/cross_model_verifier.py) + [`voting.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/communication/voting.py):

**Auto-selection reviewer из другого provider:**
```python
_WRITER_TO_REVIEWER: dict[str, str] = {
    "claude":  "google/gemini-flash-1.5",     # Claude → Gemini
    "gemini":  "anthropic/claude-haiku-4-5",   # Gemini → Claude
    "gpt":     "google/gemini-flash-1.5",      # GPT → Gemini
    "codex":   "anthropic/claude-haiku-4-5",   # Codex → Claude
    "qwen":    "anthropic/claude-haiku-4-5",   # Qwen → Claude
}

def select_reviewer_model(writer_model: str, override: str | None = None) -> str:
    if override:
        return override
    for prefix, reviewer in _WRITER_TO_REVIEWER.items():
        if prefix in writer_model.lower():
            return reviewer
    return "google/gemini-flash-1.5"  # default
```

**Structured review prompt** — параметризованный шаблон с конкретными критериями (5 фокусных областей: correctness, security, bugs, style, scope). Prompt получает title, description и diff задачи, инкапсулированные в Python multi-line string. Verdict — JSON с полями `verdict` (`approve` / `request_changes`), `feedback` и `issues`. Полный шаблон: [`_REVIEW_PROMPT_TEMPLATE`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/quality/cross_model_verifier.py).

**Multi-model voting** через `VotingProtocol`:
```python
@dataclass(frozen=True)
class VotingConfig:
    strategy: VotingStrategy = VotingStrategy.QUORUM  # MAJORITY | QUORUM | WEIGHTED | UNANIMOUS
    quorum_k: int = 1
    quorum_n: int = 1
    abstention_threshold: float = 0.3
    tie_break: TieBreak = TieBreak.REJECT  # REJECT | ACCEPT | ESCALATE
```

**Cost control:**
```python
_MAX_DIFF_CHARS = 12_000   # truncation for cost control
_MAX_TOKENS = 512          # reviewer response cap
```

**Persistent memoization** — fingerprint-based кэш для избежания повторных вызовов:
```python
def _memoized_review_call(workdir: Path) -> Any:
    store = default_store(workdir)
    return memoize_persistent(store, site="cross_model_verifier")(_raw_review_call)
```

**Graceful fallback** — при сбое LLM верификация возвращает `approve`:
```python
except RuntimeError as exc:
    return CrossModelVerdict(
        verdict="approve",
        feedback=f"Reviewer call failed: {exc}",
    )
```

**Архитектурная оценка:**

Cross-model verification реализует паттерн LLM-as-a-Judge ([Zheng et al., 2023](https://arxiv.org/abs/2306.05685)) с тремя уровнями сложности: (а) single-reviewer — QUORUM(1,1) для базового сценария; (б) multi-model voting — QUORUM(k,n) / MAJORITY / WEIGHTED / UNANIMOUS для критичных задач; (в) provider diversity — auto-selection гарантирует, что reviewer и writer используют разные provider families, снижая риск shared blind spots.

Prompt template параметризован и содержит конкретные критерии проверки (correctness, security, bugs, style, scope). Это устраняет проблему generic-промптов, которые дают низкоспецифичные оценки.

**Слабая модель проверяет сильную.** Default-ревьюеры — `gemini-flash-1.5` и `claude-haiku-4-5`: быстрые и дешёвые модели. Если writer работает на opus/sonnet, верификатор получает существенно меньше параметров и capabilities. Практические следствия: flash/haiku надёжно ловят очевидные баги (syntax, style, scope violations), но могут пропустить тонкие семантические ошибки (race conditions, incorrect abstractions), которые opus-уровень способен обнаружить. Для критичных задач voting QUORUM(k>1,n) частично компенсирует этот недостаток за счёт multiple perspectives, но каждый voter — тоже слабая модель.

**LLM-as-a-Judge: известные систематические bias'ы.** Zheng et al. (2023) документируют три значимых эффекта: (а) verbosity bias — модели-оценщики систематически предпочитают более длинные ответы; (б) position bias — при A/B сравнении результат зависит от порядка; (в) self-preference — модель оценивает собственные выходы выше. Provider diversity (разные провайдеры для writer и reviewer) снижает self-preference, но не устраняет verbosity и position bias. В текущей реализации эти bias'ы не компенсируются (например, через randomised ordering или calibration prompts).

Rework ledger (`_record_rework_sample`) записывает каждый rejection с указанием writer model, effort, роли и причины отклонения. Данные сохраняются в persistent store и доступны для model effectiveness analytics — rejection rate по модели, effort, роли. Это превращает верификатор из пассивного gate в источник обучающего сигнала.

**Ограничения реализации:**

1. **Fallback на «approve» при сбое верификатора.** Если LLM-вызов верификатора завершается ошибкой (timeout, rate limit), вердикт — `approve`. Это означает, что persistent outage верификатора может остаться незамеченным: все задачи проходят verification без фактической проверки. Логирование предупреждений есть (`logger.warning`), но нет метрики или алерта для оператора.
2. **Prompt injection через проверяемый контент.** Diff агента подставляется в промпт верификатора без санитизации. Если сгенерированный код содержит инструкции (prompt injection), модель-верификатор может быть скомпрометирована. Согласно Perez et al. (2022), LLM-оценщики уязвимы к вложенным инструкциям. Механизм защиты (sanitization, separate channel для user content) отсутствует. Следует учитывать, что prompt injection — не единственная угроза валидности: Zheng et al. (2023) документируют систематические bias'ы LLM-оценщиков (verbosity bias, position bias, self-preference), которые не устраняются prompt engineering.
3. **Diff truncation может потерять критический контекст.** `max_diff_chars = 12_000` — при больших изменениях (рефакторинг, migration) diff может быть обрезан, и верификатор не увидит критические фрагменты. Нет механизма smart truncation (приоритет последних изменений, исключение generated files).
4. **Writer-to-reviewer mapping захардкожен.** `_WRITER_TO_REVIEWER` — статический словарь. При добавлении новой модели (например, DeepSeek, Llama) маппинг нужно обновлять в коде. Конфигурируемый mapping через YAML был бы гибче.
5. **Memoization предполагает детерминизм.** Fingerprint-based кэш считает, что одинаковый input → одинаковый результат. `temperature=0.0` минимизирует вариативность, но не гарантирует детерминизм: некоторые провайдеры могут возвращать разные результаты при `temperature=0`. Нет TTL для кэш-записей — если качество модели улучшилось, старые кэшированные результаты не будут обновлены.
6. **Voting не интегрирован с бюджетным контроллером.** При QUORUM(3,3) верификация стоит 3x от single-reviewer. Нет опции budget cap на verification — при включении voting для всех задач стоимость верификации может стать значительной статьёй расходов.

---

### 3.6 Audit Trail

**Реализация:** [`audit.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/security/audit.py) — HMAC-SHA256-chained audit log:

```python
@dataclass(frozen=True)
class AuditEvent:
    timestamp: str           # ISO 8601, timezone-aware
    event_type: str
    actor: str
    resource_type: str
    resource_id: str
    details: dict[str, Any]
    prev_hmac: str           # HMAC предыдущего события
    hmac: str                # HMAC текущего события
```

**HMAC chain** — каждое событие подписано с использованием предыдущего HMAC:
```python
def _compute_hmac(key: bytes, prev_hmac: str, entry: dict) -> str:
    payload = prev_hmac + json.dumps(entry, sort_keys=True)
    return hmac.new(key, payload.encode(), hashlib.sha256).hexdigest()
```

Genesis-хеш (первое событие цепочки): для первого события в дневном файле `prev_hmac` должен быть определён. В типичных реализациях используется sentinel value (пустая строка или фиксированный seed). Это означает, что замена первого события в файле вычислима: атакующий, знающий sentinel value и ключ, может пересчитать цепочку от нового первого события. Защита: key material недоступен атакующему с write-доступом к логам (key хранится вне audit directory).

**Daily rotation:**
```python
day = datetime.now(tz=UTC).strftime("%Y-%m-%d")
log_path = self._audit_dir / f"{day}.jsonl"
```

**Key management** — HMAC-ключ хранится вне audit directory:
```python
def _default_audit_key_path() -> Path:
    # $XDG_STATE_HOME/bernstein/audit.key or ~/.local/state/bernstein/audit.key
```

**Key permission enforcement:**
```python
_REQUIRED_KEY_MODE = 0o600

def _enforce_key_permissions(key_path: Path) -> None:
    file_mode = stat.S_IMODE(key_path.stat().st_mode)
    if file_mode & 0o077:
        raise AuditKeyPermissionError(f"Audit key has insecure permissions {file_mode:04o}")
```

**Verification:**
```python
def verify(self) -> tuple[bool, list[str]]:
    """Walk all JSONL files and verify the HMAC chain."""
```

**Retention & Archive:**
```python
@dataclass(frozen=True)
class RetentionPolicy:
    retention_days: int = 90
    archive_subdir: str = "archive"

def archive(self, policy=None) -> ArchiveResult:
    # gzip-compress old files, remove originals
```

**Архитектурная оценка:**

Audit trail реализует tamper-evident логирование через HMAC-цепочку: каждое событие подписано с учётом предыдущего, что делает ретроспективное изменение записей обнаруживаемым (удаление или модификация любого события нарушает цепочку). Ключ хранится вне audit directory, что соответствует принципу разделения логгера и key material — атакующий с write-доступом к логам не может подделать подписи без доступа к ключу.

Timezone-aware timestamps (`datetime.now(tz=UTC)`) обеспечивают корректность при распределённом выполнении. Daily rotation решает проблему неограниченного роста файла. Archive с gzip-сжатием позволяет хранить историю audit trail при ограниченном дисковом пространстве.

Структурированные события (`event_type`, `actor`, `resource_type`, `resource_id`, `details`) лучше произвольного `**data` — потребители логов могут опираться на стабильные поля для фильтрации и агрегации.

**Ограничения реализации:**

1. **HMAC-ключ на файловой системе.** Ключ хранится в `~/.local/state/bernstein/audit.key` с mode `0600`. Если файловая система скомпрометирована (root access, backup leak), целостность цепочки нарушается. Для compliance-сценариев с высокими требованиями к non-repudiation может потребоваться HSM (Hardware Security Module) или внешняя signing service.
2. **Обнаружение нарушения цепочки — ретроспективное.** `verify()` проверяет цепочку при явном вызове, но нет автоматического обнаружения нарушения в реальном времени. Между запусками verify нарушенная цепочка остаётся незамеченной. Background verification (periodic check, integrity watchdog) не реализована.
3. **Query — линейный scan.** `query()` читает и парсит все JSONL файлы для фильтрации. Для многолетнего audit trail с тысячами событий в день это может быть медленным. Нет индексации по `event_type`, `actor`, `timestamp`.
4. **Single-writer модель; нет защиты от конкурентных записей.** `AuditLog` не поддерживает распределённую запись: при нескольких экземплярах оркестратора каждый будет писать в свой набор файлов, и `prev_hmac` не будет согласован между процессами. Для multi-instance деплоя потребуется centralised audit service или distributed lock. Даже в single-instance сценарии: если два потока одновременно вызывают `log_event`, race condition на `prev_hmac` может привести к broken chain, если `AuditLog` не использует внутренний lock (в отличие от `ProviderCircuitBreaker`, где `threading.Lock` объявлен явно).
5. **Archive прерывает chain verification — если verify не обрабатывает gzip.** После архивации (gzip + удаление оригинала) `verify()` должен разархивировать файлы для полной проверки цепочки. Если реализация `verify()` сканирует только `*.jsonl` и не обрабатывает `archive/*.jsonl.gz`, chain verification покрывает только текущий период. В этом случае оператор получает ложное ощущение целостности: verify проходит успешно, хотя исторические записи могут быть модифицированы.
6. **`details` dict — unstructured payload.** Хотя основные поля типизированы (`event_type`, `actor`, `resource_type`, `resource_id`), поле `details` принимает произвольный `dict[str, Any]`. Потребители логов не могут опираться на стабильную схему внутри `details`. Schema registry или typed details per event type не реализованы.

---

## 4. Прочие возможности (вне оркестрации)

### 4.1 Task Server

HTTP REST API для CRUD задач с DAG зависимостей. Централизованная координация: оркестратор опрашивает task server каждый tick, забирает открытые задачи, клонит и запускает агентов. Task server — единственный source of truth для состояния задач.

### 4.2 Git Worktree Isolation

Каждый агент получает отдельный git worktree. Scope violation detection (§3.1) использует `git diff --name-only HEAD` для проверки файлов, изменённых агентом, против `owned_files` задачи. При нарушении — kill signal + quarantine. Это решает проблему побочных эффектов: неудачный агент изолирован в своём worktree.

### 4.3 Self-Evolution (Adaptive Governance)

Цикл Analyze → Propose → Sandbox → Apply позволяет фреймворку адаптировать собственные правила. Evolution circuit breaker (§3.1.C) обеспечивает безопасность: risk levels, rate limits, rollback tracking, sandbox failure detection. `AdaptiveGovernor` ([`governance.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/evolution/governance.py)) динамически корректирует веса метрик (test_coverage, lint_score, type_safety, performance, security, maintainability) на основе `ProjectContext`. Disabled по умолчанию.

### 4.4 Auto-Decomposition

LARGE-задачи автоматически разбиваются на 3–5 подзадач через `TaskSplitter` + `ManagerAgent`. Также декомпозиция триггерится при 2+ retries — задача, не выполнимая целиком, разбивается на части. Disabled по умолчанию (`auto_decompose: false` в конфиге).

### 4.5 CI Autofix Integration

Парсинг CI-логов и создание задач на исправление — отдельная область (CI/CD-интеграция), не связанная с ядром оркестрации. Ценность зависит от экосистемы CI-инструментов.

---

## 5. Сводка по оркестрации

| Возможность | Зрелость | Архитектурные заметки |
| --- | --- | --- |
| Circuit Breaker (provider) | Средняя | Классический 3-state Nygard с thread-safety и корректным half-open→open; in-process state (no cross-process coordination); классификация ошибок существует в retry (§3.4), но не интегрирована с breaker |
| Circuit Breaker (cascading failure) | Средняя | Infrastructure-level latency и error rate thresholds; ортогонален agent orchestration — защищает upstream dependencies (DB, cache, MQ) |
| Circuit Breaker (lifecycle) | Средняя | Scope/budget/guardrail enforcement через kill signals + quarantine; hard-kill при 2x budget; нет degradation-стратегии (только kill/allow) |
| Circuit Breaker (evolution) | Средняя | Risk levels L0–L3, rate limiting, disk persistence, rollback tracking; ограничен evolution-подсистемой |
| Quality Gates | Средняя | 25+ типов, plugin registry, pipeline conditions, intent verification, DLP, mutation testing; shell-выполнение, plugin loading как code execution vector |
| Бюджетный контроль | Средняя | 3-уровневая модель (soft/graceful/hard-kill), per-turn countdown, budget modes; `budget_mode` — plain string без enum-валидации; нет cross-agent координации, единый лимит без input/output разделения, exponential budget multiplier на retry |
| Итерационные циклы | Средняя | Model/effort/budget/timeout escalation, exponential backoff, failure context injection, DLQ, dynamic retry limits; model ladder — Anthropic-only с cross-provider jump для других провайдеров; keyword-based error classification; нет координации с provider breaker; task_lifecycle.py (~3090 строк) — god object |
| Кросс-модельная верификация | Средняя | Provider-diverse auto-selection, voting protocol (4 стратегии), rework ledger, memoization; слабые модели (flash/haiku) проверяют сильные (opus); LLM-as-a-Judge bias'ы не компенсируются; fallback на approve при сбое, prompt injection risk, hardcoded writer-to-reviewer mapping |
| Audit Trail | Средняя | HMAC-SHA256 chain, daily rotation, archive, timezone-aware timestamps, key permission enforcement; single-writer без явного thread-safety lock; genesis-хеш — потенциальный attack vector; archive может прерывать chain verify; linear query scan |
| Adaptive Governance | R&D | Heuristic weight adjustment с 6 dimensions; disabled по умолчанию |
| Auto-Decomposition | Средняя | LARGE → 3–5 subtasks via manager; disabled по умолчанию |
| Git Worktree Isolation | Средняя | Полная файловая изоляция + scope violation detection; оправдана при параллельном выполнении |
| Self-Evolution | R&D | Conceptually interesting; disabled по умолчанию |

---

## 6. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории Bernstein (current `main`):

### Circuit Breaker
- [`src/bernstein/core/observability/circuit_breaker.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/observability/circuit_breaker.py) — agent lifecycle circuit breaker (scope/budget/guardrail enforcement)
- [`src/bernstein/core/observability/provider_circuit_breaker.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/observability/provider_circuit_breaker.py) — provider health circuit breaker (классический 3-state)
- [`src/bernstein/core/observability/cascading_failure_circuit_breaker.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/observability/cascading_failure_circuit_breaker.py) — infrastructure service circuit breaker с latency thresholds
- [`src/bernstein/evolution/circuit.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/evolution/circuit.py) — evolution circuit breaker с risk levels и disk persistence

### Quality Gates
- [`src/bernstein/core/quality/quality_gates.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/quality/quality_gates.py) — quality gates config + intent verification + PII/DLP gates
- [`src/bernstein/core/quality/gate_plugins.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/quality/gate_plugins.py) — plugin discovery (filesystem + entry_points)
- [`src/bernstein/core/quality/gate_pipeline.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/quality/gate_pipeline.py) — pipeline structure, conditions, status enum
- [`src/bernstein/core/quality/gate_runner.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/quality/gate_runner.py) — async gate runner с caching

### Budget & Cost
- [`src/bernstein/core/cost/budget_countdown.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/cost/budget_countdown.py) — per-turn countdown, graceful-finish, task-budgets beta
- [`src/bernstein/core/cost/completion_budget.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/cost/completion_budget.py) — task completion budget
- [`src/bernstein/core/security/agent_identity.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/security/agent_identity.py) — AgentIdentityCard с budget params

### Retry & Lifecycle
- [`src/bernstein/core/tasks/task_lifecycle.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/tasks/task_lifecycle.py) — retry с model/effort/budget escalation, backoff, DLQ
- [`src/bernstein/core/tasks/task_retry.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/tasks/task_retry.py) — re-export module

### Cross-Model Verification
- [`src/bernstein/core/quality/cross_model_verifier.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/quality/cross_model_verifier.py) — cross-model code review
- [`src/bernstein/core/communication/voting.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/communication/voting.py) — multi-model voting protocol

### Audit Trail
- [`src/bernstein/core/security/audit.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/security/audit.py) — HMAC-chained audit log с rotation и tamper-evidence
- [`src/bernstein/core/security/audit_integrity.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/core/security/audit_integrity.py) — integrity verification utilities

### Governance & Evolution
- [`src/bernstein/evolution/governance.py`](https://github.com/chernistry/bernstein/blob/main/src/bernstein/evolution/governance.py) — adaptive weight adjustment, decision trail
- [`bernstein.yaml`](https://github.com/chernistry/bernstein/blob/main/bernstein.yaml) — пример конфигурации проекта
