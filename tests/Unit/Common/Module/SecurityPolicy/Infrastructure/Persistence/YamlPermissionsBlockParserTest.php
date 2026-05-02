<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\SecurityPolicy\Infrastructure\Persistence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleActionEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\PermissionSetVo;
use TaskOrchestrator\Common\Module\SecurityPolicy\Infrastructure\Persistence\YamlPermissionsBlockParser;

#[CoversClass(YamlPermissionsBlockParser::class)]
final class YamlPermissionsBlockParserTest extends TestCase
{
    private YamlPermissionsBlockParser $parser;

    protected function setUp(): void
    {
        $this->parser = new YamlPermissionsBlockParser();
    }

    // ─── parse: null / empty block ──────────────────────────────────────

    #[Test]
    public function parseReturnsDefaultAllowOnNull(): void
    {
        $result = $this->parser->parse(null);

        $this->assertTrue($result->isAllowed(RuleTargetEnum::runner, 'any-runner'));
        $this->assertFalse($result->isDefaultDeny());
    }

    #[Test]
    public function parseReturnsDefaultAllowOnEmptyArray(): void
    {
        $result = $this->parser->parse([]);

        $this->assertTrue($result->isAllowed(RuleTargetEnum::runner, 'any-runner'));
    }

    // ─── parse: runners ─────────────────────────────────────────────────

    #[Test]
    public function parseRunnersAllowCreatesAllowPermissions(): void
    {
        $result = $this->parser->parse([
            'runners' => [
                'allow' => ['openai', 'anthropic'],
            ],
        ]);

        $this->assertTrue($result->isAllowed(RuleTargetEnum::runner, 'openai'));
        $this->assertTrue($result->isAllowed(RuleTargetEnum::runner, 'anthropic'));
        $this->assertFalse($result->isAllowed(RuleTargetEnum::runner, 'local-shell'));
    }

    #[Test]
    public function parseRunnersDenyCreatesDenyPermissions(): void
    {
        $result = $this->parser->parse([
            'runners' => [
                'deny' => ['local-shell'],
            ],
        ]);

        $this->assertFalse($result->isAllowed(RuleTargetEnum::runner, 'local-shell'));
        // No allow rules → default allow for other runners
        $this->assertTrue($result->isAllowed(RuleTargetEnum::runner, 'openai'));
    }

    #[Test]
    public function parseRunnersAllowAndDenyCombines(): void
    {
        $result = $this->parser->parse([
            'runners' => [
                'allow' => ['openai', 'anthropic'],
                'deny' => ['local-shell'],
            ],
        ]);

        $this->assertTrue($result->isAllowed(RuleTargetEnum::runner, 'openai'));
        $this->assertTrue($result->isAllowed(RuleTargetEnum::runner, 'anthropic'));
        // Deny takes priority
        $this->assertFalse($result->isAllowed(RuleTargetEnum::runner, 'local-shell'));
        // Non-listed runner: deny-by-default because there are allow rules
        $this->assertFalse($result->isAllowed(RuleTargetEnum::runner, 'unknown'));
    }

    // ─── parse: tools ───────────────────────────────────────────────────

    #[Test]
    public function parseToolsAllowCreatesPermissions(): void
    {
        $result = $this->parser->parse([
            'tools' => [
                'allow' => ['file_read', 'file_write'],
            ],
        ]);

        $this->assertTrue($result->isAllowed(RuleTargetEnum::tool, 'file_read'));
        $this->assertTrue($result->isAllowed(RuleTargetEnum::tool, 'file_write'));
        $this->assertFalse($result->isAllowed(RuleTargetEnum::tool, 'shell_exec'));
    }

    // ─── parse: models ──────────────────────────────────────────────────

    #[Test]
    public function parseModelsAllowCreatesPermissions(): void
    {
        $result = $this->parser->parse([
            'models' => [
                'allow' => ['gpt-4', 'claude-3.5'],
            ],
        ]);

        $this->assertTrue($result->isAllowed(RuleTargetEnum::model, 'gpt-4'));
        $this->assertFalse($result->isAllowed(RuleTargetEnum::model, 'gpt-3.5'));
    }

    // ─── parse: commands ────────────────────────────────────────────────

    #[Test]
    public function parseCommandsDenyStrings(): void
    {
        $result = $this->parser->parse([
            'commands' => [
                'deny' => ['rm -rf /', 'sudo'],
            ],
        ]);

        $this->assertFalse($result->isAllowed(RuleTargetEnum::command, 'rm -rf /'));
        $this->assertFalse($result->isAllowed(RuleTargetEnum::command, 'sudo'));
    }

