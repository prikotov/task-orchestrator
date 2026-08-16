# Ревью редизайна `ChainStepParserHelper` → `ChainStepFactory` + `YamlChainStepMapper` + `YamlRetryPolicyMapper`

**Роль:** Ревьювер Бэка Пуаро (code_reviewer_backend_puaro)
**Дата:** 2026-06-15
**Объект:** PR #261 (epic `refactor/phpmd-baseline-elimination`), ветка `refactor/phpmd-baseline-elimination`. Новые классы `ChainStepFactory`, `YamlChainStepMapper`, `YamlRetryPolicyMapper`, правки `ChainStepVo` / `YamlChainLoaderService`, удаление `ChainStepParserHelper`, 3 новых unit-теста.
**Задача:** Ревью нового кода по принятому дизайну архитектора Локи (`docs/agents/reports/system-architect/2026-06-15_11-55_chainstepparser-redesign.md`) с обязательным `ZERO behavioral change`.

**Эталон для сверки:**
- Дизайн Локи: `docs/agents/reports/system-architect/2026-06-15_11-55_chainstepparser-redesign.md`
- Аудит Пуаро: `docs/agents/reports/code-reviewer-backend/2026-06-15_11-06_chainstepparserhelper-convention-audit.md`
- Оригинал helper: `git show c8f2789:src/Module/ChainDefinition/Infrastructure/Service/Chain/Helper/ChainStepParserHelper.php`
- Precedent: `src/Module/ChainDefinition/Domain/Factory/ChainDefinitionFactory.php`

**Применённые конвенции:** `docs/conventions/core-patterns/factory.md`, `docs/conventions/core-patterns/mapper.md`, `docs/conventions/core-patterns/helper.md`, `docs/conventions/layers/domain.md`, `docs/conventions/layers/domain/specification.md`.

---

## Предварительная классификация

- 🧩 сложность запроса: 7/10 — многоуровневая сверка дизайна, 6 развилок, zero-behavioral-change верификация byte-to-byte по оригиналу c8f2789, два слоя конвенций.
- 🗂️ уровень контекста: 9/10 — полный контекст: дизайн-ADR, аудит, оригинал, precedent, конвенции, все изменённые файлы доступны.
- 🛡️ риск ошибки: 3/10 — реализация зрелая, все тесты (1017) + Psalm (src/) + Deptrac PASS на момент ревью; риски ограничиваются edge cases.

Допущения:
- «Тимлид завершил проверки (все PASS)» принято как факт; ключевые проверки (PHPUnit 1017/1017, Psalm src/ — no errors, Deptrac — 0 violations) перепроверены независимо.
- Проверки Psalm на `tests/` не входят в стандартный `vendor/bin/psalm` (конфиг сканирует только `src/`); замечания по тест-кодам вынесены в секцию E как REMARK (test-only).

---

## A. Соответствие дизайну Локи (6 развилок)

### A.1. ChainStepFactory в Domain\Factory — **PASS**
- `src/Module/ChainDefinition/Domain/Factory/ChainStepFactory.php` — namespace и путь соответствуют `factory.md` «размещается в слое создаваемых объектов». `ChainStepVo` — Domain VO → `Domain\Factory` корректно.
- Не Infrastructure, не Application. Развилка 1 (вариант 3) выполнена точно.

### A.2. Дефолты 'pi'/'120' через ChainStepVo constants — **PASS**
- `ChainStepVo` (стр. 25–32): добавлены `public const string DEFAULT_RUNNER = 'pi';` и `public const int DEFAULT_TIMEOUT_SECONDS = 120;`.
- Конструктор и static-factories `createAgent`/`createTool`/`createQualityGate` мигрированы на `self::DEFAULT_RUNNER` / `self::DEFAULT_TIMEOUT_SECONDS` (diff подтверждает замену всех 4 literals).
- `ChainStepFactory` использует `ChainStepVo::DEFAULT_RUNNER` и `ChainStepVo::DEFAULT_TIMEOUT_SECONDS` (стр. 73, 131, 162) — magic literals `120`/`'pi'` в фабрике отсутствуют. Развилка 2 (вариант 4) выполнена точно.

