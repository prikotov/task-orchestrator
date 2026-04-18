<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
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

        self::assertFalse($vo->getNoContextFiles());
    }

    #[Test]
    public function itSetsNoContextFilesToTrue(): void
    {
        $vo = new ChainRunRequestVo(
            role: 'test',
            task: 'task',
            noContextFiles: true,
        );

        self::assertTrue($vo->getNoContextFiles());
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
        $result = $vo->withTruncatedContext();

        self::assertNotSame($vo, $result);
        self::assertTrue($result->getNoContextFiles());
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
        $result = $vo->withTruncatedContext();

        self::assertNotSame($vo, $result);
        self::assertFalse($result->getNoContextFiles());
    }

    #[Test]
    public function withTruncatedContextReturnsSameInstanceWhenContextIsNull(): void
    {
        $vo = new ChainRunRequestVo(role: 'test', task: 'task');
        $result = $vo->withTruncatedContext();

        self::assertSame($vo, $result);
        self::assertFalse($result->getNoContextFiles());
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
        $result = $vo->withTruncatedContext();

        self::assertSame($vo, $result);
    }
}
