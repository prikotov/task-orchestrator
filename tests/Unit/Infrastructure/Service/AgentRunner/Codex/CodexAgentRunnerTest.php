<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\AgentRunner\Codex;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\{
    ProcessLivenessClockComponent,
    ProcessLivenessProbeUnavailableComponent,
    ProcessLivenessSleeperComponent,
};
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\CodexAgentRunnerService;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\CodexJsonlParser;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\ProcessLivenessWatcher;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\HttpsProxyBridge;

#[CoversClass(CodexAgentRunnerService::class)]
final class CodexAgentRunnerTest extends TestCase
{
    private CodexAgentRunnerService $runner;

    /** @var HttpsProxyBridge|null Мост для очистки в tearDown */
    private ?HttpsProxyBridge $bridgeToCleanup = null;

    /** @var list<string> */
    private array $fixtureFiles = [];

    protected function setUp(): void
    {
        putenv('CODEX_HTTP_PROXY');
        $this->runner = new CodexAgentRunnerService(new CodexJsonlParser(), $this->createLivenessWatcher());
    }

    protected function tearDown(): void
    {
        // Очистка моста если тест его создал
        $this->bridgeToCleanup?->stop();
        $this->bridgeToCleanup = null;

        foreach ($this->fixtureFiles as $fixtureFile) {
            @unlink($fixtureFile);
        }

        $this->fixtureFiles = [];

        // Очистка env-переменных
        putenv('CODEX_HTTP_PROXY');
        putenv('AGENT_RUNNER_IDLE_TIMEOUT_SEC');
    }

    // ──── getName / isAvailable ─────────────────────────────────────────

    #[Test]
    public function getNameReturnsCodex(): void
    {
        self::assertSame('codex', $this->runner->getName());
    }

    #[Test]
    public function isAvailableReturnsBool(): void
    {
        self::assertIsBool($this->runner->isAvailable());
    }

    // ──── buildCommand: default command ─────────────────────────────────

    #[Test]
    public function buildCommandUsesDefaultWhenCommandIsEmpty(): void
    {
        $request = new AgentRunRequestVo(role: 'test', task: 'do something');
        $command = $this->runner->buildCommand($request);

        self::assertSame('codex', $command[0]);
        self::assertContains('exec', $command);
        self::assertContains('--full-auto', $command);
        self::assertContains('--json', $command);
        self::assertContains('--sandbox', $command);
        self::assertContains('danger-full-access', $command);
    }

    #[Test]
    public function buildCommandAppendsUserPromptAtEnd(): void
    {
        $request = new AgentRunRequestVo(role: 'test', task: 'design the architecture');
        $command = $this->runner->buildCommand($request);

        $last = end($command);
        self::assertStringContainsString('[Задача]: design the architecture', $last);
    }

    #[Test]
    public function buildCommandIncludesPreviousContextInUserPrompt(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'continue work',
            previousContext: 'Previous step output here',
        );
        $command = $this->runner->buildCommand($request);

