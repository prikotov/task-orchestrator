---
type: refactor
created: 2026-05-01
value: V3
complexity: C2
priority: P1
depends_on:
epic: EPIC-sprint-8-conditional-branching
author: system_analyst_sherlock (Шерлок)
assignee: Бэкендер Левша
branch: task/refactor-static-audit-isolation
pr:
status: in_progress
---

# TASK-refactor-static-audit-isolation: Audit isolation в StaticExecution

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда `RunStaticChainService` (StaticExecution Domain) напрямую конструирует `ChainResultAuditDto` и `StepAuditStatusDto` из Orchestrator Domain, я хочу вынести Audit-ответственность в собственный интерфейс StaticExecution Domain, чтобы устранить нарушение границ модулей и подготовить архитектуру к добавлению ConditionalExecutionStrategy без дублирования audit-кода.

### Goal (Цель по SMART)
Устранить зависимость StaticExecution Domain от Orchestrator Domain DTO (`ChainResultAuditDto`, `StepAuditStatusDto`, `ChainRunResultVo`, `AuditLoggerInterface`). Создать `StaticAuditServiceInterface` в StaticExecution Domain со своими типами. Маппинг StaticExecution VO → Orchestrator DTO вынести в Integration-слой. Deptrac green. Срок: первая задача Sprint 8.

## 2. Context and Scope (Контекст и Границы)
### Где делаем
- `src/Module/StaticExecution/Domain/Service/RunStaticChainService.php` — основной файл (12 обращений к `$auditLogger?->...`)
- `src/Module/Orchestrator/Domain/Dto/ChainResultAuditDto.php` — Orchestrator DTO
- `src/Module/Orchestrator/Domain/Dto/StepAuditStatusDto.php` — Orchestrator DTO
- `src/Module/Orchestrator/Domain/Service/Chain/Audit/AuditLoggerInterface.php` — Orchestrator interface

### Текущее поведение
`RunStaticChainService::execute()` принимает `?AuditLoggerInterface $auditLogger` — интерфейс из Orchestrator Domain — и вызывает `logChainStart()`, `logStepStart()`, `logStepResult()`, `logChainResult()`. В `buildResult()` конструируется `ChainResultAuditDto` (Orchestrator DTO). В `executeStep()` передаётся `ChainRunResultVo` (Orchestrator VO). Это нарушение границ модулей: StaticExecution Domain → Orchestrator Domain.

### Границы (Out of Scope)
- Не трогаем AuditLoggerInterface (он остаётся в Orchestrator)
- Не меняем логику выполнения шагов (только audit path)
- Conditional Branching — следующая задача

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Создать `StaticAuditServiceInterface` в `src/Module/StaticExecution/Domain/Service/` с методами `logChainStart()`, `logStepStart()`, `logStepResult()`, `logChainResult()` на основе StaticExecution-типов (`StaticStepResultVo`, `StaticChainResultVo`)
- [ ] Создать StaticExecution Domain типы для audit (например, `StaticStepAuditVo`, `StaticChainAuditVo`) — без зависимости от Orchestrator DTO
- [ ] `RunStaticChainService` зависит только от `StaticAuditServiceInterface` (не от `AuditLoggerInterface`)
- [ ] Integration-слой: реализация `StaticAuditServiceInterface`, которая маппит StaticExecution VO → Orchestrator DTO и делегирует в `AuditLoggerInterface`
- [ ] Deptrac green: StaticExecution Domain не зависит от Orchestrator Domain DTO
- [ ] Все существующие тесты проходят без изменений поведения

### 🟡 Should Have (Желательно)
- [ ] Unit-тест на StaticAuditServiceInterface (mock)
- [ ] Integration-тест на маппинг StaticExecution VO → Orchestrator DTO

### ⚫ Won't Have (Не будем делать)
- [ ] Менять AuditLoggerInterface
- [ ] Менять логику выполнения шагов
- [ ] Conditional Branching

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем (агентом) перед стартом.*
1. [ ] ...

## 5. Definition of Done (Критерии приёмки)
- [ ] `RunStaticChainService` не содержит `use` от `Orchestrator\Domain\Dto\*` и `Orchestrator\Domain\Service\Chain\Audit\*`
- [ ] `StaticAuditServiceInterface` определён в StaticExecution Domain
- [ ] Integration-реализация маппит StaticExecution VO → Orchestrator DTO
- [ ] Deptrac green: нет нарушений границ модулей
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные
- [ ] Обратная совместимость: audit-логи содержат те же данные, что и до рефакторинга

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск:** Audit-маппинг может потерять данные при конвертации VO → DTO. Митигация: Integration-тест на полный цикл audit.
- **Зависимость:** Нет внешних зависимостей — первая задача эпика.

## 8. Sources (Источники)
- [ ] [Конвенция: Integration Layer](../../docs/conventions/layers/integration.md)
- [ ] [ADR-008: Shared Kernel Contract](../../docs/adr/008-shared-kernel-contract.md)
- [ ] [RunStaticChainService](../../src/Module/StaticExecution/Domain/Service/RunStaticChainService.php) — текущая реализация с audit-зависимостью
- [ ] [AuditLoggerInterface](../../src/Module/Orchestrator/Domain/Service/Chain/Audit/AuditLoggerInterface.php) — Orchestrator audit interface

## 9. Comments (Комментарии)
Tech debt от архитектора Локи: StaticExecution Domain конструирует Orchestrator DTO (`ChainResultAuditDto`, `StepAuditStatusDto`). Это единственное нарушение границ модулей после Static split (Sprint 7). Должно быть устранено до ConditionalExecutionStrategy, иначе Conditional унаследует ту же проблему.

## Инструкции для сабагента

**Ветка:** task/refactor-static-audit-isolation (уже создана и активна)
**PR:** уже создан (draft) из task/refactor-static-audit-isolation в task/epic-sprint-8-conditional-branching — [PR #120](https://github.com/prikotov/task-orchestrator/pull/120)

### Порядок действий
1. Переключись в ветку `task/refactor-static-audit-isolation`: `git checkout task/refactor-static-audit-isolation`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `vendor/bin/phpunit`, `vendor/bin/psalm`.
6. Сделай `git push`.
7. Переведи PR из draft в ready: `gh pr ready <PR_NUMBER>`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-01 | system_analyst_sherlock (Шерлок) | Создание задачи |
