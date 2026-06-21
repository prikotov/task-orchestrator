<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection\ModuleCompilerPass;
use TaskOrchestrator\Common\DependencyInjection\TaskOrchestratorExtension;
use TaskOrchestrator\Common\Module\GitIdentity\Application\UseCase\Command\ObtainToken\ObtainTokenCommandHandler;
use TaskOrchestrator\Common\Module\GitIdentity\GitIdentityModule;

/**
 * Интеграционная проверка универсального механизма ModuleSystem:
 * Extension::load() + compile() контейнера должны поднять сервисы модуля
 * GitIdentity через ModuleCompilerPass, зарегистрированный из реестра
 * config/modules.php.
 *
 * Ключевое свойство: если ModuleCompilerPass НЕ отработал, то Domain Service
 * aliases из Resource/config/services.yaml модуля GitIdentity отсутствуют и
 * compile() падает с ServiceNotFoundException (Cannot autowire service
 * TokenCacheInterface для ObtainTokenCommandHandler). Таким образом, успешная
 * компиляция сама по себе доказывает, что ModuleCompilerPass загрузил
 * конфигурацию модуля.
 */
#[CoversClass(TaskOrchestratorExtension::class)]
#[CoversClass(ModuleCompilerPass::class)]
final class ModuleSystemIntegrationTest extends TestCase
{
    private ContainerBuilder $container;

    #[\Override]
    protected function setUp(): void
    {
        $projectRoot = dirname(__DIR__, 3);

        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.project_dir', $projectRoot);

        (new TaskOrchestratorExtension())->load([
            [
                'roles_dir' => $projectRoot . '/docs/agents/roles/team',
                'base_path' => $projectRoot,
                'chains_yaml' => $projectRoot . '/config/chains.yaml',
                'chains_session_dir' => $projectRoot . '/var/sessions',
            ],
        ], $this->container);

        // Реальная компиляция: на этом шаге отрабатывает ModuleCompilerPass,
        // зарегистрированный Extension-ом для GitIdentityModule.
        $this->container->compile();
    }

    #[Test]
    public function compilationSucceedsWithModuleSystem(): void
    {
        // setUp() уже вызвал compile() без исключений — это означает, что
        // ModuleCompilerPass отработал и подключил Domain Service aliases
        // модуля GitIdentity, иначе autowire упал бы с ServiceNotFoundException.
        self::assertTrue($this->container->isCompiled());
    }

    #[Test]
    public function gitIdentityModuleParametersAreLoadedViaModuleSystem(): void
    {
        // Параметры module.git_identity.* объявлены в Resource/config/services.yaml
        // модуля и появляются в контейнере только если ModuleCompilerPass отработал.
        $packageDir = $this->container->getParameter('task_orchestrator.package_dir');
        $moduleDir = $this->container->getParameter('module.git_identity.module_dir');
        $cacheDir = $this->container->getParameter('module.git_identity.cache_dir');

        self::assertSame($packageDir . '/src/Module/GitIdentity', $moduleDir);
        self::assertSame($this->container->getParameter('task_orchestrator.base_path') . '/var/cache/git-identity', $cacheDir);
    }

    #[Test]
    public function gitIdentityCommandHandlerIsReachableAfterCompilation(): void
    {
        // ObtainTokenCommandHandler помечаем публичным ПЕРЕД compile() — тогда он
        // не вычищается как приватный неиспользуемый сервис и остаётся доступным
        // через get(). Это доказывает, что handler зарегистрирован (auto-discovery
        // в общем services.yaml) И что его зависимости резолвятся (aliases из
        // Resource/config/services.yaml модуля подгружены ModuleCompilerPass-ом).
        $projectRoot = dirname(__DIR__, 3);
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $projectRoot);

        (new TaskOrchestratorExtension())->load([
            [
                'roles_dir' => $projectRoot . '/docs/agents/roles/team',
                'base_path' => $projectRoot,
                'chains_yaml' => $projectRoot . '/config/chains.yaml',
                'chains_session_dir' => $projectRoot . '/var/sessions',
            ],
        ], $container);

        $handlerId = ObtainTokenCommandHandler::class;
        self::assertTrue($container->hasDefinition($handlerId));
        $container->getDefinition($handlerId)->setPublic(true);

        $container->compile();

        self::assertTrue($container->has($handlerId));
        self::assertInstanceOf(ObtainTokenCommandHandler::class, $container->get($handlerId));
    }

    #[Test]
    public function gitIdentityModuleReturnsExpectedConfigPath(): void
    {
        $module = new GitIdentityModule();
        $projectRoot = dirname(__DIR__, 3);

        self::assertSame($projectRoot . '/src/Module/GitIdentity', $module->getModuleDir());
        self::assertSame($module->getModuleDir() . '/Resource/config', $module->getModuleConfigPath());
    }
}
