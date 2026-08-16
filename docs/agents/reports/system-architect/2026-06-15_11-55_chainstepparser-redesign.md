# Мини-ADR: редизайн `ChainStepParserHelper` без изменения поведения

**Роль:** Архитектор Локи  
**Дата:** 2026-06-15  
**Объект:** `src/Module/ChainDefinition/Infrastructure/Service/Chain/Helper/ChainStepParserHelper.php`, 4 call sites в `YamlChainLoaderService`, связка `ChainStepVo` → `ExecutionStepVo`  
**Задача:** PR #261 (`epic refactor/phpmd-baseline-elimination`): спроектировать рефакторинг одного helper-класса в mini-ADR формате, с обязательным `ZERO behavioral change`.

---

## Цель рефакторинга

Убрать из `ChainStepParserHelper` смешение helper-подхода с доменной сборкой `ChainStepVo`: текущий класс одновременно парсит YAML-DSL, диспетчеризует типы шагов, применяет дефолты, наследует настройки цепочки на шаг и вычисляет семантический флаг `runnerExplicit`. Цель — сохранить внешний контракт YAML и runtime-поведение, но разделить raw YAML mapping (техническое преобразование) и доменную сборку VO (defaults, invariants, merge rules) по конвенциям `helper.md`, `factory.md`, `mapper.md`, `layers/layers.md` и precedent `ChainDefinitionFactory` + `FixIterationsReferenceIntegritySpecification`.

---

## 1. Развилка: основной паттерн и слой

### Варианты

1. Оставить `ChainStepParserHelper` и «почистить» методы внутри.  
2. Сделать один `Infrastructure\Factory\Chain\YamlChainStepFactory`, который принимает raw YAML arrays и возвращает `ChainStepVo`.  
3. Разделить на `Infrastructure\Mapper\Chain\YamlChainStepMapper` для raw YAML shape mapping и `Domain\Factory\ChainStepFactory` для создания `ChainStepVo`.

### **Выбор: вариант 3 — тонкий Infrastructure Mapper + Domain Factory через DI**

- `Domain\Factory\ChainStepFactory` — основной паттерн refactor-а. Он создаёт объект своего слоя (`ChainStepVo`) и централизует инварианты/сложность сборки, что прямо соответствует `docs/conventions/core-patterns/factory.md`: factory нужен, когда одного конструктора недостаточно и требуется централизовать проверку инвариантов/сложность создания.
- `Infrastructure\Mapper\Chain\YamlChainStepMapper` — техническая граница YAML array shape → вызовы доменной фабрики. Это не helper и не доменная фабрика: он знает ключи YAML (`type`, `runner`, `retry_policy`, `timeout_seconds`) и только извлекает/нормализует форму внешнего формата.
- DI вместо static: `factory.md` требует подключать фабрики через DI; `mapper.md` также ожидает получение mapper-а из DI. Static helper удаляется.

### Почему не вариант 1

`helper.md` запрещает бизнес-логику в helper-ах и требует один узкий контекст. Текущий класс уже провалил аудит: `parseRetryPolicy` — отдельный публичный контракт, а defaults/merge/runnerExplicit — не техническая утилита.

### Почему не вариант 2

`Infrastructure` по `docs/conventions/layers/infrastructure.md` не должен содержать бизнес-логику. Если вся логика останется в `Infrastructure\Factory`, туда переедут defaults, merge и инварианты DSL. Кроме того, `factory.md` говорит размещать фабрику в слое создаваемых объектов: `ChainStepVo` — Domain VO, значит авторитетная фабрика должна быть в `Domain\Factory`.

### Граница helper-а

Чистый helper оставлять не нужно. Если в реализации очень захочется оставить микрометоды вида `nonEmptyStringOrNull()`, они должны быть private methods внутри `YamlChainStepMapper`, а не отдельный `Helper`: это не переиспользуемая утилита, а деталь mapping-а.

