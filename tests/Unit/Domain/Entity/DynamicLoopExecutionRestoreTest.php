<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity\DynamicLoopExecution;

/**
 * Контрактный тест восстановления state dynamic-цикла из rounds[] session.json.
 *
 * restoreFromRoundFiles() при resume должен восстанавливать не только счётчики
 * (round, participantRounds), но и полный список раундов с метриками: totals,
 * roleCosts и round results — чтобы отчёт «Participants metrics» покрывал всю
 * сессию. Старые сессии без модельных полей восстанавливаются без ошибок:
 * model/provider/thinking = null, кэш-токены = 0.
 */
#[CoversClass(DynamicLoopExecution::class)]
final class DynamicLoopExecutionRestoreTest extends TestCase
{
    #[Test]
    public function restoreRebuildsRoundResultsTotalsAndCounters(): void
    {
        $execution = new DynamicLoopExecution(startRound: 2);

        $execution->restoreFromRoundFiles([
            [
                'round' => 1,
                'role' => 'team_lead',
                'is_facilitator' => true,
                'duration' => 8.0,
                'input_tokens' => 1000,
                'output_tokens' => 500,
                'cost' => 0.02,
                'model' => 'glm-5.3-flash',
                'provider' => 'zai',
                'thinking' => 'high',
                'cache_read_tokens' => 4000,
                'cache_write_tokens' => 500,
            ],
            [
                'round' => 1,
                'role' => 'backend_developer',
                'is_facilitator' => false,
                'duration' => 12.0,
                'input_tokens' => 2000,
                'output_tokens' => 1500,
                'cost' => 0.05,
                'model' => 'mimo-v2.6-pro',
                'provider' => 'xiaomi-token-plan-sgp',
                'thinking' => 'off',
                'cache_read_tokens' => 6000,
                'cache_write_tokens' => 300,
            ],
        ]);

        // Round results: полный список с модельными полями.
        $rounds = $execution->getRoundResults();
        self::assertCount(2, $rounds);
        self::assertSame('team_lead', $rounds[0]->getRole());
        self::assertTrue($rounds[0]->isFacilitator());
        self::assertSame('glm-5.3-flash', $rounds[0]->getModel());
        self::assertSame('zai', $rounds[0]->getProvider());
        self::assertSame('high', $rounds[0]->getThinking());
        self::assertSame(4000, $rounds[0]->getCacheReadTokens());
        self::assertSame('mimo-v2.6-pro', $rounds[1]->getModel());
        self::assertFalse($rounds[1]->isFacilitator());

        // Totals: вся сессия (пост-resume раунды лягут сверху).
        $totals = $execution->getTotals();
        self::assertSame(20.0, $totals['time']);
        self::assertSame(3000, $totals['in']);
        self::assertSame(2000, $totals['out']);
        self::assertSame(0.07, $totals['cost']);

        // Role costs: бюджетные проверки считают расходы до resume.
        self::assertSame(0.02, $execution->getRoleCosts()['team_lead']);
        self::assertSame(0.05, $execution->getRoleCosts()['backend_developer']);

        // Счётчики: max round и число participant-шагов.
        self::assertSame(1, $execution->getRound());
        self::assertSame(1, $execution->getParticipantRounds());
    }

    #[Test]
    public function restoreOldSessionWithoutModelFieldsYieldsNullModelAndZeroCache(): void
    {
        $execution = new DynamicLoopExecution(startRound: 3);

        $execution->restoreFromRoundFiles([
            [
                'round' => 2,
                'role' => 'system_analyst',
                'is_facilitator' => false,
                'duration' => 5.0,
                'input_tokens' => 800,
                'output_tokens' => 400,
                'cost' => 0.01,
            ],
        ]);

        $rounds = $execution->getRoundResults();
        self::assertCount(1, $rounds);
        self::assertNull($rounds[0]->getModel());
        self::assertNull($rounds[0]->getProvider());
        self::assertNull($rounds[0]->getThinking());
        self::assertSame(0, $rounds[0]->getCacheReadTokens());
        self::assertSame(0, $rounds[0]->getCacheWriteTokens());

        // Totals и roleCosts восстановлены и без модельных полей.
        $totals = $execution->getTotals();
        self::assertSame(5.0, $totals['time']);
        self::assertSame(0.01, $execution->getRoleCosts()['system_analyst']);
    }

    #[Test]
    public function restoreAccumulatesAcrossCallsKeepingLatestRoundCounter(): void
    {
        $execution = new DynamicLoopExecution(startRound: 2);

        $execution->restoreFromRoundFiles([
            ['round' => 1, 'role' => 'team_lead', 'is_facilitator' => true, 'cost' => 0.01, 'duration' => 1.0],
        ]);
        $execution->restoreFromRoundFiles([
            ['round' => 2, 'role' => 'team_lead', 'is_facilitator' => true, 'cost' => 0.02, 'duration' => 2.0],
            ['round' => 2, 'role' => 'developer', 'is_facilitator' => false, 'cost' => 0.03, 'duration' => 3.0],
        ]);

        self::assertCount(3, $execution->getRoundResults());
        self::assertSame(2, $execution->getRound());
        self::assertSame(6.0, $execution->getTotals()['time']);
    }
}
