# ADR-012: Конвенция конфигурации модулей

| Поле        | Значение                                                                  |
|-------------|---------------------------------------------------------------------------|
| Статус      | Принято (дополнено 2026-06-25: PHAR-переносимость, Вариант 4)             |
| Дата        | 2026-06-24 (дополнено 2026-06-25)                                         |
| Автор       | Архитектор (Гэндальф)                                                     |

## Контекст

`task-orchestrator` — CLI-библиотека (command-line interface — интерфейс командной строки) на Symfony 8.0 и PHP 8.4. Проект использует DDD (Domain-Driven Design — предметно-ориентированное проектирование), Clean Architecture (чистая архитектура) и модульную структуру `src/Module/<Name>`.

Конвенция `docs/conventions/modules/configuration.md` требует, чтобы модуль был самодостаточной единицей конфигурации:

- класс `src/Module/<Name>/<Name>Module.php` реализует `ModuleInterface`;
- модульная конфигурация сервисов лежит в `src/Module/<Name>/Resource/config/services.yaml`;
- модуль зарегистрирован в `config/modules.php`;
- параметры модуля именуются `module.<name>.*`.

Инфраструктура Path A уже создана и работает: `Kernel` использует `ModuleKernelTrait`, `config/modules.php` является реестром модулей, а `ModuleCompilerPass` загружает `Resource/config/services.yaml`. Пилотный модуль `GitIdentity` подтверждает этот путь: он имеет `GitIdentityModule`, модульный `Resource/config/services.yaml` и параметры `module.git_identity.*`.

Четыре старых модуля отстают от конвенции:

- `AgentRunner`;
- `ChainDefinition`;
- `ChainExecution`;
- `DynamicLoop`.

Их DI-конфигурация (Dependency Injection — внедрение зависимостей) пока централизована в `config/services.yaml`: interface aliases (алиасы интерфейсов), scalar arguments (скалярные аргументы), tagged iterators (итераторы по тегам) и module-specific tagging (модульное тегирование) объявлены в общем файле.

Проект не является web-приложением и не использует Doctrine ORM. Поэтому `TwigInterface`, `TranslationInterface` и Doctrine-части модульной системы остаются опциональными расширениями, а не обязательной частью базового рецепта.

## Решение

Выбран **Path A: привести код модулей к конвенции**.

`config/services.yaml` больше не должен быть местом хранения конфигурации конкретных доменных модулей. Каждый модуль сам владеет своей DI-конфигурацией через `Resource/config/services.yaml`, а `config/modules.php` становится единой точкой подключения модулей.

### Единый рецепт подключения модуля

Для каждого модуля `src/Module/<Name>` обязателен следующий набор:

1. `src/Module/<Name>/<Name>Module.php` реализует `ModuleInterface`.
2. `getModuleDir()` возвращает `__DIR__`.
3. `getModuleConfigPath()` возвращает `$this->getModuleDir() . '/Resource/config'`.
4. `src/Module/<Name>/Resource/config/services.yaml` содержит параметры и сервисы только этого модуля.
5. `config/modules.php` содержит запись `<Name>Module::class => ['all' => true]` или более узкую матрицу окружений.
6. Параметры модуля именуются только с префиксом `module.<name>.*`, где `<name>` — `snake_case` имя модуля:
   - `AgentRunner` → `module.agent_runner.*`;
   - `ChainDefinition` → `module.chain_definition.*`;
   - `ChainExecution` → `module.chain_execution.*`;
   - `DynamicLoop` → `module.dynamic_loop.*`;
   - `GitIdentity` → `module.git_identity.*`.
7. Параметр каталога модуля называется `module.<name>.module_dir` и для package-модулей задаётся от package root (корня пакета):

```yaml
parameters:
  module.<name>.module_dir: '%task_orchestrator.package_dir%/src/Module/<Name>'
```

