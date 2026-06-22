<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection\ModuleCompilerPass;
use TaskOrchestrator\Common\Kernel;
use TaskOrchestrator\Common\Module\GitIdentity\GitIdentityModule;

/**
 * Интеграционная проверка универсального механизма ModuleSystem под Symfony Kernel:
 * Kernel::build() (через {@see ModuleKernelTrait}) + {@see ModuleCompilerPass} должны
 * поднять сервисы модуля GitIdentity из реестра config/modules.php.
 *
 * В отличие от прежней версии (тестировавшей TaskOrchestratorExtension напрямую),
 * здесь используется реальное ядро: boot() компилирует контейнер со всеми бандлами,
 * пакетами и модулями. Успешная загрузка сама по себе доказывает, что ModuleCompilerPass
 * отработал — иначе Domain Service aliases модуля GitIdentity отсутствовали бы и
 * компиляция упала с ServiceNotFoundException (Cannot autowire TokenCacheInterface).
 */
#[CoversClass(ModuleCompilerPass::class)]
final class ModuleSystemIntegrationTest extends TestCase
{
    private Kernel $kernel;

    #[\Override]
    protected function setUp(): void
    {
        $this->kernel = new Kernel('test', false);
        $this->kernel->boot();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    #[Test]
    public function kernelBootsWithModuleSystem(): void
    {
        // boot() в setUp() отработал без исключений — значит ModuleCompilerPass
        // подключил Resource/config/services.yaml модуля GitIdentity и весь граф
        // зависимостей (включая agent:token-команду) успешно скомпилирован.
        self::assertTrue($this->kernel->getContainer()->isCompiled());
    }

    #[Test]
    public function gitIdentityModuleParametersAreLoadedViaModuleSystem(): void
    {
        // Параметры module.git_identity.* объявлены в Resource/config/services.yaml
        // модуля и появляются в контейнере только если ModuleCompilerPass отработал.
        $container = $this->kernel->getContainer();

        $packageDir = $container->getParameter('task_orchestrator.package_dir');
        $moduleDir = $container->getParameter('module.git_identity.module_dir');
        $cacheDir = $container->getParameter('module.git_identity.cache_dir');

        self::assertSame($packageDir . '/src/Module/GitIdentity', $moduleDir);
        self::assertSame($packageDir . '/var/cache/git-identity', $cacheDir);
    }

    #[Test]
    public function gitIdentityCommandIsRegistered(): void
    {
        // Команда agent:token зависит от ObtainTokenCommandHandler, который, в свою
        // очередь, требует aliases из Resource/config/services.yaml модуля. Наличие
        // команды в command_loader доказывает, что весь граф модуля зарезолвился
        // на этапе компиляции (иначе compile() упал бы).
        $container = $this->kernel->getContainer();
        self::assertTrue($container->has('console.command_loader'));

        /** @var CommandLoaderInterface $loader */
        $loader = $container->get('console.command_loader');
        self::assertTrue($loader->has('agent:token'));
    }

    #[Test]
    public function gitIdentityModuleReturnsExpectedConfigPath(): void
    {
        $module = new GitIdentityModule();
        $packageDir = $this->kernel->getProjectDir();

        self::assertSame($packageDir . '/src/Module/GitIdentity', $module->getModuleDir());
        self::assertSame($module->getModuleDir() . '/Resource/config', $module->getModuleConfigPath());
    }
}
