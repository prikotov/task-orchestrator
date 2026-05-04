<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Entity;

use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Entity\DynamicLoopExecution;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DynamicLoopExecution::class)]
final class DynamicLoopExecutionMaxTimeTest extends TestCase
{
    #[Test]
    public function maxTimeExceededDefaultsToFalse(): void
    {
        $execution = new DynamicLoopExecution();

        self::assertFalse($execution->isMaxTimeExceeded());
    }

    #[Test]
    public function markMaxTimeExceededSetsFlag(): void
    {
        $execution = new DynamicLoopExecution();
        $execution->markMaxTimeExceeded();

        self::assertTrue($execution->isMaxTimeExceeded());
    }

    #[Test]
    public function maxTimeExceededIncludedInLoopResultVo(): void
    {
        $execution = new DynamicLoopExecution();
        $execution->markMaxTimeExceeded();

        $result = $execution->toLoopResultVo();

        self::assertTrue($result->maxTimeExceeded);
    }

    #[Test]
    public function maxTimeNotExceededInLoopResultVoByDefault(): void
    {
        $execution = new DynamicLoopExecution();

        $result = $execution->toLoopResultVo();

        self::assertFalse($result->maxTimeExceeded);
    }

    #[Test]
    public function maxTimeExceededTriggersFinalizeInResultVoWhenNoSynthesis(): void
    {
        // Сценарий: maxTimeExceeded = true, synthesis = null
        // → RunDynamicLoopService должен вызвать finalize, установив synthesis
        // Проверяем, что флаг maxTimeExceeded корректно передаётся в результат
        $execution = new DynamicLoopExecution();
        $execution->markMaxTimeExceeded();

        // Simulate: finalize was called and set synthesis
        $execution->setSynthesis('Final synthesis from reserved time');

        $result = $execution->toLoopResultVo();

        self::assertTrue($result->maxTimeExceeded);
        self::assertSame('Final synthesis from reserved time', $result->synthesis);
    }

    #[Test]
    public function facilitatorJournalRecordsReserveReason(): void
    {
        // Проверяем, что журнал фиксирует причину остановки
        $execution = new DynamicLoopExecution(
            initialFacilitatorJournal: 'Initial journal\n',
        );
        $execution->markMaxTimeExceeded();
        $execution->appendFacilitatorJournal(
            '[2026-04-27 12:00] Дискуссия остановлена: резервирование времени на синтез (reserve=360s)\n',
        );

        self::assertStringContainsString(
            'резервирование времени на синтез',
            $execution->getFacilitatorJournal(),
        );
    }
}
