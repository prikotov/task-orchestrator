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

        self::assertSame(1, $roundVo->round);
        self::assertSame('role', $roundVo->role);
        self::assertTrue($roundVo->isFacilitator);
        self::assertSame('Hello', $roundVo->outputText);
        self::assertSame(100, $roundVo->inputTokens);
        self::assertSame(50, $roundVo->outputTokens);
        self::assertSame(7000, $roundVo->cacheReadTokens);
        self::assertSame(900, $roundVo->cacheWriteTokens);
        self::assertSame(0.05, $roundVo->cost);
        self::assertSame(2.5, $roundVo->duration);
        self::assertFalse($roundVo->isError);
        self::assertSame('inv', $roundVo->invocation);
        self::assertSame('system', $roundVo->systemPrompt);
        self::assertSame('prompt', $roundVo->userPrompt);
        self::assertSame('glm-5.3-flash', $roundVo->model);
        self::assertSame('zai', $roundVo->provider);
        self::assertSame('high', $roundVo->thinking);
    }

    #[Test]
    public function fromTurnResultDefaultsThinkingToNull(): void
    {
        $turnResult = new DynamicLoopTurnResultVo(
            agentResult: DynamicLoopRunResultVo::createSuccess(outputText: 'text'),
            duration: 1.0,
        );

        $roundVo = DynamicRoundResultHelper::fromTurnResult($turnResult, 2, 'architect', false);

        self::assertSame(2, $roundVo->round);
        self::assertSame('architect', $roundVo->role);
        self::assertFalse($roundVo->isFacilitator);
        self::assertNull($roundVo->thinking);
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

        self::assertTrue($normalized->agentResult->isError());
        self::assertSame('Agent returned empty output.', $normalized->agentResult->getErrorMessage());
        self::assertSame(1.5, $normalized->duration);
        self::assertSame('p', $normalized->userPrompt);
        self::assertSame('s', $normalized->systemPrompt);
        self::assertSame('inv', $normalized->invocation);
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
        self::assertFalse($normalized->agentResult->isError());
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
        self::assertSame('boom', $normalized->agentResult->getErrorMessage());
    }
}
