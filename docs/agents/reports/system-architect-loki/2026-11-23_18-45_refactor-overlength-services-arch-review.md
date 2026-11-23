# Архитектурное ревью (RV-A) изменений ветки task/refactor-overlength-services

**Роль:** Архитектор Локи (system_architect_loki)
**Дата:** 2026-11-23
**Объект:** рабочая копия ветки `task/refactor-overlength-services` (git diff HEAD + untracked): декомпозиция `src/Kernel.php`, `src/Module/ChainDefinition/Infrastructure/Service/Chain/YamlChainLoaderService.php`, `src/Module/DynamicLoop/Domain/Service/Dynamic/ExecuteDynamicTurnService.php`; новые классы `ConsoleServicesRegistrar`, `KernelParametersRegistrar`, `ReleaseVersionResolver`, `PharRunningPathResolver`, `YamlRoleConfigMapper`, `YamlDynamicPromptsResolverService(Interface)`, `DynamicRoleCommandHelper`, `DynamicRoundResultHelper`.
**Задача:** `todo/TASK-refactor-overlength-services.todo.md` (C2, ветка task/refactor-overlength-services)
**Трек:** RV-A (архитектурное ревью, RACI). RV-F пройдено ранее (Пуаро, approve) — поведение и тесты не перепроверялись.

## Вердикт

**APPROVE WITH CHANGES** — архитектурных блокеров нет: границы слоёв и правила зависимостей соблюдены, публичные контракты целостны, декомпозиция механическая и обоснованная. До запроса апрува — 2 обязательные точечные правки (CR-1, CR-2) и 1 процессная (CR-3); прочее — наблюдения без изменения кода.

## Проверено

- **Границы слоёв DDD и правила зависимостей.** Deptrac (`depfile.yaml`, включая кастомные правила CrossModuleDomainRule / ServiceContractDependencyRule): **0 нарушений**. Новые классы в допустимых слоях:
  - `YamlRoleConfigMapper` — `Infrastructure/Mapper/Chain/`, `final readonly`, без I/O, `mapTo*()`, массивы как контракт внешнего формата с shape-типами → по core-patterns/mapper.md; соседи `YamlChainStepMapper`/`YamlRetryPolicyMapper`.
  - `YamlDynamicPromptsResolverService` — Infrastructure-реализация с интерфейсом в Domain (`Domain/Service/Chain/`) + явный alias `Interface → Implementation` в `src/Module/ChainDefinition/Resource/config/services.yaml` → по core-patterns/service.md («Инфраструктурный сервис»). I/O (file_get_contents) корректно остался в Infrastructure.
  - `DynamicRoleCommandHelper`/`DynamicRoundResultHelper` — `Domain/Helper/`, `final`, только `public static`, pure, без DI/I/O; имена методов (`parse*`/`from*`/`normalize*`) попадают в `ignorepattern` StaticAccess (`phpmd.xml:83`) — подавление не требуется. Утверждение в PHPDoc «санкционирован ignorepattern StaticAccess» — верно. Размещение в Domain согласовано решением тимлида по итогам RV-F (CR-1 Пуаро).
