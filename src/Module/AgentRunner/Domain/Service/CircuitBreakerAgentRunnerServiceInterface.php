<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Domain\Service;

/**
 * Интерфейс Circuit Breaker декоратора AgentRunner.
 */
interface CircuitBreakerAgentRunnerServiceInterface extends AgentRunnerInterface
{
    /**
     * Возвращает текущее состояние circuit breaker для runner'а.
     */
    public function getCircuitState(string $runnerName): \TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\CircuitBreakerStateVo;
}
