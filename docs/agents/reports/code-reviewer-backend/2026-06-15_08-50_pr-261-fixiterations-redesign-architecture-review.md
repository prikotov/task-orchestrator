# Архитектурное ревью PR #261: редизайн проверки инварианта fix-итераций (коммиты 1a70825 + 4855c02)

**Роль:** Ревьювер Бэка Пуаро
**Дата:** 2026-06-15
**Объект:** PR #261 (`refactor/phpmd-baseline-elimination` → `main`), ТОЛЬКО новые коммиты `1a70825` (docs) + `4855c02` (refactor). Скоуп: редизайн проверки инварианта `fix_iterations`.
**Задача:** Архитектурная корректность реализации vs дизайн Архитектора Локи (`docs/agents/reports/system-architect/2026-06-15_00-10_fixiterations-validation-redesign.md`) + конвенции проекта.

---

## Рефлексия (классификация запроса)

- 🧩 **сложность запроса: 6/10** — скоуп чётко ограничен двумя коммитами и одним инвариантом, но требует верификации поведенческой эквивалентности алгоритма, проверки конвенций (specification/factory/VO/helper) и Deptrac-правил.
- 🗂️ **уровень контекста: 9/10** — предоставлены: дизайн Локи, чек-лист по пунктам, принятые архитектурные решения, список файлов.
- 🛡️ **риск ошибки: 3/10** — изменения изолированы, есть детальные тесты, Deptrac/Psalm/PHPUnit зелёные.

**Вердикт классификации:** запрос НЕ проблемный. Ревью выполнено напрямую.

**Допущения:**
- Поведенческая эквивалентность оценивается по решению TRUE/FALSE инварианта, а не по тексту исключений (текст намеренно изменён: detailed → generic — это принятый дизайн).
- «Production-код» = `src/` + `apps/`. Тесты (`tests/`) используют deprecated static factories и reflection — это допустимо для BC.

---

## Методология

1. Прочитан дизайн Локи и конвенции: `specification.md`, `factory.md`, `value-object.md`, `helper.md`.
2. Сравнён старый inline-алгоритм (`git show 1a70825^:...`) с новой спецификацией — на эквивалентность TRUE/FALSE решения.
3. Проверены Deptrac-правила (`vendor/prikotov/coding-standard/config/deptrac/depfile.yaml`) на зависимости `DomainVo`/`Domain`/`DomainSpecification`.
4. Запущены: `phpunit` (полный набор), `psalm` (изменённые файлы + полный), `deptrac`.
5. Проверены миграции call-сайтов и отсутствие висячих ссылок на удалённый helper.

---

## Результаты запуска проверок

| Проверка | Команда | Результат |
|---|---|---|
| PHPUnit (полный) | `vendor/bin/phpunit` | ✅ OK, 981 тест, 2770 assertions |
| Psalm (изменённые файлы) | `vendor/bin/psalm <files>` | ✅ No errors found |
| Psalm (полный) | `vendor/bin/psalm` | ✅ 0 errors (174 info — предсуществующие) |
| Deptrac | `vendor/bin/deptrac analyse` | ✅ 0 violations, 0 warnings |
| PHPCS sniff-tests | `php .../run-sniff-tests.php` | ⚠️ Environment error: отсутствует `vendor/prikotov/coding-standard/vendor/autoload.php` — **предсуществующая** проблема окружения, не связана с изменениями. Не блокирует ревью. |

---

## Чек-лист ревью (A1–H5)

### A. Спецификация `FixIterationsReferenceIntegritySpecification`
*(конвенция: `docs/conventions/layers/domain/specification.md`)*

