<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\SecurityPolicy\Domain\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Entity\ExecRule;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleActionEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleSeverityEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Service\ExecPolicyCheckService;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\ExecPolicyCheckResultVo;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\ExecRuleIdVo;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\RulePatternVo;

#[CoversClass(ExecPolicyCheckService::class)]
final class ExecPolicyCheckServiceTest extends TestCase
{
    private ExecPolicyCheckService $service;

    protected function setUp(): void
    {
        $this->service = new ExecPolicyCheckService();
    }

    private function createDenyRule(
        string $id,
        RuleTargetEnum $target,
        string $globPattern,
        int $priority = 0,
    ): ExecRule {
        return new ExecRule(
            id: ExecRuleIdVo::createFromString($id),
            target: $target,
            pattern: RulePatternVo::createFromGlob($globPattern),
            action: RuleActionEnum::deny,
            severity: RuleSeverityEnum::block,
            priority: $priority,
        );
    }

    private function createAllowRule(
        string $id,
        RuleTargetEnum $target,
        string $globPattern,
        int $priority = 0,
    ): ExecRule {
        return new ExecRule(
            id: ExecRuleIdVo::createFromString($id),
            target: $target,
            pattern: RulePatternVo::createFromGlob($globPattern),
            action: RuleActionEnum::allow,
            priority: $priority,
        );
    }

    // ─── Empty rules → default deny ─────────────────────────────────

    #[Test]
    public function emptyRulesResultsInDefaultDeny(): void
    {
        $result = $this->service->check('rm -rf /', RuleTargetEnum::command, []);

        self::assertTrue($result->isDenied());
        self::assertNull($result->getViolatedRule());
        self::assertSame('rm -rf /', $result->getCheckedValue());
        self::assertSame(RuleTargetEnum::command, $result->getTarget());
    }

    // ─── Deny rule matching ─────────────────────────────────────────

    #[Test]
    public function denyRuleMatchesValueReturnsDenied(): void
    {
        $rules = [
            $this->createDenyRule('deny-rm', RuleTargetEnum::command, 'rm *'),
        ];

        $result = $this->service->check('rm -rf /', RuleTargetEnum::command, $rules);

        self::assertTrue($result->isDenied());
        self::assertNotNull($result->getViolatedRule());
        self::assertSame('deny-rm', $result->getViolatedRule()->getId()->getValue());
    }

    #[Test]
    public function denyRuleDoesNotMatchValueReturnsDefaultDeny(): void
    {
        $rules = [
            $this->createDenyRule('deny-rm', RuleTargetEnum::command, 'rm *'),
        ];

        $result = $this->service->check('ls -la', RuleTargetEnum::command, $rules);

        // Нет совпадений → default deny
        self::assertTrue($result->isDenied());
        self::assertNull($result->getViolatedRule());
    }

    // ─── Allow rule matching ────────────────────────────────────────

    #[Test]
    public function allowRuleMatchesValueReturnsAllowed(): void
    {
        $rules = [
            $this->createAllowRule('allow-ls', RuleTargetEnum::command, 'ls *'),
        ];

        $result = $this->service->check('ls -la', RuleTargetEnum::command, $rules);

        self::assertTrue($result->isAllowed());
        self::assertNull($result->getViolatedRule());
    }

    // ─── Deny-first logic ───────────────────────────────────────────

    #[Test]
    public function denyFirstDenyOverAllow(): void
    {
        $rules = [
            $this->createAllowRule('allow-rm', RuleTargetEnum::command, 'rm *'),
            $this->createDenyRule('deny-rm', RuleTargetEnum::command, 'rm *'),
        ];

        $result = $this->service->check('rm -rf /', RuleTargetEnum::command, $rules);

        // deny-first: deny побеждает
        self::assertTrue($result->isDenied());
        self::assertSame('deny-rm', $result->getViolatedRule()->getId()->getValue());
    }