### Риски

- `YamlChainStepMapper` всё ещё будет иметь type-dispatch по `ChainStepTypeEnum`; это допустимо как mapping discriminator внешнего DSL, но требует прямых unit-тестов.
- Нужно не превратить `ChainStepFactory` во вторую `ChainDefinitionFactory`: первая создаёт step VO, вторая — chain definition VO.

---

## 2. Развилка: дефолты `runner='pi'` и `timeout=120`

### Варианты

1. Новый `Domain\ValueObject\ChainStepDefaultsVo`.  
2. `Domain\Specification` для дефолтов.  
3. Явные константы/дефолты в `ChainStepFactory`.  
4. Сохранить источник дефолтов в `ChainStepVo` constructor/static factories, добавив именованные constants при реализации.

### **Выбор: вариант 4 — `ChainStepVo` остаётся источником object-level defaults; фабрика делегирует или ссылается на именованные constants**

Рекомендуемая реализация: добавить в `ChainStepVo` именованные constants, например `DEFAULT_RUNNER = 'pi'`, `DEFAULT_TIMEOUT_SECONDS = 120`, и использовать их в constructor/static factory defaults. `ChainStepFactory` не должен писать magic literals `120`/`'pi'` заново; он либо не передаёт значение, либо использует constants `ChainStepVo`.

### Обоснование по конвенциям

- `value-object.md`: VO инкапсулирует инварианты, нормализацию и простые операции над своими значениями. Дефолт runner/timeout — object-level default шага, уже фактически находится в `ChainStepVo`.
- `factory.md`: фабрика отвечает за создание, но не должна становиться отдельным хранилищем доменной политики, если эта политика естественно принадлежит создаваемому VO.
- `specification.md`: спецификация возвращает только `bool`; дефолты не являются predicate-правилом, значит Specification здесь неверный паттерн.
- `ChainStepDefaultsVo` переусложняет: у него нет самостоятельной value identity, нет инварианта и нет вариативности значений в runtime.

### Риски

- В `ExecutionStepVo` уже есть fallback `runnerExplicit ?? $runner !== 'pi'`. Это отдельный модуль и отдельный VO; менять его в этом refactor-е не нужно, чтобы не расширять scope. Нормальный поток всё равно передаёт `runnerExplicit` через mapper, поэтому fallback остаётся только backward-compatible страховкой для прямой сборки `ExecutionStepVo`.
- Если позже появится не-`pi` default runner, понадобится отдельный cross-module ADR, потому что ChainDefinition и ChainExecution сейчас имеют локальные defaults.

---

## 3. Развилка: `parseRetryPolicy`

### Варианты

1. Вынести в отдельный parser/mapper.  
2. Оставить вспомогательным методом `ChainStepFactory`.  
3. Inline в `YamlChainLoaderService`.

### **Выбор: вариант 1 — отдельный `Infrastructure\Mapper\Chain\YamlRetryPolicyMapper`**

`retry_policy` используется на уровне цепочки (`YamlChainLoaderService`) и на уровне agent-step (`YamlChainStepMapper`). Это самостоятельный raw YAML → `ChainRetryPolicyVo|null` mapping, а не часть «создания шага».

Предлагаемый контракт:

- `mapToChainRetryPolicy(?array $raw): ?ChainRetryPolicyVo`
- `null` и `[]` → `null`
- непустой array → `ChainRetryPolicyVo::createFromArray($raw)`

### Обоснование по конвенциям

- `helper.md`: отдельная публичная ответственность не должна жить в step helper-е.
- `mapper.md`: массивы допустимы в Infrastructure как контракт внешнего формата, при условии shape PHPDoc и отсутствия бизнес-процессов.
- `factory.md`: `ChainStepFactory` должен создавать `ChainStepVo`, а не выступать универсальным парсером YAML-секций.

### Почему не inline

