<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection;

/**
 * Сборка параметров ядра task_orchestrator.* (и app.version) для контейнера.
 *
 * Вынесено из {@see \TaskOrchestrator\Common\Kernel::getKernelParameters()}:
 * ядро остаётся точкой сборки (момент вызова, объединение с kernel.*), а
 * состав и семантика параметров task-orchestrator — здесь.
 *
 * Устанавливаются на этапе конструирования контейнера (раньше любого импорта
 * конфигурации), поэтому task_orchestrator.* доступны services.yaml и
 * модульным Resource/config/services.yaml без отдельной настройки.
 * Обычные (plain) параметры запекаются при компиляции; phar-зависимые
 * (package_dir/phar_path), layout-пути и локаль AI-ролей остаются
 * динамическими env-плейсхолдерами и резолвятся в runtime.
 *
 * Stateless-хелпер точки сборки: ядро создаёт его через `new` (вне
 * контейнера, при сборке параметров ядра) по образцу
 * {@see ConsoleServicesRegistrar} и компонентов PharCache
 * (CacheRootOwner, CacheRootGarbageCollector).
 */
final readonly class KernelParametersRegistrar
{
    /**
     * @param string $projectRoot   каталог host-проекта (роли/цепочки/base_path)
     * @param bool   $isPhar        флаг запуска из PHAR-архива
     * @param string $platformFamily явно внедрённое семейство ОС (platform-specific компоненты)
     * @param string $version       действующая версия релиза (см. Component/Release)
     */
    public function __construct(
        private readonly string $projectRoot,
        private readonly bool $isPhar,
        private readonly string $platformFamily,
        private readonly string $version,
    ) {
    }

    /**
     * Параметры TaskOrchestrator для контейнера ядра.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        $parameters = [];

        // Phar-зависимые пути — динамические env-параметры (шаг (A) решения П2):
        // в скомпилированном контейнере параметр остаётся env-плейсхолдером и
        // резолвится через env-процессор Component/PharRuntime при КАЖДОМ
        // обращении к параметру от ЗАПУЩЕННОГО кода/архива (провайдер — ядро:
        // packageDir()/runningPharPath(), см. PharRuntimePathsProviderInterface).
        // Поэтому перемещение PHAR не оставляет мёртвых phar://-ссылок даже при
        // переиспользовании персистентного кеша в debug=false (ConfigCache не
        // проверяет свежесть); внешний контракт agent:init сохранён (.phar-binding
        // = физический путь запущенного PHAR). Имя после префикса —
        // дискриминатор ресурса процессора, не env-переменная.
        $parameters['task_orchestrator.package_dir'] = '%env(task_orchestrator_phar:package_dir)%';
        $parameters['task_orchestrator.phar_path'] = '%env(task_orchestrator_phar:phar_path)%';

        // base_path/chains_session_dir — host-корень и его производная: в
        // dual-context от пути PHAR не зависят (фар-зависимость — только в
        // standalone-PHAR, D4 — вне объёма шага (A)), поэтому остаются обычными
        // параметрами. is_phar — флаг режима, от пути архива не зависит.
        $parameters['task_orchestrator.base_path'] = $this->projectRoot;
        $parameters['task_orchestrator.is_phar'] = $this->isPhar;
        $parameters['task_orchestrator.chains_session_dir'] = $this->projectRoot . '/var/sessions';
        $parameters['task_orchestrator.platform_family'] = $this->platformFamily;

        // Локаль AI-ролей (role files + каталог skills) задаётся runtime
        // env-плейсхолдером: в скомпилированном контейнере параметр остаётся
        // динамическим (Container::getEnv() при обращении), поэтому смена
        // TASK_ORCHESTRATOR_LOCALE применяется при следующем запуске БЕЗ очистки
        // кеша при том же корне кеша. Нормализация контракта (пустая auto-локаль
        // при незаданной/пустой переменной, trim, lower-case) — единая точка
        // TaskOrchestratorLocaleEnvVarProcessor; fallback на APP_LOCALE
        // отсутствует намеренно. Локаль Symfony-переводчика (framework.
        // default_locale в config/packages/translation.yaml) независима от
        // локали AI-ролей.
        $parameters['task_orchestrator.locale'] = '%env(task_orchestrator_locale:TASK_ORCHESTRATOR_LOCALE)%';

        // Runtime layout (roles_dir/skills_dir/chains_yaml) — тоже динамические
        // env-параметры (QA-3): выбор host/package НЕ запекается в скомпилированный
        // контейнер, а выполняется env-процессором Component/Layout при каждом
        // обращении к параметру. Ресурсы host-проекта, созданные после первого
        // запуска PHAR, разрешаются в host уже на следующем запуске без ручной
        // очистки кеша; fallback на упакованные ресурсы пакета сохраняется, а при
        // отсутствии обоих кандидатов процессор бросает явное исключение.
        // Имя после префикса — дискриминатор ресурса процессора, не env-переменная.
        $parameters['task_orchestrator.roles_dir'] = '%env(task_orchestrator_layout:roles_dir)%';
        $parameters['task_orchestrator.skills_dir'] = '%env(task_orchestrator_layout:skills_dir)%';
        $parameters['task_orchestrator.skills_dirs'] = '%env(task_orchestrator_layout:skills_dirs)%';
        $parameters['task_orchestrator.chains_yaml'] = '%env(task_orchestrator_layout:chains_yaml)%';

        $parameters['app.version'] = $this->version;

        return $parameters;
    }
}
