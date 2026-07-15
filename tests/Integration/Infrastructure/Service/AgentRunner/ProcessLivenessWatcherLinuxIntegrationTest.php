<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Infrastructure\Service\AgentRunner;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Kernel;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\{
    ProcFilesystemComponent,
    ProcessLivenessClockComponent,
    ProcessLivenessProbeLinuxProcfsComponent,
    ProcessLivenessProbeUnavailableComponent,
    ProcessLivenessSleeperComponent,
};
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\ProcessLivenessWatcher;

/**
 * Real-process Linux contract: procfs без ps/pgrep/procps/pcntl.
 */
#[CoversClass(ProcessLivenessWatcher::class)]
#[CoversClass(ProcessLivenessProbeLinuxProcfsComponent::class)]
final class ProcessLivenessWatcherLinuxIntegrationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel(['environment' => 'test', 'debug' => false]);

        if (PHP_OS_FAMILY !== 'Linux' || !is_dir('/proc/self')) {
            self::markTestSkipped('Linux procfs is required for this integration contract.');
        }

        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC=1');
    }

    protected function tearDown(): void
    {
        try {
            putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC');
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
    public function waitForCpuOnlyParentSurvivesIdleThresholdAndFinishes(): void
    {
        // Arrange
        $process = $this->process($this->busyLoopCode(3.0));
        $watcher = $this->linuxWatcher();
        $process->start();

        // Act
        $completed = $watcher->waitFor($process);

        // Assert
        self::assertTrue($completed);
        self::assertTrue($process->isSuccessful());
        self::assertSame(0, $process->getExitCode());
    }

    #[Test]
    public function waitForIdleParentStopsAfterConfirmedInactivity(): void
    {
        // Arrange
        $process = $this->process('sleep(120);');
        $watcher = $this->linuxWatcher();
        $process->start();

        // Act
        $completed = $watcher->waitFor($process);

        // Assert
        self::assertFalse($completed);
        self::assertFalse($process->isRunning());
    }

    #[Test]
    public function waitForParentWithCpuActiveDirectChildFinishes(): void
    {
        // Arrange
        $parentCode = $this->childSequenceCode([$this->busyLoopCode(3.0)]);
        $process = $this->process($parentCode);
        $watcher = $this->linuxWatcher();
        $process->start();

        // Act
        $completed = $watcher->waitFor($process);

        // Assert
        self::assertTrue($completed);
        self::assertTrue($process->isSuccessful());
    }

    #[Test]
    public function waitForParentWithSequentialShortLivedChildrenFinishes(): void
    {
        // Arrange
        $parentCode = $this->childSequenceCode([
            $this->busyLoopCode(0.8),
            $this->busyLoopCode(0.8),
            $this->busyLoopCode(0.8),
            $this->busyLoopCode(0.8),
        ]);
        $process = $this->process($parentCode);
        $watcher = $this->linuxWatcher();
        $process->start();

        // Act
        $completed = $watcher->waitFor($process);

        // Assert
        self::assertTrue($completed);
        self::assertTrue($process->isSuccessful());
    }

    #[Test]
    public function waitForUnavailableProbeStillEnforcesHardCap(): void
    {
        // Arrange
        $process = new Process([PHP_BINARY, '-r', 'sleep(120);']);
        $process->setTimeout(1.0);
        $watcher = new ProcessLivenessWatcher(
            probe: new ProcessLivenessProbeUnavailableComponent(),
            clock: new ProcessLivenessClockComponent(),
            sleeper: new ProcessLivenessSleeperComponent(),
        );
        $process->start();

        // Act + Assert
        try {
            $watcher->waitFor($process);
            self::fail('Hard cap must remain enabled in UNKNOWN mode.');
        } catch (ProcessTimedOutException $exception) {
            self::assertSame($process, $exception->getProcess());
        }

        self::assertFalse($process->isRunning());
    }

    private function linuxWatcher(): ProcessLivenessWatcher
    {
        return new ProcessLivenessWatcher(
            probe: new ProcessLivenessProbeLinuxProcfsComponent(new ProcFilesystemComponent()),
            clock: new ProcessLivenessClockComponent(),
            sleeper: new ProcessLivenessSleeperComponent(),
        );
    }

    private function process(string $code): Process
    {
        $process = new Process([PHP_BINARY, '-r', $code]);
        $process->setTimeout(15.0);

        return $process;
    }

    private function busyLoopCode(float $seconds): string
    {
        return sprintf(
            '$until = microtime(true) + %.3F; $value = 0; '
            . 'while (microtime(true) < $until) { $value = ($value + 1) %% 1000003; } '
            . 'exit($value < 0 ? 1 : 0);',
            $seconds,
        );
    }

    /**
     * @param list<string> $childCodes
     */
    private function childSequenceCode(array $childCodes): string
    {
        $exportedCodes = var_export($childCodes, true);
        $exportedPhpBinary = var_export(PHP_BINARY, true);

        return sprintf(
            <<<'PHP'
foreach (%s as $childCode) {
    $pipes = [];
    $child = proc_open(
        [%s, '-r', $childCode],
        [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', '/dev/null', 'a'],
            2 => ['file', '/dev/null', 'a'],
        ],
        $pipes,
    );
    if (!is_resource($child)) {
        exit(2);
    }
    $exitCode = proc_close($child);
    if ($exitCode !== 0) {
        exit(3);
    }
}
PHP,
            $exportedCodes,
            $exportedPhpBinary,
        );
    }
}
