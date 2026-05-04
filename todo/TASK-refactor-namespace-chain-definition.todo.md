---
type: refactor
created: 2026-05-04
value: V2
complexity: C2
priority: P1
depends_on:
epic: EPIC-refactor-responsibility-decomposition
author: Аналитик (Шерлок)
assignee: Бэкендер (Левша)
branch: task/refactor-namespace-chain-definition
pr:
status: in_progress
---

# TASK-refactor-namespace-chain-definition: Механический rename Orchestrator → ChainDefinition

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда модуль `Orchestrator` (11 573 LOC) содержит смешанные ответственности (определение цепочек, выполнение, dynamic-циклы), я хочу переименовать его в `ChainDefinition`, чтобы название отражало фактическую ответственность модуля после последующей декомпозиции.

### Goal (Цель по SMART)
Механически заменить namespace `TaskOrchestrator\Common\Module\Orchestrator` → `TaskOrchestrator\Common\Module\ChainDefinition` во всех PHP-файлах (src, tests, apps/console), конфигурации (services.yaml) и документации (docs/). **Без изменения логики.** После замены все тесты проходят, Psalm — 0 ошибок.

## 2. Context and Scope (Контекст и Границы)

### Где делаем

**Переименовывается директория:**
```
src/Module/Orchestrator/ → src/Module/ChainDefinition/
```

**Исходные файлы (74 файла, 11 573 LOC):**
Все файлы в `src/Module/Orchestrator/` — переносятся как есть.

**Затрагиваемые файлы вне модуля (imports/use statements):**

1. `src/Module/StaticExecution/` — 22 файла, 42 import-строки с `Module\Orchestrator\`
2. `apps/console/src/Module/Orchestrator/` — 4 файла (Command + EventSubscriber), включая namespace директории
3. `config/services.yaml` — все alias-ы с `Module\Orchestrator\`
4. Тесты — 58 файлов с `Module\Orchestrator\` (326 import-строк)
5. Документация: `docs/adr/006-*.md`, `docs/adr/007-*.md`, `docs/adr/008-*.md`, `docs/adr/009-*.md`, `docs/guide/architecture.md`

### Текущее поведение
Все файлы используют namespace `TaskOrchestrator\Common\Module\Orchestrator\*`. Модуль работает корректно.

### Границы (Out of Scope)
- ❌ Не меняем логику — только rename namespace
- ❌ Не создаём Integration-мапперы
- ❌ Не выделяем DynamicLoop (TASK-refactor-extract-dynamic-loop)
- ❌ Не вливаем StaticExecution (TASK-refactor-merge-static-execution)
- ❌ Не создаём depfile.yaml (TASK-refactor-deptrac-decomposition-rules)
- ❌ Не меняем содержимое StaticExecution\Domain (импорты обновятся, но структура модуля не меняется)
- ❌ Не удаляем ChainDefinitionVo (legacy-монолит, 555 LOC)
- ❌apps/console namespace `TaskOrchestrator\Console\Module\Orchestrator\` обновляется (импорты в use-statement'ах), но **папка apps/console/src/Module/Orchestrator/** НЕ переименовывается (это presentation-слой, не входит в scope)

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] Директория `src/Module/Orchestrator/` переименована в `src/Module/ChainDefinition/`
- [ ] Все namespace-декларации внутри модуля обновлены: `TaskOrchestrator\Common\Module\ChainDefinition\*`
- [ ] Все `use`-statement'ы в src/, tests/, apps/console/ обновлены с нового namespace
- [ ] `config/services.yaml` — все FQCN обновлены на `ChainDefinition`
- [ ] Тег `_instanceof` для `ExecutionStrategyInterface` обновлен
- [ ] Alias `Orchestrator\Domain\Service\Integration\RunAgentServiceInterface` обновлен
- [ ] Все 22 файла StaticExecution: import-пути обновлены на `ChainDefinition` (импорты из ChainDefinition\Domain\*)
- [ ] Все тесты проходят: `vendor/bin/phpunit`
- [ ] Psalm: `vendor/bin/psalm` — 0 ошибок
- [ ] PHPCS: `php vendor/prikotov/coding-standard/bin/run-sniff-tests.php`

### 🟡 Should Have (Желательно)
- [ ] Документация обновлена: `docs/guide/architecture.md`, ADR-006, ADR-007, ADR-008, ADR-009
- [ ] Обновлены комментарии в StaticExecution\Integration, где упоминается «Orchestrator»

### 🟢 Could Have (Опционально)
- [ ] Обновить `docs/adr/001-module-decomposition.md` если существует

### ⚫ Won't Have (Не будем делать)
- Не меняем namespace `TaskOrchestrator\Console\Module\Orchestrator\` (директория presentation-слоя)
- Не переименовываем методы/переменные с именем `orchestrator` в названии
- Не создаём Integration-мапперы

## 4. Implementation Plan (План реализации)

1. [ ] Создать ветку `task/refactor-namespace-chain-definition` от `main`
2. [ ] Переименовать директорию `src/Module/Orchestrator/` → `src/Module/ChainDefinition/`
3. [ ] Массовая замена namespace во всех PHP-файлах `src/Module/ChainDefinition/`:
   - `namespace TaskOrchestrator\Common\Module\Orchestrator\` → `namespace TaskOrchestrator\Common\Module\ChainDefinition\`
4. [ ] Массовая замена `use`-statement'ов в StaticExecution (42 строки):
   - `use TaskOrchestrator\Common\Module\Orchestrator\` → `use TaskOrchestrator\Common\Module\ChainDefinition\`
5. [ ] Обновить `config/services.yaml`:
   - Все FQCN с `Module\Orchestrator\` → `Module\ChainDefinition\`
   - Тег `orchestrator.execution_strategy` переименовать в `chain_definition.execution_strategy` (или оставить, если решено не менять тег — обсудить с пользователем)
6. [ ] Обновить `use`-statement'ы в `apps/console/src/Module/Orchestrator/` (4 файла)
7. [ ] Массовая замена `use`-statement'ов в тестах (58 файлов, 326 строк)
8. [ ] Обновить documentation: `docs/guide/architecture.md`, ADR-006, ADR-007, ADR-008, ADR-009
9. [ ] Запустить `vendor/bin/phpunit` — убедиться, что все тесты проходят
10. [ ] Запустить `vendor/bin/psalm` — убедиться, что 0 ошибок
11. [ ] Запустить PHPCS sniff-тесты
12. [ ] Закоммитить (по запросу пользователя)

## 5. Definition of Done (Критерии приёмки)

- [ ] Директория `src/Module/Orchestrator/` не существует
- [ ] `grep -r 'Module\\Orchestrator\\' src/ --include='*.php'` → 0 результатов
- [ ] `grep -r 'Module\\Orchestrator\\' tests/ --include='*.php'` → 0 результатов
- [ ] `grep -r 'Module\\Orchestrator\\' apps/ --include='*.php'` → 0 результатов
- [ ] `grep -r 'Module\\Orchestrator\\' config/services.yaml` → 0 результатов
- [ ] `vendor/bin/phpunit` → OK
- [ ] `vendor/bin/psalm` → 0 ошибок
- [ ] CLI-команда `app:agent:orchestrate` работает для static/dynamic/conditional (ручная проверка или integration-тест)
- [ ] Документация обновлена

## 6. Verification (Самопроверка)

```bash
# Механическая проверка отсутствия старого namespace
grep -r 'Module\\Orchestrator\\' src/ tests/ apps/ config/ --include='*.php' --include='*.yaml'

