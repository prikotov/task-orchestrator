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
    public function buildCommandAppendsPromptAtEnd(): void
    {
        $request = new AgentRunRequestVo(role: 'test', task: 'design the architecture');
        $command = $this->runner->buildCommand($request);

        $last = end($command);
        self::assertStringContainsString('[Задача]: design the architecture', $last);
    }

    #[Test]
    public function buildCommandIncludesSystemPromptInMergedPrompt(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'do something',
            systemPrompt: 'You are Gandalf the architect.',
        );
        $command = $this->runner->buildCommand($request);

        $last = end($command);
        self::assertStringContainsString('You are Gandalf the architect.', $last);
        self::assertStringContainsString('[Задача]: do something', $last);
    }

    #[Test]
    public function buildCommandIncludesPreviousContextInMergedPrompt(): void
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
            model: 'o3',
        );
        $command = $this->runner->buildCommand($request);

        self::assertContains('-m', $command);
        $idx = array_search('-m', $command, true);
        self::assertSame('o3', $command[$idx + 1]);
    }

    #[Test]
    public function buildCommandDoesNotAddModelWhenNotProvided(): void
    {
        $request = new AgentRunRequestVo(role: 'test', task: 'task');
        $command = $this->runner->buildCommand($request);

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
                '-m', 'o3',
                '--skip-git-repo-check',
                '--ephemeral',
            ],
        );
        $command = $this->runner->buildCommand($request);

        self::assertSame('codex', $command[0]);
        self::assertContains('--skip-git-repo-check', $command);
        self::assertContains('--ephemeral', $command);
        self::assertContains('-m', $command);
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
    public function buildCommandAppendsRunnerArgs(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            runnerArgs: ['--ignore-user-config', '--ignore-rules'],
        );
        $command = $this->runner->buildCommand($request);

        self::assertContains('--ignore-user-config', $command);
        self::assertContains('--ignore-rules', $command);
    }

    // ──── buildCommand: @file resolution ────────────────────────────────

    #[Test]
    public function buildCommandResolvesAtFileArgs(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'codex_test_');
        file_put_contents($tmpFile, 'File content here');

        try {
            $request = new AgentRunRequestVo(
                role: 'test',
                task: 'task',
                command: ['codex', 'exec', '--json', '--full-auto', '@' . $tmpFile],
            );
            $command = $this->runner->buildCommand($request);

            // @file в command резолвится в содержимое и попадает в склеенный промпт (последний элемент)
            $last = end($command);
            self::assertStringContainsString('File content here', $last);
            self::assertNotContains('@' . $tmpFile, $command);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function buildCommandKeepsAtFileWhenFileNotFound(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            command: ['codex', 'exec', '--json', '--full-auto'],
            runnerArgs: ['@/nonexistent/file/path.md'],
        );
        $command = $this->runner->buildCommand($request);

        self::assertContains('@/nonexistent/file/path.md', $command);
    }

    // ──── buildCommand: merged prompt order ─────────────────────────────

    #[Test]
    public function buildCommandMergesSystemPromptContextAndTaskInCorrectOrder(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'analyze this',
            systemPrompt: 'You are Loki.',
            previousContext: 'Previous analysis',
        );
        $command = $this->runner->buildCommand($request);

        $last = end($command);
        $lokiPos = strpos($last, 'You are Loki.');
        $contextPos = strpos($last, 'Previous analysis');
        $taskPos = strpos($last, '[Задача]: analyze this');

        self::assertNotFalse($lokiPos);
        self::assertNotFalse($contextPos);
        self::assertNotFalse($taskPos);
        // system prompt comes first, then context, then task
        self::assertLessThan($contextPos, $lokiPos);
        self::assertLessThan($taskPos, $contextPos);
    }

    // ──── buildCommand: prompt without system ───────────────────────────

    #[Test]
    public function buildCommandOnlyContainsTaskWhenNoSystemPromptOrContext(): void
    {
        $request = new AgentRunRequestVo(role: 'test', task: 'simple task');
        $command = $this->runner->buildCommand($request);

        $last = end($command);
        self::assertStringContainsString('[Задача]: simple task', $last);
        self::assertStringNotContainsString('You are', $last);
    }

    // ──── buildCommand: no tools flag (Codex doesn't support it) ────────

    #[Test]
    public function buildCommandIgnoresToolsParameter(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            tools: '',
        );
        $command = $this->runner->buildCommand($request);

        // Codex не поддерживает --tools / --no-tools
        self::assertNotContains('--no-tools', $command);
        self::assertNotContains('--tools', $command);
    }

    // ──── buildCommand: @system-prompt / @append-system-prompt markers ────

    #[Test]
    public function buildCommandRemovesSystemPromptMarkerFromCommand(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            command: ['codex', 'exec', '--json', '--full-auto', '@system-prompt'],
        );
        $command = $this->runner->buildCommand($request);

        self::assertNotContains('@system-prompt', $command);
        self::assertNotContains('--system-prompt', $command);
    }

    #[Test]
    public function buildCommandRemovesAppendSystemPromptMarkerFromCommand(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            command: ['codex', 'exec', '--json', '--full-auto', '@system-prompt', '@append-system-prompt'],
        );
        $command = $this->runner->buildCommand($request);

        self::assertNotContains('@system-prompt', $command);
        self::assertNotContains('@append-system-prompt', $command);
    }

    // ──── buildCommand: --append-system-prompt in runnerArgs ──────────────

    #[Test]
    public function buildCommandExtractsAppendSystemPromptFromRunnerArgs(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'codex_append_');
        file_put_contents($tmpFile, 'Append instructions here');

        try {
            $request = new AgentRunRequestVo(
                role: 'test',
                task: 'task',
                command: ['codex', 'exec', '--json', '--full-auto'],
                runnerArgs: ['--append-system-prompt', $tmpFile],
            );
            $command = $this->runner->buildCommand($request);

            // --append-system-prompt и путь убраны из command
            self::assertNotContains('--append-system-prompt', $command);
            self::assertNotContains($tmpFile, $command);

            // Содержимое файла — в склеенном промпте (последний элемент)
            $last = end($command);
            self::assertStringContainsString('Append instructions here', $last);
        } finally {
            @unlink($tmpFile);
        }
    }

    // ──── buildCommand: systemPrompt as file path ────────────────────────

    #[Test]
    public function buildCommandReadsSystemPromptFileContent(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'codex_system_');
        file_put_contents($tmpFile, 'You are a system architect.');

        try {
            $request = new AgentRunRequestVo(
                role: 'test',
                task: 'task',
                systemPrompt: $tmpFile,
            );
            $command = $this->runner->buildCommand($request);

            $last = end($command);
            self::assertStringContainsString('You are a system architect.', $last);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function buildCommandUsesSystemPromptAsTextWhenNotAFile(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            systemPrompt: 'Direct text prompt',
        );
        $command = $this->runner->buildCommand($request);

        $last = end($command);
        self::assertStringContainsString('Direct text prompt', $last);
    }
}
