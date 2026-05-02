<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\SecurityPolicy\Domain\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\PatternTypeEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleActionEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleSeverityEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;

#[CoversClass(RuleActionEnum::class)]
#[CoversClass(RuleTargetEnum::class)]
#[CoversClass(RuleSeverityEnum::class)]
#[CoversClass(PatternTypeEnum::class)]
final class SecurityPolicyEnumTest extends TestCase
{
    #[Test]
    public function ruleActionEnumHasExpectedCases(): void
    {
        self::assertSame('allow', RuleActionEnum::allow->value);
        self::assertSame('deny', RuleActionEnum::deny->value);
        self::assertCount(2, RuleActionEnum::cases());
    }

    #[Test]
    public function ruleTargetEnumHasExpectedCases(): void
    {
        self::assertSame('command', RuleTargetEnum::command->value);
        self::assertSame('runner', RuleTargetEnum::runner->value);
        self::assertSame('tool', RuleTargetEnum::tool->value);
        self::assertSame('model', RuleTargetEnum::model->value);
        self::assertSame('chain', RuleTargetEnum::chain->value);
        self::assertCount(5, RuleTargetEnum::cases());
    }

    #[Test]
    public function ruleSeverityEnumHasExpectedCases(): void
    {
        self::assertSame('block', RuleSeverityEnum::block->value);
        self::assertSame('warn', RuleSeverityEnum::warn->value);
        self::assertCount(2, RuleSeverityEnum::cases());
    }

    #[Test]
    public function patternTypeEnumHasExpectedCases(): void
    {
        self::assertSame('exact', PatternTypeEnum::exact->value);
        self::assertSame('glob', PatternTypeEnum::glob->value);
        self::assertSame('regex', PatternTypeEnum::regex->value);
        self::assertCount(3, PatternTypeEnum::cases());
    }

    #[Test]
    public function ruleActionEnumFromValue(): void
    {
        self::assertSame(RuleActionEnum::allow, RuleActionEnum::from('allow'));
        self::assertSame(RuleActionEnum::deny, RuleActionEnum::from('deny'));
    }

    #[Test]
    public function ruleTargetEnumFromValue(): void
    {
        self::assertSame(RuleTargetEnum::command, RuleTargetEnum::from('command'));
        self::assertSame(RuleTargetEnum::runner, RuleTargetEnum::from('runner'));
    }
}
