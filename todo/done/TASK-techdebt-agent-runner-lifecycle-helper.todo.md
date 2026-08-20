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
pr: 'https://github.com/prikotov/task-orchestrator/pull/357'
status: done
---

# TASK-techdebt-agent-runner-lifecycle-helper: Общий компонент жизненного цикла раннеров Codex/Pi

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

Декомпозиция жизненного цикла процесса агента (создание `Process`, подготовка прокси-окружения, буферизация stdout в JSONL-парсер, ожидание с liveness-контролем, обработка таймаутов и сигналов, гарантированная остановка процесса и моста, сборка результата) реализована практически байт-в-байт в двух раннерах — `CodexAgentRunnerService` и `PiAgentRunnerService`. Это ~10 методов и константа на каждый раннер. Любой багфикс или тюнинг жизненного цикла придётся вносить в двух местах, и копии могут незаметно разойтись.

Замечание №1 ревью PR #356 (Пуаро), подтверждено ретроспективой 2026-08-20.

### Варианты или путь решения (Solution Sketch)

**DS Архитектора:** вынести общий жизненный цикл в Infrastructure-сервис
`RunAgentProcessLifecycleService` с внутренним контрактом
`RunAgentProcessLifecycleServiceInterface` в namespace
`TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Lifecycle`.
Интерфейс размещается рядом с реализацией в Infrastructure, потому что компонент
используется только инфраструктурными раннерами и задача запрещает трогать
Domain/Application; это локальная техническая граница, не Domain port.

Сервис владеет общей частью runtime-жизненного цикла: `Process` creation, hard-cap
настройка, working dir, proxy env/`HttpsProxyBridge`, stdout JSONL buffering, stderr
tail, ожидание через `ProcessLivenessWatcher`, idle/hard-cap/signal маппинг в
`AgentResultVo`, гарантированный stop процесса и моста.

Раннеры сохраняют свою специфику:
- `buildCommand()` остаётся в `CodexAgentRunnerService` и `PiAgentRunnerService` и **не дедуплицируется**;
- `buildResult()` остаётся runner-specific hook'ом: Codex строит success/error по своему parser result, Pi дополнительно обрабатывает `isError`/`errorMessage`;
- имя раннера передаётся в lifecycle-сервис параметром `runnerName`, поэтому signal-сообщения остаются точными (`codex process ...` / `pi process ...`).

Контракт с JSONL-парсерами — через callbacks/hooks, а не через новый общий
parser-interface: сервису нужны только `reset()` и `feed(string $line)`, а `result()`
имеет разные array-shape у Codex/Pi и интерпретируется в `buildResult()` раннера.
Это не создаёт искусственный общий тип, не ломает текущие parser-тесты и сохраняет
Pi-специфику `isError` в раннере.

Публичные тестовые seam-методы `buildProcessEnv()` и `createBridgeIfNeeded()`
переносятся из обоих раннеров в `RunAgentProcessLifecycleService`; тесты должны
проверять их на новом сервисе. Обёртки в раннерах не оставлять: цель задачи — убрать
дублируемые lifecycle-методы из обоих классов.

**Почему не Helper и не trait** (конвенции, прецедент TASK-techdebt-extract-process-liveness-service):
- `docs/conventions/core-patterns/helper.md`: helper — только статические pure-методы без I/O; здесь I/O (Symfony Process, `getenv`, старт/стоп моста).
- `docs/conventions/core-patterns/trait.md`: trait не может использовать скрытые источники данных (`getenv`); жизненный цикл их читает.

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

- [x] Единая точка жизненного цикла процесса агента в Infrastructure модуля AgentRunner (Infrastructure-сервис; NOT Helper/Port/Adapter — конвенции и терминология AGENTS.md).
- [x] Оба раннера используют общий компонент; перечисленные в Цели дублируемые методы удалены из обоих раннеров.
- [x] Поведение идентично: контракты `AgentRunnerInterface`, сообщения об ошибках (включая имя раннера в signal-сообщении), env-переменные (`CODEX_HTTP_PROXY`, `AGENT_RUNNER_*`), idle/hard-cap семантика.
- [x] Pi-специфика `isError` сохранена: ошибка из JSONL приоритетнее exit-кода.
- [x] Общий контракт JSONL-парсеров (интерфейс `reset`/`feed`/`result`) или эквивалентное решение — фиксирует проектирование.
- [x] Публичные методы `buildProcessEnv()`/`createBridgeIfNeeded()` (используются тестами) сохранены или заменены эквивалентом с обновлёнными тестами.
- [x] DI-конфигурация обновлена (регистрация, автосвязка).
- [x] Unit-тесты на новый компонент (покрытие ≥80% нового кода), существующие тесты раннеров адаптированы.
- [x] Дизайн-решение зафиксировано Архитектором до реализации (RACI `refactor`: DS обязательный этап).

