# Критический анализ отчёта Гэндальфа: Deptrac-violations

**Роль:** Архитектор Локи
**Дата:** 2026-05-06
**Объект:** Критический разбор отчёта Архитектора Гэндальфа «Анализ Deptrac-violations: классификация и рекомендации» от 2026-05-06
**Задача:** TASK-refactor-deptrac-decomposition-rules — независимая экспертная оценка

---

## Оценка рефлексии

- 🧩 сложность запроса: **6 из 10** — анализ 15 violations с кросс-модульными зависимостями, двумя кастомными Deptrac-правилами и DDD-архитектурой
- 🗂️ уровень контекста: **9 из 10** — полный доступ к исходникам, отчёту Гэндальфа, конвенциям и Deptrac-конфигурации
- 🛡️️ риск ошибки: **3 из 10** — анализ без изменения кода, риск ограничен неверными рекомендациями

---

## Резюме вердикта

**Гэндальф проделал добротную работу: классификация 8A/7B в целом верна, рекомендации по рефакторингу группы A — рабочие. Но есть слепые зоны, одно ошибочное предложение и недостаточно оценённый архитектурный риск.**

| Аспект | Оценка | Комментарий |
|--------|--------|-------------|
| Классификация A vs B | ✅ В основном верна | С оговорками по группе 5 |
| Предложенные исключения правила | ⚠️ Частично некорректны | `Service\Integration\` — не нужно |
| Анализ архитектурных рисков | ❌ Недостаточен | См. слепую зону #3 |
| Альтернативные решения | ⚠️ Частично рассмотрены | Упущен вариант для группы 5 |
| Риск лазеек в правилах | ❌ Не оценён | Критический пробел |

---

## 1. Слепые зоны — что Гэндальф упустил

### 1.1. Спекулятивное исключение `Domain\Service\Integration\`

Гэндальф предлагает добавить два исключения в `CrossModuleDomainRule`:

```
Integration → foreign Domain\Contract\     ← обоснованно
Integration → foreign Domain\Service\Integration\  ← НЕ обоснованно
```

Я проверил все 15 violations: **ни одно не зависит от чужого `Domain\Service\Integration\`**. Нарушения #4, #5 зависят от `Domain\Contract\Chain\ChainLoaderInterface`. Интерфейсы `Domain\Service\Integration\` (например, `ChainDefinitionProviderInterface`) находятся в **собственном** модуле depender'а, а не в foreign-модуле.

**Вердикт:** Второе исключение — phantom-fix. Добавлять его без текущих violations — создавать лазейку на будущее без обоснования.

### 1.2. `Contract\` — это конвенция именования, а не архитектурная гарантия

Оба предложенных исключения Гэндальфа проверяют `str_starts_with($dependent['path'], 'Contract\\')`. Это работает только потому, что интерфейсы сейчас лежат в `Domain\Contract\`. Но:

- Название каталога `Contract\` — это **конвенция**, не enforce-механизм. Разработчик может положить любой класс в `Domain\Contract\` и обойти правило.
- Более надёжная проверка: убедиться, что dependent является **интерфейсом** (`interface_exists()` или reflection), а не просто классом в каталоге `Contract\`.

**Вердикт:** Проверка по имени каталога приемлема как first step, но нужно добавить guard: `Contract\`-исключение применяется **только к интерфейсам** (не классам, не трейтам, не enum). Иначе `Contract\` станет dumping ground для любого кода, которому нужно пробить границу модулей.

### 1.3. Архитектурная проблема «общих портов» — не проанализирована

`ChainExecution\Domain\Contract\` содержит интерфейсы, которые реализуются в **двух разных модулях**:
- `AuditLoggerInterface` → реализован и в ChainExecution.Infrastructure, и в DynamicLoop.Infrastructure
- `RunAgentServiceInterface` → реализован и в ChainExecution.Integration, и в DynamicLoop.Infrastructure
- `PromptProviderInterface` → реализован и в ChainExecution.Infrastructure, и в DynamicLoop.Infrastructure

Это означает, что `ChainExecution.Domain` является de facto **Shared Kernel** — его контракты обслуживают несколько Bounded Context'ов. Гэндальф упоминает это («Вариант B2 — общий shared-модуль»), но **отмахивается** от него как от «избыточного».

Между тем, это центральный архитектурный вопрос:
- Если ChainExecution.Domain.Contract — Shared Kernel, то нужно это **явно** задокументировать и добавить соответствующие правила
- Если нет — порты должны быть определены в **потребляющем** модуле (DynamicLoop.Domain), а не в предоставляющем

Игнорирование этого вопроса означает, что каждое добавление нового модуля, реализующего ChainExecution-контракты, будет порождать новые violations и новые ad-hoc исключения.

### 1.4. Временное исключение `@todo` в `CrossModuleDomainRule` — проигнорировано

В правиле уже есть `@todo`-исключение:

```php
// TODO: temporary — Command handlers may use foreign Domain\Repository and Domain\Entity
if (
    $depender['layer'] === 'Application' && $dependent['layer'] === 'Domain'
    && str_starts_with($depender['path'], 'UseCase\\Command\\')
    && (str_starts_with($dependent['path'], 'Repository\\') || str_starts_with($dependent['path'], 'Entity\\'))
) {
    return;
}
```

Это legacy-долг, который создаёт прецедент: «если правило мешает — добавим исключение». Гэндальф не упомянул это в контексте оценки — хотя именно это исключение демонстрирует риск slide: сегодня `Contract\`, завтра `Service\`, послезавтра `Entity\`.

**Вердикт:** План корректировки правил должен включать roadmap удаления существующего `@todo`-исключения.

### 1.5. Группа 5 — гетерогенна, но Гэндальф трактует её как однородную

Violations #12, #13, #14 — разные паттерны:

| Violation | Класс | Паттерн | Dependent |
|-----------|-------|---------|-----------|
| #12 | `JsonlAuditLogger` | **Реализует** `AuditLoggerInterface` (implements) | Interface from foreign Domain.Contract |
| #13 | `RunDynamicLoopAgentService` | **Использует** `RunAgentServiceInterface` (constructor injection) | Interface from foreign Domain.Contract |
| #14 | `RunDynamicLoopAgentService` | **Использует** `PromptProviderInterface` (constructor injection) | Interface from foreign Domain.Contract |

- #12 — классический Port/Adapter: один Adapter реализует Port из другого модуля
- #13, #14 — Infrastructure-сервис **вызывает** чужой Domain.Contract через DI, при этом сам реализует интерфейс из **своего** модуля (`RunDynamicLoopAgentServiceInterface` из DynamicLoop.Domain)

Гэндальф предлагает одно правило «Infrastructure → foreign Domain.Contract разрешено», но это покрывает и реализацию, и использование. Использование foreign Domain.Contract из Infrastructure — это не Port/Adapter, это **косвенная зависимость от другого модуля через DI**.

### 1.6. ServiceContractDependencyRule и violation #1 — упущена двойственность

Violation #1 (`OrchestrateCommand → ResolveExitCodeServiceInterface`) ловится **ServiceContractDependencyRule**, а не CrossModuleDomainRule (OrchestrateCommand находится вне `Common\Module\...` namespace). Гэндальф это понимает, но не акцентирует внимание на том, что это **разные правила** с **разными механизмами**:

- `CrossModuleDomainRule` работает только внутри `Common\Module\...`
- `ServiceContractDependencyRule` также ловит external-зависимости (классы вне модульной структуры)

Это значит, что корректировка только `CrossModuleDomainRule` **не закроет** аналогичные проблемы для Presentation-слоя. Гэндальф классифицирует #1 как A (рефакторинг), что правильно, но не подсвечивает системный риск: Presentation может и дальше инжектировать любые модульные сервисы, и только ServiceContractDependencyRule это ловит.

---

## 2. Согласие / несогласие с классификацией

### Группа 1 (#1) — A: Presentation → чужой Service ✅

Полностью согласен. `OrchestrateCommand` инжектирует `ResolveExitCodeServiceInterface` из ChainExecution.Application — это нарушениеPresentation → чужой Application. Предложение Гэндальфа встроить resolve-логику в результат — отличное: `OrchestrateChainResultDto` уже содержит всё необходимое.

**Дополнение:** `ResolveExitCodeServiceInterface` в ChainExecution.Application.Service — это сервис, который Presentation не должен видеть. После рефакторинга этот интерфейс, вероятно, можно упразднить.

### Группа 2 (#2, #3) — A: ChainExecution.Application → AgentRunner.Application ✅

Полностью согласен. `GetRunnersQueryHandler` — это прокси, делегирующий в AgentRunner. Он должен быть в ChainExecution.Integration. Предложенный Гэндальфом перенос — правильный.

### Группа 3 (#4, #5) — B: Integration → foreign Domain.Contract ✅ (с оговоркой)

Согласен, что это категория B — правило слишком строгое. `layers.md` явно разрешает Integration → Domain (контракты и типы). Но:

1. Исключение должно быть **только** `Domain\Contract\` (без `Service\Integration\`)
2. Нужно добавить guard: `Contract\`-исключение — **только для интерфейсов**

### Группа 4 (#6–#11) — A: DynamicLoop.Application → ChainExecution.Application ✅

Полностью согласен. Перенос в DynamicLoop.Integration — правильное решение для всех 7 violations.

**Но есть нюанс:** после переноса `DynamicExecutionStrategy` в Integration, он будет реализовывать `ExecutionStrategyInterface` из ChainExecution.Application.Contract. Это `Integration → foreign Application`, что разрешено `CrossModuleDomainRule`. Однако `ServiceContractDependencyRule` проверяет: может ли Integration реализовывать Application-интерфейс? Таблица в правиле разрешает Integration реализовывать только `Domain` и `Integration` слои.

**Но:** `isService()` проверяет `str_starts_with($path, 'Service\\')`. `ExecutionStrategyInterface` находится в `Application\Contract\Chain\`, path = `Contract\Chain\ExecutionStrategyInterface` — НЕ начинается с `Service\`. Значит `ServiceContractDependencyRule` его не ловит. ✅

### Группа 5 (#12–#14) — Частично B, частично A ⚠️

**#12 (JsonlAuditLogger):** Согласен, что это B — Port/Adapter. Один Adapter реализует два Port'а (из ChainExecution.Domain и DynamicLoop.Domain). Исключение для `Infrastructure → foreign Domain.Contract` (implements) обоснованно.

**#13, #14 (RunDynamicLoopAgentService):** Здесь я **не согласен** с чистой категоризацией B. Этот класс:
- Реализует `RunDynamicLoopAgentServiceInterface` из **своего** модуля — OK
- **Использует** `RunAgentServiceInterface` и `PromptProviderInterface` из **чужого** ChainExecution.Domain.Contract — это межмодульная зависимость через DI

**Альтернатива Гэндальфу:** Перенести `RunDynamicLoopAgentService` в `DynamicLoop\Integration\Service\`. Тогда:
- Integration → foreign Domain.Contract (использование) — нужно новое правило или… Integration уже имеет доступ к Domain через `layers.md`
- Фактически, `RunDynamicLoopAgentService` — это Integration-адаптер: маппит VO, делегирует в другой модуль. Ему логичнее быть в Integration.

Это устранило бы violations #13, #14 через рефакторинг (категория A), а не через ослабление правила (категория B).

---

## 3. Альтернативные решения

### Альтернатива A: Перенести ВСЕ нарушения группы 5 в Integration

| Класс | Текущий слой | Предлагаемый слой |
|-------|-------------|-------------------|
| `JsonlAuditLogger` | DynamicLoop.Infrastructure | DynamicLoop.Infrastructure (оставить) |
| `RunDynamicLoopAgentService` | DynamicLoop.Infrastructure | DynamicLoop.Integration |

Тогда:
- `JsonlAuditLogger` (Port/Adapter, implements foreign Domain.Contract) → нужно **одно** исключение: `Infrastructure → foreign Domain.Contract (implements only)`
- `RunDynamicLoopAgentService` (Integration adapter) → Integration → foreign Domain — нужно исключение `Integration → foreign Domain.Contract`

Итого: **два точечных исключения** вместо трёх широких.

### Альтернатива B: Определить Integration-порты в потребляющем модуле

Вместо `RunAgentServiceInterface` в ChainExecution.Domain.Contract, определить `AgentRunnerPortInterface` в DynamicLoop.Domain.Service.Integration. Integration-реализация DynamicLoop делегирует в ChainExecution.

Это устраняет проблему «общих портов» и делает каждый модуль self-contained. Но требует рефакторинга существующих контрактов.

### Альтернатива C: Явный Shared Kernel

Признать, что `ChainExecution.Domain.Contract` (частично) является Shared Kernel. Вынести общие интерфейсы (`AuditLoggerInterface`, `RunAgentServiceInterface`, `PromptProviderInterface`) в отдельный namespace `Common\Domain\Shared\Contract\`. Deptrac-правило: все модули могут зависеть от Shared Kernel.

Это решает проблему архитектурно, но создаёт новый слой абстракции.

---

## 4. Оценка риска лазеек

### Предложение Гэндальфа: `Integration → foreign Domain\Contract\` + `Domain\Service\Integration\`

**Риск: СРЕДНИЙ**

- `Domain\Contract\` — осмысленная конвенция, но без guard на interface-only можно положить туда любой класс
- `Domain\Service\Integration\` — нет текущих violations, pure speculation. Если в будущем кто-то решит, что Integration может обращаться к любой Service.Integration любого модуля — это открывает дверь к произвольным cross-module зависимостям

### Моя рекомендация: `Contract\` + guard

```php
// Integration → foreign Domain.Contract (interfaces only)
if (
    $depender['layer'] === 'Integration' && $dependent['layer'] === 'Domain'
    && str_starts_with($dependent['path'], 'Contract\\')
) {
    // Guard: только интерфейсы, не классы/trait/enum
    if (interface_exists($event->dependentReference->getToken()->toString())) {
        return;
    }
}

