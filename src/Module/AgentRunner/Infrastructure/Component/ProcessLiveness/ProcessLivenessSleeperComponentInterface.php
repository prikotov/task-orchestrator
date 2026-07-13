<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness;

/**
 * Ожидание между итерациями liveness-политики.
 */
interface ProcessLivenessSleeperComponentInterface
{
    /**
     * Приостанавливает исполнение на заданное число микросекунд.
     */
    public function sleep(int $microseconds): void;
}
