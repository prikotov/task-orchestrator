<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Tests\Unit\Domain\ValueObject;

use TasK\Orchestrator\Domain\ValueObject\BudgetVo;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BudgetVo::class)]
final class BudgetVoTest extends TestCase
{
    #[Test]
    public function defaultBudgetIsUnlimited(): void
    {
        $vo = new BudgetVo();

        self::assertNull($vo->getMaxCostTotal());
        self::assertNull($vo->getMaxCostPerStep());
        self::assertTrue($vo->isUnlimited());
    }

    #[Test]
    public function budgetWithLimitsIsNotUnlimited(): void
    {
        $vo = new BudgetVo(maxCostTotal: 5.0, maxCostPerStep: 2.0);

        self::assertSame(5.0, $vo->getMaxCostTotal());
        self::assertSame(2.0, $vo->getMaxCostPerStep());
        self::assertFalse($vo->isUnlimited());
    }

    #[Test]
    public function budgetWithOnlyTotalIsNotUnlimited(): void
    {
        $vo = new BudgetVo(maxCostTotal: 10.0);

        self::assertSame(10.0, $vo->getMaxCostTotal());
        self::assertNull($vo->getMaxCostPerStep());
        self::assertFalse($vo->isUnlimited());
    }

    #[Test]
    public function budgetWithOnlyStepLimitIsNotUnlimited(): void
    {
        $vo = new BudgetVo(maxCostPerStep: 1.5);

        self::assertNull($vo->getMaxCostTotal());
        self::assertSame(1.5, $vo->getMaxCostPerStep());
        self::assertFalse($vo->isUnlimited());
    }

    #[Test]
    public function isWithinTotalBudgetReturnsTrueWhenUnlimited(): void
    {
        $vo = new BudgetVo();

        self::assertTrue($vo->isWithinTotalBudget(999999.0));
    }

    #[Test]
    public function isWithinTotalBudgetReturnsTrueWhenUnderLimit(): void
    {
        $vo = new BudgetVo(maxCostTotal: 5.0);

        self::assertTrue($vo->isWithinTotalBudget(4.99));
        self::assertTrue($vo->isWithinTotalBudget(5.0));
    }

    #[Test]
    public function isWithinTotalBudgetReturnsFalseWhenOverLimit(): void
    {
        $vo = new BudgetVo(maxCostTotal: 5.0);

        self::assertFalse($vo->isWithinTotalBudget(5.01));
        self::assertFalse($vo->isWithinTotalBudget(10.0));
    }

    #[Test]
    public function isWithinStepBudgetReturnsTrueWhenUnlimited(): void
    {
        $vo = new BudgetVo();

        self::assertTrue($vo->isWithinStepBudget(999999.0));
    }

    #[Test]
    public function isWithinStepBudgetReturnsTrueWhenUnderLimit(): void
    {
        $vo = new BudgetVo(maxCostPerStep: 2.0);

        self::assertTrue($vo->isWithinStepBudget(1.99));
        self::assertTrue($vo->isWithinStepBudget(2.0));
    }

    #[Test]
    public function isWithinStepBudgetReturnsFalseWhenOverLimit(): void
    {
        $vo = new BudgetVo(maxCostPerStep: 2.0);

        self::assertFalse($vo->isWithinStepBudget(2.01));
        self::assertFalse($vo->isWithinStepBudget(5.0));
    }

    #[Test]
    public function zeroBudgetAllowsZero(): void
    {
        $vo = new BudgetVo(maxCostTotal: 0.0, maxCostPerStep: 0.0);

        self::assertTrue($vo->isWithinTotalBudget(0.0));
        self::assertTrue($vo->isWithinStepBudget(0.0));
        self::assertFalse($vo->isWithinTotalBudget(0.01));
        self::assertFalse($vo->isWithinStepBudget(0.01));
    }

    #[Test]
    public function throwsOnNegativeTotal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxCostTotal must be >= 0 or null.');