### A.3. parseRetryPolicy → отдельный YamlRetryPolicyMapper — **PASS**
- `src/Module/ChainDefinition/Infrastructure/Mapper/Chain/YamlRetryPolicyMapper.php` — контракт `mapToChainRetryPolicy(?array $raw): ?ChainRetryPolicyVo`, `null`/`[]` → `null`, непустой → `ChainRetryPolicyVo::createFromArray()`.
- Используется независимо на уровне цепочки (`YamlChainLoaderService:136,190`) и на уровне шага (`YamlChainStepMapper:120`). Развилка 3 (вариант 1) выполнена точно.

### A.4. runnerExplicit вычисляется в mapper, фабрика только переносит — **PASS**
- Mapper (`YamlChainStepMapper:130`): `runnerExplicit: array_key_exists('runner', $step)` — key-presence семантика.
- Factory (`ChainStepFactory:73`): параметр `bool $runnerExplicit`, передаётся в `ChainStepVo::createAgent(runnerExplicit: $runnerExplicit)` без повторного сравнения со строкой `pi`. Развилка 4 (вариант 2) выполнена точно.

### A.5. Merge step??chain в ChainStepFactory на typed inputs — **PASS**
- Factory (`ChainStepFactory:75–76`): `retryPolicy: $stepRetryPolicy ?? $chainRetryPolicy`, `noContextFiles: $stepNoContextFiles ?? $chainNoContextFiles`.
- Параметры типизированы: `?ChainRetryPolicyVo $stepRetryPolicy`, `?ChainRetryPolicyVo $chainRetryPolicy`, `?bool $stepNoContextFiles`, `bool $chainNoContextFiles`.
- Mapper не выполняет merge: `extractNoContextFiles` возвращает `null` (отсутствие) / `bool` (явное), `extractRetryPolicy` возвращает `?array`. Развилка 5 (вариант 3) выполнена точно.

### A.6. Прямые unit-тесты на все ветвления — **PASS**
- `ChainStepFactoryTest` (24 теста): defaults, explicit flag (4 кейса включая `runner: null` backward-compat), retry inheritance (3), no_context_files inheritance (4 включая `false` перекрывает `true`), timeout defaults + explicit, guards (role/command/label × agent/tool/qg), output_key/when/post_step null-обработка.
- `YamlChainStepMapperTest` (10 тестов): type-dispatch, missing/unknown type, runnerExplicit key-presence (3 кейса), retry policy passing, no_context_files presence, tool output_key, quality_gate when-expression.
- `YamlRetryPolicyMapperTest` (4 теста): null, empty, full, partial-with-VO-defaults.
- Все 38 тестов из design-ADR (19 factory + 9 mapper + 4 retry + loader smoke) покрыты. Развилка 6 (вариант 3) выполнена точно.

---

## B. Zero Behavioral Change (сверка с оригиналом c8f2789)

### B.1. runner='pi' при отсутствии — **PASS**
- Original: `$step['runner'] ?? 'pi'` → `'pi'`.
- New: mapper `runner: $step['runner'] ?? null` → factory `$runner ?? ChainStepVo::DEFAULT_RUNNER` → `'pi'`.
- Подтверждено тестом `createAgentUsesDefaultRunnerPiWhenRunnerMissing` + `mapAgentComputesRunnerExplicitFromKeyPresence`.

### B.2. timeout=120 при отсутствии — **PASS**
- Original: `$step['timeout_seconds'] ?? 120` → `120`.
- New: mapper `timeoutSeconds: $step['timeout_seconds'] ?? null` → factory `$timeoutSeconds ?? ChainStepVo::DEFAULT_TIMEOUT_SECONDS` → `120`.
- Подтверждено тестами `createToolUsesDefaultTimeout120WhenMissing`, `createQualityGateUsesDefaultTimeout120WhenMissing`.

