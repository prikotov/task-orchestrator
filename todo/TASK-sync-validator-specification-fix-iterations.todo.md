---
type: refactor
created: 2026-06-15
value: V2
complexity: C1
priority: P2
depends_on:
epic:
author: Тимлид (Алекс)
assignee: Бэкендер (Левша)
branch: task/sync-validator-specification-fix-iterations
pr:
status: in_progress
---

# TASK-sync-validator-specification-fix-iterations: Синхронизировать детальную валидацию fix-итераций со спецификацией

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда команда сопровождает доменное правило ссылочной целостности групп `fix_iterations`, я хочу, чтобы детальный отчёт `ChainDefinitionValidatorService` отражал все правила из `FixIterationsReferenceIntegritySpecification`, чтобы ни одно нарушение не «пряталось» только в bool-спецификации и правила не расходились между двумя реализациями.

### Goal (Цель по SMART)
Закрыть расхождение, выявленное в ревью PR #261 (слепая зона №3 Ревьювера Бэка Пуаро): спецификация `FixIterationsReferenceIntegritySpecification` строже детального валидатора — она запрещает принадлежность имени шага нескольким группам `fix_iteration`, а `ChainDefinitionValidatorService` такое пропускает. Нужно добавить в валидатор детальную диагностику duplicate-membership и зафиксировать тестами, что `specification → false` всегда сопровождается violation у валидатора.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `src/Module/ChainDefinition/Domain/Service/Chain/ChainDefinitionValidatorService.php` — метод `validateStepBasedChain()` (блок проверки `fix_iterations`, строки ~75–102). Сейчас: `$stepNameMap` пересоздаётся внутри цикла по группам, проверяется только `references unknown step`, проверки duplicate-membership нет.
    *   `tests/Unit/Domain/Service/Chain/ChainDefinitionValidatorTest.php` — добавить кейсы duplicate-membership; сохранить существующий assertion на `references unknown step` (строка ~154).
*   **Текущее поведение:** `FixIterationsReferenceIntegritySpecification::isSatisfiedBy()` проверяет ДВА правила (unknown step → false, duplicate step across groups → false). `ChainDefinitionValidatorService` проверяет в отчёте только первое. Окно узкое (production-цепи создаются через `ChainDefinitionFactory` с generic fail-fast), но gap реален и нарушает принцип «одно правило — одна реализация».
*   **Границы (Out of Scope):**
    *   🔵 **Не трогаем** deprecated `ChainDefinitionVo` (слепая зона №1: утеря валидации fix-итераций целиком). Это отдельная задача — поведенческий BC-break deprecated API, оформляется отдельно.
    *   🟡 **Не трогаем** структурную недостижимость detailed-валидации в production-пути `validate-config` (слепая зона №2: loader падает раньше валидатора). Принятое решение «generic fail-fast допустим» остаётся в силе.
    *   Не меняем сигнатуру `ChainConfigViolationVo`, не меняем контракт `ChainDefinitionValidatorServiceInterface`.
    *   Не трогаем `FixIterationsReferenceIntegritySpecification` и `ChainDefinitionFactory` (уже в `main` через PR #261).

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] В `ChainDefinitionValidatorService::validateStepBasedChain()` добавить детальную диагностику принадлежности имени шага нескольким группам `fix_iteration`. Текст сообщения — по дизайну Архитектора Локи (`2026-06-15_00-10_fixiterations-validation-redesign.md`, §3.2), дословно:
  `fix_iteration step "%s" belongs to multiple groups ("%s" and "%s").` (шаг, первая группа, вторая группа).
- [ ] Вынести построение `$stepNameMap` из именованных шагов ДО цикла по группам (сейчас map пересоздаётся в каждой итерации — логически некорректно для duplicate-проверки и просто расточительно).
- [ ] Сохранить существующую диагностику `fix_iteration group "%s" references unknown step "%s".` и порядок проверок: unknown step диагностируется РАНЬШЕ duplicate-membership для одного и того же шага (соответствует `FixIterationsReferenceIntegritySpecification`, где unknown → false срабатывает раньше duplicate).
- [ ] Покрыть unit-тестами в `ChainDefinitionValidatorTest`: (1) duplicate step across groups → violation с ожидаемым текстом; (2) сохранён unknown-step кейс; (3) валидные fix-итерации без нарушений.

### 🟡 Should Have (Желательно)
- [ ] Зафиксировать anti-divergence: добавить/скорректировать тест(ы), гарантирующие, что любой вход, на котором `FixIterationsReferenceIntegritySpecification::isSatisfiedBy() === false`, даёт непустой список violations у валидатора (для unknown step и для duplicate). Можно через параметризованные/shared-фикстуры кейсы — на усмотрение исполнителя.

