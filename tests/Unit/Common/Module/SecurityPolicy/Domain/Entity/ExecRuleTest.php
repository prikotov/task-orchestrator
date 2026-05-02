<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\SecurityPolicy\Domain\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Entity\ExecRule;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleActionEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleSeverityEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\ExecRuleIdVo;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\RulePatternVo;

#[CoversClass(ExecRule::class)]
final class ExecRuleTest extends TestCase
{
    private function createRule(
        RuleActionEnum $action = RuleActionEnum::deny,
        RuleTargetEnum $target = RuleTargetEnum::command,
        string $pattern = 'rm *',
        string $patternType = 'glob',
        int $priority = 0,
    ): ExecRule {
        $rulePattern = match ($patternType) {
            'glob' => RulePatternVo::createFromGlob($pattern),
            'exact' => RulePatternVo::createFromExact($pattern),
            'regex' => RulePatternVo::createFromRegex($pattern),
            default => RulePatternVo::createFromGlob($pattern),
        };

        return new ExecRule(
            id: ExecRuleIdVo::createFromString('test-rule'),
            target: $target,
            pattern: $rulePattern,
            action: $action,
            severity: RuleSeverityEnum::block,
            priority: $priority,
            description: 'Test rule',
        );
    }

    // ─── Matching ───────────────────────────────────────────────────

    #[Test]
    public function matchesReturnsTrueWhenPatternMatches(): void
    {
        $rule = $this->createRule(pattern: 'rm *', patternType: 'glob');

        self::assertTrue($rule->matches('rm -rf /'));
        self::assertTrue($rule->matches('rm file.txt'));
        self::assertFalse($rule->matches('ls'));
    }

    #[Test]
    public function matchesReturnsTrueForExactMatch(): void
    {
        $rule = $this->createRule(pattern: 'rm', patternType: 'exact');

        self::assertTrue($rule->matches('rm'));
        self::assertFalse($rule->matches('rm -rf'));
    }

    #[Test]
    public function targetsReturnsTrueForMatchingTarget(): void
    {
        $rule = $this->createRule(target: RuleTargetEnum::command);

        self::assertTrue($rule->targets(RuleTargetEnum::command));
        self::assertFalse($rule->targets(RuleTargetEnum::runner));
    }

    // ─── Action helpers ─────────────────────────────────────────────

    #[Test]
    public function isDenyReturnsTrueForDenyAction(): void
    {
        $denyRule = $this->createRule(action: RuleActionEnum::deny);
        $allowRule = $this->createRule(action: RuleActionEnum::allow);

        self::assertTrue($denyRule->isDeny());
        self::assertFalse($allowRule->isDeny());
    }

    #[Test]
    public function isAllowReturnsTrueForAllowAction(): void
    {
        $allowRule = $this->createRule(action: RuleActionEnum::allow);
        $denyRule = $this->createRule(action: RuleActionEnum::deny);

        self::assertTrue($allowRule->isAllow());
        self::assertFalse($denyRule->isAllow());
    }

    // ─── Severity helpers ───────────────────────────────────────────

    #[Test]
    public function isBlockAndIsWarn(): void
    {
        $blockRule = new ExecRule(
            id: ExecRuleIdVo::createFromString('r1'),
            target: RuleTargetEnum::command,
            pattern: RulePatternVo::createFromGlob('*'),
            action: RuleActionEnum::deny,
            severity: RuleSeverityEnum::block,
        );

        $warnRule = new ExecRule(
            id: ExecRuleIdVo::createFromString('r2'),
            target: RuleTargetEnum::command,
            pattern: RulePatternVo::createFromGlob('*'),
            action: RuleActionEnum::deny,
            severity: RuleSeverityEnum::warn,
        );

        self::assertTrue($blockRule->isBlock());
        self::assertFalse($blockRule->isWarn());
        self::assertTrue($warnRule->isWarn());
        self::assertFalse($warnRule->isBlock());
    }

    // ─── Getters ────────────────────────────────────────────────────

    #[Test]
    public function gettersReturnExpectedValues(): void
    {
        $id = ExecRuleIdVo::createFromString('deny-rm');
        $pattern = RulePatternVo::createFromGlob('rm *');

        $rule = new ExecRule(
            id: $id,
            target: RuleTargetEnum::command,
            pattern: $pattern,
            action: RuleActionEnum::deny,
            severity: RuleSeverityEnum::block,
            priority: 10,
            description: 'Deny rm commands',
        );

        self::assertTrue($rule->getId()->equals($id));
        self::assertSame(RuleTargetEnum::command, $rule->getTarget());
        self::assertSame($pattern, $rule->getPattern());
        self::assertSame(RuleActionEnum::deny, $rule->getAction());
        self::assertSame(RuleSeverityEnum::block, $rule->getSeverity());
        self::assertSame(10, $rule->getPriority());
        self::assertSame('Deny rm commands', $rule->getDescription());
    }

    #[Test]
    public function defaultSeverityIsBlock(): void
    {
        $rule = new ExecRule(
            id: ExecRuleIdVo::createFromString('r1'),
            target: RuleTargetEnum::command,
            pattern: RulePatternVo::createFromExact('test'),
            action: RuleActionEnum::deny,
        );

        self::assertSame(RuleSeverityEnum::block, $rule->getSeverity());
        self::assertSame(0, $rule->getPriority());
        self::assertSame('', $rule->getDescription());
    }
}
