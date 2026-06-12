---
type: research
created: 2026-06-12
value: V3
complexity: C3
priority: P2
depends_on: []
epic:
author: Аналитик (Шерлок)
assignee:
branch:
pr:
status: backlog
---

# TASK-research-deep-research-chain-design: Спроектировать Deep Research Chain для наших research-задач

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда исследователь запускает глубокое исследование (research), я хочу использовать multi-step workflow с итерациями, сбором источников, чтением и синтезом в отчёт, чтобы получать структурированные результаты с доказательной базой.

### Goal (Цель по SMART)
Спроектировать архитектуру Deep Research Chain для task-orchestrator: multi-step runs с iteration loop, source collection, reading, synthesis, final report. Определить интерфейсы, entity, VO, use cases, и интеграцию с нашей DDD/Clean Architecture.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/Orchestrator/` (Application + Domain layers). Возможные новые сущности: `DeepResearchChainStepVo`, `IterationLoopStrategy`, `SourceCollector`, `ReportSynthesizer`.
*   **Текущее поведение:** task-orchestrator имеет статические/dynamic YAML chains с фиксированным набором шагов. Нет multi-step research workflow с адаптивными итерациями.
*   **Границы (Out of Scope):** Не реализовывать vector retrieval для sources — только определение интерфейсов. Не интегрировать с ChromaDB или другими хранилищами — только определить абстракции.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Определить entity/VO для Deep Research Chain: `IterationLoopVo`, `SourceCollectionStep`, `ReadingStep`, `SynthesisStep`, `FinalReportStep`
- [ ] Определить use case для запуска Deep Research: `ExecuteDeepResearchChainCommand/Handler` в Application layer
- [ ] Определить repository интерфейс для persistence результатов research: `DeepResearchRepositoryInterface` (Domain layer)
- [ ] Описать JSON/YAML конфигурацию для Deep Research Chain (новый тип chain: `research`)
- [ ] Создать ADR (Architecture Decision Record) для multi-step iteration loop design
- [ ] Указать ссылки на конвенции: [`Entity`](../../docs/conventions/layers/domain/entity.md), [`VO`](../../docs/conventions/core_patterns/value-object.md), [`Use Case`](../../docs/conventions/layers/application/use_case.md), [`Repository`](../../docs/conventions/layers/domain/repository.md)

### 🟡 Should Have (Желательно)
- [ ] Определить стратегию завершения итераций: max_iterations, convergence criteria (delta < threshold), manual stop signal
- [ ] Определить интерфейс для source collection: web search, file scan, memory lookup (abstraction, не implementation)
- [ ] Определить формат отчёта: markdown, JSON, YAML с sections (Executive Summary, Findings, Sources, Appendix)
- [ ] Рассмотреть интеграцию с `fix_iterations` pattern для adaptive iteration loop

### 🟢 Could Have (Опционально)
- [ ] Рассмотреть интеграцию с AI-эвристиками для определения достаточности источников
- [ ] Рассмотреть интеграцию с parallel execution для independent reading steps
- [ ] Рассмотреть интеграцию с audit trail для каждого iteration step

### ⚫ Won't Have (Не будем делать)
- [ ] Не реализовывать vector retrieval (ChromaDB) — только определить абстракции
- [ ] Не интегрировать с внешними API (Google Search, Jina Reader) — только определить интерфейсы
- [ ] Не реализовывать LLM-based source quality scoring — только определить potential extension point

## 4. Implementation Plan (План реализации)
*План заполняется исполнителем перед стартом.*
1. [ ] Изучить Alibaba DeepResearch workflow (если доступна документация)
2. [ ] Создать ADR для multi-step iteration loop design
3. [ ] Определить entity/VO для Deep Research Chain components
4. [ ] Определить use case handler и repository interfaces
5. [ ] Описать JSON/YAML конфигурацию для `research` chain type
6. [ ] Обновить документацию (архитектура, examples)

## 5. Definition of Done (Критерии приёмки)
- [ ] Создан ADR для multi-step iteration loop design
- [ ] Определены entity/VO для Deep Research Chain (Domain layer)
- [ ] Определены use case handler и repository interfaces (Application/Domain layers)
- [ ] Описан JSON/YAML формат конфигурации для `research` chain type
- [ ] Создан пример конфигурации Deep Research Chain (example chain YAML)
- [ ] Обновлена документация архитектуры (если требуется)

## 6. Verification (Самопроверка)
```bash
ls docs/adr/*.md  # Проверка ADR
ls src/Module/Orchestrator/Domain/Entity/  # Проверка entity/VO
ls src/Module/Orchestrator/Application/Command/Handler/  # Проверка use case
ls docs/examples/chains/research-chain.yaml  # Проверка примера конфигурации
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск:** Multi-step iteration loop может конфликтовать с существующей `fix_iterations` семантикой — требуется ADR для уточнения различий.
- **Риск:** Source collection abstraction может быть too abstract без concrete implementations — нужно определить minimal useful interface.
- **Зависимость:** Task должна быть выполнена после определения общего multi-step loop pattern (если будет отдельная задача).

## 8. Sources (Источники)
- [odysseus-comparison.md](../../docs/research/framework-comparisons/odysseus-comparison.md) — секция "Implementation Candidates for task-orchestrator"
- [Alibaba DeepResearch](https://github.com/Alibaba-NLP/DeepResearch) — reference workflow (если доступна документация)

## 9. Comments (Комментарии)
Цель этой задачи — design spike, не implementation. Результат: архитектурное решение, интерфейсы, и пример конфигурации. Implementation будет отдельной задачей/эпиком.

**AGPL disclaimer:** Концепция взята из Odysseus (adapted from Alibaba DeepResearch), но мы не копируем код. Implementation будет с нуля в нашей DDD/Clean Architecture.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-12 | Аналитик (Шерлок) | Создание задачи |