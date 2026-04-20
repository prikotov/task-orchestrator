<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\Chain;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\ChainNotFoundException;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\BudgetVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\FallbackConfigVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\RoleConfigVo;
use TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Chain\YamlChainLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlChainLoader::class)]
#[CoversClass(BudgetVo::class)]
#[CoversClass(RoleConfigVo::class)]
#[CoversClass(FallbackConfigVo::class)]
#[CoversClass(ChainRetryPolicyVo::class)]
final class YamlChainLoaderTest extends TestCase
{
    private string $fixtureDir;
    private string $fixturePath;
    private YamlChainLoader $loader;

    protected function setUp(): void
    {
        $this->fixtureDir = sys_get_temp_dir() . '/agent_chains_test_' . uniqid();
        $this->fixturePath = $this->fixtureDir . '/chains.yaml';

        mkdir($this->fixtureDir, 0777, true);
        $promptsDir = $this->fixtureDir . '/prompts/brainstorm';
        mkdir($promptsDir, 0777, true);

        file_put_contents($promptsDir . '/brainstorm_system.txt', 'Base system prompt');
        file_put_contents($promptsDir . '/facilitator_append.txt', 'Fac %s');
        file_put_contents($promptsDir . '/facilitator_start.txt', 'Start %s');
        file_put_contents($promptsDir . '/facilitator_continue.txt', 'Continue %s %s');
        file_put_contents($promptsDir . '/facilitator_finalize.txt', 'Final %s %s');
        file_put_contents($promptsDir . '/participant_append.txt', 'Sys %s');
        file_put_contents($promptsDir . '/participant_user.txt', 'Context %s %s');

        $yaml = <<<YAML
chains:
  implement:
    description: "Full implementation cycle"
    steps:
      - { type: agent, role: system_analyst, runner: pi }
      - { type: agent, role: backend_developer }
    fix_iterations: []

  analyze:
    description: "Analysis only"
    steps:
      - { type: agent, role: system_analyst }

  brainstorm:
    type: dynamic
    description: "Facilitated brainstorm"
    facilitator: system_analyst
    participants: [architect, marketer, backend_developer]
    max_rounds: 10
    prompts:
      brainstorm_system: prompts/brainstorm/brainstorm_system.txt
      facilitator_append: prompts/brainstorm/facilitator_append.txt
      facilitator_start: prompts/brainstorm/facilitator_start.txt
      facilitator_continue: prompts/brainstorm/facilitator_continue.txt
      facilitator_finalize: prompts/brainstorm/facilitator_finalize.txt
      participant_append: prompts/brainstorm/participant_append.txt
      participant_user: prompts/brainstorm/participant_user.txt

  inline_prompts:
    type: dynamic
    description: "Inline prompts test"
    facilitator: system_analyst
    participants: [architect]
    max_rounds: 5
    prompts:
      brainstorm_system: "Inline Base"
      facilitator_append: "Inline Fac %s"
      facilitator_start: "Inline Start %s"
      facilitator_continue: "Inline Cont %s %s"
      facilitator_finalize: "Inline Final %s %s"
      participant_append: "Inline SysP %s"
      participant_user: "Inline Ctx %s %s"
YAML;

        file_put_contents($this->fixturePath, $yaml);
        $this->loader = new YamlChainLoader($this->fixturePath);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->fixtureDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->fixtureDir);
    }

    // --- Static chain tests ---

    #[Test]
    public function loadReturnsStaticChainDefinition(): void
    {
        $chain = $this->loader->load('implement');

        self::assertSame('implement', $chain->getName());
        self::assertSame('Full implementation cycle', $chain->getDescription());
        self::assertSame(ChainTypeEnum::staticType, $chain->getType());
        self::assertCount(2, $chain->getSteps());
        self::assertSame('system_analyst', $chain->getSteps()[0]->getRole());
        self::assertSame('backend_developer', $chain->getSteps()[1]->getRole());
        self::assertFalse($chain->isDynamic());
    }

    #[Test]
    public function loadParsesDefaultsCorrectly(): void
    {
        $chain = $this->loader->load('analyze');

        self::assertSame('analyze', $chain->getName());
        self::assertSame('Analysis only', $chain->getDescription());
        self::assertSame(ChainTypeEnum::staticType, $chain->getType());
        self::assertSame([], $chain->getFixIterations());
        self::assertSame('system_analyst', $chain->getSteps()[0]->getRole());
        self::assertSame('pi', $chain->getSteps()[0]->getRunner());
        self::assertNull($chain->getSteps()[0]->getTools());
    }

    // --- Dynamic chain: file references ---

    #[Test]
    public function loadReturnsDynamicChainFromFileReferences(): void
    {
        $chain = $this->loader->load('brainstorm');

        self::assertSame('brainstorm', $chain->getName());
        self::assertSame('Facilitated brainstorm', $chain->getDescription());
        self::assertSame(ChainTypeEnum::dynamicType, $chain->getType());
        self::assertTrue($chain->isDynamic());
        self::assertSame('system_analyst', $chain->getFacilitator());
        self::assertSame(['architect', 'marketer', 'backend_developer'], $chain->getParticipants());
        self::assertSame(10, $chain->getMaxRounds());
        self::assertEmpty($chain->getSteps());
        self::assertSame('Base system prompt', $chain->getBrainstormSystemPrompt());
        self::assertSame('Fac %s', $chain->getFacilitatorAppendPrompt());
        self::assertSame('Start %s', $chain->getFacilitatorStartPrompt());
        self::assertSame('Continue %s %s', $chain->getFacilitatorContinuePrompt());
        self::assertSame('Final %s %s', $chain->getFacilitatorFinalizePrompt());
        self::assertSame('Sys %s', $chain->getParticipantAppendPrompt());
        self::assertSame('Context %s %s', $chain->getParticipantUserPrompt());
    }

    // --- Dynamic chain: inline prompts (backward compat) ---

    #[Test]
    public function loadReturnsDynamicChainFromInlinePrompts(): void
    {
        $chain = $this->loader->load('inline_prompts');

        self::assertSame('inline_prompts', $chain->getName());
        self::assertSame(ChainTypeEnum::dynamicType, $chain->getType());
        self::assertSame(5, $chain->getMaxRounds());
        self::assertSame('Inline Base', $chain->getBrainstormSystemPrompt());
        self::assertSame('Inline Fac %s', $chain->getFacilitatorAppendPrompt());
        self::assertSame('Inline Start %s', $chain->getFacilitatorStartPrompt());
        self::assertSame('Inline Cont %s %s', $chain->getFacilitatorContinuePrompt());
        self::assertSame('Inline Final %s %s', $chain->getFacilitatorFinalizePrompt());
        self::assertSame('Inline SysP %s', $chain->getParticipantAppendPrompt());
        self::assertSame('Inline Ctx %s %s', $chain->getParticipantUserPrompt());
    }

    // --- Error cases ---

    #[Test]
    public function loadThrowsChainNotFoundExceptionOnUnknownChain(): void
    {
        $this->expectException(ChainNotFoundException::class);

        $this->loader->load('nonexistent');
    }

    #[Test]
    public function loadHandlesMissingFile(): void
    {
        $loader = new YamlChainLoader('/nonexistent/path.yaml');

        $chains = $loader->list();

        self::assertEmpty($chains);
    }

    #[Test]
    public function loadThrowsOnEmptySteps(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_empty_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, "chains:\n  bad:\n    steps: []\n");

        try {
            $loader = new YamlChainLoader($fixturePath);
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Chain "bad" must have at least one step');
            $loader->load('bad');
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadThrowsOnMissingType(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_notype_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, "chains:\n  bad:\n    steps:\n      - { role: r1 }\n");

        try {
            $loader = new YamlChainLoader($fixturePath);
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Step "type" is required');
            $loader->load('bad');
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadThrowsOnInvalidType(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_badtype_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, "chains:\n  bad:\n    steps:\n      - { type: unknown, role: r1 }\n");

        try {
            $loader = new YamlChainLoader($fixturePath);
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Step "type" is required');
            $loader->load('bad');
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadThrowsOnMissingRoleForAgentStep(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_norole_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, "chains:\n  bad:\n    steps:\n      - { type: agent, runner: pi }\n");

        try {
            $loader = new YamlChainLoader($fixturePath);
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Agent step "role" is required');
            $loader->load('bad');
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadThrowsOnQualityGateWithoutCommand(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_qg_nocmd_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, "chains:\n  bad:\n    steps:\n      - { type: quality_gate, label: Test }\n");

        try {
            $loader = new YamlChainLoader($fixturePath);
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('quality_gate step must have "command"');
            $loader->load('bad');
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadThrowsOnQualityGateWithoutLabel(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_qg_nolabel_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, "chains:\n  bad:\n    steps:\n      - { type: quality_gate, command: 'make lint' }\n");

        try {
            $loader = new YamlChainLoader($fixturePath);
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('quality_gate step must have "label"');
            $loader->load('bad');
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadThrowsOnDynamicWithoutParticipants(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_dyn_nop_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, "chains:\n  bad:\n    type: dynamic\n    facilitator: analyst\n    participants: []\n");

        try {
            $loader = new YamlChainLoader($fixturePath);
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('at least one participant');
            $loader->load('bad');
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadThrowsOnDynamicWithoutFacilitator(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_dyn_nof_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, "chains:\n  bad:\n    type: dynamic\n    participants: [a, b]\n");

        try {
            $loader = new YamlChainLoader($fixturePath);
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('must specify a facilitator');
            $loader->load('bad');
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadThrowsOnDynamicWithoutPrompts(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_dyn_noprompts_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, "chains:\n  dyn:\n    type: dynamic\n    facilitator: x\n    participants: [a]\n");

        try {
            $loader = new YamlChainLoader($fixturePath);
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('must specify prompts.brainstorm_system');
            $loader->load('dyn');
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadUsesInlineTextWhenFileNotFound(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_fallback_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  dyn:
    type: dynamic
    facilitator: x
    participants: [a]
    prompts:
      brainstorm_system: "not_a_file.txt"
      facilitator_append: "fac_missing.txt"
      facilitator_start: "another_missing.txt"
      facilitator_continue: "missing.txt"
      facilitator_finalize: "finalize_missing.txt"
      participant_append: "sys_missing.txt"
      participant_user: "user_missing.txt"
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('dyn');
            self::assertSame('not_a_file.txt', $chain->getBrainstormSystemPrompt());
            self::assertSame('another_missing.txt', $chain->getFacilitatorStartPrompt());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadDynamicDefaultsMaxRounds(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_dyn_def_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  dyn:
    type: dynamic
    facilitator: x
    participants: [a]
    prompts:
      brainstorm_system: "BS"
      facilitator_append: "FA %s"
      facilitator_start: "St %s"
      facilitator_continue: "C %s %s"
      facilitator_finalize: "F %s %s"
      participant_append: "PA %s"
      participant_user: "PU %s %s"
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('dyn');
            self::assertSame(10, $chain->getMaxRounds());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function listReturnsAllChains(): void
    {
        $chains = $this->loader->list();

        self::assertCount(4, $chains);
        self::assertArrayHasKey('implement', $chains);
        self::assertArrayHasKey('analyze', $chains);
        self::assertArrayHasKey('brainstorm', $chains);
        self::assertArrayHasKey('inline_prompts', $chains);
    }

    #[Test]
    public function loadStaticExplicitType(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_explicit_static_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, "chains:\n  s:\n    type: static\n    steps:\n      - { type: agent, role: r1 }\n");

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('s');
            self::assertSame(ChainTypeEnum::staticType, $chain->getType());
            self::assertFalse($chain->isDynamic());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadParsesRolesSection(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_roles_' . uniqid();
        mkdir($fixtureDir);
        $promptsDir = $fixtureDir . '/prompts/brainstorm';
        mkdir($promptsDir, 0777, true);

        foreach (['brainstorm_system', 'facilitator_append', 'facilitator_start', 'facilitator_continue', 'facilitator_finalize', 'participant_append', 'participant_user'] as $prompt) {
            file_put_contents($promptsDir . "/{$prompt}.txt", "{$prompt} %s");
        }

        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
roles:
  analyst:
    prompt_file: docs/roles/analyst.md
    command:
      - --model
      - gpt-4o-mini
    timeout: 600
  architect:
    command:
      - --model
      - o3-mini
chains:
  dyn:
    type: dynamic
    facilitator: analyst
    participants: [architect]
    prompts:
      brainstorm_system: prompts/brainstorm/brainstorm_system.txt
      facilitator_append: prompts/brainstorm/facilitator_append.txt
      facilitator_start: prompts/brainstorm/facilitator_start.txt
      facilitator_continue: prompts/brainstorm/facilitator_continue.txt
      facilitator_finalize: prompts/brainstorm/facilitator_finalize.txt
      participant_append: prompts/brainstorm/participant_append.txt
      participant_user: prompts/brainstorm/participant_user.txt
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('dyn');

            $analyst = $chain->getRoleConfig('analyst');
            self::assertNotNull($analyst);
            self::assertSame(600, $analyst->getTimeout());
            self::assertSame('docs/roles/analyst.md', $analyst->getPromptFile());

            $architect = $chain->getRoleConfig('architect');
            self::assertNotNull($architect);
            self::assertSame(['--model', 'o3-mini'], $architect->getCommand());
            self::assertNull($architect->getTimeout());
            self::assertNull($architect->getPromptFile());

            self::assertNull($chain->getRoleConfig('nonexistent'));
        } finally {
            unlink($fixturePath);
            $this->rmdirRecursive($fixtureDir);
        }
    }

    #[Test]
    public function loadRolesAppliesToBothChainTypes(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_roles_both_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
roles:
  r1:
    command:
      - --model
      - gpt-4o
chains:
  static_chain:
    steps:
      - { type: agent, role: r1 }
  dyn:
    type: dynamic
    facilitator: r1
    participants: [r1]
    prompts:
      brainstorm_system: "BS"
      facilitator_append: "FA %s"
      facilitator_start: "St %s"
      facilitator_continue: "C %s %s"
      facilitator_finalize: "F %s %s"
      participant_append: "PA %s"
      participant_user: "PU %s %s"
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);

            $staticChain = $loader->load('static_chain');
            self::assertSame(['--model', 'gpt-4o'], $staticChain->getRoleConfig('r1')->getCommand());

            $dynChain = $loader->load('dyn');
            self::assertSame(['--model', 'gpt-4o'], $dynChain->getRoleConfig('r1')->getCommand());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadRolesWithRunnerAndPromptFile(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_roles_full_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
roles:
  custom_role:
    command:
      - --no-auto-commits
    timeout: 900
chains:
  s:
    steps:
      - { type: agent, role: custom_role }
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('s');

            $config = $chain->getRoleConfig('custom_role');
            self::assertNotNull($config);
            self::assertSame(['--no-auto-commits'], $config->getCommand());
            self::assertSame(900, $config->getTimeout());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    // --- Retry policy tests ---

    #[Test]
    public function loadParsesChainLevelRetryPolicy(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_retry_chain_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  retry_chain:
    steps:
      - { type: agent, role: r1 }
      - { type: agent, role: r2 }
    retry_policy:
      max_retries: 5
      initial_delay_ms: 200
      max_delay_ms: 10000
      multiplier: 1.5
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('retry_chain');

            $retryPolicy = $chain->getDefaultRetryPolicy();
            self::assertNotNull($retryPolicy);
            self::assertSame(5, $retryPolicy->getMaxRetries());
            self::assertSame(200, $retryPolicy->getInitialDelayMs());
            self::assertSame(10000, $retryPolicy->getMaxDelayMs());
            self::assertSame(1.5, $retryPolicy->getMultiplier());

            // Шаги наследуют retry_policy от цепочки
            self::assertNotNull($chain->getSteps()[0]->getRetryPolicy());
            self::assertSame(5, $chain->getSteps()[0]->getRetryPolicy()->getMaxRetries());
            self::assertNotNull($chain->getSteps()[1]->getRetryPolicy());
            self::assertSame(5, $chain->getSteps()[1]->getRetryPolicy()->getMaxRetries());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadParsesStepLevelRetryPolicyOverride(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_retry_step_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  mixed_retry:
    retry_policy:
      max_retries: 3
      initial_delay_ms: 1000
    steps:
      - { type: agent, role: r1 }
      - type: agent
        role: r2
        retry_policy:
          max_retries: 10
          initial_delay_ms: 100
      - { type: agent, role: r3 }
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('mixed_retry');

            // Цепочная политика
            $chainPolicy = $chain->getDefaultRetryPolicy();
            self::assertNotNull($chainPolicy);
            self::assertSame(3, $chainPolicy->getMaxRetries());
            self::assertSame(1000, $chainPolicy->getInitialDelayMs());

            // r1: наследует цепочную
            $r1Policy = $chain->getSteps()[0]->getRetryPolicy();
            self::assertNotNull($r1Policy);
            self::assertSame(3, $r1Policy->getMaxRetries());
            self::assertSame(1000, $r1Policy->getInitialDelayMs());

            // r2: своя политика
            $r2Policy = $chain->getSteps()[1]->getRetryPolicy();
            self::assertNotNull($r2Policy);
            self::assertSame(10, $r2Policy->getMaxRetries());
            self::assertSame(100, $r2Policy->getInitialDelayMs());

            // r3: наследует цепочную
            $r3Policy = $chain->getSteps()[2]->getRetryPolicy();
            self::assertNotNull($r3Policy);
            self::assertSame(3, $r3Policy->getMaxRetries());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadChainWithoutRetryPolicyReturnsNull(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_no_retry_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, "chains:\n  plain:\n    steps:\n      - { type: agent, role: r1 }\n");

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('plain');

            self::assertNull($chain->getDefaultRetryPolicy());
            self::assertNull($chain->getSteps()[0]->getRetryPolicy());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadStepRetryPolicyWithoutChainPolicy(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_step_only_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  step_retry:
    steps:
      - { type: agent, role: r1 }
      - type: agent
        role: r2
        retry_policy:
          max_retries: 7
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('step_retry');

            // Нет цепочной политики
            self::assertNull($chain->getDefaultRetryPolicy());

            // r1: нет retry
            self::assertNull($chain->getSteps()[0]->getRetryPolicy());

            // r2: своя политика
            $r2Policy = $chain->getSteps()[1]->getRetryPolicy();
            self::assertNotNull($r2Policy);
            self::assertSame(7, $r2Policy->getMaxRetries());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    // --- Fallback config tests (role-level) ---

    #[Test]
    public function loadParsesFallbackFromRole(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_fallback_role_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
roles:
  analyst:
    command:
      - pi
      - --model
      - glm-5-turbo
    fallback:
      command:
        - codex
        - --model
        - gpt-4o
        - --full-auto
chains:
  s:
    steps:
      - { type: agent, role: analyst }
      - { type: agent, role: other }
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('s');

            // analyst: fallback задан с полной command
            $analystConfig = $chain->getRoleConfig('analyst');
            self::assertNotNull($analystConfig);
            $fallback = $analystConfig->getFallback();
            self::assertNotNull($fallback);
            self::assertSame('codex', $fallback->getRunnerName());
            self::assertSame(['codex', '--model', 'gpt-4o', '--full-auto'], $fallback->getCommand());

            // other: fallback не задан
            $otherConfig = $chain->getRoleConfig('other');
            self::assertNull($otherConfig?->getFallback());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadTreatsEmptyFallbackAsNull(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_fallback_empty_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
roles:
  r1:
    fallback: ''
chains:
  s:
    steps:
      - { type: agent, role: r1 }
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('s');

            $r1Config = $chain->getRoleConfig('r1');
            self::assertNull($r1Config?->getFallback());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadTreatsFallbackWithEmptyCommandAsNull(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_fallback_empty_cmd_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
roles:
  r1:
    fallback:
      command: []
chains:
  s:
    steps:
      - { type: agent, role: r1 }
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('s');

            $r1Config = $chain->getRoleConfig('r1');
            self::assertNull($r1Config?->getFallback());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadFallbackCombinedWithRetryPolicy(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_fallback_retry_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
roles:
  analyst:
    fallback:
      command:
        - codex
        - --model
        - gpt-4o
chains:
  fb_retry:
    retry_policy:
      max_retries: 3
    steps:
      - type: agent
        role: analyst
        retry_policy:
          max_retries: 5
      - { type: agent, role: other }
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('fb_retry');

            // analyst: fallback через role config + retry на шаге
            $analystConfig = $chain->getRoleConfig('analyst');
            self::assertNotNull($analystConfig);
            $fallback = $analystConfig->getFallback();
            self::assertNotNull($fallback);
            self::assertSame('codex', $fallback->getRunnerName());
            self::assertSame(['codex', '--model', 'gpt-4o'], $fallback->getCommand());

            self::assertNotNull($chain->getSteps()[0]->getRetryPolicy());
            self::assertSame(5, $chain->getSteps()[0]->getRetryPolicy()->getMaxRetries());

            // other: без fallback, наследует цепочную retry
            $otherConfig = $chain->getRoleConfig('other');
            self::assertNull($otherConfig?->getFallback());
            self::assertNotNull($chain->getSteps()[1]->getRetryPolicy());
            self::assertSame(3, $chain->getSteps()[1]->getRetryPolicy()->getMaxRetries());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    // --- Budget parsing tests ---

    #[Test]
    public function loadParsesStaticChainWithBudget(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_budget_static_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  budgeted:
    steps:
      - { type: agent, role: r1 }
      - { type: agent, role: r2 }
    budget:
      max_cost_total: 5.0
      max_cost_per_step: 1.5
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('budgeted');

            $budget = $chain->getBudget();
            self::assertNotNull($budget);
            self::assertSame(5.0, $budget->getMaxCostTotal());
            self::assertSame(1.5, $budget->getMaxCostPerStep());
            self::assertFalse($budget->isUnlimited());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadParsesDynamicChainWithBudget(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_budget_dyn_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  dyn_budget:
    type: dynamic
    facilitator: x
    participants: [a, b]
    budget:
      max_cost_total: 10.0
    prompts:
      brainstorm_system: "BS"
      facilitator_append: "FA %s"
      facilitator_start: "St %s"
      facilitator_continue: "C %s %s"
      facilitator_finalize: "F %s %s"
      participant_append: "PA %s"
      participant_user: "PU %s %s"
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('dyn_budget');

            $budget = $chain->getBudget();
            self::assertNotNull($budget);
            self::assertSame(10.0, $budget->getMaxCostTotal());
            self::assertNull($budget->getMaxCostPerStep());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadChainWithoutBudgetReturnsNullBudget(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_no_budget_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, "chains:\n  plain:\n    steps:\n      - { type: agent, role: r1 }\n");

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('plain');

            self::assertNull($chain->getBudget());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadChainWithEmptyBudgetReturnsNullBudget(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_empty_budget_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  empty_budget:
    steps:
      - { type: agent, role: r1 }
    budget: []
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('empty_budget');

            self::assertNull($chain->getBudget());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadChainWithOnlyPerStepBudget(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_per_step_budget_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  per_step:
    steps:
      - { type: agent, role: r1 }
    budget:
      max_cost_per_step: 2.0
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('per_step');

            $budget = $chain->getBudget();
            self::assertNotNull($budget);
            self::assertNull($budget->getMaxCostTotal());
            self::assertSame(2.0, $budget->getMaxCostPerStep());
            self::assertFalse($budget->isUnlimited());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadParsesPerRoleBudget(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_per_role_budget_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  per_role_budget:
    steps:
      - { type: agent, role: analyst }
      - { type: agent, role: developer }
    budget:
      max_cost_total: 10.0
      per_role:
        analyst:
          max_cost_total: 3.0
          max_cost_per_step: 1.0
        developer:
          max_cost_total: 5.0
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('per_role_budget');

            $budget = $chain->getBudget();
            self::assertNotNull($budget);
            self::assertSame(10.0, $budget->getMaxCostTotal());
            self::assertTrue($budget->hasRoleBudgets());

            $analystBudget = $budget->getRoleBudget('analyst');
            self::assertNotNull($analystBudget);
            self::assertSame(3.0, $analystBudget->getMaxCostTotal());
            self::assertSame(1.0, $analystBudget->getMaxCostPerStep());

            $devBudget = $budget->getRoleBudget('developer');
            self::assertNotNull($devBudget);
            self::assertSame(5.0, $devBudget->getMaxCostTotal());
            self::assertNull($devBudget->getMaxCostPerStep());

            self::assertNull($budget->getRoleBudget('unknown_role'));
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadParsesFixIterationsSection(): void
    {
        $fixturePath = $this->fixturePath;

        $yaml = <<<YAML
chains:
  implement:
    description: "Full implementation cycle"
    steps:
      - { type: agent, role: system_analyst }
      - type: agent
        role: backend_developer
        name: implement
      - type: agent
        role: code_reviewer_backend
        name: review
    fix_iterations:
      - group: dev-review
        steps: [implement, review]
        max_iterations: 3
YAML;
        file_put_contents($fixturePath, $yaml);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('implement');

            $steps = $chain->getSteps();
            self::assertCount(3, $steps);

            // step 0: no name
            self::assertNull($steps[0]->getName());

            // step 1: developer with name
            self::assertSame('implement', $steps[1]->getName());

            // step 2: reviewer with name
            self::assertSame('review', $steps[2]->getName());

            // fix_iterations parsed correctly
            $fixIterations = $chain->getFixIterations();
            self::assertCount(1, $fixIterations);
            self::assertSame('dev-review', $fixIterations[0]->getGroup());
            self::assertSame(['implement', 'review'], $fixIterations[0]->getStepNames());
            self::assertSame(3, $fixIterations[0]->getMaxIterations());
        } finally {
            unlink($fixturePath);
        }
    }

    #[Test]
    public function loadStepsWithoutNamesHaveNullName(): void
    {
        $fixturePath = $this->fixturePath;

        $yaml = <<<YAML
chains:
  simple:
    description: "Simple chain"
    steps:
      - { type: agent, role: analyst }
      - { type: agent, role: developer }
YAML;
        file_put_contents($fixturePath, $yaml);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('simple');

            foreach ($chain->getSteps() as $step) {
                self::assertNull($step->getName());
            }

            self::assertSame([], $chain->getFixIterations());
        } finally {
            unlink($fixturePath);
        }
    }

    private function rmdirRecursive(string $dir): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }

    // --- Quality Gate step type tests ---

    #[Test]
    public function loadParsesQualityGateSteps(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_qg_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  with_gates:
    steps:
      - { type: agent, role: developer }
      - type: quality_gate
        command: 'make lint-php'
        label: 'PHP CodeSniffer'
        timeout_seconds: 60
      - type: quality_gate
        command: 'make tests-unit'
        label: 'Unit Tests'
        timeout_seconds: 120
      - { type: agent, role: reviewer }
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('with_gates');

            $steps = $chain->getSteps();
            self::assertCount(4, $steps);

            // Step 0: agent
            self::assertTrue($steps[0]->isAgent());
            self::assertSame('developer', $steps[0]->getRole());

            // Step 1: quality gate
            self::assertTrue($steps[1]->isQualityGate());
            self::assertSame('make lint-php', $steps[1]->getCommand());
            self::assertSame('PHP CodeSniffer', $steps[1]->getLabel());
            self::assertSame(60, $steps[1]->getTimeoutSeconds());
            self::assertNull($steps[1]->getRole());

            // Step 2: quality gate
            self::assertTrue($steps[2]->isQualityGate());
            self::assertSame('make tests-unit', $steps[2]->getCommand());
            self::assertSame('Unit Tests', $steps[2]->getLabel());
            self::assertSame(120, $steps[2]->getTimeoutSeconds());

            // Step 3: agent
            self::assertTrue($steps[3]->isAgent());
            self::assertSame('reviewer', $steps[3]->getRole());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadQualityGateDefaultsTimeoutTo120(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_qg_default_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  default_timeout:
    steps:
      - type: quality_gate
        command: 'make lint'
        label: 'Lint'
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('default_timeout');

            $step = $chain->getSteps()[0];
            self::assertTrue($step->isQualityGate());
            self::assertSame(120, $step->getTimeoutSeconds());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadQualityGateWithOptionalName(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_qg_name_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  named_gate:
    steps:
      - type: quality_gate
        command: 'make lint'
        label: 'Lint'
        name: lint_check
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('named_gate');

            $step = $chain->getSteps()[0];
            self::assertSame('lint_check', $step->getName());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadMixedAgentAndQualityGateSteps(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_mixed_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  mixed:
    steps:
      - { type: agent, role: system_analyst }
      - { type: agent, role: backend_developer }
      - type: quality_gate
        command: 'make lint'
        label: 'Lint'
      - type: quality_gate
        command: 'make test'
        label: 'Tests'
      - { type: agent, role: code_reviewer_backend }
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('mixed');

            $steps = $chain->getSteps();
            self::assertCount(5, $steps);

            self::assertTrue($steps[0]->isAgent());
            self::assertTrue($steps[1]->isAgent());
            self::assertTrue($steps[2]->isQualityGate());
            self::assertTrue($steps[3]->isQualityGate());
            self::assertTrue($steps[4]->isAgent());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    // --- no_context_files tests ---

    #[Test]
    public function loadParsesStepLevelNoContextFiles(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_nc_step_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  nc_step:
    steps:
      - { type: agent, role: r1, no_context_files: true }
      - { type: agent, role: r2 }
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('nc_step');

            // r1: no_context_files = true
            self::assertTrue($chain->getSteps()[0]->getNoContextFiles());
            // r2: no_context_files = false (default)
            self::assertFalse($chain->getSteps()[1]->getNoContextFiles());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadParsesChainLevelNoContextFiles(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_nc_chain_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  nc_chain:
    no_context_files: true
    steps:
      - { type: agent, role: r1 }
      - { type: agent, role: r2 }
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('nc_chain');

            // Оба шага наследуют no_context_files от цепочки
            self::assertTrue($chain->getSteps()[0]->getNoContextFiles());
            self::assertTrue($chain->getSteps()[1]->getNoContextFiles());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadStepOverridesChainLevelNoContextFiles(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_nc_override_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  nc_override:
    no_context_files: true
    steps:
      - { type: agent, role: r1 }
      - { type: agent, role: r2, no_context_files: false }
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('nc_override');

            // r1: наследует true от цепочки
            self::assertTrue($chain->getSteps()[0]->getNoContextFiles());
            // r2: переопределено на false
            self::assertFalse($chain->getSteps()[1]->getNoContextFiles());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadDefaultsNoContextFilesToFalse(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_nc_default_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, "chains:\n  plain:\n    steps:\n      - { type: agent, role: r1 }\n");

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('plain');

            self::assertFalse($chain->getSteps()[0]->getNoContextFiles());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    // --- Timeout parsing tests ---

    #[Test]
    public function loadParsesDynamicChainTimeout(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_dyn_timeout_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  dyn_t:
    type: dynamic
    facilitator: x
    participants: [a]
    timeout: 600
    prompts:
      brainstorm_system: "BS"
      facilitator_append: "FA %s"
      facilitator_start: "St %s"
      facilitator_continue: "C %s %s"
      facilitator_finalize: "F %s %s"
      participant_append: "PA %s"
      participant_user: "PU %s %s"
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('dyn_t');

            self::assertSame(600, $chain->getTimeout());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadDynamicChainWithoutTimeoutReturnsNull(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_dyn_no_timeout_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  dyn_nt:
    type: dynamic
    facilitator: x
    participants: [a]
    prompts:
      brainstorm_system: "BS"
      facilitator_append: "FA %s"
      facilitator_start: "St %s"
      facilitator_continue: "C %s %s"
      facilitator_finalize: "F %s %s"
      participant_append: "PA %s"
      participant_user: "PU %s %s"
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('dyn_nt');

            self::assertNull($chain->getTimeout());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadParsesStaticChainTimeout(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_static_timeout_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  static_t:
    timeout: 1200
    steps:
      - { type: agent, role: r1 }
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('static_t');

            self::assertSame(1200, $chain->getTimeout());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadStaticChainWithoutTimeoutReturnsNull(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_static_no_timeout_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, "chains:\n  plain:\n    steps:\n      - { type: agent, role: r1 }\n");

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('plain');

            self::assertNull($chain->getTimeout());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    // --- max_time parsing tests ---

    #[Test]
    public function loadParsesDynamicChainMaxTime(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_dyn_maxtime_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  dyn_mt:
    type: dynamic
    facilitator: x
    participants: [a]
    max_time: 1800
    prompts:
      brainstorm_system: "BS"
      facilitator_append: "FA %s"
      facilitator_start: "St %s"
      facilitator_continue: "C %s %s"
      facilitator_finalize: "F %s %s"
      participant_append: "PA %s"
      participant_user: "PU %s %s"
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('dyn_mt');

            self::assertSame(1800, $chain->getMaxTime());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }

    #[Test]
    public function loadDynamicChainWithoutMaxTimeReturnsNull(): void
    {
        $fixtureDir = sys_get_temp_dir() . '/agent_chains_dyn_no_maxtime_' . uniqid();
        mkdir($fixtureDir);
        $fixturePath = $fixtureDir . '/chains.yaml';
        file_put_contents($fixturePath, <<<'YAML'
chains:
  dyn_nmt:
    type: dynamic
    facilitator: x
    participants: [a]
    prompts:
      brainstorm_system: "BS"
      facilitator_append: "FA %s"
      facilitator_start: "St %s"
      facilitator_continue: "C %s %s"
      facilitator_finalize: "F %s %s"
      participant_append: "PA %s"
      participant_user: "PU %s %s"
YAML);

        try {
            $loader = new YamlChainLoader($fixturePath);
            $chain = $loader->load('dyn_nmt');

            self::assertNull($chain->getMaxTime());
        } finally {
            unlink($fixturePath);
            rmdir($fixtureDir);
        }
    }
}
