---
type: refactor
created: 2026-05-06
value: V2
complexity: C3
priority: P1
depends_on:
epic: EPIC-refactor-responsibility-decomposition
author: Тимлид Алекс
assignee:
branch:
pr:
status: todo
---

# TASK-refactor-cross-module-dependencies: Устранение кросс-модульных зависимостей через рефакторинг кода

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда Integration-мапперы инжектят интерфейсы из чужого Domain, а Infrastructure реализует Port'ы чужого модуля, я хочу переписать эти классы по конвенциям (через foreign Application, а не foreign Domain), чтобы Deptrac показывал 0 violations без изменений правил.

### Goal (Цель по SMART)
Устранить все 5 кросс-модульных Deptrac-violations рефакторингом кода (0 изменений в Deptrac-правилах). Каждый класс следует конвенциям mapper.md, service.md, layers.md.

## 2. Context and Scope (Контекст и Границы)

### Где делаем
**Изменяются:** Integration и Infrastructure классы в ChainExecution и DynamicLoop
**Упраздняется:** каталог `Domain\Contract\` (4 интерфейса переносятся в `Domain\Service\`)

### Диагноз (консенсус архитекторов Гэндальфа + Локи)
- `Domain\Contract\` — нестандартный каталог, созданный как workaround вокруг `ServiceContractDependencyRule`
- Мапперы `*DefinitionMapper` нарушают mapper.md: делают I/O (ChainLoaderInterface→load) и инжектят чужие сервисы
- `JsonlAuditLogger` реализует Port чужого модуля (AuditLoggerInterface из ChainExecution.Domain)
- `RunDynamicLoopAgentService` инжектит интерфейсы из чужого Domain вместо вызова foreign Application

### Границы (Out of Scope)
- ❌ Не меняем Deptrac-правила (ни CrossModuleDomainRule, ни ServiceContractDependencyRule)
- ❌ Не меняем Domain-логику (VO, Entity, инварианты)
- ❌ Не создаём новые модули
- ❌ Не трогаем TASK-docs-shared-kernel-contracts (отдельная задача)

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

**Шаг 1: Упразднить `Domain\Contract\`**
- [ ] `ChainDefinition\Domain\Contract\Chain\ChainLoaderInterface` → перенести в `Domain\Service\Chain\ChainLoaderServiceInterface` (или `ChainLoaderInterface` в `Domain\Service\Chain\`)
- [ ] `ChainExecution\Domain\Contract\Agent\RunAgentServiceInterface` → перенести в `Domain\Service\Agent\RunAgentServiceInterface`
- [ ] `ChainExecution\Domain\Contract\Chain\Audit\AuditLoggerInterface` → перенести в `Domain\Service\Chain\AuditServiceInterface`
- [ ] `ChainExecution\Domain\Contract\Prompt\PromptProviderInterface` → перенести в `Domain\Service\Prompt\PromptProviderServiceInterface`
- [ ] Обновить все `use`-импорты, `services.yaml` alias'ы, реализации

**Шаг 2: Мапперы #1, #2 → Integration Provider Services**
- [ ] `ChainExecutionDefinitionMapper` → переименовать в `ChainDefinitionProviderService` (или подобное)
  - Заменить инжекцию `ChainLoaderInterface` (foreign Domain) на `LoadChainQueryHandler` (foreign Application)
  - Убрать I/O из маппера — получать данные через QueryHandler
- [ ] `DynamicLoopDefinitionMapper` → аналогично
- [ ] Классы больше не называются `*Mapper` — они делают I/O и нарушают конвенцию mapper.md

**Шаг 3: JsonlAuditLogger (#3)**
- [ ] Убрать `implements AuditLoggerInterface` (чужой Port)
- [ ] Оставить только `implements DynamicLoopAuditLoggerInterface` (свой Port)
- [ ] Проверить: кто реализует `AuditLoggerInterface` для ChainExecution? Если никто — создать реализацию в `ChainExecution\Infrastructure\`

**Шаг 4: RunDynamicLoopAgentService (#4, #5)**
- [ ] Заменить инжекцию `RunAgentServiceInterface` + `PromptProviderInterface` (foreign Domain) на `RunAgentCommandHandler` (foreign Application)
- [ ] `RunAgentCommandHandler` уже существует в `ChainExecution\Application\UseCase\Command\RunAgent\`
- [ ] Маппить DynamicLoop VO → RunAgentCommand, результат → DynamicLoop VO

**Шаг 5: Верификация**
- [ ] `vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress` → 0 violations
- [ ] `vendor/bin/phpunit` → OK
- [ ] `vendor/bin/psalm` → OK

### 🟡 Should Have (Желательно)
- [ ] Unit-тесты на новые Integration Provider Services
- [ ] Обновить PHPDoc в перенесённых интерфейсах (убрать упоминания «Contract»)

### ⚫ Won't Have (Не будем делать)
- Не меняем Deptrac-правила
- Не создаём Shared Kernel
- Не рефакторим Domain-логику

## 4. Implementation Plan (План реализации)

1. [ ] Создать ветку `task/refactor-cross-module-dependencies` от `main`
2. [ ] Шаг 1: Перенести 4 интерфейса из `Domain\Contract\` в `Domain\Service\`
3. [ ] Шаг 2: Переписать 2 маппера как Integration Provider Services (foreign Application вместо foreign Domain)
4. [ ] Шаг 3: Разделить JsonlAuditLogger — убрать чужой Port
5. [ ] Шаг 4: Переписать RunDynamicLoopAgentService — foreign Application вместо foreign Domain
6. [ ] Шаг 5: Запустить все проверки, убедиться что 0 violations
7. [ ] Создать PR

## 5. Definition of Done (Критерии приёмки)
- [ ] `vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress` → 0 violations, 0 errors
- [ ] `vendor/bin/phpunit` → OK
- [ ] `vendor/bin/psalm` → OK
- [ ] Каталог `Domain\Contract\` удалён из всех модулей
- [ ] Ни один Integration-класс не инжектит интерфейсы из чужого Domain
- [ ] Ни один Infrastructure-класс не реализует интерфейсы из чужого Domain

## 6. Verification (Самопроверка)
```bash
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
vendor/bin/phpunit
vendor/bin/psalm
grep -r 'Domain.Contract' src/  # → пусто
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск:** Перенос интерфейсов может сломать `services.yaml` alias'ы — mitigated обновлением alias
- **Риск:** `RunAgentCommandHandler` может не покрывать все сценарии `RunDynamicLoopAgentService` (facilitator, participant, finalize) — нужно проверить
- **Риск:** Разделение JsonlAuditLogger может потребовать создания нового Infrastructure-класса в ChainExecution

## 8. Sources (Источники)
- [Аудит кросс-модульных зависимостей](../docs/agents/reports/system-architect/2026-05-06_15-00_cross-module-dependencies-audit.md)
- [Решение Гэндальфа](../docs/agents/reports/system-architect/2026-05-06_16-00_cross-module-dependencies-solution.md)
- [Критический разбор Локи](../docs/agents/reports/system-architect/2026-05-06_17-00_critical-review-cross-module-solution.md)
- [Конвенции: mapper.md](../docs/conventions/core-patterns/mapper.md)
- [Конвенции: service.md](../docs/conventions/core-patterns/service.md)
- [Конвенции: layers.md](../docs/conventions/layers/layers.md)

## 9. Comments (Комментарии)
Консенсус обоих архитекторов: проблема в коде, а не в Deptrac-правилах. Предыдущая задача TASK-refactor-crossmodule-deptrac-rule была основана на ошибочной посылке «добавить исключения в rule». Правильный подход — рефакторинг кода.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-06 | Тимлид Алекс | Создание задачи (замена TASK-refactor-crossmodule-deptrac-rule) |