### 🟢 Could Have (Опционально)
- [ ] Переиспользовать `FixIterationsReferenceIntegritySpecification` внутри валидатора как fast pre-check БЕЗ изменения детального контракта (только если это не дублирует логику и не нарушает Deptrac). Если усложняет — не делать; приоритет — детальная диагностика.

### ⚫ Won't Have (Не будем делать)
- [ ] Правки deprecated `ChainDefinitionVo` (отдельная задача).
- [ ] Изменение production-пути `validate-config` / `ValidateChainConfigQueryHandler` (отдельное решение).
- [ ] Изменение сигнатур `ChainConfigViolationVo` / `ChainDefinitionValidatorServiceInterface`.

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем (Бэкендером Левшей) перед стартом — подтвердить или скорректировать.*
1. [ ] Прочитать дизайн `docs/agents/reports/system-architect/2026-06-15_00-10_fixiterations-validation-redesign.md` (§3.2) и ревью `docs/agents/reports/code-reviewer-backend/2026-06-15_08-50_pr-261-fixiterations-redesign-architecture-review.md` (слепая зона №3).
2. [ ] В `validateStepBasedChain()`: поднять построение `$stepNameMap` до цикла по группам; ввести аккумулятор «увиденных» шагов (имя → группа) для duplicate-проверки; при встрече уже виденного шага формировать violation с текстом-шаблоном из Must Have.
3. [ ] Обеспечить порядок проверок (unknown раньше duplicate для того же шага).
4. [ ] Добавить/скорректировать тесты в `ChainDefinitionValidatorTest` (Must Have + Should Have).
5. [ ] Прогнать `make check` (или `vendor/bin/phpunit`, `vendor/bin/psalm`, Deptrac).

## 5. Definition of Done (Критерии приёмки)
- [ ] Добавлена диагностика duplicate-membership с текстом ровно по шаблону дизайна.
- [ ] `$stepNameMap` строится один раз до цикла по группам.
- [ ] Порядок проверок совпадает со спецификацией (unknown раньше duplicate).
- [ ] Unit-тесты покрывают: duplicate → violation, unknown → сохранённый violation, валидные → без нарушений.
- [ ] Anti-divergence зафиксирован тестом (spec → false ⇒ violations непустой).
- [ ] `vendor/bin/phpunit` зелёный; `vendor/bin/psalm` — 0 ошибок; Deptrac — 0 violations.
- [ ] Слои/конвенции не нарушены; нет терминов Port/Adapter.

## 6. Verification (Самопроверка)
```bash
make check
# или явно:
vendor/bin/phpunit tests/Unit/Domain/Service/Chain/ChainDefinitionValidatorTest.php
vendor/bin/phpunit
vendor/bin/psalm
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Доступность detailed-проверок в production.** Подтверждено в ревью Пуаро: в production-пути `validate-config` detailed-валидатор по fix-итерациям структурно недостижим (loader падает раньше). Эта задача НЕ чинит production-путь — только синхронизирует набор правил и делает detailed-проверки корректными для тестов/pre-flight.
- **Расхождение форматов сообщений.** Текст duplicate-диагностики взят дословно из дизайна; при изменении согласовывать с архитектором.
- Зависимости: нет. База PR #261 уже в `main`.

## 8. Sources (Источники)
- [ ] Дизайн: `docs/agents/reports/system-architect/2026-06-15_00-10_fixiterations-validation-redesign.md` (§3.2 — recommended diagnostic).
- [ ] Ревью: `docs/agents/reports/code-reviewer-backend/2026-06-15_08-50_pr-261-fixiterations-redesign-architecture-review.md` (слепая зона №3).
- [ ] Спецификация: `src/Module/ChainDefinition/Domain/Specification/Chain/FixIterationsReferenceIntegritySpecification.php`.
- [ ] Конвенции: `docs/conventions/layers/domain/specification.md`, `docs/conventions/layers/domain/service.md`.

## 9. Comments (Комментарии)
- Исполнитель: **Бэкендер Левша**. Ревью: **Ревьювер Бэка Пуаро** (владеет контекстом PR #261 и обоими слепыми зонами).
- Цикл по `task-via-subagents`: реализация → self-review → code review → доработки → PR.
- Дизайн и ревью уже лежат в `main` как отчёты — исполнитель опирается на них как на авторитетный источник решения.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-15 | Тимлид (Алекс) | Создание задачи. Постановка на основе ревью PR #261 (слепая зона №3). |
