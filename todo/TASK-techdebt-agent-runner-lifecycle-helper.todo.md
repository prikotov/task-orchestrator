---
type: refactor
created: 2026-08-20
value: V1
complexity: C2
priority: P3
depends_on:
epic:
author: Тимлид Алекс (pi)
assignee: Бэкендер Левша (pi)
branch: task/agent-runner-lifecycle-helper
pr:
status: in_progress
---

# TASK-techdebt-agent-runner-lifecycle-helper: Общий компонент жизненного цикла раннеров Codex/Pi

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

Декомпозиция жизненного цикла процесса агента (создание `Process`, подготовка прокси-окружения, буферизация stdout в JSONL-парсер, ожидание с liveness-контролем, обработка таймаутов и сигналов, гарантированная остановка процесса и моста, сборка результата) реализована практически байт-в-байт в двух раннерах — `CodexAgentRunnerService` и `PiAgentRunnerService`. Это ~10 методов и константа на каждый раннер. Любой багфикс или тюнинг жизненного цикла придётся вносить в двух местах, и копии могут незаметно разойтись.

Замечание №1 ревью PR #356 (Пуаро), подтверждено ретроспективой 2026-08-20.

### Варианты или путь решения (Solution Sketch)

Вынести общий жизненный цикл в отдельный Infrastructure-компонент модуля AgentRunner (сервис по прецеденту `ProcessLivenessWatcher`), раннеры делегируют ему исполнение. Раннер-специфика (buildCommand, JSONL-парсер, обработка Pi `isError`) остаётся в раннерах.

**Почему не Helper и не trait** (конвенции, прецедент TASK-techdebt-extract-process-liveness-service):
- `docs/conventions/core-patterns/helper.md`: helper — только статические pure-методы без I/O; здесь I/O (Symfony Process, `getenv`, старт/стоп моста).
- `docs/conventions/core-patterns/trait.md`: trait не может использовать скрытые источники данных (`getenv`); жизненный цикл их читает.

Точный дизайн (имя, контракт парсеров, границы hook'ов) фиксируется на этапе проектирования — Архитектор (RACI `refactor`: DS обязательно).

### Ожидаемый результат (Expected Result)

Дублирование декомпозиции жизненного цикла устранено: правка жизненного цикла (таймауты, прокси, буферизация) делается в одном месте и автоматически действует на оба раннера. Поведение обоих раннеров для внешнего наблюдателя не изменилось (контракты, сообщения об ошибках, env-переменные).

## 1. Концепция и Цель (Concept and Goal)

### История (User Story или Job Story)

> **Job Story:** Когда мне нужно поменять поведение жизненного цикла процесса агента (таймауты, прокси-мост, буферизация stdout), я хочу иметь одну точку изменения вместо двух идентичных копий в Codex/Pi-раннерах, чтобы правка гарантированно применялась к обоим раннерам и не порождала расхождений.

### Цель по SMART (Goal)

К концу задачи (1 рабочий сессия): дублируемые методы жизненного цикла (`run()`-декомпозиция, `createConfiguredProcess`, `attachProxyEnvironment`, `stopProcessAndBridge`, `buildProcessEnv`, `createBridgeIfNeeded`, `buildUserPrompt`, `bufferStdoutChunk`, `flushStdoutBuffer`, `appendErrorOutputTail`, `resolveSystemPromptPath`, `ERROR_OUTPUT_TAIL_BYTES`) существуют в единственном экземпляре в Infrastructure модуля AgentRunner; оба раннера делегируют ему; `make check` зелёный без изменений поведения.

## 2. Контекст и Границы (Context and Scope)

*   **Где делаем:** `src/Module/AgentRunner/Infrastructure/Service/` (Codex/, Pi/, общий сервис), DI-конфигурация в `config/`, тесты в `tests/Unit/` и `tests/Integration/`.
*   **Текущее поведение:** `CodexAgentRunnerService` (538 строк) и `PiAgentRunnerService` (532 строки) содержат идентичные приватные/public-методы жизненного цикла. Парсеры `CodexJsonlParser` и `PiJsonlParser` — независимые final-классы без общего контракта (`reset()`/`feed()`/`result()`).
*   **Отличия раннеров, которые надо сохранить:**
    - сообщение о завершении сигналом: `codex process terminated by signal %d` / `pi process terminated by signal %d`;
    - `buildResult()`: Pi дополнительно проверяет `isError`/`errorMessage` парсера (агент сообщил об ошибке в JSONL при exit 0);
    - `buildCommand()`: CLI-семантика codex/pi принципиально разная — НЕ дедуплицировать.
*   **Границы (Out of Scope):**
    - логика JSONL-парсеров (формат событий, метрики) — не трогаем;
    - `buildCommand()` раннеров — не трогаем;
    - `ProcessLivenessWatcher` и ProcessLiveness-компоненты — не трогаем (уже дедуплицированы);
    - `HttpsProxyBridge` — не трогаем, только переиспользуем;
    - Domain/Application слои и публичные контракты (`AgentRunnerInterface`, VO) — не трогаем.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)

