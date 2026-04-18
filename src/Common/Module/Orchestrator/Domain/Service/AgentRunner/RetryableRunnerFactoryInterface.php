<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\AgentRunner;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\RetryPolicyVo;

/**
 * Фабрика для создания retry-декоратора поверх AgentRunnerInterface.
 *
 * Позволяет Application-слою получать обёрнутый runner без прямого
 * зависания от Infrastructure-реализации RetryingAgentRunner.
 */
interface RetryableRunnerFactoryInterface
{
    /**
     * Оборачивает runner в retry-декоратор с заданной политикой.
     *
     * Если retryPolicy отключена (maxRetries=0) — может вернуть исходный runner.
     */
    public function createRetryableRunner(
        AgentRunnerInterface $runner,
        RetryPolicyVo $retryPolicy,
    ): AgentRunnerInterface;
}
