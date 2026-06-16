<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity\DynamicLoopExecution;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicBudgetCheckVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicRoundResultVo;

/**
 * Контрактный тест: {@see DynamicLoopExecution::toLoopResultVo()} собирает
 * DynamicLoopResultVo byte-to-byte из metrics (recordRound через getMetrics())
 * и result-state полей aggregate.
 *
 * Закрывает рекомендацию ADR Локи 3.5 / чек-листа Левша п.7: изолированный
 * unit-level контракт на cross-component поток metrics → toLoopResultVo.
 */
#[CoversClass(DynamicLoopExecution::class)]
final class DynamicLoopExecutionResultMappingTest extends TestCase
{
    #[Test]
    public function toLoopResultVoMapsAllFieldsByteToByteAfterRecordRoundViaMetrics(): void
    {
        $execution = new DynamicLoopExecution();
        $roundA = self::createRound(1, 'facilitator', true, 100, 50, 0.5, 2.5);
        $roundB = self::createRound(2, 'participant_a', false, 400, 200, 0.25, 3.0);

        // Write-side через owned components (новый контракт редизайна).
        $execution->getMetrics()->recordRound($roundA);
        $execution->getMetrics()->recordRound($roundB);

        // Result-state через aggregate.
        $execution->setSynthesis('Final synthesis');
        $execution->markMaxRoundsReached(true);
        $execution->setInterruptionReason(null);
        $execution->setBudgetBreak(new DynamicBudgetCheckVo(
            budgetExceeded: true,
            budgetLimit: 5.0,
            budgetExceededRole: 'facilitator',
        ));
        $execution->markMaxTimeExceeded();

        $result = $execution->toLoopResultVo();

        // roundResults: тот же список, тот же порядок (из metrics).
        self::assertCount(2, $result->roundResults);
        self::assertSame([$roundA, $roundB], $result->roundResults);

        // Totals: аккумулированы из recordRound (roundA + roundB).
        self::assertSame(5.5, $result->totalTime);          // 2.5 + 3.0
        self::assertSame(500, $result->totalInputTokens);   // 100 + 400
        self::assertSame(250, $result->totalOutputTokens);  // 50 + 200
        self::assertSame(0.75, $result->totalCost);         // 0.5 + 0.25

        // Result-state aggregate (без изменений).
        self::assertSame('Final synthesis', $result->synthesis);
        self::assertTrue($result->maxRoundsReached);
        self::assertNull($result->interruptionReason);

        // Budget break mapping (с fallback-семантикой nullsafe).
        self::assertTrue($result->budgetExceeded);
        self::assertSame(5.0, $result->budgetLimit);
        self::assertSame('facilitator', $result->budgetExceededRole);

        self::assertTrue($result->maxTimeExceeded);
    }

    #[Test]
    public function toLoopResultVoUsesFallbacksWhenBudgetBreakIsNull(): void
    {
        // Без setBudgetBreak — budget-поля берут дефолты (?? false / ?? 0.0 / ?-> null).
        $execution = new DynamicLoopExecution();

        $result = $execution->toLoopResultVo();

        self::assertFalse($result->budgetExceeded);
        self::assertSame(0.0, $result->budgetLimit);
        self::assertNull($result->budgetExceededRole);
    }

    private static function createRound(
        int $round,
        string $role,
        bool $isFacilitator,
        int $inputTokens,
        int $outputTokens,
        float $cost,
        float $duration,
    ): DynamicRoundResultVo {
        return new DynamicRoundResultVo(
            round: $round,
            role: $role,
            isFacilitator: $isFacilitator,
            outputText: 'text',
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $cost,
            duration: $duration,
        );
    }
}
