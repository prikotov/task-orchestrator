<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\StaticExecution\Domain\Service;

use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRunResultVo;

/**
 * Интеграционный сервис запуска AI-агента для StaticExecution Domain.
 *
 * Делегирует вызов в Orchestrator\RunAgentServiceInterface.
 * VO общие между модулями — mapper не нужен.
 */
interface RunAgentServiceInterface
{
    /**
     * Запускает агент с заданным запросом и опциональной retry-политикой.
     */
    public function run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo;
}
