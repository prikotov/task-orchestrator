<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\TurnBreakVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\TurnContinueVo;

#[CoversClass(TurnContinueVo::class)]
#[CoversClass(TurnBreakVo::class)]
final class TurnResultVoTest extends TestCase
{
    // ─── TurnContinueVo ────────────────────────────────────────────────

    #[Test]
    public function turnContinueVoDefaultsToNullFields(): void
    {
        $vo = new TurnContinueVo();

        self::assertNull($vo->nextRole);
        self::assertNull($vo->challenge);
    }

    #[Test]
    public function turnContinueVoHoldsNextRoleAndChallenge(): void
    {
        $vo = new TurnContinueVo(nextRole: 'architect', challenge: 'Design it');

        self::assertSame('architect', $vo->nextRole);
        self::assertSame('Design it', $vo->challenge);
    }

    // ─── TurnBreakVo ───────────────────────────────────────────────────

    #[Test]
    public function turnBreakVoDefaultsToNullFields(): void
    {
        $vo = new TurnBreakVo();

        self::assertNull($vo->interruptionReason);
        self::assertNull($vo->synthesis);
        self::assertNull($vo->budgetResult);
    }

    #[Test]
    public function turnBreakVoHoldsSynthesis(): void
    {
        $vo = new TurnBreakVo(synthesis: 'Final answer');

        self::assertSame('Final answer', $vo->synthesis);
        self::assertNull($vo->interruptionReason);
    }

    #[Test]
    public function turnBreakVoHoldsInterruptionReason(): void
    {
        $vo = new TurnBreakVo(interruptionReason: 'agent_error');

        self::assertSame('agent_error', $vo->interruptionReason);
        self::assertNull($vo->synthesis);
    }

    #[Test]
    public function turnBreakVoHoldsBudgetResult(): void
    {
        $budgetCheck = new \TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicBudgetCheckVo(
            shouldBreak: true,
            budgetExceeded: true,
            budgetLimit: 5.0,
        );
        $vo = new TurnBreakVo(
            interruptionReason: 'budget_exceeded',
            budgetResult: $budgetCheck,
        );

        self::assertSame('budget_exceeded', $vo->interruptionReason);
        self::assertSame($budgetCheck, $vo->budgetResult);
        self::assertTrue($vo->budgetResult->budgetExceeded);
    }
}
