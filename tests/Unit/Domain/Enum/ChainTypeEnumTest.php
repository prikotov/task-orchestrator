<?php

declare(strict_types=1);

namespace TasK\Orchestrator\Tests\Unit\Domain\Enum;

use TasK\Orchestrator\Domain\Enum\ChainTypeEnum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChainTypeEnum::class)]
final class ChainTypeEnumTest extends TestCase
{
    #[Test]
    public function staticTypeHasCorrectValue(): void
    {
        self::assertSame('static', ChainTypeEnum::staticType->value);
    }

    #[Test]
    public function dynamicTypeHasCorrectValue(): void
    {
        self::assertSame('dynamic', ChainTypeEnum::dynamicType->value);
    }

    #[Test]
    public function tryFromReturnsCorrectType(): void
    {
        self::assertSame(ChainTypeEnum::staticType, ChainTypeEnum::tryFrom('static'));
        self::assertSame(ChainTypeEnum::dynamicType, ChainTypeEnum::tryFrom('dynamic'));
        self::assertNull(ChainTypeEnum::tryFrom('unknown'));
    }
}