| Пункт | Статус | Цитата / комментарий |
|---|---|---|
| **A1.** `isSatisfiedBy` возвращает ТОЛЬКО bool, без throw | ✅ PASS | `public function isSatisfiedBy(array $steps, array $fixIterations): bool` — тело содержит только `return true/false`, ни одного `throw`. |
| **A2.** Stateless, без состояния и зависимостей | ✅ PASS | `final readonly class` без конструктора и свойств. |
| **A3.** Не делает I/O, не логирует | ✅ PASS | Только array-операции над domain values. |
| **A4.** Алгоритм корректен и эквивалентен прежнему | ✅ PASS | Сравнён со старым inline (`StaticChainDefinitionVo`) и `ChainFixIterationsValidatorHelper`. Логика идентична: `empty → true`; `nameMap` из non-null `getName()`; unknown step → `false`; duplicate across groups → `false`. Тест `unknownStepReportedBeforeDuplicateCheck` фиксирует порядок проверок. |
| **A5.** Работает только с domain values | ✅ PASS | Параметры: `list<ChainStepVo>`, `list<FixIterationGroupVo>`. Никаких DTO/Infrastructure. |

### B. Фабрика `ChainDefinitionFactory`
*(конвенция: `docs/conventions/core-patterns/factory.md`)*

| Пункт | Статус | Цитата / комментарий |
|---|---|---|
| **B1.** DI внедряет спецификацию (readonly) | ✅ PASS | `public function __construct(private FixIterationsReferenceIntegritySpecification $fixIterationsReferenceIntegritySpecification)` в `final readonly class`. |
| **B2.** Кидает исключение; сообщение GENERIC | ✅ PASS | `throw new InvalidArgumentException(sprintf('Chain "%s": fix_iterations must reference existing named steps and each step name must belong to at most one fix_iteration group.', $name))` — без имени группы/шага. |
| **B3.** НЕ дублирует детальную диагностику | ✅ PASS | Метод `assertStepBasedInvariant` вызывает spec и кидает generic. Никакого сбора `group`/`stepName`. |
| **B4.** Возвращает domain VO | ✅ PASS | `: StaticChainDefinitionVo`, `: ConditionalChainDefinitionVo`, `: DynamicChainDefinitionVo`. |
| **B5.** Сигнатуры совпадают; non-fix guards сохранены | ✅ PASS | Сигнатуры `createFromSteps/createFromConditionalSteps/createFromDynamic` поэлементно совпадают с прежними static factories. Guard `count($steps) === 0 → 'Chain "%s" must have at least one step'` сохранён; dynamic guards (facilitator/participants) сохранены. |
| **B6.** Не делает I/O, не ходит во внешние сервисы | ✅ PASS | Только создание VO + вызов spec. |

### C. VO конструкторы
*(конвенция: `docs/conventions/core-patterns/value-object.md`)*

| Пункт | Статус | Цитата / комментарий |
|---|---|---|
| **C1.** `@internal` со ссылкой на фабрику | ✅ PASS | Все 3 VO: `@internal Используйте {@see ...ChainDefinitionFactory::createFrom*()}`. |
| **C2.** inline-алгоритм fix-итераций удалён | ✅ PASS | В diff `StaticChainDefinitionVo` и `ConditionalChainDefinitionVo` блок `nameMap`/`allGroupStepNames` (~30 NLOC) удалён. `DynamicChainDefinitionVo` никогда не содержал алгоритма. |
| **C3.** Остались immutable readonly | ✅ PASS | Все 3 класса `final readonly`, private-свойства, без сеттеров. |
| **C4.** Не зависят от spec/factory/сервисов (Deptrac) | ✅ PASS | Deptrac: 0 violations. `DomainVo` ruleset разрешает только `Domain`/`DomainEnum`/`DomainVo` — спецификация/фабрика в import не фигурируют. |

### D. deprecated `ChainDefinitionVo`

| Пункт | Статус | Цитата / комментарий |
|---|---|---|
| **D1.** Helper import убран | ✅ PASS | Diff: удалён `use ...Domain\Helper\ChainFixIterationsValidatorHelper;`. |
| **D2.** Объясняющий комментарий про Deptrac | ✅ PASS | Комментарий на месте вызова: «DomainVo не может зависеть от DomainSpecification (правило Deptrac), поэтому здесь нет ни алгоритма проверки, ни выброса исключения». Проверено в `depfile.yaml`: ruleset `DomainVo` действительно НЕ содержит `DomainSpecification`. |
| **D3.** Не выше 500 NLOC | ✅ PASS | `wc -l`: 523 raw, **483 nonblank** — под порогом 500. Чистый рост +2 nonblank строки (−3 кода +5 комментария) — в бюджете «3–5 NLOC». |
| **D4.** Не добавлена новая подробная логика | ✅ PASS | Изменения: только удаление helper-call + explanatory comment. |