### 🟡 Желательно (Should Have)

- [x] Размер обоих раннер-классов заметно сокращается (цель: <400 строк каждый).
- [x] Дублируемые фрагменты идентифицированы и устранены поимённо (список в Цели — чеклист для ревью).

### 🟢 Опционально (Could Have)

- [x] Документация компонента в PHPDoc с обоснованием design-решения (как в `ProcessLivenessWatcher`).

### ⚫ Не будем делать (Won't Have)

- [ ] Изменение поведения, форматов вывода, сообщений об ошибках.
- [ ] Дедупликация `buildCommand()`/парсеров.
- [ ] Косметический рефакторинг смежного кода вне списка дубликатов.

## 4. План реализации (Implementation Plan)

1. [x] Создать внутренний Infrastructure-контракт и реализацию:
   - `src/Module/AgentRunner/Infrastructure/Service/Lifecycle/RunAgentProcessLifecycleServiceInterface.php`;
   - `src/Module/AgentRunner/Infrastructure/Service/Lifecycle/RunAgentProcessLifecycleService.php`.

2. [x] Зафиксировать публичный контракт `RunAgentProcessLifecycleServiceInterface`:
   ```php
   /**
    * @param callable(AgentRunRequestVo): list<string> $buildCommand
    * @param callable(): void $resetParser
    * @param callable(string): void $feedParserLine
    * @param callable(Process, string): AgentResultVo $buildResult
    */
   public function run(
       AgentRunRequestVo $request,
       string $runnerName,
       callable $buildCommand,
       callable $resetParser,
       callable $feedParserLine,
       callable $buildResult,
   ): AgentResultVo;

   /** @param array<string, string> $currentEnv @return array<string, string> */
   public function buildProcessEnv(array $currentEnv): array;

   public function createBridgeIfNeeded(): ?HttpsProxyBridge;

   public function buildUserPrompt(AgentRunRequestVo $request): string;

   public function resolveSystemPromptPath(AgentRunRequestVo $request): ?string;
   ```
   `Process` — `Symfony\Component\Process\Process`, `HttpsProxyBridge` остаётся существующим
   `TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\HttpsProxyBridge`
   (не перемещать в этой задаче, чтобы не расширять рефакторинг).

3. [x] Реализовать в `RunAgentProcessLifecycleService` единственную копию lifecycle-логики:
   - private const `ERROR_OUTPUT_TAIL_BYTES = 65536`;
   - private `createConfiguredProcess(AgentRunRequestVo $request, int $hardCap, callable $buildCommand): Process`;
   - private `attachProxyEnvironment(Process $process): ?HttpsProxyBridge`;
   - private `stopProcessAndBridge(Process $process, ?HttpsProxyBridge $bridge): void`;
   - private `bufferStdoutChunk(string $chunk, string &$stdoutBuffer, callable $feedParserLine): void`;
   - private `flushStdoutBuffer(string &$stdoutBuffer, callable $feedParserLine): void`;
   - private `appendErrorOutputTail(string $currentOutput, string $chunk): string`.
   Поведение перенести byte-for-byte, кроме параметризации `runnerName` в signal-сообщении:
   `sprintf('%s process terminated by signal %d.', $runnerName, $signal)`.

