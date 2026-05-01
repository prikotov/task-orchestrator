---
type: feat
created: 2026-05-01
value: V3
complexity: C3
priority: P1
depends_on: TASK-refactor-static-audit-isolation
epic: EPIC-sprint-8-conditional-branching
author: system_analyst_sherlock (Шерлок)
assignee: Бэкендер Левша
branch: task/feat-conditional-yaml-dsl
pr:
status: in_progress
---

# TASK-feat-conditional-yaml-dsl: YAML DSL `when:` expressions

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда цепочка должна ветвиться по результатам предыдущих шагов (тесты прошли → деплой, тесты упали → откат), я хочу добавить `when:` expressions в YAML-chain конфигурацию, чтобы пользователь мог declaratively задать условия выполнения каждого шага без изменения кода.

### Goal (Цель по SMART)
Расширить YAML-chain DSL для условного ветвления: `when:` expression syntax на уровне шага. Обновить [`ChainDefinitionVo`](../../src/Module/Orchestrator/Domain/ValueObject/ChainDefinitionVo.php), [`ChainStepVo`](../../src/Module/Orchestrator/Domain/ValueObject/ChainStepVo.php) и [`YamlChainLoader`](../../src/Module/Orchestrator/Infrastructure/Service/Chain/YamlChainLoader.php). Цепочки без `when:` работают без изменений. Срок: Sprint 8 (вторая задача).

## 2. Context and Scope (Контекст и Границы)
### Где делаем
- `config/chains.yaml` — формат YAML-конфигурации
- `src/Module/Orchestrator/Infrastructure/Service/Chain/YamlChainLoader.php` — парсер YAML → [`ChainDefinitionVo`](../../src/Module/Orchestrator/Domain/ValueObject/ChainDefinitionVo.php)
- `src/Module/Orchestrator/Domain/ValueObject/ChainStepVo.php` — определение шага
- `src/Module/Orchestrator/Domain/ValueObject/ChainDefinitionVo.php` — определение цепочки
- `src/Module/Orchestrator/Domain/Enum/ChainTypeEnum.php` — типы цепочек (`static`, `dynamic`)

### Текущее поведение
- YAML поддерживает `type: static` (линейные шаги) и `type: dynamic` (фасилитатор + участники)
- `ChainStepVo` содержит `type`, `role`, `runner`, `tools`, `model`, `retryPolicy`, `name`, `command`, `label`, `timeoutSeconds`, `noContextFiles` — нет поддержки conditions
- Все шаги static-цепочки выполняются последовательно, без ветвления

### Границы (Out of Scope)
- Не реализуем ConditionalExecutionStrategy (это следующая задача)
- Не добавляем nested conditions
- Не добавляем semantic conditions (LLM-based)
- Не меняем Dynamic chain type

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Определить и зафиксировать `when:` expression syntax:
  ```yaml
  steps:
    - type: agent
      role: backend_developer_levsha
      name: deploy
      when: 'steps.tests.passed == true'
  ```
- [ ] Создать [`Value Object`](../../docs/conventions/core_patterns/value-object.md) `ConditionExpressionVo` в Orchestrator Domain для представления conditions:
  - Поддержка простых сравнений: `==`, `!=`
  - Поддержка path references: `steps.<name>.passed`, `steps.<name>.exitCode`, `result.status`
  - Immutable, validated в конструкторе
- [ ] Расширить [`ChainStepVo`](../../src/Module/Orchestrator/Domain/ValueObject/ChainStepVo.php): добавить `?ConditionExpressionVo $when` (опциональное поле)
- [ ] Расширить [`ChainTypeEnum`](../../src/Module/Orchestrator/Domain/Enum/ChainTypeEnum.php): добавить `conditionalType = 'conditional'` (или обосновать, что conditional — это subtype static)
- [ ] Обновить [`YamlChainLoader`](../../src/Module/Orchestrator/Infrastructure/Service/Chain/YamlChainLoader.php): парсинг `when:` поля в шагах
- [ ] Обновить [`ChainDefinitionVo`](../../src/Module/Orchestrator/Domain/ValueObject/ChainDefinitionVo.php): factory-метод для conditional chains (если новый тип)
- [ ] Обратная совместимость: цепочки без `when:` парсятся и работают как раньше

