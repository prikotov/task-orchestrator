# Критический разбор решения Гэндальфа: Domain\Contract и кросс-модульные зависимости

**Роль:** Архитектор Локи
**Дата:** 2026-05-06
**Объект:** Критический анализ отчёта Гэндальфа (2026-05-06_16-00)
**Задача:** `TASK-refactor-crossmodule-deptrac-rule`

---

## Резюме

Гэндальф прав в диагнозе: **проблема в коде, а не в Deptrac-правилах.** Но его решения содержат слепые зоны и одно переусложнённое предложение.

---

## Согласен с Гэндальфом

1. ✅ `Domain\Contract\` — не по конвенциям, workaround вокруг ServiceContractDependencyRule
2. ✅ Мапперы #1, #2 нарушают mapper.md (I/O + чужие зависимости)
3. ✅ Deptrac-правила менять не нужно
4. ✅ Код нужно чинить

---

## Слепые зоны и несогласие

### 1. Решение B (JsonlAuditLogger): Гэндальф предлагает ненужное дублирование

**Гэндальф предлагает:** два отдельных класса:
- `ChainExecution\Infrastructure\Service\JsonlAuditLogger implements AuditLoggerInterface`
- `DynamicLoop\Infrastructure\Service\DynamicLoopAuditLogger implements DynamicLoopAuditLoggerInterface`

**Проблема:** Оба логгера будут писать в один и тот же JSONL-файл одинаковыми записями. Это дублирование логики форматирования, дублирование пути файла в конфигурации, дублирование тестов.

**Реальный вопрос:** почему `AuditLoggerInterface` лежит в ChainExecution.Domain, а используется из DynamicLoop?

Audit — это cross-cutting concern. Оба модуля логируют в один формат. Правильный ответ:

**Вариант B1 (прагматичный):** Вынести интерфейс audit-логгера в **общий Shared Kernel** (или в `Domain\Service\Integration\` — depfile.yaml уже исключает этот каталог из Integration-коллектора). Тогда оба модуля зависят от общего контракта, а не друг от друга.

Но у нас нет формального Shared Kernel. И `Domain\Service\Integration\` — это Port'ы модуля, не Shared Kernel.

**Вариант B2 (минимальный):** DynamicLoop.Domain определяет **свой** Port для audit, как уже делает (`DynamicLoopAuditLoggerInterface`). А `JsonlAuditLogger` реализует только `DynamicLoopAuditLoggerInterface`. ChainExecution имеет свой `AuditLoggerInterface` и свою реализацию.

Да, будет два логгера. Но у них разные интерфейсы с разными сигнатурами (DynamicLoop VO vs ChainExecution VO). Дублирование записи в JSONL — infrastructure detail, решается через общий Infrastructure Component или трейт.

**Мой вердикт:** Решение Гэндальфа (B) в целом верное, но формулировка «создать отдельный JsonlAuditLogger в ChainExecution\Infrastructure» скрывает вопрос: кто сейчас реализует `AuditLoggerInterface` для ChainExecution? Если никто — значит ChainExecution.Domain.Port висит без реализации. Это отдельная задача.

### 2. Решение C (RunDynamicLoopAgentService): Гэндальф не видит существующий CommandHandler

**Гэндальф предлагает:** «создать Port в DynamicLoop.Domain, реализация через foreign Application CommandHandler». Звучит как будто CommandHandler не существует.

**Факт:** `RunAgentCommandHandler` **уже существует** в `ChainExecution\Application\UseCase\Command\RunAgent\`. Он делает ровно то, что нужно: принимает `RunAgentCommand`, вызывает `RunAgentServiceInterface`, возвращает `RunAgentResultDto`.

Текущий `RunDynamicLoopAgentService` дублирует логику `RunAgentCommandHandler`:
- Маппит VO → VO
- Вызывает `RunAgentServiceInterface->run()`
- Маппит результат обратно

Правильное решение: `RunDynamicLoopAgentService` должен вызывать `RunAgentCommandHandler` (foreign Application), а не `RunAgentServiceInterface` (foreign Domain). CommandHandler уже инкапсулирует всю логику.

Это **не введение искусственного слоя** (как я опасался). CommandHandler уже существует и используется. Integration-сервис просто вызывает его.

### 3. Решение D (упразднить Domain\Contract\): неполный анализ последствий

**Гэндальф:** «Перенести в Domain\Service\. ServiceContractDependencyRule начнёт их контролировать — и это правильно.»

**Слепое пятно:** Если перенести `ChainLoaderInterface` в `Domain\Service\Chain\ChainLoaderServiceInterface`, то `ServiceContractDependencyRule` проверит:
- Кто его реализует? Infrastructure-классы **своего** модуля — OK
- Кто его использует? Integration-сервисы **чужого** модуля — **cross-module service violation**

То есть `ServiceContractDependencyRule` добавит violations для этих интерфейсов. Но мы **уже** убираем кросс-модульное использование (решения A, C). Так что к моменту переноса violations не будет.

**Но:** нужно проверить, не сломает ли перенос интерфейсов существующие alias'ы в `services.yaml`. Каждый интерфейс, перенесённый в `Domain\Service\`, потребует обновления alias.

### 4. `Domain\Service\Integration\` — неявный Shared Kernel

В depfile.yaml:
```yaml
# Domain\Service\Integration — ports (interfaces), stay in Domain layer
- type: classLike
  value: ^...Domain\\Service\\Integration\\.*
