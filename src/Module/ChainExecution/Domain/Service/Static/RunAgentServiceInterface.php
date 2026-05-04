<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Static;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRetryPolicyVo;

/**
 * Интеграционный сервис запуска AI-агента для static execution.
 */
interface RunAgentServiceInterface
{
    /**
     * Запускает агент с заданным запросом и опциональной retry-политикой.
     */
    public function run(ChainRunRequestVo $request, ?ExecutionRetryPolicyVo $retryPolicy = null): ChainRunResultVo;
}
