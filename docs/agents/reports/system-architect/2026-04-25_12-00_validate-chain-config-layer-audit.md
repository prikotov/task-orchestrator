# Аудит слоистой архитектуры: ValidateChainConfigService

**Роль:** Архитектор Локи (system_architect_loki)
**Дата:** 2026-04-25
**Объект:** `src/Module/Orchestrator/Application/Service/ValidateChainConfigService.php` и его зависимости
**Задача:** Анализ нарушения слоистой архитектуры — корректность расположения сервиса в Application-слое

---

## Вердикт: 🟡 Нарушение архитектуры ЕСТЬ — сервис частично размещён не на своём уровне

---

## 1. Что анализировалось

| Сущность | Расположение | Слой |
|----------|-------------|------|
| `ValidateChainConfigService` | `Application/Service/` | Application |
| `ValidateChainConfigServiceInterface` | `Application/Service/` | Application |
| `ChainConfigValidationResultDto` | `Application/Dto/` | Application |
| `ChainConfigValidationErrorDto` | `Application/Dto/` | Application |
| `ChainLoaderInterface` | `Domain/Service/Chain/Shared/` | Domain |
| `ChainDefinitionVo` | `Domain/ValueObject/` | Domain |
| `ChainStepVo` | `Domain/ValueObject/` | Domain |
| `OrchestrateCommand` | `apps/console/.../Command/` | Presentation |

Зависимости сервиса:
```
ValidateChainConfigService (Application)
├── ChainLoaderInterface (Domain) ✅ Application → Domain — разрешено
├── ChainDefinitionVo (Domain) ✅ Application → Domain — разрешено
├── ChainStepVo (Domain) ✅ Application → Domain — разрешено
├── ChainConfigValidationResultDto (Application) ✅ Application → Application — разрешено
└── ChainConfigValidationErrorDto (Application) ✅ Application → Application — разрешено
```

## 2. Анализ: нарушает ли размещение в Application правила слоёв?

### 2.1. Направление зависимостей — ✅ Корректно

По конвенциям (`docs/conventions/layers/layers.md`):
- **Application → Domain**: разрешено. Сервис зависит от `ChainLoaderInterface`, `ChainDefinitionVo`, `ChainStepVo` — всё из Domain.
- **Presentation → Application**: разрешено. `OrchestrateCommand` зависит от `ValidateChainConfigServiceInterface` — из Application.

Направление зависимостей не нарушено. Сервис не зависит от Infrastructure, Integration или Presentation.

### 2.2. Назначение сервиса — содержит ли бизнес-логику? — ⚠️ ПРОБЛЕМА

По конвенциям (`docs/conventions/layers/application.md`):

> Application слой не содержит бизнес-логику — он лишь координирует выполнение операций, делегируя бизнес-правила в Domain слой.

И (`docs/conventions/core_patterns/service.md`, Application Service):

> ❗ Запрещено содержать бизнес-логику — только оркестрация.

Анализ методов `ValidateChainConfigService`:

| Метод | Что делает | Это бизнес-логика? |
|-------|-----------|-------------------|
| `validateAll()` | Загружает цепочки через `ChainLoader`, валидирует каждую | Оркестрация + валидация |
| `validateChain()` | Загружает цепочку через `ChainLoader`, валидирует | Оркестрация + валидация |
| `validateStaticChain()` | Проверяет: steps не пустой, шаги корректны, fix_iterations ссылки | **Бизнес-правило** |
| `validateDynamicChain()` | Проверяет: facilitator, participants, max_rounds | **Бизнес-правило** |
| `validateStep()` | Проверяет: agent → role, quality_gate → command + label | **Бизнес-правило** |

**Критическое наблюдение**: приватные методы `validateStaticChain()`, `validateDynamicChain()`, `validateStep()` содержат **валидационные инварианты**, которые:
1. **Дублируют** инварианты из Domain-VO (`ChainDefinitionVo::createFromSteps`, `ChainStepVo::__construct`)
2. При этом проверяют те же самые бизнес-правила, но «со своей стороны» — на уже сконструированных VO