### B.3. runner: pi → explicit=true; runner: null → explicit=true — **PASS**
- Original: `runnerExplicit: array_key_exists('runner', $step)` — key presence.
- New: `runnerExplicit: array_key_exists('runner', $step)` — идентично, вычисление перенесено из helper в mapper.
- `runner: null` → key present → explicit=true, фактический runner `'pi'` через VO default. Подтверждено тестами `createAgentPreservesExplicitNullRunnerAsPiAndExplicit`, `mapAgentComputesRunnerExplicitTrueForNullRunnerKey`.

### B.4. Наследование retry_policy и no_context_files (false ≠ отсутствие) — **PASS (с REMARK по edge case)**
- **retry_policy**: `step ?? chain` сохранён, `null`/`[]` → `null` → наследование. Поведение идентично для всех случаев включая `retry_policy: null` (extractRetryPolicy использует `?? null`, тождественно absent).
- **no_context_files** для ключа absent / `true` / `false`: поведение идентично. `false` перекрывает chain `true` — подтверждено тестом `createAgentStepNoContextFilesFalseOverridesChainTrue`.
- ⚠️ **REMARK (edge case)**: для YAML `no_context_files: null` поведение изменилось.
  - Original: `(bool)($step['no_context_files'] ?? $chainNoContextFiles)` → `null ?? chain` → **наследует chain**.
  - New: `extractNoContextFiles` — `array_key_exists` = true → `(bool)null` = `false` → **false** (не наследует).
  - Подтверждено независимым PHP-тестом: `no_context_files=null, chain=true` → original `true`, new `false`.
  - Риск: минимальный (`no_context_files: null` в YAML семантически бессмысленно и нереалистично). Тестового покрытия для этого edge case нет.
  - Asymmetry-источник: `extractRetryPolicy` использует `?? null` (null-value == absent), `extractNoContextFiles` использует `array_key_exists` (null-value ≠ absent). Семантически новая логика *более консистентна* с runnerExplicit (key-presence), но формально — отклонение от zero-change для этого edge case.

### B.5. Сообщения исключений byte-to-byte — **PASS (с REMARK по 1 edge case)**
Сверены все 6 сообщений с оригиналом c8f2789:

| # | Сообщение | Статус |
|---|-----------|--------|
| 1 | `Step "type" is required in chain "%s" (expected: agent, quality_gate or tool).` | **идентично** (mapper) |
| 2 | `Tool step must have "command" in chain "%s".` | **идентично** (factory) |
| 3 | `Tool step must have "label" in chain "%s".` | **идентично** (factory) |
| 4 | `quality_gate step must have "command" in chain "%s".` | **идентично** (factory) |
| 5 | `quality_gate step must have "label" in chain "%s".` | **идентично** (factory) |
| 6 | `Agent step "role" is required in chain "%s".` | **частично** — см. ниже |

- ⚠️ **REMARK (edge case)**: для YAML `role: ''` (явная пустая строка, не null/absent):
  - Original: `$step['role'] ?? throw` не срабатывает (т.к. `''` не null) → передаёт `''` в `ChainStepVo::createAgent` → конструктор бросает `'Agent step must have a role.'` (без имени цепочки).
  - New: mapper передаёт `role: ''` → factory `if ($role === '')` → бросает `'Agent step "role" is required in chain "%s".'` (с именем цепочки).
  - Риск: минимальный (`role: ''` в YAML нереалистично; ни один существующий тест через YAML-loader не покрывает этот случай). Новое сообщение *более информативное* (включает имя цепочки), но формально — отклонение.
  - Покрытие: тест `createAgentRequiresRoleWithCurrentMessage` тестирует `role: ''` на уровне фабрики (после extraction), но не различает YAML-источник. `ChainStepVoTest:46,55` покрывают `'Agent step must have a role.'` напрямую через конструктор VO — эти тесты не затронуты.
  - Для YAML `role` absent / `role: null`: оба дают `'Agent step "role" is required in chain "%s".'` (helper бросает в `?? throw` для null/absent, factory бросает для `''`). **Идентично.**

