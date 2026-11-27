<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\QualityGateResultVo;
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

        self::assertTrue($result->isPassed());
        self::assertSame(0, $result->getExitCode());
        self::assertSame('Unit Tests', $result->getLabel());
        self::assertSame('OK (42 tests)', $result->getOutput());
        self::assertSame(1234.5, $result->getDurationMs());
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

        self::assertFalse($result->isPassed());
        self::assertSame(1, $result->getExitCode());
    }
}