8. Модульный `services.yaml` объявляет auto-discovery (автообнаружение сервисов) только своего namespace (пространства имён). В исходном Path A это делалось оператором `resource:`/`exclude:`:

```yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true

  TaskOrchestrator\Common\Module\<Name>\:
    resource: '%module.<name>.module_dir%/'
    exclude:
      - '%module.<name>.module_dir%/Domain/Entity/'
      - '%module.<name>.module_dir%/Domain/Enum/'
      - '%module.<name>.module_dir%/Domain/ValueObject/'
      - '%module.<name>.module_dir%/Application/Dto/'
      - '%module.<name>.module_dir%/Resource/'
      - '%module.<name>.module_dir%/<Name>Module.php'
```

Обязательный минимум `exclude` защищает контейнер от регистрации несервисных типов: Entity (сущность), Enum (перечисление), ValueObject (объект-значение), DTO (data transfer object — объект передачи данных), ресурсов модуля и класса модуля. Если в модуле есть дополнительные несервисные каталоги (`Application/Enum`, `Application/Event`, `Domain/Exception` и т.п.), они также добавляются в `exclude`.

> **Дополнение (Вариант 4, 2026-06-25):** операторы `resource:`/`exclude:` основаны на Symfony `GlobResource`, который не работает по путям `phar://` и ломал сборку PHAR (см. [PHAR-переносимость](#phar-переносимость-эволюция-auto-discovery-вариант-4)). Auto-discovery перенесён в программный PHAR-safe регистратор `ModuleServiceRegistrar`; namespace и `exclude`-пути теперь объявляются не в YAML, а в контракте модуля — `ModuleInterface::getServiceNamespace()` и `getServiceExcludePaths()` (базовый набор — константа `DEFAULT_SERVICE_EXCLUDE_PATHS`). Сами YAML-операторы `resource:`/`exclude:` из модульных `services.yaml` убраны.

### Что переносится из `config/services.yaml` в модульные файлы

В `src/Module/<Name>/Resource/config/services.yaml` переносится всё, что принадлежит конкретному модулю:

- interface aliases (алиасы интерфейсов) своего модуля: `Domain`/`Application` интерфейс → реализация из `Domain`, `Application`, `Integration` или `Infrastructure` этого же модуля;
- scalar arguments (скалярные аргументы) и module-specific parameters (параметры конкретного модуля);
- `_instanceof`-тегирование интерфейсов, если интерфейс и реализации принадлежат этому модулю;
- явные `tags` (теги) на сервисах, если сервис-реализация принадлежит модулю;
- tagged iterators (итераторы по тегам), которые инжектятся в сервисы этого модуля;
- явные фабрики, service maps (карты сервисов) и аргументы, которые нужны сервисам этого модуля.

Глобальные параметры `task_orchestrator.*` остаются параметрами ядра, но модуль не должен разбрасывать их напрямую по своим сервисам. В модуле вводится локальный параметр-обёртка `module.<name>.<context>`, который ссылается на `task_orchestrator.*` там, где это нужно:

```yaml
parameters:
  module.chain_definition.chains_yaml: '%task_orchestrator.chains_yaml%'
  module.chain_execution.roles_dir: '%task_orchestrator.roles_dir%'
  module.dynamic_loop.chains_session_dir: '%task_orchestrator.chains_session_dir%'
```

Так граница модуля остаётся явной: внешний источник параметра виден, но сервисы модуля зависят от `module.<name>.*`.

### Что должно остаться в общем `config/services.yaml`

В общем `config/services.yaml` остаются только package-level и cross-cutting (сквозные) настройки:

- импорт `config/console_services.yaml` для Presentation-слоя (слоя представления) CLI-приложения;
- `_defaults`, если они нужны для общих сервисов;
- auto-discovery общих компонентов вне `src/Module/*` (например, `src/Component/*`), если они не загружаются другим способом;
- глобальные aliases (алиасы), не принадлежащие конкретному модулю, например `Psr\Clock\ClockInterface` → `SystemClock`;
- общие параметры ядра `task_orchestrator.*`, которые задаются `Kernel` и используются как источник для модульных параметров;
- действительно глобальные integration glue (склейка интеграций), если у неё нет очевидного owning module (модуля-владельца).

В общем `config/services.yaml` не должно остаться блоков `# ─── <Module> module`, алиасов доменных интерфейсов конкретного модуля, скалярных аргументов сервисов конкретного модуля и тегирования, применимого только к одному модулю.

### Рецепт миграции для Бэкендера Левши

Миграция выполняется итеративно: **пилот `ChainDefinition` → `AgentRunner` → `ChainExecution` → `DynamicLoop`**. Такой порядок снижает риск: сначала переносится независимый модуль, затем движок agent runners (запускателей агентов), затем основной execution-модуль (модуль исполнения), затем зависимый dynamic loop (динамический цикл).

#### 1. Пилот: `ChainDefinition`

1. Создать `src/Module/ChainDefinition/ChainDefinitionModule.php` с `ModuleInterface`.
2. Создать `src/Module/ChainDefinition/Resource/config/services.yaml`.
3. Добавить в него:
   - `module.chain_definition.module_dir`;
   - `module.chain_definition.chains_yaml: '%task_orchestrator.chains_yaml%'`;
   - `module.chain_definition.base_path: '%task_orchestrator.base_path%'`;
   - auto-discovery namespace `TaskOrchestrator\Common\Module\ChainDefinition\` с обязательным `exclude`;
   - aliases из текущего блока `ChainDefinition module` в `config/services.yaml`;
   - аргументы `YamlChainLoaderService`, `YamlConnectivityRoleTargetProviderService`, `SymfonyConnectivityProcessRunnerService`, но через `module.chain_definition.*`.
4. Зарегистрировать `ChainDefinitionModule::class` в `config/modules.php`.
5. Удалить из общего `config/services.yaml` только перенесённый блок `ChainDefinition module`.
6. Проверить сборку контейнера и тесты. Если пилот зелёный, переносить остальные модули тем же шаблоном.

#### 2. `AgentRunner`

Перенести в `src/Module/AgentRunner/Resource/config/services.yaml`:

- `module.agent_runner.module_dir`;
- auto-discovery `TaskOrchestrator\Common\Module\AgentRunner\` с обязательным `exclude`;
- специальные исключения текущего root-конфига для `RetryingAgentRunnerService.php` и `CircuitBreakerAgentRunnerService.php`, если эти классы не должны регистрироваться как самостоятельные сервисы;
- `_instanceof` для `AgentRunnerInterface` → tag `agent.runner`;
- aliases `AgentRunnerInterface`, `PiAgentRunnerServiceInterface`, `AgentRunnerRegistryServiceInterface`, `MetricsCollectorInterface`, `RetryableRunnerFactoryInterface`;
- аргумент `$runners: !tagged_iterator agent.runner` для `AgentRunnerRegistryService`.

#### 3. `ChainExecution`

Перенести в `src/Module/ChainExecution/Resource/config/services.yaml`:

- `module.chain_execution.module_dir`;
- `module.chain_execution.roles_dir: '%task_orchestrator.roles_dir%'`;
- `module.chain_execution.base_path: '%task_orchestrator.base_path%'`;
- auto-discovery `TaskOrchestrator\Common\Module\ChainExecution\` с обязательным `exclude`;
- `_instanceof` для `ExecutionStrategyInterface` → tag `orchestrator.execution_strategy` для стратегий, реализованных внутри `ChainExecution`;
- `_instanceof` для `ExecuteStepServiceInterface` → tag `chain_execution.step_runner`;
- все aliases из текущего блока `ChainExecution module`;
- tagged iterator `$runners: !tagged_iterator chain_execution.step_runner` для `ResolveStepRunnerService`;
- service map (карта сервисов) `$mappers` для `ReportResultFactory`;
- аргументы `RolePromptBuilderService`, но через `module.chain_execution.*`;
- аргумент `$strategies: !tagged_iterator orchestrator.execution_strategy` для `OrchestrateChainCommandHandler`.

#### 4. `DynamicLoop`

Перенести в `src/Module/DynamicLoop/Resource/config/services.yaml`:

- `module.dynamic_loop.module_dir`;
- `module.dynamic_loop.chains_session_dir: '%task_orchestrator.chains_session_dir%'`;
- `module.dynamic_loop.base_path: '%task_orchestrator.base_path%'`;
- auto-discovery `TaskOrchestrator\Common\Module\DynamicLoop\` с обязательным `exclude`;
- все aliases из текущего блока `DynamicLoop module`;
- явный tag `orchestrator.execution_strategy` для `DynamicExecutionStrategy`, потому что реализация лежит в `DynamicLoop`, а контракт `ExecutionStrategyInterface` принадлежит `ChainExecution`;
- аргументы `ChainSessionLogger`, но через `module.dynamic_loop.*`.

#### 5. Финальная очистка

После переноса всех четырёх модулей:

1. Убрать из root auto-discovery покрытие `src/Module/*`, чтобы модульные `services.yaml` не дублировали определения сервисов.
2. Оставить в root auto-discovery только общие компоненты вне `src/Module/*`, если они нужны.
3. Проверить, что `config/modules.php` содержит `GitIdentity`, `AgentRunner`, `ChainDefinition`, `ChainExecution`, `DynamicLoop`.
4. Проверить, что в `config/services.yaml` нет блоков `# ─── <Module> module` и module-specific aliases/arguments.
5. Запустить `vendor/bin/phpunit`, `vendor/bin/psalm`, `make deptrac`.
6. Выполнить smoke-тест CLI (например, `bin/console --help` и `bin/task-orchestrator --help`, если entry point доступен в окружении).

## PHAR-переносимость: эволюция auto-discovery (Вариант 4)

### Контекст

Path A (см. «Решение») задумывался на стандартном Symfony-механизме `resource:`/`exclude:` auto-discovery. На практике он оказался непереносимым в собранный PHAR (PHP-архив, package-формат), и это было обнаружено только на CI `phar-smoke`.

### Проблема

Symfony-операторы `resource:`/`exclude:` реализованы поверх `Symfony\Component\Config\Resource\GlobResource` — glob-сканирования каталогов. PHP stream-wrapper для `phar://` **не поддерживает glob-операции**: `GlobResource` возвращает **0 файлов** по любому пути внутри `phar://`. Это фундаментальное ограничение stream-wrapper, а не баг Symfony. В результате в собранном PHAR auto-discovery модулей тихо становился пустым: ни один сервисный класс не регистрировался.

### Симптом

CI `phar-smoke` падал с `FileLocatorFileNotFoundException`: команды, запускаемые из собранного `task-orchestrator.phar`, не находили сервисы модулей на этапе autowire (автосвязывания). В dev-режиме (обычная файловая система) `GlobResource` работал корректно, поэтому регрессия проявлялась только в PHAR.

### Рассмотренные альтернативы

- **В1 «Откатить `resource:` в корневой `config/services.yaml`.»** Отклонена. Это даёт «ложнозелёный» dev/CI на обычной ФС, но PHAR остаётся пустым по той же причине (`GlobResource` не работает по `phar://`). Усугубляет проблему, маскируя её.
- **В2 «Self-extracting PHAR (самораспаковывающийся архив).»** Отклонена. Распаковка на старте добавляет runtime-latency (задержку запуска), плодит временные файлы и создаёт расхождение между dev и prod-средой (в dev файлы одни, в prod — распакованные копии).
- **В3 «Явная регистрация классов (перечисление FQCN каждого сервиса).»** Отклонена как основной путь. Это рабочее решение, но отступает от принципа auto-discovery: каждый новый сервис требует ручной правки списка, что сводит на нет выигрыш модульной системы и провоцирует «забыл добавить класс».
- **В4 (выбрана) «PHAR-safe регистратор + container-wide autoconfiguration.»** `dev = prod`, без runtime-распаковки, без правки composer/box-сборки.

### Решение (Вариант 4)

Две замены вместо операторов `resource:`/`exclude:` и module-local `_instanceof`.

**1. Программный auto-discovery через `ModuleServiceRegistrar`**
(`src/Component/ModuleSystem/DependencyInjection/ModuleServiceRegistrar.php`). Перечисление `*.php` через `RecursiveDirectoryIterator` (PHP SPL) — он **работает по `phar://`** (проверено эмпирически), в отличие от glob. Далее: FQCN-маппинг по PSR-4 namespace модуля, фильтрация несервисных типов (abstract/interface/trait/enum) и `exclude`-путей, регистрация каждого оставшегося класса как `autowired` + `autoconfigured` `Definition`.

Конфигурация регистратора — из самого модуля (единый источник истины), а не из YAML:

- `ModuleInterface::getServiceNamespace()` — PSR-4 префикс (для FQCN-маппинга);
- `ModuleInterface::getServiceExcludePaths()` — относительные пути для исключения;
- `ModuleInterface::DEFAULT_SERVICE_EXCLUDE_PATHS` — стандартный набор несервисных каталогов DDD-слоёв, который модули композируют со своими module-specific исключениями.

**2. Container-wide autoconfiguration вместо module-local `_instanceof`.**
Теги интерфейсов регистрируются в `Kernel::build()` через `ContainerBuilder::registerForAutoconfiguration()`:

- `AgentRunnerInterface` → `agent.runner`;
- `ExecutionStrategyInterface` → `orchestrator.execution_strategy`;
- `ExecuteStepServiceInterface` → `chain_execution.step_runner`.

Container-wide autoconfiguration применяется единообразно ко **всем** `autoconfigured`-сервисам, независимо от того, зарегистрированы они через регистратор или явным определением (explicit def), и независимо от того, в каком модуле лежит реализация. Это снимает историческое ограничение `_instanceof`, который действовал только в пределах своего файла и не тегировал cross-module реализации (например, `DynamicExecutionStrategy` в модуле `DynamicLoop` при контракте из `ChainExecution`).

### Package-root в PHAR: `getPackageDir()`

После внедрения регистратора вскрылась вторая, более глубокая проблема CWD-зависимости (current working directory — текущий рабочий каталог). Наследуемый `Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait`/`BaseKernel::getProjectDir()` ищет `composer.json`, поднимаясь от `Kernel.php`. В PHAR `composer.json` **не упакован** (Box его не кладёт), поэтому fallback даёт неверный `dirname(Kernel.php)` = `phar://.../src` вместо root PHAR.

Это ломало два места:

- `getModules()`: искал `phar://.../src/config/modules.php` (файла там нет — он в `phar://.../config/modules.php`) → возвращал `[]` → **0 модулей** → регистратор не вызывался → контейнер собирался «пустым» (hollow — полым) по модулям.
- параметр `task_orchestrator.package_dir` = `phar://.../src` → ломал все оставшиеся `resource:`-блоки (Component, apps/console).

Фикс: в `Kernel` введён `private function getPackageDir(): string { return dirname(__DIR__); }`. Для `src/Kernel.php` это даёт package root (каталог пакета с `src/`, `apps/`, `config/`) CWD-независимо:

- dev: `dirname('/path/src')` = `/path`;
- PHAR: `dirname('phar://x.phar/src')` = `phar://x.phar`.

`getPackageDir()` используется в `getModules()` и `getKernelParameters()` (для `task_orchestrator.package_dir`). Наследуемый `getProjectDir()` (нужен Symfony для `kernel.project_dir`, routing, cache) и `getProjectRoot()` (host-проект ролей/цепочек) не переопределяются. Доказано A/B-экспериментом: `getProjectDir()` → 0 модулей, `getPackageDir()` → 5 модулей.

### Расширение PHAR-safe регистрации за пределы доменных модулей

Помимо доменных модулей `src/Module/*`, операторы `resource:` использовались ещё в 4 местах — все ломались в PHAR по той же причине (`GlobResource` + `phar://`). После Варианта 4 они тоже переведены на PHAR-safe регистрацию:

1. **`config/services.yaml`** — блок `TaskOrchestrator\Common\Component\:` (`src/Component/*`) заменён на **явное определение** `TaskOrchestrator\Common\Component\Clock\SystemClock: ~`. Это единственный concrete-сервис каталога; остальные классы `Component/ModuleSystem/` (compiler passes, сам `ModuleServiceRegistrar`, interfaces, trait) либо инстанцируются вручную через `new`/`addCompilerPass`, либо пропускаются регистратором как несервисные типы.
2-4. **`config/console_services.yaml`** — три блока `apps/console/src/Module/{Orchestrator,GitIdentity}/.../{Command,EventSubscriber}/*` убраны. Регистрацию выполняет новый метод `Kernel::registerConsoleServices()`, который вызывает обобщённый `ModuleServiceRegistrar` для трёх каталогов с `public: true`.

**Обобщение регистратора.** `ModuleServiceRegistrar` сделан generic (применим не только к модулям): параметр конструктора переименован `moduleDir` → `serviceDir`, добавлена опция `public: bool = false` (для команд apps/console — `true`, чтобы Console Application мог их доставать; для доменных модулей остаётся `false`).

**Теги команд и подписчиков.** Symfony `FrameworkExtension` регистрирует container-wide autoconfiguration для `Symfony\Component\Console\Command\Command` (тег `console.command`) и `EventSubscriberInterface` (тег `kernel.event_subscriber`) — поэтому регистратору достаточно `setAutoconfigured(true)`, теги применяются автоматически единообразно в dev и PHAR.

В результате ни в `config/services.yaml`, ни в `config/console_services.yaml` не осталось ни одного `resource:`-блока: только явные определения (aliases, скалярные аргументы, alias `Psr\Clock\ClockInterface` → `SystemClock`, `EventDispatcher`, `LockFactory`).

### Усиление `phar-smoke`: ловля hollow-контейнера

Прежний `bin/phar-smoke` проверял только `--version` — **ложнозелёный**: `--version` печатается даже при hollow-контейнере (ни один сервис для неё не нужен). Усиленный smoke дополнительно проверяет наличие команд модулей (`agent:run`, `validate:connectivity`) через `list | grep` из **двух CWD** — checkout (как в CI) и произвольный временный каталог (сценарий распространяемого PHAR) — со сбросом кэша контейнера PHAR. Если команды отсутствуют — smoke падает, сигнализируя о hollow-контейнере (сломан package-root или регистратор).

### Порядок выполнения и explicit-wins

`ModuleServiceRegistrar` запускается из `ModuleCompilerPass::process()` **после** загрузки модульного `services.yaml`. Поэтому правило **explicit-wins (явное определение побеждает)**: если `services.yaml` уже объявил для FQCN alias, аргументы или явный `Definition` — регистратор его не перетирает. Это позволяет держать в модульном `services.yaml` только то, что требует ручной настройки (interface aliases, scalar arguments, tagged iterators, service maps), а «массовую» регистрацию инстанциируемых классов поручить регистратору.

### Параметр `module.<name>.module_dir`

Параметр `module.<name>.module_dir` сохранён в модульных `services.yaml` как **декларативный контракт** (каноническое место хранения каталога модуля по конвенции), но **не является runtime-значением**: регистратор берёт каталог напрямую из `ModuleInterface::getModuleDir()` (= `__DIR__`), а не из параметра контейнера. Позиция: параметр оставлен осознанно — он документирует, где физически лежит модуль, и сохраняет совместимость с рецептом Path A, если в будущем потребуется вернуться к `resource:` в среде без PHAR.

### Эмпирическое подтверждение

- PHPUnit: 1245 тестов / 3355 assertions — зелёные.
- Psalm: 0 ошибок.
- `phar-smoke` (CI): зелёный как из каталога checkout, так и из произвольного временного CWD (current working directory — текущий рабочий каталог). Усиленный smoke дополнительно проверяет, что команды модулей (`agent:run`, `validate:connectivity` и др.) действительно зарегистрированы из обоих CWD — то есть контейнер PHAR не hollow, package-root и регистратор отработали. До усиления smoke `--version` проходил даже при пустом по модулям контейнере, маскируя регрессию.

## Последствия

Положительные последствия:

- Конвенция снова становится источником истины, а не декларацией, расходящейся с кодом.
- Каждый модуль становится self-contained (самодостаточным): код, параметры и DI-правила живут рядом.
- Новый модуль подключается механически по одному рецепту: `ModuleInterface` → `Resource/config/services.yaml` → `config/modules.php`.
- Общий `config/services.yaml` перестаёт расти как монолитный DI-файл.
- Модульные расширения `TwigInterface` и `TranslationInterface` остаются совместимыми с тем же механизмом, но не навязываются CLI-модулям.
- PHAR-переносимость (Вариант 4): auto-discovery через `RecursiveDirectoryIterator` работает единообразно в обычной файловой системе и внутри `phar://`, поэтому dev-сборка и prod-PHAR-сборка собирают контейнер одинаково.

Отрицательные последствия и риски:

- Миграция затрагивает DI-контейнер, поэтому возможны регрессии autowire/autoconfigure (автосвязывания и автоконфигурации).
- На время переноса возможны дубли определений сервисов, если root auto-discovery всё ещё покрывает `src/Module/*`. Поэтому финальная очистка root auto-discovery обязательна.
- Container-wide autoconfiguration тегирует реализации интерфейсов независимо от модуля, поэтому прежнее ограничение `_instanceof` (действие только в пределах своего файла) больше неактуально. Cross-module реализации (например, `DynamicExecutionStrategy` в `DynamicLoop` при контракте `ExecutionStrategyInterface` из `ChainExecution`) тегируются автоматически; явный `tags:` на таких классах — опциональная перестраховка (explicit-wins), а не необходимость.
- `task_orchestrator.*` остаются параметрами ядра. Модульные `module.<name>.*` параметры не должны превращаться в копию всей глобальной конфигурации — только в явные зависимости модуля.

## Альтернативы

1. **Path B: переписать конвенцию под централизованный `config/services.yaml`.** Отвергнуто. Этот путь фиксирует исторический долг как новую норму, обесценивает уже созданную модульную инфраструктуру (`ModuleInterface`, `ModuleKernelTrait`, `ModuleCompilerPass`, `config/modules.php`) и оставляет модули не самодостаточными. Он также конфликтует с принципом проекта «Конвенции первичны» и с пилотом `GitIdentity`.

2. **Гибрид: новые модули через `Resource/config`, старые оставить в root `config/services.yaml`.** Отвергнуто. Две схемы подключения создают постоянную развилку для разработчиков и ревьюеров: непонятно, какой пример считать эталоном. Это сохраняет ловушку «делать как у соседей».

3. **Оформить каждый модуль как Symfony Bundle (бандл Symfony).** Отвергнуто. Для CLI-библиотеки это избыточно: текущий `ModuleInterface` уже даёт нужные точки расширения без bundle lifecycle (жизненного цикла бандла), лишней структуры и web-ориентированных предположений.

4. **Оставить root auto-discovery для всех `src/Module/*`, а в модулях хранить только aliases/arguments.** Отвергнуто как конечное состояние. Это допустимо только как краткий промежуточный шаг миграции, но не как стандарт: модульный `services.yaml` обязан быть единственным владельцем auto-discovery своего модуля.
