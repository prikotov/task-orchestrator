<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Integration;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;

/**
 * Интеграционный сервис запуска AI-агента для Orchestrator Domain.
 *
 * Инкапсулирует вызов агента и retry-политику.
 * Реализация маппит VO и делегирует в конкретный движок AI-агента.
 */
interface RunAgentServiceInterface
{
    /**
     * Запускает агент с заданным запросом и опциональной retry-политикой.
     *
     * Runner name передаётся через ChainRunRequestVo::getRunnerName().
     * Retry инкапсулирован внутри реализации: вызывающая сторона
     * не знает о RetryableRunnerFactory.
     */
    public function run(ChainRunRequestVo $request, ?ExecutionRetryPolicyVo $retryPolicy = null): ChainRunResultVo;
}
