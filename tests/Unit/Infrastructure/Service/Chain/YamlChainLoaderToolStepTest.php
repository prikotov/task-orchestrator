<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\Chain;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainStepTypeEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Enum\ChainTypeEnum;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain\YamlChainLoaderService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlChainLoaderService::class)]
final class YamlChainLoaderToolStepTest extends TestCase
{
    private string $fixtureDir;
    private string $fixturePath;

    protected function setUp(): void
    {
        $this->fixtureDir = sys_get_temp_dir() . '/agent_chains_tool_test_' . uniqid();
        $this->fixturePath = $this->fixtureDir . '/chains.yaml';

        mkdir($this->fixtureDir, 0777, true);
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

    #[Test]
    public function loadParsesToolStep(): void
    {
        $yaml = <<<YAML
chains:
  with_tool:
    description: "Chain with tool step"
    steps:
      - type: tool
        command: "git rev-parse HEAD"
        label: "Get commit hash"
        output_key: commit_hash
        timeout_seconds: 30
      - type: agent
        role: backend_developer
YAML;
        file_put_contents($this->fixturePath, $yaml);
        $loader = new YamlChainLoaderService($this->fixturePath);

        $chain = $loader->load('with_tool');

        self::assertSame('with_tool', $chain->getName());
        self::assertSame(ChainTypeEnum::staticType, $chain->getType());
        $steps = $chain->getSteps();
        self::assertCount(2, $steps);

        // Tool step
        $toolStep = $steps[0];
        self::assertSame(ChainStepTypeEnum::tool, $toolStep->getType());
        self::assertTrue($toolStep->isTool());
        self::assertSame('git rev-parse HEAD', $toolStep->getCommand());
        self::assertSame('Get commit hash', $toolStep->getLabel());
        self::assertSame(30, $toolStep->getTimeoutSeconds());
        self::assertSame('commit_hash', $toolStep->getOutputKey());

        // Agent step
        $agentStep = $steps[1];
        self::assertSame(ChainStepTypeEnum::agent, $agentStep->getType());
        self::assertSame('backend_developer', $agentStep->getRole());
    }

    #[Test]
    public function loadParsesToolStepWithoutOutputKey(): void
    {
        $yaml = <<<YAML
chains:
  tool_no_key:
    description: "Tool without output_key"
    steps:
      - type: tool
        command: "echo hello"
        label: "Echo"
YAML;
        file_put_contents($this->fixturePath, $yaml);
        $loader = new YamlChainLoaderService($this->fixturePath);

        $chain = $loader->load('tool_no_key');
        $toolStep = $chain->getSteps()[0];

        self::assertSame(ChainStepTypeEnum::tool, $toolStep->getType());
        self::assertNull($toolStep->getOutputKey());
        self::assertSame(120, $toolStep->getTimeoutSeconds());
    }

    #[Test]
    public function loadParsesToolStepWithName(): void
    {
        $yaml = <<<YAML
chains:
  tool_named:
    description: "Tool with name"
    steps:
      - type: tool
        command: "git status"
        label: "Git status"
        name: git_status_step
        output_key: git_status
YAML;
        file_put_contents($this->fixturePath, $yaml);
        $loader = new YamlChainLoaderService($this->fixturePath);

        $chain = $loader->load('tool_named');
        $toolStep = $chain->getSteps()[0];

        self::assertSame('git_status_step', $toolStep->getName());
        self::assertSame('git_status', $toolStep->getOutputKey());
    }

    #[Test]
    public function loadToolStepRequiresCommand(): void
    {
        $yaml = <<<YAML
chains:
  bad_tool:
    description: "Tool without command"
    steps:
      - type: tool
        label: "Missing command"
YAML;
        file_put_contents($this->fixturePath, $yaml);
        $loader = new YamlChainLoaderService($this->fixturePath);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tool step must have "command"');

        $loader->load('bad_tool');
    }

    #[Test]
    public function loadToolStepRequiresLabel(): void
    {
        $yaml = <<<YAML
chains:
  bad_tool:
    description: "Tool without label"
    steps:
      - type: tool
        command: "echo hi"
YAML;
        file_put_contents($this->fixturePath, $yaml);
        $loader = new YamlChainLoaderService($this->fixturePath);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tool step must have "label"');

        $loader->load('bad_tool');
    }

    #[Test]
    public function loadMixedStepsChain(): void
    {
        $yaml = <<<YAML
chains:
  mixed:
    description: "Mixed steps"
    steps:
      - type: tool
        command: "git rev-parse --abbrev-ref HEAD"
        label: "Get branch"
        output_key: branch
        timeout_seconds: 10
      - type: agent
        role: system_analyst
        name: analyze
      - type: quality_gate
        command: "make check"
        label: "Check"
        timeout_seconds: 120
      - type: agent
        role: backend_developer
        name: implement
YAML;
        file_put_contents($this->fixturePath, $yaml);
        $loader = new YamlChainLoaderService($this->fixturePath);

        $chain = $loader->load('mixed');
        $steps = $chain->getSteps();

        self::assertCount(4, $steps);
        self::assertTrue($steps[0]->isTool());
        self::assertTrue($steps[1]->isAgent());
        self::assertTrue($steps[2]->isQualityGate());
        self::assertTrue($steps[3]->isAgent());
    }
}
