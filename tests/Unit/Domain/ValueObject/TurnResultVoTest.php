<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\TurnBreakVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\TurnContinueVo;

#[CoversClass(TurnContinueVo::class)]
#[CoversClass(TurnBreakVo::class)]
final class TurnResultVoTest extends TestCase
{
    // ─── TurnContinueVo ────────────────────────────────────────────────

    #[Test]
    public function turnContinueVoDefaultsToNullFields(): void
    {
        $vo = new TurnContinueVo();

        self::assertNull($vo->getNextRole());
        self::assertNull($vo->getChallenge());
    }

    #[Test]
    public function turnContinueVoHoldsNextRoleAndChallenge(): void
    {
        $vo = new TurnContinueVo(nextRole: 'architect', challenge: 'Design it');

        self::assertSame('architect', $vo->getNextRole());
        self::assertSame('Design it', $vo->getChallenge());
    }

    // ─── TurnBreakVo ───────────────────────────────────────────────────

    #[Test]
    public function turnBreakVoDefaultsToNullFields(): void
    {
        $vo = new TurnBreakVo();

        self::assertNull($vo->getInterruptionReason());
        self::assertNull($vo->getSynthesis());
        self::assertNull($vo->getBudgetResult());
    }

    #[Test]
    public function turnBreakVoHoldsSynthesis(): void
    {
        $vo = new TurnBreakVo(synthesis: 'Final answer');

        self::assertSame('Final answer', $vo->getSynthesis());
        self::assertNull($vo->getInterruptionReason());
    }

    #[Test]
    public function turnBreakVoHoldsInterruptionReason(): void
    {
        $vo = new TurnBreakVo(interruptionReason: 'agent_error');

        self::assertSame('agent_error', $vo->getInterruptionReason());
        self::assertNull($vo->getSynthesis());
    }

    #[Test]
    public function turnBreakVoHoldsBudgetResult(): void
    {
        $budgetCheck = new \TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicBudgetCheckVo(
            shouldBreak: true,
            budgetExceeded: true,
            budgetLimit: 5.0,
        );
        $vo = new TurnBreakVo(
            interruptionReason: 'budget_exceeded',
            budgetResult: $budgetCheck,
        );

        self::assertSame('budget_exceeded', $vo->getInterruptionReason());
        self::assertSame($budgetCheck, $vo->getBudgetResult());
        self::assertTrue($vo->getBudgetResult()->isBudgetExceeded());
    }
}
