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
    public function buildCommandDoesNotIncludeSystemPromptInUserPrompt(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'do something',
            systemPrompt: 'You are Gandalf the architect.',
        );
        $command = $this->runner->buildCommand($request);

        // systemPrompt идёт в developer_instructions, не в user prompt
        $last = end($command);
        self::assertStringNotContainsString('You are Gandalf the architect.', $last);
    }

    #[Test]
    public function buildCommandPassesSystemPromptViaDeveloperInstructions(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'do something',
            systemPrompt: 'You are Gandalf the architect.',
        );
        $command = $this->runner->buildCommand($request);

        // developer_instructions через -c
        $cIdx = array_search('-c', $command, true);
        self::assertNotFalse($cIdx);
        $cValue = $command[$cIdx + 1];
        self::assertStringStartsWith('developer_instructions=', $cValue);
        self::assertStringContainsString('You are Gandalf the architect.', $cValue);
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

    // ──── buildCommand: @file resolution ────────────────────────────────

    #[Test]
    public function buildCommandResolvesAtFileArgsInCommand(): void
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

            // @file резолвится в содержимое и заменяет исходный элемент в command
            self::assertContains('File content here', $command);
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
    public function buildCommandRemovesBothPromptMarkersFromCommand(): void
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

            // Содержимое — в developer_instructions
            $cIdx = array_search('-c', $command, true);
            self::assertNotFalse($cIdx);
            self::assertStringContainsString('Append instructions here', $command[$cIdx + 1]);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function buildCommandCombinesSystemAndAppendInDeveloperInstructions(): void
    {
        $sysFile = tempnam(sys_get_temp_dir(), 'codex_sys_');
        $appendFile = tempnam(sys_get_temp_dir(), 'codex_append_');
        file_put_contents($sysFile, 'You are Loki.');
        file_put_contents($appendFile, 'Challenge all assumptions.');

        try {
            $request = new AgentRunRequestVo(
                role: 'test',
                task: 'task',
                systemPrompt: $sysFile,
                command: ['codex', 'exec', '--json', '--full-auto'],
                runnerArgs: ['--append-system-prompt', $appendFile],
            );
            $command = $this->runner->buildCommand($request);

            $cIdx = array_search('-c', $command, true);
            self::assertNotFalse($cIdx);
            $devInstructions = $command[$cIdx + 1];
            self::assertStringContainsString('You are Loki.', $devInstructions);
            self::assertStringContainsString('Challenge all assumptions.', $devInstructions);
        } finally {
            @unlink($sysFile);
            @unlink($appendFile);
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

            $cIdx = array_search('-c', $command, true);
            self::assertNotFalse($cIdx);
            self::assertStringContainsString('You are a system architect.', $command[$cIdx + 1]);
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

        $cIdx = array_search('-c', $command, true);
        self::assertNotFalse($cIdx);
        self::assertStringContainsString('Direct text prompt', $command[$cIdx + 1]);
    }

    // ──── buildCommand: TOML escaping ────────────────────────────────────

    #[Test]
    public function buildCommandEscapesTomlInDeveloperInstructions(): void
    {
        $request = new AgentRunRequestVo(
            role: 'test',
            task: 'task',
            systemPrompt: 'He said "hello" and left\\nnew line',
        );
        $command = $this->runner->buildCommand($request);

        $cIdx = array_search('-c', $command, true);
        self::assertNotFalse($cIdx);
        $value = $command[$cIdx + 1];
        // Quotes should be escaped
        self::assertStringContainsString('\\"hello\\"', $value);
        // Backslash before 'n' should be escaped
        self::assertStringContainsString('\\\\n', $value);
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

    // ──── buildCommand: no system prompt → no -c ────────────────────────

    #[Test]
    public function buildCommandOmitsDeveloperInstructionsWhenNoSystemPrompt(): void
    {
        $request = new AgentRunRequestVo(role: 'test', task: 'simple task');
        $command = $this->runner->buildCommand($request);

        self::assertNotContains('-c', $command);
        // Только user prompt в конце
        $last = end($command);
        self::assertStringContainsString('[Задача]: simple task', $last);
    }
}