### 🟡 Should Have (Желательно)
- [ ] ADR-009: Conditional Branching DSL — зафиксировать syntax, semantics, расширяемость
- [ ] Unit-тесты на `ConditionExpressionVo` (parsing, validation, evaluation)
- [ ] Unit-тесты на `YamlChainLoader` с `when:` expressions
- [ ] Validation: при парсинге YAML — проверять, что `when:` references (`steps.<name>.passed`) ссылаются на существующие именованные шаги

### 🟢 Could Have (Опционально)
- [ ] `else:` fallback branch: шаг без `when:` в conditional chain = default branch
- [ ] Comparison operators: `>`, `<`, `>=`, `<=` для numeric comparisons
- [ ] String operators: `contains`, `startsWith`, `endsWith`

### ⚫ Won't Have (Не будем делать)
- [ ] Nested conditions
- [ ] Logical operators (`and`, `or`, `not`) — MVP = single condition
- [ ] Semantic / LLM-based conditions
- [ ] Execution logic (это TASK-feat-conditional-execution-strategy)

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем (агентом) перед стартом.*
1. [ ] ...

## 5. Definition of Done (Критерии приёмки)
- [ ] `ConditionExpressionVo` создан в `Orchestrator\Domain\ValueObject\`
- [ ] [`ChainStepVo`](../../src/Module/Orchestrator/Domain/ValueObject/ChainStepVo.php) расширен полем `?ConditionExpressionVo $when`
- [ ] [`YamlChainLoader`](../../src/Module/Orchestrator/Infrastructure/Service/Chain/YamlChainLoader.php) парсит `when:` expressions
- [ ] Обратная совместимость: существующие YAML-конфигурации парсятся корректно
- [ ] Unit-тесты на parsing и validation
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
```

## 7. Risks and Dependencies (Риски и зависимости)
- **R-2 (из Roadmap):** Выбор синтаксиса `when:` DSL может вызвать обсуждения. Митигация: ADR-009 до реализации.
- **Зависимость:** TASK-refactor-static-audit-isolation (audit isolation) — должна быть завершена, чтобы новый код не наследовал audit-зависимости.

## 8. Sources (Источники)
- [ ] [Roadmap: Sprint 8 — Conditional Branching](../../docs/releases/ROADMAP-2026-Q2-Q3.md)
- [ ] [ADR-006: ExecutionStrategy composition](../../docs/adr/006-execution-strategy-composition.md)
- [ ] [Конвенция: Value Object](../../docs/conventions/core_patterns/value-object.md)
- [ ] [YamlChainLoader](../../src/Module/Orchestrator/Infrastructure/Service/Chain/YamlChainLoader.php) — текущий парсер
- [ ] [ChainStepVo](../../src/Module/Orchestrator/Domain/ValueObject/ChainStepVo.php) — текущее определение шага

## 9. Comments (Комментарии)
- DSL syntax proposal основан на исследовании 4+ фреймворков: Archon (`when:`), Mastra AI (`.branch()`), LangGraph (conditional edges), Agno (Condition + Router). Выбран `when:` как наиболее declarative и YAML-friendly.
- `when:` expression — это data (Value Object), не logic. Evaluation logic будет в ConditionalExecutionStrategy (следующая задача).
- Решение: `conditional` — отдельный `ChainTypeEnum` или subtype `static`? Если conditional chain = static chain с optional `when:` на шагах, то отдельный тип не нужен. Но для `supports()` в ConditionalExecutionStrategy нужен критерий различения. Рекомендация: добавить `conditionalType` в enum.

## Инструкции для сабагента

**Ветка:** task/feat-conditional-yaml-dsl (уже создана и активна)
**PR:** уже создан (draft) из task/feat-conditional-yaml-dsl в task/epic-sprint-8-conditional-branching — [PR #<PR_NUMBER>](<PR_LINK>)

### Порядок действий
1. Переключись в ветку `task/feat-conditional-yaml-dsl`: `git checkout task/feat-conditional-yaml-dsl`
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
