<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Component\ModuleSystem\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection\ConsoleServicesRegistrar;

/**
 * Unit-проверка PHAR-safe регистрации сервисов Presentation-слоя
 * {@see ConsoleServicesRegistrar}.
 *
 * Покрывает: регистрацию всех четырёх каталогов apps/console, флаг public,
 * autowired+autoconfigured Definition и explicit-wins (уже существующее
 * определение не перетирается).
 */
#[CoversClass(ConsoleServicesRegistrar::class)]
final class ConsoleServicesRegistrarTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/Fixtures/console-package';

    /**
     * FQCN фикстур соответствуют namespace-контракту регистратора
     * (TaskOrchestrator\Console\Module\...), физически файлы лежат в tests/
     * и грузятся require_once: composer-autoload на фикстуры не замкнут.
     *
     * @return list<string>
     */
    private static function fixtureFiles(): array
    {
        $base = self::FIXTURES_DIR . '/apps/console/src/Module';

        return [
            $base . '/Orchestrator/Command/DemoCommand.php',
            $base . '/Orchestrator/EventSubscriber/DemoSubscriber.php',
            $base . '/GitIdentity/Command/DemoGitCommand.php',
            $base . '/AgentRole/Command/DemoRoleCommand.php',
        ];
    }

    #[Test]
    public function registerAddsPublicAutowiredDefinitionsForAllCatalogs(): void
    {
        foreach (self::fixtureFiles() as $file) {
            require_once $file;
        }

        $container = new ContainerBuilder();
        (new ConsoleServicesRegistrar(self::FIXTURES_DIR))->register($container);

        $expected = [
            'TaskOrchestrator\Console\Module\Orchestrator\Command\DemoCommand',
            'TaskOrchestrator\Console\Module\Orchestrator\EventSubscriber\DemoSubscriber',
            'TaskOrchestrator\Console\Module\GitIdentity\Command\DemoGitCommand',
            'TaskOrchestrator\Console\Module\AgentRole\Command\DemoRoleCommand',
        ];

        foreach ($expected as $fqcn) {
            self::assertTrue($container->hasDefinition($fqcn), "Definition missing: {$fqcn}");

            $definition = $container->getDefinition($fqcn);
            self::assertTrue($definition->isPublic(), "{$fqcn} must be public (commands/subscribers)");
            self::assertTrue($definition->isAutowired(), "{$fqcn} must be autowired");
            self::assertTrue($definition->isAutoconfigured(), "{$fqcn} must be autoconfigured");
        }
    }

    #[Test]
    public function registerKeepsPreExistingDefinition(): void
    {
        foreach (self::fixtureFiles() as $file) {
            require_once $file;
        }

        $fqcn = 'TaskOrchestrator\Console\Module\Orchestrator\Command\DemoCommand';
        $explicit = (new Definition($fqcn))->setPublic(false);

        $container = new ContainerBuilder();
        $container->setDefinition($fqcn, $explicit);

        (new ConsoleServicesRegistrar(self::FIXTURES_DIR))->register($container);

        // explicit-wins: регистратор не перетирает уже заданное определение.
        self::assertSame($explicit, $container->getDefinition($fqcn));
    }
}