Inline в двух местах `YamlChainLoaderService` вернёт дублирование и утолщит loader, который уже отвечает за I/O (`Yaml::parseFile`), caching и chain-level orchestration.

### Риски

- `ChainRetryPolicyVo::createFromArray()` сам содержит defaults retry policy (`3/1000/30000/2.0`). Это существующая модель поведения, не предмет текущего refactor-а.

---

## 4. Развилка: `runnerExplicit`

### Варианты

1. Вычислять в `ChainStepFactory` сравнением `$runner !== 'pi'`.  
2. Вычислять в `YamlChainStepMapper` по наличию ключа `array_key_exists('runner', $step)` и передавать bool в Domain factory.  
3. Убрать флаг и положиться на `ExecutionStepVo` fallback `$runner !== 'pi'`.

### **Выбор: вариант 2 — key presence вычисляется в YAML mapper, доменная фабрика только переносит факт в `ChainStepVo`**

Причина: корректная семантика текущего поведения — «runner явно задан в YAML», а не «runner отличается от default». Поэтому `runner: pi` должен давать `hasExplicitRunner() === true`, а шаг без `runner` — `false` при том же фактическом runner `pi`.

`YamlChainStepMapper` — единственное место, где доступна информация о наличии ключа. Он вычисляет технический факт:

- key отсутствует → `runnerExplicit=false`
- key присутствует, даже `runner: pi` или `runner: null` → `runnerExplicit=true` (как сейчас в helper-е)

`ChainStepFactory` принимает `bool $runnerExplicit` и передаёт его в `ChainStepVo::createAgent(..., runnerExplicit: $runnerExplicit)` без повторного сравнения со строкой `pi`.

### Связка с `ExecutionStepVo`

`ChainExecutionDefinitionMapperService` должен продолжать передавать `runnerExplicit: $step->hasExplicitRunner()` в `ExecutionStepVo`. Тогда `ExecutionStepVo:37` остаётся fallback-логикой для прямого создания execution VO, но не участвует в штатном YAML path.

### Почему не варианты 1 и 3

- Сравнение `$runner !== 'pi'` ломает `runner: pi` как explicit override — это behavioral change.
- Убрать флаг нельзя: текущая система различает «не задан, default pi» и «явно задан pi».

### Риски

- `runner: null` сохранит текущую странность: фактический runner будет `pi`, но explicit=true. Это выглядит подозрительно, но менять нельзя из-за `ZERO behavioral change`.

---

## 5. Развилка: наследование `step ?? chain` для `retry_policy` и `no_context_files`

### Варианты

1. Оставить merge в `YamlChainLoaderService`.  
2. Делать merge в `YamlChainStepMapper`.  
3. Делать merge в `Domain\Factory\ChainStepFactory` на typed inputs.

### **Выбор: вариант 3 — merge rule принадлежит `ChainStepFactory`**

`YamlChainStepMapper` должен передавать в фабрику typed values:

- `?ChainRetryPolicyVo $stepRetryPolicy`
- `?ChainRetryPolicyVo $chainRetryPolicy`
- `?bool $stepNoContextFiles` (`null`, если ключ отсутствует)
- `bool $chainNoContextFiles`

`ChainStepFactory` применяет правило:

- `effectiveRetryPolicy = $stepRetryPolicy ?? $chainRetryPolicy`
- `effectiveNoContextFiles = $stepNoContextFiles ?? $chainNoContextFiles`

### Обоснование по конвенциям

- Это не Infrastructure logic: `Infrastructure` не должна содержать бизнес-правила (`layers/infrastructure.md`).
- Это не Specification: правило возвращает значение, а не `bool`.
- Это creation policy для `ChainStepVo`, поэтому соответствует `factory.md`: фабрика скрывает сложность сборки и централизует правила создания.

### Риски

