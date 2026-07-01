<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\AgentRunner\Pi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Pi\PiAgentRunnerService;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Pi\PiJsonlParser;

#[CoversClass(PiAgentRunnerService::class)]
final class PiAgentRunnerTest extends TestCase
{
    private PiAgentRunnerService $runner;

    /** @var list<string> */
    private array $fixtureFiles = [];

    protected function setUp(): void
    {
        $this->runner = new PiAgentRunnerService(new PiJsonlParser());
    }

    protected function tearDown(): void
    {
        foreach ($this->fixtureFiles as $fixtureFile) {
            @unlink($fixtureFile);
        }

        $this->fixtureFiles = [];
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
}
