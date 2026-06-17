# Дизайн-spike: DX-диагностика ошибок `fix_iterations` в пути загрузки конфига

**Роль:** Архитектор Локи
**Дата:** 2026-06-17
**Объект:** `src/Module/ChainDefinition` — взаимодействие `ChainDefinitionFactory` / `ChainDefinitionValidatorService` / `YamlChainLoaderService` / `ValidateChainConfigQueryHandler`.
**Задача:** [`todo/TASK-fix-fixiterations-config-error-dx.todo.md`](../../../todo/TASK-fix-fixiterations-config-error-dx.todo.md) (слепая зона №2 из ревью PR #261).

---

## Рефлексия (классификация запроса)

- 🧩 **сложность запроса: 7/10** — дилемма «fail-fast vs detailed DX» развязывает два противоречащих требования; требуется оценка 3+ подходов против конвенций (exception/factory/spec/service), Deptrac-правил и существующих тестов; нужно спроектировать единый источник диагностики, не породив второй.
- 🗂️ **уровень контекста: 9/10** — предоставлены: постановка, два предшествующих отчёта (дизайн PR #261 + ревью Пуаро), Конвенции, весь затронутый код, тесты, `depfile.yaml`, `services.yaml`.
- 🛡️ **риск ошибки: 4/10** — spike дизайн-only (код не пишется); риск лишь в неверной оценке соответствия конвенциям или в пропуске пути. Снижен полным прочтением кода и слепых зон.

**Вердикт:** запрос **проблемный** (сложность ≥ 7). Отрабатываю по протоколу: явно перечисляю допущения, не выдаю гипотезы за факты, даю альтернативы и критику собственного выбора.

**Допущения:**
1. «Боевой путь run» = `agent:orchestrate` (без `--validate-config`) и любой кодовый путь, идущий через `YamlChainLoaderService::load()` → `ChainDefinitionFactory`. Требование «fail-fast сохранён» = factory по-прежнему бросает исключение при невалидных `fix_iterations`, и невалидный VO не возвращается в рантайм.
2. «Validate-путь» = `agent:orchestrate --validate-config` через `ValidateChainConfigQueryHandler`. Именно здесь detailed-диагностика нужна и сейчас недостижима.
3. Поведенческая эквивалентность инварианта оценивается по факту выброса исключения, а не по тексту сообщения (текст намеренно уточняется).
4. Scope задачи — P2; полный охват сценария «validate **всех** цепочек за один проход» — nice-to-have, а не строгое требование (см. §6).

---

## 1. Контекст и верифицированные факты

Проверено по текущему коду `main` (без правок):

1. **`ChainDefinitionFactory::assertStepBasedInvariant()`** (`src/Module/ChainDefinition/Domain/Factory/ChainDefinitionFactory.php`, ~стр. 184) бросает **голый** `\InvalidArgumentException` с generic-сообщением:
   > `Chain "%s": fix_iterations must reference existing named steps and each step name must belong to at most one fix_iteration group.`
   Имя группы, шаг и тип нарушения (unknown / multiple groups) **отсутствуют**. Зависимость фабрики — только `FixIterationsReferenceIntegritySpecification` (bool-only).

2. **`ChainDefinitionValidatorService::validateStepBasedChain()`** (`src/Module/ChainDefinition/Domain/Service/Chain/ChainDefinitionValidatorService.php`, ~стр. 81–120) умеет писать детально:
   - `fix_iteration group "%s" references unknown step "%s".`
   - `fix_iteration step "%s" belongs to multiple groups ("%s" and "%s").`
   Но работает с **уже сконструированным VO** (`StaticChainDefinitionVo` / `ConditionalChainDefinitionVo`), а не с «сырыми» частями.

3. **Порядок вызовов в validate-пути — корень проблемы.**
   `ValidateChainConfigQueryHandler::validateSpecificChain()` сначала вызывает `$chainLoader->load($chainName)`, и **только потом** `$chainValidator->validate($chainVo)`. `load()` → `YamlChainLoaderService::loadAll()` → `parseChainDefinition()` → `ChainDefinitionFactory::createFrom*()` → `assertStepBasedInvariant()` бросает generic-исключение **до** того, как VO доходит до валидатора. Detailed-диагностика структурно недостижима. Это и есть слепая зона №2.

4. **`validate:connectivity` НЕ затрагивает `fix_iterations`** (поправка к постановке).
   `ValidateConnectivityCommand` → `RunRoleStartupCheckCommandHandler` → `ConnectivityRoleTargetProviderInterface` (`YamlConnectivityRoleTargetProviderService`) читает **только** top-level секцию `roles`, цепочки не грузит. Поэтому в `validate:connectivity` проблема не воспроизводится. Реальный пострадавший validate-путь — `agent:orchestrate --validate-config`.

5. **Run-путь** (`agent:orchestrate` без `--validate-config`) идёт через `LoadChainQueryHandler` → `load()` → та же factory. Исключение всплывает в `OrchestrateCommand`, ловится `catch (\Throwable $e)` и печатается через `$e->getMessage()`. Fail-fast соблюдён.

6. **Существующие convention-запахи (предсуществующие, не цель данной задачи, но релевантны):**
   - Фабрика бросает голый `\InvalidArgumentException`, а не реализацию `ValidationExceptionInterface`-маркера (конвенция `exception.md`: «Не перехватываем базовые PHP-исключения»; «ошибки валидации делаются для показа пользователю»; «выбрасываем реализацию, ловим интерфейс»).
   - Модульный base `OrchestratorException extends \DomainException`; `ChainNotFoundException implements NotFoundExceptionInterface` — в проекте уже есть паттерн «маркерный интерфейс + реализация».
   - CLI `executeValidateConfig` ловит голое `\Exception` — тоже запах.

7. **Deptrac** (`vendor/prikotov/coding-standard/config/deptrac/depfile.yaml`):
   - `DomainSpecification → {Domain, DomainDto, DomainEnum, DomainSpecification, DomainVo}`.
   - `ApplicationQueryHandler → {Application, +ApplicationQuery, Domain, DomainDto, DomainEnum, DomainSpecification, DomainVo}` → **QueryHandler → Domain Service разрешён**.
   - Фабрика живёт в `Domain\Factory` (правило `Domain`), её «Разрешено» по конвенции `factory.md`: примитивы/Enum/VO своего модуля + **Спецификации** + другие фабрики. Domain Service в списке явно **не назван** — это серая зона (см. §4, подход D).

8. **DI** (`config/services.yaml`): `Domain/Factory/`, `Domain/Specification/`, `Domain/Service/` **не исключены** из auto-discovery → новые Domain-компоненты подхватятся автоматически. Для сервиса по интерфейсу нужна явная alias-запись (конвенция `service.md`).

---

## 2. Анализ подходов

### Подход A — Detailed-валидатор как pre-check по «сырым» частям до factory

**Суть:** в validate-пути запускать detailed-диагностику по распарсенным, но ещё не «собранным» фабрикой, частям (`steps`, `fixIterations`), до вызова factory. Чтобы получить «сырые» части без выброса, нужно отделить парсинг YAML от сборки VO:
- либо извлечь `YamlChainParser` (Infrastructure), который возвращает структуру «сырых» частей без вызова factory;
- либо добавить в `ChainLoaderInterface` метод вида `loadParsed()` / `validate()`, не бросающий на инварианте.

**Плюсы:** factory не трогается (остаётся строго generic); detailed-логика вызывается явно в validate-пути; теоретически покрывает и single-chain, и validate-all (если парсер отдаёт все цепочки).
**Минусы:** самый большой blast radius — новый Parser (или метод загрузчика) + рефакторинг `YamlChainLoaderService` + изменение контракта `ChainLoaderInterface`; возникает **второй путь парсинга** (parse-for-VO vs parse-for-validation) с риском расхождения; валидатору нужен метод, принимающий «сырые» части, а не VO (расширение контракта `ChainDefinitionValidatorServiceInterface`).

### Подход B — Catch `InvalidArgumentException` factory + повторный detailed-парсинг

**Суть:** ловить generic-исключение фабрики в validate-пути и повторно прогонять detailed-валидатор по «сырым» данным.
**Плюсы:** factory не трогается.
**Минусы (отвергаем):**
- «Сырые» данные в точке throw — локальные переменные `parseChainDefinition()`; после throw они потеряны. Повторный парсинг = повторное чтение YAML + повторный маппинг.
- `YamlChainLoaderService::parseFixIterations()` бросает **свой** `InvalidArgumentException` (например, «группа должна иметь ≥2 шагов») **до** проверки ссылочной целостности → повторный парсинг упадёт с другой ошибкой, не дойдя до detailed-проверки. Хрупкий порядок отказов.
- По сути это «подход A с лишними шагами и нестабильным re-parse». Самый слабый вариант.

### Подход C — Half-measure: обогащение generic-сообщения списком доступных шагов

**Суть:** дописать в сообщение фабрики перечень имён шагов цепочки (без имени группы и без типа нарушения).
**Плюсы:** минимальная цена (правка одного `sprintf`), низкий риск.
**Минусы (отвергаем как недостаточно):**
- **Не достигает Expected Result задачи:** пользователь должен видеть «имя группы и/или шага **и тип нарушения** (unknown / multiple groups)». Подход C не называет группу и не различает тип.
- Дублирует форматирование сообщений между фабрикой и валидатором → ровно то, от чего уходил PR #261 (риск расхождения).
- Допустим лишь как временный stopgap, если команду устроит снижение критериев приёмки. Сама постановка требует большего, поэтому C как единственное решение не подтверждаю.

### Подход D (предлагаю альтернативу) — Общий источник диагностики + carrier-исключение фабрики + перехват в Application-слое

**Суть:** вынести detailed-логику `fix_iterations` из inline-цикла валидатора в **единый Domain Service**-коллектор; фабрика бросает **domain-исключение-носитель** (carrying raw inputs, без форматирования detailed-сообщений); validate-путь ловит это исключение и получает detailed-нарушения через коллектор. Подробно — в §3.

**Плюсы:** один источник diagnostic-логики (нет расхождения); factory остаётся чистой по зависимостям (только Specification); run-путь не меняется по поведению (исключение летит дальше, fail-fast сохранён); validate-путь получает полную детализацию; заодно исправляется convention-запах (голый `\InvalidArgumentException` → domain-исключение с маркером).
**Минусы:** вводится один Domain Service + 1–2 класса исключений; меняется тип бросаемого фабрикой исключения (тесты фабрики правятся минимально — см. §5); validate-**all** полностью не чинится без доп. работы (см. §6).

---

## 3. Обоснованный выбор: Подход D (конкретно — вариант D′)

Из D есть две подвариации, где считать нарушения:

| | D (factory-side) | **D′ (handler-side) — ВЫБРАНО** |
|---|---|---|
| Кто вызывает коллектор | Factory (нужно внедрить коллектор в фабрику) | **Application-Handler** (factory чиста) |
| Зависимости factory | + Domain Service (серая зона `factory.md`) | **без изменений** (только Specification) |
| Что несёт исключение | готовый `list<ChainConfigViolationVo>` | **raw inputs** (`name`, `steps`, `fixIterations`) |
| Convention-строгость | серая зона (factory → Service не назван в «Разрешено») | **чистая** (factory → только Specification; Handler → Domain Service разрешён Deptrac и `service.md`) |

**Решение: вариант D′ (carrier-исключение + invocation коллектора на стороне Handler).**

Почему D′, а не A/B/C:
- **Сохраняет fail-fast run-пути** полностью: фабрика по-прежнему бросает исключение при невалидных `fix_iterations`, невалидный VO не возвращается. Меняется только **класс** исключения (голый `\InvalidArgumentException` → `InvalidFixIterationsException` с маркером) и добавляются raw inputs как payload. `getMessage()` может остаться прежним generic-текстом → run-путь визуально неизменен.
- **Не ослабляет инвариант:** проверка инварианта остаётся в фабрике через Specification; исключение бросается ровно там же, где и сейчас.
- **Не порождает второй источник detailed-сообщений** (главный аргумент PR #261): detailed-логика живёт **только** в новом коллекторе. Фабрика не форматирует detailed-сообщения — она лишь несёт raw inputs. Валидатор делегирует свой inline-цикл тому же коллектору. PR #261 не «откатывается», а **уточняется**: rationale («не плодить второй источник») соблюдён строже.
- **Не требует второго пути парсинга** (в отличие от A): единственный создатель VO — фабрика; loader не рефакторится, контракт `ChainLoaderInterface` не меняется.
- **Достигает Expected Result:** validate-путь выводит имя группы, шаг и тип нарушения на уровне качества валидатора.
- **Попутно исправляет convention-запах:** голое `\InvalidArgumentException` заменяется domain-исключением с маркерным интерфейсом (паттерн уже есть в модуле: `NotFoundExceptionInterface` + `ChainNotFoundException`).

---

## 4. Trade-offs: сводная таблица

| Критерий | A (raw pre-check) | B (catch + re-parse) | C (enrich msg) | **D′ (carrier + handler-side collector) — ВЫБРАНО** |
|---|---|---|---|---|
| Run-путь fail-fast сохранён | ✅ factory не трогается | ✅ factory не трогается | ✅ | ✅ (factory бросает, класс исключения меняется, поведение нет) |
| DX: группа + шаг + тип нарушения | ✅ | ⚠️ re-parse падает раньше | ❌ только список шагов, без группы/типа | ✅ (коллектор = уровень валидатора) |
| DDD-слои и Конвенции | ✅ (Domain Service на VO) | 🔴 re-parse в Infra/App, хрупко | ⚠️ дублирование форматирования | ✅ (factory → только Spec; Handler → Domain Service; единственный источник) |
| Нет терминов Port/Adapter | ✅ | ✅ | ✅ | ✅ |
| Стоимость / blast radius | 🔴 новый Parser/метод loader + рефакторинг + контракт | 🔴 высокий (хрупкий re-parse) | 🟢 минимальный | 🟡 средний (1 Service + 1–2 исключения + catch в Handler) |
| Риск регрессии | 🟡 два пути парсинга могут разойтись | 🔴 высокий | 🟢 низкий | 🟢 низкий (единственный путь создания VO) |
| Единый источник diagnostic-логики | ⚠️ нужен рефакторинг валидатора, иначе дублирование | ❌ | ❌ дублирует валидатор | ✅ (коллектор — единственный источник) |
| Полный охват validate-**all** | ✅ (если парсер отдаёт все) | ❌ | ❌ | ⚠️ частично (см. §6) |
| Откатывает решение PR #261? | нет | нет | частично | нет (уточняет, сохраняя rationale) |

---

## 5. Критика собственного выбора (скептик-режим)

**Слабое место 1. «Исключение несёт массивы VO — это разовый хак?»**
Парирую: это ограниченный, доменный carrier-исключение. Payload — небольшие immutable readonly VO (`ChainStepVo[]`, `FixIterationGroupVo[]`). Альтернативы хуже: re-parse в Handler (подход B, хрупко) или зависимость фабрики от коллектора (подход D, серая зона `factory.md`). Если появится второй сценарий «ошибки конфига, бросаемые при load», паттерн обобщается до `ChainConfigExceptionInterface` + единый носитель `list<ChainConfigViolationVo>`. Но обобщать преждевременно (YAGNI) — стартуем с `fix_iterations`-специфичного носителя; обобщение — отдельная задача, когда появится второй кейс.

**Слабое место 2. «Новый Domain Service (коллектор) с interface + impl + alias — оверкилл для ~25 NLOC чистой функции?»**
Парирую: конвенция `service.md` требует interface + impl + alias для Domain Service. Коллектор **переиспользуется** (валидатор делегирует + Handler вызывает) — он зарабатывает своё существование. Меньше бойлерплейта даст вариант «метод на существующем валидаторе» — но тогда фабрика (D) или Handler (D′) сцепляется с валидатором, а D′ намеренно держит фабрику чистой. Collector как самостоятельный Service — самый чистый по конвенциям; бойлерплейт — цена compliance.

**Слабое место 3. «Handler получает ветвление: try load → на carrier-исключении собрать нарушения; иначе валидировать VO. Это creep Application-логики?»**
Парирую: Handler только оркестрирует — catch → делегировать коллектору (Domain) → смаппить (существующий `ChainConfigViolationDtoMapper`) → вернуть. Бизнес-логики нет, только control flow. Допустимо для Query Handler.

**Слабое место 4. «Validate-all всё ещё падает на первой невалидной цепочке?»**
Это **реальное ограничение D′**, не парируемое бесплатно (см. §6). `list()` → `loadAll()` собирает ВСЕ цепочки и бросает на первой невалидной → весь validate-all обрывается. D′ чинит single-chain полностью; validate-all — лишь частично (ловим carrier-исключение от `list()` и показываем detailed-нарушения первой упавшей цепочки вместо generic-краша). Полный охват validate-all требует подхода A (parser extraction) или метода-«listNames без загрузки» — выношу в §6 как осознанный scope-cut.

**Слабое место 5. «validate:connectivity в постановке — а он не при чём?»**
Факт (не гипотеза, проверено по коду): `validate:connectivity` не грузит цепочки и не трогает `fix_iterations`. Постановка объединяет его с `validate-config` неточно. В отчёте и Implementation Spec это учтено: `validate:connectivity` **не изменяется**.

---

## 6. Риски и зависимости

| Риск / зависимость | Оценка | Парирование |
|---|---|---|
| Изменение **класса** исключения фабрики ломает тесты | Низкий | `InvalidFixIterationsException extends \InvalidArgumentException` → `expectException(\InvalidArgumentException::class)` в `ChainDefinitionFactoryTest` остаётся зелёным. Меняется лишь добавление payload-геттеров (новые assertion-тесты). |
| Расхождение коллектор ↔ Specification | Среднее | Уже есть антидивергентный тест `specificationFalseImpliesValidatorHasFixIterationsViolations` (`ChainDefinitionValidatorTest`) — расширить: `specificationFalse ⟺ collector non-empty`. Спецификация остаётся oracle. |
| Convention-compliance нового исключения (маркерный интерфейс) | Низкий | Следуем паттерну `NotFoundExceptionInterface` + `ChainNotFoundException`. Пуаро на ревью сверяет с `exception.md`. |
| DI: новый коллектор не зарегистрировался | Низкий | `Domain/Service/` не исключён из auto-discovery; добавить явный alias `Interface → Impl` в `services.yaml` (конвенция `service.md`). Проверить `bin/console debug:container`. |
| Deptrac: Handler → Domain Service; Factory → Specification | Низкий | Обе зависимости разрешены ruleset'ом (`ApplicationQueryHandler → Domain`; `Domain → DomainSpecification`). Deptrac прогоняется в `make check`. |
| **Validate-all остаётся degraded** | Средний | Осознанный scope-cut (P2). Если валидация всех цепочек за проход важна — отдельная задача на parser extraction (подход A). В этом spike — не делаем. |
| Ручные конструкции `ValidateChainConfigQueryHandler` в тестах ломаются (новая зависимость) | Низкий | `OrchestrateCommandTest::configOptionWithValidateConfigValidatesCustomFile` собирает Handler вручную с 3 deps — добавить 4-й (коллектор). В Implementation Spec отмечено. |
| Документация | Низкий | Обновить `docs/guide/cli.md` / troubleshooting, если формулировки вывода `--validate-config` меняются; проверить поиском по «fix_iteration» / «validate-config». |

📚 **Источники:**
- Конвенции: `docs/conventions/core_patterns/exception.md`, `docs/conventions/core_patterns/factory.md`, `docs/conventions/layers/domain/specification.md`, `docs/conventions/core_patterns/service.md`.
- Deptrac: `vendor/prikotov/coding-standard/config/deptrac/depfile.yaml`.
- Предшествующий дизайн: `docs/agents/reports/system-architect/2026-06-15_00-10_fixiterations-validation-redesign.md`.
- Ревью (слепая зона №2): `docs/agents/reports/code-reviewer-backend/2026-06-15_08-50_pr-261-fixiterations-redesign-architecture-review.md`.

---

## 7. Implementation Spec для Бэкендера Левши

> Эта секция — вход для исполнителя. Реализация делегирована Бэкендеру Левше; Архитектор не пишет продакшен-код и не трогает тесты. Тимлид контролирует git.

### 7.1. Что входит в scope
- Single-chain validate-путь (`ValidateChainConfigQueryHandler::validateSpecificChain`) получает detailed-диагностику `fix_iterations` (группа + шаг + тип: unknown / multiple groups) на уровне качества `ChainDefinitionValidatorService`.
- Единственный источник detailed-логики `fix_iterations` — новый Domain Service-коллектор; валидатор делегирует ему inline-цикл.
- Фабрика бросает domain-исключение-носитель (с маркером) вместо голого `\InvalidArgumentException`; `getMessage()` остаётся прежним generic-текстом (run-путь визуально неизменен).
- Run-путь (`agent:orchestrate`, `load()`) — **fail-fast сохранён**, поведение не меняется.

### 7.2. Что НЕ входит в scope
- Полный охват validate-**all** (когда `--validate-config` без `--chain` проверяет все цепочки и одна невалидная не обрывает проход). Это требует parser extraction (подход A) — отдельная задача. В рамках текущей: validate-all ловит carrier-исключение от `list()` и показывает detailed-нарушения упавшей цепочки (минимальное улучшение, без обрыва generic-крашем).
- `validate:connectivity` — **не трогается** (не грузит цепочки, не связан с `fix_iterations`).
- Правка всех прочих голых `\InvalidArgumentException` / `catch (\Throwable)` в модуле — это предсуществующий долг, вне scope. Меняем только точку фабрики `fix_iterations`.
- Обобщение carrier-паттерна на все конфиг-ошибки (YAGNI до второго кейса).

### 7.3. Файлы к изменению / созданию (последовательность)

**Шаг 1. Создать маркерный интерфейс исключения (Domain/Exception).**
- Файл: `src/Module/ChainDefinition/Domain/Exception/ChainConfigExceptionInterface.php`
- Контракт: `interface ChainConfigExceptionInterface extends \Throwable {}` (маркер конфиг-ошибок модуля; паттерн — как `NotFoundExceptionInterface`). Handler будет ловить по нему.

**Шаг 2. Создать carrier-исключение (Domain/Exception).**
- Файл: `src/Module/ChainDefinition/Domain/Exception/InvalidFixIterationsException.php`
- Контракт:
  - `final class InvalidFixIterationsException extends \InvalidArgumentException implements ChainConfigExceptionInterface`.
  - Конструктор: `(string $chainName, array $steps, array $fixIterations, ?\Throwable $previous = null)`.
  - Сохранить **текущий generic-текст** сообщения: `sprintf('Chain "%s": fix_iterations must reference existing named steps and each step name must belong to at most one fix_iteration group.', $chainName)`.
  - Readonly-свойства + геттеры: `getChainName(): string`, `getSteps(): array` (`list<ChainStepVo>`), `getFixIterations(): array` (`list<FixIterationGroupVo>`).
  - PHPDoc с `@param list<ChainStepVo>` / `@param list<FixIterationGroupVo>`.

**Шаг 3. Создать Domain Service-коллектор (единый источник detailed-логики).**
- Интерфейс: `src/Module/ChainDefinition/Domain/Service/Chain/CollectFixIterationsViolationsServiceInterface.php`
  - `public function collect(string $chainName, array $steps, array $fixIterations): array;` (`@param list<ChainStepVo> $steps`, `@param list<FixIterationGroupVo> $fixIterations`, `@return list<ChainConfigViolationVo>`).
- Реализация: `src/Module/ChainDefinition/Domain/Service/Chain/CollectFixIterationsViolationsService.php`
  - `final readonly class … implements …Interface`.
  - Тело — **перенос** текущего inline-цикла `fix_iterations` из `ChainDefinitionValidatorService::validateStepBasedChain()` (построение `stepNameMap`, `stepFirstGroup`, форматирование сообщений про unknown step и multiple groups) **дословно**, без изменения текстов сообщений. Это критично: тесты валидатора ждут точные строки.
  - Stateless, без I/O, работает только с domain VO.

**Шаг 4. Зарегистрировать коллектор в DI.**
- Файл: `config/services.yaml` (секция `# ─── ChainDefinition module ───`).
- Добавить alias:
  ```yaml
  TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\CollectFixIterationsViolationsServiceInterface:
      alias: TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\CollectFixIterationsViolationsService
  ```
  (Реализация auto-discover'ится; нужен только alias по конвенции `service.md`.)

**Шаг 5. Валидатор делегирует коллектору (рефакторинг без изменения поведения).**
- Файл: `src/Module/ChainDefinition/Domain/Service/Chain/ChainDefinitionValidatorService.php`.
- Внедрить `CollectFixIterationsViolationsServiceInterface` через конструктор (`final readonly class` → private readonly свойство).
- В `validateStepBasedChain()`: блок проверки `fix_iterations` (построение `stepNameMap` + цикл по группам) **заменить** на `$violations = [...$violations, ...$this->collector->collect($name, $steps, $fixIterations)];`. Условие раннего возврата при `$fixIterations === []` сохранить.
- **Контракт-инвариант:** выходные сообщения и порядок нарушений должны совпадать побайтно с текущими (покрыто существующими тестами + антидивергентным тестом).

**Шаг 6. Фабрика бросает carrier-исключение.**
- Файл: `src/Module/ChainDefinition/Domain/Factory/ChainDefinitionFactory.php`, метод `assertStepBasedInvariant()`.
- Заменить:
  ```php
  throw new InvalidArgumentException(sprintf('Chain "%s": fix_iterations must reference …', $name));
  ```
  на:
  ```php
  throw new InvalidFixIterationsException($name, $steps, $fixIterations);
  ```
- Зависимости фабрики **не меняются** (по-прежнему только `FixIterationsReferenceIntegritySpecification`). Добавить `use` для нового исключения.

**Шаг 7. Validate-путь ловит carrier и собирает detailed-нарушения.**
- Файл: `src/Module/ChainDefinition/Application/UseCase/Query/Chain/ValidateChainConfig/ValidateChainConfigQueryHandler.php`.
- Добавить зависимость `CollectFixIterationsViolationsServiceInterface` в конструктор (4-я зависимость).
- `validateSpecificChain()`:
  ```php
  try {
      $chainVo = $this->chainLoader->load($chainName);
  } catch (ChainConfigExceptionInterface $e) {
      $violations = ($e instanceof InvalidFixIterationsException)
          ? $this->collector->collect($e->getChainName(), $e->getSteps(), $e->getFixIterations())
          : [];
      return new ValidateChainConfigResult(
          isValid: false,
          violations: $this->violationMapper->mapList($violations),
          validChainName: $chainName,
      );
  }
  $violations = $this->chainValidator->validate($chainVo);
  return new ValidateChainConfigResult(/* как сейчас */);
  ```
- `validateAllChains()`: обернуть `$this->chainLoader->list()` в `try/catch (ChainConfigExceptionInterface)` — на случай падения показать detailed-нарушения первой упавшей цепочки вместо generic-краша. **Не пытаться** продолжать проход по остальным цепочкам (это вне scope, см. §7.2).
- Импортировать `ChainConfigExceptionInterface` и `InvalidFixIterationsException`.

> Примечание о catch по интерфейсу: конвенция `exception.md` предписывает ловить по интерфейсу. Ловим `ChainConfigExceptionInterface`; `instanceof InvalidFixIterationsException` — точечная проверка payload (геттеры есть только у носителя). Если Пуаро предпочтёт единый носитель — допустимо вынести геттеры в интерфейс, но это усложнит контракт; стартуем с `instanceof`.

### 7.4. План тестов

**Unit — новый коллектор:** `tests/Unit/Domain/Service/Chain/CollectFixIterationsViolationsServiceTest.php`
- пустые `fixIterations` → `[]`;
- все ссылки валидны → `[]`;
- unknown step → 1 нарушение с `field=fix_iterations`, сообщение содержит имя группы и шаг (`references unknown step`);
- шаг в двух группах → 1 нарушение (`belongs to multiple groups`), сообщение содержит шаг + обе группы;
- unknown-шаг в двух группах → 2 unknown-нарушения (по числу групп), **без** эскалации в duplicate (поведение short-circuit, как в текущем `fixIterationUnknownStepDoesNotEscalateToDuplicateViolation`);
- неназванный шаг (null name) исключается из `stepNameMap`.

**Unit — обновить `ChainDefinitionFactoryTest`:**
- Существующие `…ThrowsGenericOnUnknownFixIterationStep` / `…ThrowsGenericOnDuplicateFixIterationStep` / `…ThrowsGenericOnInvalidFixIterations`: `expectException(\InvalidArgumentException::class)` **остаётся зелёным** (наследник). Проверить generic-текст через `expectExceptionMessage` (текст не меняется).
- **Добавить** assertions на payload: `assertInstanceOf(InvalidFixIterationsException::class, …)`, и проверить `getChainName()`, `getSteps()`, `getFixIterations()` несут исходные данные.
- Тесты на empty steps / dynamic guards — без изменений.

**Unit — обновить `ChainDefinitionValidatorTest`:**
- Существующие assertion'ы на точные строки (`fix_iteration step "shared" belongs to multiple groups ("groupA" and "groupB").` и т.д.) **должны остаться зелёными** после делегирования коллектору — это проверка «дословного переноса».
- Обновить `setUp()`: `new ChainDefinitionValidatorService(new CollectFixIterationsViolationsService())`.
- Антидивергентный датапровайдер `invalidFixIterationsForSpecAndValidator` — оставить; добавить симметричный oracle-check «specification false ⟺ collector non-empty».

**Unit — обновить `YamlChainLoaderTest`:**
- Если есть тест, явно завязанный на голый `\InvalidArgumentException` от фабрики по `fix_iterations` — убедиться, что наследник `InvalidFixIterationsException` его удовлетворяет; при необходимости уточнить assertion на класс/сообщение.

**Unit — новый/обновить для Handler:** `tests/Unit/Application/UseCase/Query/Chain/ValidateChainConfig/ValidateChainConfigQueryHandlerTest.php` (если нет — создать)
- mock `ChainLoaderInterface` бросает `InvalidFixIterationsException(name, steps, fixIterations)` с unknown-step → Handler возвращает `isValid=false` и violation с группой+шагом;
- то же с multiple-groups → violation с шагом+обеими группами;
- happy path: `load()` возвращает VO без нарушений → `isValid=true`;
- `load()` возвращает VO с step/role-нарушениями → возвращаются они (fix_iterations-ветка не срабатывает);
- `load()` бросает `ChainNotFoundException` → исключение всплывает (не маскируется).
- В ручных конструкциях Handler добавить коллектор.

**Unit — обновить `OrchestrateCommandTest`:**
- `configOptionWithValidateConfigValidatesCustomFile` и аналогичные ручные сборки `ValidateChainConfigQueryHandler`: добавить 4-й аргумент-коллектор.
- Добавить сценарий `validateConfigWithFixIterationsViolationRendersDetailed`: Handler возвращает violation с группой+шагом → CLI печатает имя группы и шаг (assertion на `getDisplay()`).

**Integration — опционально:** расширить `tests/Integration/_fixtures/test_chains.yaml` цепочкой с **невалидными** `fix_iterations` (unknown step) и добавить integration-тест, прогоняющий `agent:orchestrate --validate-config --chain <broken>` через реальный `YamlChainLoaderService` + factory + handler, с assertion на detailed-сообщение в выводе. Это закрывает весь путь end-to-end и является главным приёмочным тестом задачи.

### 7.5. Edge-cases (явно покрыть тестами)
- `fix_iterations` пуст → нет нарушений, factory не бросает.
- Шаг с `name: null` (неназванный) — исключается из `stepNameMap`; ссылка на него → unknown (как сейчас).
- Unknown-шаг, встречающийся в **двух** группах → ровно 2 unknown-нарушения, без duplicate (поведение short-circuit сохраняется).
- Mixed: unknown + duplicate в одном конфиге → все нарушения собираются (как у валидатора).
- Dynamic-цепочка (`createFromDynamic`) — не имеет `fix_iterations`, carrier-исключение не применимо; поведение не меняется.
- Conditional-цепочка с невалидными `fix_iterations` — тот же путь через `createFromConditionalSteps` → `assertStepBasedInvariant`.
- Run-путь: factory бросает `InvalidFixIterationsException` → всплывает в `OrchestrateCommand` → печатается `getMessage()` (generic-текст сохранён). Exit code не меняется.

### 7.6. Предварительные проверки перед отчётом Левши
- `vendor/bin/phpunit` (особенно `tests/Unit/Domain/Factory/`, `tests/Unit/Domain/Service/Chain/`, `tests/Unit/Application/.../ValidateChainConfig/`).
- `vendor/bin/psalm`.
- `vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress` (проверить `ApplicationQueryHandler → Domain`, `Domain → DomainSpecification`, отсутствие новых нарушений).
- `php vendor/prikotov/coding-standard/bin/run-sniff-tests.php` (если доступен).
- `bin/console debug:container CollectFixIterationsViolationsService` — убедиться, что alias резолвится.

### 7.7. Чек-лист соответствия Конвенциям (для самопроверки Левши и ревью Пуаро)
- [ ] `CollectFixIterationsViolationsService` — Domain Service: interface + impl + alias в `services.yaml`, stateless, работает только с domain VO. (`service.md`)
- [ ] `InvalidFixIterationsException` — domain-исключение с маркером `ChainConfigExceptionInterface extends \Throwable`; выбрасывается реализацией, ловится по интерфейсу. (`exception.md`)
- [ ] `ChainDefinitionFactory` — единственная новая зависимость отсутствует (по-прежнему только Specification); выбрасывает корректное исключение при нарушении инварианта. (`factory.md`)
- [ ] `FixIterationsReferenceIntegritySpecification` — остаётся **bool-only**, без diagnostic-метода. (`specification.md`)
- [ ] Нет терминов Port/Adapter в путях и именах. (`AGENTS.md`)
- [ ] Слои: коллектор и исключение в Domain; Handler в Application зависит от Domain (разрешено); Loader/Infrastructure не трогается по контракту.
- [ ] Run-путь остаётся fail-fast; инвариант не ослаблен; detailed-диагностика — только в validate-пути.
- [ ] Строгая типизация (`declare(strict_types=1)`, typed properties, return/param types) во всех новых файлах.
- [ ] PHPDoc на публичных методах/классах новых компонентов.
