---
type: refactor
created: 2026-11-27 02:51:32 (1795747892)
due: 
started: 2026-11-27 04:09:54 (1795752594)
completed: 
cancelled: 
value: V2
complexity: C3
priority: P1
cost_plan: 
cost_fact: 
depends_on: TASK-chore-deps-prikotov-bump
epic: 
author: Бэкендер Тони (pi)
assignee: Бэкендер Левша (pi)
consultants: []
reviewer: 
approver: 
informed: []
branch: task/vo-private-properties
pr: 
status: in_progress
---

# TASK-techdebt-vo-private-properties: Перевести свойства VO в private с геттерами (правило coding-standard 0.35.0)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- `prikotov/coding-standard` 0.35.0 ввёл правило `ValueObjectStructureSniff`: свойства Value Object обязаны быть приватными (включая promoted-свойства). В проекте 26 Domain-VO файлов с публичными свойствами — суммарно 172 ошибки PHPCS, из-за них `make check` остаётся красным после подъёма зависимостей (задача TASK-chore-deps-prikotov-bump).
- Объём правок: 26 VO + ~90 файлов-потребителей (59 production в `src/`, остальные тесты) — масштабный механический рефакторинг, по регламенту вынесен из задачи подъёма зависимостей в отдельную задачу (прецедент: релиз 0.34.1 того же пакета — «Переименование выполняется отдельной задачей техдолга»).

### Варианты или путь решения (Solution Sketch)
- Для каждого из 26 VO: promoted-свойства `public` → `private`, добавить геттеры по конвенции: `getXxx()`, для `bool` — `isXxx()` / `hasXxx()`; конструкторы и места создания (named arguments) не меняются.
- Заменить все чтения `->property` на вызовы геттеров в потребителях (Application/Domain/Infrastructure, `apps/console`, тесты). Список потребителей уточнять через `vendor/bin/phpstan` (ошибки доступа к приватным свойствам) после каждого модуля.
- Порядок — по модулям: AgentRunner → ChainDefinition → ChainExecution → DynamicLoop; после каждого модуля прогон `vendor/bin/phpunit tests/Unit/`.

### Ожидаемый результат (Expected Result)
- `vendor/bin/phpcs --standard=phpcs.xml.dist -n src/` — 0 ошибок; `make check` полностью зелёный (последний красный барьер после подъёма `prikotov/*` до 0.35.0).

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Когда я открываю кодовый PR, я хочу, чтобы `make check` был зелёным на `main`, чтобы красный CI означал проблему моего PR, а не накопленный техдолг по новому правилу VO.

### Цель по SMART (Goal)
- 0 нарушений `ValueObjectStructureSniff` (172 → 0); `make check`, `vendor/bin/phpunit`, `vendor/bin/psalm` зелёные. Блокер для всех кодовых PR после merge TASK-chore-deps-prikotov-bump.

## 2. Контекст и Границы (Context and Scope)

- **Где делаем:** `src/Module/*/Domain/ValueObject/*.php` (26 файлов) и их потребители в `src/`, `apps/console/`, `tests/`.
- **Текущее состояние (2026-11-27):** `prikotov/coding-standard` 0.35.0 (после TASK-chore-deps-prikotov-bump); `vendor/bin/phpcs` показывает 172 ошибки в 26 файлах, все — `ValueObject properties must be private, found public`.
- **Нарушители (модуль → файлы (ошибок)):**
  - AgentRunner: AgentTurnResultVo (5)
  - ChainDefinition: QualityGateVo (3), ToolStepVo (4)
  - ChainExecution: ConditionalStepResultVo (13), ExecutionChainInfoVo (2), ExecutionConditionalChainConfigVo (5), ExecutionQualityGateVo (3), ExecutionStaticChainConfigVo (7), ExecutionToolStepVo (4), FallbackAttemptVo (10), QualityGateResultVo (5), StaticChainAuditVo (8), StaticChainResultVo (9), StaticProcessResultVo (3), StaticStepAuditVo (1), StaticStepResultVo (17), StepContextVo (7), ToolStepResultVo (4)
  - DynamicLoop: DynamicBudgetCheckVo (6), DynamicLoopContextVo (9), DynamicLoopResultVo (12), DynamicLoopTurnResultVo (5), DynamicRoundResultVo (19), FacilitatorTurnResultVo (6), TurnBreakVo (3), TurnContinueVo (2)
