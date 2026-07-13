<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\AgentRunner\Pi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\{
    ProcessLivenessClockComponent,
    ProcessLivenessProbeUnavailableComponent,
    ProcessLivenessSleeperComponent,
};
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\HttpsProxyBridge;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Pi\PiAgentRunnerService;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Pi\PiJsonlParser;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\ProcessLivenessWatcher;

#[CoversClass(PiAgentRunnerService::class)]
final class PiAgentRunnerTest extends TestCase
{
    private PiAgentRunnerService $runner;

    /** @var HttpsProxyBridge|null Мост для очистки в tearDown */
    private ?HttpsProxyBridge $bridgeToCleanup = null;

    /** @var list<string> */
    private array $fixtureFiles = [];

    protected function setUp(): void
    {
        // Сбрасываем CODEX_HTTP_PROXY на каждый тест — изоляция от окружения
        putenv('CODEX_HTTP_PROXY');
        $this->runner = new PiAgentRunnerService(new PiJsonlParser(), $this->createLivenessWatcher());
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

        // Очистка env-переменной
        putenv('CODEX_HTTP_PROXY');
    }

    // ──── getName / isAvailable ─────────────────────────────────────────

    #[Test]
    public function getNameReturnsPi(): void
    {
        self::assertSame('pi', $this->runner->getName());
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

        self::assertSame('pi', $command[0]);
        self::assertContains('--mode', $command);
        self::assertContains('json', $command);
        self::assertContains('-p', $command);
        self::assertContains('--no-session', $command);
    }

    #[Test]
    public function buildCommandAppendsUserPromptAtEnd(): void
    {
        $request = new AgentRunRequestVo(role: 'test', task: 'do something');
        $command = $this->runner->buildCommand($request);

        $last = end($command);
        self::assertStringContainsString('[Задача]: do something', $last);
    }

    #[Test]
    public function buildCommandIncludesPreviousContextInPrompt(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'do something',
            previousContext: 'previous output',
        );
        $command = $this->runner->buildCommand($request);

