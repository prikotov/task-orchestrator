<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Domain\Entity;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Entity\DynamicLoopExecution;
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
}