> ⚠️ **RISK (не блокирующий, см. «Слепые зоны» №1):** в deprecated `ChainDefinitionVo` удалена проверка fix-итераций **целиком**, а не только detailed-сообщения. Класс теперь молча принимает невалидные fix-итерации. Дизайн в разделе 0 резюмировал «общий алгоритм держать только в specification» (этому соответствует), но в разделе 4.3 давал fallback «перенести generic guard в private constructor». Реализация выбрала вариант без guard вовсе. Риск низкий (нет production-вызовов — см. E3), но это **поведенческое ослабление deprecated-класса**, которое стоит зафиксировать осознанно.

### E. Миграция call sites

| Пункт | Статус | Цитата / комментарий |
|---|---|---|
| **E1.** Все 4 статических вызова заменены | ✅ PASS | Diff `YamlChainLoaderService`: строки ~152, 164, 196 (static/conditional) и ~295 (dynamic) — все `$this->chainDefinitionFactory->createFrom*()`. |
| **E2.** DI-зависимость добавлена в конструктор | ✅ PASS | `public function __construct(string $yamlPath, private readonly ChainDefinitionFactory $chainDefinitionFactory)`. |
| **E3.** Нет прямых вызовов static factories в production | ✅ PASS | `grep '::createFromSteps\|::createFromConditionalSteps\|::createFromDynamic' src/ apps/` — только PHPDoc-ссылки (`@deprecated`/`@internal`). Внешних production-вызовов deprecated `ChainDefinitionVo::create*` тоже нет (только `self::` внутри класса). |

### F. Удаление helper + phpmd.xml

| Пункт | Статус | Цитата / комментарий |
|---|---|---|
| **F1.** Файл helper удалён | ✅ PASS | `ls`: файла нет; директория `Domain/Helper/` пуста. |
| **F2.** FQCN убран из phpmd.xml | ✅ PASS | Diff: удалена строка `<property name="exceptions" value="...ChainFixIterationsValidatorHelper"/>`. Свойство `ignorepattern` сохранено — нет «висячего» пустого `exceptions`. |
| **F3.** Нет ссылок на удалённый helper | ✅ PASS | `grep -rn ChainFixIterationsValidatorHelper src/ tests/` — пусто. В `ChainDefinitionVoTest` убраны `use` и `#[CoversClass(...)]`. |

### G. Тесты

| Пункт | Статус | Цитата / комментарий |
|---|---|---|
| **G1.** SpecificationTest | ✅ PASS | Покрыты: `emptyFixIterationsIsSatisfied` (→true), `validReferencesAreSatisfied` (→true), `multipleDistinctGroupsAreSatisfied` (→true), `unknownStepIsNotSatisfied` (→false), `duplicateStepAcrossGroupsIsNotSatisfied` (→false). Доп.: `unnamedStepIsNotConsideredAValidReference` (null-name исключается из nameMap), `unknownStepReportedBeforeDuplicateCheck`. |
| **G2.** FactoryTest | ✅ PASS | 3 типа цепочек создаются; `createFromStepsThrowsOnEmptySteps` / `createFromConditionalStepsThrowsOnEmptySteps` (сообщение 'must have at least one step' сохранено); `...ThrowsGenericOnUnknownFixIterationStep`, `...ThrowsGenericOnDuplicateFixIterationStep` (generic-сообщение); dynamic guards. Доп.: propagation опциональных полей (budget/retry/timeout). |
| **G3.** ValidatorTest: 'references unknown step' сохранён | ✅ PASS | `tests/Unit/Domain/Service/Chain/ChainDefinitionValidatorTest.php:154`: `assertStringContainsString('references unknown step', ...)`. Тест строит VO через reflection (обход factory guard) — корректно. |
| **G4.** Покрытие адекватно | ✅ PASS | Все ветви spec/factory покрыты; happy + exception + опциональные поля. |

