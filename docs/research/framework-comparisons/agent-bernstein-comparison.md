# Исследование: Bernstein — AI Agent Governance Framework (Python)

> **Проект:** [github.com/chernistry/bernstein](https://github.com/chernistry/bernstein)  
> **Дата анализа:** 2026-04-05  
> **Язык:** Python  
> **Аналитик:** Аналитик (kilocode)

---

## 1. Обзор проекта

Bernstein — это фреймворк для управления (governance) AI-агентами в программных проектах. Ключевая идея: AI-агенты выполняют работу в рамках строго определённых правил (governance policy), с автоматическими проверками качества (quality gates), бюджетным контролем, circuit breaker и кросс-модельной верификацией. Проект ориентирован на CI/CD интеграцию и работу с git-репозиториями.

### Ключевые файлы

| Файл | Назначение |
|---|---|
| [`bernstein/circuit_breaker.py`](https://github.com/chernistry/bernstein/blob/main/bernstein/circuit_breaker.py) | Circuit Breaker: 3 состояния (closed/open/half-open) |
| [`bernstein/governance.py`](https://github.com/chernistry/bernstein/blob/main/bernstein/governance.py) | Governance: бюджет, итерации, audit trail, policy enforcement |
| [`bernstein/quality_gates.py`](https://github.com/chernistry/bernstein/blob/main/bernstein/quality_gates.py) | Quality Gates: lint, type-check, tests, PII scan, security |
| [`bernstein.yaml`](https://github.com/chernistry/bernstein/blob/main/bernstein.yaml) | Конфигурация: governance policy, stages, quality gates |
| [`plans/plan.yaml`](https://github.com/chernistry/bernstein/blob/main/plans/plan.yaml) | Шаблон плана: multi-stage выполнение с зависимостями |

---

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | TasK Orchestrator | Bernstein | Статус |
|---|---|---|---|
| **Circuit Breaker** | ✅ CircuitBreakerAgentRunner | ✅ 3 состояния, failure threshold | ✅ Реализовано |
| **Quality Gates** | ✅ Произвольные shell-команды | ✅ lint/type-check/tests/PII/security | ✅ Реализовано |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based) | ✅ max_cost_per_stage, max_tokens_per_stage | ✅ Реализовано |
| **Итерационные циклы** | ✅ fix_iterations в YAML | ✅ retry_on_failure, max_retries | ✅ Реализовано |
| **Кросс-модельная верификация** | ✅ Обычный agent-шаг с другой моделью | ✅ verify_with_model | ✅ Реализовано |
| **Audit trail (JSONL)** | ✅ JsonlAuditLogger | ✅ JSON-логирование всех действий | ✅ Реализовано |
| **Plan files (multi-stage)** | ❌ Только chains | ✅ Stages + steps с зависимостями | 🟡 Позже |
| **Git worktree isolation** | ❌ Нет | ✅ Каждый агент в отдельном worktree | 🟢 Не берём |
| **Self-evolution** | ❌ Нет | ✅ Analyze → propose → sandbox → apply | 🟢 Не берём |
| **CI autofix** | ❌ Нет | ✅ Parse CI logs → create fix tasks | 🟡 Позже |
| **Adaptive governance** | ❌ Нет | ✅ Правила адаптируются по результатам | 🟢 Не берём |
| **YAML-конфигурация** | ✅ YAML chains + roles | ✅ bernstein.yaml + plan.yaml | ✅ У нас есть |
| **DDD-архитектура** | ✅ Domain/Application/Infra | ❌ Плоская структура | ✅ У нас лучше |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Прямой вызов | ✅ У нас лучше |
| **Ролевые промпты** | ✅ .md файлы (18 ролей) | ❌ Встроенные в Python | ✅ У нас лучше |
| **Multiple runners** | ✅ Pi + Codex (interface) | ✅ Multi-model | ✅ Паритет |

---

## 3. Что полезно взять и почему

### 3.1 🔴 Circuit Breaker (`circuit_breaker.py`)

**Что у них:** Класс `CircuitBreaker` с тремя состояниями:
```python
class CircuitBreaker:
    def __init__(self, failure_threshold=5, reset_timeout=60):
        self.state = "closed"  # closed | open | half_open
        self.failure_count = 0
        self.failure_threshold = failure_threshold
        self.reset_timeout = reset_timeout
        self.last_failure_time = None

    def call(self, fn, *args, **kwargs):
        if self._is_open():
            raise CircuitBreakerOpenError()
        try:
            result = fn(*args, **kwargs)
            self._on_success()
            return result
        except Exception as e:
            self._on_failure()
            raise

    def _on_success(self):
        self.failure_count = 0
        self.state = "closed"

    def _on_failure(self):
        self.failure_count += 1
        self.last_failure_time = time.time()
        if self.failure_count >= self.failure_threshold:
            self.state = "open"

    def _is_open(self):
        if self.state == "closed":
            return False
        if self.state == "open":
            if time.time() - self.last_failure_time > self.reset_timeout:
                self.state = "half_open"
                return False
            return True
        return False  # half_open: allow one attempt
```

**Почему нам нужно:** Если runner `pi` начинает сбоить (API перегружен, баг в CLI), повторные вызовы тратят время и деньги. Circuit breaker после N ошибок блокирует вызовы на T секунд, затем разрешает один пробный вызов (half-open).

**Чем наша реализация отличается:**
- Immutable `CircuitBreakerStateVo` + `CircuitStateEnum` вместо mutable state
- Decorator pattern (`CircuitBreakerAgentRunner` implements `AgentRunnerInterface`)
- State хранится in-memory: `array<string, CircuitBreakerStateVo>` (ключ — runner name)

**Задача:** TASK-agent-circuit-breaker

---

### 3.2 🔴 Quality Gates (`quality_gates.py`)

**Что у них:** Класс `QualityGateRunner` с фиксированными типами проверок:
```python
class QualityGateRunner:
    GATE_TYPES = ["lint", "type_check", "tests", "pii_scan", "security"]

    def run_gate(self, gate_config, workdir):
        gate_type = gate_config["type"]
        if gate_type == "lint":
            return self._run_command("ruff check .", workdir, timeout=60)
        elif gate_type == "type_check":
            return self._run_command("mypy .", workdir, timeout=120)
        # ...
```

**Почему нам нужно:** После выполнения шага `developer` в цепочке `implement` — нужно автоматически проверить что сгенерированный код проходит lint, type-check и тесты.

**Чем наша реализация отличается:**
- Произвольные shell-команды вместо фиксированных типов: `make lint-php`, `make tests-unit`
- Каждая команда = один `QualityGateVo(command, label, timeoutSeconds)`
- Результат: `QualityGateResultVo(passed, exitCode, output, durationMs)`

**Задача:** TASK-agent-quality-gates

---

### 3.3 🔴 Бюджетный контроль (`governance.py`)

**Что у них:** Класс `GovernancePolicy` с бюджетными лимитами:
```python
class GovernancePolicy:
    max_tokens_per_stage: int = 100000
    max_cost_per_stage: float = 5.0
    max_total_cost: float = 20.0
```

**Почему нам нужно:** Цепочка `implement` из 4 шагов может стоить $0.50–$5.00. Без лимита — runaway chain может потратить $20+. Бюджет — предохранитель.

**Чем наша реализация отличается:**
- Только cost-based (USD). Token-лимиты не берём — они менее информативны
- `BudgetVo(maxCostTotal, maxCostPerStep)` — immutable VO
- Проверка перед каждым шагом + после

**Задача:** TASK-agent-budget-control

---

### 3.4 🔴 Итерационные циклы (`governance.py`, `plan.yaml`)

**Что у них:** В `plan.yaml` каждый stage может иметь `retry_on_failure: true`:
```yaml
stages:
  - name: implement
    retry_on_failure: true
    max_retries: 3
```

**Почему нам нужно:** Цепочка `implement` должна работать так: developer → reviewer → если есть замечания → developer (итерация 2) → reviewer → ...

**Чем наша реализация отличается:**
- Явная группировка через `fix_iterations` в YAML
- Группа содержит ≥ 2 именованных шагов
- `max_iterations` — лимит на группу

**Задача:** TASK-agent-iteration-loops

---

### 3.5 🔴 Кросс-модельная верификация (`quality_gates.py`)

**Что у них:** Параметр `verify_with_model` на уровне шага:
```python
def verify_result(self, result, verify_with_model):
    verification_prompt = f"Review the following output for correctness..."
    verification = call_ai_model(verify_with_model, verification_prompt)
```

**Почему нам нужно:** Одна модель может «галлюцинировать» и не замечать свои ошибки. Кросс-модельная верификация снижает риск.

**Чем наша реализация отличается:**
- Обычный agent-шаг с любой подходящей ролью — никаких специальных механизмов
- Настраиваемый через YAML: роль с другой моделью
- Audit trail, fix iterations, budget — работают из коробки

**Задача:** TASK-agent-cross-model-verify

---

### 3.6 🔴 Audit Trail (`governance.py`)

**Что у них:** JSON-логирование каждого действия через audit logger:
```python
class AuditLogger:
    def log(self, event_type, data):
        entry = {
            "timestamp": datetime.utcnow().isoformat(),
            "event_type": event_type,
            **data
        }
        with open(self.log_file, "a") as f:
            f.write(json.dumps(entry) + "\n")
```

**Почему нам нужно:** Для отладки, анализа и воспроизводимости запусков. JSONL-формат: append-only, легко парсить.

**Чем наша реализация отличается:**
- Наш JSONL содержит поле `runner` (у Bernstein только `model`)
- `FILE_APPEND | LOCK_EX` (atomic append)
- Путь к файлу — параметр bundle `%task_orchestrator.audit_log_path%`

**Задача:** TASK-agent-execution-logger

---

## 4. Что НЕ берём и почему

### 4.1 🟢 Plan Files (multi-stage YAML)

Наш формат цепочек покрывает текущие потребности — последовательное выполнение с передачей контекста. Plan files с parallel stages и dependencies — это следующий уровень сложности, который пока не нужен.

### 4.2 🟢 Git Worktree Isolation

Наш оркестратор работает в CLI, один процесс, один working directory. Worktree isolation имеет смысл для параллельного выполнения — а у нас sequential execution.

### 4.3 🟢 Self-Evolution (Adaptive Governance)

Это R&D-уровень функциональности. Требует: persistent storage, machine learning, A/B testing. Неоправданно сложно для CLI-утилиты.

### 4.4 🟢 CI Autofix Integration

Отдельная область (CI/CD интеграция), не связанная с ядром оркестратора. Может быть реализована как отдельный use case позже.

---

## 5. Сводка рекомендаций

| Фича | Приоритет | Обоснование |
|---|---|---|
| Circuit Breaker | ✅ Реализовано | Предотвращает каскадные сбои |
| Quality Gates | ✅ Реализовано | Автоматическая проверка качества кода |
| Бюджетный контроль | ✅ Реализовано | Предотвращает runaway spending |
| Итерационные циклы | ✅ Реализовано | Closed-loop (цикл разработки) |
| Кросс-модельная верификация | ✅ Реализовано | Повышает надёжность критичных результатов |
| Audit Trail (JSONL) | ✅ Реализовано | Воспроизводимость и отладка |
| Plan Files | 🟡 P3 | Для parallel execution (пока не нужно) |
| Git Worktree Isolation | 🟢 — | Только для parallel execution |
| Self-Evolution | 🟢 — | R&D, слишком сложно |
| CI Autofix | 🟢 — | Отдельная область |

---

## 6. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории Bernstein:

- [`bernstein/circuit_breaker.py`](https://github.com/chernistry/bernstein/blob/main/bernstein/circuit_breaker.py) — полная реализация circuit breaker с 3 состояниями
- [`bernstein/governance.py`](https://github.com/chernistry/bernstein/blob/main/bernstein/governance.py) — бюджет, итерации, audit, policy enforcement
- [`bernstein/quality_gates.py`](https://github.com/chernistry/bernstein/blob/main/bernstein/quality_gates.py) — quality gates + cross-model verification
- [`bernstein.yaml`](https://github.com/chernistry/bernstein/blob/main/bernstein.yaml) — пример конфигурации governance policy
- [`plans/plan.yaml`](https://github.com/chernistry/bernstein/blob/main/plans/plan.yaml) — шаблон multi-stage плана с зависимостями