- Нужно аккуратно сохранить отличие `false` от `null` для `no_context_files`: `false` на шаге обязан перекрывать `true` на цепочке. Нельзя писать `(bool)($step['no_context_files'] ?? $chainNoContextFiles)` в mapper-е — это опять спрятанный merge.

---

## 6. Развилка: unit-тесты

### Варианты

1. Оставить косвенные `YamlChainLoaderTest`.  
2. Добавить только tests для mapper-а.  
3. Добавить прямые tests для factory + mapper + retry mapper, а loader-тесты оставить как smoke/backward compatibility.

### **Выбор: вариант 3 — прямое покрытие всех ветвлений**

### Обоснование по конвенциям

- `helper.md`, `factory.md`, `mapper.md`: unit-тесты обязательны при ветвлениях, вариантах создания и исключениях.
- Текущий класс имеет type-dispatch, defaults, merge и invalid input branches. Косвенного покрытия через файловый YAML loader недостаточно.

### Риски

- Тесты могут начать закреплять текущие странности (`runner: null` explicit=true). Это правильно для refactor-а с `ZERO behavioral change`, но такие cases нужно пометить как backward compatibility, а не как желаемую новую семантику.

---

## Итоговая структура

### Создать

- `src/Module/ChainDefinition/Domain/Factory/ChainStepFactory.php` — Domain factory (через DI), создаёт `ChainStepVo` из typed primitives/VO, применяет defaults через `ChainStepVo`, guard-инварианты и merge `step ?? chain`.
- `src/Module/ChainDefinition/Infrastructure/Mapper/Chain/YamlChainStepMapper.php` — Infrastructure mapper, преобразует raw YAML step arrays в вызовы `ChainStepFactory`, делает type-dispatch, извлекает key presence для `runnerExplicit` и optional field presence для `no_context_files`.
- `src/Module/ChainDefinition/Infrastructure/Mapper/Chain/YamlRetryPolicyMapper.php` — Infrastructure mapper, преобразует raw YAML `retry_policy` в `ChainRetryPolicyVo|null`.

### Удалить

- `src/Module/ChainDefinition/Infrastructure/Service/Chain/Helper/ChainStepParserHelper.php` — удалить после переноса call sites и тестов.

### Изменить

- `YamlChainLoaderService` — заменить static calls:
  - `ChainStepParserHelper::parseRetryPolicy(...)` → `$this->yamlRetryPolicyMapper->mapToChainRetryPolicy(...)`
  - `ChainStepParserHelper::parseSteps(...)` → `$this->yamlChainStepMapper->mapToChainSteps(...)`
- `ChainStepVo` — опционально добавить named constants для `DEFAULT_RUNNER` и `DEFAULT_TIMEOUT_SECONDS`; поведение constructor/static factories не менять.
- `YamlChainLoaderTest` / `YamlChainLoaderToolStepTest` — убрать `CoversClass(ChainStepParserHelper::class)`, добавить/оставить backward compatibility smoke coverage.

### Поток данных

```text
YamlChainLoaderService
  ├─ читает YAML file и raw chain array
  ├─ YamlRetryPolicyMapper::mapToChainRetryPolicy(raw chain retry_policy)
  ├─ YamlChainStepMapper::mapToChainSteps(chainName, raw steps, chainRetryPolicy, chainNoContextFiles)
  │    ├─ dispatch по ChainStepTypeEnum из raw step['type']
  │    ├─ map raw retry_policy через YamlRetryPolicyMapper
  │    ├─ извлекает runnerExplicit = array_key_exists('runner', raw step)
  │    └─ вызывает ChainStepFactory::createAgent/createTool/createQualityGate(...)
  │         ├─ применяет default runner/timeout через ChainStepVo
  │         ├─ применяет retry/no_context_files inheritance
  │         └─ возвращает ChainStepVo
  └─ ChainDefinitionFactory::createFromSteps/createFromConditionalSteps(...)
```

### Совместимость с `ChainDefinitionFactory`

