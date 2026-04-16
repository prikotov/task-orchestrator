<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Tests\Unit\Domain\ValueObject;

use TasK\Orchestrator\Domain\ValueObject\QualityGateVo;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(QualityGateVo::class)]
final class QualityGateVoTest extends TestCase
{
    #[Test]
    public function createsWithRequiredFields(): void
    {
        $gate = new QualityGateVo(
            command: 'make tests-unit',
            label: 'Unit Tests',
        );

        self::assertSame('make tests-unit', $gate->command);
        self::assertSame('Unit Tests', $gate->label);
        self::assertSame(120, $gate->timeoutSeconds);
    }

    #[Test]
    public function createsWithCustomTimeout(): void
    {
        $gate = new QualityGateVo(
            command: 'make lint-php',
            label: 'PHP CodeSniffer',
            timeoutSeconds: 60,
        );

        self::assertSame(60, $gate->timeoutSeconds);
    }

    #[Test]
    public function throwsOnEmptyCommand(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('command must not be empty');

        new QualityGateVo(command: '', label: 'Test');
    }

    #[Test]
    public function throwsOnWhitespaceOnlyCommand(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('command must not be empty');

        new QualityGateVo(command: '   ', label: 'Test');
    }

    #[Test]
    public function throwsOnEmptyLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('label must not be empty');

        new QualityGateVo(command: 'make lint', label: '');
    }

    #[Test]
    public function throwsOnWhitespaceOnlyLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('label must not be empty');

        new QualityGateVo(command: 'make lint', label: '   ');
    }
}
