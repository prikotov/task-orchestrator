---
type: fix
created: 2026-07-01
value: V2
complexity: C3
priority: P1
depends_on:
epic:
author: Тимлид (Алекс)
assignee: backend_developer_levsha
branch: task/fix-pi-jsonl-parser-error-contract
pr: '#286'
status: done
---

# TASK-fix-pi-jsonl-parser-error-contract: PiJsonlParser не распознаёт ошибки модели (stopReason:error) и маскирует их под «пустой успешный ответ»

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Когда AI-провайдер недоступен (нет API-ключа, 5xx, лимиты), CLI `pi` завершается с кодом 0, но сообщает об ошибке **внутри JSONL** полями `stopReason:"error"` и `errorMessage:"..."`. Наш парсер `PiJsonlParser` эти поля игнорирует: он считает запуск «успешным», но с пустым текстом ответа. Дальше `ExecuteDynamicTurnService` маскирует это под обобщённое `Agent returned empty output.`, полностью затирая реальную причину (например, `No API key for provider: openai-codex`). В результате пользователь и上层-оркестратор видят бессмысленную заглушку вместо диагноза, а расследование превращается в ручное воспроизведение вызова.

### Варианты или путь решения (Solution Sketch)
Научить `PiJsonlParser` распознавать `stopReason:"error"` + `errorMessage` в событиях `message_end`/`turn_end`/`agent_end` и отдавать структурный сигнал ошибки (`isError` + `errorMessage`) из `result()`. Тогда `PiAgentRunnerService::run()` при ошибке модели вернёт `AgentResultVo::createError(errorMessage: <реальный текст>)` — и реальная причина дойдёт до артефактов сессии (`_4_error.md`, audit) и до пользователя. Сигнал берётся из структурных полей JSONL, а не из парсинга свободного текста stderr — это тестируемо и не хрупко.

### Ожидаемый результат (Expected Result)
JSONL с `stopReason:"error"` + `errorMessage` → `AgentResultVo::isError() === true` и `getErrorMessage()` содержит точный текст из JSONL. Реальная причина (`No API key for provider: openai-codex`) видна в артефактах сессии и audit вместо `Agent returned empty output.`. Happy-path, text_delta-fallback и OOM-тесты не сломаны. `make check` полностью зелёный.

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда `pi` CLI не может выполнить запрос к провайдеру (нет API-ключа, 5xx, лимиты, невалидный запрос), pi завершается с `exit 0`, но сообщает об ошибке **внутри JSONL-потока** полями `stopReason:"error"` и `errorMessage:"..."`. Я хочу, чтобы `PiJsonlParser` и `PiAgentRunnerService` распознавали эту ошибку и возвращали `AgentResultVo::createError(...)` с реальным текстом, чтобы上层-оркестратор (ChainExecution) и пользователь видели истинную причину, а не обобщённую заглушку `Agent returned empty output.`

### Goal (Цель по SMART)
Воспроизводимый, тестовый путь: JSONL с `stopReason:"error"` + `errorMessage` → `PiAgentRunnerService::run()` возвращает `AgentResultVo::createError(errorMessage: <реальный текст>, exitCode: ...)`. Поле `errorMessage` сохраняется в артефактах сессии (`_4_error.md`, audit). Никакой маскировки под «empty output» для этого класса ошибок.

## 2. Context and Scope (Контекст и Границы)

### Инцидент-первоисточник
- **Brainstorm-сессия** `var/sessions/brainstorm/2026-07-01_03-35-24/` (тема: механизм fallback раннера сабагента) упала на Step 10 (Round 5) на роли `system_analyst_sherlock` (`runner: pi`, `provider: openai-codex`).
- `audit.jsonl` показал: `input_tokens: 0`, `output_tokens: 0`, `duration_ms: 1078`, `status: error`, `error_message: "Agent returned empty output."`
- Воспроизведение вызова `pi --mode json -p --no-session --provider openai-codex --model gpt-5.5 --thinking high "Reply with exactly: PONG"` дало **exit 0**, но в stdout-JSONL:
  ```json
  {"type":"message_end","message":{"role":"assistant","content":[],"provider":"openai-codex","model":"gpt-5.5","usage":{"input":0,"output":0,"totalTokens":0,"cost":{"total":0}},"stopReason":"error","errorMessage":"No API key for provider: openai-codex"}}
  {"type":"turn_end","message":{... ,"stopReason":"error","errorMessage":"No API key for provider: openai-codex"},"toolResults":[]}
  {"type":"agent_end","messages":[{...,"role":"assistant","content":[],"stopReason":"error","errorMessage":"No API key for provider: openai-codex"}],"willRetry":false}
  ```