### B.6. config/chains.yaml не сломан — **PASS**
- 1017 интеграционных и unit-тестов проходят, включая `StaticChainIntegrationTest`, `ConditionalChainIntegrationTest`, `DynamicChainIntegrationTest`, `YamlChainLoaderTest`, `YamlChainLoaderToolStepTest`.
- Все 4 call-site в `YamlChainLoaderService` мигрированы на DI (diff подтверждает замену `ChainStepParserHelper::parseRetryPolicy` → `$this->yamlRetryPolicyMapper->mapToChainRetryPolicy` и `parseSteps` → `$this->yamlChainStepMapper->mapToChainSteps` в `parseStaticChain` и `parseConditionalChain`).

---

## C. Конвенции factory.md для ChainStepFactory

| # | Пункт чек-листа | Результат | Примечание |
|---|----------------|-----------|------------|
| C.1 | Постфикс `Factory`, правильный слой | **PASS** | `Domain\Factory\ChainStepFactory` |
| C.2 | Возвращает типизированные объекты | **PASS** | `ChainStepVo` во всех 3 методах |
| C.3 | Нет БД/I/O | **PASS** | pure, только создание VO |
| C.4 | Исключения при нарушении инвариантов | **PASS** | `InvalidArgumentException` с backward-compatible сообщениями |
| C.5 | Зависимости через конструктор | **PASS** | нет зависимостей (stateless factory — допустимо) |
| C.6 | Логика создания отделена от бизнес-логики | **PASS** | merge-rule + defaults — creation policy per factory.md |
| C.7 | Unit-тесты при ветвлениях | **PASS** | 24 теста покрывают все ветви |
| — | Класс `final` | **PASS** | `final class` |
| — | Класс `readonly` | **REMARK** | ⚠️ Отсутствует `readonly`. Precedent в этом же PR — `ChainDefinitionFactory` объявлен `final readonly class`. Пример в `factory.md` — `final readonly class ToolVoFactory`. `ChainStepFactory` не имеет mutable state → `readonly` применим и ожидаем по консистентности с precedent/конвенцией. |

### C.5*. Принимает typed primitives/VO (не raw arrays) — **PASS**
- Все параметры: `string`, `?string`, `bool`, `?int`, `?ChainRetryPolicyVo`, `?ConditionExpressionVo`. Массивы отсутствуют в сигнатуре. Соответствует `factory.md` «Массивы запрещены».

---

## D. Конвенции mapper.md для обоих мапперов

### YamlChainStepMapper

