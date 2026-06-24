# ADR-012: Конвенция конфигурации модулей

| Поле        | Значение                                             |
|-------------|------------------------------------------------------|
| Статус      | Принято                                              |
| Дата        | 2026-06-24                                           |
| Автор       | Архитектор (Гэндальф)                                |

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

8. Модульный `services.yaml` объявляет auto-discovery (автообнаружение сервисов) только своего namespace (пространства имён):

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

Обязательный минимум `exclude` защищает контейнер от регистрации несервисных типов: Entity (сущность), Enum (перечисление), ValueObject (объект-значение), DTO (data transfer object — объект передачи данных), ресурсов модуля и класса модуля. Если в модуле есть дополнительные несервисные каталоги (`Application/Enum`, `Application/Event`, `Domain/Exception` и т.п.), они также добавляются в `exclude` модульного файла.

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

## Последствия

Положительные последствия:

- Конвенция снова становится источником истины, а не декларацией, расходящейся с кодом.
- Каждый модуль становится self-contained (самодостаточным): код, параметры и DI-правила живут рядом.
- Новый модуль подключается механически по одному рецепту: `ModuleInterface` → `Resource/config/services.yaml` → `config/modules.php`.
- Общий `config/services.yaml` перестаёт расти как монолитный DI-файл.
- Модульные расширения `TwigInterface` и `TranslationInterface` остаются совместимыми с тем же механизмом, но не навязываются CLI-модулям.

Отрицательные последствия и риски:

- Миграция затрагивает DI-контейнер, поэтому возможны регрессии autowire/autoconfigure (автосвязывания и автоконфигурации).
- На время переноса возможны дубли определений сервисов, если root auto-discovery всё ещё покрывает `src/Module/*`. Поэтому финальная очистка root auto-discovery обязательна.
- `_instanceof` работает в контексте файла конфигурации. Cross-module implementations (реализации в другом модуле), например `DynamicExecutionStrategy`, должны получать явный tag в своём модульном `services.yaml`.
- `task_orchestrator.*` остаются параметрами ядра. Модульные `module.<name>.*` параметры не должны превращаться в копию всей глобальной конфигурации — только в явные зависимости модуля.

## Альтернативы

1. **Path B: переписать конвенцию под централизованный `config/services.yaml`.** Отвергнуто. Этот путь фиксирует исторический долг как новую норму, обесценивает уже созданную модульную инфраструктуру (`ModuleInterface`, `ModuleKernelTrait`, `ModuleCompilerPass`, `config/modules.php`) и оставляет модули не самодостаточными. Он также конфликтует с принципом проекта «Конвенции первичны» и с пилотом `GitIdentity`.

2. **Гибрид: новые модули через `Resource/config`, старые оставить в root `config/services.yaml`.** Отвергнуто. Две схемы подключения создают постоянную развилку для разработчиков и ревьюеров: непонятно, какой пример считать эталоном. Это сохраняет ловушку «делать как у соседей».

3. **Оформить каждый модуль как Symfony Bundle (бандл Symfony).** Отвергнуто. Для CLI-библиотеки это избыточно: текущий `ModuleInterface` уже даёт нужные точки расширения без bundle lifecycle (жизненного цикла бандла), лишней структуры и web-ориентированных предположений.

4. **Оставить root auto-discovery для всех `src/Module/*`, а в модулях хранить только aliases/arguments.** Отвергнуто как конечное состояние. Это допустимо только как краткий промежуточный шаг миграции, но не как стандарт: модульный `services.yaml` обязан быть единственным владельцем auto-discovery своего модуля.
