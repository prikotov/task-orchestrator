<?php

declare(strict_types=1);

namespace Tests\Unit\Module\ChainDefinition\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\HookResultVo;

#[CoversClass(HookResultVo::class)]
final class HookResultVoTest extends TestCase
{
    // ─── success() ────────────────────────────────────────────────────

    #[Test]
    public function successCreatesSuccessfulResult(): void
    {
        $result = HookResultVo::success(
            command: 'scripts/notify.sh',
            stdout: 'OK',
            stderr: '',
            duration: 0.5,
        );

        self::assertSame('scripts/notify.sh', $result->getCommand());
        self::assertSame(0, $result->getExitCode());
        self::assertSame('OK', $result->getStdout());
        self::assertSame('', $result->getStderr());
        self::assertSame(0.5, $result->getDuration());
        self::assertFalse($result->isTimedOut());
        self::assertTrue($result->isSuccess());
        self::assertFalse($result->isSkipped());
        self::assertFalse($result->isWarning());
        self::assertNull($result->getWarningReason());
    }

    // ─── warning() ────────────────────────────────────────────────────

    #[Test]
    public function warningCreatesWarningResultWithExitCode(): void
    {
        $result = HookResultVo::warning(
            command: 'scripts/notify.sh',
            exitCode: 1,
            stdout: 'partial output',
            stderr: 'error occurred',
            duration: 1.2,
            timedOut: false,
            reason: 'Hook exited with code 1.',
        );

        self::assertSame('scripts/notify.sh', $result->getCommand());
        self::assertSame(1, $result->getExitCode());
        self::assertSame('partial output', $result->getStdout());
        self::assertSame('error occurred', $result->getStderr());
        self::assertSame(1.2, $result->getDuration());
        self::assertFalse($result->isTimedOut());
        self::assertFalse($result->isSuccess());
        self::assertFalse($result->isSkipped());
        self::assertTrue($result->isWarning());
        self::assertSame('Hook exited with code 1.', $result->getWarningReason());
    }

    #[Test]
    public function warningCreatesTimeoutResult(): void
    {
        $result = HookResultVo::warning(
            command: 'scripts/slow.sh',
            exitCode: 137,
            stdout: '',
            stderr: 'killed',
            duration: 30.0,
            timedOut: true,
            reason: 'Hook timed out after 30 seconds.',
        );

        self::assertSame('scripts/slow.sh', $result->getCommand());
        self::assertSame(137, $result->getExitCode());
        self::assertSame(30.0, $result->getDuration());
        self::assertTrue($result->isTimedOut());
        self::assertFalse($result->isSuccess());
        self::assertTrue($result->isWarning());
        self::assertSame('Hook timed out after 30 seconds.', $result->getWarningReason());
    }

    // ─── skipped() ────────────────────────────────────────────────────

    #[Test]
    public function skippedCreatesSkippedResult(): void
    {
        $result = HookResultVo::skipped();

        self::assertSame('', $result->getCommand());
        self::assertSame(0, $result->getExitCode());
        self::assertSame('', $result->getStdout());
        self::assertSame('', $result->getStderr());
        self::assertSame(0.0, $result->getDuration());
        self::assertFalse($result->isTimedOut());
        self::assertFalse($result->isSuccess());
        self::assertTrue($result->isSkipped());
        self::assertFalse($result->isWarning());
        self::assertNull($result->getWarningReason());
    }

    // ─── Edge cases ───────────────────────────────────────────────────

    #[Test]
    public function successWithEmptyOutput(): void
    {
        $result = HookResultVo::success(
            command: 'scripts/silent.sh',
            stdout: '',
            stderr: '',
            duration: 0.01,
        );

        self::assertTrue($result->isSuccess());
        self::assertSame('', $result->getStdout());
        self::assertSame('', $result->getStderr());
    }

    #[Test]
    public function warningWithNegativeExitCode(): void
    {
        $result = HookResultVo::warning(
            command: 'scripts/broken.sh',
            exitCode: -1,
            stdout: '',
            stderr: 'Exception: file not found',
            duration: 0.001,
            timedOut: false,
            reason: 'Hook execution failed: file not found',
        );

        self::assertSame(-1, $result->getExitCode());
        self::assertTrue($result->isWarning());
        self::assertSame('Hook execution failed: file not found', $result->getWarningReason());
    }
}
