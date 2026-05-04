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
pr:
status: todo
---

# TASK-refactor-deptrac-decomposition-rules: Deptrac-правила и обновление документации

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда модули ChainDefinition, ChainExecution, DynamicLoop и AgentRunner имеют изолированные Domain-слои, я хочу формализовать правила зависимостей в Deptrac и обновить документацию, чтобы архитектурные нарушения автоматически выявлялись при CI-проверке.

### Goal (Цель по SMART)
1. Создать `depfile.yaml` с правилами зависимостей для 4 модулей: ChainDefinition, ChainExecution, DynamicLoop, AgentRunner.
2. Deptrac analyse → 0 violations.
3. Обновить ADR (создать ADR-011 на декомпозицию), обновить `docs/guide/architecture.md`.
4. Суперседировать ADR-009 (Dynamic остаётся в Orchestrator).

## 2. Context and Scope (Контекст и Границы)

### Где делаем

**Создаётся:** `depfile.yaml` (корень проекта)

**Обновляется:**
- `docs/adr/009-dynamic-split-decision.md` — статус: «Суперседировано»
- `docs/adr/` — новый ADR-011 на декомпозицию по ответственности
- `docs/guide/architecture.md` — обновление структуры модулей и диаграмм

### Текущее поведение
Deptrac не используется (`depfile.yaml` не существует). Архитектурные правила проверяются вручную через code review.

### Deptrac-правила (на основе эпика)

```
ChainDefinition\Domain → nothing (Provider)
ChainExecution\Domain → nothing
DynamicLoop\Domain → nothing
AgentRunner\Domain → nothing

ChainDefinition\Application → ChainDefinition\Domain
ChainDefinition\Infrastructure → ChainDefinition\Domain

ChainExecution\Application → ChainExecution\Domain
ChainExecution\Infrastructure → ChainExecution\Domain
ChainExecution\Integration → ChainDefinition\Domain (контракты), AgentRunner\Application

DynamicLoop\Application → DynamicLoop\Domain
DynamicLoop\Infrastructure → DynamicLoop\Domain
DynamicLoop\Integration → ChainDefinition\Domain (контракты), AgentRunner\Application

Presentation (apps/console) → ChainExecution\Application, ChainDefinition\Application

FORBIDDEN:
ChainExecution\Domain → ChainDefinition\Domain
ChainExecution\Domain → DynamicLoop\Domain
ChainExecution\Domain → AgentRunner\*
DynamicLoop\Domain → ChainDefinition\Domain
DynamicLoop\Domain → ChainExecution\Domain
DynamicLoop\Domain → AgentRunner\*
ChainDefinition\Domain → any external Domain
AgentRunner\Domain → any external Domain
ChainExecution → DynamicLoop (cross-module)
DynamicLoop → ChainExecution (cross-module)
```

### Границы (Out of Scope)
- ❌ Не меняем код (только конфигурация и документация)
- ❌ Не добавляем Deptrac в CI pipeline (отдельная задача)
- ❌ Не создаём скрипт для запуска Deptrac в Makefile (используем `vendor/bin/deptrac`)
- ❌ Не проверяем слои Domain/Application/Infrastructure внутри каждого модуля (только модульные границы и Integration)

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] Файл `depfile.yaml` создан в корне проекта
- [ ] Deptrac определяет 4 модуля: ChainDefinition, ChainExecution, DynamicLoop, AgentRunner
- [ ] `vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress` → 0 violations
- [ ] ADR-009 обновлён: статус «Суперседировано ADR-011»
- [ ] ADR-011 создан: «Декомпозиция Orchestrator по ответственности» — фиксирует 3 модуля вместо Orchestrator
- [ ] `docs/guide/architecture.md` обновлён: 4 модуля, Integration-мапперы, обновлённые диаграммы

### 🟡 Should Have (Желательно)
- [ ] Deptrac включает правила для слоёв Domain/Application/Infrastructure внутри каждого модуля
- [ ] Deptrac-skipped violations задокументированы с `@techdebt` аннотацией

### 🟢 Could Have (Опционально)
- [ ] Deptrac интегрирован в Makefile target `make deptrac`

### ⚫ Won't Have (Не будем делать)
- Не интегрируем Deptrac в CI/CD
- Не проверяем Presentation-слой (apps/console) через Deptrac
- Не создаём кастомные Deptrac rules

## 4. Implementation Plan (План реализации)