        $last = end($command);
        self::assertStringContainsString('previous output', $last);
        self::assertStringContainsString('[Задача]: do something', $last);
    }

    // ──── buildCommand: model ───────────────────────────────────────────

    #[Test]
    public function buildCommandAddsModelWhenProvided(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            model: 'claude-3.5-sonnet',
        );
        $command = $this->runner->buildCommand($request);

        self::assertContains('--model', $command);
        $idx = array_search('--model', $command, true);
        self::assertSame('claude-3.5-sonnet', $command[$idx + 1]);
    }

    // ──── buildCommand: tools ───────────────────────────────────────────

    #[Test]
    public function buildCommandAddsNoToolsWhenToolsEmptyString(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            tools: '',
        );
        $command = $this->runner->buildCommand($request);

        self::assertContains('--no-tools', $command);
    }

    #[Test]
    public function buildCommandAddsToolsWhenProvided(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            tools: 'read,write',
        );
        $command = $this->runner->buildCommand($request);

        self::assertContains('--tools', $command);
        $idx = array_search('--tools', $command, true);
        self::assertSame('read,write', $command[$idx + 1]);
    }

    // ──── buildCommand: system prompt ───────────────────────────────────

    #[Test]
    public function buildCommandAddsSystemPromptWhenProvided(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            systemPrompt: 'You are a system analyst.',
        );
        $command = $this->runner->buildCommand($request);

        self::assertContains('--system-prompt', $command);
        $idx = array_search('--system-prompt', $command, true);
        self::assertSame('You are a system analyst.', $command[$idx + 1]);
    }

    #[Test]
    public function buildCommandResolvesSystemPromptMarkerToPath(): void
    {
        // Маркер @system-prompt в command резолвится в путь из request.systemPrompt
        // (если это существующий файл). Раньше маркер оставался literal → pi игнорировал
        // role-prompt в static chains (TASK-fix-pi-static-chain-system-prompt).
        $tmpFile = (string) tempnam(sys_get_temp_dir(), 'pi_sys_');
        file_put_contents($tmpFile, 'role prompt');

        try {
            $command = $this->runner->buildCommand(new AgentRunRequestVo(
                role: 'test',
                task: 'task',
                command: ['pi', '--system-prompt', '@system-prompt'],
                systemPrompt: $tmpFile,
            ));

            self::assertContains('--system-prompt', $command);
            $idx = array_search('--system-prompt', $command, true);
            self::assertSame($tmpFile, $command[$idx + 1], '@system-prompt must be resolved to file path');
            self::assertNotContains('@system-prompt', $command, 'literal marker must not remain');
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function buildCommandResolvesAppendSystemPromptMarkerToPath(): void
    {
        $tmpFile = (string) tempnam(sys_get_temp_dir(), 'pi_append_');
        file_put_contents($tmpFile, 'append prompt');

        try {
            $command = $this->runner->buildCommand(new AgentRunRequestVo(
                role: 'test',
                task: 'task',
                command: ['pi', '--append-system-prompt', '@append-system-prompt'],
                runnerArgs: ['--append-system-prompt', $tmpFile],
            ));

            self::assertContains('--append-system-prompt', $command);
            $idx = array_search('--append-system-prompt', $command, true);
            self::assertSame($tmpFile, $command[$idx + 1], '@append-system-prompt must be resolved to file path');
            self::assertNotContains('@append-system-prompt', $command, 'literal marker must not remain');
        } finally {
            @unlink($tmpFile);
        }
    }

    // ──── buildCommand: noContextFiles ──────────────────────────────────

    #[Test]
    public function buildCommandAddsNcFlagWhenNoContextFilesIsTrue(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            noContextFiles: true,
        );
        $command = $this->runner->buildCommand($request);

        self::assertContains('-nc', $command);
    }

    #[Test]
    public function buildCommandDoesNotAddNcWhenNoContextFilesIsFalse(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            noContextFiles: false,
        );
        $command = $this->runner->buildCommand($request);

        self::assertNotContains('-nc', $command);
        self::assertNotContains('-no-context-files', $command);
    }

    #[Test]
    public function buildCommandDoesNotDuplicateNcFlag(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            command: ['pi', '-nc', '--mode', 'json'],
            noContextFiles: true,
        );
        $command = $this->runner->buildCommand($request);

        // -nc уже есть в command → не должен быть добавлен повторно
        $ncCount = count(array_filter($command, static fn(string $arg): bool => $arg === '-nc'));
        self::assertSame(1, $ncCount);
    }

    #[Test]
    public function buildCommandDoesNotDuplicateNoContextFilesLongFlag(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            command: ['pi', '-no-context-files', '--mode', 'json'],
            noContextFiles: true,
        );
        $command = $this->runner->buildCommand($request);

        // -no-context-files уже есть → -nc не должен быть добавлен
        self::assertNotContains('-nc', $command);
        self::assertContains('-no-context-files', $command);
    }

    // ──── buildCommand: custom command ──────────────────────────────────

    #[Test]
    public function buildCommandUsesCustomCommandWhenProvided(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            command: ['pi', '--mode', 'json', '-p', '--no-session', '--model', 'gpt-4'],
        );
        $command = $this->runner->buildCommand($request);

        self::assertSame('pi', $command[0]);
        self::assertContains('--model', $command);
    }

    #[Test]
    public function buildCommandRejectsNonPiCommand(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            command: ['codex', 'run'],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AgentRunRequestVo::$command must be either empty');

        $this->runner->buildCommand($request);
    }

    // ──── buildCommand: runner args ─────────────────────────────────────

    #[Test]
    public function buildCommandAppendsRunnerArgs(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            runnerArgs: ['--append-system-prompt', '/tmp/prompt.md'],
        );
        $command = $this->runner->buildCommand($request);

        self::assertContains('--append-system-prompt', $command);
        self::assertContains('/tmp/prompt.md', $command);
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
            'CODEX_HTTP_PROXY' => 'https://user:pass@example.com:8080',
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
        putenv('CODEX_HTTP_PROXY=https://user:pass@example.com:8080');

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

    // ──── AgentRunRequestVo compatibility ───────────────────────────────

    #[Test]
    public function requestAcceptsSystemPromptAndContext(): void
    {
        $request = new AgentRunRequestVo(
            role: 'system_analyst',
            task: 'Analyze the code',
            systemPrompt: 'You are a system analyst.',
            previousContext: 'Previous step output',
        );

        self::assertSame('You are a system analyst.', $request->getSystemPrompt());
        self::assertSame('Previous step output', $request->getPreviousContext());
    }

    #[Test]
    public function requestAcceptsNoContextFiles(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            noContextFiles: true,
        );

        self::assertTrue($request->hasNoContextFiles());
    }

    #[Test]
    public function requestDefaultsNoContextFilesToFalse(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
        );

        self::assertFalse($request->hasNoContextFiles());
    }

    #[Test]
    public function runStreamsChunkedJsonlAndFlushesLastLineWithoutNewline(): void
    {
        $command = $this->createExecutableFixture('pi_stream_', <<<'PHP'
fwrite(STDOUT, "{\"type\":\"message_end\",\"message\":{\"usage\":{\"input\":11,\"output\":7,\"turns\":1,\"cache\":{\"read\":3,\"write\":2},\"cost\":{\"total\":0.5}},\"model\":\"pi-test\"}}\r\n");
fflush(STDOUT);
usleep(10000);
fwrite(STDOUT, "{\"type\":\"agent_end\",\"messages\":[{\"role\":\"assistant\",\"content\":[{\"type\":\"text\",\"text\":\"Chunked ");
fflush(STDOUT);
usleep(10000);
fwrite(STDOUT, "OK\"}]}]}");
PHP);

        $result = $this->runner->run(new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            command: [$command],
        ));

        self::assertFalse($result->isError());
        self::assertSame('Chunked OK', $result->getOutputText());
        self::assertSame(11, $result->getInputTokens());
        self::assertSame(7, $result->getOutputTokens());
        self::assertSame(3, $result->getCacheReadTokens());
        self::assertSame(2, $result->getCacheWriteTokens());
        self::assertSame(0.5, $result->getCost());
        self::assertSame('pi-test', $result->getModel());
        self::assertSame(1, $result->getTurns());
    }

    #[Test]
    public function runHandlesEmptyOutputWithoutCrash(): void
    {
        $command = $this->createExecutableFixture('pi_empty_', <<<'PHP'
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
    public function runReturnsErrorWhenProcessExitsBeforeAgentEnd(): void
    {
        $command = $this->createExecutableFixture('pi_broken_', <<<'PHP'
fwrite(STDOUT, "{\"type\":\"message_update\",\"assistantMessageEvent\":{\"type\":\"text_delta\",\"delta\":\"partial\"}}\n");
fwrite(STDERR, "pipe closed");
exit(7);
PHP);

        $result = $this->runner->run(new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            command: [$command],
        ));

        self::assertTrue($result->isError());
        self::assertSame(7, $result->getExitCode());
        self::assertSame('pipe closed', $result->getErrorMessage());
    }

    // ──── run: ошибка модели (exit 0 + stopReason:error в JSONL) ─────────

    #[Test]
    public function runReturnsErrorWhenModelReportsErrorInJsonl(): void
    {
        // Инцидент-сценарий: pi выходит с exit 0, но сообщает об ошибке модели
        // внутри JSONL (stopReason:"error" + errorMessage) — реальная фиксстура
        // из var/sessions/brainstorm/2026-07-01_03-35-24/ (step 010).
        $command = $this->createExecutableFixture('pi_model_error_', <<<'PHP'
fwrite(STDOUT, "{\"type\":\"message_end\",\"message\":{\"role\":\"assistant\",\"content\":[],\"usage\":{\"input\":0,\"output\":0,\"cost\":{\"total\":0}},\"stopReason\":\"error\",\"errorMessage\":\"No API key for provider: openai-codex\"}}\n");
fwrite(STDOUT, "{\"type\":\"turn_end\",\"message\":{\"stopReason\":\"error\",\"errorMessage\":\"No API key for provider: openai-codex\"},\"toolResults\":[]}\n");
fwrite(STDOUT, "{\"type\":\"agent_end\",\"messages\":[{\"role\":\"assistant\",\"content\":[],\"stopReason\":\"error\",\"errorMessage\":\"No API key for provider: openai-codex\"}],\"willRetry\":false}\n");
exit(0);
PHP);

        $result = $this->runner->run(new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            command: [$command],
        ));

        self::assertTrue($result->isError());
        self::assertSame('No API key for provider: openai-codex', $result->getErrorMessage());
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
