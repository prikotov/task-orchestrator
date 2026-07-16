<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Bin;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Проверяет публичный CLI-контракт `task-orchestrator --version`.
 */
final class VersionCliTest extends TestCase
{
    private string $projectRoot;
    private string $runtimeDir;

    #[Override]
    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 3);
        $this->runtimeDir = sys_get_temp_dir() . '/to-version-cli-' . bin2hex(random_bytes(6));
    }

    #[Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->runtimeDir);
    }

    #[Test]
    public function sourceCheckoutDisplaysDevVersion(): void
    {
        // Arrange / Act
        $process = $this->runVersionCommand(null);

        // Assert
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame("Task Orchestrator dev\n", $process->getOutput());
    }

    #[Test]
    public function explicitReleaseDisplaysExactSemVer(): void
    {
        // Arrange / Act
        $process = $this->runVersionCommand('0.2.1-rc.1+build.5');

        // Assert
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame("Task Orchestrator 0.2.1-rc.1+build.5\n", $process->getOutput());
    }

    private function runVersionCommand(?string $releaseVersion): Process
    {
        $process = new Process(
            [PHP_BINARY, 'bin/task-orchestrator', '--version'],
            $this->projectRoot,
            [
                'APP_CACHE_DIR' => $this->runtimeDir . '/cache',
                'APP_LOG_DIR' => $this->runtimeDir . '/log',
                'APP_RELEASE_VERSION' => $releaseVersion ?? false,
            ],
        );
        $process->run();

        return $process;
    }
}
