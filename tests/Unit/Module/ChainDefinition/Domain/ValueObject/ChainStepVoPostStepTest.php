<?php

declare(strict_types=1);

namespace Tests\Unit\Module\ChainDefinition\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainStepVo;

#[CoversClass(ChainStepVo::class)]
final class ChainStepVoPostStepTest extends TestCase
{
    // ─── Agent step with post_step ────────────────────────────────────

    #[Test]
    public function agentStepWithPostStep(): void
    {
        $step = ChainStepVo::createFromAgent(
            role: 'developer',
            postStep: 'scripts/notify.sh',
        );

        self::assertTrue($step->hasPostStep());
        self::assertSame('scripts/notify.sh', $step->getPostStep());
    }

    #[Test]
    public function agentStepWithoutPostStep(): void
    {
        $step = ChainStepVo::createFromAgent(role: 'developer');

        self::assertFalse($step->hasPostStep());
        self::assertNull($step->getPostStep());
    }

    // ─── Quality gate step with post_step ─────────────────────────────

    #[Test]
    public function qualityGateStepWithPostStep(): void
    {
        $step = ChainStepVo::createFromQualityGate(
            command: 'vendor/bin/phpcs',
            label: 'PHPCS',
            postStep: 'scripts/log-quality.sh',
        );

        self::assertTrue($step->hasPostStep());
        self::assertSame('scripts/log-quality.sh', $step->getPostStep());
    }

    #[Test]
    public function qualityGateStepWithoutPostStep(): void
    {
        $step = ChainStepVo::createFromQualityGate(
            command: 'vendor/bin/phpcs',
            label: 'PHPCS',
        );

        self::assertFalse($step->hasPostStep());
        self::assertNull($step->getPostStep());
    }

    // ─── Constructor directly ─────────────────────────────────────────

    #[Test]
    public function constructorWithPostStep(): void
    {
        $step = new ChainStepVo(
            type: \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainStepTypeEnum::agent,
            role: 'analyst',
            postStep: 'scripts/hook.sh',
        );

        self::assertTrue($step->hasPostStep());
        self::assertSame('scripts/hook.sh', $step->getPostStep());
    }

    #[Test]
    public function constructorWithNullPostStep(): void
    {
        $step = new ChainStepVo(
            type: \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainStepTypeEnum::agent,
            role: 'analyst',
            postStep: null,
        );

        self::assertFalse($step->hasPostStep());
        self::assertNull($step->getPostStep());
    }
}
