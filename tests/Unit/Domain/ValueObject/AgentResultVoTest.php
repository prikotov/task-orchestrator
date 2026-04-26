<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;

#[CoversClass(AgentResultVo::class)]
final class AgentResultVoTest extends TestCase
{
    // ─── createFromSuccess: полная проверка конструирования ────────────────

    #[Test]
    public function fromSuccessCreatesCorrectVo(): void
    {
        $vo = AgentResultVo::createFromSuccess(
            outputText: 'Hello!',
            inputTokens: 100,
            outputTokens: 50,
            cacheReadTokens: 10,
            cacheWriteTokens: 5,
            cost: 0.01,
            model: 'claude-3.5-sonnet',
            turns: 2,
        );

        self::assertSame('Hello!', $vo->getOutputText());
        self::assertSame(100, $vo->getInputTokens());
        self::assertSame(50, $vo->getOutputTokens());
        self::assertSame(10, $vo->getCacheReadTokens());
        self::assertSame(5, $vo->getCacheWriteTokens());
        self::assertSame(0.01, $vo->getCost());
        self::assertSame(0, $vo->getExitCode());
        self::assertSame('claude-3.5-sonnet', $vo->getModel());
        self::assertSame(2, $vo->getTurns());
        self::assertFalse($vo->isError());
        self::assertNull($vo->getErrorMessage());
    }

    #[Test]
    public function fromSuccessWithDefaults(): void
    {
        $vo = AgentResultVo::createFromSuccess(outputText: 'Result');

        self::assertSame('Result', $vo->getOutputText());
        self::assertSame(0, $vo->getInputTokens());
        self::assertSame(0, $vo->getOutputTokens());
        self::assertSame(0.0, $vo->getCost());
        self::assertNull($vo->getModel());
        self::assertFalse($vo->isError());
    }

    #[Test]
    public function fromSuccessHasTimedOutFalse(): void
    {
        $vo = AgentResultVo::createFromSuccess(outputText: 'OK');

        self::assertFalse($vo->isTimedOut());
    }

    // ─── createFromError: полная проверка конструирования ──────────────────

    #[Test]
    public function fromErrorCreatesCorrectVo(): void
    {
        $vo = AgentResultVo::createFromError(
            errorMessage: 'Something went wrong',
            exitCode: 1,
        );

        self::assertSame('', $vo->getOutputText());
        self::assertSame(1, $vo->getExitCode());
        self::assertTrue($vo->isError());
        self::assertSame('Something went wrong', $vo->getErrorMessage());
        self::assertSame(0, $vo->getInputTokens());
        self::assertSame(0.0, $vo->getCost());
    }

    #[Test]
    public function fromErrorDefaultHasTimedOutFalse(): void
    {
        $vo = AgentResultVo::createFromError(errorMessage: 'Some error');

        self::assertFalse($vo->isTimedOut());
        self::assertTrue($vo->isError());
    }

    #[Test]
    public function fromErrorWithTimedOutTrue(): void
    {
        $vo = AgentResultVo::createFromError(
            errorMessage: 'Agent timed out after 30 seconds.',
            timedOut: true,
        );

        self::assertTrue($vo->isTimedOut());
        self::assertTrue($vo->isError());
        self::assertSame('Agent timed out after 30 seconds.', $vo->getErrorMessage());
    }

    #[Test]
    public function fromErrorWithCustomExitCodeAndTimedOut(): void
    {
        $vo = AgentResultVo::createFromError(
            errorMessage: 'Timeout',
            exitCode: 124,
            timedOut: true,
        );

        self::assertTrue($vo->isTimedOut());
        self::assertSame(124, $vo->getExitCode());
    }
}
