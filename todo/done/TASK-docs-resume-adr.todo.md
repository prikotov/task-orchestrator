---
type: docs
created: 2026-05-02
value: V2
complexity: C1
priority: P2
depends_on:
epic: EPIC-sprint-10-hooks-debt-cleanup
author: system_analyst_sherlock (Шерлок)
assignee: system_architect_gandalf
branch:
pr:
status: todo
---

# TASK-docs-resume-adr: ADR: Resume для static цепочек

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда static или conditional цепочка из 10 шагов падает на 8-м — всё начинается с начала. Для дорогих LLM-вызовов ($0.50–2.00 за шаг) это реальная потеря денег ($3.50–14.00 потеряно). Dynamic стратегия уже поддерживает resume через [`ChainSessionLogger`](../../src/Module/Orchestrator/Domain/Service/Chain/Audit/AuditLoggerInterface.php) (checkpoint в JSONL). Я хочу зафиксировать архитектурное решение: паттерн checkpoint + resume для static/conditional стратегий, чтобы команда имела план на Q4 и понимала, как resume интегрируется с ExecutionStrategy pattern.

### Goal (Цель по SMART)
Создать ADR (Architecture Decision Record) в `docs/adr/`, фиксирующий паттерн checkpoint + resume для static/conditional цепочек: формат checkpoint, точка сохранения, resume flow, интеграция с `ExecutionStrategyInterface::resume()`. Реализация — Q4. Только документация, без кода. Срок: 0.5 дня.

## 2. Context and Scope (Контекст и Границы)
### Где делаем
- `docs/adr/` — новый ADR (номер определить по существующим)
- Ссылки на: [`ExecutionStrategyInterface`](../../src/Module/Orchestrator/Application/Service/Chain/ExecutionStrategyInterface.php) (метод `resume()` уже существует), [`AuditLoggerInterface`](../../src/Module/Orchestrator/Domain/Service/Chain/Audit/AuditLoggerInterface.php) (JSONL audit)

### Текущее поведение
- `StaticExecutionStrategy::resume()` → `LogicException('Static chain does not support resume.')`
- `ConditionalExecutionStrategy::resume()` → `LogicException('Conditional chain does not support resume.')`
- `DynamicExecutionStrategy::resume()` — реализован через `ChainSessionLogger` (JSONL checkpoint)
- Для static цепочек: падение на 8-м из 10 шагов = всё сначала, потеря ~$3.50–14.00

### Границы (Out of Scope)
- Реализация resume (только ADR, код — Q4)
- Изменение существующих стратегий
- Parallel execution
- Sub-agents

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] ADR создан в `docs/adr/NNN-resume-static-conditional-chains.md` (номер по порядку)
- [ ] Контекст: почему resume нужен для static/conditional, но не реализован
- [ ] Решение: паттерн checkpoint + resume — формат, точка сохранения, resume flow
- [ ] Альтернативы: рассмотрены и обоснованно отвергнуты
- [ ] Критерий реализации: когда реализуем (Q4), какие условия
- [ ] Ссылки на `ExecutionStrategyInterface::resume()`, JSONL audit format

### 🟡 Should Have (Желательно)
- [ ] Mermaid-диаграмма: resume flow для static цепочки
- [ ] Оценка LOC и сложности реализации для Q4 планирования

### 🟢 Could Have (Опционально)
- [ ] Пример YAML-конфигурации для resume-совместимой цепочки

### ⚫ Won't Have (Не будем делать)
- [ ] Код реализации (только ADR)
- [ ] Изменение ExecutionStrategyInterface
- [ ] Тесты (документация)

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем (агентом) перед стартом.*

1. [ ] Изучить существующую реализацию `DynamicExecutionStrategy::resume()` — как устроен checkpoint через JSONL
2. [ ] Изучить `StaticExecutionStrategy` — точки для checkpoint (после каждого шага)
3. [ ] Изучить `ConditionalExecutionStrategy` — точки для checkpoint (после каждого evaluated branch)
4. [ ] Определить номер ADR (следующий после последнего в `docs/adr/`)
5. [ ] Написать ADR по шаблону проекта: Context, Decision, Alternatives, Consequences
6. [ ] Добавить Mermaid-диаграмму resume flow
7. [ ] Ссылки на `ExecutionStrategyInterface::resume()`, JSONL audit, MetricsCollector

## 5. Definition of Done (Критерии приёмки)
- [ ] ADR создан в `docs/adr/NNN-resume-static-conditional-chains.md`
- [ ] Паттерн checkpoint + resume зафиксирован: формат, точка сохранения, resume flow
- [ ] Альтернативы рассмотрены
- [ ] Критерий реализации для Q4 определён
- [ ] Ссылки на существующий код корректны

## 6. Verification (Самопроверка)
```bash
# Проверить, что ADR создан
ls -la docs/adr/NNN-resume-static-conditional-chains.md
# Проверить корректность ссылок на существующий код
grep -r "resume()" src/Module/Orchestrator/Application/Service/Chain/
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Минимальный риск:** задача — чисто документальная, без изменений кода.
- **Зависимость от знаний:** нужно понимать реализацию `DynamicExecutionStrategy::resume()` как reference implementation.
- **Спекулятивный характер:** ADR фиксирует решение, но реализация отложена до Q4. Контекст может измениться.

## 8. Sources (Источники)
- [ ] [Roadmap: Sprint 10](../../docs/releases/ROADMAP-2026-Q2-Q3.md) — Sprint 10, Задача 3
- [ ] [Анализ Локи: Resume для static цепочек](../../docs/research/loki-roadmap-review-2026-05.md) — Упущенная боль #4
- [ ] [ExecutionStrategyInterface](../../src/Module/Orchestrator/Application/Service/Chain/ExecutionStrategyInterface.php) — метод `resume()`
- [ ] [ADR-006: ExecutionStrategy composition](../../docs/adr/006-execution-strategy-composition.md) — архитектурный контекст
- [ ] [ADR-008: Shared Kernel Contract](../../docs/adr/008-shared-kernel-contract.md) — архитектурный контекст

## 9. Comments (Комментарии)
- Pain level: bookmark на будущее. Реальная боль при expensive LLM-вызовов ($0.50–2.00 за шаг × 7 потерянных = $3.50–14.00 на каждый failed run).
- Аналогия: Dynamic стратегия уже реализует resume через JSONL checkpoint. ADR обобщает этот паттерн на static/conditional.
- Не блокирует кодовые задачи эпика — может выполняться параллельно с TASK-refactor-chain-definition-split и TASK-feat-hooks-post-step.
- Ответственный: Гэндальф — архитектурное решение, не код.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-02 | system_analyst_sherlock (Шерлок) | Создание задачи |
