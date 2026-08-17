<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\DependencyInjection;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use TaskOrchestrator\Common\Kernel;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\CodexAgentRunnerService;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Pi\PiAgentRunnerService;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\ProcessLivenessWatcher;

/**
 * Интеграционная проверка DI-связки runner-специфичных liveness watcher-ов.
 */
#[CoversClass(ProcessLivenessWatcher::class)]
final class AgentRunnerLivenessWatcherIntegrationTest extends KernelTestCase
{
    /** @var array<string, string|false> */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['AGENT_RUNNER_IDLE_TIMEOUT_SEC', 'AGENT_RUNNER_CODEX_IDLE_TIMEOUT_SEC'] as $name) {
            $this->originalEnv[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->originalEnv as $name => $value) {
                putenv($value === false ? $name : $name . '=' . $value);
            }
        } finally {
            parent::tearDown();
        }
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    #[Test]
    public function containerWiresIndependentRunnerSpecificIdleThresholds(): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => false]);
        $container = self::getContainer();

        $codexRunner = $container->get(CodexAgentRunnerService::class);
        $piRunner = $container->get(PiAgentRunnerService::class);
        self::assertInstanceOf(CodexAgentRunnerService::class, $codexRunner);
        self::assertInstanceOf(PiAgentRunnerService::class, $piRunner);

        $codexWatcher = $this->watcherFrom($codexRunner);
        $piWatcher = $this->watcherFrom($piRunner);

        self::assertNotSame($codexWatcher, $piWatcher);
        self::assertSame(330, $codexWatcher->getIdleThreshold());
        self::assertSame(60, $piWatcher->getIdleThreshold());

        putenv('AGENT_RUNNER_CODEX_IDLE_TIMEOUT_SEC=420');
        self::assertSame(420, $codexWatcher->getIdleThreshold());
        self::assertSame(60, $piWatcher->getIdleThreshold());

        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC=75');
        self::assertSame(420, $codexWatcher->getIdleThreshold());
        self::assertSame(75, $piWatcher->getIdleThreshold());
    }

    private function watcherFrom(CodexAgentRunnerService|PiAgentRunnerService $runner): ProcessLivenessWatcher
    {
        $property = new ReflectionProperty($runner, 'livenessWatcher');
        $watcher = $property->getValue($runner);
        self::assertInstanceOf(ProcessLivenessWatcher::class, $watcher);

        return $watcher;
    }
}
