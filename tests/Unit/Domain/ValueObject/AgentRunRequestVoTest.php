<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
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
    public function itDefaultsNoContextFilesToFalse(): void
    {
        $vo = new AgentRunRequestVo(role: 'test', task: 'test');

        self::assertFalse($vo->hasNoContextFiles());
    }
}
