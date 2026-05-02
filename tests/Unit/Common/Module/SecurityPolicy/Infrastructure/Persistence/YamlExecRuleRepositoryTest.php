<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\SecurityPolicy\Infrastructure\Persistence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\PatternTypeEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleActionEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleSeverityEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Infrastructure\Persistence\YamlExecRuleRepository;

#[CoversClass(YamlExecRuleRepository::class)]
final class YamlExecRuleRepositoryTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/_fixtures';
    }

    // ─── loadRules: valid YAML ─────────────────────────────────────────

    #[Test]
    public function loadRulesReturnsExecRulesFromValidYaml(): void
    {
        $repository = $this->createRepository('valid_security_policy.yaml');
        $rules = $repository->loadRules();

        $this->assertCount(5, $rules);

        // First rule: deny bash -c*
        $rule = $rules[0];
        $this->assertSame('deny-bash-c', $rule->getId()->getValue());
        $this->assertSame(RuleTargetEnum::command, $rule->getTarget());
        $this->assertSame('bash -c*', $rule->getPattern()->getPattern());
        $this->assertSame(PatternTypeEnum::glob, $rule->getPattern()->getType());
        $this->assertSame(RuleActionEnum::deny, $rule->getAction());
        $this->assertSame(RuleSeverityEnum::block, $rule->getSeverity());
        $this->assertSame(100, $rule->getPriority());
        $this->assertSame('Deny inline bash execution', $rule->getDescription());
    }

    #[Test]
    public function loadRulesParsesAllRuleFields(): void
    {
        $repository = $this->createRepository('valid_security_policy.yaml');
        $rules = $repository->loadRules();

        // Second rule: deny rm -rf /*
        $rule = $rules[1];
        $this->assertSame('deny-rm-rf', $rule->getId()->getValue());
        $this->assertTrue($rule->matches('rm -rf /'));
        $this->assertTrue($rule->isDeny());
        $this->assertTrue($rule->isBlock());
    }

    #[Test]
    public function loadRulesParsesGlobPattern(): void
    {
        $repository = $this->createRepository('valid_security_policy.yaml');
        $rules = $repository->loadRules();

        // sudo* glob pattern
        $sudoRule = $rules[3];
        $this->assertTrue($sudoRule->matches('sudo apt install'));
        $this->assertTrue($sudoRule->matches('sudo'));
        $this->assertFalse($sudoRule->matches('ls sudo'));
    }

    #[Test]
    public function loadRulesParsesExactPattern(): void
    {
        $repository = $this->createRepository('valid_security_policy.yaml');
        $rules = $repository->loadRules();

        // exact runner deny
        $runnerDeny = $rules[4];
        $this->assertSame(PatternTypeEnum::exact, $runnerDeny->getPattern()->getType());
        $this->assertSame('local-shell', $runnerDeny->getPattern()->getPattern());
        $this->assertTrue($runnerDeny->matches('local-shell'));
        $this->assertFalse($runnerDeny->matches('local-shell-extended'));
    }

    #[Test]
    public function loadRulesGeneratesAutoIdWhenMissing(): void
    {
        $repository = $this->createRepository('rules_without_id.yaml');
        $rules = $repository->loadRules();

        $this->assertCount(2, $rules);
        $this->assertSame('yaml-rule-0', $rules[0]->getId()->getValue());
        $this->assertSame('yaml-rule-1', $rules[1]->getId()->getValue());
    }

    // ─── loadRules: missing file ────────────────────────────────────────

    #[Test]
    public function loadRulesReturnsEmptyArrayWhenFileNotFound(): void
    {
        $repository = $this->createRepository('nonexistent.yaml');
        $rules = $repository->loadRules();

        $this->assertSame([], $rules);
    }

    // ─── loadRules: invalid YAML ────────────────────────────────────────

    #[Test]
    public function loadRulesReturnsEmptyArrayOnInvalidYaml(): void
    {
        $repository = $this->createRepository('invalid_yaml.yaml');
        $rules = $repository->loadRules();

        $this->assertSame([], $rules);
    }

    #[Test]
    public function loadRulesReturnsEmptyArrayOnMissingRulesKey(): void
    {
        $repository = $this->createRepository('no_rules_key.yaml');
        $rules = $repository->loadRules();

        $this->assertSame([], $rules);
    }

    // ─── loadRules: partial / invalid rules ─────────────────────────────

    #[Test]
    public function loadRulesSkipsRulesWithInvalidTarget(): void
    {
        $repository = $this->createRepository('rules_with_invalid_target.yaml');
        $rules = $repository->loadRules();

        // Only valid rule should be loaded
        $this->assertCount(1, $rules);
        $this->assertSame(RuleTargetEnum::command, $rules[0]->getTarget());
    }

    #[Test]
    public function loadRulesSkipsRulesWithEmptyPattern(): void
    {
        $repository = $this->createRepository('rules_with_empty_pattern.yaml');
        $rules = $repository->loadRules();

        $this->assertCount(1, $rules);
        $this->assertSame('valid-pattern', $rules[0]->getPattern()->getPattern());
    }

    #[Test]
    public function loadRulesSkipsRulesWithInvalidAction(): void
    {
        $repository = $this->createRepository('rules_with_invalid_action.yaml');
        $rules = $repository->loadRules();

        $this->assertCount(1, $rules);
        $this->assertSame(RuleActionEnum::deny, $rules[0]->getAction());
    }

    #[Test]
    public function loadRulesSkipsNonArrayEntries(): void
    {
        $repository = $this->createRepository('rules_with_non_array_entries.yaml');
        $rules = $repository->loadRules();

        $this->assertCount(1, $rules);
    }

    #[Test]
    public function loadRulesDefaultsSeverityToBlock(): void
    {
        $repository = $this->createRepository('rules_without_severity.yaml');
        $rules = $repository->loadRules();

        $this->assertCount(1, $rules);
        $this->assertSame(RuleSeverityEnum::block, $rules[0]->getSeverity());
    }

    #[Test]
    public function loadRulesDefaultsPriorityToZero(): void
    {
        $repository = $this->createRepository('rules_without_priority.yaml');
        $rules = $repository->loadRules();

        $this->assertCount(1, $rules);
        $this->assertSame(0, $rules[0]->getPriority());
    }

    // ─── loadDefaultPolicy ──────────────────────────────────────────────

    #[Test]
    public function loadDefaultPolicyReturnsAllowWhenFileSaysAllow(): void
    {
        $repository = $this->createRepository('valid_security_policy.yaml');

        $this->assertSame('allow', $repository->loadDefaultPolicy());
    }

    #[Test]
    public function loadDefaultPolicyReturnsDenyWhenFileSaysDeny(): void
    {
        $repository = $this->createRepository('policy_deny.yaml');

        $this->assertSame('deny', $repository->loadDefaultPolicy());
    }

    #[Test]
    public function loadDefaultPolicyReturnsAllowWhenFileNotFound(): void
    {
        $repository = $this->createRepository('nonexistent.yaml');

        $this->assertSame('allow', $repository->loadDefaultPolicy());
    }

    #[Test]
    public function loadDefaultPolicyReturnsAllowForInvalidYaml(): void
    {
        $repository = $this->createRepository('invalid_yaml.yaml');

        $this->assertSame('allow', $repository->loadDefaultPolicy());
    }

    // ─── Helper ─────────────────────────────────────────────────────────

    private function createRepository(string $fixtureFile): YamlExecRuleRepository
    {
        return new YamlExecRuleRepository(
            yamlPath: $this->fixturesDir . '/' . $fixtureFile,
        );
    }
}
