---
type: refactor
created: 2026-05-04
value: V2
complexity: C2
priority: P1
depends_on: TASK-refactor-extract-dynamic-loop, TASK-refactor-merge-static-execution
epic: EPIC-refactor-responsibility-decomposition
author: Аналитик (Шерлок)
assignee: Бэкендер (Левша)
branch:
pr: https://github.com/prikotov/task-orchestrator/pull/153
status: cancelled
---

# TASK-refactor-deptrac-decomposition-rules: Deptrac-правила и обновление документации

## Причина отмены

Задача основана на ошибочной предпосылке: «создать `depfile.yaml` с кастомными модуль-специфичными Deptrac-правилами». Однако:

1. **`depfile.yaml` уже существует** — импортирует правила из `prikotov/coding-standard`
2. Базовые правила содержат `CrossModuleDomainRule` и `ServiceContractDependencyRule` — кастомные Deptrac-subscriber'ы, которые строже и корректнее предложенных в задаче
3. Переписывать или дублировать правила **не нужно**

Вместо одной задачи созданы три целевых:

- `TASK-refactor-crossmodule-deptrac-rule` — 2 точечных исключения в `CrossModuleDomainRule` (PR в coding-standard)
- `TASK-refactor-integration-layer-violations` — рефакторинг 10 violations (перенос классов в Integration)
- `TASK-docs-shared-kernel-contracts` — документирование статуса `ChainExecution.Domain.Contract`

Решение принято по результатам консультации Архитектора Гэндальфа и Архитектора Локи (2026-05-06).

## Исходное содержимое задачи (архив)

### Concept and Goal

Когда модули ChainDefinition, ChainExecution, DynamicLoop и AgentRunner имеют изолированные Domain-слои, я хочу формализовать правила зависимостей в Deptrac и обновить документацию, чтобы архитектурные нарушения автоматически выявлялись при CI-проверке.

### Goal по SMART
1. Создать `depfile.yaml` с правилами зависимостей для 4 модулей.
2. Deptrac analyse → 0 violations.
3. Обновить ADR, architecture.md.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-04 | Аналитик (Шерлок) | Создание задачи |
| 2026-05-06 | Тимлид Алекс | Отмена — предпосылка ошибочна, декомпозирована на 3 целевых задачи |