Фабрики не конкурируют:

- `ChainStepFactory` — создаёт один `ChainStepVo` и знает step-level defaults/merge/invariants.
- `ChainDefinitionFactory` — создаёт `StaticChainDefinitionVo`, `ConditionalChainDefinitionVo`, `DynamicChainDefinitionVo` и проверяет cross-step invariant `fix_iterations` через `FixIterationsReferenceIntegritySpecification`.

`ChainDefinitionFactory` не должен принимать raw YAML и не должен создавать отдельные steps из массивов.

---

## Список unit-тестов, которые надо создать

### `tests/Unit/Domain/Factory/ChainStepFactoryTest.php`

1. `createAgentUsesDefaultRunnerPiWhenRunnerMissing()` — фактический runner `pi`, `hasExplicitRunner=false`.
2. `createAgentPreservesExplicitPiRunner()` — runner `pi`, `runnerExplicit=true`.
3. `createAgentPreservesExplicitNullRunnerAsPiAndExplicit()` — backward-compatible case: raw key present with null → runner `pi`, explicit=true.
4. `createAgentRequiresRoleWithCurrentMessage()` — message совместим с текущим helper path: `Agent step "role" is required in chain "%s".`
5. `createAgentInheritsChainRetryPolicyWhenStepPolicyMissing()`.
6. `createAgentUsesStepRetryPolicyOverChainPolicy()`.
7. `createAgentLeavesRetryPolicyNullWhenBothMissing()`.
8. `createAgentInheritsNoContextFilesFromChainWhenStepMissing()`.
9. `createAgentStepNoContextFilesFalseOverridesChainTrue()` — критичный `false !== null` case.
10. `createToolUsesDefaultTimeout120WhenMissing()`.
11. `createToolUsesExplicitTimeout()`.
12. `createToolRequiresCommandWithCurrentMessage()`.
13. `createToolRequiresLabelWithCurrentMessage()`.
14. `createToolPreservesOutputKeyNameWhenPostStep()`.
15. `createQualityGateUsesDefaultTimeout120WhenMissing()`.
16. `createQualityGateRequiresCommandWithCurrentMessage()`.
17. `createQualityGateRequiresLabelWithCurrentMessage()`.
18. `createStepCreatesConditionExpressionFromWhenString()` / или отдельные cases для agent/tool/qg, если condition создаётся в factory.
19. `createStepIgnoresEmptyWhenAndEmptyPostStep()`.

### `tests/Unit/Infrastructure/Mapper/Chain/YamlChainStepMapperTest.php`

1. `mapToChainStepsDispatchesAgentToolQualityGate()`.
2. `mapToChainStepsThrowsOnMissingTypeWithCurrentMessage()`.
3. `mapToChainStepsThrowsOnUnknownTypeWithCurrentMessage()`.
4. `mapAgentComputesRunnerExplicitFromKeyPresence()` — без `runner` false, с `runner: pi` true.
5. `mapAgentComputesRunnerExplicitTrueForNullRunnerKey()` — backward compatibility.
6. `mapAgentPassesStepRetryPolicyToFactory()` / observable через returned VO.
7. `mapAgentPassesNoContextFilesPresenceAsNullOrBool()` — observable через returned VO.
8. `mapToolMapsOutputKeyTimeoutNameWhenPostStep()`.
9. `mapQualityGateMapsWhenExpression()`.

### `tests/Unit/Infrastructure/Mapper/Chain/YamlRetryPolicyMapperTest.php`

1. `mapNullReturnsNull()`.
2. `mapEmptyArrayReturnsNull()`.
3. `mapFullRetryPolicyCreatesVo()`.
4. `mapPartialRetryPolicyUsesExistingChainRetryPolicyVoDefaults()`.

### Обновить существующие tests

- `YamlChainLoaderTest` оставить для end-to-end YAML file smoke: chain-level retry inheritance, step override, no retry, defaults.
- `YamlChainLoaderToolStepTest` оставить как compatibility test для реального YAML-файла.