### H. Конвенции и архитектура

| Пункт | Статус | Цитата / комментарий |
|---|---|---|
| **H1.** Нет терминов Port/Adapter | ✅ PASS | `grep -rin 'Port\|Adapter'` по новым/изменённым файлам — пусто. |
| **H2.** Слои корректны | ✅ PASS | Spec в `Domain/Specification/Chain/`, Factory в `Domain/Factory/`. Соответствует конвенциям расположения. |
| **H3.** Нет статических синглтонов / глобального состояния | ✅ PASS | Новые классы — экземпляры через DI; static factories помечены `@deprecated`. |
| **H4.** Строгая типизация | ✅ PASS | Все новые/изменённые файлы: `declare(strict_types=1)`, return types, param types, typed properties. |
| **H5.** PHPDoc на публичных методах | ✅ PASS | `isSatisfiedBy`, все 3 `createFrom*`, приватные методы — с PHPDoc + `@param`/`@throws`/`@return`. |

---

## Слепые зоны (не описанные в дизайне или требующие внимания)

### 🔵 Слепая зона №1 — deprecated `ChainDefinitionVo`: полная потеря валидации (RISK, не блокирует)

Реализация удалила валидацию fix-итераций в deprecated классе **полностью** (не только detailed-сообщения). Дизайн давал два варианта: (а) spec inline — заблокирован Deptrac (`DomainVo` ↛ `DomainSpecification`); (б) constructor guard с generic-сообщением. Реализация выбрала **третий путь**: никакой проверки, только объясняющий комментарий.

**Следствие:** `ChainDefinitionVo::createLinearChain(...)` с невалидными fix-итерациями теперь создаёт VO **молча** (раньше кидал `InvalidArgumentException`).

**Оценка риска:** НИЗКИЙ. Внешних production-вызовов deprecated-класса нет (E3). Все боевые call-сайты мигрированы на фабрику. Однако:
- Это **поведенческое изменение** deprecated API (BC-break по поведению, не по сигнатуре).
- Дизайн явно указывал constructor-guard как допустимый fallback; он не реализован.
- Если в будущем кто-то вызовет deprecated API (например, из не-мигрированного кода), инвариант будет нарушен без исключения.

**Рекомендация:** Зафиксировать решение осознанно. Варианты (по убыванию предпочтения):
1. Принять как есть (deprecated класс на удалении, комментарий есть) — допустимо.
2. Добавить minimal generic guard в private-конструктор `ChainDefinitionVo::__construct()` (без spec, с inline bool-проверкой ~8 NLOC) — строго соответствует fallback из дизайна. Замерить NLOC: класс 483 nonblank, запас до 500 ≈ 17 — вписывается.

> Это единственный пункт, где реализация расходится с дизайном. Он некритичен, но требует явного решения заказчика/архитектора.

### 🟡 Слепая зона №2 — `validate-config`: detailed-диагностика fix-итераций структурно недостижима (наблюдение, не регрессия)

Подтверждена слепая зона из дизайна Локи (раздел 3.2/8). `ValidateChainConfigQueryHandler::validateSpecificChain()` сначала вызывает `$chainLoader->load()`, а потом `$chainValidator->validate()`. Loader теперь использует factory, которая кидает generic fail-fast при невалидных fix-итерациях → **`ChainDefinitionValidatorService` с detailed-диагностикой `fix_iteration group "%s" references unknown step "%s"` никогда не выполняется** для таких цепочек.

**Важно: это НЕ регрессия данного рефакторинга.** До него старый inline-алгоритм в `StaticChainDefinitionVo` ТОЖЕ кидал исключение в loader-пути до валидатора. Изменился только **текст** исключения: detailed → generic (что и есть принятый дизайн, вариант б).