- **Декомпозиция Kernel.php (497 LOC).** Вынос механический, поведение идентично: `getKernelParameters()` = `parent::getKernelParameters() + KernelParametersRegistrar::parameters()` (ключи `kernel.*` и `task_orchestrator.*` не пересекаются — `+` безопасен, приоритет kernel.* сохранён); `build()` → `ConsoleServicesRegistrar::register()`; `resolvePharPath()` → `PharRunningPathResolver::runningPath()`; `isEnvironmentIncluded()` перенесён в `ModuleKernelTrait` (трейт стал самодостаточным, фалспозит PHPMD UnusedPrivateMethod устранён по существу, а не подавлением). `ModuleKernelTrait` получил неявную зависимость от `$this->environment` (свойство BaseKernel) — приемлемо: трейт и раньше был специфичен для Kernel и документирован как используемый только им.
- **Декомпозиция YamlChainLoaderService.php (386 LOC).** `parseRoles`/`resolveCommandsByComplexity`/`parseFallbackConfig` → `YamlRoleConfigMapper`, `resolvePrompts`/`readFile` → `YamlDynamicPromptsResolverService` — код перенесён верbatim, сигнатуры вызовов эквивалентны.
- **Публичные контракты.** `YamlChainLoaderServiceInterface`, `ChainLoaderInterface`, `ExecuteDynamicTurnServiceInterface`, `RunDynamicLoopAgentServiceInterface`, protected-поверхность Kernel (`PharRuntimePathsProviderInterface::resolvePharPath()`) не изменены. Удалённые `public static` (`ExecuteDynamicTurnService::resolveRunner/resolveThinking/toRoundResultVo`) внешних потребителей не имели (проверено поиском по src/, apps/, tests/). CLI и формат конфигурации не менялись (правки конфигов — только комментарии).
- **Документация.** ADR-012 дополнен примечанием (историческая формулировка про `Kernel::registerConsoleServices()` явно помечена как историческая — допустимо); `docs/guide/architecture.md`, `config/services.yaml`, `config/console_services.yaml` актуализированы. Поиска по `registerConsoleServices` в других документах (README, SKILL.md, docs/guide/*) — устаревших упоминаний нет.
- **Дельта метрик** (`coding-standard-metrics-review --base=HEAD`, `var/metrics-review/comparison.json`): 28 улучшений / 70 нейтральных / 12 «ухудшений» — все маргинальные и ожидаемые последствия извлечения: `ca.count` +1 у `RoleConfigVo`/`DynamicLoopRoleConfigVo`/`DynamicLoopTurnResultVo` (рост fan-in от нового маппера/хелперов), `ce.count` +1 у `YamlChainLoaderService` (новая зависимость-интерфейс), `lcom4.normalized` `ExecuteDynamicTurnService` 0.083→0.125 (тривиально), LOC делегирующих вызовов +2..+4. Сигналов CC/WMC нет — направление декомпозиции метриками подтверждается.
- **Верификация.** Psalm — exit=0 (результат предыдущего запуска, не перепроверялся по заданию); PHPUnit — вне скоупа RV-A (RV-F approve); Deptrac прогнан в этом запуске — 0 нарушений.

## Замечания (запросы на изменения)

### CR-1 (обязательно) — неединообразные механизмы подавления PHPMD StaticAccess и потеря обоснования у baseline-записи

- **Файл/строка:** `phpmd.baseline.xml:3` (запись `StaticAccess` для `src/Component/PharRuntime/PharRunningPathResolver.php`); `src/Component/Release/ReleaseVersionResolver.php:64` и `:84` (два `@SuppressWarnings("PHPMD.StaticAccess")`).
- **Суть:** исходный @techdebt-комментарий-блок baseline удалён полностью, а одна запись (`StaticAccess`) перенесена на новый файл без комментария-обоснования — формально это ослабление проверки без учёта. При этом та же проблема (санкционированный static API вендора) в `ReleaseVersionResolver` закрыта другим механизмом — `@SuppressWarnings`. Два разных механизма подавления одного правила в одном PR.
- **Требование (по `docs/conventions/ops/phpmd-suppressions-guidelines.md`):** привести к единому механизму и вернуть обоснование. Допустимые варианты: (а) у записи baseline добавить комментарий-причину («единственная точка static API `\Phar::running()`, постоянное, не техдолг») и такой же комментарий-форму оставить у `@SuppressWarnings` в `ReleaseVersionResolver`; (б) оба sanctioned-случая (`\Phar::running()`, `Composer\InstalledVersions::*`) закрыть через `phpmd.xml` (StaticAccess ignorepattern/exceptions) — тогда записи baseline и suppress-метки исчезают совсем. Важно: механизм одинаковый + обоснование рядом с каждым подавлением.

### CR-2 (обязательно, с выбором варианта) — именование `YamlDynamicPromptsResolverService` отступает от core-patterns/service.md

- **Файл/строка:** `src/Module/ChainDefinition/Domain/Service/Chain/YamlDynamicPromptsResolverServiceInterface.php:22`; `src/Module/ChainDefinition/Infrastructure/Service/Chain/YamlDynamicPromptsResolverService.php:30`; alias — `src/Module/ChainDefinition/Resource/config/services.yaml:32-33`.
- **Суть:** по service.md `{ServiceName} = {Action}{Target}Service`, где `Action` — глагол (`ChangeStatusService`); «Resolver» — существительное. Это новое имя в новом коде; соседние `*ResolverService`/`*ProviderService`/`*LoaderService` — прецеденты, но по AGENTS.md соседний код не эталон (Конвенции первичны).
- **Требование (один из вариантов):** (а) переименовать в `ResolveYamlDynamicPromptsService(Interface)` (точки входа — 2 файла + alias + тесты, радиус мал); (б) оставить имя и зафиксировать расхождение отдельной задачей техдолга в `todo/` на закрытие пробела в Конвенциях (описать паттерн «Resolver/Provider-сервис» либо провести переименование семейства) — по AGENTS.md «правильный код по Конвенции + расхождение фиксируется задачей». Рекомендация: (б) дешевле стратегически, (а) дешевле тактически; решение — за тимлидом.

### CR-3 (процессная, обязательная по AGENTS.md) — @techdebt по `runFacilitator()` не закрыт ни меткой, ни задачей

- **Файл/строка:** `src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopAgentServiceInterface.php:23` (сигнатура `runFacilitator()`, 15 позиционных параметров).
- **Суть:** по итогам предыдущего ревью инкапсуляция хвоста сигнатуры в VO перенесена в техдолг, но: (1) в коде нет метки `@techdebt` (дата/причина) — требование AGENTS.md «Помечай временный код»; (2) в `todo/` нет задачи на устранение — требование «Создавай задачу на устранение техдолга»; комментарий в задаче («завести по согласованию с тимлидом») о процесс висячем.
- **Требование:** добавить `@techdebt`-метку к PHPDoc сигнатуры (дата 2026-11-23, причина: long parameter list, отложено вне скоупа рефакторинга) и завести `TASK-*` в `todo/` (либо зафиксировать явное решение тимлида «не заводить» в комментариях задачи).

## Наблюдения (не блокируют, вносить правки не требуется)

- **O-1. Размещение `KernelParametersRegistrar`** (`src/Component/ModuleSystem/DependencyInjection/KernelParametersRegistrar.php:26`): класс собирает параметры ядра (`task_orchestrator.*`, `app.version`, release-версия), а не модульную систему; соседство с `ConsoleServicesRegistrar` выбрано «по образцу», когезия каталога `ModuleSystem` слабеет. При следующем расширении точек сборки — рассмотреть `Component/Kernel/`. Сейчас приемлемо.
- **O-2. Паттерны «Registrar»/«Resolver» не описаны в Конвенциях.** Stateless-объекты точки сборки, создаваемые через `new` (прецеденты: `ModuleServiceRegistrar` — ADR-012, `CacheRootOwner`, `HostPackageLayoutResolver`), не формализованы в core-patterns; PHPDoc называет их «Stateless-хелпер», что конфликтует с helper.md (Helper = только static). Предложение (моя зона как архитектора): задача на фиксацию паттерна в Конвенциях/ADR + правка термина «хелпер» → «объект точки сборки» в PHPDoc.
- **O-3. `Component/Release/ReleaseVersionResolver` зависит от `TaskOrchestrator\Common\ValueObject\ReleaseVersionVo`** — для «переносимого компонента» (component.md) это проектная зависимость. Фактически класс не Component-паттерна (без `*ComponentInterface`) — build-time резолвер; Deptrac чист. Учесть в задаче O-2.
- **O-4. Смешанные layout-конвенции тестов:** Helper-тесты — в зеркальной раскладке `tests/Unit/Common/Module/DynamicLoop/...`, новые тесты ChainDefinition — в legacy `tests/Unit/Infrastructure/...` (консистентно с прямыми соседями, но testing/index.md требует «повторяет структуру src/»). Не блокирует; унифицировать при следующем заходе.
- **O-5. Запас `Kernel.php` — 3 строки до порога 500** (497 LOC): следующая функциональная правка ядра снова даст ExcessiveClassLength. Информационно; следующий кандидат на вынос стоит планировать заранее.

## Чек-лист архитектурного ревью

- [x] Конвенции, ADR и архитектурные правила прочитаны (core-patterns: service/helper/mapper/component; phpmd-suppressions-guidelines; review-architectural; testing/index)
- [x] Логика и зависимости в границах слоёв и модулей (Deptrac 0 нарушений)
- [x] Доменное поведение на своём месте (DDD) — да, извлечение механическое
- [x] Контракты и обратная совместимость не сломаны
- [ ] Ослабления проверок качества сопровождены техдолгом и задачей — **CR-1/CR-3**
- [x] Дельта метрик изучена (28/70/12, «ухудшения» маргинальны)
- [x] DI и конфигурация соответствуют ADR-012 (затрагивалось — примечание добавлено, alias присутствует)
- [x] Внешние зависимости в допустимом слое (Composer/Phar static API изолированы в Component)
- [x] Секреты не затронуты
- [ ] Новое архитектурное решение записано в ADR — частично (ADR-012 дополнен; паттерн Registrar/Resolver не формализован — **O-2**)