1. [ ] Создать ветку `task/refactor-deptrac-decomposition-rules` от `main`
2. [ ] Создать `depfile.yaml` с 4 слоями (модулями) и правилами зависимостей
3. [ ] Запустить `vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress`
4. [ ] Если есть violations — исправить или задокументировать как `@techdebt`
5. [ ] Обновить ADR-009: добавить секцию «Суперседировано» со ссылкой на ADR-011
6. [ ] Создать ADR-011: «Декомпозиция Orchestrator по ответственности»
   - Контекст: Orchestrator 11 573 LOC → 3 модуля
   - Решение: ChainDefinition + ChainExecution + DynamicLoop
   - Integration-мапперы: 2 (ChainDefinition→ChainExecution, ChainDefinition→DynamicLoop) + AuditLoggerInterface split
   - Критерии пересмотра
7. [ ] Обновить `docs/guide/architecture.md`:
   - Заменить «Двухмодульная структура» → «Четырёхмодульная структура»
   - Добавить модули ChainDefinition, ChainExecution, DynamicLoop
   - Обновить Integration-диаграммы
   - Обновить матрицу зависимостей
8. [ ] Обновить docs/releases/ если есть план релиза
9. [ ] Запустить финальную проверку: `vendor/bin/deptrac analyse`

## 5. Definition of Done (Критерии приёмки)

- [ ] `depfile.yaml` существует в корне проекта
- [ ] `vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress` → 0 violations
- [ ] ADR-009: статус «Суперседировано»
- [ ] ADR-011: создан, фиксирует декомпозицию
- [ ] `docs/guide/architecture.md`: описаны 4 модуля (ChainDefinition, ChainExecution, DynamicLoop, AgentRunner)

## 6. Verification (Самопроверка)

```bash
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
vendor/bin/phpunit  # регрессия
vendor/bin/psalm    # регрессия
```

## 7. Risks and Dependencies (Риски и зависимости)

### Риски

| Риск | Вероятность | Влияние | Митигация |
|------|-------------|---------|-----------|
| Deptrac находит violations, которые требуют изменения кода | Средняя | Высокое | Deptrac запускается после завершения TASK-refactor-extract-dynamic-loop и TASK-refactor-merge-static-execution; violations = баги в этих задачах |
| Deptrac-правила для Integration-слоёв сложны в настройке | Низкая | Среднее | Использовать `allow` с конкретными путями для Integration\* → Domain (контракты) |
| Deptrac не установлен как dependency | — | Блокирующее | Проверить `composer.json`; при отсутствии — добавить `qossmic/deptrac` как dev-dependency |

### Зависимости
- **Зависит от:** TASK-refactor-extract-dynamic-loop (завершена), TASK-refactor-merge-static-execution (завершена)
- **Не блокирует:** никаких задач (финальная в цепочке)

## 8. Sources (Источники)

- [Deptrac documentation](https://github.com/qossmic/deptrac)
- [Brainstorm #6 protocol](../var/sessions/brainstorm/2026-05-04_01-59-17/discussion_history.md)
- [ADR-006: ExecutionStrategy](../docs/adr/006-execution-strategy-composition.md)
- [ADR-007: VO ACL Boundary](../docs/adr/007-vo-acl-boundary.md)
- [ADR-008: Shared Kernel Contract](../docs/adr/008-shared-kernel-contract.md)
- [ADR-009: Dynamic Split Decision](../docs/adr/009-dynamic-split-decision.md) — суперседируется
- [Конвенции: layers.md](../docs/conventions/layers/layers.md)

## 9. Comments (Комментарии)

### Почему Deptrac-задача последняя
Deptrac верифицирует архитектурные границы. Нет смысла настраивать его до того, как модули физически существуют и их Domain-слои изолированы. Задача выполняется после завершения TASK-refactor-extract-dynamic-loop и TASK-refactor-merge-static-execution.

### Связь с ADR-009
ADR-009 принял решение «Dynamic остаётся в Orchestrator» с критериями пересмотра (C1–C5). Данный эпик реализует критерий C5 («Командный consensus: 2+ участника считают Dynamic split необходимым») — brainstorm #6 показал консенсус 4 из 4 участников.

### Deptrac и CI
Интеграция Deptrac в CI pipeline — отдельная задача. Данная задача создаёт `depfile.yaml` и верифицирует 0 violations локально. CI-интеграция выполняется при настройке CI/CD.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-04 | Аналитик (Шерлок) | Создание задачи |
