---
type: refactor
created: 2026-06-15
value: V2
complexity: C1
priority: P3
depends_on:
epic:
author: Тимлид (Алекс)
assignee: Бэкендер (Левша)
branch: task/guard-chaindefinitionvo-fixiterations
pr: https://github.com/prikotov/task-orchestrator/pull/263
status: done
---

# TASK-guard-chaindefinitionvo-fixiterations: Восстановить fail-fast guard fix-итераций в deprecated ChainDefinitionVo

## 0. Простое описание (Human Brief)
Вернуть минимальную fail-fast проверку ссылочной целостности `fix_iterations` в приватный конструктор deprecated `ChainDefinitionVo` (была удалена целиком в PR #261).

### Проблема простыми словами (Problem)
В PR #261 из deprecated `ChainDefinitionVo` валидация `fix_iterations` удалена **целиком** — теперь `createLinearChain(...)` молча создаёт VO с невалидными ссылками (раньше кидал `InvalidArgumentException`). Дизайн давал fallback №2 (constructor-guard с generic-сообщением), но он не реализован. Это поведенческий BC-break deprecated API (Пуаро, слепая зона №1).

### Варианты или путь решения (Solution Sketch)
Добавить inline-проверку ссылочной целостности в приватный `ChainDefinitionVo::__construct()` (единая точка для всех статических фабрик): собрать множество имён шагов из `$steps`, пройти группы `$fixIterations`, при unknown-step или шаге в нескольких группах — бросать `InvalidArgumentException` с generic-сообщением. Без зависимости от `FixIterationsReferenceIntegritySpecification` (правило Deptrac `DomainVo` ↛ `DomainSpecification`).

### Ожидаемый результат (Expected Result)
Deprecated `ChainDefinitionVo` снова fail-fast на невалидных `fix_iterations` (generic-сообщение); production-фабрика и detailed-валидатор не затронуты; `make check` зелёный; NLOC класса < 500.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда разработчик обращается к deprecated `ChainDefinitionVo` (compatibility shim до удаления), я хочу, чтобы невалидные ссылки `fix_iterations` по-прежнему вызывали исключение на конструировании, чтобы инвариант не нарушался молча и поведение deprecated API не слабело по сравнению с предыдущей версией.

### Goal (Цель по SMART)
Добавить в `ChainDefinitionVo::__construct()` inline-проверку: unknown-step или шаг в нескольких группах `fix_iterations` → `InvalidArgumentException` с generic-сообщением. Покрыть unit-тестом. Не превысить NLOC-порог PHPMD (500).

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/ChainDefinition/Domain/ValueObject/ChainDefinitionVo.php` (приватный `__construct()`, строки ~53–78; тело сейчас пустое).
*   **Текущее состояние:** В `createLinearChain()` (строки ~169–174) стоит объясняющий комментарий о том, что валидация перенесена в фабрику; самого guard нет.
*   **Deptrac-ограничение:** `DomainVo` ↛ `DomainSpecification` — нельзя подключать `FixIterationsReferenceIntegritySpecification`. Проверка только inline.
*   **Границы (Out of Scope):**
    *   Не трогать `ChainDefinitionFactory`, `FixIterationsReferenceIntegritySpecification`, `ChainDefinitionValidatorService` (готово в PR #262).
    *   Не добавлять detailed-сообщения (это deprecated fail-fast guard, не детальный валидатор).
    *   Не менять поведение production-фабрики (guard только в deprecated VO).
    *   Не менять сигнатуры (поведенческий фикс, BC по поведению восстанавливается к досституту до #261).

## 3. Requirements (MoSCoW)
### 🔴 Must Have
- [ ] Guard в `ChainDefinitionVo::__construct()`: unknown-step → throw `InvalidArgumentException`.
- [ ] Guard в `ChainDefinitionVo::__construct()`: duplicate-across-groups → throw `InvalidArgumentException`.
- [ ] Generic-сообщение (одно на оба случая) вида: `Chain "%s" has invalid fix-iterations references (unknown step or step in multiple groups).`
- [ ] Unit-тест: невалидные `fix_iterations` через deprecated VO → `InvalidArgumentException`; валидные → OK.
- [ ] NLOC класса остаётся < 500 (проверить `make phpmd`).
- [ ] `make check` зелёный.

### 🟡 Should Have
- [ ] Именованный приватный метод-валидатор (например, `assertFixIterationsReferencesValid()`), чтобы конструктор остался читаемым и guard был переиспользуем/тестируем.

### ⚫ Won't Have (Не будем делать)
- Зависимость от `FixIterationsReferenceIntegritySpecification` (Deptrac).
- Detailed-сообщения (это не `ChainDefinitionValidatorService`).
- Правки `ChainDefinitionFactory` / спецификации / detailed-валидатора.
- Изменение поведения production-фабрики.

## 4. Implementation Plan

План (Бэкендер Левша, Reverse Briefing уточнит детали):

1. В `ChainDefinitionVo` добавить приватный static или instance-метод проверки: собрать множество имён именованных шагов из `$steps` (с учётом `getName() === null`), пройти группы `$fixIterations`, при unknown-step или шаге в нескольких группах — бросить `InvalidArgumentException` с generic-сообщением.
2. Вызывать проверку в приватном `__construct()` (после присвоения полей или до — на усмотрение исполнителя, главное единая точка для всех фабрик).
3. Обновить/убрать объясняющий комментарий в `createLinearChain()` (строки ~169–174), раз guard восстановлен — комментарий должен отражать новое состояние (generic guard inline, detailed-валидация в фабрике).
4. Unit-тесты в `tests/Unit/Domain/ValueObject/` (или рядом с существующими тестами `ChainDefinitionVo`): невалидные `fix_iterations` → throw; валидные → OK.
5. Контролировать NLOC: класс 483 non-blank, порог 500, запас ~17 — guard должен быть компактным (Should Have: вынесенный метод).

## 5. Definition of Done
- [ ] Guard работает (throw на невалидных `fix_iterations` в deprecated VO).
- [ ] Generic-сообщение дословно по шаблону Must Have.
- [ ] Unit-тесты зелёные.
- [ ] `make check` зелёный (включая `phpmd`, `deptrac`, `validate-todo`).
- [ ] NLOC класса < 500.
- [ ] Код чистый (без `@todo`/мусора), Conventional Commits.

## 6. Verification
```bash
vendor/bin/phpunit
vendor/bin/psalm
make phpmd
make deptrac
make check
```

## 7. Risks and Dependencies
- **NLOC:** класс 483 non-blank при пороге 500 (ExcessiveClassLength, `ignore-whitespace=false`). Запас ~17 строк — guard должен быть компактным. Риск превышения: низкий (Should Have — вынесенный метод ~8–12 NLOC).
- **Risk (production):** низкий. Deprecated-класс, 0 production-callers (Пуаро E3). Guard восстанавливает досститут-поведение, не вводит новое.
- **Deptrac:** guard inline, без зависимости от Specification — граница слоёв не нарушается.

## 8. Sources
- `src/Module/ChainDefinition/Domain/ValueObject/ChainDefinitionVo.php`
- Ревью: `docs/agents/reports/code-reviewer-backend/2026-06-15_08-50_pr-261-fixiterations-redesign-architecture-review.md` (слепая зона №1, раздел 4.3 fallback)
- Дизайн: `docs/agents/reports/system-architect/2026-06-15_00-10_fixiterations-validation-redesign.md` (раздел 4.3 — fallback constructor-guard)
- Смежная (завершённая) задача: `todo/done/TASK-sync-validator-specification-fix-iterations.todo.md` (PR #262)

## Инструкции для сабагента

**Ветка:** `task/guard-chaindefinitionvo-fixiterations` (уже создана от `main` и активна)

### Порядок действий
1. Реализуй задачу в текущей ветке согласно описанию выше (Must Have / Should Have).
2. Следуй [Конвенциям](../../docs/conventions/index.md) проекта и AGENTS.md.
3. Опирайся на ревью (`docs/agents/reports/code-reviewer-backend/2026-06-15_08-50_pr-261-fixiterations-redesign-architecture-review.md`, слепая зона №1) и дизайн (`docs/agents/reports/system-architect/2026-06-15_00-10_fixiterations-validation-redesign.md`, раздел 4.3 fallback).
4. После реализации запусти проверки: `make check`. Должен быть зелёным.
5. НЕ делай коммит и НЕ пуш — Тимлид контролирует git.

## Reverse Briefing (Левша → Тимлид)

**Подтверждаю понимание задачи.** Восстановить fail-fast guard ссылочной целостности
`fix_iterations` в приватном конструкторе deprecated `ChainDefinitionVo` (единая точка
для всех статических фабрик `createFromSteps` / `createFromConditionalSteps` /
`createFromDynamic`). Guard — inline-проверка **без** зависимости от
`FixIterationsReferenceIntegritySpecification` (правило Deptrac `DomainVo` ↛
`DomainSpecification`).

**План реализации:**
1. Добавить приватный instance-метод-предикат `areFixIterationsReferencesValid($steps,
   $fixIterations): bool`, алгоритмически эквивалентный `FixIterationsReferenceIntegritySpecification`
   (empty → true; `nameMap` из non-null `getName()`; для каждой группы — unknown-step
   или шаг в нескольких группах → false). Предикат выбран вместо `assert*(): void`, т.к.
   sniff `ValueObjectStructureSniff` запрещает void return type для non-static методов VO.
2. В приватном `__construct()` вызвать предикат и при `false` бросить
   `InvalidArgumentException` с generic-сообщением (единая точка для всех фабрик;
   для dynamic `fixIterations === []` → no-op).
3. Обновить объясняющий комментарий в `createLinearChain()` (~169–174): теперь отражает,
   что generic guard inline восстановлен в конструкторе, а detailed-валидация осталась
   в `ChainDefinitionFactory`.
4. Unit-тесты в `ChainDefinitionVoTest`: valid references → OK (в т.ч. несколько
   непересекающихся групп); unknown-step → throw; duplicate-across-groups → throw;
   unnamed-step (`getName() === null`) → throw (не резолвит ссылку). Проверка generic-сообщения.
5. `make check` зелёный.

**Уточнённые детали / допущения:**
- Guard восстанавливает поведение, существовавшее до PR #261: `InvalidArgumentException`
  вместо молчаливого принятия невалидных ссылок.
- Generic-сообщение дословно по Must Have:
  `Chain "%s" has invalid fix-iterations references (unknown step or step in multiple groups).`
  (отличается от сообщения `ChainDefinitionFactory` — это намеренно, deprecated-путь).
- Empty-steps guard (`count($steps) === 0`) остаётся в `createLinearChain()`, НЕ в
  конструкторе — dynamic-цепочки валидно имеют `steps: []`.
- Не трогаю: `ChainDefinitionFactory`, `FixIterationsReferenceIntegritySpecification`,
  `ChainDefinitionValidatorService`.
- **NLOC-бюджет (проверено эмпирически):** PHPMD `ExcessiveClassLength` считает NLOC
  по PHPDepend (non-comment lines). Реальный NLOC класса = **375** после правок (было 345;
  non-blank 483+ включает комментарии). Порог 500, запас ≈125 код-строк — guard вписывается
  с большим запасом. Should Have (вынесенный метод-предикат) выполнен.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-15 | Тимлид (Алекс) | Создание задачи. Постановка на основе слепой зоны №1 из ревью PR #261 (fallback №2 из дизайна, раздел 4.3). |
| 2026-06-15 | Бэкендер (Левша) | Reverse Briefing + реализация: guard в `__construct()` через pure-предикат `areFixIterationsReferencesValid(): bool`, 6 unit-тестов, `make check` зелёный (NLOC 375 < 500). |
| 2026-06-15 | Ревьювер (Пуаро) | Code review: APPROVE — эквивалентность спецификации, Deptrac-чистота (`DomainVo` ↛ `DomainSpecification`), sniff-совместимость, регрессий нет. PHPUnit 1042/1042, Psalm 0 errors, Deptrac 0 violations. |
| 2026-06-15 | Тимлид (Алекс) | Задача → done, файл перенесён в done/; PR #263 открыт. Ждёт подтверждения merge. |
