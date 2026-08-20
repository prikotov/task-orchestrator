<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Infrastructure\Service\AgentRunner;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use TaskOrchestrator\Common\Kernel;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\{
    ProcFilesystemComponent,
    ProcessLivenessClockComponent,
    ProcessLivenessProbeLinuxProcfsComponent,
    ProcessLivenessSleeperComponent,
};
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\CodexAgentRunnerService;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\CodexJsonlParser;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Lifecycle\RunAgentProcessLifecycleService;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\ProcessLivenessWatcher;

/**
 * Публичный Codex runner contract поверх Linux liveness policy.
 */
#[CoversClass(CodexAgentRunnerService::class)]
final class CodexAgentRunnerLivenessIntegrationTest extends KernelTestCase
{
    /** @var list<string> */
    private array $fixtureFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel(['environment' => 'test', 'debug' => false]);

        if (PHP_OS_FAMILY !== 'Linux' || !is_dir('/proc/self')) {
            self::markTestSkipped('Linux procfs is required for adaptive liveness integration.');
        }

        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC=1');
        putenv('CODEX_HTTP_PROXY');
    }

    protected function tearDown(): void
    {
        try {
            putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC');
            putenv('CODEX_HTTP_PROXY');

            foreach ($this->fixtureFiles as $fixtureFile) {
                unlink($fixtureFile);
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
    public function runConfirmedIdleProcessReturnsTransientError(): void
    {
        // Arrange
        $runner = $this->runner();
        $command = $this->createExecutableFixture('codex_idle_', 'sleep(120);');

        // Act
        $result = $runner->run(new AgentRunRequestVo(role: 'test', task: 'task', command: [$command]));

        // Assert
        self::assertTrue($result->isError());
        self::assertTrue($result->isTimedOut());
        self::assertStringContainsString('Agent idle', $result->getErrorMessage());
    }

    #[Test]
    public function runCpuOnlyProcessSurvivesAndReturnsSuccess(): void
    {
        // Arrange
        $runner = $this->runner();
        $command = $this->createExecutableFixture('codex_active_', <<<'PHP'
$until = microtime(true) + 3.0;
$value = 0;
while (microtime(true) < $until) {
    $value = ($value + 1) % 1000003;
}
fwrite(STDOUT, "{\"type\":\"turn.completed\",\"usage\":{\"input_tokens\":1,\"output_tokens\":1,\"cost\":0.1}}");
PHP);

        // Act
        $result = $runner->run(new AgentRunRequestVo(role: 'test', task: 'task', command: [$command]));

        // Assert
        self::assertFalse($result->isError());
        self::assertFalse($result->isTimedOut());
        self::assertSame(1, $result->getInputTokens());
    }

    private function runner(): CodexAgentRunnerService
    {
        return new CodexAgentRunnerService(
            parser: new CodexJsonlParser(),
            processLifecycle: new RunAgentProcessLifecycleService(
                livenessWatcher: new ProcessLivenessWatcher(
                    probe: new ProcessLivenessProbeLinuxProcfsComponent(new ProcFilesystemComponent()),
                    clock: new ProcessLivenessClockComponent(),
                    sleeper: new ProcessLivenessSleeperComponent(),
                ),
            ),
        );
    }

    private function createExecutableFixture(string $prefix, string $script): string
    {
        $fixtureFile = tempnam(sys_get_temp_dir(), $prefix);
        if ($fixtureFile === false) {
            self::fail('Unable to create liveness fixture.');
        }

        file_put_contents($fixtureFile, "#!/usr/bin/env php\n<?php\n" . $script);
        chmod($fixtureFile, 0700);
        $this->fixtureFiles[] = $fixtureFile;

        return $fixtureFile;
    }
}
