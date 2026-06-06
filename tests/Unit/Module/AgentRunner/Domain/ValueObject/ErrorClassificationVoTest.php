<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\AgentRunner\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\Enum\ErrorClassificationEnum;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\ErrorClassificationVo;

#[CoversClass(ErrorClassificationVo::class)]
final class ErrorClassificationVoTest extends TestCase
{
    // ─── Rule 1: isTimedOut() == true → TRANSIENT ───────────────────────

    #[Test]
    public function timedOutResultClassifiedAsTransient(): void
    {
        $result = AgentResultVo::createError(
            errorMessage: 'Agent timed out',
            timedOut: true,
        );

        $classification = ErrorClassificationVo::createFromClassification($result);

        self::assertSame(ErrorClassificationEnum::transient, $classification->getClassification());
        self::assertTrue($classification->shouldRetry());
    }

    // ─── Rule 2: exitCode >= 100 → FATAL ────────────────────────────────

    #[Test]
    public function exitCode100ClassifiedAsFatal(): void
    {
        $result = AgentResultVo::createError(
            errorMessage: 'Process crash',
            exitCode: 100,
        );

        $classification = ErrorClassificationVo::createFromClassification($result);

        self::assertSame(ErrorClassificationEnum::fatal, $classification->getClassification());
        self::assertFalse($classification->shouldRetry());
    }

    #[Test]
    public function exitCode137ClassifiedAsFatal(): void
    {
        $result = AgentResultVo::createError(
            errorMessage: 'SIGKILL',
            exitCode: 137,
        );

        $classification = ErrorClassificationVo::createFromClassification($result);

        self::assertSame(ErrorClassificationEnum::fatal, $classification->getClassification());
        self::assertFalse($classification->shouldRetry());
    }

    #[Test]
    public function exitCode255ClassifiedAsFatal(): void
    {
        $result = AgentResultVo::createError(
            errorMessage: 'Invalid API key',
            exitCode: 255,
        );

        $classification = ErrorClassificationVo::createFromClassification($result);

        self::assertSame(ErrorClassificationEnum::fatal, $classification->getClassification());
        self::assertFalse($classification->shouldRetry());
    }

    // ─── Rule 3: exitCode == 0 + isError → UNKNOWN ──────────────────────

    #[Test]
    public function exitCode0WithErrorClassifiedAsUnknown(): void
    {
        $result = AgentResultVo::createError(
            errorMessage: 'Anomaly',
            exitCode: 0,
        );

        $classification = ErrorClassificationVo::createFromClassification($result);

        self::assertSame(ErrorClassificationEnum::unknown, $classification->getClassification());
        self::assertTrue($classification->shouldRetry());
    }

    // ─── Rule 4: exitCode > 0 && < 100 → TRANSIENT (default) ────────────

    #[Test]
    public function exitCode1ClassifiedAsTransient(): void
    {
        $result = AgentResultVo::createError(
            errorMessage: 'Rate limit exceeded',
            exitCode: 1,
        );

        $classification = ErrorClassificationVo::createFromClassification($result);

        self::assertSame(ErrorClassificationEnum::transient, $classification->getClassification());
        self::assertTrue($classification->shouldRetry());
    }

    #[Test]
    public function exitCode2ClassifiedAsTransient(): void
    {
        $result = AgentResultVo::createError(
            errorMessage: 'Connection refused',
            exitCode: 2,
        );

        $classification = ErrorClassificationVo::createFromClassification($result);

        self::assertSame(ErrorClassificationEnum::transient, $classification->getClassification());
        self::assertTrue($classification->shouldRetry());
    }

    #[Test]
    public function exitCode99ClassifiedAsTransient(): void
    {
        $result = AgentResultVo::createError(
            errorMessage: 'Some error',
            exitCode: 99,
        );

        $classification = ErrorClassificationVo::createFromClassification($result);

        self::assertSame(ErrorClassificationEnum::transient, $classification->getClassification());
    }

    // ─── Priority: timeout overrides exitCode >= 100 ────────────────────

    #[Test]
    public function timedOutWithHighExitCodeStillTransient(): void
    {
        $result = AgentResultVo::createError(
            errorMessage: 'Timeout before crash',
            exitCode: 137,
            timedOut: true,
        );

        $classification = ErrorClassificationVo::createFromClassification($result);

        self::assertSame(ErrorClassificationEnum::transient, $classification->getClassification());
        self::assertTrue($classification->shouldRetry());
    }

    // ─── Success result (not error) → TRANSIENT (default branch) ────────

    #[Test]
    public function successResultClassifiedAsTransient(): void
    {
        $result = AgentResultVo::createSuccess(outputText: 'OK');

        $classification = ErrorClassificationVo::createFromClassification($result);

        self::assertSame(ErrorClassificationEnum::transient, $classification->getClassification());
    }

    // ─── classifyFromException → TRANSIENT ──────────────────────────────

    #[Test]
    public function exceptionClassifiedAsTransient(): void
    {
        $classification = ErrorClassificationVo::createFromClassException();

        self::assertSame(ErrorClassificationEnum::transient, $classification->getClassification());
        self::assertTrue($classification->shouldRetry());
    }

    // ─── equals() ────────────────────────────────────────────────────────

    #[Test]
    public function equalsReturnsTrueForSameClassification(): void
    {
        $a = ErrorClassificationVo::createFromClassification(
            AgentResultVo::createError(errorMessage: 'x', exitCode: 1),
        );
        $b = ErrorClassificationVo::createFromClassification(
            AgentResultVo::createError(errorMessage: 'y', exitCode: 2),
        );

        self::assertTrue($a->equals($b)); // both TRANSIENT
    }

    #[Test]
    public function equalsReturnsFalseForDifferentClassification(): void
    {
        $transient = ErrorClassificationVo::createFromClassification(
            AgentResultVo::createError(errorMessage: 'x', exitCode: 1),
        );
        $fatal = ErrorClassificationVo::createFromClassification(
            AgentResultVo::createError(errorMessage: 'y', exitCode: 100),
        );

        self::assertFalse($transient->equals($fatal));
    }
}
