<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainStepVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\QualityGateVo;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChainStepVo::class)]
final class ChainStepVoTest extends TestCase
{
    // ── Agent step: constructor ──────────────────────────────────────────────

    #[Test]
    public function agentConstructorSetsDefaults(): void
    {
        $step = new ChainStepVo(
            type: ChainStepTypeEnum::agent,
            role: 'developer',
        );

        self::assertSame(ChainStepTypeEnum::agent, $step->getType());
        self::assertSame('developer', $step->getRole());
        self::assertSame('pi', $step->getRunner());
        self::assertNull($step->getTools());
        self::assertNull($step->getModel());
        self::assertNull($step->getRetryPolicy());
        self::assertNull($step->getName());
        self::assertFalse($step->getNoContextFiles());
        self::assertTrue($step->isAgent());
        self::assertFalse($step->isQualityGate());
    }

    #[Test]
    public function agentConstructorRequiresRole(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Agent step must have a role.');

        new ChainStepVo(type: ChainStepTypeEnum::agent);
    }

    #[Test]
    public function agentConstructorRejectsEmptyRole(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Agent step must have a role.');

        new ChainStepVo(type: ChainStepTypeEnum::agent, role: '');
    }

    // ── Agent step: factory method ───────────────────────────────────────────

    #[Test]
    public function agentFactoryCreatesAgentStep(): void
    {
        $step = ChainStepVo::agent(
            role: 'backend_developer',
            runner: 'codex',
            name: 'implement',
        );

        self::assertSame(ChainStepTypeEnum::agent, $step->getType());
        self::assertSame('backend_developer', $step->getRole());
        self::assertSame('codex', $step->getRunner());
        self::assertSame('implement', $step->getName());
        self::assertTrue($step->isAgent());
    }

    #[Test]
    public function agentFactoryCreatesAgentStepWithNoContextFiles(): void
    {
        $step = ChainStepVo::agent(
            role: 'backend_developer',
            noContextFiles: true,
        );

        self::assertTrue($step->getNoContextFiles());
    }

    #[Test]
    public function agentFactoryDefaultsNoContextFilesToFalse(): void
    {
        $step = ChainStepVo::agent(role: 'developer');

        self::assertFalse($step->getNoContextFiles());
    }

    // ── Quality gate step: constructor ───────────────────────────────────────

    #[Test]
    public function qualityGateConstructorSetsFields(): void
    {
        $step = new ChainStepVo(
            type: ChainStepTypeEnum::qualityGate,
            command: 'make lint-php',
            label: 'Lint',
            timeoutSeconds: 60,
        );

        self::assertSame(ChainStepTypeEnum::qualityGate, $step->getType());
        self::assertNull($step->getRole());
        self::assertSame('make lint-php', $step->getCommand());
        self::assertSame('Lint', $step->getLabel());
        self::assertSame(60, $step->getTimeoutSeconds());
        self::assertFalse($step->getNoContextFiles());
        self::assertTrue($step->isQualityGate());
        self::assertFalse($step->isAgent());
    }

    #[Test]
    public function qualityGateConstructorRequiresCommand(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quality gate step must have a command.');

        new ChainStepVo(
            type: ChainStepTypeEnum::qualityGate,
            label: 'Test',
        );
    }

    #[Test]
    public function qualityGateConstructorRequiresLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quality gate step must have a label.');

        new ChainStepVo(
            type: ChainStepTypeEnum::qualityGate,
            command: 'make test',
        );
    }

    // ── Quality gate step: factory method ────────────────────────────────────

    #[Test]
    public function qualityGateFactoryCreatesGateStep(): void
    {
        $step = ChainStepVo::qualityGate(
            command: 'make tests-unit',
            label: 'Unit Tests',
            timeoutSeconds: 120,
            name: 'unit_tests',
        );

        self::assertSame(ChainStepTypeEnum::qualityGate, $step->getType());
        self::assertSame('make tests-unit', $step->getCommand());
        self::assertSame('Unit Tests', $step->getLabel());
        self::assertSame(120, $step->getTimeoutSeconds());
        self::assertSame('unit_tests', $step->getName());
    }

    // ── toQualityGateVo conversion ───────────────────────────────────────────

    #[Test]
    public function toQualityGateVoReturnsCorrectVo(): void
    {
        $step = ChainStepVo::qualityGate(
            command: 'make lint-php',
            label: 'Lint',
            timeoutSeconds: 60,
        );

        $vo = $step->toQualityGateVo();

        self::assertInstanceOf(QualityGateVo::class, $vo);
        self::assertSame('make lint-php', $vo->command);
        self::assertSame('Lint', $vo->label);
        self::assertSame(60, $vo->timeoutSeconds);
    }

    #[Test]
    public function toQualityGateVoThrowsForAgentStep(): void
    {
        $step = ChainStepVo::agent(role: 'developer');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only quality_gate steps can be converted to QualityGateVo.');

        $step->toQualityGateVo();
    }
}
