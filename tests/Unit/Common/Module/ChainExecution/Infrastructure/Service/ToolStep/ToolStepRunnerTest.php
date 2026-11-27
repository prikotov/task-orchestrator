<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\ChainExecution\Infrastructure\Service\ToolStep;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionToolStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Infrastructure\Service\ToolStep\ToolStepRunnerService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolStepRunnerService::class)]
final class ToolStepRunnerTest extends TestCase
{
    private ToolStepRunnerService $runner;

    protected function setUp(): void
    {
        $this->runner = new ToolStepRunnerService();
    }

    #[Test]
    public function runSuccessfulCommand(): void
    {
        $vo = new ExecutionToolStepVo(
            command: 'echo hello',
            label: 'Echo test',
            timeoutSeconds: 10,
        );

        $result = $this->runner->run($vo);

        self::assertTrue($result->isSuccess());
        self::assertSame(0, $result->getExitCode());
        self::assertStringContainsString('hello', $result->getStdout());
        self::assertGreaterThan(0.0, $result->getDurationMs());
    }

    #[Test]
    public function runFailingCommand(): void
    {
        $vo = new ExecutionToolStepVo(
            command: 'false',
            label: 'Always fail',
            timeoutSeconds: 10,
        );

        $result = $this->runner->run($vo);

        self::assertFalse($result->isSuccess());
        self::assertNotSame(0, $result->getExitCode());
    }

    #[Test]
    public function runCommandWithMultiLineOutput(): void
    {
        $vo = new ExecutionToolStepVo(
            command: 'echo "line1" && echo "line2"',
            label: 'Multi-line',
            timeoutSeconds: 10,
        );

        $result = $this->runner->run($vo);

        self::assertTrue($result->isSuccess());
        self::assertStringContainsString('line1', $result->getStdout());
        self::assertStringContainsString('line2', $result->getStdout());
    }

    #[Test]
    public function runCommandWithExitCode(): void
    {
        $vo = new ExecutionToolStepVo(
            command: 'exit 42',
            label: 'Exit 42',
            timeoutSeconds: 10,
        );

        $result = $this->runner->run($vo);

        self::assertFalse($result->isSuccess());
        self::assertSame(42, $result->getExitCode());
    }
}
