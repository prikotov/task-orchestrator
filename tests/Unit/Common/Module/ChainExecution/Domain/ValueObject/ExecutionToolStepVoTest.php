<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\ChainExecution\Domain\ValueObject;

use InvalidArgumentException;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionToolStepVo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExecutionToolStepVo::class)]
final class ExecutionToolStepVoTest extends TestCase
{
    #[Test]
    public function constructorSetsRequiredFields(): void
    {
        $vo = new ExecutionToolStepVo(
            command: 'git status',
            label: 'Git status',
        );

        self::assertSame('git status', $vo->command);
        self::assertSame('Git status', $vo->label);
        self::assertSame(120, $vo->timeoutSeconds);
        self::assertNull($vo->outputKey);
    }

    #[Test]
    public function constructorSetsAllFields(): void
    {
        $vo = new ExecutionToolStepVo(
            command: 'echo test',
            label: 'Echo',
            timeoutSeconds: 60,
            outputKey: 'echo_result',
        );

        self::assertSame(60, $vo->timeoutSeconds);
        self::assertSame('echo_result', $vo->outputKey);
    }

    #[Test]
    public function constructorRejectsEmptyCommand(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ExecutionToolStepVo::command must not be empty.');

        new ExecutionToolStepVo(command: '', label: 'Test');
    }

    #[Test]
    public function constructorRejectsEmptyLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ExecutionToolStepVo::label must not be empty.');

        new ExecutionToolStepVo(command: 'echo hi', label: '');
    }
}
