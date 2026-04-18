<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\AgentRunRequestVo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgentRunRequestVo::class)]
final class AgentRunRequestVoTest extends TestCase
{
    #[Test]
    public function itCreatesWithDefaults(): void
    {
        $vo = new AgentRunRequestVo(
            role: 'system_analyst',
            task: 'Analyze the codebase',
        );

        self::assertSame('system_analyst', $vo->getRole());
        self::assertSame('Analyze the codebase', $vo->getTask());
        self::assertNull($vo->getSystemPrompt());
        self::assertNull($vo->getPreviousContext());
        self::assertNull($vo->getModel());
        self::assertNull($vo->getTools());
        self::assertNull($vo->getWorkingDir());
        self::assertSame(300, $vo->getTimeout());
        self::assertSame(50000, $vo->getMaxContextLength());
    }

    #[Test]
    public function itCreatesWithAllParameters(): void
    {
        $vo = new AgentRunRequestVo(
            role: 'backend_developer',
            task: 'Implement feature',
            systemPrompt: 'You are a backend developer.',
            previousContext: 'Some context',
            model: 'claude-3.5-sonnet',
            tools: 'read,write',
            workingDir: '/tmp/work',
            timeout: 600,
            maxContextLength: 100000,
        );

        self::assertSame('backend_developer', $vo->getRole());
        self::assertSame('You are a backend developer.', $vo->getSystemPrompt());
        self::assertSame('claude-3.5-sonnet', $vo->getModel());
        self::assertSame('read,write', $vo->getTools());
        self::assertSame('/tmp/work', $vo->getWorkingDir());
        self::assertSame(600, $vo->getTimeout());
        self::assertSame(100000, $vo->getMaxContextLength());
    }

    #[Test]
    public function withTruncatedContextReturnsSameWhenNoContext(): void
    {
        $vo = new AgentRunRequestVo(role: 'test', task: 'test');
        $result = $vo->withTruncatedContext();

        self::assertSame($vo, $result);
    }

    #[Test]
    public function withTruncatedContextReturnsSameWhenShortEnough(): void
    {
        $vo = new AgentRunRequestVo(
            role: 'test',
            task: 'test',
            previousContext: str_repeat('a', 100),
            maxContextLength: 200,
        );
        $result = $vo->withTruncatedContext();

        self::assertSame($vo, $result);
    }

    #[Test]
    public function withTruncatedContextTruncatesWhenTooLong(): void
    {
        $context = str_repeat('a', 1000);
        $vo = new AgentRunRequestVo(
            role: 'test',
            task: 'test',
            previousContext: $context,
            maxContextLength: 500,
        );
        $result = $vo->withTruncatedContext();

        self::assertNotSame($vo, $result);
        self::assertSame(500, strlen($result->getPreviousContext()));
        self::assertSame(substr($context, -500), $result->getPreviousContext());
        self::assertSame('test', $result->getRole());
        self::assertSame('test', $result->getTask());
        self::assertNull($result->getSystemPrompt());
    }

    #[Test]
    public function withTruncatedContextPreservesSystemPrompt(): void
    {
        $vo = new AgentRunRequestVo(
            role: 'test',
            task: 'test',
            systemPrompt: 'System prompt',
            previousContext: str_repeat('x', 1000),
            maxContextLength: 500,
        );
        $result = $vo->withTruncatedContext();

        self::assertSame('System prompt', $result->getSystemPrompt());
    }
}
