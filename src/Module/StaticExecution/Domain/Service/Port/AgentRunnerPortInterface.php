<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\Port;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainRunResultVo;

/**
 * Port: запуск AI-агента из модуля StaticExecution.
 *
 * ACL-интерфейс — изолирует StaticExecution Domain от Orchestrator Integration-порта.
 * Адаптер в StaticExecution\Integration делегирует в Orchestrator\RunAgentServiceInterface.
 */
interface AgentRunnerPortInterface
{
    /**
     * Запускает агент с заданным запросом и опциональной retry-политикой.
     */
    public function run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo;
}
