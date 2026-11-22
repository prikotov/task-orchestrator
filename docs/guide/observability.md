# Наблюдаемость

## Журнал аудита (Audit Trail, JSONL)

Оркестратор поддерживает опциональное audit-логирование в JSONL-файл с дозаписью (append-only).
Каждый запуск цепочки с журналом аудита (audit-log) создаёт записи четырёх типов:

```
{"ts":"2026-04-10T12:00:00+00:00","event":"chain_start","chain":"implement","task":"..."}
{"ts":"...","event":"step_start","chain":"implement","step":1,"role":"analyst","runner":"pi"}
{"ts":"...","event":"step_result","chain":"implement","step":1,"role":"analyst","runner":"pi","input_tokens":1500,"output_tokens":800,"cost":0.023,"duration_ms":5432,"status":"success"}
{"ts":"...","event":"chain_result","chain":"implement","steps_count":4,"total_cost":0.089,"duration_ms":45000,"status":"success"}
```

### Audit динамических цепочек

Dynamic-цепочки пишут тот же набор событий (`chain_start` / `step_start` / `step_result` /
`chain_result`) в `audit.jsonl` внутри каталога сессии (`var/sessions/<chain>/<date>_<time>/audit.jsonl`);
отключается опцией `--no-audit-log`. Записи `step_result` динамического шага содержат модельные поля
и кэш-токены:

```
{"ts":"...","event":"step_result","chain":"brainstorm","step":3,"role":"backend_developer","runner":"pi","input_tokens":1500,"output_tokens":800,"cache_read_tokens":8200,"cache_write_tokens":900,"model":"glm-5.3-flash","provider":"zai","thinking":"high","cost":0.023,"duration_ms":5432,"status":"success"}
```

- `model`, `provider`, `thinking` — модель шага; `null`, если раннер не сообщил (старые сессии, fallback-раннеры).
- `cache_read_tokens` / `cache_write_tokens` — кэш-токены из `usage` финального `message_end`; `0`, если раннер их не отдаёт.

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

- **Опциональность (Optional)**: audit отключен по умолчанию, включается через опции запуска
- **Потокобезопасность (Thread-safe)**: `LOCK_EX` для атомарной записи при параллельных запусках
- **Дозапись (Append-only)**: существующий файл дописывается, не перезаписывается
- **Автосоздание (Auto-create)**: директория и файл создаются автоматически при первой записи
- **Status**: `"success"` / `"error"` — определяется по `isError` флагу результата шага
- **Quality gate**: логируется как шаг с `runner: "quality_gate"`, `tokens: 0`, `cost: 0`

### Конфигурация пути

Путь к audit-логу настраивается через параметр конфигурации:

```yaml
# config/packages/task_orchestrator.yaml
task_orchestrator:
    audit_log_path: '%kernel.project_dir%/var/log/agent_audit.jsonl'
```

### Ограничения

- Audit static-цепочек настраивается глобально (`audit_log_path`); dynamic-цепочки пишут audit в каталог своей сессии независимо от этой настройки (отключение — `--no-audit-log`).
- Проваленный quality gate (`passed: false`) логируется со `status: "success"` (gate выполнился без ошибки, результат проверки — отдельная информация).

## Budget (ограничение стоимости)

Цепочки поддерживают опциональный бюджет — ограничение стоимости в долларах США (USD).
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
- `ReportFormatEnum` — `text` | `json`
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

Отчёты динамических цепочек дополнительно содержат секцию «Participants metrics» — агрегированную
по ролям таблицу участников (модель, выступления, время, токены, кэш, стоимость):

```
Participants metrics
Participant       Model                     Speeches Facilitator Time   Tok in Tok out Cache read Cache % Cost
team_lead_alex    zai/glm-5.3-flash (high)  0        1           8.0s   1.0k   500     8.0k       88.9%   $0.0200
backend_developer zai/glm-5.3-flash (high)  2        0           22.0s  4.0k   3.0k    10.0k      71.4%   $0.1000
```

- Модель рендерится как `provider/model (thinking)`; «н/д» — если раннер не сообщил модель
  (раунды старых сессий без новых полей). При разных моделях у участника — маркер `mixed`.
- `Cache %` = `cache_read / (cache_read + input)`; «н/д» — если токены не сообщены.

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
