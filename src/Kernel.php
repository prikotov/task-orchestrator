<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
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
 * getProjectDir() наследуется от BaseKernel и разрешается как каталог с
 * composer.json пакета (package root) — он же источник config/*. Это работает
 * единообразно в standalone- и vendor-режиме.
 *
 * Параметры task_orchestrator.* устанавливаются в getKernelParameters() — это
 * самый ранний этап сборки контейнера, поэтому параметры гарантированно доступны
 * при импорте services.yaml (resource/exclude и аргументы ссылаются на них).
 *
 * Dual-context (vendor binary): конструктор принимает $projectRoot — каталог
 * host-проекта, из которого берутся роли, цепочки и base_path (с fallback на
 * package root, если в host-проекте ресурсов нет). В standalone-режиме
 * $projectRoot не передаётся и совпадает с package root.
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
     * @return array<string, array|bool|string|int|float|\UnitEnum|null>
     */
    #[Override]
    protected function getKernelParameters(): array
    {
        $parameters = parent::getKernelParameters();

        $packageDir = $this->getProjectDir();
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
        $this->registerModules($container, $this->getModules());
    }

    /**
     * Реестр модулей из config/modules.php (относительно package root).
     *
     * @return array<string, array<string, bool>>
     */
    private function getModules(): array
    {
        $file = $this->getProjectDir() . '/config/modules.php';

        return is_file($file) ? require $file : [];
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
            $version = \Composer\InstalledVersions::getVersion('prikotov/task-orchestrator')
                ?? \Composer\InstalledVersions::getRootPackage()['version']
                ?? '0.1.0';

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
