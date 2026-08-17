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
    private string $piRoleFile;
    private string $chainsConfig;
    private string $runnerCaptureFile;
    private string $argsCaptureFile;
    private string $stdinCaptureFile;

    protected function setUp(): void
    {
        $tempDir = sys_get_temp_dir() . '/watch-subagent-test-' . bin2hex(random_bytes(6));
        mkdir($tempDir, 0777, true);

        $this->tempDir = $tempDir;
        $this->fakeBinDir = $tempDir . '/bin';
        $this->roleFile = $tempDir . '/backend_developer_levsha.ru.md';
        $this->piRoleFile = $tempDir . '/team_lead_alex.ru.md';
        $this->chainsConfig = $tempDir . '/chains.yaml';
        $this->runnerCaptureFile = $tempDir . '/runner.txt';
        $this->argsCaptureFile = $tempDir . '/args.txt';
        $this->stdinCaptureFile = $tempDir . '/stdin.txt';

        mkdir($this->fakeBinDir, 0777, true);
        file_put_contents($this->roleFile, "# Test role\n");
        file_put_contents($this->piRoleFile, "# Test pi role\n");
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
  team_lead_alex:
    command:
      - pi
      - --mode
      - json
      - -p
      - --no-session
      - --provider
      - zai
      - --model
      - pi-role-model
      - --thinking
      - high
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
            'PROVIDER' => 'env-provider',
            'MODEL' => 'env-model',
            'REASONING' => 'low',
        ]);

        self::assertSame('pi', trim((string) file_get_contents($this->runnerCaptureFile)));

        $args = (string) file_get_contents($this->argsCaptureFile);
        self::assertStringContainsString('--provider env-provider', $args);
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
                'PROVIDER' => 'env-provider',
                'MODEL' => 'env-model',
                'REASONING' => 'low',
            ],
        );

        self::assertSame('codex', trim((string) file_get_contents($this->runnerCaptureFile)));

        $args = (string) file_get_contents($this->argsCaptureFile);
        self::assertStringContainsString('--model cli-model', $args);
        self::assertStringContainsString('model_reasoning_effort=medium', $args);
        self::assertStringNotContainsString('env-provider', $args);
        self::assertStringNotContainsString('env-model', $args);
        self::assertStringNotContainsString('role-model', $args);
    }

    #[Test]
    public function passesPromptToRunnerStdin(): void
    {
        $this->runScript(prompt: 'Prompt sentinel 123');

        self::assertSame('Prompt sentinel 123', trim((string) file_get_contents($this->stdinCaptureFile)));
    }

    #[Test]
    public function usesPiProviderModelAndReasoningFromRoleCommandProfile(): void
    {
        $this->runScript(roleFile: $this->piRoleFile);

        self::assertSame('pi', trim((string) file_get_contents($this->runnerCaptureFile)));

        $args = (string) file_get_contents($this->argsCaptureFile);
        self::assertStringContainsString('-p', $args);
        self::assertStringContainsString('--provider zai', $args);
        self::assertStringContainsString('--model pi-role-model', $args);
        self::assertStringContainsString('--thinking high', $args);
    }

    #[Test]
    public function explicitRunnerDifferentFromPiRoleCommandProfileDoesNotUsePiProfileDefaults(): void
    {
        $this->runScript(arguments: ['--runner', 'codex'], roleFile: $this->piRoleFile);

        self::assertSame('codex', trim((string) file_get_contents($this->runnerCaptureFile)));

        $args = (string) file_get_contents($this->argsCaptureFile);
        self::assertStringNotContainsString('--provider zai', $args);
        self::assertStringNotContainsString('pi-role-model', $args);
        self::assertStringNotContainsString('model_reasoning_effort=high', $args);
    }

    #[Test]
    public function failsWhenRunnerClosesPipeWithoutAgentEnd(): void
    {
        $process = $this->runScript(
            env: [
                'FAKE_RUNNER_AGENT_END' => '0',
            ],
            roleFile: $this->piRoleFile,
            expectSuccess: false,
        );

        self::assertFalse($process->isSuccessful());
        self::assertStringContainsString('"reason":"missing_agent_end"', $process->getErrorOutput());
    }

    #[Test]
    public function failsWhenRunnerExitsWithNonZeroStatusAndShowsStderr(): void
    {
        $process = $this->runScript(
            env: [
                'FAKE_RUNNER_AGENT_END' => '0',
                'FAKE_RUNNER_EXIT_CODE' => '42',
                'FAKE_RUNNER_STDERR' => 'No API key found for opencode.',
            ],
            expectSuccess: false,
        );

        self::assertFalse($process->isSuccessful());
        self::assertStringContainsString('"reason":"runner_failed"', $process->getErrorOutput());
        self::assertStringContainsString('"exit_code":42', $process->getErrorOutput());
        self::assertStringContainsString('No API key found for opencode.', $process->getErrorOutput());
    }

    #[Test]
    public function codexRunnerCompletesOnTurnCompletedAndExtractsItemText(): void
    {
        $process = $this->runScript(
            arguments: ['--runner', 'codex', '-o', 'text'],
            env: [
                'FAKE_RUNNER_EVENTS' => implode("\n", [
                    '{"type":"item.completed","item":{"type":"agent_message","text":"Codex OK"}}',
                    '{"type":"turn.completed","usage":{"input_tokens":1,"output_tokens":1}}',
                ]) . "\n",
            ],
        );

        self::assertSame("Codex OK\n", $process->getOutput());
    }

    #[Test]
    public function codexRunnerPersistsRolloutAndRequestsLastMessageByDefault(): void
    {
        $logDir = $this->tempDir . '/logs';

        $this->runScript(
            arguments: ['--runner', 'codex'],
            env: ['WATCH_LOG_DIR' => $logDir],
        );

        $args = (string) file_get_contents($this->argsCaptureFile);
        $runLog = $this->findLatestRunLog($logDir);

        self::assertNotNull($runLog);
        self::assertStringNotContainsString('--ephemeral', $args);
        self::assertStringContainsString('-o ' . dirname($runLog) . '/last_message.txt', $args);
        self::assertStringContainsString(
            'codex_rollout_dir=~/.codex/sessions ephemeral=0',
            (string) file_get_contents($runLog),
        );
    }

    #[Test]
    public function codexRunnerCanRestoreEphemeralModeAndStillRequestsLastMessage(): void
    {
        $logDir = $this->tempDir . '/logs';

        $this->runScript(
            arguments: ['--runner', 'codex'],
            env: [
                'WATCH_CODEX_EPHEMERAL' => '1',
                'WATCH_LOG_DIR' => $logDir,
            ],
        );

        $args = (string) file_get_contents($this->argsCaptureFile);
        $runLog = $this->findLatestRunLog($logDir);

        self::assertNotNull($runLog);
        self::assertStringContainsString('--ephemeral', $args);
        self::assertStringContainsString('-o ' . dirname($runLog) . '/last_message.txt', $args);
        self::assertStringContainsString(
            'codex_rollout_dir=disabled ephemeral=1',
            (string) file_get_contents($runLog),
        );
    }

    #[Test]
    public function codexRunnerRaisesShortStallTimeoutAndLogsEffectiveValue(): void
    {
        $logDir = $this->tempDir . '/logs';

        $this->runScript(
            arguments: ['--runner', 'codex', '-t', '120'],
            env: ['WATCH_LOG_DIR' => $logDir],
        );

        $runLog = $this->findLatestRunLog($logDir);
        self::assertNotNull($runLog);
        self::assertStringContainsString(
            'requested_stall_timeout=120s effective_stall_timeout=360s',
            (string) file_get_contents($runLog),
        );
    }

    #[Test]
    public function shortCodexStallTimeoutRequiresExplicitEnvOptIn(): void
    {
        $logDir = $this->tempDir . '/logs';

        $this->runScript(
            arguments: ['--runner', 'codex', '-t', '120'],
            env: [
                'WATCH_CODEX_ALLOW_SHORT_STALL' => '1',
                'WATCH_LOG_DIR' => $logDir,
            ],
        );

        $runLog = $this->findLatestRunLog($logDir);
        self::assertNotNull($runLog);
        self::assertStringContainsString(
            'requested_stall_timeout=120s effective_stall_timeout=120s',
            (string) file_get_contents($runLog),
        );
    }

    #[Test]
    public function piRunnerKeepsConfiguredStallTimeout(): void
    {
        $logDir = $this->tempDir . '/logs';

        $this->runScript(
            arguments: ['--runner', 'pi', '-t', '120'],
            env: ['WATCH_LOG_DIR' => $logDir],
            roleFile: $this->piRoleFile,
        );

        $runLog = $this->findLatestRunLog($logDir);
        self::assertNotNull($runLog);
        self::assertStringContainsString(
            'requested_stall_timeout=120s effective_stall_timeout=120s',
            (string) file_get_contents($runLog),
        );
    }

    #[Test]
    public function codexRunnerPrefersTurnCompletedItemsOverItemCompletedFallback(): void
    {
        $process = $this->runScript(
            arguments: ['--runner', 'codex', '-o', 'text'],
            env: [
                'FAKE_RUNNER_EVENTS' => implode("\n", [
                    '{"type":"item.completed","item":{"type":"agent_message","content":[{"type":"text","text":"Fallback text"}]}}',
                    '{"type":"turn.completed","turn":{"items":[{"type":"command_execution","command":"ls","result":"files"},{"type":"agent_message","content":[{"type":"text","text":"Turn text"}]}]}}',
                ]) . "\n",
            ],
        );

        self::assertSame("Turn text\n", $process->getOutput());
    }

    #[Test]
    public function codexRunnerFailsOnTurnFailed(): void
    {
        $process = $this->runScript(
            arguments: ['--runner', 'codex'],
            env: [
                'FAKE_RUNNER_EVENTS' => '{"type":"turn.failed","error":{"message":"boom"}}' . "\n",
            ],
            expectSuccess: false,
        );

        self::assertFalse($process->isSuccessful());
        self::assertStringContainsString('"reason":"runner_failed_event"', $process->getErrorOutput());
    }

    #[Test]
    public function watcherTerminatesStuckRunLoopWithSummaryAndArchive(): void
    {
        // Симуляция бага: pi эмитит agent_start и засыпает. При большом stall-timeout
        // (-t 999) read блокируется, и in-loop проверки soft/hard/stall не исполняются.
        // Фоновый watcher должен сам терминировать запуск по soft-timeout, написать
        // RUN SUMMARY (source=watcher) и заархивировать events/.
        $logDir = $this->tempDir . '/logs';

        $process = $this->runScript(
            arguments: ['-t', '999', '-m', '60', '--runner', 'pi'],
            env: [
                'FAKE_RUNNER_HANG' => '1',
                'WATCH_WATCHER_INTERVAL' => '1',
                'WATCH_LOG_DIR' => $logDir,
            ],
            expectSuccess: false,
        );

        self::assertFalse($process->isSuccessful());

        $runLog = $this->findLatestRunLog($logDir);
        self::assertNotNull($runLog, 'run.log not found in ' . $logDir);
        $log = (string) file_get_contents($runLog);

        self::assertStringContainsString('watcher_started', $log, 'watcher must be started');
        self::assertStringContainsString('watcher_fired reason=soft_timeout', $log);
        self::assertStringContainsString('=== RUN SUMMARY ===', $log);
        self::assertStringContainsString('reason=soft_timeout', $log);
        self::assertStringContainsString('source=watcher', $log);

        $eventsDir = dirname($runLog) . '/events';
        self::assertFileExists($eventsDir . '/events.ndjson', 'events/ must be archived on failure');
    }

    #[Test]
    public function stallDoesNotKillActiveProcessAndWaitsUntilSoftTimeout(): void
    {
        // Процесс молчит в потоке, но грузит CPU (busy-loop) → liveness=active →
        // stall-timeout НЕ убивает; запуск доживает до soft-timeout (watcher).
        $logDir = $this->tempDir . '/logs';

        $process = $this->runScript(
            arguments: ['-s', '4', '-t', '2', '-m', '30', '--runner', 'pi'],
            env: [
                'FAKE_RUNNER_HANG_ACTIVE' => '1',
                'WATCH_WATCHER_INTERVAL' => '1',
                'WATCH_LOG_DIR' => $logDir,
            ],
            expectSuccess: false,
        );

        self::assertFalse($process->isSuccessful());

        $runLog = $this->findLatestRunLog($logDir);
        self::assertNotNull($runLog, 'run.log not found in ' . $logDir);
        $log = (string) file_get_contents($runLog);

        self::assertStringContainsString('live=active', $log, 'watcher must see busy-loop process as active');
        self::assertStringContainsString('reason=soft_timeout', $log, 'active process must wait until soft-timeout, not stall');
        self::assertStringNotContainsString('reason=stall', $log, 'active process must NOT be killed by stall');
    }

    #[Test]
    public function stallKillsIdleProcessEvenWithLivenessGate(): void
    {
        // Процесс заснул (sleep) → liveness=idle → stall-timeout убивает как обычно.
        $logDir = $this->tempDir . '/logs';

        $process = $this->runScript(
            arguments: ['-s', '30', '-t', '2', '-m', '60', '--runner', 'pi'],
            env: [
                'FAKE_RUNNER_HANG' => '1',
                'WATCH_WATCHER_INTERVAL' => '1',
                'WATCH_LOG_DIR' => $logDir,
            ],
            expectSuccess: false,
        );

        self::assertFalse($process->isSuccessful());

        $runLog = $this->findLatestRunLog($logDir);
        self::assertNotNull($runLog, 'run.log not found in ' . $logDir);
        $log = (string) file_get_contents($runLog);

        self::assertStringContainsString('live=idle', $log, 'watcher must see sleeping process as idle');
        self::assertStringContainsString('reason=stall', $log, 'idle process must be killed by stall even with liveness gate');
    }

    #[Test]
    public function livenessGateCanBeDisabledAndKillsActiveProcessOnStall(): void
    {
        // WATCH_STALL_RESPECT_LIVENESS=0 отключает gate: активный (busy-loop)
        // процесс убивается по stall-timeout — старое поведение.
        $logDir = $this->tempDir . '/logs';

        $process = $this->runScript(
            arguments: ['-s', '30', '-t', '2', '-m', '60', '--runner', 'pi'],
            env: [
                'FAKE_RUNNER_HANG_ACTIVE' => '1',
                'WATCH_STALL_RESPECT_LIVENESS' => '0',
                'WATCH_WATCHER_INTERVAL' => '1',
                'WATCH_LOG_DIR' => $logDir,
            ],
            expectSuccess: false,
        );

        self::assertFalse($process->isSuccessful());

        $runLog = $this->findLatestRunLog($logDir);
        self::assertNotNull($runLog, 'run.log not found in ' . $logDir);
        $log = (string) file_get_contents($runLog);

        self::assertStringContainsString('reason=stall', $log, 'with gate disabled, active process must be killed by stall');
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string> $env
     */
    private function runScript(
        array $arguments = [],
        array $env = [],
        ?string $roleFile = null,
        string $prompt = 'Test prompt',
        bool $expectSuccess = true,
    ): Process {
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
                    $roleFile ?? $this->roleFile,
                ],
                $arguments,
                [$prompt],
            ),
            $projectRoot,
            array_merge(
                [
                    'PATH' => $path,
                    'CHAINS_CONFIG' => $this->chainsConfig,
                    'RUNNER_CAPTURE_FILE' => $this->runnerCaptureFile,
                    'ARGS_CAPTURE_FILE' => $this->argsCaptureFile,
                    'STDIN_CAPTURE_FILE' => $this->stdinCaptureFile,
                ],
                $env,
            ),
        );
        $process->setTimeout(10);
        $process->run();

        if ($expectSuccess) {
            self::assertTrue(
                $process->isSuccessful(),
                $process->getErrorOutput() . $process->getOutput(),
            );
            self::assertNotSame('', trim($process->getOutput()));
        }

        return $process;
    }

    private function writeFakeRunner(string $name): void
    {
        $path = $this->fakeBinDir . '/' . $name;
        file_put_contents($path, <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$(basename "$0")" > "$RUNNER_CAPTURE_FILE"
printf '%s\n' "$*" > "$ARGS_CAPTURE_FILE"
cat > "$STDIN_CAPTURE_FILE"
if [[ "${FAKE_RUNNER_HANG:-0}" == "1" ]]; then
    # Симуляция зависания: одно событие, затем тишина. При большом stall-timeout
    # read блокируется, in-loop проверки soft/hard не исполняются.
    printf '{"type":"agent_start"}\n'
    sleep 60
    exit 0
fi
if [[ "${FAKE_RUNNER_HANG_ACTIVE:-0}" == "1" ]]; then
    # Симуляция «работает, но молчит»: одно событие, затем тишина в потоке, но
    # процесс грузит CPU (busy-loop) — process_is_active должен видеть active.
    printf '{"type":"agent_start"}\n'
    end=$((SECONDS + 120))
    while [ "$SECONDS" -lt "$end" ]; do :; done
    exit 0
fi
if [[ -n "${FAKE_RUNNER_STDERR:-}" ]]; then
    printf '%s\n' "$FAKE_RUNNER_STDERR" >&2
fi
if [[ -n "${FAKE_RUNNER_EVENTS:-}" ]]; then
    printf '%s' "$FAKE_RUNNER_EVENTS"
elif [[ "$(basename "$0")" == "codex" ]]; then
    if [[ "${FAKE_RUNNER_AGENT_END:-1}" == "1" ]]; then
        printf '{"type":"item.completed","item":{"type":"agent_message","text":"codex fake output"}}\n'
        printf '{"type":"turn.completed","usage":{"input_tokens":1,"output_tokens":1}}\n'
    else
        printf '{"type":"session"}\n'
    fi
elif [[ "${FAKE_RUNNER_AGENT_END:-1}" == "1" ]]; then
    printf '{"type":"agent_end"}\n'
else
    printf '{"type":"session"}\n'
fi
sleep 0.2
exit "${FAKE_RUNNER_EXIT_CODE:-0}"
BASH);
        chmod($path, 0755);
    }

    private function findLatestRunLog(string $logDir): ?string
    {
        if (!is_dir($logDir)) {
            return null;
        }

        $latest = null;
        $latestMtime = 0;
        foreach ((array) glob($logDir . '/*', GLOB_ONLYDIR) as $entry) {
            $runLog = $entry . '/run.log';
            if (is_file($runLog) && filemtime($runLog) > $latestMtime) {
                $latestMtime = filemtime($runLog);
                $latest = $runLog;
            }
        }

        return $latest;
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
