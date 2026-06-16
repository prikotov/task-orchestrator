<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity\DynamicLoopMetrics;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicRoundResultVo;

#[CoversClass(DynamicLoopMetrics::class)]
final class DynamicLoopMetricsTest extends TestCase
{
    #[Test]
    public function defaultsAreZeroAndEmpty(): void
    {
        $metrics = new DynamicLoopMetrics();

        self::assertSame([], $metrics->getRoundResults());
        self::assertSame(['time' => 0.0, 'in' => 0, 'out' => 0, 'cost' => 0.0], $metrics->getTotals());
        self::assertSame([], $metrics->getRoleCosts());
        self::assertSame(0.0, $metrics->getTotalCost());
    }

    #[Test]
    public function recordRoundAppendsResultAndAccumulatesTotals(): void
    {
        $metrics = new DynamicLoopMetrics();
        $round = self::createRound(1, 10, 20, 0.5, 1.2);

        $metrics->recordRound($round);

        self::assertCount(1, $metrics->getRoundResults());
        self::assertSame($round, $metrics->getRoundResults()[0]);
        self::assertSame(['time' => 1.2, 'in' => 10, 'out' => 20, 'cost' => 0.5], $metrics->getTotals());
        self::assertSame(0.5, $metrics->getTotalCost());
    }

    #[Test]
    public function multipleRecordRoundsPreserveOrderAndSumTotals(): void
    {
        $metrics = new DynamicLoopMetrics();
        $roundA = self::createRound(1, 100, 200, 1.0, 2.5);
        $roundB = self::createRound(2, 30, 40, 0.5, 1.5);
        $roundC = self::createRound(3, 5, 7, 0.25, 0.5);

        $metrics->recordRound($roundA);
        $metrics->recordRound($roundB);
        $metrics->recordRound($roundC);

        $results = $metrics->getRoundResults();
        self::assertCount(3, $results);
        self::assertSame([$roundA, $roundB, $roundC], $results);

        self::assertSame(['time' => 4.5, 'in' => 135, 'out' => 247, 'cost' => 1.75], $metrics->getTotals());
    }

    #[Test]
    public function addRoleCostAccumulatesPerRole(): void
    {
        $metrics = new DynamicLoopMetrics();

        $metrics->addRoleCost('facilitator', 1.5);
        $metrics->addRoleCost('participant_a', 0.3);
        $metrics->addRoleCost('facilitator', 2.5);

        self::assertSame(
            ['facilitator' => 4.0, 'participant_a' => 0.3],
            $metrics->getRoleCosts(),
        );
    }

    #[Test]
    public function getTotalsReturnsExactKeyOrderTimeInOutCost(): void
    {
        $metrics = new DynamicLoopMetrics();
        $metrics->recordRound(self::createRound(1, 10, 20, 0.5, 1.2));

        self::assertSame(
            ['time', 'in', 'out', 'cost'],
            array_keys($metrics->getTotals()),
        );
    }

    private static function createRound(
        int $round,
        int $inputTokens,
        int $outputTokens,
        float $cost,
        float $duration,
    ): DynamicRoundResultVo {
        return new DynamicRoundResultVo(
            round: $round,
            role: 'facilitator',
            isFacilitator: true,
            outputText: 'text',
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $cost,
            duration: $duration,
        );
    }
}
