<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\ChainExecution\Domain\Service\Static;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Agent\RunAgentServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ExecuteStaticStepService;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\FormatPromptServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\QualityGateRunnerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ResolveChainRunnerServiceInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ToolStepRunnerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionToolStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StaticStepResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ToolStepResultVo;

#[CoversClass(ExecuteStaticStepService::class)]
final class ExecuteStaticStepServiceToolTest extends TestCase
{
    #[Test]
    public function runToolStepReturnsSuccessfulResult(): void
    {
        $toolRunner = $this->createMock(ToolStepRunnerInterface::class);
        $toolRunner->method('run')->willReturn(new ToolStepResultVo(
            exitCode: 0,
            stdout: 'abc123def',
            success: true,
            durationMs: 50.0,
        ));

        $service = new ExecuteStaticStepService(
            agentRunner: $this->createMock(RunAgentServiceInterface::class),
            runnerHelper: $this->createMock(ResolveChainRunnerServiceInterface::class),
            formatter: $this->createMock(FormatPromptServiceInterface::class),
            qualityGateRunner: $this->createMock(QualityGateRunnerInterface::class),
            toolStepRunner: $toolRunner,
        );

        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::tool,
            command: 'git rev-parse HEAD',
            label: 'Get commit',
            timeoutSeconds: 30,
            outputKey: 'commit_hash',
        );

        $result = $service->runToolStep($step);

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
    public function runToolStepReturnsErrorOnFailure(): void
    {
        $toolRunner = $this->createMock(ToolStepRunnerInterface::class);
        $toolRunner->method('run')->willReturn(new ToolStepResultVo(
            exitCode: 1,
            stdout: 'fatal: not a git repository',
            success: false,
            durationMs: 10.0,
        ));

        $service = new ExecuteStaticStepService(
            agentRunner: $this->createMock(RunAgentServiceInterface::class),
            runnerHelper: $this->createMock(ResolveChainRunnerServiceInterface::class),
            formatter: $this->createMock(FormatPromptServiceInterface::class),
            qualityGateRunner: $this->createMock(QualityGateRunnerInterface::class),
            toolStepRunner: $toolRunner,
        );

        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::tool,
            command: 'git status',
            label: 'Git status',
        );

        $result = $service->runToolStep($step);

        self::assertTrue($result->isError);
        self::assertSame('fatal: not a git repository', $result->outputText);
        self::assertSame(1, $result->exitCode);
        self::assertNotNull($result->errorMessage);
        self::assertStringContainsString('failed', $result->errorMessage);
    }

    #[Test]
    public function runToolStepNoOpWithoutRunner(): void
    {
        $service = new ExecuteStaticStepService(
            agentRunner: $this->createMock(RunAgentServiceInterface::class),
            runnerHelper: $this->createMock(ResolveChainRunnerServiceInterface::class),
            formatter: $this->createMock(FormatPromptServiceInterface::class),
            qualityGateRunner: $this->createMock(QualityGateRunnerInterface::class),
            toolStepRunner: null,
        );

        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::tool,
            command: 'echo hi',
            label: 'Echo',
        );

        $result = $service->runToolStep($step);

        self::assertFalse($result->isError);
        self::assertSame('', $result->outputText);
        self::assertSame(0, $result->exitCode);
    }

    #[Test]
    public function runToolStepPassesCorrectVoToRunner(): void
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

        $service = new ExecuteStaticStepService(
            agentRunner: $this->createMock(RunAgentServiceInterface::class),
            runnerHelper: $this->createMock(ResolveChainRunnerServiceInterface::class),
            formatter: $this->createMock(FormatPromptServiceInterface::class),
            qualityGateRunner: $this->createMock(QualityGateRunnerInterface::class),
            toolStepRunner: $toolRunner,
        );

        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::tool,
            command: 'git log -1 --format=%s',
            label: 'Last commit msg',
            timeoutSeconds: 15,
            outputKey: 'last_msg',
        );

        $service->runToolStep($step);
    }
}
