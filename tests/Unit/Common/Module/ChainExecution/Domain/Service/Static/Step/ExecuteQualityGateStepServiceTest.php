<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\ChainExecution\Domain\Service\Static\Step;

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

        self::assertSame('quality_gate', $result->role);
        self::assertSame('shell', $result->runner);
        self::assertTrue($result->passed);
        self::assertSame('Lint check', $result->label);
        self::assertSame(0, $result->exitCode);
        self::assertFalse($result->isError);
        self::assertGreaterThan(0.0, $result->duration);
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

        self::assertFalse($result->passed);
        self::assertSame(1, $result->exitCode);
        self::assertSame('FAILURES! 2 tests failed.', $result->outputText);
        self::assertFalse($result->isError);
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

        self::assertTrue($result->passed);
        self::assertSame('', $result->outputText);
        self::assertSame(0.0, $result->duration);
        self::assertSame('Check', $result->label);
    }

    #[Test]
    public function runPassesCorrectVoToRunner(): void
    {
        $gateRunner = $this->createMock(QualityGateRunnerInterface::class);
        $gateRunner->expects($this->once())->method('run')->with(
            $this->callback(function (ExecutionQualityGateVo $vo): bool {
                return $vo->command === 'make check'
                    && $vo->label === 'Quality Gate'
                    && $vo->timeoutSeconds === 120;
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