**Что осталось latent:** detailed fix-iter проверки в `ChainDefinitionValidatorService` теперь упражняются только через reflection в тестах. Для production-пути они мертвы. Локи явно отметил это и предложил два сценария — заказчик выбрал «приемлем generic fail-fast». Решение зафиксировано, но стоит помнить, что detailed-path валидатора по fix-итерациям стал тестовым артефактом.

### 🟡 Слепая зона №3 — `ChainDefinitionValidatorService` не проверяет duplicate-membership (наблюдение)

Дизайн (раздел 3.2) рекомендовал «добавить диагностику duplicate membership, если бизнес хочет». Validator содержит только проверку `references unknown step` — **без** проверки «шаг принадлежит нескольким группам». Следствие: если цепочка создана через deprecated `ChainDefinitionVo` (без валидации — слепая зона №1) с дубликатом шага по группам, ни factory (обойдён), ни validator (нет такой проверки) это не поймают. Окно узкое (только deprecated-путь), но gap реален. Не требует правки в этом PR, но стоит завести отдельную задачу на синхронизацию validator ↔ specification (design, риск №3).

### 🟢 Слепая зона №4 — положительная находка: DI auto-discovery корректен

Дизайн (раздел 8, риск №5) требовал проверить, что `ChainDefinitionFactory` и `FixIterationsReferenceIntegritySpecification` попадут в контейнер. Проверено: `config/services.yaml` исключает `Domain/{Dto,Entity,Enum,Exception,ValueObject}/`, но **НЕ** исключает `Domain/Factory/` и `Domain/Specification/`. Оба класса `final readonly` с autowireable-зависимостями → будут зарегистрированы автоматически. Интеграционные тесты (5 шт.) проходят с вручную собранным графом — косвенно подтверждает корректность wiring.

---

## Вердикт: ✅ APPROVE (с одной некритичной рекомендацией)

Реализация **точно и добросовестно** следует дизайну Локи. Все ключевые архитектурные решения соблюдены:

1. ✅ Specification = bool-only, stateless, без исключений/I/O, работает только с domain values.
2. ✅ Factory = DI-граница создания VO, generic fail-fast, НЕ дублирует detailed-диагностику.
3. ✅ VO-конструкторы `@internal` thin; inline-алгоритм удалён (3 копии → 1 спецификация).
4. ✅ deprecated `ChainDefinitionVo`: helper убран, объясняющий комментарий о Deptrac-ограничении на месте, класс не вырос (483 nonblank < 500).
5. ✅ Helper удалён, FQCN убран из phpmd.xml, висячих ссылок нет.
6. ✅ Миграция 4 call-сайтов на фабрику; production static-factory-вызовов не осталось.
7. ✅ Тесты покрывают spec + factory + сохраняют detailed-assertions валидатора.

**Проверки зелёные:** PHPUnit (981/981), Psalm (0 errors), Deptrac (0 violations).

**Единственная рекомендация (не блокирует merge):** по слепой зоне №1 — принять осознанное решение по deprecated `ChainDefinitionVo` (оставить без guard ИЛИ добавить minimal constructor-guard из fallback-варианта дизайна). Текущее состояние допустимо (deprecated, нет production-вызовов), но должно быть явно согласовано, т.к. это поведенческий BC-break deprecated API.

**Наблюдения №2–№3** (недостижимость detailed-validator, отсутствие duplicate-check в validator) — это известные trade-off'ы принятого дизайна, не регрессии. Рекомендую завести задачу на синхронизацию validator ↔ specification, чтобы не плодить расхождение правил.

---

## Источники (📚)

Внутренние:
- `docs/conventions/layers/domain/specification.md`
- `docs/conventions/core-patterns/factory.md`
- `docs/conventions/core-patterns/value-object.md`
- `docs/conventions/core-patterns/helper.md`
- `vendor/prikotov/coding-standard/config/deptrac/depfile.yaml` (ruleset `DomainVo`/`DomainSpecification`)
- `docs/agents/reports/system-architect/2026-06-15_00-10_fixiterations-validation-redesign.md`