4. [x] В `CodexAgentRunnerService` заменить lifecycle-декомпозицию делегированием:
   - constructor: заменить `ProcessLivenessWatcher $livenessWatcher` на
     `RunAgentProcessLifecycleServiceInterface $processLifecycle`;
   - `run(AgentRunRequestVo $request)` сделать thin wrapper вокруг `$this->processLifecycle->run(...)`;
   - передать callbacks: `buildCommand`, `parser->reset`, `parser->feed`, private `buildResult(Process $process, string $errorOutput)`;
   - `buildCommand()` оставить Codex-specific, но заменить общие вызовы на
     `$this->processLifecycle->buildUserPrompt($request)` и
     `$this->processLifecycle->resolveSystemPromptPath($request)`;
   - удалить из класса дублируемые методы/константу: `createConfiguredProcess`, `attachProxyEnvironment`,
     `stopProcessAndBridge`, `buildProcessEnv`, `createBridgeIfNeeded`, `buildUserPrompt`,
     `bufferStdoutChunk`, `flushStdoutBuffer`, `appendErrorOutputTail`, `resolveSystemPromptPath`,
     `ERROR_OUTPUT_TAIL_BYTES`;
   - оставить Codex-specific методы: `buildCommand`, `buildResult`, `resolvePromptSlots`,
     `escapeTomlString`, `extractAppendFromRunnerArgs`, `getFilteredRunnerArgs`, `readFileOrValue`.

5. [x] В `PiAgentRunnerService` выполнить симметричную замену:
   - constructor: заменить `ProcessLivenessWatcher $livenessWatcher` на
     `RunAgentProcessLifecycleServiceInterface $processLifecycle`;
   - `run()` делегирует в `$this->processLifecycle->run(...)` с `runnerName: 'pi'`;
   - `buildCommand()` использует `$this->processLifecycle->buildUserPrompt($request)` и
     `$this->processLifecycle->resolveSystemPromptPath($request)`;
   - удалить тот же набор lifecycle-методов/константу, что и в Codex;
   - оставить Pi-specific методы: `buildCommand`, `buildResult` с проверкой `isError`/`errorMessage`,
     `resolvePromptMarkers`, `extractAppendPromptPath`, `resolveCommandFiles`.

6. [x] Не вводить общий интерфейс для `CodexJsonlParser`/`PiJsonlParser` на этом шаге.
   Эквивалентный контракт lifecycle-сервиса — callbacks `resetParser`/`feedParserLine` +
   `buildResult` hook. Это сохраняет текущий `result(): array` shape каждого parser-а и не
   смешивает Pi error-семантику с Codex.

7. [x] Обновить DI в `src/Module/AgentRunner/Resource/config/services.yaml`:
   ```yaml
   TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Lifecycle\RunAgentProcessLifecycleServiceInterface:
     alias: TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Lifecycle\RunAgentProcessLifecycleService
   ```
   Явного definition для implementation не нужно: `ModuleServiceRegistrar` auto-discovers instantiable
   классы модуля; alias нужен для autowire интерфейса в раннеры.

8. [x] Обновить тесты:
   - добавить `tests/Unit/Infrastructure/Service/AgentRunner/Lifecycle/RunAgentProcessLifecycleServiceTest.php`
     с покрытием `buildProcessEnv`, `createBridgeIfNeeded`, chunked stdout + flush last line, stderr-tail,
     hard-cap timeout, idle timeout и signal-сообщения с `runnerName`;
   - перенести duplicated proxy-тесты из `CodexAgentRunnerTest` и `PiAgentRunnerTest` на новый service;
   - обновить setup direct-construction в runner-тестах: создавать `RunAgentProcessLifecycleService` с
     тестовым `ProcessLivenessWatcher`;
   - оставить в runner-тестах проверки `buildCommand()` и runner-specific результата (`Codex` success/error,
     `Pi` success/error + JSONL `isError` при exit 0);
   - обновить `AgentRunnerLivenessWatcherIntegrationTest`: проверять watcher через
     `processLifecycle` (или assert same lifecycle-service в обоих раннерах), а не private `livenessWatcher`
     внутри раннера;
   - обновить `AgentRunnerProbeErrorCleanupIntegrationTest` и `CodexAgentRunnerLivenessIntegrationTest`
     под новый constructor.

9. [x] Проверить, что после рефакторинга `rg "buildProcessEnv|createBridgeIfNeeded|ERROR_OUTPUT_TAIL_BYTES|bufferStdoutChunk|flushStdoutBuffer|appendErrorOutputTail" src/Module/AgentRunner/Infrastructure/Service/{Codex,Pi}`
   не находит дубликатов в раннерах; разрешены только runner-specific prompt/command/result методы.