        new BudgetVo(maxCostTotal: -1.0);
    }

    #[Test]
    public function throwsOnNegativeStepLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxCostPerStep must be >= 0 or null.');

        new BudgetVo(maxCostPerStep: -0.5);
    }

    #[Test]
    public function fromArrayCreatesBudgetWithBothLimits(): void
    {
        $vo = BudgetVo::fromArray([
            'max_cost_total' => 5.0,
            'max_cost_per_step' => 2.0,
        ]);

        self::assertSame(5.0, $vo->getMaxCostTotal());
        self::assertSame(2.0, $vo->getMaxCostPerStep());
    }

    #[Test]
    public function fromArrayCreatesBudgetWithIntegerValues(): void
    {
        $vo = BudgetVo::fromArray([
            'max_cost_total' => 10,
            'max_cost_per_step' => 3,
        ]);

        self::assertSame(10.0, $vo->getMaxCostTotal());
        self::assertSame(3.0, $vo->getMaxCostPerStep());
    }

    #[Test]
    public function fromArrayCreatesUnlimitedBudgetFromEmptyArray(): void
    {
        $vo = BudgetVo::fromArray([]);

        self::assertTrue($vo->isUnlimited());
    }

    #[Test]
    public function fromArrayCreatesBudgetWithOnlyTotal(): void
    {
        $vo = BudgetVo::fromArray(['max_cost_total' => 7.5]);

        self::assertSame(7.5, $vo->getMaxCostTotal());
        self::assertNull($vo->getMaxCostPerStep());
    }

    #[Test]
    public function fromArrayCreatesBudgetWithOnlyStepLimit(): void
    {
        $vo = BudgetVo::fromArray(['max_cost_per_step' => 1.0]);

        self::assertNull($vo->getMaxCostTotal());
        self::assertSame(1.0, $vo->getMaxCostPerStep());
    }

    #[Test]
    public function fromArrayHandlesNullValues(): void
    {
        $vo = BudgetVo::fromArray([
            'max_cost_total' => null,
            'max_cost_per_step' => null,
        ]);

        self::assertTrue($vo->isUnlimited());
    }

    #[Test]
    public function toLogStringShowsLimits(): void
    {
        $vo = new BudgetVo(maxCostTotal: 5.0, maxCostPerStep: 2.0);

        self::assertSame('Budget(maxCostTotal=5.00, maxCostPerStep=2.00)', $vo->toLogString());
    }

    #[Test]
    public function toLogStringShowsUnlimited(): void
    {
        $vo = new BudgetVo();

        self::assertSame('Budget(maxCostTotal=unlimited, maxCostPerStep=unlimited)', $vo->toLogString());
    }

    // ─── Per-role budget tests ─────────────────────────────────────────────

    #[Test]
    public function perRoleBudgetIsStored(): void
    {
        $devBudget = new BudgetVo(maxCostTotal: 2.0);
        $vo = new BudgetVo(maxCostTotal: 5.0, perRoleBudgets: ['dev' => $devBudget]);

        self::assertTrue($vo->hasRoleBudgets());
        self::assertSame($devBudget, $vo->getRoleBudget('dev'));
        self::assertNull($vo->getRoleBudget('unknown_role'));
        self::assertSame(['dev' => $devBudget], $vo->getPerRoleBudgets());
    }

    #[Test]
    public function isUnlimitedReturnsFalseWithPerRoleBudgets(): void
    {
        $vo = new BudgetVo(perRoleBudgets: ['dev' => new BudgetVo(maxCostTotal: 1.0)]);

        self::assertFalse($vo->isUnlimited());
        self::assertTrue($vo->hasRoleBudgets());
    }

    #[Test]
    public function isWithinRoleBudgetReturnsTrueWhenNoRoleLimit(): void
    {
        $vo = new BudgetVo(maxCostTotal: 5.0);

        self::assertTrue($vo->isWithinRoleBudget('dev', 999.0));
    }

    #[Test]
    public function isWithinRoleBudgetReturnsTrueWhenUnderLimit(): void
    {
        $vo = new BudgetVo(
            maxCostTotal: 10.0,
            perRoleBudgets: ['dev' => new BudgetVo(maxCostTotal: 3.0)],
        );

        self::assertTrue($vo->isWithinRoleBudget('dev', 2.99));
        self::assertTrue($vo->isWithinRoleBudget('dev', 3.0));
    }

    #[Test]
    public function isWithinRoleBudgetReturnsFalseWhenOverLimit(): void
    {
        $vo = new BudgetVo(
            perRoleBudgets: ['dev' => new BudgetVo(maxCostTotal: 2.0)],
        );

        self::assertFalse($vo->isWithinRoleBudget('dev', 2.01));
    }

    #[Test]
    public function isWithinRoleStepBudgetReturnsTrueWhenNoRoleLimit(): void
    {
        $vo = new BudgetVo(maxCostTotal: 5.0);

        self::assertTrue($vo->isWithinRoleStepBudget('dev', 999.0));
    }

    #[Test]
    public function isWithinRoleStepBudgetReturnsFalseWhenOverLimit(): void
    {
        $vo = new BudgetVo(
            perRoleBudgets: ['dev' => new BudgetVo(maxCostPerStep: 1.0)],
        );

        self::assertTrue($vo->isWithinRoleStepBudget('dev', 1.0));
        self::assertFalse($vo->isWithinRoleStepBudget('dev', 1.01));
    }

    #[Test]
    public function fromArrayParsesPerRoleBudgets(): void
    {
        $vo = BudgetVo::fromArray([
            'max_cost_total' => 5.0,
            'per_role' => [
                'dev' => ['max_cost_total' => 2.0],
                'reviewer' => ['max_cost_total' => 1.0, 'max_cost_per_step' => 0.5],
            ],
        ]);

        self::assertSame(5.0, $vo->getMaxCostTotal());
        self::assertTrue($vo->hasRoleBudgets());
        self::assertSame(2.0, $vo->getRoleBudget('dev')?->getMaxCostTotal());
        self::assertSame(1.0, $vo->getRoleBudget('reviewer')?->getMaxCostTotal());
        self::assertSame(0.5, $vo->getRoleBudget('reviewer')?->getMaxCostPerStep());
    }

    #[Test]
    public function fromArrayIgnoresNonArrayPerRoleEntries(): void
    {
        $vo = BudgetVo::fromArray([
            'per_role' => [
                'dev' => ['max_cost_total' => 2.0],
                'bad' => 'not_an_array',
            ],
        ]);

        self::assertNotNull($vo->getRoleBudget('dev'));
        self::assertNull($vo->getRoleBudget('bad'));
    }

    #[Test]
    public function toLogStringIncludesPerRoleBudgets(): void
    {
        $vo = new BudgetVo(
            maxCostTotal: 5.0,
            perRoleBudgets: ['dev' => new BudgetVo(maxCostTotal: 2.0)],
        );

        self::assertSame(
            'Budget(maxCostTotal=5.00, maxCostPerStep=unlimited, dev=(total=2.00, step=∞))',
            $vo->toLogString(),
        );
    }

    // ─── isNearTotalBudget (80% warning) ──────────────────────────────────

    #[Test]
    public function isNearTotalBudgetReturnsFalseWhenUnlimited(): void
    {
        $vo = new BudgetVo();

        self::assertFalse($vo->isNearTotalBudget(999.0));
    }

    #[Test]
    public function isNearTotalBudgetReturnsTrueAt80Percent(): void
    {
        $vo = new BudgetVo(maxCostTotal: 1.0);

        self::assertTrue($vo->isNearTotalBudget(0.8));
    }

    #[Test]
    public function isNearTotalBudgetReturnsTrueBetween80And100(): void
    {
        $vo = new BudgetVo(maxCostTotal: 1.0);

        self::assertTrue($vo->isNearTotalBudget(0.9));
        self::assertTrue($vo->isNearTotalBudget(1.0));
    }

    #[Test]
    public function isNearTotalBudgetReturnsFalseBelow80(): void
    {
        $vo = new BudgetVo(maxCostTotal: 1.0);

        self::assertFalse($vo->isNearTotalBudget(0.79));
        self::assertFalse($vo->isNearTotalBudget(0.0));
    }

    #[Test]
    public function isNearTotalBudgetReturnsFalseWhenOverBudget(): void
    {
        $vo = new BudgetVo(maxCostTotal: 1.0);

        self::assertFalse($vo->isNearTotalBudget(1.01));
        self::assertFalse($vo->isNearTotalBudget(2.0));
    }

    #[Test]
    public function isNearTotalBudgetSupportsCustomThreshold(): void
    {
        $vo = new BudgetVo(maxCostTotal: 1.0);

        // При пороге 50%: 0.5 → true, 0.49 → false
        self::assertTrue($vo->isNearTotalBudget(0.5, 0.5));
        self::assertFalse($vo->isNearTotalBudget(0.49, 0.5));
    }
}