// Infrastructure → foreign Domain.Contract (implements only)
if (
    $depender['layer'] === 'Infrastructure' && $dependent['layer'] === 'Domain'
    && str_starts_with($dependent['path'], 'Contract\\')
    && $event->dependency->getContext()->dependencyType === DependencyType::INHERIT
    && interface_exists($event->dependentReference->getToken()->toString())
) {
    return;
}
```

Отличия от предложения Гэндальфа:
1. **Нет** `Service\Integration\` — не нужно
2. **interface_exists()** guard — нельзя обойти, положив класс в `Contract\`
3. **DependencyType::INHERIT** для Infrastructure — разрешено только implements (реализация Port), а не использование (constructor injection)

---

## 5. Итоговое заключение и рекомендация

### Вердикт по рекомендациям Гэндальфа

| # | Рекомендация Гэндальфа | Мой вердикт | Комментарий |
|---|------------------------|-------------|-------------|
| 1 | Добавить `Domain\Contract\` + `Domain\Service\Integration\` в CrossModuleDomainRule | ⚠️ Частично верно | `Domain\Contract\` — да, `Service\Integration\` — нет |
| 2 | Рефакторинг 8 violations (категория A) | ✅ Согласен | Все предложения рабочие |
| 3 | Правка через PR в coding-standard | ✅ Согласен | Правильный подход |
| 4 | `skip_violations` как временная мера | ⚠️ Осторожно | Только если есть конкретный план удаления |

### Рекомендуемый план действий

**Шаг 1: Корректировка CrossModuleDomainRule** (coding-standard PR)

Добавить ДВА точечных исключения с guards:

1. `Integration → foreign Domain\Contract\` (interface only) — закрывает #4, #5
2. `Infrastructure → foreign Domain\Contract\` (implements only, DependencyType::INHERIT + interface guard) — закрывает #12

**НЕ добавлять:** `Domain\Service\Integration\` — нет текущих violations, создаёт лазейку.

**Шаг 2: Рефакторинг кода** (task-PR)

| Violation | Действие | Сложность |
|-----------|----------|-----------|
| #1 | Упразднить `ResolveExitCodeServiceInterface`, встроить в `OrchestrateChainResultDto` | 3/10 |
| #2, #3 | Перенести `GetRunnersQueryHandler` в `ChainExecution.Integration` | 4/10 |
| #6, #7 | Перенести `Dispatch*EventService` в `DynamicLoop.Integration` | 3/10 |
| #8–#11 | Перенести `DynamicExecutionStrategy` в `DynamicLoop.Integration` | 5/10 |
| #13, #14 | Перенести `RunDynamicLoopAgentService` в `DynamicLoop.Integration` | 4/10 |

Итого рефакторинг: **10 violations** (8 по Гэндальфу + #13, #14)

**Шаг 3: Roadmap для `@todo`-исключения**

Запланировать удаление временного исключения `Application\UseCase\Command\ → foreign Domain\Repository\Entity` из `CrossModuleDomainRule`. Это существующий техдолг, который снижает ценность правила.

**Шаг 4: Архитектурный вопрос (отдельная задача)**

Документировать статус `ChainExecution.Domain.Contract` — является ли он Shared Kernel? Если да — внести в конвенции и Deptrac-правила явно. Если нет — спланировать миграцию общих контрактов.

---

## Ссылки на источники 📚

1. `docs/agents/reports/system-architect/2026-05-06_10-00_deptrac-violations-analysis.md` — отчёт Гэндальфа
2. `vendor/prikotov/coding-standard/src/Deptrac/CrossModuleDomainRule.php` — исходник правила
3. `vendor/prikotov/coding-standard/src/Deptrac/ServiceContractDependencyRule.php` — исходник правила
4. `docs/conventions/layers/layers.md` — конвенции по слоям
5. `vendor/prikotov/coding-standard/config/deptrac/depfile.yaml` — базовая Deptrac-конфигурация
