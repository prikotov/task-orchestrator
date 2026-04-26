# Анализ ChainProviderService: необходимость и альтернативы

**Роль:** Архитектор Локи
**Дата:** 2026-04-25
**Объект:** `ChainProviderServiceInterface` + `ChainProviderService` (Application/Service/Chain/)
**Задача:** Оценить необходимость Application Service для цепочек, предложить вариант без него

---

## Постановка вопроса

Пользователь спрашивает: зачем нужен `ChainProviderServiceInterface` и его реализация? Хочется обойтись без Application-сервисов.

## Текущее состояние

### Что делает ChainProviderService

`ChainProviderService` — прослойка, которая:
1. Берёт Domain `ChainLoaderInterface` и Domain `ChainDefinitionValidator`
2. Вызывает их (возвращает Domain VO)
3. Маппит Domain VO → Application DTO (`ChainDefinitionVo` → `ChainDefinitionDto`)

```
ChainLoaderInterface::load()    → ChainDefinitionVo
                                   ↓ mapToDto()
                                  ChainDefinitionDto

ChainDefinitionValidator::validate() → ChainConfigViolationVo[]
                                       ↓ array_map
                                      ChainConfigViolationDto[]
```

### Кто его использует

**Было (до текущего коммита):**
- `OrchestrateCommand` (Presentation) → `ChainProviderServiceInterface` напрямую

**Стало (после текущего коммита):**
- `OrchestrateCommand` (Presentation) → `LoadChainQueryHandler` → `ChainProviderServiceInterface`
- `OrchestrateCommand` (Presentation) → `ValidateChainConfigQueryHandler` → `ChainProviderServiceInterface`
- `ListChainsQueryHandler` → `ChainProviderServiceInterface` (создан, но не используется в Command)

Presentation уже не знает про `ChainProviderServiceInterface`. Только 3 Query Handler'а зависят от него.

### Сравнение с другими Application-сервисами проекта

| Сервис | Зависит от | Кто использует | Имеет интерфейс |
|--------|-----------|---------------|-----------------|
| `ExecuteStaticChainService` | Domain interfaces (`RunAgentServiceInterface`, `QualityGateRunnerInterface`, …) | `OrchestrateChainCommandHandler` | Да |
| `DispatchRoundEventService` | `EventDispatcherInterface` (Symfony) | Domain через `RoundCompletedNotifierInterface` | Реализует Domain-интерфейс |
| `ResolveExitCodeService` | Только Application типы | `OrchestrateCommand` | Да |
| **`ChainProviderService`** | `ChainLoaderInterface` + `ChainDefinitionValidator` (Domain) | 3 Query Handler'а | Да |

## Диагноз

`ChainProviderService` — это ** mapper-прослойка без собственной логики**. Все его методы — это:
1. Вызов Domain-сервиса
2. Трансформация VO → DTO

Это **ровно то, что Query Handler и должен делать** по конвенции проекта (`docs/conventions/layers/application.md`):

> *«Query Handler: маппит доменные модели в DTO»*

### Точная формулировка проблемы

`ChainProviderService` дублирует ответственность Query Handler'а. Паттерн:

```
Handler → Service → Domain    (текущий, лишнее звено)
Handler → Domain              (как должно быть по конвенции)
```

Доказательство — `OrchestrateChainCommandHandler` уже работает с `ChainLoaderInterface` напрямую, без промежуточного сервиса. Тот же паттерн уместен и для Query Handler'ов.

## Предложение: инлайн маппинга в Handler'ы

### Что делаем

1. **Удалить** `ChainProviderServiceInterface` и `ChainProviderService`
2. **Перенести** маппинг VO→DTO из `ChainProviderService::mapToDto()` в Handler'ы (или в отдельный Mapper-класс по конвенции `Application/Mapper/`)
3. **Инжектить** Domain-зависимости напрямую в Handler'ы: `ChainLoaderInterface` + `ChainDefinitionValidator`

### Как будет выглядеть LoadChainQueryHandler