    #[Test]
    public function denyFirstEvenWithHigherPriorityAllow(): void
    {
        $rules = [
            $this->createAllowRule('allow-rm', RuleTargetEnum::command, 'rm *', 100),
            $this->createDenyRule('deny-rm', RuleTargetEnum::command, 'rm *', 1),
        ];

        $result = $this->service->check('rm -rf /', RuleTargetEnum::command, $rules);

        // deny-first: deny побеждает независимо от приоритета
        self::assertTrue($result->isDenied());
    }

    // ─── Priority ordering ──────────────────────────────────────────

    #[Test]
    public function higherPriorityDenyReportedFirst(): void
    {
        $rules = [
            $this->createDenyRule('deny-low', RuleTargetEnum::command, 'rm *', 1),
            $this->createDenyRule('deny-high', RuleTargetEnum::command, 'rm *', 100),
        ];

        $result = $this->service->check('rm -rf /', RuleTargetEnum::command, $rules);

        // Высший приоритет первым
        self::assertTrue($result->isDenied());
        self::assertSame('deny-high', $result->getViolatedRule()->getId()->getValue());
    }

    // ─── Target filtering ───────────────────────────────────────────

    #[Test]
    public function rulesForOtherTargetsAreIgnored(): void
    {
        $rules = [
            $this->createDenyRule('deny-runner', RuleTargetEnum::runner, 'local-shell'),
            $this->createAllowRule('allow-cmd', RuleTargetEnum::command, 'ls *'),
        ];

        // Проверяем command — runner rule должна быть проигнорирована
        $result = $this->service->check('ls -la', RuleTargetEnum::command, $rules);
        self::assertTrue($result->isAllowed());

        // Проверяем runner — нет rules для runner кроме deny
        $result = $this->service->check('local-shell', RuleTargetEnum::runner, $rules);
        self::assertTrue($result->isDenied());
    }

    // ─── Detailed result (Should Have) ──────────────────────────────

    #[Test]
    public function resultContainsMatchedRulesForDebug(): void
    {
        $rules = [
            $this->createAllowRule('allow-rm', RuleTargetEnum::command, 'rm *'),
            $this->createDenyRule('deny-rm', RuleTargetEnum::command, 'rm *'),
            $this->createAllowRule('allow-ls', RuleTargetEnum::command, 'ls *'),
        ];

        $result = $this->service->check('rm -rf /', RuleTargetEnum::command, $rules);

        // allow-rm и deny-rm совпали, allow-ls не совпал
        self::assertCount(2, $result->getMatchedRules());
    }

    #[Test]
    public function resultContainsNoMatchedRulesForNoMatch(): void
    {
        $rules = [
            $this->createDenyRule('deny-rm', RuleTargetEnum::command, 'rm *'),
        ];

        $result = $this->service->check('ls -la', RuleTargetEnum::command, $rules);

        self::assertCount(0, $result->getMatchedRules());
    }

    // ─── Mixed exact and glob patterns ──────────────────────────────

    #[Test]
    public function mixedPatternTypes(): void
    {
        $rules = [
            new ExecRule(
                id: ExecRuleIdVo::createFromString('deny-exact-bash'),
                target: RuleTargetEnum::command,
                pattern: RulePatternVo::createFromExact('bash'),
                action: RuleActionEnum::deny,
            ),
            new ExecRule(
                id: ExecRuleIdVo::createFromString('allow-bash-c'),
                target: RuleTargetEnum::command,
                pattern: RulePatternVo::createFromGlob('bash -c *'),
                action: RuleActionEnum::allow,
            ),
        ];

        // Точное совпадение "bash" → deny
        $result = $this->service->check('bash', RuleTargetEnum::command, $rules);
        self::assertTrue($result->isDenied());

        // "bash -c echo" совпадает с deny-exact-bash? Нет (exact !== "bash -c echo").
        // Совпадает с allow-bash-c? Да.
        // Нет deny → allowed
        $result = $this->service->check('bash -c echo', RuleTargetEnum::command, $rules);
        self::assertTrue($result->isAllowed());
    }
}
