# Аудит кросс-модульных зависимостей: Domain\Contract и Integration-мапперы

**Роль:** Тимлид Алекс (запрос консультации)
**Дата:** 2026-05-06
**Объект:** 5 кросс-модульных зависимостей, отмеченных Deptrac `CrossModuleDomainRule`
**Задача:** `TASK-refactor-crossmodule-deptrac-rule` — пересмотр постановки

---

## Постановка вопроса

В кодовой базе 5 кросс-модульных зависимостей, которые Deptrac помечает как violations.
Предыдущий анализ (Гэндальф, Локи) предлагал «добавить исключения в rule» — но эта постановка ошибочна.

**Нужно ответить на два вопроса:**

1. **Правильно ли расположен код?** Если код стоит не в том слое/модуле — его надо переместить, а не добавлять исключения в Deptrac.
2. **Соответствуют ли зависимости конвенциям?** Если конвенции нарушены — надо чинить код, а не rule.

## Конвенции — что говорят

### Расположение интерфейсов (service.md)

Интерфейсы сервисов лежат в `Domain\Service\{Context}\{Name}ServiceInterface`:
```
{ProjectName}\Common\Module\{ModuleName}\Domain\Service\{Context?}\{ServiceName}ServiceInterface
```

**Ни в одной конвенции нет каталога `Domain\Contract\`.** Это нестандартная структура.

### Мапперы (mapper.md)

> **Разрешено**: Через DI зависимости из того же слоя. Примитивы, Enum, VO своего модуля и своего слоя. Другие мапперы того же слоя.

Маппер **не должен инжектить** сервисы чужого модуля. Он принимает данные на вход и возвращает результат.

### Integration-слой (integration.md)

> Integration вызывает Application чужого модуля.
> Реализует интеграции через интерфейсы сервисов своего Domain.

CrossModuleDomainRule разрешает: `Integration → foreign Application`. Всё остальное — violation.

## Текущие 5 кросс-модульных зависимостей

### #1–#2: Integration-мапперы инжектят ChainLoaderInterface (чужой Domain)

```
ChainExecution\Integration\Service\ChainDefinition\ChainExecutionDefinitionMapper
  → ChainDefinition\Domain\Contract\Chain\ChainLoaderInterface

DynamicLoop\Integration\Service\ChainDefinition\DynamicLoopDefinitionMapper
  → ChainDefinition\Domain\Contract\Chain\ChainLoaderInterface
```

`ChainExecutionDefinitionMapper` — это Integration-маппер, который:
- Инжектит `ChainLoaderInterface` из чужого `ChainDefinition\Domain\Contract\`
- Вызывает `$this->chainLoader->load($chainName)` — это I/O (чтение YAML-файла)
- Маппит `ChainDefinition\Domain\ValueObject\*` → `ChainExecution\Domain\ValueObject\*`

**По конвенциям mapper.md:** маппер не должен делать I/O. Маппер не должен инжектить чужие сервисы. Маппер принимает данные на вход.

**По конвенциям service.md:** `Domain\Contract\` — нестандартный каталог. Интерфейсы должны быть в `Domain\Service\`.

### #3: Infrastructure реализует AuditLoggerInterface (чужой Domain)

```
DynamicLoop\Infrastructure\Service\JsonlAuditLogger
  implements ChainExecution\Domain\Contract\Chain\Audit\AuditLoggerInterface
```

`JsonlAuditLogger` — Infrastructure-сервис, который реализует **два** интерфейса:
- `ChainExecution\Domain\Contract\Chain\Audit\AuditLoggerInterface` (чужой модуль)
- `DynamicLoop\Domain\Service\Audit\DynamicLoopAuditLoggerInterface` (свой модуль)

Один Physical Adapter обслуживает два Port'а из разных модулей.

### #4–#5: Integration-сервис инжектит RunAgentServiceInterface и PromptProviderInterface (чужой Domain)

```
DynamicLoop\Integration\Service\ChainExecution\RunDynamicLoopAgentService
  → ChainExecution\Domain\Contract\Agent\RunAgentServiceInterface
  → ChainExecution\Domain\Contract\Prompt\PromptProviderInterface