# Тесты
vendor/bin/phpunit
vendor/bin/psalm
php vendor/prikotov/coding-standard/bin/run-sniff-tests.php
```

## 7. Risks and Dependencies (Риски и зависимости)

### Риски

| Риск | Вероятность | Влияние | Митигация |
|------|-------------|---------|-----------|
| Пропущенный import → runtime error | Средняя | Высокое | Grep-проверка после замены + PHPUnit |
| Deptrac не существует (depfile.yaml нет) | — | Низкое | Нет depfile.yaml — не проверяем Deptrac |
| Переименование тега `orchestrator.execution_strategy` ломает tagged iterator | Низкая | Высокое | Если меняем тег — обновить везде синхронно |
| StaticExecution.Domain импортирует 13 VO из ChainDefinition.Domain | — | Ожидаемое | Это violation, но фиксим в TASK-refactor-merge-static-execution, не здесь |

### Зависимости
- **Не зависит от других задач** — первая в цепочке.
- **Блокирует:** TASK-refactor-extract-dynamic-loop, TASK-refactor-merge-static-execution (обе работают уже с namespace ChainDefinition).

## 8. Sources (Источники)

- [Brainstorm #6 protocol](../var/sessions/brainstorm/2026-05-04_01-59-17/discussion_history.md) — декомпозиция по ответственности
- [ADR-006: ExecutionStrategy Composition](../docs/adr/006-execution-strategy-composition.md)
- [ADR-007: VO ACL Boundary](../docs/adr/007-vo-acl-boundary.md)
- [ADR-008: Shared Kernel Contract](../docs/adr/008-shared-kernel-contract.md)
- [ADR-009: Dynamic Split Decision](../docs/adr/009-dynamic-split-decision.md) — суперседится данным эпиком
- [Конвенции: layers.md](../docs/conventions/layers/layers.md) — правила зависимостей Domain → никто
- [Конвенции: service.md](../docs/conventions/core_patterns/service.md) — alias Interface → Implementation

## 9. Comments (Комментарии)

### Почему namespace `ChainDefinition`, а не `Chain`
Имя модуля отражает его итоговую ответственность: загрузка, валидация и VO **определения** цепочек. После выделения DynamicLoop и вливания StaticExecution модуль будет содержать только Definition-логику.

### Почему StaticExecution.Domain после rename всё ещё импортирует ChainDefinition.Domain
Это известное нарушение конвенции «Domain → никто». В рамках данной задачи (механический rename) мы только обновляем import-пути. Исправление нарушения — задача TASK-refactor-merge-static-execution.

### Почему не переименовываем apps/console/src/Module/Orchestrator/
Presentation-слой не входит в scope декомпозиции. Директория и namespace `TaskOrchestrator\Console\Module\Orchestrator\` в apps/console — это presentation-обёртка, которая будет обновлена при отдельной задаче (или оставлена как есть — naming convention presentation не обязан совпадать с module naming).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-04 | Аналитик (Шерлок) | Создание задачи |
