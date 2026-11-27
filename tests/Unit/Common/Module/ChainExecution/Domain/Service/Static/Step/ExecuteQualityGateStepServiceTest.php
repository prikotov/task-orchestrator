<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\ChainExecution\Domain\Service\Static\Step;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\QualityGateRunnerInterface;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static\ExecuteQualityGateStepService;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionQualityGateVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionStepVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\QualityGateResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\StepContextVo;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(ExecuteQualityGateStepService::class)]
final class ExecuteQualityGateStepServiceTest extends TestCase
{
    #[Test]
    public function supportsReturnsTrueForQualityGateType(): void
    {
        $runner = new ExecuteQualityGateStepService();
        self::assertTrue($runner->supports(ChainStepTypeEnum::qualityGate));
    }

    #[Test]
    public function supportsReturnsFalseForOtherTypes(): void
    {
        $runner = new ExecuteQualityGateStepService();
        self::assertFalse($runner->supports(ChainStepTypeEnum::agent));
        self::assertFalse($runner->supports(ChainStepTypeEnum::tool));
    }

    #[Test]
    public function runReturnsPassedResult(): void
    {
        $gateRunner = $this->createMock(QualityGateRunnerInterface::class);
        $gateRunner->method('run')->willReturn(new QualityGateResultVo(
            label: 'Lint check',
            passed: true,
            exitCode: 0,
            output: '',
            durationMs: 150.0,
        ));

        $runner = new ExecuteQualityGateStepService(qualityGateRunner: $gateRunner);

        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::qualityGate,
            command: 'make lint',
            label: 'Lint check',
        );

        $context = new StepContextVo(task: 'test task');
        $result = $runner->run($step, $context);

        self::assertSame('quality_gate', $result->getRole());
        self::assertSame('shell', $result->getRunner());
        self::assertTrue($result->isPassed());
        self::assertSame('Lint check', $result->getLabel());
        self::assertSame(0, $result->getExitCode());
        self::assertFalse($result->isError());
        self::assertGreaterThan(0.0, $result->getDuration());
    }

    #[Test]
    public function runReturnsFailedResult(): void
    {
        $gateRunner = $this->createMock(QualityGateRunnerInterface::class);
        $gateRunner->method('run')->willReturn(new QualityGateResultVo(
            label: 'Tests',
            passed: false,
            exitCode: 1,
            output: 'FAILURES! 2 tests failed.',
            durationMs: 500.0,
        ));

        $runner = new ExecuteQualityGateStepService(qualityGateRunner: $gateRunner);

        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::qualityGate,
            command: 'phpunit',
            label: 'Tests',
        );

        $context = new StepContextVo(task: 'test task');
        $result = $runner->run($step, $context);

        self::assertFalse($result->isPassed());
        self::assertSame(1, $result->getExitCode());
        self::assertSame('FAILURES! 2 tests failed.', $result->getOutputText());
        self::assertFalse($result->isError());
    }

    #[Test]
    public function runNoOpWithoutRunner(): void
    {
        $runner = new ExecuteQualityGateStepService(qualityGateRunner: null);

        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::qualityGate,
            command: 'make check',
            label: 'Check',
        );

        $context = new StepContextVo(task: 'test task');
        $result = $runner->run($step, $context);

        self::assertTrue($result->isPassed());
        self::assertSame('', $result->getOutputText());
        self::assertSame(0.0, $result->getDuration());
        self::assertSame('Check', $result->getLabel());
    }

    #[Test]
    public function runPassesCorrectVoToRunner(): void
    {
        $gateRunner = $this->createMock(QualityGateRunnerInterface::class);
        $gateRunner->expects($this->once())->method('run')->with(
            $this->callback(function (ExecutionQualityGateVo $vo): bool {
                return $vo->getCommand() === 'make check'
                    && $vo->getLabel() === 'Quality Gate'
                    && $vo->getTimeoutSeconds() === 120;
            }),
        )->willReturn(new QualityGateResultVo(
            label: 'Quality Gate',
            passed: true,
            exitCode: 0,
            output: 'OK',
            durationMs: 200.0,
        ));

        $runner = new ExecuteQualityGateStepService(qualityGateRunner: $gateRunner);

        $step = new ExecutionStepVo(
            type: ChainStepTypeEnum::qualityGate,
            command: 'make check',
            label: 'Quality Gate',
            timeoutSeconds: 120,
        );

        $context = new StepContextVo(task: 'test task');
        $runner->run($step, $context);
    }
}
