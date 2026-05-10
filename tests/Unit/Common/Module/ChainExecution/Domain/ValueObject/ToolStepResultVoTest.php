<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\ChainExecution\Domain\ValueObject;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ToolStepResultVo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolStepResultVo::class)]
final class ToolStepResultVoTest extends TestCase
{
    #[Test]
    public function constructorSetsFields(): void
    {
        $vo = new ToolStepResultVo(
            exitCode: 0,
            stdout: 'abc123',
            success: true,
            durationMs: 42.5,
        );

        self::assertSame(0, $vo->exitCode);
        self::assertSame('abc123', $vo->stdout);
        self::assertTrue($vo->success);
        self::assertSame(42.5, $vo->durationMs);
    }

    #[Test]
    public function failedResult(): void
    {
        $vo = new ToolStepResultVo(
            exitCode: 1,
            stdout: 'error output',
            success: false,
            durationMs: 10.0,
        );

        self::assertSame(1, $vo->exitCode);
        self::assertFalse($vo->success);
    }
}
