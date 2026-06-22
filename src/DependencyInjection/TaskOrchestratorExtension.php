<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\DependencyInjection;

use Override;
use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection\ModuleCompilerPass;
use TaskOrchestrator\Common\Component\ModuleSystem\ModuleInterface;

/**
 * Extension для TaskOrchestrator.
 *
 * Загружает config/services.yaml, регистрирует параметры конфигурации
 * (roles_dir, chains_yaml, chains_session_dir, base_path, package_dir) и
 * подключает модули из реестра config/modules.php через универсальный
 * механизм ModuleSystem (см. конвенцию docs/conventions/modules/configuration.md).
 *
 * Extension не знает о конкретных модулях: для каждого класса модуля из
 * реестра, реализующего ModuleInterface, регистрируется ModuleCompilerPass,
 * который на этапе компиляции подгружает Resource/config/services.yaml модуля.
 *
 * Используется напрямую в CLI entry point (bin/console) без Symfony Kernel.
 */
class TaskOrchestratorExtension extends Extension
{
    /**
     * Окружение по умолчанию для CLI-бандла без Symfony Kernel.
     */
    public const string DEFAULT_ENVIRONMENT = 'cli';

    /**
     * @param array<array-key, array<array-key, mixed>> $configs
     *
     * @throws \Exception
     */
    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $packageDir = dirname(__DIR__, 2);

        $container->setParameter('task_orchestrator.roles_dir', $config['roles_dir']);
        $container->setParameter('task_orchestrator.chains_yaml', $config['chains_yaml']);
        $container->setParameter('task_orchestrator.chains_session_dir', $config['chains_session_dir']);
        $container->setParameter('task_orchestrator.base_path', $config['base_path']);
        $container->setParameter('task_orchestrator.package_dir', $packageDir);

        $loader = new YamlFileLoader($container, new FileLocator($packageDir . '/config'));
        $loader->load('services.yaml');

        // Регистрация модулей через универсальный механизм ModuleSystem
        // (конвенция docs/conventions/modules/configuration.md).
        // Extension перебирает реестр config/modules.php и для каждого модуля,
        // подходящего под текущее окружение и реализующего ModuleInterface,
        // регистрирует ModuleCompilerPass — он подгрузит Resource/config/services.yaml
        // модуля на этапе compile() контейнера.
        $this->registerModules($container, $packageDir);
    }

    /**
     * Перебирает реестр config/modules.php и регистрирует ModuleCompilerPass
     * для каждого подходящего модуля.
     *
     * Если config/modules.php отсутствует — тихо пропускается для обратной
     * совместимости (composer-потребитель может не иметь реестра).
     *
     * @throws RuntimeException если класс модуля не существует или не реализует ModuleInterface
     */
    private function registerModules(ContainerBuilder $container, string $packageDir): void
    {
        $modulesFilePath = $packageDir . '/config/modules.php';
        if (!is_file($modulesFilePath)) {
            return;
        }

        /** @var array<string, array<string, bool>> $modules */
        $modules = require $modulesFilePath;
        $environment = $this->resolveEnvironment($container);

        foreach ($modules as $class => $envs) {
            if (!$this->isEnvironmentIncluded($envs, $environment)) {
                continue;
            }

            if (!class_exists($class)) {
                throw new RuntimeException(sprintf('Module class %s does not exist.', $class));
            }

            $module = new $class();
            if (!$module instanceof ModuleInterface) {
                throw new RuntimeException(
                    sprintf('Module %s must implement %s.', $class, ModuleInterface::class),
                );
            }

            $container->addCompilerPass(
                new ModuleCompilerPass($module->getModuleConfigPath(), $environment),
                PassConfig::TYPE_BEFORE_OPTIMIZATION,
                10000,
            );
        }
    }

    /**
     * Возвращает имя окружения из параметра kernel.environment либо дефолт
     * для CLI-бандла без Kernel.
     */
    private function resolveEnvironment(ContainerBuilder $container): string
    {
        if ($container->hasParameter('kernel.environment')) {
            /** @var string $environment */
            $environment = $container->getParameter('kernel.environment');

            return $environment;
        }

        return self::DEFAULT_ENVIRONMENT;
    }

    /**
     * Реализует правило конвенции docs/conventions/modules/configuration.md:
     * модуль активен, если явно указано текущее окружение, либо ключ 'all'.
     *
     * @param array<string, bool> $envs конфигурация окружений модуля
     */
    private function isEnvironmentIncluded(array $envs, string $environment): bool
    {
        return $envs[$environment] ?? $envs['all'] ?? false;
    }
}