- [ ] Единая точка жизненного цикла процесса агента в Infrastructure модуля AgentRunner (Infrastructure-сервис; NOT Helper/Port/Adapter — конвенции и терминология AGENTS.md).
- [ ] Оба раннера используют общий компонент; перечисленные в Цели дублируемые методы удалены из обоих раннеров.
- [ ] Поведение идентично: контракты `AgentRunnerInterface`, сообщения об ошибках (включая имя раннера в signal-сообщении), env-переменные (`CODEX_HTTP_PROXY`, `AGENT_RUNNER_*`), idle/hard-cap семантика.
- [ ] Pi-специфика `isError` сохранена: ошибка из JSONL приоритетнее exit-кода.
- [ ] Общий контракт JSONL-парсеров (интерфейс `reset`/`feed`/`result`) или эквивалентное решение — фиксирует проектирование.
- [ ] Публичные методы `buildProcessEnv()`/`createBridgeIfNeeded()` (используются тестами) сохранены или заменены эквивалентом с обновлёнными тестами.
- [ ] DI-конфигурация обновлена (регистрация, автосвязка).
- [ ] Unit-тесты на новый компонент (покрытие ≥80% нового кода), существующие тесты раннеров адаптированы.
- [ ] Дизайн-решение зафиксировано Архитектором до реализации (RACI `refactor`: DS обязательный этап).

### 🟡 Желательно (Should Have)

- [ ] Размер обоих раннер-классов заметно сокращается (цель: <400 строк каждый).
- [ ] Дублируемые фрагменты идентифицированы и устранены поимённо (список в Цели — чеклист для ревью).

### 🟢 Опционально (Could Have)

- [ ] Документация компонента в PHPDoc с обоснованием design-решения (как в `ProcessLivenessWatcher`).

### ⚫ Не будем делать (Won't Have)

- [ ] Изменение поведения, форматов вывода, сообщений об ошибках.
- [ ] Дедупликация `buildCommand()`/парсеров.
- [ ] Косметический рефакторинг смежного кода вне списка дубликатов.

## 4. План реализации (Implementation Plan)

<!-- Заполняется на этапе проектирования (Архитектор) и уточняется исполнителем (Reverse Briefing). -->

1. [ ] DS: Архитектор фиксирует дизайн (имя и границы сервиса, контракт парсеров, hook для Pi `isError`, судьба публичных `buildProcessEnv`/`createBridgeIfNeeded`).
2. [ ] ...
3. [ ] ...

## 5. Критерии приёмки (Definition of Done)

- [ ] Дублируемые методы жизненного цикла удалены из обоих раннеров, существуют в одном экземпляре.
- [ ] Поведение обоих раннеров не изменилось (никаких правок контрактов/сообщений/env).
- [ ] Новые Unit-тесты на общий компонент; существующие тесты адаптированы и зелёные.
- [ ] `make check` зелёный (PHPUnit, Psalm, PHPStan, Deptrac, phpmd, phpcs).
- [ ] Нет регрессий в смежных модулях (ChainExecution, DynamicLoop используют раннеры).

## 6. Самопроверка (Verification)

```bash
make check                                            # lint + валидация + тесты
php vendor/bin/todo-md validate todo/TASK-techdebt-agent-runner-lifecycle-helper.todo.md
```

## 7. Риски и зависимости (Risks and Dependencies)

- Риск регрессии в обработке таймаутов/сигналов: высокая цена ошибки в рантайме оркестрации. Митигируется: сохранение сообщений и порядка операций, полное тест-покрытие, ревью Пуаро.
- Сокетные/процессные тесты раннеров чувствительны к окружению: прогонять в полноценном окружении (прецедент PR #356).
- Зависимостей от других задач нет.

## 8. Источники (Sources)

- [ ] [Ретроспектива 2026-08-20 (предложение техдолга, замечание №1)](../docs/agents/team-retro/2026-08-20_00-36-techdebt-phpmd-lengths.md)
- [ ] [PR #356 — источник замечания ревью](https://github.com/prikotov/task-orchestrator/pull/356)
- [ ] [Прецедент: TASK-techdebt-extract-process-liveness-service](done/TASK-techdebt-extract-process-liveness-service.todo.md)
- [ ] [Конвенция Helper](../docs/conventions/core-patterns/helper.md)
- [ ] [Конвенция Service](../docs/conventions/core-patterns/service.md)
- [ ] [Матрица RACI — refactor](../docs/agents/raci-matrix.md)

## 9. Комментарии (Comments)

- Источник: ретроспектива задачи `TASK-techdebt-phpmd-lengths` (PR #356), предложение 🟢 «Завести техдолг TASK-techdebt-agent-runner-lifecycle-helper», подтверждено владельцем 2026-08-20.
- По завершении: обновить реестр ретро `docs/agents/team-retro/RETRO-ROADMAP.md` (отметить предложение выполненным).

## История изменений (Change History)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-20 | Тимлид Алекс (pi) | Создание задачи (по подтверждению владельца, из ретро PR #356) |
