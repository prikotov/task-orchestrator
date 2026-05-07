# ADR-011: Межмодульное взаимодействие через Application

| Поле        | Значение                                             |
|-------------|------------------------------------------------------|
| Статус      | Принято                                              |
| Дата        | 2026-05-06                                           |
| Автор       | Архитектор (Гэндальф)                                |
| Участники   | Гэндальф, Локи, Левша, Алекс                        |
| Источник    | Аудит кросс-модульных Deptrac-нарушений              |

## Контекст

После декомпозиции монолитного Orchestrator на три модуля (ChainDefinition, ChainExecution, DynamicLoop) возникла необходимость формализовать модель межмодульного взаимодействия.

### Проблема

Первоначально Integration-слой одного модуля инжектил интерфейсы из `Domain\Service\` другого модуля напрямую:

```
DynamicLoop\Integration → ChainExecution\Domain\Service\Agent\RunAgentServiceInterface  ✗
DynamicLoop\Integration → ChainExecution\Domain\Service\Prompt\PromptProviderInterface   ✗
ChainExecution\Integration → ChainDefinition\Domain\Service\Chain\ChainLoaderInterface   ✗
DynamicLoop\Infrastructure → ChainExecution\Domain\Service\Chain\Audit\AuditLoggerInterface  ✗
```

Это нарушало `CrossModuleDomainRule` Deptrac: Integration/Infrastructure не имеет права зависеть от чужого Domain.

Также существовал каталог `Domain\Contract\` — workaround вокруг `ServiceContractDependencyRule`, не предусмотренный конвенциями.

### Решение из предыдущих ADR

- **ADR-001** устанавливает Integration-слой как ACL между модулями.
- **ADR-008** определяет Shared Kernel как identity-контракт (name, budget, roles).
- Ни один ADR не описывает модель взаимодействия Integration → foreign Application.

## Решение

**Integration-слой обращается к чужому Application (QueryHandler / CommandHandler), а не к чужому Domain.**

### Правило

```
Integration  →  foreign Application  ✓  (QueryHandler / CommandHandler)
Integration  →  foreign Domain       ✗  (интерфейсы, VO, Entity)
Infrastructure → foreign Domain      ✗  (реализует только свой Port)
Infrastructure → foreign Application ✗  (Infrastructure не общается с чужими модулями)
```

### Почему Application, а не Domain

Application-слой — это **API модуля**. Он:
1. Инкапсулирует Domain-реализации за типизированным контрактом
2. Принимает простые DTO (Query/Command) и возвращает DTO (Result) — Integration не видит чужих VO
3. Управляет транзакциями, side effects, маппингом внутри своего модуля

Domain — это **внутренняя реализация**. Interface Segregation: внешний мир не должен знать о структуре Domain.

### Паттерны Integration → foreign Application

#### 1. Query: получение данных из чужого модуля

```php
// ChainDefinition.Application — предоставляет API
final readonly class LoadRawChainQueryHandler
{
    public function __construct(private ChainLoaderInterface $loader) {}

    public function __invoke(LoadRawChainQuery $query): ChainDefinitionVo { ... }
}

// ChainExecution.Integration — потребляет через foreign Application
final readonly class ChainExecutionDefinitionMapper implements ChainDefinitionProviderInterface
{
    public function __construct(
        private LoadRawChainQueryHandler $loadRawChain,  // foreign Application ✓
    ) {}
}
```

#### 2. Command: вызов side-effect в чужом модуле

```php
// ChainExecution.Application — API запуска агента
final readonly class RunAgentQueryHandler
{
    public function __construct(private RunAgentServiceInterface $runner) {}

    public function run(ChainRunRequestVo $request, ?ExecutionRetryPolicyVo $retryPolicy = null): ChainRunResultVo { ... }
}

// DynamicLoop.Integration — вызывает через foreign Application
final readonly class RunDynamicLoopAgentService implements RunDynamicLoopAgentServiceInterface
{
    public function __construct(
        private RunAgentQueryHandler $agentRunner,  // foreign Application ✓
    ) {}
}
```

#### 3. Infrastructure реализует только свой Port

```php
// Каждый модуль имеет свой Infrastructure-адаптер для JSONL-логирования:

// ChainExecution.Infrastructure — реализует свой Port
final class JsonlAuditLogger implements AuditLoggerInterface { ... }

// DynamicLoop.Infrastructure — реализует свой Port
final class JsonlAuditLogger implements DynamicLoopAuditLoggerInterface { ... }
```

### Что запрещено

| Паттерн | Почему |
|---------|--------|
| `Integration → foreign Domain\Service\*Interface` | Нарушает инкапсуляцию Domain |
| `Infrastructure implements foreign Domain\*Interface` | Infrastructure не знает о чужих модулях |
| `Domain\Contract\` (workaround namespace) | Не по конвенциям, обходит Deptrac |
| Маппер, делающий I/O (загрузка данных) | Нарушает mapper.md: маппер — чистая функция |

### Deptrac-верификация

`CrossModuleDomainRule` и `ServiceContractDependencyRule` верифицируют модель автоматически:

```
vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress
→ 0 violations
```

0 violations = модель соблюдается.

## Обоснование

| Критерий | Integration → foreign Domain | Integration → foreign Application |
|----------|------------------------------|----------------------------------|
| Инкапсуляция Domain | ✗ Integration видит VO, Entity | ✓ Integration видит только DTO |
| Свобода рефакторинга Domain | ✗ Изменение VO ломает Integration | ✓ Только Application API — контракт |
| Deptrac-контроль | ✗ Нужны исключения | ✓ 0 violations без исключений |
| Конвенции (service.md, mapper.md) | ✗ Мапперы делают I/O | ✓ Маппинг внутри Application |
| Тестируемость | ✗ Mock чужих Domain-интерфейсов | ✓ Mock Application handlers |

## Последствия

### Положительные

- **Формализованная модель** межмодульного взаимодействия, верифицируемая Deptrac
- **Domain каждого модуля — чёрный ящик** для других модулей
- **Application — единственная точка входа** в модуль извне
- **Нет исключений в Deptrac** — код следует правилам, а не наоборот

### Отрицательные

- **Бойлерплейт:** каждый cross-module вызов требует Application-level обёртки (QueryHandler/CommandHandler)
- **Косвенность:** один вызов проходит Integration → foreign Application → foreign Domain, а не напрямую

### Компромисс

Бойлерплейт (~30–50 строк на handler) — предсказуемая и конечная цена за изолированность модулей. При добавлении нового модуля паттерн повторяется механически.

## Альтернативы

1. **Integration → foreign Domain (статус-кво):** Integration инжектит интерфейсы чужого Domain. Отвергнуто — нарушает Deptrac, ломает инкапсуляцию, требует workaround (`Domain\Contract\`).

2. **Shared Kernel с общими интерфейсами:** Выделить общие интерфейсы в отдельный Shared Kernel. Отвергнуто — ADR-008 уже ограничивает Shared Kernel до identity (name, budget, roles). Расширение Shared Kernel под каждую потребность = его размывание.

3. **Event-driven взаимодействие:** Модули общаются через события. Отвергнуто — оркестрация синхронна (запрос→результат), события добавляют ненужную сложность (eventual consistency, ordering).

4. **Direct DI (Dependency Injection без слоя):** Service container напрямую связывает модули. Отвергнуто — нарушает слоистую архитектуру, Deptrac не может верифицировать.

## Критерии пересмотра

ADR пересматривается, если:
1. Появляется async-оркестрация (тогда — event-driven модель)
2. Модуль имеет > 5 Application API для одного foreign-модуля (возможно, модули стоит объединить)
3. Performance-профилирование показывает, что indirect-call overhead значим (маловероятно для PHP)

## Ссылки

- [ADR-001: Декомпозиция на модули](001-module-decomposition.md)
- [ADR-008: Shared Kernel Contract](008-shared-kernel-contract.md)
- [Конвенции: layers.md](../conventions/layers/layers.md)
- [Конвенции: service.md](../conventions/core-patterns/service.md)
- [Конвенции: mapper.md](../conventions/core-patterns/mapper.md)
- [Аудит кросс-модульных зависимостей](../agents/reports/system-architect/2026-05-06_15-00_cross-module-dependencies-audit.md)
