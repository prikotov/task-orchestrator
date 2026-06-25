<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Component\ModuleSystem\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection\ModuleServiceRegistrar;
use TaskOrchestrator\Tests\Unit\Component\ModuleSystem\DependencyInjection\Fixtures\FakeModule\Application\Service\AnotherConcreteService;
use TaskOrchestrator\Tests\Unit\Component\ModuleSystem\DependencyInjection\Fixtures\FakeModule\Domain\Dto\ExcludedDto;
use TaskOrchestrator\Tests\Unit\Component\ModuleSystem\DependencyInjection\Fixtures\FakeModule\Domain\Service\AbstractBaseService;
use TaskOrchestrator\Tests\Unit\Component\ModuleSystem\DependencyInjection\Fixtures\FakeModule\Domain\Service\ConcreteService;
use TaskOrchestrator\Tests\Unit\Component\ModuleSystem\DependencyInjection\Fixtures\FakeModule\Domain\Service\SomeServiceInterface;
use TaskOrchestrator\Tests\Unit\Component\ModuleSystem\DependencyInjection\Fixtures\FakeModule\Domain\Service\SomeTrait;
use TaskOrchestrator\Tests\Unit\Component\ModuleSystem\DependencyInjection\Fixtures\FakeModule\Domain\Service\StatusEnum;
use TaskOrchestrator\Tests\Unit\Component\ModuleSystem\DependencyInjection\Fixtures\FakeModule\FakeModule;

/**
 * Unit-проверка PHAR-safe {@see ModuleServiceRegistrar}: регистрирует только
 * instantiable-классы, пропускает abstract/interface/trait/enum и exclude-пути,
 * не перетирает явные Definition/Alias (explicit-wins) и ставит autowire+
 * autoconfigure на новых определениях.
 *
 * Фикстуры лежат в {@see \TaskOrchestrator\Tests\Unit\Component\ModuleSystem\DependencyInjection\Fixtures\FakeModule\}
 * и autoloadable по PSR-4 (tests/ → TaskOrchestrator\Tests\), поэтому регистратор
 * может отразить их через ContainerBuilder::getReflectionClass().
 */
#[CoversClass(ModuleServiceRegistrar::class)]
final class ModuleServiceRegistrarTest extends TestCase
{
    private const FIXTURES_MODULE_DIR = __DIR__ . '/Fixtures/FakeModule';

    private const FIXTURES_NAMESPACE =
        'TaskOrchestrator\Tests\Unit\Component\ModuleSystem\DependencyInjection\Fixtures\FakeModule';

    #[Test]
    public function testRegisterCreatesAutowiredDefinitionForConcreteService(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $registrar = $this->createRegistrar();

        // Act
        $registrar->register($container);

        // Assert
        self::assertTrue($container->hasDefinition(ConcreteService::class));
        $definition = $container->getDefinition(ConcreteService::class);
        self::assertTrue($definition->isAutowired(), 'новые определения должны быть autowired');
        self::assertTrue($definition->isAutoconfigured(), 'новые определения должны быть autoconfigured');
    }

    #[Test]
    public function testRegisterDiscoversServicesInNestedDirectories(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $registrar = $this->createRegistrar();

        // Act
        $registrar->register($container);

        // Assert: Application/Service/ — вложенный каталог, обход рекурсивный.
        self::assertTrue($container->hasDefinition(AnotherConcreteService::class));
    }

    #[Test]
    public function testRegisterSkipsAbstractClass(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        // Act
        $this->createRegistrar()->register($container);

        // Assert
        self::assertFalse($container->hasDefinition(AbstractBaseService::class));
    }

    #[Test]
    public function testRegisterSkipsInterface(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        // Act
        $this->createRegistrar()->register($container);

        // Assert
        self::assertFalse($container->hasDefinition(SomeServiceInterface::class));
    }

    #[Test]
    public function testRegisterSkipsTrait(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        // Act
        $this->createRegistrar()->register($container);

        // Assert
        self::assertFalse($container->hasDefinition(SomeTrait::class));
    }

    #[Test]
    public function testRegisterSkipsEnum(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        // Act
        $this->createRegistrar()->register($container);

        // Assert: enum расположен вне exclude-путей — пропущен именно по isEnum().
        self::assertFalse($container->hasDefinition(StatusEnum::class));
    }

    #[Test]
    public function testRegisterSkipsExcludedPath(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        // Act
        $this->createRegistrar()->register($container);

        // Assert: Domain/Dto/ в exclude-списке — класс instantiable, но исключён путём.
        self::assertFalse($container->hasDefinition(ExcludedDto::class));
    }

    #[Test]
    public function testRegisterSkipsModuleClassFile(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        // Act
        $this->createRegistrar()->register($container);

        // Assert: FakeModule.php исключён по имени файла (как *Module.php).
        self::assertFalse($container->hasDefinition(FakeModule::class));
    }

    #[Test]
    public function testExplicitDefinitionWinsOverAutoDiscovery(): void
    {
        // Arrange: явное определение из services.yaml уже в контейнере.
        $container = new ContainerBuilder();
        $explicit = new Definition(ConcreteService::class);
        $explicit->setShared(false);
        $explicit->setAutowired(false);
        $container->setDefinition(ConcreteService::class, $explicit);

        // Act
        $this->createRegistrar()->register($container);

        // Assert: регистратор не перетёр явное определение.
        $definition = $container->getDefinition(ConcreteService::class);
        self::assertFalse($definition->isShared(), 'явное shared=false сохранено');
        self::assertFalse($definition->isAutowired(), 'регистратор не навязал autowire явному определению');
    }

    #[Test]
    public function testExplicitAliasWinsOverAutoDiscovery(): void
    {
        // Arrange: alias интерфейса уже объявлен в services.yaml.
        $container = new ContainerBuilder();
        $container->setAlias(SomeServiceInterface::class, ConcreteService::class);

        // Act
        $this->createRegistrar()->register($container);

        // Assert: для интерфейса остался alias, а не auto-discovered Definition.
        self::assertTrue($container->hasAlias(SomeServiceInterface::class));
        self::assertFalse($container->hasDefinition(SomeServiceInterface::class));
    }

    #[Test]
    public function testPublicOptionMakesDefinitionsPublic(): void
    {
        // Arrange: регистратор с public=true (нужно для команд apps/console).
        $container = new ContainerBuilder();
        $registrar = new ModuleServiceRegistrar(
            serviceDir: self::FIXTURES_MODULE_DIR,
            serviceNamespace: self::FIXTURES_NAMESPACE,
            excludeRelativePaths: ['Domain/Dto/', 'FakeModule.php'],
            public: true,
        );

        // Act
        $registrar->register($container);

        // Assert: по умолчанию definition приватный; public=true делает его public.
        self::assertTrue($container->hasDefinition(ConcreteService::class));
        self::assertTrue(
            $container->getDefinition(ConcreteService::class)->isPublic(),
            'public=true делает новые определения public (нужно для команд apps/console)',
        );
    }

    #[Test]
    public function testDefinitionsArePrivateByDefault(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        // Act
        $this->createRegistrar()->register($container);

        // Assert: без опции public определения остаются приватными (как модули).
        self::assertFalse(
            $container->getDefinition(ConcreteService::class)->isPublic(),
            'по умолчанию определения приватны',
        );
    }

    private function createRegistrar(): ModuleServiceRegistrar
    {
        return new ModuleServiceRegistrar(
            serviceDir: self::FIXTURES_MODULE_DIR,
            serviceNamespace: self::FIXTURES_NAMESPACE,
            excludeRelativePaths: ['Domain/Dto/', 'FakeModule.php'],
        );
    }
}
