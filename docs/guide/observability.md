# Наблюдаемость

## Audit Trail (JSONL)

Оркестратор поддерживает optional audit-логирование в append-only JSONL-файл.
Каждый запуск цепочки с audit-log создаёт записи четырёх типов:

```
{"ts":"2026-04-10T12:00:00+00:00","event":"chain_start","chain":"implement","task":"..."}
{"ts":"...","event":"step_start","chain":"implement","step":1,"role":"analyst","runner":"pi"}
{"ts":"...","event":"step_result","chain":"implement","step":1,"role":"analyst","runner":"pi","input_tokens":1500,"output_tokens":800,"cost":0.023,"duration_ms":5432,"status":"success"}
{"ts":"...","event":"chain_result","chain":"implement","steps_count":4,"total_cost":0.089,"duration_ms":45000,"status":"success"}
```

### Архитектура audit-логирования

```
Domain:
  AuditLoggerInterface — контракт (logChainStart, logStepStart, logStepResult, logChainResult)
  AuditLoggerFactoryInterface — create(filePath): AuditLoggerInterface
  ChainResultAuditDto — параметры logChainResult (stepsCount, totalCost, stepStatuses, …)
  StepAuditStatusDto — isError-статус шага
    ↑
Application:
  ExecuteStaticChainService — вызывает audit logger на каждом этапе static-цепочки
  OrchestrateChainCommandHandler — resolveAuditLogger() через фабрику
    ↑
Infrastructure:
  JsonlAuditLogger — запись в JSONL (FILE_APPEND | LOCK_EX, UTC timestamp)
  JsonlAuditLoggerFactory — создание логгера с заданным файловым путём
```

### Особенности

- **Optional**: audit отключен по умолчанию, включается через опции запуска
- **Thread-safe**: `LOCK_EX` для атомарной записи при параллельных запусках
- **Append-only**: существующий файл дописывается, не перезаписывается
- **Auto-create**: директория и файл создаются автоматически при первой записи
- **Status**: `"success"` / `"error"` — определяется по `isError` флагу результата шага
- **Quality gate**: логируется как шаг с `runner: "quality_gate"`, `tokens: 0`, `cost: 0`

### Конфигурация пути

Путь к audit-логу настраивается через параметр bundle:

```yaml
# config/packages/task_orchestrator.yaml
task_orchestrator:
    audit_log_path: '%kernel.project_dir%/var/log/agent_audit.jsonl'
```

### Ограничения

- Audit trail поддерживается только для **static-цепочек**. Dynamic-цепочки будут покрыты в отдельной задаче.
- Failed quality gate (`passed: false`) логируется со `status: "success"` (gate выполнился без ошибки, результат проверки — отдельная информация).

## Budget (ограничение стоимости)

Цепочки поддерживают optional бюджет — ограничение стоимости в USD.
Проверяется перед и после каждого шага.

### Конфигурация бюджета

```yaml
chains:
  implement:
    description: "Цепочка с бюджетом"
    steps:
      - { type: agent, role: system_analyst }
      - { type: agent, role: backend_developer }
    budget:
      max_cost_total: 5.0            # Максимум $5 на всю цепочку (null = безлимит)
      max_cost_per_step: 1.5         # Максимум $1.50 за один шаг (null = безлимит)
      per_role:                      # Per-role бюджеты (опционально)
        backend_developer:
          max_cost_total: 2.0
        code_reviewer_backend:
          max_cost_total: 1.0
          max_cost_per_step: 0.5
```

### Поведение при превышении

- **Перед шагом**: если суммарная стоимость уже ≥ `max_cost_total` — цепочка прерывается с `budgetExceeded = true`.
- **После шага**: если стоимость шага превысила `max_cost_per_step` — предупреждение, цепочка продолжается.
- Результат: `OrchestrateChainResultDto::budgetExceeded`, `budgetLimit`, `budgetExceededRole`.

### Архитектура

```
Domain:
  BudgetVo — maxCostTotal, maxCostPerStep, perRole
  CheckStaticBudgetService — проверка бюджета static-цепочки
  CheckStaticBudgetServiceInterface — контракт static-бюджета
  CheckDynamicBudgetServiceInterface — контракт dynamic-бюджета
    ↑
Infrastructure:
  CheckDynamicBudgetService — реализация проверки dynamic-бюджета
```

## Отчёты (Reports)

После выполнения цепочки можно сгенерировать отчёт в формате `text` или `json`.

- `GenerateReportQuery` / `GenerateReportQueryHandler` — генерация отчёта
- `ReportFormatEnum` — text | json
- `ReportTextMapper` / `ReportJsonMapper` — маппинг DTO → формат

### Text-формат

```
=== Chain: implement ===
Steps: 5 | Duration: 45s | Cost: $0.089

[1/5] system_analyst @ pi    ✓  ↑3.1k ↓1.2k  $0.009  8s
[2/5] system_architect @ pi  ✓  ↑4.5k ↓2.0k  $0.016  11s
[3/5] backend_developer @ pi ✓  ↑12k ↓5.8k   $0.042  25s
[4/5] code_reviewer @ pi     ✓  ↑2.1k ↓0.9k  $0.008  6s
[5/5] qa_backend @ pi        ✓  ↑1.0k ↓0.5k  $0.014  4s

Budget: $0.089 / $5.00 (1.8%)
Iterations: 0
```

### JSON-формат

```json
{
  "chain": "implement",
  "status": "success",
  "steps_count": 5,
  "total_cost": 0.089,
  "total_duration_ms": 45000,
  "steps": [
    {"role": "system_analyst", "runner": "pi", "status": "success", "input_tokens": 3100, "output_tokens": 1200, "cost": 0.009, "duration_ms": 8000},
    ...
  ]
}
```