```php
class LoadChainQueryHandler
{
    public function __construct(
        private ChainLoaderInterface $chainLoader,     // Domain
        private ChainDefinitionDtoMapper $mapper,      // Application Mapper
    ) {}

    public function __invoke(LoadChainQuery $query): LoadChainResult
    {
        if ($query->configPath !== null) {
            $this->chainLoader->overridePath($query->configPath);
        }

        $chainVo = $this->chainLoader->load($query->chainName);

        return new LoadChainResult(
            chain: $this->mapper->map($chainVo),
        );
    }
}
```

### Куда девать маппинг

По конвенции (`docs/conventions/layers/application.md`): мапперы лежат в `Application/Mapper/`. Можно создать:

- `ChainDefinitionDtoMapper` — маппит `ChainDefinitionVo` → `ChainDefinitionDto`
- Инжектится в Handler'ы, которые нуждаются в трансформации

Альтернатива: инлайн map-метода прямо в Handler (если маппинг простой и используется в одном месте). Но по конвенции проекта мапперы — отдельные классы.

### ValidateChainConfig: без маппинга DTO→VO

Сейчас `ChainProviderService::validate()` делает костыль — загружает VO заново, потому что DTO не содержит всех полей. Без Service это выглядит естественнее:

```php
class ValidateChainConfigQueryHandler
{
    public function __construct(
        private ChainLoaderInterface $chainLoader,
        private ChainDefinitionValidator $chainValidator,
        private ChainConfigViolationDtoMapper $violationMapper,
    ) {}

    private function validateSpecificChain(string $chainName): ValidateChainConfigResult
    {
        $chainVo = $this->chainLoader->load($chainName);
        $violations = $this->chainValidator->validate($chainVo);

        return new ValidateChainConfigResult(
            isValid: $violations === [],
            violations: $this->violationMapper->mapList($violations),
            validChainName: $chainName,
        );
    }
}
```

## Что останется в Application/Service/

После удаления `ChainProviderService`:

| Сервис | Причина оставания |
|--------|------------------|
| `ExecuteStaticChainService` + Interface | Орхестрирует Domain-сервисы (агент, quality gate, budget) — реальная координация, а не просто маппинг |
| `DispatchRoundEventService` | Реализует Domain-интерфейс `RoundCompletedNotifierInterface` — мост Application↔Symfony EventDispatcher |
| `ResolveExitCodeService` + Interface | Содержит логику приоритизации exit codes (budgetExceeded > timeout > error) — не тривиальный маппинг |

Эти сервисы имеют **поведенческую** логику (координация нескольких Domain-сервисов, приоритизация). `ChainProviderService` — чисто **трансформационный**, без логики.

## Оценка рисков

| Аспект | Оценка |
|--------|--------|
| Сложность изменений | 3/10 — простой рефакторинг, маппинг переносится |
| Риск регрессии | 2/10 — тесты на ChainProviderService переносятся на Handler + Mapper |
| Выигрыш | −2 файла интерфейс + реализация, −1 промежуточный слой, Handler'ы соответствуют конвенции |
| Количество затронутых файлов | ~6 (3 Handler'а, 1 Mapper, 2 удалённых, 1 тест, services.yaml) |

## Вывод

**`ChainProviderService` не нужен.** Это классический случай over-engineering — промежуточный слой, который не добавляет поведения, а лишь перекладывает данные. Query Handler'ы обязаны маппить Domain→Application DTO сами (или через Mapper), это их прямая ответственность по конвенции проекта.

---

## План реализации

1. Создать `ChainDefinitionDtoMapper` в `Application/Mapper/` (метод `map(ChainDefinitionVo): ChainDefinitionDto`)
2. Создать `ChainConfigViolationDtoMapper` (метод `mapList(array): array`)
3. Обновить 3 Query Handler'а: инжектить Domain + Mapper вместо `ChainProviderServiceInterface`
4. Удалить `ChainProviderServiceInterface` и `ChainProviderService`
5. Обновить `services.yaml` (убрать alias)
6. Перенести/адаптировать тесты
