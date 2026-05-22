<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Domain\Service;

use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\CircuitBreakerStateVo;

/**
 * Интерфейс Circuit Breaker декоратора AgentRunner.
 */
interface CircuitBreakerAgentRunnerServiceInterface extends AgentRunnerInterface
{
    /**
     * Возвращает текущее состояние circuit breaker для runner'а.
     */
    public function getCircuitState(string $runnerName): CircuitBreakerStateVo;
}
