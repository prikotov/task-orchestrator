<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection\ModuleServiceRegistrar;
use TaskOrchestrator\Common\Component\ModuleSystem\ModuleKernelTrait;

/**
 * Ядро приложения TaskOrchestrator (console-only).
 *
 * Заменяет прежнюю ручную сборку контейнера в bin/*. Следует модульной
 * архитектуре: MicroKernelTrait загружает config/packages/*, config/services.yaml
 * (который, в свою очередь, импортирует config/console_services.yaml) и
 * config/bundles.php; ModuleKernelTrait регистрирует модули из config/modules.php
 * (подгрузка Resource/config/services.yaml, Twig- и Translation-путей).
 *
 * Package root (каталог пакета с src/, apps/, config/) разрешается через
 * {@see getPackageDir()} = dirname(__DIR__): CWD-независимо и одинаково в
 * обычной FS и в PHAR (где наследуемый BaseKernel::getProjectDir() даёт
 * неверный phar://.../src, так как composer.json не упакован в PHAR).
 * getPackageDir() используется для config/modules.php и параметра
 * task_orchestrator.package_dir.
 *
 * {@see getProjectDir()} переопределён: в PHAR возвращает package root
 * (через getPackageDir()), в обычной FS — наследуемый поиск по composer.json.
 * Это чинит Symfony-конфигурацию в PHAR (config/bundles.php, config/packages/*,
 * config/services.yaml грузятся через MicroKernelTrait::getConfigDir() =
 * getProjectDir()/config) и параметр kernel.project_dir.
 *
 * Параметры task_orchestrator.* устанавливаются в getKernelParameters() — это
 * самый ранний этап сборки контейнера, поэтому параметры гарантированно доступны
 * при импорте services.yaml (resource/exclude и аргументы ссылаются на них).
 *
 * Dual-context (vendor binary): конструктор принимает $projectRoot — каталог
 * host-проекта, из которого берутся роли, цепочки и base_path (с fallback на
 * package root, если в host-проекте ресурсов нет). В standalone-режиме
 * $projectRoot не передаётся и совпадает с package root. host-ресурсы
 * разрешаются через {@see getProjectRoot()}, package-ресурсы — через
 * getPackageDir()/getProjectDir().
 *
 * Phar: package root доступен только для чтения, поэтому cache/log при работе
 * из Phar уходят в системный временный каталог, разделённый по host-проекту.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;
    use ModuleKernelTrait;

    /**
     * @param string      $environment имя окружения (prod/dev/test)
     * @param bool        $debug       режим отладки
     * @param string|null $projectRoot каталог host-проекта для ролей/цепочек/base_path;
     *                                 null = standalone (совпадает с package root)
     */
    public function __construct(
        string $environment,
        bool $debug,
        private readonly ?string $projectRoot = null,
    ) {
        parent::__construct($environment, $debug);
    }

    /**
     * Каталог host-проекта: источник ролей, цепочек и base_path.
     * В standalone-режиме совпадает с package root (getProjectDir()).
     */
    public function getProjectRoot(): string
    {
        return $this->projectRoot ?? $this->getProjectDir();
    }

    /**
     * Корень проекта для Symfony: каталог, где лежат config/ (bundles.php,
     * packages/*, services.yaml), src/, templates/ и (в обычной FS) composer.json.
     *
     * В обычной FS наследуемый BaseKernel::getProjectDir() находит packageRoot
     * по composer.json. В PHAR root composer.json не упакован, поэтому
     * наследуемый поиск падает на dirname(__FILE__) = phar://.../src —
     * неверный проектный корень, из-за которого не грузятся config/services.yaml,
     * config/packages/*, config/bundles.php (через MicroKernelTrait::getConfigDir()
     * = getProjectDir()/config) и ломается весь PHAR-контейнер.
     *
     * Поэтому в PHAR явно возвращаем package root ({@see getPackageDir()}): там
     * лежат config/, src/, templates/, упакованные в архив. В обычной FS
     * поведение наследуемого getProjectDir() сохраняется.
     *
     * НЕ путать с {@see getProjectRoot()} — каталогом host-проекта для ролей,
     * цепочек, base_path и writable cache/log.
     */
    #[Override]
    public function getProjectDir(): string
    {
        return $this->isPhar() ? $this->getPackageDir() : parent::getProjectDir();
    }

    /**
     * Package root: каталог пакета, где лежат src/, apps/, config/.
     *
     * CWD-независимо и работает как в обычной файловой системе, так и в PHAR.
     * В PHAR composer.json не упакован, поэтому наследуемый
     * BaseKernel::getProjectDir() даёт неверный phar://.../src: он ищет
     * composer.json, поднимаясь от Kernel.php, и при отсутствии падает на
     * dirname(__FILE__). Kernel.php лежит в src/, поэтому package root =
     * dirname(__DIR__).
     *
     *   dev:  dirname('/path/src')          = '/path'           (root проекта).
     *   PHAR: dirname('phar://x.phar/src')  = 'phar://x.phar'   (root PHAR).
     */
    private function getPackageDir(): string
    {
        return dirname(__DIR__);
    }

    #[Override]
    public function getCacheDir(): string
    {
        $base = $_SERVER['APP_CACHE_DIR'] ?? null;
        if (is_string($base) && $base !== '') {
            return $base . '/' . $this->environment;
        }

        return $this->writableRoot() . '/var/cache/' . $this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        $base = $_SERVER['APP_LOG_DIR'] ?? null;
        if (is_string($base) && $base !== '') {
            return $base;
        }

        return $this->writableRoot() . '/var/log';
    }

    /**
     * Параметры ядра: стандартные kernel.* + параметры TaskOrchestrator.
     *
     * Устанавливаются на этапе конструирования контейнера (раньше любого импорта
     * конфигурации), поэтому task_orchestrator.* доступны services.yaml и
     * модульным Resource/config/services.yaml без отдельной настройки.
     *
     * @return array<string, mixed>
     */
    #[Override]
    protected function getKernelParameters(): array
    {
        $parameters = parent::getKernelParameters();

        $packageDir = $this->getPackageDir();
        $projectRoot = $this->getProjectRoot();

        $parameters['task_orchestrator.package_dir'] = $packageDir;
        $parameters['task_orchestrator.base_path'] = $projectRoot;
        $parameters['task_orchestrator.chains_session_dir'] = $projectRoot . '/var/sessions';
        $parameters['task_orchestrator.roles_dir'] = $this->resolveRolesDir($projectRoot, $packageDir);
        $parameters['task_orchestrator.chains_yaml'] = $this->resolveChainsYaml($projectRoot, $packageDir);
        $parameters['app.version'] = $this->resolveVersion();

        return $parameters;
    }

    #[Override]
    protected function build(ContainerBuilder $container): void
    {
        $this->registerAutoconfiguration($container);
        $this->registerModules($container, $this->getModules());
        $this->registerConsoleServices($container);
    }

    /**
     * Container-wide autoconfiguration для тегов, которые раньше объявлялись
     * через module-local _instanceof. После перехода с resource: на PHAR-safe
     * регистратор (ModuleServiceRegistrar) _instanceof больше не применяется
     * (оно работало только к классам из resource: своего файла).
     *
     * Container-wide autoconfig применяется ко всем сервисам с autoconfigured=true,
     * независимо от способа регистрации (resource: или регистратор) — единообразно
     * в dev и PHAR.
     */
    private function registerAutoconfiguration(ContainerBuilder $container): void
    {
        // Теги интерфейсов регистрируются container-wide (а не module-local
        // _instanceof), поэтому применяются ко всем autoconfigured-сервисам
        // независимо от способа регистрации (explicit def или PHAR-safe регистратор).
        $container->registerForAutoconfiguration(
            \TaskOrchestrator\Common\Module\AgentRunner\Domain\Service\AgentRunnerInterface::class,
        )->addTag('agent.runner');

        $container->registerForAutoconfiguration(
            \TaskOrchestrator\Common\Module\ChainExecution\Application\Contract\Chain\ExecutionStrategyInterface::class,
        )->addTag('orchestrator.execution_strategy');

        $container->registerForAutoconfiguration(
            \TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ExecuteStepServiceInterface::class,
        )->addTag('chain_execution.step_runner');
    }

    /**
     * Реестр модулей из config/modules.php (относительно package root).
     *
     * @return array<string, array<string, bool>>
     */
    private function getModules(): array
    {
        $file = $this->getPackageDir() . '/config/modules.php';

        return is_file($file) ? require $file : [];
    }

    /**
     * PHAR-safe регистрация сервисов Presentation-слоя apps/console
     * (консольные команды и подписчики событий).
     *
     * Заменяет прежние блоки `resource:` в config/console_services.yaml, которые
     * ломались в PHAR через Symfony GlobResource (возвращает 0 файлов по phar://).
     *
     * Каталоги apps/console захардкожены здесь, а не в config/modules.php:
     * apps/console — это Presentation-слой конкретного CLI-приложения проекта
     * (не доменный модуль и не переиспользуемая часть библиотеки), поэтому он
     * не описывается ModuleInterface и не входит в реестр модулей.
     *
     * Регистратор вызывается в {@see build()}, до загрузки console_services.yaml
     * (Symfony: prepareContainer→build → затем registerContainerConfiguration).
     * Это безопасно: explicit defs в YAML (EventDispatcher/LockFactory/FlockStore)
     * не пересекаются с FQCN этих каталогов. Теги `console.command` (Command) и
     * `kernel.event_subscriber` (EventSubscriber) добавляются автоматически через
     * container-wide registerForAutoconfiguration из FrameworkExtension — она
     * применяется на этапе compile единообразно для dev и PHAR. public=true
     * сохраняет прежнее поведение resource:-блоков (команды public).
     */
    private function registerConsoleServices(ContainerBuilder $container): void
    {
        $appsConsoleModule = $this->getPackageDir() . '/apps/console/src/Module';

        foreach (
            [
                [
                    'dir' => $appsConsoleModule . '/Orchestrator/Command',
                    'namespace' => 'TaskOrchestrator\Console\Module\Orchestrator\Command',
                ],
                [
                    'dir' => $appsConsoleModule . '/GitIdentity/Command',
                    'namespace' => 'TaskOrchestrator\Console\Module\GitIdentity\Command',
                ],
                [
                    'dir' => $appsConsoleModule . '/Orchestrator/EventSubscriber',
                    'namespace' => 'TaskOrchestrator\Console\Module\Orchestrator\EventSubscriber',
                ],
            ] as $registration
        ) {
            (new ModuleServiceRegistrar(
                serviceDir: $registration['dir'],
                serviceNamespace: $registration['namespace'],
                excludeRelativePaths: [],
                public: true,
            ))->register($container);
        }
    }

    /**
     * Роли: host-проект → package root (поведение прежнего bin/task-orchestrator).
     */
    private function resolveRolesDir(string $projectRoot, string $packageDir): string
    {
        $candidate = $projectRoot . '/docs/agents/roles/team';

        return is_dir($candidate) ? $candidate : $packageDir . '/docs/agents/roles/team';
    }

    private function resolveChainsYaml(string $projectRoot, string $packageDir): string
    {
        $candidate = $projectRoot . '/config/chains.yaml';

        return is_file($candidate) ? $candidate : $packageDir . '/config/chains.yaml';
    }

    private function resolveVersion(): string
    {
        if (class_exists(\Composer\InstalledVersions::class)) {
            $version = \Composer\InstalledVersions::getVersion('prikotov/task-orchestrator');
            if ($version === null) {
                $version = \Composer\InstalledVersions::getRootPackage()['version'];
            }

            return ltrim($version, 'v');
        }

        return '0.1.0';
    }

    /**
     * Корень для writable-артефактов (cache/log).
     *
     * Из Phar package root доступен только для чтения — используем системный
     * временный каталог, разделённый по host-проекту (чтобы избежать коллизий
     * и устаревших параметров между разными проектами). В обычном режиме —
     * host-проект (projectRoot), что даёт изоляцию кеша по CWD и согласовано с
     * другими writable-путями (var/sessions, var/cache/git-identity).
     */
    private function writableRoot(): string
    {
        if ($this->isPhar()) {
            return rtrim(sys_get_temp_dir(), '/\\')
                . '/task-orchestrator/'
                . hash('xxh64', $this->getProjectRoot());
        }

        return $this->getProjectRoot();
    }

    private function isPhar(): bool
    {
        return str_starts_with(__FILE__, 'phar://');
    }

    /**
     * @param array<string, bool> $envs
     */
    private function isEnvironmentIncluded(array $envs): bool
    {
        return $envs[$this->environment] ?? $envs['all'] ?? false;
    }
}
