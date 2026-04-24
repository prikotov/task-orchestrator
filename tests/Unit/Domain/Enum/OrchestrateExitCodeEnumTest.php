<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\OrchestrateExitCodeEnum;

#[CoversClass(OrchestrateExitCodeEnum::class)]
final class OrchestrateExitCodeEnumTest extends TestCase
{
    #[Test]
    public function successHasCorrectValue(): void
    {
        self::assertSame(0, OrchestrateExitCodeEnum::success->value);
    }

    #[Test]
    public function chainFailedHasCorrectValue(): void
    {
        self::assertSame(1, OrchestrateExitCodeEnum::chainFailed->value);
    }

    #[Test]
    public function chainNotFoundHasCorrectValue(): void
    {
        self::assertSame(3, OrchestrateExitCodeEnum::chainNotFound->value);
    }

    #[Test]
    public function budgetExceededHasCorrectValue(): void
    {
        self::assertSame(4, OrchestrateExitCodeEnum::budgetExceeded->value);
    }

    #[Test]
    public function invalidConfigHasCorrectValue(): void
    {
        self::assertSame(5, OrchestrateExitCodeEnum::invalidConfig->value);
    }

    #[Test]
    public function timeoutHasCorrectValue(): void
    {
        self::assertSame(6, OrchestrateExitCodeEnum::timeout->value);
    }

    #[Test]
    public function allCasesAreUnique(): void
    {
        $values = array_map(
            static fn(OrchestrateExitCodeEnum $case): int => $case->value,
            OrchestrateExitCodeEnum::cases(),
        );

        self::assertSame($values, array_unique($values));
    }

    #[Test]
    public function allCasesAreBackedToInt(): void
    {
        foreach (OrchestrateExitCodeEnum::cases() as $case) {
            self::assertIsInt($case->value);
        }
    }
}
