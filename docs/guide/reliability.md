# Надёжность

## Retry Policy (повторные попытки)

Каждый шаг может иметь retry-policy — автоматический повтор при временных ошибках (таймаут, network error).

```yaml
chains:
  implement:
    retry_policy:                     # Глобальная политика для всех шагов
      max_retries: 3
      initial_delay_ms: 1000
      max_delay_ms: 30000             # Верхняя граница задержки
      multiplier: 2.0                 # Экспоненциальная задержка
    steps:
      - type: agent
        role: system_analyst
        retry_policy:                  # Переопределение на уровне шага
          max_retries: 5
```

**Поля RetryPolicyVo:**

| Поле | Тип | Описание |
|---|---|---|
| `max_retries` | `int` | Максимум повторных попыток |
| `initial_delay_ms` | `int` | Начальная задержка в мс |
| `max_delay_ms` | `int` | Верхняя граница задержки в мс |
| `multiplier` | `float` | Множитель экспоненциальной задержки |

**Архитектура:** `RetryingAgentRunner` оборачивает `AgentRunnerInterface`, при ошибке — повтор с задержкой.

## Circuit Breaker

Защита от каскадных сбоев — если runner последовательно падает N раз, он временно отключается.

**Состояния:** `closed` (норма) → `open` (заблокирован) → `half_open` (пробный запрос).

**Архитектура:** `CircuitBreakerAgentRunner` оборачивает `AgentRunnerInterface`,
состояние хранится в `CircuitBreakerStateVo` (`CircuitStateEnum`).

## Fallback

Роль может определить fallback-команду — альтернативный runner при недоступности основного.

```yaml
roles:
  backend_developer:
    prompt_file: docs/agents/roles/team/backend_developer.ru.md
    command: [pi, ...]
    fallback:
      command: [codex, --model, gpt-4o, ...]
```

**Архитектура:** `ResolveChainRunnerService` пытается выполнить шаг через основной runner,
при ошибке — через fallback. Результат: `StepResultDto::fallbackRunnerUsed`.

## Сессии и Resume

Dynamic-цепочки поддерживают **resume** — промежуточное состояние сохраняется в JSONL-файлы.

- `ChainSessionWriter` / `ChainSessionReader` / `ChainSessionLogger` — запись и чтение состояния сессии
- `ChainSessionStateVo` — VO состояния сессии
- `--resume <dir>` — возобновление прерванной сессии (через Presentation-слой)
