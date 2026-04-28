<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\AgentRunner\Codex;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\CodexAgentRunner;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\CodexJsonlParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CodexAgentRunner::class)]
final class CodexAgentRunnerTest extends TestCase
{
    private CodexAgentRunner $runner;

    protected function setUp(): void
    {
        $this->runner = new CodexAgentRunner(new CodexJsonlParser());
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

    // ──── buildCommand: @append-system-prompt in command ────────────────

    #[Test]
    public function buildCommandResolvesAppendSlotInCommand(): void
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

            // Маркер заменён на содержимое файла
            $cIdx = array_search('-c', $command, true);
            self::assertNotFalse($cIdx);
            $cValue = $command[$cIdx + 1];
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
    public function buildCommandLeavesMarkerWhenNoRunnerArgs(): void
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

        // Нет runnerArgs → маркер остаётся как есть
        self::assertContains('developer_instructions="@append-system-prompt"', $command);
    }

    #[Test]
    public function buildCommandEscapesTomlInResolvedSlot(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'codex_append_');
        file_put_contents($tmpFile, 'He said "hello" and left');

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

            $cIdx = array_search('-c', $command, true);
            self::assertNotFalse($cIdx);
            $cValue = $command[$cIdx + 1];
            // Quotes должны быть экранированы для TOML
            self::assertStringContainsString('\\"hello\\"', $cValue);
        } finally {
            @unlink($tmpFile);
        }
    }

    // ──── buildCommand: systemPrompt ignored for codex ──────────────────

    #[Test]
    public function buildCommandIgnoresSystemPrompt(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            systemPrompt: 'You are a system architect.',
        );
        $command = $this->runner->buildCommand($request);

        // systemPrompt не передаётся codex
        self::assertNotContains('-c', $command);
        foreach ($command as $arg) {
            self::assertStringNotContainsString('You are a system architect.', $arg);
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
}
