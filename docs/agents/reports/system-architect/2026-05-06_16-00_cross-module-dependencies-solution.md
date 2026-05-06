# Архитектурный аудит: Domain\Contract и кросс-модульные зависимости

**Роль:** Архитектор Гэндальф
**Дата:** 2026-05-06
**Объект:** 5 кросс-модульных зависимостей, Domain\Contract\, Integration-мапперы
**Задача:** `TASK-refactor-crossmodule-deptrac-rule` — полная переоценка

---

## Резюме

**Проблема не в Deptrac-правилах. Проблема в коде.**

Создан нестандартный каталог `Domain\Contract\` как «лазейка» вокруг `ServiceContractDependencyRule`. Мапперы нарушают конвенции mapper.md (делают I/O, инжектят чужие сервисы). CrossModuleDomainFlag работает корректно — он ловит реальные архитектурные нарушения.

**Вердикт:** ни одно исключение в Deptrac-правила не нужно. Нужно починить код.

---

## Анализ

### 1. Domain\Contract\ — не по конвенциям

**Конвенция** (`service.md`): интерфейсы лежат в `Domain\Service\{Context}\{Name}ServiceInterface`.

**Факт:** в проекте 4 интерфейса в `Domain\Contract\`:

| Интерфейс | Где лежит | Где должен лежать |
|-----------|-----------|-------------------|
| `ChainLoaderInterface` | `ChainDefinition\Domain\Contract\Chain\` | `ChainDefinition\Domain\Service\Chain\ChainLoaderServiceInterface` |
| `RunAgentServiceInterface` | `ChainExecution\Domain\Contract\Agent\` | `ChainExecution\Domain\Service\Agent\RunAgentServiceInterface` |
| `AuditLoggerInterface` | `ChainExecution\Domain\Contract\Chain\Audit\` | `ChainExecution\Domain\Service\Chain\AuditServiceInterface` |
| `PromptProviderInterface` | `ChainExecution\Domain\Contract\Prompt\` | `ChainExecution\Domain\Service\Prompt\PromptProviderServiceInterface` |

В каждом PHPDoc написано:
> «Расположен в Contract (а не Service), чтобы ServiceContractDependencyRule не считал его cross-module сервисом»

**Это намеренное обходное решение (workaround) вместо следования конвенциям.** Вместо того чтобы проектировать взаимодействие правильно, интерфейсы засунули в несуществующий по конвенциям каталог, чтобы Deptrac их «не видел».

### 2. Мапперы #1, #2 — нарушают mapper.md

**`ChainExecutionDefinitionMapper`** и **`DynamicLoopDefinitionMapper`** — оба называются Mapper, но:

| Правило mapper.md | Нарушение |
|-------------------|-----------|
| «Не изменяет состояние БД и не выполняет I/O» | Вызывают `$this->chainLoader->load()` — это I/O (чтение YAML) |
| «Зависимости из того же слоя» | Инжектят `ChainLoaderInterface` из чужого модуля |
| «Примитивы, Enum, VO своего модуля» | Импортируют VO из `ChainDefinition\Domain\ValueObject\` |

**По конвенциям:** маппер — чистая функция (stateless transformer). Он принимает данные на вход и возвращает результат. Он не загружает данные.

**На самом деле это не мапперы.** Это Integration-сервисы, которые:
1. Загружают данные из чужого модуля (через `ChainLoaderInterface`)
2. Транслируют VO чужого модуля в VO своего модуля

Правильное название — `ChainDefinitionProvider` или `ChainDefinitionIntegrationService`.

### 3. JsonlAuditLogger (#3) — один адаптер, два Port'а

`DynamicLoop\Infrastructure\Service\JsonlAuditLogger` реализует:
- `ChainExecution\Domain\Contract\Chain\Audit\AuditLoggerInterface` (чужой модуль)
- `DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerInterface` (свой модуль)

**Проблема:** Infrastructure одного модуля не должен реализовывать интерфейсы чужого Domain. По конвенциям (`service.md`, Infrastructure Service): «Интерфейс размещается в Domain, реализация — в Infrastructure» — но это про **свой** Domain, не чужой.

**Правильное решение:** ChainExecution должен иметь свой Infrastructure-адаптер, реализующий `AuditLoggerInterface`. DynamicLoop не должен знать о существовании `ChainExecution\Domain\Contract\Audit\AuditLoggerInterface`.

### 4. RunDynamicLoopAgentService (#4, #5) — Integration инжектит чужой Domain

`DynamicLoop\Integration\Service\ChainExecution\RunDynamicLoopAgentService` инжектит:
- `ChainExecution\Domain\Contract\Agent\RunAgentServiceInterface`
- `ChainExecution\Domain\Contract\Prompt\PromptProviderInterface`

CrossModuleDomainRule разрешает только `Integration → foreign Application`. Обращение к чужому `Domain\*` — нарушение.

**Правильный путь:** Integration должен обращаться к чужому Application (QueryHandler/CommandHandler), не к Domain-интерфейсам напрямую.

---

## Решения

### Решение A: Мапперы #1, #2 — заменить инъекцию ChainLoaderInterface на Application Query

**Сейчас:**
```
ChainExecutionDefinitionMapper (Integration)
  → ChainLoaderInterface (ChainDefinition.Domain.Contract)  ← VIOLATION
