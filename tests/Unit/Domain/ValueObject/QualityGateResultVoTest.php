<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Tests\Unit\Domain\ValueObject;

use TasK\Orchestrator\Domain\ValueObject\QualityGateResultVo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(QualityGateResultVo::class)]
final class QualityGateResultVoTest extends TestCase
{
    #[Test]
    public function passedGateHasCorrectState(): void
    {
        $result = new QualityGateResultVo(
            label: 'Unit Tests',
            passed: true,
            exitCode: 0,
            output: 'OK (42 tests)',
            durationMs: 1234.5,
        );

        self::assertTrue($result->passed);
        self::assertSame(0, $result->exitCode);
        self::assertSame('Unit Tests', $result->label);
        self::assertSame('OK (42 tests)', $result->output);
        self::assertSame(1234.5, $result->durationMs);
    }

    #[Test]
    public function failedGateHasCorrectState(): void
    {
        $result = new QualityGateResultVo(
            label: 'PHP CodeSniffer',
            passed: false,
            exitCode: 1,
            output: 'FOUND 3 ERRORS',
            durationMs: 567.0,
        );

        self::assertFalse($result->passed);
        self::assertSame(1, $result->exitCode);
    }
}