```

Уже есть два интерфейса:
- `ChainExecution\Domain\Service\Integration\ChainDefinitionProviderInterface`
- `DynamicLoop\Domain\Service\Integration\ChainDefinitionProviderInterface`

Это Port'ы, которые модуль определяет для себя, а Integration-слой реализует. Это не Shared Kernel — это **локальные** интеграционные порты.

Но Deptrac исключает их из Integration-коллектора, оставляя в Domain-слое. Значит, они **могут** использоваться чужими модулями без layer violation (Domain→Domain в рамках матрицы). Но CrossModuleDomainRule всё равно поймает cross-module.

**Вывод:** `Domain\Service\Integration\` и `Domain\Contract\` — это одна и та же концепция, но с разными именами. Первая легализована в depfile.yaml, вторая — нет. Правильно: упразднить `Domain\Contract\` и использовать `Domain\Service\Integration\` для Port'ов, которые реализуются Integration-слоем.

---

## Уточнённый план

| Шаг | Что делаем | Замечание Локи |
|-----|-----------|----------------|
| 1 | Упразднить `Domain\Contract\` → перенести интерфейсы в `Domain\Service\` | Обновить alias в services.yaml |
| 2 | Мапперы → Integration Provider Service: инжектить `LoadChainQueryHandler` (foreign Application) | `LoadChainQueryHandler` уже существует |
| 3 | `JsonlAuditLogger`: убрать implements чужого Port | Проверить: кто сейчас реализует AuditLoggerInterface для ChainExecution? |
| 4 | `RunDynamicLoopAgentService`: заменить `RunAgentServiceInterface` + `PromptProviderInterface` на `RunAgentCommandHandler` (foreign Application) | CommandHandler уже существует |
| 5 | Удалить `TASK-refactor-crossmodule-deptrac-rule` — задача сводится к рефакторингу кода, а не Deptrac | Пересоздать как задачу на рефакторинг |

---

## Ответ на вопросы Тимлида

1. **Код правильно расположен?** Нет. Integration-мапперы делают I/O (нарушение mapper.md). Infrastructure реализует чужие Port'ы (нарушение module boundary).

2. **Domain\Contract\ перенести?** Да, в `Domain\Service\`. Это не创设ание нового паттерна — `Domain\Service\Integration\` уже существует и работает.

3. **Мапперы:** Превратить в Integration Provider Services. Вызывать foreign Application (LoadChainQueryHandler), а не foreign Domain (ChainLoaderInterface).

4. **Один адаптер — два Port'а:** Ошибка. Разделить на два Infrastructure-класса, каждый в своём модуле.

5. **Integration→foreign Domain:** Integration должен обращаться к foreign Application, не к foreign Domain. CommandHandlers уже существуют (RunAgentCommandHandler, LoadChainQueryHandler).

---

## Источники

- Конвенции: `docs/conventions/core-patterns/mapper.md`, `docs/conventions/core-patterns/service.md`, `docs/conventions/layers/layers.md`
- Deptrac: `vendor/prikotov/coding-standard/src/Deptrac/CrossModuleDomainRule.php`, `depfile.yaml`
- Существующий CommandHandler: `src/Module/ChainExecution/Application/UseCase/Command/RunAgent/RunAgentCommandHandler.php`
- Существующие Port'ы: `src/Module/ChainExecution/Domain/Service/Integration/ChainDefinitionProviderInterface.php`, `src/Module/DynamicLoop/Domain/Service/Integration/ChainDefinitionProviderInterface.php`
