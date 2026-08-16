---
type: feat
created: 2026-05-02
value: V3
complexity: C2
priority: P1
depends_on: TASK-refactor-chain-definition-split
epic: EPIC-sprint-10-hooks-debt-cleanup
author: system_analyst_sherlock (Шерлок)
assignee: backend_developer_levsha
branch:
pr:
status: done
---

# TASK-feat-hooks-post-step: Hooks system: post_step MVP

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда нужно добавить custom-логику после выполнения шага цепочки (webhook-уведомление, запись метрики, нотификация в Slack) — единственный способ: писать новый сервис, тащить его в DI, добавлять dependency injection. Я хочу declarative `post_step` hooks через YAML DSL, чтобы можно было добавить custom-логику без изменения Domain-кода — просто указав shell-скрипт в конфигурации шага.

### Goal (Цель по SMART)
Реализовать `post_step` hooks MVP: shell-скрипты выполняются после каждого шага через [`Symfony\Process`](https://symfony.com/doc/current/components/process.html) с таймаутом 30с. Hook failure = warning (не failure цепочки). Hook stdout/stderr — в audit log. YAML DSL: `post_step: "scripts/notify.sh"` (опционально). Срок: 1.5 дня.

## 2. Context and Scope (Контекст и Границы)
### Где делаем
- [`src/Module/Orchestrator/Domain/`](../../../src/Module/Orchestrator/Domain/) — hook interface (`HookExecutorInterface`), hook result VO (`HookResultVo`)
- [`src/Module/Orchestrator/Domain/ValueObject/ChainStepVo.php`](../../../src/Module/Orchestrator/Domain/ValueObject/ChainStepVo.php) — добавить `?string $postStep` (hook config)
- [`src/Module/Orchestrator/Application/Service/Chain/ExecutionStrategyInterface.php`](../../../src/Module/Orchestrator/Application/Service/Chain/ExecutionStrategyInterface.php) — strategy execution вызывает hook executor
- [`src/Module/Orchestrator/Domain/Service/Chain/Audit/AuditLoggerInterface.php`](../../../src/Module/Orchestrator/Domain/Service/Chain/Audit/AuditLoggerInterface.php) — audit logging hook stdout/stderr
- [`src/Module/Orchestrator/Infrastructure/`](../../../src/Module/Orchestrator/Infrastructure/) — `ShellHookExecutor` (через Symfony Process)
- [`config/chains.yaml`](../../config/chains.yaml) — YAML DSL для `post_step`

### Текущее поведение
- После выполнения шага цепочки — нет расширяемой точки для custom-логики
- Чтобы добавить «после шага X отправить webhook» — нужен новый сервис + DI wiring
- MetricsCollector (Sprint 9) — in-memory, но нет declarative способа записать метрику после шага

### Границы (Out of Scope)
- `pre_step` hooks — control flow (это conditional branching через `when:`, Sprint 8)
- Hook chaining (массив hooks на один шаг) — Could Have, не Must
- Persistent hook execution history
- Hook sandboxing / security validation (контроль доступа — через OS sandbox)
- Параллельное выполнение hooks

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `HookExecutorInterface` в Domain: `execute(scriptPath: string, context: HookContextVo): HookResultVo`
- [ ] `ShellHookExecutor` в Infrastructure: выполняет shell-скрипт через `Symfony\Process` с таймаутом 30с
- [ ] `HookResultVo` в Domain: `success()`, `warning()`, `skipped()` — immutable VO
- [ ] `?string $postStep` в [`ChainStepVo`](../../../src/Module/Orchestrator/Domain/ValueObject/ChainStepVo.php) — путь к hook-скрипту (nullable = hook не сконфигурирован)
- [ ] Hook failure (exit code !== 0 или timeout) = warning в audit log, цепочка продолжает выполнение
- [ ] Hook stdout/stderr — в audit log через [`AuditLoggerInterface`](../../../src/Module/Orchestrator/Domain/Service/Chain/Audit/AuditLoggerInterface.php)
- [ ] YAML DSL: `post_step: "scripts/notify.sh"` на уровне шага (опционально)
- [ ] Unit-тесты: hook success, hook failure → warning, hook timeout → warning, no hook → skipped
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные

### 🟡 Should Have (Желательно)
- [ ] Hook context: step name, chain name, step result summary (передаются как env vars в скрипт)
- [ ] Integration-тест: цепочка с `post_step` hook → hook вызывается, цепочка завершается успешно
- [ ] Deptrac green

### 🟢 Could Have (Опционально)
- [ ] Массив hooks: `post_step: ["scripts/notify.sh", "scripts/metrics.sh"]` — последовательное выполнение

### ⚫ Won't Have (Не будем делать)
- [ ] `pre_step` hooks (control flow — через `when:` expressions, Sprint 8)
- [ ] Hook retry (hook упал = warning, без повторной попытки)
- [ ] Hook sandboxing / security policy
- [ ] Параллельное выполнение hooks
- [ ] Webhook/http hooks (только shell-скрипты в MVP)

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем (агентом) перед стартом.*

1. [ ] Создать `HookResultVo` в Domain (`success()`, `warning(reason, stdout, stderr)`, `skipped()`) — immutable [`Value Object`](../../docs/conventions/core-patterns/value-object.md)
2. [ ] Создать `HookContextVo` в Domain — context для hook execution (step name, chain name, step result summary)
3. [ ] Создать `HookExecutorInterface` в Domain — `execute(string $scriptPath, HookContextVo $context): HookResultVo`
4. [ ] Добавить `?string $postStep` в [`ChainStepVo`](../../../src/Module/Orchestrator/Domain/ValueObject/ChainStepVo.php) — через конструктор (nullable, default null)
5. [ ] Обновить `YamlChainLoader` — парсинг `post_step` поля из YAML в `ChainStepVo`
6. [ ] Создать `ShellHookExecutor` в Infrastructure — реализация через `Symfony\Process`:
   - `new Process(['sh', $scriptPath])` с env vars из HookContextVo
   - `setTimeout(30)` — 30 секунд
   - `run()` + обработка результата
   - Exception / timeout → `HookResultVo::warning()`
7. [ ] Интегрировать hook executor в стратегии выполнения (Static/Dynamic/Conditional) — вызов `hookExecutor->execute()` после успешного шага
8. [ ] Audit logging: hook stdout/stderr → [`AuditLoggerInterface`](../../../src/Module/Orchestrator/Domain/Service/Chain/Audit/AuditLoggerInterface.php)
9. [ ] Unit-тесты: HookResultVo, ShellHookExecutor (mock Process), ChainStepVo с postStep
10. [ ] Integration-тест: цепочка с `post_step` → hook вызывается
11. [ ] Psalm + phpunit — зелёные

### Структура файлов
```
src/Module/Orchestrator/Domain/
  Service/Chain/Hook/
    HookExecutorInterface.php          — новый
    HookResultVo.php                    — новый
    HookContextVo.php                    — новый
  ValueObject/
    ChainStepVo.php                     — изменён (+ ?string $postStep)
src/Module/Orchestrator/Infrastructure/
  Service/Chain/Hook/
    ShellHookExecutor.php               — новый
src/Module/Orchestrator/Application/
  Service/Chain/
    StaticExecutionStrategy.php         — изменён (hook call)
    DynamicExecutionStrategy.php        — изменён (hook call)
    ConditionalExecutionStrategy.php    — изменён (hook call)
config/chains.yaml                      — пример post_step
tests/Unit/Module/Orchestrator/
  Domain/Service/Chain/Hook/
    HookResultVoTest.php                — новый
    HookContextVoTest.php                — новый
  Infrastructure/Service/Chain/Hook/
    ShellHookExecutorTest.php           — новый
tests/Integration/
  PostStepHookIntegrationTest.php       — новый
```

## 5. Definition of Done (Критерии приёмки)
- [ ] `post_step` hook выполняется после каждого шага (если сконфигурирован в YAML)
- [ ] Hook failure (exit code !== 0) = warning в audit log, цепочка продолжает выполнение
- [ ] Hook timeout (30с) = warning в audit log, цепочка продолжает выполнение
- [ ] Hook stdout/stderr — в audit log
- [ ] YAML DSL: `post_step: "scripts/notify.sh"` (опционально, nullable)
- [ ] Unit-тесты ≥80% покрытия нового кода
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные
- [ ] Deptrac green

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Module/Orchestrator/
vendor/bin/phpunit tests/Integration/PostStepHookIntegrationTest.php
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Зависимость:** `TASK-refactor-chain-definition-split` — ChainStepVo будет обновлён при split ChainDefinitionVo. Hooks task должен использовать обновлённый ChainStepVo.
- **Shell-скрипты = attack surface.** Митигация: `post_step` — observability/notification, не control flow. OS sandbox при необходимости.
- **Symfony Process dependency:** `symfony/process` — проверить наличие в `composer.json`. Если нет — добавить.
- **Cross-strategy integration:** Hook executor вызывается из всех 3 стратегий. Дублирование кода? Митигация: trait или abstract base, если паттерн повторяется.
- **AuditLoggerInterface:** проверить наличие метода для arbitrary log messages (warning/info с контекстом). Если нет — расширить или использовать существующий.

## 8. Sources (Источники)
- [ ] [Roadmap: Sprint 10](../../docs/releases/ROADMAP-2026-Q2-Q3.md)
- [ ] [Анализ Локи: Hooks System](../../docs/research/analytical/loki-roadmap-review-2026-05.md) — Sprint 10, Задача 1
- [ ] [Symfony Process Component](https://symfony.com/doc/current/components/process.html)
- [ ] [Конвенции: Value Object](../../docs/conventions/core-patterns/value-object.md)
- [ ] [Конвенции: External Service](../../docs/conventions/core-patterns/external-service.md)

## 9. Comments (Комментарии)
- Pain level: 6/10 — единственный способ declarative расширения без изменения Domain. Подтверждён 3+ фреймворками (Claude Code 20+ events, OpenHands SDK 6 lifecycle events, Codex hooks).
- MVP scope: только `post_step` (observability/notification). `pre_step` — conditional branching через `when:`, отдельная задача.
- Альтернатива Decorator pattern: вместо `LoggingAgentRunner`, `TimingAgentRunner`, `WebhookAgentRunner` — один hook pipeline. Это declarative extension point.
- MetricsCollector (Sprint 9) может быть consumer hook-событий в будущем, но в MVP — не обязательно.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-02 | system_analyst_sherlock (Шерлок) | Создание задачи |
