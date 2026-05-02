<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\SecurityPolicy\Domain\ValueObject;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleActionEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\PermissionVo;

#[CoversClass(PermissionVo::class)]
final class PermissionVoTest extends TestCase
{
    #[Test]
    public function allowCreatesPermissionWithAllowAction(): void
    {
        $permission = PermissionVo::allow(RuleTargetEnum::runner, 'openai');

        self::assertSame(RuleTargetEnum::runner, $permission->getTarget());
        self::assertSame('openai', $permission->getResource());
        self::assertSame(RuleActionEnum::allow, $permission->getAction());
        self::assertTrue($permission->isAllow());
        self::assertFalse($permission->isDeny());
    }

    #[Test]
    public function denyCreatesPermissionWithDenyAction(): void
    {
        $permission = PermissionVo::deny(RuleTargetEnum::runner, 'local-shell');

        self::assertSame(RuleTargetEnum::runner, $permission->getTarget());
        self::assertSame('local-shell', $permission->getResource());
        self::assertSame(RuleActionEnum::deny, $permission->getAction());
        self::assertTrue($permission->isDeny());
        self::assertFalse($permission->isAllow());
    }

    #[Test]
    public function matchesReturnsTrueForSameTargetAndResource(): void
    {
        $permission = PermissionVo::allow(RuleTargetEnum::runner, 'openai');

        self::assertTrue($permission->matches(RuleTargetEnum::runner, 'openai'));
    }

    #[Test]
    public function matchesReturnsFalseForDifferentTarget(): void
    {
        $permission = PermissionVo::allow(RuleTargetEnum::runner, 'openai');

        self::assertFalse($permission->matches(RuleTargetEnum::tool, 'openai'));
    }

    #[Test]
    public function matchesReturnsFalseForDifferentResource(): void
    {
        $permission = PermissionVo::allow(RuleTargetEnum::runner, 'openai');

        self::assertFalse($permission->matches(RuleTargetEnum::runner, 'anthropic'));
    }

    #[Test]
    public function rejectsEmptyResource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be empty');

        PermissionVo::allow(RuleTargetEnum::runner, '');
    }

    #[Test]
    public function equalsReturnsTrueForSamePermission(): void
    {
        $p1 = PermissionVo::allow(RuleTargetEnum::runner, 'openai');
        $p2 = PermissionVo::allow(RuleTargetEnum::runner, 'openai');

        self::assertTrue($p1->equals($p2));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentAction(): void
    {
        $allow = PermissionVo::allow(RuleTargetEnum::runner, 'openai');
        $deny = PermissionVo::deny(RuleTargetEnum::runner, 'openai');

        self::assertFalse($allow->equals($deny));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentResource(): void
    {
        $p1 = PermissionVo::allow(RuleTargetEnum::runner, 'openai');
        $p2 = PermissionVo::allow(RuleTargetEnum::runner, 'anthropic');

        self::assertFalse($p1->equals($p2));
    }
}
