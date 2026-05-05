<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChainRunRequestVo::class)]
final class ChainRunRequestVoTest extends TestCase
{
    #[Test]
    public function itDefaultsNoContextFilesToFalse(): void
    {
        $vo = new ChainRunRequestVo(role: 'test', task: 'task');

        self::assertFalse($vo->hasNoContextFiles());
    }

    #[Test]
    public function itSetsNoContextFilesToTrue(): void
    {
        $vo = new ChainRunRequestVo(
            role: 'test',
            task: 'task',
            noContextFiles: true,
        );

        self::assertTrue($vo->hasNoContextFiles());
    }

    #[Test]
    public function withTruncatedContextPreservesNoContextFilesTrue(): void
    {
        $vo = new ChainRunRequestVo(
            role: 'test',
            task: 'task',
            previousContext: str_repeat('x', 1000),
            maxContextLength: 500,
            noContextFiles: true,
        );
        $result = $vo->toTruncatedContext();

        self::assertNotSame($vo, $result);
        self::assertTrue($result->hasNoContextFiles());
    }

    #[Test]
    public function withTruncatedContextPreservesNoContextFilesFalse(): void
    {
        $vo = new ChainRunRequestVo(
            role: 'test',
            task: 'task',
            previousContext: str_repeat('x', 1000),
            maxContextLength: 500,
            noContextFiles: false,
        );
        $result = $vo->toTruncatedContext();

        self::assertNotSame($vo, $result);
        self::assertFalse($result->hasNoContextFiles());
    }

    #[Test]
    public function withTruncatedContextReturnsSameInstanceWhenContextIsNull(): void
    {
        $vo = new ChainRunRequestVo(role: 'test', task: 'task');
        $result = $vo->toTruncatedContext();

        self::assertSame($vo, $result);
        self::assertFalse($result->hasNoContextFiles());
    }

    #[Test]
    public function withTruncatedContextReturnsSameInstanceWhenContextShortEnough(): void
    {
        $vo = new ChainRunRequestVo(
            role: 'test',
            task: 'task',
            previousContext: 'short context',
            maxContextLength: 50000,
        );
        $result = $vo->toTruncatedContext();

        self::assertSame($vo, $result);
    }
}