- **Корневая причина:** pi выходит с `exit 0`, сообщая об ошибке через `stopReason:"error"` + `errorMessage` в JSONL. `PiJsonlParser` эти поля **не читает**: из `message_end`/`agent_end` он извлекает только `usage` и текст последнего assistant-сообщения (которое пустое). `PiJsonlParser::result()` не содержит флага ошибки. Поэтому `PiAgentRunnerService::run()` возвращает `AgentResultVo::createSuccess(outputText:'', 0 токенов)`, а `ExecuteDynamicTurnService::normalizeEmptySuccessfulOutput()` маскирует это под `"Agent returned empty output."`, полностью затирая реальную причину.

### Где делаем (слой Infrastructure, модуль AgentRunner)
- `src/Module/AgentRunner/Infrastructure/Service/Pi/PiJsonlParser.php` — распознать `stopReason:"error"` + `errorMessage`.
- `src/Module/AgentRunner/Infrastructure/Service/Pi/PiAgentRunnerService.php` (`run()`) — вернуть `AgentResultVo::createError(...)` при ошибке модели.
- `tests/Unit/Infrastructure/Service/AgentRunner/Pi/PiJsonlParserTest.php`, `PiAgentRunnerTest.php` — тесты на error-контракт.

### Границы (Out of Scope)
- ❌ **Observability**: сохранение сырого stdout/stderr в артефакты сессии при exit 0 — отдельная задача `TASK-fix-pi-runner-raw-output-telemetry` (TODO, P2).
- ❌ **Конфигурация провайдера**: отсутствие ключа `openai-codex` у роли Sherlock (и аналогичные роли на `pi`+`openai-codex`) — отдельная задача `TASK-fix-sherlock-runner-provider-config` (TODO, P2).
- ❌ Классификация ошибок провайдера (provider_unavailable vs business_error) для **автоматического** fallback — это тема `TASK-feat-runner-provider-fallback`; данная задача лишь даёт **структурный сигнал** (isError + errorMessage), на котором будущая классификация будет строиться.
- ❌ Аналогичный фикс для `CodexJsonlParser`/`CodexAgentRunnerService` — только если в рамках воспроизведения выяснится, что codex имеет ту же дыру; иначе — отдельная задача.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] `PiJsonlParser` распознаёт ошибку модели: `stopReason:"error"` (или `"stop_reason":"error"`) + `errorMessage` в событиях `message_end` / `turn_end` / `agent_end`.
- [ ] `PiJsonlParser::result()` отдаёт структурный сигнал ошибки (например, поля `isError: bool` и `errorMessage: string`), сохраняя обратную совместимость существующих ключей.
- [ ] `PiAgentRunnerService::run()` при `isError` возвращает `AgentResultVo::createError(errorMessage: <реальный текст из JSONL>)` — **НЕ** `createSuccess`.
- [ ] В случае ошибки модели `outputText` НЕ маскируется под `Agent returned empty output.`: в `_4_error.md`/audit попадает реальный `errorMessage` (например, `No API key for provider: openai-codex`).
- [ ] Существующее поведение happy-path и text_delta-fallback **не сломано** (все текущие тесты `PiJsonlParserTest`/`PiAgentRunnerTest` зелёные).
- [ ] Новые unit-тесты:
  - `PiJsonlParser`: feed JSONL с `stopReason:"error"` + `errorMessage` → `result()` сигнализирует ошибку с точным текстом. Фикстура — реальный JSONL из инцидента (см. п.2). Вариант: `stopReason:"error"` без `errorMessage` → ошибка с fallback-сообщением.
  - `PiAgentRunnerService` (через Process-mock/fake stdout): JSONL с ошибкой модели → `AgentResultVo::isError() === true` и `getErrorMessage()` содержит текст из JSONL.