    #[Test]
    public function parseCommandsDenyPatternObjects(): void
    {
        $result = $this->parser->parse([
            'commands' => [
                'deny' => [
                    ['pattern' => 'rm -rf *', 'type' => 'glob'],
                    ['pattern' => 'bash -c*', 'type' => 'glob'],
                ],
            ],
        ]);

        // PermissionVo does exact match by resource string
        $this->assertFalse($result->isAllowed(RuleTargetEnum::command, 'rm -rf *'));
        $this->assertFalse($result->isAllowed(RuleTargetEnum::command, 'bash -c*'));
    }

    #[Test]
    public function parseCommandsAllow(): void
    {
        $result = $this->parser->parse([
            'commands' => [
                'allow' => ['ls', 'cat'],
            ],
        ]);

        $this->assertTrue($result->isAllowed(RuleTargetEnum::command, 'ls'));
        $this->assertFalse($result->isAllowed(RuleTargetEnum::command, 'rm'));
    }

    // ─── parse: combined permissions ────────────────────────────────────

    #[Test]
    public function parseCombinedPermissionsBlock(): void
    {
        $block = [
            'runners' => [
                'allow' => ['openai', 'anthropic'],
                'deny' => ['local-shell'],
            ],
            'tools' => [
                'allow' => ['file_read', 'file_write'],
            ],
            'commands' => [
                'deny' => [
                    ['pattern' => 'rm -rf *', 'type' => 'glob'],
                ],
            ],
        ];

        $result = $this->parser->parse($block);

        // Runners
        $this->assertTrue($result->isAllowed(RuleTargetEnum::runner, 'openai'));
        $this->assertFalse($result->isAllowed(RuleTargetEnum::runner, 'local-shell'));

        // Tools
        $this->assertTrue($result->isAllowed(RuleTargetEnum::tool, 'file_read'));
        $this->assertFalse($result->isAllowed(RuleTargetEnum::tool, 'shell_exec'));

        // Commands
        $this->assertFalse($result->isAllowed(RuleTargetEnum::command, 'rm -rf *'));
    }

    #[Test]
    public function parseWithAllowRulesSetsDefaultDeny(): void
    {
        $result = $this->parser->parse([
            'runners' => [
                'allow' => ['openai'],
            ],
        ]);

        // Has allow rules → deny-by-default for non-listed
        $this->assertTrue($result->isDefaultDeny());
    }

    #[Test]
    public function parseWithOnlyDenyRulesKeepsDefaultAllow(): void
    {
        $result = $this->parser->parse([
            'runners' => [
                'deny' => ['local-shell'],
            ],
        ]);

        // Only deny rules → allow-by-default (black-list mode)
        $this->assertFalse($result->isDefaultDeny());
        $this->assertTrue($result->isAllowed(RuleTargetEnum::runner, 'openai'));
    }

    // ─── parse: edge cases ──────────────────────────────────────────────

    #[Test]
    public function parseSkipsEmptyResources(): void
    {
        $result = $this->parser->parse([
            'runners' => [
                'allow' => ['', 'openai'],
                'deny' => [''],
            ],
        ]);

        // Empty strings are skipped, only 'openai' allowed
        $this->assertTrue($result->isAllowed(RuleTargetEnum::runner, 'openai'));
    }

    #[Test]
    public function parseHandlesEmptyAllowDenyArrays(): void
    {
        $result = $this->parser->parse([
            'runners' => [
                'allow' => [],
                'deny' => [],
            ],
        ]);

        // No permissions created → default allow
        $this->assertTrue($result->isAllowed(RuleTargetEnum::runner, 'any'));
    }

    #[Test]
    public function parseHandlesMissingAllowDenyKeys(): void
    {
        $result = $this->parser->parse([
            'runners' => [],
        ]);

        // Empty section → default allow
        $this->assertTrue($result->isAllowed(RuleTargetEnum::runner, 'any'));
    }

    #[Test]
    public function parseReturnsCorrectPermissionCount(): void
    {
        $result = $this->parser->parse([
            'runners' => [
                'allow' => ['openai', 'anthropic'],
                'deny' => ['local-shell'],
            ],
            'tools' => [
                'allow' => ['read'],
            ],
        ]);

        $permissions = $result->getPermissions();
        $this->assertCount(4, $permissions);
    }

    #[Test]
    public function parseGetDenyPermissionsFiltersByTarget(): void
    {
        $result = $this->parser->parse([
            'runners' => [
                'deny' => ['local-shell'],
            ],
            'tools' => [
                'deny' => ['shell_exec'],
            ],
        ]);

        $runnerDenies = $result->getDenyPermissions(RuleTargetEnum::runner);
        $this->assertCount(1, $runnerDenies);
        $this->assertSame('local-shell', $runnerDenies[0]->getResource());
    }

    #[Test]
    public function parseGetAllowPermissionsFiltersByTarget(): void
    {
        $result = $this->parser->parse([
            'runners' => [
                'allow' => ['openai', 'anthropic'],
            ],
        ]);

        $runnerAllows = $result->getAllowPermissions(RuleTargetEnum::runner);
        $this->assertCount(2, $runnerAllows);
    }
}
