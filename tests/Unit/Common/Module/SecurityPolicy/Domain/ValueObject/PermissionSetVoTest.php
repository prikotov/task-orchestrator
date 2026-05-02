<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\SecurityPolicy\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\PermissionSetVo;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\PermissionVo;

#[CoversClass(PermissionSetVo::class)]
final class PermissionSetVoTest extends TestCase
{
    // ─── Default deny ───────────────────────────────────────────────

    #[Test]
    public function defaultDenyRejectsUnknownResource(): void
    {
        $set = PermissionSetVo::createDefaultDeny();

        self::assertFalse($set->isAllowed(RuleTargetEnum::runner, 'openai'));
        self::assertTrue($set->isDefaultDeny());
    }

    // ─── Default allow ──────────────────────────────────────────────

    #[Test]
    public function defaultAllowAcceptsUnknownResource(): void
    {
        $set = PermissionSetVo::createDefaultAllow();

        self::assertTrue($set->isAllowed(RuleTargetEnum::runner, 'openai'));
        self::assertFalse($set->isDefaultDeny());
    }

    // ─── Deny-first logic ───────────────────────────────────────────

    #[Test]
    public function denyTakesPrecedenceOverAllow(): void
    {
        $set = PermissionSetVo::createFromPermissions([
            PermissionVo::allow(RuleTargetEnum::runner, 'local-shell'),
            PermissionVo::deny(RuleTargetEnum::runner, 'local-shell'),
        ]);

        self::assertFalse($set->isAllowed(RuleTargetEnum::runner, 'local-shell'));
    }

    #[Test]
    public function allowWithoutDenyIsAllowed(): void
    {
        $set = PermissionSetVo::createFromPermissions([
            PermissionVo::allow(RuleTargetEnum::runner, 'openai'),
        ]);

        self::assertTrue($set->isAllowed(RuleTargetEnum::runner, 'openai'));
    }

    #[Test]
    public function denyWithoutAllowIsDenied(): void
    {
        $set = PermissionSetVo::createFromPermissions([
            PermissionVo::deny(RuleTargetEnum::runner, 'local-shell'),
        ]);

        self::assertFalse($set->isAllowed(RuleTargetEnum::runner, 'local-shell'));
    }

    #[Test]
    public function noMatchWithDefaultDenyIsDenied(): void
    {
        $set = PermissionSetVo::createFromPermissions([
            PermissionVo::allow(RuleTargetEnum::runner, 'openai'),
        ]);

        self::assertFalse($set->isAllowed(RuleTargetEnum::runner, 'anthropic'));
    }

    #[Test]
    public function noMatchWithDefaultAllowIsAllowed(): void
    {
        $set = PermissionSetVo::createFromPermissions(
            [PermissionVo::allow(RuleTargetEnum::runner, 'openai')],
            defaultDeny: false,
        );

        self::assertTrue($set->isAllowed(RuleTargetEnum::runner, 'anthropic'));
    }

    // ─── Deny overrides allow even when allow comes first ───────────

    #[Test]
    public function denyFirstIsEvaluatedCorrectly(): void
    {
        $set = PermissionSetVo::createFromPermissions([
            PermissionVo::allow(RuleTargetEnum::runner, 'openai'),
            PermissionVo::deny(RuleTargetEnum::runner, 'openai'),
            PermissionVo::allow(RuleTargetEnum::runner, 'openai'),
        ]);

        // deny-first: deny всегда побеждает
        self::assertFalse($set->isAllowed(RuleTargetEnum::runner, 'openai'));
    }

    // ─── Different targets don't interfere ──────────────────────────

    #[Test]
    public function differentTargetsDoNotInterfere(): void
    {
        $set = PermissionSetVo::createFromPermissions([
            PermissionVo::deny(RuleTargetEnum::runner, 'local-shell'),
            PermissionVo::allow(RuleTargetEnum::tool, 'read'),
        ]);

        self::assertFalse($set->isAllowed(RuleTargetEnum::runner, 'local-shell'));
        self::assertTrue($set->isAllowed(RuleTargetEnum::tool, 'read'));
        // tool:write — нет совпадения, default deny → false
        self::assertFalse($set->isAllowed(RuleTargetEnum::tool, 'write'));
    }

    // ─── Helper methods ─────────────────────────────────────────────

    #[Test]
    public function getDenyPermissionsFiltersCorrectly(): void
    {
        $set = PermissionSetVo::createFromPermissions([
            PermissionVo::allow(RuleTargetEnum::runner, 'openai'),
            PermissionVo::deny(RuleTargetEnum::runner, 'local-shell'),
            PermissionVo::deny(RuleTargetEnum::tool, 'sudo'),
        ]);

        $denies = $set->getDenyPermissions(RuleTargetEnum::runner);
        self::assertCount(1, $denies);
        self::assertSame('local-shell', $denies[0]->getResource());
    }

    #[Test]
    public function getAllowPermissionsFiltersCorrectly(): void
    {
        $set = PermissionSetVo::createFromPermissions([
            PermissionVo::allow(RuleTargetEnum::runner, 'openai'),
            PermissionVo::allow(RuleTargetEnum::runner, 'anthropic'),
            PermissionVo::deny(RuleTargetEnum::runner, 'local-shell'),
        ]);

        $allows = $set->getAllowPermissions(RuleTargetEnum::runner);
        self::assertCount(2, $allows);
    }

    #[Test]
    public function getPermissionsReturnsAll(): void
    {
        $permissions = [
            PermissionVo::allow(RuleTargetEnum::runner, 'openai'),
            PermissionVo::deny(RuleTargetEnum::runner, 'local-shell'),
        ];
        $set = PermissionSetVo::createFromPermissions($permissions);

        self::assertSame($permissions, $set->getPermissions());
    }

    #[Test]
    public function emptySetWithDefaultDenyDeniesEverything(): void
    {
        $set = PermissionSetVo::createDefaultDeny();

        self::assertFalse($set->isAllowed(RuleTargetEnum::command, 'rm'));
        self::assertFalse($set->isAllowed(RuleTargetEnum::runner, 'openai'));
        self::assertFalse($set->isAllowed(RuleTargetEnum::model, 'gpt-4'));
    }
}