- **Out of scope:** DTO (публичные свойства DTO правилом не запрещены); новые Deptrac-слои 0.35.0 (`DomainEmbeddable` и др. — deptrac уже зелёный); изменения поведения и бизнес-логики; отключение правила PHPCS.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] Все 26 VO: свойства приватные, геттеры по конвенции `docs/conventions/core-patterns/value-object.md` (`getXxx()`, bool — `isXxx()`/`hasXxx()`, без сеттеров).
- [ ] Все потребители переведены на геттеры; поведение не меняется.
- [ ] `vendor/bin/phpcs --standard=phpcs.xml.dist -n src/` — 0 ошибок; `make check` зелёный; `vendor/bin/phpunit` и `vendor/bin/psalm` зелёные.
### ⚫ Won't Have (Не будем делать)
- Отключение/ослабление правила PHPCS; изменение контрактов границ модулей; правки DTO.

## 4. План реализации (Implementation Plan)
1. [ ] AgentRunner: 1 VO (5 свойств) + потребители, прогон Unit-тестов.
2. [ ] ChainDefinition: 2 VO (7 свойств) + потребители, прогон Unit-тестов.
3. [ ] ChainExecution: 15 VO (98 свойств) + потребители — самый большой модуль, при необходимости декомпозировать на подэтапы по каталогам `Execution*` / `Static*` / прочие.
4. [ ] DynamicLoop: 8 VO (62 свойства) + потребители, прогон Unit-тестов.
5. [ ] Полный прогон: `make check`, `vendor/bin/phpunit`, `vendor/bin/psalm`, `vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress`.

## 5. Критерии приёмки (Definition of Done)
- [ ] `vendor/bin/phpcs` — 0 нарушений; `make check` зелёный; `vendor/bin/phpunit` и `vendor/bin/psalm` зелёные.
- [ ] CI на PR зелёный (включая job маркера `local-check`).

## 6. Самопроверка (Verification)
```bash
vendor/bin/phpcs --standard=phpcs.xml.dist --no-colors -n src/
vendor/bin/phpunit
vendor/bin/psalm
make check
```

## 7. Риски и зависимости (Risks and Dependencies)
- Зависимость: TASK-chore-deps-prikotov-bump (правило появляется вместе с coding-standard 0.35.0).
- Имена свойств совпадают с полями DTO (например, `role`, `runner` у `StaticStepResultVo` и `StepResultDto`) — слепая замена `->prop` → `->getProp()` по репозиторию недопустима; правки только по локальному контексту файла, навигация — через ошибки `vendor/bin/phpstan`.
- Геттеры `readonly`-классов тривиальны, но объём ~116 затронутых файлов — держать PR только про механику, без смешивания с логикой.

## 8. Источники (Sources)
- Релиз `prikotov/coding-standard` v0.35.0: правило приватности свойств VO (`ValueObjectStructureSniff`, #135), классификация типов свойств (`ForbiddenPropertyType`, #136).
- `docs/conventions/core-patterns/value-object.md` (обновлён `coding-standard-init` до 0.35.0).
- Прецедент разделения: релиз v0.34.1 — «Переименование выполняется отдельной задачей техдолга; на ревью новых VO правило применяется сразу».
- Вывод `vendor/bin/phpcs --report=summary src/` (2026-11-27): 172 ошибки, 26 файлов.
- Задача TASK-chore-deps-prikotov-bump (подъём зависимостей, комментарии о разбиении).

## 9. Комментарии (Comments)
- Решение о разбиении принято при выполнении TASK-chore-deps-prikotov-bump 2026-11-27: объём (172 ошибки, ~90 файлов-потребителей) превышает рамки задачи подъёма зависимостей; план исходной задачи (п. 4.3) прямо предписывает эскалацию при большом объёме.

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-27 02:51:32 (1795747892) | Бэкендер Тони (pi) | Создание задачи |
