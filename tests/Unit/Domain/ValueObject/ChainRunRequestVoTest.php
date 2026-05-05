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
        $context = $vo->getPreviousContext();
        $truncated = strlen($context) > $vo->getMaxContextLength()
            ? substr($context, -$vo->getMaxContextLength())
            : $context;
        $result = new ChainRunRequestVo(
            role: $vo->getRole(),
            task: $vo->getTask(),
            systemPrompt: $vo->getSystemPrompt(),
            previousContext: $truncated,
            model: $vo->getModel(),
            tools: $vo->getTools(),
            workingDir: $vo->getWorkingDir(),
            timeout: $vo->getTimeout(),
            maxContextLength: $vo->getMaxContextLength(),
            command: $vo->getCommand(),
            runnerArgs: $vo->getRunnerArgs(),
            runnerName: $vo->getRunnerName(),
            noContextFiles: $vo->hasNoContextFiles(),
        );

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
        $context = $vo->getPreviousContext();
        $truncated = strlen($context) > $vo->getMaxContextLength()
            ? substr($context, -$vo->getMaxContextLength())
            : $context;
        $result = new ChainRunRequestVo(
            role: $vo->getRole(),
            task: $vo->getTask(),
            systemPrompt: $vo->getSystemPrompt(),
            previousContext: $truncated,
            model: $vo->getModel(),
            tools: $vo->getTools(),
            workingDir: $vo->getWorkingDir(),
            timeout: $vo->getTimeout(),
            maxContextLength: $vo->getMaxContextLength(),
            command: $vo->getCommand(),
            runnerArgs: $vo->getRunnerArgs(),
            runnerName: $vo->getRunnerName(),
            noContextFiles: $vo->hasNoContextFiles(),
        );

        self::assertFalse($result->hasNoContextFiles());
    }

    #[Test]
    public function withTruncatedContextReturnsSameInstanceWhenContextIsNull(): void
    {
        $vo = new ChainRunRequestVo(role: 'test', task: 'task');
        $context = $vo->getPreviousContext();

        self::assertNull($context);
        self::assertFalse($vo->hasNoContextFiles());
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

        self::assertSame('short context', $vo->getPreviousContext());
    }
}
