<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\ChainExecution\Domain\ValueObject;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionToolStepVo;

#[CoversClass(ExecutionStepVo::class)]
final class ExecutionStepVoToolTest extends TestCase
{
    #[Test]
    public function toolStepProperties(): void
    {
        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::tool,
            command: 'git status',
            label: 'Git status',
            timeoutSeconds: 30,
            outputKey: 'status',
        );

        self::assertSame(ChainStepTypeEnum::tool, $step->getType());
        self::assertTrue($step->isTool());
        self::assertFalse($step->isAgent());
        self::assertFalse($step->isQualityGate());
        self::assertSame('git status', $step->getCommand());
        self::assertSame('Git status', $step->getLabel());
        self::assertSame(30, $step->getTimeoutSeconds());
        self::assertSame('status', $step->getOutputKey());
    }

    #[Test]
    public function toToolStepVoReturnsCorrectVo(): void
    {
        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::tool,
            command: 'echo test',
            label: 'Echo',
            timeoutSeconds: 10,
            outputKey: 'echo_out',
        );

        $vo = $step->toToolStepVo();

        self::assertInstanceOf(ExecutionToolStepVo::class, $vo);
        self::assertSame('echo test', $vo->command);
        self::assertSame('Echo', $vo->label);
        self::assertSame(10, $vo->timeoutSeconds);
        self::assertSame('echo_out', $vo->outputKey);
    }

    #[Test]
    public function toToolStepVoThrowsForAgentStep(): void
    {
        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::agent,
            role: 'developer',
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Only tool steps can be converted to ExecutionToolStepVo.');

        $step->toToolStepVo();
    }

    #[Test]
    public function agentStepOutputKeyIsNull(): void
    {
        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::agent,
            role: 'developer',
        );

        self::assertNull($step->getOutputKey());
    }
}