```

`RunDynamicLoopAgentService` — Integration-сервис, который:
- Инжектит `RunAgentServiceInterface` из чужого `ChainExecution\Domain\Contract\Agent\`
- Инжектит `PromptProviderInterface` из чужого `ChainExecution\Domain\Contract\Prompt\`
- Маппит DynamicLoop VO → ChainExecution VO и вызывает `agentRunner->run()`

## Что не так с Domain\Contract\

В проекте 4 интерфейса в `Domain\Contract\`:

```
ChainDefinition\Domain\Contract\Chain\ChainLoaderInterface
ChainExecution\Domain\Contract\Agent\RunAgentServiceInterface
ChainExecution\Domain\Contract\Chain\Audit\AuditLoggerInterface
ChainExecution\Domain\Contract\Prompt\PromptProviderInterface
```

Конвенции (service.md) говорят, что интерфейсы должны лежать в `Domain\Service\{Context}\{Name}ServiceInterface`.

В Deptrac (`depfile.yaml`) есть отдельный исключённый паттерн:
```yaml
# Domain\Service\Integration — ports (interfaces), stay in Domain layer
- type: classLike
  value: ^(?:[A-Za-z_]+\\)?Common\\Module\\.*\\Domain\\Service\\Integration\\.*
```

То есть `Domain\Service\Integration\` уже предусмотрен как стандартное место для Port'ов (интерфейсов, которые реализуются Integration-слоем).

**Вопрос:** зачем существует `Domain\Contract\`, если конвенции определяют `Domain\Service\`?

## Сводная таблица для анализа

| # | Класс (depender) | Зависимость (dependent) | Слой→Слой | Паттерн | Что не так |
|---|---|---|---|---|---|
| 1 | `ChainExecution\Integration\...ChainExecutionDefinitionMapper` | `ChainDefinition\Domain\Contract\ChainLoaderInterface` | Integration→foreign Domain | Маппер инжектит чужой сервис + делает I/O | Mapper.md: нет I/O, нет чужих зависимостей |
| 2 | `DynamicLoop\Integration\...DynamicLoopDefinitionMapper` | `ChainDefinition\Domain\Contract\ChainLoaderInterface` | Integration→foreign Domain | Маппер инжектит чужой сервис + делает I/O | Mapper.md: нет I/O, нет чужих зависимостей |
| 3 | `DynamicLoop\Infrastructure\...JsonlAuditLogger` | `ChainExecution\Domain\Contract\Audit\AuditLoggerInterface` | Infrastructure→foreign Domain (implements) | Один адаптер — два Port'а из разных модулей | Cross-module Port/Adapter |
| 4 | `DynamicLoop\Integration\...RunDynamicLoopAgentService` | `ChainExecution\Domain\Contract\RunAgentServiceInterface` | Integration→foreign Domain | Integration инжектит чужой Domain-контракт | Domain\Contract — не по конвенциям |
| 5 | `DynamicLoop\Integration\...RunDynamicLoopAgentService` | `ChainExecution\Domain\Contract\PromptProviderInterface` | Integration→foreign Domain | Integration инжектит чужой Domain-контракт | Domain\Contract — не по конвенциям |

## Что прошу от архитекторов

1. **Для каждого из 5 случаев:** код правильно расположен или его надо переместить/переписать?
2. **Domain\Contract\:** перенести интерфейсы в `Domain\Service\` по конвенциям или легализовать `Domain\Contract\` отдельной конвенцией?
3. **Мапперы (#1, #2):** они нарушают mapper.md (I/O + чужие зависимости). Как правильно организовать загрузку chain definition через Integration-слой без нарушения конвенций?
4. **Один адаптер — два Port'а (#3):** это нормальный паттерн или архитектурная ошибка?
5. **Integration→foreign Domain (#4, #5):** если Integration не может зависеть от чужого Domain, то откуда брать интерфейсы для cross-module delegation?

Формат: отчёт с конкретными решениями — что перемещаем, что переименовываем, какие интерфейсы создаём/удаляем.

---

## Исходные файлы

- `src/Module/ChainExecution/Integration/Service/ChainDefinition/ChainExecutionDefinitionMapper.php`
- `src/Module/DynamicLoop/Integration/Service/ChainDefinition/DynamicLoopDefinitionMapper.php`
- `src/Module/DynamicLoop/Infrastructure/Service/JsonlAuditLogger.php`
- `src/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentService.php`
- `src/Module/ChainDefinition/Domain/Contract/Chain/ChainLoaderInterface.php`
- `src/Module/ChainExecution/Domain/Contract/Agent/RunAgentServiceInterface.php`
- `src/Module/ChainExecution/Domain/Contract/Chain/Audit/AuditLoggerInterface.php`
- `src/Module/ChainExecution/Domain/Contract/Prompt/PromptProviderInterface.php`
- Конвенции: `docs/conventions/core-patterns/mapper.md`, `docs/conventions/core-patterns/service.md`, `docs/conventions/layers/integration.md`, `docs/conventions/layers/layers.md`
- Deptrac rule: `vendor/prikotov/coding-standard/src/Deptrac/CrossModuleDomainRule.php`
