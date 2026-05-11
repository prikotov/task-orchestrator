<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\ChainExecution\Domain\Service\Static\Step;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\Step\ExecuteToolStepService;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ToolStepRunnerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionToolStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticStepResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StepContextVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ToolStepResultVo;

#[CoversClass(ExecuteToolStepService::class)]
final class ExecuteToolStepServiceTest extends TestCase
{
    #[Test]
    public function supportsReturnsTrueForToolType(): void
    {
        $strategy = new ExecuteToolStepService();
        self::assertTrue($strategy->supports(ChainStepTypeEnum::tool));
    }

    #[Test]
    public function supportsReturnsFalseForOtherTypes(): void
    {
        $strategy = new ExecuteToolStepService();
        self::assertFalse($strategy->supports(ChainStepTypeEnum::agent));
        self::assertFalse($strategy->supports(ChainStepTypeEnum::qualityGate));
    }

    #[Test]
    public function runReturnsSuccessfulResult(): void
    {
        $toolRunner = $this->createMock(ToolStepRunnerInterface::class);
        $toolRunner->method('run')->willReturn(new ToolStepResultVo(
            exitCode: 0,
            stdout: 'abc123def',
            success: true,
            durationMs: 50.0,
        ));

        $strategy = new ExecuteToolStepService(toolStepRunner: $toolRunner);

        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::tool,
            command: 'git rev-parse HEAD',
            label: 'Get commit',
            timeoutSeconds: 30,
            outputKey: 'commit_hash',
        );

        $context = new StepContextVo(task: 'test task');
        $result = $strategy->run($step, $context);

        self::assertFalse($result->isError);
        self::assertSame('abc123def', $result->outputText);
        self::assertSame('tool', $result->role);
        self::assertSame('shell', $result->runner);
        self::assertSame('Get commit', $result->label);
        self::assertSame(0, $result->exitCode);
        self::assertSame('commit_hash', $result->outputKey);
        self::assertGreaterThan(0.0, $result->duration);
    }

    #[Test]
    public function runReturnsErrorOnFailure(): void
    {
        $toolRunner = $this->createMock(ToolStepRunnerInterface::class);
        $toolRunner->method('run')->willReturn(new ToolStepResultVo(
            exitCode: 1,
            stdout: 'fatal: not a git repository',
            success: false,
            durationMs: 10.0,
        ));

        $strategy = new ExecuteToolStepService(toolStepRunner: $toolRunner);

        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::tool,
            command: 'git status',
            label: 'Git status',
        );

        $context = new StepContextVo(task: 'test task');
        $result = $strategy->run($step, $context);

        self::assertTrue($result->isError);
        self::assertSame('fatal: not a git repository', $result->outputText);
        self::assertSame(1, $result->exitCode);
        self::assertNotNull($result->errorMessage);
        self::assertStringContainsString('failed', $result->errorMessage);
    }

    #[Test]
    public function runNoOpWithoutRunner(): void
    {
        $strategy = new ExecuteToolStepService(toolStepRunner: null);

        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::tool,
            command: 'echo hi',
            label: 'Echo',
        );

        $context = new StepContextVo(task: 'test task');
        $result = $strategy->run($step, $context);

        self::assertFalse($result->isError);
        self::assertSame('', $result->outputText);
        self::assertSame(0, $result->exitCode);
    }

    #[Test]
    public function runPassesCorrectVoToRunner(): void
    {
        $toolRunner = $this->createMock(ToolStepRunnerInterface::class);
        $toolRunner->expects($this->once())->method('run')->with(
            $this->callback(function (ExecutionToolStepVo $vo): bool {
                return $vo->command === 'git log -1 --format=%s'
                    && $vo->label === 'Last commit msg'
                    && $vo->timeoutSeconds === 15
                    && $vo->outputKey === 'last_msg';
            }),
        )->willReturn(new ToolStepResultVo(
            exitCode: 0,
            stdout: 'feat: add tool step',
            success: true,
            durationMs: 20.0,
        ));

        $strategy = new ExecuteToolStepService(toolStepRunner: $toolRunner);

        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::tool,
            command: 'git log -1 --format=%s',
            label: 'Last commit msg',
            timeoutSeconds: 15,
            outputKey: 'last_msg',
        );

        $context = new StepContextVo(task: 'test task');
        $strategy->run($step, $context);
    }
}