| Проверка | В Domain VO | В Application Service |
|----------|------------|----------------------|
| Static chain: steps не пустой | `ChainDefinitionVo::createFromSteps` → `InvalidArgumentException` | `validateStaticChain` → `ChainConfigValidationErrorDto` |
| Dynamic chain: facilitator не пустой | `ChainDefinitionVo::createFromDynamic` → `InvalidArgumentException` | `validateDynamicChain` → `ChainConfigValidationErrorDto` |
| Dynamic chain: participants не пустой | `ChainDefinitionVo::createFromDynamic` → `InvalidArgumentException` | `validateDynamicChain` → `ChainConfigValidationErrorDto` |
| Agent step: role обязателен | `ChainStepVo::__construct` → `InvalidArgumentException` | `validateStep` → `ChainConfigValidationErrorDto` |
| QualityGate step: command обязателен | `ChainStepVo::__construct` → `InvalidArgumentException` | `validateStep` → `ChainConfigValidationErrorDto` |
| QualityGate step: label обязателен | `ChainStepVo::__construct` → `InvalidArgumentException` | `validateStep` → `ChainConfigValidationErrorDto` |
| fix_iterations: ссылки на существующие шаги | `ChainDefinitionVo::validateFixIterations` | `validateStaticChain` → `ChainConfigValidationErrorDto` |

### 2.3. Проблема «двойной валидации»

Сервис работает с **уже сконструированными** `ChainDefinitionVo` и `ChainStepVo`. Если Domain-конструкторы отработали корректно, все эти инварианты **уже выполнены** — VO не могут существовать в невалидном состоянии (immutable + factory-методы с guard-проверками).

Application-сервис:
1. Загружает VO через `ChainLoader` (который вызывает factory-методы Domain → валидация уже прошла)
2. Проверяет те же инварианты **поверх** уже валидных VO
3. Формирует «дружелюбные» ошибки вместо `InvalidArgumentException`

Это означает, что приватные методы-валидаторы — **мёртвый код** при нормальном flow. Они сработают только если `ChainLoader` нарушит контракт Domain и вернёт «битый» VO.

## 3. Какой слой правильный?

Есть **два ортогональных аспекта** в этом сервисе, которые нужно разделить:

### Аспект A: Валидация как Query (читает данные, не меняет состояние)

Сервис вызывается из Presentation (`OrchestrateCommand`) для pre-flight проверки конфигурации. Это **read-only операция** — по сути, Query. Размещение в Application как Application Service — **корректно** для координационной части.

### Аспект B: Содержимое приватных методов (бизнес-инварианты)

Приватные валидационные методы дублируют Domain-логику. По конвенциям:

> Application слой не содержит бизнес-логику — он лишь координирует выполнение операций, делегируя бизнес-правила в Domain слой.

**Это и есть нарушение**: бизнес-инварианты находятся в Application вместо Domain.

## 4. Рекомендация

### Вариант 1 (минимальный, прагматичный): признать сервис Application-корректным, но убрать дублирование

**Обоснование**: Сервис является Application-Query, который:
- Оркестирует загрузку (через Domain `ChainLoaderInterface`)
- Трансформирует Domain-исключения в Application-DTO с дружелюбными ошибками
- Вызывается из Presentation

Само по себе размещение сервиса в Application — **правильное**. Проблема не в слое, а в **дублировании бизнес-инвариантов**.

**Действия:**
1. Убрать приватные методы-валидаторы (`validateStaticChain`, `validateDynamicChain`, `validateStep`)
2. Заменить их на вызовы Domain-VO: попытаться «пересоздать» VO из тех же данных — Domain сам кинет `InvalidArgumentException` при невалидных данных
3. Либо — ещё проще — обернуть `$this->chainLoader->load()` / `$this->chainLoader->list()` в try-catch и преобразовывать `InvalidArgumentException` из Domain в `ChainConfigValidationErrorDto`

Но это не сработает, если VO уже сконструированы без ошибок — тогда catch не поймает ничего. Что, собственно, и происходит.

