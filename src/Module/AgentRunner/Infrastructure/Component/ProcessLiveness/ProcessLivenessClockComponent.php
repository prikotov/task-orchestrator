<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness;

use Override;

/**
 * Системный монотонный источник времени на основе hrtime().
 */
final readonly class ProcessLivenessClockComponent implements ProcessLivenessClockComponentInterface
{
    #[Override]
    public function now(): float
    {
        return (float) hrtime(true) / 1_000_000_000.0;
    }
}
