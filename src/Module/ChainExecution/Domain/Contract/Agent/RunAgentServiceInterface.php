<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Contract\Agent;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ChainRunResultVo;
use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\ExecutionRetryPolicyVo;

/**
 * Интеграционный порт запуска AI-агента для Orchestrator Domain.
 *
 * Инкапсулирует вызов агента и retry-политику.
 * Реализация маппит VO и делегирует в конкретный движок AI-агента.
 *
 * Расположен в Contract (а не Service), чтобы ServiceContractDependencyRule
 * не считал его cross-module сервисом при реализации в других модулях (Port/Adapter).
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