---

## Явное подтверждение `ZERO behavioral change`

- `runner='pi'` сохраняется: отсутствующий runner у agent-step по-прежнему даёт фактический runner `pi`. Источник default переносится из helper в `ChainStepVo`/`ChainStepFactory` path, без изменения значения.
- `timeout=120` сохраняется: tool/quality_gate без `timeout_seconds` по-прежнему получают `120`.
- `runnerExplicit` сохраняется: explicit определяется по наличию YAML-ключа `runner`, а не по значению. Поэтому `runner: pi` остаётся explicit=true, отсутствие runner — explicit=false; mapper в ChainExecution продолжает передавать `hasExplicitRunner()` в `ExecutionStepVo`.
- Наследование `retry_policy` сохраняется: step-level policy перекрывает chain-level, иначе agent-step наследует chain-level, иначе null.
- Наследование `no_context_files` сохраняется: step-level `true/false` перекрывает chain-level; отсутствие ключа наследует chain-level.
- `config/chains.yaml` не ломается: inline agent steps без runner продолжают работать и получают default `pi`.
- Сообщения исключений желательно сохранить byte-to-byte для уже существующих loader-тестов, особенно missing/unknown `type`, missing `role`, missing `command/label`.

---

## Слепые зоны и риски

1. **Deptrac**: `Infrastructure -> Domain\ValueObject` и `Infrastructure -> Domain\Factory` допустимы текущим imported ruleset: `Infrastructure` может зависеть от `Domain`, `DomainVo`, `DomainEnum`. `Domain\Factory\ChainStepFactory -> DomainVo/DomainEnum` тоже допустимо. Запрещённого направления `Domain -> Infrastructure` нет.
2. **services.yaml autowiring**: `TaskOrchestrator\Common\` resource покрывает `src/*`; excludes не исключают `Domain/Factory`, `Domain/Specification`, `Infrastructure/Mapper`. Новые mapper/factory попадут в autodiscovery; `YamlChainLoaderService` уже имеет явный `$yamlPath`, остальные constructor args autowire-ятся.
3. **Mapper convention vs raw arrays**: `mapper.md` разрешает arrays в Infrastructure для внешнего формата, но требует shape PHPDoc. Без shape-аннотаций Psalm будет видеть `array<string,mixed>` и риск ошибок останется.
4. **`ChainRetryPolicyVo::createFromArray()` содержит YAML array factory в Domain VO**: это уже существующее поведение. Не расширять scope; новый `YamlRetryPolicyMapper` только изолирует null/empty handling и call site.
5. **Дубли default runner между ChainDefinition и ChainExecution**: `ExecutionStepVo` сохраняет fallback `$runner !== 'pi'`. Это не новое дублирование и не штатный путь после mapper-а, но архитектурно остаётся техдолгом для отдельного ADR, если default runner когда-либо станет конфигурируемым.
6. **`runner: null`**: текущая семантика explicit=true при фактическом `pi` выглядит спорно. Менять нельзя: это breaking behavior относительно `array_key_exists('runner', $step)`.
7. **Соблазн утащить весь raw YAML parsing в Domain factory**: не делать. `factory.md` не любит raw arrays, а `Domain` не должен знать YAML DSL keys напрямую.
8. **Соблазн оставить мелкий helper**: не делать без необходимости. Старый helper был проблемой именно потому, что стал контейнером для разнородной логики.

---

## Решение

Рефакторить `ChainStepParserHelper` не в новый helper, а в связку `YamlChainStepMapper` + `ChainStepFactory` + `YamlRetryPolicyMapper`. Это сохраняет поведение, убирает static helper, соблюдает SRP и закрепляет прямыми unit-тестами все ветвления: type-dispatch, defaults, explicit flag, inheritance и invalid DSL inputs.
