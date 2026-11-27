<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\DynamicLoop\Domain\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Helper\DynamicRoundResultHelper;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopRunResultVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopTurnResultVo;

#[CoversClass(DynamicRoundResultHelper::class)]
final class DynamicRoundResultHelperTest extends TestCase
{
    // ─── fromTurnResult ───────────────────────────────────────────────

    #[Test]
    public function fromTurnResultMapsAllFields(): void
    {
        $agentResult = DynamicLoopRunResultVo::createSuccess(
            outputText: 'Hello',
            inputTokens: 100,
            outputTokens: 50,
            cacheReadTokens: 7000,
            cacheWriteTokens: 900,
            cost: 0.05,
            model: 'glm-5.3-flash',
            provider: 'zai',
        );
        $turnResult = new DynamicLoopTurnResultVo(
            agentResult: $agentResult,
            duration: 2.5,
            userPrompt: 'prompt',
            systemPrompt: 'system',
            invocation: 'inv',
        );

        $roundVo = DynamicRoundResultHelper::fromTurnResult($turnResult, 1, 'role', true, 'high');

        self::assertSame(1, $roundVo->getRound());
        self::assertSame('role', $roundVo->getRole());
        self::assertTrue($roundVo->isFacilitator());
        self::assertSame('Hello', $roundVo->getOutputText());
        self::assertSame(100, $roundVo->getInputTokens());
        self::assertSame(50, $roundVo->getOutputTokens());
        self::assertSame(7000, $roundVo->getCacheReadTokens());
        self::assertSame(900, $roundVo->getCacheWriteTokens());
        self::assertSame(0.05, $roundVo->getCost());
        self::assertSame(2.5, $roundVo->getDuration());
        self::assertFalse($roundVo->isError());
        self::assertSame('inv', $roundVo->getInvocation());
        self::assertSame('system', $roundVo->getSystemPrompt());
        self::assertSame('prompt', $roundVo->getUserPrompt());
        self::assertSame('glm-5.3-flash', $roundVo->getModel());
        self::assertSame('zai', $roundVo->getProvider());
        self::assertSame('high', $roundVo->getThinking());
    }

    #[Test]
    public function fromTurnResultDefaultsThinkingToNull(): void
    {
        $turnResult = new DynamicLoopTurnResultVo(
            agentResult: DynamicLoopRunResultVo::createSuccess(outputText: 'text'),
            duration: 1.0,
        );

        $roundVo = DynamicRoundResultHelper::fromTurnResult($turnResult, 2, 'architect', false);

        self::assertSame(2, $roundVo->getRound());
        self::assertSame('architect', $roundVo->getRole());
        self::assertFalse($roundVo->isFacilitator());
        self::assertNull($roundVo->getThinking());
    }

    // ─── normalizeEmptyOutput ─────────────────────────────────────────

    #[Test]
    public function normalizeEmptyOutputReplacesBlankSuccessWithError(): void
    {
        $turnResult = new DynamicLoopTurnResultVo(
            agentResult: DynamicLoopRunResultVo::createSuccess(outputText: "   \n  "),
            duration: 1.5,
            userPrompt: 'p',
            systemPrompt: 's',
            invocation: 'inv',
        );

        $normalized = DynamicRoundResultHelper::normalizeEmptyOutput($turnResult);

        self::assertTrue($normalized->getAgentResult()->isError());
        self::assertSame('Agent returned empty output.', $normalized->getAgentResult()->getErrorMessage());
        self::assertSame(1.5, $normalized->getDuration());
        self::assertSame('p', $normalized->getUserPrompt());
        self::assertSame('s', $normalized->getSystemPrompt());
        self::assertSame('inv', $normalized->getInvocation());
    }

    #[Test]
    public function normalizeEmptyOutputKeepsNonEmptySuccess(): void
    {
        $turnResult = new DynamicLoopTurnResultVo(
            agentResult: DynamicLoopRunResultVo::createSuccess(outputText: 'real answer'),
            duration: 1.5,
        );

        $normalized = DynamicRoundResultHelper::normalizeEmptyOutput($turnResult);

        self::assertSame($turnResult, $normalized);
        self::assertFalse($normalized->getAgentResult()->isError());
    }

    #[Test]
    public function normalizeEmptyOutputKeepsErrorResult(): void
    {
        $turnResult = new DynamicLoopTurnResultVo(
            agentResult: DynamicLoopRunResultVo::createError('boom'),
            duration: 1.0,
        );

        $normalized = DynamicRoundResultHelper::normalizeEmptyOutput($turnResult);

        self::assertSame($turnResult, $normalized);
        self::assertSame('boom', $normalized->getAgentResult()->getErrorMessage());
    }
}