### Вариант 2 (архитектурно чистый): переместить валидационные инварианты в Domain Specification

**Обоснование**: Если нужна «мягкая» валидация (собрать все ошибки, а не падать на первой), Domain должен предоставить для этого механизм.

**Действия:**
1. Создать Domain Specification: `ChainDefinitionSpecification` в `Domain/Specification/`
   - Метод `validate(ChainDefinitionVo): list<ChainConfigViolationVo>` — собирает все нарушения без исключений
   - Бизнес-инварианты (facilitator, participants, steps, fix_iterations) определяются **один раз** в Domain
2. В `ChainDefinitionVo` и `ChainStepVo` текущие guard-проверки оставить как fail-fast при конструировании
3. Specification даёт «небросающую» альтернативу для мягкой валидации
4. `ValidateChainConfigService` (Application) вызывает Specification и маппит `ChainConfigViolationVo` → `ChainConfigValidationErrorDto`

```
Presentation → Application Service → Domain Specification → Domain VO
```

Это полностью соответствует конвенциям:
- Application не содержит бизнес-логику — делегирует в Domain Specification ✅
- Domain владеет инвариантами ✅
- Нет дублирования ✅

### Вариант 3 (радикальный): сервис → Infrastructure

**Не рекомендую.** Сервис не работает с внешними системами (файлы, БД, API). Он оркестирует Domain-операции и возвращает Application-DTO. Infrastructure-слой по конвенциям реализует интерфейсы Domain, а не координирует валидацию для Presentation.

## 5. Итоговая оценка

| Критерий | Оценка | Комментарий |
|----------|--------|-------------|
| Направление зависимостей | ✅ Корректно | Application → Domain, Presentation → Application |
| Отсутствие бизнес-логики в Application | ⚠️ Нарушение | Приватные методы дублируют Domain-инварианты |
| Дублирование Domain-проверок | 🔴 Проблема | 7 из 7 проверок дублируют Domain VO guard-клаuses |
| Размещение интерфейса | ✅ Корректно | Interface + Implementation в Application — допустимо для Application Service |
| Целевой слой | ✅ Application — корректно | Это Query/оркестратор для Presentation, не Infrastructure |

## 6. Резюме

**Сервис размещён в правильном слое (Application), но нарушает правило «Application не содержит бизнес-логику».** 

Корень проблемы — не в выборе слоя, а в **дублировании Domain-инвариантов** внутри Application-методов. При этом дублирование фактически бесполезно: VO уже прошли валидацию при конструировании в `ChainLoader`, и приватные методы-валидаторы никогда не найдут ошибку в нормальном flow.

**Рекомендация**: Вариант 2 — создать `ChainDefinitionSpecification` в Domain, который реализует «мягкую» валидацию (сбор всех ошибок без исключений). ValidateChainConfigService в Application делегирует ему проверку и маппит результат в DTO. Это устранит дублирование и полностью соответствует конвенциям.

---

## Приложения

### A. Ссылки на конвенции

- [`docs/conventions/layers/layers.md`](docs/conventions/layers/layers.md) — матрица зависимостей между слоями
- [`docs/conventions/layers/application.md`](docs/conventions/layers/application.md) — правила Application-слоя
- [`docs/conventions/core_patterns/service.md`](docs/conventions/core_patterns/service.md) — классификация сервисов (Domain / Application / Infrastructure)
- [`docs/conventions/layers/domain.md`](docs/conventions/layers/domain.md) — Domain Specification

### B. Связанная техдолг-задача

В `OrchestrateCommand.php` уже отмечен `@techdebt`:

```
@techdebt 2026-04-24: Command зависит от Domain\ChainLoaderInterface и Domain\ChainDefinitionVo.
Нужно вынести загрузку chain в Application-слой.
Задача: todo/TASK-chore-presentation-domain-decouple.todo.md
```

Данная проблема — часть того же техдолга: Presentation зависит от Domain напрямую (`ChainLoaderInterface`, `ChainDefinitionVo`), хотя валидация уже идёт через Application. Рекомендуется решить обе проблемы совместно.