        $last = end($command);
        self::assertStringContainsString('Previous step output here', $last);
        self::assertStringContainsString('[Задача]: continue work', $last);
    }

    // ──── buildCommand: model ───────────────────────────────────────────

    #[Test]
    public function buildCommandAddsModelWhenProvided(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            model: 'gpt-5.5',
        );
        $command = $this->runner->buildCommand($request);

        self::assertContains('--model', $command);
        $idx = array_search('--model', $command, true);
        self::assertSame('gpt-5.5', $command[$idx + 1]);
    }

    #[Test]
    public function buildCommandDoesNotAddModelWhenNotProvided(): void
    {
        $request = new AgentRunRequestVo(role: 'test', task: 'task');
        $command = $this->runner->buildCommand($request);

        self::assertNotContains('--model', $command);
        self::assertNotContains('-m', $command);
    }

    // ──── buildCommand: custom command ──────────────────────────────────

    #[Test]
    public function buildCommandUsesCustomCommandWhenProvided(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            command: [
                'codex', 'exec', '--full-auto', '--json',
                '--sandbox', 'danger-full-access',
                '--model', 'gpt-5.5',
                '-c', 'model_reasoning_effort="xhigh"',
                '--skip-git-repo-check',
                '--ephemeral',
            ],
        );
        $command = $this->runner->buildCommand($request);

        self::assertSame('codex', $command[0]);
        self::assertContains('--skip-git-repo-check', $command);
        self::assertContains('--ephemeral', $command);
        self::assertContains('--model', $command);
    }

    #[Test]
    public function buildCommandRejectsNonCodexCommand(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            command: ['pi', '--mode', 'json'],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AgentRunRequestVo::$command must be either empty');

        $this->runner->buildCommand($request);
    }

    // ──── buildCommand: runner args ─────────────────────────────────────

    #[Test]
    public function buildCommandAppendsFilteredRunnerArgs(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            runnerArgs: ['--some-flag', '--other-flag'],
        );
        $command = $this->runner->buildCommand($request);

        self::assertContains('--some-flag', $command);
        self::assertContains('--other-flag', $command);
    }

    // ──── @system-prompt: resolved as file path ─────────────────────────

    #[Test]
    public function buildCommandResolvesSystemPromptAsPath(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'codex_sys_');
        file_put_contents($tmpFile, 'You are Gandalf the architect.');

        try {
            $request = new AgentRunRequestVo(
                role: 'test',
                task: 'task',
                systemPrompt: $tmpFile,
                command: [
                    'codex', 'exec', '--json', '--full-auto',
                    '-c', 'model_instructions_file="@system-prompt"',
                ],
            );
            $command = $this->runner->buildCommand($request);

            // @system-prompt заменён на путь (codex читает файл сам)
            $cIdx = array_search('-c', $command, true);
            self::assertNotFalse($cIdx);
            self::assertSame(
                sprintf('model_instructions_file="%s"', $tmpFile),
                $command[$cIdx + 1],
            );
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function buildCommandLeavesSystemPromptMarkerWhenNoFile(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            systemPrompt: 'plain text, not a file',
            command: [
                'codex', 'exec', '--json', '--full-auto',
                '-c', 'model_instructions_file="@system-prompt"',
            ],
        );
        $command = $this->runner->buildCommand($request);

        // Не файл — маркер остаётся
        self::assertContains('model_instructions_file="@system-prompt"', $command);
    }

    // ──── @append-system-prompt: resolved as file content ───────────────

    #[Test]
    public function buildCommandResolvesAppendSlotAsContent(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'codex_append_');
        file_put_contents($tmpFile, 'Challenge all assumptions.');

        try {
            $request = new AgentRunRequestVo(
                role: 'test',
                task: 'task',
                command: [
                    'codex', 'exec', '--json', '--full-auto',
                    '-c', 'developer_instructions="@append-system-prompt"',
                ],
                runnerArgs: ['--append-system-prompt', $tmpFile],
            );
            $command = $this->runner->buildCommand($request);

            // @append-system-prompt заменён на содержимое файла
            $cIndices = array_keys(array_filter($command, static fn($v) => $v === '-c'));
            $devInstrIdx = end($cIndices);
            $cValue = $command[$devInstrIdx + 1];
            self::assertStringContainsString('Challenge all assumptions.', $cValue);
            self::assertStringNotContainsString('@append-system-prompt', $cValue);

            // --append-system-prompt убран из runnerArgs
            self::assertNotContains('--append-system-prompt', $command);
            self::assertNotContains($tmpFile, $command);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function buildCommandLeavesAppendMarkerWhenNoRunnerArgs(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            command: [
                'codex', 'exec', '--json', '--full-auto',
                '-c', 'developer_instructions="@append-system-prompt"',
            ],
        );
        $command = $this->runner->buildCommand($request);

        // Нет runnerArgs → маркер остаётся
        self::assertContains('developer_instructions="@append-system-prompt"', $command);
    }

    #[Test]
    public function buildCommandEscapesTomlInAppendContent(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'codex_append_');
        file_put_contents($tmpFile, 'He said "hello"');

        try {
            $request = new AgentRunRequestVo(
                role: 'test',
                task: 'task',
                command: [
                    'codex', 'exec', '--json', '--full-auto',
                    '-c', 'developer_instructions="@append-system-prompt"',
                ],
                runnerArgs: ['--append-system-prompt', $tmpFile],
            );
            $command = $this->runner->buildCommand($request);

            $cIndices = array_keys(array_filter($command, static fn($v) => $v === '-c'));
            $devInstrIdx = end($cIndices);
            $cValue = $command[$devInstrIdx + 1];
            self::assertStringContainsString('\\"hello\\"', $cValue);
        } finally {
            @unlink($tmpFile);
        }
    }

    // ──── Both markers together ─────────────────────────────────────────

    #[Test]
    public function buildCommandResolvesBothMarkersTogether(): void
    {
        $sysFile = tempnam(sys_get_temp_dir(), 'codex_sys_');
        $appendFile = tempnam(sys_get_temp_dir(), 'codex_append_');
        file_put_contents($sysFile, 'You are Loki.');
        file_put_contents($appendFile, 'Challenge assumptions.');

        try {
            $request = new AgentRunRequestVo(
                role: 'test',
                task: 'task',
                systemPrompt: $sysFile,
                command: [
                    'codex', 'exec', '--json', '--full-auto',
                    '-c', 'model_instructions_file="@system-prompt"',
                    '-c', 'developer_instructions="@append-system-prompt"',
                ],
                runnerArgs: ['--append-system-prompt', $appendFile],
            );
            $command = $this->runner->buildCommand($request);

            // model_instructions_file содержит путь
            $cIndices = array_keys(array_filter($command, static fn($v) => $v === '-c'));
            $sysIdx = $cIndices[0];
            self::assertSame(
                sprintf('model_instructions_file="%s"', $sysFile),
                $command[$sysIdx + 1],
            );

            // developer_instructions содержит текст
            $appendIdx = $cIndices[1];
            self::assertStringContainsString('Challenge assumptions.', $command[$appendIdx + 1]);
        } finally {
            @unlink($sysFile);
            @unlink($appendFile);
        }
    }

    // ──── buildCommand: tools ignored ───────────────────────────────────

    #[Test]
    public function buildCommandIgnoresToolsParameter(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            tools: '',
        );
        $command = $this->runner->buildCommand($request);

        self::assertNotContains('--no-tools', $command);
        self::assertNotContains('--tools', $command);
    }

    // ──── buildProcessEnv: proxy scenarios ───────────────────────────────

    #[Test]
    public function buildProcessEnvWithCodexProxySetsHttpsProxy(): void
    {
        $env = $this->runner->buildProcessEnv([
            'PATH' => '/usr/bin',
            'CODEX_HTTP_PROXY' => 'http://proxy.example.com:8080',
        ]);

        self::assertSame('http://proxy.example.com:8080', $env['HTTPS_PROXY']);
        self::assertSame('/usr/bin', $env['PATH']);
    }

    #[Test]
    public function buildProcessEnvWithoutCodexProxyReturnsEnvUnchanged(): void
    {
        $env = $this->runner->buildProcessEnv([
            'PATH' => '/usr/bin',
            'HOME' => '/home/user',
        ]);

        self::assertArrayNotHasKey('HTTPS_PROXY', $env);
        self::assertSame('/usr/bin', $env['PATH']);
        self::assertSame('/home/user', $env['HOME']);
    }

    #[Test]
    public function buildProcessEnvCodexProxyOverridesExistingHttpsProxy(): void
    {
        $env = $this->runner->buildProcessEnv([
            'PATH' => '/usr/bin',
            'HTTPS_PROXY' => 'http://old-proxy:3128',
            'CODEX_HTTP_PROXY' => 'http://new-proxy:8080',
        ]);

        self::assertSame('http://new-proxy:8080', $env['HTTPS_PROXY']);
    }

    #[Test]
    public function buildProcessEnvEmptyCodexProxyDoesNotOverride(): void
    {
        $env = $this->runner->buildProcessEnv([
            'HTTPS_PROXY' => 'http://existing-proxy:3128',
            'CODEX_HTTP_PROXY' => '',
        ]);

        // Пустой CODEX_HTTP_PROXY не подменяет HTTPS_PROXY
        self::assertSame('http://existing-proxy:3128', $env['HTTPS_PROXY']);
    }

    #[Test]
    public function buildProcessEnvPreservesHttpProxy(): void
    {
        $env = $this->runner->buildProcessEnv([
            'HTTP_PROXY' => 'http://http-proxy:3128',
            'CODEX_HTTP_PROXY' => 'http://codex-proxy:8080',
        ]);

        // HTTP_PROXY не затрагивается — передаётся как есть
        self::assertSame('http://http-proxy:3128', $env['HTTP_PROXY']);
        self::assertSame('http://codex-proxy:8080', $env['HTTPS_PROXY']);
    }

    #[Test]
    public function buildProcessEnvEmptyArrayReturnsEmpty(): void
    {
        $env = $this->runner->buildProcessEnv([]);

        self::assertArrayNotHasKey('HTTPS_PROXY', $env);
        self::assertSame([], $env);
    }

    // ──── buildProcessEnv: HTTPS-прокси не подменяет HTTPS_PROXY напрямую ─

    #[Test]
    public function buildProcessEnvDoesNotOverrideForHttpsProxy(): void
    {
        $env = $this->runner->buildProcessEnv([
            'HTTPS_PROXY' => 'http://existing-proxy:3128',
            'CODEX_HTTP_PROXY' => 'https://user:pass@proxy.example.com:8080',
        ]);

        // HTTPS-прокси: мост подменит HTTPS_PROXY в run(), здесь не трогаем
        self::assertSame('http://existing-proxy:3128', $env['HTTPS_PROXY']);
    }

    #[Test]
    public function buildProcessEnvStillOverridesForHttpProxy(): void
    {
        $env = $this->runner->buildProcessEnv([
            'HTTPS_PROXY' => 'http://old-proxy:3128',
            'CODEX_HTTP_PROXY' => 'http://new-proxy:8080',
        ]);

        // HTTP-прокси: подменяем как раньше
        self::assertSame('http://new-proxy:8080', $env['HTTPS_PROXY']);
    }

    // ──── createBridgeIfNeeded: HTTPS-прокси активирует мост ────────────

    #[Test]
    public function createBridgeIfNeededReturnsBridgeForHttpsProxy(): void
    {
        putenv('CODEX_HTTP_PROXY=https://user:pass@proxy.example.com:8080');

        $bridge = $this->runner->createBridgeIfNeeded();

        self::assertInstanceOf(HttpsProxyBridge::class, $bridge);
        self::assertTrue($bridge->isRunning());
        self::assertMatchesRegularExpression('#^http://127\.0\.0\.1:\d+$#', $bridge->getLocalProxyUrl());

        // Сохраняем для очистки в tearDown
        $this->bridgeToCleanup = $bridge;
    }

    #[Test]
    public function createBridgeIfNeededReturnsNullForHttpProxy(): void
    {
        putenv('CODEX_HTTP_PROXY=http://proxy.example.com:8080');

        $bridge = $this->runner->createBridgeIfNeeded();

        self::assertNull($bridge);
    }

    #[Test]
    public function createBridgeIfNeededReturnsNullWhenEnvNotSet(): void
    {
        // CODEX_HTTP_PROXY не установлен (tearDown очистит если был)
        $bridge = $this->runner->createBridgeIfNeeded();

        self::assertNull($bridge);
    }

    #[Test]
    public function runStreamsChunkedJsonlAndFlushesLastLineWithoutNewline(): void
    {
        $command = $this->createExecutableFixture('codex_stream_', <<<'PHP'
fwrite(STDOUT, "{\"type\":\"item.completed\",\"item\":{\"id\":\"item_0\",\"type\":\"agent_message\",\"text\":\"Chunked ");
fflush(STDOUT);
usleep(10000);
fwrite(STDOUT, "OK\"}}\r\n");
fflush(STDOUT);
usleep(10000);
fwrite(STDOUT, "{\"type\":\"turn.completed\",\"usage\":{\"input_tokens\":13,\"cached_input_tokens\":5,\"output_tokens\":8,\"reasoning_output_tokens\":2,\"cost\":0.75}}");
PHP);

        $result = $this->runner->run(new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            command: [$command],
        ));

        self::assertFalse($result->isError());
        self::assertSame('Chunked OK', $result->getOutputText());
        self::assertSame(13, $result->getInputTokens());
        self::assertSame(8, $result->getOutputTokens());
        self::assertSame(5, $result->getCacheReadTokens());
        self::assertSame(0, $result->getCacheWriteTokens());
        self::assertSame(0.75, $result->getCost());
        self::assertSame(1, $result->getTurns());
    }

    #[Test]
    public function runHandlesEmptyOutputWithoutCrash(): void
    {
        $command = $this->createExecutableFixture('codex_empty_', <<<'PHP'
exit(0);
PHP);

        $result = $this->runner->run(new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            command: [$command],
        ));

        self::assertFalse($result->isError());
        self::assertSame('', $result->getOutputText());
        self::assertSame(0, $result->getInputTokens());
    }

    #[Test]
    public function runReturnsErrorWhenProcessExitsBeforeTurnCompleted(): void
    {
        $command = $this->createExecutableFixture('codex_broken_', <<<'PHP'
fwrite(STDOUT, "{\"type\":\"item.completed\",\"item\":{\"id\":\"item_0\",\"type\":\"agent_message\",\"text\":\"partial\"}}\n");
fwrite(STDERR, "pipe closed");
exit(9);
PHP);

        $result = $this->runner->run(new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            command: [$command],
        ));

        self::assertTrue($result->isError());
        self::assertSame(9, $result->getExitCode());
        self::assertSame('pipe closed', $result->getErrorMessage());
    }

    private function createExecutableFixture(string $prefix, string $script): string
    {
        $fixtureFile = tempnam(sys_get_temp_dir(), $prefix);
        if ($fixtureFile === false) {
            self::fail('Unable to create temporary runner fixture.');
        }

        file_put_contents($fixtureFile, "#!/usr/bin/env php\n<?php\n" . $script);
        chmod($fixtureFile, 0700);
        $this->fixtureFiles[] = $fixtureFile;

        return $fixtureFile;
    }

    private function createLivenessWatcher(): ProcessLivenessWatcher
    {
        return new ProcessLivenessWatcher(
            probe: new ProcessLivenessProbeUnavailableComponent(),
            clock: new ProcessLivenessClockComponent(),
            sleeper: new ProcessLivenessSleeperComponent(),
        );
    }
}
