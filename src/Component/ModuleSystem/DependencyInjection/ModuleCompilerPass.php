<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection;

use Exception;
use Override;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Подгружает конфигурацию сервисов модуля (Resource/config/services.yaml)
 * на этапе компиляции контейнера.
 *
 * Дополнительно подключает services.php и services_<environment>.php, если
 * они присутствуют в каталоге конфигурации модуля. Один pass регистрируется
 * на каждый модуль из реестра config/modules.php в Kernel (через ModuleKernelTrait).
 */
final readonly class ModuleCompilerPass implements CompilerPassInterface
{
    public function __construct(
        private string $serviceConfigPath,
        private string $environment,
        private ?ModuleServiceRegistrar $registrar = null,
    ) {
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator($this->serviceConfigPath), $this->environment);
        $loader->load('services.yaml');

        // PHAR-safe auto-discovery сервисов модуля через RecursiveDirectoryIterator.
        // Выполняется ПОСЛЕ загрузки services.yaml, чтобы явные определения
        // (aliases/аргументы/теги) выигрывали (explicit-wins в ModuleServiceRegistrar).
        if ($this->registrar instanceof ModuleServiceRegistrar) {
            $this->registrar->register($container);
        }

        $phpLoader = new PhpFileLoader($container, new FileLocator($this->serviceConfigPath), $this->environment);
        if (is_file($this->serviceConfigPath . '/services.php')) {
            $phpLoader->load('services.php');
        }

        $environmentServices = sprintf('services_%s.php', $this->environment);
        if (is_file($this->serviceConfigPath . '/' . $environmentServices)) {
            $phpLoader->load($environmentServices);
        }
    }
}
