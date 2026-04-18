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
    #[Test]
    public function getNameReturnsPi(): void
    {
        $parser = new PiJsonlParser();
        $runner = new PiAgentRunner($parser);

        self::assertSame('pi', $runner->getName());
    }

    #[Test]
    public function isAvailableReturnsBool(): void
    {
        $parser = new PiJsonlParser();
        $runner = new PiAgentRunner($parser);

        self::assertIsBool($runner->isAvailable());
    }

    #[Test]
    public function requestAcceptsSystemPromptAndContext(): void
    {
        // Verify AgentRunRequestVo constructor accepts systemPrompt
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