```

**Правильно:**
```
ChainExecutionDefinitionMapper (Integration)
  → LoadChainQueryHandler (ChainDefinition.Application)  ← CROSS-MODULE: Integration→Application ✓
```

Маппер не должен загружать данные. Он должен получать уже загруженные данные из чужого Application (QueryHandler).

Но здесь нюанс: маппер реализует `ChainDefinitionProviderInterface` из своего Domain, который требует `loadChainInfo($name)` — то есть принимает строку (имя), а не готовый объект. Это значит мапперу нужно загрузить цепочку по имени — а это уже не чистый маппинг, а Integration-сервис.

**Два варианта:**

**Вариант A1:** Разделить загрузку и маппинг. Создать Integration-сервис, который:
1. Вызывает `LoadChainQueryHandler` (foreign Application)
2. Передаёт результат в чистый Mapper

```php
// Integration-сервис (делает I/O через foreign Application)
final readonly class ChainDefinitionProviderService implements ChainDefinitionProviderInterface
{
    public function __construct(
        private LoadChainQueryHandler $loadChainHandler,  // foreign Application ✓
        private ChainDefinitionDtoMapper $mapper,          // свой Application — чистый маппер
    ) {}
}

// Application-маппер (чистая трансформация, без I/O)
final readonly class ChainDefinitionDtoMapper
{
    public function mapStaticChain(LoadChainResult $dto): ExecutionStaticChainConfigVo { ... }
}
```

**Вариант A2 (проще):** Integration-сервис вызывает foreign QueryHandler напрямую, маппит inline.

```php
final readonly class ChainDefinitionProviderService implements ChainDefinitionProviderInterface
{
    public function __construct(
        private LoadChainQueryHandler $loadChainHandler,  // foreign Application ✓
    ) {}

    public function loadStaticChainConfig(string $chainName): ExecutionStaticChainConfigVo
    {
        $result = ($this->loadChainHandler)(new LoadChainQuery($chainName));
        return $this->mapStaticChain($result);
    }
}
```

Оба варианта убирают зависимость от `ChainLoaderInterface` (foreign Domain) и заменяют на `LoadChainQueryHandler` (foreign Application). CrossModuleDomainRule это разрешает.

**Переименовать:** Классы должны называться `*ProviderService` или `*IntegrationService`, а не `*Mapper` (они делают I/O, конвенция mapper.md не выполняется).

### Решение B: JsonlAuditLogger (#3) — убрать реализацию чужого Port

**Сейчас:**
```
DynamicLoop\Infrastructure\Service\JsonlAuditLogger
  implements AuditLoggerInterface (ChainExecution.Domain) ← VIOLATION
  implements DynamicLoopAuditLoggerInterface (DynamicLoop.Domain) ← OK
```

**Правильно:** `JsonlAuditLogger` реализует только `DynamicLoopAuditLoggerInterface` (свой Port). Для `AuditLoggerInterface` (чужой Port) — ChainExecution должен иметь свой Infrastructure-адаптер.

Если логика записи в JSONL общая — выделить Infrastructure-компонент в ChainExecution (или в общий Infrastructure), и оба логгера делегируют ему.

```
ChainExecution\Infrastructure\Service\JsonlAuditLogger
  implements AuditLoggerInterface (свой Domain) ✓

