<?php

declare(strict_types=1);

namespace Tests\Unit\Module\ChainDefinition\Infrastructure\Service\Chain\Hook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\HookResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Infrastructure\Service\Chain\Hook\ShellHookExecutorService;

#[CoversClass(ShellHookExecutorService::class)]
final class ShellHookExecutorTest extends TestCase
{
    private string $tempDir;
    private ShellHookExecutorService $executor;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/hook_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->executor = new ShellHookExecutorService($this->logger);
    }

    protected function tearDown(): void
    {
        // Clean up temp scripts
        $files = glob($this->tempDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    // ─── Success path ─────────────────────────────────────────────────

    #[Test]
    public function executeSuccessfulScriptReturnsSuccess(): void
    {
        $scriptPath = $this->createScript('success.sh', "#!/bin/sh\nexit 0\n");

        $result = $this->executor->execute($scriptPath, [
            'chain_name' => 'test-chain',
            'step_name' => 'dev',
        ]);

        self::assertTrue($result->isSuccess());
        self::assertFalse($result->isWarning());
        self::assertFalse($result->isSkipped());
        self::assertSame(0, $result->getExitCode());
        self::assertFalse($result->isTimedOut());
    }

    // ─── Failure path ─────────────────────────────────────────────────

    #[Test]
    public function executeFailingScriptReturnsWarning(): void
    {
        $scriptPath = $this->createScript('fail.sh', "#!/bin/sh\necho 'error output' >&2\nexit 1\n");

        $result = $this->executor->execute($scriptPath);

        self::assertFalse($result->isSuccess());
        self::assertTrue($result->isWarning());
        self::assertFalse($result->isSkipped());
        self::assertSame(1, $result->getExitCode());
        self::assertFalse($result->isTimedOut());
        self::assertStringContainsString('error output', $result->getStderr());
    }

    // ─── Timeout path ─────────────────────────────────────────────────

    #[Test]
    public function executeTimeoutScriptReturnsWarning(): void
    {
        // Script that sleeps for 60 seconds (exceeds 30s timeout)
        $scriptPath = $this->createScript('slow.sh', "#!/bin/sh\nsleep 60\n");

        $result = $this->executor->execute($scriptPath);

        self::assertFalse($result->isSuccess());
        self::assertTrue($result->isWarning());
        self::assertTrue($result->isTimedOut());
        self::assertNotNull($result->getWarningReason());
        self::assertStringContainsStringIgnoringCase('timed out', $result->getWarningReason());
    }

    // ─── Non-existent script ──────────────────────────────────────────

    #[Test]
    public function executeNonExistentScriptReturnsWarning(): void
    {
        $result = $this->executor->execute('/nonexistent/path/script.sh');

        self::assertFalse($result->isSuccess());
        self::assertTrue($result->isWarning());
        // sh returns non-zero exit code for non-existent files (127 on Linux, 2 on some systems)
        self::assertNotSame(0, $result->getExitCode());
    }

    // ─── Stdout/stderr capture ────────────────────────────────────────

    #[Test]
    public function executeCapturesStdout(): void
    {
        $scriptPath = $this->createScript('output.sh', "#!/bin/sh\necho 'hello world'\nexit 0\n");

        $result = $this->executor->execute($scriptPath);

        self::assertTrue($result->isSuccess());
        self::assertStringContainsString('hello world', $result->getStdout());
    }

    #[Test]
    public function executeCapturesStderr(): void
    {
        $scriptPath = $this->createScript('stderr.sh', "#!/bin/sh\necho 'warn message' >&2\nexit 0\n");

        $result = $this->executor->execute($scriptPath);

        self::assertTrue($result->isSuccess());
        self::assertStringContainsString('warn message', $result->getStderr());
    }

    // ─── Duration tracking ────────────────────────────────────────────

    #[Test]
    public function executeTracksDuration(): void
    {
        $scriptPath = $this->createScript('duration.sh', "#!/bin/sh\nexit 0\n");

        $result = $this->executor->execute($scriptPath);

        self::assertGreaterThanOrEqual(0.0, $result->getDuration());
    }

    // ─── Env vars from context ────────────────────────────────────────

    #[Test]
    public function executePassesContextAsEnvVars(): void
    {
        // Script that echoes env vars
        $scriptPath = $this->createScript('env.sh', "#!/bin/sh\necho \"CHAIN=\$HOOK_CHAIN_NAME\"\necho \"STEP=\$HOOK_STEP_NAME\"\nexit 0\n");

        $result = $this->executor->execute($scriptPath, [
            'chain_name' => 'my-chain',
            'step_name' => 'implement',
            'runner' => 'pi',
            'exit_code' => 0,
            'duration' => 1.5,
            'role' => 'developer',
        ]);

        self::assertTrue($result->isSuccess());
        self::assertStringContainsString('CHAIN=my-chain', $result->getStdout());
        self::assertStringContainsString('STEP=implement', $result->getStdout());
    }

    #[Test]
    public function executeSkipsNullContextValues(): void
    {
        $scriptPath = $this->createScript('env_null.sh', "#!/bin/sh\necho \"STEP=\${HOOK_STEP_NAME:-unset}\"\nexit 0\n");

        $result = $this->executor->execute($scriptPath, [
            'chain_name' => 'test',
            'step_name' => null,
        ]);

        self::assertTrue($result->isSuccess());
        self::assertStringContainsString('STEP=unset', $result->getStdout());
    }

    // ─── Logging ──────────────────────────────────────────────────────

    #[Test]
    public function executeLogsHookStart(): void
    {
        $scriptPath = $this->createScript('log_start.sh', "#!/bin/sh\nexit 0\n");

        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->with($this->stringContains('Hook'), $this->anything());

        $this->executor->execute($scriptPath);
    }

    #[Test]
    public function executeLogsWarningOnFailure(): void
    {
        $scriptPath = $this->createScript('log_fail.sh', "#!/bin/sh\nexit 1\n");

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with(
                $this->isType('string'),
                $this->callback(fn(array $ctx) => isset($ctx['exitCode']) && $ctx['exitCode'] === 1),
            );

        $this->executor->execute($scriptPath);
    }

    // ─── Empty context ────────────────────────────────────────────────

    #[Test]
    public function executeWithEmptyContext(): void
    {
        $scriptPath = $this->createScript('empty_ctx.sh', "#!/bin/sh\nexit 0\n");

        $result = $this->executor->execute($scriptPath, []);

        self::assertTrue($result->isSuccess());
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    private function createScript(string $name, string $content): string
    {
        $path = $this->tempDir . '/' . $name;
        file_put_contents($path, $content);
        chmod($path, 0755);

        return $path;
    }
}