### 🟡 Should Have (Желательно)
- [ ] Учесть, что pi может дублировать `stopReason:"error"` в `message_end`, `turn_end` и `agent_end` одновременно — брать первое/наиболее осмысленное сообщение, не терять и не дублировать токены.

### 🟢 Could Have (Опционально)
- [ ] Если в рамках фикса обнаружится, что `CodexAgentRunnerService` имеет аналогичную дыру (codex exit 0 + ошибка в JSONL) — НЕ чинить здесь, а зафиксировать отдельной задачей со ссылкой на эту.

### ⚫ Won't Have (Не будем делать)
- [ ] Observability: сохранение сырого stdout/stderr в артефакты сессии при exit 0 — отдельная задача `TASK-fix-pi-runner-raw-output-telemetry` (TODO, P2).
- [ ] Конфигурация провайдера: отсутствие ключа `openai-codex` у роли Sherlock (и аналогичные роли на `pi`+`openai-codex`) — отдельная задача `TASK-fix-sherlock-runner-provider-config` (TODO, P2).
- [ ] Автоматическая классификация ошибок (provider_unavailable vs business_error) и сам fallback — тема `TASK-feat-runner-provider-fallback`; эта задача даёт лишь структурный сигнал, на котором будущая классификация будет строиться.
- [ ] Аналогичный фикс для `CodexAgentRunnerService`/`CodexJsonlParser` — только если обнаружится та же дыра; иначе отдельной задачей.

## 4. Implementation Plan (План реализации)
Исполнитель заполняет перед стартом. Рекомендуемый порядок:
1. Прочитать `PiJsonlParser.php`, `PiAgentRunnerService.php`, `AgentResultVo.php`, существующие тесты.
2. Добавить в `PiJsonlParser` поля `bool $hasError`, `string $errorMessage`, обработку `stopReason`/`errorMessage` в `feed()` (для `message_end`, `turn_end`, `agent_end`).
3. Расширить `result()` ключами `isError`, `errorMessage`.
4. В `PiAgentRunnerService::run()` — после `$parsed = $this->parser->result()` ветка: если `isError` → `createError`.
5. Тесты (unit): парсер + сервис.

## 5. Definition of Done (Критерии приёмки)
- [ ] JSONL с `stopReason:"error"` + `errorMessage` → `AgentResultVo::isError() === true`, `getErrorMessage()` = текст из JSONL.
- [ ] Happy-path / text_delta fallback / OOM-тесты — зелёные (нет регрессии).
- [ ] `make check` (phpunit + psalm; при необходимости phpcs/deptrac) — зелёный.
- [ ] Слой Infrastructure (AgentRunner), Domain-контракт (`AgentResultVo`) не нарушен; не добавлено лишних зависимостей.
- [ ] Соблюдены Конвенции (`docs/conventions/index.md`).

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Infrastructure/Service/AgentRunner/Pi/
vendor/bin/psalm
make check
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Backward compat `result()`**: изменение shape массива `result()` может затронуть других потребителей. Проверить все вызовы `result()`; добавлять ключи (additive), не удалять существующие.
- **Двойная нормализация**: после фикса `isError=true` уже НЕ попадает в `normalizeEmptySuccessfulOutput()` (он работает только для `isError=false`). Убедиться, что true-ошибки доходят до `ExecuteDynamicTurnService` корректно (`agent_error` interruption).
- Связь: закрывает Action Item «Investigate empty Sherlock responses» из ретроспективы `docs/agents/team-retro/2026-06-30_20-10-pi-jsonl-parser-oom-fix.md` (строка ~68: «первопричина не установлена, сырой stdout не сохранялся»).

## 8. Sources (Источники)
- Brainstorm-сессия `var/sessions/brainstorm/2026-07-01_03-35-24/` (audit.jsonl, step_010 error).
- Ретроспектива `docs/agents/team-retro/2026-06-30_20-10-pi-jsonl-parser-oom-fix.md` (Action Item «empty Sherlock responses»).
- Реплики Архитектора Гэндальфа / Бэкендера Левши в той же brainstorm-сессии: «нужен структурный контракт ошибки (provider_unavailable / agent_business_error / invalid_request / unknown)».

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-01 | Тимлид (Алекс) | Создание задачи по итогам расследования падения brainstorm-сессии (pi exit 0 + stopReason:error маскируется под «empty output»). |
