<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TaskOrchestrator\Common\DependencyInjection\TaskOrchestratorExtension;

#[CoversClass(TaskOrchestratorExtension::class)]
final class TaskOrchestratorExtensionTest extends TestCase
{
    #[Test]
    public function excludesResourcePhpFilesFromServiceDiscovery(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $container = new ContainerBuilder();

        (new TaskOrchestratorExtension())->load([
            [
                'roles_dir' => $projectRoot . '/docs/agents/roles/team',
                'base_path' => $projectRoot,
                'chains_yaml' => $projectRoot . '/config/chains.yaml',
                'chains_session_dir' => $projectRoot . '/var/sessions',
            ],
        ], $container);

        $bridgeResourceId = 'TaskOrchestrator\\Common\\Module\\AgentRunner\\Infrastructure\\Service\\Codex\\Resources\\bridge';

        self::assertSame($projectRoot, $container->getParameter('task_orchestrator.package_dir'));
        self::assertTrue($container->hasDefinition($bridgeResourceId));

        $definition = $container->getDefinition($bridgeResourceId);
        self::assertTrue($definition->isAbstract());
        self::assertNotSame([], $definition->getTag('container.excluded'));
    }
}