| # | Пункт чек-листа | Результат | Примечание |
|---|----------------|-----------|------------|
| D.1 | Постфикс `Mapper`, правильный слой | **PASS** | `Infrastructure\Mapper\Chain\YamlChainStepMapper` |
| D.2 | `final` + `readonly` | **PASS** | `final readonly class` |
| D.3 | Возвращает значение, не `void` | **PASS** | `list<ChainStepVo>` / `ChainStepVo` |
| D.4 | Shape PHPDoc для массивов | **REMARK** | ⚠️ `mapToChainSteps`: `@param list<array<string, mixed>> $stepsData` — не shape. Private-методы `mapToolStep`/`mapQualityGateStep`/`mapAgentStep`: `@param array<string, mixed> $step`. `extractRetryPolicy` имеет корректный shape `array{max_retries?: int, ...}|null`. ADR Локи (риск #3) явно признаёт это допустимым компромиссом для deeply-variable YAML shape; shape для `$step` был бы избыточно сложным. |
| D.5 | Нет БД/I/O | **PASS** | pure |
| D.6 | DI через конструктор | **PASS** | `ChainStepFactory`, `YamlRetryPolicyMapper` |
| D.7 | Логика преобразования отделена от бизнес-логики | **PASS** | type-dispatch как mapping discriminator, no defaults/merge |
| D.8 | Unit-тесты | **PASS** | 10 тестов |

### YamlRetryPolicyMapper

| # | Пункт чек-листа | Результат | Примечание |
|---|----------------|-----------|------------|
| D.1 | Постфикс `Mapper`, слой | **PASS** | `Infrastructure\Mapper\Chain\YamlRetryPolicyMapper` |
| D.2 | `final` + `readonly` | **PASS** | `final readonly class` |
| D.3 | Не `void` | **PASS** | `?ChainRetryPolicyVo` |
| D.4 | Shape PHPDoc | **PASS** | `@param array{max_retries?: int, initial_delay_ms?: int, max_delay_ms?: int, multiplier?: float}|null` |
| D.5–D.8 | | **PASS** | нет зависимостей, no I/O, no business logic, 4 теста |

---

## E. Потенциальные дефекты / риски

### E.1. Deptrac-совместимость — **PASS**
- `vendor/bin/deptrac`: **0 violations**.
- `ChainStepFactory` (Domain) → `ChainStepVo`/`ChainRetryPolicyVo`/`ConditionExpressionVo` (DomainVo) — разрешено ruleset `Domain`.
- `YamlChainStepMapper` (Infrastructure) → `ChainStepFactory` (Domain), `ChainStepTypeEnum` (DomainEnum), DomainVo's, `YamlRetryPolicyMapper` (Infrastructure) — разрешено ruleset `Infrastructure`.
- `YamlRetryPolicyMapper` (Infrastructure) → `ChainRetryPolicyVo` (DomainVo) — разрешено.
- Запрещённого направления `Domain → Infrastructure` нет.

### E.2. Autowiring — **PASS**
- `config/services.yaml`: resource `%task_orchestrator.package_dir%/src/*` покрывает все 3 новых класса.
- Excludes не исключают `Domain/Factory` (→ `ChainStepFactory` auto-discovered), `Infrastructure/Mapper` (→ оба mapper'а auto-discovered).
- `ChainStepFactory` — нет constructor deps; `YamlChainStepMapper` — deps `ChainStepFactory` + `YamlRetryPolicyMapper` (оба autowireable); `YamlRetryPolicyMapper` — нет deps.
- `YamlChainLoaderService` constructor расширен двумя readonly-свойствами — autowireable.

### E.3. Edge cases в тестах — **PASS (с REMARK)**
- Покрыты: все 3 типа шагов, defaults, explicit/non-explicit/null runner, retry step/chain/both-null, no_context_files inherit/override/false-overrides-true, timeout default/explicit, guards, when/post_step null.
- ⚠️ **REMARK**: edge case `no_context_files: null` не покрыт тестом и имеет behavioral change (см. B.4).
- ⚠️ **REMARK**: edge case `role: ''` через YAML-path не покрыт (изменение сообщения, см. B.5).

### E.4. Дублирование с ChainDefinitionFactory — **PASS**
- `ChainStepFactory` создаёт `ChainStepVo` (step-level, знает step defaults/merge/invariants).
- `ChainDefinitionFactory` создаёт `StaticChainDefinitionVo`/`ConditionalChainDefinitionVo`/`DynamicChainDefinitionVo` (chain-level, знает cross-step fix_iterations через Specification).
- Области не пересекаются, как требует ADR (раздел «Совместимость»).

### E.5. Psalm-типы — **PASS (src/) / REMARK (tests/)**
- `vendor/bin/psalm` (src/): **No errors found!**
- ⚠️ **REMARK (test-only, вне Psalm-config)**: при сканировании `tests/` Psalm находит 5 issues:
  - `MissingOverrideAttribute` × 3: `setUp()` в `ChainStepFactoryTest:21`, `YamlChainStepMapperTest:21`, `YamlRetryPolicyMapperTest:18` — отсутствует `#[Override]`. В проекте 13/61 тестов используют `#[Override]` на setUp — тренд к adoption есть, но не enforced.
  - `PossiblyNullReference` × 2: `ChainStepFactoryTest:135` (`$step->getRetryPolicy()->getMaxRetries()` после assertNotNull — Psalm не сужает через повторный method-call), `YamlChainStepMapperTest:173` (`$steps[0]->getWhen()->getPath()` без assertNotNull). Фикс — локальная переменная. Не влияет на build (tests/ вне Psalm-config).

### E.6. PHPDoc — **PASS**
- Все публичные методы имеют полные PHPDoc с `@param`, `@return`, `@throws`. Class-level PHPDoc описывает ответственность и ссылается на конвенции.

### E.7. Утечка business-логики в маппер — **PASS**
- Mapper не содержит defaults, merge rules или invariant checks. Только extraction, key-presence, type-dispatch (mapping discriminator), delegation.
- Вся creation-policy (defaults via VO constants, `step ?? chain`, guards) — в `ChainStepFactory`.

### E.8. Тесты реальны (не tautological) — **PASS**
- Все assertions проверяют observable state VO (`getRunner()`, `hasExplicitRunner()`, `getRetryPolicy()`, `hasNoContextFiles()`, `getTimeoutSeconds()`, etc.) или exception messages.
- Нет assertSame на same-input-same-output без промежуточной логики.

---

## F. Качество тестов

### F.1. Все ветвления покрыты — **PASS**
- Factory: type-dispatch (3), runner default/explicit/null (4), retry inheritance (3), no_context_files inheritance (4), timeout default/explicit (4), guards role/command/label (6), output_key/when/post_step presence (3).
- Mapper: type-dispatch (3 — agent/tool/qg), missing/unknown type (2), runnerExplicit key-presence (3), retry passing (1), no_context_files presence (1), tool fields (1), qg when (1).
- Retry: null/empty/full/partial (4).

### F.2. Backward-compat кейсы помечены — **PASS**
- `createAgentPreservesExplicitNullRunnerAsPiAndExplicit` — комментарий «Backward-compatible case: ключ runner присутствует со значением null».
- `mapAgentComputesRunnerExplicitTrueForNullRunnerKey` — комментарий «Backward-compatible case: ключ runner присутствует со значением null → explicit=true».
- `createAgentStepNoContextFilesFalseOverridesChainTrue` — комментарий «false !== null — отличие явного false от отсутствия ключа».

### F.3. Guard-сообщения проверяются — **PASS**
- `expectExceptionMessage` с полными сообщениями включая chain name во всех 6 guard-тестах (role × 1, command × 2, label × 2, type × 2).

### F.4. Нет хрупких тестов — **PASS**
- Нет FS-зависимостей, временных зависимостей, network-зависимостей.
- Все данные — inline arrays/primitives в тест-методах.
- Tests создаются через `new ChainStepFactory()` / `new YamlChainStepMapper(...)` напрямую (mapper.md разрешает ручное создание в тестах).

---

## Сводка REMARK-ов (не блокирующие)

1. **ChainStepFactory missing `readonly`** — precedent `ChainDefinitionFactory` в этом же PR объявлен `final readonly`. Добавить `readonly` для консистентности с `factory.md` example и precedent. (C, низкий приоритет)

2. **`no_context_files: null` edge case — behavioral change** — original наследует chain, new возвращает `false`. Нереалистичный YAML-вход, нет тестового покрытия. (B.4, E.3, низкий приоритет — либо задокументировать как intentional improvement, либо выровнять с runnerExplicit-семантикой осознанно)

3. **`role: ''` edge case — изменённое сообщение исключения** — original `'Agent step must have a role.'` (через VO constructor), new `'Agent step "role" is required in chain "%s".'` (через factory). Нереалистичный YAML-вход. (B.5, E.3, низкий приоритет — новое сообщение более информативно)

4. **Shape PHPDoc для `$step` arrays** — `array<string, mixed>` вместо `array{...}`. ADR Локи (риск #3) признаёт допустимым компромиссом. (D.4, info)

5. **Test-only Psalm issues (5)** — `MissingOverrideAttribute` × 3, `PossiblyNullReference` × 2. Вне `vendor/bin/psalm` (src/-only). (E.5, info)

---

## Проверки (перепроверены независимо)

| Проверка | Результат |
|----------|-----------|
| `vendor/bin/phpunit` (все тесты) | **1017 tests, 2848 assertions, OK** |
| `vendor/bin/phpunit tests/Unit/Domain/Factory/ChainStepFactoryTest.php tests/Unit/Infrastructure/Mapper/Chain/` | **36 tests, OK** |
| `vendor/bin/phpunit tests/Unit/Infrastructure/Service/Chain/ tests/Unit/Domain/ValueObject/ChainStepVoTest.php` | **149 tests, OK** |
| `vendor/bin/psalm` (src/) | **No errors found!** (171 info) |
| `vendor/bin/deptrac analyse` | **0 violations, 0 errors, 0 warnings** |

---

## Финальный вердикт

# ✅ APPROVE

Реализация строго соответствует мини-ADR Локи по всем 6 развилкам, проходит все чек-листы `factory.md`/`mapper.md`/`helper.md` (удаление helper корректно, новых helper-нарушений нет), сохраняет поведение для всех реалистичных YAML-входов, и подтверждается зелёными PHPUnit (1017/1017), Psalm (src/ — no errors) и Deptrac (0 violations). Пять выявленных замечаний — REMARK-уровня (низкий приоритет): один cosmetic (`readonly` на factory), два — edge-case behavioral deviations для нереалистичных YAML-входов (`no_context_files: null`, `role: ''`), два — test-only Psalm issues вне стандартного конфига. Ни одно не нарушает хард-конвенцию, не ломает сборку и не блокирует merge.

Рекомендации (опционально, на усмотрение автора, вне скоупа блокировки):
- Добавить `readonly` в `final class ChainStepFactory` → `final readonly class ChainStepFactory` (1 строка, консистентность с precedent).
- Для полноты zero-change: добавить тест-кейс `no_context_files: null` (чтобы зафиксировать текущее поведение как intentional или выявить regression).

---

## Реакция Тимлида на REMARK-и (2026-06-15, post-review)

Тимлид Алекс принял решения по всем 5 REMARK-ам после ревью:

| REMARK | Решение Тимлида | Обоснование |
|---|---|---|
| 1. `readonly` на `ChainStepFactory` | **ПРИМЕНЕНО** | Консистентность с precedent `ChainDefinitionFactory` (`final readonly`) и примером `factory.md`. Тесты зелёные. |
| 2. `no_context_files: null` — behavioral change | **ВЫРОВНЯНО (zero-change восстановлен)** | `extractNoContextFiles` изменён: null-value трактуется как absent (`?? null` вместо `array_key_exists`). Сравнение с `runnerExplicit` из ревью — ложная аналогия: `runnerExplicit` — мета-флаг (key-presence уместен), `no_context_files` — конфигурационное значение (null-value = absent — стандартная PHP-семантика). Устранена асимметрия с `extractRetryPolicy`. Добавлен тест `mapAgentTreatsNullNoContextFilesAsAbsentAndInheritsChain`. |
| 3. `role: ''` — изменённое сообщение | **ПРИНЯТО как intentional improvement** | Новое сообщение (с именем цепочки) информативнее и соответствует `factory.md` (фабрика централизует guard-инварианты). Восстанавливать zero-change означало бы делегировать проверку в VO — нарушение `factory.md`. Тест `createAgentRequiresRoleWithCurrentMessage` уже покрывает `role: ''` — добавлен комментарий с обоснованием решения. |
| 4. Shape PHPDoc для `$step` arrays | **Оставлено как есть** | ADR Локи (риск #3) признал допустимым компромиссом для deeply-variable YAML shape. |
| 5. Test-only Psalm issues | **Оставлено как есть** | Вне стандартного конфига `vendor/bin/psalm` (src/-only). Не влияет на build. |

**Итоговый статус после правок:** PHPUnit 1018 tests / 2849 assertions OK, Psalm src/ — no errors, PHPMD — no violations (baseline неизменен), Deptrac — 0 violations. REMARK 2 устранён (zero-change полностью восстановлен для всех edge cases), REMARK 1 применён, REMARK 3 задокументирован как intentional.