10. [x] Запустить проверки из раздела Verification: `make check` и `php vendor/bin/todo-md validate todo/TASK-techdebt-agent-runner-lifecycle-helper.todo.md`.

## 5. Критерии приёмки (Definition of Done)

- [x] Дублируемые методы жизненного цикла удалены из обоих раннеров, существуют в одном экземпляре.
- [x] Поведение обоих раннеров не изменилось (никаких правок контрактов/сообщений/env).
- [x] Новые Unit-тесты на общий компонент; существующие тесты адаптированы и зелёные.
- [x] `make check` зелёный (PHPUnit, Psalm, PHPStan, Deptrac, phpmd, phpcs).
- [x] Нет регрессий в смежных модулях (ChainExecution, DynamicLoop используют раннеры).

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

- [x] [Ретроспектива 2026-08-20 (предложение техдолга, замечание №1)](../../docs/agents/team-retro/2026-08-20_00-36-techdebt-phpmd-lengths.md)
- [x] [PR #356 — источник замечания ревью](https://github.com/prikotov/task-orchestrator/pull/356)
- [x] [Прецедент: TASK-techdebt-extract-process-liveness-service](TASK-techdebt-extract-process-liveness-service.todo.md)
- [x] [Конвенция Helper](../../docs/conventions/core-patterns/helper.md)
- [x] [Конвенция Service](../../docs/conventions/core-patterns/service.md)
- [ ] [Матрица RACI — refactor](../../docs/agents/raci-matrix.md)

## 9. Комментарии (Comments)

- Источник: ретроспектива задачи `TASK-techdebt-phpmd-lengths` (PR #356), предложение 🟢 «Завести техдолг TASK-techdebt-agent-runner-lifecycle-helper», подтверждено владельцем 2026-08-20.
- По завершении: обновить реестр ретро `docs/agents/team-retro/RETRO-ROADMAP.md` (отметить предложение выполненным).

## История изменений (Change History)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-20 | Тимлид Алекс (pi) | Создание задачи (по подтверждению владельца, из ретро PR #356) |
| 2026-08-20 | Архитектор Гэндальф | Зафиксировано DS: `RunAgentProcessLifecycleService`, callback-контракт parser lifecycle, перенос public proxy seam-методов в общий Infrastructure-сервис. |
| 2026-08-20 | Бэкендер Левша (pi) | Реализация по плану: `Lifecycle/RunAgentProcessLifecycleService` + Interface (291+76 строк) — единственная копия lifecycle-логики; делегирование обоих раннеров через callbacks (538→281 и 532→273 строк); DI-alias интерфейса; 29 unit-тестов lifecycle-сервиса (покрытие 100% строк/методов) с переносом proxy-тестов с раннеров; runner- и integration-тесты адаптированы под новый constructor. rg-проверка п.9 без дубликатов; `make check` зелёный (1503 теста, 4025 assertions). |
| 2026-08-20 | Бэкендер Левша (pi) | Доработка по ревью Пуаро (одобрено с замечаниями: 3 minor + 1 nit): Minor-1 — убран `static` у `appendErrorOutputTail()` и вызов через `$this->` (конвенция Service: без статических методов); Minor-2 — артефакт `implode("\n", [])` в дефолтном buildResult-hook заменён на `''` с комментарием; Minor-3 — добавлены wiring-тесты `runSignalsRunnerNameInTerminatedBySignalMessage` в Codex/Pi runner-тесты (ThrowingProbeStub + SIGTERM-процесс → префикс `codex`/`pi` в signal-сообщении); Nit-4 — дубликат `runAppliesHttpProxyEnvironmentWithoutBridge` удалён из PiAgentRunnerTest (копия осталась в Codex + lifecycle-тест). `make check` зелёный (1504 теста, 4030 assertions). |
| 2026-08-20 | Бэкендер Левша (pi) | Фикс gitleaks: фикстурные URL с кредитеншалами выровнены на allowlist-паттерн `user:pass@example.com` (4 строки lifecycle-теста). |
| 2026-08-20 | Тимлид Алекс (pi) | Повторное ревью Пуаро — финальный апрув; независимый `make check` зелёный; PR #357; статус `done`, перенос в `todo/done/`. |
