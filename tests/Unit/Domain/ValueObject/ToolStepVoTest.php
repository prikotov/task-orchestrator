<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use InvalidArgumentException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ToolStepVo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolStepVo::class)]
final class ToolStepVoTest extends TestCase
{
    // ── Constructor: valid ──────────────────────────────────────────────────

    #[Test]
    public function constructorSetsRequiredFields(): void
    {
        $vo = new ToolStepVo(
            command: 'git rev-parse HEAD',
            label: 'Get commit hash',
        );

        self::assertSame('git rev-parse HEAD', $vo->command);
        self::assertSame('Get commit hash', $vo->label);
        self::assertSame(120, $vo->timeoutSeconds);
        self::assertNull($vo->outputKey);
    }

    #[Test]
    public function constructorSetsAllFields(): void
    {
        $vo = new ToolStepVo(
            command: 'git branch --show-current',
            label: 'Get branch',
            timeoutSeconds: 30,
            outputKey: 'current_branch',
        );

        self::assertSame('git branch --show-current', $vo->command);
        self::assertSame('Get branch', $vo->label);
        self::assertSame(30, $vo->timeoutSeconds);
        self::assertSame('current_branch', $vo->outputKey);
    }

    // ── Constructor: validation ─────────────────────────────────────────────

    #[Test]
    public function constructorRejectsEmptyCommand(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ToolStepVo::command must not be empty.');

        new ToolStepVo(command: '', label: 'Test');
    }

    #[Test]
    public function constructorRejectsWhitespaceCommand(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ToolStepVo::command must not be empty.');

        new ToolStepVo(command: '   ', label: 'Test');
    }

    #[Test]
    public function constructorRejectsEmptyLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ToolStepVo::label must not be empty.');

        new ToolStepVo(command: 'echo hi', label: '');
    }

    #[Test]
    public function constructorRejectsWhitespaceLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ToolStepVo::label must not be empty.');

        new ToolStepVo(command: 'echo hi', label: '  ');
    }

    #[Test]
    public function constructorRejectsEmptyOutputKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ToolStepVo::outputKey must not be empty string.');

        new ToolStepVo(command: 'echo hi', label: 'Test', outputKey: '');
    }

    #[Test]
    public function constructorAcceptsNullOutputKey(): void
    {
        $vo = new ToolStepVo(command: 'echo hi', label: 'Test', outputKey: null);

        self::assertNull($vo->outputKey);
    }
}
