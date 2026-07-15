<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness;

/**
 * Монотонный источник времени для политики liveness.
 */
interface ProcessLivenessClockComponentInterface
{
    /**
     * Возвращает монотонное время в секундах.
     */
    public function now(): float;
}
