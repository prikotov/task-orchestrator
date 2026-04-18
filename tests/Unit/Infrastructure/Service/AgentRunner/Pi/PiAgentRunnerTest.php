<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\AgentRunner\Pi;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Pi\PiAgentRunner;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Pi\PiJsonlParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PiAgentRunner::class)]
final class PiAgentRunnerTest extends TestCase
{
    private PiAgentRunner $runner;

    protected function setUp(): void
    {
        $this->runner = new PiAgentRunner(new PiJsonlParser());
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

        self::assertTrue($request->getNoContextFiles());
    }

    #[Test]
    public function requestDefaultsNoContextFilesToFalse(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
        );

        self::assertFalse($request->getNoContextFiles());
    }
}
