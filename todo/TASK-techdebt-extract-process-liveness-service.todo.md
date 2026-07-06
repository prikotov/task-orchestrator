---
type: chore
created: 2026-07-06
value: V1
complexity: C2
priority: P3
depends_on:
epic:
author: Тимлид Алекс
assignee:
branch:
pr:
status: todo
---

# TASK-techdebt-extract-process-liveness-service: Вынести liveness-логику в общий service

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Liveness-adaptive timeout реализован идентично в двух раннерах — `PiAgentRunnerService` (#297) и `CodexAgentRunnerService` (#298). Четыре метода (`waitForProcessWithLiveness`, `readProcessCpuTime`, `readProcessIo`, `envInt`) дублируются байт-в-байт. Это DRY-нарушение: багфикс/тюнинг liveness придётся вносить в двух местах, разойтись они могут незаметно.

### Варианты или путь решения (Solution Sketch)
Вынести liveness в отдельный service `ProcessLivenessWatcher` (Infrastructure-слой, модуль AgentRunner). Service принимает `Process`, возвращает `bool` (completed/idle-killed); env-чтение (`AGENT_RUNNER_*`) и I/O (`ps`, `/proc/<pid>/io`) — внутри service. Раннеры инжектируют его через constructor и делегируют вызов.

**Почему именно service, а не trait/helper:** конвенции это запрещают.
- `docs/conventions/core_patterns/trait.md`: trait не может использовать скрытые источники данных (`getenv`/`$_ENV`/`$_SERVER`) — liveness их читает.
- `docs/conventions/core_patterns/helper.md`: helper не может делать I/O (`shell_exec`, `file_get_contents('/proc/...')`) — liveness их делает.

Service — единственный законный по конвенциям вариант.

## Контекст

- Источник замечания: code-review PR #298 (Пуаро, REQUEST CHANGES).
- Симметрия реализована: pi (#297, merged), codex (#298).
- Параметры liveness: `AGENT_RUNNER_HARD_TIMEOUT_SEC` (default 1800), `AGENT_RUNNER_IDLE_TIMEOUT_SEC` (default 60).
- Тесты liveness есть в обоих runner-test'ах (`runKillsIdleProcessAsTransient`, `runLetsActiveProcessSurvive`) — после рефакторинга их надо адаптировать (service можно мокать) и добавить unit-тесты на сам `ProcessLivenessWatcher`.

## Критерии готовности (Definition of Done)

- [ ] Создан `ProcessLivenessWatcher` (Infrastructure, AgentRunner) с методом `waitFor(Process $process): bool` и helper'ами чтения CPU/IO/env.
- [ ] `PiAgentRunnerService` и `CodexAgentRunnerService` инжектируют service через constructor, делегируют liveness-ожидание ему.
- [ ] DI-конфиг (`config/`) обновлён — service регистрируется и автосвязывается в оба раннера.
- [ ] Дублирование методов удалено из обоих раннеров.
- [ ] Unit-тесты: `ProcessLivenessWatcherTest` покрывает idle-kill + active-survive (+ edge: pid=null, /proc недоступен, ps недоступен).
- [ ] Существующие runner-тесты адаптированы (мок service или integration через реальный process).
- [ ] Psalm 0, PHPUnit green, Deptrac/PHPCS чисто.
- [ ] Поведение env-рук (`AGENT_RUNNER_*`) сохранено, дефолты те же.
