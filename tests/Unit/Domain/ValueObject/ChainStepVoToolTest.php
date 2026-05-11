<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use InvalidArgumentException;
use LogicException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ToolStepVo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChainStepVo::class)]
#[CoversClass(ToolStepVo::class)]
final class ChainStepVoToolTest extends TestCase
{
    // ── Tool step: constructor ──────────────────────────────────────────────

    #[Test]
    public function toolConstructorSetsFields(): void
    {
        $step = new ChainStepVo(
            type: ChainStepTypeEnum::tool,
            command: 'git rev-parse HEAD',
            label: 'Get commit',
            timeoutSeconds: 30,
            outputKey: 'commit_hash',
        );

        self::assertSame(ChainStepTypeEnum::tool, $step->getType());
        self::assertTrue($step->isTool());
        self::assertFalse($step->isAgent());
        self::assertFalse($step->isQualityGate());
        self::assertSame('git rev-parse HEAD', $step->getCommand());
        self::assertSame('Get commit', $step->getLabel());
        self::assertSame(30, $step->getTimeoutSeconds());
        self::assertSame('commit_hash', $step->getOutputKey());
    }

    #[Test]
    public function toolConstructorRequiresCommand(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tool step must have a command.');

        new ChainStepVo(
            type: ChainStepTypeEnum::tool,
            label: 'Test',
        );
    }

    #[Test]
    public function toolConstructorRequiresLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tool step must have a label.');

        new ChainStepVo(
            type: ChainStepTypeEnum::tool,
            command: 'echo hi',
        );
    }

    // ── Tool step: factory method ───────────────────────────────────────────

    #[Test]
    public function createToolReturnsToolStep(): void
    {
        $step = ChainStepVo::createTool(
            command: 'git branch --show-current',
            label: 'Get branch',
            timeoutSeconds: 15,
            outputKey: 'branch',
            name: 'get_branch',
        );

        self::assertSame(ChainStepTypeEnum::tool, $step->getType());
        self::assertTrue($step->isTool());
        self::assertSame('git branch --show-current', $step->getCommand());
        self::assertSame('Get branch', $step->getLabel());
        self::assertSame(15, $step->getTimeoutSeconds());
        self::assertSame('branch', $step->getOutputKey());
        self::assertSame('get_branch', $step->getName());
    }

    #[Test]
    public function createToolDefaultsWithoutOutputKey(): void
    {
        $step = ChainStepVo::createTool(
            command: 'echo test',
            label: 'Echo test',
        );

        self::assertSame(120, $step->getTimeoutSeconds());
        self::assertNull($step->getOutputKey());
    }

    // ── toToolStepVo conversion ─────────────────────────────────────────────

    #[Test]
    public function toToolStepVoReturnsCorrectVo(): void
    {
        $step = ChainStepVo::createTool(
            command: 'git log -1 --format=%s',
            label: 'Last commit msg',
            timeoutSeconds: 10,
            outputKey: 'last_msg',
        );

        $vo = $step->toToolStepVo();

        self::assertInstanceOf(ToolStepVo::class, $vo);
        self::assertSame('git log -1 --format=%s', $vo->command);
        self::assertSame('Last commit msg', $vo->label);
        self::assertSame(10, $vo->timeoutSeconds);
        self::assertSame('last_msg', $vo->outputKey);
    }

    #[Test]
    public function toToolStepVoThrowsForAgentStep(): void
    {
        $step = ChainStepVo::createAgent(role: 'developer');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Only tool steps can be converted to ToolStepVo.');

        $step->toToolStepVo();
    }

    #[Test]
    public function toToolStepVoThrowsForQualityGateStep(): void
    {
        $step = ChainStepVo::createQualityGate(
            command: 'make test',
            label: 'Tests',
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Only tool steps can be converted to ToolStepVo.');

        $step->toToolStepVo();
    }

    // ── Tool step outputKey is null for agent/quality_gate ──────────────────

    #[Test]
    public function agentStepOutputKeyIsNull(): void
    {
        $step = ChainStepVo::createAgent(role: 'developer');

        self::assertNull($step->getOutputKey());
    }

    #[Test]
    public function qualityGateStepOutputKeyIsNull(): void
    {
        $step = ChainStepVo::createQualityGate(
            command: 'make test',
            label: 'Tests',
        );

        self::assertNull($step->getOutputKey());
    }
}
