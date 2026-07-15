<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness;

use Override;

/**
 * Системное ожидание между итерациями liveness-политики.
 */
final readonly class ProcessLivenessSleeperComponent implements ProcessLivenessSleeperComponentInterface
{
    #[Override]
    public function sleep(int $microseconds): void
    {
        usleep($microseconds);
    }
}
