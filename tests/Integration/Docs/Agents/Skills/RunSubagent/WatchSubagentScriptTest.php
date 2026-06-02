<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Docs\Agents\Skills\RunSubagent;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class WatchSubagentScriptTest extends TestCase
{
    private string $tempDir;
    private string $fakeBinDir;
    private string $roleFile;
    private string $chainsConfig;
    private string $runnerCaptureFile;
    private string $argsCaptureFile;

    protected function setUp(): void
    {
        $tempDir = sys_get_temp_dir() . '/watch-subagent-test-' . bin2hex(random_bytes(6));
        mkdir($tempDir, 0777, true);

        $this->tempDir = $tempDir;
        $this->fakeBinDir = $tempDir . '/bin';
        $this->roleFile = $tempDir . '/backend_developer_levsha.ru.md';
        $this->chainsConfig = $tempDir . '/chains.yaml';
        $this->runnerCaptureFile = $tempDir . '/runner.txt';
        $this->argsCaptureFile = $tempDir . '/args.txt';

        mkdir($this->fakeBinDir, 0777, true);
        file_put_contents($this->roleFile, "# Test role\n");
        file_put_contents($this->chainsConfig, <<<'YAML'
roles:
  backend_developer_levsha:
    command:
      - codex
      - exec
      - --json
      - --model
      - role-model
      - -c
      - 'model_reasoning_effort="xhigh"'
YAML);

        $this->writeFakeRunner('pi');
        $this->writeFakeRunner('codex');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    #[Test]
    public function usesRoleCommandProfileWhenRunnerModelAndReasoningAreNotExplicit(): void
    {
        $this->runScript();

        self::assertSame('codex', trim((string) file_get_contents($this->runnerCaptureFile)));

        $args = (string) file_get_contents($this->argsCaptureFile);
        self::assertStringContainsString('--model role-model', $args);
        self::assertStringContainsString('model_reasoning_effort=xhigh', $args);
    }

    #[Test]
    public function explicitRunnerMatchingRoleCommandProfileUsesRoleModelAndReasoningDefaults(): void
    {
        $this->runScript(arguments: ['--runner', 'codex']);

        self::assertSame('codex', trim((string) file_get_contents($this->runnerCaptureFile)));

        $args = (string) file_get_contents($this->argsCaptureFile);
        self::assertStringContainsString('--model role-model', $args);
        self::assertStringContainsString('model_reasoning_effort=xhigh', $args);
    }

    #[Test]
    public function explicitRunnerDifferentFromRoleCommandProfileDoesNotUseRoleModelAndReasoningDefaults(): void
    {
        $this->runScript(arguments: ['--runner', 'pi']);

        self::assertSame('pi', trim((string) file_get_contents($this->runnerCaptureFile)));

        $args = (string) file_get_contents($this->argsCaptureFile);
        self::assertStringNotContainsString('--model role-model', $args);
        self::assertStringNotContainsString('--thinking xhigh', $args);
        self::assertStringNotContainsString('role-model', $args);
        self::assertStringNotContainsString('xhigh', $args);
    }

    #[Test]
    public function envOverridesRoleCommandProfile(): void
    {
        $this->runScript(env: [
            'RUNNER' => 'pi',
            'MODEL' => 'env-model',
            'REASONING' => 'low',
        ]);

        self::assertSame('pi', trim((string) file_get_contents($this->runnerCaptureFile)));

        $args = (string) file_get_contents($this->argsCaptureFile);
        self::assertStringContainsString('--model env-model', $args);
        self::assertStringContainsString('--thinking low', $args);
        self::assertStringNotContainsString('role-model', $args);
    }

    #[Test]
    public function cliOptionsOverrideEnvAndRoleCommandProfile(): void
    {
        $this->runScript(
            arguments: ['--runner', 'codex', '--model', 'cli-model', '--reasoning', 'medium'],
            env: [
                'RUNNER' => 'pi',
                'MODEL' => 'env-model',
                'REASONING' => 'low',
            ],
        );

        self::assertSame('codex', trim((string) file_get_contents($this->runnerCaptureFile)));

        $args = (string) file_get_contents($this->argsCaptureFile);
        self::assertStringContainsString('--model cli-model', $args);
        self::assertStringContainsString('model_reasoning_effort=medium', $args);
        self::assertStringNotContainsString('env-model', $args);
        self::assertStringNotContainsString('role-model', $args);
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string> $env
     */
    private function runScript(array $arguments = [], array $env = []): void
    {
        $projectRoot = dirname(__DIR__, 6);
        $script = $projectRoot . '/docs/agents/skills/run-subagent/scripts/watch-subagent.sh';
        $path = $this->fakeBinDir . PATH_SEPARATOR . (getenv('PATH') ?: '');

        $process = new Process(
            array_merge(
                [
                    $script,
                    '-s',
                    '2',
                    '-t',
                    '2',
                    '-m',
                    '4',
                    '-r',
                    $this->roleFile,
                ],
                $arguments,
                ['Test prompt'],
            ),
            $projectRoot,
            array_merge(
                [
                    'PATH' => $path,
                    'CHAINS_CONFIG' => $this->chainsConfig,
                    'RUNNER_CAPTURE_FILE' => $this->runnerCaptureFile,
                    'ARGS_CAPTURE_FILE' => $this->argsCaptureFile,
                ],
                $env,
            ),
        );
        $process->setTimeout(10);
        $process->run();

        self::assertTrue(
            $process->isSuccessful(),
            $process->getErrorOutput() . $process->getOutput(),
        );
        self::assertStringContainsString('"agent_end"', $process->getOutput());
    }

    private function writeFakeRunner(string $name): void
    {
        $path = $this->fakeBinDir . '/' . $name;
        file_put_contents($path, <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$(basename "$0")" > "$RUNNER_CAPTURE_FILE"
printf '%s\n' "$*" > "$ARGS_CAPTURE_FILE"
printf '{"type":"agent_end"}\n'
sleep 0.2
BASH);
        chmod($path, 0755);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
                continue;
            }

            unlink($itemPath);
        }

        rmdir($path);
    }
}