DynamicLoop\Infrastructure\Service\DynamicLoopAuditLogger
  implements DynamicLoopAuditLoggerInterface (свой Domain) ✓
```

### Решение C: RunDynamicLoopAgentService (#4, #5) — заменить Domain-контракты на Application Query

**Сейчас:**
```
RunDynamicLoopAgentService (DynamicLoop.Integration)
  → RunAgentServiceInterface (ChainExecution.Domain.Contract)  ← VIOLATION
  → PromptProviderInterface (ChainExecution.Domain.Contract)    ← VIOLATION
```

**Проблема:** Это не просто данные — это выполнение (run agent). Нельзя заменить на QueryHandler, потому что это Command (side-effect).

**Правильный путь:** DynamicLoop.Domain определяет свой Port для запуска агентов:

```php
// DynamicLoop\Domain\Service\Integration\RunAgentPortInterface
interface RunAgentPortInterface
{
    public function run(DynamicLoopRunRequestVo $request): DynamicLoopRunResultVo;
}
```

Integration-слой DynamicLoop реализует этот Port, обращаясь к ChainExecution.Application через CommandHandler:

```php
// DynamicLoop\Integration\Service\ChainExecution\RunAgentIntegrationService
final readonly class RunAgentIntegrationService implements RunAgentPortInterface
{
    public function __construct(
        private RunChainCommandHandler $commandHandler,  // foreign Application ✓
    ) {}
}
```

Если в ChainExecution.Application нет подходящего CommandHandler для запуска одного агента — его нужно создать. Это правильная граница: foreign Application предоставляет API (CommandHandler), Integration делегирует.

### Решение D: Domain\Contract\ — упразднить

Все 4 интерфейса перенести в `Domain\Service\` по конвенциям. `Domain\Contract\` — нестандартный каталог, созданный как обходной путь.

После перемещения:
- `ServiceContractDependencyRule` начнёт их контролировать (и это **правильно** — интерфейсы Domain-сервисов должны контролироваться)
- `CrossModuleDomainRule` продолжит их ловить при кросс-модульном использовании (и это тоже **правильно** — именно это заставит использовать Integration→foreign Application)

---

## План действий

| Шаг | Что делаем | Убирает violations |
|-----|-----------|-------------------|
| 1 | Упразднить `Domain\Contract\` → перенести в `Domain\Service\` | — (подготовка) |
| 2 | `ChainExecutionDefinitionMapper` → `ChainDefinitionProviderService`: инжектить `LoadChainQueryHandler` вместо `ChainLoaderInterface`, переименовать | #1 |
| 3 | `DynamicLoopDefinitionMapper` → аналогично | #2 |
| 4 | `JsonlAuditLogger`: убрать `implements AuditLoggerInterface`, создать отдельный `ChainExecution\Infrastructure\Service\JsonlAuditLogger` | #3 |
| 5 | `RunDynamicLoopAgentService`: создать Port в DynamicLoop.Domain, реализация через foreign Application CommandHandler | #4, #5 |
| 6 | Обновить Deptrac: violations должно быть 0 | — |

**Результат:** 0 violations, 0 исключений в правилах, код следует конвенциям.

---

## Исходные файлы для референса

- `src/Module/ChainExecution/Integration/Service/ChainDefinition/ChainExecutionDefinitionMapper.php`
- `src/Module/DynamicLoop/Integration/Service/ChainDefinition/DynamicLoopDefinitionMapper.php`
- `src/Module/DynamicLoop/Infrastructure/Service/JsonlAuditLogger.php`
- `src/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentService.php`
- `src/Module/ChainDefinition/Domain/Contract/Chain/ChainLoaderInterface.php`
- `src/Module/ChainExecution/Domain/Contract/Agent/RunAgentServiceInterface.php`
- `src/Module/ChainExecution/Domain/Contract/Chain/Audit/AuditLoggerInterface.php`
- `src/Module/ChainExecution/Domain/Contract/Prompt/PromptProviderInterface.php`
